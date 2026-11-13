<?php

declare(strict_types=1);

/**
 * POST api/quiz_test.php — Testlauf eines Quiz-Bausteins OHNE Schülerbezug.
 *
 * Es wird nichts gespeichert (keine Nachrichten, keine Punkte, keine Statusliste).
 * Der Gesprächsverlauf kommt bei jeder Anfrage aus dem Browser.
 *
 * Body: slug, name, stufe, verlauf (JSON: [{role, content}, …])
 * Antwort: { ok, text, formel, richtig: true|false|null, dauer }
 */

require dirname(__DIR__) . '/inc/config.php';
require dirname(__DIR__) . '/inc/chat.php';
require dirname(__DIR__) . '/inc/ki.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    json_response(['ok' => false, 'fehler' => 'Nur POST erlaubt.'], 405);
}

$slug     = (string) ($_POST['slug'] ?? '');
$baustein = $slug !== '' ? baustein_laden($slug) : null;

if ($baustein === null) {
    json_response(['ok' => false, 'fehler' => 'Baustein nicht gefunden.'], 404);
}

// Der Testlauf ist nur für Quiz-Bausteine gedacht (art = quiz).
if (($baustein['art'] ?? '') !== 'quiz') {
    json_response([
        'ok' => false,
        'fehler' => 'Dieser Baustein ist kein Quiz – bitte einen Quiz-Baustein wählen.',
    ], 409);
}

if (trim((string) ($baustein['prompt'] ?? '')) === '') {
    json_response([
        'ok' => false,
        'fehler' => 'Für diesen Baustein gibt es keinen Prompt – bitte im Quiz-Editor speichern.',
    ], 409);
}

$name  = normalize_text((string) ($_POST['name'] ?? ''));
$name  = $name !== '' ? $name : 'Testkandidat';
$stufe = (string) ($_POST['stufe'] ?? 'leicht');
if (!in_array($stufe, ['leicht', 'mittel', 'schwer', 'zufall'], true)) {
    $stufe = 'leicht';
}

// Verlauf aus dem Browser (nur user/assistant, begrenzt).
$verlauf = json_decode((string) ($_POST['verlauf'] ?? '[]'), true);
$verlauf = is_array($verlauf) ? array_slice($verlauf, -40) : [];

$messages = [];
foreach ($verlauf as $zeile) {
    if (!is_array($zeile)) {
        continue;
    }

    $inhalt = trim((string) ($zeile['content'] ?? ''));
    if ($inhalt === '') {
        continue;
    }

    $messages[] = [
        'role'    => (($zeile['role'] ?? '') === 'user') ? 'user' : 'assistant',
        'content' => mb_substr($inhalt, 0, 3000),
    ];
}

$prompt  = fill_prompt_placeholders((string) $baustein['prompt'], $name, $stufe, '');
$anfrage = array_merge([['role' => 'system', 'content' => $prompt]], $messages);

if ($messages === []) {
    $anfrage[] = [
        'role' => 'user',
        'content' => 'Starte jetzt das Quiz mit Frage 1. Keine Begrüßungsrede, direkt zur ersten Frage.',
    ];
}

$start = microtime(true);
$roh   = ki_chat($anfrage);
$dauer = round(microtime(true) - $start, 1);

if ($roh === null) {
    json_response(['ok' => false, 'fehler' => 'Keine Antwort von der KI (Zeitüberschreitung oder Fehler).'], 502);
}

// Bewertungs-Marker der KI auswerten (vor dem Aufräumen des Textes).
$richtig = null;
if (str_contains($roh, '[[RICHTIG]]')) {
    $richtig = true;
} elseif (str_contains($roh, '[[FALSCH]]')) {
    $richtig = false;
}

$decoded = ki_antwort_lesen($roh);

if (is_array($decoded)) {
    $text   = clean_ai_text((string) ($decoded['text'] ?? ''));
    $formel = (string) ($decoded['formel'] ?? '');
} else {
    $text   = clean_ai_text($roh);
    $formel = '';
}

if ($text === '' && $formel === '') {
    $text = 'Ich habe mich verhaspelt. Frag mich bitte noch einmal.';
}

json_response([
    'ok'      => true,
    'text'    => $text,
    'formel'  => $formel,
    'richtig' => $richtig,
    'dauer'   => $dauer,
]);
