<?php

declare(strict_types=1);

/**
 * KI-Bausteine (aus dem Altstand übernommen, unverändert im Verhalten):
 * Aufruf bei Gemini, robustes Lesen der JSON-Antwort, Marker, Verlauf→Messages.
 *
 * Einbinden nach inc/config.php.
 */

/**
 * Ruft Gemini mit einer vollständigen Nachrichtenliste auf
 * (System + Verlauf + aktuelle Frage). null = Fehler/Zeitüberschreitung.
 *
 * @param array $optionen zusätzliche Felder für die Anfrage, z. B.
 *                        ['temperature' => 0.9, 'max_tokens' => 900]
 */
function ki_chat(array $messages, array $optionen = []): ?string
{
    if (!function_exists('curl_init')) {
        return null;
    }

    $payload = json_encode(
        ['model' => TUTOR_MODEL, 'messages' => $messages] + $optionen,
        JSON_UNESCAPED_UNICODE
    );

    if ($payload === false) {
        return null;
    }

    $ch = curl_init(KI_BASE_URL . 'chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . KI_API_KEY,
        ],
        CURLOPT_TIMEOUT        => API_TIMEOUT,
        // Lokales XAMPP ohne CA-Bundle → SSL-Prüfung deaktivieren.
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ]);

    $resp = curl_exec($ch);
    curl_close($ch);

    if ($resp === false) {
        return null;
    }

    $data = json_decode($resp, true);

    return is_array($data) ? ($data['choices'][0]['message']['content'] ?? null) : null;
}

/**
 * Ruft Gemini mit System- und Nutzertext auf (für Generator/Quiz-Test).
 *
 * @param array $optionen siehe ki_chat()
 */
function ki_generate(string $system, string $user, array $optionen = []): ?string
{
    return ki_chat([
        ['role' => 'system', 'content' => $system],
        ['role' => 'user', 'content' => $user],
    ], $optionen);
}

/**
 * Die KI schreibt LaTeX gern als "\cdot" statt "\\cdot". "\c" ist in JSON kein
 * gültiger Escape → json_decode() scheitert und rohes JSON landet im Chat.
 * Diese Funktion verdoppelt nur die ungültigen Backslashes und lässt gültige
 * Escapes ("\\", "\"", "\n", "\u00e4", …) unangetastet.
 */
function json_backslashes_reparieren(string $json): string
{
    $aus      = '';
    $laenge   = strlen($json);
    $inString = false;

    for ($i = 0; $i < $laenge; $i++) {
        $zeichen = $json[$i];

        if (!$inString) {
            $aus .= $zeichen;
            if ($zeichen === '"') {
                $inString = true;
            }
            continue;
        }

        if ($zeichen === '\\') {
            $naechstes = ($i + 1 < $laenge) ? $json[$i + 1] : '';

            // Nur echte JSON-Escapes stehen lassen. "\b", "\f", "\n", "\r"
            // und "\t" werden bewusst NICHT als Escape gewertet: in einem
            // Mathe-Prompt heißen die fast immer \beta, \frac, \neq,
            // \rightarrow oder \theta.
            $gueltig = in_array($naechstes, ['"', '\\', '/'], true);

            if (!$gueltig && $naechstes === 'u'
                && preg_match('/^[0-9a-fA-F]{4}$/', substr($json, $i + 2, 4)) === 1) {
                $gueltig = true;
            }

            $aus .= $gueltig ? '\\' . $naechstes : '\\\\' . $naechstes;
            $i++;
            continue;
        }

        if ($zeichen === '"') {
            $inString = false;
        }

        $aus .= $zeichen;
    }

    return $aus;
}
/**
 * Liest die KI-Antwort als JSON-Objekt und fängt die üblichen Modell-Pannen ab:
 * Code-Fences, Text vor/nach dem Objekt und unescapte LaTeX-Backslashes.
 * Rückgabe: ['text' => …, 'formel' => …] oder null.
 */
function ki_antwort_lesen(string $roh): ?array
{
    $roh = trim($roh);

    if ($roh === '') {
        return null;
    }

    // ```json … ``` entfernen
    if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/s', $roh, $treffer)) {
        $roh = trim($treffer[1]);
    }

    $kandidaten = [$roh];

    // Nur das äußerste {…} prüfen (falls Text davor/danach steht)
    $start = strpos($roh, '{');
    $ende  = strrpos($roh, '}');

    if ($start !== false && $ende !== false && $ende > $start) {
        $kandidaten[] = substr($roh, $start, $ende - $start + 1);
    }

    foreach ($kandidaten as $kandidat) {
        $daten = json_decode($kandidat, true);

        if (is_array($daten)) {
            return $daten;
        }

        $daten = json_decode(json_backslashes_reparieren($kandidat), true);

        if (is_array($daten)) {
            return $daten;
        }
    }

    return null;
}

/**
 * Stellt sicher, dass die Ziel-Regel im Prompt steht. KI-erzeugte Prompts
 * vergessen sie gern – dann wird der verbindliche Block angehängt.
 *
 * @return array{0: string, 1: bool}
 */
function prompt_ziel_regel_sichern(string $prompt): array
{
    if (str_contains($prompt, '[[ZIEL ERREICHT]]')) {
        return [$prompt, false];
    }

    return [rtrim($prompt) . "\n\n" . TUTOR_ZIEL_REGEL, true];
}

/**
 * Füllt die Platzhalter {{name}}, {{stufe}}, {{bild_info}} im Prompt.
 */
function fill_prompt_placeholders(string $prompt, string $name, string $stufe, string $bildInfo): string
{
    return str_replace(
        ['{{name}}', '{{stufe}}', '{{bild_info}}'],
        [$name, $stufe, $bildInfo],
        $prompt
    );
}

/**
 * Wandelt Nachrichtenzeilen (richtung/text/formel) in eine Gemini-Liste um.
 * an_lehrer = Schüler (user), an_schueler = Lehrer/KI (assistant).
 */
function verlauf_zu_messages(array $verlauf): array
{
    $messages = [];

    foreach ($verlauf as $z) {
        $text   = trim((string) ($z['text'] ?? ''));
        $formel = trim((string) ($z['formel'] ?? ''));

        $content = $text;
        if ($formel !== '') {
            $content .= ($content !== '' ? "\nFormel: " : 'Formel: ') . $formel;
        }

        if ($content === '') {
            continue;
        }

        $richtung = (string) ($z['richtung'] ?? '');
        $role     = $richtung === 'an_lehrer' ? 'user' : 'assistant';

        $messages[] = ['role' => $role, 'content' => $content];
    }

    return $messages;
}

/**
 * Liest die internen Marker aus der ROH-Antwort (vor clean_ai_text):
 *   [[SCHAF]]        → Belohnung, ein Schaf pro Marker (max. 3 pro Antwort)
 *   [[ZIEL ERREICHT]] → Baustein ist geschafft
 *
 * @return array{schafe: int, ziel: bool}
 */
function ki_marker_lesen(string $rohantwort): array
{
    $schafe = substr_count($rohantwort, '[[SCHAF]]');
    $schafe = max(0, min($schafe, 3));   // Schutz gegen ausufernde Antworten

    return [
        'schafe' => $schafe,
        'ziel'   => str_contains($rohantwort, '[[ZIEL ERREICHT]]'),
    ];
}

/**
 * Entfernt interne Markierungen wie [[SCHAF]] und [[ZIEL ERREICHT]] aus dem
 * KI-Text. Diese werden durch Schafsbilder ersetzt, dürfen aber nie wörtlich
 * im Chat erscheinen.
 */
function clean_ai_text(string $text): string
{
    $text = (string) preg_replace('/\[\[[^\]]*\]\]/', '', $text);
    $text = (string) preg_replace('/ {2,}/', ' ', $text);

    return trim($text);
}
/**
 * Hängt ein Bild als Data-URL an die letzte Schüler-Nachricht (multimodal).
 */
function ki_bild_anhaengen(array $messages, string $dataUrl): array
{
    for ($i = count($messages) - 1; $i >= 0; $i--) {
        if (($messages[$i]['role'] ?? '') !== 'user') {
            continue;
        }

        $inhalt = $messages[$i]['content'] ?? '';

        if (is_string($inhalt)) {
            $messages[$i]['content'] = [
                ['type' => 'text', 'text' => $inhalt],
                ['type' => 'image_url', 'image_url' => ['url' => $dataUrl]],
            ];
        }

        return $messages;
    }

    return $messages;
}

/**
 * Baut den Referenzblock „je eine Aufgabe pro Stufe" für den Prompt.
 *
 * Der Block wird dem Prompt wörtlich angehängt (statt darauf zu hoffen, dass
 * die KI ihn abschreibt) – so kennt der Tutor die Vorgaben der Lehrkraft
 * garantiert und erfindet nur die übrigen Aufgaben.
 *
 * @param array<string, string> $aufgaben  stufe => "text | formel"
 */
function prompt_referenzaufgaben(array $aufgaben): string
{
    $zeilen = [];

    foreach (['leicht', 'mittel', 'schwer'] as $stufe) {
        $roh = trim((string) ($aufgaben[$stufe] ?? ''));

        if ($roh === '') {
            continue;
        }

        $teile  = array_map('trim', explode('|', $roh, 2));
        $text   = (string) ($teile[0] ?? '');
        $formel = (string) ($teile[1] ?? '');

        if ($text === '' && $formel === '') {
            continue;
        }

        $zeile = '- ' . $stufe . ': ' . ($text !== '' ? $text : '(nur Formel)');

        if ($formel !== '') {
            $zeile .= '  [Formel: ' . $formel . ']';
        }

        $zeilen[] = $zeile;
    }

    if ($zeilen === []) {
        return '';
    }

    return "REFERENZAUFGABEN (Vorgabe der Lehrkraft – je Stufe genau EINE Aufgabe als Niveau):\n"
        . implode("\n", $zeilen) . "\n"
        . "Beginne eine Übung mit der Referenzaufgabe der gewählten Stufe. Alle weiteren Aufgaben "
        . "erfindest du selbst im gleichen Stil und Schwierigkeitsgrad; keine Aufgabe doppelt, "
        . "keine Lösung in der Aufgabe nennen.";
}

/**
 * Stellt sicher, dass die Formel-Regel im Prompt steht (LaTeX, das MathLive
 * auch darstellt). Wird an ChatGPT-/Tutor-Prompts angehängt, wenn sie fehlt.
 */
function prompt_formel_regel_sichern(string $prompt): string
{
    if (str_contains($prompt, 'FORMEL-REGEL')) {
        return $prompt;
    }

    return rtrim($prompt) . "\n\n" . FORMEL_REGEL;
}

/**
 * Räumt eine Formel so auf, dass der Renderer sie sicher darstellt.
 *
 * Bekanntes Problem: Die KI schreibt \dots – MathLive kennt nur \ldots und
 * zeigt \dots wörtlich mit Wellenlinie an. Andere Befehle (\, \mathrm{km},
 * \cdot, \left( \right)) rendert MathLive korrekt, die bleiben unangetastet.
 */
function formel_saeubern(string $latex): string
{
    $f = trim($latex);

    if ($f === '') {
        return '';
    }

    /* \dots → \ldots (einziger Auslassungsbefehl, den MathLive darstellt) */
    $f = preg_replace('/\\\\dots(?![A-Za-z])/', '\\\\ldots', $f) ?? $f;

    return trim($f);
}

/**
 * Erzeugt im Auftrag des Baustein-Editors einen fertigen System-Prompt.
 *
 * @param array<string, mixed> $daten art, titel, thema, dialog, aufgaben_*,
 *                                    fragen, quiz_thema, quiz_anzahl
 * @return string|null null = KI hat nicht geantwortet
 */
function ki_prompt_erzeugen(array $daten): ?string
{
    $art = (string) ($daten['art'] ?? 'lern') === 'quiz' ? 'quiz' : 'lern';

    $titel  = trim((string) ($daten['titel'] ?? ''));
    $thema  = trim((string) ($daten['thema'] ?? ''));
    $dialog = trim((string) ($daten['dialog'] ?? ''));
    $aufgaben = [];
    foreach (['leicht', 'mittel', 'schwer'] as $stufe) {
        $aufgaben[$stufe] = trim((string) ($daten['aufgaben_' . $stufe] ?? ''));
    }
    $fragen    = trim((string) ($daten['fragen'] ?? ''));
    $quizThema = trim((string) ($daten['quiz_thema'] ?? ''));
    $anzahl    = max(1, min(20, (int) ($daten['quiz_anzahl'] ?? 5)));

    if ($art === 'quiz') {
        $system = 'Du erzeugst den System-Prompt für ein Multiple-Choice-Quiz im Lernprogramm „Matheschaf“. '
            . 'Der Prompt wird einer KI als Systemnachricht gegeben. Er muss folgendes verlangen: '
            . 'genau ' . $anzahl . ' Fragen pro Durchgang, jede mit vier Antworten A), B), C), D) und genau einer richtigen; '
            . 'Frage und Antworten stehen vollständig in "text", jede Antwort in einer eigenen Zeile; '
            . 'die Stufe (leicht/mittel/schwer) bestimmt den Schwierigkeitsgrad; die Lösung wird nie vorher verraten. '
            . 'Nach der Antwort: kurze Rückmeldung (max. zwei Sätze), richtig → Marker [[RICHTIG]] und [[SCHAF]] anhängen, '
            . 'falsch → [[FALSCH]] und die richtige Antwort in einem Satz erklären; danach sofort die nächste Frage '
            . 'mit „Frage x von ' . $anzahl . '“. Nach der letzten Frage eine Auswertung „x von ' . $anzahl . ' richtig“ '
            . 'und keine neue Frage. Keine Joker, keine Geldbeträge, keine Fernsehshow-Bezüge, keine Werbung. '
            . 'Ausgabe ausschließlich als JSON {"text": "...", "formel": "..."} ohne Text außerhalb; '
            . '"formel" ist LaTeX nur bei einer Rechnung, sonst ein leerer String; in "text" keine LaTeX-Formeln. '
            . 'Antworte AUSSCHLIESSLICH mit dem fertigen Prompt-Text, ohne Einleitung und ohne Code-Fences.';

        $user = 'Titel: ' . ($titel !== '' ? $titel : 'Mathe-Quiz')
            . "\nThema: " . ($thema !== '' ? $thema : 'Mathe allgemein')
            . "\nQuiz-Thema: " . ($quizThema !== '' ? $quizThema : ($thema !== '' ? $thema : 'Mathe allgemein'))
            . "\nFragen pro Durchgang: " . $anzahl;

        if ($fragen !== '') {
            $user .= "\nReferenzfragen (nur als Niveau, nicht wörtlich übernehmen):\n" . $fragen;
        }
    } else {
        $klasse   = trim((string) ($daten['klasse'] ?? ''));
        $hinweise = trim((string) ($daten['hinweise'] ?? ''));

        $system = "Du schreibst den System-Prompt für den KI-Lernbegleiter „Matheschaf“ "
            . "(Mathe-Tutor für Schülerinnen und Schüler). Der Prompt wird einer anderen KI als "
            . "Systemnachricht gegeben und muss dort ohne weitere Erklärung funktionieren.\n\n"
            . "Der Prompt, den du formulierst, MUSS diese Punkte enthalten und zwar als klare Anweisungen:\n"
            . "1. ROLLE & ZIELGRUPPE: Thema, Klassenstufe, freundlicher geduldiger Tutor, duzt das Kind.\n"
            . "2. ANTWORTFORMAT (verbindlich): Antworte ausschließlich mit JSON "
            . "{\"text\": \"...\", \"formel\": \"...\"} – ohne Text davor oder danach, ohne Code-Fences.\n"
            . "   - \"text\" enthält NUR Worte. Keine Rechnungen, Gleichungen, Terme, Ergebnisse, "
            . "Hoch- oder Tiefstellungen, kein LaTeX, kein \\$.\n"
            . "   - \"formel\" enthält jede Rechnung, jeden Term und jedes Ergebnis als LaTeX "
            . "(z. B. \\frac{3}{4}, x^2+2x, \\sqrt{16}). Ist nichts zu rechnen, bleibt \"formel\" leer.\n"
            . "3. DIALOGSTIL: kurze Antworten (ein bis zwei Sätze), motivierend, höchstens EINE Frage "
            . "pro Antwort, die Lösung niemals verraten, über Fragen zum Lösungsweg führen.\n"
            . "4. AUFGABEN: Beginne mit der Referenzaufgabe der gewählten Stufe. Alle weiteren Aufgaben "
            . "erfindest du selbst im gleichen Stil und im passenden Schwierigkeitsgrad "
            . "(leicht, mittel, schwer); keine Aufgabe doppelt, keine Lösung in der Aufgabe.\n"
            . "5. BELOHNUNG: Für einen richtigen Zwischenschritt hängt die KI [[SCHAF]] an \"text\" an.\n"
            . "6. ZIEL-REGEL: Der Prompt muss den folgenden Block WÖRTLICH enthalten:\n\n"
            . TUTOR_ZIEL_REGEL . "\n\n"
            . "7. MUSTERDIALOG: Der beigefügte Musterdialog ist verbindliches Vorbild für Stil, Länge "
            . "und die Trennung von Text und Formel. Verlange im Prompt ausdrücklich, dass diese "
            . "Trennung in JEDER Antwort eingehalten wird – auch bei Zwischenschritten und Ergebnissen.\n\n"
            . "8. FORMELN: Übernimm den folgenden Block WÖRTLICH in den Prompt – er legt fest, "
            . "welche LaTeX-Befehle die App darstellt:\n\n"
            . FORMEL_REGEL . "\n\n"
            . "Antworte AUSSCHLIESSLICH mit dem fertigen Prompt-Text, ohne Einleitung, ohne Kommentar, "
            . "ohne Code-Fences.";

        $user = 'Thema: ' . ($thema !== '' ? $thema : 'Mathematik')
            . "\nTitel: " . ($titel !== '' ? $titel : 'Lern-Baustein');

        if ($klasse !== '') {
            $user .= "\nKlasse/Stufe: " . $klasse;
        }

        if ($hinweise !== '') {
            $user .= "\nHinweise der Lehrkraft: " . $hinweise;
        }

        if ($dialog !== '') {
            $user .= "\n\nMusterdialog (Lehrer/Schüler, Formeln getrennt in [Formel]):\n" . $dialog;
        }

        foreach ($aufgaben as $stufe => $text) {
            if ($text !== '') {
                $user .= "\n\nReferenzaufgabe " . $stufe . " (nur als Niveau, Rest selbst erfinden):\n" . $text;
            }
        }
    }

    $erzeugt = ki_generate($system, $user);

    if ($erzeugt === null || trim($erzeugt) === '') {
        return null;
    }

    $prompt = trim($erzeugt);

    /* Ziel-Regel garantieren und die Referenzaufgaben wörtlich anhängen –
       darauf verlassen wir uns nicht bei der KI. */
    [$prompt] = prompt_ziel_regel_sichern($prompt);

    if ($art === 'lern') {
        $block = prompt_referenzaufgaben($aufgaben);

        if ($block !== '' && !str_contains($prompt, 'REFERENZAUFGABEN')) {
            $prompt = rtrim($prompt) . "\n\n" . $block;
        }
    }

    return $prompt;
}

/** Nimmt alle Bildteile wieder heraus (Fallback ohne Bild). */
function ki_bild_entfernen(array $messages): array
{
    foreach ($messages as $i => $nachricht) {
        $inhalt = $nachricht['content'] ?? '';

        if (!is_array($inhalt)) {
            continue;
        }

        $texte = [];

        foreach ($inhalt as $teil) {
            if (($teil['type'] ?? '') === 'text') {
                $texte[] = (string) ($teil['text'] ?? '');
            }
        }

        $messages[$i]['content'] = implode("\n", $texte);
    }

    return $messages;
}
