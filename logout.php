<?php

declare(strict_types=1);

/**
 * Abmelden und zurück zur Anmeldung (Gerüst E0).
 * POST empfohlen; ein einfacher Aufruf per Link funktioniert ebenfalls.
 */

require __DIR__ . '/inc/config.php';
require __DIR__ . '/inc/auth.php';

lehrer_abmelden();

header('Location: login.php', true, 302);
exit;
