<?php

namespace App\Services;

use App\Enums\SegmentStatus;
use App\Enums\TrafficType;
use App\Models\Era;
use App\Models\Line;
use Clickbar\Magellan\Data\Geometries\LineString;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Importeur de la dataset officielle SNCF Réseau "Lignes par statut".
 *
 * Endpoint :
 *   https://data.sncf.com/api/explore/v2.1/catalog/datasets/lignes-par-statut/exports/geojson
 *
 * Avantages vs OSM brut :
 *   - Géométrie autoritaire (issue du SIG SNCF Réseau)
 *   - Statut officiel : Exploitée / Fermée / Neutralisée / Transférée
 *   - Code ligne RFN garanti correct
 *   - Pas d'erreurs de tagging communautaires
 *
 * Limites :
 *   - Certaines lignes "Exploitée" peuvent être en réalité peu/pas
 *     exploitées (Cerisy-Caen par ex.) - pas de filtre parfait.
 *     Le cross-check avec OSM aide pour ces cas.
 */
class SncfOpenDataImporter
{
    public function __construct(
        protected HttpFactory $http,
        protected string $endpoint = 'https://data.sncf.com/api/explore/v2.1/catalog/datasets/lignes-par-statut/exports/geojson',
        protected string $userAgent = 'VFF-Bot/1.0 (+https://vff-project.org)',
    ) {}

    /**
     * Récupère le GeoJSON complet du réseau RFN classé par statut.
     */
    public function fetch(): array
    {
        Log::info('[SncfOpenDataImporter] Fetching '.$this->endpoint);

        $response = $this->http
            ->withHeaders(['User-Agent' => $this->userAgent])
            ->timeout(360)
            ->get($this->endpoint);

        if (! $response->ok()) {
            throw new \RuntimeException("SNCF Open Data returned HTTP {$response->status()}");
        }

        return (array) $response->json();
    }

    /**
     * Persiste les segments d'après le GeoJSON SNCF Open Data.
     *
     * @return array{processed: int, created_lines: int, created_segments: int, skipped: int}
     */
    public function persist(array $geojson, ?Era $era = null, bool $onlyExploitee = true): array
    {
        $era ??= Era::where('is_current', true)->firstOrFail();

        $createdLines = 0;
        $createdSegments = 0;
        $skipped = 0;
        $processed = 0;

        $lineCache = [];

        foreach ($geojson['features'] ?? [] as $feat) {
            $processed++;
            $props = $feat['properties'] ?? [];
            $geom = $feat['geometry'] ?? null;

            $statut = $props['statut'] ?? '';
            $code = $props['code_ligne']
                ?? $props['lib_ligne']
                ?? $props['code'] ?? null;

            // Filtre principal : exclut Fermée / Neutralisée pour 2030.
            if ($onlyExploitee && ! $this->isExploitee($statut)) {
                $skipped++;

                continue;
            }

            if (! $code || ! $geom) {
                $skipped++;

                continue;
            }

            $normalizedCode = $this->normalizeCode((string) $code);

            // Récupère ou crée la Line correspondante
            $line = $lineCache[$normalizedCode] ?? null;
            if (! $line) {
                // Même normalisation des deux côtés (espaces ET zéros de tête) : sinon
                // « 070000 » en base ne correspondait pas à « 70000 » et l'import
                // créait une ligne rfn-70000 en double.
                $line = Line::query()
                    ->whereRaw("COALESCE(NULLIF(LTRIM(UPPER(REGEXP_REPLACE(code, '\\s+', '', 'g')), '0'), ''), '0') = ?", [$normalizedCode])
                    ->first();

                if (! $line) {
                    $rawName = $props['lib_ligne']
                        ?? $props['libelle']
                        ?? $props['nom']
                        ?? null;

                    // SNCF utilise parfois le code comme nom (lib_ligne = "508000"),
                    // dans ce cas on préfère un fallback "Ligne XXX" pour distinguer
                    // visuellement et permettre un upgrade ultérieur via OSM.
                    $hasRealName = $rawName
                        && $this->normalizeCode((string) $rawName) !== $normalizedCode;
                    $name = $hasRealName ? $rawName : "Ligne {$code}";

                    $slug = 'rfn-'.Str::slug($normalizedCode);
                    $line = Line::firstOrCreate(
                        ['slug' => $slug],
                        ['name' => $name, 'code' => $code],
                    );
                    $createdLines++;
                }
                $lineCache[$normalizedCode] = $line;
            }

            // Convertit la géométrie GeoJSON en LineString(s)
            $segments = $this->extractLineStrings($geom);
            foreach ($segments as $i => $coords) {
                if (count($coords) < 2) {
                    continue;
                }

                $points = array_map(
                    fn ($c) => Point::makeGeodetic($c[1], $c[0]),
                    $coords,
                );

                // Pas de clé naturelle (osm_way_id NULL pour tous) : un tronçon de
                // même géométrie déjà présent pour la ligne et l'ère n'est pas recréé,
                // l'import est ainsi rejouable sans doublon (aucun truncate préalable).
                $geometry = LineString::make($points);
                $exists = DB::table('line_segments')
                    ->where('line_id', $line->id)->where('era_id', $era->id)
                    ->whereRaw('ST_Equals(geom, ST_SetSRID(ST_GeomFromGeoJSON(?), 4326))', [json_encode(['type' => 'LineString', 'coordinates' => $coords])])
                    ->exists();
                if ($exists) {
                    $skipped++;

                    continue;
                }
                $line->segments()->create([
                    'era_id' => $era->id,
                    'geom' => $geometry,
                    'status' => $this->mapStatus($statut)->value,
                    'traffic_type' => $this->mapTrafficType($statut)->value,
                    'electrified' => isset($props['electrifiee']) && stripos($props['electrifiee'], 'oui') !== false,
                    'voltage_v' => $this->parseVoltage($props['tension'] ?? null),
                    'max_speed_kmh' => isset($props['vitesse_max']) ? (int) $props['vitesse_max'] : null,
                    'gauge_mm' => 1435,
                    'pk_start_m' => $this->parsePk($props['pkd'] ?? null),
                    'pk_end_m' => $this->parsePk($props['pkf'] ?? null),
                ]);
                $createdSegments++;
            }
        }

        return [
            'processed' => $processed,
            'created_lines' => $createdLines,
            'created_segments' => $createdSegments,
            'skipped' => $skipped,
        ];
    }

    /**
     * Statuts SNCF "Exploitée" :
     *   - "Exploitée"
     *   - "Exploitée Voyageurs"
     *   - "Exploitée Fret seul"
     *   - "Exploitée VU Fret seul"
     *
     * Exclus : Fermée, Neutralisée, Transférée, Retranchée.
     */
    protected function isExploitee(string $statut): bool
    {
        return stripos($statut, 'exploit') !== false;
    }

    protected function mapStatus(string $statut): SegmentStatus
    {
        // Mapping unique partagé (cf. SegmentStatus::fromSncfLibelle).
        return SegmentStatus::fromSncfLibelle($statut);
    }

    /**
     * Détermine si la section est fret seul ou mixte depuis le statut SNCF.
     * Règle métier R1 « si y'a du passager, y'a du fret » : une section ouverte
     * aux voyageurs est MIXTE ; « voyageurs seuls » est réservé aux LGV, que
     * vff:classify-lines reconnaît ensuite (le statut SNCF ne le dit pas).
     * Exemples :
     *   "Exploitée Voyageurs"        → Mixed
     *   "Exploitée VU Voyageurs"     → Mixed
     *   "Exploitée Fret seul"        → Freight
     *   "Exploitée VU Fret seul"     → Freight
     *   "Exploitée"                  → Mixed (default)
     */
    protected function mapTrafficType(string $statut): TrafficType
    {
        $s = mb_strtolower($statut);
        if (str_contains($s, 'fret seul')) {
            return TrafficType::Freight;
        }
        if (str_contains($s, 'exploit')) {
            return TrafficType::Mixed;
        }

        return TrafficType::Unknown;
    }

    protected function normalizeCode(string $code): string
    {
        $clean = preg_replace('/\s+/', '', strtoupper(trim($code)));
        // Strip leading zeros pour les refs purement numériques
        if (ctype_digit($clean)) {
            $clean = ltrim($clean, '0') ?: '0';
        }

        return $clean;
    }

    protected function parseVoltage(?string $raw): ?int
    {
        if (! $raw) {
            return null;
        }
        // « 25 kV », « 1,5 kV » : tension en kilovolts (sinon 25 V).
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*kv/i', $raw, $m)) {
            $v = (int) round((float) str_replace(',', '.', $m[1]) * 1000);

            return ($v > 0 && $v <= 50000) ? $v : null;
        }
        $v = (int) preg_replace('/[^0-9]/', '', $raw);

        return ($v > 0 && $v <= 50000) ? $v : null;
    }

    /**
     * Parse PK SNCF "123+450" → 123450 mètres depuis l'origine.
     */
    protected function parsePk(?string $raw): ?int
    {
        if (! $raw) {
            return null;
        }
        if (! preg_match('/^(\d+)\+(\d+)$/', trim($raw), $m)) {
            return null;
        }

        return (int) $m[1] * 1000 + (int) $m[2];
    }

    /**
     * Extrait des coordonnées GeoJSON LineString ou MultiLineString une liste
     * de LineStrings (chaque sous-array = coords d'une ligne).
     *
     * @return array<int, array<int, array{0: float, 1: float}>>
     */
    protected function extractLineStrings(array $geom): array
    {
        $type = $geom['type'] ?? '';
        $coords = $geom['coordinates'] ?? [];

        return match ($type) {
            'LineString' => [$coords],
            'MultiLineString' => $coords,
            default => [],
        };
    }
}
