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
 * fédéré (PanoramaxClient, cache disque). Les fichiers restent chez Panoramax : vignette `thumb.jpg` (500 px) et
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

    /** @var list<array<string, mixed>> photos retenues (dernier passage), pour la relecture à la main */
    public array $accepted = [];

    /**
     * @param  ?list<int>  $stationIds  gares à lire (toutes si null)
     */
    public function __construct(
        private readonly PanoramaxClient $client,
        private readonly ?array $stationIds = null,
        private readonly bool $cacheOnly = false,
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
        $this->stationsRead = $this->stationsSkipped = $this->stationsFailed = 0;

        $q = DB::table('stations')->selectRaw('id, slug, name, ST_Y(geom) AS lat, ST_X(geom) AS lng')->orderBy('id');
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
            $this->stationsRead++;
            foreach (PanoramaxSelection::pick($features, $lat, $lng, $this->rejected) as $f) {
                $record = $this->record($f, (int) $s->id, $lat, $lng);
                $this->accepted[] = ['station' => $s->name, 'slug' => $s->slug, 'id' => $record->externalId,
                    'distance_m' => $record->meta['distance_m'], 'angle' => $record->meta['angle_to_station'],
                    'license' => $record->license, 'date' => $record->capturedAt, 'thumb' => $record->thumbUrl, 'page' => $record->sourceUrl];

                yield $record;
            }
        }
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
            ],
        );
    }
}
