<?php

declare(strict_types=1);

/**
 * KI-Fragen für die Challenge („Sprung über die Schlucht“).
 *
 * Ein Thema hat genau einen Satz aus drei Fragen (Position 1–3). In der
 * Datenbank liegt nur, was die Lehrkraft freigegeben hat; alles andere wird
 * bei jedem Aufruf neu erzeugt und nicht gespeichert.
 *
 * Einbinden nach inc/config.php und inc/chat.php (normalize_text, db()).
 * Die KI-Antwort wird über inc/ki.php gelesen (ki_antwort_lesen).
 */

require_once __DIR__ . '/ki.php';

/**
 * Thema vereinheitlichen (Anzeige und Vergleich).
 */
function fragen_thema(string $thema): string
{
    $thema = normalize_text($thema);
    $thema = (string) (preg_replace('/\s+/u', ' ', $thema) ?? $thema);

    return mb_substr($thema, 0, 60);
}

/**
 * Prüft eine einzelne Frage; null = unbrauchbar.
 *
 * @param mixed $eintrag
 * @return array{frage: string, antworten: list<string>, richtig: int}|null
 */
function frage_pruefen($eintrag): ?array
{
    if (!is_array($eintrag)) {
        return null;
    }

    $frage = fragen_thema((string) ($eintrag['frage'] ?? ''));
    $frage = mb_substr($frage, 0, 200);

    $antworten = [];
    foreach ((array) ($eintrag['antworten'] ?? []) as $antwort) {
        if (!is_scalar($antwort)) {
            continue;
        }

        $text = fragen_thema((string) $antwort);
        $text = mb_substr($text, 0, 120);

        if ($text !== '') {
            $antworten[] = $text;
        }
    }

    $richtig = (int) ($eintrag['richtig'] ?? -1);

    if ($frage === '' || count($antworten) !== 2 || !in_array($richtig, [0, 1], true)) {
        return null;
    }

    // Zwei gleiche Antworten wären keine Frage.
    if (mb_strtolower($antworten[0]) === mb_strtolower($antworten[1])) {
        return null;
    }

    return [
        'frage'     => $frage,
        'antworten' => [$antworten[0], $antworten[1]],
        'richtig'   => $richtig,
    ];
}

/**
 * Prüft eine Liste von Fragen und liefert bis zu drei saubere.
 *
 * @param array<string, mixed> $daten
 * @return list<array{frage: string, antworten: list<string>, richtig: int}>
 */
function fragen_pruefen_daten(array $daten): array
{
    if (!isset($daten['fragen']) || !is_array($daten['fragen'])) {
        return [];
    }

    $sauber = [];

    foreach ($daten['fragen'] as $eintrag) {
        $frage = frage_pruefen($eintrag);

        if ($frage !== null) {
            $sauber[] = $frage;
        }

        if (count($sauber) === 3) {
            break;
        }
    }

    return $sauber;
}

/**
 * Rohantwort der KI (JSON-Text) → bis zu drei saubere Fragen.
 *
 * @return list<array{frage: string, antworten: list<string>, richtig: int}>
 */
function fragen_pruefen(string $roh): array
{
    $daten = ki_antwort_lesen($roh);

    return is_array($daten) ? fragen_pruefen_daten($daten) : [];
}

/** Gibt es die Tabelle schon (Migration 003 gelaufen)? */
function fragen_tabelle_bereit(): bool
{
    try {
        return db_tabelle_vorhanden(db(), 'ms_fragen');
    } catch (Throwable $ausnahme) {
        return false;
    }
}

/**
 * Freigegebener Satz eines Themas (drei Fragen) oder [].
 *
 * @return list<array{frage: string, antworten: list<string>, richtig: int}>
 */
function fragen_aus_db(string $thema): array
{
    if (!fragen_tabelle_bereit()) {
        return [];
    }

    $abfrage = db()->prepare(
        'SELECT frage, antwort_a, antwort_b, richtig
           FROM ms_fragen
          WHERE thema = :thema AND freigegeben = 1
          ORDER BY position'
    );
    $abfrage->execute([':thema' => fragen_thema($thema)]);

    $fragen = [];

    foreach ($abfrage->fetchAll() as $zeile) {
        $fragen[] = [
            'frage'     => (string) $zeile['frage'],
            'antworten' => [(string) $zeile['antwort_a'], (string) $zeile['antwort_b']],
            'richtig'   => (int) $zeile['richtig'],
        ];
    }

    // Nur vollständige Sätze ausliefern (drei Fragen).
    return count($fragen) === 3 ? $fragen : [];
}

/**
 * Ersetzt den Satz eines Themas (drei Fragen müssen es sein).
 *
 * @param list<array{frage: string, antworten: list<string>, richtig: int}> $fragen
 * @return bool true = gespeichert
 */
function fragen_speichern(string $thema, array $fragen): bool
{
    if (count($fragen) !== 3 || !fragen_tabelle_bereit()) {
        return false;
    }

    $thema = fragen_thema($thema);
    $pdo   = db();
    $pdo->beginTransaction();

    try {
        $pdo->prepare('DELETE FROM ms_fragen WHERE thema = :thema')
            ->execute([':thema' => $thema]);

        $einfuegen = $pdo->prepare(
            'INSERT INTO ms_fragen
                (thema, position, frage, antwort_a, antwort_b, richtig, quelle, freigegeben)
             VALUES (:thema, :position, :frage, :a, :b, :richtig, :quelle, 1)'
        );

        foreach ($fragen as $i => $frage) {
            $einfuegen->execute([
                ':thema'    => $thema,
                ':position' => $i + 1,
                ':frage'    => $frage['frage'],
                ':a'        => $frage['antworten'][0],
                ':b'        => $frage['antworten'][1],
                ':richtig'  => $frage['richtig'],
                ':quelle'   => 'ki',
            ]);
        }

        $pdo->commit();
    } catch (Throwable $ausnahme) {
        $pdo->rollBack();

        return false;
    }

    return true;
}
