<?php

declare(strict_types=1);

/** mitlesen.php — Verlauf eines Schülers aus der DB (E5), nur nach Login. */

require __DIR__ . '/inc/config.php';
require __DIR__ . '/inc/chat.php';
require __DIR__ . '/inc/auth.php';
require __DIR__ . '/inc/verwaltung.php';

auth_starten();
verwaltung_erzwingen();

$id = trim((string) ($_GET['id'] ?? ''));
$kontext = (string) ($_GET['kontext'] ?? 'chat');
if (!in_array($kontext, ['chat', 'quiz'], true)) {
    $kontext = 'chat';
}

$schueler = $id !== '' ? schueler_laden($id) : null;
$verlauf = $schueler !== null ? nachrichten_laden($id, $kontext, 500) : [];

verwaltung_kopf('Mitlesen', 'mitlesen.php');

if ($schueler === null) {
    echo '<p class="form-notice">Bitte einen Schüler wählen.</p>';
} else {
    echo '<p class="hinweis">' . e(schueler_name($schueler)) . ' · ID ' . e($id)
        . ' · ' . count($verlauf) . ' Nachrichten (' . e($kontext) . ')</p>';
}
?>
<div class="tags" style="margin:10px 0 16px">
    <?php foreach (schueler_liste() as $s): ?>
        <?php $sid = (string) $s['id_code']; ?>
        <a class="pill <?= $sid === $id ? 'pill--green' : '' ?>"
           href="mitlesen.php?id=<?= rawurlencode($sid) ?>"><?= e(schueler_name($s)) ?> · <?= e($sid) ?></a>
    <?php endforeach; ?>
</div>

<?php if ($schueler !== null): ?>
    <div class="tags" style="margin-bottom:14px">
        <a class="pill <?= $kontext === 'chat' ? 'pill--green' : '' ?>" href="mitlesen.php?id=<?= rawurlencode($id) ?>&amp;kontext=chat">Lernchat</a>
        <a class="pill <?= $kontext === 'quiz' ? 'pill--quiz' : '' ?>" href="mitlesen.php?id=<?= rawurlencode($id) ?>&amp;kontext=quiz">Quiz</a>
        <a class="pill" href="index.php?id=<?= rawurlencode($id) ?>">Chat öffnen</a>
    </div>

    <main class="chat-list chat-list--lesen" style="max-height:65vh;overflow:auto">
        <?php if ($verlauf === []): ?>
            <p class="msg-empty">Noch keine Nachrichten in diesem Bereich.</p>
        <?php endif; ?>
        <?php foreach ($verlauf as $z): ?>
            <div class="msg-row <?= (($z['richtung'] ?? '') === 'an_lehrer') ? 'msg-row--ich' : 'msg-row--gegen' ?>">
                <div class="msg-bubble">
                    <?php if ((string) ($z['text'] ?? '') !== ''): ?>
                        <span class="msg-text"><?= e((string) $z['text']) ?></span>
                    <?php endif; ?>
                    <?php if ((string) ($z['formel'] ?? '') !== ''): ?>
                        <div class="msg-formel"><math-div><?= e((string) $z['formel']) ?></math-div></div>
                    <?php endif; ?>
                    <div class="msg-meta" style="font-size:.75rem;color:#777;margin-top:4px">
                        <?= e((string) ($z['zeit'] ?? '')) ?>
                        <?php if (($z['richtig'] ?? null) !== null): ?>
                            · <?= (int) $z['richtig'] === 1 ? 'richtig' : 'falsch' ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/mathlive"></script>
<?php endif; ?>
<?php
verwaltung_fuss();
