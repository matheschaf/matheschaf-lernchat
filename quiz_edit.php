<?php

declare(strict_types=1);

/** quiz_edit.php — Einstieg in den Quiz-Editor (E5). */

require __DIR__ . '/inc/config.php';
require __DIR__ . '/inc/auth.php';

auth_starten();
verwaltung_erzwingen();

$slug = trim((string) ($_GET['slug'] ?? ''));

header(
    'Location: baustein_edit.php?art=quiz' . ($slug !== '' ? '&slug=' . rawurlencode($slug) : '&neu=1'),
    true,
    302
);
exit;
