<?php

declare(strict_types=1);

/**
 * POST api/fragen_speichern.php — Lehrkraft genehmigt drei Fragen zu einem Thema.
 *
 * Nur mit lernchat-Login (das Challenge-Projekt hat keinen eigenen Login).
 * Ein vorhandener Satz desselben Themas wird ersetzt: pro Thema gibt es genau
 * drei Fragen. Löschen/Ablehnen braucht es nicht – ohne Klick wird nichts
 * gespeichert.
 *
 * CSRF: Das Sitzungscookie ist SameSite=Lax, ein fremder POST bekommt die
 * Sitzung also nicht mit. Deshalb genügt hier der Login-Check.
 *
 * Body:  thema, fragen (JSON: [{frage, antworten: [a, b], richtig: 0|1}, …])
 * Antwort: { ok, thema, anzahl }
 */

require dirname(__DIR__) . '/inc/config.php';
require dirname(__DIR__) . '/inc/chat.php';
require dirname(__DIR__) . '/inc/auth.php';
require dirname(__DIR__) . '/inc/fragen.php';

@ini_set('display_errors', '0');

auth_starten();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    json_response(['ok' => false, 'fehler' => 'Nur POST erlaubt.'], 405);
}

if (!lehrer_ist_angemeldet()) {
    json_response([
        'ok'    => false,
        'fehler' => 'Bitte zuerst in lernchat anmelden – dann noch einmal auf „Genehmigen“ drücken.',
        'login' => BASE_URL . 'login.php',
    ], 403);
}

// ---- Thema -----------------------------------------------------------------

$thema = fragen_thema((string) ($_POST['thema'] ?? ''));

if (mb_strlen($thema) < 2) {
    json_response(['ok' => false, 'fehler' => 'Bitte ein Thema angeben.'], 422);
}

if (preg_match('/^[\p{L}\p{N} \-\+\/\(\).,]{2,60}$/u', $thema) !== 1) {
    json_response(['ok' => false, 'fehler' => 'Das Thema enthält unerlaubte Zeichen.'], 422);
}

// ---- Fragen ----------------------------------------------------------------

$roh   = (string) ($_POST['fragen'] ?? '');
$daten = json_decode($roh, true);

if (!is_array($daten)) {
    json_response(['ok' => false, 'fehler' => 'Die Fragen konnten nicht gelesen werden.'], 422);
}

$fragen = fragen_pruefen_daten(['fragen' => $daten]);

if (count($fragen) !== 3) {
    json_response([
        'ok'     => false,
        'fehler' => 'Es müssen genau drei gültige Fragen sein (je zwei Antworten, eine richtig).',
        'anzahl' => count($fragen),
    ], 422);
}

// ---- Speichern -------------------------------------------------------------

if (!fragen_tabelle_bereit()) {
    json_response([
        'ok'     => false,
        'fehler' => 'Die Tabelle ms_fragen fehlt – bitte zuerst die Migration 003 ausführen.',
    ], 500);
}

if (!fragen_speichern($thema, $fragen)) {
    json_response(['ok' => false, 'fehler' => 'Speichern fehlgeschlagen.'], 500);
}

json_response([
    'ok'     => true,
    'thema'  => $thema,
    'anzahl' => count($fragen),
    'fragen' => $fragen,
]);
