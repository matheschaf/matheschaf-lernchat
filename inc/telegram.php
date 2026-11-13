<?php

declare(strict_types=1);

/**
 * Telegram-Benachrichtigungen an die Lehrkraft.
 *
 * Gesendet wird nur, wenn der Schüler der Telegram-Benachrichtigung
 * zugestimmt hat (ms_schueler.ok_telegram = 1) – so steht es in der
 * Einwilligung und in der Datenschutzerklärung (Punkt 9).
 *
 * Einbinden nach inc/config.php und inc/chat.php (BASE_URL, schueler_name).
 */

/** Ist der Bot eingerichtet (Token + Chat-ID vorhanden)? */
function telegram_bereit(): bool
{
    return TELEGRAM_BOT_TOKEN !== '' && TELEGRAM_CHAT_ID !== '' && function_exists('curl_init');
}

/**
 * Schickt eine Nachricht an den Chat der Lehrkraft.
 * Fehler dürfen den Schüler-Chat nicht stören – deshalb immer nur true/false.
 *
 * @param string $parseMode '' (reiner Text) oder 'HTML'
 */
function telegram_senden(string $text, string $chatId = '', int $timeout = 6, string $parseMode = 'HTML'): bool
{
    if (!telegram_bereit() || trim($text) === '') {
        return false;
    }

    $daten = [
        'chat_id'                  => $chatId !== '' ? $chatId : TELEGRAM_CHAT_ID,
        'text'                     => $text,
        'disable_web_page_preview' => 'true',
    ];

    if ($parseMode !== '') {
        $daten['parse_mode'] = $parseMode;
    }

    $ch = curl_init(TELEGRAM_API . TELEGRAM_BOT_TOKEN . '/sendMessage');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($daten),
        CURLOPT_TIMEOUT        => $timeout,
        // Wie bei der KI: XAMPP hat kein CA-Bundle.
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ]);

    $antwort = curl_exec($ch);
    curl_close($ch);

    if ($antwort === false) {
        return false;
    }

    $ergebnis = json_decode((string) $antwort, true);

    return is_array($ergebnis) && ($ergebnis['ok'] ?? false) === true;
}

/**
 * Bremst Wiederholungen: true = jetzt senden, false = zu kurz her.
 */
function telegram_drosseln(string $schluessel, int $sekunden = 60): bool
{
    $datei = sys_get_temp_dir() . '/matheschaf-telegram-' . md5($schluessel) . '.txt';
    $jetzt = time();

    if (is_file($datei)) {
        $letzte = (int) file_get_contents($datei);

        if ($letzte > $jetzt - $sekunden) {
            return false;
        }
    }

    @file_put_contents($datei, (string) $jetzt, LOCK_EX);

    return true;
}

/**
 * Grobe Mengenbegrenzung je Schlüssel (z. B. IP-Adresse):
 * true = erlaubt, false = Grenze im Zeitfenster erreicht.
 */
function telegram_haeufigkeit(string $schluessel, int $max = 300, int $sekunden = 3600): bool
{
    $datei = sys_get_temp_dir() . '/matheschaf-telegram-zaehler-' . md5($schluessel) . '.json';
    $jetzt = time();
    $stand = ['start' => $jetzt, 'anzahl' => 0];

    if (is_file($datei)) {
        $roh = json_decode((string) file_get_contents($datei), true);

        if (is_array($roh) && isset($roh['start'], $roh['anzahl'])) {
            $stand = ['start' => (int) $roh['start'], 'anzahl' => (int) $roh['anzahl']];
        }
    }

    if ($jetzt - $stand['start'] >= $sekunden) {
        $stand = ['start' => $jetzt, 'anzahl' => 0];
    }

    if ($stand['anzahl'] >= $max) {
        return false;
    }

    $stand['anzahl']++;
    @file_put_contents($datei, (string) json_encode($stand), LOCK_EX);

    return true;
}

/**
 * Kurze Meldung: „🐑 Michael – Challenge gestartet".
 *
 * Bewusst NUR der Vorname: keine Kennung, kein Baustein, keine Stufe,
 * keine Punkte, kein Link.
 */
function telegram_kurz(string $emoji, string $vorname, string $was): bool
{
    $text = $emoji . ' ' . htmlspecialchars($vorname, ENT_QUOTES, 'UTF-8') . ' – ' . $was;

    return telegram_senden($text, '', 4);
}

/**
 * Darf für diesen Schüler jetzt eine „Start"-Meldung raus?
 * (Einwilligung vorhanden + höchstens 1× je Minute – egal ob Übung,
 * Quiz oder Challenge, damit ein Neuladen keine Lawine auslöst.)
 *
 * @param array<string, mixed> $student
 */
function telegram_start_erlaubt(array $student, string $id): bool
{
    return (int) ($student['ok_telegram'] ?? 0) === 1
        && telegram_bereit()
        && telegram_drosseln('start-' . $id, 60);
}

/**
 * Meldung „Übung/Quiz gestartet" (nur mit Einwilligung, max. 1× je Minute).
 *
 * @param array<string, mixed> $student Schülerzeile aus der DB
 */
function telegram_chat_start(array $student, string $id, string $modus = 'lern'): bool
{
    if (!telegram_start_erlaubt($student, $id)) {
        return false;
    }

    return $modus === 'quiz'
        ? telegram_kurz('🎬', schueler_vorname($student), 'Quiz gestartet')
        : telegram_kurz('🐑', schueler_vorname($student), 'Übung gestartet');
}

/**
 * Meldung „Challenge gestartet" (Sprung-Spiel aus der Lobby).
 *
 * @param array<string, mixed> $student Schülerzeile aus der DB
 */
function telegram_challenge_start(array $student, string $id): bool
{
    if (!telegram_start_erlaubt($student, $id)) {
        return false;
    }

    return telegram_kurz('🐑', schueler_vorname($student), 'Challenge gestartet');
}

/**
 * Meldung „Ziel erreicht" – höchstens 1× je 30 s und Schüler.
 *
 * @param array<string, mixed> $student Schülerzeile aus der DB
 */
function telegram_ziel_erreicht(array $student, string $id): bool
{
    if ((int) ($student['ok_telegram'] ?? 0) !== 1 || !telegram_bereit()) {
        return false;
    }

    if (!telegram_drosseln('ziel-' . $id, 30)) {
        return false;
    }

    return telegram_kurz('🎉', schueler_vorname($student), 'Ziel erreicht');
}
