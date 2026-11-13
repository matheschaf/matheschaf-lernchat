<?php

declare(strict_types=1);

/**
 * SMS und Anruf über seven.io (aus dem Altstand übernommen).
 *
 * Wird nur von api/sms.php und api/call.php genutzt und setzt voraus, dass die
 * Einwilligung des Schülers geprüft wurde (ok_sms / ok_anruf).
 * Einbinden nach inc/config.php.
 */

/**
 * Normalisiert eine Rufnummer auf sieben.io-Format (ohne +, mit Ländervorwahl).
 * null = unbrauchbar.
 */
function seven_nummer(string $nummer): ?string
{
    $n = (string) preg_replace('/[^0-9+]/', '', $nummer);

    if ($n === '') {
        return null;
    }

    if (str_starts_with($n, '00')) {
        $n = substr($n, 2);
    } elseif (str_starts_with($n, '+')) {
        $n = ltrim($n, '+');
    } elseif (str_starts_with($n, '0')) {
        $n = '49' . substr($n, 1); // deutsche nationale Schreibweise
    }

    return preg_match('/^[1-9]\d{7,14}$/', $n) ? $n : null;
}

/**
 * Wertet die Antwort von seven.io aus.
 * Rückgabe: ['ok' => bool, 'error' => string, 'id' => string]
 */
function seven_antwort_lesen(string $antwort, int $status): array
{
    $daten = json_decode($antwort, true);

    if (is_array($daten)) {
        $message = (isset($daten['messages'][0]) && is_array($daten['messages'][0])) ? $daten['messages'][0] : null;

        if ($message !== null) {
            if (($message['success'] ?? false) === true) {
                return ['ok' => true, 'error' => '', 'id' => (string) ($message['id'] ?? '')];
            }

            $fehler = (string) ($message['error_text'] ?? '');

            if ($fehler === '' && isset($message['error'])) {
                $fehler = 'Fehlercode ' . $message['error'];
            }

            return ['ok' => false, 'error' => $fehler !== '' ? $fehler : 'Versand fehlgeschlagen', 'id' => ''];
        }

        $code = $daten['success'] ?? null;

        if ($code === true || $code === '100' || $code === 100) {
            return ['ok' => true, 'error' => '', 'id' => (string) ($daten['id'] ?? '')];
        }

        return [
            'ok'    => false,
            'error' => is_scalar($code) ? 'Fehlercode ' . $code : 'Versand fehlgeschlagen',
            'id'    => '',
        ];
    }

    // Klartext-Antwort: "100", "100 <id>" oder ein Fehlercode wie "202".
    $klar = trim($antwort);

    if (preg_match('/^100(?:\s+(\S+))?/', $klar, $m)) {
        return ['ok' => true, 'error' => '', 'id' => $m[1] ?? ''];
    }

    if ($klar !== '') {
        return ['ok' => false, 'error' => 'seven-Code ' . $klar, 'id' => ''];
    }

    return ['ok' => false, 'error' => 'Unbekannte Antwort (HTTP ' . $status . ')', 'id' => ''];
}

/**
 * Sendet eine Flash-SMS über seven.io.
 * Rückgabe: ['ok' => bool, 'error' => string, 'id' => string]
 */
function seven_flash_sms(string $nummer, string $text): array
{
    $to = seven_nummer($nummer);

    if ($to === null) {
        return ['ok' => false, 'error' => 'Ungültige oder fehlende Rufnummer.', 'id' => ''];
    }

    if (!function_exists('curl_init')) {
        return ['ok' => false, 'error' => 'curl ist nicht verfügbar.', 'id' => ''];
    }

    $felder = ['to' => $to, 'text' => $text, 'flash' => '1'];

    if (SEVEN_FROM !== '') {
        $felder['from'] = SEVEN_FROM;
    }

    $ch = curl_init(SEVEN_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($felder),
        CURLOPT_HTTPHEADER     => [
            'X-Api-Key: ' . SEVEN_API_KEY,
            // JSON liefert die Message-ID und klare Fehlertexte.
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded',
        ],
        CURLOPT_TIMEOUT        => 20,
        // Lokales XAMPP ohne CA-Bundle → SSL-Prüfung deaktivieren.
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ]);

    $antwort = curl_exec($ch);
    $status  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($antwort === false) {
        return ['ok' => false, 'error' => 'Keine Verbindung zu seven.io.', 'id' => ''];
    }

    return seven_antwort_lesen((string) $antwort, $status);
}
/**
 * Wählt zufällig eine Ansage und liefert sie als Klartext (für die Anzeige)
 * und als SSML (weibliche Stimme, etwas langsamer, kurze Pause nach dem Namen).
 * Rückgabe: ['text' => string, 'ssml' => string]
 */
function anruf_ansage(string $vorname): array
{
    $vorlagen = ANRUF_TEXTE;
    $vorlage  = $vorlagen[random_int(0, count($vorlagen) - 1)];
    $teile    = explode('{P}', $vorlage);

    $nameXml = htmlspecialchars($vorname, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    // ANRUF_WORT_SPRACHE darf SSML-Markup enthalten (feste Konstante, keine Nutzereingabe).
    $wortXml = ANRUF_WORT_SPRACHE;

    $klar = [];
    $ssml = [];

    foreach ($teile as $teil) {
        $klar[] = trim(sprintf($teil, $vorname, ANRUF_WORT_ANZEIGE));
        $ssml[] = trim(sprintf(htmlspecialchars($teil, ENT_XML1 | ENT_QUOTES, 'UTF-8'), $nameXml, $wortXml));
    }

    return [
        'text' => implode(' ', $klar),
        'ssml' => '<speak version="1.0" xmlns="http://www.w3.org/2001/10/synthesis" xml:lang="de-DE">'
            . '<voice name="' . ANRUF_STIMME . '">'
            . '<prosody rate="' . ANRUF_RATE . '">'
            . implode(' ' . ANRUF_PAUSE . ' ', $ssml)
            . '</prosody></voice></speak>',
    ];
}

/**
 * Startet einen TTS-Anruf über seven.io (liest den Text vor).
 * Rückgabe: ['ok' => bool, 'error' => string, 'id' => string]
 */
function seven_anruf(string $nummer, string $text): array
{
    $to = seven_nummer($nummer);

    if ($to === null) {
        return ['ok' => false, 'error' => 'Ungültige oder fehlende Rufnummer.', 'id' => ''];
    }

    if (!function_exists('curl_init')) {
        return ['ok' => false, 'error' => 'curl ist nicht verfügbar.', 'id' => ''];
    }

    $felder = ['to' => $to, 'text' => $text];

    if (SEVEN_FROM !== '') {
        $felder['from'] = SEVEN_FROM;
    }

    $ch = curl_init(SEVEN_VOICE_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($felder),
        CURLOPT_HTTPHEADER     => [
            'X-Api-Key: ' . SEVEN_API_KEY,
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded',
        ],
        CURLOPT_TIMEOUT        => 20,
        // Lokales XAMPP ohne CA-Bundle → SSL-Prüfung deaktivieren.
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ]);

    $antwort = curl_exec($ch);
    $status  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($antwort === false) {
        return ['ok' => false, 'error' => 'Keine Verbindung zu seven.io.', 'id' => ''];
    }

    return seven_antwort_lesen((string) $antwort, $status);
}
