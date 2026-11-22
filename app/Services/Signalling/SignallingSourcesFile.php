<?php

namespace App\Services\Signalling;

use App\Enums\SignallingRole;

/**
 * Mises en service de signalisation datées par des documents (communiqués de SNCF Réseau, rapports du BEA-TT
 * et de l'EPSF, Journal officiel, presse, ouvrages), relevées à la main dans
 * `database/data/signalling_history_sources.php`. Lecture et contrôle du fichier, sans accès à la base :
 * `vff:import-signalling-sources` range ensuite les faits dans `line_signalling_events`.
 *
 * Un fait du fichier :
 *   line        : code SNCF Réseau à 6 chiffres (ou slug VFF) ;
 *   system      : BAL, BAPR, BM, CT, TVM-300, TVM-430, TVM, ETCS_L1, ETCS_L2, ETCS ou KVB ;
 *   date        : AAAA-MM-JJ, AAAA-MM ou AAAA ; la précision (day, month, year) se lit sur la forme ;
 *   scope       : whole_line, section (avec from et to : gares telles que les documents les nomment, ou
 *                 pk_start_m et pk_end_m : PK en mètres donnés par un document) ou unspecified ;
 *   replaces    : système remplacé quand un document le dit (information, la frise ne l'affiche pas) ;
 *   summary     : le fait, rédigé par VFF (jamais une phrase recopiée), 200 caractères au plus ;
 *   sources     : documents qui datent le fait, le plus sûr d'abord : publisher, title, url (https), published
 *                 (AAAA-MM-JJ, AAAA-MM ou AAAA, facultatif), official (organisme officiel : SNCF Réseau, État,
 *                 région, EPSF, BEA-TT, Journal officiel).
 * Règle de vérification : un document officiel, ou deux documents indépendants (`verification` en découle).
 * Fiabilité : high (jour ou mois), medium (année seule), comme les faits de Wikipédia.
 */
final class SignallingSourcesFile
{
    public const SCOPES = ['whole_line', 'section', 'unspecified'];

    public static function defaultPath(): string
    {
        return database_path('data/signalling_history_sources.php');
    }

    /**
     * Faits du fichier, contrôlés et mis à plat ; une erreur par fait refusé (le fait n'est pas gardé).
     *
     * @param  list<array<string, mixed>>  $entries
     * @return array{facts: list<array{line: string, system: string, role: string, date: string, precision: string, confidence: string,
     *     scope: string, section_from: ?string, section_to: ?string, pk_start_m: ?int, pk_end_m: ?int, replaces: ?string, summary: string,
     *     documents: list<array{publisher: string, title: string, url: string, published: ?string, official: bool}>, verification: string}>,
     *     errors: list<string>}
     */
    public static function parse(array $entries): array
    {
        $facts = [];
        $errors = [];
        foreach ($entries as $i => $entry) {
            $error = self::error($entry);
            if ($error !== null) {
                $errors[] = sprintf('fait n° %d (%s %s) : %s', $i + 1, $entry['line'] ?? '?', $entry['system'] ?? '?', $error);

                continue;
            }
            [$date, $precision] = self::date((string) $entry['date']);
            $documents = array_map(fn (array $s): array => [
                'publisher' => trim((string) $s['publisher']),
                'title' => trim((string) $s['title']),
                'url' => (string) $s['url'],
                'published' => isset($s['published']) ? (string) $s['published'] : null,
                'official' => (bool) ($s['official'] ?? false),
            ], array_values($entry['sources']));
            $section = $entry['scope'] === 'section';
            $facts[] = [
                'line' => (string) $entry['line'],
                'system' => (string) $entry['system'],
                'role' => SignallingRole::forSystem((string) $entry['system'])->value ?? 'block',
                'date' => $date,
                'precision' => $precision,
                'confidence' => $precision === 'year' ? 'medium' : 'high',
                'scope' => (string) $entry['scope'],
                'section_from' => $section && trim((string) ($entry['from'] ?? '')) !== '' ? trim((string) $entry['from']) : null,
                'section_to' => $section && trim((string) ($entry['to'] ?? '')) !== '' ? trim((string) $entry['to']) : null,
                'pk_start_m' => $section && isset($entry['pk_start_m']) ? (int) $entry['pk_start_m'] : null,
                'pk_end_m' => $section && isset($entry['pk_end_m']) ? (int) $entry['pk_end_m'] : null,
                'replaces' => isset($entry['replaces']) ? (string) $entry['replaces'] : null,
                'summary' => trim((string) $entry['summary']),
                'documents' => $documents,
                'verification' => in_array(true, array_column($documents, 'official'), true) ? 'official' : 'two_sources',
            ];
        }

        return ['facts' => $facts, 'errors' => $errors];
    }

    /**
     * Date du fichier en date complète et précision : « 2010-06-13 » (day), « 2010-06 » (month : jour 01),
     * « 2010 » (year : 1er janvier).
     *
     * @return array{0: string, 1: string}
     */
    public static function date(string $value): array
    {
        return match (true) {
            (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) => [$value, 'day'],
            (bool) preg_match('/^\d{4}-\d{2}$/', $value) => [$value.'-01', 'month'],
            default => [$value.'-01-01', 'year'],
        };
    }

    /** @param  mixed  $entry */
    private static function error($entry): ?string
    {
        if (! is_array($entry)) {
            return 'pas un tableau';
        }
        foreach (['line', 'system', 'date', 'scope', 'summary', 'sources'] as $key) {
            if (! isset($entry[$key]) || $entry[$key] === '' || $entry[$key] === []) {
                return "champ « {$key} » manquant";
            }
        }
        if (SignallingRole::forSystem((string) $entry['system']) === null || ! preg_match('/^(?:BAL|BAPR|BM|CT|TVM|TVM-300|TVM-430|ETCS|ETCS_L1|ETCS_L2|KVB)$/', (string) $entry['system'])) {
            return 'système inconnu';
        }
        if (isset($entry['replaces']) && SignallingRole::forSystem((string) $entry['replaces']) === null) {
            return 'système remplacé inconnu';
        }
        $value = (string) $entry['date'];
        if (! preg_match('/^\d{4}(?:-\d{2}(?:-\d{2})?)?$/', $value)) {
            return 'date illisible (AAAA, AAAA-MM ou AAAA-MM-JJ)';
        }
        [$date] = self::date($value);
        if (! checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))) {
            return 'date impossible';
        }
        if ($date > date('Y-m-d')) {
            return 'date future';
        }
        if (SignallingSystems::tooEarly((string) $entry['system'], (int) substr($date, 0, 4))) {
            return 'date antérieure au système';
        }
        if (! in_array($entry['scope'], self::SCOPES, true)) {
            return 'portée inconnue';
        }
        $pk = isset($entry['pk_start_m'], $entry['pk_end_m']) && is_int($entry['pk_start_m']) && is_int($entry['pk_end_m'])
            && $entry['pk_start_m'] >= 0 && $entry['pk_end_m'] > $entry['pk_start_m'];
        if ((isset($entry['pk_start_m']) || isset($entry['pk_end_m'])) && ! $pk) {
            return 'PK de la portion invalides (entiers en mètres, début avant fin)';
        }
        if ($entry['scope'] === 'section' && ! $pk && (trim((string) ($entry['from'] ?? '')) === '' || trim((string) ($entry['to'] ?? '')) === '')) {
            return 'portion sans ses deux extrémités';
        }
        if (mb_strlen((string) $entry['summary']) > 200) {
            return 'résumé de plus de 200 caractères';
        }
        if (preg_match('/[—–]|--/u', (string) $entry['summary'])) {
            return 'tiret long dans le résumé';
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

        return null;
    }
}
