<?php

declare(strict_types=1);

/**
 * E1 — Import des Altbestands (0711/backups/*.json) in die ms_*-Tabellen.
 *
 *   Bericht, ändert nichts:
 *     C:\xampp\php\php.exe import.php
 *   Import ausführen:
 *     C:\xampp\php\php.exe import.php --apply
 *   Andere Quelle:
 *     … --schueler=PFAD --bausteine=PFAD
 *
 *   Im Browser nur mit Einmal-Token:
 *     http://localhost/lernchat/import.php?token=…
 *     http://localhost/lernchat/import.php?token=…&apply=1
 *
 * Wiederholbar: Schüler und Bausteine per Upsert, Dialog/Aufgaben/Fragen und die
 * Nachrichten des jeweiligen Schülers werden vor dem Einspielen ersetzt.
 * Alles läuft in einer Transaktion – bei einem Fehler bleibt der alte Stand.
 * Die Quelldateien werden ausschließlich gelesen.
 */

require __DIR__ . '/inc/config.php';
require __DIR__ . '/inc/db.php';

// Altbestand liegt neben lernchat/ (lokal C:\xampp\htdocs\0711\backups).
const MS_ALT_BACKUPS = MS_ROOT . '/../0711/backups';

// Zielwerte aus NEU.md (Etappe E1). Nachrichten werden als Soll/Ist gemeldet,
// weil der Live-Snapshot älter sein kann als die Übergabe.
const MS_ERWARTET = [
    'schueler'    => 11,
    'bausteine'   => 11,
    'punkte'      => 119,
    'nachrichten' => 71,
];

/** @var array{apply: bool, schueler: string, bausteine: string} $optionen */
$optionen = ['apply' => false, 'schueler' => '', 'bausteine' => ''];
$istCli = PHP_SAPI === 'cli';

if ($istCli) {
    foreach (array_slice($argv ?? [], 1) as $argument) {
        if ($argument === '--apply') {
            $optionen['apply'] = true;
            continue;
        }
        foreach (['schueler', 'bausteine'] as $name) {
            if (str_starts_with($argument, '--' . $name . '=')) {
                $optionen[$name] = substr($argument, strlen($name) + 3);
            }
        }
    }
} else {
    header('Content-Type: text/plain; charset=utf-8');

    $token = (string) ($_GET['token'] ?? '');
    if (!defined('MS_MIGRATIONS_TOKEN')
        || MS_MIGRATIONS_TOKEN === ''
        || MS_MIGRATIONS_TOKEN === 'BITTE_AUSFUELLEN'
        || !hash_equals(MS_MIGRATIONS_TOKEN, $token)) {
        http_response_code(403);
        exit("403 — Token fehlt oder ist falsch (?token=…).\n");
    }

    $optionen['apply'] = ($_GET['apply'] ?? '') === '1';
    $optionen['schueler'] = (string) ($_GET['schueler'] ?? '');
    $optionen['bausteine'] = (string) ($_GET['bausteine'] ?? '');
}

/**
 * Wählt die maßgebliche Sicherung: bevorzugt „live“-Dateien, dann den
 * jüngsten Zeitstempel im Dateinamen (YYYYMMDD-HHMM[SS]).
 */
function quelle_finden(string $praefix, string $vorgabe): string
{
    if ($vorgabe !== '') {
        if (!is_file($vorgabe)) {
            throw new RuntimeException('Datei nicht gefunden: ' . $vorgabe);
        }

        return $vorgabe;
    }

    $dateien = glob(MS_ALT_BACKUPS . '/' . $praefix . '*.json') ?: [];
    if ($dateien === []) {
        throw new RuntimeException('Keine Sicherung gefunden: ' . MS_ALT_BACKUPS . '/' . $praefix . '*.json');
    }

    $live = array_values(array_filter(
        $dateien,
        static fn(string $pfad): bool => str_contains(basename($pfad), 'live')
    ));
    $kandidaten = $live !== [] ? $live : $dateien;

    $besterStempel = -1;
    $besteWahl = [];
    foreach ($kandidaten as $pfad) {
        $stempel = 0;
        if (preg_match('/(\d{8})[-_](\d{4,6})/', basename($pfad), $treffer)) {
            $stempel = (int) ($treffer[1] . str_pad($treffer[2], 6, '0'));
        }
        if ($stempel > $besterStempel) {
            $besterStempel = $stempel;
            $besteWahl = [$pfad];
        } elseif ($stempel === $besterStempel) {
            $besteWahl[] = $pfad;
        }
    }

    return (string) ($besteWahl[0] ?? $dateien[0]);
}

/**
 * JSON-Datei lesen und prüfen.
 *
 * @return array<string|int, mixed>
 */
function json_laden(string $pfad, string $bezeichnung): array
{
    $roh = file_get_contents($pfad);
    if ($roh === false) {
        throw new RuntimeException($bezeichnung . ' nicht lesbar: ' . $pfad);
    }

    try {
        $daten = json_decode($roh, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $ausnahme) {
        throw new RuntimeException($bezeichnung . ' ist kein gültiges JSON: ' . $ausnahme->getMessage());
    }

    if (!is_array($daten) || $daten === []) {
        throw new RuntimeException($bezeichnung . ' enthält keine Daten: ' . $pfad);
    }

    return $daten;
}

/** Skalarer Wert als Zeichenkette, ohne Leerzeichen am Rand. */
function wert(mixed $wert): string
{
    return is_scalar($wert) ? trim((string) $wert) : '';
}

/** Wie wert(), aber ohne Trimmen – für Nachrichtentexte und Formeln. */
function roh_wert(mixed $wert): string
{
    return is_scalar($wert) ? (string) $wert : '';
}

/** DATETIME oder null; ungültige/fehlende Werte werden gezählt. */
function zeitpunkt(mixed $wert): ?string
{
    $text = wert($wert);
    if ($text === '') {
        return null;
    }
    $zeit = strtotime($text);

    return $zeit === false ? null : date('Y-m-d H:i:s', $zeit);
}

/**
 * Baut aus beiden JSON-Ständen den vollständigen Importplan.
 *
 * @param array<string|int, mixed> $bausteine
 * @param array<string|int, mixed> $schueler
 * @return array<string, mixed>
 */
function plan_bauen(array $bausteine, array $schueler): array
{
    $plan = [
        'bausteine'   => [],
        'dialog'      => [],
        'aufgaben'    => [],
        'fragen'      => [],
        'schueler'    => [],
        'nachrichten' => [],
        'status'      => [],
        'hinweise'    => [],
        'statistik'   => [
            'arten'        => ['lern' => 0, 'quiz' => 0],
            'telefone'     => 0,
            'klassen'      => 0,
            'chat'         => 0,
            'quiz'         => 0,
            'leer'         => 0,
            'zeit_ersetzt' => 0,
            'zeitraum_von' => null,
            'zeitraum_bis' => null,
            'punkte'       => 0,
            'statusliste'  => 0,
            'status_fremd' => [],
        ],
    ];

    // ---- Bausteine ---------------------------------------------------------
    foreach ($bausteine as $slug => $roh) {
        $slug = wert($slug);
        if ($slug === '' || !is_array($roh)) {
            $plan['hinweise'][] = 'Baustein ohne gültigen Slug übersprungen: ' . $slug;
            continue;
        }

        $quiz = isset($roh['quiz']) && is_array($roh['quiz']) ? $roh['quiz'] : null;
        $art = $quiz !== null ? 'quiz' : 'lern';

        $plan['bausteine'][] = [
            'slug'         => $slug,
            'art'          => $art,
            'titel'        => wert($roh['titel'] ?? '') !== '' ? wert($roh['titel']) : $slug,
            'thema'        => wert($roh['thema'] ?? '') !== '' ? wert($roh['thema']) : null,
            'prompt'       => roh_wert($roh['prompt'] ?? ''),
            'quiz_thema'   => $quiz !== null && wert($quiz['thema'] ?? '') !== '' ? wert($quiz['thema']) : null,
            'quiz_anzahl'  => $quiz !== null && isset($quiz['anzahl']) && is_numeric($quiz['anzahl'])
                ? (int) $quiz['anzahl'] : null,
            'erstellt_at'  => zeitpunkt($roh['erstellt_at'] ?? null),
            'geaendert_at' => zeitpunkt($roh['geaendert_at'] ?? null),
        ];
        $plan['statistik']['arten'][$art]++;

        // Beispieldialog
        $dialog = isset($roh['dialog']) && is_array($roh['dialog']) ? $roh['dialog'] : [];
        foreach (array_values($dialog) as $position => $zeile) {
            if (!is_array($zeile)) {
                continue;
            }
            $rolle = wert($zeile['rolle'] ?? '');
            if (!in_array($rolle, ['lehrer', 'schueler'], true)) {
                $plan['hinweise'][] = 'Unbekannte Rolle bei ' . $slug . ' #' . $position . ': „' . $rolle . '“ → lehrer';
                $rolle = 'lehrer';
            }
            $plan['dialog'][] = [
                'slug'     => $slug,
                'position' => $position,
                'rolle'    => $rolle,
                'text'     => roh_wert($zeile['text'] ?? ''),
                'formel'   => roh_wert($zeile['formel'] ?? ''),
                'bild'     => wert($zeile['bild'] ?? ''),
            ];
        }

        // Referenzaufgaben je Stufe
        $aufgaben = isset($roh['aufgaben']) && is_array($roh['aufgaben']) ? $roh['aufgaben'] : [];
        foreach (['leicht', 'mittel', 'schwer'] as $stufe) {
            $liste = isset($aufgaben[$stufe]) && is_array($aufgaben[$stufe]) ? $aufgaben[$stufe] : [];
            foreach (array_values($liste) as $position => $zeile) {
                if (!is_array($zeile)) {
                    continue;
                }
                $plan['aufgaben'][] = [
                    'slug'     => $slug,
                    'stufe'    => $stufe,
                    'position' => $position,
                    'text'     => roh_wert($zeile['text'] ?? ''),
                    'formel'   => roh_wert($zeile['formel'] ?? ''),
                ];
            }
        }

        // Quiz-Fragen (nur bei art = quiz)
        if ($quiz !== null) {
            $fragen = isset($quiz['fragen']) && is_array($quiz['fragen']) ? $quiz['fragen'] : [];
            foreach (array_values($fragen) as $position => $zeile) {
                if (!is_array($zeile)) {
                    continue;
                }
                $plan['fragen'][] = [
                    'slug'     => $slug,
                    'position' => $position,
                    'frage'    => roh_wert($zeile['frage'] ?? ''),
                    'antwort'  => roh_wert($zeile['antwort'] ?? ''),
                ];
            }
        }
    }

    // ---- Schüler -----------------------------------------------------------
    foreach ($schueler as $id => $roh) {
        $id = wert($id);
        if (!preg_match('/^\d{4}$/', $id)) {
            $plan['hinweise'][] = 'Schüler mit ungültiger Kennung übersprungen: ' . $id;
            continue;
        }
        if (!is_array($roh)) {
            $plan['hinweise'][] = 'Schüler ' . $id . ' hat keine gültigen Daten, übersprungen.';
            continue;
        }

        $stufe = wert($roh['stufe_aktiv'] ?? '');
        if (!in_array($stufe, ['leicht', 'mittel', 'schwer', 'zufall'], true)) {
            $stufe = 'leicht';
        }

        $bonuspunkte = isset($roh['bonuspunkte']) && is_numeric($roh['bonuspunkte'])
            ? (int) $roh['bonuspunkte'] : 0;

        $plan['schueler'][] = [
            'id_code'          => $id,
            'vorname'          => wert($roh['vorname'] ?? ''),
            'nachname'         => wert($roh['nachname'] ?? ''),
            'klasse'           => wert($roh['klasse'] ?? '') !== '' ? wert($roh['klasse']) : null,
            'telefon'          => wert($roh['telefon'] ?? '') !== '' ? wert($roh['telefon']) : null,
            'bonuspunkte'      => $bonuspunkte,
            'aktiver_baustein' => wert($roh['aktiver_baustein'] ?? '') !== ''
                ? wert($roh['aktiver_baustein']) : null,
            'quiz_baustein'    => wert($roh['quiz_baustein'] ?? '') !== ''
                ? wert($roh['quiz_baustein']) : null,
            'stufe'            => $stufe,
            'gestartet_at'     => zeitpunkt($roh['gestartet_at'] ?? null),
        ];

        $plan['statistik']['punkte'] += $bonuspunkte;
        if (wert($roh['telefon'] ?? '') !== '') {
            $plan['statistik']['telefone']++;
        }
        if (wert($roh['klasse'] ?? '') !== '') {
            $plan['statistik']['klassen']++;
        }

        // Altbestand führt eine Statusliste (offen/geschafft) und Schalter.
        // Die Statusliste wandert nach ms_schueler_bausteine (002); die
        // Schalter (chat_mr_aktiv, chat_ki_aktiv, thema_aktiv) haben im
        // Zielmodell weiterhin kein Feld und werden nur berichtet.
        $statusliste = isset($roh['bausteine']) && is_array($roh['bausteine']) ? $roh['bausteine'] : [];
        $plan['statistik']['statusliste'] += count($statusliste);

        foreach ($statusliste as $eintrag) {
            if (!is_array($eintrag)) {
                continue;
            }
            $slug = wert($eintrag['slug'] ?? '');
            if ($slug === '') {
                continue;
            }
            $plan['status'][] = [
                'schueler_id' => $id,
                'slug'        => $slug,
                'status'      => wert($eintrag['status'] ?? '') === 'j' ? 'geschafft' : 'offen',
                'erreicht_at' => zeitpunkt($eintrag['erreicht_at'] ?? null),
            ];
        }

        // ---- Verläufe ------------------------------------------------------
        foreach ([['verlauf', 'chat'], ['verlauf_quiz', 'quiz']] as [$feld, $kontext]) {
            $liste = isset($roh[$feld]) && is_array($roh[$feld]) ? $roh[$feld] : [];
            foreach (array_values($liste) as $position => $zeile) {
                if (!is_array($zeile)) {
                    continue;
                }

                $richtung = wert($zeile['richtung'] ?? '');
                if (!in_array($richtung, ['an_schueler', 'an_lehrer'], true)) {
                    $plan['hinweise'][] = 'Unbekannte Richtung bei ' . $id . '/' . $kontext
                        . ' #' . $position . ': „' . $richtung . '“ → an_schueler';
                    $richtung = 'an_schueler';
                }

                $zeit = zeitpunkt($zeile['zeit'] ?? null);
                if ($zeit === null) {
                    $zeit = date('Y-m-d H:i:s');
                    $plan['statistik']['zeit_ersetzt']++;
                }

                $text = roh_wert($zeile['text'] ?? '');
                $formel = roh_wert($zeile['formel'] ?? '');
                if (trim($text) === '' && trim($formel) === '') {
                    $plan['statistik']['leer']++;
                }

                $plan['nachrichten'][] = [
                    'schueler_id' => $id,
                    'kontext'     => $kontext,
                    'richtung'    => $richtung,
                    'text'        => $text,
                    'formel'      => $formel,
                    'richtig'     => null,
                    'zeit'        => $zeit,
                ];
                $plan['statistik'][$kontext]++;

                if ($plan['statistik']['zeitraum_von'] === null || $zeit < $plan['statistik']['zeitraum_von']) {
                    $plan['statistik']['zeitraum_von'] = $zeit;
                }
                if ($plan['statistik']['zeitraum_bis'] === null || $zeit > $plan['statistik']['zeitraum_bis']) {
                    $plan['statistik']['zeitraum_bis'] = $zeit;
                }
            }
        }
    }

    // Aktive Bausteine gegen den Bausteinbestand prüfen.
    $bekannt = array_column($plan['bausteine'], 'slug');
    foreach ($plan['schueler'] as $eintrag) {
        foreach (['aktiver_baustein', 'quiz_baustein'] as $feld) {
            $slug = $eintrag[$feld];
            if ($slug !== null && !in_array($slug, $bekannt, true)) {
                $plan['hinweise'][] = 'Schüler ' . $eintrag['id_code'] . ': ' . $feld
                    . ' „' . $slug . '“ fehlt im Bausteinbestand';
            }
        }
    }

    // Statusliste nur für vorhandene Bausteine übernehmen (Fremd-Slugs wie
    // rechtw-dreieck_prompt/demo_prompt würden den Fremdschlüssel brechen).
    $gefiltert = [];
    foreach ($plan['status'] as $eintrag) {
        if (!in_array($eintrag['slug'], $bekannt, true)) {
            $plan['hinweise'][] = 'Statusliste: Baustein „' . $eintrag['slug']
                . '“ fehlt im Bestand – für Schüler ' . $eintrag['schueler_id'] . ' übersprungen';
            continue;
        }
        $gefiltert[] = $eintrag;
    }
    $plan['status'] = $gefiltert;
    $plan['statistik']['statusliste'] = count($plan['status']);

    // Statusliste auf fremde Slugs prüfen (z. B. rechtw-dreieck_prompt).
    foreach ($schueler as $roh) {
        foreach ((array) ($roh['bausteine'] ?? []) as $eintrag) {
            $slug = is_array($eintrag) ? wert($eintrag['slug'] ?? '') : '';
            if ($slug !== '' && !in_array($slug, $bekannt, true)) {
                $plan['statistik']['status_fremd'][$slug] = true;
            }
        }
    }

    return $plan;
}

/**
 * Schreibt den Plan in die Datenbank (eine Transaktion, wiederholbar).
 *
 * @param array<string, mixed> $plan
 * @return array<string, int> Anzahl geschriebener Zeilen je Tabelle
 */
function import_ausfuehren(PDO $pdo, array $plan): array
{
    $jetzt = date('Y-m-d H:i:s');
    $geschrieben = [
        'bausteine' => 0, 'dialog' => 0, 'aufgaben' => 0,
        'fragen' => 0, 'schueler' => 0, 'nachrichten' => 0, 'status' => 0,
    ];

    $pdo->beginTransaction();
    try {
        $bausteinSql = $pdo->prepare(
            'INSERT INTO ms_bausteine
                (slug, art, titel, thema, prompt, quiz_thema, quiz_anzahl, erstellt_at, geaendert_at)
             VALUES (:slug, :art, :titel, :thema, :prompt, :quiz_thema, :quiz_anzahl, :erstellt_at, :geaendert_at)
             ON DUPLICATE KEY UPDATE
                art = VALUES(art), titel = VALUES(titel), thema = VALUES(thema), prompt = VALUES(prompt),
                quiz_thema = VALUES(quiz_thema), quiz_anzahl = VALUES(quiz_anzahl),
                geaendert_at = VALUES(geaendert_at), erstellt_at = VALUES(erstellt_at)'
        );
        foreach ($plan['bausteine'] as $b) {
            $bausteinSql->execute([
                ':slug'         => $b['slug'],
                ':art'          => $b['art'],
                ':titel'        => $b['titel'],
                ':thema'        => $b['thema'],
                ':prompt'       => $b['prompt'],
                ':quiz_thema'   => $b['quiz_thema'],
                ':quiz_anzahl'  => $b['quiz_anzahl'],
                ':erstellt_at'  => $b['erstellt_at'] ?? $jetzt,
                ':geaendert_at' => $b['geaendert_at'] ?? $jetzt,
            ]);
            $geschrieben['bausteine']++;
        }

        $dialogWeg  = $pdo->prepare('DELETE FROM ms_baustein_dialog WHERE slug = :slug');
        $aufgabeWeg = $pdo->prepare('DELETE FROM ms_baustein_aufgaben WHERE slug = :slug');
        $frageWeg   = $pdo->prepare('DELETE FROM ms_quiz_fragen WHERE slug = :slug');
        $dialogNeu  = $pdo->prepare(
            'INSERT INTO ms_baustein_dialog (slug, position, rolle, text, formel, bild)
             VALUES (:slug, :position, :rolle, :text, :formel, :bild)'
        );
        $aufgabeNeu = $pdo->prepare(
            'INSERT INTO ms_baustein_aufgaben (slug, stufe, position, text, formel)
             VALUES (:slug, :stufe, :position, :text, :formel)'
        );
        $frageNeu   = $pdo->prepare(
            'INSERT INTO ms_quiz_fragen (slug, position, frage, antwort)
             VALUES (:slug, :position, :frage, :antwort)'
        );

        foreach ($plan['bausteine'] as $b) {
            $dialogWeg->execute([':slug' => $b['slug']]);
            $aufgabeWeg->execute([':slug' => $b['slug']]);
            $frageWeg->execute([':slug' => $b['slug']]);
        }
        foreach ($plan['dialog'] as $d) {
            $dialogNeu->execute([
                ':slug'     => $d['slug'],
                ':position' => $d['position'],
                ':rolle'    => $d['rolle'],
                ':text'     => $d['text'],
                ':formel'   => $d['formel'],
                ':bild'     => $d['bild'],
            ]);
            $geschrieben['dialog']++;
        }
        foreach ($plan['aufgaben'] as $a) {
            $aufgabeNeu->execute([
                ':slug'     => $a['slug'],
                ':stufe'    => $a['stufe'],
                ':position' => $a['position'],
                ':text'     => $a['text'],
                ':formel'   => $a['formel'],
            ]);
            $geschrieben['aufgaben']++;
        }
        foreach ($plan['fragen'] as $f) {
            $frageNeu->execute([
                ':slug'     => $f['slug'],
                ':position' => $f['position'],
                ':frage'    => $f['frage'],
                ':antwort'  => $f['antwort'],
            ]);
            $geschrieben['fragen']++;
        }

        $schuelerSql = $pdo->prepare(
            'INSERT INTO ms_schueler
                (id_code, vorname, nachname, klasse, telefon, bonuspunkte, aktiver_baustein,
                 quiz_baustein, stufe, gestartet_at)
             VALUES (:id_code, :vorname, :nachname, :klasse, :telefon, :bonuspunkte,
                 :aktiver_baustein, :quiz_baustein, :stufe, :gestartet_at)
             ON DUPLICATE KEY UPDATE
                vorname = VALUES(vorname), nachname = VALUES(nachname), klasse = VALUES(klasse),
                telefon = VALUES(telefon), bonuspunkte = VALUES(bonuspunkte),
                aktiver_baustein = VALUES(aktiver_baustein), quiz_baustein = VALUES(quiz_baustein),
                stufe = VALUES(stufe), gestartet_at = VALUES(gestartet_at)'
        );
        foreach ($plan['schueler'] as $s) {
            $schuelerSql->execute([
                ':id_code'          => $s['id_code'],
                ':vorname'          => $s['vorname'],
                ':nachname'         => $s['nachname'],
                ':klasse'           => $s['klasse'],
                ':telefon'          => $s['telefon'],
                ':bonuspunkte'      => $s['bonuspunkte'],
                ':aktiver_baustein' => $s['aktiver_baustein'],
                ':quiz_baustein'    => $s['quiz_baustein'],
                ':stufe'            => $s['stufe'],
                ':gestartet_at'     => $s['gestartet_at'],
            ]);
            $geschrieben['schueler']++;
        }

        $nachrichtWeg = $pdo->prepare('DELETE FROM ms_nachrichten WHERE schueler_id = :id');
        $nachrichtNeu = $pdo->prepare(
            'INSERT INTO ms_nachrichten (schueler_id, kontext, richtung, text, formel, richtig, zeit)
             VALUES (:schueler_id, :kontext, :richtung, :text, :formel, :richtig, :zeit)'
        );
        foreach ($plan['schueler'] as $s) {
            $nachrichtWeg->execute([':id' => $s['id_code']]);
        }
        foreach ($plan['nachrichten'] as $n) {
            $nachrichtNeu->execute([
                ':schueler_id' => $n['schueler_id'],
                ':kontext'     => $n['kontext'],
                ':richtung'    => $n['richtung'],
                ':text'        => $n['text'],
                ':formel'      => $n['formel'],
                ':richtig'     => $n['richtig'],
                ':zeit'        => $n['zeit'],
            ]);
            $geschrieben['nachrichten']++;
        }

        // Statusliste (offen/geschafft) je Schüler neu schreiben.
        $statusWeg = $pdo->prepare('DELETE FROM ms_schueler_bausteine WHERE schueler_id = :id');
        $statusNeu = $pdo->prepare(
            "INSERT INTO ms_schueler_bausteine (schueler_id, slug, status, erreicht_at)
             VALUES (:id, :slug, :status, :erreicht_at)
             ON DUPLICATE KEY UPDATE status = VALUES(status), erreicht_at = VALUES(erreicht_at)"
        );
        foreach ($plan['schueler'] as $s) {
            $statusWeg->execute([':id' => $s['id_code']]);
        }
        foreach ($plan['status'] as $st) {
            $statusNeu->execute([
                ':id'          => $st['schueler_id'],
                ':slug'        => $st['slug'],
                ':status'      => $st['status'],
                ':erreicht_at' => $st['erreicht_at'],
            ]);
            $geschrieben['status']++;
        }

        $pdo->commit();
    } catch (Throwable $ausnahme) {
        $pdo->rollBack();
        throw $ausnahme;
    }

    return $geschrieben;
}

/**
 * Aktueller Datenbankstand für den Prüfbericht.
 *
 * @return array<string, int>
 */
function db_stand(PDO $pdo): array
{
    $zaehle = static fn(string $tabelle): int =>
        (int) $pdo->query('SELECT COUNT(*) FROM ' . $tabelle)->fetchColumn();

    return [
        'schueler'    => $zaehle('ms_schueler'),
        'bausteine'   => $zaehle('ms_bausteine'),
        'dialog'      => $zaehle('ms_baustein_dialog'),
        'aufgaben'    => $zaehle('ms_baustein_aufgaben'),
        'fragen'      => $zaehle('ms_quiz_fragen'),
        'nachrichten' => $zaehle('ms_nachrichten'),
        'status'      => $zaehle('ms_schueler_bausteine'),
        'chat'        => (int) $pdo->query("SELECT COUNT(*) FROM ms_nachrichten WHERE kontext = 'chat'")->fetchColumn(),
        'quiz'        => (int) $pdo->query("SELECT COUNT(*) FROM ms_nachrichten WHERE kontext = 'quiz'")->fetchColumn(),
        'punkte'      => (int) $pdo->query('SELECT COALESCE(SUM(bonuspunkte), 0) FROM ms_schueler')->fetchColumn(),
    ];
}

/**
 * Prüfbericht als Textzeilen.
 *
 * @param list<array{was: string, pfad: string, mtime: int, groesse: int}> $quellen
 * @param array<string, mixed>      $plan
 * @param array<string, int>        $vorher
 * @param array<string, int>|null   $nachher
 * @param array<string, int>|null   $geschrieben
 * @return array{zeilen: list<string>, fehler: int}
 */
function bericht(array $quellen, array $plan, array $vorher, ?array $nachher, ?array $geschrieben, bool $liveDelta = false): array
{
    $s = $plan['statistik'];
    $zeilen = [];
    $pruefungen = [];   // [status, text] – status: ok | warn | fehler

    $zeilen[] = 'Quellen (nur gelesen)';
    foreach ($quellen as $quelle) {
        $zeilen[] = sprintf(
            '  %-11s %-40s %s  %s',
            $quelle['was'],
            basename($quelle['pfad']),
            date('d.m.Y H:i', $quelle['mtime']),
            number_format($quelle['groesse'], 0, ',', '.') . ' Bytes'
        );
    }

    $zeilen[] = '';
    $zeilen[] = 'Soll/Ist in der Quelle';
    $zeilen[] = sprintf('  %-14s %s', 'Schüler:', count($plan['schueler']) . ' (Soll ' . MS_ERWARTET['schueler'] . ')');
    $zeilen[] = sprintf(
        '  %-14s %s',
        'Bausteine:',
        count($plan['bausteine']) . ' (Soll ' . MS_ERWARTET['bausteine'] . '; '
        . $s['arten']['lern'] . ' Lern, ' . $s['arten']['quiz'] . ' Quiz)'
    );
    $zeilen[] = sprintf(
        '  %-14s %s',
        'Inhalte:',
        count($plan['dialog']) . ' Dialogzeilen, ' . count($plan['aufgaben']) . ' Aufgaben, '
        . count($plan['fragen']) . ' Quiz-Fragen'
    );
    $zeilen[] = sprintf(
        '  %-14s %s',
        'Nachrichten:',
        count($plan['nachrichten']) . ' (' . $s['chat'] . ' Chat, ' . $s['quiz'] . ' Quiz), Doku '
        . MS_ERWARTET['nachrichten']
    );
    $zeilen[] = sprintf('  %-14s %s', 'Bonuspunkte:', $s['punkte'] . ' (Soll ' . MS_ERWARTET['punkte'] . ')');
    $zeilen[] = sprintf(
        '  %-14s %s',
        'Kontaktdaten:',
        $s['telefone'] . ' Telefonnummern, ' . $s['klassen'] . ' Klassenangaben'
    );
    if ($s['zeitraum_von'] !== null) {
        $zeilen[] = sprintf('  %-14s %s – %s', 'Zeitraum:', $s['zeitraum_von'], $s['zeitraum_bis']);
    }

    $pruefungen[] = [
        count($plan['schueler']) === MS_ERWARTET['schueler'] ? 'ok' : 'fehler',
        'Schülerzahl ' . count($plan['schueler']) . ' von ' . MS_ERWARTET['schueler'],
    ];
    $pruefungen[] = [
        count($plan['bausteine']) === MS_ERWARTET['bausteine'] ? 'ok' : 'fehler',
        'Bausteinzahl ' . count($plan['bausteine']) . ' von ' . MS_ERWARTET['bausteine'],
    ];
    $pruefungen[] = [
        $s['punkte'] === MS_ERWARTET['punkte'] || $liveDelta ? 'ok' : 'fehler',
        'Punktsumme ' . $s['punkte'] . ' von ' . MS_ERWARTET['punkte']
        . ($liveDelta
            ? ' (Live-Stand; Delta ' . ($s['punkte'] - MS_ERWARTET['punkte'] > 0 ? '+' : '')
                . ($s['punkte'] - MS_ERWARTET['punkte']) . ')'
            : ''),
    ];

    $delta = count($plan['nachrichten']) - MS_ERWARTET['nachrichten'];
    $pruefungen[] = [
        $delta === 0 ? 'ok' : 'warn',
        'Nachrichten ' . count($plan['nachrichten']) . ' von ' . MS_ERWARTET['nachrichten']
        . ($delta === 0 ? '' : ' (Delta ' . ($delta > 0 ? '+' : '') . $delta
            . '; Snapshot kann älter sein als die Übergabe → E7-Delta)'),
    ];
    $pruefungen[] = [
        $s['zeit_ersetzt'] === 0 ? 'ok' : 'warn',
        'Zeitstempel vollständig (' . $s['zeit_ersetzt'] . ' ersetzt)',
    ];
    $pruefungen[] = [
        $s['leer'] === 0 ? 'ok' : 'warn',
        'Nachrichten ohne Text und Formel: ' . $s['leer'],
    ];

    $fremdeAktive = count(array_filter(
        $plan['hinweise'],
        static fn(string $h): bool => str_contains($h, 'fehlt im Bausteinbestand')
    ));
    $pruefungen[] = [
        $fremdeAktive === 0 ? 'ok' : 'warn',
        'Aktive Bausteine vorhanden (' . $fremdeAktive . ' fehlen)',
    ];

    $zeilen[] = '';
    $zeilen[] = 'Datenbank';
    $zeilen[] = sprintf(
        '  vorher:  %d Schüler, %d Bausteine, %d Nachrichten (Chat %d / Quiz %d), %d Status, %d Punkte',
        $vorher['schueler'], $vorher['bausteine'], $vorher['nachrichten'],
        $vorher['chat'], $vorher['quiz'], $vorher['status'], $vorher['punkte']
    );

    if ($nachher !== null && $geschrieben !== null) {
        $zeilen[] = sprintf(
            '  nachher: %d Schüler, %d Bausteine, %d Nachrichten (Chat %d / Quiz %d), %d Status, %d Punkte',
            $nachher['schueler'], $nachher['bausteine'], $nachher['nachrichten'],
            $nachher['chat'], $nachher['quiz'], $nachher['status'], $nachher['punkte']
        );
        $zeilen[] = sprintf(
            '  geschrieben: %d Bausteine, %d Dialogzeilen, %d Aufgaben, %d Quiz-Fragen, %d Schüler, %d Nachrichten, %d Status',
            $geschrieben['bausteine'], $geschrieben['dialog'], $geschrieben['aufgaben'],
            $geschrieben['fragen'], $geschrieben['schueler'], $geschrieben['nachrichten'],
            $geschrieben['status']
        );

        $soll = [
            'schueler'    => count($plan['schueler']),
            'bausteine'   => count($plan['bausteine']),
            'dialog'      => count($plan['dialog']),
            'aufgaben'    => count($plan['aufgaben']),
            'fragen'      => count($plan['fragen']),
            'nachrichten' => count($plan['nachrichten']),
            'status'      => count($plan['status']),
            'chat'        => $s['chat'],
            'quiz'        => $s['quiz'],
            'punkte'      => $s['punkte'],
        ];
        $abweichungen = [];
        foreach ($soll as $feld => $wert) {
            if (($nachher[$feld] ?? -1) !== $wert) {
                $abweichungen[] = $feld . ' ' . ($nachher[$feld] ?? '?') . ' ≠ ' . $wert;
            }
        }
        $pruefungen[] = [
            $abweichungen === [] ? 'ok' : 'fehler',
            'Datenbank entspricht der Quelle'
            . ($abweichungen === [] ? '' : ' (' . implode(', ', $abweichungen) . ')'),
        ];
        $pruefungen[] = [
            $nachher['chat'] + $nachher['quiz'] === $nachher['nachrichten'] ? 'ok' : 'fehler',
            'Kontexte Chat + Quiz = Gesamtzahl',
        ];
    }

    $zeilen[] = '';
    $zeilen[] = 'Prüfungen';
    $fehler = 0;
    foreach ($pruefungen as [$status, $text]) {
        $zeichen = match ($status) {
            'ok' => '[ok]  ',
            'warn' => '[warn]',
            default => '[FEHL]',
        };
        if ($status === 'fehler') {
            $fehler++;
        }
        $zeilen[] = '  ' . $zeichen . ' ' . $text;
    }

    $zeilen[] = '';
    $zeilen[] = 'Nicht abgebildet (bewusst, Zielmodell Abschnitt 4)';
    $zeilen[] = '  Statusliste „bearbeitete Bausteine“: ' . $s['statusliste']
        . ' Einträge → ms_schueler_bausteine (Migration 002)';
    $zeilen[] = '  Schalter chat_mr_aktiv / chat_ki_aktiv und thema_aktiv (je Schüler)';
    $zeilen[] = '  Einwilligungen: im Altbestand keine Daten → alle Kanäle auf „nein“';

    if ($plan['hinweise'] !== []) {
        $zeilen[] = '';
        $zeilen[] = 'Hinweise beim Lesen der Quelle (' . count($plan['hinweise']) . ')';
        foreach (array_slice($plan['hinweise'], 0, 20) as $hinweis) {
            $zeilen[] = '  - ' . $hinweis;
        }
        if (count($plan['hinweise']) > 20) {
            $zeilen[] = '  … weitere ' . (count($plan['hinweise']) - 20);
        }
    }

    $zeilen[] = '';
    $zeilen[] = 'Ergebnis: ' . ($fehler === 0
        ? ($nachher !== null
            ? 'grün – Import geschrieben'
            : 'grün – nur Bericht, nichts geschrieben (--apply fehlt)')
        : 'rot – ' . $fehler . ' Prüfung(en) fehlgeschlagen');

    return ['zeilen' => $zeilen, 'fehler' => $fehler];
}

// ---------------------------------------------------------------------------
// Ausführen
// ---------------------------------------------------------------------------
$zeilen = ['=== E1 — Altbestand 0711 → ms_* ===', ''];
$fehler = 0;

try {
    $schuelerPfad  = quelle_finden('schueler', $optionen['schueler']);
    $bausteinePfad = quelle_finden('bausteine', $optionen['bausteine']);

    $quellen = [];
    foreach ([['Schüler:', $schuelerPfad], ['Bausteine:', $bausteinePfad]] as [$was, $pfad]) {
        $groesse = filesize($pfad);
        $quellen[] = [
            'was'     => $was,
            'pfad'    => $pfad,
            'mtime'   => (int) filemtime($pfad),
            'groesse' => $groesse === false ? 0 : $groesse,
        ];
    }

    $plan = plan_bauen(
        json_laden($bausteinePfad, 'Bausteine'),
        json_laden($schuelerPfad, 'Schüler')
    );

    $pdo = db();
    $vorher = db_stand($pdo);

    $nachher = null;
    $geschrieben = null;
    if ($optionen['apply']) {
        $geschrieben = import_ausfuehren($pdo, $plan);
        $nachher = db_stand($pdo);
    }

    $liveDelta = $optionen['schueler'] !== '' || $optionen['bausteine'] !== '';
    $ergebnis = bericht($quellen, $plan, $vorher, $nachher, $geschrieben, $liveDelta);
    $zeilen = array_merge($zeilen, $ergebnis['zeilen']);
    $fehler = $ergebnis['fehler'];
} catch (Throwable $ausnahme) {
    $fehler = 1;
    $zeilen[] = 'FEHLER: ' . $ausnahme->getMessage();
    $zeilen[] = 'Hinweis: Schema vorhanden? Sonst zuerst „php migrate.php“ ausführen.';
}

echo implode(PHP_EOL, $zeilen) . PHP_EOL;

if ($istCli) {
    exit($fehler);
}
