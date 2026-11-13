<?php

declare(strict_types=1);

/**
 * POST api/upload.php — Bild für einen Baustein hochladen (nur Lehrkraft).
 *
 * Aufruf aus dem Dialog-Recorder (dialog_baustein.php, baustein_edit.php):
 *   FormData: file (Bild), csrf
 *   Antwort:  { ok: true, pfad: "bilder/bild-…jpg", name: "…", groesse: 12345 }
 *
 * Das erste Bild im Musterdialog gilt als Hauptbild des Bausteins
 * (baustein_referenzbild() → linke Würfelfläche + klein oben im Chat).
 */

require dirname(__DIR__) . '/inc/config.php';
require dirname(__DIR__) . '/inc/chat.php';
require dirname(__DIR__) . '/inc/auth.php';

@ini_set('display_errors', '0');

auth_starten();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    json_response(['ok' => false, 'fehler' => 'Nur POST erlaubt.'], 405);
}

if (!lehrer_ist_angemeldet()) {
    json_response([
        'ok'     => false,
        'fehler' => 'Bitte zuerst in lernchat anmelden.',
        'login'  => BASE_URL . 'login.php',
    ], 403);
}

if (!csrf_pruefen($_POST['csrf'] ?? null)) {
    json_response(['ok' => false, 'fehler' => 'Ungültiges Token, bitte Seite neu laden.'], 403);
}

$datei = $_FILES['file'] ?? null;

if (!is_array($datei) || ($datei['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
    json_response(['ok' => false, 'fehler' => 'Keine Datei empfangen.'], 422);
}

if (($datei['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    json_response(['ok' => false, 'fehler' => 'Upload fehlgeschlagen (Code ' . (int) $datei['error'] . ').'], 422);
}

if ((int) ($datei['size'] ?? 0) > 10 * 1024 * 1024) {
    json_response(['ok' => false, 'fehler' => 'Datei zu groß (max. 10 MB).'], 422);
}

$original = (string) ($datei['name'] ?? 'bild');
$endung   = strtolower((string) pathinfo($original, PATHINFO_EXTENSION));
$erlaubt  = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

if (!in_array($endung, $erlaubt, true)) {
    json_response([
        'ok'     => false,
        'fehler' => 'Nur Bilder (jpg, jpeg, png, webp, gif).',
    ], 422);
}

/* Ist es wirklich ein Bild? (nicht nur die Endung) */
$info = @getimagesize((string) ($datei['tmp_name'] ?? ''));

if ($info === false) {
    json_response(['ok' => false, 'fehler' => 'Die Datei ist kein lesbares Bild.'], 422);
}

$ordner = dirname(__DIR__) . '/bilder';

if (!is_dir($ordner) && !mkdir($ordner, 0775, true) && !is_dir($ordner)) {
    json_response(['ok' => false, 'fehler' => 'Bilder-Ordner kann nicht angelegt werden.'], 500);
}

$name   = 'bild-' . time() . '-' . bin2hex(random_bytes(4)) . '.' . $endung;
$ziel   = $ordner . '/' . $name;

if (!move_uploaded_file((string) $datei['tmp_name'], $ziel)) {
    json_response(['ok' => false, 'fehler' => 'Datei konnte nicht gespeichert werden.'], 500);
}

@chmod($ziel, 0664);

json_response([
    'ok'      => true,
    'pfad'    => 'bilder/' . $name,
    'name'    => $original,
    'groesse' => (int) filesize($ziel),
    'breite'  => (int) ($info[0] ?? 0),
    'hoehe'   => (int) ($info[1] ?? 0),
], 201);
