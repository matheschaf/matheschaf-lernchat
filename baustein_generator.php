<?php

declare(strict_types=1);

/**
 * baustein_generator.php — KI erzeugt einen Tutor-Prompt (E5).
 *
 * Erzeugt nur den Prompt-Text (kein JSON); gespeichert wird als Lern-Baustein
 * mit Titel/Thema/Prompt. Dialog/Aufgaben lassen sich danach im Editor ergänzen.
 */

require __DIR__ . '/inc/config.php';
require __DIR__ . '/inc/auth.php';
require __DIR__ . '/inc/chat.php';
require __DIR__ . '/inc/ki.php';
require __DIR__ . '/inc/verwaltung.php';

auth_starten();
verwaltung_erzwingen();

$titel    = trim((string) ($_POST['titel'] ?? ''));
$thema    = trim((string) ($_POST['thema'] ?? ''));
$klasse   = trim((string) ($_POST['klasse'] ?? ''));
$hinweise = trim((string) ($_POST['hinweise'] ?? ''));
$prompt   = (string) ($_POST['prompt'] ?? '');
$dialog   = trim((string) ($_POST['dialog'] ?? ''));
$meldung  = '';
$fehler   = [];

/** Je Stufe eine Referenzaufgabe (Text | Formel) – den Rest erfindet die KI. */
$aufgaben = ['leicht' => '', 'mittel' => '', 'schwer' => ''];

foreach (array_keys($aufgaben) as $stufe) {
    $aufgaben[$stufe] = trim((string) ($_POST['aufgaben_' . $stufe] ?? ''));
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_pruefen($_POST['csrf'] ?? null)) {
        $fehler[] = 'Ungültiges Token, bitte Seite neu laden.';
    } else {
        $aktion = (string) ($_POST['aktion'] ?? '');

        if ($aktion === 'erzeugen') {
            if ($thema === '') {
                $fehler[] = 'Bitte ein Thema eingeben.';
            } else {
                /* Derselbe Weg wie im Dialog-Ablauf: Musterdialog, Klasse,
                   Referenzaufgaben, Formel-Regel und Ziel-Regel sind damit drin. */
                $erzeugt = ki_prompt_erzeugen([
                    'art'             => 'lern',
                    'titel'           => $titel,
                    'thema'           => $thema,
                    'klasse'          => $klasse,
                    'hinweise'        => $hinweise,
                    'dialog'          => dialog_huebsch($dialog),
                    'aufgaben_leicht' => $aufgaben['leicht'],
                    'aufgaben_mittel' => $aufgaben['mittel'],
                    'aufgaben_schwer' => $aufgaben['schwer'],
                    'fragen'          => '',
                    'quiz_thema'      => '',
                    'quiz_anzahl'     => 5,
                ]);

                if ($erzeugt === null || trim($erzeugt) === '') {
                    $fehler[] = 'Die KI hat nicht geantwortet. Bitte noch einmal versuchen.';
                } else {
                    $prompt  = $erzeugt;
                    $meldung = 'Prompt erzeugt – prüfen, dann speichern.';
                }
            }
        } elseif ($aktion === 'speichern') {
            $daten = [
                'slug'        => (string) ($_POST['slug'] ?? ''),
                'art'         => 'lern',
                'titel'       => $titel !== '' ? $titel : $thema,
                'thema'       => $klasse !== '' ? $thema . ' (Klasse ' . $klasse . ')' : $thema,
                'prompt'      => $prompt,
                'quiz_thema'  => '',
                'quiz_anzahl' => '',
                'dialog'      => $dialog,
                'aufgaben_leicht' => $aufgaben['leicht'],
                'aufgaben_mittel' => $aufgaben['mittel'],
                'aufgaben_schwer' => $aufgaben['schwer'],
                'fragen'      => '',
            ];

            $ergebnis = baustein_speichern($daten, true);
            if ($ergebnis['fehler'] === []) {
                header('Location: baustein_edit.php?slug=' . rawurlencode($ergebnis['slug']), true, 302);
                exit;
            }
            $fehler = $ergebnis['fehler'];
        }
    }
}

verwaltung_kopf('Prompt-Generator', 'bausteine.php');

if ($meldung !== '') {
    echo '<p class="form-notice">' . e($meldung) . '</p>';
}
if ($fehler !== []) {
    echo '<p class="form-error">' . e(implode(' ', $fehler)) . '</p>';
}
?>
<p class="hinweis">
    Die KI formuliert den System-Prompt (ohne Lösung, mit Ziel-Regel).
    Prüfen, anpassen, dann speichern – Dialog/Aufgaben danach im Editor.
</p>

<form class="form" method="post" action="baustein_generator.php" id="generatorForm">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

    <div class="field">
        <label for="titel">Titel (optional)</label>
        <input type="text" id="titel" name="titel" maxlength="200" value="<?= e($titel) ?>">
    </div>

    <div class="field">
        <label for="thema">Thema / Aufgabe</label>
        <input type="text" id="thema" name="thema" maxlength="255" required
               value="<?= e($thema) ?>" placeholder="z. B. Addieren ganzer Zahlen">
    </div>

    <div class="field">
        <label for="hinweise">Zusätzliche Hinweise (optional)</label>
        <textarea id="hinweise" name="hinweise" style="min-height:70px"><?= e($hinweise) ?></textarea>
    </div>

    <div class="field">
        <label for="klasse">Klasse / Stufe (optional)</label>
        <input type="text" id="klasse" name="klasse" maxlength="60" value="<?= e($klasse) ?>"
               placeholder="z. B. 6 oder 7/8">
    </div>

    <p class="hinweis">
        <strong>Je eine Referenzaufgabe pro Stufe</strong> – Format <code>Aufgabe | Formel</code>.
        Die KI nimmt sie als Niveau und erfindet die übrigen Aufgaben selbst.
        Komfortabler geht das mit dem Recorder im
        <a href="dialog_baustein.php">Dialog-Baustein</a>.
    </p>

    <div class="field">
        <label for="aufgaben_leicht">Aufgabe(n) leicht</label>
        <textarea id="aufgaben_leicht" name="aufgaben_leicht" style="min-height:60px"
                  placeholder="1/4 + 2/4 = ? | \frac{1}{4} + \frac{2}{4}"><?= e($aufgaben['leicht']) ?></textarea>
    </div>

    <div class="field">
        <label for="aufgaben_mittel">Aufgabe(n) mittel</label>
        <textarea id="aufgaben_mittel" name="aufgaben_mittel" style="min-height:60px"
                  placeholder="1/3 + 1/4 = ? | \frac{1}{3} + \frac{1}{4}"><?= e($aufgaben['mittel']) ?></textarea>
    </div>

    <div class="field">
        <label for="aufgaben_schwer">Aufgabe(n) schwer</label>
        <textarea id="aufgaben_schwer" name="aufgaben_schwer" style="min-height:60px"
                  placeholder="2/3 · 3/5 = ? | \frac{2}{3} \cdot \frac{3}{5}"><?= e($aufgaben['schwer']) ?></textarea>
    </div>

    <div class="form-actions">
        <button type="submit" name="aktion" value="erzeugen" class="btn">✨ Prompt erzeugen</button>
    </div>

    <div class="field">
        <label for="prompt">Prompt (bearbeitbar)</label>
        <textarea id="prompt" name="prompt" style="min-height:320px"><?= e($prompt) ?></textarea>
    </div>

    <div class="field">
        <label for="slug">Slug (leer = aus Titel)</label>
        <input type="text" id="slug" name="slug" maxlength="100" placeholder="z. B. ganze-zahlen">
    </div>

    <div class="field">
        <label for="dialog">Beispieldialog (optional, rolle | text | formel | bild)</label>
        <textarea id="dialog" name="dialog" style="min-height:90px"></textarea>
    </div>

    <div class="form-actions">
        <button type="submit" name="aktion" value="speichern" class="btn">Als Lern-Baustein speichern</button>
        <a class="btn btn--secondary" href="bausteine.php">Abbrechen</a>
    </div>
</form>

<div class="ki-spinner" id="ki-spinner" data-form="#generatorForm" hidden role="status" aria-live="polite">
    <div class="ki-spinner__kreis" aria-hidden="true"></div>
    <p class="ki-spinner__text">Die KI schreibt den Prompt – das dauert meist 10–20 Sekunden …</p>
</div>
<?php
verwaltung_fuss();
