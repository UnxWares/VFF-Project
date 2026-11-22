<?php

namespace App\Services\Signalling;

/**
 * Corrections sourcées de l'historique de la signalisation, relevées à la main dans
 * `database/data/signalling_history_corrections.php` (même esprit que `line_event_corrections*.php`).
 * Lecture et contrôle du fichier, sans accès à la base : `vff:import-signalling-history` (faits de Wikipédia) et
 * `vff:import-signalling-sources` (faits des documents) l'appliquent en rangeant leurs faits.
 *
 * Une correction :
 *   line    : code SNCF Réseau à 6 chiffres (ou slug VFF) ;
 *   action  : `neutralize` (le fait visé n'est plus montré nulle part : fiche, API, carte par ère ; il reste en
 *             base avec sa correction) ou `replace` (idem, et `with` donne le fait juste, au format de
 *             `signalling_history_sources.php` sans `line`, rangé par `vff:import-signalling-sources`) ;
 *   target  : le fait visé : `source` (`wikipedia` ou `manual`), `system`, `date` (AAAA-MM-JJ, AAAA-MM ou AAAA,
 *             telle que rangée : jour ou mois manquants complétés par 01) et sa portion (`from` et `to`, telles
 *             qu'écrites ; absentes pour un fait sans portion nommée) ;
 *   sources : documents qui contredisent le fait (publisher, title, url https, published facultatif, official
 *             facultatif) ; même règle que les faits : un document officiel ou deux sites différents ;
 *   quote   : citation d'un des documents, 250 caractères au plus ;
 *   reason  : pourquoi le fait est faux, rédigé par VFF.
 * Un fait visé introuvable à l'import est une erreur : la source (article Wikipédia, fichier de documents) a changé.
 */
final class SignallingCorrectionsFile
{
    public const ACTIONS = ['neutralize', 'replace'];

    public const TARGET_SOURCES = ['wikipedia', 'manual'];

    public static function defaultPath(): string
    {
        return database_path('data/signalling_history_corrections.php');
    }

    /**
     * Corrections du fichier, contrôlées et mises à plat ; une erreur par correction refusée.
     *
     * @param  list<array<string, mixed>>  $entries
     * @return array{corrections: list<array{line: string, action: string, target: array{source: string, system: string, date: string, from: ?string, to: ?string},
     *     sources: list<array{publisher: string, title: string, url: string, published: ?string, official: bool}>, quote: string, reason: string, with: ?array<string, mixed>}>,
     *     errors: list<string>}
     */
    public static function parse(array $entries): array
    {
        $corrections = [];
        $errors = [];
        foreach ($entries as $i => $entry) {
            $error = self::error($entry);
            if ($error !== null) {
                $errors[] = sprintf('correction n° %d (%s %s) : %s', $i + 1, $entry['line'] ?? '?', is_array($entry['target'] ?? null) ? ($entry['target']['system'] ?? '?') : '?', $error);

                continue;
            }
            $target = $entry['target'];
            [$date] = SignallingSourcesFile::date((string) $target['date']);
            $corrections[] = [
                'line' => (string) $entry['line'],
                'action' => (string) $entry['action'],
                'target' => [
                    'source' => (string) $target['source'],
                    'system' => (string) $target['system'],
                    'date' => $date,
                    'from' => self::text($target['from'] ?? null),
                    'to' => self::text($target['to'] ?? null),
                ],
                'sources' => array_map(fn (array $s): array => [
                    'publisher' => trim((string) $s['publisher']),
                    'title' => trim((string) $s['title']),
                    'url' => (string) $s['url'],
                    'published' => isset($s['published']) ? (string) $s['published'] : null,
                    'official' => (bool) ($s['official'] ?? false),
                ], array_values($entry['sources'])),
                'quote' => trim((string) $entry['quote']),
                'reason' => trim((string) $entry['reason']),
                'with' => $entry['action'] === 'replace' ? $entry['with'] + ['line' => (string) $entry['line']] : null,
            ];
        }

        return ['corrections' => $corrections, 'errors' => $errors];
    }

    /**
     * La correction vise-t-elle ce fait rangé ?
     *
     * @param  array{line: string, target: array{source: string, system: string, date: string, from: ?string, to: ?string}}  $correction
     * @param  list<string>  $lineRefs  code et slug de la ligne du fait
     */
    public static function matches(array $correction, string $source, array $lineRefs, string $system, string $date, ?string $from, ?string $to): bool
    {
        $t = $correction['target'];
        $same = fn (?string $a, ?string $b): bool => mb_strtolower(trim((string) $a)) === mb_strtolower(trim((string) $b));

        return $t['source'] === $source && in_array($correction['line'], $lineRefs, true) && $t['system'] === $system
            && $t['date'] === $date && $same($t['from'], $from) && $same($t['to'], $to);
    }

    /**
     * Ce que la base garde d'une correction (colonne `correction`), sans la marque « officiel » interne.
     *
     * @param  array{action: string, quote: string, reason: string, sources: list<array<string, mixed>>}  $correction
     */
    public static function stored(array $correction): string
    {
        return json_encode([
            'action' => $correction['action'],
            'reason' => $correction['reason'],
            'quote' => $correction['quote'],
            'sources' => array_map(fn (array $s): array => array_diff_key($s, ['official' => true]), $correction['sources']),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * Libellé lisible d'une correction non appliquée, pour l'erreur d'import.
     *
     * @param  array{line: string, target: array{source: string, system: string, date: string}}  $correction
     */
    public static function describe(array $correction): string
    {
        return sprintf('%s %s du %s (%s)', $correction['line'], $correction['target']['system'], $correction['target']['date'], $correction['target']['source']);
    }

    private static function text(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private static function error(mixed $entry): ?string
    {
        if (! is_array($entry)) {
            return 'pas un tableau';
        }
        foreach (['line', 'action', 'target', 'sources', 'quote', 'reason'] as $key) {
            if (! isset($entry[$key]) || $entry[$key] === '' || $entry[$key] === []) {
                return "champ « {$key} » manquant";
            }
        }
        if (! in_array($entry['action'], self::ACTIONS, true)) {
            return 'action inconnue (neutralize ou replace)';
        }
        $t = $entry['target'];
        if (! is_array($t) || ! in_array($t['source'] ?? null, self::TARGET_SOURCES, true)) {
            return 'fait visé : source inconnue (wikipedia ou manual)';
        }
        if (! preg_match('/^(?:BAL|BAPR|BM|CT|TVM|TVM-300|TVM-430|ETCS|ETCS_L1|ETCS_L2|KVB)$/', (string) ($t['system'] ?? ''))) {
            return 'fait visé : système inconnu';
        }
        if (! preg_match('/^\d{4}(?:-\d{2}(?:-\d{2})?)?$/', (string) ($t['date'] ?? ''))) {
            return 'fait visé : date illisible (AAAA, AAAA-MM ou AAAA-MM-JJ)';
        }
        [$date] = SignallingSourcesFile::date((string) $t['date']);
        if (! checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))) {
            return 'fait visé : date impossible';
        }
        if ((self::text($t['from'] ?? null) === null) !== (self::text($t['to'] ?? null) === null)) {
            return 'fait visé : portion sans ses deux extrémités';
        }
        if (mb_strlen((string) $entry['quote']) > 250) {
            return 'citation de plus de 250 caractères';
        }
        if (preg_match('/[—–]|--/u', (string) $entry['reason'].(string) $entry['quote'])) {
            return 'tiret long dans la raison ou la citation';
        }
        if (! is_array($entry['sources'])) {
            return 'sources illisibles';
        }
        foreach ($entry['sources'] as $source) {
            if (! is_array($source) || trim((string) ($source['publisher'] ?? '')) === '' || trim((string) ($source['title'] ?? '')) === '') {
                return 'source sans éditeur ou sans titre';
            }
            if (! str_starts_with((string) ($source['url'] ?? ''), 'https://') || filter_var($source['url'], FILTER_VALIDATE_URL) === false) {
                return 'lien de source invalide (https attendu)';
            }
            if (isset($source['published']) && ! preg_match('/^\d{4}(?:-\d{2}(?:-\d{2})?)?$/', (string) $source['published'])) {
                return 'date de publication illisible';
            }
            if (str_contains((string) $source['url'], 'archeoferroviaire')) {
                return 'source non citable';
            }
        }
        $official = array_filter($entry['sources'], fn (array $s): bool => (bool) ($s['official'] ?? false));
        if ($official === [] && count(array_unique(array_map(fn (array $s): string => (string) parse_url((string) $s['url'], PHP_URL_HOST), $entry['sources']))) < 2) {
            return 'ni source officielle ni deux sources indépendantes (deux sites différents)';
        }
        if ($entry['action'] === 'replace') {
            if (! isset($entry['with']) || ! is_array($entry['with'])) {
                return 'remplacement sans fait juste (champ « with »)';
            }
            $parsed = SignallingSourcesFile::parse([$entry['with'] + ['line' => (string) $entry['line']]]);
            if ($parsed['errors'] !== []) {
                return 'fait juste refusé : '.$parsed['errors'][0];
            }
        } elseif (isset($entry['with'])) {
            return 'fait juste (« with ») seulement avec l\'action replace';
        }

        return null;
    }
}
