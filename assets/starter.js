/**
 * Lehrer-Bereich (starter.php):
 * - "🔗 Link": kopiert den Chat-Link eines Schülers in die Zwischenablage
 *   (immer die Online-Adresse aus data-link).
 * - "📲 SMS": schickt eine Flash-SMS mit dem Chat-Link an die Schülernummer
 *   (Versand über api/sms.php → seven.io).
 */
(() => {
    'use strict';

    const cfg = window.STARTER_CFG || {};
    const FEEDBACK_MS = 1600;

    /** Kurze Rückmeldung auf dem Button (Text + Farbe), danach zurücksetzen. */
    function feedback(btn, label, original, ok, hinweis, okText) {
        label.textContent = ok ? (okText || '✓ OK') : '⚠ Fehler';
        btn.classList.toggle('is-ok', ok);
        btn.classList.toggle('is-fehler', !ok);

        if (hinweis) {
            btn.title = hinweis;
        }

        window.clearTimeout(btn._timer);
        btn._timer = window.setTimeout(() => {
            label.textContent = original;
            btn.classList.remove('is-ok', 'is-fehler');
        }, FEEDBACK_MS);
    }

    /** Meldungszeile oben auf der Seite (z. B. Fehler beim SMS-Versand). */
    function meldung(text, ok) {
        const el = document.getElementById('starter-meldung');
        if (!el) return;

        el.textContent = text;
        el.classList.toggle('form-notice', ok);
        el.classList.toggle('form-error', !ok);
        el.hidden = false;

        window.clearTimeout(meldung._timer);
        meldung._timer = window.setTimeout(() => {
            el.hidden = true;
        }, 8000);
    }

    /* ------------------------------------------------------ Chat-Link kopieren */

    const copyButtons = document.querySelectorAll('.js-kopieren');

    /** Kopiert Text; Fallback für unsichere Kontexte (http über LAN-IP). */
    async function kopiere(text) {
        if (navigator.clipboard && window.isSecureContext) {
            try {
                await navigator.clipboard.writeText(text);
                return true;
            } catch (e) {
                // weiter mit Fallback
            }
        }

        const hilfe = document.createElement('textarea');
        hilfe.value = text;
        hilfe.setAttribute('readonly', '');
        hilfe.style.position = 'fixed';
        hilfe.style.top = '-1000px';
        hilfe.style.left = '-1000px';
        hilfe.style.opacity = '0';
        document.body.appendChild(hilfe);

        let ok = false;
        try {
            hilfe.select();
            ok = document.execCommand('copy');
        } catch (e) {
            ok = false;
        }
        hilfe.remove();

        return ok;
    }

    copyButtons.forEach((btn) => {
        const label = btn.querySelector('.js-kopieren-text') || btn;
        const original = label.textContent;

        btn.addEventListener('click', async (event) => {
            event.preventDefault();
            event.stopPropagation();

            const ok = await kopiere(btn.dataset.link || '');

            if (ok) {
                feedback(btn, label, original, true, '', '✓ Kopiert');
            } else {
                feedback(btn, label, original, false, 'Kopieren nicht möglich – Link bitte manuell kopieren.');
            }
        });
    });

    /* ----------------------------------------------- Server-Aktionen (SMS/Anruf) */

    /**
     * Schickt eine Aktion (SMS oder Anruf) an den Server und zeigt die Rückmeldung
     * direkt auf dem Button; Fehler zusätzlich in der Meldungszeile oben.
     */
    function aktion(btn, label, original, opts) {
        if (btn.disabled || btn.dataset.laeuft === '1') return;

        btn.dataset.laeuft = '1';
        label.textContent = opts.laeuftLabel || '…';
        btn.classList.remove('is-ok', 'is-fehler');

        const fd = new FormData();
        fd.append('id', btn.dataset.id || '');
        fd.append('csrf', cfg.csrf || '');

        fetch(opts.endpoint, { method: 'POST', body: fd })
            .then((resp) => resp.json().catch(() => ({})).then((data) => ({ resp, data })))
            .then(({ resp, data }) => {
                if (resp.ok && data.ok) {
                    let hinweis = opts.okText;
                    if (data.nummer) hinweis += ' an ' + data.nummer;
                    if (data.text) hinweis += ': „' + data.text + '"';
                    feedback(btn, label, original, true, hinweis, opts.okLabel);
                } else {
                    const fehler = data.error || ('HTTP ' + resp.status);
                    console.warn(opts.fehlerPrefix + ':', fehler);
                    feedback(btn, label, original, false, 'Fehler: ' + fehler);
                    meldung(opts.fehlerPrefix + ': ' + fehler, false);
                }
            })
            .catch((e) => {
                console.warn(opts.fehlerPrefix + ':', e);
                feedback(btn, label, original, false, 'Netzwerkfehler.');
                meldung(opts.fehlerPrefix + ': Netzwerkfehler.', false);
            })
            .finally(() => {
                btn.dataset.laeuft = '0';
            });
    }

    // Flash-SMS
    document.querySelectorAll('.js-sms').forEach((btn) => {
        const label = btn.querySelector('.js-sms-text') || btn;
        const original = label.textContent;

        btn.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();

            aktion(btn, label, original, {
                endpoint: cfg.smsEndpoint || 'api/sms.php',
                laeuftLabel: '… sendet',
                okLabel: '✓ Gesendet',
                okText: 'SMS gesendet',
                fehlerPrefix: 'SMS-Versand fehlgeschlagen',
            });
        });
    });

    // Anruf (TTS-Ansage)
    document.querySelectorAll('.js-anruf').forEach((btn) => {
        const label = btn.querySelector('.js-anruf-text') || btn;
        const original = label.textContent;

        btn.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();

            aktion(btn, label, original, {
                endpoint: cfg.callEndpoint || 'api/call.php',
                laeuftLabel: '…',
                okLabel: '✓',
                okText: 'Anruf gestartet',
                fehlerPrefix: 'Anruf fehlgeschlagen',
            });
        });
    });
})();