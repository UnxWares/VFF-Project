<?php

namespace App\Services;

use App\Services\Greenways\LineGreenways;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * « Chemins de fer touristiques et vélorails » : les exploitations actuelles (trains touristiques, vélorails) et les
 * voies vertes sur ancienne voie. Page `/tourist-railways` et `GET /api/v1/tourist-railways`. Lecture seule.
 *
 * Deux sources croisées :
 *   - les exploitations que `vff:import-heritage-lines` a écrites dans `line_events` (`heritage_start` /
 *     `heritage_end`, source `heritage`) : lignes parcourues, longueur exploitée (somme de `section_length_m`), année de
 *     début quand une source la donne, exploitant quand la description le nomme, sources citées ; une exploitation
 *     dont le dernier événement est une fin n'est pas affichée ;
 *   - le catalogue versionné `database/data/chemins_de_fer_touristiques.php` (Wikipédia, Wikidata) : type, écartement,
 *     région, départements, site officiel, articles. Un nom du catalogue qui ressemble à une exploitation de la
 *     base (HeritageText::nameSimilarity, même type) la complète ; une exploitation du catalogue sans
 *     équivalent en base est affichée sans longueur, avec la fiche de ligne si la ligne que Wikipédia lui donne existe
 *     (`lines.wikipedia_title`) ; une exploitation de la base absente du catalogue (repérée par l'IGN ou OpenStreetMap)
 *     est affichée avec ses seuls faits de la base.
 *
 * Faits facultatifs du catalogue, relevés dans l'article Wikipédia de l'exploitation (version citée par
 * `detail_source`) : `section_from` / `section_to` (lieux de départ et d'arrivée de la section exploitée, pas les gares
 * SNCF voisines), `season` (saison d'exploitation, en texte) et `opened_on` (jour d'ouverture touristique, AAAA-MM-JJ) ;
 * servis par l'API sous `section`, `season`, `opened_on` et `detail_source_url`.
 *
 * Chaque fait porte sa source (`sources`, clé = fait, valeur = clé du registre SOURCES). Les voies vertes viennent
 * de `line_greenways` (OpenStreetMap, ODbL) : seules celles d'au moins GREENWAY_MIN_M sur l'ancienne emprise, nommées.
 */
final class TouristRailways
{
    public const CACHE_KEY = 'tourist_railways:v1';

    public const CATALOGUE = 'database/data/chemins_de_fer_touristiques.php';

    /** Voie verte retenue : longueur minimale (m) sur l'ancienne emprise. */
    public const GREENWAY_MIN_M = 5000;

    /** Types d'exploitation, dans l'ordre d'affichage des filtres. */
    public const TYPES = ['steam', 'railcar', 'tram', 'rack', 'velorail', 'train'];

    /** Ressemblance minimale (HeritageText::nameSimilarity) entre un nom du catalogue et une exploitation de la base. */
    private const MATCH_MIN = 0.65;

    /** Mots trop courants pour dire que deux noms désignent la même exploitation (« vallée », « pays », « vélo-rail »…). */
    private const GENERIC = ['vallee', 'pays', 'haut', 'bas', 'val', 'petit', 'grand', 'grande', 'nord', 'sud', 'est', 'ouest', 'centre',
        'ligne', 'touristique', 'velo', 'rail', 'velorail', 'cyclorail', 'draisine', 'draisines', 'pedalorail', 'rando', 'train', 'tramway', 'musee', 'chemin', 'fer'];

    /** @var array<string, array{label: string, url: ?string, licence: ?string}> */
    public const SOURCES = [
        'wp-list' => ['label' => 'Wikipédia, liste des chemins de fer touristiques de France', 'url' => 'https://fr.wikipedia.org/wiki/Liste_des_chemins_de_fer_touristiques_de_France', 'licence' => 'CC BY-SA 4.0'],
        'wp-article' => ['label' => "Wikipédia, article de l'exploitation", 'url' => null, 'licence' => 'CC BY-SA 4.0'],
        'official-site' => ['label' => 'Site officiel de l’exploitation', 'url' => null, 'licence' => null],
        'wp-line' => ['label' => 'Wikipédia, article de la ligne', 'url' => null, 'licence' => 'CC BY-SA 4.0'],
        'correction' => ['label' => 'VFF (correction sourcée)', 'url' => null, 'licence' => null],
        'wikidata' => ['label' => 'Wikidata', 'url' => 'https://www.wikidata.org/', 'licence' => 'CC0'],
        'ign' => ['label' => 'IGN BD TOPO', 'url' => 'https://geoservices.ign.fr/bdtopo', 'licence' => 'Licence Ouverte 2.0'],
        'osm' => ['label' => 'OpenStreetMap', 'url' => 'https://www.openstreetmap.org/copyright', 'licence' => 'ODbL 1.0'],
        'lignes-oubliees' => ['label' => 'lignes-oubliees.com', 'url' => 'https://www.lignes-oubliees.com/', 'licence' => null],
        'vff' => ['label' => 'VFF (fiche de la ligne)', 'url' => null, 'licence' => null],
    ];

    /** Libellés des sources que cite la description d'une exploitation, vers les clés du registre. */
    private const DESCRIPTION_SOURCES = [
        'Wikipédia (liste)' => 'wp-list', 'Wikipédia (article)' => 'wp-article', 'Wikidata' => 'wikidata',
        'IGN BD TOPO' => 'ign', 'OpenStreetMap' => 'osm', 'lignes-oubliees' => 'lignes-oubliees',
    ];

    public function __construct(private readonly LineGreenways $greenways) {}

    /**
     * Données de la page et de l'API, gardées en cache une heure.
     *
     * @return array{railways: list<array<string, mixed>>, greenways: list<array<string, mixed>>, sources: array<string, array<string, ?string>>, regions: list<string>, types: list<string>}
     */
    public function get(): array
    {
        return Cache::remember(self::CACHE_KEY, 3600, fn (): array => $this->build());
    }

    /** @return array{railways: list<array<string, mixed>>, greenways: list<array<string, mixed>>, sources: array<string, array<string, ?string>>, regions: list<string>, types: list<string>} */
    public function build(): array
    {
        $railways = $this->railways();
        $regions = [];
        foreach ($railways as $r) {
            foreach ($r['regions'] as $region) {
                $regions[$region] = true;
            }
        }
        $greenways = $this->greenwaySection();
        foreach ($greenways as $g) {
            foreach ($g['regions'] as $region) {
                $regions[$region] = true;
            }
        }
        ksort($regions);

        return [
            'railways' => $railways,
            'greenways' => $greenways,
            'sources' => self::SOURCES,
            'regions' => array_keys($regions),
            'types' => array_values(array_filter(self::TYPES, fn (string $t): bool => collect($railways)->contains('type', $t))),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function railways(): array
    {
        $groups = $this->databaseGroups();
        $catalogue = $this->catalogue();
        $lineByTitle = $this->linesByWikipediaTitle($catalogue);
        $lineBySlug = $this->linesBySlug($catalogue);
        $out = [];

        // Appariement global, du meilleur score au moins bon : un nom du catalogue ne prend pas une exploitation qu'un
        // nom plus proche réclame, et chaque exploitation de la base n'est prise qu'une fois par type.
        $pairs = [];
        foreach ($catalogue as $e => $entry) {
            foreach ($entry['kinds'] as $entryKind) {
                $kind = $entryKind === 'velorail' ? 'velorail' : 'tourist_train';
                foreach ($groups as $i => $g) {
                    if ($g['kind'] !== $kind) {
                        continue;
                    }
                    $score = $this->score($entry, $g, $lineByTitle);
                    if ($score >= self::MATCH_MIN) {
                        $pairs[] = [$score, $e, $kind, $i];
                    }
                }
            }
        }
        usort($pairs, fn (array $a, array $b): int => $b[0] <=> $a[0]);
        $used = [];
        $matched = [];
        foreach ($pairs as [, $e, $kind, $i]) {
            if (isset($used[$i]) || isset($matched[$e][$kind])) {
                continue;
            }
            $used[$i] = true;
            $matched[$e][$kind] = $i;
        }

        foreach ($catalogue as $e => $entry) {
            if (empty($matched[$e])) {
                $out[] = $this->fromCatalogueOnly($entry, $lineByTitle, $lineBySlug);

                continue;
            }
            // Association qui exploite un train et un vélorail : un enregistrement par exploitation de la base.
            foreach ($matched[$e] as $i) {
                $out[] = $this->fromBoth($entry, $groups[$i], $lineBySlug);
            }
        }
        foreach ($groups as $i => $g) {
            if (! isset($used[$i])) {
                $out[] = $this->fromDatabaseOnly($g);
            }
        }

        usort($out, fn (array $a, array $b): int => strcmp(HeritageText::norm($a['name']), HeritageText::norm($b['name'])));
        foreach ($out as $i => &$r) {
            $r['id'] = $this->slug($r['name']).'-'.$r['type'];
        }
        unset($r);

        return $this->uniqueIds($out);
    }

    /**
     * Exploitations en cours d'après line_events : regroupées par nom et type, avec leurs lignes.
     *
     * @return list<array<string, mixed>>
     */
    private function databaseGroups(): array
    {
        $rows = DB::select(<<<'SQL'
            SELECT e.id, e.line_id, l.slug, l.name AS line_name, l.regions, l.gauge_mm_default, l.wikipedia_title,
                   e.type, e.heritage_kind AS kind, e.event_date, e.date_precision, e.confidence, e.description, e.source,
                   e.source_url, e.section_length_m,
                   CASE WHEN e.section_geom IS NULL THEN NULL
                        ELSE ST_X(ST_ClosestPoint(e.section_geom, ST_Centroid(e.section_geom))) END AS lng,
                   CASE WHEN e.section_geom IS NULL THEN NULL
                        ELSE ST_Y(ST_ClosestPoint(e.section_geom, ST_Centroid(e.section_geom))) END AS lat
            FROM line_events e
            JOIN lines l ON l.id = e.line_id
            WHERE e.heritage_kind IN ('tourist_train', 'velorail')
              AND e.type IN ('heritage_start', 'heritage_end', 'other')
            ORDER BY e.line_id, e.event_date, e.id
            SQL);

        // 1. Événements de l'import, par ligne : nom, type, début ou fin.
        $perLine = [];
        $hints = [];
        foreach ($rows as $row) {
            if ($row->source !== 'heritage') {
                $hints[$row->line_id][$row->kind][] = $row;

                continue;
            }
            $parsed = $this->parse((string) $row->description);
            if ($parsed === null) {
                continue;
            }
            $kind = $row->kind === 'tourist_train' && preg_match('/^v[ée]los?[ -]?rails?\b|^cyclo|^draisines?|^rando[ -]?rail|^p[ée]dalo/iu', $parsed['name']) ? 'velorail' : $row->kind;
            $perLine[$row->line_id][$kind][] = [...$parsed, 'row' => $row];
        }

        // 2. Une exploitation par ligne et nom : en cours si son dernier événement est un début.
        $groups = [];
        foreach ($perLine as $lineId => $kinds) {
            foreach ($kinds as $kind => $events) {
                $byName = [];
                foreach ($events as $ev) {
                    $placed = false;
                    foreach ($byName as &$bucket) {
                        if (HeritageText::nameSimilarity($bucket['name'], $ev['name']) >= 0.75) {
                            $bucket['events'][] = $ev;
                            $placed = true;
                            break;
                        }
                    }
                    unset($bucket);
                    if (! $placed) {
                        $byName[] = ['name' => $ev['name'], 'events' => [$ev]];
                    }
                }
                foreach ($byName as $bucket) {
                    $last = end($bucket['events']);
                    if ($last['end']) {
                        continue;
                    }
                    // Début : le premier événement qui n'est ni une reprise ni une simple attestation ; longueur et
                    // portion : le dernier événement qui en porte une.
                    $first = null;
                    $withLength = $last['row'];
                    $length = 0;
                    foreach ($bucket['events'] as $ev) {
                        if (! $ev['end'] && ! $ev['resume'] && ! $ev['attested'] && $first === null) {
                            $first = $ev;
                        }
                        if (! $ev['end'] && (int) $ev['row']->section_length_m > 0) {
                            $withLength = $ev['row'];
                            // Une reprise rouvre la portion déjà comptée par le début : elle n'ajoute pas de longueur.
                            $length += $ev['resume'] ? 0 : (int) $ev['row']->section_length_m;
                        }
                    }
                    $start = $withLength;
                    $count = count($byName);
                    $extra = $first === null ? $this->hintYear($hints[$lineId][$kind] ?? [], $bucket['name'], $count) : [];
                    $year = $first !== null ? (int) substr((string) $first['row']->event_date, 0, 4) : ($extra['year'] ?? null);
                    $operator = null;
                    $sources = [];
                    foreach ($bucket['events'] as $ev) {
                        $operator = $ev['operator'] ?? $operator;
                        $sources = [...$sources, ...$ev['sources']];
                    }
                    $groups[] = [
                        'name' => $bucket['name'], 'kind' => $kind, 'operator' => $operator,
                        'line_id' => $lineId, 'slug' => $start->slug, 'line_name' => $start->line_name,
                        'regions' => json_decode((string) $start->regions, true) ?: [], 'gauge' => $start->gauge_mm_default,
                        'wikipedia_title' => $start->wikipedia_title,
                        'length_m' => $length > 0 ? $length : (int) $start->section_length_m, 'lat' => $start->lat, 'lng' => $start->lng,
                        'year' => $year, 'year_from' => $first !== null ? ($first['date_source'] ?? 'vff') : ($extra['source'] ?? null),
                        'sources' => array_values(array_unique($sources)), 'source_url' => $start->source_url,
                    ];
                }
            }
        }

        return $this->mergeAcrossLines($groups);
    }

    /**
     * Analyse la description d'un événement de l'import : « Exploitation associative : NOM (exploitant : X), train
     * touristique entre A et B, depuis 1986 (…) ; aussi nommé … Sources : … ».
     *
     * @return array{date_source: ?string, name: string, operator: ?string, end: bool, resume: bool, attested: bool, sources: list<string>}|null
     */
    private function parse(string $description): ?array
    {
        if (! preg_match('/^(Exploitation associative|Reprise de l\'exploitation associative|Fin de l\'exploitation associative|Interruption de l\'exploitation associative) : (.+?)(?: \(exploitant : (.+?)\))?, (train touristique|vélorail|musée)/u', $description, $m)) {
            return null;
        }
        $sources = [];
        if (preg_match('/Sources : (.+?)\.?$/u', $description, $s)) {
            foreach (array_map('trim', explode(',', $s[1])) as $label) {
                if (isset(self::DESCRIPTION_SOURCES[$label])) {
                    $sources[] = self::DESCRIPTION_SOURCES[$label];
                }
            }
        }

        $dateSource = null;
        if (preg_match('/\(d\'après ([^)]+)\)/u', $description, $d)) {
            $dateSource = match (true) {
                str_contains($d[1], 'exploitation') || str_contains($d[1], 'infobox') => 'wp-article',
                str_contains($d[1], 'Wikipédia') => 'wp-line',
                str_contains($d[1], 'Wikidata') => 'wikidata',
                str_contains($d[1], 'lignes-oubliees') => 'lignes-oubliees',
                default => null,
            };
        }

        return [
            'date_source' => $dateSource,
            'name' => trim($m[2]),
            // Un nom d'exploitation (« CF Saint-Georges… ») n'est pas un exploitant.
            'operator' => $m[3] !== '' && ! preg_match('/^(?:CF|Chemin|Train|V[ée]lo)\b/iu', trim($m[3])) ? trim($m[3]) : null,
            'end' => str_starts_with($m[1], 'Fin') || str_starts_with($m[1], 'Interruption'),
            'resume' => str_starts_with($m[1], 'Reprise'),
            'attested' => str_contains($description, 'date de début non trouvée'),
            'sources' => array_values(array_unique($sources)),
        ];
    }

    /**
     * Année de début donnée par un événement manuel (correction sourcée) de la même ligne et du même type : seulement
     * quand la ligne n'a qu'une exploitation de ce type, ou que le nom y est cité.
     *
     * @param  list<object>  $rows
     * @return array{year?: int, source?: string}
     */
    private function hintYear(array $rows, string $name, int $sameKindCount): array
    {
        foreach ($rows as $row) {
            $text = (string) $row->description;
            $year = (int) substr((string) $row->event_date, 0, 4);
            $named = HeritageText::nameSimilarity($text, $name) >= 0.5;
            if (! $named && $sameKindCount !== 1) {
                continue;
            }
            if ($row->type === 'heritage_start' && $year >= HeritageText::MIN_YEAR && $year < 2026) {
                return ['year' => $year, 'source' => 'correction'];
            }
            if ($row->type === 'other' && preg_match('/(?:commenc\w+|ouvert|depuis|circule)\s+(?:en\s+|le\s+)?(?:\d{1,2}(?:er)?\s+\p{L}+\s+)?((?:19|20)\d{2})/u', $text, $y) && (int) $y[1] >= HeritageText::MIN_YEAR) {
                return ['year' => (int) $y[1], 'source' => 'correction'];
            }
        }

        return [];
    }

    /**
     * Fusionne les exploitations de même type et de même nom sur plusieurs lignes (le Haut-Forez roule sur deux lignes) :
     * longueurs additionnées, lignes listées, année la plus ancienne.
     *
     * @param  list<array<string, mixed>>  $groups
     * @return list<array<string, mixed>>
     */
    private function mergeAcrossLines(array $groups): array
    {
        $merged = [];
        foreach ($groups as $g) {
            $placed = false;
            foreach ($merged as &$m) {
                if ($m['kind'] === $g['kind'] && HeritageText::nameSimilarity($m['name'], $g['name']) >= 0.75) {
                    $m['lines'][] = ['slug' => $g['slug'], 'name' => $g['line_name'], 'length_m' => $g['length_m'], 'wikipedia_title' => $g['wikipedia_title']];
                    $m['length_m'] += $g['length_m'];
                    $m['regions'] = array_values(array_unique([...$m['regions'], ...$g['regions']]));
                    if ($g['year'] !== null && ($m['year'] === null || $g['year'] < $m['year'])) {
                        [$m['year'], $m['year_from']] = [$g['year'], $g['year_from']];
                    }
                    $m['operator'] ??= $g['operator'];
                    $m['sources'] = array_values(array_unique([...$m['sources'], ...$g['sources']]));
                    $placed = true;
                    break;
                }
            }
            unset($m);
            if (! $placed) {
                $merged[] = [...$g, 'lines' => [['slug' => $g['slug'], 'name' => $g['line_name'], 'length_m' => $g['length_m'], 'wikipedia_title' => $g['wikipedia_title']]]];
            }
        }

        return $merged;
    }

    /** @return list<array<string, mixed>> */
    private function catalogue(): array
    {
        $path = base_path(self::CATALOGUE);

        return is_file($path) ? (array) require $path : [];
    }

    /**
     * Lignes de la base dont le titre d'article Wikipédia (ou le nom) est un titre donné par le catalogue : titre → [slug, nom].
     *
     * @param  list<array<string, mixed>>  $catalogue
     * @return array<string, array{slug: string, name: string}>
     */
    private function linesByWikipediaTitle(array $catalogue): array
    {
        $titles = [];
        foreach ($catalogue as $e) {
            foreach ($e['lines'] as $t) {
                $titles[$t] = true;
            }
        }
        if ($titles === []) {
            return [];
        }
        $list = array_keys($titles);
        $rows = DB::table('lines')->whereIn('wikipedia_title', $list)->orWhereIn('name', $list)->get(['slug', 'name', 'wikipedia_title']);
        $out = [];
        foreach ($rows as $r) {
            foreach (array_filter([$r->wikipedia_title, $r->name]) as $title) {
                if (isset($titles[$title])) {
                    $out[$title] ??= ['slug' => $r->slug, 'name' => $r->name];
                }
            }
        }

        return $out;
    }

    /**
     * Ressemblance d'une entrée du catalogue et d'une exploitation de la base : nom, plus un bonus quand la ligne
     * que Wikipédia lui donne est une des lignes de l'exploitation.
     *
     * @param  array<string, mixed>  $entry
     * @param  array<string, mixed>  $g
     * @param  array<string, array{slug: string, name: string}>  $lineByTitle
     */
    private function score(array $entry, array $g, array $lineByTitle): float
    {
        $score = $this->similarity($entry['name'], $g['name']);
        $slugs = array_column($g['lines'], 'slug');
        foreach ($entry['lines'] as $title) {
            if (isset($lineByTitle[$title]) && in_array($lineByTitle[$title]['slug'], $slugs, true)) {
                return max($score, 0.5) + 0.25;
            }
        }

        return $score;
    }

    /**
     * Fiches de ligne désignées à la main par le catalogue (`line_slugs`) : slug → [nom, écartement].
     *
     * @param  list<array<string, mixed>>  $catalogue
     * @return array<string, array{name: string, gauge: ?int, length_m: int}>
     */
    private function linesBySlug(array $catalogue): array
    {
        $slugs = array_values(array_unique(array_merge(...array_map(fn (array $e): array => $e['line_slugs'] ?? [], $catalogue))));
        if ($slugs === []) {
            return [];
        }
        $out = [];
        $lengths = DB::table('line_segments as s')->join('eras as e', 'e.id', '=', 's.era_id')->join('lines as l', 'l.id', '=', 's.line_id')
            ->where('e.is_current', true)->whereIn('l.slug', $slugs)->groupBy('l.slug')->selectRaw('l.slug, SUM(s.length_meters) AS m')
            ->pluck('m', 'slug');
        foreach (DB::table('lines')->whereIn('slug', $slugs)->get(['slug', 'name', 'gauge_mm_default']) as $r) {
            $out[$r->slug] = ['name' => $r->name, 'gauge' => $r->gauge_mm_default, 'length_m' => (int) ($lengths[$r->slug] ?? 0)];
        }

        return $out;
    }

    /** Ressemblance de deux noms sur leurs seuls mots distinctifs (sans « vallée », « pays », « vélo-rail »…). */
    private function similarity(string $a, string $b): float
    {
        $ta = array_values(array_diff(HeritageText::nameTokens($a), self::GENERIC));
        $tb = array_values(array_diff(HeritageText::nameTokens($b), self::GENERIC));
        if ($ta === [] || $tb === []) {
            return 0.0;
        }
        $common = count(array_intersect($ta, $tb));

        return 0.5 * $common / min(count($ta), count($tb)) + 0.5 * $common / max(count($ta), count($tb));
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  array<string, mixed>  $g
     * @param  array<string, array{name: string, gauge: ?int, length_m: int}>  $lineBySlug
     * @return array<string, mixed>
     */
    private function fromBoth(array $entry, array $g, array $lineBySlug = []): array
    {
        $r = $this->fromCatalogueOnly($entry, [], [], $g['kind']);
        $r['origin'] = 'both';
        $r['name'] = $g['name'];
        $r['type'] = $g['kind'] === 'velorail' ? 'velorail' : ($entry['type'] === 'velorail' ? 'train' : $entry['type']);
        $r['lines'] = $this->lineLinks($g['lines']);
        foreach ($entry['line_slugs'] ?? [] as $slug) {
            if (isset($lineBySlug[$slug]) && ! in_array($slug, array_column($r['lines'], 'slug'), true)) {
                $r['lines'][] = ['slug' => $slug, 'name' => $lineBySlug[$slug]['name'], 'length_m' => $lineBySlug[$slug]['length_m'] ?: null];
            }
        }
        $r['length_m'] = $g['length_m'] > 0 ? $g['length_m'] : null;
        $dbSources = array_values(array_intersect($g['sources'], ['ign', 'osm', 'lignes-oubliees']));
        $r['sources']['length'] = $g['length_m'] > 0 ? ($dbSources[0] ?? 'vff') : null;
        if ($g['operator'] !== null && $r['operator'] === null) {
            [$r['operator'], $r['sources']['operator']] = [$g['operator'], 'wp-article'];
        }
        if ($r['start_year'] === null && $g['year'] !== null) {
            [$r['start_year'], $r['sources']['start_year']] = [$g['year'], $g['year_from'] ?? 'vff'];
        }
        $r['regions'] = $entry['region'] !== null ? [$entry['region']] : $g['regions'];
        $r['sources']['region'] = $entry['region'] !== null ? ($entry['region_source'] ?? 'wp-list') : 'vff';
        $r['lat'] ??= $g['lat'] !== null ? round((float) $g['lat'], 5) : null;
        $r['lng'] ??= $g['lng'] !== null ? round((float) $g['lng'], 5) : null;
        $r['gauge_mm'] ??= $g['gauge'] !== null ? (int) $g['gauge'] : null;

        return $r;
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  array<string, array{slug: string, name: string}>  $lineByTitle
     * @return array<string, mixed>
     */
    private function fromCatalogueOnly(array $entry, array $lineByTitle, array $lineBySlug = [], ?string $kind = null): array
    {
        $lines = [];
        foreach ($entry['line_slugs'] ?? [] as $slug) {
            if (isset($lineBySlug[$slug])) {
                $lines[$slug] = ['slug' => $slug, 'name' => $lineBySlug[$slug]['name'], 'length_m' => $lineBySlug[$slug]['length_m'] ?: null];
            }
        }
        foreach ($entry['lines'] as $title) {
            if (isset($lineByTitle[$title])) {
                $lines[$lineByTitle[$title]['slug']] = ['slug' => $lineByTitle[$title]['slug'], 'name' => $lineByTitle[$title]['name'], 'length_m' => null];
            }
        }
        $lines = array_values($lines);
        $fromSlugs = array_sum(array_map(fn (array $l): int => (int) $l['length_m'], array_filter($lines, fn (array $l): bool => in_array($l['slug'], $entry['line_slugs'] ?? [], true))));
        $gauges = $entry['gauges'] !== [] ? $entry['gauges'] : ($lines !== [] && ($lineBySlug[$lines[0]['slug']]['gauge'] ?? null) ? [(int) $lineBySlug[$lines[0]['slug']]['gauge']] : []);
        $gaugeFromLine = $entry['gauges'] === [] && $gauges !== [];
        $wp = $entry['wikipedia'] !== null ? 'https://fr.wikipedia.org/wiki/'.str_replace(' ', '_', $entry['wikipedia']) : null;
        $sources = [
            'type' => $entry['type'] === 'train' || $entry['type'] === 'velorail' ? 'wp-list' : ($entry['wikipedia'] !== null ? 'wp-article' : 'wp-list'),
            'gauge' => $gauges !== [] ? ($gaugeFromLine ? 'vff' : 'wp-list') : null,
            'region' => $entry['region_source'] ?? 'wp-list',
            'length' => $fromSlugs > 0 ? 'vff' : null,
            'start_year' => $entry['start_year'] !== null ? $entry['start_year_source'] : null,
            'operator' => $entry['operator'] !== null ? $entry['operator_source'] : null,
            'website' => $entry['website'] !== null ? $entry['website_source'] : null,
        ];

        return [
            'id' => '',
            'origin' => 'catalogue',
            'name' => $entry['name'],
            'type' => $kind === 'velorail' || $entry['type'] === 'velorail' ? 'velorail' : $entry['type'],
            'operator' => $entry['operator'],
            'gauge_mm' => $gauges[0] ?? null,
            'gauges_mm' => $gauges,
            'length_m' => $fromSlugs > 0 ? $fromSlugs : null,
            'start_year' => $entry['start_year'],
            'regions' => $entry['region'] !== null ? [$entry['region']] : [],
            'departements' => $entry['departements'],
            'lines' => $lines,
            'website' => $entry['website'],
            'wikipedia_url' => $wp,
            'wikidata' => $entry['wikidata'],
            'lat' => $entry['lat'],
            'lng' => $entry['lng'],
            'section' => ($entry['section_from'] ?? null) !== null ? ['from' => $entry['section_from'], 'to' => $entry['section_to'] ?? null] : null,
            'season' => $entry['season'] ?? null,
            'opened_on' => $entry['opened_on'] ?? null,
            'detail_source_url' => $entry['detail_source'] ?? null,
            'sources' => $sources,
        ];
    }

    /**
     * @param  array<string, mixed>  $g
     * @return array<string, mixed>
     */
    private function fromDatabaseOnly(array $g): array
    {
        $type = $g['kind'] === 'velorail' ? 'velorail' : $this->typeFromName($g['name']);
        $dbSources = array_values(array_intersect($g['sources'], ['ign', 'osm', 'lignes-oubliees']));

        return [
            'id' => '',
            'origin' => 'database',
            'name' => $g['name'],
            'type' => $type,
            'operator' => $g['operator'],
            'gauge_mm' => $g['gauge'] !== null ? (int) $g['gauge'] : null,
            'gauges_mm' => $g['gauge'] !== null ? [(int) $g['gauge']] : [],
            'length_m' => $g['length_m'] > 0 ? $g['length_m'] : null,
            'start_year' => $g['year'],
            'regions' => $g['regions'],
            'departements' => [],
            'lines' => $this->lineLinks($g['lines']),
            'website' => null,
            'wikipedia_url' => str_starts_with((string) $g['source_url'], 'https://fr.wikipedia.org/') ? $g['source_url'] : null,
            'wikidata' => null,
            'lat' => $g['lat'] !== null ? round((float) $g['lat'], 5) : null,
            'lng' => $g['lng'] !== null ? round((float) $g['lng'], 5) : null,
            'section' => null,
            'season' => null,
            'opened_on' => null,
            'detail_source_url' => null,
            'sources' => [
                'type' => $g['kind'] === 'velorail' ? ($dbSources[0] ?? 'vff') : 'vff',
                'gauge' => $g['gauge'] !== null ? 'vff' : null,
                'region' => $g['regions'] !== [] ? 'vff' : null,
                'length' => $g['length_m'] > 0 ? ($dbSources[0] ?? 'vff') : null,
                'start_year' => $g['year'] !== null ? ($g['year_from'] ?? 'vff') : null,
                'operator' => $g['operator'] !== null ? 'wp-article' : null,
                'website' => null,
            ],
        ];
    }

    private function typeFromName(string $name): string
    {
        $n = HeritageText::norm($name);

        return match (true) {
            (bool) preg_match('/\bvapeur\b/', $n) => 'steam',
            (bool) preg_match('/\bautorail\b/', $n) => 'railcar',
            (bool) preg_match('/\btram/', $n) => 'tram',
            (bool) preg_match('/cremaillere/', $n) => 'rack',
            default => 'train',
        };
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return list<array{slug: string, name: string, length_m: ?int}>
     */
    private function lineLinks(array $lines): array
    {
        $out = [];
        foreach ($lines as $l) {
            $out[$l['slug']] = ['slug' => $l['slug'], 'name' => $l['name'], 'length_m' => ($l['length_m'] ?? 0) > 0 ? (int) $l['length_m'] : null];
        }

        return array_values($out);
    }

    /** @param  list<array<string, mixed>>  $rows @return list<array<string, mixed>> */
    private function uniqueIds(array $rows): array
    {
        $seen = [];
        foreach ($rows as &$r) {
            $id = $r['id'];
            $seen[$id] = ($seen[$id] ?? 0) + 1;
            if ($seen[$id] > 1) {
                $r['id'] = $id.'-'.$seen[$id];
            }
        }

        return $rows;
    }

    private function slug(string $name): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', '-', HeritageText::norm($name)), '-');
    }

    /**
     * Voies vertes sur ancienne voie, nommées, d'au moins GREENWAY_MIN_M : une entrée par voie verte et par ligne.
     *
     * @return list<array<string, mixed>>
     */
    private function greenwaySection(): array
    {
        $lines = DB::table('line_greenways as g')->join('lines as l', 'l.id', '=', 'g.line_id')
            ->whereNotNull('g.display_name')
            ->groupBy('g.line_id', 'l.slug', 'l.name', 'l.regions')
            ->havingRaw('SUM(g.shared_length_m) >= ?', [self::GREENWAY_MIN_M])
            ->get(['g.line_id', 'l.slug', 'l.name', 'l.regions']);
        $byLine = [];
        foreach ($lines as $l) {
            $byLine[$l->line_id] = $l;
        }
        $groups = $this->greenways->groupsByLine(array_map('intval', array_keys($byLine)));
        $out = [];
        foreach ($groups as $lineId => $list) {
            foreach ($list as $g) {
                if ($g['name'] === null || $g['shared_length_m'] < self::GREENWAY_MIN_M) {
                    continue;
                }
                $line = $byLine[$lineId];
                $out[] = [
                    'name' => $g['name'],
                    'length_m' => $g['shared_length_m'],
                    'opened_year' => $g['opened_year'],
                    'regions' => json_decode((string) $line->regions, true) ?: [],
                    'line' => ['slug' => $line->slug, 'name' => $line->name],
                    'osm_url' => $g['osm_url'],
                    'sources' => ['length' => 'osm', 'name' => 'osm', 'opened_year' => $g['opened_year'] !== null ? 'osm' : null],
                ];
            }
        }
        usort($out, fn (array $a, array $b): int => [$b['length_m'], $a['name']] <=> [$a['length_m'], $b['name']]);

        return $out;
    }
}
