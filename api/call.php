<?php

declare(strict_types=1);

/**
 * POST api/call.php — TTS-Anruf („denk an dein Mathe-Schaaf“) an einen Schüler.
 *
 * Body: id=<Kennung>, csrf=<Token>
 * Voraussetzungen: Telefonnummer hinterlegt UND Einwilligung `ok_anruf = 1`.
 * Ohne Einwilligung wird nicht angerufen (403).
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

if ((int) ($student['ok_anruf'] ?? 0) !== 1) {
    json_response([
        'ok' => false,
        'error' => 'Keine Einwilligung für Anrufe – zuerst in der Einwilligung freigeben.',
    ], 403);
}

$ansage   = anruf_ansage(schueler_vorname($student));
$ergebnis = seven_anruf($nummer, $ansage['ssml']);

if (!$ergebnis['ok']) {
    json_response(['ok' => false, 'error' => $ergebnis['error']], 502);
}

json_response([
    'ok'      => true,
    'call_id' => $ergebnis['id'],
    'nummer'  => seven_nummer($nummer) ?? '',
    'text'    => $ansage['text'],
]);
