<?php

namespace App\Services;

use App\Enums\SegmentStatus;
use App\Models\Era;
use App\Models\Line;
use App\Models\LineSegment;
use Clickbar\Magellan\Data\Geometries\LineString;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Importeur du réseau ferré français depuis l'API Overpass d'OpenStreetMap.
 *
 * Cette classe est SQUELETTE en Phase 0 — le mapping OSM → modèle métier
 * sera affiné par itération (statuts railway=*, gestion des tronçons,
 * dédoublonnage, etc.). L'objectif est d'avoir un point d'entrée unique
 * `php artisan vff:import-osm`.
 *
 * Mapping initial des tags OSM `railway=*` vers nos statuts métier :
 *   - rail        → SegmentStatus::InService
 *   - construction → SegmentStatus::InService (on traite comme actif)
 *   - abandoned   → SegmentStatus::Abandoned
 *   - disused     → SegmentStatus::Abandoned
 *   - razed       → SegmentStatus::Razed
 *   - dismantled  → SegmentStatus::Dismantled
 */
class OsmImporter
{
    public function __construct(
        protected HttpFactory $http,
        protected string $endpoint = '',
        protected string $userAgent = '',
    ) {
        $this->endpoint = $this->endpoint ?: (string) config('services.osm.overpass_endpoint', 'https://overpass-api.de/api/interpreter');
        $this->userAgent = $this->userAgent ?: (string) config('services.osm.user_agent', 'VFF-Bot/1.0 (+https://vff-project.org)');
    }

    /**
     * Construit une requête Overpass QL pour récupérer le réseau ferré
     * d'une région française (ISO 3166-2 : FR, FR-IDF, FR-BRE, …).
     */
    public function buildQuery(string $regionIsoCode = 'FR'): string
    {
        return <<<OVERPASS
            [out:json][timeout:300];
            area["ISO3166-1"="FR"][admin_level=2]->.fr;
            (
              way["railway"~"^(rail|abandoned|disused|razed|dismantled|construction)$"](area.fr);
            );
            out geom;
        OVERPASS;
    }

    /**
     * Lance la requête Overpass et retourne le payload JSON décodé.
     *
     * @return array{elements: array<int, array<string, mixed>>}
     */
    public function fetch(string $regionIsoCode = 'FR'): array
    {
        $query = $this->buildQuery($regionIsoCode);

        Log::info('[OsmImporter] Overpass request', ['region' => $regionIsoCode, 'length' => strlen($query)]);

        $response = $this->http
            ->withHeaders(['User-Agent' => $this->userAgent])
            ->timeout(360)
            ->asForm()
            ->post($this->endpoint, ['data' => $query]);

        if (! $response->ok()) {
            throw new \RuntimeException("Overpass returned HTTP {$response->status()}");
        }

        /** @var array{elements: array<int, array<string, mixed>>} $payload */
        $payload = $response->json();

        return $payload;
    }

    /**
     * Persiste les segments dans la base. Lie chaque way OSM à une `Line`
     * (créée à la volée par nom/ref) et à un `LineSegment` pour l'ère
     * courante. Idempotent par OSM way id (stocké dans alt_names.osm_id).
     *
     * @param  array<int, array<string, mixed>>  $elements
     */
    public function persist(array $elements, ?Era $era = null): int
    {
        $era ??= Era::where('is_current', true)->firstOrFail();

        $created = 0;

        foreach ($elements as $element) {
            if (($element['type'] ?? null) !== 'way' || empty($element['geometry'])) {
                continue;
            }

            $tags = $element['tags'] ?? [];
            $name = $tags['name'] ?? ($tags['ref'] ?? "OSM way #{$element['id']}");
            $slug = Str::slug($name).'-'.$element['id'];

            $line = Line::firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'code' => $tags['ref'] ?? null,
                    'alt_names' => ['osm_id' => $element['id']],
                ],
            );

            $points = array_map(
                fn (array $node) => Point::makeGeodetic($node['lat'], $node['lon']),
                $element['geometry'],
            );

            // Au moins 2 points pour un LINESTRING valide
            if (count($points) < 2) {
                continue;
            }

            $line->segments()->create([
                'era_id' => $era->id,
                'geom' => LineString::make($points),
                'status' => $this->mapStatus($tags['railway'] ?? 'rail')->value,
                'electrified' => isset($tags['electrified']) && $tags['electrified'] !== 'no',
                'voltage_v' => isset($tags['voltage']) ? (int) $tags['voltage'] : null,
                'max_speed_kmh' => isset($tags['maxspeed']) ? (int) $tags['maxspeed'] : null,
                'gauge_mm' => isset($tags['gauge']) ? (int) $tags['gauge'] : 1435,
            ]);

            $created++;
        }

        return $created;
    }

    protected function mapStatus(string $osmRailway): SegmentStatus
    {
        return match ($osmRailway) {
            'rail', 'construction' => SegmentStatus::InService,
            'abandoned', 'disused' => SegmentStatus::Abandoned,
            'razed' => SegmentStatus::Razed,
            'dismantled' => SegmentStatus::Dismantled,
            default => SegmentStatus::InService,
        };
    }
}
