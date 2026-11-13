/* ---------------------------------------------------------------------------
 * KI-Spinner
 *
 * Läuft unabhängig vom Recorder: sobald ein Formular einen Knopf mit
 * value="erzeugen" abschickt, zeigt die Seite „Die KI schreibt …" an. Der
 * Server braucht dafür 10–20 Sekunden, ohne Rückmeldung wirkt die Seite tot.
 *
 * Markup: <div class="ki-spinner" id="ki-spinner" data-form="#…" hidden>
 * ------------------------------------------------------------------------- */

(() => {
    'use strict';

    const spinner = document.getElementById('ki-spinner');

    if (!spinner) {
        return;
    }

    const formular = spinner.dataset.form
        ? document.querySelector(spinner.dataset.form)
        : document.querySelector('form');

    if (!formular) {
        return;
    }

    let absender = null;

    formular.querySelectorAll('button[type="submit"]').forEach((knopf) => {
        knopf.addEventListener('click', () => { absender = knopf; });
    });

    formular.addEventListener('submit', (ev) => {
        const knopf = ev.submitter instanceof HTMLElement ? ev.submitter : absender;
        const aktion = knopf && knopf.value ? knopf.value : '';

        if (aktion !== 'erzeugen') {
            return;
        }

        if (spinner.dataset.laeuft === '1') {
            ev.preventDefault();        // zweiter Klick zählt nicht

            return;
        }

        spinner.dataset.laeuft = '1';
        spinner.hidden = false;

        /* Alle Knöpfe sperren außer dem gedrückten – ein deaktivierter Knopf
           würde seinen Wert nicht mehr mitsenden. */
        formular.querySelectorAll('button[type="submit"]').forEach((b) => {
            if (b !== knopf) {
                b.disabled = true;
            }
        });

        if (knopf) {
            knopf.textContent = 'Prompt wird erzeugt …';
        }

        formular.setAttribute('aria-busy', 'true');
    });
})();

/* ---------------------------------------------------------------------------
 * Dialog-Recorder für Lern-Bausteine
 *
 * Ein Musterdialog wird wie im echten Chat aufgezeichnet: Lehrer links (weiß),
 * Schüler rechts (grün). Jede Blase hat Text UND Formel getrennt – genau so,
 * wie die KI später antworten soll (JSON: text / formel).
 *
 * Die Zeilen landen als Textzeilen im Formularfeld #dialog:
 *     rolle | text | formel | bild
 *
 * Blasen lassen sich jederzeit bearbeiten, löschen und verschieben.
 * ------------------------------------------------------------------------- */

(() => {
    'use strict';

    const liste    = document.getElementById('bubble-list');
    const textEl   = document.getElementById('dialog-text');
    const formelEl = document.getElementById('dialog-formel');
    const bildEl   = document.getElementById('dialog-bild');
    const addBtn   = document.getElementById('dialog-add');
    const ziel     = document.getElementById('dialog');
    const banner   = document.getElementById('edit-banner');
    const cancel   = document.getElementById('edit-cancel');
    const zaehler  = document.getElementById('dialog-zaehler');
    const dateiBtn = document.getElementById('dialog-hochladen');
    const dateiEl  = document.getElementById('dialog-datei');
    const vorschau = document.getElementById('dialog-vorschau');
    const status   = document.getElementById('dialog-upload-status');

    if (!liste || !textEl || !addBtn || !ziel) {
        return;
    }

    /** @type {Array<{rolle:string, text:string, formel:string, bild:string}>} */
    let zeilen = [];
    let bearbeiten = -1;        // -1 = neue Zeile

    /* ----------------------------------------------------------------------
     * Textzeilen lesen und schreiben
     * -------------------------------------------------------------------- */

    function feldSaeubern(wert) {
        return String(wert === undefined || wert === null ? '' : wert)
            .replace(/[\r\n]+/g, ' ')
            .replace(/\|/g, ' ')
            .trim();
    }

    /** „rolle | text | formel | bild" → Objekt. */
    function zeileLesen(text) {
        const teile = String(text).split('|');

        if (teile.length < 2) {
            return null;
        }

        const rolle = (teile[0] || '').trim().toLowerCase();

        return {
            rolle: (rolle === 'schueler' || rolle === 'schüler') ? 'schueler' : 'lehrer',
            text: (teile[1] || '').trim(),
            formel: (teile[2] || '').trim(),
            bild: (teile[3] || '').trim(),
        };
    }

    function zeileSchreiben(z) {
        return [z.rolle, feldSaeubern(z.text), feldSaeubern(z.formel), feldSaeubern(z.bild)].join(' | ');
    }

    /** Formularfeld aktualisieren (wird gespeichert und an die KI gegeben). */
    function sichern() {
        ziel.value = zeilen.map(zeileSchreiben).join('\n');

        if (zaehler) {
            const lehrer = zeilen.filter((z) => z.rolle === 'lehrer').length;
            zaehler.textContent = zeilen.length === 0
                ? 'noch keine Zeile'
                : zeilen.length + ' Zeilen (' + lehrer + ' Lehrer · '
                    + (zeilen.length - lehrer) + ' Schüler)';
        }

        document.dispatchEvent(new CustomEvent('dialog:geaendert', {
            detail: { anzahl: zeilen.length },
        }));
    }

    function rolleGewaehlt() {
        const aktiv = document.querySelector('input[name="rolle"]:checked');

        return aktiv && aktiv.value === 'schueler' ? 'schueler' : 'lehrer';
    }

    function setzeRolle(rolle) {
        document.querySelectorAll('input[name="rolle"]').forEach((r) => {
            r.checked = r.value === rolle;
        });
    }

    /* ----------------------------------------------------------------------
     * Anzeige
     * -------------------------------------------------------------------- */

    /** Nummer der ersten Zeile mit Bild – das ist das Hauptbild des Bausteins. */
    function erstesBildIndex() {
        return zeilen.findIndex((z) => String(z.bild || '').trim() !== '');
    }

    function baueBlase(z, nr) {
        const row = document.createElement('div');
        row.className = 'bubble-row bubble-row--' + z.rolle
            + (nr === bearbeiten ? ' bubble-row--edit' : '');
        row.dataset.nr = String(nr);

        const bubble = document.createElement('div');
        bubble.className = 'bubble';

        const kopf = document.createElement('span');
        kopf.className = 'bubble-role';
        kopf.textContent = (nr + 1) + '. ' + (z.rolle === 'lehrer' ? 'Lehrer' : 'Schüler');

        if (z.bild !== '' && nr === erstesBildIndex()) {
            const marke = document.createElement('span');
            marke.className = 'bubble-hauptbild';
            marke.textContent = 'Hauptbild';
            marke.title = 'Erstes Bild im Musterdialog – wird als Referenzbild des Bausteins verwendet';
            kopf.append(' ', marke);
        }

        bubble.append(kopf);

        if (z.text !== '') {
            const text = document.createElement('span');
            text.className = 'bubble-text';
            text.textContent = z.text;
            bubble.append(text);
        }

        if (z.formel !== '') {
            const wrap = document.createElement('div');
            wrap.className = 'bubble-formel';
            wrap.title = z.formel;

            /* MathLive ersetzt den Inhalt; ohne MathLive steht der LaTeX-Text da. */
            const math = document.createElement('math-div');
            math.textContent = z.formel;
            wrap.append(math);
            bubble.append(wrap);
        }

        if (z.bild !== '') {
            const bild = document.createElement('img');
            bild.className = 'bubble-bild';
            bild.src = z.bild;
            bild.alt = '';
            bild.loading = 'lazy';
            bubble.append(bild);
        }

        if (z.text === '' && z.formel === '' && z.bild === '') {
            const leer = document.createElement('span');
            leer.className = 'bubble-text bubble-text--leer';
            leer.textContent = '(leer)';
            bubble.append(leer);
        }

        const werkzeuge = document.createElement('div');
        werkzeuge.className = 'bubble-tools';

        [
            { zeichen: '✏️', titel: 'Bearbeiten', aktion: 'bearbeiten', klasse: 'bubble-edit' },
            { zeichen: '⇄', titel: 'Rolle tauschen (Lehrer ↔ Schüler)', aktion: 'tauschen', klasse: 'bubble-rollen' },
            { zeichen: '↑', titel: 'Nach oben', aktion: 'hoch', klasse: 'bubble-edit' },
            { zeichen: '↓', titel: 'Nach unten', aktion: 'runter', klasse: 'bubble-edit' },
            { zeichen: '×', titel: 'Löschen', aktion: 'loeschen', klasse: 'bubble-del' },
        ].forEach((k) => {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = k.klasse;
            b.textContent = k.zeichen;
            b.title = k.titel;
            b.setAttribute('aria-label', k.titel + ' (Zeile ' + (nr + 1) + ')');
            b.dataset.aktion = k.aktion;
            b.dataset.nr = String(nr);
            werkzeuge.append(b);
        });

        /* Die Werkzeuge liegen unter der Blase (in der Zeile), nicht außen am
           Rand – sonst rutschen sie bei schmalen Blasen aus dem Feld. */
        row.append(bubble);
        row.append(werkzeuge);
        liste.append(row);
    }

    function zeichnen() {
        liste.textContent = '';
        zeilen.forEach((z, i) => baueBlase(z, i));

        if (zeilen.length === 0) {
            const leer = document.createElement('p');
            leer.className = 'bubble-leer';
            leer.textContent = 'Noch kein Musterdialog – unten Rolle wählen, Text und Formel '
                + 'eingeben, dann auf ➤ drücken.';
            liste.append(leer);
        }

        sichern();
    }

    /* ----------------------------------------------------------------------
     * Composer
     * -------------------------------------------------------------------- */

    function formelWert() {
        if (!formelEl) {
            return '';
        }

        const wert = typeof formelEl.getValue === 'function' ? formelEl.getValue() : formelEl.value;

        return String(wert || '').trim();
    }

    function formelSetzen(wert) {
        if (!formelEl) {
            return;
        }

        if (typeof formelEl.setValue === 'function') {
            formelEl.setValue(wert);
        } else {
            formelEl.value = wert;
        }
    }

    function composerLeeren() {
        textEl.value = '';
        formelSetzen('');

        if (bildEl) {
            bildEl.value = '';
        }

        vorschauZeigen();
    }

    function bearbeitenBeenden() {
        bearbeiten = -1;
        composerLeeren();
        addBtn.textContent = '➤';
        addBtn.title = 'Zeile hinzufügen';
        addBtn.setAttribute('aria-label', 'Zeile hinzufügen');

        if (banner) {
            banner.hidden = true;
        }

        zeichnen();
    }

    function hinzufuegen() {
        const text = feldSaeubern(textEl.value);
        const formel = feldSaeubern(formelWert());
        const bild = feldSaeubern(bildEl ? bildEl.value : '');

        if (text === '' && formel === '' && bild === '') {
            textEl.focus();

            return;
        }

        const neu = { rolle: rolleGewaehlt(), text: text, formel: formel, bild: bild };

        if (bearbeiten >= 0 && zeilen[bearbeiten]) {
            zeilen[bearbeiten] = neu;
            bearbeitenBeenden();

            return;
        }

        zeilen.push(neu);
        composerLeeren();
        zeichnen();
        textEl.focus();
    }

    function bearbeitenStarten(nr) {
        const z = zeilen[nr];

        if (!z) {
            return;
        }

        bearbeiten = nr;
        setzeRolle(z.rolle);
        textEl.value = z.text;
        formelSetzen(z.formel);

        if (bildEl) {
            bildEl.value = z.bild;
        }

        vorschauZeigen();

        addBtn.textContent = '✓';
        addBtn.title = 'Änderung übernehmen';
        addBtn.setAttribute('aria-label', 'Änderung übernehmen');

        if (banner) {
            banner.hidden = false;
        }

        zeichnen();
        textEl.focus();
    }

    /* ----------------------------------------------------------------------
     * Bild-Upload (Lehrkraft, api/upload.php)
     * -------------------------------------------------------------------- */

    /** Vorschau des Bildpfads im Composer. */
    function vorschauZeigen() {
        if (!vorschau) {
            return;
        }

        const pfad = bildEl ? feldSaeubern(bildEl.value) : '';
        vorschau.textContent = '';

        if (pfad === '') {
            vorschau.hidden = true;

            return;
        }

        const img = document.createElement('img');
        img.src = pfad;
        img.alt = 'Bildvorschau';
        vorschau.append(img);

        const vorherSchonBild = zeilen.some((z, i) => i !== bearbeiten && String(z.bild || '').trim() !== '');

        const hinweis = document.createElement('span');
        hinweis.className = 'dialog-vorschau-hinweis';
        hinweis.textContent = vorherSchonBild
            ? 'weiteres Bild (kein Hauptbild)'
            : 'Hauptbild des Bausteins';
        vorschau.append(hinweis);
        vorschau.hidden = false;
    }

    async function hochladen(datei) {
        if (!datei || !bildEl) {
            return;
        }

        if (status) {
            status.textContent = 'Bild wird hochgeladen …';
        }

        const daten = new FormData();
        daten.append('file', datei);

        const csrfFeld = document.querySelector('input[name="csrf"]');

        if (csrfFeld) {
            daten.append('csrf', csrfFeld.value);
        }

        try {
            const antwort = await fetch('api/upload.php', { method: 'POST', body: daten });
            const ergebnis = await antwort.json();

            if (!ergebnis || ergebnis.ok !== true) {
                throw new Error((ergebnis && ergebnis.fehler) || 'Upload fehlgeschlagen (HTTP ' + antwort.status + ')');
            }

            bildEl.value = ergebnis.pfad;

            if (status) {
                status.textContent = 'Hochgeladen: ' + ergebnis.pfad;
            }

            vorschauZeigen();
        } catch (fehler) {
            if (status) {
                status.textContent = 'Upload hat nicht geklappt: ' + fehler.message;
            }
        } finally {
            if (dateiEl) {
                dateiEl.value = '';
            }
        }
    }

    /* ----------------------------------------------------------------------
     * Tastatur: Desktop = Hardware, Touch = Bildschirmtastatur
     * -------------------------------------------------------------------- */

    /** Sitzt ein echter Zeiger (Maus) davor? Dann ist eine Hardware-Tastatur da. */
    function hatHardwareTastatur() {
        const feinerZeiger = window.matchMedia
            && window.matchMedia('(hover: hover) and (pointer: fine)').matches;

        return !!feinerZeiger;
    }

    /**
     * Nur am Desktop: das Formelfeld nimmt Tippen von der Hardware-Tastatur an.
     * Auf Tablets/Handys bleibt inputmode="none" – dort tippt man über die
     * Bildschirmtastatur, sonst käme zusätzlich die System-Tastatur hoch.
     */
    function hardwareTastaturErlauben() {
        if (!formelEl || !hatHardwareTastatur()) {
            return;
        }

        formelEl.removeAttribute('inputmode');

        try {
            formelEl.inputMode = 'text';
        } catch (fehler) {
            /* ältere MathLive-Versionen kennen inputMode nicht */
        }

        const schatten = formelEl.shadowRoot;

        if (schatten) {
            schatten.querySelectorAll('textarea, input, [contenteditable]').forEach((el) => {
                el.removeAttribute('inputmode');
                el.setAttribute('inputmode', 'text');
            });
        }
    }

    /* MathLive und keyboard.js bauen das Feld erst nach dem Laden zusammen –
       deshalb mehrfach nachfassen (DOMContentLoaded, kurz danach, beim Fokus). */
    function tastaturNachfassen() {
        hardwareTastaturErlauben();
        window.setTimeout(hardwareTastaturErlauben, 300);
        window.setTimeout(hardwareTastaturErlauben, 1500);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', tastaturNachfassen);
    } else {
        tastaturNachfassen();
    }

    if (window.customElements && typeof window.customElements.whenDefined === 'function') {
        window.customElements.whenDefined('math-field')
            .then(hardwareTastaturErlauben)
            .catch(() => {});
    }

    if (formelEl) {
        formelEl.addEventListener('focus', hardwareTastaturErlauben);
    }

    /* ----------------------------------------------------------------------
     * Ereignisse
     * -------------------------------------------------------------------- */

    if (dateiBtn && dateiEl) {
        dateiBtn.addEventListener('click', () => dateiEl.click());
        dateiEl.addEventListener('change', () => hochladen(dateiEl.files && dateiEl.files[0]));
    }

    if (bildEl) {
        bildEl.addEventListener('input', vorschauZeigen);
    }

    addBtn.addEventListener('click', hinzufuegen);
    if (cancel) {
        cancel.addEventListener('click', bearbeitenBeenden);
    }

    textEl.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            hinzufuegen();
        }
    });

    liste.addEventListener('click', (e) => {
        const knopf = e.target.closest('button[data-aktion]');

        if (!knopf) {
            return;
        }

        const nr = Number(knopf.dataset.nr);
        const aktion = knopf.dataset.aktion;

        if (aktion === 'bearbeiten') {
            bearbeitenStarten(nr);

            return;
        }

        if (aktion === 'tauschen') {
            const zeile = zeilen[nr];

            if (!zeile) {
                return;
            }

            zeile.rolle = zeile.rolle === 'lehrer' ? 'schueler' : 'lehrer';

            /* Wird die Zeile gerade bearbeitet, springt die Rollenwahl mit. */
            if (bearbeiten === nr) {
                setzeRolle(zeile.rolle);
            }

            zeichnen();

            return;
        }

        if (aktion === 'loeschen') {
            zeilen.splice(nr, 1);

            if (bearbeiten === nr) {
                bearbeitenBeenden();

                return;
            }

            if (bearbeiten > nr) {
                bearbeiten--;
            }

            zeichnen();

            return;
        }

        const tausch = aktion === 'hoch' ? nr - 1 : nr + 1;

        if ((aktion === 'hoch' && nr > 0) || (aktion === 'runter' && nr < zeilen.length - 1)) {
            const zwischen = zeilen[tausch];
            zeilen[tausch] = zeilen[nr];
            zeilen[nr] = zwischen;
            zeichnen();
        }
    });

    /* ----------------------------------------------------------------------
     * Start + Schnittstelle
     * -------------------------------------------------------------------- */

    function laden(text) {
        zeilen = String(text || '')
            .split('\n')
            .map(zeileLesen)
            .filter((z) => z !== null);

        bearbeiten = -1;
        zeichnen();
    }

    laden(ziel.value);

    window.DialogEditor = {
        zeilen: () => zeilen.map((z) => Object.assign({}, z)),
        text: () => ziel.value,
        laden: laden,
        hinzufuegen: (rolle, text, formel, bild) => {
            zeilen.push({
                rolle: rolle === 'schueler' ? 'schueler' : 'lehrer',
                text: text || '',
                formel: formel || '',
                bild: bild || '',
            });
            zeichnen();
        },
        leeren: () => {
            zeilen = [];
            bearbeitenBeenden();
        },
    };
})();
