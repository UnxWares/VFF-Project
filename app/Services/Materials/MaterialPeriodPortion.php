<?php

namespace App\Services\Materials;

use App\Services\Signalling\SignallingSectionLocator;
use Illuminate\Support\Facades\DB;

/**
 * Portion de la ligne parcourue pendant une période de circulation (`line_material_periods`), quand la source
 * la donne, placée par ses gares d'extrémité et leurs PK calés comme l'historique de la signalisation
 * (SignallingSectionLocator : PK de SNCF Réseau, d'une liste ou d'un schéma recalé, jamais l'abscisse brute
 * d'un tracé). Rien n'est deviné : au moindre doute, la période reste sur la ligne entière (null).
 *
 * Trois sources, dans cet ordre :
 *   1. le titre de section de l'article de la ligne (« Section de Saint-Just-en-Chaussée à Cambrai ») : les
 *      deux lieux sont des gares ou des points remarquables de la ligne (`stations`, `landmarks`), sinon
 *      `unlocated` ;
 *   2. la relation citée par l'article de la série (MaterialRelations), arrêts identifiés en base, ou
 *      l'itinéraire d'un article de ligne (database/data/wikipedia_itineraries.php), arrêts nommés ;
 *   3. la relation ou la portion nommée par la phrase datée (MaterialPortionReader : « entre Culoz et
 *      Genève », « Lyon - Ambérieu - Culoz - Bellegarde - Genève », « de Grenoble à Pontcharra »). Une phrase
 *      qui en nomme plusieurs n'est retenue que si une seule touche la ligne.
 *
 * Relation (2 et 3) : les arrêts sur la ligne (gares au PK calé) se suivent ; ceux d'avant et d'après sont hors
 * de la ligne (« Paris - Caen - Cherbourg » sur Paris - Le Havre ou sur Mantes-la-Jolie - Cherbourg) : la
 * relation quitte alors la ligne, de chaque côté, à une gare de jonction, gare de la ligne au PK calé partagée avec
 *   - une autre ligne de la relation (relation lue par l'import du matériel, ses lignes sont connues) : il en
 *     faut exactement une par extrémité hors de la ligne ;
 *   - sinon une ligne de la gare d'arrivée (une seule jonction possible pour chaque extrémité).
 * Une relation qui quitte la ligne puis y revient, un nom qui désigne plusieurs gares éloignées de la ligne, une
 * gare de la ligne sans PK calé, une jonction ambiguë : rien.
 *
 * Résultat : `location` (`stations`, `landmarks`, `unlocated` pour un titre de section, `line` quand la
 * portion couvre au moins WHOLE_LINE_SHARE de la ligne), gares d'extrémité (dans l'ordre des PK) et PK.
 *
 * @phpstan-type Portion array{location: string, from_station_id: ?int, to_station_id: ?int, pk_start_m: ?int, pk_end_m: ?int}
 */
final class MaterialPeriodPortion
{
    /** Part de la ligne (entre ses gares extrêmes au PK calé) au-delà de laquelle la portion est la ligne entière. */
    public const WHOLE_LINE_SHARE = 0.9;

    /** Écart maximal (m) entre deux gares retenues pour un même lieu ou une même jonction. */
    private const SAME_PLACE_M = 1000;

    /** @var array<int, array{name: string, key: string}>|null toutes les gares */
    private ?array $allStations = null;

    /** @var array<string, list<int>> clé de nom → gares */
    private array $byKey = [];

    /** @var array<int, list<int>> gare → lignes (`station_lines`, toute origine de PK) */
    private array $linesOf = [];

    public function __construct(
        private readonly SignallingSectionLocator $places = new SignallingSectionLocator,
        private readonly MaterialPortionReader $reader = new MaterialPortionReader,
    ) {}

    /**
     * Portion de la ligne pour une période, ou null quand la source n'en nomme aucune (ou pas sûrement).
     *
     * @param  list<array{id: ?int, name: string}>|null  $stops  arrêts de la relation citée, dans l'ordre
     * @param  list<int>|null  $relationLines  lignes de la relation, quand l'import les a trouvées
     * @param  ?string  $text  phrase qui date la période (texte brut)
     * @return Portion|null
     */
    public function locate(int $lineId, ?string $sectionFrom, ?string $sectionTo, ?array $stops = null, ?array $relationLines = null, ?string $text = null): ?array
    {
        if ($sectionFrom !== null && $sectionTo !== null) {
            $placed = $this->places->locate($lineId, 'section', $sectionFrom, $sectionTo, null, null);

            return $placed['location'] === null ? null : ['location' => (string) $placed['location']] + array_intersect_key($placed, array_flip(['from_station_id', 'to_station_id', 'pk_start_m', 'pk_end_m']));
        }
        if ($stops !== null && count($stops) >= 2) {
            return $this->ofStops($lineId, $stops, $relationLines);
        }
        if ($text === null || trim($text) === '') {
            return null;
        }
        $placed = [];
        foreach ($this->reader->relations($text) as $names) {
            $portion = $this->ofStops($lineId, array_map(fn (string $n): array => ['id' => null, 'name' => $n], $this->merged($lineId, $names)), null);
            if ($portion !== null) {
                $placed[] = $portion;
            } elseif ($this->touches($lineId, $names)) {
                // Une relation de la phrase passe sur la ligne sans être placée : on ne sait pas laquelle vaut.
                return null;
            }
        }

        return count($placed) === 1 ? $placed[0] : null;
    }

    /**
     * Portion d'une relation sur la ligne (voir la classe), ou null.
     *
     * @param  list<array{id: ?int, name: string}>  $stops
     * @param  list<int>|null  $relationLines
     * @return Portion|null
     */
    public function ofStops(int $lineId, array $stops, ?array $relationLines): ?array
    {
        $onLine = [];
        foreach ($this->places->stations($lineId) as $s) {
            $onLine[$s['id']] = $s['pk_m'];
        }
        if (count($onLine) < 2) {
            return null;
        }
        $points = [];
        $placed = [];
        foreach ($stops as $k => $stop) {
            $point = $this->onLine($lineId, $stop, $onLine);
            if ($point === false) {
                return null;
            }
            if ($point !== null) {
                $points[] = $point;
                $placed[] = $k;
            }
        }
        // Les arrêts sur la ligne se suivent : la relation la quitte au plus une fois de chaque côté.
        if ($placed !== [] && $placed !== range($placed[0], $placed[count($placed) - 1])) {
            return null;
        }
        // Arrêt hors de la ligne le plus proche de chaque côté : celui vers lequel la relation la quitte. Sans les
        // lignes de la relation, une relation qui ne s'arrête nulle part sur la ligne n'y est pas placée
        // (« Paris - Marseille » cité par l'article d'une ligne qu'elle traverse peut-être).
        if ($placed === []) {
            if ($relationLines === null) {
                return null;
            }
            $offEnds = [$stops[0], $stops[count($stops) - 1]];
        } else {
            $offEnds = array_values(array_filter([$stops[$placed[0] - 1] ?? null, $stops[$placed[count($placed) - 1] + 1] ?? null]));
        }
        if ($offEnds !== []) {
            $exits = $this->exits($lineId, $offEnds, $relationLines, $onLine);
            if ($exits === null) {
                return null;
            }
            array_push($points, ...$exits);
        }
        usort($points, fn (array $a, array $b): int => $a['pk_m'] <=> $b['pk_m']);
        [$first, $end] = [$points[0], $points[count($points) - 1]];
        if ($end['pk_m'] - $first['pk_m'] <= self::SAME_PLACE_M) {
            return null;
        }
        $span = max($onLine) - min($onLine);

        return [
            'location' => self::WHOLE_LINE_SHARE * $span <= $end['pk_m'] - $first['pk_m'] ? 'line' : 'stations',
            'from_station_id' => $first['id'],
            'to_station_id' => $end['id'],
            'pk_start_m' => $first['pk_m'],
            'pk_end_m' => $end['pk_m'],
        ];
    }

    /**
     * L'arrêt sur la ligne : sa gare et son PK calé, null s'il est hors de la ligne, false si on ne peut pas le
     * dire (gare de la ligne sans PK calé, nom de plusieurs gares éloignées).
     *
     * @param  array{id: ?int, name: string}  $stop
     * @param  array<int, int>  $onLine  gare → PK calé
     * @return array{id: int, pk_m: int}|false|null
     */
    private function onLine(int $lineId, array $stop, array $onLine): array|false|null
    {
        if ($stop['id'] !== null) {
            if (isset($onLine[$stop['id']])) {
                return ['id' => $stop['id'], 'pk_m' => $onLine[$stop['id']]];
            }

            return in_array($lineId, $this->lines($stop['id']), true) ? false : null;
        }
        $station = $this->places->station($lineId, $stop['name']);
        if ($station !== null) {
            return ['id' => $station['id'], 'pk_m' => $station['pk_m']];
        }
        // Gare de la ligne sans PK calé, ou plusieurs gares de ce nom sur la ligne : on ne sait pas la placer.
        foreach ($this->named($stop['name']) as $id) {
            if (in_array($lineId, $this->lines($id), true)) {
                return false;
            }
        }

        return null;
    }

    /**
     * Gares de jonction par lesquelles la relation quitte la ligne vers ses extrémités hors de la ligne : une par
     * extrémité, distinctes, sinon null.
     *
     * @param  non-empty-list<array{id: ?int, name: string}>  $offEnds
     * @param  list<int>|null  $relationLines
     * @param  array<int, int>  $onLine
     * @return list<array{id: int, pk_m: int}>|null
     */
    private function exits(int $lineId, array $offEnds, ?array $relationLines, array $onLine): ?array
    {
        if ($relationLines !== null) {
            $exits = $this->junctions($lineId, array_values(array_diff($relationLines, [$lineId])), $onLine);

            return $exits !== null && count($exits) === count($offEnds) ? $exits : null;
        }
        $exits = [];
        foreach ($offEnds as $stop) {
            $ids = $stop['id'] !== null ? [$stop['id']] : $this->named($stop['name']);
            $lines = array_values(array_diff(array_unique(array_merge([], ...array_map(fn (int $id): array => $this->lines($id), $ids))), [$lineId]));
            $found = $lines === [] ? null : $this->junctions($lineId, $lines, $onLine);
            if ($found === null || count($found) !== 1) {
                return null;
            }
            $exits[] = $found[0];
        }
        if (count($exits) === 2 && abs($exits[0]['pk_m'] - $exits[1]['pk_m']) <= self::SAME_PLACE_M) {
            return null;
        }

        return $exits;
    }

    /**
     * Gares de la ligne (PK calé) partagées avec ces lignes, une par lieu ; null si une même ligne la touche en
     * plusieurs lieux (tronc commun, boucle : on ne sait pas où la relation en sort).
     *
     * @param  list<int>  $lines
     * @param  array<int, int>  $onLine
     * @return list<array{id: int, pk_m: int}>|null
     */
    private function junctions(int $lineId, array $lines, array $onLine): ?array
    {
        $byLine = [];
        foreach ($onLine as $id => $pk) {
            foreach (array_intersect($this->lines($id), $lines) as $other) {
                $byLine[$other][] = ['id' => $id, 'pk_m' => $pk];
            }
        }
        $points = [];
        foreach ($byLine as $shared) {
            $pks = array_column($shared, 'pk_m');
            if (max($pks) - min($pks) > self::SAME_PLACE_M) {
                return null;
            }
            $points[] = $shared[0];
        }
        usort($points, fn (array $a, array $b): int => $a['pk_m'] <=> $b['pk_m']);
        $out = [];
        foreach ($points as $p) {
            if ($out === [] || $p['pk_m'] - $out[count($out) - 1]['pk_m'] > self::SAME_PLACE_M) {
                $out[] = $p;
            }
        }

        return $out;
    }

    /**
     * Noms de la phrase réunis quand deux noms voisins forment celui d'une gare (« Clelles - Mens »,
     * « Trouville - Deauville » : le tiret entouré d'espaces fait partie du nom).
     *
     * @param  list<string>  $names
     * @return list<string>
     */
    private function merged(int $lineId, array $names): array
    {
        $out = [];
        for ($i = 0, $n = count($names); $i < $n; $i++) {
            if ($i + 1 < $n) {
                $joined = $names[$i].' - '.$names[$i + 1];
                $key = SignallingSectionLocator::key($joined);
                if (array_filter($this->places->stations($lineId), fn (array $s): bool => $s['key'] === $key) !== [] || ($this->index()[$key] ?? []) !== []) {
                    $out[] = $joined;
                    $i++;

                    continue;
                }
            }
            $out[] = $names[$i];
        }

        return $out;
    }

    /**
     * La relation nomme-t-elle au moins deux gares de la ligne ?
     *
     * @param  list<string>  $names
     */
    private function touches(int $lineId, array $names): bool
    {
        return count(array_filter($names, fn (string $n): bool => $this->places->station($lineId, $n) !== null)) >= 2;
    }

    /**
     * Gares de ce nom sur tout le réseau : nom identique, sinon gares dont le nom commence par lui
     * (« Paris » → « Paris-Saint-Lazare », « Paris-Montparnasse »…).
     *
     * @return list<int>
     */
    private function named(string $name): array
    {
        $key = SignallingSectionLocator::key($name);
        if ($key === '') {
            return [];
        }
        $index = $this->index();
        if (isset($index[$key])) {
            return $index[$key];
        }
        $found = [];
        foreach ($this->allStations ?? [] as $id => $s) {
            if (str_starts_with($s['key'], $key.' ')) {
                $found[] = $id;
            }
        }

        return $found;
    }

    /** @return array<string, list<int>> */
    private function index(): array
    {
        if ($this->allStations === null) {
            $this->allStations = [];
            foreach (DB::table('stations')->get(['id', 'name']) as $row) {
                $key = SignallingSectionLocator::key((string) $row->name);
                $this->allStations[(int) $row->id] = ['name' => (string) $row->name, 'key' => $key];
                $this->byKey[$key][] = (int) $row->id;
            }
            foreach (DB::table('station_lines')->get(['station_id', 'line_id']) as $row) {
                $this->linesOf[(int) $row->station_id][] = (int) $row->line_id;
            }
        }

        return $this->byKey;
    }

    /** @return list<int> lignes de la gare */
    private function lines(int $stationId): array
    {
        $this->index();

        return $this->linesOf[$stationId] ?? [];
    }
}
