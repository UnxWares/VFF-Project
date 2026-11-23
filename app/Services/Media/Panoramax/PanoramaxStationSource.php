<?php

namespace App\Services\Media\Panoramax;

use App\Enums\MediaType;
use App\Models\Media;
use App\Models\Station;
use App\Services\Media\Sources\MediaRecord;
use App\Services\Media\Sources\MediaSource;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Photos de rue Panoramax autour de chaque gare (règles : PanoramaxSelection), une requête par gare au catalogue
 * fédéré (PanoramaxClient, cache disque). Avec un PanoramaxSceneBuilder (cas normal), la règle des abords : la photo
 * doit voir un bâtiment ou un quai de la gare (BD TOPO, SNCF) ; sans lui, la règle ancienne (60 m, 15°). Les fichiers restent chez Panoramax : vignette `thumb.jpg` (500 px) et
 * image `sd.jpg` (2 048 px) en lien direct, page de la photo sur son instance.
 *
 * Retour arrière : `DELETE FROM media WHERE provider = 'panoramax'`.
 */
final class PanoramaxStationSource implements MediaSource
{
    public const PROVIDER = 'panoramax';

    /** @var array<string, int> motif => nombre de photos écartées (dernier passage) */
    public array $rejected = [];

    /** Gares lues (dernier passage), dont celles absentes du cache en mode `cacheOnly`. */
    public int $stationsRead = 0;

    public int $stationsSkipped = 0;

    public int $stationsFailed = 0;

    /** Gares sans bâtiment de gare connu (règle des abords) ou rasées : aucune photo possible. */
    public int $stationsWithoutScene = 0;

    /** @var list<array<string, mixed>> photos retenues (dernier passage), pour la relecture à la main */
    public array $accepted = [];

    /**
     * @param  ?list<int>  $stationIds  gares à lire (toutes si null)
     * @param  ?PanoramaxSceneBuilder  $scenes  abords des gares (règle des abords), null pour la règle ancienne
     * @param  ?list<string>  $instances  instances gardées (toutes celles de PanoramaxSelection::INSTANCES si null)
     * @param  ?PanoramaxVision  $vision  tri par vision (note de chaque vignette), combiné à la règle des abords si
     *                                    `$scenes` est donné
     */
    public function __construct(
        private readonly PanoramaxClient $client,
        private readonly ?array $stationIds = null,
        private readonly bool $cacheOnly = false,
        private readonly ?PanoramaxSceneBuilder $scenes = null,
        private readonly ?array $instances = null,
        private readonly ?PanoramaxVision $vision = null,
    ) {}

    public function provider(): string
    {
        return self::PROVIDER;
    }

    /** @return iterable<MediaRecord> */
    public function records(?int $limit = null): iterable
    {
        $this->rejected = [];
        $this->accepted = [];
        $this->stationsRead = $this->stationsSkipped = $this->stationsFailed = $this->stationsWithoutScene = 0;

        $q = DB::table('stations')->selectRaw('id, slug, name, lifecycle, ST_Y(geom) AS lat, ST_X(geom) AS lng')->orderBy('id');
        if ($this->stationIds !== null) {
            $q->whereIn('id', $this->stationIds);
        }
        if ($limit !== null) {
            $q->limit($limit);
        }

        foreach ($q->get() as $s) {
            $lat = (float) $s->lat;
            $lng = (float) $s->lng;
            $dLat = PanoramaxSelection::SEARCH_RADIUS_M / 111_320;
            $dLng = PanoramaxSelection::SEARCH_RADIUS_M / (111_320 * max(0.2, cos(deg2rad($lat))));
            try {
                $features = $this->client->search($lng - $dLng, $lat - $dLat, $lng + $dLng, $lat + $dLat, $this->cacheOnly);
            } catch (RuntimeException) {
                $this->stationsFailed++;

                continue;
            }
            if ($features === null) {
                $this->stationsSkipped++;

                continue;
            }
            if ($this->instances !== null) {
                $features = array_values(array_filter($features, fn (array $f): bool => in_array(PanoramaxSelection::instance($f), $this->instances, true)));
            }
            if ($this->vision !== null) {
                $this->stationsRead++;
                foreach ($this->visionPick($features, $s, $lat, $lng) as $f) {
                    yield $this->accept($f, $s, $lat, $lng);
                }

                continue;
            }
            $scene = null;
            // Abords lus seulement si une photo peut servir (instance, licence, crédit) : une requête à l'IGN de moins.
            if ($this->scenes !== null && array_filter($features, fn (array $f): bool => PanoramaxSelection::basicReason($f) === null) !== []) {
                try {
                    $scene = $this->scenes->scene($lat, $lng, $this->cacheOnly);
                } catch (RuntimeException) {
                    $this->stationsFailed++;

                    continue;
                }
                if ($scene === null) {
                    $this->stationsSkipped++;

                    continue;
                }
                // Bâtiment rasé : les photos du lieu ne montrent plus la gare.
                if (($s->lifecycle ?? null) === 'razed') {
                    $scene = new StationScene($lat, $lng);
                }
                if ($scene->isEmpty()) {
                    $this->stationsWithoutScene++;
                }
            }
            $this->stationsRead++;
            if ($this->scenes !== null && $scene === null) {
                continue;
            }
            foreach (PanoramaxSelection::pick($features, $lat, $lng, $this->rejected, $scene) as $f) {
                yield $this->accept($f, $s, $lat, $lng);
            }
        }
        $this->vision?->flushUnscored();
    }

    /**
     * Tri par vision d'une gare : candidates (premier filtre géométrique), vignettes téléchargées si besoin, notes
     * relues ; la règle des abords, si les abords sont connus, abaisse le seuil. Une gare rasée ne reçoit rien.
     *
     * @param  list<array<string, mixed>>  $features
     * @return list<array<string, mixed>>
     */
    private function visionPick(array $features, object $s, float $lat, float $lng): array
    {
        $vision = $this->vision;
        if ($vision === null || ($s->lifecycle ?? null) === 'razed') {
            return [];
        }
        $candidates = PanoramaxSelection::visionCandidates($features, $lat, $lng, $this->rejected);
        if ($candidates === []) {
            return [];
        }
        foreach ($candidates as $f) {
            $vision->fetch($f, $this->cacheOnly);
            if ($vision->score((string) $f['id']) === null) {
                $vision->unscored[] = (string) $f['id'];
            }
        }
        $scene = null;
        if ($this->scenes !== null) {
            try {
                $scene = $this->scenes->scene($lat, $lng, $this->cacheOnly);
            } catch (RuntimeException) {
                $scene = null;
            }
        }

        return PanoramaxSelection::pickVision($candidates, $lat, $lng, $vision->score(...), $this->rejected, $scene);
    }

    /** @param  array<string, mixed>  $f */
    private function accept(array $f, object $s, float $lat, float $lng): MediaRecord
    {
        $record = $this->record($f, (int) $s->id, $lat, $lng);
        $this->accepted[] = ['station' => $s->name, 'slug' => $s->slug, 'id' => $record->externalId,
            'distance_m' => $record->meta['distance_m'], 'angle' => $record->meta['angle_to_station'],
            'license' => $record->license, 'date' => $record->capturedAt, 'thumb' => $record->thumbUrl, 'page' => $record->sourceUrl,
            'instance' => $record->meta['instance'], 'station_seen_deg' => $record->meta['station_seen_deg'] ?? null,
            'platform_seen_deg' => $record->meta['platform_seen_deg'] ?? null, 'station_building_m' => $record->meta['station_building_m'] ?? null,
            'vision_score' => $record->meta['vision_score'] ?? null];

        return $record;
    }

    /** @param  array<string, mixed>  $f */
    private function record(array $f, int $stationId, float $stationLat, float $stationLng): MediaRecord
    {
        $p = (array) $f['properties'];
        [$lng, $lat] = PanoramaxSelection::position($f) ?? [null, null];
        $instance = (string) PanoramaxSelection::instance($f);
        [$instanceLabel, $instanceUrl] = PanoramaxSelection::INSTANCES[$instance];
        [$licenseLabel, $licenseUrl, $shareAlike] = PanoramaxSelection::LICENSES[(string) $p['license']];
        $producer = PanoramaxSelection::producer($f);
        $date = isset($p['datetime']) ? substr((string) $p['datetime'], 0, 10) : null;
        $fov = (int) ($p['pers:interior_orientation']['field_of_view'] ?? 0);

        return new MediaRecord(
            provider: self::PROVIDER,
            externalId: (string) $f['id'],
            mediableType: Station::class,
            mediableId: $stationId,
            rightsStatus: Media::RIGHTS_OPEN_LICENSE,
            license: $licenseLabel,
            licenseUrl: $licenseUrl,
            commercialOk: true,
            derivativesOk: true,
            shareAlike: $shareAlike,
            attribution: "Photo : {$producer}, Panoramax ({$instanceLabel}), {$licenseLabel}",
            author: $producer,
            sourceUrl: $instanceUrl.'/#focus=pic&pic='.rawurlencode((string) $f['id']),
            thumbUrl: PanoramaxSelection::thumb($f),
            displayUrl: PanoramaxSelection::display($f),
            type: MediaType::Photo,
            title: null,
            width: $fov > 0 && $fov < 360 ? (int) ($p['pers:interior_orientation']['sensor_array_dimensions'][0] ?? 0) ?: null : null,
            capturedAt: $date !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 ? $date : null,
            datePrecision: $date !== null ? 'day' : null,
            depictedYear: $date !== null ? (int) substr($date, 0, 4) : null,
            lat: $lat,
            lng: $lng,
            meta: [
                'via' => 'panoramax_station',
                'instance' => $instance,
                'sequence' => $f['collection'] ?? null,
                'license_id' => $p['license'],
                'azimuth' => $p['view:azimuth'] ?? null,
                'field_of_view' => $fov ?: null,
                'distance_m' => (int) round(PanoramaxSelection::distanceM((float) $lat, (float) $lng, $stationLat, $stationLng)),
                'angle_to_station' => (int) round(PanoramaxSelection::angle($f, $stationLat, $stationLng)),
                'date_precision' => $date !== null ? 'day' : null,
                ...(is_array($f['vff:view'] ?? null) ? [
                    'rule' => 'station_scene',
                    'station_seen_deg' => (int) round((float) $f['vff:view']['target_deg']),
                    'platform_seen_deg' => (int) round((float) $f['vff:view']['platform_deg']),
                    'station_building_m' => $f['vff:view']['nearest_m'] !== null ? (int) round((float) $f['vff:view']['nearest_m']) : null,
                ] : []),
                ...(is_array($f['vff:vision'] ?? null) ? [
                    'rule' => ($f['vff:vision']['scene'] ?? false) ? 'vision_scene' : 'vision',
                    'vision_score' => round((float) $f['vff:vision']['score'], 3),
                ] : []),
            ],
        );
    }
}
