<?php

declare(strict_types=1);

/**
 * hilfe.php – Kurzanleitung für Schülerinnen, Schüler und Lehrkräfte.
 * Stand 11.11.2026 (Lernchat, vier Würfelflächen, Multiple-Choice-Quiz
 * „Teste dein Wissen"). Bewusst ohne Bezug auf Fernsehquiz-Fernsehformate:
 * das Quiz heißt hier „Multiple Choice", es gibt keine Hilfsmittel.
 */

require __DIR__ . '/inc/config.php';
require __DIR__ . '/inc/chat.php';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Hilfe · Matheschaf</title>
    <link rel="stylesheet" href="<?= asset_version('assets/admin2.css') ?>">
    <link rel="stylesheet" href="<?= asset_version('assets/recht.css') ?>">
    <?= app_icon_tags('starter.php') ?>
</head>
<body>
    <header class="topbar">
        <div class="topbar-inner">
            <img class="brand-logo" src="schaf1.png" alt="" width="44" height="44">
            <div class="brand-text">
                <h1>So funktioniert's</h1>
                <p>Kurzanleitung für Schülerinnen, Schüler und Lehrkräfte</p>
            </div>
            <a class="topbar-link" href="starter.php">Lobby</a>
        </div>
    </header>

    <div class="wrap recht-wrap">
        <div class="recht-karte">
            <h2>1. Reinkommen</h2>
            <p>
                Du bekommst von deiner Lehrkraft einen Link (z. B.
                <code><?= e(BASE_URL) ?>index.php?id=2301</code>) oder eine SMS. Öffne den Link im
                Browser – Anmeldung mit Passwort brauchst du nicht, deine ID steckt im Link.
                Tipp: Lege die Seite über das Browser-Menü <strong>„Zum Startbildschirm hinzufügen"</strong>
                ab, dann startet sie wie eine App mit dem Schaf-Icon.
            </p>
            <p>
                Oben in der Leiste stehen dein Name und das aktuelle Thema. Direkt neben dem Namen
                sitzt ein kleines <strong>?</strong> – es öffnet diese Hilfe jederzeit wieder.
            </p>

            <h2>2. Die Lobby – hier startest du</h2>
            <p>
                Was du nach dem Öffnen siehst, ist die <strong>Lobby</strong>: die mittlere
                Würfelfläche mit der Überschrift „Level-Check". Von hier aus gibt es zwei Wege:
            </p>
            <ul>
                <li>
                    <strong>Schwierigkeit wählen:</strong> <strong>Leicht</strong> (zum Warmlaufen),
                    <strong>Mittel</strong>, <strong>Schwer</strong> oder <strong>Zufall</strong>
                    (das Matheschaf sucht aus). Mit dem Klick startet eine <strong>neue Übung</strong>
                    – der alte Verlauf wird dabei geleert.
                </li>
                <li>
                    <strong>Quiz:</strong> der goldene Balken <strong>„🎬 Teste dein Wissen"</strong>.
                    Er zeigt den Titel und die Zahl der Fragen (z. B. „Trigonometrie · 5 Fragen").
                    Ein Klick dreht den Würfel auf die Quiz-Fläche und startet das Quiz.
                </li>
            </ul>
            <p>
                Ist für dich <strong>kein Quiz freigeschaltet</strong>, ist der Balken blass
                („noch kein Quiz freigeschaltet") und reagiert nicht – dann fehlt nur die
                Freischaltung durch deine Lehrkraft, alles andere funktioniert normal.
            </p>
            <p>
                Sobald du eine Schwierigkeit gewählt hast, verschwindet die Lobby und der Chat
                erscheint. Zurück zur Lobby kommst du, indem du die Seite neu lädst
                (F5 bzw. „Neu laden" im Browsermenü) – dein Verlauf bleibt dabei erhalten.
                Ganz unten am Seitenende stehen die winzigen Links zu <strong>Impressum</strong>,
                <strong>Datenschutz</strong> und <strong>Einwilligung</strong>; sie sind von jeder
                Würfelfläche aus erreichbar.
            </p>

            <h2>3. Vier Flächen – der Würfel</h2>
            <p>Die Seite ist ein drehbarer Würfel mit vier Seiten. Wechseln kannst du mit:</p>
            <ul>
                <li><strong>Wischen</strong> mit dem Finger (oder der Maus),</li>
                <li>den <strong>Pfeilen ‹ ›</strong> oben links und rechts,</li>
                <li>der <strong>Pfeiltaste</strong> auf der Tastatur.</li>
            </ul>
            <table class="recht-tabelle">
                <tr><th>Fläche</th><th>Was du dort findest</th></tr>
                <tr><td>Links</td><td>Das <strong>Referenzbild</strong> zur Aufgabe (z. B. eine Skizze). Die KI kennt das Bild und geht darauf ein.</td></tr>
                <tr><td>Mitte</td><td>Der <strong>Chat</strong>: Aufgabe, deine Antworten, die Rückmeldungen der KI.</td></tr>
                <tr><td>Rechts</td><td>Die <strong>Schafswiese</strong> mit deinen gesammelten Schafen und wechselndem Wetter (Sonne, Wolken, Regen, Schnee, Nebel, Gewitter).</td></tr>
                <tr><td>Ganz rechts</td><td>Das <strong>Multiple-Choice-Quiz „Teste dein Wissen"</strong> mit Antwortknöpfen A–D.</td></tr>
            </table>

            <h2>4. Antworten schreiben</h2>
            <p>
                Schreibe unten ins Feld und drücke <strong>Enter</strong> oder den grünen Senden-Knopf.
                <strong>Grüne Blasen</strong> sind deine Nachrichten, <strong>weiße Blasen</strong> kommen vom
                Matheschaf. Für Formeln gibt es das Feld darunter (MathLive) – dort erscheint deine Eingabe
                als richtige Formel.
            </p>

            <h2>5. Die Bildschirmtastatur</h2>
            <p>Drei Ebenen, umschaltbar über die Tabs:</p>
            <ul>
                <li><strong>ABC</strong> – Buchstaben und Zahlen für ganze Sätze.</li>
                <li><strong>∑ Mathe</strong> – Grundrechenarten, Brüche, Wurzeln, Hochzahlen.</li>
                <li><strong>αβγ</strong> – sin, cos, tan, griechische Buchstaben, Gradzeichen.</li>
            </ul>
            <p>
                Die Tastatur startet auf <strong>Σ Mathe</strong>, damit du Zahlen und Operatoren sofort
                tippen kannst. Mit dem Pfeil rechts neben den Tabs klappst du sie ein und aus.
            </p>
        </div>

        <div class="recht-karte">
            <h2>6. Schafe, Punkte und Ziel</h2>
            <p>
                Für jeden richtig gelösten Zwischenschritt bekommst du ein <strong>Schaf</strong> – jedes
                Schaf ist ein Bonuspunkt und erscheint auf der Schafswiese. Wenn du
                <strong>zwei Aufgaben in Folge vollständig richtig</strong> löst, ist das Ziel erreicht:
                Es regnet Schafe, die Übung ist für diese Lehrkraft als „geschafft" markiert und die KI
                verabschiedet sich.
            </p>
            <p>
                Eine falsche oder unvollständige Antwort setzt den Zähler zurück. Die Lösung wird dir
                nicht verraten – du bekommst Hinweise und Rückfragen, bis du selbst drauf kommst.
            </p>

            <h2>7. Quiz: „Teste dein Wissen" (Multiple Choice)</h2>
            <p>
                Das Quiz ist ein <strong>Multiple-Choice-Quiz</strong>: Das Matheschaf stellt
                <strong>immer genau eine Frage</strong> und gibt dazu <strong>vier Antworten von
                A) bis D)</strong>, von denen genau eine stimmt. Du tippst auf den farbigen Knopf
                mit deiner Antwort. Ein Eingabefeld gibt es auf dieser Fläche bewusst nicht –
                so öffnet sich am Handy keine Tastatur und die Schafstribüne bleibt sichtbar.
            </p>
            <ul>
                <li>Wie viele Fragen ein Durchgang hat, stellt deine Lehrkraft ein (meist
                    <strong>fünf</strong>). Über der Frage steht immer, wo du bist – z. B.
                    „Frage 2 von 5".</li>
                <li>Jede <strong>neue Frage beginnt oben</strong> in der Fragenliste; die
                    Rückmeldung zur vorigen Frage rutscht darüber hinaus.</li>
                <li><strong>Richtig:</strong> Konfetti, Jubel und die Schafe auf der Tribüne hüpfen –
                    und ein Schaf wandert auf deine Wiese.</li>
                <li><strong>Falsch:</strong> ein „mäh", die Schafe schütteln den Kopf, und in einem
                    Satz wird die richtige Antwort erklärt. Danach geht es sofort mit der
                    <strong>nächsten Frage</strong> weiter – die Frage wird nicht wiederholt.</li>
                <li>Nach der letzten Frage kommt die Auswertung („3 von 5 richtig").</li>
                <li>Der Lautsprecher oben rechts schaltet den Ton ein und aus; die Einstellung
                    bleibt gespeichert.</li>
                <li><strong>„← Zurück zur Lobby"</strong> oben links dreht den Würfel zurück,
                    <strong>„▶️ Neues Quiz"</strong> startet einen neuen Durchgang.</li>
            </ul>
            <p>
                Ob du ein Quiz hast, entscheidet deine Lehrkraft. Fehlt die Freischaltung, steht das
                direkt auf der Fläche.
            </p>

            <h2>8. Wenn etwas klemmt</h2>
            <ul>
                <li><strong>„⚠️ Keine Verbindung zum Matheschaf"</strong> – unten erscheint der Knopf
                    „↻ Nochmal versuchen"; deine Eingabe bleibt erhalten.</li>
                <li><strong>Leere Antwort</strong> – das Matheschaf hat sich „verhaspelt"; einfach noch einmal fragen.</li>
                <li><strong>Keine Aufgabe freigeschaltet</strong> – bitte deine Lehrkraft, dir einen Baustein bzw. ein Quiz zuzuweisen.</li>
                <li><strong>KI irrt sich</strong> – das kann passieren. Sag deiner Lehrkraft Bescheid; sie kann den Verlauf mitlesen.</li>
            </ul>

            <h2>9. Für Lehrkräfte (Kurzüberblick)</h2>
            <ul>
                <li><strong>Anmeldung:</strong> <code>login.php</code> – alle Verwaltungsseiten sind
                    geschützt; nach dem Login geht es zurück auf die angeforderte Seite.</li>
                <li><strong>starter.php</strong> – Cockpit: Schülerkarten mit Punkten, Bausteinen,
                    „geschafft/offen“, Einwilligung, Chat-Link, SMS, Anruf, Mitlesen.</li>
                <li><strong>schueler.php</strong> / <strong>schueler_edit.php</strong> – Schüler anlegen und
                    ändern, Einwilligung je Kanal (SMS, Anruf, Quiz, Telegram) mit Version/Datum/Art.</li>
                <li><strong>bausteine.php</strong> / <strong>baustein_edit.php</strong> – Bausteine ansehen und
                    bearbeiten (Dialog, Aufgaben, Quiz-Fragen als Textzeilen).</li>
                <li><strong>baustein_generator.php</strong> – Prompt von der KI erzeugen lassen, prüfen, speichern.</li>
                <li><strong>zuordnung.php</strong> – Lern- und Quiz-Baustein einem Schüler zuweisen.</li>
                <li><strong>mitlesen.php?id=…</strong> – Verlauf lesen, umschaltbar zwischen Lernchat und Quiz.</li>
                <li><strong>quiz_test.php</strong> – Quiz ausprobieren, ohne Schülerbezug.</li>
                <li><strong>einwilligung.php?id=…</strong> – Link an die Eltern: Einwilligung granular setzen
                    (SMS/Anruf/Quiz/Telegram) oder widerrufen.</li>
            </ul>

            <p class="recht-stand">Stand: 11. November 2026 · Fassung Neubau (lernchat)</p>
        </div>

        <div class="recht-knoepfe">
            <button type="button" class="btn" onclick="zurueck()">Alles klar, zurück</button>
            <a class="btn btn--secondary" href="impressum.php">Impressum</a>
            <a class="btn btn--secondary" href="datenschutz.php">Datenschutz</a>
            <a class="btn btn--secondary" href="einwilligung.php">Einwilligung</a>
        </div>
    </div>

    <p class="foot">Matheschaf · 🐑</p>

    <script>
        function zurueck() {
            if (window.self !== window.top) {
                window.parent.postMessage('closeHilfe', '*');
            } else if (history.length > 1) {
                history.back();
            } else {
                window.location.href = 'starter.php';
            }
        }
    </script>
</body>
</html>

