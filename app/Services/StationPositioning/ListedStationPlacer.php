<?php

namespace App\Services\StationPositioning;

use App\Support\StationNames;
use Illuminate\Support\Facades\DB;

/**
 * Replace sur leur ligne les gares des listes historiques (schémas Wikipédia,
 * lignes-oubliees) posées par un calage propre à la liste (vff:import-station-sources
 * --source=pk, SectionResolver::placeStations) : calage robuste « PK = distance »
 * (PkConsensus) sur les gares de la liste dont la position est fiable et sur les
 * lieux-dits homonymes proches de la voie (BD TOPO, IgnPlaceSearch).
 *
 * Gares concernées : celles dont la position retenue vient de la liste
 * (stations.position_source = wikipedia_schema | lignes-oubliees), placées par leur PK ou
 * par leur commune, hors calage officiel de la ligne (points SNCF : gares et passages à
 * niveau, plus sûrs). Une gare reste où elle est quand :
 *   - le calage est ambigu ou trop faible (moins de deux gares, ou d'une gare et d'un
 *     lieu-dit, concordants) ;
 *   - son PK est à plus de PkFit::MAX_EXTRAPOLATION_M du point d'appui le plus proche ;
 *   - le lieu-dit de son nom, près de la voie, contredit le calage, ou (hors de deux gares
 *     d'appui qui l'encadrent) elle s'en éloignerait de plus de OWN_PLACE_SLACK_M ;
 *   - le nouveau point n'est pas plus précis que l'ancien et l'ancien concorde avec le calage.
 *
 * PK d'une autre ligne : une gare d'appui dont le PK de la liste est celui que la SNCF lui
 * donne sur une AUTRE ligne (et pas celui de cette ligne) est écartée, sauf si toutes les
 * gares d'appui du groupe sont dans ce cas (la liste suit alors le repère de cette ligne).
 *
 * Commune : une gare qui porte le nom d'une commune proche (actuelle, ou déléguée et
 * associée : contour de sa commune de rattachement) n'en sort pas : sa nouvelle position
 * doit être à moins de COMMUNE_MARGIN_M du contour, sinon elle reste où elle est (schéma
 * aux PK arrondis au kilomètre, axe recousu de travers : Agnicourt, ligne de Marle à
 * Montcornet, aurait été posée à 4,1 km du village).
 *
 * Une seule gare d'appui ne suffit jamais : le sens de parcours et l'origine des PK doivent
 * être confirmés par une autre gare ou un lieu-dit (une première version acceptait une gare
 * au bout de l'axe seule : Cilly et Agnicourt, PK arrondis, partaient à 3 et 4 km).
 */
final class ListedStationPlacer
{
    /** Sources des listes par ligne (vff:import-station-sources --source=pk). */
    public const LISTED = ['wikipedia_schema', 'lignes-oubliees'];

    /** Positions fiables : une gare de la liste ainsi placée sert de point d'appui. */
    public const RELIABLE = ['sncf', 'sncf_voyageurs', 'osm', 'wikidata', 'ohm', 'correction'];

    /** PK de la liste égal au PK SNCF d'une autre ligne : à ce nombre de mètres près. */
    public const FOREIGN_PK_M = 5;

    /** Commune homonyme cherchée à moins de cette distance (m) de l'ancienne position. */
    public const COMMUNE_SEARCH_M = 15000;

    /** Nouvelle position à plus de cette distance (m) du contour de la commune homonyme : refusée. */
    public const COMMUNE_MARGIN_M = 500;

    /** Déplacement en deçà duquel rien n'est écrit (m). */
    public const MIN_MOVE_M = 20;

    /** Lieux-dits homonymes à plus de cette distance (m) l'un de l'autre le long de l'axe : ambigu. */
    public const PLACE_AMBIGUOUS_M = 1000;

    /** Hors encadrement par deux gares d'appui : éloignement toléré (m) de la halte à son propre lieu-dit. */
    public const OWN_PLACE_SLACK_M = 100;

    public function __construct(private readonly IgnPlaceSearch $places) {}

    /**
     * Lignes ayant des gares de liste à replacer.
     *
     * @return list<int>
     */
    public function candidateLines(): array
    {
        return array_map('intval', DB::table('station_sources as ss')
            ->join('stations as s', 's.id', '=', 'ss.station_id')
            ->whereIn('ss.source', self::LISTED)->whereColumn('s.position_source', 'ss.source')
            ->whereNotNull(DB::raw("ss.properties->>'line_id'"))
            ->distinct()->orderBy('line_id')->pluck(DB::raw("(ss.properties->>'line_id')::bigint AS line_id"))->all());
    }

    /**
     * Axe de la ligne (tracé de référence recousu, Lambert-93), ou null.
     */
    public function axis(int $lineId): ?Polyline
    {
        $rows = DB::select(
            'SELECT ST_AsGeoJSON(d.geom, 2) AS g FROM line_reference_geometries r
             CROSS JOIN LATERAL ST_Dump(ST_LineMerge(ST_Transform(ST_Force2D(r.geom), 2154))) d
             WHERE r.line_id = ?',
            [$lineId],
        );
        $parts = [];
        foreach ($rows as $r) {
            $coords = json_decode($r->g, true)['coordinates'] ?? [];
            $parts[] = array_map(fn ($c) => [(float) $c[0], (float) $c[1]], $coords);
        }

        return Polyline::chain($parts);
    }

    /**
     * Nouvelles positions des gares de liste d'une ligne (rien n'est écrit).
     *
     * @return list<ListedPlacement>
     */
    public function placeLine(int $lineId, bool $withPlaces = true): array
    {
        $axis = $this->axis($lineId);
        if ($axis === null || $axis->length() < 100) {
            return [];
        }
        $code = DB::table('lines')->where('id', $lineId)->value('code');
        $members = $this->members($lineId);
        $foreign = $this->foreignPks($members, $code);

        $groups = [];
        foreach ($members as $m) {
            $groups[$m->grp][] = $m;
        }
        $out = [];
        foreach ($groups as $rows) {
            array_push($out, ...$this->placeGroup($lineId, $axis, $rows, $foreign, $withPlaces));
        }

        return $out;
    }

    /**
     * @param  list<object>  $rows
     * @param  array<int, true>  $foreign  station_sources.id dont le PK est celui d'une autre ligne
     * @return list<ListedPlacement>
     */
    private function placeGroup(int $lineId, Polyline $axis, array $rows, array $foreign, bool $withPlaces): array
    {
        $movable = array_values(array_filter($rows, fn ($m) => $this->movable($m)));
        if ($movable === []) {
            return [];
        }
        [$stations, $foreignAnchors, $placeOf] = $this->anchors($axis, $rows, $foreign, $withPlaces);
        $anchors = array_merge($stations, array_values($placeOf));
        $fit = PkConsensus::fit($anchors);
        $calibration = $fit ? $this->describe($fit, $foreignAnchors) : 'calage ambigu ou insuffisant';

        $out = [];
        foreach ($movable as $m) {
            $keep = fn (string $why) => new ListedPlacement((int) $m->id, (int) $m->station_id, $lineId, $m->name, (int) $m->pk,
                (float) $m->src_lon, (float) $m->src_lat, $m->precision_m !== null ? (int) $m->precision_m : null,
                null, null, null, $why, $calibration, 0);
            if ($fit === null) {
                $out[] = $keep('inchangée : '.$calibration);

                continue;
            }
            $placed = $fit->place((int) $m->pk);
            if ($placed === null || $placed->measure < -200 || $placed->measure > $axis->length() + 200) {
                $out[] = $keep('inchangée : PK hors du calage');

                continue;
            }
            $own = $placeOf[$m->id] ?? null;
            if ($own !== null && ! $fit->agrees($own)) {
                $out[] = $keep(sprintf('inchangée : le lieu-dit %s contredit le calage', $own->label));

                continue;
            }
            [$curMeasure, $curD] = $axis->project((float) $m->x, (float) $m->y);
            // Hors de deux gares d'appui qui l'encadrent, le PK ne vaut pas mieux que le lieu-dit de la
            // halte : jamais plus loin de son lieu-dit qu'avant (Poms, Ganland, Landonvillers).
            if ($own !== null && ! $placed->bracketed
                && abs($placed->measure - $own->measure) > abs($curMeasure - $own->measure) + self::OWN_PLACE_SLACK_M) {
                $out[] = $keep(sprintf('inchangée : s\'éloignerait de son lieu-dit %s', $own->label));

                continue;
            }
            $consistent = $curD <= PkConsensus::ANCHOR_MAX_M
                && abs($curMeasure - $placed->measure) <= AnchorKind::Station->toleranceM() + PkConsensus::TOLERANCE_SLOPE * $placed->gapM;
            if ($consistent && $m->precision_m !== null && (int) $m->precision_m <= $placed->precisionM) {
                $out[] = $keep('inchangée : position actuelle concordante et aussi précise');

                continue;
            }
            [$x, $y] = $axis->pointAt($placed->measure);
            if (($commune = $this->outsideOwnCommune($m, $x, $y)) !== null) {
                $out[] = $keep(sprintf('inchangée : hors de sa commune (%s)', $commune));

                continue;
            }
            $moved = (int) round(hypot($x - (float) $m->x, $y - (float) $m->y));
            if ($moved < self::MIN_MOVE_M) {
                $out[] = $keep('inchangée : déplacement négligeable');

                continue;
            }
            $out[] = new ListedPlacement((int) $m->id, (int) $m->station_id, $lineId, $m->name, (int) $m->pk,
                (float) $m->src_lon, (float) $m->src_lat, $m->precision_m !== null ? (int) $m->precision_m : null,
                $x, $y, $placed->precisionM, sprintf('PK %s : %s', $this->km((int) $m->pk), $placed->how), $calibration, $moved);
        }

        return $out;
    }

    /**
     * Points d'appui d'un groupe : gares de position fiable (hors PK d'une autre ligne, sauf
     * si toutes sont dans ce cas), gares au PK d'une autre ligne, lieux-dits des autres gares.
     *
     * @param  list<object>  $rows
     * @param  array<int, true>  $foreign
     * @return array{0: list<PkAnchor>, 1: list<PkAnchor>, 2: array<int, PkAnchor>}
     */
    private function anchors(Polyline $axis, array $rows, array $foreign, bool $withPlaces): array
    {
        $stations = [];
        $foreignAnchors = [];
        foreach ($rows as $m) {
            if ($m->pk === null || ! in_array($m->position_source, self::RELIABLE, true)) {
                continue;
            }
            [$measure, $d] = $axis->project((float) $m->sx, (float) $m->sy);
            if ($d > PkConsensus::ANCHOR_MAX_M) {
                continue;
            }
            $a = new PkAnchor((int) $m->pk, $measure, AnchorKind::Station, sprintf('« %s »', $m->name));
            isset($foreign[$m->id]) ? $foreignAnchors[] = $a : $stations[] = $a;
        }
        if ($stations === []) {
            $stations = $foreignAnchors; // toute la liste dans le repère de l'autre ligne
            $foreignAnchors = [];
        }
        $placeOf = [];
        if ($withPlaces) {
            foreach ($rows as $m) {
                if ($m->pk === null || in_array($m->position_source, self::RELIABLE, true)) {
                    continue;
                }
                if ($p = $this->placeAnchor($axis, $m)) {
                    $placeOf[(int) $m->id] = $p;
                }
            }
        }

        return [$stations, $foreignAnchors, $placeOf];
    }

    /**
     * Validation croisée : chaque gare d'appui d'un groupe (position fiable) est replacée par
     * son PK, sans elle, par le calage robuste et par une interpolation naïve sur toutes les
     * autres gares d'appui (PK d'une autre ligne compris, sans consensus : la méthode d'avant,
     * à ses filtres de pente près). Écart le long de la voie (m), null si non placée.
     *
     * @return list<array{line_id: int, name: string, pk: int, robust: ?int, naive: ?int}>
     */
    public function crossValidate(int $lineId, bool $withPlaces = true): array
    {
        $axis = $this->axis($lineId);
        if ($axis === null || $axis->length() < 100) {
            return [];
        }
        $code = DB::table('lines')->where('id', $lineId)->value('code');
        $members = $this->members($lineId);
        $foreign = $this->foreignPks($members, $code);
        $groups = [];
        foreach ($members as $m) {
            $groups[$m->grp][] = $m;
        }
        $out = [];
        foreach ($groups as $rows) {
            [$stations, $foreignAnchors, $placeOf] = $this->anchors($axis, $rows, $foreign, $withPlaces);
            $all = array_merge($stations, $foreignAnchors);
            if (count($all) < 2) {
                continue;
            }
            foreach ($stations as $i => $target) {
                $others = array_values(array_diff_key($stations, [$i => true]));
                $fit = PkConsensus::fit(array_merge($others, array_values($placeOf)));
                $robust = $fit?->place($target->pk);
                $rest = array_values(array_filter($all, fn (PkAnchor $a) => $a !== $target));
                usort($rest, fn (PkAnchor $a, PkAnchor $b) => $a->pk <=> $b->pk);
                $naive = count($rest) >= 2 ? (new PkFit($rest[count($rest) - 1]->measure >= $rest[0]->measure ? 1 : -1, $rest, [], 0))->place($target->pk) : null;
                $out[] = ['line_id' => $lineId, 'name' => trim($target->label, '« »'), 'pk' => $target->pk,
                    'robust' => $robust ? (int) round(abs($robust->measure - $target->measure)) : null,
                    'naive' => $naive ? (int) round(abs($naive->measure - $target->measure)) : null];
            }
        }

        return $out;
    }

    /** @return list<object> */
    private function members(int $lineId): array
    {
        return DB::select(<<<'SQL'
            SELECT ss.id, ss.source, ss.name, ss.station_id, (ss.properties->>'pk_source_m')::int AS pk,
                   COALESCE(ss.properties->>'group', '') AS grp, ss.properties->>'geocoding' AS geocoding,
                   (ss.properties->>'precision_m')::int AS precision_m, COALESCE(ss.properties->>'placement', '') AS placement,
                   s.position_source, ST_X(s.geom) AS lon, ST_Y(s.geom) AS lat,
                   ST_X(ST_Transform(s.geom, 2154)) AS sx, ST_Y(ST_Transform(s.geom, 2154)) AS sy,
                   ST_X(ss.geom) AS src_lon, ST_Y(ss.geom) AS src_lat,
                   ST_X(ST_Transform(ss.geom, 2154)) AS x, ST_Y(ST_Transform(ss.geom, 2154)) AS y
            FROM station_sources ss JOIN stations s ON s.id = ss.station_id
            WHERE ss.source IN ('wikipedia_schema', 'lignes-oubliees') AND (ss.properties->>'line_id')::bigint = ?
            ORDER BY ss.id
        SQL, [$lineId]);
    }

    /** Gare de liste dont la position retenue vient de la liste, placée par son PK ou sa commune, hors calage officiel. */
    private function movable(object $m): bool
    {
        return $m->pk !== null && $m->position_source === $m->source
            && in_array($m->geocoding, ['pk', 'commune'], true)
            && ! str_contains((string) $m->placement, 'calage officiel');
    }

    /**
     * Lieu-dit habité du nom de la gare, à moins de PkConsensus::ANCHOR_MAX_M de l'axe et
     * sans homonyme ailleurs le long de l'axe.
     */
    private function placeAnchor(Polyline $axis, object $m): ?PkAnchor
    {
        $found = [];
        foreach ($this->places->places((string) $m->name, (float) $m->lon, (float) $m->lat) as $p) {
            $xy = DB::selectOne('SELECT ST_X(g) AS x, ST_Y(g) AS y FROM ST_Transform(ST_SetSRID(ST_MakePoint(?, ?), 4326), 2154) g', [$p['lon'], $p['lat']]);
            [$measure, $d] = $axis->project((float) $xy->x, (float) $xy->y);
            if ($d <= PkConsensus::ANCHOR_MAX_M) {
                $found[] = [$measure, $p['name'], $d];
            }
        }
        if ($found === []) {
            return null;
        }
        $ms = array_column($found, 0);
        if (max($ms) - min($ms) > self::PLACE_AMBIGUOUS_M) {
            return null;
        }
        usort($found, fn ($a, $b) => $a[2] <=> $b[2]);

        return new PkAnchor((int) $m->pk, $found[0][0], AnchorKind::Place, sprintf('lieu-dit « %s » (%d m de la voie)', $found[0][1], $found[0][2]));
    }

    /**
     * Nom de la commune homonyme (la plus proche de l'ancienne position, à moins de
     * COMMUNE_SEARCH_M) dont la nouvelle position (Lambert-93) sort de plus de
     * COMMUNE_MARGIN_M, ou null.
     */
    private function outsideOwnCommune(object $m, float $x, float $y): ?string
    {
        $q = StationNames::normalize((string) $m->name);
        if ($q === null || mb_strlen($q) < 3) {
            return null;
        }
        $r = DB::selectOne(<<<'SQL'
            SELECT cm.nom, ST_DWithin(ct.geom::geography, ST_Transform(ST_SetSRID(ST_MakePoint(?, ?), 2154), 4326)::geography, ?) AS inside
            FROM communes cm
            JOIN commune_contours ct ON ct.code_insee = CASE WHEN cm.type <> 'commune-actuelle' AND cm.chef_lieu IS NOT NULL THEN cm.chef_lieu ELSE cm.code_insee END
            WHERE (cm.nom_norm = ? OR cm.nom_norm LIKE ?)
              AND cm.geom && ST_Expand(ST_SetSRID(ST_MakePoint(?, ?), 4326), 0.25)
              AND ST_DWithin(cm.geom::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)
            ORDER BY cm.geom <-> ST_SetSRID(ST_MakePoint(?, ?), 4326)
            LIMIT 1
        SQL, [$x, $y, self::COMMUNE_MARGIN_M, $q, $q.' %', $m->src_lon, $m->src_lat, $m->src_lon, $m->src_lat, self::COMMUNE_SEARCH_M, $m->src_lon, $m->src_lat]);

        return $r && ! $r->inside ? (string) $r->nom : null;
    }

    /**
     * Gares de liste dont le PK est celui que la SNCF leur donne sur une autre ligne.
     *
     * @param  list<object>  $members
     * @return array<int, true>
     */
    private function foreignPks(array $members, ?string $code): array
    {
        $ids = array_values(array_unique(array_map(fn ($m) => (int) $m->station_id, array_filter($members, fn ($m) => $m->pk !== null))));
        if ($ids === []) {
            return [];
        }
        $pks = [];
        foreach (DB::select(
            "SELECT ss.station_id, x->>'code_ligne' AS code, (x->>'pk_m')::int AS pk FROM station_sources ss
             CROSS JOIN LATERAL jsonb_array_elements(COALESCE(ss.properties->'lines', '[]'::jsonb)) x
             WHERE ss.source = 'sncf' AND ss.station_id = ANY(?::bigint[]) AND x->>'pk_m' IS NOT NULL",
            ['{'.implode(',', $ids).'}'],
        ) as $r) {
            $pks[(int) $r->station_id][] = [(string) $r->code, (int) $r->pk];
        }
        $out = [];
        foreach ($members as $m) {
            if ($m->pk === null || (int) $m->pk === 0) {
                continue;
            }
            $own = false;
            $other = false;
            foreach ($pks[(int) $m->station_id] ?? [] as [$c, $pk]) {
                if (abs($pk - (int) $m->pk) <= self::FOREIGN_PK_M) {
                    $c === $code ? $own = true : $other = true;
                }
            }
            if ($other && ! $own) {
                $out[(int) $m->id] = true;
            }
        }

        return $out;
    }

    /** @param  list<PkAnchor>  $foreign */
    private function describe(PkFit $fit, array $foreign): string
    {
        $st = count($fit->stations());
        $pl = count($fit->inliers) - $st;
        $rejected = array_merge(array_map(fn ($a) => $a->label, $fit->outliers), array_map(fn ($a) => $a->label.' (PK d\'une autre ligne)', $foreign));

        return sprintf('consensus PK = distance sur %d gare(s) et %d lieu(x)-dit(s)%s', $st, $pl,
            $rejected ? ' ; écartés : '.implode(', ', array_slice($rejected, 0, 4)).(count($rejected) > 4 ? '…' : '') : '');
    }

    private function km(int $m): string
    {
        return number_format($m / 1000, 3, ',', ' ');
    }
}
