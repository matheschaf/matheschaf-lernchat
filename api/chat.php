<?php

declare(strict_types=1);

/**
 * POST api/chat.php — Schüler-Chat und Quiz (E2), jetzt mit Datenbank.
 *
 * Felder: id, stufe, action (start|send), text, formel, quiz (1 = Quiz-Modus)
 * Antwort: {ok, ai:{text,formel}, bonuspunkte, schafe_neu, ziel_erreicht,
 *           richtig, modus} — gleicher Vertrag wie im Altstand, damit
 *           assets/chat.js unverändert funktioniert.
 *
 * Der Start einer Übung leert bewusst den Verlauf dieses Kontexts
 * (chat oder quiz) – so war es im Altstand gewollt.
 */

require dirname(__DIR__) . '/inc/config.php';
require dirname(__DIR__) . '/inc/chat.php';
require dirname(__DIR__) . '/inc/ki.php';
require dirname(__DIR__) . '/inc/telegram.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    json_response(['ok' => false, 'error' => 'Nur POST erlaubt.'], 405);
}

$id     = (string) ($_POST['id'] ?? '');
$stufe  = (string) ($_POST['stufe'] ?? 'leicht');
$action = (string) ($_POST['action'] ?? 'send');
$text   = normalize_text((string) ($_POST['text'] ?? ''));
$formel = trim((string) ($_POST['formel'] ?? ''));

if (!in_array($stufe, ['leicht', 'mittel', 'schwer', 'zufall'], true)) {
    $stufe = 'leicht';
}

// Zwei Modi: „lernen“ (Chat) und „quiz“ (4. Würfelfläche „Teste dein Wissen“).
$modus    = (($_POST['quiz'] ?? '') === '1') ? 'quiz' : 'lernen';
$slugFeld = $modus === 'quiz' ? 'quiz_baustein' : 'aktiver_baustein';
$kontext  = $modus === 'quiz' ? 'quiz' : 'chat';

if ($action !== 'start' && $text === '' && $formel === '') {
    json_response(['ok' => false, 'fehler' => 'Leere Nachricht.'], 422);
}

if (mb_strlen($text) > MAX_MESSAGE_LENGTH) {
    json_response([
        'ok' => false,
        'fehler' => 'Die Nachricht ist zu lang (max. ' . MAX_MESSAGE_LENGTH . ' Zeichen).',
    ], 422);
}

$student = schueler_laden($id);
if ($student === null) {
    json_response(['ok' => false, 'fehler' => 'Schüler nicht gefunden.'], 404);
}

$bausteinSlug = (string) ($student[$slugFeld] ?? '');
$baustein     = $bausteinSlug !== '' ? baustein_laden($bausteinSlug) : null;

// Start: Verlauf dieses Kontexts leeren, Stufe/Startzeit setzen.
if ($action === 'start') {
    uebung_starten($id, $kontext, $stufe);

    // Lehrkraft per Telegram informieren – nur mit ok_telegram, max. 1×/Minute,
    // Inhalt: nur der Vorname („🐑 Michael – Übung gestartet").
    // Fehler (Bot aus, kein Netz) dürfen den Chat nicht aufhalten.
    telegram_chat_start($student, $id, $modus);
}

// Ohne Prompt kann die KI nicht antworten: klarer Fehler statt leerem Chat.
if ($baustein === null || trim((string) ($baustein['prompt'] ?? '')) === '') {
    json_response([
        'ok'     => false,
        'fehler' => ($baustein === null
            ? 'Für dich ist noch keine Aufgabe freigeschaltet.'
            : 'Für diesen Baustein fehlt noch der Prompt.')
            . ' Bitte sag deinem Lehrer Bescheid.',
    ], 409);
}

// Referenzbild des Bausteins: zeigt die Seite links an und geht an die KI.
$bildPfad = baustein_referenzbild($baustein);
$bildUrl  = $bildPfad !== '' ? bild_als_data_url($bildPfad) : null;

$bildInfo = $bildUrl !== null
    ? 'Der Schüler sieht links im Chat ein Referenzbild zur Aufgabe. Beziehe dich darauf, wenn er es erwähnt (z. B. markierte Linien oder Beschriftungen).'
    : '';

$prompt = fill_prompt_placeholders(
    (string) $baustein['prompt'],
    schueler_vorname($student),
    $stufe,
    $bildInfo
);

/* Formel-Regel garantieren: nur LaTeX, das die App auch darstellt
   (sonst erscheinen Befehle wie \dots als Rohtext mit Wellenlinie). */
$prompt = prompt_formel_regel_sichern($prompt);

if ($action === 'start') {
    // Die KI eröffnet den Dialog (keine Schülernachricht).
    $messages = [
        ['role' => 'system', 'content' => $prompt],
        ['role' => 'user', 'content' => 'Stelle jetzt sofort die erste Aufgabe zur gewählten Stufe. Keine Begrüßung, keine Floskeln – komm direkt zur Sache.'],
    ];
} else {
    // Schülernachricht zuerst festschreiben (auch wenn die KI danach ausfällt).
    nachricht_speichern($id, $kontext, 'an_lehrer', $text, $formel);

    $messages = [['role' => 'system', 'content' => $prompt]];
    foreach (verlauf_zu_messages(nachrichten_laden($id, $kontext, 50)) as $m) {
        $messages[] = $m;
    }
}

// Bild an die aktuelle Schüler-Nachricht hängen (multimodal).
if ($bildUrl !== null) {
    $messages = ki_bild_anhaengen($messages, $bildUrl);
}

$resp = ki_chat($messages);

// Kann der Endpunkt keine Bilder verarbeiten? Dann ohne Bild erneut.
if ($resp === null && $bildUrl !== null) {
    $resp = ki_chat(ki_bild_entfernen($messages));
}
$aiText       = '';
$aiFormel     = '';
$schafeNeu    = 0;
$zielErreicht = false;
$richtig      = null;

if ($resp !== null) {
    $resp = trim($resp);

    // Marker zuerst aus der Roh-Antwort lesen – clean_ai_text entfernt sie.
    $marker       = ki_marker_lesen($resp);
    $schafeNeu    = $marker['schafe'];
    $zielErreicht = $marker['ziel'];

    // Quiz-Auswertung: die KI markiert richtige/falsche Antworten.
    if (str_contains($resp, '[[RICHTIG]]')) {
        $richtig = true;
    } elseif (str_contains($resp, '[[FALSCH]]')) {
        $richtig = false;
    }

    // Robust lesen: Code-Fences, Text drumherum und unescapte Backslashes.
    $decoded = ki_antwort_lesen($resp);

    if (is_array($decoded)) {
        $aiText   = clean_ai_text((string) ($decoded['text'] ?? ''));
        $aiFormel = formel_saeubern((string) ($decoded['formel'] ?? ''));
    } else {
        // Kein JSON: reiner Text oder kaputte Antwort.
        $aiText = clean_ai_text($resp);
    }

    // Nichts Unbrauchbares ans Kind geben: leere Antwort, reiner Zeichenmüll
    // oder ein durchgerutschter JSON-Blob werden ersetzt.
    if ($aiFormel === '' && (
        $aiText === ''
        || !preg_match('/\p{L}/u', $aiText)
        || preg_match('/^\s*\{.*"text"\s*:/s', $aiText) === 1
    )) {
        $aiText = 'Ich habe mich verhaspelt. Frag mich bitte noch einmal.';
    }

    if ($aiText !== '' || $aiFormel !== '') {
        nachricht_speichern($id, $kontext, 'an_schueler', $aiText, $aiFormel, $richtig);
    }
}

// Belohnung: [[SCHAF]] → je Marker ein Bonuspunkt (und ein Schaf mehr).
$bonuspunkte = (int) ($student['bonuspunkte'] ?? 0);
if ($schafeNeu > 0) {
    $bonuspunkte = bonuspunkte_addieren($id, $schafeNeu);
}

// [[ZIEL ERREICHT]] → Baustein in der Statusliste als geschafft eintragen.
if ($zielErreicht) {
    baustein_geschafft($id, $bausteinSlug);

    // Lehrkraft informieren (nur mit ok_telegram, nur der Vorname).
    telegram_ziel_erreicht($student, $id);
}

json_response([
    'ok'            => true,
    'ai'            => ['text' => $aiText, 'formel' => $aiFormel],
    'bonuspunkte'   => $bonuspunkte,
    'schafe_neu'    => $schafeNeu,
    'ziel_erreicht' => $zielErreicht,
    'richtig'       => $richtig,
    'modus'         => $modus,
]);
