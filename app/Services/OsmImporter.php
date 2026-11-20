<?php

namespace App\Services;

use App\Enums\SegmentStatus;
use App\Enums\StationType;
use App\Models\Era;
use App\Models\Line;
use App\Models\LineSegment;
use App\Models\Station;
use Clickbar\Magellan\Data\Geometries\LineString;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;

/**
 * Importeur du réseau ferré français depuis l'API Overpass d'OpenStreetMap.
 *
 * Cette classe est SQUELETTE en Phase 0 - le mapping OSM → modèle métier
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
     * d'une région française.
     *
     *   - "FR"        → pays entier (ISO 3166-1, admin_level=2)
     *   - "FR-BRE"    → Bretagne (ISO 3166-2, admin_level=4)
     *   - "FR-IDF"    → Île-de-France (ISO 3166-2, admin_level=4)
     *   - etc.
     */
    public function buildQuery(string $regionIsoCode = 'FR'): string
    {
        $code = strtoupper(trim($regionIsoCode));
        if ($code === 'FR' || $code === '') {
            $areaSelector = '["ISO3166-1"="FR"][admin_level=2]';
        } else {
            // Sécurise contre l'injection Overpass : on ne garde que les caractères ISO valides.
            $safe = preg_replace('/[^A-Z0-9\-]/', '', $code);
            $areaSelector = "[\"ISO3166-2\"=\"{$safe}\"][admin_level=4]";
        }

        // Filtres essentiels pour ne garder que les LIGNES (pas les voies de
        // garage, gares de triage, embranchements industriels). Sans ces filtres
        // l'import devient illisible dans les nœuds ferroviaires (Rennes, Paris).
        //
        // - "service"!~"."          : exclut yard, siding, spur, crossover
        // - "usage"!~"^(industrial|military|test|tourism)$" : exclut RFN non-mainline
        // - les sous-tags ferroviaires (subway/tram/light_rail) sont déjà exclus
        //   du regex railway= ci-dessous.
        return <<<OVERPASS
            [out:json][timeout:300];
            area{$areaSelector}->.region;
            (
              way["railway"~"^(rail|narrow_gauge|light_rail|abandoned|disused|razed|dismantled|construction)$"]
                 ["service"!~"."]
                 ["usage"!~"^(industrial|military|test)$"]
                 ["end_date"!~"."]
                 ["disused:railway"!~"."]
                 ["abandoned:railway"!~"."]
                 (area.region);
            );
            out geom;
        OVERPASS;
    }

    /**
     * Lance la requête Overpass et retourne le payload JSON décodé.
     * Retry avec backoff exponentiel sur 504/429 (Overpass est régulièrement surchargé).
     *
     * @return array{elements: array<int, array<string, mixed>>}
     */
    public function fetch(string $regionIsoCode = 'FR', int $maxAttempts = 4): array
    {
        $query = $this->buildQuery($regionIsoCode);

        Log::info('[OsmImporter] Overpass request', ['region' => $regionIsoCode, 'length' => strlen($query)]);

        $attempt = 0;
        $lastError = null;
        while ($attempt < $maxAttempts) {
            $attempt++;
            try {
                $response = $this->http
                    ->withHeaders(['User-Agent' => $this->userAgent])
                    ->timeout(360)
                    ->asForm()
                    ->post($this->endpoint, ['data' => $query]);

                if ($response->ok()) {
                    /** @var array{elements: array<int, array<string, mixed>>} $payload */
                    $payload = $response->json();

                    return $payload;
                }

                $lastError = "HTTP {$response->status()}";
                // 4xx (sauf 429) ne bénéficie pas de retry
                if ($response->status() >= 400 && $response->status() < 500 && $response->status() !== 429) {
                    break;
                }
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
            }

            if ($attempt < $maxAttempts) {
                $sleep = (2 ** $attempt) * 5; // 10s, 20s, 40s
                Log::warning("[OsmImporter] tentative {$attempt} échouée ({$lastError}), retry dans {$sleep}s");
                Sleep::sleep($sleep);
            }
        }

        throw new \RuntimeException("Overpass a échoué après {$attempt} tentative(s) : {$lastError}");
    }

    /**
     * Persiste les segments dans la base. Lie chaque way OSM à une `Line`
     * (créée à la volée par nom/ref) et à un `LineSegment` pour l'ère
     * courante. Idempotent par OSM way id (stocké dans alt_names.osm_id).
     *
     * Par défaut, seuls les ways portant un tag `ref` (numéro RFN officiel)
     * sont importés. Cela garantit qu'on ne crée pas une `Line` par chaque
     * embranchement OSM, mais qu'on aggrège tous les ways d'une même ligne
     * RFN sous une seule `Line` (ex: 1049 segments pour Paris-Brest).
     *
     * Passer `$skipWithoutRef = false` permet d'inclure aussi les lignes
     * non-numérotées (touristiques, courts embranchements…).
     *
     * @param  array<int, array<string, mixed>>  $elements
     */
    public function persist(
        array $elements,
        ?Era $era = null,
        bool $skipWithoutRef = true,
        bool $strictExisting = false,
    ): int {
        $era ??= Era::where('is_current', true)->firstOrFail();

        $created = 0;

        foreach ($elements as $element) {
            // Au moins 2 points pour un LINESTRING valide : vérifié AVANT de créer la Line
            // (sinon un way dégénéré laissait une ligne fantôme sans aucun tronçon).
            if (($element['type'] ?? null) !== 'way' || count($element['geometry'] ?? []) < 2) {
                continue;
            }

            $tags = $element['tags'] ?? [];
            $name = $tags['name'] ?? ($tags['ref'] ?? "OSM way #{$element['id']}");
            $ref = isset($tags['ref']) ? $this->normalizeRef($tags['ref']) : null;

            // Erreurs de tagging OSM fréquentes : des ways `railway=rail` ont
            // un `ref` qui est en réalité un numéro de route départementale
            // (D 812) ou nationale (N 7). On ignore ces faux positifs.
            if ($ref && preg_match('/^(D|N|RD|RN|A|M|E)\d+[A-Z]?$/', $ref)) {
                continue;
            }

            // Stratégie RFN : on n'importe que les ways portant un numéro de
            // ligne. Les voies sans ref (industrielles, courts segments isolés)
            // sont ignorées sauf si on les demande explicitement.
            if ($skipWithoutRef && ! $ref) {
                continue;
            }

            // Si la ligne OSM porte un ref (ex: "420 000") et qu'on a déjà une
            // Line VFF avec ce code (ex: seeder Paris-Brest), on attache à
            // l'existant au lieu de créer un doublon. Sinon on crée une Line
            // basée sur le ref (slug stable, pas l'OSM way id, pour qu'un
            // ré-import retrouve la même Line).
            $line = $ref ? $this->findLineByRef($ref) : null;
            if (! $line) {
                // Mode strict : on n'attache QU'aux Lines déjà connues (typique-
                // ment seedées depuis SNCF Open Data). Évite la pollution par les
                // milliers de ways OSM tagués `ref=X` qui n'ont aucun équivalent
                // RFN officiel.
                if ($strictExisting) {
                    continue;
                }

                $slug = $ref
                    ? 'rfn-'.Str::slug($ref)  // ex: "rfn-441000" pour ref="441000"
                    : Str::slug($name).'-'.$element['id'];
                $line = Line::firstOrCreate(
                    ['slug' => $slug],
                    [
                        'name' => $name,
                        'code' => $tags['ref'] ?? null,
                        'alt_names' => ['osm_id' => $element['id']],
                    ],
                );
                // Mémoïse pour les itérations suivantes du même import - sinon
                // la prochaine way avec le même ref recréerait une Line doublon.
                if ($ref) {
                    $this->lineByRefCache[$ref] = $line;
                }
            } elseif ($this->isBetterName($name, $line->name)) {
                // Upgrade : si la Line existante a un nom moins bon (ex: "830000")
                // et qu'on tombe maintenant sur un way avec "Ligne de X à Y", on
                // remplace.
                $line->update(['name' => $name]);
            }

            $points = array_map(
                fn (array $node) => Point::makeGeodetic($node['lat'], $node['lon']),
                $element['geometry'],
            );

            // Au moins 2 points pour un LINESTRING valide
            if (count($points) < 2) {
                continue;
            }

            $operator = $tags['operator'] ?? null;
            $line->segments()->updateOrCreate(
                [
                    'era_id' => $era->id,
                    'osm_way_id' => $element['id'],
                ],
                [
                    'geom' => LineString::make($points),
                    'status' => $this->mapStatus($tags['railway'] ?? 'rail')->value,
                    'electrified' => isset($tags['electrified']) && $tags['electrified'] !== 'no',
                    'voltage_v' => $this->sanitizeVoltage($tags['voltage'] ?? null),
                    'max_speed_kmh' => $this->sanitizeSpeed($tags['maxspeed'] ?? null),
                    'gauge_mm' => isset($tags['gauge']) ? (int) $tags['gauge'] : 1435,
                    'operator' => $operator,
                    'is_heritage' => self::isHeritageOperator($operator),
                    // Tags OSM discriminants conservés pour corroborer la classification.
                    'osm_traffic_mode' => $tags['railway:traffic_mode'] ?? null,
                    'osm_highspeed' => ($tags['highspeed'] ?? null) === 'yes',
                    'osm_usage' => $tags['usage'] ?? null,
                ],
            );

            $created++;
        }

        return $created;
    }

    /**
     * Normalise un tag `ref` OSM pour comparaison robuste : retire les espaces,
     * passe en majuscules. "420 000" → "420000", "L2C" → "L2C".
     */
    protected function normalizeRef(string $ref): string
    {
        $clean = strtoupper(preg_replace('/\s+/', '', $ref));
        // Strip leading zeros pour les refs purement numériques (sinon
        // "006000" et "6000" sont considérés distincts → doublons).
        if (ctype_digit($clean)) {
            $clean = ltrim($clean, '0') ?: '0';
        }

        return $clean;
    }

    /**
     * Cherche une Line existante dont le `code` normalisé matche le `ref` OSM.
     * Mémoïsé pendant la durée de l'import pour éviter N requêtes.
     */
    private array $lineByRefCache = [];

    protected function findLineByRef(string $normalizedRef): ?Line
    {
        if (array_key_exists($normalizedRef, $this->lineByRefCache)) {
            return $this->lineByRefCache[$normalizedRef];
        }

        $line = Line::query()
            ->whereNotNull('code')
            ->get()
            ->first(fn (Line $l) => $this->normalizeRef((string) $l->code) === $normalizedRef);

        return $this->lineByRefCache[$normalizedRef] = $line;
    }

    /**
     * Spatial enrichment : importe les ways SANS ref OSM et les attache
     * spatialement à la Line existante la plus proche (dans un rayon donné).
     * Permet de récupérer les tunnels et raccordements qui ne sont pas tagués
     * avec un numéro de ligne mais font physiquement partie d'une ligne RFN.
     *
     * Aucune nouvelle Line n'est créée - les ways isolés (industriels privés,
     * etc.) sont simplement skippés.
     *
     * @return array{fetched: int, attached: int, skipped: int}
     */
    public function enrichTunnels(string $regionIsoCode = 'FR', ?Era $era = null, int $maxDistanceM = 200): array
    {
        $era ??= Era::where('is_current', true)->firstOrFail();

        $code = strtoupper(trim($regionIsoCode));
        $areaSelector = $code === 'FR' || $code === ''
            ? '["ISO3166-1"="FR"][admin_level=2]'
            : '["ISO3166-2"="'.preg_replace('/[^A-Z0-9\-]/', '', $code).'"][admin_level=4]';

        // Ways `railway=rail` sans `ref` (les tunnels/raccordements typiques)
        $query = <<<OVERPASS
            [out:json][timeout:300];
            area{$areaSelector}->.region;
            (
              way["railway"="rail"]
                 ["ref"!~"."]
                 ["service"!~"."]
                 ["usage"!~"^(industrial|military|test)$"]
                 ["disused:railway"!~"."]
                 ["abandoned:railway"!~"."]
                 (area.region);
            );
            out geom;
        OVERPASS;

        Log::info('[OsmImporter] enrichTunnels query', ['region' => $regionIsoCode]);

        $response = $this->http
            ->withHeaders(['User-Agent' => $this->userAgent])
            ->timeout(360)
            ->asForm()
            ->post($this->endpoint, ['data' => $query]);

        if (! $response->ok()) {
            throw new \RuntimeException("Overpass HTTP {$response->status()}");
        }

        $payload = $response->json();
        $elements = $payload['elements'] ?? [];
        $attached = 0;
        $skipped = 0;

        foreach ($elements as $element) {
            if (($element['type'] ?? null) !== 'way' || empty($element['geometry'])) {
                continue;
            }

            $points = array_map(
                fn ($n) => Point::makeGeodetic($n['lat'], $n['lon']),
                $element['geometry'],
            );
            if (count($points) < 2) {
                continue;
            }

            // Centre approximatif du way pour le test spatial
            $midIdx = (int) (count($element['geometry']) / 2);
            $midLat = $element['geometry'][$midIdx]['lat'];
            $midLon = $element['geometry'][$midIdx]['lon'];

            // Trouve la Line la plus proche via son segment le plus proche
            $row = DB::selectOne(
                <<<'SQL'
                SELECT ls.line_id
                FROM line_segments ls
                WHERE ls.era_id = ?
                  AND ls.geom && ST_Expand(ST_SetSRID(ST_MakePoint(?, ?), 4326), 0.003)
                ORDER BY ST_Distance(ls.geom, ST_SetSRID(ST_MakePoint(?, ?), 4326)) ASC
                LIMIT 1
                SQL,
                [$era->id, $midLon, $midLat, $midLon, $midLat],
            );

            if (! $row) {
                $skipped++;

                continue;
            }

            // Vérifie la distance réelle (geography, en mètres) - bbox-only n'est pas assez précis
            $distRow = DB::selectOne(
                'SELECT ST_Distance(ls.geom::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography) AS d
                 FROM line_segments ls WHERE ls.line_id = ? AND ls.era_id = ?
                 ORDER BY d ASC LIMIT 1',
                [$midLon, $midLat, $row->line_id, $era->id],
            );
            if (! $distRow || $distRow->d > $maxDistanceM) {
                $skipped++;

                continue;
            }

            // Attache le segment à la Line trouvée
            LineSegment::updateOrCreate(
                [
                    'era_id' => $era->id,
                    'osm_way_id' => $element['id'],
                ],
                [
                    'line_id' => $row->line_id,
                    'geom' => LineString::make($points),
                    'status' => $this->mapStatus($element['tags']['railway'] ?? 'rail')->value,
                    'gauge_mm' => 1435,
                ],
            );
            $attached++;
        }

        return [
            'fetched' => count($elements),
            'attached' => $attached,
            'skipped' => $skipped,
        ];
    }

    /**
     * Importe les gares depuis OSM (railway=station|halt|stop|service_station).
     * Idempotent par osm_node_id.
     *
     *   - railway=station         → Gare
     *   - railway=halt            → Halte
     *   - railway=stop            → PointArret
     *   - railway=service_station → Embranchement
     *
     * @return array{fetched: int, created: int, updated: int}
     */
    public function importStations(string $regionIsoCode = 'FR'): array
    {
        $code = strtoupper(trim($regionIsoCode));
        $areaSelector = $code === 'FR' || $code === ''
            ? '["ISO3166-1"="FR"][admin_level=2]'
            : '["ISO3166-2"="'.preg_replace('/[^A-Z0-9\-]/', '', $code).'"][admin_level=4]';

        // Filtres :
        //   - "name" obligatoire (sinon pas une vraie gare référencée)
        //   - exclut les disused/abandoned/historic (souvent mal taggés)
        $query = <<<OVERPASS
            [out:json][timeout:300];
            area{$areaSelector}->.region;
            (
              node["railway"~"^(station|halt|stop|service_station)$"]
                  ["name"]
                  ["disused"!~"."]
                  ["abandoned"!~"."]
                  ["historic"!~"."]
                  (area.region);
            );
            out tags geom;
        OVERPASS;

        Log::info('[OsmImporter] importStations query', ['region' => $regionIsoCode]);

        $response = $this->http
            ->withHeaders(['User-Agent' => $this->userAgent])
            ->timeout(360)
            ->asForm()
            ->post($this->endpoint, ['data' => $query]);

        if (! $response->ok()) {
            throw new \RuntimeException("Overpass returned HTTP {$response->status()}");
        }

        $payload = $response->json();
        $created = 0;
        $updated = 0;

        foreach ($payload['elements'] ?? [] as $node) {
            if (($node['type'] ?? null) !== 'node') {
                continue;
            }
            if (! isset($node['lat'], $node['lon'])) {
                continue;
            }

            $tags = $node['tags'] ?? [];
            $type = $this->mapStationType($tags['railway'] ?? 'station');
            $name = $tags['name']
                ?? $tags['ref:SNCF']
                ?? $tags['uic_ref']
                ?? "Gare #{$node['id']}";

            // Slug stable basé sur OSM id pour idempotence - préfixé pour ne pas
            // collider avec les slugs seedés à la main (paris-montparnasse, etc.)
            $slug = 'osm-'.$node['id'].'-'.Str::slug($name);

            $station = Station::firstOrNew(['osm_node_id' => $node['id']]);
            // Slug posé à la création seulement : vff:canonicalize-station-slugs le remplace
            // par un slug lisible (+ redirection 301) ; le réécrire ici casserait ces URL.
            if (! $station->exists) {
                $station->slug = $slug;
            }
            $station->fill([
                'name' => $name,
                'type' => $type->value,
                'geom' => Point::makeGeodetic($node['lat'], $node['lon']),
                'city' => $tags['addr:city'] ?? null,
            ])->save();

            $station->wasRecentlyCreated ? $created++ : $updated++;
        }

        return [
            'fetched' => count($payload['elements'] ?? []),
            'created' => $created,
            'updated' => $updated,
        ];
    }

    protected function mapStationType(string $osmRailway): StationType
    {
        return match ($osmRailway) {
            'station' => StationType::Gare,
            'halt' => StationType::Halte,
            'stop' => StationType::PointArret,
            'service_station' => StationType::Embranchement,
            default => StationType::Gare,
        };
    }

    /**
     * Backfill des noms des Lines anonymes (name == ref numérique).
     * Requête Overpass tags-only (sans geom) → réponse ~50× plus petite.
     * Retourne le nombre de Lines mises à jour.
     */
    public function backfillLineNames(string $regionIsoCode = 'FR'): int
    {
        $code = strtoupper(trim($regionIsoCode));
        $areaSelector = $code === 'FR' || $code === ''
            ? '["ISO3166-1"="FR"][admin_level=2]'
            : '["ISO3166-2"="'.preg_replace('/[^A-Z0-9\-]/', '', $code).'"][admin_level=4]';

        $query = <<<OVERPASS
            [out:json][timeout:300];
            area{$areaSelector}->.region;
            (
              way["railway"~"^(rail|abandoned|disused|razed|dismantled|construction)$"]
                 ["ref"]["name"]
                 ["service"!~"."]
                 (area.region);
            );
            out tags;
        OVERPASS;

        Log::info('[OsmImporter] backfillLineNames query', ['region' => $regionIsoCode]);

        $response = $this->http
            ->withHeaders(['User-Agent' => $this->userAgent])
            ->timeout(360)
            ->asForm()
            ->post($this->endpoint, ['data' => $query]);

        if (! $response->ok()) {
            throw new \RuntimeException("Overpass returned HTTP {$response->status()}");
        }

        $payload = $response->json();
        $updated = 0;

        foreach ($payload['elements'] ?? [] as $element) {
            $tags = $element['tags'] ?? [];
            $ref = isset($tags['ref']) ? $this->normalizeRef($tags['ref']) : null;
            $name = $tags['name'] ?? null;
            if (! $ref || ! $name || ! str_starts_with($name, 'Ligne ')) {
                continue;
            }
            if (preg_match('/^(D|N|RD|RN|A|M|E)\d+[A-Z]?$/', $ref)) {
                continue;
            }

            // Trouve la Line correspondante par code normalisé
            $line = Line::query()
                ->whereNotNull('code')
                ->get()
                ->first(fn (Line $l) => $this->normalizeRef((string) $l->code) === $ref);

            if (! $line) {
                continue;
            }
            if ($this->isBetterName($name, $line->name)) {
                $line->update(['name' => $name]);
                $updated++;
            }
        }

        return $updated;
    }

    /**
     * Détermine si le nouveau nom est meilleur que l'existant. Les noms du
     * style "Ligne de X à Y" battent ceux qui sont juste un numéro ou vides.
     */
    protected function isBetterName(string $newName, ?string $existingName): bool
    {
        if (! $existingName) {
            return true;
        }
        // Existant est anonyme (juste des chiffres) ou égal au code → upgrade possible
        $existingIsAnonymous = preg_match('/^[0-9\s]+$/', $existingName);

        return $existingIsAnonymous && str_starts_with($newName, 'Ligne ');
    }

    /**
     * Détermine si l'opérateur OSM est un chemin de fer touristique / patrimonial
     * / vélorail (à distinguer du réseau commercial).
     *
     * APPROCHE PAR LISTE POSITIVE (défaut false) : être « non SNCF Réseau » ne
     * fait PAS d'une ligne un train touristique. Les concessions LGV (LISEA,
     * MESEA, OC'VIA…), les réseaux étrangers (SBB, CFL, Adif, Renfe), les AOT
     * régionales (Région X, régies) et les trams (Transkeo, Soléa, métropoles)
     * sont du trafic réel → PAS heritage. On ne flague heritage que sur un
     * signal touristique explicite. La curation fine reste via vff:flag-heritage.
     */
    public static function isHeritageOperator(?string $operator): bool
    {
        if (! $operator) {
            return false;
        }
        $o = mb_strtolower($operator);

        $heritageNeedles = [
            'tourist', 'touristique', 'vélorail', 'velorail', 'vélo-rail', 'velo rail',
            'draisine', 'cyclo', 'vapeur', 'train historique', 'train à vapeur',
            'musée', 'museum', 'aaatv', 'chemin de fer touristique', 'cftt', 'cftn',
            'cfbs', 'cftr', 'truffadou', 'panoramique', 'agrivap',
            'train du ', 'train de la ', "train de l'", 'train thur', 'train à vapeur',
            'autorail touristique', 'ecomusée', 'écomusée', 'préservation', 'historique',
        ];
        foreach ($heritageNeedles as $needle) {
            if (str_contains($o, $needle)) {
                return true;
            }
        }

        // Tout le reste (réseau commercial, concession, étranger, AOT, tram) = non heritage.
        return false;
    }

    /**
     * Filtre les tensions traction aberrantes (max plausible ≈ 50 kV).
     */
    protected function sanitizeVoltage(?string $raw): ?int
    {
        if ($raw === null) {
            return null;
        }
        $v = (int) $raw;

        return ($v > 0 && $v <= 50000) ? $v : null;
    }

    /**
     * Filtre les vitesses absurdes (record TGV ≈ 575 km/h, RFN max ≈ 320).
     */
    protected function sanitizeSpeed(?string $raw): ?int
    {
        if ($raw === null) {
            return null;
        }
        $v = (int) $raw;

        return ($v > 0 && $v <= 600) ? $v : null;
    }

    protected function mapStatus(string $osmRailway): SegmentStatus
    {
        return match ($osmRailway) {
            'rail' => SegmentStatus::InService,
            'construction' => SegmentStatus::Construction,
            'abandoned', 'disused' => SegmentStatus::Abandoned,
            'razed' => SegmentStatus::Razed,
            'dismantled' => SegmentStatus::Dismantled,
            default => SegmentStatus::InService,
        };
    }
}
