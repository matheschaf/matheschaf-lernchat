<?php

declare(strict_types=1);

/**
 * impressum.php – Anbieterkennzeichnung.
 *
 * ENTWURF: inhaltlich auf dem Stand vom 11.11.2026. Die abschließende
 * rechtliche Prüfung erfolgt durch eine Anwaltskanzlei.
 */

require __DIR__ . '/inc/config.php';
require __DIR__ . '/inc/chat.php';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Impressum · Matheschaf</title>
    <link rel="stylesheet" href="<?= asset_version('assets/admin2.css') ?>">
    <link rel="stylesheet" href="<?= asset_version('assets/recht.css') ?>">
    <?= app_icon_tags('starter.php') ?>
</head>
<body>
    <header class="topbar">
        <div class="topbar-inner">
            <img class="brand-logo" src="schaf1.png" alt="" width="44" height="44">
            <div class="brand-text">
                <h1>Impressum</h1>
                <p>Angaben gemäß § 5 DDG</p>
            </div>
            <a class="topbar-link" href="starter.php">Lobby</a>
        </div>
    </header>

    <div class="wrap recht-wrap">
        <p class="recht-hinweis">
            <strong>Entwurf.</strong> Dieser Text ist auf dem Stand des Systems vom 11.11.2026.
            Die abschließende rechtliche Prüfung steht noch aus und erfolgt durch eine Anwaltskanzlei.
        </p>

        <div class="recht-karte">
            <h2>Angaben gemäß § 5 DDG</h2>
            <p>
                Michael Romahn<br>
                Sollnitzer Str. 1<br>
                D-06785 Oranienbaum-Wörlitz<br>
                Deutschland
            </p>

            <h2>Kontakt</h2>
            <p>
                Telefon: <a href="tel:+4915773301560">01577 3301560</a><br>
                E-Mail: <a href="mailto:miro@matheschaf.de">miro@matheschaf.de</a>
            </p>

            <h2>Verantwortlich für den Inhalt nach § 18 Abs. 2 MStV</h2>
            <p>
                Michael Romahn<br>
                Sollnitzer Str. 1<br>
                D-06785 Oranienbaum-Wörlitz
            </p>

            <h2>Angebot und Zweck</h2>
            <p>
                Das Matheschaf ist ein nicht-kommerzielles Lernangebot für den Mathematikunterricht:
                ein Schüler-Chat mit einem KI-Lernbegleiter, ein Quiz im Stil einer Ratesendung, ein
                Lehrer-Cockpit zur Begleitung sowie ein Werkzeug zum Erstellen eigener Aufgaben
                (Bausteine). Es werden keine Waren oder Dienstleistungen verkauft und keine Werbung
                ausgeliefert.
            </p>

            <h2>Verbraucherstreitbeilegung / Universalschlichtungsstelle</h2>
            <p>
                Wir sind nicht bereit und nicht verpflichtet, an Streitbeilegungsverfahren vor einer
                Verbraucherschlichtungsstelle teilzunehmen (§ 36 VSBG). Die Europäische
                Online-Streitbeilegungs-Plattform (OS-Plattform) wurde zum 20. Juli 2025 eingestellt;
                ein entsprechender Verweis entfällt daher.
            </p>
        </div>

        <div class="recht-karte">
            <h2>Haftung für Inhalte</h2>
            <p>
                Die Inhalte dieser Seiten wurden mit größter Sorgfalt erstellt. Für die Richtigkeit,
                Vollständigkeit und Aktualität der Inhalte können wir jedoch keine Gewähr übernehmen.
                Als Diensteanbieter sind wir gemäß § 7 Abs. 1 DDG für eigene Inhalte auf diesen Seiten
                nach den allgemeinen Gesetzen verantwortlich. Nach §§ 8 bis 10 DDG sind wir als
                Diensteanbieter jedoch nicht verpflichtet, übermittelte oder gespeicherte fremde
                Informationen zu überwachen oder nach Umständen zu forschen, die auf eine rechtswidrige
                Tätigkeit hinweisen. Verpflichtungen zur Entfernung oder Sperrung der Nutzung von
                Informationen nach den allgemeinen Gesetzen bleiben hiervon unberührt.
            </p>

            <h2>Hinweis zu KI-generierten Inhalten</h2>
            <p>
                Die Antworten des Lernbegleiters und die Fragen des Quiz werden automatisch durch ein
                Sprachmodell erzeugt (Einzelheiten unter <a href="datenschutz.php">Datenschutz</a>).
                Sie sind <strong>keine geprüfte fachliche Auskunft</strong> und können falsch,
                unvollständig oder unpassend sein. Verbindlich ist nur, was die Lehrkraft bestätigt.
                Die KI gibt grundsätzlich keine Lösungen vor, sondern führt über Rückfragen zum
                Lösungsweg.
            </p>

            <h2>Haftung für Links</h2>
            <p>
                Unser Angebot enthält Links zu externen Websites Dritter, auf deren Inhalte wir keinen
                Einfluss haben. Deshalb können wir für diese fremden Inhalte auch keine Gewähr
                übernehmen. Für die Inhalte der verlinkten Seiten ist stets der jeweilige Anbieter oder
                Betreiber der Seiten verantwortlich. Die verlinkten Seiten wurden zum Zeitpunkt der
                Verlinkung auf mögliche Rechtsverstöße überprüft. Rechtswidrige Inhalte waren zum
                Zeitpunkt der Verlinkung nicht erkennbar. Eine permanente inhaltliche Kontrolle der
                verlinkten Seiten ist jedoch ohne konkrete Anhaltspunkte einer Rechtsverletzung nicht
                zumutbar. Bei Bekanntwerden von Rechtsverletzungen werden wir derartige Links umgehend
                entfernen.
            </p>

            <h2>Urheberrecht</h2>
            <p>
                Die durch die Seitenbetreiber erstellten Inhalte und Werke auf diesen Seiten unterliegen
                dem deutschen Urheberrecht. Die Vervielfältigung, Bearbeitung, Verbreitung und jede Art
                der Verwertung außerhalb der Grenzen des Urheberrechtes bedürfen der schriftlichen
                Zustimmung des jeweiligen Autors bzw. Erstellers. Downloads und Kopien dieser Seite sind
                nur für den privaten, nicht kommerziellen Gebrauch gestattet. Soweit die Inhalte auf
                dieser Seite nicht vom Betreiber erstellt wurden, werden die Urheberrechte Dritter
                beachtet. Insbesondere werden Inhalte Dritter als solche gekennzeichnet. Sollten Sie
                trotzdem auf eine Urheberrechtsverletzung aufmerksam werden, bitten wir um einen
                entsprechenden Hinweis. Bei Bekanntwerden von Rechtsverletzungen werden wir derartige
                Inhalte umgehend entfernen.
            </p>

            <h2>Verwendete Bibliotheken und Grafiken</h2>
            <ul>
                <li><strong>MathLive</strong> (Formeleingabe und -darstellung) – MIT-Lizenz, ausgeliefert über jsDelivr.</li>
                <li><strong>Schaf-, Wiesen- und Symbolgrafiken</strong> – eigene Dateien des Projekts.</li>
                <li><strong>Emoji</strong> – Darstellung über die Schriftart des jeweiligen Geräts.</li>
            </ul>

            <p class="recht-stand">Stand: 11. November 2026 · Fassung Neubau (lernchat)</p>
        </div>

        <div class="recht-knoepfe">
            <button type="button" class="btn" onclick="zurueck()">Zurück</button>
            <a class="btn btn--secondary" href="datenschutz.php">Datenschutz</a>
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

