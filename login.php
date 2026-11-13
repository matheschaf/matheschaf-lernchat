<?php

declare(strict_types=1);

/**
 * Login (Gerüst E0): ein Lehrer-Passwort.
 *
 * lokal: http://localhost/lernchat/login.php   (Startpasswort: matheschaf)
 * live:  hinter jedem Verwaltungsaufruf; starter.php leitet hierher um.
 */

require __DIR__ . '/inc/config.php';
require __DIR__ . '/inc/auth.php';

auth_starten();

$weiter  = auth_weiter_ziel($_GET['weiter'] ?? null, BASE_URL . 'starter.php');
$fehler  = '';
$absende = $_SERVER['REQUEST_METHOD'] === 'POST';

if ($absende) {
    $weiter  = auth_weiter_ziel($_POST['weiter'] ?? null, BASE_URL . 'starter.php');
    $passwort = (string) ($_POST['passwort'] ?? '');

    if (!csrf_pruefen($_POST['csrf_token'] ?? null)) {
        $fehler = 'Die Sitzung ist abgelaufen. Bitte die Seite neu laden.';
    } elseif ($passwort === '') {
        $fehler = 'Bitte das Passwort eingeben.';
    } elseif (lehrer_passwort_pruefen($passwort)) {
        lehrer_anmelden();
        header('Location: ' . $weiter, true, 302);
        exit;
    } else {
        // Kurze Pause gegen Durchprobieren von Passwörtern.
        usleep(400000);
        $fehler = 'Das Passwort stimmt nicht.';
    }
}

$bereitsAngemeldet = lehrer_ist_angemeldet();
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e(APP_NAME) ?> – Anmeldung</title>
<style>
    :root { --gruen: <?= e(APP_THEME_COLOR) ?>; }
    * { box-sizing: border-box; }
    body {
        margin: 0;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 24px 16px;
        font: 16px/1.5 system-ui, -apple-system, "Segoe UI", sans-serif;
        background: #eef3ea;
        color: #1a1a1a;
    }
    .karte {
        width: 100%;
        max-width: 360px;
        background: #fff;
        border-radius: 14px;
        padding: 24px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, .12);
    }
    h1 { font-size: 1.3rem; color: var(--gruen); margin: 0 0 4px; }
    .unterzeile { margin: 0 0 18px; font-size: .9rem; color: #555; }
    label { display: block; font-size: .9rem; margin-bottom: 6px; color: #555; }
    input[type="password"] {
        width: 100%;
        padding: 10px 12px;
        font-size: 1rem;
        border: 1px solid #c8d2c3;
        border-radius: 8px;
    }
    input[type="password"]:focus { outline: 2px solid var(--gruen); border-color: var(--gruen); }
    button {
        width: 100%;
        margin-top: 16px;
        padding: 11px 12px;
        font-size: 1rem;
        font-weight: 600;
        color: #fff;
        background: var(--gruen);
        border: 0;
        border-radius: 8px;
        cursor: pointer;
    }
    button:hover { filter: brightness(1.08); }
    .fehler {
        margin: 0 0 14px;
        padding: 9px 12px;
        font-size: .9rem;
        color: #7a1c14;
        background: #fbe9e7;
        border-radius: 8px;
    }
    .hinweis { margin: 14px 0 0; font-size: .85rem; color: #555; }
    a { color: var(--gruen); }
</style>
</head>
<body>
<main class="karte">
    <h1><?= e(APP_NAME) ?></h1>
    <p class="unterzeile">Anmeldung für die Lehrkraft</p>

    <?php if ($fehler !== ''): ?>
        <p class="fehler"><?= e($fehler) ?></p>
    <?php endif; ?>

    <?php if ($bereitsAngemeldet): ?>
        <p class="unterzeile">Du bist bereits angemeldet.</p>
        <p><a href="<?= e($weiter) ?>">Weiter</a> · <a href="logout.php">Abmelden</a></p>
    <?php else: ?>
        <form method="post" action="login.php" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="weiter" value="<?= e($weiter) ?>">
            <label for="passwort">Passwort</label>
            <input type="password" id="passwort" name="passwort" required autofocus>
            <button type="submit">Anmelden</button>
        </form>
    <?php endif; ?>

    <p class="hinweis">
        Passwort vergessen? Hash in <code>inc/config.local.php</code> bzw.
        <code>inc/config.live.php</code> neu erzeugen.
    </p>
</main>
</body>
</html>
