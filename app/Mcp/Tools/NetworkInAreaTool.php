<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\Lookup;
use App\Mcp\Support\MapArea;
use App\Mcp\Support\VffTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('network_in_area')]
#[Title('État du réseau dans une zone à une époque')]
#[Description(<<<'TXT'
État du réseau ferré dans une zone géographique à une époque de la carte VFF : kilomètres en service, fermés et déposés DANS la zone, lignes qui la traversent et gares qui s'y trouvent, avec leur état à cette époque. Répond à « quel était le réseau autour de Limoges en 1930 ? » ; pour la France entière, utilisez network_at_era.
Zone : soit un rectangle `south`, `west`, `north`, `east` (degrés WGS 84 ; 0,01° à 1° de haut, 0,01° à 1,5° de large), soit un cercle `lat`, `lng`, `radius_km` (1 à 50 km) ; pas les deux. Zone trop grande : erreur `area_too_large` ; trop petite : `area_too_small` ; zone incomplète ou les deux à la fois : `invalid_area`.
Une année quelconque est ramenée à l'ère qui la contient ou la précède (1937 donne 1930) ; sans `year`, le réseau d'aujourd'hui. `era` : ère retenue (`snapshot` : 31/12 de l'année, ou aujourd'hui).
`totals` (km mesurés sur la partie des tronçons comprise dans la zone, arrondis à 0,1 km) : `open_km` (en service), `closed_km` (fermée voie en place, voie de service, en construction), `gone_km` (déposée ou effacée), `electrified_km` (en service et électrifiée), `open_km_by_traffic` (mixed, passenger, freight, heritage = train touristique, unknown). `lines` : lignes présentes dans la zone à cette ère, la plus longue d'abord (slug, name, code, `open_km`, `closed_km`, `gone_km`, `electrified_km`, `main_traffic` = trafic du plus long tronçon en service, null si aucun, `url`) ; au plus `max_lines`, `lines_total` donne le compte complet. `stations` : gares de la zone qui ont un état à cette ère (slug, name, city, `status` : active, tourist, no_commercial, declassified…, `status_label`, `uncertain` = état non confirmé, `url`), ouvertes d'abord ; au plus `max_stations`, `stations_total` et `stations_by_status` comptent tout.
Aucune géométrie n'est renvoyée : pour le tracé, utilisez les exports REST (GeoJSON de l'ère) ou `links.map`, la carte centrée sur la zone. Longueurs : tronçons de la carte, ordre de grandeur et non kilométrage officiel.
Exemples : {"lat": 45.83, "lng": 1.26, "radius_km": 30, "year": 1930} ; {"south": 48.0, "west": -4.8, "north": 48.8, "east": -3.5, "year": 1960, "max_lines": 20}.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class NetworkInAreaTool extends VffTool
{
    private const LEN = 'ST_Length(ST_Intersection(ls.geom, a.g)::geography)';

    protected function rules(): array
    {
        return [
            'south' => ['sometimes', 'numeric', 'between:-90,90'],
            'west' => ['sometimes', 'numeric', 'between:-180,180'],
            'north' => ['sometimes', 'numeric', 'between:-90,90'],
            'east' => ['sometimes', 'numeric', 'between:-180,180'],
            'lat' => ['sometimes', 'numeric', 'between:-90,90'],
            'lng' => ['sometimes', 'numeric', 'between:-180,180'],
            'radius_km' => ['sometimes', 'numeric', 'min:'.MapArea::MIN_RADIUS_KM, 'max:'.MapArea::MAX_RADIUS_KM],
            'year' => ['sometimes', 'integer', 'min:1800', 'max:2100'],
            'max_lines' => ['sometimes', 'integer', 'min:1', 'max:200'],
            'max_stations' => ['sometimes', 'integer', 'min:0', 'max:300'],
        ];
    }

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'south' => $schema->number()->min(-90)->max(90)->description('Rectangle : latitude du bord sud (ex. 48.0).'),
            'west' => $schema->number()->min(-180)->max(180)->description('Rectangle : longitude du bord ouest (ex. -4.8).'),
            'north' => $schema->number()->min(-90)->max(90)->description('Rectangle : latitude du bord nord, plus grande que `south`, 1° de plus au plus.'),
            'east' => $schema->number()->min(-180)->max(180)->description('Rectangle : longitude du bord est, plus grande que `west`, 1,5° de plus au plus.'),
            'lat' => $schema->number()->min(-90)->max(90)->description('Cercle : latitude du centre (ex. 45.83), avec `lng` et `radius_km`.'),
            'lng' => $schema->number()->min(-180)->max(180)->description('Cercle : longitude du centre (ex. 1.26).'),
            'radius_km' => $schema->number()->min(MapArea::MIN_RADIUS_KM)->max(MapArea::MAX_RADIUS_KM)->description('Cercle : rayon en km, 1 à 50.'),
            'year' => $schema->integer()->min(1800)->max(2100)->description('Année quelconque de 1800 à 2100 (ex. 1930), ramenée à l\'ère de la carte ; par défaut aujourd\'hui.'),
            'max_lines' => $schema->integer()->min(1)->max(200)->default(50)->description('Nombre de lignes renvoyées au plus, 1 à 200 (50 par défaut).'),
            'max_stations' => $schema->integer()->min(0)->max(300)->default(100)->description('Nombre de gares renvoyées au plus, 0 à 300 (100 par défaut).'),
        ];
    }

    protected function answer(array $input, string $lang): Response
    {
        $area = MapArea::from($input);
        if ($area === null || $area->inverted()) {
            return $this->fail('invalid_area');
        }
        if ($area->tooSmall()) {
            return $this->fail('area_too_small', ['side' => str_replace('.', $lang === 'en' ? '.' : ',', (string) MapArea::MIN_SIDE_DEG), 'radius' => MapArea::MIN_RADIUS_KM]);
        }
        if ($area->tooLarge()) {
            // Séparateur décimal de la langue (1,5 en français et en allemand, 1.5 en anglais).
            $n = fn (float|int $v): string => str_replace('.', $lang === 'en' ? '.' : ',', (string) $v);

            return $this->fail('area_too_large', ['height' => $n(MapArea::MAX_HEIGHT_DEG), 'width' => $n(MapArea::MAX_WIDTH_DEG), 'radius' => $n(MapArea::MAX_RADIUS_KM)]);
        }
        $era = Lookup::era(isset($input['year']) ? (int) $input['year'] : null);
        if ($era === null) {
            return $this->fail('era_not_found', ['year' => $input['year'] ?? ''], ['available_eras' => Lookup::eraYears()]);
        }
        $maxLines = (int) ($input['max_lines'] ?? 50);
        $maxStations = (int) ($input['max_stations'] ?? 100);
        [$geom, $bindings] = $area->sql();
        $len = self::LEN;
        $from = "FROM line_segments ls, (SELECT {$geom} AS g) a WHERE ls.era_id = ? AND ls.geom && a.g AND ST_Intersects(ls.geom, a.g)";
        $params = [...$bindings, $era->id];

        $lines = DB::select(
            "SELECT l.slug, l.name, l.code,
                    SUM(CASE WHEN x.status IN ('in_service', 'single_track') THEN x.m END) AS open_m,
                    SUM(CASE WHEN x.status IN ('abandoned', 'service_track', 'construction') THEN x.m END) AS closed_m,
                    SUM(CASE WHEN x.status IN ('dismantled', 'razed') THEN x.m END) AS gone_m,
                    SUM(CASE WHEN x.status IN ('in_service', 'single_track') AND x.electrified THEN x.m END) AS electrified_m,
                    (ARRAY_AGG(x.traffic ORDER BY x.m DESC) FILTER (WHERE x.status IN ('in_service', 'single_track')))[1] AS main_traffic
             FROM (SELECT ls.line_id, ls.status, ls.electrified,
                          CASE WHEN COALESCE(ls.is_heritage, false) THEN 'heritage' ELSE COALESCE(ls.traffic_type, 'unknown') END AS traffic,
                          {$len} AS m {$from}) x
             JOIN lines l ON l.id = x.line_id
             GROUP BY l.id, l.slug, l.name, l.code
             ORDER BY COALESCE(SUM(x.m), 0) DESC, l.name",
            $params,
        );
        $byTraffic = [];
        foreach (DB::select(
            "SELECT CASE WHEN COALESCE(ls.is_heritage, false) THEN 'heritage' ELSE COALESCE(ls.traffic_type, 'unknown') END AS t, SUM({$len}) AS m
             {$from} AND ls.status IN ('in_service', 'single_track') GROUP BY 1 ORDER BY m DESC",
            $params,
        ) as $r) {
            $byTraffic[] = ['code' => (string) $r->t, 'label' => (string) trans($r->t === 'passenger' ? 'mcp.traffic.passenger' : 'lines.categories.'.$r->t, [], $lang),
                'km' => self::km($r->m)];
        }
        $providers = DB::table(DB::raw("(SELECT DISTINCT ls.source {$from}) s"))->setBindings($params)->pluck('source')
            ->map(fn ($s) => $this->attribution->providerFor('line_segments', $s === null ? null : (string) $s))->all();

        $sum = fn (string $k): float => self::km(array_sum(array_map(fn (object $l): float => (float) ($l->{$k} ?? 0), $lines)));
        $lineItems = array_map(fn (object $l): array => [
            'slug' => $l->slug, 'name' => $l->name, 'code' => $l->code,
            'open_km' => self::km($l->open_m), 'closed_km' => self::km($l->closed_m), 'gone_km' => self::km($l->gone_m),
            'electrified_km' => self::km($l->electrified_m), 'main_traffic' => $l->main_traffic,
            'url' => self::url('/lines/'.$l->slug),
        ], array_slice($lines, 0, $maxLines));

        $stations = DB::select(
            "SELECT s.slug, s.name, s.city, se.status, COALESCE(se.uncertain, false) AS uncertain, s.position_source
             FROM stations s JOIN station_eras se ON se.station_id = s.id AND se.era_id = ?, (SELECT {$geom} AS g) a
             WHERE se.status IS NOT NULL AND s.geom && a.g AND ST_Intersects(s.geom, a.g)
             ORDER BY CASE WHEN se.status IN ('active', 'tourist', 'no_commercial') THEN 0 ELSE 1 END, s.name",
            [$era->id, ...$bindings],
        );
        $byStatus = [];
        foreach ($stations as $s) {
            $byStatus[$s->status] = ($byStatus[$s->status] ?? 0) + 1;
            $providers[] = $this->attribution->providerFor('station_sources', $s->position_source === null ? null : (string) $s->position_source);
        }
        arsort($byStatus);
        $stationItems = array_map(fn (object $s): array => [
            'slug' => $s->slug, 'name' => $s->name, 'city' => $s->city, 'status' => $s->status,
            'status_label' => (string) trans('stations.statuses.'.$s->status, [], $lang), 'uncertain' => (bool) $s->uncertain,
            'url' => self::url('/stations/'.$s->slug),
        ], array_slice($stations, 0, $maxStations));

        [$lng, $lat] = $area->center();
        $map = self::url(sprintf('/maps?era=%d&center=%.5F,%.5F&zoom=%d', $era->year, $lng, $lat, $area->zoom()));

        return $this->respond([
            'area' => $area->describe(),
            'era' => [
                'requested_year' => isset($input['year']) ? (int) $input['year'] : null,
                'year' => $era->year,
                'is_current' => $era->is_current,
                'snapshot' => $era->is_current ? (string) trans('mcp.today', [], $lang) : sprintf('%d-12-31', $era->year),
            ],
            'totals' => [
                'open_km' => $sum('open_m'),
                'closed_km' => $sum('closed_m'),
                'gone_km' => $sum('gone_m'),
                'electrified_km' => $sum('electrified_m'),
                'open_km_by_traffic' => $byTraffic,
            ],
            'lines_total' => count($lines),
            'lines' => $lineItems,
            'stations_total' => count($stations),
            'stations_by_status' => $byStatus,
            'stations' => $stationItems,
            'links' => ['map' => $map],
            'available_eras' => Lookup::eraYears(),
        ], $providers, $lang, $map, ['stations', 'lines']);
    }

    private static function km(mixed $metres): float
    {
        return round((float) ($metres ?? 0) / 1000, 1);
    }
}
