<?php

declare(strict_types=1);

/**
 * dialog_baustein.php — Lern-Baustein im Dialog-Ablauf anlegen.
 *
 * Eigener Ablauf wie beim Quiz, nur mit Musterdialog:
 *   1. Musterdialog aufzeichnen – Blasen wie im echten Chat, Text und Formel
 *      getrennt (das ist das Alleinstellungsmerkmal).
 *   2. Thema / Klasse / Hinweise → Prompt von der KI erzeugen lassen.
 *   3. Prüfen, speichern, danach im Baustein-Editor weiterarbeiten.
 *
 * Aufruf: dialog_baustein.php · ?von=slug (Musterdialog aus Baustein laden)
 */

require __DIR__ . '/inc/config.php';
require __DIR__ . '/inc/chat.php';
require __DIR__ . '/inc/auth.php';
require __DIR__ . '/inc/verwaltung.php';
require __DIR__ . '/inc/ki.php';

auth_starten();
verwaltung_erzwingen();

$titel    = '';
$thema    = '';
$klasse   = '';
$hinweise = '';
$dialog   = '';
$prompt   = '';
$slug     = '';
$fehler   = [];
$meldung  = '';

/** Je eine Referenzaufgabe pro Stufe (Text | Formel) – den Rest erfindet die KI. */
$aufgaben = ['leicht' => '', 'mittel' => '', 'schwer' => ''];

/* ---- Vorhandenen Baustein öffnen (?slug=… oder ?von=…) ------------------ */

$oeffnen    = trim((string) ($_GET['slug'] ?? $_GET['von'] ?? ''));
$bearbeiten = '';               // Slug des geöffneten Bausteins, '' = neuer Baustein

if ($oeffnen !== '' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    $vorlage = baustein_laden($oeffnen);

    if ($vorlage === null) {
        $fehler[] = 'Baustein „' . $oeffnen . '“ gibt es nicht.';
    } elseif (($vorlage['art'] ?? 'lern') !== 'lern') {
        $fehler[] = '„' . $oeffnen . '“ ist ein Quiz – dafür gibt es den Quiz-Editor.';
    } else {
        $bearbeiten = $oeffnen;
        $slug       = $oeffnen;
        $titel      = (string) ($vorlage['titel'] ?? '');
        $thema      = (string) ($vorlage['thema'] ?? '');
        $prompt     = (string) ($vorlage['prompt'] ?? '');
        $dialog     = dialog_aus_baustein($vorlage);

        /* „Thema (Klasse 6)" wieder in zwei Felder trennen */
        if (preg_match('/^(.*?)\s*\(Klasse\s*([^)]*)\)$/u', $thema, $treffer) === 1) {
            $thema  = trim($treffer[1]);
            $klasse = trim($treffer[2]);
        }

        foreach (array_keys($aufgaben) as $stufe) {
            $zeilen = [];

            foreach ($vorlage['aufgaben'][$stufe] ?? [] as $a) {
                $zeilen[] = trim((string) ($a['text'] ?? '')) . ' | ' . trim((string) ($a['formel'] ?? ''));
            }

            $aufgaben[$stufe] = implode("\n", $zeilen);
        }

        $meldung = 'Baustein „' . $titel . '“ geöffnet – alles änderbar, Speichern überschreibt ihn.';
    }
}

/* ---- Absenden ----------------------------------------------------------- */

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $titel    = trim((string) ($_POST['titel'] ?? ''));
    $thema    = trim((string) ($_POST['thema'] ?? ''));
    $klasse   = trim((string) ($_POST['klasse'] ?? ''));
    $hinweise = trim((string) ($_POST['hinweise'] ?? ''));
    $dialog   = trim((string) ($_POST['dialog'] ?? ''));
    $prompt   = (string) ($_POST['prompt'] ?? '');
    $slug     = trim((string) ($_POST['slug'] ?? ''));
    $aktion   = (string) ($_POST['aktion'] ?? '');

    /* Bearbeiten-Modus kommt als verstecktes Feld mit ('' = neuer Baustein). */
    $bearbeiten = trim((string) ($_POST['bearbeiten'] ?? ''));

    foreach (array_keys($aufgaben) as $stufe) {
        $aufgaben[$stufe] = trim((string) ($_POST['aufgaben_' . $stufe] ?? ''));
    }

    if (!csrf_pruefen($_POST['csrf'] ?? null)) {
        $fehler[] = 'Ungültiges Token, bitte Seite neu laden.';
    } elseif ($aktion === 'loeschen') {
        if ($bearbeiten === '') {
            $fehler[] = 'Zum Löschen bitte erst einen Baustein öffnen.';
        } else {
            $weg = baustein_loeschen($bearbeiten);

            if ($weg['fehler'] === []) {
                header(
                    'Location: bausteine.php?geloescht=' . rawurlencode($bearbeiten)
                    . '&schueler=' . (int) $weg['schueler'],
                    true,
                    302
                );
                exit;
            }

            $fehler = $weg['fehler'];
        }
    } elseif ($aktion === 'erzeugen') {
        if ($thema === '') {
            $fehler[] = 'Bitte ein Thema angeben.';
        } elseif ($dialog === '') {
            $fehler[] = 'Bitte zuerst den Musterdialog aufzeichnen.';
        } else {
            $erzeugt = ki_prompt_erzeugen([
                'art'             => 'lern',
                'titel'           => $titel,
                'thema'           => $thema,
                'klasse'          => $klasse,
                'hinweise'        => $hinweise,
                'dialog'          => dialog_huebsch($dialog),
                'aufgaben_leicht' => $aufgaben['leicht'],
                'aufgaben_mittel' => $aufgaben['mittel'],
                'aufgaben_schwer' => $aufgaben['schwer'],
                'fragen'          => '',
                'quiz_thema'      => '',
                'quiz_anzahl'     => 5,
            ]);

            if ($erzeugt === null) {
                $fehler[] = 'Die KI hat nicht geantwortet. Bitte noch einmal versuchen.';
            } else {
                $prompt  = $erzeugt;
                $meldung = 'Prompt erzeugt – prüfen (Formel-Trennung!), dann speichern.';
            }
        }
    } elseif ($aktion === 'speichern') {
        $teile = [];

        if ($titel === '') {
            $teile[] = 'Bitte einen Titel angeben.';
        }

        if (trim($prompt) === '') {
            $teile[] = 'Der Prompt ist leer – bitte erst erzeugen (oder von Hand schreiben).';
        }

        if ($dialog === '') {
            $teile[] = 'Der Musterdialog ist leer.';
        }

        if ($teile !== []) {
            $fehler = $teile;
        } else {
            $ergebnis = baustein_speichern([
                'slug'            => $bearbeiten !== '' ? $bearbeiten : $slug,
                'art'             => 'lern',
                'titel'           => $titel,
                'thema'           => $klasse !== '' ? $thema . ' (Klasse ' . $klasse . ')' : $thema,
                'prompt'          => $prompt,
                'quiz_thema'      => '',
                'quiz_anzahl'     => '',
                'dialog'          => $dialog,
                'aufgaben_leicht' => $aufgaben['leicht'],
                'aufgaben_mittel' => $aufgaben['mittel'],
                'aufgaben_schwer' => $aufgaben['schwer'],
                'fragen'          => '',
            ], $bearbeiten === '');

            if ($ergebnis['fehler'] === []) {
                header('Location: baustein_edit.php?slug=' . rawurlencode($ergebnis['slug']), true, 302);
                exit;
            }

            $fehler = $ergebnis['fehler'];
        }
    }
}

$uebersicht = bausteine_uebersicht();

verwaltung_kopf(
    $bearbeiten !== '' ? 'Baustein bearbeiten' : 'Dialog-Baustein',
    'dialog_baustein.php',
    '<link rel="stylesheet" href="' . e(asset_version('assets/dialog_editor.css')) . '">'
    . '<link rel="stylesheet" href="' . e(asset_version('assets/keyboard2.css')) . '">'
);
?>
<!-- Baustein öffnen: auffällig ganz oben -->
<div class="dialog-kopf">
    <form class="dialog-oeffnen" method="get" action="dialog_baustein.php">
        <label for="oeffnen-slug">Baustein öffnen</label>
        <select id="oeffnen-slug" name="slug">
            <option value="">— neuen Baustein anlegen —</option>
            <?php foreach ($uebersicht as $eintragSlug => $eintrag): ?>
                <?php if (($eintrag['art'] ?? 'lern') !== 'lern') { continue; } ?>
                <option value="<?= e((string) $eintragSlug) ?>"
                    <?= (string) $eintragSlug === $bearbeiten ? 'selected' : '' ?>>
                    <?= e((string) ($eintrag['titel'] ?? $eintragSlug)) ?> (<?= e((string) $eintragSlug) ?>)
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-oeffnen">📂 Öffnen</button>
    </form>

    <p class="dialog-status">
        <?php if ($bearbeiten !== ''): ?>
            Du bearbeitest <strong><?= e($titel) ?></strong> (<?= e($bearbeiten) ?>) – Speichern überschreibt ihn.
            · <a href="dialog_baustein.php">Neuen Baustein anlegen</a>
            · <a href="baustein_edit.php?slug=<?= rawurlencode($bearbeiten) ?>">Erweiterter Editor</a>
        <?php else: ?>
            Neuer Baustein. Oben einen vorhandenen öffnen, um ihn zu ändern.
        <?php endif; ?>
    </p>
</div>
<?php

if ($meldung !== '') {
    echo '<p class="form-notice">' . e($meldung) . '</p>';
}

if ($fehler !== []) {
    echo '<p class="form-error">' . e(implode(' ', $fehler)) . '</p>';
}
?>
<p class="hinweis">
    Erst den <strong>Musterdialog</strong> wie im echten Chat aufzeichnen – Lehrer links, Schüler rechts,
    Worte im Text und Rechnungen getrennt in der Formel. Aus diesem Vorbild erzeugt die KI den Prompt;
    die Trennung Text/Formel wird darin ausdrücklich verlangt.
</p>

<form class="form" method="post" action="dialog_baustein.php" id="dialogForm">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="bearbeiten" value="<?= e($bearbeiten) ?>">

    <div class="schritt">
        <div class="schritt__kopf"><span class="schritt__nummer">1</span><h2>Musterdialog aufzeichnen</h2></div>
        <p class="hinweis">Rolle wählen, Text tippen, Formel im Feld darunter – dann ➤. Jede Blase lässt sich
            bearbeiten, löschen und verschieben. Den Bildpfad nur ausfüllen, wenn ein Bild dazugehört.</p>
    </div>

    <div class="dialog-editor">
        <div class="edit-banner" id="edit-banner" hidden>
            <span>✏️ Zeile wird bearbeitet</span>
            <button type="button" class="edit-cancel" id="edit-cancel">Abbrechen</button>
        </div>

        <div class="bubble-list" id="bubble-list"></div>
        <p class="dialog-zaehler" id="dialog-zaehler"></p>

        <div class="dialog-composer">
            <div class="radio-group" role="radiogroup" aria-label="Rolle">
                <label><input type="radio" name="rolle" value="lehrer" checked><span>Lehrer</span></label>
                <label><input type="radio" name="rolle" value="schueler"><span>Schüler</span></label>
            </div>

            <div class="composer-input-row">
                <button type="button" class="attach-btn" id="dialog-hochladen"
                        title="Bild hochladen (wird als Pfad übernommen)" aria-label="Bild hochladen">📎</button>
                <input id="dialog-text" type="text" maxlength="500" placeholder="Worte …" aria-label="Text">
                <button type="button" class="send-btn" id="dialog-add"
                        title="Zeile hinzufügen" aria-label="Zeile hinzufügen">➤</button>
            </div>

            <input type="file" id="dialog-datei" accept="image/*" hidden>

            <p class="dialog-feld-hinweis">Formel – nur Rechnungen (Worte kommen in das Feld oben)</p>
            <math-field id="dialog-formel" virtual-keyboard-mode="manual"
                        virtual-keyboard-toggle-glyph="none" menu-glyph="none" inputmode="none"
                        aria-label="Formel"></math-field>

            <input type="text" id="dialog-bild" name="bild" maxlength="255"
                   placeholder="Bildpfad (optional) – über 📎 hochladen">

            <div class="dialog-vorschau" id="dialog-vorschau" hidden></div>
            <p class="dialog-upload-status" id="dialog-upload-status"></p>
        </div>

        <div data-tastatur data-text="#dialog-text" data-math="#dialog-formel"></div>

        <details style="margin-top:10px">
            <summary class="hinweis" style="cursor:pointer">Textzeilen (Experten / zum Prüfen)</summary>
            <textarea class="dialog-zeilen" id="dialog" name="dialog"
                      spellcheck="false"><?= e($dialog) ?></textarea>
        </details>
    </div>

    <div class="schritt">
        <div class="schritt__kopf"><span class="schritt__nummer">2</span><h2>Prompt erzeugen</h2></div>
        <p class="hinweis">Thema und Klasse der Übung. Die KI formuliert daraus den System-Prompt –
            mit dem Musterdialog als Vorbild und der Pflicht, Text und Formel getrennt zu halten.</p>
    </div>

    <div class="field">
        <label for="thema">Thema</label>
        <input type="text" id="thema" name="thema" maxlength="255" value="<?= e($thema) ?>"
               placeholder="z. B. Bruchrechnung: Brüche addieren">
    </div>

    <div class="field">
        <label for="titel">Titel</label>
        <input type="text" id="titel" name="titel" maxlength="200" value="<?= e($titel) ?>"
               placeholder="z. B. Brüche addieren">
    </div>

    <div class="field">
        <label for="klasse">Klasse / Stufe</label>
        <input type="text" id="klasse" name="klasse" maxlength="60" value="<?= e($klasse) ?>"
               placeholder="z. B. 6 oder 7/8">
    </div>

    <div class="field">
        <label for="hinweise">Hinweise an die KI (optional)</label>
        <textarea id="hinweise" name="hinweise" style="min-height:70px"
                  placeholder="z. B. immer erst nach dem gemeinsamen Nenner fragen"><?= e($hinweise) ?></textarea>
    </div>

    <p class="hinweis" style="margin-top:14px">
        <strong>Je eine Referenzaufgabe pro Stufe</strong> – Format <code>Aufgabe | Formel</code>
        (Formel darf leer bleiben). Die KI nimmt sie als Niveau und <strong>erfindet die übrigen
        Aufgaben selbst</strong>.
    </p>

    <div class="field">
        <label for="aufgaben_leicht">Aufgabe(n) leicht</label>
        <textarea id="aufgaben_leicht" name="aufgaben_leicht" style="min-height:60px"
                  placeholder="1/4 + 2/4 = ? | \frac{1}{4} + \frac{2}{4}"><?= e($aufgaben['leicht']) ?></textarea>
    </div>

    <div class="field">
        <label for="aufgaben_mittel">Aufgabe(n) mittel</label>
        <textarea id="aufgaben_mittel" name="aufgaben_mittel" style="min-height:60px"
                  placeholder="1/3 + 1/4 = ? | \frac{1}{3} + \frac{1}{4}"><?= e($aufgaben['mittel']) ?></textarea>
    </div>

    <div class="field">
        <label for="aufgaben_schwer">Aufgabe(n) schwer</label>
        <textarea id="aufgaben_schwer" name="aufgaben_schwer" style="min-height:60px"
                  placeholder="2/3 · 3/5 = ? | \frac{2}{3} \cdot \frac{3}{5}"><?= e($aufgaben['schwer']) ?></textarea>
    </div>

    <div class="form-actions">
        <button type="submit" name="aktion" value="erzeugen" class="btn" formnovalidate>
            ✨ Prompt erzeugen
        </button>
    </div>

    <div class="schritt">
        <div class="schritt__kopf"><span class="schritt__nummer">3</span><h2>Prüfen und speichern</h2></div>
        <p class="hinweis">Der Prompt muss verlangen, dass Rechnungen ausschließlich in „formel“ stehen.
            Kurz gegenlesen, Slug festlegen, speichern – danach geht es im Baustein-Editor weiter
            (Aufgaben, Referenzbild, Test).</p>
    </div>

    <div class="field">
        <label for="prompt">KI-Prompt (System)</label>
        <textarea id="prompt" name="prompt" style="min-height:300px"><?= e($prompt) ?></textarea>
    </div>

    <div class="field">
        <label for="slug">Slug (leer = aus dem Titel)</label>
        <input type="text" id="slug" name="slug" maxlength="100" value="<?= e($slug) ?>"
               <?= $bearbeiten !== '' ? 'readonly' : '' ?>
               placeholder="z. B. brueche-addieren">
    </div>

    <div class="form-actions">
        <button type="submit" name="aktion" value="speichern" class="btn">
            <?= $bearbeiten !== '' ? 'Änderungen speichern' : 'Als Lern-Baustein speichern' ?>
        </button>
        <a class="btn btn--secondary" href="bausteine.php">Abbrechen</a>
    </div>
</form>

<?php if ($bearbeiten !== ''): ?>
    <div class="dialog-gefahr">
        <h2>Baustein löschen</h2>
        <p class="hinweis">
            Entfernt <strong><?= e($titel) ?></strong> (<?= e($bearbeiten) ?>) endgültig – samt Musterdialog,
            Referenzaufgaben und Zuordnungen zu Schülerinnen und Schülern.
            Das lässt sich nicht rückgängig machen.
        </p>
        <form method="post" action="dialog_baustein.php"
              onsubmit="return confirm('Baustein wirklich löschen? Musterdialog, Aufgaben und Zuordnungen werden entfernt.');">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="bearbeiten" value="<?= e($bearbeiten) ?>">
            <button type="submit" name="aktion" value="loeschen" class="btn btn--gefahr">
                🗑 Baustein löschen
            </button>
        </form>
    </div>
<?php endif; ?>

<div class="ki-spinner" id="ki-spinner" data-form="#dialogForm" hidden role="status" aria-live="polite">
    <div class="ki-spinner__kreis" aria-hidden="true"></div>
    <p class="ki-spinner__text">Die KI schreibt den Prompt – das dauert meist 10–20 Sekunden …</p>
</div>

<script src="https://cdn.jsdelivr.net/npm/mathlive"></script>
<script src="<?= e(asset_version('assets/keyboard-layout.js')) ?>"></script>
<script src="<?= e(asset_version('assets/keyboard.js')) ?>"></script>
<script src="<?= e(asset_version('assets/dialog_editor.js')) ?>"></script>
<?php
verwaltung_fuss();
