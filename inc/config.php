<?php

declare(strict_types=1);

/**
 * Grundkonfiguration Matheschaf-Lernchat (Neubau).
 *
 * Diese Datei enthält bewusst KEINE Zugangsdaten. Sie lädt:
 *   - lokal: inc/config.local.php   (XAMPP, root ohne Passwort)
 *   - live:  inc/config.live.php    (All-Inkl-Zugang, wird per WinSCP hochgeladen)
 *
 * Beide Dateien sind per .htaccess gesperrt und werden nie veröffentlicht.
 * Alle Seiten binden ausschließlich diese Datei ein – Reihenfolge beachten:
 * erst inc/config.php, dann inc/db.php / inc/auth.php.
 */

date_default_timezone_set('Europe/Berlin');

// ---- Pfade -----------------------------------------------------------------
const MS_ROOT       = __DIR__ . '/..';          // Projektordner (lernchat/)
const MS_INC        = __DIR__;                  // inc/
const MS_MIGRATIONS = MS_ROOT . '/migrations';  // migrations/

// ---- App -------------------------------------------------------------------
// Name und Farbe der „App“: Browser-Tab, Startbildschirm, Statusleiste,
// Manifest und alle Seiten greifen auf diese beiden Werte zurück.
const APP_NAME        = 'Matheschaf';
const APP_THEME_COLOR = '#1b5e20';   // = --ms-green-dark (später assets/admin.css)

// ---- Datenbank -------------------------------------------------------------
// Alle Tabellen tragen dieses Präfix (eine DB bei All-Inkl, mehrere Projekte).
const DB_PRAEFIX = 'ms_';

// ---- KI (Gemini über OpenAI-kompatiblen Endpunkt) --------------------------
// Der Schlüssel steht NUR in den Zugangsdateien (inc/config.local.php bzw.
// inc/config.live.php) – niemals hier, sonst landet er in Git und auf GitHub.
const KI_BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/openai/';
const TUTOR_MODEL = 'gemini-3.8-flash';
const API_TIMEOUT = 120;

// ---- SMS und Anruf (seven.io) ----------------------------------------------
// Schlüssel ebenfalls nur in den Zugangsdateien.
const SEVEN_API_URL   = 'https://gateway.seven.io/api/sms';
const SEVEN_VOICE_URL = 'https://gateway.seven.io/api/voice';
const SEVEN_FROM      = '';                 // leer = seven-Standardabsender

// Ansage-Texte: %1$s = Vorname, %2$s = Schafwort, {P} = Sprechpause nach dem Namen.
const ANRUF_TEXTE = [
    'Hallo %1$s,{P} denk an dein Mathe %2$s!',
    'Hallo %1$s,{P} dein Mathes %2$s wartet!',
    'Hallo %1$s,{P} das Mathe %2$s langweilt sich.',
];
// Schreibweise in der Anzeige (Tooltip / Rückmeldung).
const ANRUF_WORT_ANZEIGE = 'Schaaf';
// IPA-Lautschrift: damit die Stimme „Schaf" (nicht „schaff") sagt.
const ANRUF_WORT_SPRACHE  = '<phoneme alphabet="ipa" ph="ʃaːf">Schaf</phoneme>';
const ANRUF_STIMME = 'de-de-female';
const ANRUF_RATE   = '-10%';
const ANRUF_PAUSE  = '<break time="400ms"/>';

// ---- Chat ------------------------------------------------------------------
const MAX_MESSAGE_LENGTH = 4000;   // Zeichen pro Schülernachricht
const MESSAGES_ON_LOAD   = 200;    // so viele Nachrichten zeigt der Chat

// ---- Schafswiese (rechter Flügel) -----------------------------------------
const WIESE_BILD         = 'bilder/wiese.png';
const WIESE_SCHAF_BILDER = ['bilder/schaf1.png', 'bilder/schaf2.png', 'bilder/schaf3.png'];
const WIESE_SCHAF_PLUS   = 'bilder/schafplus.png';   // kurzes „+1"-Bild
const WIESE_MAX_SCHAFE   = 60;                       // Obergrenze der Herde

/**
 * Verbindlicher Regelblock „Ziel erreicht“: zwei richtig gelöste Aufgaben in
 * Folge beenden die Übung. Wird an KI-erzeugte Prompts angehängt
 * (prompt_ziel_regel_sichern) und im Prompt-Template verwendet.
 */
const TUTOR_ZIEL_REGEL = <<<'TXT'
ZIEL ERREICHT — FESTE REGEL (gilt immer, unabhängig von der Stufe):
- Das Ziel ist erreicht, sobald der Schüler ZWEI Aufgaben nacheinander vollständig richtig gelöst hat.
- Es zählt nur eine vollständig richtige Antwort. Eine falsche, unvollständige oder unklare Antwort setzt den Zähler auf 0 zurück.
- Nach einer richtigen Lösung stellst du sofort die nächste Aufgabe desselben Typs.
- Bei der zweiten richtigen Antwort in Folge: kurz bestätigen und an 'text' exakt anhängen: [[SCHAF]] [[SCHAF]] [[SCHAF]] [[ZIEL ERREICHT]]
- Nach [[ZIEL ERREICHT]] stellst du KEINE neue Aufgabe mehr und verabschiedest dich kurz.
TXT;

/**
 * Verbindliche Regel für das Formelfeld: nur LaTeX, das die App (MathLive
 * 0.111) auch darstellt. Unbekannte Befehle erscheinen sonst als Rohtext
 * mit Wellenlinie (z. B. \dots). Wird an Prompts angehängt.
 */
const FORMEL_REGEL = <<<'TXT'
FORMEL-REGEL (verbindlich):
- "formel" enthält ausschließlich LaTeX, das ein Formel-Renderer direkt darstellt.
- Erlaubt und erwünscht: \frac{a}{b}, \sqrt{x}, x^2, x_1, \cdot, \times, \div, \pm, \le, \ge,
  \neq, \approx, \pi, \alpha … \omega, \Delta, \angle, \overline{AB}, \left( … \right),
  \mathrm{km} für Einheiten, \, und \quad für Abstände.
- Auslassungspunkte NUR als \ldots (das Kommando \dots kennt der Renderer NICHT und
  zeigt es wörtlich an) – oder die Lücke im TEXT als …
- Verboten: \dots, \def, eigene Makros, \color, \tiny … \Huge, \includegraphics,
  \begin{…}-Umgebungen (außer matrix/pmatrix), HTML.
- Einheiten im Text ausschreiben (Kilometer). Müssen sie in die Formel, dann als \mathrm{km}.
TXT;

// ---- Zugangsdaten laden ----------------------------------------------------
$ms_zugangsdatei = __DIR__ . '/config.local.php';
if (!is_file($ms_zugangsdatei)) {
    $ms_zugangsdatei = __DIR__ . '/config.live.php';
}
if (!is_file($ms_zugangsdatei)) {
    http_response_code(500);
    exit('Zugangsdaten fehlen: inc/config.local.php (lokal) bzw. inc/config.live.php (live) anlegen.');
}
require $ms_zugangsdatei;
unset($ms_zugangsdatei);

// ---- Rückfallwerte, falls die Zugangsdatei etwas nicht setzt ---------------
if (!defined('MS_UMGEBUNG')) {
    define('MS_UMGEBUNG', 'live');
}

// ---- Telegram (Benachrichtigung der Lehrkraft) -----------------------------
// Bot „Matheschaf“. Token und Chat-ID stehen NUR in den Zugangsdateien
// (inc/config.local.php bzw. inc/config.live.php), never hier.
const TELEGRAM_API = 'https://api.telegram.org/bot';

if (!defined('TELEGRAM_BOT_TOKEN')) {
    define('TELEGRAM_BOT_TOKEN', '');
}

if (!defined('TELEGRAM_CHAT_ID')) {
    define('TELEGRAM_CHAT_ID', '');
}

// ---- Zugangsschlüssel: leer, wenn die Zugangsdatei nichts setzt ------------
// (Werte stehen in inc/config.local.php bzw. inc/config.live.php)
if (!defined('KI_API_KEY')) {
    define('KI_API_KEY', '');
}

if (!defined('SEVEN_API_KEY')) {
    define('SEVEN_API_KEY', '');
}

/**
 * Öffentliche Adresse des Projekts, inkl. abschließendem Schrägstrich.
 * In Texten nie hart kodieren, sondern Platzhalter {{BASE_URL}} verwenden.
 * Lokal: http://localhost/lernchat/ – live: https://matheschaf.de/lernchat/
 */
if (!defined('BASE_URL')) {
    $ms_schema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $ms_host   = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $ms_pfad   = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
    define('BASE_URL', $ms_schema . '://' . $ms_host . $ms_pfad . '/');
    unset($ms_schema, $ms_host, $ms_pfad);
}

/**
 * Hängt den Änderungszeitpunkt einer Datei an eine CSS/JS-URL,
 * damit nach dem Ausrollen kein alter Cache im Browser hängt.
 */
function asset_version(string $relativ): string
{
    $datei = MS_ROOT . '/' . ltrim($relativ, '/');
    $stempel = is_file($datei) ? (string) filemtime($datei) : (string) time();

    return $relativ . '?v=' . $stempel;
}

/**
 * Einheitliche HTML-Ausgabe (kein Short-Echo, keine doppelten Aufrufe).
 */
function e(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
