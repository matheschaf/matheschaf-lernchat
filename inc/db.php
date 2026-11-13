<?php

declare(strict_types=1);

/**
 * Datenbankverbindung (PDO, MariaDB) — eine Verbindung pro Aufruf.
 *
 * Einbinden nach inc/config.php:
 *     require __DIR__ . '/inc/config.php';
 *     require __DIR__ . '/inc/db.php';
 *     $pdo = db();
 *
 * Die Verbindung nutzt utf8mb4 (Umlaute, Emojis, °, ≤ …), Ausnahmen als
 * PDOException, assoziative Ergebnisse und echte Prepared Statements.
 */

/**
 * PDO-Optionen für alle Verbindungen.
 *
 * @return array<int, mixed>
 */
function db_optionen(): array
{
    return [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
}

/**
 * Verbindung zur Projektdatenbank (DB_NAME). Wirft bei Fehlern eine
 * PDOException – Aufrufer entscheiden, ob sie eine Meldung anzeigen.
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, db_optionen());

    return $pdo;
}

/**
 * Verbindung zum Server ohne Datenbank – nur für migrate.php nötig
 * (Datenbank anlegen, bevor die erste Tabelle existiert).
 */
function db_server(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, db_optionen());

    return $pdo;
}

/**
 * Prüft, ob eine Tabelle in der aktuellen Datenbank existiert.
 */
function db_tabelle_vorhanden(PDO $pdo, string $tabelle): bool
{
    $abfrage = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :name'
    );
    $abfrage->execute([':name' => $tabelle]);

    return (int) $abfrage->fetchColumn() > 0;
}
