<?php

declare(strict_types=1);

/** bausteine.php — Bausteinübersicht (E5), nur nach Login. */

require __DIR__ . '/inc/config.php';
require __DIR__ . '/inc/chat.php';
require __DIR__ . '/inc/auth.php';
require __DIR__ . '/inc/verwaltung.php';

auth_starten();
verwaltung_erzwingen();

$uebersicht = bausteine_uebersicht();
$anzahl     = ['lern' => 0, 'quiz' => 0];
foreach ($uebersicht as $b) {
    $anzahl[$b['art']] = ($anzahl[$b['art']] ?? 0) + 1;
}

verwaltung_kopf('Bausteine', 'bausteine.php');

if (isset($_GET['geloescht'])) {
    $geloeschterSlug = (string) $_GET['geloescht'];
    $betroffen       = (int) ($_GET['schueler'] ?? 0);

    echo '<p class="form-notice">Baustein „' . e($geloeschterSlug) . '“ wurde gelöscht'
        . ($betroffen > 0
            ? ' – bei ' . $betroffen . ' Schüler(inne)n wurde die Zuordnung entfernt'
            : '')
        . '.</p>';
}
?>
<p class="hinweis">
    Lern-Bausteine enthalten Dialog und Referenzaufgaben, Quiz-Bausteine die Fragen.
    Prompts werden im Chat an die KI gegeben.
</p>

<div class="form-actions" style="margin:10px 0 16px">
    <a class="btn" href="baustein_edit.php?neu=1">＋ Neuer Lern-Baustein</a>
    <a class="btn" href="baustein_edit.php?neu=1&amp;art=quiz">＋ Neues Quiz</a>
    <a class="btn btn--secondary" href="dialog_baustein.php">🎬 Dialog-Baustein aufzeichnen</a>
    <a class="btn btn--secondary" href="baustein_generator.php">✨ Prompt-Generator</a>
</div>

<section class="section">
    <div class="section-head">
        <h2>Alle Bausteine</h2>
        <span class="count"><?= count($uebersicht) ?> (<?= $anzahl['lern'] ?> Lern · <?= $anzahl['quiz'] ?> Quiz)</span>
    </div>

    <div class="students">
        <?php foreach ($uebersicht as $slug => $b): ?>
            <div class="student">
                <a class="student-main" href="baustein_edit.php?slug=<?= rawurlencode($slug) ?>">
                    <div class="avatar" aria-hidden="true"><?= $b['art'] === 'quiz' ? '🎬' : '📘' ?></div>
                    <div class="student-body">
                        <p class="student-name"><?= e((string) $b['titel']) ?></p>
                        <p class="student-meta"><?= e((string) $slug) ?> · <?= $b['art'] === 'quiz' ? 'Quiz' : 'Lern-Baustein' ?></p>
                        <div class="tags">
                            <span class="pill <?= $b['prompt_ok'] ? 'pill--green' : 'pill--warnung' ?>">
                                <?= $b['prompt_ok'] ? 'Prompt ✓' : 'kein Prompt' ?>
                            </span>
                            <?php if ($b['art'] === 'quiz'): ?>
                                <span class="pill pill--quiz"><?= (int) $b['quiz_anzahl'] ?> Fragen</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
                <div class="student-action">
                    <a class="btn" href="baustein_edit.php?slug=<?= rawurlencode($slug) ?>">Bearbeiten</a>
                    <?php if ($b['art'] === 'quiz'): ?>
                        <a class="btn btn--secondary" href="quiz_test.php?slug=<?= rawurlencode($slug) ?>">Testen</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php
verwaltung_fuss();
