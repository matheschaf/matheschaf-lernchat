<?php

declare(strict_types=1);

/**
 * Login-Gerüst (E0): ein Lehrer-Passwort, Session, CSRF-Schutz.
 *
 * Entscheidung aus NEU.md, Abschnitt 9: für den Start ein einzelnes
 * Lehrer-Passwort (Hash in inc/config.local.php bzw. inc/config.live.php).
 * Konten (ms_lehrer) kommen in E5, die Funktionen hier bleiben dann bestehen.
 */

/**
 * Startet die Session (ein Mal pro Aufruf, Cookie nur serverseitig lesbar).
 */
function auth_starten(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_name('ms_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/**
 * Ist die Lehrkraft angemeldet?
 */
function lehrer_ist_angemeldet(): bool
{
    auth_starten();

    return !empty($_SESSION['lehrer_angemeldet']);
}

/**
 * Prüft das Passwort gegen den Hash aus der Konfiguration.
 */
function lehrer_passwort_pruefen(string $passwort): bool
{
    if (!defined('LEHRER_PASSWORT_HASH') || LEHRER_PASSWORT_HASH === ''
        || LEHRER_PASSWORT_HASH === 'BITTE_AUSFUELLEN') {
        return false;
    }

    return password_verify($passwort, LEHRER_PASSWORT_HASH);
}

/**
 * Meldet die Lehrkraft an (Session-ID neu, damit alte IDs ungültig werden).
 */
function lehrer_anmelden(): void
{
    auth_starten();
    session_regenerate_id(true);
    $_SESSION['lehrer_angemeldet'] = true;
    $_SESSION['lehrer_angemeldet_am'] = date('Y-m-d H:i:s');
}

/**
 * Meldet ab und räumt die Session auf.
 */
function lehrer_abmelden(): void
{
    auth_starten();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $parameter = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $parameter['path'],
            'secure'   => (bool) $parameter['secure'],
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    session_destroy();
}

/**
 * Bricht den Aufruf ab und leitet zum Login (mit Rückweg) um.
 *
 * @param string $weiter Ziel nach erfolgreichem Login, z. B. BASE_URL . 'health.php'
 */
function login_erzwingen(string $weiter = ''): void
{
    $ziel = BASE_URL . 'login.php';
    if ($weiter !== '') {
        $ziel .= '?weiter=' . rawurlencode($weiter);
    }
    header('Location: ' . $ziel, true, 302);
    exit;
}

/**
 * Schutz für alle Verwaltungsseiten: ohne Login geht es zur Anmeldung,
 * mit Rückkehr auf genau diese Seite.
 */
function verwaltung_erzwingen(): void
{
    if (lehrer_ist_angemeldet()) {
        return;
    }

    $weiter = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    login_erzwingen($weiter);
}

/**
 * CSRF-Token der aktuellen Session (wird beim ersten Aufruf erzeugt).
 */
function csrf_token(): string
{
    auth_starten();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Prüft einen übermittelten CSRF-Token in konstanter Zeit.
 */
function csrf_pruefen(?string $token): bool
{
    auth_starten();

    return is_string($token) && $token !== ''
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Nur Ziele innerhalb dieser Installation als „weiter“-Adresse akzeptieren
 * (kein offener Redirect). Erlaubt sind Pfade wie /lernchat/health.php sowie
 * absolute Adressen derselben Installation, nicht aber //fremde-domain/…
 */
function auth_weiter_ziel(?string $weiter, string $rueckfall): string
{
    if (!is_string($weiter) || $weiter === '') {
        return $rueckfall;
    }

    $teile = parse_url($weiter);
    if ($teile === false) {
        return $rueckfall;
    }

    $basis = parse_url(BASE_URL);
    if ($basis === false || empty($basis['host'])) {
        return $rueckfall;
    }
    $basisPfad = rtrim((string) ($basis['path'] ?? '/'), '/') . '/';

    // Absolute Adresse: muss auf dieselbe Installation zeigen.
    if (!empty($teile['host'])) {
        $gleicherHost = strcasecmp((string) $teile['host'], (string) $basis['host']) === 0
            && (string) ($teile['scheme'] ?? '') === (string) ($basis['scheme'] ?? '')
            && (int) ($teile['port'] ?? 0) === (int) ($basis['port'] ?? 0);
        if (!$gleicherHost) {
            return $rueckfall;
        }

        $pfad = (string) ($teile['path'] ?? '/');
        $abfrage = isset($teile['query']) ? '?' . $teile['query'] : '';

        return str_starts_with($pfad, $basisPfad) ? $pfad . $abfrage : $rueckfall;
    }

    // Relative Adresse: nur Pfade innerhalb des Projekts.
    if (!str_starts_with($weiter, '/') || str_starts_with($weiter, '//')) {
        return $rueckfall;
    }

    return str_starts_with($weiter, $basisPfad) ? $weiter : $rueckfall;
}
