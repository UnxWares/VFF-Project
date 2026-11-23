<?php

namespace App\Services;

use App\Enums\ServiceEnd;
use App\Support\LineDataSignature;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Gares d'une ligne avec leur état et leurs dates par ère.
 *
 * TOUTES les gares rattachées à la ligne (station_lines, toutes ères, y
 * compris fermées, disparues ou sur une portion déposée : la fiche ligne
 * garde toutes ses gares), dans l'ordre des PK. Mêmes données que la carte
 * (GeoExporter::stationsAsGeoJson) et la fiche gare (StationController) :
 *   - état par ère = station_eras (statut plafonné par les lignes, « incertaine »
 *     comme sur la carte : ouverte selon ses sources mais non confirmée) ;
 *   - date de fermeture = stations.closed_at / closed_precision /
 *     provenance.closed_after, écrite par vff:build-station-eras
 *     (StationTimeline::resolveClosure) dans l'intervalle où les ères placent
 *     la fermeture : la date ne contredit jamais la frise.
 *
 * Utilisée par la fiche ligne (liste des gares, vue « décennie par décennie »).
 *
 * Points techniques (StationTimeline::KIND_TECHNICAL : jamais ouverts au
 * public) : hors de `stations`, rendus à part dans `technical_points`. Gares
 * projetées jamais ouvertes : dans `stations` avec kind = projected, sans
 * état ni date de fermeture (hors compteurs).
 */
final class LineStations
{
    /** Statuts affichés (codes de la carte et de la fiche gare). */
    public const UNCERTAIN = 'uncertain';

    /**
     * Gares de la ligne, chacune avec son état à chaque ère.
     *
     * @return array{eras: list<array{year: int, is_current: bool}>, stations: list<array<string, mixed>>, technical_points: list<array<string, mixed>>}
     *
     * Chaque gare : kind (station | projected), id, slug, name, type, city, department_code, lng, lat,
     * pk_m, pk_label, is_terminal (gare au bout du tracé, voir terminals()), link_method
     * (sncf_pk, source_pk, segment…), served_from / served_until (années de
     * desserte par CETTE ligne, station_lines), served_until_kind (ServiceEnd :
     * year, upper_bound, undated), years_source, service_start / service_end
     * (date, précision et événement de ligne, StationServiceDates::bounds), opened_at / opened_precision / opened_after,
     * opened_source (provenance de la date d'ouverture, « inferred:… » si déduite des lignes),
     * closed_at / closed_precision / closed_after / closed_year (date retenue,
     * null si ouverte aujourd'hui), status_today (statut affiché à l'ère
     * courante, null : n'existe plus), eras : année → {status (active,
     * tourist, no_commercial, declassified, uncertain, ou null : absente),
     * sources_status (statut des sources, diffère si incertaine), note, stop
     * (arrêt de CETTE ligne à l'ère, line_stops)}.
     */
    public function forLine(int $lineId, ?string $lineName = null): array
    {
        $eras = DB::table('eras')->orderBy('year')->get(['id', 'year', 'is_current']);
        $dates = StationServiceDates::columns();
        $rows = DB::select(<<<SQL
            SELECT s.id, s.slug, s.name, s.type, s.city, s.department_code,
                   ST_X(s.geom) AS lng, ST_Y(s.geom) AS lat,
                   sl.pk_m, sl.method, sl.first_year, sl.last_year, sl.years_source, {$dates},
                   s.opened_at, s.opened_precision, s.provenance->>'opened_at' AS opened_source, s.provenance->>'opened_after' AS opened_after,
                   s.closed_at, s.closed_precision,
                   s.provenance->>'closed_after' AS closed_after, s.provenance->>'closed_at' AS closed_source,
                   s.provenance->>'kind' AS kind
            FROM station_lines sl JOIN stations s ON s.id = sl.station_id
            WHERE sl.line_id = ? AND sl.years_source IS DISTINCT FROM 'never_served'
            ORDER BY sl.pk_m NULLS LAST, s.name, s.id
        SQL, [$lineId]);
        $eraList = $eras->map(fn ($e) => ['year' => (int) $e->year, 'is_current' => (bool) $e->is_current])->all();
        // Points techniques (postes, garages, points d'une LGV : jamais ouverts au public) : à part.
        $technical = array_map(fn ($r) => ['id' => (int) $r->id, 'slug' => $r->slug, 'name' => $r->name, 'pk_m' => $r->pk_m !== null ? (int) $r->pk_m : null,
            'pk_label' => self::pkLabel($r->pk_m !== null ? (int) $r->pk_m : null)], array_values(array_filter($rows, fn ($r) => $r->kind === StationTimeline::KIND_TECHNICAL)));
        // Fiche hors du réseau ferré (correction sourcée : tramway, fiche erronée) : pas une gare de la ligne.
        $rows = array_values(array_filter($rows, fn ($r) => ! in_array($r->kind, [StationTimeline::KIND_TECHNICAL, StationTimeline::KIND_EXCLUDED], true)));
        if ($rows === []) {
            return ['eras' => $eraList, 'stations' => [], 'technical_points' => $technical];
        }
        $ids = array_map(fn ($r) => (int) $r->id, $rows);
        $states = [];
        foreach (DB::table('station_eras as se')->join('eras as e', 'e.id', '=', 'se.era_id')->whereIn('se.station_id', $ids)
            ->get(['se.station_id', 'e.year', 'se.status', 'se.uncertain', 'se.note']) as $r) {
            $states[(int) $r->station_id][(int) $r->year] = $r;
        }
        $stops = [];
        foreach (DB::table('line_stops as ls')->join('eras as e', 'e.id', '=', 'ls.era_id')->where('ls.line_id', $lineId)
            ->get(['ls.station_id', 'e.year']) as $r) {
            $stops[(int) $r->station_id][(int) $r->year] = true;
        }
        // Nom de la ligne pour les terminus : celui de l'appelant quand il a déjà la ligne (LineSheet), sinon lu ici.
        $lineName ??= (string) DB::table('lines')->where('id', $lineId)->value('name');
        $terminals = $this->terminals($lineId, $lineName, array_values(array_filter($rows, fn ($r) => $r->kind === null)));
        $current = $eras->firstWhere('is_current', true);

        $stations = [];
        foreach ($rows as $r) {
            $sid = (int) $r->id;
            $byEra = [];
            foreach ($eras as $e) {
                $st = $states[$sid][(int) $e->year] ?? null;
                $byEra[(int) $e->year] = [
                    'status' => self::displayStatus($st?->status, (bool) ($st->uncertain ?? false)),
                    'sources_status' => $st?->status,
                    'note' => $st?->note,
                    'stop' => isset($stops[$sid][(int) $e->year]),
                ];
            }
            $stations[] = [
                'id' => $sid,
                'slug' => $r->slug,
                'name' => $r->name,
                'type' => $r->type,
                'city' => $r->city,
                'department_code' => $r->department_code,
                'lng' => (float) $r->lng,
                'lat' => (float) $r->lat,
                'pk_m' => $r->pk_m !== null ? (int) $r->pk_m : null,
                'pk_label' => self::pkLabel($r->pk_m !== null ? (int) $r->pk_m : null),
                // station | projected (gare projetée jamais ouverte : jamais « fermée », hors compteurs).
                'kind' => $r->kind ?? StationTimeline::KIND_STATION,
                'is_terminal' => in_array($sid, $terminals, true),
                'link_method' => $r->method,
                'served_from' => $r->first_year !== null ? (int) $r->first_year : null,
                'served_until' => $r->last_year !== null ? (int) $r->last_year : null,
                // year, upper_bound (au plus tard cette année) ou undated (fin non datée) : App\Enums\ServiceEnd.
                'served_until_kind' => ServiceEnd::of($r->last_year !== null ? (int) $r->last_year : null,
                    $r->closed_at !== null ? (string) $r->closed_at : null, $r->closed_precision, (int) now()->year)?->value,
                // line_events, traces ou station (années tirées des dates de la gare), null si inconnues.
                'years_source' => $r->years_source,
                // Date, précision et événement de ligne qui ouvrent et terminent la desserte (StationServiceDates).
                'service_start' => ($bounds = StationServiceDates::bounds($r))['start'],
                'service_end' => $bounds['end'],
                'opened_at' => $r->opened_at,
                'opened_precision' => $r->opened_precision,
                // Ouverture encadrée (précision range) : ouverte après cette année, au plus tard l'année de opened_at.
                'opened_after' => $r->opened_precision === 'range' && is_numeric($r->opened_after) ? (int) $r->opened_after : null,
                // Provenance de la date d'ouverture (wikidata, inferred:line_event…) : une date déduite de
                // la ligne (« inferred:… ») est signalée comme telle dans la liste des gares.
                'opened_source' => $r->opened_source,
                'closed_at' => $r->closed_at,
                'closed_precision' => $r->closed_precision,
                'closed_after' => is_numeric($r->closed_after) ? (int) $r->closed_after : null,
                'closed_year' => $r->closed_at !== null ? (int) substr((string) $r->closed_at, 0, 4) : null,
                'closed_source' => $r->closed_source,
                'status_today' => $current ? $byEra[(int) $current->year]['status'] : null,
                'eras' => $byEra,
            ];
        }

        return ['eras' => $eraList, 'stations' => $stations, 'technical_points' => $technical];
    }

    /** Distance maximale (m) entre une extrémité du tracé et la gare qui en est le terminus. */
    public const TERMINUS_MAX_M = 3000;

    /** Durée de vie des extrémités gardées par terminals() (s) : la signature des tronçons les invalide avant. */
    public const ENDS_CACHE_TTL = 86_400;

    /**
     * Terminus = gare la plus proche de chacune des deux extrémités de la ligne (les deux bouts de
     * tracé les plus éloignés l'un de l'autre, toutes ères confondues), à TERMINUS_MAX_M au plus.
     * Pas « la première et la dernière gare à PK » : sur une LGV, qui finit en pleine voie à une
     * bifurcation, Le Creusot et Aix-en-Provence-TGV n'en sont pas les terminus (contrôle qualité du
     * 2026-10-20) ; un PK faux mettait une gare en tête de liste et la marquait terminus.
     *
     * Deux gares au même bout (Paris-Austerlitz et Paris-Austerlitz-Souterrain) : celle que le nom de
     * la ligne désigne (« Ligne de Paris-Austerlitz à Bordeaux-St-Jean »), sinon la plus proche.
     *
     * @param  list<object>  $rows  gares de la ligne (id, name, lng, lat)
     * @return list<int>
     */
    private function terminals(int $lineId, string $lineName, array $rows): array
    {
        if ($rows === []) {
            return [];
        }
        // Les deux bouts ne dépendent que des tronçons de la ligne : gardés en cache tant qu'ils ne changent
        // pas (LineDataSignature), le calcul prenait 170 ms sur la LGV Sud-Est. Liste vide : aucun tracé.
        $ends = Cache::remember(
            "line-stations:ends:{$lineId}:".LineDataSignature::segments($lineId),
            self::ENDS_CACHE_TTL,
            function () use ($lineId): array {
                $row = DB::selectOne(<<<'SQL'
                    WITH parts AS (SELECT (ST_Dump(ST_LineMerge(ST_Collect(geom)))).geom AS g
                                   FROM (SELECT DISTINCT geom FROM line_segments WHERE line_id = ?) x),
                         ends AS (SELECT ST_StartPoint(g) AS p FROM parts UNION ALL SELECT ST_EndPoint(g) FROM parts)
                    SELECT ST_X(a.p) AS x1, ST_Y(a.p) AS y1, ST_X(b.p) AS x2, ST_Y(b.p) AS y2
                    FROM ends a CROSS JOIN ends b
                    ORDER BY ST_DistanceSphere(a.p, b.p) DESC LIMIT 1
                SQL, [$lineId]);

                return $row === null || $row->x1 === null ? []
                    : [[(float) $row->x1, (float) $row->y1], [(float) $row->x2, (float) $row->y2]];
            },
        );
        if ($ends === []) {
            return [];
        }
        $out = [];
        foreach ($ends as [$x, $y]) {
            $best = null;
            $bestKey = null;
            foreach ($rows as $r) {
                $d = self::metres((float) $r->lng, (float) $r->lat, $x, $y);
                if ($d > self::TERMINUS_MAX_M) {
                    continue;
                }
                $key = [self::namedIn((string) $r->name, $lineName) ? 0 : 1, $d];
                if ($bestKey === null || $key < $bestKey) {
                    [$best, $bestKey] = [(int) $r->id, $key];
                }
            }
            if ($best !== null && ! in_array($best, $out, true)) {
                $out[] = $best;
            }
        }

        return $out;
    }

    /** Le nom de la gare figure (mot entier, sans accents ni casse) dans le nom de la ligne. */
    public static function namedIn(string $station, string $line): bool
    {
        $norm = fn (string $t) => ' '.trim((string) preg_replace('/[^a-z0-9]+/', ' ', strtolower(Str::ascii($t)))).' ';
        $st = trim($norm((string) preg_replace('/\s*\(.*\)$/u', '', $station)));

        return $st !== '' && str_contains($norm($line), ' '.$st.' ');
    }

    /** Distance (m) à vol d'oiseau entre deux points WGS84 (haversine). */
    public static function metres(float $lng1, float $lat1, float $lng2, float $lat2): float
    {
        $r = 6371008.8;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $r * asin(min(1.0, sqrt($a)));
    }

    /**
     * PK au format « kilomètres+mètres » : 7735 → « 7+735 » ; avant l'origine de
     * la ligne (gare de bifurcation projetée en amont) : -250 → « -0+250 ».
     */
    public static function pkLabel(?int $meters): ?string
    {
        if ($meters === null) {
            return null;
        }
        $abs = abs($meters);

        return ($meters < 0 ? '-' : '').sprintf('%d+%03d', intdiv($abs, 1000), $abs % 1000);
    }

    /**
     * Les gares de la ligne à une ère : celles qui existent à cette ère
     * (ouvertes, incertaines ou fermées), avec leur statut affiché.
     *
     * @return list<array<string, mixed>>
     */
    public function atEra(int $lineId, int $year): array
    {
        $out = [];
        foreach ($this->forLine($lineId)['stations'] as $s) {
            $era = $s['eras'][$year] ?? null;
            if ($era === null || $era['status'] === null) {
                continue;
            }
            unset($s['eras']);
            $out[] = $s + ['era_status' => $era['status'], 'era_note' => $era['note'], 'stop' => $era['stop']];
        }

        return $out;
    }

    /**
     * Résumé par ère pour la vue « décennie par décennie » : gares ouvertes
     * (voyageurs, touristiques, fret seul), incertaines, fermées, et gares
     * qui passent à « fermée » à cette ère (fermées à cette ère, pas à la
     * précédente : les mêmes statuts que les compteurs, donc jamais plus de
     * noms que de fermetures comptées), avec la date de la fiche gare.
     *
     * Une gare qui passe d'ouverte (ou non confirmée) à fermée sans date retenue pour ce passage
     * (fermeture non datée, ou fermeture provisoire suivie d'une réouverture : nogent-sur-seine en
     * 1940) est aussi nommée, avec l'intervalle que donne la frise (`closed_precision` range :
     * « entre 1931 et 1940 ») et `reopened` si elle rouvre à une ère suivante. Avant, elle comptait
     * parmi les gares fermées sans figurer dans la liste.
     *
     * @return list<array{year: int, is_current: bool, open: int, uncertain: int, closed: int, closures: list<array<string, mixed>>}>
     */
    public function byEra(int $lineId): array
    {
        return self::byEraOf($this->forLine($lineId));
    }

    /**
     * Même résumé que byEra() à partir du résultat de forLine() déjà calculé : la fiche ligne
     * (LineSheet) s'en sert pour ne pas relancer les requêtes des gares et des terminus.
     *
     * @param  array{eras: list<array{year: int, is_current: bool}>, stations: list<array<string, mixed>>}  $data  résultat de forLine()
     * @return list<array{year: int, is_current: bool, open: int, uncertain: int, closed: int, closures: list<array<string, mixed>>}>
     */
    public static function byEraOf(array $data): array
    {
        $years = array_column($data['eras'], 'year');
        $closuresAt = [];
        foreach ($data['stations'] as $s) {
            foreach (self::closureEntries($s, $years) as $y => $entry) {
                $closuresAt[$y][] = $entry;
            }
        }
        $out = [];
        foreach ($data['eras'] as $era) {
            $y = $era['year'];
            $counts = ['open' => 0, 'uncertain' => 0, 'closed' => 0];
            foreach ($data['stations'] as $s) {
                $status = $s['eras'][$y]['status'] ?? null;
                if (in_array($status, StationTimeline::OPEN_STATUSES, true)) {
                    $counts['open']++;
                } elseif ($status === self::UNCERTAIN) {
                    $counts['uncertain']++;
                } elseif ($status === StationTimeline::CLOSED) {
                    $counts['closed']++;
                }
            }
            $out[] = ['year' => $y, 'is_current' => $era['is_current']] + $counts + ['closures' => $closuresAt[$y] ?? []];
        }

        return $out;
    }

    /**
     * Fermetures d'une gare à nommer dans la vue par décennie, par année d'ère (calcul pur, cf. byEra) :
     *   - la fermeture retenue (gare fermée aujourd'hui, `closed_at`) à la dernière ère où son fil
     *     passe à « fermée », c'est-à-dire la même ère que les compteurs : une date retenue antérieure
     *     (13 mars 2018) dont la frise ne montre la fermeture qu'à l'ère suivante ne crée pas une gare
     *     « fermée » de plus que le compteur ; si le fil finit par une disparition (ouverte ou non
     *     confirmée, puis absente) après ce passage, à l'ère de la disparition (gare comptée absente,
     *     non fermée, à cette ère) ; sans passage à « fermée » ni disparition dans le fil (données
     *     contradictoires), à la décennie de la date retenue ;
     *   - chaque autre passage d'ouverte ou non confirmée à fermée : intervalle de la frise
     *     (`closed_at` = 31/12 de l'ère, `closed_after` = année de l'ère précédente, précision
     *     range), `reopened` si la gare est de nouveau ouverte ou non confirmée à une ère suivante.
     *
     * @param  array<string, mixed>  $station  gare de forLine (slug, name, pk_label, closed_*, eras)
     * @param  list<int>  $years  années des ères, croissantes
     * @return array<int, array<string, mixed>>
     */
    public static function closureEntries(array $station, array $years): array
    {
        $status = fn (int $y): ?string => $station['eras'][$y]['status'] ?? null;
        $wasOpen = fn (?string $st): bool => in_array($st, [...StationTimeline::OPEN_STATUSES, self::UNCERTAIN], true);
        $transitions = [];
        $before = null;
        $previousYear = null;
        foreach ($years as $y) {
            if ($status($y) === StationTimeline::CLOSED && $before !== StationTimeline::CLOSED) {
                $transitions[$y] = ['from' => $before, 'previous_year' => $previousYear];
            }
            $before = $status($y);
            $previousYear = $y;
        }

        // Gare disparue : dernier passage d'ouverte (ou non confirmée) à absente.
        $vanishedAt = null;
        $before = null;
        foreach ($years as $y) {
            if ($status($y) === null && $wasOpen($before)) {
                $vanishedAt = $y;
            }
            $before = $status($y);
        }

        $retainedAt = null;
        if ($station['closed_year'] !== null) {
            $retainedAt = array_key_last($transitions);
            // Le fil finit par une disparition après le dernier passage à « fermée » (Vigny : fermée de
            // 1980 à 2000, ouverte en 2010 et 2020, absente en 2030, fermée le 17/10/2023) : la date
            // retenue est celle de ce passage, pas de l'ancienne fermeture.
            if ($vanishedAt !== null && ($retainedAt === null || $vanishedAt > $retainedAt)) {
                $retainedAt = $vanishedAt;
            }
            if ($retainedAt === null) {
                foreach ($years as $y) {
                    if ($station['closed_year'] <= $y) {
                        $retainedAt = $y;
                        break;
                    }
                }
            }
        }

        $entry = fn (?string $date, ?string $precision, ?int $after, bool $reopened): array => ['slug' => $station['slug'], 'name' => $station['name'],
            'pk_label' => $station['pk_label'], 'closed_at' => $date, 'closed_precision' => $precision, 'closed_after' => $after,
            'label' => StationTimeline::closureLabel($date, $precision, $after), 'reopened' => $reopened];
        $out = [];
        foreach ($transitions as $y => $t) {
            if ($y === $retainedAt || ! $wasOpen($t['from'])) {
                continue;
            }
            $reopened = (bool) array_filter($years, fn (int $later): bool => $later > $y && $wasOpen($status($later)));
            $out[$y] = $entry(sprintf('%04d-12-31', $y), StationTimeline::RANGE, $t['previous_year'], $reopened);
        }
        if ($retainedAt !== null) {
            $out[$retainedAt] = $entry($station['closed_at'], $station['closed_precision'], $station['closed_after'] ?? null, false);
        }
        ksort($out);

        return $out;
    }

    /** Statut affiché, comme la carte et la fiche gare : « incertaine » si ouverte mais non confirmée. */
    public static function displayStatus(?string $status, bool $uncertain): ?string
    {
        if ($status === null) {
            return null;
        }

        return $uncertain && $status !== StationTimeline::CLOSED ? self::UNCERTAIN : $status;
    }
}
