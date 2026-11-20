<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;

/**
 * Importe dans `present_osm_ways` ce qu'OSM dit de l'état ACTUEL des emprises
 * ferroviaires : voies vertes / pistes cyclables proches du rail, et ways
 * ferroviaires (rails en place ou cycle de vie abandoned/razed/disused…).
 *
 * Pourquoi : une ligne « Fermée » ou « Déclassée » pour SNCF Réseau est
 * souvent devenue une voie verte, mais le way OSM de la voie verte ne porte
 * pas forcément de tag ferroviaire (railway=abandoned…). vff:detect-present-states
 * compare donc ces ways à l'axe de l'ancienne voie (≤ 8 m sur ≥ 70 %).
 * Les ways ferroviaires sont repris ici (en plus de source_traces, alimenté
 * par vff:import-osm-railways) pour ne pas dépendre de l'avancement de cet import.
 *
 * Ce qui est importé, par région ISO 3166-2 (une requête Overpass par région,
 * pause entre régions, backoff et rotation de serveur) :
 *   - kind = greenway : highway=cycleway, ou highway=path|track|footway|
 *     bridleway|pedestrian avec bicycle=designated|official ;
 *   - kind = railway : railway=rail|narrow_gauge|light_rail|funicular|abandoned|
 *     disused|razed|dismantled|construction|preserved, <cycle>:railway=<voie>,
 *     historic=railway (même sélection que vff:import-osm-railways) ;
 *   - les relations route=bicycle|mtb dont les voies vertes sont membres (nom,
 *     start_date) : elles donnent parfois l'année d'ouverture de la voie verte.
 * Les voies vertes à plus de ~80 m de tout tracé ferroviaire connu
 * (source_traces, ways ferroviaires OSM) ne sont pas conservées.
 *
 * Chaque réponse est gardée dans storage/app/sources/osm/
 * (greenways-<région>-<date>.json, format JSON Overpass) : --from-cache
 * réutilise la dernière copie sans aucune requête. Quand Overpass est saturé,
 * bin/osm-geofabrik produit ce cache depuis les extraits Geofabrik (même
 * filtre, même format, docker/osm/pbf2overpass.py).
 * Données © contributeurs OpenStreetMap, ODbL. User-Agent VFF-Bot.
 *
 *   bin/osm-geofabrik && php artisan vff:import-osm-greenways --from-cache
 *
 *   php artisan vff:import-osm-greenways
 *   php artisan vff:import-osm-greenways --region=FR-PDL --region=FR-NOR
 *   php artisan vff:import-osm-greenways --from-cache
 */
class ImportOsmGreenways extends Command
{
    protected $signature = 'vff:import-osm-greenways
        {--region=* : codes ISO 3166-2 (FR-BRE, FR-NOR…) ; défaut : 13 régions métropolitaines}
        {--endpoint=* : serveur(s) Overpass, essayés à tour de rôle}
        {--pause=30 : secondes de pause entre deux régions}
        {--from-cache : n\'utilise que la dernière copie locale de chaque région}
        {--refresh : re-télécharge même si une copie du jour existe}';

    protected $description = 'Importe les voies vertes OSM proches du rail et les ways ferroviaires (rails / cycle de vie) dans present_osm_ways.';

    private const DEFAULT_ENDPOINTS = [
        'https://overpass-api.de/api/interpreter',
        'https://maps.mail.ru/osm/tools/overpass/api/interpreter',
    ];

    /** Distance max (degrés, ~60-90 m) à un tracé ferroviaire pour garder un way. */
    private const NEAR_RAIL_DEG = 0.0009;

    private int $endpointIndex = 0;

    public function handle(): int
    {
        ini_set('memory_limit', '4G');
        $regions = array_values(array_unique(array_map(
            fn ($r) => preg_replace('/[^A-Z0-9\-]/', '', strtoupper($r)),
            $this->option('region') ?: ImportOsmRailways::METRO_REGIONS,
        )));
        $startedAt = now()->startOfSecond();
        $failed = [];

        foreach ($regions as $i => $region) {
            $this->line("→ {$region}");
            try {
                [$payload, $fromCache] = $this->payload($region);
            } catch (\RuntimeException $e) {
                $this->error("  {$region} : {$e->getMessage()}");
                $failed[] = $region;

                continue;
            }
            if ($payload === null) {
                $this->warn("  {$region} : aucune copie locale");
                $failed[] = $region;

                continue;
            }
            [$read, $kept] = $this->persist($region, $payload['elements'] ?? [], $startedAt);
            $this->line("  {$read} ways lus, {$kept} gardés (proches d'une voie ferrée)".($fromCache ? ' [copie locale]' : ''));
            unset($payload);

            if (! $fromCache && $i < count($regions) - 1) {
                Sleep::sleep(max(0, (int) $this->option('pause')));
            }
        }

        // Ways disparus d'OSM : seulement pour les régions effectivement relues.
        $done = array_diff($regions, $failed);
        if ($done) {
            $n = DB::table('present_osm_ways')->whereIn('region', $done)->where('seen_at', '<', $startedAt)->delete();
            if ($n) {
                $this->line("{$n} ways absents de la dernière lecture supprimés.");
            }
        }
        $this->report();

        if ($failed) {
            $this->error('Régions en échec (relancer : '.implode(' ', array_map(fn ($r) => "--region={$r}", $failed)).')');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    public static function query(string $region): string
    {
        $railway = 'rail|narrow_gauge|light_rail|funicular|abandoned|disused|razed|dismantled|construction|preserved';
        $prefixes = 'abandoned|disused|razed|removed|dismantled|demolished|was|construction|preserved';
        $tracks = 'rail|narrow_gauge|light_rail|tram|funicular|monorail|yes|preserved|abandoned|disused|razed|dismantled|construction';

        return <<<OVERPASS
            [out:json][timeout:900][maxsize:2147483648];
            area["ISO3166-2"="{$region}"]["boundary"="administrative"]->.r;
            (
              way["highway"="cycleway"](area.r);
              way["highway"~"^(path|track|footway|bridleway|pedestrian)$"]["bicycle"~"^(designated|official)$"](area.r);
            )->.w;
            (
              way["railway"~"^({$railway})$"](area.r);
              way[~"^({$prefixes}):railway$"~"^({$tracks})$"](area.r);
              way["historic"="railway"](area.r);
            )->.f;
            (.w; .f;);
            out tags geom qt;
            rel(bw.w)["route"~"^(bicycle|mtb)$"];
            out body qt;
            OVERPASS;
    }

    /** @return array{0: ?array<string, mixed>, 1: bool} */
    private function payload(string $region): array
    {
        $disk = Storage::disk('local');
        if ($this->option('from-cache')) {
            $files = collect($disk->files('sources/osm'))->filter(fn ($f) => str_starts_with(basename($f), "greenways-{$region}-"))->sort()->values();

            return [$files->isEmpty() ? null : json_decode($disk->get($files->last()), true, flags: JSON_THROW_ON_ERROR), true];
        }
        $path = 'sources/osm/greenways-'.$region.'-'.now()->format('Y-m-d').'.json';
        if (! $this->option('refresh') && $disk->exists($path)) {
            return [json_decode($disk->get($path), true, flags: JSON_THROW_ON_ERROR), true];
        }
        $body = $this->fetch(self::query($region));
        $disk->put($path, $body);

        return [json_decode($body, true, flags: JSON_THROW_ON_ERROR), false];
    }

    private function fetch(string $query, int $maxAttempts = 6): string
    {
        $endpoints = $this->option('endpoint') ?: self::DEFAULT_ENDPOINTS;
        $ua = (string) config('services.osm.user_agent', 'VFF-Bot/1.0 (+https://vff-project.org)');
        $lastError = '';
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $endpoint = $endpoints[$this->endpointIndex % count($endpoints)];
            try {
                $response = Http::withHeaders(['User-Agent' => $ua])->timeout(960)->asForm()->post($endpoint, ['data' => $query]);
                $body = $response->body();
                if ($response->ok() && str_starts_with(ltrim($body), '{')) {
                    // Dépassement de temps / mémoire signalé en HTTP 200 avec un résultat tronqué.
                    if (preg_match('/"remark"\s*:\s*"([^"]*runtime error[^"]*)"/', substr($body, -2000), $m)) {
                        $lastError = $m[1];
                    } else {
                        return $body;
                    }
                } else {
                    $lastError = "HTTP {$response->status()} sur ".parse_url($endpoint, PHP_URL_HOST);
                    if ($response->status() === 400) {
                        throw new \RuntimeException("requête refusée : {$lastError}");
                    }
                }
            } catch (ConnectionException $e) {
                $lastError = $e->getMessage();
            }
            $this->endpointIndex++;
            if ($attempt < $maxAttempts) {
                $wait = 30 * 2 ** ($attempt - 1);
                $this->warn("  tentative {$attempt} échouée ({$lastError}) : nouvel essai dans {$wait}s");
                Sleep::sleep($wait);
            }
        }

        throw new \RuntimeException("Overpass a échoué après {$maxAttempts} tentatives : {$lastError}");
    }

    /**
     * @param  array<int, array<string, mixed>>  $elements
     * @return array{0: int, 1: int}
     */
    private function persist(string $region, array $elements, \DateTimeInterface $seenAt): array
    {
        // Relations vélo → résumé par way membre.
        $routes = [];
        foreach ($elements as $el) {
            if (($el['type'] ?? null) !== 'relation') {
                continue;
            }
            $t = $el['tags'] ?? [];
            $summary = array_filter([
                'id' => $el['id'],
                'name' => $t['name'] ?? null,
                'ref' => $t['ref'] ?? null,
                'network' => $t['network'] ?? null,
                'start_date' => $t['start_date'] ?? $t['opening_date'] ?? null,
            ], fn ($v) => $v !== null);
            foreach ($el['members'] ?? [] as $m) {
                if (($m['type'] ?? null) === 'way') {
                    $routes[$m['ref']][] = $summary;
                }
            }
        }

        $rows = [];
        foreach ($elements as $el) {
            if (($el['type'] ?? null) !== 'way') {
                continue;
            }
            $coords = [];
            foreach ($el['geometry'] ?? [] as $pt) {
                if (is_array($pt) && isset($pt['lon'], $pt['lat'])) {
                    $coords[] = [(float) $pt['lon'], (float) $pt['lat']];
                }
            }
            if (count($coords) < 2) {
                continue;
            }
            $tags = $el['tags'] ?? [];
            $rows[] = [
                'id' => $el['id'],
                'kind' => self::isRailway($tags) ? 'railway' : 'greenway',
                'highway' => $tags['highway'] ?? null,
                'name' => $tags['name'] ?? null,
                'tags' => $tags ?: new \stdClass,
                'routes' => $routes[$el['id']] ?? null,
                'start_year' => self::year($tags['start_date'] ?? $tags['opening_date'] ?? null),
                'g' => ['type' => 'LineString', 'coordinates' => $coords],
            ];
        }

        // Ways ferroviaires d'abord, dans des requêtes SÉPARÉES : ils servent au filtre de
        // proximité des voies vertes, et un INSERT … SELECT ne voit pas les lignes qu'il
        // insère lui-même (avant : une voie verte du même lot qu'un way ferroviaire OSM
        // non encore en base était écartée).
        $railways = array_values(array_filter($rows, fn ($r) => $r['kind'] === 'railway'));
        $greenways = array_values(array_filter($rows, fn ($r) => $r['kind'] !== 'railway'));
        $kept = 0;
        foreach ([...array_chunk($railways, 2000), ...array_chunk($greenways, 2000)] as $chunk) {
            $kept += DB::affectingStatement(<<<'SQL'
                INSERT INTO present_osm_ways (id, region, kind, highway, name, tags, routes, start_year, length_m, seen_at, created_at, updated_at, geom)
                SELECT (r->>'id')::bigint, :region, r->>'kind', LEFT(r->>'highway', 30), r->>'name', r->'tags',
                       NULLIF(r->'routes', 'null'::jsonb), (r->>'start_year')::smallint,
                       ROUND(ST_Length(g::geography))::int, :seen, NOW(), NOW(), g
                FROM jsonb_array_elements(CAST(:rows AS jsonb)) r
                CROSS JOIN LATERAL (SELECT ST_SetSRID(ST_GeomFromGeoJSON(r->>'g'), 4326) AS g) t
                WHERE r->>'kind' = 'railway'
                   OR EXISTS (SELECT 1 FROM source_traces s WHERE ST_DWithin(s.geom, t.g, :near))
                   OR EXISTS (SELECT 1 FROM present_osm_ways w WHERE w.kind = 'railway' AND ST_DWithin(w.geom, t.g, :near2))
                ON CONFLICT (id) DO UPDATE
                    SET region = EXCLUDED.region, kind = EXCLUDED.kind, highway = EXCLUDED.highway, name = EXCLUDED.name,
                        tags = EXCLUDED.tags, routes = EXCLUDED.routes, start_year = EXCLUDED.start_year,
                        length_m = EXCLUDED.length_m, geom = EXCLUDED.geom, seen_at = EXCLUDED.seen_at, updated_at = NOW()
                SQL, [
                'region' => $region,
                'seen' => $seenAt,
                'rows' => json_encode($chunk, JSON_UNESCAPED_UNICODE),
                'near' => self::NEAR_RAIL_DEG,
                'near2' => self::NEAR_RAIL_DEG,
            ]);
        }

        return [count($rows), $kept];
    }

    /** Way ferroviaire (rails en place ou cycle de vie), même sélection que vff:import-osm-railways. */
    public static function isRailway(array $tags): bool
    {
        return ImportOsmRailways::layer($tags) !== null;
    }

    /** « 2012 », « 2012-06-30 », « ~1995 », « 2010..2012 » → première année plausible. */
    public static function year(?string $date): ?int
    {
        if ($date && preg_match('/(1[89]\d\d|20\d\d)/', $date, $m)) {
            $y = (int) $m[1];

            return $y <= (int) date('Y') ? $y : null;
        }

        return null;
    }

    private function report(): void
    {
        $rows = DB::select(<<<'SQL'
            SELECT region, kind, COUNT(*) AS n, ROUND(SUM(length_m) / 1000.0) AS km,
                   COUNT(*) FILTER (WHERE start_year IS NOT NULL) AS dated,
                   COUNT(*) FILTER (WHERE routes IS NOT NULL) AS in_route
            FROM present_osm_ways GROUP BY 1, 2 ORDER BY 1, 2
            SQL);
        $this->table(['Région', 'Type', 'Ways', 'km', 'datés (start_date)', 'dans une véloroute'],
            array_map(fn ($r) => [$r->region, $r->kind, $r->n, $r->km, $r->dated, $r->in_route], $rows));
    }
}
