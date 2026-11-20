<?php

namespace App\Console\Commands;

use App\Services\GeoJsonXDecoder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Importe les tracés de la carte générale d'archeoferroviaire dans `source_traces`.
 *
 * La carte générale du site (spip.php?rubrique170) est une carte IGN « Ma carte » :
 * tous les tracés tiennent en UNE requête (~10 Mo), bien plus léger que de
 * parcourir les ~1 350 articles (pages de ~730 Ko chacune, serveur free.fr saturé).
 *
 * Droits : usage non commercial, crédit nominatif et lien vers l'article (cf.
 * database/data/source_rights.php). Cette commande ne garde ni textes, ni dates, ni photos.
 *
 * Idempotent : un tracé est identifié par l'empreinte (calque, nom, géométrie).
 * Un tracé modifié chez archeo devient une nouvelle ligne ; les tracés qui ne
 * sont plus servis sont signalés (et supprimés avec --prune).
 *
 *   php artisan vff:import-archeo-map
 *   php artisan vff:import-archeo-map --file=storage/app/sources/archeoferroviaire/macarte-2026-10-15.json
 */
class ImportArcheoMap extends Command
{
    protected $signature = 'vff:import-archeo-map
        {--file= : fichier JSON déjà téléchargé (sinon téléchargement)}
        {--prune : supprime les tracés que la carte ne contient plus}';

    protected $description = 'Importe les tracés de la carte générale archeoferroviaire (IGN Ma carte) dans source_traces.';

    /** Propriétés conservées d'un tracé (ni textes, ni dates, ni photos). */
    private const KEPT_PROPERTIES = ['name', 'number'];

    private const MAP_URL = 'https://macarte.ign.fr/api/maps/024b0874d035fce0cf59f97ae2581257/file';

    /** Calques de la carte → catégorie VFF (lines.network) ou 'exploitee' (contexte). */
    private const LAYERS = [
        'Réseau principal' => 'principal',
        'Réseaux secondaires' => 'secondaire',
        'Réseaux militaires' => 'militaire',
        'Réseaux particuliers' => 'particulier',
        'Lignes exploitées' => 'exploitee',
    ];

    public function handle(GeoJsonXDecoder $decoder): int
    {
        $raw = $this->option('file') ? file_get_contents($this->option('file')) : $this->download();
        $map = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        $startedAt = now();

        $stats = [];
        $skipped = 0;
        foreach ($map['layers'] ?? [] as $layer) {
            $category = self::LAYERS[$layer['name'] ?? ''] ?? null;
            if (($layer['type'] ?? null) !== 'Vector' || ! $category || ! is_array($layer['data'] ?? null)) {
                continue;
            }

            $count = 0;
            foreach ($decoder->decodeCollection($layer['data']) as $feature) {
                $lines = $this->lineStrings($feature['geometry']);
                if (! $lines) {
                    $skipped++;

                    continue;
                }
                $this->upsert($category, $feature['properties'], $lines, $startedAt);
                $count++;
            }
            $stats[] = [$layer['name'], $category, $count];
        }

        $this->table(['Calque', 'Catégorie', 'Tracés'], $stats);
        $this->line("Géométries dégénérées ignorées : {$skipped}");

        $stale = DB::table('source_traces')->where('source', 'archeoferroviaire')->where('seen_at', '<', $startedAt);
        $staleCount = $stale->count();
        if ($staleCount && $this->option('prune')) {
            $stale->delete();
            $this->warn("{$staleCount} tracés absents de la carte supprimés.");
        } elseif ($staleCount) {
            $this->warn("{$staleCount} tracés absents de la carte (relancer avec --prune pour les supprimer).");
        }

        $summary = DB::selectOne("SELECT COUNT(*) n, ROUND(SUM(length_m) / 1000.0) km FROM source_traces WHERE source = 'archeoferroviaire'");
        $this->info("✓ {$summary->n} tracés archeoferroviaire en base ({$summary->km} km).");

        return self::SUCCESS;
    }

    private function download(): string
    {
        $this->line('→ Téléchargement de la carte générale archeoferroviaire…');
        $response = Http::withHeaders(['User-Agent' => (string) config('services.osm.user_agent', 'VFF-Bot/1.0 (+https://vff-project.org)')])
            ->timeout(300)
            ->get(self::MAP_URL)
            ->throw();

        // Copie brute datée : permet de rejouer l'import sans re-télécharger.
        $path = 'sources/archeoferroviaire/macarte-'.now()->format('Y-m-d').'.json';
        Storage::disk('local')->put($path, $response->body());
        $this->line('  copie brute : '.Storage::disk('local')->path($path));

        return $response->body();
    }

    /**
     * Extrait les LineString valides (≥ 2 points) d'une géométrie LineString ou
     * MultiLineString, convertis en WGS84.
     *
     * @return array<int, array<int, array{0: float, 1: float}>>
     */
    private function lineStrings(array $geometry): array
    {
        $parts = match ($geometry['type']) {
            'LineString' => [$geometry['coordinates']],
            'MultiLineString' => $geometry['coordinates'],
            default => [],
        };

        $out = [];
        foreach ($parts as $part) {
            // Un « LineString » d'un seul point est décodé comme une paire de nombres.
            if (! is_array($part) || ! is_array($part[0] ?? null) || count($part) < 2) {
                continue;
            }
            $out[] = GeoJsonXDecoder::toWgs84($part);
        }

        return $out;
    }

    private function upsert(string $category, array $properties, array $lines, \DateTimeInterface $seenAt): void
    {
        $name = isset($properties['name']) ? trim((string) $properties['name']) : null;
        $geojson = json_encode(['type' => 'MultiLineString', 'coordinates' => $lines]);
        $ref = md5($category.'|'.$name.'|'.$geojson);

        DB::statement(<<<'SQL'
            INSERT INTO source_traces (source, source_ref, layer, name, properties, geom, length_m, seen_at, created_at, updated_at)
            SELECT 'archeoferroviaire', :ref, :layer, :name, CAST(:props AS jsonb), g,
                   ROUND(ST_Length(g::geography))::int, :seen, NOW(), NOW()
            FROM (SELECT ST_Multi(ST_SetSRID(ST_GeomFromGeoJSON(:geojson), 4326)) AS g) t
            ON CONFLICT (source, source_ref) DO UPDATE
                SET seen_at = EXCLUDED.seen_at, properties = EXCLUDED.properties, updated_at = NOW()
            SQL, [
            'ref' => $ref,
            'layer' => $category,
            'name' => $name ?: null,
            // Cette commande ne garde que le tracé et son nom : les textes viennent des articles.
            'props' => json_encode(array_intersect_key($properties, array_flip(self::KEPT_PROPERTIES)) ?: new \stdClass, JSON_UNESCAPED_UNICODE),
            'seen' => $seenAt,
            'geojson' => $geojson,
        ]);
    }
}
