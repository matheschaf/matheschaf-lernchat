/**
 * Matheschaf – Bildschirmtastatur (Logik).
 * Layout: keyboard-layout.js (window.KB_LAYOUT).
 * Treibt ein Text-<input> und ein MathLive <math-field> an.
 */
(() => {
    'use strict';

    const LAYOUT = window.KB_LAYOUT;
    if (!LAYOUT) return;

    const LAYERS = [
        { id: 'text',      label: 'ABC',     rows: LAYOUT.text,      grid: false },
        { id: 'math',      label: '∑ Mathe', rows: LAYOUT.math,      grid: true  },
        { id: 'erweitert', label: 'αβγ',     rows: LAYOUT.erweitert, grid: true  }
    ];

    let textInput, mathField, root;
    let textFelder = [];          // alle Felder, die die Tastatur bedienen kann
    let mathFelder = [];          // alle Formelfelder (z. B. je Beispiel eines)
    // Beim Öffnen steht die Tastatur auf „Mathe", damit der Schüler sofort
    // die Antwort (Zahl/Formel) eintippen kann – Zahlen und Operatoren
    // landen dabei direkt im Formelfeld.
    let activeLayer = 'math';
    let activeField = 'text';
    let shiftActive = false;
    let tonAktiv = true;
    const TON_KEY = 'matheschaf_kb_ton';
    const isTouch = ('ontouchstart' in window) || (navigator.maxTouchPoints > 0);

    // Ton
    let audioCtx = null;
    function beep() {
        if (!tonAktiv) return;
        try {
            audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
            const o = audioCtx.createOscillator();
            const g = audioCtx.createGain();
            o.type = 'sine';
            o.frequency.value = 1000;
            g.gain.setValueAtTime(0.14, audioCtx.currentTime);
            g.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.08);
            o.connect(g);
            g.connect(audioCtx.destination);
            o.start();
            o.stop(audioCtx.currentTime + 0.08);
        } catch (e) {}
    }
    function loadTone() { try { tonAktiv = localStorage.getItem(TON_KEY) !== '0'; } catch (e) { tonAktiv = true; } }
    function saveTone() { try { localStorage.setItem(TON_KEY, tonAktiv ? '1' : '0'); } catch (e) {} }

    // Eingaben
    function setActiveField(field) {
        activeField = field;
        textFelder.forEach((f) => f.classList.toggle('kb-aktiv', field === 'text' && f === textInput));
        mathFelder.forEach((f) => f.classList.toggle('kb-aktiv', field === 'math' && f === mathField));
    }

    /** Ziel-Textfeld wechseln – z. B. per Klick in ein anderes Aufgaben-Feld. */
    function setActiveText(el) {
        if (!el) return;
        textInput = el;
        setActiveField('text');
    }

    /** Ziel-Formelfeld wechseln (mehrere Formelfelder möglich). */
    function setActiveMath(el) {
        if (!el) return;
        mathField = el;
        setActiveField('math');
    }

    // Cursor-Position; bei read-only (Touch) oder ohne Fokus ans Ende springen.
    function cursor() {
        if (!textInput) return [0, 0];

        if (textInput.hasAttribute('readonly') || document.activeElement !== textInput) {
            const n = textInput.value.length;
            return [n, n];
        }
        return [textInput.selectionStart ?? textInput.value.length, textInput.selectionEnd ?? textInput.value.length];
    }

    function insertText(text) {
        if (!textInput) return;

        const [s, e] = cursor();
        textInput.value = textInput.value.slice(0, s) + text + textInput.value.slice(e);
        textInput.selectionStart = textInput.selectionEnd = s + text.length;
        textInput.dispatchEvent(new Event('input'));
    }

    /** Fügt LaTeX ins Formelfeld ein. false = es gibt (hier) kein Formelfeld. */
    function insertMath(latex) {
        if (!mathField) return false;

        if (typeof mathField.insert === 'function') mathField.insert(latex);
        else mathField.value = (mathField.value || '') + latex;
        // bewusst KEIN focus(): sonst öffnet MathLive seine eigene Tastatur

        return true;
    }

    function backspace() {
        if (activeField === 'math' && mathField && typeof mathField.executeCommand === 'function') {
            mathField.executeCommand('deleteBackward');
            return;
        }
        if (!textInput) return;

        const [s, e] = cursor();
        if (s === e) {
            if (s === 0) return;
            textInput.value = textInput.value.slice(0, s - 1) + textInput.value.slice(s);
            textInput.selectionStart = textInput.selectionEnd = s - 1;
        } else {
            textInput.value = textInput.value.slice(0, s) + textInput.value.slice(e);
            textInput.selectionStart = textInput.selectionEnd = s;
        }
        textInput.dispatchEvent(new Event('input'));
    }

    // Tasten bauen
    function keyData(key) {
        if (typeof key === 'string') return { label: key, latex: key, action: null, wide: false, small: false, cls: '' };
        return { label: key.l, latex: key.latex ?? key.l, action: key.action ?? null, wide: !!key.wide, small: !!key.small, cls: key.cls ?? '' };
    }

    function makeKey(key, layerId) {
        const k = keyData(key);
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'kb-key' + (k.wide ? ' kb-key--wide' : '') + (k.small ? ' kb-key--small' : '') + (k.cls ? ' ' + k.cls : '');
        b.textContent = k.label;
        if (k.action) b.dataset.action = k.action;
        else { b.dataset.insert = k.latex; b.dataset.target = layerId === 'text' ? 'text' : 'math'; }
        return b;
    }

    function buildLayer(layer) {
        const el = document.createElement('div');
        el.className = 'kb-layer kb-layer--' + layer.id;
        layer.rows.forEach((row) => {
            if (layer.grid) {
                row.forEach((key) => el.appendChild(makeKey(key, layer.id)));
            } else {
                const r = document.createElement('div');
                r.className = 'kb-row';
                row.forEach((key) => r.appendChild(makeKey(key, layer.id)));
                el.appendChild(r);
            }
        });
        return el;
    }

    function buildStrip() {
        const strip = document.createElement('div');
        strip.className = 'kb-strip';
        (LAYOUT.strip || []).forEach((key) => {
            const k = keyData(key);
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'kb-chip';
            b.textContent = k.label;
            if (k.action) b.dataset.action = k.action;
            else { b.dataset.insert = k.latex; b.dataset.target = 'math'; }
            strip.appendChild(b);
        });
        return strip;
    }

    function buildTabs() {
        const tabs = document.createElement('div');
        tabs.className = 'kb-tabs';
        LAYERS.forEach((layer) => {
            const t = document.createElement('button');
            t.type = 'button';
            t.className = 'kb-tab' + (layer.id === activeLayer ? ' kb-tab--aktiv' : '');
            t.dataset.layer = layer.id;
            t.textContent = layer.label;
            tabs.appendChild(t);
        });
        const tone = document.createElement('button');
        tone.type = 'button'; tone.id = 'kb-ton'; tone.className = 'kb-tab kb-tab--icon';
        tone.textContent = tonAktiv ? '🔊' : '🔇'; tone.title = 'Tastatur-Töne';
        tabs.appendChild(tone);
        const hide = document.createElement('button');
        hide.type = 'button'; hide.id = 'kb-hide'; hide.className = 'kb-tab kb-tab--icon';
        hide.textContent = '▼'; hide.title = 'Tastatur ausblenden';
        tabs.appendChild(hide);
        return tabs;
    }

    // Steuerung
    function switchLayer(id, fokus = true) {
        activeLayer = id;
        root.querySelectorAll('.kb-layer').forEach((el) => {
            el.classList.toggle('kb-layer--aktiv', el.classList.contains('kb-layer--' + id));
        });
        root.querySelectorAll('.kb-tab[data-layer]').forEach((t) => {
            t.classList.toggle('kb-tab--aktiv', t.dataset.layer === id);
        });
        if (id === 'text') {
            setActiveField('text');
            if (fokus && textInput) textInput.focus({ preventScroll: true });
        } else {
            // Ohne Formelfeld tippt die Mathe-Ebene ins aktive Textfeld.
            setActiveField(mathField ? 'math' : 'text');
            if (fokus && !isTouch && mathField && typeof mathField.focus === 'function') {
                mathField.focus({ preventScroll: true });
            }
        }
    }

    function toggleShift() {
        shiftActive = !shiftActive;
        const sh = root.querySelector('[data-action="shift"]');
        if (sh) sh.classList.toggle('aktiv', shiftActive);
        const tl = root.querySelector('.kb-layer--text');
        if (tl) tl.classList.toggle('upper', shiftActive);
    }

    function toggleTone() {
        tonAktiv = !tonAktiv;
        saveTone();
        const btn = root.querySelector('#kb-ton');
        if (btn) btn.textContent = tonAktiv ? '🔊' : '🔇';
    }

    function toggleHide() {
        root.classList.toggle('kb-geschlossen');
        const btn = root.querySelector('#kb-hide');
        if (btn) btn.textContent = root.classList.contains('kb-geschlossen') ? '▲' : '▼';
    }

    function handleInsert(b) {
        const val = b.dataset.insert ?? b.textContent;

        if (b.dataset.target === 'math') {
            // Ohne Formelfeld (z. B. Baustein-Generator) das lesbare Zeichen
            // einsetzen – √, π, ≤ … statt LaTeX-Code wie \sqrt{}.
            if (!insertMath(val)) insertText(b.textContent);
            return;
        }

        const ch = shiftActive ? val.toUpperCase() : val;

        if (activeField === 'math' && mathField) insertMath(ch);
        else insertText(ch);

        if (shiftActive) toggleShift();
    }

    function onClick(e) {
        const b = e.target.closest('button');
        if (!b) return;
        if (b.id === 'kb-ton') { beep(); toggleTone(); return; }
        if (b.id === 'kb-hide') { beep(); toggleHide(); return; }
        if (b.dataset.layer) { beep(); switchLayer(b.dataset.layer); return; }
        const action = b.dataset.action;
        if (action === 'backspace') { beep(); backspace(); }
        else if (action === 'shift') { beep(); toggleShift(); }
        else if (action === 'space') { beep(); insertText(' '); }
        else if (action === 'enter') { beep(); insertText('\n'); }
        else { beep(); handleInsert(b); }
    }

    function init() {
        root = document.querySelector('[data-tastatur]');
        if (!root) return;

        // data-text darf mehrere Felder treffen (z. B. ".kb-feld" im Generator).
        textFelder = Array.prototype.slice.call(
            document.querySelectorAll(root.dataset.text || '#tastatur-text')
        );
        textInput = textFelder[0] || null;

        // data-math ist optional und darf mehrere Formelfelder treffen
        // (z. B. ein Formelfeld je Beispiel im Baustein-Generator).
        mathFelder = root.dataset.math
            ? Array.prototype.slice.call(document.querySelectorAll(root.dataset.math))
            : [];
        mathField = mathFelder[0] || null;

        if (!textInput && !mathField) return;

        // MathLive-eigene Tastatur/UI + native OS-Tastatur deaktivieren
        mathFelder.forEach((feld) => {
            try { feld.virtualKeyboardMode = 'manual'; } catch (e) {}
            try { feld.virtualKeyboardToggleGlyph = 'none'; } catch (e) {}
            try { feld.menuGlyph = 'none'; } catch (e) {}
            try { feld.inputMode = 'none'; } catch (e) {}
            if (feld.shadowRoot) {
                try {
                    feld.shadowRoot.querySelectorAll('textarea, input, [contenteditable]').forEach((el) => {
                        el.setAttribute('inputmode', 'none');
                    });
                } catch (e) {}
            }

            if (isTouch) {
                // Nur auf Touch: Fokus verhindern → keine MathLive-/native Tastatur
                feld.setAttribute('tabindex', '-1');
                ['pointerdown', 'touchstart', 'mousedown'].forEach((evt) => {
                    feld.addEventListener(evt, (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        setActiveMath(feld);
                    }, { capture: true });
                });
            } else {
                // Desktop: Fokus erlauben → physische Tastatur funktioniert
                feld.addEventListener('focus', () => setActiveMath(feld));
            }
        });

        // Jedes Textfeld kann Ziel werden – das zuletzt berührte gewinnt.
        textFelder.forEach((feld) => {
            feld.addEventListener('pointerdown', () => setActiveText(feld));
            feld.addEventListener('focus', () => setActiveText(feld));
        });

        // Auf Touch-Geräten das native Tastatur-Aufklappen verhindern
        // (Desktop behält die physische Tastatur). Nur im Schüler-Chat mit
        // Formelfeld – mit data-tippen (Generator, Lehrer-Werkzeuge) bleibt
        // die normale Eingabe erlaubt.
        const tippenErlaubt = root.hasAttribute('data-tippen');

        if (isTouch && mathField && !tippenErlaubt) {
            textFelder.forEach((feld) => feld.setAttribute('readonly', ''));
        }

        loadTone();
        root.appendChild(buildStrip());
        root.appendChild(buildTabs());
        LAYERS.forEach((l) => root.appendChild(buildLayer(l)));
        // Start auf „Mathe" – aber ohne Focus zu stehlen (fokus = false).
        switchLayer('math', false);
        root.addEventListener('click', onClick);

        const toggleBtn = document.getElementById('kb-toggle-btn');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', () => {
                root.hidden = !root.hidden;
                toggleBtn.setAttribute('aria-expanded', String(!root.hidden));
            });
        }
    }

    function whenReady(fn) {
        // Seiten ohne Formelfeld (Baustein-Generator) brauchen nicht auf
        // MathLive zu warten – Tastatur sofort aufbauen.
        if (!document.querySelector('math-field')) { fn(); return; }

        if (window.customElements && window.customElements.get && window.customElements.get('math-field')) { fn(); return; }
        let done = false;
        const run = () => { if (!done) { done = true; fn(); } };
        if (window.customElements && window.customElements.whenDefined) {
            window.customElements.whenDefined('math-field').then(run);
        }
        setTimeout(run, 1500);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => whenReady(init));
    else whenReady(init);
})();
