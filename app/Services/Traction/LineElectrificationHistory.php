<?php

namespace App\Services\Traction;

use App\Models\Line;
use App\Services\LineStations;
use App\Services\Signalling\SignallingSectionLocator;
use App\Support\RedistributableDates;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Histoire de l'électrification d'une ligne : état actuel par tranche de PK (SNCF Réseau Open Data,
 * `sncf_line_electrification`) et mises sous tension, conversions de tension et désélectrifications
 * datées, relevées dans les chronologies d'articles (`line_events`, type « electrification »).
 *
 * Lecture PRUDENTE : seuls les faits sûrs sont rendus. Un événement est écarté si sa phrase décrit des
 * travaux, un projet ou une décision, si la date est une fourchette (« de 1924 à 1927 »), si l'année
 * n'apparaît pas dans la phrase citée, si la phrase ne dit pas qu'une portion a été mise sous tension,
 * ou si la date est antérieure à la première traction électrique (1900) ou à la première ligne en 50 Hz
 * pour une tension alternative (1951). Les sources de droits incertains (lignes-oubliees) ne sont pas
 * servies. Même lecture pour la fiche ligne, l'API REST, GraphQL et le serveur MCP.
 */
final class LineElectrificationHistory
{
    /** Écart (m) au-delà duquel deux tranches de même tension restent séparées. */
    public const MERGE_GAP_M = 50;

    public const DATASET = 'liste-des-lignes-electrifiees';

    public const DATASET_URL = 'https://ressources.data.sncf.com/explore/dataset/liste-des-lignes-electrifiees/';

    /** Sources dont les droits sont établis (les autres ne sont pas servies). */
    public const PROVIDERS = ['wikipedia', 'wikidata', 'manual'];

    private const EXPLICIT = '/(?:[ée]lectrifi(?:[ée]e?s?|er)\b|mise?s?\s+sous\s+tension|mise\s+en\s+service\s+de\s+l\'[ée]lectrification|d[ée]s[\s-]?[ée]lectrifi|hors[\s-]tension|cat[ée]naires?\s+(?:a|ont|est|sont)\s+[ée]t[ée]\s+d[ée]pos|d[ée]pos[ée]e?s?\s+(?:de\s+)?(?:la|les)\s+cat[ée]naires?)/iu';

    private const LIST_HEADING = '/liste,\s*«[^»]*[ée]lectrification/iu';

    private const RANGE = '/\b(?:entre|de|depuis)\s+(?:1[89]|20)\d{2}\s+(?:et|à|a)\s+(?:1[89]|20)\d{2}\b|\b(?:1[89]|20)\d{2}\s*[-\x{2013}]\s*(?:1[89]|20)\d{2}\b/iu';

    private const FUTURE = '/\b(?:sera|seront|devrait|devraient|doit\s+[êe]tre|pr[ée]vue?s?|programm[ée]e?s?|envisag[ée]e?s?|projet[ée]e?s?)\b|mise?s?\s+en\s+(?:œuvre|oeuvre|chantier)/iu';

    /** « Le plus long axe électrifié de France », « réseau électrifié », « 300 km électrifiés » : un état, pas un fait daté. */
    private const STATE_ONLY = '/(?:plus\s+(?:long|grand|important)s?\s+\w+\s+[ée]lectrifi|\b(?:axe|r[ée]seau|longueur|kilom[èe]tres?|km)s?\s+(?:de\s+(?:lignes?|voies?)\s+)?[ée]lectrifi)/iu';

    private const CONVERSION = '/(?:r[ée]-?[ée]lectrifi|conversion|converti|transform[ée]|remplac\w+|passage\s+(?:en|à|au|du)|changement\s+de\s+tension)/iu';

    public function __construct(private readonly SignallingSectionLocator $locator) {}

    /**
     * Charge complète d'une ligne.
     *
     * @return array{ranges: list<array<string, mixed>>, summary: array<string, mixed>,
     *     history: list<array<string, mixed>>, provenance: array<string, mixed>}
     */
    public function forLine(Line $line, bool $withExcerpt = true): array
    {
        $ranges = $this->ranges((string) ($line->code ?? ''));

        return [
            'ranges' => $ranges,
            'summary' => self::summary($ranges, $line),
            'history' => $this->history($line, $withExcerpt),
            'provenance' => self::provenance(),
        ];
    }

    /**
     * Tranches de PK électrifiées aujourd'hui, dans l'ordre des PK, voisines de même tension réunies.
     *
     * @return list<array{pk_start_m: int, pk_end_m: int, pk_start_label: string, pk_end_label: string,
     *     length_m: int, voltage_v: ?int, system: string}>
     */
    public function ranges(string $code): array
    {
        if ($code === '' || ! Schema::hasTable('sncf_line_electrification')) {
            return [];
        }
        $rows = DB::table('sncf_line_electrification')->where('code_ligne', $code)
            ->whereNotNull('pkd_m')->whereNotNull('pkf_m')
            ->get(['pkd_m', 'pkf_m', 'voltage_v', 'frequency_hz', 'third_rail'])
            ->map(fn (object $r): array => [
                'from' => min((int) $r->pkd_m, (int) $r->pkf_m),
                'to' => max((int) $r->pkd_m, (int) $r->pkf_m),
                'v' => $r->voltage_v === null ? null : (int) $r->voltage_v,
                'system' => self::system((bool) $r->third_rail, $r->frequency_hz === null ? null : (float) $r->frequency_hz),
            ])->filter(fn (array $r): bool => $r['to'] > $r['from'])
            ->sortBy([['from', 'asc'], ['to', 'asc']])->values()->all();

        return array_map(fn (array $r): array => [
            'pk_start_m' => $r['from'],
            'pk_end_m' => $r['to'],
            'pk_start_label' => (string) LineStations::pkLabel($r['from']),
            'pk_end_label' => (string) LineStations::pkLabel($r['to']),
            'length_m' => $r['to'] - $r['from'],
            'voltage_v' => $r['v'],
            'system' => $r['system'],
        ], self::merge($rows));
    }

    /** `third_rail`, `ac` (alternatif), `dc` (continu) ou `unknown`. */
    public static function system(bool $thirdRail, ?float $hz): string
    {
        return $thirdRail ? 'third_rail' : ($hz === null ? 'unknown' : ($hz > 0 ? 'ac' : 'dc'));
    }

    /**
     * @param  list<array{from: int, to: int, v: ?int, system: string}>  $rows  triées par PK de début
     * @return list<array{from: int, to: int, v: ?int, system: string}>
     */
    public static function merge(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $i = $out === [] ? null : array_key_last($out);
            if ($i !== null && $out[$i]['v'] === $r['v'] && $out[$i]['system'] === $r['system'] && $r['from'] - $out[$i]['to'] <= self::MERGE_GAP_M) {
                $out[$i]['to'] = max($out[$i]['to'], $r['to']);

                continue;
            }
            $out[] = $r;
        }

        return $out;
    }

    /**
     * `electrified_m` : réunion des tranches. Deux itinéraires d'une même ligne (rg_troncon 1 et 2) peuvent se
     * recouvrir sous deux tensions (890000 : 1 500 V et 25 kV sur 15 km) ; la somme les compterait deux fois.
     * `by_voltage` garde la longueur sous chaque tension, itinéraires compris.
     *
     * @param  list<array{pk_start_m: int, length_m: int, voltage_v: ?int, system: string}>  $ranges
     * @return array{line_electrified: bool, line_voltage_v: ?int, electrified_m: int, by_voltage: list<array{voltage_v: ?int, system: string, length_m: int}>}
     */
    public static function summary(array $ranges, ?Line $line = null): array
    {
        $by = [];
        foreach ($ranges as $r) {
            $k = ($r['voltage_v'] ?? 0).'|'.$r['system'];
            $by[$k] = ['voltage_v' => $r['voltage_v'], 'system' => $r['system'], 'length_m' => ($by[$k]['length_m'] ?? 0) + $r['length_m']];
        }
        $by = array_values($by);
        usort($by, fn (array $a, array $b): int => $b['length_m'] <=> $a['length_m']);

        return [
            'line_electrified' => (bool) ($line->electrified ?? false),
            'line_voltage_v' => $line?->voltage_v === null ? null : (int) $line->voltage_v,
            'electrified_m' => self::unionLength($ranges),
            'by_voltage' => $by,
        ];
    }

    /**
     * Longueur de la réunion des tranches (PK), sans compter deux fois un recouvrement.
     *
     * @param  list<array{pk_start_m: int, length_m: int}>  $ranges
     */
    private static function unionLength(array $ranges): int
    {
        $spans = array_map(fn (array $r): array => [$r['pk_start_m'], $r['pk_start_m'] + $r['length_m']], $ranges);
        usort($spans, fn (array $a, array $b): int => $a[0] <=> $b[0]);
        $total = 0;
        $end = null;
        foreach ($spans as [$from, $to]) {
            if ($end === null || $from > $end) {
                $total += $to - $from;
                $end = $to;
            } elseif ($to > $end) {
                $total += $to - $end;
                $end = $to;
            }
        }

        return $total;
    }

    /**
     * Mises sous tension, conversions et désélectrifications datées et sûres, du plus ancien au plus récent.
     *
     * @return list<array{kind: string, voltage_v: ?int, system: ?string, date: string, date_precision: string,
     *     reliability: string, scope: string,
     *     section: array{from: ?string, to: ?string, pk_start_m: ?int, pk_end_m: ?int}|null,
     *     source: array{provider: string, url: ?string, excerpt: ?string}}>
     */
    public function history(Line $line, bool $withExcerpt = true): array
    {
        // Servi seulement hors du site (REST, GraphQL, MCP) : jamais un événement sans source redistribuable.
        $rows = DB::table('line_events')->where('line_id', $line->id)->where('type', 'electrification')
            ->whereIn('source', self::PROVIDERS)->whereRaw(app(RedistributableDates::class)->lineEventKept('line_events'))
            ->orderBy('event_date')->orderBy('id')
            ->get(['event_date', 'date_precision', 'description', 'source', 'source_quote', 'source_url', 'confidence',
                'section_from', 'section_to', 'section_pk_start_m', 'section_pk_end_m', 'section_resolution']);
        $out = [];
        $seen = [];
        foreach ($rows as $row) {
            $fact = self::read((array) $row);
            if ($fact === null) {
                continue;
            }
            $key = implode('|', [$fact['date'], $fact['kind'], $fact['section']['from'] ?? '', $fact['section']['to'] ?? '', $fact['scope']]);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            if ($fact['section'] !== null && $fact['section']['pk_start_m'] === null) {
                $place = $this->locator->locate((int) $line->id, 'section', $fact['section']['from'], $fact['section']['to'], null, null);
                $fact['section']['pk_start_m'] = $place['pk_start_m'];
                $fact['section']['pk_end_m'] = $place['pk_end_m'];
            }
            if (! $withExcerpt) {
                $fact['source']['excerpt'] = null;
            }
            $out[] = $fact;
        }

        // Wikidata ne donne qu'une date pour toute la ligne : écartée dès qu'un article en donne le détail.
        if (array_filter($out, fn (array $f): bool => $f['source']['provider'] === 'wikipedia') !== []) {
            $out = array_values(array_filter($out, fn (array $f): bool => $f['source']['provider'] !== 'wikidata'));
        }

        return $out;
    }

    /**
     * Lit un événement : le fait sûr qu'il porte, ou null s'il est ambigu.
     *
     * @param  array<string, mixed>  $e  ligne de `line_events`
     * @return array<string, mixed>|null
     */
    public static function read(array $e, ?string $today = null): ?array
    {
        $date = (string) $e['event_date'];
        $year = (int) substr($date, 0, 4);
        if ($year < ElectrificationTimeline::FIRST_YEAR || $date > ($today ?? date('Y-m-d'))) {
            return null;
        }
        $quote = trim((string) ($e['source_quote'] ?? ''));
        $description = trim((string) ($e['description'] ?? ''));
        $provider = (string) $e['source'];
        $text = $quote !== '' ? $quote : $description;
        $kind = ElectrificationText::kind($text);
        if ($kind === ElectrificationText::KIND_IGNORE || preg_match(self::FUTURE, $text) || self::stateOnlyAround($text, $year)) {
            return null;
        }
        $precision = (string) ($e['date_precision'] ?? 'year');
        $dateInText = true;
        $structured = $provider === 'wikidata' || $provider === 'manual';
        $listed = (bool) preg_match(self::LIST_HEADING, $description);
        $infobox = str_starts_with($quote, 'Infobox');
        $voltage = ElectrificationText::voltage($text) ?? ($structured ? ElectrificationText::voltage($description) : null);
        if ($infobox) {
            // « électrification = 1988 ; électrification2 = 1989 » : une date par portion, la portion n'est pas dite.
            if (! str_contains($quote, (string) $year)) {
                return null;
            }
        } elseif (! $structured && ! $listed) {
            // Une fourchette (« de 1924 à 1927 ») ne date pas une mise sous tension, sauf si l'année retenue
            // figure aussi hors de la fourchette (« entre 1926 et 1929 : Dax - Hendaye en 1926 »).
            $outside = (string) preg_replace(self::RANGE, ' ', $text);
            $fullDate = in_array($precision, ['day', 'month'], true)
                && (bool) preg_match('/\b(?:janvier|f[ée]vrier|mars|avril|mai|juin|juillet|ao[uû]t|septembre|octobre|novembre|d[ée]cembre)\s+'.$year.'\b/iu', $outside);
            $yearIn = (bool) preg_match('/(?<!\d)'.$year.'(?!\d)/', $outside);
            if (! $yearIn && ! $fullDate && preg_match(self::RANGE, $text)) {
                return null;
            }
            $noun = (bool) preg_match('/[ée]lectrification\s+(?:en|par|de|du|des|d\'|sur)/iu', $text) && ($voltage !== null || $fullDate);
            $fromDescription = $fullDate && (bool) preg_match('/^[ée]lectrification\b/iu', $description);
            if (! preg_match(self::EXPLICIT, $text) && ! $noun && ! $fromDescription) {
                return null;
            }
            // Année absente de la phrase citée : la date vient d'une proposition voisine ; seule une date
            // précise (jour ou mois) avec une tension citée est retenue.
            // Une confiance haute ou moyenne de l'extracteur (date lue dans l'infobox ou dans le paragraphe de
            // la section) suffit quand la phrase dit explicitement la mise en service de l'électrification.
            $sure = in_array((string) ($e['confidence'] ?? 'low'), ['high', 'medium'], true)
                && (bool) preg_match('/mise\s+en\s+service\s+de\s+l\'[ée]lectrification|mises?\s+sous\s+tension/iu', $text);
            $dateInText = $yearIn || $fullDate;
            if (! $yearIn && ! $fullDate && ! $sure && ! (in_array($precision, ['day', 'month'], true) && $voltage !== null)) {
                return null;
            }
        }
        if ($kind === ElectrificationText::KIND_ELECTRIFICATION && $voltage !== null && $voltage >= 20000 && $year < ElectrificationTimeline::FIRST_AC50_YEAR) {
            return null;
        }
        $isConversion = $kind === ElectrificationText::KIND_ELECTRIFICATION
            && (($voltage !== null && (bool) preg_match(self::CONVERSION, $text)) || (bool) preg_match('/r[ée]-?[ée]lectrifi/iu', $text));
        $names = ($e['section_from'] ?? null) !== null && ($e['section_to'] ?? null) !== null;
        $pk = ($e['section_pk_start_m'] ?? null) !== null && ($e['section_pk_end_m'] ?? null) !== null;
        $scope = ($names || $pk) ? 'section' : (($e['section_resolution'] ?? null) === 'whole_line' && $dateInText && ! $infobox && ! preg_match('/\p{Lu}[\p{L}\'-]+\s+[\x{2013}-]\s+\p{Lu}/u', $text) ? 'whole_line' : 'unspecified');
        $deelec = $kind === ElectrificationText::KIND_DEELECTRIFICATION;

        return [
            'kind' => $deelec ? 'deelectrification' : ($isConversion ? 'conversion' : 'electrification'),
            'voltage_v' => $deelec ? null : $voltage,
            'system' => $deelec || $voltage === null ? null : self::systemOf($voltage, $text),
            'date' => $date,
            'date_precision' => $precision,
            'reliability' => in_array($precision, ['day', 'month'], true) && ! $structured ? 'high' : 'medium',
            'scope' => $scope,
            'section' => $scope === 'section' ? [
                'from' => $e['section_from'] ?? null, 'to' => $e['section_to'] ?? null,
                'pk_start_m' => $pk ? min((int) $e['section_pk_start_m'], (int) $e['section_pk_end_m']) : null,
                'pk_end_m' => $pk ? max((int) $e['section_pk_start_m'], (int) $e['section_pk_end_m']) : null,
            ] : null,
            'source' => [
                'provider' => $provider,
                'url' => ($e['source_url'] ?? null) !== null ? (string) $e['source_url'] : null,
                'excerpt' => $provider === 'wikipedia' ? ($text === '' ? null : mb_substr($text, 0, 255)) : null,
            ],
        ];
    }

    /** La proposition qui cite l'année décrit un état (« depuis 1938 le plus long axe électrifié »), pas un fait daté. */
    private static function stateOnlyAround(string $text, int $year): bool
    {
        foreach (preg_split('/[,;:]/u', $text) ?: [] as $clause) {
            if (str_contains($clause, (string) $year) && preg_match(self::STATE_ONLY, $clause)) {
                return true;
            }
        }

        return false;
    }

    /** Nature du courant d'après la tension et le texte : `third_rail`, `ac`, `dc`. */
    public static function systemOf(int $voltage, string $text): string
    {
        if (preg_match('/troisi[èe]me\s+rail|rail\s+lat[ée]ral/iu', $text)) {
            return 'third_rail';
        }

        return $voltage >= 10000 ? 'ac' : 'dc';
    }

    /** @return array{ranges: array<string, string>, history: array<string, string>} */
    public static function provenance(): array
    {
        return [
            'ranges' => [
                'provider' => 'SNCF Réseau', 'dataset' => self::DATASET, 'url' => self::DATASET_URL, 'license' => 'ODbL-1.0',
                'mention' => 'SNCF Réseau, Open Data (ODbL)',
            ],
            'history' => [
                'provider' => 'Wikipédia, Wikidata', 'license' => 'CC BY-SA 4.0, CC0 1.0',
                'mention' => 'Faits relevés dans les articles de Wikipédia (CC BY-SA 4.0), lien vers la révision lue, et dans Wikidata (CC0)',
            ],
        ];
    }
}
