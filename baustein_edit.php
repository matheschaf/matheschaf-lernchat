<?php

declare(strict_types=1);

/**
 * baustein_edit.php — Lern- und Quiz-Bausteine bearbeiten (E5).
 *
 * Aufruf: ?slug=negzahl (ändern) · ?neu=1[&art=quiz] (neu)
 * Kinder werden als Textzeilen gepflegt:
 *   Dialog: rolle | text | formel | bild
 *   Aufgaben: text | formel (je Stufe)
 *   Fragen: frage | antwort
 */

require __DIR__ . '/inc/config.php';
require __DIR__ . '/inc/chat.php';
require __DIR__ . '/inc/auth.php';
require __DIR__ . '/inc/verwaltung.php';
require __DIR__ . '/inc/ki.php';

auth_starten();
verwaltung_erzwingen();

$istNeu   = !isset($_GET['slug']) || trim((string) $_GET['slug']) === '';
$slug     = $istNeu ? '' : trim((string) $_GET['slug']);
$artVorgabe = (string) ($_GET['art'] ?? 'lern');
$artVorgabe = in_array($artVorgabe, ['lern', 'quiz'], true) ? $artVorgabe : 'lern';

$baustein = $istNeu ? null : baustein_laden($slug);
if (!$istNeu && $baustein === null) {
    header('Location: bausteine.php', true, 302);
    exit;
}

/** Textzeilen aus DB-Struktur bauen (Zeilenumbrüche werden zu Leerzeichen). */
$alsZeile = static function (array $teile): string {
    $saeubern = static fn(string $s): string => trim(str_replace(["\r\n", "\n", "\r", '|'], ' ', $s));

    return implode(' | ', array_map($saeubern, $teile));
};

$dialogText = '';
$aufgabenText = ['leicht' => '', 'mittel' => '', 'schwer' => ''];
$fragenText = '';

if ($baustein !== null) {
    $dialogZeilen = [];
    foreach ($baustein['dialog'] as $zeile) {
        $dialogZeilen[] = $alsZeile([
            (string) $zeile['rolle'],
            (string) $zeile['text'],
            (string) $zeile['formel'],
            (string) $zeile['bild'],
        ]);
    }
    $dialogText = implode("\n", $dialogZeilen);

    foreach ($aufgabenText as $stufe => $_) {
        $zeilen = [];
        foreach ($baustein['aufgaben'][$stufe] ?? [] as $a) {
            $zeilen[] = $alsZeile([(string) $a['text'], (string) $a['formel']]);
        }
        $aufgabenText[$stufe] = implode("\n", $zeilen);
    }

    if (isset($baustein['quiz']['fragen'])) {
        $zeilen = [];
        foreach ($baustein['quiz']['fragen'] as $f) {
            $zeilen[] = $alsZeile([(string) $f['frage'], (string) $f['antwort']]);
        }
        $fragenText = implode("\n", $zeilen);
    }
}

$werte = [
    'slug'        => $slug,
    'art'         => $baustein['art'] ?? $artVorgabe,
    'titel'       => $baustein['titel'] ?? '',
    'thema'       => $baustein['thema'] ?? '',
    'prompt'      => $baustein['prompt'] ?? '',
    'quiz_thema'  => $baustein['quiz_thema'] ?? '',
    'quiz_anzahl' => $baustein['quiz_anzahl'] ?? 5,
    'dialog'      => $dialogText,
    'aufgaben_leicht' => $aufgabenText['leicht'],
    'aufgaben_mittel' => $aufgabenText['mittel'],
    'aufgaben_schwer' => $aufgabenText['schwer'],
    'fragen'      => $fragenText,
];

$fehler = [];
$meldung = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_pruefen($_POST['csrf'] ?? null)) {
        $fehler[] = 'Ungültiges Token, bitte Seite neu laden.';
    } else {
        $aktion = (string) ($_POST['aktion'] ?? 'speichern');

        foreach ($werte as $feld => $_) {
            $werte[$feld] = (string) ($_POST[$feld] ?? '');
        }

        if ($aktion === 'erzeugen') {
            // Nur den Prompt erzeugen – nichts speichern, Eingaben bleiben stehen.
            $prompt = ki_prompt_erzeugen($werte);

            if ($prompt === null) {
                $fehler[] = 'Die KI hat nicht geantwortet. Bitte noch einmal versuchen.';
            } else {
                $werte['prompt'] = $prompt;
                $meldung = 'Prompt erzeugt – prüfen, anpassen, dann speichern.';
            }
        } else {
            $ergebnis = baustein_speichern($werte, $istNeu);

            if ($ergebnis['fehler'] === []) {
                header('Location: bausteine.php', true, 302);
                exit;
            }

            $fehler = $ergebnis['fehler'];
        }
    }
}

verwaltung_kopf(
    $istNeu ? 'Neuer Baustein' : 'Baustein bearbeiten',
    'bausteine.php',
    '<link rel="stylesheet" href="' . e(asset_version('assets/dialog_editor.css')) . '">'
    . '<link rel="stylesheet" href="' . e(asset_version('assets/keyboard2.css')) . '">'
);

if ($meldung !== '') {
    echo '<p class="form-notice">' . e($meldung) . '</p>';
}
if ($fehler !== []) {
    echo '<p class="form-error">' . e(implode(' ', $fehler)) . '</p>';
}
?>
<p class="hinweis">
    <a href="bausteine.php">← Zur Bausteinliste</a>
    <?php if (!$istNeu && $werte['art'] === 'quiz'): ?>
        · <a href="quiz_test.php?slug=<?= rawurlencode((string) $werte['slug']) ?>">Testlauf</a>
    <?php endif; ?>
    · Zeilenformat: <code>rolle | text | formel | bild</code> (leere Felder erlaubt)
</p>

<form class="form" method="post" id="bausteinForm" action="baustein_edit.php<?= $istNeu ? '' : '?slug=' . rawurlencode((string) $werte['slug']) ?>">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <div class="field">
        <label for="slug">Slug (a–z, 0–9, -)</label>
        <input type="text" id="slug" name="slug" maxlength="100"
               value="<?= e((string) $werte['slug']) ?>" <?= $istNeu ? 'required' : 'readonly' ?>>
    </div>

    <div class="field">
        <label for="titel">Titel</label>
        <input type="text" id="titel" name="titel" maxlength="200" required
               value="<?= e((string) $werte['titel']) ?>">
    </div>

    <div class="field">
        <label for="thema">Thema</label>
        <input type="text" id="thema" name="thema" maxlength="255"
               value="<?= e((string) $werte['thema']) ?>">
    </div>

    <div class="field">
        <label for="art">Art</label>
        <select id="art" name="art">
            <option value="lern" <?= $werte['art'] === 'lern' ? 'selected' : '' ?>>Lern-Baustein (Chat)</option>
            <option value="quiz" <?= $werte['art'] === 'quiz' ? 'selected' : '' ?>>Quiz (Multiple Choice)</option>
        </select>
    </div>

    <div class="field">
        <label for="quiz_thema">Quiz-Thema (nur Quiz)</label>
        <input type="text" id="quiz_thema" name="quiz_thema" maxlength="255"
               value="<?= e((string) $werte['quiz_thema']) ?>">
    </div>

    <div class="field">
        <label for="quiz_anzahl">Fragen pro Quiz-Durchgang (1–20)</label>
        <input type="text" id="quiz_anzahl" name="quiz_anzahl" inputmode="numeric"
               value="<?= e((string) $werte['quiz_anzahl']) ?>">
    </div>

    <div class="field">
        <label for="prompt">KI-Prompt (System)</label>
        <p class="hinweis">
            „✨ Prompt erzeugen“ baut den Prompt aus Titel, Thema, Art, Beispieldialog,
            Aufgaben und Quiz-Fragen. Ersetzt den bisherigen Prompt – vorher prüfen, dann speichern.
        </p>
        <textarea id="prompt" name="prompt" style="min-height:260px"><?= e((string) $werte['prompt']) ?></textarea>
    </div>

    <div class="form-actions" style="margin-top:12px">
        <button type="submit" name="aktion" value="erzeugen" class="btn btn--secondary" formnovalidate>
            ✨ Prompt erzeugen
        </button>
    </div>

    <div class="field">
        <label>Beispieldialog (Musterdialog)</label>
        <p class="hinweis">
            Blasen wie im echten Chat: Lehrer links, Schüler rechts. Worte in den Text,
            Rechnungen getrennt in die Formel – die KI antwortet später genauso.
        </p>

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

                <input type="text" id="dialog-bild" maxlength="255"
                       placeholder="Bildpfad (optional) – über 📎 hochladen">

                <div class="dialog-vorschau" id="dialog-vorschau" hidden></div>
                <p class="dialog-upload-status" id="dialog-upload-status"></p>
            </div>

            <div data-tastatur data-text="#dialog-text" data-math="#dialog-formel"></div>

            <details style="margin-top:10px">
                <summary class="hinweis" style="cursor:pointer">Textzeilen (Experten / zum Prüfen)</summary>
                <textarea id="dialog" name="dialog" style="min-height:160px"
                          spellcheck="false"><?= e((string) $werte['dialog']) ?></textarea>
            </details>
        </div>
    </div>

    <div class="field">
        <label for="aufgaben_leicht">Aufgaben leicht (text | formel)</label>
        <textarea id="aufgaben_leicht" name="aufgaben_leicht"><?= e((string) $werte['aufgaben_leicht']) ?></textarea>
    </div>

    <div class="field">
        <label for="aufgaben_mittel">Aufgaben mittel</label>
        <textarea id="aufgaben_mittel" name="aufgaben_mittel"><?= e((string) $werte['aufgaben_mittel']) ?></textarea>
    </div>

    <div class="field">
        <label for="aufgaben_schwer">Aufgaben schwer</label>
        <textarea id="aufgaben_schwer" name="aufgaben_schwer"><?= e((string) $werte['aufgaben_schwer']) ?></textarea>
    </div>

    <div class="field">
        <label for="fragen">Quiz-Fragen (frage | richtige Antwort)</label>
        <textarea id="fragen" name="fragen" style="min-height:160px"><?= e((string) $werte['fragen']) ?></textarea>
    </div>

    <div class="form-actions">
        <button type="submit" name="aktion" value="speichern" class="btn">Speichern</button>
        <a class="btn btn--secondary" href="bausteine.php">Abbrechen</a>
    </div>
</form>

<div class="ki-spinner" id="ki-spinner" data-form="#bausteinForm" hidden role="status" aria-live="polite">
    <div class="ki-spinner__kreis" aria-hidden="true"></div>
    <p class="ki-spinner__text">Die KI schreibt den Prompt – das dauert meist 10–20 Sekunden …</p>
</div>

<script src="https://cdn.jsdelivr.net/npm/mathlive"></script>
<script src="<?= e(asset_version('assets/keyboard-layout.js')) ?>"></script>
<script src="<?= e(asset_version('assets/keyboard.js')) ?>"></script>
<script src="<?= e(asset_version('assets/dialog_editor.js')) ?>"></script>
<?php
verwaltung_fuss();
