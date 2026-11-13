<?php

declare(strict_types=1);

/**
 * Chat-Daten und Seitenhelfer des Neubaus (E2).
 *
 * Kapselt alles, was früher direkt in schueler.json/bausteine.json lag:
 * Schüler, Baustein samt Dialog/Aufgaben/Fragen, Verlauf in ms_nachrichten,
 * Bonuspunkte und die Statusliste ms_schueler_bausteine.
 *
 * Einbinden nach inc/config.php (und inc/db.php wird bei Bedarf geladen).
 */

require_once __DIR__ . '/db.php';

/**
 * Vereinheitlicht Nutzereingaben: Zeilenumbrüche normalisieren,
 * Steuerzeichen und umschließende Leerzeichen entfernen.
 */
function normalize_text(string $text): string
{
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? $text;

    return trim($text);
}

/**
 * Beendet den Request mit einer JSON-Antwort.
 */
function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Schülerzeile oder null.
 *
 * @return array<string, mixed>|null
 */
function schueler_laden(string $id): ?array
{
    if (!preg_match('/^\d{4}$/', $id)) {
        return null;
    }

    $abfrage = db()->prepare('SELECT * FROM ms_schueler WHERE id_code = :id');
    $abfrage->execute([':id' => $id]);
    $zeile = $abfrage->fetch();

    return is_array($zeile) ? $zeile : null;
}

/**
 * Anzeigename: Vorname + Anfangsbuchstabe des Nachnamens („Michael R.").
 * Der volle Nachname wird nirgends angezeigt oder übermittelt (Telegram,
 * SMS, Anruf, Listen, Links, Mitlesen). Die eindeutige Zuordnung läuft über
 * die Kennung (ID), die überall daneben steht. Voller Name nur in den
 * Rohdaten (DB/JSON-Export) und im Bearbeiten-Formular → schueler_name_voll().
 */
function schueler_name(array $schueler): string
{
    $vorname  = trim((string) ($schueler['vorname'] ?? ''));
    $nachname = trim((string) ($schueler['nachname'] ?? ''));

    if ($vorname === '') {
        return 'Unbekannt';
    }

    if ($nachname === '') {
        return $vorname;
    }

    return $vorname . ' ' . mb_strtoupper(mb_substr($nachname, 0, 1)) . '.';
}

/**
 * Voller Name („Michael Romahn") – NUR für Rohdaten (Datenbankstand,
 * JSON-Export) und für das Bearbeiten-Formular in schueler_edit.php.
 */
function schueler_name_voll(array $schueler): string
{
    $name = trim(
        (string) ($schueler['vorname'] ?? '') . ' ' . (string) ($schueler['nachname'] ?? '')
    );

    return $name !== '' ? $name : 'Unbekannt';
}

/** Nur der Vorname (Anrede für KI, SMS und Anruf-Ansage). */
function schueler_vorname(array $schueler): string
{
    $vorname = trim((string) ($schueler['vorname'] ?? ''));

    return $vorname !== '' ? $vorname : 'Unbekannt';
}

/**
 * Ein Baustein in der Struktur des Altbestands:
 * dialog[], aufgaben[stufe][], quiz[thema|anzahl|fragen][].
 *
 * @return array<string, mixed>|null
 */
function baustein_laden(string $slug): ?array
{
    $slug = trim($slug);
    if ($slug === '') {
        return null;
    }

    $pdo = db();
    $abfrage = $pdo->prepare('SELECT * FROM ms_bausteine WHERE slug = :slug');
    $abfrage->execute([':slug' => $slug]);
    $baustein = $abfrage->fetch();

    if (!is_array($baustein)) {
        return null;
    }

    $dialog = $pdo->prepare(
        'SELECT rolle, text, formel, bild FROM ms_baustein_dialog
          WHERE slug = :slug ORDER BY position'
    );
    $dialog->execute([':slug' => $slug]);
    $baustein['dialog'] = $dialog->fetchAll();

    $baustein['aufgaben'] = ['leicht' => [], 'mittel' => [], 'schwer' => []];
    $aufgaben = $pdo->prepare(
        'SELECT stufe, text, formel FROM ms_baustein_aufgaben
          WHERE slug = :slug ORDER BY stufe, position'
    );
    $aufgaben->execute([':slug' => $slug]);
    foreach ($aufgaben->fetchAll() as $zeile) {
        $stufe = (string) $zeile['stufe'];
        if (!isset($baustein['aufgaben'][$stufe])) {
            continue;
        }
        $baustein['aufgaben'][$stufe][] = [
            'text'   => (string) $zeile['text'],
            'formel' => (string) $zeile['formel'],
        ];
    }

    if (($baustein['art'] ?? '') === 'quiz') {
        $fragen = $pdo->prepare(
            'SELECT frage, antwort FROM ms_quiz_fragen WHERE slug = :slug ORDER BY position'
        );
        $fragen->execute([':slug' => $slug]);

        $baustein['quiz'] = [
            'thema'  => (string) ($baustein['quiz_thema'] ?? ''),
            'anzahl' => (int) ($baustein['quiz_anzahl'] ?? 0),
            'fragen' => $fragen->fetchAll(),
        ];
    }

    return $baustein;
}

/**
 * Erstes Bild aus dem Dialog eines Bausteins – das Referenzbild links im Chat.
 */
function baustein_referenzbild(array $baustein): string
{
    foreach ($baustein['dialog'] ?? [] as $zeile) {
        $bild = is_array($zeile) ? trim((string) ($zeile['bild'] ?? '')) : '';

        if ($bild !== '') {
            return $bild;
        }
    }

    return '';
}

/**
 * Liest eine Bilddatei aus dem Projekt und baut daraus einen Data-URL,
 * den die KI direkt verarbeiten kann. null = nicht lesbar / zu groß.
 */
function bild_als_data_url(string $relPfad): ?string
{
    if ($relPfad === '') {
        return null;
    }

    $pfad = MS_ROOT . '/' . ltrim($relPfad, '/');

    if (!is_file($pfad)) {
        return null;
    }

    $info = @getimagesize($pfad);

    if ($info === false) {
        return null;
    }

    $mime = (string) ($info['mime'] ?? '');

    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
        return null;
    }

    $daten = @file_get_contents($pfad);

    // Über 5 MB ist für die Übergabe an die KI zu viel.
    if ($daten === false || strlen($daten) > 5 * 1024 * 1024) {
        return null;
    }

    return 'data:' . $mime . ';base64,' . base64_encode($daten);
}

/**
 * Verlauf eines Schülers, chronologisch (älteste zuerst).
 *
 * @return list<array<string, mixed>>
 */
function nachrichten_laden(string $id, string $kontext, int $limit = MESSAGES_ON_LOAD): array
{
    if (!in_array($kontext, ['chat', 'quiz'], true)) {
        $kontext = 'chat';
    }
    $limit = max(1, min($limit, 1000));

    $abfrage = db()->prepare(
        'SELECT richtung, text, formel, richtig, zeit
           FROM ms_nachrichten
          WHERE schueler_id = :id AND kontext = :kontext
          ORDER BY id DESC
          LIMIT ' . $limit
    );
    $abfrage->execute([':id' => $id, ':kontext' => $kontext]);

    return array_reverse($abfrage->fetchAll());
}

/**
 * Eine Nachricht speichern und ihre ID zurückgeben.
 */
function nachricht_speichern(
    string $id,
    string $kontext,
    string $richtung,
    string $text,
    string $formel = '',
    ?bool $richtig = null
): int {
    if (!in_array($kontext, ['chat', 'quiz'], true)) {
        $kontext = 'chat';
    }
    if (!in_array($richtung, ['an_schueler', 'an_lehrer'], true)) {
        $richtung = 'an_schueler';
    }

    $pdo = db();
    $einfuegen = $pdo->prepare(
        'INSERT INTO ms_nachrichten (schueler_id, kontext, richtung, text, formel, richtig, zeit)
         VALUES (:id, :kontext, :richtung, :text, :formel, :richtig, :zeit)'
    );
    $einfuegen->execute([
        ':id'       => $id,
        ':kontext'  => $kontext,
        ':richtung' => $richtung,
        ':text'     => $text,
        ':formel'   => $formel,
        ':richtig'  => $richtig === null ? null : ($richtig ? 1 : 0),
        ':zeit'     => date('Y-m-d H:i:s'),
    ]);

    return (int) $pdo->lastInsertId();
}

/**
 * Start einer Übung: alten Verlauf dieses Kontexts verwerfen, Stufe und
 * Startzeit am Schüler setzen (bewusst, wie im Altstand).
 */
function uebung_starten(string $id, string $kontext, string $stufe): void
{
    if (!in_array($kontext, ['chat', 'quiz'], true)) {
        $kontext = 'chat';
    }
    if (!in_array($stufe, ['leicht', 'mittel', 'schwer', 'zufall'], true)) {
        $stufe = 'leicht';
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $loeschen = $pdo->prepare(
            'DELETE FROM ms_nachrichten WHERE schueler_id = :id AND kontext = :kontext'
        );
        $loeschen->execute([':id' => $id, ':kontext' => $kontext]);

        $aendern = $pdo->prepare(
            'UPDATE ms_schueler SET stufe = :stufe, gestartet_at = :jetzt WHERE id_code = :id'
        );
        $aendern->execute([
            ':stufe' => $stufe,
            ':jetzt' => date('Y-m-d H:i:s'),
            ':id'    => $id,
        ]);

        $pdo->commit();
    } catch (Throwable $ausnahme) {
        $pdo->rollBack();
        throw $ausnahme;
    }
}

/**
 * Bonuspunkte addieren (nie unter 0) und den neuen Stand zurückgeben.
 */
function bonuspunkte_addieren(string $id, int $punkte): int
{
    $pdo = db();
    $aendern = $pdo->prepare(
        'UPDATE ms_schueler SET bonuspunkte = GREATEST(0, bonuspunkte + :punkte) WHERE id_code = :id'
    );
    $aendern->execute([':punkte' => $punkte, ':id' => $id]);

    $abfrage = $pdo->prepare('SELECT bonuspunkte FROM ms_schueler WHERE id_code = :id');
    $abfrage->execute([':id' => $id]);

    return (int) $abfrage->fetchColumn();
}

/**
 * Baustein als geschafft eintragen (Upsert) – Quelle für „Ziel erreicht“
 * und später die Karten in starter.php.
 */
function baustein_geschafft(string $id, string $slug): void
{
    if ($slug === '') {
        return;
    }

    $einfuegen = db()->prepare(
        "INSERT INTO ms_schueler_bausteine (schueler_id, slug, status, erreicht_at)
         VALUES (:id, :slug, 'geschafft', :jetzt)
         ON DUPLICATE KEY UPDATE status = 'geschafft', erreicht_at = VALUES(erreicht_at)"
    );
    $einfuegen->execute([':id' => $id, ':slug' => $slug, ':jetzt' => date('Y-m-d H:i:s')]);
}

/**
 * Statusliste eines Schülers (slug => ['status' => …, 'erreicht_at' => …]).
 *
 * @return array<string, array<string, mixed>>
 */
function baustein_status_liste(string $id): array
{
    $abfrage = db()->prepare(
        'SELECT slug, status, erreicht_at FROM ms_schueler_bausteine WHERE schueler_id = :id'
    );
    $abfrage->execute([':id' => $id]);

    $liste = [];
    foreach ($abfrage->fetchAll() as $zeile) {
        $liste[(string) $zeile['slug']] = [
            'status'      => (string) $zeile['status'],
            'erreicht_at' => $zeile['erreicht_at'],
        ];
    }

    return $liste;
}

/**
 * Alle Schüler, numerisch nach Kennung sortiert (Lehrer-Bereich).
 *
 * @return list<array<string, mixed>>
 */
function schueler_liste(): array
{
    $zeilen = db()->query('SELECT * FROM ms_schueler ORDER BY id_code')->fetchAll();

    usort(
        $zeilen,
        static fn(array $a, array $b): int => (int) $a['id_code'] <=> (int) $b['id_code']
    );

    return $zeilen;
}

/**
 * Bausteine als Übersicht: slug => ['titel','art','prompt_ok','quiz_anzahl'].
 *
 * @return array<string, array<string, mixed>>
 */
function bausteine_uebersicht(): array
{
    $uebersicht = [];

    foreach (db()->query('SELECT slug, titel, art, prompt, quiz_anzahl FROM ms_bausteine ORDER BY titel') as $zeile) {
        $uebersicht[(string) $zeile['slug']] = [
            'titel'       => (string) $zeile['titel'],
            'art'         => (string) $zeile['art'],
            'prompt_ok'   => trim((string) $zeile['prompt']) !== '',
            'quiz_anzahl' => (int) ($zeile['quiz_anzahl'] ?? 0),
        ];
    }

    return $uebersicht;
}

/**
 * Anzahl geschaffener/offener Bausteine je Schüler.
 *
 * @return array<string, array{geschafft: int, offen: int}>
 */
function status_zaehler(): array
{
    $zaehler = [];

    $abfrage = db()->query(
        "SELECT schueler_id,
                SUM(status = 'geschafft') AS geschafft,
                SUM(status = 'offen')     AS offen
           FROM ms_schueler_bausteine
          GROUP BY schueler_id"
    );

    foreach ($abfrage->fetchAll() as $zeile) {
        $zaehler[(string) $zeile['schueler_id']] = [
            'geschafft' => (int) $zeile['geschafft'],
            'offen'     => (int) $zeile['offen'],
        ];
    }

    return $zaehler;
}

/** Öffentlicher Chat-Link eines Schülers (immer die Online-Adresse). */
function online_chat_link(string $id): string
{
    return BASE_URL . 'index.php?id=' . rawurlencode($id);
}
/**
 * Positionen der Schafe auf der Wiese – reproduzierbar je Schüler
 * (gleiche Kennung → gleiche Herde, auch nach einem Neuladen).
 *
 * @return list<array<string, mixed>>
 */
function wiese_schafe(string $id, int $bonuspunkte): array
{
    $anzahl = min(max($bonuspunkte, 0), WIESE_MAX_SCHAFE);

    if ($anzahl === 0) {
        return [];
    }

    $bilder = WIESE_SCHAF_BILDER;
    $state  = crc32($id !== '' ? $id : 'gast');

    // Linearer Kongruenzgenerator → reproduzierbare „Zufallswerte“ je Schüler.
    $rand = static function () use (&$state): float {
        $state = ($state * 1103515245 + 12345) & 0x7fffffff;

        return $state / 0x7fffffff;
    };

    // Grasband im Wiesenbild (oben Himmel, unten Wiese) in Prozent der Höhe.
    $bandTop  = 44.0;
    $bandSize = 52.0;

    $spalten = (int) ceil(sqrt($anzahl * 1.8));
    $spalten = max(1, min($spalten, 8));
    $zeilen  = (int) ceil($anzahl / $spalten);

    $zellBreite = 100 / $spalten;
    $zellHoehe  = $bandSize / max($zeilen, 1);

    // Grundgröße in % der Wiesenbreite: bei vielen Schafen kleiner, aber nie
    // so klein, dass die Tiere nur noch als Punkte erkennbar sind.
    $grundGroesse = max(10.5, min(17.0, 46.0 / $spalten));

    $schafe = [];

    for ($i = 0; $i < $anzahl; $i++) {
        $zeile  = intdiv($i, $spalten);
        $spalte = $i % $spalten;

        $x = ($spalte + 0.5) * $zellBreite + ($rand() - 0.5) * $zellBreite * 0.55;
        $y = $bandTop + ($zeile + 0.5) * $zellHoehe + ($rand() - 0.5) * $zellHoehe * 0.45;

        $x = max(4.0, min(96.0, $x));
        $y = max($bandTop + 2.0, min($bandTop + $bandSize, $y));

        // Perspektive: weiter unten (vorne) größer.
        $tiefe = ($y - $bandTop) / $bandSize;

        $dauer = round(3.6 + $rand() * 4.4, 2);                     // 3,6–8 s
        $weite = round((10 + $rand() * 26) * ($rand() < 0.5 ? -1 : 1), 1);

        $schafe[] = [
            'src'   => $bilder[(int) floor($rand() * count($bilder)) % count($bilder)],
            'x'     => round($x, 2),
            'y'     => round($y, 2),
            'size'  => round($grundGroesse * (0.72 + 0.40 * $tiefe), 2),
            'dur'   => $dauer,
            'dist'  => $weite,
            'delay' => -round($rand() * $dauer, 2),
            'z'     => (int) round($y),
        ];
    }

    return $schafe;
}

/**
 * Adresse des Challenge-Projekts. Es liegt als eigenes Projekt NEBEN /lernchat/
 * und läuft ohne Login: lokal http://localhost/challenge/ –
 * live https://matheschaf.de/challenge/
 *
 * @param string $anhang z. B. '?thema=' . rawurlencode('Brüche')
 */
function challenge_url(string $anhang = ''): string
{
    $basis  = rtrim(BASE_URL, '/');
    $wurzel = substr($basis, 0, (int) strrpos($basis, '/') + 1);

    return $wurzel . 'challenge/' . ltrim($anhang, '/');
}

/**
 * Tags für Browser-Tab und „Zum Home-Bildschirm hinzufügen“.
 * Quelle aller Icons ist favicon.ico; Android/Chrome zieht die Icons aus
 * manifest.php, iOS braucht ein PNG (apple-touch-icon).
 */
function app_icon_tags(string $startUrl = 'starter.php', string $appName = APP_NAME): string
{
    $start    = ltrim(trim($startUrl), '/');
    $name     = trim($appName) !== '' ? trim($appName) : APP_NAME;
    $nameHtml = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');

    $manifest = 'manifest.php?start=' . rawurlencode($start) . '&name=' . rawurlencode($name);
    $farbe    = htmlspecialchars(APP_THEME_COLOR, ENT_QUOTES, 'UTF-8');

    $tags = [
        '<link rel="icon" href="favicon.ico" sizes="any">',
        '<link rel="icon" type="image/png" sizes="192x192" href="icons/icon-192.png">',
        '<link rel="icon" type="image/png" sizes="512x512" href="icons/icon-512.png">',
        '<link rel="apple-touch-icon" sizes="180x180" href="icons/apple-touch-icon.png">',
        '<link rel="manifest" href="' . htmlspecialchars($manifest, ENT_QUOTES, 'UTF-8') . '">',
        '<meta name="theme-color" content="' . $farbe . '">',
        '<meta name="application-name" content="' . $nameHtml . '">',
        '<meta name="mobile-web-app-capable" content="yes">',
        '<meta name="apple-mobile-web-app-capable" content="yes">',
        '<meta name="apple-mobile-web-app-status-bar-style" content="default">',
        '<meta name="apple-mobile-web-app-title" content="' . $nameHtml . '">',
    ];

    return implode("\n    ", $tags);
}
