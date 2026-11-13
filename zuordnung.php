<?php

declare(strict_types=1);

/** zuordnung.php — Lern- und Quiz-Baustein einem Schüler zuweisen (E5). */

require __DIR__ . '/inc/config.php';
require __DIR__ . '/inc/chat.php';
require __DIR__ . '/inc/auth.php';
require __DIR__ . '/inc/verwaltung.php';

auth_starten();
verwaltung_erzwingen();

$schueler = schueler_liste();
if ($schueler === []) {
    verwaltung_kopf('Zuordnung', 'zuordnung.php');
    echo '<p class="form-notice">Noch keine Schüler vorhanden.</p>';
    verwaltung_fuss();
    exit;
}

$id = (string) ($_GET['id'] ?? $schueler[0]['id_code']);
$student = schueler_laden($id);
if ($student === null) {
    $id = (string) $schueler[0]['id_code'];
    $student = schueler_laden($id);
}

$uebersicht = bausteine_uebersicht();
$meldung = '';
$fehler = [];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_pruefen($_POST['csrf'] ?? null)) {
        $fehler[] = 'Ungültiges Token, bitte Seite neu laden.';
    } else {
        $id = (string) ($_POST['id'] ?? '');
        $fehler = zuordnung_setzen(
            $id,
            (string) ($_POST['aktiver_baustein'] ?? ''),
            (string) ($_POST['quiz_baustein'] ?? '')
        );
        if ($fehler === []) {
            $meldung = 'Zuordnung gespeichert.';
        }
        $student = schueler_laden($id);
    }
}

verwaltung_kopf('Zuordnung', 'zuordnung.php');

if ($meldung !== '') {
    echo '<p class="form-notice">' . e($meldung) . '</p>';
}
if ($fehler !== []) {
    echo '<p class="form-error">' . e(implode(' ', $fehler)) . '</p>';
}
?>
<p class="hinweis">Schüler wählen, dann Lern- und Quiz-Baustein zuweisen.</p>

<div class="tags" style="margin-bottom:14px">
    <?php foreach ($schueler as $s): ?>
        <?php $sid = (string) $s['id_code']; ?>
        <a class="pill <?= $sid === $id ? 'pill--green' : '' ?>"
           href="zuordnung.php?id=<?= rawurlencode($sid) ?>"><?= e(schueler_name($s)) ?> · <?= e($sid) ?></a>
    <?php endforeach; ?>
</div>

<form class="form" method="post" action="zuordnung.php?id=<?= rawurlencode((string) ($student['id_code'] ?? $id)) ?>">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="id" value="<?= e((string) ($student['id_code'] ?? $id)) ?>">

    <p class="hinweis">
        Aktuell: <?= e(schueler_name($student)) ?> (ID <?= e((string) $student['id_code']) ?>)
    </p>

    <div class="field">
        <label for="aktiver_baustein">Lern-Baustein</label>
        <select id="aktiver_baustein" name="aktiver_baustein">
            <option value="">– keiner –</option>
            <?php foreach ($uebersicht as $slug => $b): ?>
                <?php if ($b['art'] !== 'lern') { continue; } ?>
                <option value="<?= e((string) $slug) ?>" <?= (string) ($student['aktiver_baustein'] ?? '') === (string) $slug ? 'selected' : '' ?>>
                    <?= e((string) $b['titel']) ?> (<?= e((string) $slug) ?>)
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="field">
        <label for="quiz_baustein">Quiz-Baustein</label>
        <select id="quiz_baustein" name="quiz_baustein">
            <option value="">– keiner –</option>
            <?php foreach ($uebersicht as $slug => $b): ?>
                <?php if ($b['art'] !== 'quiz') { continue; } ?>
                <option value="<?= e((string) $slug) ?>" <?= (string) ($student['quiz_baustein'] ?? '') === (string) $slug ? 'selected' : '' ?>>
                    <?= e((string) $b['titel']) ?> (<?= e((string) $slug) ?>)
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn">Speichern</button>
        <a class="btn btn--secondary" href="schueler_edit.php?id=<?= rawurlencode((string) $student['id_code']) ?>">Schüler bearbeiten</a>
    </div>
</form>
<?php
verwaltung_fuss();
