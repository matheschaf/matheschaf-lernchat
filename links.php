<?php

declare(strict_types=1);

/**
 * links.php — Chat- und Einwilligungslinks zum Austeilen/Drucken (E5b).
 *
 * Reine Liste ohne QR-Code: die Kennungen stehen in jeder Adresse, ein
 * QR-Dienst im Netz würde sie an Dritte übertragen. Zum Austeilen reicht
 * Kopieren oder Drucken (Papierstreifen).
 */

require __DIR__ . '/inc/config.php';
require __DIR__ . '/inc/chat.php';
require __DIR__ . '/inc/auth.php';
require __DIR__ . '/inc/verwaltung.php';

auth_starten();
verwaltung_erzwingen();

$schueler = schueler_liste();

verwaltung_kopf('Linkliste', 'schueler.php');
?>
<style>
    .links-tabelle { width: 100%; border-collapse: collapse; background: #fff; border-radius: 12px; overflow: hidden; }
    .links-tabelle th, .links-tabelle td { padding: 8px 10px; border-bottom: 1px solid #e6e6e6; text-align: left; vertical-align: top; font-size: .92rem; }
    .links-tabelle th { background: #f3f6f3; font-size: .82rem; text-transform: uppercase; letter-spacing: .03em; color: #444; }
    .links-tabelle code { font-size: .82rem; word-break: break-all; }
    .links-tabelle td.links-aktion { white-space: nowrap; }
    @media print {
        .topbar, .verwaltung-nav, .no-print { display: none !important; }
        body { background: #fff; }
        .wrap { padding: 0; }
        .links-tabelle { border: 1px solid #ccc; }
    }
</style>

<p class="hinweis no-print">
    <a href="schueler.php">← Zur Schülerliste</a> ·
    Chat-Link zum Verteilen, Einwilligungslink für die Eltern.
    Drucken liefert eine Liste ohne Knöpfe.
</p>

<div class="form-actions no-print" style="margin-bottom:16px;flex-wrap:wrap">
    <button type="button" class="btn" onclick="window.print()">Drucken</button>
    <button type="button" class="btn btn--secondary" data-kopiere-alle>Alle Chat-Links kopieren</button>
</div>

<?php if ($schueler === []): ?>
    <p class="form-notice">Noch keine Schüler vorhanden.</p>
<?php else: ?>
    <table class="links-tabelle">
        <thead>
            <tr>
                <th>Kennung</th>
                <th>Name</th>
                <th>Chat-Link</th>
                <th>Einwilligung</th>
                <th class="no-print"></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($schueler as $s): ?>
                <?php
                $id        = (string) $s['id_code'];
                $chatLink  = online_chat_link($id);
                $willLink  = BASE_URL . 'einwilligung.php?id=' . rawurlencode($id);
                $smsOk     = (int) ($s['ok_sms'] ?? 0) === 1;
                $anrufOk   = (int) ($s['ok_anruf'] ?? 0) === 1;
                ?>
                <tr>
                    <td><strong><?= e($id) ?></strong></td>
                    <td><?= e(schueler_name($s)) ?></td>
                    <td><code><?= e($chatLink) ?></code></td>
                    <td>
                        <a href="<?= e($willLink) ?>">Eltern-Link</a>
                        <div class="tags" style="margin-top:4px">
                            <span class="pill <?= $smsOk ? 'pill--green' : 'pill--warnung' ?>">SMS <?= $smsOk ? '✓' : '✗' ?></span>
                            <span class="pill <?= $anrufOk ? 'pill--green' : 'pill--warnung' ?>">Anruf <?= $anrufOk ? '✓' : '✗' ?></span>
                        </div>
                    </td>
                    <td class="links-aktion no-print">
                        <button type="button" class="btn btn--secondary"
                                data-kopiere="<?= e($chatLink) ?>">Kopieren</button>
                        <a class="btn btn--secondary" href="schueler_edit.php?id=<?= rawurlencode($id) ?>">Bearbeiten</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <script>
        (function () {
            var alle = <?= json_encode(
                array_map(
                    static fn(array $s): string => online_chat_link((string) $s['id_code']),
                    $schueler
                ),
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            ) ?>;

            async function kopieren(text, knopf, zurueck) {
                try {
                    await navigator.clipboard.writeText(text);
                    if (knopf) {
                        knopf.textContent = 'kopiert';
                        setTimeout(function () { knopf.textContent = zurueck; }, 1500);
                    }
                } catch (fehler) {
                    window.prompt('Link kopieren:', text);
                }
            }

            document.addEventListener('click', function (ev) {
                var knopf = ev.target.closest('[data-kopiere]');
                if (!knopf) {
                    return;
                }
                kopieren(knopf.dataset.kopiere, knopf, 'Kopieren');
            });

            var alleKnopf = document.querySelector('[data-kopiere-alle]');
            if (alleKnopf) {
                alleKnopf.addEventListener('click', function () {
                    kopieren(alle.join('\n'), alleKnopf, 'Alle Chat-Links kopieren');
                });
            }
        }());
    </script>
<?php endif; ?>
<?php
verwaltung_fuss();
