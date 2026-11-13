/**
 * Matheschaf – Quiz (Editor + Testlauf)
 *
 * Zwei Aufgaben in einer Datei:
 *  1. Quiz-Editor (quiz_edit.php): Zeilen für Fragen hinzufügen/entfernen
 *  2. Testlauf (quiz_test.php): Chat mit der KI, Konfetti bei richtiger
 *     Antwort, "mäh" bei falscher – ohne Schüler und ohne Speichern
 *
 * Nichts hier schreibt in schueler.json; der Verlauf lebt nur im Browser.
 */
(() => {
    'use strict';

    // ---------------------------------------------------------------- Editor
    const zeilenBox = document.getElementById('quiz-zeilen');

    if (zeilenBox) {
        const plusKnopf = document.getElementById('quiz-plus');

        const neuNummerieren = () => {
            zeilenBox.querySelectorAll('.quiz-zeile').forEach((zeile, i) => {
                const nr = zeile.querySelector('.quiz-nr');
                if (nr) nr.textContent = String(i + 1);
            });
        };

        const zeileBauen = () => {
            const div = document.createElement('div');
            div.className = 'quiz-zeile';
            div.innerHTML =
                '<span class="quiz-nr"></span>' +
                '<input name="frage[]" type="text" maxlength="300" placeholder="Frage">' +
                '<input name="antwort[]" type="text" maxlength="200" placeholder="richtige Antwort">' +
                '<button type="button" class="quiz-weg" aria-label="Zeile löschen">✕</button>';
            return div;
        };

        if (plusKnopf) {
            plusKnopf.addEventListener('click', () => {
                const zeile = zeileBauen();
                zeilenBox.appendChild(zeile);
                neuNummerieren();
                const input = zeile.querySelector('input');
                if (input) input.focus();
            });
        }

        zeilenBox.addEventListener('click', (e) => {
            const knopf = e.target.closest('.quiz-weg');
            if (!knopf) return;
            const zeile = knopf.closest('.quiz-zeile');
            if (!zeile) return;

            // Letzte Zeile nie löschen – sonst steht das Formular leer da.
            if (zeilenBox.querySelectorAll('.quiz-zeile').length > 1) {
                zeile.remove();
            } else {
                zeile.querySelectorAll('input').forEach((i) => { i.value = ''; });
            }
            neuNummerieren();
        });

        // Beim Speichern: Sanduhr im Knopf, Doppelklick unmöglich
        const formular = document.getElementById('quiz-form');

        if (formular) {
            formular.addEventListener('submit', () => {
                const knopf = formular.querySelector('button[type="submit"]');

                if (knopf && !knopf.disabled) {
                    knopf.dataset.text = knopf.textContent;
                    knopf.textContent = '⏳ Speichern …';
                    knopf.disabled = true;
                }
            });
        }
    }

    // --------------------------------------------------------------- Testlauf
    const cfg = window.QUIZ_CFG;

    if (!cfg) {
        return;
    }

    const chatEl   = document.getElementById('quiz-chat');
    const emptyEl  = document.getElementById('quiz-empty');
    const textEl   = document.getElementById('quiz-text');
    const sendBtn  = document.getElementById('quiz-send');
    const startBtn = document.getElementById('quiz-start');
    const nameEl   = document.getElementById('quiz-name');
    const stufeEl  = document.getElementById('quiz-stufe');
    const tonBtn   = document.getElementById('quiz-ton');
    const layer    = document.getElementById('konfetti-layer');

    if (!chatEl || !textEl || !sendBtn) {
        return;
    }

    // Töne kommen aus assets/quiz-sound.js (gemeinsam mit der 4. Würfelfläche)
    const ton      = window.MSQuizSound || null;
    let   tonAktiv = ton ? ton.istAn() : false;
    let   verlauf  = [];
    let   laeuft   = false;

    // ---- Ton-Schalter ----
    const tonMal = () => {
        if (!tonBtn) return;
        tonBtn.textContent = tonAktiv ? '🔊' : '🔇';
        tonBtn.setAttribute('aria-pressed', tonAktiv ? 'true' : 'false');
    };
    tonMal();

    if (tonBtn) {
        tonBtn.addEventListener('click', () => {
            tonAktiv = !tonAktiv;
            if (ton) ton.setAn(tonAktiv);
            tonMal();
            if (tonAktiv && ton) ton.maeh();      // kurze Hörprobe
        });
    }

    // Kurzschreibweisen – die Töne selbst liegen in assets/quiz-sound.js
    const maeh  = () => { if (ton) ton.maeh(); };
    const jubel = () => { if (ton) ton.jubel(); };

    // ---- Zuschauer-Tribüne reagiert ----
    const tribuene = document.getElementById('quiz-tribuene');

    function tribueneReaktion(art) {
        if (!tribuene) return;

        tribuene.classList.remove('ist-jubel', 'ist-buh');
        void tribuene.offsetWidth;                 // Neustart der Animation erzwingen
        tribuene.classList.add(art === 'jubel' ? 'ist-jubel' : 'ist-buh');

        window.setTimeout(() => tribuene.classList.remove('ist-jubel', 'ist-buh'), 1500);
    }


    // ---- Konfetti ----
    const reduziert = () => !!(window.matchMedia
        && window.matchMedia('(prefers-reduced-motion: reduce)').matches);

    function konfetti() {
        if (!layer || reduziert()) return;

        const farben = ['#2f7d32', '#f2a007', '#e53935', '#1e88e5', '#8e24aa', '#fdd835'];

        for (let i = 0; i < 70; i++) {
            const s = document.createElement('span');
            s.className = 'konfetti';
            s.style.left = (Math.random() * 100).toFixed(2) + '%';
            s.style.background = farben[i % farben.length];
            s.style.width = (5 + Math.random() * 6).toFixed(0) + 'px';
            s.style.height = (8 + Math.random() * 7).toFixed(0) + 'px';
            s.style.setProperty('--delay', (Math.random() * 0.3).toFixed(2) + 's');
            s.style.setProperty('--dauer', (1.5 + Math.random() * 1.4).toFixed(2) + 's');
            s.style.setProperty('--dreh', Math.round(Math.random() * 900 - 450) + 'deg');
            layer.appendChild(s);
            window.setTimeout(() => s.remove(), 3400);
        }
    }

    // ---- Chat ----
    const ansEnde = () => { chatEl.scrollTop = chatEl.scrollHeight; };

    function zeigePunkt() {
        const row = document.createElement('div');
        row.className = 'msg-row msg-row--gegen';
        row.dataset.punkt = '1';

        const bubble = document.createElement('div');
        bubble.className = 'msg-bubble msg-bubble--denkt';

        const sanduhr = document.createElement('span');
        sanduhr.className = 'quiz-sanduhr';
        sanduhr.setAttribute('aria-hidden', 'true');
        sanduhr.textContent = '⏳';

        const text = document.createElement('span');
        text.className = 'msg-text quiz-denk-text';
        text.textContent = 'Einen Moment, die Frage kommt …';

        bubble.append(sanduhr, text);
        row.appendChild(bubble);
        chatEl.appendChild(row);
        ansEnde();
        return row;
    }

    /**
     * Trennt eine KI-Antwort in "Vorlauf" (Rückmeldung) und "Frage".
     * Trennlinie ist die letzte Zeile mit "Frage x von y", sonst der letzte Absatz.
     */
    function teileAntwort(text) {
        const roh = String(text || '').trim();

        if (roh === '') {
            return { vorlauf: '', frage: '' };
        }

        const zeilen = roh.split(/\r?\n/);
        let idx = -1;

        for (let i = zeilen.length - 1; i >= 0; i--) {
            if (/Frage\s+\d+\s+von\s+\d+/i.test(zeilen[i])) {
                idx = i;
                break;
            }
        }

        if (idx > 0) {
            return {
                vorlauf: zeilen.slice(0, idx).join('\n').trim(),
                frage:   zeilen.slice(idx).join('\n').trim(),
            };
        }

        const absaetze = roh.split(/\n{2,}/);

        if (absaetze.length > 1) {
            return {
                vorlauf: absaetze.slice(0, -1).join('\n\n').trim(),
                frage:   absaetze[absaetze.length - 1].trim(),
            };
        }

        return { vorlauf: '', frage: roh };
    }

    /** Scrollt eine Karte an die Oberkante des Sichtfensters (neue Frage oben). */
    function karteOben(row) {
        if (!row || !chatEl) return;

        const setzen = () => {
            const pad  = parseFloat(window.getComputedStyle(chatEl).paddingTop) || 0;
            const ziel = row.getBoundingClientRect().top
                - chatEl.getBoundingClientRect().top
                + chatEl.scrollTop
                - pad;

            chatEl.scrollTop = Math.max(0, ziel);
        };

        setzen();                                   // sofort und exakt
        window.setTimeout(setzen, 180);             // Nachkorrektur nach dem Formel-Reflow
    }

    // Zerlegt die KI-Antwort in Fragetext, Antwortoptionen und "Frage x von y".
    const OPTION_MUSTER = /^\s*([A-D])\s*[).:\-]\s*(.+)$/i;

    function zerlegeAntwort(roh) {
        const zeilen  = String(roh || '').split(/\r?\n/);
        const optionen = [];
        const rest     = [];

        zeilen.forEach((zeile) => {
            const treffer = zeile.match(OPTION_MUSTER);

            if (treffer) {
                optionen.push({ buchstabe: treffer[1].toUpperCase(), text: treffer[2].trim() });
            } else {
                rest.push(zeile);
            }
        });

        const nr = String(roh || '').match(/Frage\s+(\d+)\s+von\s+(\d+)/i);

        return {
            frage:    rest.join('\n').trim(),
            optionen: optionen,
            nr:       nr ? nr[1] : '',
            gesamt:   nr ? nr[2] : ''
        };
    }

    function optionenSperren() {
        document.querySelectorAll('.quiz-opt').forEach((k) => { k.disabled = true; });
    }

    function blase(rolle, text, formel, karte) {
        if (emptyEl) emptyEl.hidden = true;

        const row = document.createElement('div');
        row.className = 'msg-row ' + (rolle === 'user' ? 'msg-row--ich' : 'msg-row--gegen');

        const bubble = document.createElement('div');
        bubble.className = 'msg-bubble';

        if (karte && karte.nr) {
            const chip = document.createElement('span');
            chip.className = 'quiz-chip';
            chip.textContent = 'Frage ' + karte.nr + (karte.gesamt ? ' von ' + karte.gesamt : '');
            bubble.appendChild(chip);
        }

        if (text) {
            const span = document.createElement('span');
            span.className = 'msg-text';
            span.textContent = text;
            bubble.appendChild(span);
        }

        if (formel) {
            const box = document.createElement('div');
            box.className = 'msg-formel';
            const md = document.createElement('math-div');
            md.textContent = formel;
            box.appendChild(md);
            bubble.appendChild(box);
        }

        // Antwortknöpfe direkt an der Frage
        if (karte && karte.optionen && karte.optionen.length >= 2) {
            bubble.classList.add('msg-bubble--frage');
            row.classList.add('msg-row--frage');

            const grid = document.createElement('div');
            grid.className = 'quiz-optionen';

            karte.optionen.forEach((o) => {
                const knopf = document.createElement('button');
                knopf.type = 'button';
                knopf.className = 'quiz-opt quiz-opt--' + o.buchstabe.toLowerCase();

                const buchstabe = document.createElement('span');
                buchstabe.className = 'quiz-opt-buchstabe';
                buchstabe.textContent = o.buchstabe;

                const inhalt = document.createElement('span');
                inhalt.className = 'quiz-opt-text';
                inhalt.textContent = o.text;

                knopf.append(buchstabe, inhalt);

                knopf.addEventListener('click', () => {
                    if (knopf.disabled) return;
                    optionenSperren();
                    knopf.classList.add('ist-gewaehlt');
                    senden(o.buchstabe);
                });

                grid.appendChild(knopf);
            });

            bubble.appendChild(grid);
        }

        row.appendChild(bubble);
        chatEl.appendChild(row);

        // Mit Optionen (Frage) wird hinterher nach oben gescrollt,
        // sonst (Fehlermeldung, reine Auflösung) bleibt der Blick unten.
        if (!(karte && karte.optionen && karte.optionen.length >= 2)) {
            ansEnde();
        }

        return row;
    }


    // ---- Klartext-Fehler statt „irgendwas ging schief" ----
    function serverMeldung(status, data) {
        if (data && data.fehler) {
            return data.fehler;
        }

        if (status === 404) {
            return 'Der Server-Endpunkt fehlt (HTTP 404). Bitte api/quiz_test.php auf den Server hochladen.';
        }

        if (status === 409) {
            return 'Für diesen Baustein gibt es keinen Prompt – bitte im Quiz-Editor speichern.';
        }

        if (status === 500 || status === 502 || status === 503) {
            return 'Der Server konnte die Frage nicht holen (HTTP ' + status + '). Bitte im error_log nachsehen.';
        }

        return 'Keine Antwort vom Matheschaf (HTTP ' + status + ').';
    }

    async function fragen() {
        if (laeuft) return;

        laeuft = true;
        optionenSperren();
        if (sendBtn) sendBtn.disabled = true;
        if (startBtn) startBtn.disabled = true;

        const punkt = zeigePunkt();

        try {
            const fd = new FormData();
            fd.append('slug', cfg.slug);
            fd.append('name', (nameEl && nameEl.value.trim()) || 'Test');
            fd.append('stufe', (stufeEl && stufeEl.value) || 'leicht');
            fd.append('verlauf', JSON.stringify(verlauf));

            const resp = await fetch(cfg.endpoint, { method: 'POST', body: fd });

            let data = null;
            try { data = await resp.json(); } catch (e) { data = null; }

            if (punkt) punkt.remove();

            if (!resp.ok || !data || !data.ok) {
                blase('assistant', '⚠️ ' + serverMeldung(resp.status, data), '');
                return;
            }

            const teile = zerlegeAntwort(data.text || '');
            const split = teileAntwort(teile.frage);

            // Rückmeldung als eigene Blase (scrollt nach unten)
            if (split.vorlauf !== '') {
                blase('assistant', split.vorlauf, '', null);
            }

            const row = blase('assistant', split.frage, data.formel || '', teile);

            if (teile.optionen.length >= 2) {
                karteOben(row);                 // neue Frage beginnt oben im Sichtfenster
            }

            if (data.richtig === true) {
                row.classList.add('ist-richtig');
                konfetti();
                jubel();
                tribueneReaktion('jubel');
            } else if (data.richtig === false) {
                row.classList.add('ist-falsch');
                maeh();
                tribueneReaktion('buh');
            }

            verlauf.push({
                role: 'assistant',
                content: (data.text || '') + (data.formel ? "\nFormel: " + data.formel : '')
            });
        } catch (e) {
            if (punkt) punkt.remove();
            blase('assistant', '⚠️ Keine Verbindung zum Matheschaf.', '');
        } finally {
            laeuft = false;
            if (sendBtn) sendBtn.disabled = false;
            if (startBtn) startBtn.disabled = false;
            if (textEl) textEl.focus();
        }
    }

    function senden(text) {
        const inhalt = String(text || '').trim();
        if (inhalt === '' || laeuft) return;

        optionenSperren();

        blase('user', inhalt, '');
        verlauf.push({ role: 'user', content: inhalt });

        if (textEl) textEl.value = '';
        fragen();
    }

    if (sendBtn) sendBtn.addEventListener('click', () => senden(textEl.value));

    if (textEl) {
        textEl.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                senden(textEl.value);
            }
        });
    }

    if (startBtn) {
        startBtn.addEventListener('click', () => {
            if (laeuft) return;
            verlauf = [];
            chatEl.querySelectorAll('.msg-row').forEach((r) => r.remove());
            if (emptyEl) emptyEl.hidden = true;
            fragen();
        });

        // ?start=1 – Testlauf beginnt sofort
        if (cfg.autoStart) {
            startBtn.click();
        }
    }
})();
