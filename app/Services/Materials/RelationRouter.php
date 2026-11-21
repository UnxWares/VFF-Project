<?php

namespace App\Services\Materials;

use App\Services\PathfindingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use SplPriorityQueue;

/**
 * Lignes parcourues par une relation « A - B - C » citée dans un article de série, sur le graphe gare à gare
 * d'une ère (`station_edges`, celui de PathfindingService) : plus court chemin entre deux arrêts consécutifs,
 * gardé seulement s'il est sans ambiguïté.
 *
 * Ambiguïté : il existe un autre itinéraire au plus DETOUR_TOLERANCE fois plus long (et au plus
 * LOCAL_TOLERANCE fois plus long que la portion qu'il contourne) qui emprunte au moins MIN_ALTERNATIVE_M
 * sur des lignes absentes du plus court chemin. Les autres itinéraires sont cherchés par
 * arête « de passage » : pour chaque arête portée par une autre ligne, le meilleur chemin qui la traverse
 * (départ → arête → arrivée), sans repasser par une même gare (un aller-retour sur un embranchement n'est
 * pas un autre itinéraire).
 *
 * Arrêt désigné par son nom : nom exact de la gare (accents, « Saint » / « St » et espaces autour des
 * tirets ignorés), sinon gares « <nom>-… » d'une même ville (moins de CITY_SPREAD_KM d'écart, « Paris »).
 * Une ville à plusieurs gares part de celle que choisit le plus court chemin, et l'ambiguïté se mesure
 * depuis cette gare seule (sinon deux gares parisiennes voisines passeraient pour deux itinéraires). Parmi
 * les gares d'une ville, les installations techniques (« Lyon-Perrache (MIN) », « Metz-n° 2 »,
 * « Honfleur (IE) », « … Marchandises », « … Triage ») sont laissées de côté s'il reste une autre gare.
 *
 * Série électrique (rame TGV, locomotive électrique, automotrice dont les systèmes sont connus) : les arêtes
 * qui ne suivent que des lignes non électrifiées sont retirées (voir electrifiedLines() : moins de
 * MaterialTractionRule::MIN_SHARE de la longueur connue sous caténaire à toutes les ères, ou, sans
 * électrification connue par tronçon, ni `lines.electrified` ni tronçon électrifié). Sans cela,
 * Paris-Nord - Arras - Boulogne-Ville en TGV passait par Saint-Pol et Étaples, ligne jamais électrifiée, et
 * Paris - Toulouse passait pour ambigu à cause du kilomètre électrifié de Brive - Capdenac - Toulouse.
 *
 * Graphe d'une ère passée troué (morceau de ligne absent de `station_edges` à cette ère, comme le kilomètre
 * entre Vénissieux et Saint-Fons sur Lyon - Grenoble avant 2030) : quand le plus court chemin de l'époque
 * actuelle est nettement plus court (rapport DETOUR_TOLERANCE) et suit des lignes déjà présentes dans le
 * graphe de l'ère, entre des gares qui y sont (une gare ouverte depuis est tolérée), c'est lui qui est retenu.
 * Sinon Lyon - Grenoble passait par Valence.
 *
 * Ligne directe : quand les deux arrêts sont les extrémités nommées d'une ligne (« Ligne de Motteville à
 * Montérolier-Buchy » pour Montérolier - Motteville) et que le graphe ne donne pas de chemin sûr, ou un
 * chemin sans elle plus de DIRECT_DETOUR fois plus long que la distance à vol d'oiseau, la relation suit
 * cette ligne seule (graphe troué sur la ligne directe elle-même).
 */
final class RelationRouter
{
    /** Un autre itinéraire plus long de moins de 15 % rend la relation ambiguë… */
    public const DETOUR_TOLERANCE = 1.15;

    /**
     * … s'il est aussi une vraie variante là où il s'écarte : au plus 30 % plus long que la partie du plus
     * court chemin qu'il remplace. Un crochet de quelques kilomètres par un raccordement (Viroflay sur
     * Paris - Granville) ou par une antenne (Briouze, La Ferté-Macé et Flers sur Argentan - Granville) reste
     * sous les 15 % du trajet entier, mais il double la portion contournée : ce n'est pas un autre itinéraire.
     */
    public const LOCAL_TOLERANCE = 1.30;

    /** Longueur minimale, sur d'autres lignes (raccordements non comptés), d'un itinéraire différent. */
    public const MIN_ALTERNATIVE_M = 2000;

    /**
     * Longueur minimale parcourue sur une ligne pour la compter dans le chemin : en deçà, bout de jonction ou
     * dernière portion d'une ligne voisine à l'entrée d'une grande gare (les 2,2 km de Paris - Brest avant
     * Rennes, la fin de Paris - Toulouse dans Montauban).
     */
    public const MIN_LINE_M = 5000;

    /**
     * Chemin sans la ligne directe plus long que ce rapport à la distance à vol d'oiseau : le graphe est troué
     * sur la ligne directe (un vrai chemin de fer dépasse rarement 1,5 fois la distance à vol d'oiseau).
     */
    public const DIRECT_DETOUR = 1.5;

    /** Installation technique ou de fret dans un nom de gare : écartée quand la ville a une autre gare. */
    private const TECHNICAL_STATION = '/\((?:IE|MIN|ITE)\)|\bn°\s*\d|\b(?:marchandises|triage|fret|dépôt|atelier)\b|\s\d+$/iu';

    /** Écart maximal entre les gares d'une même ville. */
    private const CITY_SPREAD_KM = 30.0;

    /** Part minimale des gares « <nom>-… » groupées dans une même ville quand d'autres sont loin. */
    private const CITY_MAJORITY = 0.75;

    /** @var array<int, int>|null année de l'ère → identifiant */
    private ?array $eras = null;

    private ?int $currentEraYear = null;

    /** @var array<int, array{adj: array<int, list<array{0: int, 1: float, 2: int}>>, parts: array<int, list<array{0: int, 1: int}>>}|null> */
    private array $graphs = [];

    /** @var array<int, array{name: string, key: string, lat: float, lng: float}>|null */
    private ?array $stations = null;

    /** @var array<string, list<int>> nom normalisé → gares */
    private array $byKey = [];

    /** @var array<string, array{status: string, lines: list<int>, ratio: ?float, from: ?int, to: ?int}> */
    private array $memo = [];

    /** @var array<int, bool>|null lignes à grande vitesse */
    private ?array $lgv = null;

    /** @var array<int, bool>|null raccordements (nom « Raccordement … ») */
    private ?array $junctions = null;

    /** @var array<int, bool>|null lignes électrifiées à une ère au moins (ou aujourd'hui) */
    private ?array $electrified = null;

    /** @var array<int, array{0: string, 1: string}>|null ligne → extrémités nommées (« Ligne de A à B »), noms normalisés */
    private ?array $namedEnds = null;

    /** Année de l'ère du graphe pour une année quelconque (null : aucune ère, ou graphe absent). */
    public function eraYear(int $year): ?int
    {
        $this->loadEras();
        if ($this->eras === [] || $this->currentEraYear === null) {
            return null;
        }

        return PathfindingService::eraYearFor(array_keys($this->eras), $year, $this->currentEraYear, (int) date('Y'));
    }

    /** Le nom désigne-t-il une gare connue (toutes ères confondues) ? */
    public function isStationName(string $name): bool
    {
        $this->loadStations();
        $key = self::key($name);

        return $key !== '' && isset($this->byKey[$key]);
    }

    /** Nom de la gare (pour la preuve). */
    public function stationName(int $id): string
    {
        $this->loadStations();

        return $this->stations[$id]['name'] ?? (string) $id;
    }

    /**
     * Gares du graphe de l'ère désignées par ce nom ; vide si aucune ou si elles sont trop éloignées
     * pour être une même ville.
     *
     * @return list<int>
     */
    public function resolve(string $name, int $eraYear): array
    {
        $this->loadStations();
        $graph = $this->graph($eraYear);
        $key = self::key($name);
        if ($key === '' || $graph === null) {
            return [];
        }
        $found = array_values(array_filter($this->byKey[$key] ?? [], fn (int $id): bool => isset($graph['adj'][$id])));
        $prefixed = false;
        if ($found === [] && mb_strlen($key) >= 3) {
            $prefixed = true;
            foreach ($this->stations as $id => $station) {
                if (isset($graph['adj'][$id]) && (str_starts_with($station['key'], $key.'-') || str_starts_with($station['key'], $key.' '))) {
                    $found[] = $id;
                }
            }
        }
        if (count($found) > 1 && $this->spreadKm($found) > self::CITY_SPREAD_KM) {
            // Gares « <nom>-… » : une commune lointaine au nom composé (Paris-l'Hôpital, en Saône-et-Loire,
            // ouverte aux ères passées) ne retire pas « Paris » à toute une époque.
            $found = $prefixed ? $this->mainCity($found) : [];
            if ($found === []) {
                return [];
            }
        }
        if (count($found) > 1) {
            $public = array_values(array_filter($found, fn (int $id): bool => preg_match(self::TECHNICAL_STATION, $this->stations[$id]['name']) !== 1));
            $found = $public !== [] ? $public : $found;
        }

        return $found;
    }

    /**
     * Lignes du plus court chemin entre deux arrêts, ou le motif du refus.
     *
     * `status` : `ok`, `ambiguous` (autre itinéraire proche, `ratio` = sa longueur rapportée au plus court),
     * `same` (même gare) ou `no_path`.
     *
     * `$fast` : la série roule à grande vitesse ; sinon les LGV sont retirées du graphe (comme LineMaterialLinker,
     * qui ne rattache à une LGV que les engins rapides). `$electric` : la série ne roule que sous caténaire ;
     * les arêtes des lignes jamais électrifiées sont retirées.
     *
     * @param  list<int>  $from
     * @param  list<int>  $to
     * @return array{status: string, lines: list<int>, ratio: ?float, from: ?int, to: ?int}
     */
    public function route(array $from, array $to, int $eraYear, bool $fast = false, bool $electric = false): array
    {
        sort($from);
        sort($to);
        $memoKey = $eraYear.($fast ? 'f' : 's').($electric ? 'e' : 't').':'.implode(',', $from).'>'.implode(',', $to);

        if (! isset($this->memo[$memoKey])) {
            $route = $this->computeRoute($from, $to, $eraYear, $fast, $electric);
            unset($route['cost']);
            $this->memo[$memoKey] = $route;
        }

        return $this->memo[$memoKey];
    }

    /**
     * @param  list<int>  $from
     * @param  list<int>  $to
     * @return array{status: string, lines: list<int>, ratio: ?float, from: ?int, to: ?int, cost?: float}
     */
    private function computeRoute(array $from, array $to, int $eraYear, bool $fast, bool $electric): array
    {
        $result = $this->routeOn($from, $to, $eraYear, $fast, $electric);
        $result = $this->bridgeGap($from, $to, $eraYear, $fast, $electric, $result);

        return $this->directLine($from, $to, $eraYear, $fast, $electric, $result) ?? $result;
    }

    /**
     * Graphe de l'ère troué : plus court chemin de l'époque actuelle s'il est plus court d'au moins
     * DETOUR_TOLERANCE et s'il suit des lignes du graphe de l'ère (voir la classe).
     *
     * @param  list<int>  $from
     * @param  list<int>  $to
     * @param  array{status: string, lines: list<int>, ratio: ?float, from: ?int, to: ?int, cost?: float}  $result
     * @return array{status: string, lines: list<int>, ratio: ?float, from: ?int, to: ?int, cost?: float}
     */
    private function bridgeGap(array $from, array $to, int $eraYear, bool $fast, bool $electric, array $result): array
    {
        $this->loadEras();
        $current = $this->currentEraYear;
        $graph = $this->graph($eraYear, $fast, $electric);
        if ($current === null || $current === $eraYear || $graph === null || $result['status'] === 'same') {
            return $result;
        }
        $now = $this->graph($current, $fast, $electric);
        $path = $now === null ? null : PathfindingService::dijkstra($now['adj'], $from, $to);
        if ($path === null || $path['edges'] === [] || (isset($result['cost']) && $result['cost'] < $path['cost'] * self::DETOUR_TOLERANCE)) {
            return $result;
        }
        // Chaque arête : au moins une de ses gares dans le graphe de l'ère (une gare ouverte depuis, comme
        // L'Isle-d'Abeau, passe ; un prolongement de plusieurs gares, non) et la plus grande part de sa longueur
        // sur des lignes de l'ère ou des raccordements (Lyon-Guillotière, Grenay : bouts de raccordements récents).
        $eraLines = $this->linesOf($graph);
        foreach ($path['edges'] as $k => $eid) {
            if (! isset($graph['adj'][$path['stations'][$k]]) && ! isset($graph['adj'][$path['stations'][$k + 1]])) {
                return $result;
            }
            $known = 0;
            $total = 0;
            foreach ($now['parts'][$eid] as [$line, $len]) {
                $known += isset($eraLines[$line]) || isset($this->junctions[$line]) ? $len : 0;
                $total += $len;
            }
            if (2 * $known < $total) {
                return $result;
            }
        }

        return $this->routeOn($from, $to, $current, $fast, $electric);
    }

    /**
     * Relation entre les deux extrémités nommées d'une ligne présente à l'ère, quand le graphe ne la fait
     * pas passer par elle (voir la classe) ; null sinon.
     *
     * @param  list<int>  $from
     * @param  list<int>  $to
     * @param  array{status: string, lines: list<int>, ratio: ?float, from: ?int, to: ?int, cost?: float}  $result
     * @return array{status: string, lines: list<int>, ratio: ?float, from: ?int, to: ?int, cost?: float}|null
     */
    private function directLine(array $from, array $to, int $eraYear, bool $fast, bool $electric, array $result): ?array
    {
        $graph = $this->graph($eraYear, $fast, $electric);
        if ($graph === null || $result['status'] === 'same') {
            return null;
        }
        $this->loadStations();
        $this->loadNamedEnds();
        $eraLines = $this->linesOf($graph);
        $found = [];
        foreach ($from as $a) {
            foreach ($to as $b) {
                $pair = [$this->stations[$a]['key'] ?? '', $this->stations[$b]['key'] ?? ''];
                foreach ($this->namedEnds as $line => $ends) {
                    if (isset($eraLines[$line]) && ($ends === $pair || $ends === [$pair[1], $pair[0]])) {
                        $found[$line] = [$a, $b];
                    }
                }
            }
        }
        if (count($found) !== 1) {
            return null;
        }
        $line = (int) array_key_first($found);
        [$a, $b] = $found[$line];
        if ($result['status'] === 'ok' && (in_array($line, $result['lines'], true) || ($result['cost'] ?? 0.0) <= self::DIRECT_DETOUR * 1000 * $this->distanceKm($a, $b))) {
            return null;
        }

        return ['status' => 'ok', 'lines' => [$line], 'ratio' => null, 'from' => $a, 'to' => $b];
    }

    /**
     * Lignes portées par au moins une arête du graphe.
     *
     * @param  array{adj: array<int, list<array{0: int, 1: float, 2: int}>>, parts: array<int, list<array{0: int, 1: int}>>}  $graph
     * @return array<int, true>
     */
    private function linesOf(array $graph): array
    {
        $lines = [];
        foreach ($graph['parts'] as $parts) {
            foreach ($parts as [$line]) {
                $lines[$line] = true;
            }
        }

        return $lines;
    }

    /**
     * Plus court chemin sur le graphe d'une ère, avec son coût (`cost`, en mètres pondérés).
     *
     * @param  list<int>  $from
     * @param  list<int>  $to
     * @return array{status: string, lines: list<int>, ratio: ?float, from: ?int, to: ?int, cost?: float}
     */
    private function routeOn(array $from, array $to, int $eraYear, bool $fast, bool $electric): array
    {
        $graph = $this->graph($eraYear, $fast, $electric);
        if ($graph === null || $from === [] || $to === []) {
            return ['status' => 'no_path', 'lines' => [], 'ratio' => null, 'from' => null, 'to' => null];
        }
        if (array_intersect($from, $to) !== []) {
            return ['status' => 'same', 'lines' => [], 'ratio' => null, 'from' => null, 'to' => null];
        }
        $path = PathfindingService::dijkstra($graph['adj'], $from, $to);
        if ($path === null || $path['edges'] === []) {
            return ['status' => 'no_path', 'lines' => [], 'ratio' => null, 'from' => null, 'to' => null];
        }
        $onPath = [];
        $lengths = [];
        foreach ($path['edges'] as $eid) {
            foreach ($graph['parts'][$eid] as [$line, $len]) {
                $onPath[$line] = true;
                $lengths[$line] = ($lengths[$line] ?? 0) + $len;
            }
        }
        // Dans l'ordre du trajet, sans les bouts de jonction (quelques centaines de mètres d'une ligne voisine).
        // Série électrique : sans les lignes non électrifiées portées par une arête qu'elle partage avec une
        // ligne électrifiée (tronc commun avant Cahors, où Monsempron-Libos - Cahors rejoint Paris - Toulouse).
        $lines = array_keys(array_filter($lengths, fn (int $len, int $line): bool => $len >= self::MIN_LINE_M
            && (! $electric || isset($this->electrified[$line])), ARRAY_FILTER_USE_BOTH));
        $ratio = $this->alternative($graph, $path, $onPath);

        $ends = ['from' => $path['stations'][0], 'to' => $path['stations'][count($path['stations']) - 1], 'cost' => (float) $path['cost']];

        return $ratio !== null
            ? ['status' => 'ambiguous', 'lines' => $lines, 'ratio' => round($ratio, 3)] + $ends
            : ['status' => 'ok', 'lines' => $lines, 'ratio' => null] + $ends;
    }

    /**
     * Rapport de longueur du meilleur autre itinéraire assez différent (voir DETOUR_TOLERANCE et
     * LOCAL_TOLERANCE), ou null s'il n'y en a pas.
     *
     * @param  array{adj: array<int, list<array{0: int, 1: float, 2: int}>>, parts: array<int, list<array{0: int, 1: int}>>}  $graph
     * @param  array{cost: float, stations: list<int>, edges: list<int>}  $path  plus court chemin (une gare de départ)
     * @param  array<int, true>  $onPath  lignes du plus court chemin
     */
    private function alternative(array $graph, array $path, array $onPath): ?float
    {
        $cost = $path['cost'];
        $limit = $cost * self::DETOUR_TOLERANCE;
        [$ds, $ps] = self::bounded($graph['adj'], $path['stations'][0], $limit);
        [$dt, $pt] = self::bounded($graph['adj'], $path['stations'][count($path['stations']) - 1], $limit);
        // Position de chaque gare du plus court chemin (coût depuis le départ).
        $along = [];
        $position = 0.0;
        foreach ($path['stations'] as $k => $station) {
            $along[$station] = $position;
            $edge = $path['edges'][$k] ?? null;
            if ($edge !== null) {
                foreach ($graph['adj'][$station] as [$next, $w, $eid]) {
                    if ($eid === $edge) {
                        $position += $w;
                        break;
                    }
                }
            }
        }
        $best = null;
        foreach ($ds as $u => $du) {
            foreach ($graph['adj'][$u] ?? [] as [$v, $w, $eid]) {
                $total = isset($dt[$v]) ? $du + $w + $dt[$v] : INF;
                if ($total > $limit || ($best !== null && $total >= $best)) {
                    continue;
                }
                $outside = $this->outside($graph['parts'][$eid], $onPath);
                if ($outside === 0) {
                    continue;
                }
                // Chemin complet : départ → u, arête, v → arrivée ; une gare vue deux fois = aller-retour.
                // `$leave` / `$join` : gares où il quitte puis rejoint le plus court chemin.
                $seen = [$u => true, $v => true];
                $ends = [];
                foreach ([[$u, $ps], [$v, $pt]] as $side => [$node, $pred]) {
                    $ends[$side] = isset($along[$node]) ? $node : null;
                    for ($n = $node; isset($pred[$n]); $n = $pred[$n][0]) {
                        $outside += $this->outside($graph['parts'][$pred[$n][1]], $onPath);
                        $back = $pred[$n][0];
                        if (isset($seen[$back])) {
                            continue 3;
                        }
                        $seen[$back] = true;
                        $ends[$side] ??= isset($along[$back]) ? $back : null;
                    }
                }
                [$leave, $join] = [$ends[0] ?? $path['stations'][0], $ends[1] ?? $path['stations'][count($path['stations']) - 1]];
                $replaced = $along[$join] - $along[$leave];
                $detour = $total - $cost + $replaced;
                if ($outside >= self::MIN_ALTERNATIVE_M && $replaced > 0 && $detour <= $replaced * self::LOCAL_TOLERANCE) {
                    $best = $total;
                }
            }
        }

        return $best === null ? null : $best / $cost;
    }

    /**
     * @param  list<array{0: int, 1: int}>  $parts
     * @param  array<int, true>  $onPath
     */
    private function outside(array $parts, array $onPath): int
    {
        $sum = 0;
        foreach ($parts as [$line, $len]) {
            $sum += isset($onPath[$line]) || isset($this->junctions[$line]) ? 0 : $len;
        }

        return $sum;
    }

    /**
     * Distances depuis une gare jusqu'à la borne, avec les prédécesseurs (gare, arête).
     *
     * @param  array<int, list<array{0: int, 1: float, 2: int}>>  $adj
     * @return array{0: array<int, float>, 1: array<int, array{0: int, 1: int}>}
     */
    private static function bounded(array $adj, int $source, float $limit): array
    {
        $dist = [$source => 0.0];
        $prev = [];
        $done = [];
        $pq = new SplPriorityQueue;
        $pq->setExtractFlags(SplPriorityQueue::EXTR_BOTH);
        $pq->insert($source, 0.0);
        while (! $pq->isEmpty()) {
            $item = $pq->extract();
            $u = $item['data'];
            if (isset($done[$u])) {
                continue;
            }
            $done[$u] = true;
            foreach ($adj[$u] ?? [] as [$v, $w, $eid]) {
                $alt = $dist[$u] + $w;
                if ($alt <= $limit && $alt < ($dist[$v] ?? INF)) {
                    $dist[$v] = $alt;
                    $prev[$v] = [$u, $eid];
                    $pq->insert($v, -$alt);
                }
            }
        }

        return [$dist, $prev];
    }

    /**
     * Graphe de l'ère (voies associatives exclues, voies sans voyageurs pénalisées comme dans
     * PathfindingService), ou null s'il n'est pas construit. Sans `$fast`, les arêtes surtout portées par
     * une LGV sont retirées ; avec `$electric`, celles qui ne suivent que des lignes jamais électrifiées.
     *
     * @return array{adj: array<int, list<array{0: int, 1: float, 2: int}>>, parts: array<int, list<array{0: int, 1: int}>>}|null
     */
    private function graph(int $eraYear, bool $fast = true, bool $electric = false): ?array
    {
        $this->loadEras();
        $eraId = $this->eras[$eraYear] ?? null;
        if ($eraId === null) {
            return null;
        }
        if ($this->lgv === null) {
            $this->lgv = DB::table('lines')->where('is_lgv', true)->pluck('id')->mapWithKeys(fn ($id): array => [(int) $id => true])->all();
            $this->junctions = DB::table('lines')->where('name', 'ilike', 'raccordement%')->pluck('id')->mapWithKeys(fn ($id): array => [(int) $id => true])->all();
        }
        if ($electric && $this->electrified === null) {
            $this->electrified = self::electrifiedLines();
        }
        $graphKey = $eraYear * 4 + ($fast ? 1 : 0) + ($electric ? 2 : 0);
        if (! array_key_exists($graphKey, $this->graphs)) {
            $adj = [];
            $parts = [];
            $rows = DB::table('station_edges')->where('era_id', $eraId)->where('heritage_m', 0)
                ->get(['id', 'from_station_id', 'to_station_id', 'line_id', 'length_m', 'non_passenger_m', 'line_ids', 'line_lengths']);
            foreach ($rows as $r) {
                $a = (int) $r->from_station_id;
                $b = (int) $r->to_station_id;
                $w = (float) $r->length_m + (PathfindingService::NON_PASSENGER_FACTOR - 1.0) * (float) $r->non_passenger_m;
                $eid = (int) $r->id;
                $ids = self::pgInts($r->line_ids);
                $lengths = self::pgInts($r->line_lengths);
                $parts[$eid] = $ids !== [] && count($ids) === count($lengths)
                    ? array_map(null, $ids, $lengths)
                    : [[(int) $r->line_id, (int) $r->length_m]];
                // Arête surtout portée par une LGV (pas un simple tronc commun en sortie de gare).
                if (! $fast && 2 * array_sum(array_map(fn (array $p): int => isset($this->lgv[$p[0]]) ? $p[1] : 0, $parts[$eid])) > (int) $r->length_m) {
                    continue;
                }
                // Arête qui ne suit que des lignes jamais électrifiées, pour une série électrique.
                if ($electric && array_filter($parts[$eid], fn (array $p): bool => isset($this->electrified[$p[0]])) === []) {
                    continue;
                }
                $adj[$a][] = [$b, $w, $eid];
                $adj[$b][] = [$a, $w, $eid];
            }
            $this->graphs[$graphKey] = $adj === [] ? null : ['adj' => $adj, 'parts' => $parts];
        }

        return $this->graphs[$graphKey];
    }

    /**
     * Lignes qu'une série électrique peut suivre, avec la mesure de MaterialTractionRule : au moins MIN_SHARE
     * de la longueur connue sous caténaire à une ère au moins. Le bout électrifié d'une grande gare ne suffit
     * pas : la ligne de Brive à Toulouse par Capdenac (718000, 1 km sur 249 à Toulouse) rendait Paris - Toulouse
     * ambigu pour une BB 26000, et celle de Lyon à Bourg par la Dombes (886000) détournait les BB 22200 de
     * Lyon - Bourg-en-Bresse. Ligne sans électrification connue par tronçon : `lines.electrified` ou un tronçon
     * électrifié, comme avant.
     *
     * @return array<int, true>
     */
    private static function electrifiedLines(): array
    {
        $length = 'COALESCE(line_segments.length_meters, ST_Length(line_segments.geom::geography))';
        $best = [];
        $rows = DB::table('line_segments')
            ->whereNotNull('electrification_source')->whereNotIn('electrification_source', MaterialTractionRule::UNKNOWN_SOURCES)
            ->groupBy('line_id', 'era_id')
            ->selectRaw("line_id, SUM({$length}) AS total, SUM(CASE WHEN electrified THEN {$length} ELSE 0 END) AS electrified")
            ->get();
        foreach ($rows as $row) {
            $share = (float) $row->total > 0 ? (float) $row->electrified / (float) $row->total : 0.0;
            $best[(int) $row->line_id] = max($best[(int) $row->line_id] ?? 0.0, $share);
        }
        $lines = [];
        foreach ($best as $line => $share) {
            if ($share >= MaterialTractionRule::MIN_SHARE) {
                $lines[$line] = true;
            }
        }
        $declared = DB::table('lines')->where('electrified', true)
            ->orWhereExists(fn ($q) => $q->selectRaw('1')->from('line_segments')->whereColumn('line_segments.line_id', 'lines.id')->where('line_segments.electrified', true))
            ->pluck('id');
        foreach ($declared as $id) {
            if (! isset($best[(int) $id])) {
                $lines[(int) $id] = true;
            }
        }

        return $lines;
    }

    private function loadEras(): void
    {
        if ($this->eras !== null) {
            return;
        }
        $this->eras = [];
        foreach (DB::table('eras')->orderBy('year')->get(['id', 'year', 'is_current']) as $era) {
            $this->eras[(int) $era->year] = (int) $era->id;
            if ((bool) $era->is_current) {
                $this->currentEraYear = (int) $era->year;
            }
        }
        $this->currentEraYear ??= $this->eras === [] ? null : (int) array_key_last($this->eras);
    }

    private function loadStations(): void
    {
        if ($this->stations !== null) {
            return;
        }
        $this->stations = [];
        foreach (DB::table('stations')->selectRaw('id, name, ST_Y(geom) AS lat, ST_X(geom) AS lng')->whereNotNull('geom')->cursor() as $s) {
            $key = self::key((string) $s->name);
            $this->stations[(int) $s->id] = ['name' => (string) $s->name, 'key' => $key, 'lat' => (float) $s->lat, 'lng' => (float) $s->lng];
            $this->byKey[$key][] = (int) $s->id;
        }
    }

    /**
     * Nom de gare lisible pour un libellé de relation : abréviations développées (« St-Malo » → « Saint-Malo »,
     * « Pontorson-Mont-St-Michel » → « Pontorson-Mont-Saint-Michel ») et codes techniques retirés
     * (« Honfleur (IE) », « Lyon-Perrache (MIN) », « Metz-n° 2 »). Les précisions de lieu restent (« Bize (Aude) »).
     */
    public static function readable(string $name): string
    {
        $name = (string) preg_replace(['/\s*\((?:IE|MIN|ITE|Tram\/Train)\)\s*$/u', '/-?\s*n°\s*\d+\s*$/u'], '', trim($name));
        $name = (string) preg_replace(['/\bSt(?=[- ]\p{Lu})/u', '/\bSte(?=[- ]\p{Lu})/u'], ['Saint', 'Sainte'], $name);

        return trim($name);
    }

    /** Nom comparable : sans accents ni majuscules, « Saint » = « St », pas d'espace autour des tirets. */
    public static function key(string $name): string
    {
        $key = mb_strtolower(Str::ascii(trim((string) preg_replace('/\s*\([^)]*\)\s*$/u', '', $name))));
        $key = (string) preg_replace(['/[’\']/u', '/\s*-\s*/u', '/\s+/u'], ["'", '-', ' '], $key);

        return (string) preg_replace(['/\bsaint(?=[- ])/u', '/\bsainte(?=[- ])/u'], ['st', 'ste'], $key);
    }

    /** @param list<int> $ids */
    private function spreadKm(array $ids): float
    {
        $max = 0.0;
        foreach ($ids as $i => $a) {
            foreach (array_slice($ids, $i + 1) as $b) {
                $max = max($max, $this->distanceKm($a, $b));
            }
        }

        return $max;
    }

    /**
     * Gares d'une même ville parmi des gares « <nom>-… » trop éloignées : celles à moins de CITY_SPREAD_KM de
     * la gare qui a le plus de voisines, si elles restent groupées et font au moins CITY_MAJORITY du tout ;
     * sinon aucune (deux villes de taille voisine : nom ambigu).
     *
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function mainCity(array $ids): array
    {
        $best = [];
        foreach ($ids as $a) {
            $near = array_values(array_filter($ids, fn (int $b): bool => $this->distanceKm($a, $b) <= self::CITY_SPREAD_KM));
            if (count($near) > count($best)) {
                $best = $near;
            }
        }

        return count($best) >= self::CITY_MAJORITY * count($ids) && $this->spreadKm($best) <= self::CITY_SPREAD_KM ? $best : [];
    }

    /** Distance à vol d'oiseau entre deux gares, en kilomètres. */
    private function distanceKm(int $a, int $b): float
    {
        $sa = $this->stations[$a];
        $sb = $this->stations[$b];
        $dLat = deg2rad($sb['lat'] - $sa['lat']);
        $dLng = deg2rad($sb['lng'] - $sa['lng']) * cos(deg2rad(($sa['lat'] + $sb['lat']) / 2));

        return 6371.0 * sqrt($dLat ** 2 + $dLng ** 2);
    }

    /** Extrémités nommées des lignes « Ligne de A à B » (précision finale entre parenthèses ignorée). */
    private function loadNamedEnds(): void
    {
        if ($this->namedEnds !== null) {
            return;
        }
        $this->namedEnds = [];
        foreach (DB::table('lines')->where('name', 'ilike', 'ligne d%')->get(['id', 'name']) as $line) {
            $ends = self::namedEnds((string) $line->name);
            if ($ends !== null) {
                $this->namedEnds[(int) $line->id] = $ends;
            }
        }
    }

    /**
     * Extrémités d'un nom « Ligne de A à B », normalisées (key()) ; null si le nom n'a pas cette forme ou
     * contient plusieurs « à ».
     *
     * @return array{0: string, 1: string}|null
     */
    public static function namedEnds(string $name): ?array
    {
        $name = trim((string) preg_replace('/\s*\([^)]*\)\s*$/u', '', $name));
        if (preg_match('/^ligne\s+(?:de\s+la\s+|de\s+l[’\']|de\s+|d[’\']|du\s+|des\s+)(.+)$/iu', $name, $m) !== 1) {
            return null;
        }
        $parts = preg_split('/\s+à\s+/u', $m[1]) ?: [];
        if (count($parts) !== 2) {
            return null;
        }
        [$a, $b] = [self::key($parts[0]), self::key($parts[1])];

        return $a !== '' && $b !== '' && $a !== $b ? [$a, $b] : null;
    }

    /** @return list<int> */
    private static function pgInts(mixed $value): array
    {
        if (is_array($value)) {
            return array_map('intval', $value);
        }
        $value = trim((string) $value, '{}');

        return $value === '' ? [] : array_map('intval', explode(',', $value));
    }
}
