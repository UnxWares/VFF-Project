<?php

namespace App\Services\Media\Sources;

use App\Enums\MediaType;
use App\Models\Line;
use App\Models\Media;
use App\Models\Station;
use Illuminate\Support\Facades\DB;

/**
 * Rattachement aux gares, sans aucune requête réseau, des fichiers Commons déjà en base pour les lignes : chaque
 * fichier retenu reçoit une ligne `media` jumelle pour la gare (mêmes droits, même `external_id`), la ligne
 * d'origine restant attachée à la ligne. Règles : CommonsStationRules. Catégories Commons des gares (P373) et
 * images de référence (P18) lues dans le cache Wikidata de `vff:import-station-sources`
 * (`storage/app/sources/stations/wikidata/entities-*.json`), jamais sur le réseau.
 *
 * Retour arrière : `DELETE FROM media WHERE mediable_type = 'App\Models\Station' AND provider = 'commons'
 * AND provider_meta->>'via' LIKE 'station_%'` (aucune autre ligne n'est touchée).
 */
final class CommonsStationSource implements MediaSource
{
    public const VIA_P373 = 'station_p373';

    public const VIA_CATEGORY = 'station_category';

    public const VIA_TITLE = 'station_title';

    /** @var array<string, int> motif => nombre de rattachements écartés (dernier passage) */
    public array $rejected = [];

    /** @var list<array<string, mixed>> rattachements retenus du dernier passage (pour la relecture à la main) */
    public array $accepted = [];

    public function __construct(private readonly ?string $wikidataCacheDir = null) {}

    public function provider(): string
    {
        return Media::PROVIDER_COMMONS;
    }

    /**
     * @param  ?int  $limit  nombre de fichiers lus au plus (essais)
     * @return iterable<MediaRecord>
     */
    public function records(?int $limit = null, ?string $stationSlug = null): iterable
    {
        $this->rejected = [];
        $this->accepted = [];
        $stations = $this->stations($stationSlug);
        if ($stations === []) {
            return;
        }
        [$p373, $p18] = $this->wikidata($stations);
        $lineStations = $this->lineStations(array_keys($stations));

        $keyIndex = [];
        foreach ($stations as $id => $s) {
            foreach ($s['keys'] as $k) {
                $keyIndex[$k][] = $id;
            }
        }

        $files = DB::select(
            'SELECT external_id, min(id) AS rep, array_to_json(array_agg(DISTINCT mediable_id)) AS lines
             FROM media WHERE provider = ? AND mediable_type = ? GROUP BY external_id ORDER BY external_id'.($limit !== null ? ' LIMIT '.(int) $limit : ''),
            [Media::PROVIDER_COMMONS, Line::class],
        );

        foreach (array_chunk($files, 1000) as $chunk) {
            $reps = DB::table('media')->whereIn('id', array_map(fn (object $f): int => (int) $f->rep, $chunk))
                ->select('*')->selectRaw('ST_Y(location) AS lat, ST_X(location) AS lng')->get()->keyBy('id');
            foreach ($chunk as $f) {
                $row = $reps[(int) $f->rep] ?? null;
                if ($row === null) {
                    continue;
                }
                yield from $this->recordsForFile($row, json_decode((string) $f->lines, true) ?: [], $stations, $keyIndex, $lineStations, $p373, $p18);
            }
        }
    }

    /**
     * @param  list<int>  $lines
     * @param  array<int, array<string, mixed>>  $stations
     * @param  array<string, list<int>>  $keyIndex
     * @param  array<int, list<int>>  $lineStations
     * @param  array<string, list<int>>  $p373
     * @param  array<string, list<int>>  $p18
     * @return iterable<MediaRecord>
     */
    private function recordsForFile(object $row, array $lines, array $stations, array $keyIndex, array $lineStations, array $p373, array $p18): iterable
    {
        $meta = json_decode((string) $row->provider_meta, true) ?: [];
        $categories = array_values(array_filter((array) ($meta['categories'] ?? []), 'is_string'));
        $title = (string) ($meta['file_title'] ?? $row->title ?? '');
        $served = [];
        foreach ($lines as $lineId) {
            foreach ($lineStations[(int) $lineId] ?? [] as $sid) {
                $served[$sid] = true;
            }
        }
        $lat = $row->lat !== null ? (float) $row->lat : null;
        $lng = $row->lng !== null ? (float) $row->lng : null;

        /** @var array<int, array{method: string, match: string}> $cands */
        $cands = [];
        foreach ($categories as $c) {
            // Une catégorie P373 qui ne nomme pas une gare (ville, ligne, maison de garde-barrière) ne compte pas.
            if (! CommonsStationRules::namesAStation($c)) {
                continue;
            }
            foreach ($p373[CommonsStationRules::normalize($c)] ?? [] as $sid) {
                $cands[$sid] ??= ['method' => self::VIA_P373, 'match' => $c];
            }
        }
        foreach ($categories as $c) {
            foreach (CommonsStationRules::categoryKeys($c) as $k) {
                foreach ($keyIndex[$k] ?? [] as $sid) {
                    $cands[$sid] ??= ['method' => self::VIA_CATEGORY, 'match' => $c];
                }
            }
        }
        // Titre : seulement les gares des lignes du fichier ; quand deux gares y sont nommées (« gare de Valence TGV »
        // nomme aussi Valence), seule celle dont le nom est le plus long est gardée.
        $byTitle = [];
        foreach (array_keys($served) as $sid) {
            if (! isset($cands[$sid]) && isset($stations[$sid])) {
                $key = CommonsStationRules::titleKey($title, $stations[$sid]['keys']);
                if ($key === null) {
                    continue;
                }
                $longer = array_filter(CommonsStationRules::titleExtensions($title, $key), fn (string $k): bool => isset($keyIndex[$k]));
                if ($longer !== []) {
                    $this->reject('longer_station_name');

                    continue;
                }
                $byTitle[$sid] = $key;
            }
        }
        foreach ($byTitle as $sid => $key) {
            foreach ($byTitle as $other => $otherKey) {
                if ($other !== $sid && mb_strlen($otherKey) > mb_strlen($key) && str_contains($otherKey, $key)) {
                    $this->reject('shorter_name');

                    continue 2;
                }
            }
            $cands[$sid] = ['method' => self::VIA_TITLE, 'match' => $title];
        }
        if ($cands === []) {
            return;
        }

        $kept = [];
        foreach ($cands as $sid => $c) {
            $s = $stations[$sid];
            $distance = $lat !== null && $lng !== null ? self::distanceM($lat, $lng, $s['lat'], $s['lng']) : null;
            $corroborated = isset($served[$sid]) || ($distance !== null && $distance <= CommonsStationRules::MAX_DISTANCE_M);
            $reason = CommonsStationRules::rejectReason($c['method'], $distance, $corroborated,
                $row->depicted_year !== null ? (int) $row->depicted_year : null, $s['opened'], $title.' '.implode(' ', $categories));
            if ($reason !== null) {
                $this->reject($reason);

                continue;
            }
            $kept[$sid] = [...$c, 'distance' => $distance, 'served' => isset($served[$sid])];
        }
        // Homonymes : un même nom retenu pour plusieurs gares sans catégorie P373 ni position qui tranche.
        $byName = array_filter($kept, fn (array $c): bool => $c['method'] !== self::VIA_P373 && $c['distance'] === null);
        if (count($byName) > 1) {
            foreach (array_keys($byName) as $sid) {
                unset($kept[$sid]);
                $this->reject('ambiguous');
            }
        }
        if (count($kept) > CommonsStationRules::MAX_STATIONS_PER_FILE) {
            $this->reject('too_many_stations');

            return;
        }

        $fileKey = CommonsStationRules::normalize(preg_replace('/^File:/i', '', $title) ?? '');
        foreach ($kept as $sid => $c) {
            $featured = in_array($sid, $p18[$fileKey] ?? [], true);
            $this->accepted[] = ['station_id' => $sid, 'station' => $stations[$sid]['name'], 'slug' => $stations[$sid]['slug'],
                'file' => $title, 'via' => $c['method'], 'match' => $c['match'], 'distance_m' => $c['distance'] !== null ? (int) round($c['distance']) : null,
                'served' => $c['served'], 'year' => $row->depicted_year, 'source_url' => $row->source_url, 'thumb_url' => $row->thumb_url];

            yield $this->record($row, $meta, $sid, $c, $featured);
        }
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array{method: string, match: string, distance: ?float, served: bool}  $c
     */
    private function record(object $row, array $meta, int $stationId, array $c, bool $featured): MediaRecord
    {
        $type = MediaType::tryFrom((string) $row->type) ?? MediaType::Photo;
        $keep = array_intersect_key($meta, array_flip(['file_title', 'categories', 'credit', 'attribution_required', 'date_raw', 'year_to', 'qid']));

        return new MediaRecord(
            provider: Media::PROVIDER_COMMONS,
            externalId: (string) $row->external_id,
            mediableType: Station::class,
            mediableId: $stationId,
            rightsStatus: (string) $row->rights_status,
            license: $row->license,
            licenseUrl: $row->license_url,
            commercialOk: $row->commercial_ok === null ? null : (bool) $row->commercial_ok,
            // Commons n'accepte que des licences qui permettent la modification.
            derivativesOk: true,
            shareAlike: $row->share_alike === null ? null : (bool) $row->share_alike,
            attribution: (string) ($row->attribution ?? ''),
            author: $row->author,
            sourceUrl: (string) $row->source_url,
            thumbUrl: $row->thumb_url,
            displayUrl: $row->display_url,
            type: $type,
            title: $row->title,
            caption: $row->caption,
            width: $row->width !== null ? (int) $row->width : null,
            height: $row->height !== null ? (int) $row->height : null,
            capturedAt: $row->captured_at !== null ? substr((string) $row->captured_at, 0, 10) : null,
            datePrecision: in_array($meta['date_precision'] ?? null, MediaRecord::PRECISIONS, true) ? $meta['date_precision'] : null,
            depictedYear: $row->depicted_year !== null ? (int) $row->depicted_year : null,
            lat: $row->lat !== null ? (float) $row->lat : null,
            lng: $row->lng !== null ? (float) $row->lng : null,
            featured: $featured,
            meta: [...$keep, 'via' => $c['method'], 'station_match' => $c['match'],
                'distance_m' => $c['distance'] !== null ? (int) round($c['distance']) : null,
                'line_link' => $c['served'], 'from_media_id' => (int) $row->id],
        );
    }

    private function reject(string $reason): void
    {
        $this->rejected[$reason] = ($this->rejected[$reason] ?? 0) + 1;
    }

    /** @return array<int, array{name: string, slug: string, lat: float, lng: float, opened: ?int, wikidata: ?string, keys: list<string>}> */
    private function stations(?string $slug): array
    {
        $rows = DB::table('stations')
            ->when($slug !== null, fn ($q) => $q->where('slug', $slug))
            ->selectRaw('id, slug, name, alt_names, wikidata_id, ST_Y(geom) AS lat, ST_X(geom) AS lng, EXTRACT(YEAR FROM opened_at)::int AS opened')
            ->get();
        $out = [];
        foreach ($rows as $r) {
            $names = [(string) $r->name];
            foreach (json_decode((string) $r->alt_names, true) ?: [] as $alt) {
                if (is_array($alt) && is_string($alt['name'] ?? null)) {
                    $names[] = $alt['name'];
                }
            }
            $out[(int) $r->id] = ['name' => (string) $r->name, 'slug' => (string) $r->slug, 'lat' => (float) $r->lat, 'lng' => (float) $r->lng,
                'opened' => $r->opened !== null ? (int) $r->opened : null, 'wikidata' => $r->wikidata_id, 'keys' => CommonsStationRules::stationKeys($names)];
        }

        return $out;
    }

    /**
     * Catégories (P373) et images de référence (P18) des gares, lues dans le cache Wikidata, indexées par forme
     * normalisée : catégorie => gares, fichier => gares.
     *
     * @param  array<int, array<string, mixed>>  $stations
     * @return array{0: array<string, list<int>>, 1: array<string, list<int>>}
     */
    private function wikidata(array $stations): array
    {
        $byQid = [];
        foreach ($stations as $id => $s) {
            if (is_string($s['wikidata']) && $s['wikidata'] !== '') {
                $byQid[$s['wikidata']][] = $id;
            }
        }
        $p373 = $p18 = [];
        $dir = $this->wikidataCacheDir ?? storage_path('app/sources/stations/wikidata');
        foreach (glob($dir.'/entities-*.json') ?: [] as $file) {
            $entities = json_decode((string) file_get_contents($file), true)['entities'] ?? [];
            foreach ($entities as $qid => $e) {
                if (! isset($byQid[$qid])) {
                    continue;
                }
                foreach (self::claimValues($e, 'P373') as $k) {
                    $p373[$k] = array_values(array_unique([...($p373[$k] ?? []), ...$byQid[$qid]]));
                }
                foreach (self::claimValues($e, 'P18') as $k) {
                    $p18[$k] = array_values(array_unique([...($p18[$k] ?? []), ...$byQid[$qid]]));
                }
            }
        }

        return [$p373, $p18];
    }

    /**
     * Valeurs texte d'une propriété d'un élément Wikidata, normalisées.
     *
     * @param  array<string, mixed>  $entity
     * @return list<string>
     */
    private static function claimValues(array $entity, string $property): array
    {
        $out = [];
        foreach ($entity['claims'][$property] ?? [] as $claim) {
            $v = $claim['mainsnak']['datavalue']['value'] ?? null;
            if (is_string($v) && $v !== '') {
                $out[] = CommonsStationRules::normalize($v);
            }
        }

        return $out;
    }

    /**
     * Gares de chaque ligne (station_lines).
     *
     * @param  list<int>  $stationIds
     * @return array<int, list<int>>
     */
    private function lineStations(array $stationIds): array
    {
        $out = [];
        foreach (array_chunk($stationIds, 5000) as $ids) {
            foreach (DB::table('station_lines')->whereIn('station_id', $ids)->get(['station_id', 'line_id']) as $r) {
                $out[(int) $r->line_id][] = (int) $r->station_id;
            }
        }

        return $out;
    }

    /** Distance en mètres sur la sphère (haversine), assez précise sous le kilomètre. */
    private static function distanceM(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371008.8;
        $p1 = deg2rad($lat1);
        $p2 = deg2rad($lat2);
        $dp = $p2 - $p1;
        $dl = deg2rad($lng2 - $lng1);
        $a = sin($dp / 2) ** 2 + cos($p1) * cos($p2) * sin($dl / 2) ** 2;

        return 2 * $r * asin(min(1.0, sqrt($a)));
    }
}
