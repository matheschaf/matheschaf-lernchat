<?php

declare(strict_types=1);

/**
 * schueler_json.php — Rohdaten-Export als `schueler.json`, nur nach Login.
 *
 * Enthält den VOLLEN Namen (Datenstand/Sicherung). In der Oberfläche selbst
 * erscheint nur „Vorname N." (siehe schueler_name()).
 *
 * ?telefon=1  Telefonnummern mit exportieren (sonst bewusst weggelassen).
 */

require __DIR__ . '/inc/config.php';
require __DIR__ . '/inc/chat.php';
require __DIR__ . '/inc/auth.php';
require __DIR__ . '/inc/verwaltung.php';

auth_starten();
verwaltung_erzwingen();

$mitTelefon = (string) ($_GET['telefon'] ?? '') === '1';
$daten      = schueler_json_daten($mitTelefon);
$json       = json_encode($daten, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="schueler.json"');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

echo $json;
