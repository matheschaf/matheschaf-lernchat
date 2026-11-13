<?php

declare(strict_types=1);

/**
 * Migrations-Runner (E0).
 *
 *   Lokal (Kommandozeile, ohne Token):
 *     C:\xampp\php\php.exe C:\xampp\htdocs\lernchat\migrate.php
 *
 *   Geschützt im Browser (live per phpMyAdmin-Import oder einmaligem Token):
 *     https://matheschaf.de/lernchat/migrate.php?token=…
 *
 * Ablauf: Datenbank anlegen (falls sie fehlt) → migrations/*.sql sortiert
 * lesen → nur Dateien ausführen, die nicht in ms_migrationen stehen →
 * Ergebnis je Datei protokollieren. Angewendete Dateien werden nie verändert.
 */

require __DIR__ . '/inc/config.php';
require __DIR__ . '/inc/db.php';

$istCli = PHP_SAPI === 'cli';

if (!$istCli) {
    header('Content-Type: text/plain; charset=utf-8');

    $token = (string) ($_GET['token'] ?? '');
    if (!defined('MS_MIGRATIONS_TOKEN')
        || MS_MIGRATIONS_TOKEN === ''
        || MS_MIGRATIONS_TOKEN === 'BITTE_AUSFUELLEN'
        || !hash_equals(MS_MIGRATIONS_TOKEN, $token)) {
        http_response_code(403);
        exit("403 — Token fehlt oder ist falsch (?token=…).\n");
    }
}

/**
 * Zerlegt eine SQL-Datei in einzelne Anweisungen.
 * Reicht für unsere Migrationen: eine Anweisung endet mit „;“ am Zeilenende,
 * „--“-Zeilen sind Kommentare, es gibt keine Prozeduren und keine Semikola
 * innerhalb von Zeichenketten.
 *
 * @return list<string>
 */
function sql_statemente(string $sql): array
{
    $statemente = [];
    $puffer = '';

    foreach (preg_split('/\R/', $sql) ?: [] as $zeile) {
        $zeile = trim($zeile);
        if ($zeile === '' || str_starts_with($zeile, '--')) {
            continue;
        }

        $puffer .= $zeile . "\n";

        if (str_ends_with($zeile, ';')) {
            $statemente[] = rtrim(trim($puffer), ';');
            $puffer = '';
        }
    }

    if (trim($puffer) !== '') {
        $statemente[] = trim($puffer);
    }

    return $statemente;
}

/**
 * Bereits angewendete Migrationsdateien (leer, solange die Tabelle fehlt).
 *
 * @return list<string>
 */
function migrationen_angewendet(PDO $pdo): array
{
    if (!db_tabelle_vorhanden($pdo, 'ms_migrationen')) {
        return [];
    }

    return $pdo->query('SELECT datei FROM ms_migrationen ORDER BY datei')
        ->fetchAll(PDO::FETCH_COLUMN);
}

$ausgabe = [];
$fehler = 0;

try {
    if (!preg_match('/^[A-Za-z0-9_]+$/', DB_NAME)) {
        throw new RuntimeException('DB_NAME enthält unerlaubte Zeichen: ' . DB_NAME);
    }

    // 1. Datenbank anlegen, falls sie noch fehlt (utf8mb4, wie alle Tabellen).
    $server = db_server();
    $server->exec(
        'CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '`'
        . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
    );
    $ausgabe[] = 'Datenbank bereit: ' . DB_NAME;

    $pdo = db();
    $ausgabe[] = 'Verbindung steht: ' . $pdo->query('SELECT VERSION()')->fetchColumn();

    // 2. Dateien sortiert einlesen.
    $dateien = glob(MS_MIGRATIONS . '/*.sql') ?: [];
    sort($dateien, SORT_NATURAL);
    if ($dateien === []) {
        throw new RuntimeException('Keine Migrationsdateien in ' . MS_MIGRATIONS . ' gefunden.');
    }

    $angewendet = migrationen_angewendet($pdo);

    foreach ($dateien as $pfad) {
        $datei = basename($pfad);

        if (in_array($datei, $angewendet, true)) {
            $ausgabe[] = 'übersprungen (schon angewendet): ' . $datei;
            continue;
        }

        $sql = file_get_contents($pfad);
        if ($sql === false) {
            throw new RuntimeException('Datei nicht lesbar: ' . $datei);
        }

        $statemente = sql_statemente($sql);
        if ($statemente === []) {
            throw new RuntimeException('Keine SQL-Anweisung gefunden: ' . $datei);
        }

        foreach ($statemente as $nummer => $anweisung) {
            try {
                $pdo->exec($anweisung);
            } catch (PDOException $ausnahme) {
                throw new RuntimeException(
                    $datei . ', Anweisung ' . ($nummer + 1) . ': ' . $ausnahme->getMessage(),
                    0,
                    $ausnahme
                );
            }
        }

        // Erst nach erfolgreicher Ausführung protokollieren. Die Tabelle
        // ms_migrationen entsteht in 001_init.sql selbst.
        if (db_tabelle_vorhanden($pdo, 'ms_migrationen')) {
            $eintrag = $pdo->prepare(
                'INSERT INTO ms_migrationen (datei) VALUES (:datei)
                 ON DUPLICATE KEY UPDATE angewendet_am = angewendet_am'
            );
            $eintrag->execute([':datei' => $datei]);
        }

        $ausgabe[] = 'angewendet: ' . $datei . ' (' . count($statemente) . ' Anweisungen)';
    }
} catch (Throwable $ausnahme) {
    $fehler = 1;
    $ausgabe[] = 'FEHLER: ' . $ausnahme->getMessage();
}

$ausgabe[] = $fehler === 0 ? 'Fertig.' : 'Abgebrochen.';

echo implode(PHP_EOL, $ausgabe) . PHP_EOL;

if ($istCli) {
    exit($fehler);
}
