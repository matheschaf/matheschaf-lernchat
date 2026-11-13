<?php

declare(strict_types=1);

/**
 * POST api/sms.php — Flash-SMS mit dem Chat-Link an einen Schüler.
 *
 * Body: id=<Kennung>, csrf=<Token>
 * Voraussetzungen: Telefonnummer hinterlegt UND Einwilligung `ok_sms = 1`.
 * Ohne Einwilligung wird nichts gesendet (403).
 */

require dirname(__DIR__) . '/inc/config.php';
require dirname(__DIR__) . '/inc/chat.php';
require dirname(__DIR__) . '/inc/auth.php';
require dirname(__DIR__) . '/inc/messages.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    json_response(['ok' => false, 'error' => 'Nur POST erlaubt.'], 405);
}

auth_starten();

if (!lehrer_ist_angemeldet()) {
    json_response(['ok' => false, 'error' => 'Nicht angemeldet.'], 403);
}

if (!csrf_pruefen($_POST['csrf'] ?? null)) {
    json_response(['ok' => false, 'error' => 'Ungültiges Token, bitte Seite neu laden.'], 403);
}

$id      = (string) ($_POST['id'] ?? '');
$student = schueler_laden($id);

if ($student === null) {
    json_response(['ok' => false, 'error' => 'Schüler nicht gefunden.'], 404);
}

$nummer = trim((string) ($student['telefon'] ?? ''));

if ($nummer === '' || seven_nummer($nummer) === null) {
    json_response(['ok' => false, 'error' => 'Für diesen Schüler ist keine gültige Telefonnummer hinterlegt.'], 422);
}

if ((int) ($student['ok_sms'] ?? 0) !== 1) {
    json_response([
        'ok' => false,
        'error' => 'Keine Einwilligung für SMS – zuerst in der Einwilligung freigeben.',
    ], 403);
}

$text = 'Hallo ' . schueler_vorname($student) . ', schau in dein Matheschaf: ' . online_chat_link($id);

$ergebnis = seven_flash_sms($nummer, $text);

if (!$ergebnis['ok']) {
    json_response(['ok' => false, 'error' => $ergebnis['error']], 502);
}

json_response([
    'ok'     => true,
    'sms_id' => $ergebnis['id'],
    'nummer' => seven_nummer($nummer) ?? '',
    'text'   => $text,
]);
