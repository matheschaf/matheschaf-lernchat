/**
 * Schüler-Chat (index.php?id=…).
 * Sendet Antworten an api/chat.php und zeigt die KI-Antwort an.
 */
(() => {
    'use strict';

    const cfg = window.CHAT_CFG;
    if (!cfg) return;

    const listEl = document.getElementById('chat-list');
    const emptyEl = document.getElementById('msg-empty');
    const textEl = document.getElementById('chat-text');
    const formelEl = document.getElementById('chat-formel');
    const sendBtn = document.getElementById('chat-send');

    if (!listEl || !textEl || !sendBtn) return;

    // ---- 3D-Würfel: linker Flügel · Chat · Schafswiese ---------------------
    // Die drei Bereiche sind Flächen eines Würfels (CSS: .fluegel-wrap/.wuerfel).
    // Position 0 = links, 1 = Chat (Mitte), 2 = Wiese. Gedreht wird der Würfel
    // pro Schritt um 90°: 0° → -90° → -180°.
    const wrapEl = document.getElementById('fluegel-wrap');
    const wuerfelEl = document.getElementById('wuerfel');

    const wingLeft = document.getElementById('wing-left');
    const wingRight = document.getElementById('wing-right');
    const wingLeftBtn = document.getElementById('wing-left-btn');
    const wingRightBtn = document.getElementById('wing-right-btn');

    const WINKEL_SCHRITT = 90;   // Grad pro Fläche
    const WISCH_START = 12;      // px, ab hier wird die Richtung erkannt
    const WISCH_FANGEN = 22;     // px, erst hier wird der Zeiger „gefangen"
    const WISCH_SCHWELLE = 42;   // px, ab hier wird geblättert

    let position = 1;
    let zug = null;              // laufender Finger-/Maus-Zug

    const DREH_DAUER = 700;      // ms – etwas mehr als die CSS-Transition
    const flaechen = wuerfelEl ? Array.prototype.slice.call(wuerfelEl.children) : [];

    let flachTimer = 0;

    function reduziertBewegung() {
        return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
    }

    /** Würfeltiefe = halbe Breite, damit die Kanten beim Drehen abschließen. */
    function tiefeSetzen() {
        if (!wrapEl || !wuerfelEl) return;

        const breite = wrapEl.clientWidth;
        if (breite > 0) {
            wuerfelEl.style.setProperty('--tiefe', (breite / 2).toFixed(1) + 'px');
        }
    }

    function winkelFuer(pos) {
        return -WINKEL_SCHRITT * pos;
    }

    /** 3D-Lage der Position pos. translateZ(-tiefe) hält die vordere Fläche
     *  genau in der Perspektiv-Ebene (sonst wird sie vergrößert). */
    function kubusWinkel(pos) {
        return 'translateZ(calc(var(--tiefe) * -1)) rotateY(' + winkelFuer(pos).toFixed(2) + 'deg)';
    }

    /** Vordere Fläche, Schaf-Animation und Pfeile nachziehen. */
    function flaecheMarkieren() {
        flaechen.forEach((flaeche, i) => flaeche.classList.toggle('ist-vorn', i === position));

        if (wingLeft) wingLeft.classList.toggle('is-open', position === 0);
        if (wingRight) wingRight.classList.toggle('is-open', position === 2);
        if (wingLeftBtn) wingLeftBtn.classList.toggle('is-off', position === 0);
        if (wingRightBtn) wingRightBtn.classList.toggle('is-off', position === 3);
    }

    /** Ruhezustand: keine 3D-Transformation, aktive Fläche flach und klickbar.
     *  Nötig, weil Chrome in 3D-transformierten Flächen keine Klickziele findet. */
    function flachSetzen() {
        window.clearTimeout(flachTimer);

        if (wuerfelEl) {
            // is-ziehen schaltet die Transition ab: das Flachlegen muss springen,
            // sonst dreht sich der Würfel sichtbar zurück.
            wuerfelEl.classList.add('is-ziehen');
            wuerfelEl.classList.remove('wuerfel--3d');
            wuerfelEl.style.transform = 'none';
            void wuerfelEl.offsetWidth;                  // Reflow erzwingen
            wuerfelEl.classList.remove('is-ziehen');
        }

        flaecheMarkieren();
    }

    /** 3D-Grundstellung einnehmen – ohne Animation und optisch unverändert. */
    function kubusStellen(pos) {
        if (!wuerfelEl) return;

        wuerfelEl.classList.add('wuerfel--3d');
        wuerfelEl.classList.add('is-ziehen');            // Sprung ohne Transition
        wuerfelEl.style.transform = kubusWinkel(pos);
        void wuerfelEl.offsetWidth;                      // Reflow erzwingen
        wuerfelEl.classList.remove('is-ziehen');         // Transition wieder an
    }

    /** Live-Drehung während des Ziehens (ohne Transition). */
    function liveDrehen(winkel) {
        if (!wuerfelEl) return;

        wuerfelEl.classList.add('wuerfel--3d');
        wuerfelEl.classList.add('is-ziehen');
        wuerfelEl.style.transform =
            'translateZ(calc(var(--tiefe) * -1)) rotateY(' + winkel.toFixed(2) + 'deg)';
    }

    /** Animiert zur Position zielPos und legt danach wieder flach. */
    function dreheZu(zielPos) {
        if (!wuerfelEl || reduziertBewegung()) {
            flachSetzen();
            return;
        }

        wuerfelEl.classList.add('wuerfel--3d');
        wuerfelEl.classList.remove('is-ziehen');
        wuerfelEl.style.transform = kubusWinkel(zielPos);

        window.clearTimeout(flachTimer);
        flachTimer = window.setTimeout(flachSetzen, DREH_DAUER);
        flaecheMarkieren();
    }

    /** Blättert eine Fläche weiter (Pfeile, Pfeiltasten, Wischen). */
    function goTo(pos) {
        const ziel = Math.max(0, Math.min(3, pos));

        if (ziel === position) {
            flachSetzen();
            return;
        }

        // Wetter würfeln, sobald die Wiese geöffnet wird – es ist sofort da.
        if (ziel === 2) {
            wetterWuerfeln();
        }

        if (!wuerfelEl || reduziertBewegung()) {
            position = ziel;
            flachSetzen();
            return;
        }

        kubusStellen(position);          // 3D-Ausgangslage (sieht genauso aus)
        position = ziel;
        flaecheMarkieren();

        // kubusStellen hat die Ausgangslage bereits „geflusht" (Reflow),
        // deshalb läuft die Transition jetzt zuverlässig.
        dreheZu(position);
    }

    if (wingLeftBtn) wingLeftBtn.addEventListener('click', () => goTo(position - 1));
    if (wingRightBtn) wingRightBtn.addEventListener('click', () => goTo(position + 1));

    /* Kleines Hauptbild oben im Chat: Klick dreht den Würfel zur Bildseite. */
    document.addEventListener('click', (ev) => {
        const ziel = ev.target.closest('[data-dreh-zu]');

        if (!ziel) {
            return;
        }

        ev.preventDefault();
        goTo(Number(ziel.dataset.drehZu) || 0);
    });

    // ---- Wetter auf der Schafswiese ---------------------------------------
    // Bei jedem Öffnen der Wiese wird neu gewürfelt: sechs Zustände, gleich
    // verteilt, nie zweimal derselbe hintereinander.
    const wetterEl = document.getElementById('wetter');
    const WETTER_ARTEN = ['sonne', 'wolken', 'regen', 'schnee', 'nebel', 'gewitter'];
    let letztesWetter = '';
    let gewitterTaktTimer = 0;

    const zufall = (min, max) => min + Math.random() * (max - min);

    function wetterLeeren() {
        if (!wetterEl) return;

        window.clearTimeout(gewitterTaktTimer);   // sonst blitzt es weiter
        gewitterTaktTimer = 0;

        while (wetterEl.firstChild) {
            wetterEl.removeChild(wetterEl.firstChild);
        }
    }

    /** Wolken – ziehen langsam von rechts nach links. `dunkel` = schwere
        Gewitterwolken, die tief über dem Horizont hängen. */
    function wolkenBauen(anzahl, dunkel) {
        if (!wetterEl) return;

        const teil = document.createDocumentFragment();

        for (let i = 0; i < anzahl; i++) {
            const breite = Math.round(dunkel ? zufall(150, 380) : zufall(130, 340));
            const dauer = +zufall(38, 93).toFixed(1);

            const wolke = document.createElement('span');
            wolke.className = 'wolke' + (dunkel || Math.random() < 0.45 ? ' wolke--dunkel' : '');
            wolke.style.top = (dunkel ? zufall(1, 19) : zufall(1, 38)).toFixed(1) + '%';
            wolke.style.setProperty('--breite', breite + 'px');
            wolke.style.setProperty('--hoehe', Math.round(breite * zufall(0.24, 0.38)) + 'px');
            wolke.style.setProperty('--dauer', dauer + 's');
            wolke.style.setProperty('--delay', (-zufall(0, dauer)).toFixed(1) + 's');
            wolke.style.setProperty('--blur', Math.round(zufall(6, 18)) + 'px');
            wolke.style.setProperty('--deckkraft', (dunkel ? zufall(0.45, 0.88) : zufall(0.30, 0.72)).toFixed(2));

            teil.appendChild(wolke);
        }

        wetterEl.appendChild(teil);
    }

    /** Schneeflocken – kleine weiße Punkte, die langsam fallen und pendeln. */
    function flockenBauen(anzahl) {
        if (!wetterEl) return;

        const teil = document.createDocumentFragment();

        for (let i = 0; i < anzahl; i++) {
            const groesse = +zufall(2.8, 7).toFixed(1);
            const dauer = +(15 - groesse + Math.random() * 6).toFixed(2);
            const delay = -+zufall(0, dauer).toFixed(2);

            const bahn = document.createElement('span');
            bahn.className = 'flocke-bahn';
            bahn.style.left = zufall(0, 100).toFixed(2) + '%';
            bahn.style.animationDuration = dauer + 's';
            bahn.style.animationDelay = delay + 's';

            const flocke = document.createElement('span');
            flocke.className = 'flocke';
            flocke.style.setProperty('--groesse', groesse + 'px');
            flocke.style.setProperty('--drift', Math.round(zufall(7, 27)) + 'px');
            flocke.style.setProperty('--driftdauer', zufall(2.8, 7).toFixed(1) + 's');
            flocke.style.setProperty('--deckkraft', zufall(0.55, 0.97).toFixed(2));
            flocke.style.animationDelay = delay + 's';

            bahn.appendChild(flocke);
            teil.appendChild(bahn);
        }

        wetterEl.appendChild(teil);
    }

    /** Regen – viele kleine dunkelblaue Tropfen, jeder fünfte hell. */
    function tropfenBauen(anzahl) {
        if (!wetterEl) return;

        const teil = document.createDocumentFragment();

        for (let i = 0; i < anzahl; i++) {
            const dauer = +zufall(0.62, 1.47).toFixed(2);
            const delay = -+zufall(0, dauer).toFixed(2);

            const bahn = document.createElement('span');
            bahn.className = 'tropfen-bahn';
            bahn.style.left = zufall(0, 100).toFixed(2) + '%';
            bahn.style.animationDuration = dauer + 's';
            bahn.style.animationDelay = delay + 's';

            const tropfen = document.createElement('span');
            tropfen.className = 'tropfen' + (Math.random() < 0.22 ? ' tropfen--hell' : '');
            tropfen.style.setProperty('--breite', zufall(1, 2.3).toFixed(1) + 'px');
            tropfen.style.setProperty('--laenge', zufall(8, 18).toFixed(1) + 'px');
            tropfen.style.setProperty('--neigung', zufall(5, 10).toFixed(1) + 'deg');
            tropfen.style.setProperty('--deckkraft', zufall(0.45, 0.9).toFixed(2));

            bahn.appendChild(tropfen);
            teil.appendChild(bahn);
        }

        wetterEl.appendChild(teil);
    }

    /** Nebel – große, weiche Schwaden im Vordergrund. */
    function schwadenBauen(anzahl) {
        if (!wetterEl) return;

        const teil = document.createDocumentFragment();

        for (let i = 0; i < anzahl; i++) {
            const breite = Math.round(zufall(320, 640));
            const dauer = +zufall(55, 125).toFixed(1);

            const schwade = document.createElement('span');
            schwade.className = 'schwade';
            schwade.style.top = zufall(42, 92).toFixed(1) + '%';
            schwade.style.setProperty('--breite', breite + 'px');
            schwade.style.setProperty('--hoehe', Math.round(breite * zufall(0.20, 0.36)) + 'px');
            schwade.style.setProperty('--dauer', dauer + 's');
            schwade.style.setProperty('--delay', (-zufall(0, dauer)).toFixed(1) + 's');
            schwade.style.setProperty('--blur', Math.round(zufall(20, 40)) + 'px');
            schwade.style.setProperty('--deckkraft', zufall(0.22, 0.5).toFixed(2));

            teil.appendChild(schwade);
        }

        wetterEl.appendChild(teil);
    }

    /* ---- Gewitter: Donner aus der Web Audio API --------------------------
       Kein Sound-File: braunes Rauschen, tief gefiltert und mit Hüllkurve.
       Der Kontext wird beim Würfeln angelegt (das passiert nach einem Klick
       oder Wischen – Browser erlauben Audio erst nach einer Nutzer-Geste). */

    let hoererKontext = null;
    let donnerRauschen = null;

    function hoerer() {
        if (hoererKontext) return hoererKontext;

        const Ctx = window.AudioContext || window.webkitAudioContext;
        if (!Ctx) return null;

        try {
            hoererKontext = new Ctx();
        } catch (e) {
            return null;
        }

        return hoererKontext;
    }

    /** Audio-Kontext bei Bedarf anlegen und nach einer Nutzer-Geste wecken. */
    function hoererWecken() {
        const ctx = hoerer();
        if (!ctx || ctx.state !== 'suspended' || !ctx.resume) return ctx;

        const versprechen = ctx.resume();
        if (versprechen && versprechen.catch) {
            versprechen.catch(function () {});   // Headless/ohne Ausgabe: egal
        }

        return ctx;
    }

    /** Ein Donnerschlag. `fern` = weiter weg (dumpfer, leiser, später). */
    function donner(fern) {
        const ctx = hoererWecken();
        if (!ctx || !ctx.createBufferSource) return;

        // Rauschen einmal bauen und danach wiederverwenden.
        if (!donnerRauschen) {
            const laenge = Math.floor(ctx.sampleRate * 3);
            const puffer = ctx.createBuffer(1, laenge, ctx.sampleRate);
            const daten = puffer.getChannelData(0);
            let summe = 0;

            for (let i = 0; i < laenge; i++) {
                summe = (summe + 0.02 * (Math.random() * 2 - 1)) / 1.02;   // braun
                daten[i] = summe * 3.2;
            }

            donnerRauschen = puffer;
        }

        const jetzt = ctx.currentTime;
        const dauer = 2.2 + Math.random() * 0.9;

        const quelle = ctx.createBufferSource();
        quelle.buffer = donnerRauschen;
        quelle.playbackRate.value = 0.8 + Math.random() * 0.35;

        const tiefpass = ctx.createBiquadFilter();
        tiefpass.type = 'lowpass';
        tiefpass.frequency.setValueAtTime(fern ? 340 : 1000, jetzt);
        tiefpass.frequency.exponentialRampToValueAtTime(fern ? 80 : 130, jetzt + dauer * 0.8);

        const lautstaerke = ctx.createGain();
        const laut = (fern ? 0.14 : 0.32) * (0.75 + Math.random() * 0.5);
        lautstaerke.gain.setValueAtTime(0.0001, jetzt);
        lautstaerke.gain.exponentialRampToValueAtTime(laut, jetzt + 0.08);
        lautstaerke.gain.exponentialRampToValueAtTime(laut * 0.35, jetzt + 0.9);
        lautstaerke.gain.exponentialRampToValueAtTime(0.0001, jetzt + dauer);

        quelle.connect(tiefpass).connect(lautstaerke).connect(ctx.destination);
        quelle.start(jetzt);
        quelle.stop(jetzt + dauer + 0.1);
    }

    /* ---- Gewitter: Blitze ------------------------------------------------ */

    function blitzBauen() {
        const ns = 'http://www.w3.org/2000/svg';
        const svg = document.createElementNS(ns, 'svg');

        svg.setAttribute('class', 'blitz');
        svg.setAttribute('viewBox', '0 0 100 100');
        svg.setAttribute('preserveAspectRatio', 'none');

        const strahl = document.createElementNS(ns, 'polyline');
        strahl.setAttribute('vector-effect', 'non-scaling-stroke');
        svg.appendChild(strahl);

        return svg;
    }

    /** Neuen Zickzack in den Strahl schreiben (0–100 in beide Richtungen). */
    function blitzNeu(strahl) {
        const punkte = [];
        let x = 30 + Math.random() * 40;
        let y = 0;

        punkte.push([x, y]);

        while (y < 100) {
            y = Math.min(100, y + 12 + Math.random() * 22);
            x = Math.max(4, Math.min(96, x + (Math.random() * 2 - 1) * 30));
            punkte.push([x, y]);
        }

        strahl.setAttribute(
            'points',
            punkte.map((p) => p[0].toFixed(1) + ',' + p[1].toFixed(1)).join(' ')
        );
    }

    /** Ein Blitz: Position würfeln, Schein und Strahl aufblitzen lassen, dann
        passend dazu (nah = schnell und laut) den Donner. */
    function blitzAusloesen() {
        const svg = wetterEl.querySelector('.blitz');
        const strahl = svg && svg.querySelector('polyline');
        if (!svg || !strahl) return;

        const fern = Math.random() < 0.45;

        blitzNeu(strahl);
        svg.style.left = (6 + Math.random() * 76).toFixed(1) + '%';
        svg.style.top = (1 + Math.random() * 7).toFixed(1) + '%';
        svg.style.width = Math.round(52 + Math.random() * 48) + 'px';
        svg.style.height = (12 + Math.random() * 12).toFixed(1) + '%';
        svg.style.display = Math.random() < 0.7 ? 'block' : 'none';   // sonst nur Wolkenschein

        wetterEl.classList.remove('ist-blitz');
        void wetterEl.offsetWidth;          // Animation neu starten
        wetterEl.classList.add('ist-blitz');

        window.setTimeout(function () {
            wetterEl.classList.remove('ist-blitz');
        }, 700);

        window.setTimeout(function () {
            donner(fern);
        }, fern ? 800 + Math.random() * 1500 : 250 + Math.random() * 700);
    }

    /** Nächsten Blitz in 3,5–9,5 s – aber nur, solange die Wiese offen ist. */
    function gewitterTakt() {
        window.clearTimeout(gewitterTaktTimer);

        gewitterTaktTimer = window.setTimeout(function () {
            if (position !== 2 || !wetterEl.classList.contains('wetter--gewitter')) return;

            blitzAusloesen();
            gewitterTakt();
        }, 3500 + Math.random() * 6000);
    }

    function gewitterBauen() {
        hoererWecken();                      // erst nach einer Nutzer-Geste erlaubt

        wolkenBauen(9, true);                // schwere Wolken tief über dem Horizont
        tropfenBauen(90);                    // etwas Regen dazu

        const schein = document.createElement('span');
        schein.className = 'blitz-schein';

        wetterEl.appendChild(blitzBauen());
        wetterEl.appendChild(schein);

        if (reduziertBewegung()) return;     // kein Blitzgewitter bei reduzierter Bewegung

        gewitterTakt();
    }

    /** Baut eine Wetterlage auf die Wiese (sonne = gar nichts). */
    function wetterBauen(art) {
        if (!wetterEl) return;

        wetterLeeren();
        wetterEl.className = 'wetter' + (art === 'sonne' ? '' : ' wetter--' + art);

        if (art === 'wolken') {
            wolkenBauen(8);
        } else if (art === 'regen') {
            wolkenBauen(7);
            tropfenBauen(180);
        } else if (art === 'schnee') {
            wolkenBauen(7);
            flockenBauen(45);
        } else if (art === 'nebel') {
            schwadenBauen(6);
        } else if (art === 'gewitter') {
            gewitterBauen();
        }
    }

    /** Neues Wetter würfeln – nie dasselbe wie beim letzten Mal. */
    function wetterWuerfeln() {
        // Testschalter: index.php?id=…&wetter=gewitter erzwingt eine Lage.
        const probe = (cfg && cfg.wetterProbe) ? String(cfg.wetterProbe) : '';
        if (probe) {
            letztesWetter = probe;
            wetterBauen(probe);
            return;
        }

        const auswahl = WETTER_ARTEN.filter((art) => art !== letztesWetter);
        const art = auswahl[Math.floor(Math.random() * auswahl.length)] || 'sonne';

        letztesWetter = art;
        wetterBauen(art);
    }

    // ---- Wischen mit dem Finger / Ziehen mit der Maus -----------------------
    // WICHTIG: Auf Bedienelementen (Level-Karten, Senden, Tastatur, Felder)
    // beginnt bewusst KEINE Wisch-Geste. Sonst würde der Zeiger gefangen
    // (setPointerCapture) und das click-Ereignis käme nie beim Knopf an –
    // dann führen „Leicht/Mittel/Schwer" nicht mehr in den Chat.
    const BEDIEN_SELECTOR = 'button, a, input, textarea, select, label, math-field, [data-tastatur]';

    function zugBeginnen(e) {
        if (!wrapEl) return;
        if (e.pointerType === 'mouse' && e.button !== 0) return;

        const ziel = e.target;
        if (ziel && typeof ziel.closest === 'function' && ziel.closest(BEDIEN_SELECTOR)) {
            zug = null;
            return;
        }

        zug = {
            id: e.pointerId,
            x: e.clientX,
            y: e.clientY,
            breite: Math.max(wrapEl.clientWidth, 1),
            achse: '',      // '' = offen, 'x' = wischen, 'y' = Seite scrollen
            gefangen: false,
        };
    }

    function zugBewegen(e) {
        if (!zug || e.pointerId !== zug.id) return;

        const dx = e.clientX - zug.x;
        const dy = e.clientY - zug.y;

        // Richtung erst festlegen, wenn die Geste eindeutig ist.
        if (zug.achse === '') {
            if (Math.abs(dx) < WISCH_START && Math.abs(dy) < WISCH_START) return;

            zug.achse = Math.abs(dx) > Math.abs(dy) * 1.2 ? 'x' : 'y';
        }

        if (zug.achse !== 'x') return;

        // Zeiger erst fangen, wenn es wirklich ein Wischen ist (Taps bleiben Taps).
        if (!zug.gefangen && Math.abs(dx) >= WISCH_FANGEN) {
            zug.gefangen = true;
            try { wrapEl.setPointerCapture(e.pointerId); } catch (err) { /* egal */ }
            kubusStellen(position);          // in den 3D-Modus wechseln (unsichtbar)
        }

        if (!zug.gefangen) return;

        // An den Enden nur widerwillig mitdrehen (Gummiband-Gefühl).
        let versatz = dx;
        if ((position === 0 && versatz > 0) || (position === 2 && versatz < 0)) {
            versatz *= 0.35;
        }

        liveDrehen(winkelFuer(position) + (versatz / zug.breite) * WINKEL_SCHRITT);
    }

    function zugBeenden(e) {
        if (!zug || e.pointerId !== zug.id) return;

        const dx = e.clientX - zug.x;
        const warWisch = zug.achse === 'x';
        const abgebrochen = e.type === 'pointercancel';

        zug = null;

        // Tippen oder senkrechtes Scrollen: der Würfel bleibt einfach stehen.
        if (!warWisch) return;

        const ziel = Math.max(0, Math.min(2, position + (dx < 0 ? 1 : -1)));

        if (abgebrochen || Math.abs(dx) < WISCH_SCHWELLE || ziel === position) {
            dreheZu(position);               // zurückfedern
            return;
        }

        position = ziel;
        flaecheMarkieren();
        dreheZu(position);
    }

    if (wrapEl) {
        wrapEl.addEventListener('pointerdown', zugBeginnen);
        wrapEl.addEventListener('pointermove', zugBewegen);
        wrapEl.addEventListener('pointerup', zugBeenden);
        wrapEl.addEventListener('pointercancel', zugBeenden);
    }

    // Pfeiltasten am Rechner (nicht beim Tippen in einem Feld).
    document.addEventListener('keydown', (e) => {
        const ziel = e.target;
        const tippt = ziel && (
            ziel.tagName === 'INPUT' ||
            ziel.tagName === 'TEXTAREA' ||
            ziel.tagName === 'MATH-FIELD' ||
            ziel.isContentEditable
        );

        if (tippt || e.altKey || e.ctrlKey || e.metaKey) return;

        if (e.key === 'ArrowLeft') { e.preventDefault(); goTo(position - 1); }
        if (e.key === 'ArrowRight') { e.preventDefault(); goTo(position + 1); }
    });

    // Tiefe nachziehen, wenn sich die Breite ändert (Gerät drehen, Fenster).
    if (wrapEl && typeof ResizeObserver === 'function') {
        new ResizeObserver(tiefeSetzen).observe(wrapEl);
    } else {
        window.addEventListener('resize', tiefeSetzen);
    }

    tiefeSetzen();
    flachSetzen();

    let stufe = 'leicht';
    let abschlussLaeuft = false; // true, solange nach "Ziel erreicht" die Wiese gezeigt wird

    const screenEl = document.getElementById('stufe-screen');
    const stageEl = document.getElementById('chat-stage');

    document.querySelectorAll('.stufe-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.stufe-btn').forEach((b) => b.classList.remove('is-on'));
            btn.classList.add('is-on');
            stufe = btn.dataset.stufe || 'leicht';
            if (screenEl) screenEl.hidden = true;
            if (stageEl) stageEl.hidden = false;
            start();
        });
    });

    async function start() {
        // Chat zu Beginn komplett leeren – auch die bereits gerenderten Blasen.
        abschlussLaeuft = false;
        clearChat();
        const thinking = showThinking();
        sendBtn.disabled = true;
        try {
            const fd = new FormData();
            fd.append('id', cfg.id);
            fd.append('stufe', stufe);
            fd.append('action', 'start');
            const resp = await fetch(cfg.chatEndpoint, { method: 'POST', body: fd });
            const data = await resp.json().catch(() => ({}));
            if (resp.ok && data.ok && data.ai && (data.ai.text || data.ai.formel)) {
                appendBubble('an_schueler', data.ai.text || '', data.ai.formel || '');
            } else if (data && data.fehler) {
                // z. B. Baustein ohne Prompt → deutlich sagen statt leerer Fläche
                fehlerMelden(data.fehler);
            } else {
                fehlerMelden('Das Matheschaf hat nicht geantwortet.', start);
            }
            if (data && data.ok) belohnungen(data);
        } catch (e) {
            fehlerMelden('Keine Verbindung zum Matheschaf.', start);
        } finally {
            hideThinking(thinking);
            sendBtn.disabled = false;
            textEl.focus();
        }
    }

    /** Leert die sichtbare Chatliste (Bubbles + Leer-Hinweis). */
    function clearChat() {
        listEl.querySelectorAll('.msg-row').forEach((row) => row.remove());
        if (emptyEl) emptyEl.hidden = true;
    }

    /** Kleine System-Zeile in der Mitte der Chatliste (Belohnung, Ziel erreicht). */
    function appendSystem(text, art) {
        if (emptyEl) emptyEl.hidden = true;

        const row = document.createElement('div');
        row.className = 'msg-row msg-row--system';

        const note = document.createElement('span');
        note.className = 'msg-system' + (art === 'fehler' ? ' msg-system--fehler' : '');
        note.textContent = text;

        row.appendChild(note);
        listEl.appendChild(row);
        listEl.scrollTop = listEl.scrollHeight;
    }

    /**
     * Meldet ein Problem sichtbar, statt es zu verschlucken.
     * wiederholen (optional) = Funktion, die „Nochmal versuchen" ausführt.
     */
    function fehlerMelden(text, wiederholen) {
        appendSystem('⚠️ ' + text, 'fehler');

        if (typeof wiederholen !== 'function') return;

        const row = document.createElement('div');
        row.className = 'msg-row msg-row--system';

        const knopf = document.createElement('button');
        knopf.type = 'button';
        knopf.className = 'btn btn--secondary msg-retry';
        knopf.textContent = '↻ Nochmal versuchen';
        knopf.addEventListener('click', () => {
            row.remove();
            wiederholen();
        });

        row.appendChild(knopf);
        listEl.appendChild(row);
        listEl.scrollTop = listEl.scrollHeight;
    }

    /** Hängt neue Schafe an die Wiese (Live-Belohnung) und zählt den Zähler hoch. */
    function belohneSchafe(anzahl) {
        const wiese = document.querySelector('.wiese');
        if (!wiese || !anzahl) return;

        const max = Number(cfg.wieseMax) || 60;
        const vorhanden = wiese.querySelectorAll('.schaf-ort').length;
        let gesamt = vorhanden;

        const leer = wiese.querySelector('.wiese-leer');
        if (leer) leer.remove();

        for (let i = 0; i < anzahl && gesamt < max; i++) {
            gesamt += 1;
            wiese.appendChild(einSchaf(gesamt));
        }

        const zaehler = document.getElementById('wiese-anzahl');
        const wort = document.getElementById('wiese-wort');
        if (zaehler) zaehler.textContent = String(gesamt);
        if (wort) wort.textContent = gesamt === 1 ? 'Schaf' : 'Schafe';

        // Pfeil zum rechten Flügel kurz blinken lassen, damit es auffällt.
        if (wingRightBtn) {
            wingRightBtn.classList.remove('is-flash');
            void wingRightBtn.offsetWidth; // Animation neu starten
            wingRightBtn.classList.add('is-flash');
        }
    }

    /** Baut ein einzelnes Schaf-Element (Position/Animation zufällig im Grasband). */
    function einSchaf(nummer) {
        const bilder = (cfg.schafBilder && cfg.schafBilder.length) ? cfg.schafBilder : ['bilder/schaf1.png'];
        const zufall = (min, max) => min + Math.random() * (max - min);

        const top = zufall(50, 94);
        const tiefe = (top - 44) / 52; // 0 = hinten, 1 = vorne
        const groesse = zufall(9, 12) * (0.78 + 0.34 * tiefe);
        const dauer = zufall(3.6, 8);
        const weite = zufall(10, 36) * (Math.random() < 0.5 ? -1 : 1);

        const ort = document.createElement('span');
        ort.className = 'schaf-ort';
        ort.style.left = zufall(6, 90).toFixed(2) + '%';
        ort.style.top = top.toFixed(2) + '%';
        ort.style.width = groesse.toFixed(2) + '%';
        ort.style.zIndex = String(Math.round(top));

        const img = document.createElement('img');
        img.className = 'schaf';
        img.src = bilder[nummer % bilder.length];
        img.alt = '';
        img.draggable = false;
        img.decoding = 'async';
        img.style.setProperty('--dur', dauer.toFixed(2) + 's');
        img.style.setProperty('--dist', weite.toFixed(1) + 'px');
        img.style.setProperty('--delay', (-Math.random() * dauer).toFixed(2) + 's');

        ort.appendChild(img);
        return ort;
    }

    /** Zeigt kurz (3 s) das "+1 Schaf"-Bild im Chat und blendet es wieder aus. */
    function zeigeSchafPlus() {
        const row = document.createElement('div');
        row.className = 'msg-row msg-row--system';

        const img = document.createElement('img');
        img.className = 'msg-schafplus';
        img.src = cfg.schafPlus || 'bilder/schafplus.png';
        img.alt = '+1 Schaf für deine Wiese';
        img.draggable = false;
        img.decoding = 'async';

        row.appendChild(img);
        listEl.appendChild(row);
        listEl.scrollTop = listEl.scrollHeight;

        // nach 2,7 s ausblenden, nach 3 s komplett entfernen
        setTimeout(() => row.classList.add('is-weg'), 2700);
        setTimeout(() => row.remove(), 3000);
    }

    /** Ziel erreicht: Meldung, Wiese kurz einblenden, dann zurück zur Stufenwahl. */
    function zielAbschluss() {
        appendSystem('🎉 Ziel erreicht – super gemacht!');

        abschlussLaeuft = true;
        goTo(2); // Schafswiese zeigen (neue Schafe stehen schon drauf)

        window.setTimeout(() => {
            goTo(1);
            if (stageEl) stageEl.hidden = true;    // Chat ausblenden
            if (screenEl) screenEl.hidden = false; // Stufenwahl zeigen
            document.querySelectorAll('.stufe-btn').forEach((b) => b.classList.remove('is-on'));
            clearChat();
            abschlussLaeuft = false;
        }, 4000);
    }

    /** Reagiert auf Belohnungen aus der Server-Antwort. */
    function belohnungen(data) {
        const schafe = Number(data.schafe_neu) || 0;

        if (schafe > 0) {
            belohneSchafe(schafe);
            zeigeSchafPlus();
        }

        if (data.ziel_erreicht) {
            zielAbschluss();
        }
    }

    // Antwortoptionen (A) … D)) aus der KI-Antwort schneiden – nötig für Quiz-Bausteine
    const OPTION_MUSTER = /^\s*([A-D])\s*[).:\-]\s*(.+)$/i;

    function schnittOptionen(text) {
        const zeilen   = String(text || '').split(/\r?\n/);
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

        return { text: rest.join('\n').trim(), optionen: optionen };
    }

    function appendBubble(richtung, text, formel) {
        if (emptyEl) emptyEl.hidden = true;

        const inhalt = richtung === 'an_schueler'
            ? schnittOptionen(text)
            : { text: String(text || ''), optionen: [] };

        const row = document.createElement('div');
        row.className = 'msg-row ' + (richtung === 'an_lehrer' ? 'msg-row--ich' : 'msg-row--gegen');

        const bubble = document.createElement('div');
        bubble.className = 'msg-bubble';

        if (inhalt.text) {
            const t = document.createElement('span');
            t.className = 'msg-text';
            t.textContent = inhalt.text;
            bubble.appendChild(t);
        }

        if (formel) {
            const w = document.createElement('div');
            w.className = 'msg-formel';
            const md = document.createElement('math-div');
            md.textContent = formel;
            w.appendChild(md);
            bubble.appendChild(w);
            if (typeof md.render === 'function') {
                try { md.render().catch(() => {}); } catch (e) {}
            }
        }

        // Bei Quiz-Antworten: Knöpfe direkt in der Blase – ein Klick antwortet
        if (inhalt.optionen.length >= 2) {
            row.classList.add('msg-row--frage');
            bubble.classList.add('msg-bubble--frage');

            const grid = document.createElement('div');
            grid.className = 'msg-optionen';

            inhalt.optionen.forEach((o) => {
                const knopf = document.createElement('button');
                knopf.type = 'button';
                knopf.className = 'msg-option msg-option--' + o.buchstabe.toLowerCase();
                knopf.textContent = o.buchstabe + ') ' + o.text;

                knopf.addEventListener('click', () => {
                    if (knopf.disabled) return;

                    listEl.querySelectorAll('.msg-option').forEach((k) => { k.disabled = true; });

                    textEl.value = o.buchstabe;
                    send();
                });

                grid.appendChild(knopf);
            });

            bubble.appendChild(grid);
        }

        row.appendChild(bubble);
        listEl.appendChild(row);
        listEl.scrollTop = listEl.scrollHeight;
    }

    function showThinking() {
        const row = document.createElement('div');
        row.className = 'msg-row msg-row--gegen';
        const bubble = document.createElement('div');
        bubble.className = 'msg-bubble msg-thinking';
        const label = document.createElement('span');
        label.className = 'msg-thinking-label';
        label.textContent = 'Matheschaf denkt';
        bubble.appendChild(label);
        for (let i = 0; i < 3; i++) {
            const dot = document.createElement('span');
            dot.className = 'dot';
            bubble.appendChild(dot);
        }
        row.appendChild(bubble);
        listEl.appendChild(row);
        listEl.scrollTop = listEl.scrollHeight;
        return row;
    }

    function hideThinking(row) {
        if (row && row.parentNode) row.parentNode.removeChild(row);
    }

    function formelWert() {
        if (!formelEl) return '';
        try { return (formelEl.value || '').trim(); } catch (e) { return ''; }
    }

    async function send() {
        if (abschlussLaeuft) return; // während der Ziel-Animation nichts senden
        const text = textEl.value.trim();
        const formel = formelWert();
        if (!text && !formel) return;

        // Für „Nochmal versuchen": Eingabe wiederherstellen und erneut senden.
        const nochmal = () => {
            textEl.value = text;
            try { formelEl.value = formel; } catch (e) {}
            send();
        };

        appendBubble('an_lehrer', text, formel);

        textEl.value = '';
        try { formelEl.value = ''; } catch (e) {}

        const thinking = showThinking();
        sendBtn.disabled = true;

        try {
            const fd = new FormData();
            fd.append('id', cfg.id);
            fd.append('stufe', stufe);
            fd.append('text', text);
            fd.append('formel', formel);

            const resp = await fetch(cfg.chatEndpoint, { method: 'POST', body: fd });
            const data = await resp.json().catch(() => ({}));

            if (resp.ok && data.ok && data.ai && (data.ai.text || data.ai.formel)) {
                appendBubble('an_schueler', data.ai.text || '', data.ai.formel || '');
            } else if (data && data.fehler) {
                fehlerMelden(data.fehler);
            } else {
                fehlerMelden('Das Matheschaf hat nicht geantwortet.', nochmal);
            }
            if (data && data.ok) belohnungen(data);
        } catch (e) {
            fehlerMelden('Keine Verbindung zum Matheschaf.', nochmal);
        } finally {
            hideThinking(thinking);
            sendBtn.disabled = false;
            textEl.focus();
        }
    }

    sendBtn.addEventListener('click', send);
    textEl.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { e.preventDefault(); send(); }
    });

    // ------------------------------------------- 4. Fläche: "Teste dein Wissen"
    const quizBalken    = document.getElementById('quiz-balken');
    const quizFaceChat  = document.getElementById('quiz-face-chat');
    const quizFaceStart = document.getElementById('quiz-face-start');
    const quizFaceEmpty = document.getElementById('quiz-face-empty');
    const quizFaceMeta  = document.getElementById('quiz-face-meta');
    const quizTribuene  = document.getElementById('quiz-tribuene');
    const konfettiLayer = document.getElementById('konfetti-layer');
    const quizZurueckBtn = document.getElementById('quiz-face-zurueck');
    const quizTonBtn     = document.getElementById('quiz-face-ton');
    const sound          = window.MSQuizSound || null;

    let quizLaeuft    = false;
    let quizGestartet = false;
    let quizTonAn     = sound ? sound.istAn() : false;

    // ---- Ton-Schalter der Quiz-Fläche (Töne aus assets/quiz-sound.js) ----
    const quizTonMal = () => {
        if (!quizTonBtn) return;
        quizTonBtn.textContent = quizTonAn ? '🔊' : '🔇';
        quizTonBtn.setAttribute('aria-pressed', quizTonAn ? 'true' : 'false');
    };
    quizTonMal();

    if (quizTonBtn) {
        quizTonBtn.addEventListener('click', () => {
            quizTonAn = !quizTonAn;
            if (sound) sound.setAn(quizTonAn);
            quizTonMal();
            if (quizTonAn && sound) sound.maeh();      // Hörprobe
        });
    }

    /** Kleiner Konfettiregen (nutzt .konfetti aus quiz.css). */
    function quizKonfetti() {
        if (!konfettiLayer) return;
        if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

        const farben = ['#2f7d32', '#f2a007', '#e53935', '#1e88e5', '#8e24aa', '#fdd835'];

        for (let i = 0; i < 60; i++) {
            const s = document.createElement('span');
            s.className = 'konfetti';
            s.style.left = (Math.random() * 100).toFixed(2) + '%';
            s.style.background = farben[i % farben.length];
            s.style.width = (5 + Math.random() * 6).toFixed(0) + 'px';
            s.style.height = (8 + Math.random() * 7).toFixed(0) + 'px';
            s.style.setProperty('--delay', (Math.random() * 0.3).toFixed(2) + 's');
            s.style.setProperty('--dauer', (1.5 + Math.random() * 1.4).toFixed(2) + 's');
            s.style.setProperty('--dreh', Math.round(Math.random() * 900 - 450) + 'deg');
            konfettiLayer.appendChild(s);
            window.setTimeout(() => s.remove(), 3400);
        }
    }

    /** Schafe auf der Tribüne reagieren: hüpfen oder Kopf schütteln. */
    function tribueneReaktion(art) {
        if (!quizTribuene) return;

        quizTribuene.classList.remove('ist-jubel', 'ist-buh');
        void quizTribuene.offsetWidth;
        quizTribuene.classList.add(art === 'jubel' ? 'ist-jubel' : 'ist-buh');
        window.setTimeout(() => quizTribuene.classList.remove('ist-jubel', 'ist-buh'), 1500);
    }

    /**
     * Trennt eine KI-Antwort in "Vorlauf" (Rückmeldung) und "Frage".
     * Trennlinie ist die letzte Zeile mit "Frage x von y", sonst der letzte Absatz.
     */
    function quizTeile(text) {
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
    function quizKarteOben(row) {
        if (!row || !quizFaceChat) return;

        const setzen = () => {
            const pad  = parseFloat(window.getComputedStyle(quizFaceChat).paddingTop) || 0;
            const ziel = row.getBoundingClientRect().top
                - quizFaceChat.getBoundingClientRect().top
                + quizFaceChat.scrollTop
                - pad;

            quizFaceChat.scrollTop = Math.max(0, ziel);
        };

        setzen();                                   // sofort und exakt
        window.setTimeout(setzen, 180);             // Nachkorrektur nach dem Formel-Reflow
    }

    function quizBubble(rolle, text, formel, optionen) {
        if (!quizFaceChat) return;
        if (quizFaceEmpty) quizFaceEmpty.hidden = true;

        const row = document.createElement('div');
        row.className = 'msg-row ' + (rolle === 'ich' ? 'msg-row--ich' : 'msg-row--gegen');

        const bubble = document.createElement('div');
        bubble.className = 'msg-bubble';

        if (text) {
            const span = document.createElement('span');
            span.className = 'msg-text';
            span.textContent = text;
            bubble.appendChild(span);
        }

        if (formel) {
            const w = document.createElement('div');
            w.className = 'msg-formel';
            const md = document.createElement('math-div');
            md.textContent = formel;
            w.appendChild(md);
            bubble.appendChild(w);
            if (typeof md.render === 'function') {
                try { md.render().catch(() => {}); } catch (e) {}
            }
        }

        if (optionen && optionen.length >= 2) {
            bubble.classList.add('msg-bubble--frage');
            row.classList.add('msg-row--frage');

            const grid = document.createElement('div');
            grid.className = 'msg-optionen';

            optionen.forEach((o) => {
                const knopf = document.createElement('button');
                knopf.type = 'button';
                knopf.className = 'msg-option msg-option--' + o.buchstabe.toLowerCase();
                knopf.textContent = o.buchstabe + ') ' + o.text;
                knopf.addEventListener('click', () => quizAntwort(o.buchstabe, knopf));
                grid.appendChild(knopf);
            });

            bubble.appendChild(grid);
        }

        row.appendChild(bubble);
        quizFaceChat.appendChild(row);

        // Ohne Optionen (z. B. Fehlermeldung) bleibt der Blick unten,
        // die neue Frage holt quizFrage() selbst nach oben.
        if (!(optionen && optionen.length >= 2)) {
            quizFaceChat.scrollTop = quizFaceChat.scrollHeight;
        }

        return row;
    }

    async function quizFrage(aktion, antwortText) {
        if (quizLaeuft || !quizFaceChat) return;

        // AudioContext nach der Nutzer-Geste starten (Browser-Vorgabe)
        if (sound) sound.wecken();

        quizLaeuft = true;
        if (quizFaceStart) quizFaceStart.disabled = true;

        const punkt = document.createElement('div');
        punkt.className = 'msg-row msg-row--gegen';
        punkt.innerHTML = '<div class="msg-bubble msg-bubble--denkt"><span class="quiz-sanduhr">⏳</span><span class="msg-text quiz-denk-text">Einen Moment …</span></div>';
        quizFaceChat.appendChild(punkt);
        quizFaceChat.scrollTop = quizFaceChat.scrollHeight;

        try {
            const fd = new FormData();
            fd.append('id', cfg.id);
            fd.append('quiz', '1');
            fd.append('stufe', 'leicht');

            if (aktion === 'start') {
                fd.append('action', 'start');
            } else {
                fd.append('text', antwortText);
            }

            const resp = await fetch(cfg.chatEndpoint, { method: 'POST', body: fd });
            let data = null;
            try { data = await resp.json(); } catch (e) { data = null; }

            punkt.remove();

            if (!resp.ok || !data || !data.ok) {
                quizBubble('gegen', '⚠️ ' + ((data && (data.fehler || data.error)) || 'Keine Antwort (HTTP ' + resp.status + ')'), '', []);
                return;
            }

            const ai      = data.ai || {};
            const teile   = schnittOptionen(ai.text || '');
            const split   = quizTeile(teile.text);
            const frageNr = String(ai.text || '').match(/Frage\s+(\d+)\s+von\s+(\d+)/i);

            if (quizFaceMeta && frageNr) {
                quizFaceMeta.textContent = (cfg.quizTitel || 'Quiz') + ' · Frage ' + frageNr[1] + ' von ' + frageNr[2];
            }

            // Rückmeldung („Richtig/Falsch …") als eigene Blase – sie scrollt nach unten
            if (split.vorlauf !== '') {
                quizBubble('gegen', split.vorlauf, '', []);
            }

            const karte = quizBubble('gegen', split.frage, ai.formel || '', teile.optionen);

            if (teile.optionen.length >= 2) {
                quizKarteOben(karte);   // neue Frage beginnt oben im Sichtfenster
            }

            if (data.richtig === true) {
                tribueneReaktion('jubel');
                quizKonfetti();
                if (sound && quizTonAn) sound.jubel();
            } else if (data.richtig === false) {
                tribueneReaktion('buh');
                if (sound && quizTonAn) sound.maeh();
            }
        } catch (e) {
            punkt.remove();
            quizBubble('gegen', '⚠️ Keine Verbindung zum Matheschaf.', '', []);
        } finally {
            quizLaeuft = false;
            if (quizFaceStart) quizFaceStart.disabled = false;
        }
    }

    function quizAntwort(buchstabe, knopf) {
        if (quizLaeuft) return;

        quizFaceChat.querySelectorAll('.msg-option').forEach((k) => { k.disabled = true; });
        if (knopf) knopf.classList.add('ist-gewaehlt');

        quizBubble('ich', buchstabe, '', []);
        quizFrage('send', buchstabe);
    }

    function quizStarten() {
        if (!quizFaceChat || !cfg.quizBereit) return;

        quizFaceChat.querySelectorAll('.msg-row').forEach((r) => r.remove());
        if (quizFaceEmpty) quizFaceEmpty.hidden = true;

        quizGestartet = true;
        quizFrage('start', '');
    }

    if (quizFaceStart) {
        quizFaceStart.addEventListener('click', quizStarten);
    }

    if (quizZurueckBtn) {
        quizZurueckBtn.addEventListener('click', () => goTo(1));
    }

    if (quizBalken) {
        quizBalken.addEventListener('click', () => {
            goTo(3);

            // Erster Klick startet das Quiz direkt auf der 4. Fläche
            if (cfg.quizBereit && !quizGestartet) {
                window.setTimeout(quizStarten, reduziertBewegung() ? 0 : DREH_DAUER);
            }
        });
    }
})();
