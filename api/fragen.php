<?php

declare(strict_types=1);

/**
 * POST api/fragen.php — drei Fragen mit je zwei Antworten zu einem Stichwort.
 *
 * Gedacht für die Challenge (/challenge/), gleiche Domain, kein Login.
 * Reihenfolge:
 *   1. Ist das Thema in ms_fragen freigegeben? → von dort (keine KI, kein Geld).
 *   2. Sonst: KI erzeugen, prüfen, zurückgeben — NICHT speichern.
 * Gespeichert wird nur über api/fragen_speichern.php (Lehrkraft, Login).
 *
 * Body:  thema (2–60 Zeichen), neu=1 (Datenbank übergehen, frisch erzeugen),
 *        schueler (optional, vierstellige Kennung → Telegram an die Lehrkraft)
 * Antwort: { ok, thema, fragen: [{frage, antworten: [a, b], richtig: 0|1}],
 *            quelle: 'db'|'ki', dauer }
 */

// Fehler (auch fehlende Dateien) dürfen keine weiße Seite ergeben: Der Handler
// wird VOR den require-Zeilen gesetzt und antwortet immer mit JSON.
@ini_set('display_errors', '0');

register_shutdown_function(static function (): void {
    $fehler = error_get_last();

    if ($fehler === null || !in_array($fehler['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }

    echo json_encode([
        'ok'     => false,
        'fehler' => 'Serverfehler: ' . $fehler['message'],
    ], JSON_UNESCAPED_UNICODE);
});

require dirname(__DIR__) . '/inc/config.php';
require dirname(__DIR__) . '/inc/chat.php';
require dirname(__DIR__) . '/inc/ki.php';
require dirname(__DIR__) . '/inc/auth.php';
require dirname(__DIR__) . '/inc/fragen.php';
require dirname(__DIR__) . '/inc/telegram.php';

auth_starten();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    // Selbstdiagnose im Browser: …/api/fragen.php?diag=1
    if (isset($_GET['diag'])) {
        json_response([
            'ok'              => true,
            'php'             => PHP_VERSION,
            'tabelle'         => fragen_tabelle_bereit(),
            'ki_datei'        => is_file(dirname(__DIR__) . '/inc/ki.php'),
            'ki_optionen'     => (static function (): bool {
                if (!function_exists('ki_generate')) {
                    return false;
                }

                return (new ReflectionFunction('ki_generate'))->getNumberOfParameters() >= 3;
            })(),
            'ki_key'          => defined('KI_API_KEY') && KI_API_KEY !== '',
            'curl'            => function_exists('curl_init'),
            'temp_schreibbar' => is_writable(sys_get_temp_dir()),
            'session'         => session_status() === PHP_SESSION_ACTIVE,
            'umgebung'        => defined('MS_UMGEBUNG') ? MS_UMGEBUNG : '',
        ], 200);
    }

    json_response(['ok' => false, 'fehler' => 'Nur POST erlaubt.'], 405);
}

// ---- Thema prüfen ----------------------------------------------------------

$thema = fragen_thema((string) ($_POST['thema'] ?? ''));

if (mb_strlen($thema) < 2) {
    json_response(['ok' => false, 'fehler' => 'Bitte ein Thema angeben (mindestens 2 Zeichen).'], 422);
}

// Buchstaben, Ziffern, Leerzeichen und harmlose Zeichen – kein Markup, kein Code.
if (preg_match('/^[\p{L}\p{N} \-\+\/\(\).,]{2,60}$/u', $thema) !== 1) {
    json_response(['ok' => false, 'fehler' => 'Das Thema enthält unerlaubte Zeichen.'], 422);
}

// ---- Meldung an die Lehrkraft: Challenge gestartet --------------------------
// Die Challenge schickt ihre Kennung mit. Gemeldet wird nur der Vorname,
// höchstens 1× je Minute und Schüler (inc/telegram.php), zusätzlich gebremst
// auf 300 Meldungen je IP und Stunde. Fehler dürfen hier nichts aufhalten.

$schuelerId = trim((string) ($_POST['schueler'] ?? ''));

if (preg_match('/^\d{4}$/', $schuelerId) === 1
    && telegram_haeufigkeit('challenge-ip-' . (string) ($_SERVER['REMOTE_ADDR'] ?? ''), 300, 3600)
) {
    $schueler = schueler_laden($schuelerId);

    if ($schueler !== null) {
        telegram_challenge_start($schueler, $schuelerId);
    }
}

// ---- 1. Freigegebene Fragen aus der Datenbank ------------------------------
$neu = (string) ($_POST['neu'] ?? '') === '1';

if (!$neu) {
    $gespeichert = fragen_aus_db($thema);

    if ($gespeichert !== []) {
        json_response([
            'ok'     => true,
            'thema'  => $thema,
            'fragen' => $gespeichert,
            'quelle' => 'db',
            'dauer'  => 0,
        ]);
    }
}

// ---- Stunden- und Tageslimit (nur echte KI-Aufrufe zählen) ----------------

$jetzt    = time();
$anfragen = array_values(array_filter(
    (array) ($_SESSION['fragen_anfragen'] ?? []),
    static fn($zeitpunkt): bool => is_int($zeitpunkt) && $zeitpunkt > $jetzt - 3600
));

if (count($anfragen) >= 20) {
    json_response([
        'ok' => false,
        'fehler' => 'Zu viele Anfragen in der letzten Stunde. Bitte später noch einmal versuchen.',
    ], 429);
}

$zaehlerDatei = sys_get_temp_dir() . '/matheschaf-fragen-zaehler.json';
$heute        = date('Y-m-d');
$zaehler      = ['tag' => $heute, 'anzahl' => 0];

if (is_file($zaehlerDatei)) {
    $gespeichert = json_decode((string) file_get_contents($zaehlerDatei), true);

    if (is_array($gespeichert) && ($gespeichert['tag'] ?? '') === $heute) {
        $zaehler['anzahl'] = (int) ($gespeichert['anzahl'] ?? 0);
    }
}

if ($zaehler['anzahl'] >= 300) {
    json_response([
        'ok' => false,
        'fehler' => 'Für heute ist das Tageslimit erreicht. Bitte morgen noch einmal versuchen.',
    ], 429);
}

// ---- 2. Sonst selbst erzeugen --------------------------------------------

$system = 'Du stellst Multiple-Choice-Fragen für eine Mathe-Lern-App (Klassen 5 bis 13). '
    . 'Der Nutzer nennt ein Stichwort. Erfinde dazu GENAU DREI Fragen mit je GENAU ZWEI Antworten: '
    . 'eine richtig, eine falsch. Regeln: '
    . 'Die Fragen sind kurz, eindeutig und fachlich korrekt. '
    . 'Die falsche Antwort muss plausibel klingen (typischer Fehler), nicht albern sein. '
    . 'Antworten höchstens 60 Zeichen, ohne Erklärung. '
    . 'Kein LaTeX, keine Dollarzeichen: Formeln als Klartext mit Unicode (², ³, √, π, ·, ≤, ≠). '
    . '"richtig" ist der Index der korrekten Antwort (0 oder 1); verteile 0 und 1 gemischt. '
    . 'Sprache: Deutsch. '
    . 'Antworte AUSSCHLIESSLICH mit diesem JSON, ohne Code-Fences und ohne Text davor oder danach: '
    . '{"fragen":[{"frage":"…","antworten":["…","…"],"richtig":0},'
    . '{"frage":"…","antworten":["…","…"],"richtig":1},'
    . '{"frage":"…","antworten":["…","…"],"richtig":0}]}';

$dauer   = 0.0;
$fragen  = [];
$versuche = 0;

// Zwei Versuche: der zweite mit ruhigerem Zufallswert, falls die Antwort unsauber war.
while ($versuche < 2 && count($fragen) < 3) {
    $versuche++;
    $start = microtime(true);
    $roh   = ki_generate($system, 'Stichwort: ' . $thema, [
        'temperature' => $versuche === 1 ? 0.9 : 0.5,
        'max_tokens'  => 900,
    ]);
    $dauer += round(microtime(true) - $start, 1);

    if ($roh === null) {
        continue;
    }

    $zaehler['anzahl']++;
    @file_put_contents($zaehlerDatei, json_encode($zaehler), LOCK_EX);

    $fragen = fragen_pruefen($roh);
}

if ($fragen === []) {
    json_response([
        'ok' => false,
        'fehler' => 'Die KI hat nicht geantwortet (Zeitüberschreitung oder Fehler).',
    ], 502);
}

if (count($fragen) < 3) {
    json_response([
        'ok'     => false,
        'fehler' => 'Die Antwort der KI war unvollständig. Bitte noch einmal versuchen.',
        'anzahl' => count($fragen),
    ], 502);
}

$anfragen[] = $jetzt;
$_SESSION['fragen_anfragen'] = $anfragen;

json_response([
    'ok'       => true,
    'thema'    => $thema,
    'fragen'   => $fragen,
    'quelle'   => 'ki',
    'dauer'    => round($dauer, 1),
    'speicherbar' => true,
]);

