<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;

/**
 * Importe TOUS les ways ferroviaires OSM de France dans `source_traces`
 * (source = 'osm'), y compris les variantes de cycle de vie.
 *
 * Pourquoi une seconde voie d'import OSM : `vff:import-osm` (OsmImporter)
 * matérialise directement des `line_segments` et filtre volontairement les
 * voies de service, les ways sans ref et les cycles de vie préfixés
 * (abandoned:railway=*, …). Ici on garde la matière brute, sans perte, à côté
 * de la BD TOPO : OSM apporte ce que l'IGN n'a pas (tags ref = code ligne,
 * usage, start_date, wikidata, gauge) et surtout les lignes DISPARUES
 * (railway=abandoned/razed, razed:railway=*, …) dont la plateforme subsiste.
 *
 * Ce qui est importé :
 *   - railway = rail | narrow_gauge | light_rail | funicular | abandoned |
 *     disused | razed | dismantled | construction | preserved ;
 *   - <cycle>:railway = voie (rail, narrow_gauge, tram, …) pour cycle ∈
 *     abandoned, disused, razed, removed, dismantled, demolished, was,
 *     construction, preserved - les gares/quais préfixés sont ignorés ;
 *   - historic = railway.
 * Les tramways urbains actifs (railway=tram), le métro et le miniature sont
 * exclus ; les voies de service (service=yard/siding/spur) sont gardées, pour
 * comparer à armes égales avec la BD TOPO.
 *
 * Mapping : source_ref = id du way, layer = valeur de railway (sinon le
 * préfixe de cycle de vie, sinon 'historic'), name = name (ou old_name),
 * properties = tous les tags. Idempotent (ON CONFLICT + seen_at).
 *
 * Overpass est ménagé : une requête par région ISO 3166-2 (ou département,
 * ex. FR-29), pause entre régions, backoff et rotation de serveur sur
 * 429/502/504 et sur les « runtime error » renvoyées en HTTP 200. Chaque
 * réponse est conservée dans storage/app/sources/osm/ : relancer la commande
 * le même jour reprend sans re-télécharger les régions déjà reçues.
 * Repli si Overpass reste saturé : --geojsonseq= (extraits Geofabrik filtrés
 * par osmium, cf. importGeojsonSeq()).
 * Données © contributeurs OpenStreetMap, ODbL.
 *
 *   php artisan vff:import-osm-railways                      # 13 régions métropolitaines
 *   php artisan vff:import-osm-railways --region=FR-BRE --region=FR-PDL
 *   php artisan vff:import-osm-railways --refresh --prune
 */
class ImportOsmRailways extends Command
{
    protected $signature = 'vff:import-osm-railways
        {--region=* : codes ISO 3166-2 (FR-BRE, FR-20R, FR-29…) ; défaut : 13 régions métropolitaines}
        {--endpoint=* : serveur(s) Overpass, essayés à tour de rôle}
        {--pause=20 : secondes de pause entre deux régions}
        {--attempts=8 : tentatives par région (backoff 30 s → 10 min)}
        {--refresh : re-télécharge même si une copie du jour existe}
        {--geojsonseq=* : repli hors Overpass : fichier(s) GeoJSON-seq produits par osmium (voir docblock)}
        {--prune : supprime les ways osm absents (seulement après les 13 régions)}';

    protected $description = 'Importe les ways ferroviaires OSM (actifs + cycles de vie) de France dans source_traces.';

    public const METRO_REGIONS = [
        'FR-ARA', 'FR-BFC', 'FR-BRE', 'FR-CVL', 'FR-20R', 'FR-GES', 'FR-HDF',
        'FR-IDF', 'FR-NOR', 'FR-NAQ', 'FR-OCC', 'FR-PDL', 'FR-PAC',
    ];

    private const DEFAULT_ENDPOINTS = [
        'https://overpass-api.de/api/interpreter',
    ];

    private const RAILWAY_VALUES = 'rail|narrow_gauge|light_rail|funicular|abandoned|disused|razed|dismantled|construction|preserved';

    private const LIFECYCLE_PREFIXES = 'abandoned|disused|razed|removed|dismantled|demolished|was|construction|preserved';

    /** Valeurs « voie » acceptées derrière un préfixe de cycle de vie (exclut station, platform, …). */
    private const LIFECYCLE_TRACK_VALUES = 'rail|narrow_gauge|light_rail|tram|funicular|monorail|yes|preserved|abandoned|disused|razed|dismantled|construction';

    private int $endpointIndex = 0;

    public function handle(): int
    {
        ini_set('memory_limit', '4G');
        $regions = array_values(array_unique(array_map(
            fn ($r) => preg_replace('/[^A-Z0-9\-]/', '', strtoupper($r)),
            $this->option('region') ?: self::METRO_REGIONS,
        )));
        $startedAt = now()->startOfSecond();
        $done = [];
        $failed = [];

        if ($files = $this->option('geojsonseq')) {
            // Tous les fichiers lisibles avant d'importer quoi que ce soit (sinon avertissement PHP brut
            // de fopen, import à moitié fait).
            foreach ($files as $file) {
                if (! is_file((string) $file) || ! is_readable((string) $file)) {
                    $this->error('Fichier illisible : '.$file);

                    return self::FAILURE;
                }
            }
            foreach ($files as $file) {
                [$kept, $ignored] = $this->importGeojsonSeq((string) $file, $startedAt);
                $this->line('→ '.basename((string) $file)." : {$kept} ways importés, {$ignored} ignorés");
            }
            $this->report($startedAt);
            $this->line('Import par fichiers : --prune non applicable.');

            return self::SUCCESS;
        }

        foreach ($regions as $i => $region) {
            $this->line("→ {$region}");
            try {
                [$payload, $fromCache] = $this->payload($region);
            } catch (\RuntimeException $e) {
                $this->error("  {$region} : {$e->getMessage()}");
                $failed[] = $region;

                continue;
            }
            [$kept, $ignored] = $this->persist($payload['elements'] ?? [], $startedAt);
            $this->line("  {$kept} ways importés, {$ignored} ignorés (pas une voie ou < 2 nœuds)".($fromCache ? ' [copie locale du jour]' : ''));
            $done[] = $region;
            unset($payload);

            if (! $fromCache && $i < count($regions) - 1) {
                Sleep::for(max(0, (int) $this->option('pause')))->seconds();
            }
        }

        $this->report($startedAt);

        $complete = ! $failed && ! array_diff(self::METRO_REGIONS, $done);
        $stale = DB::table('source_traces')->where('source', 'osm')->where('seen_at', '<', $startedAt);
        $staleCount = $stale->count();
        if ($staleCount && $this->option('prune') && $complete) {
            $stale->delete();
            $this->warn("{$staleCount} ways osm absents d'Overpass supprimés.");
        } elseif ($staleCount) {
            $this->line("{$staleCount} ways osm non revus lors de cet import".($this->option('prune') ? ' (--prune ignoré : import partiel).' : '.'));
        }

        if ($failed) {
            $this->error('Régions en échec (relancer : '.implode(' ', array_map(fn ($r) => "--region={$r}", $failed)).')');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    public static function query(string $region): string
    {
        // Département (FR-29, FR-2A) ou région : même sélecteur, la valeur ISO suffit.
        $railway = self::RAILWAY_VALUES;
        $prefixes = self::LIFECYCLE_PREFIXES;
        $tracks = self::LIFECYCLE_TRACK_VALUES;

        return <<<OVERPASS
            [out:json][timeout:600][maxsize:1073741824];
            area["ISO3166-2"="{$region}"]["boundary"="administrative"]->.r;
            (
              way["railway"~"^({$railway})$"](area.r);
              way[~"^({$prefixes}):railway$"~"^({$tracks})$"](area.r);
              way["historic"="railway"](area.r);
            );
            out tags geom qt;
            OVERPASS;
    }

    /**
     * Charge la copie du jour si elle existe, sinon interroge Overpass et la sauve.
     *
     * @return array{0: array<string, mixed>, 1: bool}
     */
    private function payload(string $region): array
    {
        $path = 'sources/osm/railways-'.$region.'-'.now()->format('Y-m-d').'.json';
        $disk = Storage::disk('local');
        if (! $this->option('refresh') && $disk->exists($path)) {
            return [json_decode($disk->get($path), true, flags: JSON_THROW_ON_ERROR), true];
        }

        $body = $this->fetch(self::query($region));
        $disk->put($path, $body);

        return [json_decode($body, true, flags: JSON_THROW_ON_ERROR), false];
    }

    private function fetch(string $query): string
    {
        $maxAttempts = max(1, (int) $this->option('attempts'));
        $endpoints = $this->option('endpoint') ?: self::DEFAULT_ENDPOINTS;
        $ua = (string) config('services.osm.user_agent', 'VFF-Bot/1.0 (+https://vff-project.org)');
        $lastError = '';

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $endpoint = $endpoints[$this->endpointIndex % count($endpoints)];
            try {
                $response = Http::withHeaders(['User-Agent' => $ua])
                    ->timeout(660)
                    ->asForm()
                    ->post($endpoint, ['data' => $query]);
                $body = $response->body();
                if ($response->ok() && str_starts_with(ltrim($body), '{')) {
                    // Overpass signale les dépassements (timeout, mémoire) en HTTP 200
                    // avec une remarque et un résultat TRONQUÉ : à rejeter.
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
                $wait = min(600, 30 * 2 ** ($attempt - 1));
                $this->warn("  tentative {$attempt} échouée ({$lastError}) : nouvel essai dans {$wait}s");
                Sleep::for($wait)->seconds(); // simulable (Sleep::fake) dans les tests
            }
        }

        throw new \RuntimeException("Overpass a échoué après {$maxAttempts} tentatives : {$lastError}");
    }

    /**
     * Éléments Overpass (`out tags geom`) → lignes source_traces.
     *
     * @param  array<int, array<string, mixed>>  $elements
     * @return array{0: int, 1: int}
     */
    private function persist(array $elements, \DateTimeInterface $seenAt): array
    {
        $rows = [];
        $ignored = 0;
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
            $row = self::row((string) $el['id'], $el['tags'] ?? [], $coords);
            if ($row) {
                $rows[] = $row;
            } else {
                $ignored++;
            }
        }
        $this->upsert($rows, $seenAt);

        return [count($rows), $ignored];
    }

    /**
     * Repli quand Overpass est saturé : extraits Geofabrik filtrés par osmium
     * (conteneur), mêmes tags que la requête Overpass, géométrie complète :
     *
     *   osmium tags-filter X.osm.pbf w/railway=rail,narrow_gauge,light_rail,funicular,abandoned,disused,razed,dismantled,construction,preserved \
     *     w/abandoned:railway w/disused:railway w/razed:railway w/removed:railway w/dismantled:railway \
     *     w/demolished:railway w/was:railway w/construction:railway w/preserved:railway w/historic=railway -o X-rail.pbf
     *   osmium export X-rail.pbf -f geojsonseq --geometry-types=linestring -a type,id \
     *     -x print_record_separator=false -o X-rail.geojsonseq
     *
     * Le filtrage fin (valeurs de voie derrière un préfixe de cycle de vie) est
     * celui de layer(), identique au chemin Overpass.
     *
     * @return array{0: int, 1: int}
     */
    private function importGeojsonSeq(string $file, \DateTimeInterface $seenAt): array
    {
        $fh = fopen($file, 'r');
        if (! $fh) {
            throw new \RuntimeException("Fichier illisible : {$file}");
        }
        $rows = [];
        $kept = 0;
        $ignored = 0;
        while (($line = fgets($fh)) !== false) {
            $line = trim($line, "\x1e \n\r\t");
            if ($line === '') {
                continue;
            }
            $f = json_decode($line, true);
            $props = $f['properties'] ?? [];
            if (($props['@type'] ?? 'way') !== 'way' || ($f['geometry']['type'] ?? null) !== 'LineString') {
                $ignored++;

                continue;
            }
            $id = (string) ($props['@id'] ?? '');
            unset($props['@id'], $props['@type']);
            $row = $id !== '' ? self::row($id, $props, $f['geometry']['coordinates']) : null;
            if (! $row) {
                $ignored++;

                continue;
            }
            $rows[] = $row;
            $kept++;
            if (count($rows) >= 2000) {
                $this->upsert($rows, $seenAt);
                $rows = [];
            }
        }
        fclose($fh);
        $this->upsert($rows, $seenAt);

        return [$kept, $ignored];
    }

    /**
     * @param  array<string, mixed>  $tags
     * @param  array<int, array{0: float, 1: float}>  $coords
     * @return array<string, mixed>|null
     */
    private static function row(string $id, array $tags, array $coords): ?array
    {
        $layer = self::layer($tags);
        if (! $layer || count($coords) < 2) {
            return null;
        }

        return [
            'ref' => $id,
            'layer' => $layer,
            'name' => $tags['name'] ?? $tags['old_name'] ?? null,
            'tags' => $tags ?: new \stdClass,
            'g' => ['type' => 'LineString', 'coordinates' => $coords],
        ];
    }

    /**
     * Upsert par paquets de 2 000 ways (un seul INSERT … SELECT par paquet).
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function upsert(array $rows, \DateTimeInterface $seenAt): void
    {
        foreach (array_chunk($rows, 2000) as $chunk) {
            DB::statement(<<<'SQL'
                INSERT INTO source_traces (source, source_ref, layer, name, properties, geom, length_m, seen_at, created_at, updated_at)
                SELECT 'osm', r->>'ref', LEFT(r->>'layer', 40), r->>'name', r->'tags', g,
                       ROUND(ST_Length(g::geography))::int, :seen, NOW(), NOW()
                FROM jsonb_array_elements(CAST(:rows AS jsonb)) r
                CROSS JOIN LATERAL (SELECT ST_Multi(ST_SetSRID(ST_GeomFromGeoJSON(r->>'g'), 4326)) AS g) t
                ON CONFLICT (source, source_ref) DO UPDATE
                    SET layer = EXCLUDED.layer, name = EXCLUDED.name, properties = EXCLUDED.properties,
                        geom = EXCLUDED.geom, length_m = EXCLUDED.length_m,
                        seen_at = EXCLUDED.seen_at, updated_at = NOW()
                SQL, [
                'rows' => json_encode($chunk, JSON_UNESCAPED_UNICODE),
                'seen' => $seenAt,
            ]);
        }
    }

    /** Calque : valeur de railway si c'est une voie, sinon préfixe de cycle de vie, sinon historic. */
    public static function layer(array $tags): ?string
    {
        $railway = $tags['railway'] ?? null;
        if ($railway && preg_match('/^('.self::RAILWAY_VALUES.')$/', $railway)) {
            return $railway;
        }
        foreach (explode('|', self::LIFECYCLE_PREFIXES) as $prefix) {
            $v = $tags["{$prefix}:railway"] ?? null;
            if ($v && preg_match('/^('.self::LIFECYCLE_TRACK_VALUES.')$/', $v)) {
                return "{$prefix}:railway";
            }
        }

        return ($tags['historic'] ?? null) === 'railway' ? 'historic' : null;
    }

    private function report(\DateTimeInterface $startedAt): void
    {
        $rows = DB::select(<<<'SQL'
            SELECT layer, COUNT(*) AS n, ROUND(SUM(length_m) / 1000.0) AS km,
                   COUNT(*) FILTER (WHERE jsonb_exists(properties, 'service')) AS service
            FROM source_traces
            WHERE source = 'osm' AND seen_at >= ?
            GROUP BY 1 ORDER BY 2 DESC
            SQL, [$startedAt]);
        $this->table(['Calque', 'Ways', 'km', 'dont service=*'],
            array_map(fn ($r) => [$r->layer, $r->n, $r->km, $r->service], $rows));
    }
}
