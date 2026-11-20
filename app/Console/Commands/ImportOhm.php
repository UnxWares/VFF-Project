<?php

namespace App\Console\Commands;

use App\Services\OhmDate;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;

/**
 * Importe dans `source_traces` (source = 'ohm') TOUS les ways ferroviaires
 * d'OpenHistoricalMap couvrant la France métropolitaine (Corse comprise).
 *
 * Pourquoi OHM : c'est la seule source ouverte (CC0, cf.
 * database/data/source_rights.php ; on cite quand même la source) où chaque
 * tronçon porte ses propres dates d'existence. Elle complète archeoferroviaire
 * (tracés sans dates exploitables) pour découper les lignes par décennie.
 *
 * Périmètre : railway = rail | narrow_gauge | light_rail | tram | subway |
 * funicular | monorail | preserved | miniature | abandoned | disused | razed |
 * dismantled | construction | proposed, plus les variantes à préfixe de cycle
 * de vie (`disused:railway=rail`…, dépréciées dans OHM mais présentes) et
 * `historic=railway`. Les ways de quais, gares, plaques tournantes… sont
 * exclus. Un way est gardé s'il intersecte la métropole (contours Etalab
 * « régions 1000 m », tampon de 2 km pour garder les lignes transfrontalières).
 *
 * Champs : layer = valeur de `railway` (ou le préfixe de cycle de vie, ou
 * 'historic') ; properties = TOUS les tags ; opened_year / closed_year =
 * année de start_date / end_date (formats ISO, EDTF et texte libre gérés par
 * App\Services\OhmDate) ; dates_source = 'ohm'. Attention : ces deux années
 * sont la période d'existence DU WAY tel que tagué, pas forcément l'ouverture
 * et la fermeture de la ligne (cf. conventions dans vff:ohm-events).
 *
 * Collecte : Overpass OHM par tuiles de 2° (≈ 30 tuiles utiles), redécoupées
 * en 4 si le serveur renvoie une erreur d'exécution ; pause entre requêtes,
 * reprises avec attente croissante sur 429 / 5xx / délai dépassé. Chaque
 * réponse brute est gardée (gzip) dans storage/app/sources/ohm/AAAA-MM-JJ/
 * pour rejouer l'import sans re-télécharger (--cache).
 *
 * Idempotent : upsert sur (source, source_ref = id du way). Les ways qui ne
 * sont plus renvoyés sont signalés, et supprimés avec --prune (seulement si
 * toutes les tuiles ont été récupérées).
 *
 *   php artisan vff:import-ohm
 *   php artisan vff:import-ohm --cache --prune
 */
class ImportOhm extends Command
{
    protected $signature = 'vff:import-ohm
        {--cache : réutilise les réponses Overpass déjà téléchargées aujourd\'hui}
        {--tile=2 : taille des tuiles en degrés}
        {--pause=2 : pause entre deux requêtes Overpass (secondes)}
        {--prune : supprime les ways OHM qui ne sont plus renvoyés}';

    protected $description = 'Importe les ways ferroviaires OpenHistoricalMap (France métropolitaine) dans source_traces.';

    private const OVERPASS_URL = 'https://overpass-api.openhistoricalmap.org/api/interpreter';

    private const BOUNDARY_URL = 'https://etalab-datasets.geo.data.gouv.fr/contours-administratifs/2024/geojson/regions-1000m.geojson.gz';

    /** Emprise de la métropole + Corse (sud, ouest, nord, est). */
    private const BBOX = [41.3, -5.3, 51.2, 9.7];

    /** Valeurs de railway=* qui décrivent une voie (et non un quai, une gare…). */
    private const TRACK_VALUES = 'rail|narrow_gauge|light_rail|tram|subway|funicular|monorail|preserved|miniature|abandoned|disused|razed|dismantled|construction|proposed';

    /** Préfixes de cycle de vie reconnus sur la clé railway. */
    private const LIFECYCLE_PREFIXES = 'abandoned|disused|razed|dismantled|demolished|removed|destroyed|was|construction|proposed|planned|preserved|historic';

    private string $cacheDir;

    private int $failedTiles = 0;

    /** @var array<int, true> ids déjà écrits pendant ce passage (tuiles qui se recouvrent) */
    private array $seen = [];

    private int $written = 0;

    private int $outside = 0;

    public function handle(): int
    {
        $startedAt = now();
        $this->cacheDir = 'sources/ohm/'.now()->format('Y-m-d');

        $this->loadBoundary();

        $step = max(0.25, (float) $this->option('tile'));
        [$south, $west, $north, $east] = self::BBOX;
        $tiles = [];
        for ($lat = $south; $lat < $north; $lat += $step) {
            for ($lon = $west; $lon < $east; $lon += $step) {
                $tile = [round($lat, 4), round($lon, 4), round(min($lat + $step, $north), 4), round(min($lon + $step, $east), 4)];
                if ($this->tileTouchesFrance($tile)) {
                    $tiles[] = $tile;
                }
            }
        }
        $this->line(sprintf('→ %d tuiles de %s° recouvrent la métropole.', count($tiles), $step));

        $bar = $this->output->createProgressBar(count($tiles));
        foreach ($tiles as $tile) {
            $this->fetchTile($tile, 0);
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();

        $this->line("Ways écrits : {$this->written} · hors métropole ignorés : {$this->outside} · tuiles en échec : {$this->failedTiles}");

        $stale = DB::table('source_traces')->where('source', 'ohm')->where('seen_at', '<', $startedAt);
        $staleCount = $stale->count();
        if ($staleCount && $this->option('prune') && $this->failedTiles === 0) {
            $stale->delete();
            $this->warn("{$staleCount} ways OHM disparus supprimés.");
        } elseif ($staleCount) {
            $this->warn("{$staleCount} ways OHM non renvoyés par ce passage (--prune pour les supprimer, si aucune tuile n'a échoué).");
        }

        $this->table(['Calque', 'Ways', 'km', 'Datés (début ou fin)', 'Avec fin'], array_map(
            fn ($r) => [$r->layer, $r->n, $r->km, $r->dated, $r->ended],
            DB::select(<<<'SQL'
                SELECT layer, COUNT(*) n, ROUND(SUM(length_m) / 1000.0) km,
                       COUNT(*) FILTER (WHERE opened_year IS NOT NULL OR closed_year IS NOT NULL) dated,
                       COUNT(*) FILTER (WHERE closed_year IS NOT NULL) ended
                FROM source_traces WHERE source = 'ohm' GROUP BY layer ORDER BY 2 DESC
                SQL),
        ));
        $summary = DB::selectOne("SELECT COUNT(*) n, ROUND(SUM(length_m) / 1000.0) km FROM source_traces WHERE source = 'ohm'");
        $this->info("✓ {$summary->n} ways OHM en base ({$summary->km} km).");

        return $this->failedTiles ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Contour de la métropole (union des 13 régions, tampon 2 km) dans une
     * table temporaire de session, en Lambert-93 et en WGS84.
     */
    private function loadBoundary(): void
    {
        $path = 'sources/ohm/regions-1000m.geojson';
        if (! Storage::disk('local')->exists($path)) {
            $this->line('→ Téléchargement des contours régionaux (Etalab, Licence Ouverte)…');
            $gz = Http::withHeaders(['User-Agent' => $this->userAgent()])->timeout(120)->get(self::BOUNDARY_URL)->throw()->body();
            Storage::disk('local')->put($path, gzdecode($gz) ?: $gz);
        }
        $regions = json_decode(Storage::disk('local')->get($path), true, flags: JSON_THROW_ON_ERROR);
        $geometries = [];
        foreach ($regions['features'] as $feature) {
            $code = (string) ($feature['properties']['code'] ?? '');
            if (strlen($code) === 2 && $code >= '11') { // régions métropolitaines (les DROM ont 01-06)
                $geometries[] = $feature['geometry'];
            }
        }

        DB::statement('DROP TABLE IF EXISTS ohm_france');
        DB::statement(<<<'SQL'
            CREATE TEMP TABLE ohm_france AS
            SELECT ST_Transform(b, 4326) AS g
            FROM (SELECT ST_Buffer(ST_Transform(ST_UnaryUnion(ST_MakeValid(ST_SetSRID(ST_GeomFromGeoJSON(:gc), 4326))), 2154), 2000) AS b) t
            SQL, ['gc' => json_encode(['type' => 'GeometryCollection', 'geometries' => $geometries])]);
    }

    /** @param  array{0: float, 1: float, 2: float, 3: float}  $tile */
    private function tileTouchesFrance(array $tile): bool
    {
        return (bool) DB::selectOne(
            'SELECT ST_Intersects(g, ST_MakeEnvelope(?, ?, ?, ?, 4326)) AS hit FROM ohm_france',
            [$tile[1], $tile[0], $tile[3], $tile[2]],
        )->hit;
    }

    /** @param  array{0: float, 1: float, 2: float, 3: float}  $tile */
    private function fetchTile(array $tile, int $depth): void
    {
        $bbox = implode(',', $tile);
        $cacheFile = $this->cacheDir.'/'.str_replace(',', '_', $bbox).'.json.gz';

        $json = null;
        if ($this->option('cache') && Storage::disk('local')->exists($cacheFile)) {
            $json = gzdecode(Storage::disk('local')->get($cacheFile));
        }
        if ($json === null) {
            $json = $this->overpass($this->query($bbox));
            if ($json === null) {
                // Erreur persistante : on redécoupe (jusqu'à 2 niveaux) avant d'abandonner.
                if ($depth < 2) {
                    foreach ($this->split($tile) as $sub) {
                        $this->fetchTile($sub, $depth + 1);
                    }
                } else {
                    $this->failedTiles++;
                    $this->newLine();
                    $this->error("Tuile {$bbox} abandonnée après plusieurs essais.");
                }

                return;
            }
            Storage::disk('local')->put($cacheFile, gzencode($json, 6));
            Sleep::sleep(max(0, (int) $this->option('pause')));
        }

        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        $this->store($data['elements'] ?? []);
    }

    private function query(string $bbox): string
    {
        $values = self::TRACK_VALUES;
        $prefixes = self::LIFECYCLE_PREFIXES;
        $base = 'rail|narrow_gauge|light_rail|tram|subway|funicular|monorail|preserved|miniature';

        return <<<OVERPASS
            [out:json][timeout:240][maxsize:536870912];
            (
              way["railway"~"^({$values})$"]({$bbox});
              way[~"^({$prefixes}):railway$"~"^({$base})$"]({$bbox});
              way["historic"="railway"]({$bbox});
            );
            out geom;
            OVERPASS;
    }

    /** Requête Overpass polie : reprises avec attente croissante, null si échec. */
    private function overpass(string $query): ?string
    {
        for ($attempt = 1; $attempt <= 4; $attempt++) {
            try {
                $response = Http::withHeaders(['User-Agent' => $this->userAgent()])
                    ->asForm()
                    ->timeout(300)
                    ->post(self::OVERPASS_URL, ['data' => $query]);
            } catch (ConnectionException $e) {
                $this->warn("  connexion Overpass impossible ({$e->getMessage()}), essai {$attempt}/4");
                Sleep::sleep(15 * $attempt);

                continue;
            }

            if ($response->successful()) {
                $body = $response->body();
                // Overpass répond 200 avec un « remark » en cas de dépassement de délai / mémoire.
                if (preg_match('/"remark"\s*:\s*"[^"]*(runtime error|timed out|out of memory)/i', substr($body, -2000))) {
                    $this->warn('  Overpass : erreur d\'exécution, la tuile sera redécoupée.');

                    return null;
                }

                return $body;
            }
            $wait = in_array($response->status(), [429, 503, 504], true) ? 30 * $attempt : 10 * $attempt;
            $this->warn("  Overpass HTTP {$response->status()}, nouvel essai dans {$wait} s ({$attempt}/4)");
            Sleep::sleep($wait);
        }

        return null;
    }

    /**
     * @param  array{0: float, 1: float, 2: float, 3: float}  $tile
     * @return array<int, array{0: float, 1: float, 2: float, 3: float}>
     */
    private function split(array $tile): array
    {
        [$s, $w, $n, $e] = $tile;
        $midLat = round(($s + $n) / 2, 4);
        $midLon = round(($w + $e) / 2, 4);

        return [[$s, $w, $midLat, $midLon], [$s, $midLon, $midLat, $e], [$midLat, $w, $n, $midLon], [$midLat, $midLon, $n, $e]];
    }

    /** @param  array<int, array<string, mixed>>  $elements */
    private function store(array $elements): void
    {
        $rows = [];
        foreach ($elements as $el) {
            if (($el['type'] ?? null) !== 'way' || isset($this->seen[$el['id']])) {
                continue;
            }
            $coords = [];
            foreach ($el['geometry'] ?? [] as $pt) {
                if (is_array($pt) && isset($pt['lon'], $pt['lat'])) {
                    $coords[] = [(float) $pt['lon'], (float) $pt['lat']];
                }
            }
            if (count(array_unique(array_map(fn ($c) => $c[0].','.$c[1], $coords))) < 2) {
                continue; // way dégénéré (nœuds manquants ou confondus)
            }
            $tags = $el['tags'] ?? [];
            $start = OhmDate::fromTags($tags, 'start');
            $end = OhmDate::fromTags($tags, 'end');

            $rows[] = [
                'ref' => (string) $el['id'],
                'layer' => $this->layer($tags),
                'name' => isset($tags['name']) ? mb_substr(trim((string) $tags['name']), 0, 2000) : null,
                'props' => json_encode($tags ?: new \stdClass, JSON_UNESCAPED_UNICODE),
                'geojson' => json_encode(['type' => 'LineString', 'coordinates' => $coords]),
                'opened' => $start['year'] ?? null,
                'closed' => $end['year'] ?? null,
                'dated' => ($start || $end) ? 1.0 : null,
            ];
            $this->seen[$el['id']] = true;
        }

        foreach (array_chunk($rows, 250) as $chunk) {
            $this->upsert($chunk);
        }
    }

    /** Calque : valeur de railway, sinon préfixe de cycle de vie, sinon historic. */
    private function layer(array $tags): string
    {
        $railway = $tags['railway'] ?? null;
        if ($railway && preg_match('/^('.self::TRACK_VALUES.')$/', $railway)) {
            return $railway;
        }
        foreach ($tags as $key => $value) {
            if (preg_match('/^('.self::LIFECYCLE_PREFIXES.'):railway$/', (string) $key, $m)) {
                return $m[1];
            }
        }

        return 'historic';
    }

    /** @param  array<int, array<string, mixed>>  $rows */
    private function upsert(array $rows): void
    {
        $values = [];
        $bindings = [];
        foreach ($rows as $row) {
            $values[] = '(?, ?, ?, ?, ?, ?, ?, ?)';
            array_push($bindings, $row['ref'], $row['layer'], $row['name'], $row['props'], $row['geojson'], $row['opened'], $row['closed'], $row['dated']);
        }
        $seen = now();
        $sql = <<<'SQL'
            WITH v(ref, layer, name, props, geojson, opened, closed, dated) AS (VALUES %s),
            g AS (
                SELECT v.*, ST_Multi(ST_SetSRID(ST_GeomFromGeoJSON(geojson), 4326)) AS geom FROM v
            ),
            ins AS (
                INSERT INTO source_traces (source, source_ref, layer, name, properties, geom, length_m,
                                           opened_year, closed_year, dates_source, dates_score, seen_at, created_at, updated_at)
                SELECT 'ohm', g.ref, g.layer, g.name, CAST(g.props AS jsonb), g.geom,
                       ROUND(ST_Length(g.geom::geography))::int,
                       CAST(g.opened AS smallint), CAST(g.closed AS smallint),
                       CASE WHEN g.dated IS NOT NULL THEN 'ohm' END, CAST(g.dated AS double precision),
                       CAST(? AS timestamp), NOW(), NOW()
                FROM g, ohm_france f
                WHERE ST_Intersects(g.geom, f.g)
                ON CONFLICT (source, source_ref) DO UPDATE SET
                    layer = EXCLUDED.layer, name = EXCLUDED.name, properties = EXCLUDED.properties,
                    geom = EXCLUDED.geom, length_m = EXCLUDED.length_m,
                    opened_year = EXCLUDED.opened_year, closed_year = EXCLUDED.closed_year,
                    dates_source = EXCLUDED.dates_source, dates_score = EXCLUDED.dates_score,
                    seen_at = EXCLUDED.seen_at,
                    updated_at = CASE WHEN source_traces.properties IS DISTINCT FROM EXCLUDED.properties
                                        OR ST_AsBinary(source_traces.geom) IS DISTINCT FROM ST_AsBinary(EXCLUDED.geom)
                                      THEN NOW() ELSE source_traces.updated_at END
                RETURNING 1
            )
            SELECT COUNT(*) AS n FROM ins
            SQL;
        $bindings[] = $seen;
        $n = (int) DB::selectOne(sprintf($sql, implode(', ', $values)), $bindings)->n;
        $this->written += $n;
        $this->outside += count($rows) - $n;
    }

    private function userAgent(): string
    {
        return (string) config('services.osm.user_agent', 'VFF-Bot/1.0 (+https://vff-project.org)');
    }
}
