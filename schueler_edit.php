<?php

declare(strict_types=1);

/**
 * schueler_edit.php — Schüler anlegen/ändern inkl. granularer Einwilligung (E5).
 *
 * Aufruf: schueler_edit.php (neu) · schueler_edit.php?id=2301 (ändern)
 */

require __DIR__ . '/inc/config.php';
require __DIR__ . '/inc/chat.php';
require __DIR__ . '/inc/auth.php';
require __DIR__ . '/inc/verwaltung.php';

auth_starten();
verwaltung_erzwingen();

$istNeu = !isset($_GET['id']) || $_GET['id'] === '';
$id     = $istNeu ? (naechste_kennung() ?? '') : (string) $_GET['id'];

$student = $istNeu ? [
    'id_code'              => $id,
    'vorname'              => '',
    'nachname'             => '',
    'klasse'               => '',
    'telefon'              => '',
    'bonuspunkte'          => 0,
    'stufe'                => 'leicht',
    'einwilligung_version' => '',
    'einwilligung_am'      => '',
    'einwilligung_art'     => '',
    'ok_sms'               => 0,
    'ok_anruf'             => 0,
    'ok_quiz'              => 0,
    'ok_telegram'          => 0,
] : schueler_laden($id);

if ($student === null) {
    header('Location: schueler.php', true, 302);
    exit;
}

$fehler = [];
$meldung = '';
$aktion  = (string) ($_POST['aktion'] ?? 'speichern');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_pruefen($_POST['csrf'] ?? null)) {
        $fehler[] = 'Ungültiges Token, bitte Seite neu laden.';
    } elseif (!$istNeu && $aktion === 'loeschen') {
        if (trim((string) ($_POST['bestaetigung'] ?? '')) !== $id) {
            $fehler[] = 'Zum Löschen bitte die Kennung ' . $id . ' in das Bestätigungsfeld eintragen.';
        } elseif (schueler_loeschen($id)) {
            header('Location: schueler.php?geloescht=' . rawurlencode($id), true, 302);
            exit;
        } else {
            $fehler[] = 'Löschen fehlgeschlagen – der Schüler wurde nicht gefunden.';
        }
    } elseif (!$istNeu && $aktion === 'duplizieren') {
        $kopie = schueler_duplizieren($id);
        if ($kopie === null) {
            $fehler[] = 'Duplizieren fehlgeschlagen – es ist keine freie Kennung mehr verfügbar.';
        } else {
            header('Location: schueler_edit.php?id=' . rawurlencode($kopie) . '&kopie=1', true, 302);
            exit;
        }
    } elseif (!$istNeu && $aktion === 'verlauf') {
        $verlaufKontext = (string) ($_POST['verlauf_kontext'] ?? 'beides');
        if (!in_array($verlaufKontext, ['chat', 'quiz', 'beides'], true)) {
            $verlaufKontext = 'beides';
        }
        $anzahl  = verlauf_leeren($id, $verlaufKontext);
        $meldung = $anzahl === 1
            ? '1 Nachricht gelöscht.'
            : $anzahl . ' Nachrichten gelöscht.';
    } else {
        $daten = [
            'id_code'              => $istNeu ? trim((string) ($_POST['id_code'] ?? '')) : $id,
            'vorname'              => (string) ($_POST['vorname'] ?? ''),
            'nachname'             => (string) ($_POST['nachname'] ?? ''),
            'klasse'               => (string) ($_POST['klasse'] ?? ''),
            'telefon'              => (string) ($_POST['telefon'] ?? ''),
            'bonuspunkte'          => (int) ($_POST['bonuspunkte'] ?? 0),
            'stufe'                => (string) ($_POST['stufe'] ?? 'leicht'),
            'einwilligung_version' => (string) ($_POST['einwilligung_version'] ?? ''),
            'einwilligung_am'      => (string) ($_POST['einwilligung_am'] ?? ''),
            'einwilligung_art'     => (string) ($_POST['einwilligung_art'] ?? ''),
            'ok_sms'               => isset($_POST['ok_sms']) ? 1 : 0,
            'ok_anruf'             => isset($_POST['ok_anruf']) ? 1 : 0,
            'ok_quiz'              => isset($_POST['ok_quiz']) ? 1 : 0,
            'ok_telegram'          => isset($_POST['ok_telegram']) ? 1 : 0,
        ];

        $fehler = schueler_speichern($daten, $istNeu);

        if ($fehler === []) {
            header('Location: schueler.php', true, 302);
            exit;
        }

        $student = array_merge($student, $daten);
    }
}

verwaltung_kopf($istNeu ? 'Neuer Schüler' : 'Schüler bearbeiten', 'schueler.php');

if ($meldung === '' && isset($_GET['kopie'])) {
    $meldung = 'Schüler kopiert – bitte Namen anpassen (Stammdaten und Einwilligung wurden übernommen, der Verlauf nicht).';
}

if ($fehler !== []) {
    echo '<p class="form-error">' . e(implode(' ', $fehler)) . '</p>';
}
if ($meldung !== '') {
    echo '<p class="form-notice">' . e($meldung) . '</p>';
}
?>
<p class="hinweis">
    <a href="schueler.php">← Zur Schülerliste</a>
    <?php if (!$istNeu): ?>
        · <a href="mitlesen.php?id=<?= rawurlencode($id) ?>">Verlauf mitlesen</a>
        · <a href="zuordnung.php?id=<?= rawurlencode($id) ?>">Bausteine zuordnen</a>
    <?php endif; ?>
</p>

<?php if (!$istNeu): ?>
    <p class="hinweis" style="font-weight:700">
        <?= e(schueler_name_voll($student)) ?> · ID <?= e($id) ?>
        <span class="hinweis" style="font-weight:400">(voller Name – in Listen und Meldungen erscheint nur
            „<?= e(schueler_name($student)) ?>")</span>
    </p>
<?php endif; ?>

<form class="form" method="post" action="schueler_edit.php<?= $istNeu ? '' : '?id=' . rawurlencode($id) ?>">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="aktion" value="speichern">

    <div class="field">
        <label for="id_code">Kennung (4 Ziffern)</label>
        <input type="text" id="id_code" name="id_code" inputmode="numeric" maxlength="4"
               value="<?= e((string) $student['id_code']) ?>" <?= $istNeu ? 'required' : 'readonly' ?>>
    </div>

    <div class="field">
        <label for="vorname">Vorname</label>
        <input type="text" id="vorname" name="vorname" maxlength="100" required
               value="<?= e((string) $student['vorname']) ?>">
    </div>

    <div class="field">
        <label for="nachname">Nachname</label>
        <input type="text" id="nachname" name="nachname" maxlength="100"
               value="<?= e((string) $student['nachname']) ?>">
    </div>

    <div class="field">
        <label for="klasse">Klasse / Gruppe</label>
        <input type="text" id="klasse" name="klasse" maxlength="100"
               value="<?= e((string) ($student['klasse'] ?? '')) ?>">
    </div>

    <div class="field">
        <label for="telefon">Telefon (für SMS/Anruf)</label>
        <input type="text" id="telefon" name="telefon" maxlength="30"
               value="<?= e((string) ($student['telefon'] ?? '')) ?>">
    </div>

    <div class="field">
        <label for="bonuspunkte">Bonuspunkte</label>
        <input type="text" id="bonuspunkte" name="bonuspunkte" inputmode="numeric"
               value="<?= (int) ($student['bonuspunkte'] ?? 0) ?>">
    </div>

    <div class="field">
        <label for="stufe">Stufe</label>
        <select id="stufe" name="stufe">
            <?php foreach (['leicht', 'mittel', 'schwer', 'zufall'] as $stufe): ?>
                <option value="<?= e($stufe) ?>" <?= ($student['stufe'] ?? '') === $stufe ? 'selected' : '' ?>>
                    <?= e(ucfirst($stufe)) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <h2 style="margin-top:22px">Einwilligung</h2>
    <p class="hinweis">
        Ohne Häkchen bleiben SMS/Anruf im Lehrer-Bereich gesperrt.
        Version/Datum/Art dokumentieren, wie die Einwilligung eingeholt wurde.
    </p>

    <div class="field">
        <label for="einwilligung_version">Version (z. B. 2026-11)</label>
        <input type="text" id="einwilligung_version" name="einwilligung_version" maxlength="20"
               value="<?= e((string) ($student['einwilligung_version'] ?? '')) ?>">
    </div>

    <div class="field">
        <label for="einwilligung_am">Datum</label>
        <input type="datetime-local" id="einwilligung_am" name="einwilligung_am"
               value="<?= e(str_replace(' ', 'T', substr((string) ($student['einwilligung_am'] ?? ''), 0, 16))) ?>">
    </div>

    <div class="field">
        <label for="einwilligung_art">Art</label>
        <select id="einwilligung_art" name="einwilligung_art">
            <option value="">– keine Angabe –</option>
            <option value="mail" <?= ($student['einwilligung_art'] ?? '') === 'mail' ? 'selected' : '' ?>>per Mail</option>
            <option value="papier" <?= ($student['einwilligung_art'] ?? '') === 'papier' ? 'selected' : '' ?>>auf Papier</option>
        </select>
    </div>

    <div class="field">
        <label><input type="checkbox" name="ok_sms" value="1" <?= (int) ($student['ok_sms'] ?? 0) === 1 ? 'checked' : '' ?>> SMS (Flash-SMS mit Chat-Link)</label>
        <label><input type="checkbox" name="ok_anruf" value="1" <?= (int) ($student['ok_anruf'] ?? 0) === 1 ? 'checked' : '' ?>> Anruf (TTS-Ansage)</label>
        <label><input type="checkbox" name="ok_quiz" value="1" <?= (int) ($student['ok_quiz'] ?? 0) === 1 ? 'checked' : '' ?>> Quiz</label>
        <label><input type="checkbox" name="ok_telegram" value="1" <?= (int) ($student['ok_telegram'] ?? 0) === 1 ? 'checked' : '' ?>> Telegram (optional, standardmäßig aus)</label>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn">Speichern</button>
        <a class="btn btn--secondary" href="schueler.php">Abbrechen</a>
    </div>
</form>

<?php if (!$istNeu): ?>
    <section class="section">
        <div class="section-head"><h2>Verlauf leeren</h2></div>
        <p class="hinweis">
            Löscht die Nachrichten dieses Schülers endgültig – im gewählten Bereich
            (Chat, Quiz oder beides). Bonuspunkte und Baustein-Status bleiben erhalten.
        </p>
        <form class="form" method="post" action="schueler_edit.php?id=<?= rawurlencode($id) ?>">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="aktion" value="verlauf">
            <div class="field">
                <label for="verlauf_kontext">Bereich</label>
                <select id="verlauf_kontext" name="verlauf_kontext">
                    <option value="beides">Chat und Quiz</option>
                    <option value="chat">nur Lernchat</option>
                    <option value="quiz">nur Quiz</option>
                </select>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn--secondary">Verlauf leeren</button>
            </div>
        </form>
    </section>

    <section class="section">
        <div class="section-head"><h2>Kopieren</h2></div>
        <p class="hinweis">
            Legt eine Kopie unter der nächsten freien Kennung an – ohne Verlauf und
            ohne Baustein-Status. Danach Namen anpassen.
        </p>
        <form class="form" method="post" action="schueler_edit.php?id=<?= rawurlencode($id) ?>">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="aktion" value="duplizieren">
            <div class="form-actions">
                <button type="submit" class="btn btn--secondary">Schüler duplizieren</button>
            </div>
        </form>
    </section>

    <section class="section">
        <div class="section-head"><h2>Löschen</h2></div>
        <p class="hinweis">
            Entfernt den Schüler samt Verlauf und Baustein-Status endgültig.
            Zur Sicherheit die Kennung <strong><?= e($id) ?></strong> eintragen.
        </p>
        <form class="form" method="post" action="schueler_edit.php?id=<?= rawurlencode($id) ?>">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="aktion" value="loeschen">
            <div class="field">
                <label for="bestaetigung">Kennung zur Bestätigung</label>
                <input type="text" id="bestaetigung" name="bestaetigung" inputmode="numeric"
                       maxlength="4" autocomplete="off" placeholder="<?= e($id) ?>">
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn--danger">Schüler löschen</button>
            </div>
        </form>
    </section>
<?php endif; ?>
<?php
verwaltung_fuss();
