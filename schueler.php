<?php

declare(strict_types=1);

/**
 * schueler.php — Schülerverwaltung (E5), nur nach Login.
 */

require __DIR__ . '/inc/config.php';
require __DIR__ . '/inc/chat.php';
require __DIR__ . '/inc/auth.php';
require __DIR__ . '/inc/verwaltung.php';

auth_starten();
verwaltung_erzwingen();

$filter = (string) ($_GET['filter'] ?? 'alle');
if (!in_array($filter, ['alle', 'ohne-einwilligung', 'ohne-sms', 'ohne-anruf'], true)) {
    $filter = 'alle';
}

/** Hat der Schüler irgendeine Einwilligung? */
$hat_einwilligung = static function (array $s): bool {
    return (int) ($s['ok_sms'] ?? 0) === 1
        || (int) ($s['ok_anruf'] ?? 0) === 1
        || (int) ($s['ok_quiz'] ?? 0) === 1
        || (int) ($s['ok_telegram'] ?? 0) === 1;
};

$alle     = schueler_liste();
$schueler = array_values(array_filter(
    $alle,
    static fn(array $s): bool => match ($filter) {
        'ohne-einwilligung' => !$hat_einwilligung($s),
        'ohne-sms'          => (int) ($s['ok_sms'] ?? 0) !== 1,
        'ohne-anruf'        => (int) ($s['ok_anruf'] ?? 0) !== 1,
        default             => true,
    }
));

$filter_liste = [
    'alle'              => ['Alle', count($alle)],
    'ohne-einwilligung' => ['Ohne Einwilligung', count(array_filter($alle, static fn(array $s): bool => !$hat_einwilligung($s)))],
    'ohne-sms'          => ['Ohne SMS', count(array_filter($alle, static fn(array $s): bool => (int) ($s['ok_sms'] ?? 0) !== 1))],
    'ohne-anruf'        => ['Ohne Anruf', count(array_filter($alle, static fn(array $s): bool => (int) ($s['ok_anruf'] ?? 0) !== 1))],
];

verwaltung_kopf('Schüler', 'schueler.php');
?>
<p class="hinweis">
    Einwilligungen werden je Schüler gepflegt (SMS, Anruf, Quiz, Telegram).
    Neue Kennung: nächste freie Nummer, änderbar.
</p>

<div class="form-actions" style="margin:10px 0 16px;flex-wrap:wrap">
    <a class="btn" href="schueler_edit.php">＋ Neuer Schüler</a>
    <a class="btn btn--secondary" href="links.php">Linkliste</a>
    <a class="btn btn--secondary" href="schueler_json.php" title="Rohdaten aller Schüler als schueler.json (voller Name, ohne Telefonnummern)">JSON-Export</a>
</div>

<div class="tags" style="margin-bottom:14px">
    <?php foreach ($filter_liste as $name => [$beschriftung, $anzahl]): ?>
        <a class="pill <?= $name === $filter ? 'pill--green' : '' ?>"
           href="schueler.php?filter=<?= rawurlencode($name) ?>"><?= e($beschriftung) ?> · <?= $anzahl ?></a>
    <?php endforeach; ?>
</div>

<section class="section">
    <div class="section-head">
        <h2><?= $filter === 'alle' ? 'Alle Schüler' : e($filter_liste[$filter][0]) ?></h2>
        <span class="count"><?= count($schueler) ?></span>
    </div>

    <?php if ($schueler === []): ?>
        <p class="form-notice">Keine Schüler in dieser Auswahl.</p>
    <?php endif; ?>

    <div class="students">
        <?php foreach ($schueler as $s): ?>
            <?php
            $id      = (string) $s['id_code'];
            $smsOk   = (int) ($s['ok_sms'] ?? 0) === 1;
            $anrufOk = (int) ($s['ok_anruf'] ?? 0) === 1;
            ?>
            <div class="student">
                <a class="student-main" href="schueler_edit.php?id=<?= rawurlencode($id) ?>">
                    <div class="avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr(schueler_name($s), 0, 1))) ?></div>
                    <div class="student-body">
                        <p class="student-name"><?= e(schueler_name($s)) ?></p>
                        <p class="student-meta">
                            ID <?= e($id) ?> · Klasse <?= e((string) ($s['klasse'] ?? '–')) ?>
                            · Stufe <?= e((string) ($s['stufe'] ?? '–')) ?>
                        </p>
                        <div class="tags">
                            <span class="pill pill--amber">⭐ <?= (int) ($s['bonuspunkte'] ?? 0) ?></span>
                            <span class="pill <?= $smsOk ? 'pill--green' : 'pill--warnung' ?>">SMS <?= $smsOk ? '✓' : '✗' ?></span>
                            <span class="pill <?= $anrufOk ? 'pill--green' : 'pill--warnung' ?>">Anruf <?= $anrufOk ? '✓' : '✗' ?></span>
                            <?php if ((int) ($s['ok_quiz'] ?? 0) === 1): ?>
                                <span class="pill pill--green">Quiz ✓</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
                <div class="student-action">
                    <a class="btn" href="schueler_edit.php?id=<?= rawurlencode($id) ?>">Bearbeiten</a>
                    <a class="btn btn--secondary" href="mitlesen.php?id=<?= rawurlencode($id) ?>">Mitlesen</a>
                    <a class="btn btn--secondary" href="zuordnung.php?id=<?= rawurlencode($id) ?>">Zuordnen</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php
verwaltung_fuss();
