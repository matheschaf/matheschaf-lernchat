/**
 * Matheschaf – Quiz-Töne (Web Audio, keine Audiodateien).
 *
 * Wird von zwei Stellen genutzt:
 *   - assets/quiz.js   → Quiz-Testlauf (quiz_test.php)
 *   - assets/chat.js   → 4. Würfelfläche "Teste dein Wissen" (index.php)
 *
 * Öffentliche API:
 *   MSQuizSound.istAn()          → true/false (merkt sich die Wahl in localStorage)
 *   MSQuizSound.setAn(wert)
 *   MSQuizSound.wecken()         → AudioContext nach einer Nutzer-Geste starten
 *   MSQuizSound.maeh()           → "mäh" bei falscher Antwort
 *   MSQuizSound.jubel()          → Applaus + Fanfare bei richtiger Antwort
 */
(() => {
    'use strict';

    const TON_KEY = 'matheschaf_quiz_ton';

    let tonAn = localStorage.getItem(TON_KEY) !== '0';
    let kontext = null;

    /** AudioContext träge anlegen (Browser erlauben ihn erst nach einer Geste). */
    function hoerer() {
        if (kontext) return kontext;

        const Ctx = window.AudioContext || window.webkitAudioContext;
        if (!Ctx) return null;

        try {
            kontext = new Ctx();
        } catch (e) {
            return null;
        }

        return kontext;
    }

    function wecken() {
        const c = hoerer();
        if (c && c.state === 'suspended' && typeof c.resume === 'function') {
            try { c.resume(); } catch (e) {}
        }
        return c;
    }

    /** "mäh": Sägezahn mit Vibrato (Meckern), ~0,7 s. */
    function maeh() {
        if (!tonAn) return;

        const c = wecken();
        if (!c) return;

        const jetzt = c.currentTime;
        const gain  = c.createGain();
        gain.gain.setValueAtTime(0.0001, jetzt);
        gain.gain.exponentialRampToValueAtTime(0.22, jetzt + 0.04);
        gain.gain.setValueAtTime(0.22, jetzt + 0.28);
        gain.gain.exponentialRampToValueAtTime(0.0001, jetzt + 0.66);

        const lfo     = c.createOscillator();
        const lfoGain = c.createGain();
        lfo.frequency.value = 11;
        lfoGain.gain.value  = 28;

        const osc = c.createOscillator();
        osc.type = 'sawtooth';
        osc.frequency.setValueAtTime(430, jetzt);
        osc.frequency.exponentialRampToValueAtTime(295, jetzt + 0.55);

        const osc2 = c.createOscillator();
        osc2.type = 'square';
        osc2.frequency.setValueAtTime(215, jetzt);
        osc2.frequency.exponentialRampToValueAtTime(148, jetzt + 0.55);

        const g2 = c.createGain();
        g2.gain.value = 0.32;

        const filter = c.createBiquadFilter();
        filter.type = 'bandpass';
        filter.frequency.value = 900;
        filter.Q.value = 1.4;

        lfo.connect(lfoGain).connect(osc.frequency);
        osc.connect(filter);
        osc2.connect(g2).connect(filter);
        filter.connect(gain).connect(c.destination);

        lfo.start(jetzt); osc.start(jetzt); osc2.start(jetzt);
        lfo.stop(jetzt + 0.7); osc.stop(jetzt + 0.7); osc2.stop(jetzt + 0.7);
    }

    /** Jubel: Applaus-Rauschen mit Klatsch-Puls + kleine aufsteigende Fanfare. */
    function jubel() {
        if (!tonAn) return;

        const c = wecken();
        if (!c) return;

        const jetzt  = c.currentTime;
        const dauer  = 1.5;
        const laenge = Math.floor(c.sampleRate * dauer);
        const puffer = c.createBuffer(1, laenge, c.sampleRate);
        const daten  = puffer.getChannelData(0);

        for (let i = 0; i < laenge; i++) {
            const t    = i / c.sampleRate;
            const puls = 0.45 + 0.55 * Math.abs(Math.sin(2 * Math.PI * (13 + 6 * Math.sin(t * 5)) * t));
            const huell = Math.min(1, t * 10) * Math.max(0, 1 - t / dauer);
            daten[i] = (Math.random() * 2 - 1) * puls * huell;
        }

        const quelle = c.createBufferSource();
        quelle.buffer = puffer;

        const hoch = c.createBiquadFilter();
        hoch.type = 'highpass';
        hoch.frequency.value = 850;

        const applause = c.createGain();
        applause.gain.value = 0.3;

        quelle.connect(hoch).connect(applause).connect(c.destination);
        quelle.start(jetzt);

        [523.25, 659.25, 783.99, 1046.5].forEach((f, i) => {
            const o  = c.createOscillator();
            const g  = c.createGain();
            const t0 = jetzt + 0.04 + i * 0.085;

            o.type = 'triangle';
            o.frequency.value = f;

            g.gain.setValueAtTime(0.0001, t0);
            g.gain.exponentialRampToValueAtTime(0.15, t0 + 0.02);
            g.gain.exponentialRampToValueAtTime(0.0001, t0 + 0.5);

            o.connect(g).connect(c.destination);
            o.start(t0);
            o.stop(t0 + 0.55);
        });
    }

    window.MSQuizSound = {
        istAn: () => tonAn,
        setAn: (wert) => {
            tonAn = !!wert;
            localStorage.setItem(TON_KEY, tonAn ? '1' : '0');
        },
        wecken,
        maeh,
        jubel,
    };
})();
