<?php

declare(strict_types=1);

/**
 * Schüler-Chat (E2) – Daten aus der Datenbank statt aus schueler.json.
 *
 * Aufruf: index.php?id=2301
 * Der 3D-Würfel, die Stufenwahl, Wiese/Wetter und Tastatur kommen aus
 * assets/chat.js; der Endpoint ist api/chat.php.
 */

require __DIR__ . '/inc/config.php';
require __DIR__ . '/inc/chat.php';

$id      = (string) ($_GET['id'] ?? '');
$student = schueler_laden($id);

if ($student === null) {
    http_response_code(404);
    echo '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8"><title>Schüler nicht gefunden</title></head>'
        . '<body style="font-family:sans-serif;padding:24px"><h1>Schüler nicht gefunden</h1>'
        . '<p>ID: ' . e($id) . '</p><p><a href="starter.php">Zurück</a></p></body></html>';
    exit;
}

$name            = schueler_name($student);
$verlauf         = nachrichten_laden($id, 'chat', MESSAGES_ON_LOAD);
$bausteinSlug    = (string) ($student['aktiver_baustein'] ?? '');
$baustein        = $bausteinSlug !== '' ? baustein_laden($bausteinSlug) : null;

// Quiz für die 4. Würfelfläche („Teste dein Wissen“) – eigenes Feld am Schüler.
$quizSlug     = (string) ($student['quiz_baustein'] ?? '');
$quizBaustein = $quizSlug !== '' ? baustein_laden($quizSlug) : null;
if ($quizBaustein !== null && !isset($quizBaustein['quiz'])) {
    $quizBaustein = null;
}
$quizTitel  = $quizBaustein !== null ? (string) ($quizBaustein['titel'] ?? $quizSlug) : '';
$quizAnzahl = $quizBaustein !== null ? max(1, (int) ($quizBaustein['quiz']['anzahl'] ?? 5)) : 0;

// Thema des Quiz-Bausteins -> wird an die Challenge übergeben (Fragen dort).
$quizThema = '';
if ($quizBaustein !== null) {
    foreach (['quiz_thema', 'thema', 'titel'] as $feld) {
        $wert = trim((string) ($quizBaustein[$feld] ?? ''));
        if ($wert !== '') {
            $quizThema = $wert;
            break;
        }
    }
}

$challengeLink = challenge_url(
    $quizThema !== ''
        ? '?thema=' . rawurlencode($quizThema) . '&schueler=' . rawurlencode($id)
        : ''
);

// Schafswiese im rechten Flügel: ein Schaf pro Bonuspunkt.
$bonuspunkte = max(0, (int) ($student['bonuspunkte'] ?? 0));
$wieseSchafe = wiese_schafe($id, $bonuspunkte);
$wieseAnzahl = count($wieseSchafe);

$cfg = [
    'id'           => $id,
    'chatEndpoint' => 'api/chat.php',
    // Zum Ausprobieren: index.php?id=…&wetter=gewitter erzwingt eine Wetterlage.
    'wetterProbe'  => in_array($_GET['wetter'] ?? '', ['sonne', 'wolken', 'regen', 'schnee', 'nebel', 'gewitter'], true)
        ? (string) $_GET['wetter']
        : '',
    // Für die Schafswiese: Bildpfade + Obergrenze (JS hängt neue Schafe live an).
    'schafBilder'  => WIESE_SCHAF_BILDER,
    'schafPlus'    => WIESE_SCHAF_PLUS,
    'wieseMax'     => WIESE_MAX_SCHAFE,
    // 4. Würfelfläche „Teste dein Wissen“
    'quizBereit'   => $quizBaustein !== null,
    'quizTitel'    => $quizTitel,
    'quizAnzahl'   => $quizAnzahl,
];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Chat · <?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="<?= asset_version('assets/admin2.css') ?>">
    <link rel="stylesheet" href="<?= asset_version('assets/quiz.css') ?>">
    <link rel="stylesheet" href="<?= asset_version('assets/keyboard2.css') ?>">
    <?= app_icon_tags('index.php?id=' . rawurlencode($id), schueler_vorname($student)) ?>
</head>
<body class="is-chat">
    <header class="topbar">
        <div class="topbar-inner">
            <button type="button" class="wing-arrow" id="wing-left-btn" aria-label="Linken Flügel umschalten">‹</button>
            <img class="brand-logo" src="schaf1.png" alt="" width="44" height="44">
            <div class="brand-text">
                <h1><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?><a class="hilfe-frage" href="hilfe.php" title="Hilfe" aria-label="Hilfe">?</a></h1>
                <p><?= $baustein ? htmlspecialchars((string) ($baustein['titel'] ?? $bausteinSlug), ENT_QUOTES, 'UTF-8') : 'Kein Baustein zugewiesen' ?></p>
            </div>
            <button type="button" class="wing-arrow" id="wing-right-btn" aria-label="Rechten Flügel umschalten">›</button>
        </div>
    </header>

    <div class="wrap chat-wrap">
        <div class="fluegel-wrap" id="fluegel-wrap">
            <!-- 3D-Würfel: die drei Bereiche sind Würfelflächen.
                 Gedreht wird per Wisch-Geste (Finger/Maus) oder mit den Pfeilen oben. -->
            <div class="wuerfel" id="wuerfel">
            <aside class="wing wing--left" id="wing-left">
                <?php $referenzBild = baustein_referenzbild($baustein ?? []); ?>
                <?php if ($referenzBild !== ''): ?>
                    <!-- Referenzbild des Bausteins: oben, volle Breite -->
                    <div class="wing-inner wing-inner--bild">
                        <img class="wing-bild"
                             src="<?= htmlspecialchars($referenzBild, ENT_QUOTES, 'UTF-8') ?>"
                             alt="Referenzbild zur Aufgabe" draggable="false" decoding="async">
                        <p class="wing-bild-titel">Referenzbild · <?= htmlspecialchars((string) ($baustein['thema'] ?? $baustein['titel'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                <?php else: ?>
                    <div class="wing-inner">
                        <h2>Referenz / Bild</h2>
                        <p class="wing-hint">Für diesen Baustein ist noch kein Bild hinterlegt.</p>
                    </div>
                <?php endif; ?>
            </aside>

            <div class="chat-col ist-vorn">
        <?php if ($referenzBild !== ''): ?>
            <!-- Hauptbild (erstes Bild im Musterdialog), klein oben im Chat.
                 Klick dreht den Würfel zur Bildseite (links). -->
            <div class="chat-bild-leiste">
                <button type="button" class="chat-bild-chip" data-dreh-zu="0"
                        title="Referenzbild groß anzeigen" aria-label="Referenzbild groß anzeigen">
                    <img src="<?= htmlspecialchars($referenzBild, ENT_QUOTES, 'UTF-8') ?>"
                         alt="" draggable="false" decoding="async">
                </button>
            </div>
        <?php endif; ?>
        <div class="stufe-screen" id="stufe-screen">
            <p class="stufe-kicker">Level-Check</p>
            <h2>Wähle deine Schwierigkeit</h2>
            <p class="stufe-sub">Wie mutig bist du heute?</p>
            <div class="stufe-group">
                <button type="button" class="stufe-btn stufe-btn--leicht" data-stufe="leicht" style="--i:0">
                    <span class="stufe-btn-icon" aria-hidden="true">🌱</span>
                    <span class="stufe-btn-label">Leicht</span>
                    <span class="stufe-btn-hint">Zum Warmlaufen</span>
                </button>
                <button type="button" class="stufe-btn stufe-btn--mittel" data-stufe="mittel" style="--i:1">
                    <span class="stufe-btn-icon" aria-hidden="true">⚡</span>
                    <span class="stufe-btn-label">Mittel</span>
                    <span class="stufe-btn-hint">Genau dein Ding</span>
                </button>
                <button type="button" class="stufe-btn stufe-btn--schwer" data-stufe="schwer" style="--i:2">
                    <span class="stufe-btn-icon" aria-hidden="true">🔥</span>
                    <span class="stufe-btn-label">Schwer</span>
                    <span class="stufe-btn-hint">Für Profis</span>
                </button>
                <button type="button" class="stufe-btn stufe-btn--zufall" data-stufe="zufall" style="--i:3">
                    <span class="stufe-btn-icon" aria-hidden="true">🎲</span>
                    <span class="stufe-btn-label">Zufall</span>
                    <span class="stufe-btn-hint">Trau dich!</span>
                </button>
            </div>

            <!-- Balken unter den Stufen: führt auf die 4. Würfelfläche (Quiz) -->
            <button type="button" class="quiz-balken<?= $quizBaustein === null ? ' ist-gesperrt' : '' ?>" id="quiz-balken">
                <span class="quiz-balken-icon" aria-hidden="true">🎬</span>
                <span class="quiz-balken-text">Teste dein Wissen</span>
                <span class="quiz-balken-meta">
                    <?= $quizBaustein !== null
                        ? htmlspecialchars($quizTitel . ' · ' . $quizAnzahl . ' Fragen', ENT_QUOTES, 'UTF-8')
                        : 'noch kein Quiz freigeschaltet' ?>
                </span>
                <span class="quiz-balken-pfeil" aria-hidden="true">→</span>
            </button>

            <!-- Challenge (eigenes Projekt). Das Thema des Quiz geht als ?thema= mit. -->
            <a class="quiz-balken challenge-balken" href="<?= htmlspecialchars($challengeLink, ENT_QUOTES, 'UTF-8') ?>">
                <img class="challenge-balken-schaf" src="bilder/schaf1.png" alt="" width="26" height="26" draggable="false">
                <span class="quiz-balken-text">Challenge</span>
                <span class="quiz-balken-meta">
                    <?= $quizThema !== ''
                        ? htmlspecialchars($quizThema, ENT_QUOTES, 'UTF-8')
                        : 'Spring mit dem Schaf' ?>
                </span>
                <span class="quiz-balken-pfeil" aria-hidden="true">→</span>
            </a>

            <!-- Wege zu den Rechtstexten stehen unten am Seitenende (.fuss-recht) -->
        </div>

        <div class="chat-stage" id="chat-stage" hidden>
        <main class="chat-list" id="chat-list" aria-live="polite">
            <?php foreach ($verlauf as $z): ?>
                <div class="msg-row <?= (($z['richtung'] ?? '') === 'an_lehrer') ? 'msg-row--ich' : 'msg-row--gegen' ?>">
                    <div class="msg-bubble">
                        <?php if (!empty($z['text'])): ?><span class="msg-text"><?= htmlspecialchars((string) $z['text'], ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
                        <?php if (!empty($z['formel'])): ?><div class="msg-formel"><math-div><?= htmlspecialchars((string) $z['formel'], ENT_QUOTES, 'UTF-8') ?></math-div></div><?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <div class="msg-empty" id="msg-empty" <?= $verlauf !== [] ? 'hidden' : '' ?>>Noch keine Nachrichten.</div>
        </main>

        <div class="chat-composer">
            <div class="composer-input-row">
                <input id="chat-text" type="text" inputmode="none" maxlength="1000" placeholder="Antwort …" aria-label="Antwort">
                <button type="button" class="btn btn--secondary kb-toggle" id="kb-toggle-btn" aria-expanded="true" title="Tastatur ein-/ausblenden" aria-label="Tastatur">⌨️</button>
                <button type="button" class="send-btn" id="chat-send" title="Senden" aria-label="Senden">
                    <svg viewBox="0 0 24 24" width="21" height="21" fill="currentColor" aria-hidden="true"><path d="M2.01 21 23 12 2.01 3 2 10l15 2-15 2z"/></svg>
                </button>
            </div>
            <math-field id="chat-formel" virtual-keyboard-mode="manual" virtual-keyboard-toggle-glyph="none" menu-glyph="none" inputmode="none" aria-label="Formel"></math-field>
        </div>

        <div data-tastatur data-text="#chat-text" data-math="#chat-formel"></div>
        </div>
            </div>

            <aside class="wing wing--right" id="wing-right">
                <div class="wing-inner wing-inner--wiese">
                    <div class="wiese" role="img" aria-label="Schafswiese mit <?= $wieseAnzahl ?> Schafen"
                         style="background-image:url('<?= htmlspecialchars(WIESE_BILD, ENT_QUOTES, 'UTF-8') ?>')">
                        <?php foreach ($wieseSchafe as $schaf): ?>
                            <span class="schaf-ort"
                                  style="left:<?= $schaf['x'] ?>%; top:<?= $schaf['y'] ?>%; width:<?= $schaf['size'] ?>%; z-index:<?= $schaf['z'] ?>"><img class="schaf"
                                     src="<?= htmlspecialchars($schaf['src'], ENT_QUOTES, 'UTF-8') ?>" alt=""
                                     draggable="false" decoding="async"
                                     style="--dur:<?= $schaf['dur'] ?>s; --dist:<?= $schaf['dist'] ?>px; --delay:<?= $schaf['delay'] ?>s"></span>
                        <?php endforeach; ?>
                        <?php if ($wieseAnzahl === 0): ?>
                            <p class="wiese-leer">Noch kein Schaf auf der Wiese – löse eine Aufgabe richtig, dann kommt das erste. 🐑</p>
                        <?php endif; ?>

                        <!-- Das Wetter baut chat.js bei jedem Öffnen neu (Zufall). -->
                        <div class="wetter" id="wetter" aria-hidden="true"></div>
                    </div>
                    <div class="wiese-kopf">
                        <h2>Schafswiese</h2>
                        <p class="wiese-zaehler">
                            🐑 <span id="wiese-anzahl"><?= $wieseAnzahl ?></span>
                            <span id="wiese-wort"><?= $wieseAnzahl === 1 ? 'Schaf' : 'Schafe' ?></span>
                            <?php if ($bonuspunkte > WIESE_MAX_SCHAFE): ?>
                                <span class="wiese-zaehler-hinweis">(<?= $bonuspunkte ?> Bonuspunkte)</span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </aside>
            <!-- 4. Würfelfläche: Quiz "Teste dein Wissen" (Zuordnung wie ein Baustein) -->
            <aside class="wing wing--quiz" id="wing-quiz">
                <div class="wing-inner wing-inner--quiz">
                    <div class="quiz-tribuene" id="quiz-tribuene" aria-hidden="true">
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

                    <div class="quiz-face-kopf">
                        <button type="button" class="quiz-face-zurueck" id="quiz-face-zurueck">← Zurück zur Lobby</button>
                        <button type="button" class="quiz-face-ton" id="quiz-face-ton" aria-pressed="true" title="Ton ein/aus">🔊</button>
                    </div>

                    <div class="quiz-face-head">
                        <h2>Teste dein Wissen</h2>
                        <p class="quiz-face-meta" id="quiz-face-meta">
                            <?= $quizBaustein !== null
                                ? htmlspecialchars($quizTitel . ' · ' . $quizAnzahl . ' Fragen', ENT_QUOTES, 'UTF-8')
                                : 'Für dich ist noch kein Quiz freigeschaltet.' ?>
                        </p>
                    </div>

                    <div class="quiz-face-chat" id="quiz-face-chat">
                        <p class="msg-empty" id="quiz-face-empty">
                            <?= $quizBaustein !== null ? 'Tippe auf „Neues Quiz", dann geht es los.' : 'Sag deinem Lehrer Bescheid.' ?>
                        </p>
                    </div>

                    <div class="quiz-face-actions">
                        <button type="button" class="btn" id="quiz-face-start"<?= $quizBaustein === null ? ' disabled' : '' ?>>▶️ Neues Quiz</button>
                    </div>
                </div>
            </aside>
            </div><!-- /.wuerfel -->
        </div><!-- /.fluegel-wrap -->
    </div>

    <!-- Rechts-Fußzeile: winzige Links am Seitenende, von jeder Würfelfläche
         erreichbar. Die Einwilligung gehört zum Schüler, deshalb mit seiner ID. -->
    <footer class="fuss-recht">
        <a href="impressum.php">Impressum</a>
        <span aria-hidden="true">·</span>
        <a href="datenschutz.php">Datenschutz</a>
        <span aria-hidden="true">·</span>
        <a href="einwilligung.php?id=<?= rawurlencode($id) ?>">Einwilligung</a>
    </footer>

    <div class="konfetti-layer" id="konfetti-layer" aria-hidden="true"></div>

    <script>window.CHAT_CFG = <?= json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
    <script src="https://cdn.jsdelivr.net/npm/mathlive"></script>
    <script src="<?= asset_version('assets/keyboard-layout.js') ?>"></script>
    <script src="<?= asset_version('assets/keyboard.js') ?>"></script>
    <script src="<?= asset_version('assets/quiz-sound.js') ?>"></script>
    <script src="<?= asset_version('assets/chat.js') ?>"></script>
</body>
</html>
