<?php

declare(strict_types=1);

/**
 * Selbsttest des Grundgerüsts (E0): Verbindung, Serverversion, Tabellen mit
 * Zeilenzahlen, angewendete Migrationen, PHP-Umgebung.
 *
 * Lokal ohne Login erreichbar, live nur nach Anmeldung:
 *   http://localhost/lernchat/health.php
 */

require __DIR__ . '/inc/config.php';
require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/auth.php';

$lokal = MS_UMGEBUNG === 'lokal';
if (!$lokal && !lehrer_ist_angemeldet()) {
    login_erzwingen(BASE_URL . 'health.php');
}

$verbindung  = false;
$serverInfo  = '';
$dbFehler    = '';
$tabellen    = [];
$migrationen = [];

try {
    $pdo = db();
    $verbindung = true;
    $serverInfo = (string) $pdo->query('SELECT VERSION()')->fetchColumn();

    $namen = $pdo->query(
        "SELECT TABLE_NAME FROM information_schema.TABLES
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE 'ms\\_%'
          ORDER BY TABLE_NAME"
    )->fetchAll(PDO::FETCH_COLUMN);

    foreach ($namen as $name) {
        $tabellen[$name] = (int) $pdo->query('SELECT COUNT(*) FROM `' . $name . '`')->fetchColumn();
    }

    if (db_tabelle_vorhanden($pdo, 'ms_migrationen')) {
        $migrationen = $pdo->query(
            'SELECT datei, angewendet_am FROM ms_migrationen ORDER BY datei'
        )->fetchAll();
    }
} catch (Throwable $ausnahme) {
    $dbFehler = $ausnahme->getMessage();
}

/** Tabellen aus dem Zielmodell (NEU.md, Abschnitt 4) für die Vollständigkeitsliste. */
$erwartet = [
    'ms_migrationen',
    'ms_schueler',
    'ms_bausteine',
    'ms_baustein_dialog',
    'ms_baustein_aufgaben',
    'ms_quiz_fragen',
    'ms_nachrichten',
];
$fehlend = array_values(array_diff($erwartet, array_keys($tabellen)));
$allesGut = $verbindung && $fehlend === [];
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e(APP_NAME) ?> – Systemcheck</title>
<style>
    :root { --gruen: <?= e(APP_THEME_COLOR) ?>; --rot: #b3261e; --grau: #555; }
    * { box-sizing: border-box; }
    body {
        margin: 0;
        padding: 24px 16px 48px;
        font: 16px/1.5 system-ui, -apple-system, "Segoe UI", sans-serif;
        background: #f4f7f2;
        color: #1a1a1a;
    }
    main { max-width: 780px; margin: 0 auto; }
    h1 { font-size: 1.5rem; color: var(--gruen); margin: 0 0 4px; }
    .unterzeile { color: var(--grau); margin: 0 0 20px; font-size: .9rem; }
    .karte {
        background: #fff;
        border-radius: 12px;
        padding: 16px 20px;
        margin: 0 0 16px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, .12);
    }
    .karte h2 { font-size: 1rem; margin: 0 0 10px; }
    .status { font-weight: 600; }
    .ok { color: var(--gruen); }
    .fehler { color: var(--rot); }
    table { width: 100%; border-collapse: collapse; font-size: .95rem; }
    th, td { text-align: left; padding: 6px 4px; border-bottom: 1px solid #e6e6e6; }
    th { color: var(--grau); font-weight: 600; }
    td.zahl, th.zahl { text-align: right; font-variant-numeric: tabular-nums; }
    code { background: #eef2ea; padding: 1px 5px; border-radius: 4px; }
    .hinweis { font-size: .9rem; color: var(--grau); margin: 10px 0 0; }
    .fuss { font-size: .85rem; color: var(--grau); margin-top: 20px; }
</style>
</head>
<body>
<main>
    <h1>Systemcheck</h1>
    <p class="unterzeile">
        Umgebung: <strong><?= e(MS_UMGEBUNG) ?></strong> ·
        Adresse: <code><?= e(BASE_URL) ?></code>
    </p>

    <section class="karte">
        <h2>Datenbank</h2>
        <table>
            <tr>
                <th>Verbindung</th>
                <td class="status <?= $verbindung ? 'ok' : 'fehler' ?>">
                    <?= $verbindung ? 'verbunden' : 'nicht verbunden' ?>
                </td>
            </tr>
            <tr><th>Host</th><td><?= e(DB_HOST) ?></td></tr>
            <tr><th>Datenbank</th><td><?= e(DB_NAME) ?></td></tr>
            <tr><th>MariaDB-Version</th><td><?= e($serverInfo !== '' ? $serverInfo : '–') ?></td></tr>
            <tr><th>Tabellenpräfix</th><td><code><?= e(DB_PRAEFIX) ?></code></td></tr>
        </table>
        <?php if ($dbFehler !== ''): ?>
            <p class="status fehler">Fehler: <?= e($dbFehler) ?></p>
            <p class="hinweis">
                Fehlt die Datenbank? Einmal <code>php migrate.php</code> im Projektordner ausführen.
            </p>
        <?php endif; ?>
    </section>

    <section class="karte">
        <h2>Tabellen</h2>
        <table>
            <tr><th>Tabelle</th><th class="zahl">Zeilen</th></tr>
            <?php foreach ($erwartet as $name): ?>
                <tr>
                    <td>
                        <?= e($name) ?>
                        <?php if (!array_key_exists($name, $tabellen)): ?>
                            <span class="status fehler">– fehlt</span>
                        <?php endif; ?>
                    </td>
                    <td class="zahl"><?= array_key_exists($name, $tabellen) ? (int) $tabellen[$name] : '–' ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
        <?php if ($fehlend !== []): ?>
            <p class="hinweis">
                Migration ausstehend – <code>php migrate.php</code> im Projektordner ausführen.
            </p>
        <?php endif; ?>
    </section>

    <section class="karte">
        <h2>Migrationen</h2>
        <?php if ($migrationen === []): ?>
            <p class="status fehler">Noch keine Migration protokolliert.</p>
        <?php else: ?>
            <table>
                <tr><th>Datei</th><th>Angewendet am</th></tr>
                <?php foreach ($migrationen as $eintrag): ?>
                    <tr>
                        <td><?= e((string) $eintrag['datei']) ?></td>
                        <td><?= e((string) $eintrag['angewendet_am']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </section>

    <section class="karte">
        <h2>PHP</h2>
        <table>
            <tr><th>Version</th><td><?= e(PHP_VERSION) ?></td></tr>
            <tr><th>Zeitzone</th><td><?= e(date_default_timezone_get()) ?></td></tr>
            <tr><th>PDO MySQL</th><td><?= extension_loaded('pdo_mysql') ? 'vorhanden' : 'fehlt' ?></td></tr>
            <tr><th>mbstring</th><td><?= extension_loaded('mbstring') ? 'vorhanden' : 'fehlt' ?></td></tr>
        </table>
    </section>

    <p class="fuss">
        Gesamtstatus:
        <strong class="<?= $allesGut ? 'ok' : 'fehler' ?>"><?= $allesGut ? 'grün' : 'rot' ?></strong>
        <?php if ($lokal): ?>
            · lokal ohne Login · <a href="login.php">Login öffnen</a>
        <?php elseif (lehrer_ist_angemeldet()): ?>
            · angemeldet · <a href="logout.php">Abmelden</a>
        <?php endif; ?>
    </p>
</main>
</body>
</html>
