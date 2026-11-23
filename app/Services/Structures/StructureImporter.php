<?php

declare(strict_types=1);

namespace App\Services\Structures;

use App\Services\Media\CommonsClient;
use App\Services\Media\CommonsMetadata;
use App\Services\Profiles\GeoplateformeClient;
use App\Services\WikimediaClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Ouvrages d'art des lignes (tables `structures` et `line_structures`), croisés entre trois sources :
 *
 * 1. **BD TOPO, voies** (GeoplateformeClient, cache de tuiles du profil en long) : les tronçons de voie
 *    ferrée en tunnel ou sur pont, assemblés en ouvrages (TrackChains). Tracé et longueur mesurés.
 * 2. **BD TOPO, constructions** (BdTopoConstructions) : le nom d'un tunnel ou d'un pont quand il coïncide
 *    avec une chaîne (milieu de chacun à NAME_M mètres au plus de l'autre), jamais celui d'une passerelle ;
 *    et les tunnels et ponts nommés qui n'ont plus de voie (ligne déposée), gardés seulement si aucun
 *    tronçon de voie de la BD TOPO ne passe à NO_TRACK_M mètres de leur milieu (sinon c'est une route
 *    sous la voie ou à côté), les ponts seulement si leur nom dit le chemin de fer (RAIL_BRIDGE_NAME) ou
 *    si Wikidata les reconnaît : un pont nommé sans voie est le plus souvent un pont routier.
 * 3. **Wikidata** (WikidataStructures) : nom, dates, protection, Mérimée, image et article ; les dates
 *    absentes de Wikidata sont lues dans l'infobox de l'article de Wikipédia (WikipediaStructureDates). Un élément est
 *    rattaché à l'ouvrage de même famille (tunnel, ou pont et viaduc) le plus proche, à WD_TUNNEL_M ou
 *    WD_BRIDGE_M mètres au plus du tracé ; un élément de classe générique (pont, viaduc, tunnel) n'est
 *    gardé que rattaché ainsi ; un élément de classe ferroviaire sans tracé devient un ouvrage ponctuel.
 *
 * Ouvrages gardés : tous ceux de Wikidata ; les tunnels de MIN_TUNNEL_M mètres ou plus, ou nommés ; les
 * ponts de MIN_BRIDGE_M mètres ou plus, ou nommés et de MIN_NAMED_BRIDGE_M mètres ou plus.
 *
 * Rattachement aux lignes : le tracé de la ligne (line_segments, toutes ères) passe à LINE_MID_M mètres
 * au plus du milieu de l'ouvrage et à LINE_END_M mètres au plus de chacune de ses extrémités (ouvrage
 * ponctuel : LINE_POINT_M mètres), et l'ouvrage la suit au lieu de la croiser (followsLine). PK de début et de fin : projection des extrémités sur le profil en long
 * de la ligne, dont le PK est calé sur les gares (PkCalibration) ; nul sans profil ou sans calage. Ères : celles
 * où un tronçon en service de la ligne passe à l'ouvrage (une ligne fermée ou déposée n'y compte plus).
 */
final class StructureImporter
{
    public const NAME_M = 20.0;

    /** Passerelle piétonne : jamais un ouvrage de la ligne, même si elle la longe. */
    public const FOOTBRIDGE = '/^passerelle\b/iu';

    /** Nom d'un pont qui dit le chemin de fer. */
    public const RAIL_BRIDGE_NAME = '/viaduc|ferroviaire|chemin de fer|voie ferr[ée]e|pont[- ]rail/iu';

    public const NO_TRACK_M = 25.0;

    public const WD_TUNNEL_M = 150.0;

    public const WD_BRIDGE_M = 80.0;

    public const MIN_TUNNEL_M = 50;

    public const MIN_BRIDGE_M = 100;

    public const MIN_NAMED_BRIDGE_M = 30;

    public const LINE_MID_M = 25.0;

    public const LINE_END_M = 40.0;

    public const LINE_POINT_M = 100.0;

    /** Rapport au-delà duquel la longueur de Wikidata remplace celle d'un tracé de la BD TOPO incomplet. */
    public const PARTIAL_TRACE = 1.5;

    /** Part de sa longueur sur laquelle un ouvrage doit suivre la ligne pour lui être rattaché. */
    public const ALONG_SHARE = 0.5;

    /** Écart maximal (m) entre un point de l'ouvrage et le profil en long pour en tirer un PK. */
    public const PROFILE_M = 80.0;

    public function __construct(
        private readonly GeoplateformeClient $ign,
        private readonly BdTopoConstructions $constructions,
        private readonly WikidataStructures $wikidata,
        private readonly CommonsClient $commons,
        private readonly WikimediaClient $wikipedia,
        private string $cacheDir = '',
    ) {
        $this->cacheDir = $cacheDir !== '' ? $cacheDir : storage_path('app/data/ouvrages');
    }

    /**
     * @param  callable(string): void  $log
     * @return array{tiles: int, chains: int, constructions: int, wikidata_items: int, wikidata_matched: int,
     *     structures: int, links: int, lines: int, by_kind: array<string, int>, named: int, dated: int,
     *     with_pk: int, removed: int, network_calls: int}
     */
    public function run(bool $dry, bool $offline, bool $refreshWikidata, callable $log): array
    {
        $tiles = $this->tiles();
        $log(count($tiles).' tuiles de 0,2 degré sur le parcours des lignes.');

        [$chains, $tracksAt] = $this->railChains($tiles, $offline);
        $log(count($chains).' ouvrages reconstitués depuis les voies de la BD TOPO.');

        $constructions = $this->constructionsFor($tiles, $offline);
        $log(count($constructions).' tunnels et ponts nommés de la BD TOPO (constructions).');

        $candidates = $this->fromChains($chains, $constructions);
        $orphans = $this->orphanConstructions($constructions, $candidates, $tracksAt);
        $candidates = [...$candidates, ...$orphans];
        $log(count($orphans).' ouvrages sans voie dans la BD TOPO (lignes déposées).');

        $items = $this->withWikipediaDates($this->wikidataItems($offline, $refreshWikidata), $offline);
        [$candidates, $matched] = $this->withWikidata($candidates, $items);
        $log(count($items).' éléments Wikidata, '.$matched.' rattachés à un ouvrage de la BD TOPO.');

        $candidates = array_values(array_filter($candidates, fn (array $c): bool => self::keep($c)));
        $log(count($candidates).' ouvrages retenus (longueur, nom ou Wikidata), à rattacher aux lignes.');
        $links = $this->attach($candidates);
        $kept = array_values(array_filter($candidates, fn (array $c, int $i): bool => isset($links[$i]), ARRAY_FILTER_USE_BOTH));
        $linksKept = array_values(array_filter($links));
        $origin = fn (array $c): string => ($c['sources'][0]['source'] ?? 'wikidata').'/'.$c['kind'];
        $retained = array_count_values(array_map($origin, $candidates));
        $attached = array_count_values(array_map($origin, $kept));
        ksort($retained);
        foreach ($retained as $key => $n) {
            $log(sprintf('  %s : %d retenus, %d rattachés', $key, $n, $attached[$key] ?? 0));
        }
        $log(count($kept).' ouvrages rattachés à au moins une ligne.');

        $images = $this->images($kept, $offline);
        $removed = $dry ? 0 : $this->write($candidates, $links, $images);

        $flat = array_merge(...($linksKept ?: [[]]));

        return [
            'tiles' => count($tiles),
            'chains' => count($chains),
            'constructions' => count($constructions),
            'wikidata_items' => count($items),
            'wikidata_matched' => $matched,
            'structures' => count($kept),
            'links' => count($flat),
            'lines' => count(array_unique(array_column($flat, 'line_id'))),
            'by_kind' => array_count_values(array_column($kept, 'kind')),
            'named' => count(array_filter($kept, fn (array $c): bool => $c['name'] !== null)),
            'dated' => count(array_filter($kept, fn (array $c): bool => ($c['built'] ?? $c['opened'] ?? null) !== null)),
            'with_pk' => count(array_filter($flat, fn (array $l): bool => $l['pk_start_m'] !== null)),
            'removed' => $removed,
            'network_calls' => $this->ign->networkCalls() + $this->constructions->networkCalls(),
        ];
    }

    /**
     * Tuiles du cache de la BD TOPO que traversent les profils en long (toutes les lignes, déposées
     * comprises).
     *
     * @return list<array{0: int, 1: int}>
     */
    private function tiles(): array
    {
        $deg = GeoplateformeClient::TILE_DEG;

        return DB::table('line_profile_points')->whereNotNull('geom')
            ->selectRaw('DISTINCT floor(ST_X(geom) / ?)::int AS x, floor(ST_Y(geom) / ?)::int AS y', [$deg, $deg])
            ->orderBy('x')->orderBy('y')->get()
            ->map(fn (object $r): array => [(int) $r->x, (int) $r->y])->all();
    }

    /**
     * Chaînes de tronçons en tunnel ou sur pont, et un index des tuiles qui permet de savoir si une voie
     * passe près d'un point.
     *
     * @param  list<array{0: int, 1: int}>  $tiles
     * @return array{0: list<array{kind: string, coords: list<array{0: float, 1: float}>, length_m: float}>, 1: callable(array{0: float, 1: float}, float): bool}
     */
    private function railChains(array $tiles, bool $offline): array
    {
        $tracks = [];
        foreach ($tiles as [$x, $y]) {
            if ($offline && ! $this->ign->hasTile($x, $y)) {
                continue;
            }
            foreach ($this->ign->railTracksInTile($x, $y) as $t) {
                if ($t['kind'] !== 'ground') {
                    $tracks[] = ['kind' => $t['kind'], 'coords' => $t['coords']];
                }
            }
        }

        // Voies de toutes positions près d'un point : relues dans le cache, une tuile à la fois.
        $loaded = [];
        $tracksAt = function (array $p, float $radius) use (&$loaded, $offline): bool {
            [$x, $y] = GeoplateformeClient::tileOf($p[0], $p[1]);
            $key = $x.'_'.$y;
            if (! isset($loaded[$key])) {
                if (count($loaded) > 16) {
                    array_shift($loaded);
                }
                $loaded[$key] = $offline && ! $this->ign->hasTile($x, $y) ? [] : $this->ign->railTracksInTile($x, $y);
            }
            foreach ($loaded[$key] as $t) {
                if (StructureGeometry::distanceToLine($p, $t['coords']) <= $radius) {
                    return true;
                }
            }

            return false;
        };

        return [TrackChains::build($tracks), $tracksAt];
    }

    /**
     * @param  list<array{0: int, 1: int}>  $tiles
     * @return list<array{nature: string, name: ?string, coords: list<array{0: float, 1: float}>, mid: array{0: float, 1: float}, length_m: float}>
     */
    private function constructionsFor(array $tiles, bool $offline): array
    {
        $big = [];
        foreach ($tiles as [$x, $y]) {
            $big[(int) floor($x * GeoplateformeClient::TILE_DEG / BdTopoConstructions::TILE_DEG).'_'.(int) floor($y * GeoplateformeClient::TILE_DEG / BdTopoConstructions::TILE_DEG)] = true;
        }
        $out = [];
        foreach (array_keys($big) as $key) {
            [$x, $y] = array_map('intval', explode('_', $key));
            foreach ($this->constructions->inTile($x, $y, $offline) as $c) {
                $id = sprintf('%.6F,%.6F,%d', $c['coords'][0][0], $c['coords'][0][1], count($c['coords']));
                $out[$id] = $c + ['mid' => StructureGeometry::midpoint($c['coords']), 'length_m' => StructureGeometry::length($c['coords'])];
            }
        }

        return array_values($out);
    }

    /**
     * Ouvrages tirés des chaînes de voie, nommés par la construction de la BD TOPO qui coïncide.
     *
     * @param  list<array{kind: string, coords: list<array{0: float, 1: float}>, length_m: float}>  $chains
     * @param  list<array{nature: string, name: ?string, coords: list<array{0: float, 1: float}>, mid: array{0: float, 1: float}, length_m: float}>  $constructions
     * @return list<array<string, mixed>>
     */
    private function fromChains(array $chains, array $constructions): array
    {
        $grid = self::grid($constructions, fn (array $c): array => $c['mid']);
        $out = [];
        foreach ($chains as $chain) {
            $mid = StructureGeometry::midpoint($chain['coords']);
            $nature = $chain['kind'] === 'tunnel' ? 'Tunnel' : 'Pont';
            $name = null;
            $best = INF;
            foreach (self::near($grid, $mid) as $c) {
                $c = $constructions[$c];
                if ($c['nature'] !== $nature || $c['name'] === null || preg_match(self::FOOTBRIDGE, $c['name'])) {
                    continue;
                }
                $d = StructureGeometry::distanceToLine($mid, $c['coords']);
                if ($d <= self::NAME_M && StructureGeometry::distanceToLine($c['mid'], $chain['coords']) <= self::NAME_M && $d < $best) {
                    $best = $d;
                    $name = $c['name'];
                }
            }
            $out[] = self::candidate($chain['kind'], $chain['coords'], $name, $name === null ? null : 'bdtopo', 'bdtopo');
        }

        return $out;
    }

    /**
     * Tunnels (nommés ou non) et ponts nommés de la BD TOPO qui ne coïncident avec aucun ouvrage de voie
     * et loin de toute voie : ouvrages de lignes déposées.
     *
     * @param  list<array{nature: string, name: ?string, coords: list<array{0: float, 1: float}>, mid: array{0: float, 1: float}, length_m: float}>  $constructions
     * @param  list<array<string, mixed>>  $candidates
     * @param  callable(array{0: float, 1: float}, float): bool  $tracksAt
     * @return list<array<string, mixed>>
     */
    private function orphanConstructions(array $constructions, array $candidates, callable $tracksAt): array
    {
        $out = [];
        foreach ($constructions as $c) {
            if (! in_array($c['nature'], ['Tunnel', 'Pont'], true) || ($c['nature'] === 'Pont' && $c['name'] === null)) {
                continue;
            }
            if ($tracksAt($c['mid'], self::NO_TRACK_M)) {
                continue;
            }
            if ($c['name'] !== null && preg_match(self::FOOTBRIDGE, $c['name'])) {
                continue;
            }
            $kind = $c['nature'] === 'Tunnel' ? 'tunnel' : 'bridge';
            $candidate = self::candidate($kind, $c['coords'], $c['name'], $c['name'] === null ? null : 'bdtopo', 'bdtopo-construction');
            // Un pont nommé sans voie est le plus souvent un pont routier : gardé si son nom dit le chemin de
            // fer (viaduc, pont ferroviaire) ou si Wikidata le reconnaît (withWikidata).
            $candidate['needs_wikidata'] = $kind === 'bridge' && ! preg_match(self::RAIL_BRIDGE_NAME, (string) $c['name']);
            $out[] = $candidate;
        }

        return $out;
    }

    /**
     * Éléments Wikidata normalisés. Les réponses brutes du service (classes et propriétés) sont gardées dans
     * `wikidata.json`, relu sauf --refresh-wikidata : une correction de la lecture (WikidataStructures::normalize)
     * s'applique au passage suivant sans relire Wikidata.
     *
     * @return list<array<string, mixed>>
     */
    private function wikidataItems(bool $offline, bool $refresh): array
    {
        $file = $this->cacheDir.'/wikidata.json';
        $cached = is_file($file) && ! $refresh ? json_decode((string) file_get_contents($file), true) : null;
        if (! is_array($cached) || ! isset($cached['classes'], $cached['rows'])) {
            if ($offline) {
                return [];
            }
            $classes = $this->wikidata->listItems();
            $cached = ['classes' => $classes, 'rows' => $this->wikidata->details(array_keys($classes))];
            if (! is_dir($this->cacheDir)) {
                mkdir($this->cacheDir, 0775, true);
            }
            file_put_contents($file, json_encode($cached, JSON_UNESCAPED_UNICODE));
        }

        $items = [];
        foreach ($cached['rows'] as $row) {
            $id = preg_match('#(Q\d+)$#', (string) ($row['item']['value'] ?? ''), $m) ? $m[1] : null;
            $item = $id === null ? null : WikidataStructures::normalize($row, $cached['classes'][$id] ?? []);
            if ($item !== null) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * Dates que Wikidata ne donne pas, lues dans l'infobox de l'article de Wikipédia (WikipediaStructureDates),
     * 50 articles par requête ; cache `wikipedia.json` (titre → révision et dates). Une date de Wikidata
     * n'est jamais remplacée.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function withWikipediaDates(array $items, bool $offline): array
    {
        $file = $this->cacheDir.'/wikipedia.json';
        $cache = is_file($file) ? (json_decode((string) file_get_contents($file), true) ?: []) : [];
        $wanted = [];
        foreach ($items as $item) {
            if ($item['wikipedia_title'] !== null && ($item['built'] === null || $item['opened'] === null || $item['closed'] === null)) {
                $wanted[] = (string) $item['wikipedia_title'];
            }
        }
        $missing = array_values(array_diff(array_unique($wanted), array_keys($cache)));
        if ($missing !== [] && ! $offline) {
            $pages = $this->wikipedia->fetchPages($missing, leadOnly: true);
            foreach ($missing as $title) {
                $page = $pages[$title] ?? null;
                $cache[$title] = $page === null ? null : ['revid' => $page['revid'], ...WikipediaStructureDates::fromWikitext($page['content'])];
            }
            if (! is_dir($this->cacheDir)) {
                mkdir($this->cacheDir, 0775, true);
            }
            file_put_contents($file, json_encode($cache, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }

        foreach ($items as &$item) {
            $found = $item['wikipedia_title'] === null ? null : ($cache[$item['wikipedia_title']] ?? null);
            if ($found === null) {
                continue;
            }
            $filled = [];
            foreach (['built' => 'built_on', 'opened' => 'opened_on', 'closed' => 'closed_on'] as $key => $field) {
                if ($item[$key] === null && ($found[$key] ?? null) !== null) {
                    $item[$key] = $found[$key];
                    $filled[] = $field;
                }
            }
            $item['wikipedia_revid'] = (int) $found['revid'];
            $item['wikipedia_fields'] = $filled;
        }
        unset($item);

        return $items;
    }

    /**
     * Rattache chaque élément Wikidata à l'ouvrage de même famille le plus proche (une paire à la fois, de
     * la plus proche à la plus lointaine, un ouvrage par élément) ; les éléments ferroviaires restants
     * deviennent des ouvrages ponctuels.
     *
     * @param  list<array<string, mixed>>  $candidates
     * @param  list<array<string, mixed>>  $items
     * @return array{0: list<array<string, mixed>>, 1: int}
     */
    private function withWikidata(array $candidates, array $items): array
    {
        // Chaque ouvrage est rangé dans toutes les mailles que touche son tracé : le point de Wikidata d'un
        // long tunnel est souvent à une tête, loin du milieu.
        $grid = [];
        foreach ($candidates as $k => $c) {
            $cells = [];
            $n = count($c['coords']);
            for ($j = 0; $j < $n; $j++) {
                $a = $c['coords'][$j];
                $b = $c['coords'][min($j + 1, $n - 1)];
                // Un point tous les 500 m : un tunnel droit peut n'avoir que deux sommets.
                $steps = max(1, (int) ceil(StructureGeometry::metres($a, $b) / 500));
                for ($s = 0; $s < $steps; $s++) {
                    $x = $a[0] + ($b[0] - $a[0]) * $s / $steps;
                    $y = $a[1] + ($b[1] - $a[1]) * $s / $steps;
                    $cells[(int) floor($x / 0.01).':'.(int) floor($y / 0.01)] = true;
                }
            }
            foreach (array_keys($cells) as $cell) {
                $grid[$cell][] = $k;
            }
        }
        $pairs = [];
        foreach ($items as $i => $item) {
            $p = [(float) $item['lon'], (float) $item['lat']];
            $limit = $item['kind'] === 'tunnel' ? self::WD_TUNNEL_M : self::WD_BRIDGE_M;
            foreach (array_unique(self::near($grid, $p)) as $k) {
                $c = $candidates[$k];
                if (($c['kind'] === 'tunnel') !== ($item['kind'] === 'tunnel')) {
                    continue;
                }
                // Un pont de classe générique (souvent routier) ne suffit pas à garder un pont sans voie ni nom
                // ferroviaire (pont routier de 1967 sur l'emprise d'une ligne déposée) ; il nomme et date les autres.
                if (! $item['rail_class'] && ($c['needs_wikidata'] ?? false)) {
                    continue;
                }
                $d = StructureGeometry::distanceToLine($p, $c['coords']);
                if ($d <= $limit) {
                    $pairs[] = [$d, $i, $k];
                }
            }
        }
        usort($pairs, fn (array $a, array $b): int => $a[0] <=> $b[0]);
        $usedItem = [];
        $usedCandidate = [];
        foreach ($pairs as [, $i, $k]) {
            if (isset($usedItem[$i]) || isset($usedCandidate[$k])) {
                continue;
            }
            $usedItem[$i] = $usedCandidate[$k] = true;
            $candidates[$k] = self::enrich($candidates[$k], $items[$i]);
        }
        foreach ($items as $i => $item) {
            if (! isset($usedItem[$i]) && $item['rail_class']) {
                $point = [(float) $item['lon'], (float) $item['lat']];
                $candidates[] = self::enrich(self::candidate($item['kind'], [$point], null, null, null), $item);
            }
        }

        return [$candidates, count($usedItem)];
    }

    /**
     * @param  array<string, mixed>  $c
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private static function enrich(array $c, array $item): array
    {
        $kind = $c['kind'] === 'tunnel' ? 'tunnel' : $item['kind'];
        $hasTrace = count($c['coords']) > 1;
        // Tracé de la BD TOPO bien plus court que l'ouvrage connu de Wikidata : ouvrage coupé par la frontière
        // (tunnel du Fréjus : 6,7 km en France sur 13,7 km), la longueur de Wikidata est la bonne.
        $partial = $hasTrace && $item['length_m'] !== null && $item['length_m'] > self::PARTIAL_TRACE * (int) $c['length_m'];
        $wikidataLength = $item['length_m'] !== null && (! $hasTrace || $partial);
        $fields = array_values(array_filter([
            $item['name'] !== null ? 'name' : null,
            $wikidataLength ? 'length_m' : null,
            $item['built'] !== null ? 'built_on' : null,
            $item['opened'] !== null ? 'opened_on' : null,
            $item['closed'] !== null ? 'closed_on' : null,
            $item['heritage'] !== null ? 'heritage' : null,
            $item['merimee_ref'] !== null ? 'merimee_ref' : null,
            $item['commons_file'] !== null ? 'image' : null,
        ]));
        // Le nom de Wikidata remplace celui de la BD TOPO : la BD TOPO n'en est plus la source.
        $earlier = array_map(fn (array $src): array => [
            ...$src,
            'fields' => array_values(array_diff($src['fields'], [...($item['name'] !== null ? ['name'] : []), ...($wikidataLength ? ['length_m'] : [])])),
        ], $c['sources']);
        $sources = [...$earlier, ['source' => 'wikidata', 'url' => 'https://www.wikidata.org/wiki/'.$item['wikidata_id'], 'fields' => $fields]];
        if ($item['wikipedia_title'] !== null) {
            $wikipediaFields = $item['wikipedia_fields'] ?? [];
            // Les dates comblées par l'infobox ne sont pas celles de Wikidata.
            $sources[count($sources) - 1]['fields'] = array_values(array_diff($fields, $wikipediaFields));
            $revid = $item['wikipedia_revid'] ?? null;
            $sources[] = [
                'source' => 'wikipedia',
                'url' => $revid !== null && $wikipediaFields !== []
                    ? 'https://fr.wikipedia.org/w/index.php?oldid='.$revid
                    : 'https://fr.wikipedia.org/wiki/'.rawurlencode(str_replace(' ', '_', (string) $item['wikipedia_title'])),
                'fields' => $wikipediaFields,
            ];
        }
        if ($item['merimee_ref'] !== null) {
            $sources[] = ['source' => 'merimee', 'url' => 'https://pop.culture.gouv.fr/notice/merimee/'.rawurlencode((string) $item['merimee_ref']), 'fields' => ['heritage']];
        }

        return [
            ...$c,
            'kind' => $kind,
            'name' => $item['name'] ?? $c['name'],
            'name_source' => $item['name'] !== null ? 'wikidata' : $c['name_source'],
            'length_m' => $hasTrace && ! $partial ? $c['length_m'] : $item['length_m'],
            'length_source' => $hasTrace && ! $partial ? $c['length_source'] : ($item['length_m'] !== null ? 'wikidata' : null),
            'wikidata' => $item,
            'built' => $item['built'],
            'opened' => $item['opened'],
            'closed' => $item['closed'],
            'sources' => $sources,
        ];
    }

    /**
     * @param  list<array{0: float, 1: float}>  $coords
     * @return array<string, mixed>
     */
    private static function candidate(string $kind, array $coords, ?string $name, ?string $nameSource, ?string $source): array
    {
        if ($kind === 'bridge' && $name !== null && preg_match('/^viaduc\b/iu', $name)) {
            $kind = 'viaduct';
        }
        $length = count($coords) > 1 ? (int) round(StructureGeometry::length($coords)) : null;
        $sources = $source === null ? [] : [[
            'source' => $source,
            'url' => 'https://geoservices.ign.fr/bdtopo',
            'fields' => $nameSource === 'bdtopo' ? ['geometry', 'length_m', 'name'] : ['geometry', 'length_m'],
        ]];

        return [
            'kind' => $kind,
            'coords' => $coords,
            'mid' => count($coords) > 1 ? StructureGeometry::midpoint($coords) : $coords[0],
            'name' => $name,
            'name_source' => $nameSource,
            'length_m' => $length,
            'length_source' => $length === null ? null : 'bdtopo',
            'wikidata' => null,
            'built' => null,
            'opened' => null,
            'closed' => null,
            'sources' => $sources,
        ];
    }

    /** @param  array<string, mixed>  $c */
    private static function keep(array $c): bool
    {
        if ($c['wikidata'] !== null) {
            return true;
        }
        if ($c['needs_wikidata'] ?? false) {
            return false;
        }
        $length = (int) ($c['length_m'] ?? 0);

        return $c['kind'] === 'tunnel'
            ? $length >= self::MIN_TUNNEL_M || $c['name'] !== null
            : $length >= self::MIN_BRIDGE_M || ($c['name'] !== null && $length >= self::MIN_NAMED_BRIDGE_M);
    }

    /**
     * Lignes de chaque ouvrage, avec PK, distances le long du profil, écart et ères.
     *
     * @param  list<array<string, mixed>>  $candidates
     * @return array<int, list<array{line_id: int, pk_start_m: ?int, pk_end_m: ?int, distance_from_m: ?int, distance_to_m: ?int, offset_m: int, era_years: list<int>}>>
     */
    private function attach(array $candidates): array
    {
        $profiles = new ProfileLocator;
        $out = [];
        foreach ($candidates as $i => $c) {
            $point = count($c['coords']) === 1;
            // Milieu à placer sur le profil, et alignement à contrôler, pour un ouvrage tracé seulement.
            $mid = $point ? null : $c['mid'];
            $start = $c['coords'][0];
            $end = $c['coords'][count($c['coords']) - 1];
            // Distances aux extrémités mesurées sur tout le tracé de la ligne près de chacune : le tracé d'un
            // long tunnel est souvent coupé en plusieurs tronçons, et ceux du milieu n'atteignent pas les têtes.
            $rows = DB::select(<<<'SQL'
                WITH p AS (
                    SELECT ST_SetSRID(ST_MakePoint(?, ?), 4326) AS mid, ST_SetSRID(ST_MakePoint(?, ?), 4326) AS a, ST_SetSRID(ST_MakePoint(?, ?), 4326) AS b
                ), near AS (
                    SELECT s.line_id,
                           MIN(ST_Distance(s.geom::geography, p.mid::geography)) AS d_mid,
                           COALESCE(jsonb_agg(DISTINCT e.year) FILTER (WHERE ST_DWithin(s.geom::geography, p.mid::geography, ?)
                               AND s.status IN ('in_service', 'single_track', 'service_track')), '[]'::jsonb) AS years
                    FROM line_segments s
                    JOIN eras e ON e.id = s.era_id
                    CROSS JOIN p
                    WHERE ST_DWithin(s.geom, p.mid, ?)
                    GROUP BY s.line_id
                )
                SELECT near.line_id, near.d_mid, near.years,
                       (SELECT MIN(ST_Distance(s.geom::geography, p.a::geography)) FROM line_segments s
                        WHERE s.line_id = near.line_id AND ST_DWithin(s.geom, p.a, 0.0008)) AS d_a,
                       (SELECT MIN(ST_Distance(s.geom::geography, p.b::geography)) FROM line_segments s
                        WHERE s.line_id = near.line_id AND ST_DWithin(s.geom, p.b, 0.0008)) AS d_b
                FROM near CROSS JOIN p
                SQL, [
                $c['mid'][0], $c['mid'][1], $start[0], $start[1], $end[0], $end[1],
                $point ? self::LINE_POINT_M : self::LINE_END_M,
                // Préfiltre en degrés sur l'index : un peu plus que la distance gardée, à toute latitude française.
                $point ? 0.0018 : 0.0008,
            ]);
            $links = [];
            foreach ($rows as $r) {
                $ok = $point
                    ? (float) $r->d_mid <= self::LINE_POINT_M
                    : (float) $r->d_mid <= self::LINE_MID_M && $r->d_a !== null && (float) $r->d_a <= self::LINE_END_M
                        && $r->d_b !== null && (float) $r->d_b <= self::LINE_END_M;
                if (! $ok) {
                    continue;
                }
                $years = array_map('intval', json_decode((string) $r->years, true) ?: []);
                sort($years);
                $located = $profiles->locate((int) $r->line_id, $start, $end, $mid);
                if ($mid !== null && ! self::followsLine($c, $located, $located['ends'])) {
                    continue;
                }
                unset($located['ends']);
                $links[] = [
                    'line_id' => (int) $r->line_id,
                    ...$located,
                    'offset_m' => (int) round((float) $r->d_mid),
                    'era_years' => $years,
                ];
            }
            if ($links !== []) {
                $out[$i] = $links;
            }
        }

        return $out;
    }

    /**
     * L'ouvrage suit-il la ligne, ou la croise-t-il ? Ses extrémités projetées sur le profil doivent être
     * écartées d'au moins ALONG_SHARE fois sa longueur : un pont routier sur une ligne déposée, ou le pont
     * d'une autre ligne qui la franchit (saut-de-mouton), touche le tracé par son milieu seulement. Quand
     * le profil ne passe pas par les deux extrémités (profil absent, trou du tracé dans un long tunnel),
     * seuls les ouvrages tirés des voies de la BD TOPO sont gardés : le tracé de la ligne passe déjà à
     * LINE_END_M mètres de chaque extrémité.
     *
     * @param  array<string, mixed>  $c
     * @param  array{distance_from_m: ?int, distance_to_m: ?int}  $located
     */
    private static function followsLine(array $c, array $located, int $ends): bool
    {
        if ($ends < 2 || $located['distance_from_m'] === null || $located['distance_to_m'] === null) {
            return ($c['sources'][0]['source'] ?? null) === 'bdtopo';
        }
        // Longueur du tracé lui-même : celle de Wikidata peut la dépasser (ouvrage coupé par la frontière).
        $length = (int) round(StructureGeometry::length($c['coords']));

        return $length < 20 || abs($located['distance_to_m'] - $located['distance_from_m']) >= self::ALONG_SHARE * $length;
    }

    /**
     * Métadonnées Commons des images (licence, auteur, vignette) ; seules les images sous licence libre
     * ou dans le domaine public sont gardées. Cache `commons.json`.
     *
     * @param  list<array<string, mixed>>  $kept
     * @return array<string, array<string, mixed>> nom du fichier → image
     */
    private function images(array $kept, bool $offline): array
    {
        $file = $this->cacheDir.'/commons.json';
        $cache = is_file($file) ? (json_decode((string) file_get_contents($file), true) ?: []) : [];
        $wanted = array_values(array_unique(array_filter(array_map(fn (array $c): ?string => $c['wikidata']['commons_file'] ?? null, $kept))));
        $missing = array_values(array_filter($wanted, fn (string $f): bool => ! array_key_exists($f, $cache)));
        if ($missing !== [] && ! $offline) {
            $byTitle = [];
            foreach ($this->commons->fileInfoByTitles(array_map(fn (string $f): string => 'File:'.$f, $missing)) as $page) {
                $byTitle[(string) preg_replace('/^File:/', '', (string) $page['title'])] = CommonsMetadata::normalize($page);
            }
            foreach ($missing as $f) {
                $m = $byTitle[$f] ?? null;
                $cache[$f] = $m === null || ! in_array($m['rights_status'], ['open_license', 'public_domain'], true) ? null : [
                    'file' => $f,
                    'thumb_url' => $m['thumb_url'],
                    'width' => $m['width'],
                    'height' => $m['height'],
                    'author' => $m['author'],
                    'license' => $m['license'],
                    'license_url' => $m['license_url'],
                    'rights_status' => $m['rights_status'],
                    'source_url' => $m['source_url'],
                ];
            }
            if (! is_dir(dirname($file))) {
                mkdir(dirname($file), 0775, true);
            }
            file_put_contents($file, json_encode($cache, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }

        return array_filter(array_intersect_key($cache, array_flip($wanted)));
    }

    /**
     * Remplace le contenu des deux tables par l'import, en une transaction.
     *
     * @param  list<array<string, mixed>>  $candidates
     * @param  array<int, list<array<string, mixed>>>  $links
     * @param  array<string, array<string, mixed>>  $images
     */
    private function write(array $candidates, array $links, array $images): int
    {
        $now = Carbon::now();

        return DB::transaction(function () use ($candidates, $links, $images, $now): int {
            $keys = [];
            foreach ($links as $i => $lineLinks) {
                $c = $candidates[$i];
                $wd = $c['wikidata'];
                $key = $wd['wikidata_id'] ?? 'bdtopo:'.substr(md5(self::traceKey($c['coords'])), 0, 24);
                if (isset($keys[$key])) {
                    continue;
                }
                $file = $wd['commons_file'] ?? null;
                $row = [
                    'kind' => $c['kind'],
                    'name' => $c['name'],
                    'name_source' => $c['name_source'],
                    'length_m' => $c['length_m'],
                    'length_source' => $c['length_source'],
                    'lat' => $c['mid'][1],
                    'lon' => $c['mid'][0],
                    'built_on' => $c['built']['date'] ?? null,
                    'built_precision' => $c['built']['precision'] ?? null,
                    'opened_on' => $c['opened']['date'] ?? null,
                    'opened_precision' => $c['opened']['precision'] ?? null,
                    'closed_on' => $c['closed']['date'] ?? null,
                    'closed_precision' => $c['closed']['precision'] ?? null,
                    'heritage' => $wd['heritage'] ?? null,
                    'merimee_ref' => $wd['merimee_ref'] ?? null,
                    'image' => $file !== null && isset($images[$file]) ? json_encode($images[$file], JSON_UNESCAPED_UNICODE) : null,
                    'wikidata_id' => $wd['wikidata_id'] ?? null,
                    'wikipedia_title' => $wd['wikipedia_title'] ?? null,
                    'sources' => json_encode($c['sources'], JSON_UNESCAPED_UNICODE),
                    'fetched_at' => $now,
                    'updated_at' => $now,
                ];
                $geom = count($c['coords']) > 1 ? StructureGeometry::wkt($c['coords']) : null;
                $existing = DB::table('structures')->where('match_key', $key)->value('id');
                if ($existing === null) {
                    $id = (int) DB::table('structures')->insertGetId($row + ['match_key' => $key, 'created_at' => $now]);
                } else {
                    $id = (int) $existing;
                    DB::table('structures')->where('id', $id)->update($row);
                }
                DB::update('UPDATE structures SET geom = CASE WHEN ?::text IS NULL THEN NULL ELSE ST_GeomFromText(?, 4326) END WHERE id = ?', [$geom, $geom, $id]);
                $keys[$key] = $id;

                DB::table('line_structures')->where('structure_id', $id)->delete();
                DB::table('line_structures')->insert(array_map(fn (array $l): array => [
                    'line_id' => $l['line_id'],
                    'structure_id' => $id,
                    'pk_start_m' => $l['pk_start_m'],
                    'pk_end_m' => $l['pk_end_m'],
                    'distance_from_m' => $l['distance_from_m'],
                    'distance_to_m' => $l['distance_to_m'],
                    'offset_m' => min(32767, $l['offset_m']),
                    'era_years' => json_encode($l['era_years']),
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $lineLinks));
            }

            // Ouvrages d'un import précédent que celui-ci ne retrouve plus (tables propres à cet import).
            return DB::table('structures')->whereNotIn('id', array_values($keys) ?: [0])->delete();
        });
    }

    /** @param  list<array{0: float, 1: float}>  $coords */
    private static function traceKey(array $coords): string
    {
        $a = sprintf('%.5F,%.5F', ...$coords[0]);
        $b = sprintf('%.5F,%.5F', ...$coords[count($coords) - 1]);

        return $a < $b ? $a.'/'.$b : $b.'/'.$a;
    }

    /**
     * @template T
     *
     * @param  list<T>  $items
     * @param  callable(T): array{0: float, 1: float}  $point
     * @return array<string, list<int>>
     */
    private static function grid(array $items, callable $point, float $cell = 0.01): array
    {
        $grid = [];
        foreach ($items as $i => $item) {
            $p = $point($item);
            $grid[(int) floor($p[0] / $cell).':'.(int) floor($p[1] / $cell)][] = $i;
        }

        return $grid;
    }

    /**
     * @param  array<string, list<int>>  $grid
     * @param  array{0: float, 1: float}  $p
     * @return list<int>
     */
    private static function near(array $grid, array $p, float $cell = 0.01): array
    {
        $cx = (int) floor($p[0] / $cell);
        $cy = (int) floor($p[1] / $cell);
        $out = [];
        for ($dx = -1; $dx <= 1; $dx++) {
            for ($dy = -1; $dy <= 1; $dy++) {
                foreach ($grid[($cx + $dx).':'.($cy + $dy)] ?? [] as $i) {
                    $out[] = $i;
                }
            }
        }

        return $out;
    }
}
