<?php

namespace App\Services\Materials;

use App\Services\JunctionRepair;
use App\Services\PathfindingService;
use App\Support\Lgv;
use App\Support\TrackPriority;
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
 *
 * Gare absente du graphe de l'ère (fermée aux voyageurs, comme Desvres après 1968 ou Neuville-sur-Saône
 * après 1938 : `station_edges` ne relie que les gares ouvertes) : quand aucune gare ouverte ne porte ce nom,
 * elle sert d'extrémité, rattachée aux gares du graphe les plus proches sur les voies en service de l'ère
 * (`network_edges`), en partant d'une voie de l'une de ses lignes (`station_lines`) à moins de
 * CLOSED_ATTACH_M (voir attach()). Une relation de fret « Hesdigneul - Desvres » se résout donc encore. Rien
 * n'est écrit dans le graphe : le rattachement ne vit que dans ce routeur.
 *
 * Rester sur sa ligne : quand les deux arrêts sont des gares d'une même ligne du chemin (PK de source sûre,
 * JunctionRepair::RELIABLE_PK_SOURCES) et que le chemin par cette ligne seule (raccordements tolérés) fait au
 * plus LOCAL_TOLERANCE fois le plus court, c'est lui qui est retenu (voir stayOnLine()). Paris - Bordeaux
 * passait par Chartres - Bordeaux entre Saint-Loubès et Cenon, 7 % plus court que Paris - Bordeaux.
 *
 * Tronc commun : sur une arête portée par deux lignes dont l'une suit la voie de l'autre (à moins de
 * TrackPriority::TOLERANCE_M), la longueur commune revient à la ligne prioritaire au sens de TrackPriority
 * (celle que montre la carte), si les deux gares de l'arête ont un PK sûr sur elle ; voir trunkParts(). Entre Espère-Caillac et Cahors, 5,3 km étaient comptés à
 * Monsempron-Libos - Cahors, posée sur Paris - Toulouse.
 *
 * @phpstan-type Graph array{adj: array<int, list<array{0: int, 1: float, 2: int}>>, parts: array<int, list<array{0: int, 1: int}>>, major: array<int, int>}
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

    /**
     * Gare absente du graphe : distance maximale (m) entre son point et une voie en service de l'une de ses
     * lignes. Le point d'une gare est souvent posé sur le bâtiment voyageurs, à quelques dizaines de mètres de
     * l'axe des voies (51 m à Versailles-Matelots).
     */
    public const CLOSED_ATTACH_M = 100;

    /** Gare absente du graphe : parcours maximal sur les voies (m) jusqu'aux gares du graphe. */
    public const CLOSED_WALK_M = 40000;

    /** Installation technique ou de fret dans un nom de gare : écartée quand la ville a une autre gare. */
    private const TECHNICAL_STATION = '/\((?:IE|MIN|ITE)\)|\bn°\s*\d|\b(?:marchandises|triage|fret|dépôt|atelier)\b|\s\d+$/iu';

    /** Écart maximal entre les gares d'une même ville. */
    private const CITY_SPREAD_KM = 30.0;

    /** Part minimale des gares « <nom>-… » groupées dans une même ville quand d'autres sont loin. */
    private const CITY_MAJORITY = 0.75;

    /** @var array<int, int>|null année de l'ère → identifiant */
    private ?array $eras = null;

    private ?int $currentEraYear = null;

    /** @var array<int, Graph|null> */
    private array $graphs = [];

    /**
     * Rattachements des gares absentes du graphe (voir attach()) : clé du graphe → gare → arêtes
     * [gare du graphe, coût, arête] et leurs parts ; liste vide si rien ne la rattache.
     *
     * @var array<int, array<int, list<array{0: int, 1: float, 2: int, 3: list<array{0: int, 1: int}>}>>>
     */
    private array $attachments = [];

    /** Compteur des arêtes de rattachement (identifiants négatifs, jamais dans `station_edges`). */
    private int $attachEdges = 0;

    /**
     * Voies en service d'une ère (`network_edges`) : nœud → [[nœud voisin, longueur, ligne, hors voyageurs]],
     * et gare de chaque nœud de gare.
     *
     * @var array<int, array{adj: array<int, list<array{0: int, 1: int, 2: ?int, 3: bool}>>, station: array<int, int>}>
     */
    private array $networks = [];

    /** @var array<int, array<int, true>> gare → lignes où elle a un PK de source sûre */
    private array $reliableLines = [];

    /** @var array<int, list<array{0: int, 1: int}>> arête → parts, tronc commun rendu à la ligne prioritaire */
    private array $trunks = [];

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
     * pour être une même ville. Sans gare ouverte de ce nom, la gare de ce nom fermée aux voyageurs à l'ère, si
     * elle se rattache aux voies de l'ère (voir attach()) ; puis les gares « <nom>-… ».
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
        $open = fn (int $id): bool => isset($graph['adj'][$id]);
        $found = array_values(array_filter($this->byKey[$key] ?? [], $open));
        // Gare de ce nom fermée aux voyageurs à l'ère : avant les gares « <nom>-… » (Sens, absente du graphe de
        // 1970, partait sinon de Sens-Saint-Clément, sur la ligne de Coolus).
        if ($found === []) {
            $found = array_values(array_filter($this->byKey[$key] ?? [], fn (int $id): bool => $this->attach($id, $eraYear, true, false) !== []));
        }
        $prefixed = false;
        if ($found === [] && mb_strlen($key) >= 3) {
            $prefixed = true;
            foreach ($this->stations as $id => $station) {
                if ($open($id) && (str_starts_with($station['key'], $key.'-') || str_starts_with($station['key'], $key.' '))) {
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
        $this->loadEras();
        // Rester sur sa ligne : aux ères passées seulement (et sur le graphe actuel repris pour elles par
        // bridgeGap()). Aujourd'hui, une ligne plus courte en service peut être l'itinéraire réel : entre Cenon et
        // Ambarès, rien ne dit encore si les trains de Bordeaux vers Libourne prennent les voies de Chartres -
        // Bordeaux depuis 2017.
        $result = $this->routeOn($from, $to, $eraYear, $fast, $electric, $eraYear !== $this->currentEraYear);
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
     * @param  Graph  $graph
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
    private function routeOn(array $from, array $to, int $eraYear, bool $fast, bool $electric, bool $stayOnLine = true): array
    {
        if ($from === [] || $to === []) {
            return ['status' => 'no_path', 'lines' => [], 'ratio' => null, 'from' => null, 'to' => null];
        }
        $graph = $this->graph($eraYear, $fast, $electric);
        if ($graph === null) {
            return ['status' => 'no_path', 'lines' => [], 'ratio' => null, 'from' => null, 'to' => null];
        }
        $graph = $this->withEnds($graph, array_merge($from, $to), $eraYear, $fast, $electric);
        if (array_intersect($from, $to) !== []) {
            return ['status' => 'same', 'lines' => [], 'ratio' => null, 'from' => null, 'to' => null];
        }
        $path = PathfindingService::dijkstra($graph['adj'], $from, $to);
        if ($path === null || $path['edges'] === []) {
            return ['status' => 'no_path', 'lines' => [], 'ratio' => null, 'from' => null, 'to' => null];
        }
        $onPath = [];
        foreach ($path['edges'] as $eid) {
            foreach ($graph['parts'][$eid] as [$line]) {
                $onPath[$line] = true;
            }
        }
        $stay = $stayOnLine ? $this->stayOnLine($graph, $path) : null;
        $path = $stay ?? $path;
        $lengths = [];
        foreach ($path['edges'] as $eid) {
            foreach ($this->trunkParts($eid, $graph['parts'][$eid]) as [$line, $len]) {
                $lengths[$line] = ($lengths[$line] ?? 0) + $len;
            }
        }
        // Dans l'ordre du trajet, sans les bouts de jonction (quelques centaines de mètres d'une ligne voisine).
        // Série électrique : sans les lignes non électrifiées portées par une arête qu'elle partage avec une
        // ligne électrifiée (tronc commun avant Cahors, où Monsempron-Libos - Cahors rejoint Paris - Toulouse).
        $lines = array_keys(array_filter($lengths, fn (int $len, int $line): bool => $len >= self::MIN_LINE_M
            && (! $electric || isset($this->electrified[$line])), ARRAY_FILTER_USE_BOTH));
        if ($this->crossesGap($graph, $path, $lines, $eraYear)) {
            return ['status' => 'no_path', 'lines' => [], 'ratio' => null, 'from' => null, 'to' => null];
        }
        // Deux gares d'une même ligne restées sur elle : la variante plus courte qu'elle écarte n'est pas une
        // ambiguïté.
        $ratio = $stay === null ? $this->alternative($graph, $path, $onPath) : null;

        $ends = ['from' => $path['stations'][0], 'to' => $path['stations'][count($path['stations']) - 1], 'cost' => (float) $path['cost']];

        return $ratio !== null
            ? ['status' => 'ambiguous', 'lines' => $lines, 'ratio' => round($ratio, 3)] + $ends
            : ['status' => 'ok', 'lines' => $lines, 'ratio' => null] + $ends;
    }

    /**
     * Rapport de longueur du meilleur autre itinéraire assez différent (voir DETOUR_TOLERANCE et
     * LOCAL_TOLERANCE), ou null s'il n'y en a pas.
     *
     * @param  Graph  $graph
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
     * Relation qui part d'une gare rattachée (absente du graphe de l'ère) ou y arrive, et qui contourne un trou
     * du graphe : chemin de plus de DIRECT_DETOUR fois la distance à vol d'oiseau (longueur réelle, sans la
     * pénalité des voies sans voyageurs), ou ligne retenue où aucun des deux arrêts n'a de PK sûr. Elle est
     * refusée plutôt que de lier une ligne voisine (Sens et Montereau, données fermées aux voyageurs en 1970,
     * rejoignaient Paris - Marseille par Coolus - Sens et Saint-Julien - Saint-Florentin).
     *
     * @param  Graph  $graph
     * @param  array{cost: float, stations: list<int>, edges: list<int>}  $path
     * @param  list<int>  $lines  lignes retenues pour la relation
     */
    private function crossesGap(array $graph, array $path, array $lines, int $eraYear): bool
    {
        $full = $this->graph($eraYear);
        $a = $path['stations'][0];
        $b = $path['stations'][count($path['stations']) - 1];
        if (isset($full['adj'][$a]) && isset($full['adj'][$b])) {
            return false;
        }
        $length = 0;
        foreach ($path['edges'] as $eid) {
            foreach ($graph['parts'][$eid] as [, $len]) {
                $length += $len;
            }
        }
        $this->loadStations();
        $own = $this->reliableLinesOf($a) + $this->reliableLinesOf($b);
        foreach ($lines as $line) {
            if (! isset($own[$line])) {
                return true;
            }
        }

        return $length > self::DIRECT_DETOUR * 1000 * $this->distanceKm($a, $b);
    }

    /**
     * Rester sur sa ligne (voir la classe) : chemin entre les deux bouts du plus court chemin par les seules
     * arêtes d'une ligne M (M majoritaire, ou deux gares à PK sûr sur M) dont les deux gares ont un PK de source sûre, s'il fait au plus LOCAL_TOLERANCE
     * fois le plus court ; null si aucune ligne ne s'y prête ou si le plus court chemin la suit déjà.
     * Pas de règle quand le plus court chemin emprunte une LGV : un TGV Paris - Lyon ne reprend pas la ligne
     * classique, même si ses deux gares en sont.
     *
     * @param  Graph  $graph
     * @param  array{cost: float, stations: list<int>, edges: list<int>}  $path
     * @return array{cost: float, stations: list<int>, edges: list<int>}|null
     */
    private function stayOnLine(array $graph, array $path): ?array
    {
        $a = $path['stations'][0];
        $b = $path['stations'][count($path['stations']) - 1];
        $common = array_intersect_key($this->reliableLinesOf($a), $this->reliableLinesOf($b));
        if ($common === []) {
            return null;
        }
        $onPath = [];
        foreach ($path['edges'] as $eid) {
            if (isset($this->lgv[$graph['major'][$eid]])) {
                return null;
            }
            foreach ($graph['parts'][$eid] as [$line]) {
                $onPath[$line] = true;
            }
        }
        $best = null;
        foreach (array_keys($common) as $line) {
            if (! isset($onPath[$line]) || isset($this->junctions[$line])) {
                continue;
            }
            // Arête de M : M y porte la plus grande part, ou ses deux gares ont un PK sûr sur M (tronc commun
            // Anor - Hirson, que Fives - Hirson partage avec La Plaine - Hirson) ; raccordements tolérés.
            $allowed = fn (int $eid, int $u, int $v): bool => $graph['major'][$eid] === $line || isset($this->junctions[$graph['major'][$eid]])
                || (isset($this->reliableLinesOf($u)[$line]) && isset($this->reliableLinesOf($v)[$line]));
            $off = false;
            foreach ($path['edges'] as $k => $eid) {
                $off = $off || ! $allowed($eid, $path['stations'][$k], $path['stations'][$k + 1]);
            }
            if (! $off) {
                return null;
            }
            $kept = self::search($graph['adj'], $a, $b, $path['cost'] * self::LOCAL_TOLERANCE, $allowed);
            if ($kept !== null && ($best === null || $kept['cost'] < $best['cost'])) {
                $best = $kept;
            }
        }

        return $best;
    }

    /**
     * Plus court chemin d'une gare à une autre par les seules arêtes permises, jusqu'à la borne de coût.
     *
     * @param  array<int, list<array{0: int, 1: float, 2: int}>>  $adj
     * @param  callable(int, int, int): bool  $allowed  arête permise (arête, gare de départ, gare d'arrivée)
     * @return array{cost: float, stations: list<int>, edges: list<int>}|null
     */
    private static function search(array $adj, int $source, int $target, float $limit, callable $allowed): ?array
    {
        $dist = [$source => 0.0];
        $prev = [];
        $pq = new SplPriorityQueue;
        $pq->setExtractFlags(SplPriorityQueue::EXTR_BOTH);
        $pq->insert($source, 0.0);
        while (! $pq->isEmpty()) {
            $item = $pq->extract();
            $u = $item['data'];
            if (-$item['priority'] > $dist[$u]) {
                continue;
            }
            if ($u === $target) {
                $stations = [$u];
                $edges = [];
                for ($n = $u; isset($prev[$n]); $n = $prev[$n][0]) {
                    $edges[] = $prev[$n][1];
                    $stations[] = $prev[$n][0];
                }

                return ['cost' => $dist[$u], 'stations' => array_reverse($stations), 'edges' => array_reverse($edges)];
            }
            foreach ($adj[$u] ?? [] as [$v, $w, $eid]) {
                $alt = $dist[$u] + $w;
                if ($alt <= $limit && $alt < ($dist[$v] ?? INF) && $allowed($eid, $u, $v)) {
                    $dist[$v] = $alt;
                    $prev[$v] = [$u, $eid];
                    $pq->insert($v, -$alt);
                }
            }
        }

        return null;
    }

    /**
     * Lignes où la gare a un PK de source sûre (JunctionRepair::RELIABLE_PK_SOURCES).
     *
     * @return array<int, true>
     */
    private function reliableLinesOf(int $station): array
    {
        if (! isset($this->reliableLines[$station])) {
            $lines = DB::table('station_lines')->where('station_id', $station)
                ->whereIn('pk_source', JunctionRepair::RELIABLE_PK_SOURCES)->whereNotNull('pk_m')->pluck('line_id')->all();
            $this->reliableLines[$station] = array_fill_keys(array_map('intval', $lines), true);
        }

        return $this->reliableLines[$station];
    }

    /**
     * Parts d'une arête du graphe, tronc commun rendu à la ligne prioritaire (voir la classe) : pour chaque
     * ligne M de l'arête, la longueur de l'arête à moins de TrackPriority::TOLERANCE_M de ses voies en service,
     * au-delà de sa propre part, est reprise aux lignes moins prioritaires de l'arête (la moins prioritaire
     * d'abord), si elle atteint TrackPriority::MIN_SHARED_M. Raccordements et arêtes de rattachement
     * (identifiant négatif) non touchés.
     *
     * @param  list<array{0: int, 1: int}>  $parts
     * @return list<array{0: int, 1: int}>
     */
    private function trunkParts(int $eid, array $parts): array
    {
        $lengths = [];
        foreach ($parts as [$line, $len]) {
            if ($len > 0 && ! isset($this->junctions[$line])) {
                $lengths[$line] = ($lengths[$line] ?? 0) + $len;
            }
        }
        if ($eid < 0 || count($lengths) < 2) {
            return $parts;
        }
        if (! isset($this->trunks[$eid])) {
            [$near, $ends] = $this->trackNear($eid, array_keys($lengths));
            // Seule une ligne dont les deux gares de l'arête ont un PK sûr reprend le tronc commun : dans un
            // faisceau de plusieurs lignes parallèles (Paris-Est - Noisy-le-Sec), les voies voisines à moins de
            // 10 m ne sont pas une même voie.
            $receivers = $ends === [] ? [] : array_keys(array_intersect_key($this->reliableLinesOf($ends[0]), $this->reliableLinesOf($ends[1])));
            // Une ligne garde sa part si l'une des gares de l'arête a un PK sûr sur elle sans en être une extrémité
            // nommée : elle la traverse (Anor, sur La Plaine - Hirson et Anor). Cahors, extrémité de
            // Monsempron-Libos - Cahors, ne protège pas cette ligne.
            $kept = [];
            $this->loadStations();
            $this->loadNamedEnds();
            foreach ($ends as $station) {
                foreach (array_keys($this->reliableLinesOf($station)) as $line) {
                    if (! in_array($this->stations[$station]['key'] ?? '', $this->namedEnds[$line] ?? [], true)) {
                        $kept[] = $line;
                    }
                }
            }
            $this->trunks[$eid] = self::shareTrunk($parts, $lengths, $near, $receivers, $kept);
        }

        return $this->trunks[$eid];
    }

    /**
     * Répartition des parts selon la voie suivie (fonction pure, voir trunkParts()).
     *
     * @param  list<array{0: int, 1: int}>  $parts
     * @param  array<int, int>  $lengths  ligne (hors raccordements) → longueur sur l'arête
     * @param  array<int, array{near: int, priority: int}>  $near  ligne → longueur de l'arête près de ses voies, priorité
     * @param  list<int>  $receivers  lignes à PK sûr aux deux gares de l'arête : seules à reprendre une longueur, jamais reprises
     * @param  list<int>  $kept  lignes qui gardent leur part (elles traversent une gare de l'arête)
     * @return list<array{0: int, 1: int}>
     */
    public static function shareTrunk(array $parts, array $lengths, array $near, array $receivers, array $kept = []): array
    {
        $order = array_keys(array_intersect_key($lengths, $near));
        // Plus prioritaire d'abord ; à égalité, la plus petite ligne (TrackPriority).
        usort($order, fn (int $x, int $y): int => [$near[$y]['priority'], $x] <=> [$near[$x]['priority'], $y]);
        $moved = false;
        foreach ($order as $k => $line) {
            if (! in_array($line, $receivers, true)) {
                continue;
            }
            $excess = $near[$line]['near'] - $lengths[$line];
            for ($j = count($order) - 1; $j > $k && $excess >= TrackPriority::MIN_SHARED_M; $j--) {
                $lower = $order[$j];
                $take = min($lengths[$lower], $excess);
                // Une ligne dont les deux gares de l'arête ont aussi un PK sûr garde sa part (tronc Hirson -
                // Origny-en-Thiérache, gare des deux lignes).
                if ($take <= 0 || in_array($lower, $receivers, true) || in_array($lower, $kept, true)) {
                    continue;
                }
                $lengths[$lower] -= $take;
                $lengths[$line] += $take;
                $excess -= $take;
                $moved = true;
            }
        }
        if (! $moved) {
            return $parts;
        }
        $out = [];
        foreach ($parts as [$line, $len]) {
            if (! isset($lengths[$line])) {
                $out[] = [$line, $len];
            } elseif ($lengths[$line] !== -1) {
                $out[] = [$line, $lengths[$line]];
                $lengths[$line] = -1;
            }
        }

        return $out;
    }

    /**
     * Pour chaque ligne, longueur de l'arête à moins de TrackPriority::TOLERANCE_M de ses voies en service à
     * l'ère de l'arête, et priorité de ces voies (TrackPriority::drawRankSql, la meilleure).
     *
     * @param  list<int>  $lines
     * @return array{0: array<int, array{near: int, priority: int}>, 1: list<int>} longueurs par ligne, gares de l'arête
     */
    private function trackNear(int $eid, array $lines): array
    {
        $priority = TrackPriority::drawRankSql(Lgv::sql('l', 'ls'), 'l', 'ls');
        $tolerance = TrackPriority::TOLERANCE_M;
        $rows = DB::select(<<<SQL
            WITH e AS (SELECT geom, ST_Transform(geom, 2154) AS g, era_id, from_station_id, to_station_id FROM station_edges WHERE id = ? AND geom IS NOT NULL)
            SELECT ls.line_id, MAX({$priority}) AS priority, MIN(e.from_station_id) AS a, MIN(e.to_station_id) AS b,
                   ST_Length(ST_Intersection(e.g, ST_Buffer(ST_Union(ST_Transform(ls.geom, 2154)), {$tolerance}))) AS near_m
            FROM e
            JOIN line_segments ls ON ls.era_id = e.era_id AND ls.geom && ST_Expand(e.geom, 0.001)
                 AND ls.status IN ('in_service', 'single_track')
                 AND ST_DWithin(ST_Transform(ls.geom, 2154), e.g, {$tolerance})
            JOIN lines l ON l.id = ls.line_id
            WHERE ls.line_id = ANY (?::bigint[])
            GROUP BY ls.line_id, e.g
            SQL, [$eid, '{'.implode(',', $lines).'}']);
        $near = [];
        $ends = [];
        foreach ($rows as $row) {
            $near[(int) $row->line_id] = ['near' => (int) round((float) $row->near_m), 'priority' => (int) $row->priority];
            $ends = [(int) $row->a, (int) $row->b];
        }

        return [$near, $ends];
    }

    /**
     * Graphe de la relation : celui de l'ère, plus les arêtes de rattachement de ses arrêts absents du graphe
     * de l'ère (gares fermées résolues par resolve()). Copie locale : une gare rattachée ne sert que
     * d'extrémité à sa relation, jamais de passage à une autre (ses rattachements franchiraient les trous du
     * graphe). Un arrêt présent dans le graphe de l'ère mais retiré de cette variante (ligne non électrifiée
     * pour une série électrique, LGV pour une série lente) n'est pas rattaché.
     *
     * @param  Graph  $graph
     * @param  list<int>  $stations
     * @return Graph
     */
    private function withEnds(array $graph, array $stations, int $eraYear, bool $fast, bool $electric): array
    {
        $full = $this->graph($eraYear);
        foreach (array_unique($stations) as $station) {
            if (isset($graph['adj'][$station]) || isset($full['adj'][$station])) {
                continue;
            }
            foreach ($this->attach($station, $eraYear, $fast, $electric) as [$open, $cost, $eid, $parts]) {
                $graph['adj'][$station][] = [$open, $cost, $eid];
                $graph['adj'][$open][] = [$station, $cost, $eid];
                $graph['parts'][$eid] = $parts;
                $graph['major'][$eid] = self::majorLine($parts);
            }
        }

        return $graph;
    }

    /**
     * Rattachement d'une gare absente du graphe (voir la classe) : depuis le point le plus proche d'une voie en
     * service de l'une de ses lignes (celles où elle a un PK sûr, s'il y en a), à moins de CLOSED_ATTACH_M,
     * parcours des voies de ces lignes et des raccordements à l'ère (`network_edges`, mêmes exclusions que le
     * graphe : LGV sans `$fast`, lignes non électrifiées avec `$electric`, voies sans
     * voyageurs pénalisées) jusqu'aux premières gares du graphe, à CLOSED_WALK_M au plus. Chaque gare atteinte
     * donne une arête de rattachement (identifiant négatif), avec les lignes parcourues. Le parcours ne traverse
     * pas une gare du graphe. Liste vide : rien ne la rattache.
     *
     * @return list<array{0: int, 1: float, 2: int, 3: list<array{0: int, 1: int}>}>
     */
    private function attach(int $station, int $eraYear, bool $fast, bool $electric): array
    {
        $graphKey = $eraYear * 4 + ($fast ? 1 : 0) + ($electric ? 2 : 0);
        if (! isset($this->attachments[$graphKey][$station])) {
            $graph = $this->graph($eraYear, $fast, $electric);
            $edges = [];
            if ($graph !== null && $this->closedAt($station, $eraYear)) {
                foreach ($this->walkToGraph($station, (int) $this->eras[$eraYear], $graph, $fast, $electric) as $open => [$cost, $lines]) {
                    $parts = [];
                    foreach ($lines as $line => $len) {
                        $parts[] = [$line, $len];
                    }
                    $edges[] = [$open, $cost, -(++$this->attachEdges), $parts];
                }
            }
            $this->attachments[$graphKey][$station] = $edges;
        }

        return $this->attachments[$graphKey][$station];
    }

    /**
     * La gare est-elle fermée aux voyageurs à l'ère (`station_eras.status` = `declassified`) ? Une gare ouverte que
     * le graphe a perdue n'est pas rattachée : ce serait franchir un trou du graphe.
     */
    private function closedAt(int $station, int $eraYear): bool
    {
        return DB::table('station_eras')->where('station_id', $station)->where('era_id', $this->eras[$eraYear] ?? 0)
            ->where('status', 'declassified')->exists();
    }

    /**
     * Gares du graphe atteintes depuis une gare absente (voir attach()) : gare → [coût, longueur par ligne].
     *
     * @param  Graph  $graph
     * @return array<int, array{0: float, 1: array<int, int>}>
     */
    private function walkToGraph(int $station, int $eraId, array $graph, bool $fast, bool $electric): array
    {
        // Voie de départ sur une ligne où la gare a un PK sûr, s'il y en a une : à Sens, la ligne de Coolus
        // (PK projeté) passe plus près que Paris - Marseille.
        $reliable = '{'.implode(',', JunctionRepair::RELIABLE_PK_SOURCES).'}';
        $start = DB::selectOne(<<<'SQL'
            WITH p AS (SELECT geom, ST_Transform(geom, 2154) AS g FROM stations WHERE id = ? AND geom IS NOT NULL)
            SELECT ne.from_node_id, ne.to_node_id, ne.length_m, ne.line_id,
                   CASE WHEN ST_Distance(ST_StartPoint(ne.geom), a.geom) <= ST_Distance(ST_EndPoint(ne.geom), a.geom)
                        THEN ST_LineLocatePoint(ST_Transform(ne.geom, 2154), p.g)
                        ELSE 1 - ST_LineLocatePoint(ST_Transform(ne.geom, 2154), p.g) END AS fraction
            FROM p
            JOIN network_edges ne ON ne.era_id = ? AND ne.geom && ST_Expand(p.geom, 0.002) AND NOT ne.is_heritage
                 AND ST_DWithin(ST_Transform(ne.geom, 2154), p.g, ?)
                 AND ne.line_id IN (SELECT line_id FROM station_lines WHERE station_id = ? AND (pk_source = ANY (?::text[])
                     OR NOT EXISTS (SELECT 1 FROM station_lines r WHERE r.station_id = ? AND r.pk_source = ANY (?::text[]))))
            JOIN network_nodes a ON a.id = ne.from_node_id
            ORDER BY ST_Distance(ST_Transform(ne.geom, 2154), p.g)
            LIMIT 1
            SQL, [$station, $eraId, self::CLOSED_ATTACH_M, $station, $reliable, $station, $reliable]);
        if ($start === null) {
            return [];
        }
        $network = $this->network($eraId);
        // Parcours sur les lignes de la gare (celles à PK sûr s'il y en a) et les raccordements : un graphe troué
        // à l'ère (Sens et Montereau absents en 1970) ne fait pas passer la gare par une ligne voisine.
        $own = $this->reliableLinesOf($station);
        if ($own === []) {
            $own = array_fill_keys(array_map('intval', DB::table('station_lines')->where('station_id', $station)->pluck('line_id')->all()), true);
        }
        $allowed = fn (?int $line): bool => $line === null || isset($this->junctions[$line])
            || (isset($own[$line]) && ($fast || ! isset($this->lgv[$line])) && (! $electric || isset($this->electrified[$line])));
        if (! $allowed($start->line_id === null ? null : (int) $start->line_id)) {
            return [];
        }
        $length = (int) $start->length_m;
        $fraction = (float) $start->fraction;
        $line = $start->line_id === null ? null : (int) $start->line_id;
        // Coût et lignes parcourues jusqu'à chaque nœud ; les deux bouts de la voie de départ.
        $dist = [];
        $lines = [];
        $pq = new SplPriorityQueue;
        $pq->setExtractFlags(SplPriorityQueue::EXTR_BOTH);
        foreach ([[(int) $start->from_node_id, $fraction * $length], [(int) $start->to_node_id, (1 - $fraction) * $length]] as [$node, $len]) {
            if ($len < ($dist[$node] ?? INF)) {
                $dist[$node] = $len;
                $lines[$node] = $line === null ? [] : [$line => (int) round($len)];
                $pq->insert($node, -$len);
            }
        }
        $reached = [];
        $done = [];
        while (! $pq->isEmpty()) {
            $u = $pq->extract()['data'];
            if (isset($done[$u])) {
                continue;
            }
            $done[$u] = true;
            $open = $network['station'][$u] ?? null;
            if ($open !== null && $open !== $station && isset($graph['adj'][$open])) {
                $reached[$open] = isset($reached[$open]) && $reached[$open][0] <= $dist[$u] ? $reached[$open] : [$dist[$u], $lines[$u]];

                continue;
            }
            foreach ($network['adj'][$u] ?? [] as [$v, $len, $edgeLine, $nonPassenger]) {
                if (! $allowed($edgeLine)) {
                    continue;
                }
                $alt = $dist[$u] + $len * ($nonPassenger ? PathfindingService::NON_PASSENGER_FACTOR : 1.0);
                if ($alt <= self::CLOSED_WALK_M && $alt < ($dist[$v] ?? INF)) {
                    $dist[$v] = $alt;
                    $lines[$v] = $lines[$u];
                    if ($edgeLine !== null) {
                        $lines[$v][$edgeLine] = ($lines[$v][$edgeLine] ?? 0) + $len;
                    }
                    $pq->insert($v, -$alt);
                }
            }
        }

        return $reached;
    }

    /**
     * Voies en service d'une ère, pour attach().
     *
     * @return array{adj: array<int, list<array{0: int, 1: int, 2: ?int, 3: bool}>>, station: array<int, int>}
     */
    private function network(int $eraId): array
    {
        if (! isset($this->networks[$eraId])) {
            $adj = [];
            $rows = DB::table('network_edges')->where('era_id', $eraId)->where('is_heritage', false)
                ->get(['from_node_id', 'to_node_id', 'length_m', 'line_id', 'traffic_type', 'network']);
            foreach ($rows as $r) {
                $line = $r->line_id === null ? null : (int) $r->line_id;
                $nonPassenger = $r->traffic_type === 'freight' || in_array($r->network, ['militaire', 'particulier'], true);
                $adj[(int) $r->from_node_id][] = [(int) $r->to_node_id, (int) $r->length_m, $line, $nonPassenger];
                $adj[(int) $r->to_node_id][] = [(int) $r->from_node_id, (int) $r->length_m, $line, $nonPassenger];
            }
            $station = DB::table('network_nodes')->where('era_id', $eraId)->whereNotNull('station_id')
                ->pluck('station_id', 'id')->mapWithKeys(fn ($s, $id): array => [(int) $id => (int) $s])->all();
            $this->networks[$eraId] = ['adj' => $adj, 'station' => $station];
        }

        return $this->networks[$eraId];
    }

    /**
     * Ligne qui porte la plus grande part d'une arête.
     *
     * @param  list<array{0: int, 1: int}>  $parts
     */
    private static function majorLine(array $parts): int
    {
        $best = $parts[0][0] ?? 0;
        $max = -1;
        foreach ($parts as [$line, $len]) {
            if ($len > $max) {
                [$best, $max] = [$line, $len];
            }
        }

        return $best;
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
     * @return Graph|null
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
            $major = [];
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
                $major[$eid] = self::majorLine($parts[$eid]);
            }
            $this->graphs[$graphKey] = $adj === [] ? null : ['adj' => $adj, 'parts' => $parts, 'major' => $major];
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
