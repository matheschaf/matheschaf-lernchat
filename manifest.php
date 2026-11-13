<?php

declare(strict_types=1);

/**
 * manifest.php?start=index.php%3Fid%3D2301&name=Michael
 *
 * Web-App-Manifest für „Zum Home-Bildschirm hinzufügen" unter Android/Chrome.
 * Es liefert Icon, Name und Startseite der Verknüpfung. iPhone/iPad ignorieren
 * das Manifest und nutzen die apple-touch-icon-Tags aus app_icon_tags().
 *
 * Die Parameter werden geprüft, damit nur echte Projektseiten als Startziel
 * durchgehen (keine fremden URLs, keine Pfad-Tricks).
 */

require __DIR__ . '/inc/config.php';
require __DIR__ . '/inc/chat.php';

$start = (string) ($_GET['start'] ?? 'starter.php');

if (preg_match('/^(starter|index|schueler|schueler_edit|bausteine|baustein_edit|baustein_generator|dialog_baustein|zuordnung|mitlesen|quiz_test|health)\.php(\?id=[A-Za-z0-9_-]{1,20})?$/', $start) !== 1) {
    $start = 'starter.php';
}

$name = normalize_text((string) ($_GET['name'] ?? ''));
$name = mb_substr($name !== '' ? $name : APP_NAME, 0, 30);

$manifest = [
    'id'               => './' . $start,
    'name'             => $name === APP_NAME ? APP_NAME : APP_NAME . ' · ' . $name,
    'short_name'       => $name,
    'description'      => 'Matheschaf – Mathe-Lernchat mit KI',
    'start_url'        => './' . $start,
    'scope'            => './',
    'display'          => 'standalone',
    'background_color' => '#f7f4ec',          // --ms-cream
    'theme_color'      => APP_THEME_COLOR,
    'lang'             => 'de',
    'dir'              => 'ltr',
    'icons'            => [
        ['src' => 'icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => 'icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => 'icons/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
    ],
];

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: no-cache');
echo json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
