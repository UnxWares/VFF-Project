<?php

namespace App\Services\Signalling;

use App\Enums\SignallingRole;

/**
 * Faits de signalisation que l'extracteur prudent (SignallingHistoryExtractor) laisse passer et qu'une personne
 * a relus dans l'article Wikipédia : `database/data/signalling_history_wikipedia_supplements.php`, lus par
 * `vff:import-signalling-history` en plus des faits extraits. Lecture et contrôle du fichier, sans accès à la base.
 *
 * Un fait du fichier :
 *   line     : code SNCF Réseau à 6 chiffres (ou slug VFF) de la ligne ;
 *   article  : titre de l'article qui porte la phrase (facultatif : l'article de la ligne) ;
 *   quote    : la phrase de l'article, telle qu'elle y est (texte brut, 255 caractères au plus ; tirets écrits « - »,
 *            liens ⟦…⟧ ôtés). L'import la
 *              cherche dans l'article lu : absente (l'article a changé), le fait n'est pas rangé et la commande
 *              échoue. Elle devient l'extrait du fait ;
 *   system   : BAL, BAPR, BM, CT, CC (commande centralisée), TVM-300, TVM-430, TVM, ETCS_L1, ETCS_L2, ETCS ou KVB ;
 *   date     : AAAA-MM-JJ, AAAA-MM ou AAAA, comme la phrase (jamais une date déduite) ;
 *   scope    : whole_line, section (avec from et to : gares telles que l'article les nomme) ou unspecified.
 *
 * Fiabilité comme les faits extraits : high (jour ou mois), medium (année seule). La source du fait est la
 * révision lue de l'article, comme pour tout fait de Wikipédia (aucun document n'est ajouté).
 */
final class SignallingSupplementsFile
{
    public static function defaultPath(): string
    {
        return database_path('data/signalling_history_wikipedia_supplements.php');
    }

    /**
     * Faits du fichier, contrôlés ; une erreur par fait refusé (le fait n'est pas gardé).
     *
     * @param  list<array<string, mixed>>  $entries
     * @return array{facts: list<array{line: string, article: ?string, quote: string, system: string, role: string, date: string,
     *     precision: string, confidence: string, scope: string, section_from: ?string, section_to: ?string}>, errors: list<string>}
     */
    public static function parse(array $entries, ?\DateTimeInterface $today = null): array
    {
        $today ??= new \DateTimeImmutable;
        $facts = [];
        $errors = [];
        foreach ($entries as $i => $entry) {
            $error = self::error($entry, $today);
            if ($error !== null) {
                $errors[] = sprintf('fait n° %d (%s %s) : %s', $i + 1, $entry['line'] ?? '?', $entry['system'] ?? '?', $error);

                continue;
            }
            [$date, $precision] = SignallingSourcesFile::date((string) $entry['date']);
            $section = $entry['scope'] === 'section';
            $facts[] = [
                'line' => (string) $entry['line'],
                'article' => isset($entry['article']) && trim((string) $entry['article']) !== '' ? trim((string) $entry['article']) : null,
                'quote' => trim((string) $entry['quote']),
                'system' => (string) $entry['system'],
                'role' => SignallingRole::forSystem((string) $entry['system'])->value ?? 'block',
                'date' => $date,
                'precision' => $precision,
                'confidence' => $precision === 'year' ? 'medium' : 'high',
                'scope' => (string) $entry['scope'],
                'section_from' => $section ? trim((string) $entry['from']) : null,
                'section_to' => $section ? trim((string) $entry['to']) : null,
            ];
        }

        return ['facts' => $facts, 'errors' => $errors];
    }

    /**
     * La phrase du fichier se lit-elle dans le texte brut de l'article ? Espaces, espaces insécables et
     * apostrophes typographiques sont rapprochés avant de comparer.
     */
    public static function quoteIn(string $quote, string $plainText): bool
    {
        $needle = self::flatten($quote);

        return $needle !== '' && str_contains(self::flatten($plainText), $needle);
    }

    private static function flatten(string $text): string
    {
        // Liens ⟦…⟧ du texte brut retirés ; tirets de l'article écrits « - » (le fichier n'a jamais de tiret long).
        $text = str_replace(["\u{00A0}", "\u{202F}", "\u{2009}", '’', '‘', '⟦', '⟧', "\u{2014}", "\u{2013}"], [' ', ' ', ' ', "'", "'", '', '', '-', '-'], $text);

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /** @param  mixed  $entry */
    private static function error($entry, \DateTimeInterface $today): ?string
    {
        if (! is_array($entry)) {
            return 'pas un tableau';
        }
        foreach (['line', 'system', 'date', 'scope', 'quote'] as $key) {
            if (! isset($entry[$key]) || trim((string) $entry[$key]) === '') {
                return "champ « {$key} » manquant";
            }
        }
        if (SignallingRole::forSystem((string) $entry['system']) === null || ! preg_match('/^(?:BAL|BAPR|BM|CT|CC|TVM|TVM-300|TVM-430|ETCS|ETCS_L1|ETCS_L2|KVB)$/', (string) $entry['system'])) {
            return 'système inconnu';
        }
        $value = (string) $entry['date'];
        if (! preg_match('/^\d{4}(?:-\d{2}(?:-\d{2})?)?$/', $value)) {
            return 'date illisible (AAAA, AAAA-MM ou AAAA-MM-JJ)';
        }
        [$date] = SignallingSourcesFile::date($value);
        if (! checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))) {
            return 'date impossible';
        }
        if ($date > $today->format('Y-m-d')) {
            return 'date future';
        }
        if (SignallingSystems::tooEarly((string) $entry['system'], (int) substr($date, 0, 4))) {
            return 'date antérieure au système';
        }
        if (! in_array($entry['scope'], SignallingSourcesFile::SCOPES, true)) {
            return 'portée inconnue';
        }
        if ($entry['scope'] === 'section' && (trim((string) ($entry['from'] ?? '')) === '' || trim((string) ($entry['to'] ?? '')) === '')) {
            return 'portion sans ses deux extrémités';
        }
        if (mb_strlen(trim((string) $entry['quote'])) > 255) {
            return 'phrase de plus de 255 caractères';
        }
        if (preg_match('/[\x{2014}\x{2013}⟦⟧]|--/u', (string) $entry['quote'])) {
            // Un tiret de l'article s'écrit « - » dans le fichier (l'import les rapproche) ; pas de lien ⟦…⟧.
            return 'tiret long ou lien dans la phrase (écrire « - », sans ⟦ ⟧)';
        }

        return null;
    }
}
