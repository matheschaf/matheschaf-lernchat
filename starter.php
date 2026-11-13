<?php

declare(strict_types=1);

/**
 * starter.php — Lehrer-Bereich (E4), Daten aus der Datenbank.
 *
 * Karten je Schüler mit Stufe, Bausteinen, Punkten, Status und Einwilligung;
 * Filter (Suche, Pillen), Aktionen Chat/Link/SMS/Anruf, Kosten-Karte, Hilfe.
 * SMS/Anruf nur mit Einwilligung (ok_sms / ok_anruf) – sonst Knopf gesperrt.
 */

require __DIR__ . '/inc/config.php';
require __DIR__ . '/inc/chat.php';
require __DIR__ . '/inc/auth.php';

auth_starten();
verwaltung_erzwingen();

/**
 * Adresse der Challenge. Sie liegt als eigenes Projekt NEBEN /lernchat/ und
 * läuft ohne Login – die Adresse kommt aus inc/chat.php (challenge_url()).
 */
$challengeUrl = challenge_url();

/** Verweist die Linkliste schon auf eine vorhandene Datei? */
$linksVorhanden = is_file(__DIR__ . '/links.php');

$schueler  = schueler_liste();
$bausteine = bausteine_uebersicht();
$zaehler   = status_zaehler();

/**
 * Filterprüfung: alle | offen | geschafft | ohne-einwilligung | ohne-prompt.
 */
function passt_filter(array $s, string $filter, array $bausteine, array $zaehler): bool
{
    $id     = (string) $s['id_code'];
    $aktiv  = (string) ($s['aktiver_baustein'] ?? '');
    $quiz   = (string) ($s['quiz_baustein'] ?? '');
    $prompt = static fn(string $slug): bool =>
        $slug !== '' && isset($bausteine[$slug]) && $bausteine[$slug]['prompt_ok'];
    $geschafft = (int) ($zaehler[$id]['geschafft'] ?? 0);

    return match ($filter) {
        'offen' => $aktiv !== '' && $prompt($aktiv) && $geschafft === 0,
        'geschafft' => $geschafft > 0,
        'ohne-einwilligung' => (int) ($s['ok_sms'] ?? 0) !== 1 && (int) ($s['ok_anruf'] ?? 0) !== 1,
        'ohne-prompt' => ($aktiv !== '' && !$prompt($aktiv)) || ($quiz !== '' && !$prompt($quiz)),
        default => true,
    };
}

$erlaubteFilter = ['alle', 'offen', 'geschafft', 'ohne-einwilligung', 'ohne-prompt'];
$filter = (string) ($_GET['filter'] ?? 'alle');
if (!in_array($filter, $erlaubteFilter, true)) {
    $filter = 'alle';
}
$suche = trim((string) ($_GET['q'] ?? ''));

$gefiltert = array_values(array_filter(
    $schueler,
    static fn(array $s): bool => passt_filter($s, $filter, $bausteine, $zaehler)
));

if ($suche !== '') {
    $nadel = mb_strtolower($suche);
    $gefiltert = array_values(array_filter(
        $gefiltert,
        static function (array $s) use ($nadel): bool {
            $heu = mb_strtolower(implode(' ', [
                (string) ($s['id_code'] ?? ''),
                (string) ($s['vorname'] ?? ''),
                (string) ($s['nachname'] ?? ''),
                (string) ($s['klasse'] ?? ''),
            ]));

            return str_contains($heu, $nadel);
        }
    ));
}

/** Anzahl je Filter über ALLE Schüler. */
$filterZahlen = [];
foreach ($erlaubteFilter as $name) {
    $filterZahlen[$name] = count(array_filter(
        $schueler,
        static fn(array $s): bool => passt_filter($s, $name, $bausteine, $zaehler)
    ));
}

$cfg = [
    'csrf'         => csrf_token(),
    'smsEndpoint'  => 'api/sms.php',
    'callEndpoint' => 'api/call.php',
];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= e(APP_NAME) ?> · Lehrer-Bereich</title>
    <link rel="stylesheet" href="<?= asset_version('assets/admin2.css') ?>">
    <?= app_icon_tags('starter.php') ?>
    <style>
        .filter-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
            margin: 0 0 14px;
        }
        .filter-bar input[type="search"] {
            flex: 1 1 200px;
            min-width: 160px;
            padding: 8px 10px;
            border: 1px solid #c8d2c3;
            border-radius: 8px;
            font: inherit;
        }
        .filter-bar .pill { text-decoration: none; }
        .filter-bar .pill[aria-current="page"] {
            outline: 2px solid #1b5e20;
            outline-offset: 1px;
        }
        .student-einwilligung,
        .student-status { font-size: .85rem; color: #555; margin: 6px 0 0; overflow-wrap: anywhere; }
        .tool--aus { opacity: .6; cursor: not-allowed; }

        /* Karten robust halten: lange Slugs/Datum dürfen das Grid nicht sprengen. */
        .students { grid-template-columns: minmax(0, 1fr); }
        @media (min-width: 620px) { .students { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (min-width: 960px) { .students { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
        .student { min-width: 0; }
        .student-name {
            white-space: normal;
            overflow: visible;
            text-overflow: clip;
            overflow-wrap: anywhere;
        }
        .tags .pill { max-width: 100%; overflow-wrap: anywhere; }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="topbar-inner">
            <img class="brand-logo" src="schaf1.png" alt="" width="44" height="44">
            <div class="brand-text">
                <h1><?= e(APP_NAME) ?></h1>
                <p>Lehrer-Bereich · lokal</p>
            </div>
            <a class="topbar-link" href="hilfe.php" title="Hilfe">?</a>
            <a class="topbar-link" href="schueler.php" title="Schüler verwalten">👥</a>
            <a class="topbar-link" href="logout.php">Abmelden</a>
        </div>
    </header>

    <div class="wrap">
        <p class="form-notice" id="starter-meldung" hidden></p>

        <section class="section">
            <div class="section-head">
                <h2>Schüler</h2>
                <span class="count"><?= count($gefiltert) ?> von <?= count($schueler) ?></span>
            </div>

            <form class="filter-bar" method="get" action="starter.php">
                <input type="search" name="q" value="<?= e($suche) ?>" placeholder="Name, Klasse oder Kennung …" aria-label="Schüler suchen">
                <input type="hidden" name="filter" value="<?= e($filter) ?>">
                <button type="submit" class="btn btn--secondary">Suchen</button>
                <?php if ($suche !== '' || $filter !== 'alle'): ?>
                    <a class="btn btn--secondary" href="starter.php">Zurücksetzen</a>
                <?php endif; ?>
            </form>

            <div class="tags filter-bar">
                <?php foreach ($erlaubteFilter as $name): ?>
                    <?php
                    $klasse = match ($name) {
                        'geschafft' => 'pill--green',
                        'ohne-einwilligung', 'ohne-prompt' => 'pill--warnung',
                        'offen' => 'pill--amber',
                        default => 'pill--quiz',
                    };
                    $label = match ($name) {
                        'alle' => 'Alle',
                        'offen' => 'Offen',
                        'geschafft' => 'Geschafft',
                        'ohne-einwilligung' => 'Ohne Einwilligung',
                        'ohne-prompt' => 'Ohne Prompt',
                    };
                    ?>
                    <a class="pill <?= $klasse ?>"
                       href="starter.php?filter=<?= rawurlencode($name) ?><?= $suche !== '' ? '&amp;q=' . rawurlencode($suche) : '' ?>"
                       <?= $name === $filter ? 'aria-current="page"' : '' ?>>
                        <?= e($label) ?> · <?= (int) ($filterZahlen[$name] ?? 0) ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <?php if ($gefiltert === []): ?>
                <p class="form-notice">Kein Schüler passt zu dieser Auswahl.</p>
            <?php endif; ?>

            <div class="students">
                <?php foreach ($gefiltert as $s): ?>
                    <?php
                    $id        = (string) $s['id_code'];
                    $chatHref  = 'index.php?id=' . rawurlencode($id);
                    $telefon   = trim((string) ($s['telefon'] ?? ''));
                    $telefonOk = $telefon !== '';
                    $smsOk     = (int) ($s['ok_sms'] ?? 0) === 1;
                    $anrufOk   = (int) ($s['ok_anruf'] ?? 0) === 1;
                    $aktivSlug = (string) ($s['aktiver_baustein'] ?? '');
                    $quizSlug  = (string) ($s['quiz_baustein'] ?? '');
                    $aktivOk   = $aktivSlug !== '' && isset($bausteine[$aktivSlug]) && $bausteine[$aktivSlug]['prompt_ok'];
                    $quizOk    = $quizSlug !== '' && isset($bausteine[$quizSlug]) && $bausteine[$quizSlug]['prompt_ok'];
                    $geschafft = (int) ($zaehler[$id]['geschafft'] ?? 0);
                    $offen     = (int) ($zaehler[$id]['offen'] ?? 0);
                    ?>
                    <div class="student" data-id="<?= e($id) ?>">
                        <a class="student-main" href="<?= e($chatHref) ?>">
                            <div class="avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr(schueler_name($s), 0, 1))) ?></div>
                            <div class="student-body">
                                <p class="student-name"><?= e(schueler_name($s)) ?></p>
                                <p class="student-meta">
                                    Klasse <?= e((string) ($s['klasse'] ?? '–')) ?> · ID <?= e($id) ?>
                                    · Stufe <?= e((string) ($s['stufe'] ?? '–')) ?>
                                </p>
                                <div class="tags">
                                    <span class="pill pill--amber">⭐ <?= (int) ($s['bonuspunkte'] ?? 0) ?></span>
                                    <?php if ($aktivSlug !== ''): ?>
                                        <span class="pill pill--green" title="Aktiver Lern-Baustein"><?= e($aktivSlug) ?></span>
                                        <?php if (!$aktivOk): ?>
                                            <span class="pill pill--warnung" title="Diesem Baustein fehlt der Prompt – die KI kann nicht antworten.">⚠️ kein Prompt</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <?php if ($quizSlug !== ''): ?>
                                        <span class="pill pill--quiz" title="Quiz für „Teste dein Wissen“">🎬 <?= e($quizSlug) ?></span>
                                        <?php if (!$quizOk): ?>
                                            <span class="pill pill--warnung">⚠️ Quiz fehlt</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                                <p class="student-status">
                                    ✅ <?= $geschafft ?> geschafft · ◻ <?= $offen ?> offen
                                    <?php if (($s['gestartet_at'] ?? null) !== null): ?>
                                        · zuletzt gestartet <?= e((string) $s['gestartet_at']) ?>
                                    <?php endif; ?>
                                </p>
                                <p class="student-einwilligung">
                                    Einwilligung:
                                    <?= $smsOk ? 'SMS ✓' : 'SMS ✗' ?> ·
                                    <?= $anrufOk ? 'Anruf ✓' : 'Anruf ✗' ?>
                                    <?php if (!$telefonOk): ?> · keine Telefonnummer<?php endif; ?>
                                </p>
                            </div>
                        </a>
                        <div class="student-action">
                            <a class="btn" href="<?= e($chatHref) ?>">Chat</a>
                            <button type="button" class="btn btn--secondary js-anruf"
                                    data-id="<?= e($id) ?>"
                                    title="<?= $anrufOk && $telefonOk ? 'Anruf-Ansage starten' : 'Gesperrt: Einwilligung für Anrufe fehlt oder keine Nummer' ?>"
                                    aria-label="Anruf an <?= e(schueler_name($s)) ?>"
                                    <?= (!$anrufOk || !$telefonOk) ? 'disabled' : '' ?>>
                                <span class="js-anruf-text">📞</span>
                            </button>
                            <button type="button" class="btn btn--secondary js-sms"
                                    data-id="<?= e($id) ?>"
                                    title="<?= $smsOk && $telefonOk ? 'Flash-SMS mit dem Chat-Link senden' : 'Gesperrt: Einwilligung für SMS fehlt oder keine Nummer' ?>"
                                    aria-label="Flash-SMS an <?= e(schueler_name($s)) ?>"
                                    <?= (!$smsOk || !$telefonOk) ? 'disabled' : '' ?>>
                                <span class="js-sms-text">📲 SMS</span>
                            </button>
                            <button type="button" class="btn btn--secondary js-kopieren"
                                    data-link="<?= e(online_chat_link($id)) ?>"
                                    title="Chat-Link in die Zwischenablage kopieren (Online-Adresse)"
                                    aria-label="Chat-Link von <?= e(schueler_name($s)) ?> kopieren">
                                <span class="js-kopieren-text">🔗 Link</span>
                            </button>
                            <a class="btn btn--secondary" href="einwilligung.php?id=<?= rawurlencode($id) ?>"
                               title="Einwilligung für <?= e(schueler_name($s)) ?> bearbeiten">Einwilligung</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <section class="section">
            <div class="section-head">
                <h2>Werkzeuge</h2>
            </div>

            <div class="tools">
                <a class="tool tool--link" href="<?= e($challengeUrl) ?>" target="_blank" rel="noopener">
                    <div class="tool-icon" aria-hidden="true">🐑</div>
                    <div>
                        <h3>Challenge</h3>
                        <p>Spring mit dem Schaf über die Schlucht – kleine Bewegungspause.</p>
                    </div>
                    <span class="soon soon--ok">Neu</span>
                </a>

                <a class="tool tool--link" href="quiz_test.php">
                    <div class="tool-icon" aria-hidden="true">🎬</div>
                    <div>
                        <h3>Quiz-Testlauf</h3>
                        <p>Multiple-Choice-Quiz testen – ohne Schüler, ohne Speichern.</p>
                    </div>
                    <span class="soon soon--ok">Test</span>
                </a>

                <a class="tool tool--link" href="index.php?id=2301">
                    <div class="tool-icon" aria-hidden="true">💬</div>
                    <div>
                        <h3>Chat ansehen</h3>
                        <p>Schüler-Ansicht mit Würfel, Wiese und Quiz öffnen.</p>
                    </div>
                    <span class="soon soon--ok">Aktiv</span>
                </a>

                <a class="tool tool--link" href="bausteine.php">
                    <div class="tool-icon" aria-hidden="true">🗂️</div>
                    <div>
                        <h3>Bausteine verwalten</h3>
                        <p>Ansehen, bearbeiten, Prompts und Quiz-Fragen pflegen.</p>
                    </div>
                    <span class="soon soon--ok">Aktiv</span>
                </a>

                <a class="tool tool--link" href="schueler.php">
                    <div class="tool-icon" aria-hidden="true">👥</div>
                    <div>
                        <h3>Schüler verwalten</h3>
                        <p>Anlegen, bearbeiten, Einwilligung je Kanal pflegen.</p>
                    </div>
                    <span class="soon soon--ok">Aktiv</span>
                </a>

                <?php if ($linksVorhanden): ?>
                    <a class="tool tool--link" href="links.php">
                        <div class="tool-icon" aria-hidden="true">📋</div>
                        <div>
                            <h3>Linkliste</h3>
                            <p>Chat- und Eltern-Links kopieren, drucken, austeilen.</p>
                        </div>
                        <span class="soon soon--ok">Neu</span>
                    </a>
                <?php endif; ?>

                <a class="tool tool--link" href="zuordnung.php">
                    <div class="tool-icon" aria-hidden="true">🔗</div>
                    <div>
                        <h3>Bausteine zuordnen</h3>
                        <p>Lern- und Quiz-Baustein einem Schüler zuweisen.</p>
                    </div>
                    <span class="soon soon--ok">Aktiv</span>
                </a>

                <a class="tool tool--link" href="baustein_generator.php">
                    <div class="tool-icon" aria-hidden="true">✨</div>
                    <div>
                        <h3>Prompt-Generator</h3>
                        <p>KI formuliert einen Tutor-Prompt, du prüfst und speicherst.</p>
                    </div>
                    <span class="soon soon--ok">KI</span>
                </a>

                <a class="tool tool--link" href="dialog_baustein.php">
                    <div class="tool-icon" aria-hidden="true">🎬</div>
                    <div>
                        <h3>Dialog-Baustein</h3>
                        <p>Musterdialog wie im Chat aufzeichnen, Prompt erzeugen, speichern.</p>
                    </div>
                    <span class="soon soon--ok">Neu</span>
                </a>
            </div>
        </section>

        <section class="section">
            <div class="section-head">
                <h2>Kosten &amp; Abrechnung</h2>
                <span class="count">3 Dienste</span>
            </div>

            <div class="kosten-karte">
                <a class="kosten-zeile" href="https://aistudio.google.com/billing?billing=0102C2-0F4720-B34C9A"
                   target="_blank" rel="noopener">
                    <div class="kosten-icon" aria-hidden="true">🤖</div>
                    <div class="kosten-text">
                        <h3>Google AI Studio <span class="kosten-marke">Gemini</span></h3>
                        <p>Guthaben und Abrechnung der KI-Prompts (Chat, Quiz, Generator).</p>
                    </div>
                    <span class="kosten-pfeil" aria-hidden="true">↗</span>
                </a>

                <a class="kosten-zeile" href="https://dashboard.seven.io/dashboard" target="_blank" rel="noopener">
                    <div class="kosten-icon" aria-hidden="true">📲</div>
                    <div class="kosten-text">
                        <h3>seven.io <span class="kosten-marke">SMS &amp; Anruf</span></h3>
                        <p>Guthaben für Flash-SMS und die Anruf-Ansage.</p>
                    </div>
                    <span class="kosten-pfeil" aria-hidden="true">↗</span>
                </a>

                <a class="kosten-zeile" href="https://platform.deepseek.com/usage" target="_blank" rel="noopener">
                    <div class="kosten-icon" aria-hidden="true">🧠</div>
                    <div class="kosten-text">
                        <h3>DeepSeek <span class="kosten-marke">API</span></h3>
                        <p>Nutzung und Kosten des DeepSeek-Kontos.</p>
                    </div>
                    <span class="kosten-pfeil" aria-hidden="true">↗</span>
                </a>
            </div>
        </section>

        <p class="foot">
            <?= e(APP_NAME) ?> · 🐑 ·
            <a href="hilfe.php">Hilfe</a> ·
            <a href="impressum.php">Impressum</a> ·
            <a href="datenschutz.php">Datenschutz</a> ·
            <a href="health.php">Systemcheck</a>
        </p>
    </div>

    <script>window.STARTER_CFG = <?= json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
    <script src="<?= asset_version('assets/starter.js') ?>"></script>
</body>
</html>
