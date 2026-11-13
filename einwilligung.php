<?php

declare(strict_types=1);

/**
 * einwilligung.php — Einwilligung der Erziehungsberechtigten (E6, DB).
 *
 * GET  einwilligung.php?id=2301  Text + Formular für einen Schüler
 * POST speichert die Häkchen in ms_schueler (ok_sms, ok_anruf, ok_quiz,
 *      ok_telegram) samt Version, Datum und Art.
 *
 * ENTWURF, Stand 11.11.2026 – die abschließende rechtliche Prüfung steht aus.
 */

require __DIR__ . '/inc/config.php';
require __DIR__ . '/inc/chat.php';
require __DIR__ . '/inc/auth.php';
require __DIR__ . '/inc/verwaltung.php';

auth_starten();

$id      = preg_replace('/[^0-9]/', '', (string) ($_GET['id'] ?? ''));
$student = ($id !== '' && strlen($id) === 4) ? schueler_laden($id) : null;

$fehler = [];
$gespeichert = false;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $student !== null) {
    if (!csrf_pruefen($_POST['csrf'] ?? null)) {
        $fehler[] = 'Die Sitzung ist abgelaufen. Bitte die Seite neu laden.';
    } else {
        $fehler = einwilligung_speichern($id, [
            'version'     => (string) ($_POST['version'] ?? ''),
            'am'          => (string) ($_POST['am'] ?? ''),
            'art'         => (string) ($_POST['art'] ?? ''),
            'ok_sms'      => isset($_POST['ok_sms']),
            'ok_anruf'    => isset($_POST['ok_anruf']),
            'ok_quiz'     => isset($_POST['ok_quiz']),
            'ok_telegram' => isset($_POST['ok_telegram']),
        ]);

        if ($fehler === []) {
            $gespeichert = true;
            $student = schueler_laden($id);
        }
    }
}

$kindName = $student !== null ? schueler_name($student) : '';
$stand    = date('d.m.Y');
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <title>Einwilligung · <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= asset_version('assets/admin2.css') ?>">
    <link rel="stylesheet" href="<?= asset_version('assets/recht.css') ?>">
    <style>
        .recht-karte .field label {
            text-transform: none;
            letter-spacing: normal;
            font-weight: 400;
            display: block;
            margin: 0 0 8px;
        }
    </style>
    <?= app_icon_tags('starter.php') ?>
</head>
<body>
    <header class="topbar">
        <div class="topbar-inner">
            <img class="brand-logo" src="schaf1.png" alt="" width="44" height="44">
            <div class="brand-text">
                <h1>Einwilligung</h1>
                <p>Einwilligung der Erziehungsberechtigten</p>
            </div>
            <a class="topbar-link" href="hilfe.php">Hilfe</a>
        </div>
    </header>

    <div class="wrap recht-wrap">
        <p class="recht-hinweis">
            <strong>Entwurf.</strong> Der Text ist auf dem Stand vom 11.11.2026 und dient als
            Arbeitsgrundlage; die abschließende rechtliche Prüfung steht aus.
        </p>

        <?php if ($student === null): ?>
            <div class="recht-karte">
                <h2>Einwilligung aufrufen</h2>
                <p>
                    Die Einwilligung gehört immer zu einer Schülerin oder einem Schüler. Öffne den
                    Link, den du von der Lehrkraft bekommen hast – er endet auf
                    <code>einwilligung.php?id=…</code>.
                </p>
                <p class="recht-stand">
                    Verantwortlicher: Michael Romahn, Sollnitzer Str. 1, D-06785 Oranienbaum-Wörlitz ·
                    Telefon 01577 3301560 · miro@matheschaf.de
                </p>
            </div>
        <?php else: ?>
            <?php if ($gespeichert): ?>
                <p class="form-notice">Einwilligung gespeichert – danke. Du kannst sie jederzeit hier ändern.</p>
            <?php endif; ?>
            <?php if ($fehler !== []): ?>
                <p class="form-error"><?= e(implode(' ', $fehler)) ?></p>
            <?php endif; ?>

            <div class="recht-karte">
                <h2>Einwilligung zur Teilnahme am KI-Tutor „Matheschaf“</h2>
                <p>
                    Kind: <strong><?= e($kindName) ?></strong> · Klasse:
                    <?= e((string) ($student['klasse'] ?? '–')) ?> · Schüler-ID: <?= e($id) ?>
                </p>

                <h3>1. Welche Daten verarbeitet werden</h3>
                <ul>
                    <li>Vorname, Nachname, Klasse und die interne Schüler-ID,</li>
                    <li>Verlauf der Übungen (Nachrichten, Formeln, Zeitpunkte), getrennt nach Lernchat und Quiz,</li>
                    <li>Bonuspunkte („Schafe“) und Bearbeitungsstand der Bausteine,</li>
                    <li>Telefonnummer – nur wenn hinterlegt, und nur für die unten freigegebenen Erinnerungen,</li>
                    <li>technische Daten (Server-Logfiles, Sitzungs-Cookie).</li>
                </ul>

                <h3>2. Wer die Daten erhält</h3>
                <ul>
                    <li><strong>Google (Gemini)</strong> – Eingaben, bisheriger Verlauf, Vorname und ggf.
                        ein Referenzbild werden zur Erzeugung der Antworten übermittelt
                        (Verarbeitung auch außerhalb der EU möglich).</li>
                    <li><strong>seven.io</strong> – nur bei freigegebenen SMS/Anrufen: Telefonnummer,
                        Vorname und Link bzw. Ansage.</li>
                    <li><strong>Hosting-Anbieter</strong> – technischer Betrieb der Website.</li>
                    <li><strong>Telegram</strong> – nur wenn ausdrücklich freigegeben (Standard: aus).</li>
                </ul>

                <h3>3. Was ich zur Kenntnis nehme</h3>
                <ul>
                    <li>Die KI kann falsche oder unvollständige Antworten geben; verbindlich ist die Lehrkraft.</li>
                    <li>Die Lehrkraft kann den Verlauf mitlesen.</li>
                    <li>Aus der Teilnahme entstehen keine Noten und keine Nachteile.</li>
                    <li>Die Einwilligung ist freiwillig und kann jederzeit mit Wirkung für die Zukunft
                        widerrufen werden (formlos per E-Mail an
                        <a href="mailto:miro@matheschaf.de">miro@matheschaf.de</a>).</li>
                </ul>

                <p>
                    Mehr dazu in der
                    <a href="datenschutz.php">Datenschutzerklärung</a>.
                </p>
            </div>
            <div class="recht-karte">
                <h2>Abgeben</h2>
                <form class="form" method="post" action="einwilligung.php?id=<?= rawurlencode($id) ?>">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

                    <div class="field">
                        <label>
                            <input type="checkbox" name="ok_quiz" value="1"
                                <?= (int) ($student['ok_quiz'] ?? 0) === 1 ? 'checked' : '' ?>>
                            Teilnahme am Lernchat und am Quiz „Teste dein Wissen“
                        </label>
                        <label>
                            <input type="checkbox" name="ok_sms" value="1"
                                <?= (int) ($student['ok_sms'] ?? 0) === 1 ? 'checked' : '' ?>>
                            Erinnerung per Flash-SMS (Telefonnummer nötig)
                        </label>
                        <label>
                            <input type="checkbox" name="ok_anruf" value="1"
                                <?= (int) ($student['ok_anruf'] ?? 0) === 1 ? 'checked' : '' ?>>
                            Erinnerung per Sprachanruf (Telefonnummer nötig)
                        </label>
                        <label>
                            <input type="checkbox" name="ok_telegram" value="1"
                                <?= (int) ($student['ok_telegram'] ?? 0) === 1 ? 'checked' : '' ?>>
                            Statusmeldungen an die Lehrkraft per Telegram (optional, Standard aus)
                        </label>
                    </div>

                    <div class="field">
                        <label for="version">Version</label>
                        <input type="text" id="version" name="version" maxlength="20"
                               value="<?= e((string) ($student['einwilligung_version'] ?? '2026-11')) ?>">
                    </div>

                    <div class="field">
                        <label for="am">Datum</label>
                        <input type="datetime-local" id="am" name="am"
                               value="<?= e(str_replace(' ', 'T', substr((string) ($student['einwilligung_am'] ?? date('Y-m-d H:i:s')), 0, 16))) ?>">
                    </div>

                    <div class="field">
                        <label for="art">Art</label>
                        <select id="art" name="art">
                            <option value="mail" <?= ($student['einwilligung_art'] ?? '') === 'mail' ? 'selected' : '' ?>>per Mail</option>
                            <option value="papier" <?= ($student['einwilligung_art'] ?? '') === 'papier' ? 'selected' : '' ?>>auf Papier</option>
                        </select>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn">Einwilligung speichern</button>
                        <a class="btn btn--secondary" href="datenschutz.php">Datenschutz</a>
                    </div>
                </form>
                <p class="recht-stand">
                    Alternativ: Text ausdrucken, unterschreiben und der Lehrkraft geben.
                    Verantwortlicher: Michael Romahn · miro@matheschaf.de · Stand: <?= e($stand) ?>
                </p>
            </div>
        <?php endif; ?>

        <div class="recht-knoepfe">
            <a class="btn btn--secondary" href="hilfe.php">Hilfe</a>
            <a class="btn btn--secondary" href="datenschutz.php">Datenschutz</a>
            <a class="btn btn--secondary" href="impressum.php">Impressum</a>
        </div>
    </div>

    <p class="foot"><?= e(APP_NAME) ?> · 🐑</p>
</body>
</html>
