<?php

declare(strict_types=1);

/**
 * quiz_test.php?slug=quiz-trigonometrie
 *
 * Testlauf eines Quiz-Bausteins – ohne Schüler, ohne Zuordnung, ohne
 * Speichern. Der Verlauf lebt nur im Browser; die KI läuft über
 * api/quiz_test.php (DB-Bausteine, nichts wird geschrieben).
 */

require __DIR__ . '/inc/config.php';
require __DIR__ . '/inc/chat.php';
require __DIR__ . '/inc/auth.php';

auth_starten();
verwaltung_erzwingen();

// Nur Quiz-Bausteine anbieten, nach Titel sortiert.
$quizSlugs = [];
$bausteine = [];

$abfrage = db()->query("SELECT slug, titel FROM ms_bausteine WHERE art = 'quiz' ORDER BY titel");
foreach ($abfrage->fetchAll() as $zeile) {
    $slug = (string) $zeile['slug'];
    $quizSlugs[] = $slug;
    $bausteine[$slug] = ['titel' => (string) $zeile['titel']];
}

// Rückfall: falls (noch) kein Baustein als Quiz markiert ist, alle anbieten.
if ($quizSlugs === []) {
    $abfrage = db()->query('SELECT slug, titel FROM ms_bausteine ORDER BY titel');
    foreach ($abfrage->fetchAll() as $zeile) {
        $slug = (string) $zeile['slug'];
        $quizSlugs[] = $slug;
        $bausteine[$slug] = ['titel' => (string) $zeile['titel']];
    }
}

$slug = (string) ($_GET['slug'] ?? ($quizSlugs[0] ?? ''));
if (!in_array($slug, $quizSlugs, true)) {
    $slug = (string) ($quizSlugs[0] ?? '');
}

$b            = $slug !== '' ? baustein_laden($slug) : null;
$anzahl       = max(1, min(10, (int) ($b['quiz']['anzahl'] ?? 5)));
$anzahlFragen = isset($b['quiz']['fragen']) && is_array($b['quiz']['fragen'])
    ? count($b['quiz']['fragen'])
    : 0;
$hatPrompt    = $b !== null && trim((string) ($b['prompt'] ?? '')) !== '';

$cfg = [
    'slug'      => $slug,
    'endpoint'  => 'api/quiz_test.php',
    'anzahl'    => $anzahl,
    'hatPrompt' => $hatPrompt,
    // ?start=1 startet den Testlauf sofort (praktisch für Screenshots/Demos)
    'autoStart' => ($_GET['start'] ?? '') === '1',
];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Quiz-Testlauf · Matheschaf</title>
    <link rel="stylesheet" href="<?= asset_version('assets/admin2.css') ?>">
    <link rel="stylesheet" href="<?= asset_version('assets/quiz.css') ?>">
    <?= app_icon_tags('bausteine.php') ?>
</head>
<body class="is-quiz">
    <header class="topbar">
        <div class="topbar-inner">
            <img class="brand-logo" src="schaf1.png" alt="" width="44" height="44">
            <div class="brand-text">
                <h1>Quiz-Testlauf</h1>
                <p><?= $b !== null ? htmlspecialchars((string) ($b['titel'] ?? $slug), ENT_QUOTES, 'UTF-8') : 'kein Baustein' ?></p>
            </div>
            <button type="button" class="quiz-ton" id="quiz-ton" aria-pressed="true" title="Ton ein/aus">🔊</button>
        </div>
    </header>

    <div class="wrap">
        <p class="back-link">
            <a href="bausteine.php">&larr; Bausteine</a>
            · <span class="meta">Testlauf ohne Schüler – es wird nichts gespeichert</span>
            <?php if ($b !== null): ?>
                · <span class="meta"><?= $anzahlFragen ?> Vorab-Fragen · <?= $anzahl ?> pro Durchgang</span>
            <?php endif; ?>
        </p>

        <section class="section quiz-kopf">
            <div class="quiz-setup">
                <label for="quiz-baustein" class="quiz-label">Baustein</label>
                <select id="quiz-baustein" onchange="window.location.href='quiz_test.php?slug=' + encodeURIComponent(this.value);">
                    <?php foreach ($quizSlugs as $s): ?>
                        <option value="<?= htmlspecialchars($s, ENT_QUOTES, 'UTF-8') ?>" <?= $s === $slug ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string) ($bausteine[$s]['titel'] ?? $s), ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="quiz-name" class="quiz-label">Kandidat</label>
                <input id="quiz-name" type="text" maxlength="30" value="Test" placeholder="Name">

                <label for="quiz-stufe" class="quiz-label">Stufe</label>
                <select id="quiz-stufe">
                    <?php foreach (['leicht', 'mittel', 'schwer', 'zufall'] as $st): ?>
                        <option value="<?= $st ?>" <?= $st === 'leicht' ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
                    <?php endforeach; ?>
                </select>

                <button type="button" class="btn" id="quiz-start">▶️ Neues Spiel</button>
            </div>

            <?php if (!$hatPrompt): ?>
                <p class="form-warnung">Für diesen Baustein gibt es keinen Prompt – bitte in der Verwaltung (Etappe E5) speichern.</p>
            <?php endif; ?>
        </section>

        <!-- Zuschauer-Tribüne: die Schafe kommentieren jede Antwort.
             Bilder sind auf 200 px Breite optimiert (bilder/tribuene/) –
             das reicht für 104 CSS-Pixel auf dem Desktop und für 3x-Displays
             am iPhone, spart aber rund 70 % Ladezeit gegenüber den Originalen. -->
        <section class="quiz-buehne" aria-hidden="true">
            <div class="quiz-tribuene" id="quiz-tribuene">
                <div class="tribuene-reihe tribuene-reihe--hinten">
                    <img class="tribuene-schaf" style="--i:0" src="bilder/tribuene/schaf2.png" width="200" height="200" alt="" draggable="false" decoding="async">
                    <img class="tribuene-schaf" style="--i:1" src="bilder/tribuene/schaf1.png" width="200" height="195" alt="" draggable="false" decoding="async">
                    <img class="tribuene-schaf" style="--i:2" src="bilder/tribuene/schaf3.png" width="200" height="199" alt="" draggable="false" decoding="async">
                    <img class="tribuene-schaf" style="--i:3" src="bilder/tribuene/schaf0.png" width="200" height="200" alt="" draggable="false" decoding="async">
                </div>
                <div class="tribuene-reihe tribuene-reihe--vorn">
                    <img class="tribuene-schaf" style="--i:0" src="bilder/tribuene/schaf1.png" width="200" height="195" alt="" draggable="false" decoding="async">
                    <img class="tribuene-schaf" style="--i:1" src="bilder/tribuene/schaf0.png" width="200" height="200" alt="" draggable="false" decoding="async">
                    <img class="tribuene-schaf" style="--i:2" src="bilder/tribuene/schaf3.png" width="200" height="199" alt="" draggable="false" decoding="async">
                    <img class="tribuene-schaf" style="--i:3" src="bilder/tribuene/schaf2.png" width="200" height="200" alt="" draggable="false" decoding="async">
                    <img class="tribuene-schaf" style="--i:4" src="bilder/tribuene/schaf1.png" width="200" height="195" alt="" draggable="false" decoding="async">
                </div>
                <div class="tribuene-boden"></div>
            </div>
        </section>

        <main class="chat-list quiz-chat" id="quiz-chat" aria-live="polite">
            <p class="msg-empty" id="quiz-empty">Noch kein Spiel gestartet – „▶️ Neues Spiel" drücken.</p>
        </main>

        <div class="chat-composer quiz-composer">
            <div class="composer-input-row">
                <input id="quiz-text" type="text" maxlength="300" placeholder="Antwort oder Frage …" aria-label="Antwort">
                <button type="button" class="send-btn" id="quiz-send" title="Senden" aria-label="Senden">
                    <svg viewBox="0 0 24 24" width="21" height="21" fill="currentColor" aria-hidden="true"><path d="M2.01 21 23 12 2.01 3 2 10l15 2-15 2z"/></svg>
                </button>
            </div>
        </div>
    </div>

    <div class="konfetti-layer" id="konfetti-layer" aria-hidden="true"></div>

    <script>window.QUIZ_CFG = <?= json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
    <script src="https://cdn.jsdelivr.net/npm/mathlive"></script>
    <script src="<?= asset_version('assets/quiz-sound.js') ?>"></script>
    <script src="<?= asset_version('assets/quiz.js') ?>"></script>
</body>
</html>