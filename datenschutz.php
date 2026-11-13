<?php

declare(strict_types=1);

/**
 * datenschutz.php – Datenschutzerklärung (Entwurf, Stand 11.11.2026).
 * Die abschließende rechtliche Prüfung erfolgt durch eine Anwaltskanzlei.
 */

require __DIR__ . '/inc/config.php';
require __DIR__ . '/inc/chat.php';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Datenschutz · Matheschaf</title>
    <link rel="stylesheet" href="<?= asset_version('assets/admin2.css') ?>">
    <link rel="stylesheet" href="<?= asset_version('assets/recht.css') ?>">
    <?= app_icon_tags('starter.php') ?>
</head>
<body>
    <header class="topbar">
        <div class="topbar-inner">
            <img class="brand-logo" src="schaf1.png" alt="" width="44" height="44">
            <div class="brand-text">
                <h1>Datenschutz</h1>
                <p>Informationen nach Art. 13 und 14 DSGVO</p>
            </div>
            <a class="topbar-link" href="starter.php">Lobby</a>
        </div>
    </header>

    <div class="wrap recht-wrap">
        <p class="recht-hinweis">
            <strong>Entwurf.</strong> Diese Erklärung beschreibt die Datenverarbeitung auf dem Stand
            vom 11.11.2026. Die abschließende rechtliche Prüfung erfolgt durch eine Anwaltskanzlei.
            Wo Angaben noch fehlen (z. B. Hosting-Anbieter, Auftragsverarbeitungsverträge),
            sind sie mit <code>[…]</code> markiert.
        </p>

        <div class="recht-karte">
            <h2>1. Verantwortlicher</h2>
            <p>Verantwortlich für die Datenverarbeitung im Sinne der DSGVO ist:</p>
            <p>
                Michael Romahn<br>
                Sollnitzer Str. 1<br>
                D-06785 Oranienbaum-Wörlitz<br>
                Telefon: <a href="tel:+4915773301560">01577 3301560</a><br>
                E-Mail: <a href="mailto:miro@matheschaf.de">miro@matheschaf.de</a>
            </p>
            <p>
                Ein Datenschutzbeauftragter ist nicht bestellt; die gesetzlichen Voraussetzungen
                (Art. 37 DSGVO, § 38 BDSG) liegen nach derzeitiger Einschätzung nicht vor.
                <span class="recht-stand">[…] durch die Anwaltskanzlei prüfen lassen.</span>
            </p>

            <h2>2. Überblick über das Angebot</h2>
            <p>Das Matheschaf ist ein KI-gestütztes Lernsystem für den Mathematikunterricht. Es besteht aus:</p>
            <ul>
                <li><strong>Schüler-Chat</strong> mit einem KI-Lernbegleiter, der auf Lern-Bausteinen (Prompts) der Lehrkraft aufsetzt,</li>
                <li><strong>Quiz „Teste dein Wissen"</strong> (vierte Würfelfläche) mit Fragen im Multiple-Choice-Format,</li>
                <li><strong>Lehrer-Cockpit</strong> zum Anlegen von Schülern und Bausteinen, zur Zuordnung und Begleitung,</li>
                <li><strong>Mitlese-Ansicht</strong>, mit der die Lehrkraft einen Verlauf passiv lesen kann,</li>
                <li><strong>Benachrichtigungen</strong> per Flash-SMS und Sprachanruf über seven.io –
                    nur mit ausdrücklicher Einwilligung; Telegram ist optional und standardmäßig aus.</li>
            </ul>
            <p>
                Personenbezogene Daten verarbeiten wir nur, soweit dies für den Betrieb dieses Angebots
                erforderlich ist oder eine Einwilligung vorliegt.
            </p>
        </div>

        <div class="recht-karte">
            <h2>3. Welche Daten wir verarbeiten</h2>

            <h3>3.1 Stammdaten der Schülerinnen und Schüler</h3>
            <ul>
                <li>interne Schüler-ID (Zugangskennung, steht im Chat-Link),</li>
                <li>Vorname, Nachname, Klasse,</li>
                <li>Telefonnummer (nur wenn für SMS/Anruf hinterlegt),</li>
                <li>Bonuspunkte (Anzahl der gesammelten „Schafe"),</li>
                <li>Kennzeichen, ob eine Aufgabe bzw. ein Quiz freigeschaltet ist.</li>
            </ul>

            <h3>3.2 Inhalts- und Nutzungsdaten</h3>
            <ul>
                <li><strong>Lern-Verlauf</strong> (<code>ms_nachrichten</code>, Kontext <code>chat</code>):
                    Nachrichten der Schülerin bzw. des Schülers und Antworten der KI, jeweils als Text
                    und ggf. als LaTeX-Formel, mit Zeitstempel; bleibt gespeichert, bis er gelöscht wird,</li>
                <li><strong>Quiz-Verlauf</strong> (<code>ms_nachrichten</code>, Kontext <code>quiz</code>):
                    derselbe Aufbau, getrennt vom Lernchat, inkl. Kennzeichen richtig/falsch,</li>
                <li>gewählte Schwierigkeitsstufe, aktiver Lern-Baustein, aktives Quiz-Baustein,</li>
                <li>Liste der bearbeiteten Bausteine mit Status (offen/geschafft) und Erreichungsdatum
                    (<code>ms_schueler_bausteine</code>),</li>
                <li>Zeitpunkte, z. B. Beginn einer Übung.</li>
            </ul>

            <h3>3.3 Bilder</h3>
            <p>
                Beim Aufzeichnen von Bausteinen und beim Hochladen durch Lehrkräfte können Bilddateien auf
                dem Server gespeichert werden (<code>bilder/</code>, Dateinamen ohne Personenbezug).
                Referenzbilder eines Bausteins werden zusätzlich an den KI-Dienst übermittelt (siehe 6.).
            </p>

            <h3>3.4 Bausteine und Prompts</h3>
            <p>
                Lern-Bausteine und Quiz-Inhalte enthalten Aufgaben, Beispieldialoge und Prompts. Sie
                enthalten grundsätzlich keine personenbezogenen Daten; trägt eine Lehrkraft in einem
                Beispieldialog einen Schülernamen ein, ist dieser Bestandteil des Bausteins.
            </p>

            <h3>3.5 Technische Daten</h3>
            <ul>
                <li>Server-Logfiles des Hosting-Anbieters (IP-Adresse, Zeitpunkt, aufgerufene Datei, User-Agent, Statuscode),</li>
                <li>Session-Cookie <code>ms_session</code> (technisch notwendig; keine Werbe- oder Tracking-Cookies),</li>
                <li>lokale Einstellung <code>matheschaf_quiz_ton</code> (Ton an/aus) im Speicher des Browsers.</li>
            </ul>
            <p>
                Wir setzen <strong>keine</strong> Analyse-, Werbe- oder Social-Media-Cookies ein und
                erstellen keine Nutzungsprofile.
            </p>
        </div>

        <div class="recht-karte">
            <h2>4. Zwecke und Rechtsgrundlagen</h2>
            <table class="recht-tabelle">
                <tr><th>Verarbeitung</th><th>Zweck</th><th>Rechtsgrundlage</th></tr>
                <tr>
                    <td>Bereitstellung von Chat und Quiz</td>
                    <td>Durchführung der Übung, Anzeige des Verlaufs</td>
                    <td>Art. 6 Abs. 1 lit. b bzw. lit. a DSGVO</td>
                </tr>
                <tr>
                    <td>Übermittlung an den KI-Dienst</td>
                    <td>Erzeugen der Antworten und Quizfragen</td>
                    <td>Art. 6 Abs. 1 lit. a, Art. 49 Abs. 1 lit. a DSGVO</td>
                </tr>
                <tr>
                    <td>Telefonnummer für SMS/Anruf</td>
                    <td>Erinnerung, Kontaktaufnahme durch die Lehrkraft</td>
                    <td>Art. 6 Abs. 1 lit. a DSGVO</td>
                </tr>
                <tr>
                    <td>Telegram-Benachrichtigungen</td>
                    <td>Information der Lehrkraft (Start, Ziel erreicht)</td>
                    <td>Art. 6 Abs. 1 lit. f DSGVO</td>
                </tr>
                <tr>
                    <td>Server-Logfiles</td>
                    <td>Betriebssicherheit, Fehlersuche</td>
                    <td>Art. 6 Abs. 1 lit. f DSGVO</td>
                </tr>
            </table>
            <p>
                Eine automatisierte Entscheidungsfindung mit rechtlicher Wirkung (Art. 22 DSGVO) findet
                nicht statt. Bonuspunkte dienen ausschließlich der Motivation.
            </p>

            <h2>5. Minderjährige</h2>
            <p>
                Das Angebot richtet sich an Schülerinnen und Schüler. Bei Kindern und Jugendlichen unter
                16 Jahren ist für die Verarbeitung auf Grundlage einer Einwilligung die Zustimmung der
                Erziehungsberechtigten erforderlich (Art. 8 DSGVO i. V. m. § 107 BGB). Dafür gibt es das
                Formular unter <a href="einwilligung.php">Einwilligung</a>.
            </p>

            <h2>6. Einsatz des KI-Lernbegleiters (Google Gemini)</h2>
            <p>
                Für die Antworten im Chat und die Quizfragen nutzen wir ein Sprachmodell von Google
                (Gemini), aufgerufen über die Programmierschnittstelle
                <code>generativelanguage.googleapis.com</code>. Übermittelt werden dabei:
            </p>
            <ul>
                <li>der eingegebene Text der Schülerin bzw. des Schülers (inkl. Formel),</li>
                <li>der bisherige Gesprächsverlauf dieser Übung,</li>
                <li>der Vorname (zur Anrede), die gewählte Stufe und der System-Prompt des Bausteins,</li>
                <li>ggf. das Referenzbild des Bausteins (als Bilddaten).</li>
            </ul>
            <p>
                <strong>Drittland:</strong> Google verarbeitet die Daten auch in den USA. Die Übermittlung
                erfolgt auf Grundlage der Einwilligung (Art. 49 Abs. 1 lit. a DSGVO) bzw. der von Google
                bereitgestellten Garantien. <span class="recht-stand">[…] Auftragsverarbeitungsvertrag und
                Garantien durch die Anwaltskanzlei prüfen.</span>
            </p>
            <p>
                Wir nutzen die übermittelten Inhalte nicht zum Training eigener Modelle. Die Antworten
                können fehlerhaft sein; verbindlich ist nur die Rückmeldung der Lehrkraft.
            </p>

            <h2>7. Quiz „Teste dein Wissen"</h2>
            <p>
                Das Quiz nutzt denselben KI-Dienst wie der Lernchat, jedoch mit einem eigenen Prompt und
                einem eigenen Verlauf (<code>ms_nachrichten</code>, Kontext <code>quiz</code>), der den
                Lernchat nicht verändert.
                Richtige Antworten können Bonuspunkte ergeben; nach der letzten Frage wird der Durchgang
                ausgewertet. Es gibt keine Wetten und keine Gewinnspiele.
            </p>

            <h2>8. Lehrer-Begleitung und Mitlese-Link</h2>
            <p>
                Die Lehrkraft sieht im Cockpit Namen, Klasse, Bonuspunkte, den aktiven Baustein und das
                aktive Quiz. Über einen Link mit der Schüler-ID kann sie den Verlauf passiv mitlesen und
                dort auch Nachrichten versenden. Die Schüler-ID ist die Zugangskennung; sie wird
                ausschließlich an die betreffende Person bzw. deren Erziehungsberechtigte weitergegeben.
            </p>

            <h2>9. Benachrichtigungen über Telegram</h2>
            <p>
                Bei bestimmten Ereignissen (Übung bzw. Quiz gestartet, Ziel erreicht) sendet das System
                eine Nachricht an einen Telegram-Chat der Lehrkraft. Übermittelt werden dabei Vorname,
                Anfangsbuchstabe des Nachnamens, Schüler-ID, Baustein, Stufe, Bonuspunkte und ein Link
                zur Mitlese-Ansicht. Der vollständige Nachname wird nicht übermittelt und in der App
                auch nicht angezeigt.
                Telegram wird von der Telegram Messenger LLP betrieben; eine Verarbeitung außerhalb der
                EU ist möglich. <span class="recht-stand">[…] Rechtsgrundlage und Garantien prüfen.</span>
            </p>

            <h2>10. SMS und Sprachanruf über seven.io</h2>
            <p>
                Auf Anstoß der Lehrkraft versenden wir Flash-SMS mit dem Chat-Link oder lösen einen
                Sprachanruf mit einer kurzen Ansage aus. Dabei werden die Telefonnummer der Schülerin
                bzw. des Schülers, der Vorname für die Ansage und der Link übermittelt. Der Dienst wird
                von der seven communications GmbH &amp; Co. KG betrieben.
                <span class="recht-stand">[…] Auftragsverarbeitung prüfen.</span>
            </p>
        </div>

        <div class="recht-karte">
            <h2>11. Cookies und lokale Speicherung</h2>
            <ul>
                <li><strong>Session-Cookie <code>ms_session</code></strong> – technisch notwendig für die
                    Anmeldung der Lehrkraft und den Formularschutz; gültig bis zum Schließen des Browsers
                    bzw. bis zur Abmeldung.</li>
                <li><strong><code>localStorage</code></strong> – nur die Einstellung „Ton an/aus"
                    (<code>matheschaf_quiz_ton</code>); keine Kennungen, keine Verlaufsdaten.</li>
                <li><strong>Kein</strong> Einwilligungsbanner nötig, da ausschließlich technisch notwendige
                    Speicherung erfolgt. <span class="recht-stand">[…] rechtlich bestätigen lassen.</span></li>
            </ul>

            <h2>12. Hosting und Server-Logfiles</h2>
            <p>
                Die Website wird bei einem externen Hosting-Anbieter betrieben
                <span class="recht-stand">[Name und Serverstandort ergänzen]</span>. Der Anbieter
                verarbeitet in unserem Auftrag technische Zugriffsdaten (IP-Adresse, Zeitpunkt, aufgerufene
                Datei, übertragene Datenmenge, User-Agent, Statuscode). Diese Daten nutzen wir zur
                Betriebssicherheit und Fehlersuche; sie werden nicht mit anderen Daten zusammengeführt.
                <span class="recht-stand">[…] Auftragsverarbeitungsvertrag prüfen, Speicherdauer der Logs
                ergänzen (üblich 7–14 Tage).</span>
            </p>

            <h2>13. Eingebundene Fremdinhalte</h2>
            <p>
                Für die Formeleingabe lädt der Chat die Bibliothek <strong>MathLive</strong> über das
                Content-Delivery-Netzwerk <strong>jsDelivr</strong> (ausgeliefert über Cloudflare). Dabei
                wird die IP-Adresse des Geräts an den Anbieter übermittelt. Eine Verarbeitung außerhalb der
                EU ist möglich. <span class="recht-stand">[…] Alternative: Bibliothek lokal einbinden –
                dann entfällt die Übermittlung.</span>
            </p>

            <h2>14. Empfänger der Daten</h2>
            <table class="recht-tabelle">
                <tr><th>Empfänger</th><th>Daten</th><th>Zweck</th></tr>
                <tr><td>Hosting-Anbieter <span class="recht-stand">[Name]</span></td><td>alle auf dem Server gespeicherten Daten, Logfiles</td><td>technischer Betrieb</td></tr>
                <tr><td>Google (Gemini API)</td><td>Chat- und Quiztexte, Verlauf, Vorname, Stufe, ggf. Referenzbild</td><td>Erzeugen der Antworten</td></tr>
                <tr><td>seven.io</td><td>Telefonnummer, Vorname, Link</td><td>Flash-SMS, Sprachanruf</td></tr>
                <tr><td>Telegram (optional, Standard aus)</td><td>Vorname, Anfangsbuchstabe des Nachnamens, ID, Baustein, Stufe, Bonuspunkte, Mitlese-Link</td><td>Benachrichtigung der Lehrkraft – nur mit Einwilligung</td></tr>
                <tr><td>jsDelivr / Cloudflare</td><td>IP-Adresse, User-Agent</td><td>Auslieferung der Formel-Bibliothek</td></tr>
            </table>
            <p>Eine Weitergabe zu Werbezwecken findet nicht statt.</p>

            <h2>15. Speicherdauer und Löschung</h2>
            <ul>
                <li>Stammdaten und Verläufe bleiben in der Datenbank gespeichert, bis die Lehrkraft sie
                    löscht oder die Einwilligung widerrufen wird.</li>
                <li>Der Beginn einer neuen Übung leert den Verlauf dieses Bereichs (Lernchat bzw. Quiz);
                    ältere Beiträge werden dabei bewusst verworfen. Eine automatische Kürzung auf eine
                    feste Anzahl findet nicht mehr statt.</li>
                <li>Einwilligungsangaben (Version, Datum, Art, Kanal-Ja/Nein) bleiben als Nachweis
                    gespeichert, auch wenn einzelne Kanäle später abgelehnt werden.</li>
                <li>Sicherungskopien werden vom Betreiber manuell als Datenbank-Dump angelegt und bei
                    Bedarf gelöscht; sie liegen nicht im öffentlich erreichbaren Bereich.</li>
                <li>Server-Logfiles werden nach Ablauf der beim Hoster eingestellten Frist gelöscht
                    <span class="recht-stand">[Frist ergänzen]</span>.</li>
            </ul>

            <h2>16. Ihre Rechte</h2>
            <p>Sie bzw. die Erziehungsberechtigten haben nach der DSGVO das Recht auf:</p>
            <ul>
                <li><strong>Auskunft</strong> über die verarbeiteten Daten (Art. 15),</li>
                <li><strong>Berichtigung</strong> unrichtiger Daten (Art. 16),</li>
                <li><strong>Löschung</strong> (Art. 17) und <strong>Einschränkung</strong> der Verarbeitung (Art. 18),</li>
                <li><strong>Datenübertragbarkeit</strong> (Art. 20),</li>
                <li><strong>Widerspruch</strong> gegen Verarbeitungen auf Grundlage berechtigter Interessen (Art. 21),</li>
                <li><strong>Widerruf</strong> einer erteilten Einwilligung mit Wirkung für die Zukunft (Art. 7 Abs. 3).</li>
            </ul>
            <p>
                Eine Anfrage genügt formlos per E-Mail an
                <a href="mailto:miro@matheschaf.de">miro@matheschaf.de</a>.
            </p>

            <h2>17. Beschwerderecht bei der Aufsichtsbehörde</h2>
            <p>
                Unabhängig von anderen Rechtsbehelfen steht Ihnen das Recht zu, sich bei einer
                Datenschutz-Aufsichtsbehörde zu beschweren (Art. 77 DSGVO). Zuständig ist:
            </p>
            <p>
                Landesbeauftragte für den Datenschutz Sachsen-Anhalt<br>
                Leiterstraße 9, 39104 Magdeburg<br>
                <a href="https://datenschutz.sachsen-anhalt.de/" target="_blank" rel="noopener">datenschutz.sachsen-anhalt.de</a>
            </p>

            <h2>18. Datensicherheit</h2>
            <p>
                Die Übertragung erfolgt verschlüsselt (HTTPS). Schüler-, Baustein- und Verlaufsdaten
                liegen in einer MariaDB-Datenbank; Zugangsdaten stehen in gesperrten Konfigurationsdateien
                außerhalb des öffentlichen Abrufs. Alle Verwaltungsseiten sind durch eine Anmeldung
                geschützt; Schüler erreichen ihren Chat nur über den Link mit ihrer Kennung.
                Der Verlauf ist für die Lehrkraft zusätzlich in der Mitlese-Ansicht sichtbar.
                Bitte behandeln Sie Chat- und Mitlese-Links vertraulich.
            </p>
            <p class="recht-hinweis">
                <strong>Offener Punkt für die Anwaltskanzlei:</strong> Auftragsverarbeitung mit dem
                Hosting-Anbieter und die Übermittlung an den KI-Dienst sind vertraglich zu prüfen;
                Einwilligungstexte vor der Verteilung juristisch gegenlesen lassen.
            </p>

            <h2>19. Änderungen dieser Erklärung</h2>
            <p>
                Wir passen diese Datenschutzerklärung an, wenn sich Funktionen, Dienstleister oder
                rechtliche Anforderungen ändern. Es gilt die jeweils hier veröffentlichte Fassung.
            </p>

            <p class="recht-stand">Stand: 11. November 2026 · Fassung Neubau (lernchat)</p>
        </div>

        <div class="recht-knoepfe">
            <button type="button" class="btn" onclick="zurueck()">Zurück</button>
            <a class="btn btn--secondary" href="impressum.php">Impressum</a>
            <a class="btn btn--secondary" href="hilfe.php">Hilfe</a>
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




