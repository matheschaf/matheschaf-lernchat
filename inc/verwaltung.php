<?php

declare(strict_types=1);

/**
 * Verwaltung (E5): Speichern von Schülern, Einwilligungen, Bausteinen,
 * Zuordnungen – alles über die ms_*-Tabellen.
 *
 * Einbinden nach inc/config.php (inc/chat.php wird für Helfer benötigt).
 */

require_once __DIR__ . '/chat.php';

/**
 * Nächste freie vierstellige Kennung. Zuerst 2300–2999, dann der Rest.
 */
function naechste_kennung(): ?string
{
    $belegt = array_map(
        'strval',
        db()->query('SELECT id_code FROM ms_schueler')->fetchAll(PDO::FETCH_COLUMN)
    );

    return kennung_frei($belegt);
}

/**
 * Erste freie Kennung aus einer Liste belegter Kennungen (ohne DB-Abfrage).
 *
 * @param list<string> $belegt
 */
function kennung_frei(array $belegt): ?string
{
    $belegt = array_map('strval', $belegt);

    foreach ([range(2300, 2999), range(1000, 2299), range(3000, 9998)] as $bereich) {
        foreach ($bereich as $nummer) {
            $kennung = (string) $nummer;
            if (!in_array($kennung, $belegt, true)) {
                return $kennung;
            }
        }
    }

    return null;
}

/**
 * Rohdaten aller Schüler für den JSON-Export (`schueler.json`).
 *
 * Enthält bewusst den VOLLEN Namen – das ist der Datenstand bzw. die
 * Sicherung. In der Oberfläche erscheint nur „Vorname N." (schueler_name()).
 *
 * @return array{stand: string, anzahl: int, schueler: list<array<string, mixed>>}
 */
function schueler_json_daten(bool $mitTelefon = false): array
{
    $schueler = [];

    foreach (schueler_liste() as $s) {
        $zeile = [
            'id_code'              => (string) ($s['id_code'] ?? ''),
            'vorname'              => (string) ($s['vorname'] ?? ''),
            'nachname'             => (string) ($s['nachname'] ?? ''),
            'klasse'               => (string) ($s['klasse'] ?? ''),
            'bonuspunkte'          => (int) ($s['bonuspunkte'] ?? 0),
            'aktiver_baustein'     => (string) ($s['aktiver_baustein'] ?? ''),
            'quiz_baustein'        => (string) ($s['quiz_baustein'] ?? ''),
            'stufe'                => (string) ($s['stufe'] ?? ''),
            'ok_sms'               => (int) ($s['ok_sms'] ?? 0),
            'ok_anruf'             => (int) ($s['ok_anruf'] ?? 0),
            'ok_quiz'              => (int) ($s['ok_quiz'] ?? 0),
            'ok_telegram'          => (int) ($s['ok_telegram'] ?? 0),
            'einwilligung_version' => (string) ($s['einwilligung_version'] ?? ''),
            'einwilligung_am'      => (string) ($s['einwilligung_am'] ?? ''),
            'einwilligung_art'     => (string) ($s['einwilligung_art'] ?? ''),
        ];

        if ($mitTelefon) {
            $zeile['telefon'] = (string) ($s['telefon'] ?? '');
        }

        $schueler[] = $zeile;
    }

    return [
        'stand'    => date('c'),
        'anzahl'   => count($schueler),
        'schueler' => $schueler,
    ];
}

/**
 * Dialogzeilen „rolle | text | formel | bild" in lesbaren Text für die KI.
 */
function dialog_huebsch(string $zeilen): string
{
    $aus = [];

    foreach (preg_split('/\R/', $zeilen) ?: [] as $zeile) {
        $teile = array_map('trim', explode('|', $zeile));

        if (count($teile) < 2) {
            continue;
        }

        $rolle  = in_array($teile[0] ?? '', ['schueler', 'schüler'], true) ? 'Schüler' : 'Lehrer';
        $text   = (string) ($teile[1] ?? '');
        $formel = (string) ($teile[2] ?? '');
        $bild   = (string) ($teile[3] ?? '');

        $stueck = $rolle . ': ' . $text;

        if ($formel !== '') {
            $stueck .= ' [Formel: ' . $formel . ']';
        }

        if ($bild !== '') {
            $stueck .= ' [Bild: ' . $bild . ']';
        }

        $aus[] = $stueck;
    }

    return implode("\n", $aus);
}

/**
 * Musterdialog eines Bausteins als Textzeilen (für Recorder/„übernehmen").
 *
 * @param array<string, mixed>|null $baustein
 */
function dialog_aus_baustein(?array $baustein): string
{
    if ($baustein === null) {
        return '';
    }

    $zeilen = [];

    foreach ($baustein['dialog'] ?? [] as $z) {
        $saeubern = static fn(string $s): string => trim(str_replace(["\r\n", "\n", "\r", '|'], ' ', $s));
        $zeilen[] = implode(' | ', [
            (string) ($z['rolle'] ?? 'lehrer'),
            $saeubern((string) ($z['text'] ?? '')),
            $saeubern((string) ($z['formel'] ?? '')),
            $saeubern((string) ($z['bild'] ?? '')),
        ]);
    }

    return implode("\n", $zeilen);
}

/**
 * Löscht einen Baustein samt Kindern.
 *
 * ms_baustein_dialog, ms_baustein_aufgaben, ms_quiz_fragen und
 * ms_schueler_bausteine hängen per ON DELETE CASCADE dran. Zusätzlich werden
 * die Zuordnungen in ms_schueler (aktiver_baustein / quiz_baustein) geleert,
 * damit kein Kind auf einen toten Slug zeigt.
 *
 * @return array{geloescht: bool, schueler: int, fehler: list<string>}
 */
function baustein_loeschen(string $slug): array
{
    $slug = trim($slug);

    if ($slug === '' || baustein_laden($slug) === null) {
        return ['geloescht' => false, 'schueler' => 0, 'fehler' => ['Diesen Baustein gibt es nicht.']];
    }

    $pdo = db();
    $pdo->beginTransaction();

    try {
        $zuordnung = $pdo->prepare(
            'UPDATE ms_schueler
                SET aktiver_baustein = NULLIF(aktiver_baustein, :slug1),
                    quiz_baustein    = NULLIF(quiz_baustein, :slug2)
              WHERE aktiver_baustein = :slug3 OR quiz_baustein = :slug4'
        );
        $zuordnung->execute([':slug1' => $slug, ':slug2' => $slug, ':slug3' => $slug, ':slug4' => $slug]);
        $betroffen = $zuordnung->rowCount();

        $pdo->prepare('DELETE FROM ms_bausteine WHERE slug = :slug')->execute([':slug' => $slug]);

        $pdo->commit();

        return ['geloescht' => true, 'schueler' => $betroffen, 'fehler' => []];
    } catch (Throwable $fehler) {
        $pdo->rollBack();

        return ['geloescht' => false, 'schueler' => 0, 'fehler' => ['Löschen fehlgeschlagen: ' . $fehler->getMessage()]];
    }
}

/**
 * Legt einen Schüler an oder ändert ihn (id_code bleibt unverändert).
 *
 * @param array<string, mixed> $daten  vorname, nachname, klasse, telefon,
 *                                     bonuspunkte, stufe sowie Einwilligung
 * @param bool                 $istNeu
 * @return list<string> Fehlermeldungen (leer = gespeichert)
 */
function schueler_speichern(array $daten, bool $istNeu): array
{
    $kennung = (string) ($daten['id_code'] ?? '');
    $fehler  = [];

    if (!preg_match('/^\d{4}$/', $kennung)) {
        $fehler[] = 'Die Kennung muss aus genau vier Ziffern bestehen.';
    }

    if ($istNeu && !$fehler && schueler_laden($kennung) !== null) {
        $fehler[] = 'Diese Kennung ist bereits vergeben.';
    }

    $stufe = (string) ($daten['stufe'] ?? 'leicht');
    if (!in_array($stufe, ['leicht', 'mittel', 'schwer', 'zufall'], true)) {
        $stufe = 'leicht';
    }

    $art = (string) ($daten['einwilligung_art'] ?? '');
    if (!in_array($art, ['mail', 'papier'], true)) {
        $art = '';
    }

    $am = trim((string) ($daten['einwilligung_am'] ?? ''));
    if ($am !== '') {
        $am = str_replace('T', ' ', $am);
        if (strlen($am) === 16) {
            $am .= ':00';
        }
    } else {
        $am = '';
    }

    if ($fehler) {
        return $fehler;
    }

    $werte = [
        ':id'          => $kennung,
        ':vorname'     => trim((string) ($daten['vorname'] ?? '')),
        ':nachname'    => trim((string) ($daten['nachname'] ?? '')),
        ':klasse'      => trim((string) ($daten['klasse'] ?? '')) ?: null,
        ':telefon'     => trim((string) ($daten['telefon'] ?? '')) ?: null,
        ':bonus'       => max(0, (int) ($daten['bonuspunkte'] ?? 0)),
        ':stufe'       => $stufe,
        ':version'     => trim((string) ($daten['einwilligung_version'] ?? '')) ?: null,
        ':am'          => $am !== '' ? $am : null,
        ':art'         => $art !== '' ? $art : null,
        ':ok_sms'      => !empty($daten['ok_sms']) ? 1 : 0,
        ':ok_anruf'    => !empty($daten['ok_anruf']) ? 1 : 0,
        ':ok_quiz'     => !empty($daten['ok_quiz']) ? 1 : 0,
        ':ok_telegram' => !empty($daten['ok_telegram']) ? 1 : 0,
    ];

    $pdo = db();

    if ($istNeu) {
        $einfuegen = $pdo->prepare(
            'INSERT INTO ms_schueler
                (id_code, vorname, nachname, klasse, telefon, bonuspunkte, stufe,
                 einwilligung_version, einwilligung_am, einwilligung_art,
                 ok_sms, ok_anruf, ok_quiz, ok_telegram)
             VALUES
                (:id, :vorname, :nachname, :klasse, :telefon, :bonus, :stufe,
                 :version, :am, :art, :ok_sms, :ok_anruf, :ok_quiz, :ok_telegram)'
        );
        $einfuegen->execute($werte);

        return [];
    }

    $aendern = $pdo->prepare(
        'UPDATE ms_schueler SET
            vorname = :vorname, nachname = :nachname, klasse = :klasse,
            telefon = :telefon, bonuspunkte = :bonus, stufe = :stufe,
            einwilligung_version = :version, einwilligung_am = :am,
            einwilligung_art = :art, ok_sms = :ok_sms, ok_anruf = :ok_anruf,
            ok_quiz = :ok_quiz, ok_telegram = :ok_telegram
          WHERE id_code = :id'
    );
    $aendern->execute($werte);

    return [];
}

/**
 * Zerlegt ein Textfeld in Zeilen und trennt jede Zeile an „|“.
 * Leere Zeilen und Zeilen mit „#“ am Anfang werden ignoriert.
 *
 * @return list<list<string>>
 */
function zeilen_parsen(string $text): array
{
    $zeilen = [];

    foreach (preg_split('/\R/u', $text) ?: [] as $zeile) {
        $zeile = trim($zeile);
        if ($zeile === '' || str_starts_with($zeile, '#')) {
            continue;
        }

        $zeilen[] = array_map('trim', explode('|', $zeile));
    }

    return $zeilen;
}

/** Datei-/URL-tauglicher Slug aus einem Titel. */
function verwaltung_slugify(string $text): string
{
    $text = mb_strtolower($text);
    $text = str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';

    return trim($text, '-');
}

/**
 * Legt einen Baustein an oder ändert ihn (slug bleibt beim Ändern fest).
 *
 * Textformate:
 *   Dialog:    rolle | text | formel | bild      (rolle = lehrer/schueler)
 *   Aufgaben:  text | formel                     (je Stufe ein Feld)
 *   Fragen:    frage | antwort
 *
 * @param array<string, mixed> $daten
 * @return array{fehler: list<string>, slug: string}
 */
function baustein_speichern(array $daten, bool $istNeu): array
{
    $fehler = [];

    $titel = trim((string) ($daten['titel'] ?? ''));
    $slug  = trim((string) preg_replace('/[^a-z0-9-]+/', '-', mb_strtolower(trim((string) ($daten['slug'] ?? '')))), '-');

    if ($titel === '') {
        $fehler[] = 'Bitte einen Titel eingeben.';
    }
    if ($slug === '') {
        $slug = verwaltung_slugify($titel);
    }
    if ($slug === '') {
        $fehler[] = 'Bitte einen Slug eingeben (a–z, 0–9, -).';
    }
    if ($istNeu && !$fehler && baustein_laden($slug) !== null) {
        $fehler[] = 'Diesen Slug gibt es bereits.';
    }

    $art = (string) ($daten['art'] ?? 'lern');
    if (!in_array($art, ['lern', 'quiz'], true)) {
        $art = 'lern';
    }

    $quizAnzahl = null;
    if ($art === 'quiz') {
        $quizAnzahl = max(1, min(20, (int) ($daten['quiz_anzahl'] ?? 5)));
    }

    if ($fehler) {
        return ['fehler' => $fehler, 'slug' => $slug];
    }

    $pdo = db();
    $pdo->beginTransaction();

    try {
        $werte = [
            ':slug'        => $slug,
            ':art'         => $art,
            ':titel'       => $titel,
            ':thema'       => trim((string) ($daten['thema'] ?? '')) ?: null,
            ':prompt'      => (string) ($daten['prompt'] ?? ''),
            ':quiz_thema'  => $art === 'quiz' ? (trim((string) ($daten['quiz_thema'] ?? '')) ?: null) : null,
            ':quiz_anzahl' => $quizAnzahl,
        ];

        if ($istNeu) {
            $pdo->prepare(
                'INSERT INTO ms_bausteine (slug, art, titel, thema, prompt, quiz_thema, quiz_anzahl)
                 VALUES (:slug, :art, :titel, :thema, :prompt, :quiz_thema, :quiz_anzahl)'
            )->execute($werte);
        } else {
            $pdo->prepare(
                'UPDATE ms_bausteine SET art = :art, titel = :titel, thema = :thema,
                        prompt = :prompt, quiz_thema = :quiz_thema, quiz_anzahl = :quiz_anzahl
                  WHERE slug = :slug'
            )->execute($werte);
        }

        // Kinder immer neu schreiben (Reihenfolge = Zeilenreihenfolge).
        $pdo->prepare('DELETE FROM ms_baustein_dialog WHERE slug = :slug')->execute([':slug' => $slug]);
        $pdo->prepare('DELETE FROM ms_baustein_aufgaben WHERE slug = :slug')->execute([':slug' => $slug]);
        $pdo->prepare('DELETE FROM ms_quiz_fragen WHERE slug = :slug')->execute([':slug' => $slug]);

        $dialog = $pdo->prepare(
            'INSERT INTO ms_baustein_dialog (slug, position, rolle, text, formel, bild)
             VALUES (:slug, :position, :rolle, :text, :formel, :bild)'
        );
        foreach (zeilen_parsen((string) ($daten['dialog'] ?? '')) as $position => $teile) {
            $rolle = $teile[0] ?? 'lehrer';
            if (!in_array($rolle, ['lehrer', 'schueler'], true)) {
                $rolle = 'lehrer';
            }
            $dialog->execute([
                ':slug'     => $slug,
                ':position' => $position,
                ':rolle'    => $rolle,
                ':text'     => $teile[1] ?? '',
                ':formel'   => $teile[2] ?? '',
                ':bild'     => $teile[3] ?? '',
            ]);
        }
        $aufgabe = $pdo->prepare(
            'INSERT INTO ms_baustein_aufgaben (slug, stufe, position, text, formel)
             VALUES (:slug, :stufe, :position, :text, :formel)'
        );
        foreach (['leicht', 'mittel', 'schwer'] as $stufe) {
            $feld = 'aufgaben_' . $stufe;
            foreach (zeilen_parsen((string) ($daten[$feld] ?? '')) as $position => $teile) {
                $aufgabe->execute([
                    ':slug'     => $slug,
                    ':stufe'    => $stufe,
                    ':position' => $position,
                    ':text'     => $teile[0] ?? '',
                    ':formel'   => $teile[1] ?? '',
                ]);
            }
        }

        $frage = $pdo->prepare(
            'INSERT INTO ms_quiz_fragen (slug, position, frage, antwort)
             VALUES (:slug, :position, :frage, :antwort)'
        );
        foreach (zeilen_parsen((string) ($daten['fragen'] ?? '')) as $position => $teile) {
            $frage->execute([
                ':slug'     => $slug,
                ':position' => $position,
                ':frage'    => $teile[0] ?? '',
                ':antwort'  => $teile[1] ?? '',
            ]);
        }

        $pdo->commit();
    } catch (Throwable $ausnahme) {
        $pdo->rollBack();
        return ['fehler' => ['Speichern fehlgeschlagen: ' . $ausnahme->getMessage()], 'slug' => $slug];
    }

    return ['fehler' => [], 'slug' => $slug];
}

/**
 * Gemeinsamer Kopf für die Verwaltungsseiten (E5).
 * Setzt voraus, dass die Seite auth_starten() und verwaltung_erzwingen()
 * bereits ausgeführt hat.
 */
function verwaltung_kopf(string $titel, string $aktiv = '', string $zusatzKopf = ''): void
{
    $nav = [
        'starter.php'    => 'Start',
        'schueler.php'   => 'Schüler',
        'bausteine.php'  => 'Bausteine',
        'dialog_baustein.php' => 'Dialog-Baustein',
        'zuordnung.php'  => 'Zuordnung',
        'mitlesen.php'   => 'Mitlesen',
    ];

    echo '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">'
        . '<title>' . e($titel) . ' · ' . e(APP_NAME) . '</title>'
        . '<link rel="stylesheet" href="' . e(asset_version('assets/admin2.css')) . '">'
        . app_icon_tags('starter.php')
        . '<style>'
        . '.verwaltung-nav{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 14px}'
        . '.verwaltung-nav a{padding:7px 12px;border-radius:999px;background:#fff;text-decoration:none;'
        . 'color:#1b5e20;font-weight:600;box-shadow:0 1px 2px rgba(0,0,0,.08)}'
        . '.verwaltung-nav a[aria-current="page"]{background:#1b5e20;color:#fff}'
        . '.form textarea{min-height:90px;font-family:ui-monospace,Consolas,monospace;font-size:.9rem}'
        . '.hinweis{font-size:.9rem;color:#555}'
        . '</style>'
        . $zusatzKopf
        . '</head><body>'
        . '<header class="topbar"><div class="topbar-inner">'
        . '<img class="brand-logo" src="schaf1.png" alt="" width="44" height="44">'
        . '<div class="brand-text"><h1>' . e(APP_NAME) . '</h1><p>Verwaltung</p></div>'
        . '<a class="topbar-link" href="hilfe.php" title="Hilfe">?</a>'
        . '<a class="topbar-link" href="logout.php">Abmelden</a>'
        . '</div></header><div class="wrap">'
        . '<nav class="verwaltung-nav">';

    foreach ($nav as $ziel => $beschriftung) {
        printf(
            '<a href="%s"%s>%s</a>',
            e($ziel),
            $ziel === $aktiv ? ' aria-current="page"' : '',
            e($beschriftung)
        );
    }

    echo '</nav><h1 class="section-head"><span>' . e($titel) . '</span></h1>';
}

/** Schließt die Seite (Scripts werden von der Seite selbst gesetzt). */
function verwaltung_fuss(): void
{
    echo '</div></body></html>';
}

/**
 * Speichert die Einwilligung eines Schülers (öffentliche Seite, ohne Login).
 *
 * @param array<string, mixed> $daten ok_sms, ok_anruf, ok_quiz, ok_telegram,
 *                                    version, am, art
 * @return list<string> Fehlermeldungen
 */
function einwilligung_speichern(string $id, array $daten): array
{
    if (schueler_laden($id) === null) {
        return ['Schüler nicht gefunden.'];
    }

    $art = (string) ($daten['art'] ?? '');
    if (!in_array($art, ['mail', 'papier'], true)) {
        $art = '';
    }

    $am = trim((string) ($daten['am'] ?? ''));
    if ($am !== '') {
        $am = str_replace('T', ' ', $am);
        if (strlen($am) === 16) {
            $am .= ':00';
        }
    }

    $aendern = db()->prepare(
        'UPDATE ms_schueler SET
            einwilligung_version = :version, einwilligung_am = :am, einwilligung_art = :art,
            ok_sms = :ok_sms, ok_anruf = :ok_anruf, ok_quiz = :ok_quiz, ok_telegram = :ok_telegram
          WHERE id_code = :id'
    );
    $aendern->execute([
        ':version'     => trim((string) ($daten['version'] ?? '')) ?: null,
        ':am'          => $am !== '' ? $am : null,
        ':art'         => $art !== '' ? $art : null,
        ':ok_sms'      => !empty($daten['ok_sms']) ? 1 : 0,
        ':ok_anruf'    => !empty($daten['ok_anruf']) ? 1 : 0,
        ':ok_quiz'     => !empty($daten['ok_quiz']) ? 1 : 0,
        ':ok_telegram' => !empty($daten['ok_telegram']) ? 1 : 0,
        ':id'          => $id,
    ]);

    return [];
}

/**
 * Weist einem Schüler Lern- und Quiz-Baustein zu (leer = keiner).
 *
 * @return list<string> Fehlermeldungen
 */
function zuordnung_setzen(string $id, string $lernSlug, string $quizSlug): array
{
    $fehler = [];

    $lernSlug = trim($lernSlug);
    $quizSlug = trim($quizSlug);

    if ($lernSlug !== '' && baustein_laden($lernSlug) === null) {
        $fehler[] = 'Lern-Baustein „' . $lernSlug . '“ gibt es nicht.';
    }

    $quiz = $quizSlug !== '' ? baustein_laden($quizSlug) : null;
    if ($quizSlug !== '' && $quiz === null) {
        $fehler[] = 'Quiz-Baustein „' . $quizSlug . '“ gibt es nicht.';
    } elseif ($quiz !== null && ($quiz['art'] ?? '') !== 'quiz') {
        $fehler[] = '„' . $quizSlug . '“ ist kein Quiz-Baustein.';
    }

    if ($fehler) {
        return $fehler;
    }

    $aendern = db()->prepare(
        'UPDATE ms_schueler SET aktiver_baustein = :lern, quiz_baustein = :quiz WHERE id_code = :id'
    );
    $aendern->execute([
        ':lern' => $lernSlug !== '' ? $lernSlug : null,
        ':quiz' => $quizSlug !== '' ? $quizSlug : null,
        ':id'   => $id,
    ]);

    return [];
}

/**
 * Löscht einen Schüler samt Verlauf und Baustein-Status (E5b).
 *
 * Die Fremdschlüssel haben ON DELETE CASCADE; die abhängigen Zeilen werden
 * trotzdem ausdrücklich gelöscht, damit es auch dann sauber bleibt, wenn eine
 * Installation ohne Constraints eingespielt wurde.
 *
 * @return bool true = gelöscht, false = Schüler gab es nicht
 */
function schueler_loeschen(string $id): bool
{
    if (!preg_match('/^\d{4}$/', $id) || schueler_laden($id) === null) {
        return false;
    }

    $pdo = db();
    $pdo->beginTransaction();

    try {
        $pdo->prepare('DELETE FROM ms_nachrichten WHERE schueler_id = :id')
            ->execute([':id' => $id]);
        $pdo->prepare('DELETE FROM ms_schueler_bausteine WHERE schueler_id = :id')
            ->execute([':id' => $id]);
        $pdo->prepare('DELETE FROM ms_schueler WHERE id_code = :id')
            ->execute([':id' => $id]);

        $pdo->commit();
    } catch (Throwable $ausnahme) {
        $pdo->rollBack();
        throw $ausnahme;
    }

    return true;
}

/**
 * Kopiert einen Schüler unter der nächsten freien Kennung (E5b).
 * Übernommen werden Stammdaten, Zuordnung und Einwilligung – nicht der Verlauf
 * und nicht der Baustein-Status.
 *
 * @return string|null neue Kennung oder null (Schüler fehlt / keine freie Nummer)
 */
function schueler_duplizieren(string $id): ?string
{
    if (schueler_laden($id) === null) {
        return null;
    }

    $neu = naechste_kennung();
    if ($neu === null) {
        return null;
    }

    $einfuegen = db()->prepare(
        'INSERT INTO ms_schueler
            (id_code, vorname, nachname, klasse, telefon, bonuspunkte,
             aktiver_baustein, quiz_baustein, stufe,
             einwilligung_version, einwilligung_am, einwilligung_art,
             ok_sms, ok_anruf, ok_quiz, ok_telegram)
         SELECT :neu, vorname, nachname, klasse, telefon, bonuspunkte,
                aktiver_baustein, quiz_baustein, stufe,
                einwilligung_version, einwilligung_am, einwilligung_art,
                ok_sms, ok_anruf, ok_quiz, ok_telegram
           FROM ms_schueler WHERE id_code = :alt'
    );
    $einfuegen->execute([':neu' => $neu, ':alt' => $id]);

    return $neu;
}

/**
 * Leert den Verlauf eines Schülers (E5b).
 *
 * @param string $kontext chat, quiz oder beides
 * @return int Anzahl gelöschter Nachrichten
 */
function verlauf_leeren(string $id, string $kontext = 'beides'): int
{
    if (!preg_match('/^\d{4}$/', $id)) {
        return 0;
    }

    if (in_array($kontext, ['chat', 'quiz'], true)) {
        $loeschen = db()->prepare(
            'DELETE FROM ms_nachrichten WHERE schueler_id = :id AND kontext = :kontext'
        );
        $loeschen->execute([':id' => $id, ':kontext' => $kontext]);

        return $loeschen->rowCount();
    }

    $loeschen = db()->prepare('DELETE FROM ms_nachrichten WHERE schueler_id = :id');
    $loeschen->execute([':id' => $id]);

    return $loeschen->rowCount();
}
