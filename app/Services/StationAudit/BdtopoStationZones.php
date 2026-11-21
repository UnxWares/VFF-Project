<?php

namespace App\Services\StationAudit;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use RuntimeException;

/**
 * Gares de la BD TOPO de l'IGN (Licence Ouverte 2.0) : zones « équipement de transport » de nature
 * gare (voyageurs, voyageurs et fret, fret) et haltes ferroviaires (« Arrêt voyageurs » dont le nom
 * commence par Halte ou Gare : les autres sont surtout des arrêts de car), lues par le WFS de la
 * Géoplateforme, dalle de 1° par dalle de 1°.
 *
 * Politesse : une requête à la fois, une seconde de pause après chaque appel réseau, User-Agent
 * générique de VFF (aucune donnée personnelle), nouvelles tentatives espacées sur 429 et 5xx.
 * Chaque page de réponse est gardée dans storage/app/data/gares-audit/bdtopo/ (dossier marqué
 * `.nobackup`) : une nouvelle passe ne rappelle pas l'IGN.
 */
class BdtopoStationZones
{
    public const WFS_URL = 'https://data.geopf.fr/wfs/ows';

    public const USER_AGENT = 'VFF-Bot/1.0 (+https://vff-project.org)';

    public const TYPENAME = 'BDTOPO_V3:equipement_de_transport';

    private const PAGE = 1000;

    private const NATURES = ['Gare voyageurs uniquement', 'Gare voyageurs et fret', 'Gare fret uniquement'];

    private int $networkCalls = 0;

    public function __construct(private string $cacheDir = '', private readonly int $pauseMs = 1000)
    {
        $this->cacheDir = $cacheDir !== '' ? $cacheDir : storage_path('app/data/gares-audit');
    }

    public function networkCalls(): int
    {
        return $this->networkCalls;
    }

    /**
     * Dalles de 1° (coin sud-ouest, entier) qui couvrent ces points.
     *
     * @param  list<array{0: float, 1: float}>  $points  [lon, lat]
     * @return list<array{0: int, 1: int}>
     */
    public static function tiles(array $points): array
    {
        $tiles = [];
        foreach ($points as [$lon, $lat]) {
            $key = (int) floor($lon).':'.(int) floor($lat);
            $tiles[$key] = [(int) floor($lon), (int) floor($lat)];
        }
        ksort($tiles);

        return array_values($tiles);
    }

    /**
     * Gares d'une dalle : identifiant, nature, nom, état et géométrie GeoJSON (MultiPolygon).
     *
     * @return list<array{cleabs: string, nature: string, toponyme: ?string, state: ?string, geometry: array<string, mixed>}>
     */
    public function tile(int $west, int $south, bool $offline = false): array
    {
        $out = [];
        for ($start = 0; ; $start += self::PAGE) {
            $query = [
                'SERVICE' => 'WFS',
                'VERSION' => '2.0.0',
                'REQUEST' => 'GetFeature',
                'TYPENAMES' => self::TYPENAME,
                'OUTPUTFORMAT' => 'application/json',
                'PROPERTYNAME' => 'cleabs,nature,toponyme,etat_de_l_objet,geometrie',
                'COUNT' => self::PAGE,
                'STARTINDEX' => $start,
                // Axe CRS84 (lon, lat) : avec EPSG:4326 le service attend lat, lon.
                'CQL_FILTER' => sprintf(
                    "(nature LIKE 'Gare%%' OR nature = 'Arrêt voyageurs') AND BBOX(geometrie,%d,%d,%d,%d,'urn:ogc:def:crs:OGC:1.3:CRS84')",
                    $west, $south, $west + 1, $south + 1,
                ),
            ];
            $json = $this->cached($query, $offline);
            if ($json === null) {
                break;
            }
            $features = $json['features'] ?? [];
            foreach ($features as $f) {
                if (($zone = self::zone($f)) !== null) {
                    $out[] = $zone;
                }
            }
            if (count($features) < self::PAGE) {
                break;
            }
        }

        return $out;
    }

    /**
     * Garde une gare ferroviaire (nature gare, ou halte nommée comme telle) ; écarte gares
     * routières et maritimes, aérogares, arrêts de car.
     *
     * @param  array<string, mixed>  $feature
     * @return array{cleabs: string, nature: string, toponyme: ?string, state: ?string, geometry: array<string, mixed>}|null
     */
    public static function zone(array $feature): ?array
    {
        $p = $feature['properties'] ?? [];
        $nature = (string) ($p['nature'] ?? '');
        $name = isset($p['toponyme']) ? (string) $p['toponyme'] : null;
        $rail = in_array($nature, self::NATURES, true)
            || ($nature === 'Arrêt voyageurs' && $name !== null && preg_match('/^(halte|gare|ancienne gare)\b/iu', $name) === 1);
        if (! $rail || ! is_array($feature['geometry'] ?? null) || ! isset($p['cleabs'])) {
            return null;
        }

        return [
            'cleabs' => (string) $p['cleabs'],
            'nature' => $nature,
            'toponyme' => $name,
            'state' => isset($p['etat_de_l_objet']) ? (string) $p['etat_de_l_objet'] : null,
            'geometry' => $feature['geometry'],
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>|null
     */
    private function cached(array $query, bool $offline): ?array
    {
        $dir = $this->cacheDir.'/bdtopo';
        $file = $dir.'/'.sha1((string) json_encode($query)).'.json';
        if (is_file($file)) {
            $json = json_decode((string) file_get_contents($file), true);
            if (is_array($json)) {
                return $json;
            }
        }
        if ($offline) {
            return null;
        }
        $json = $this->fetch($query);
        File::ensureDirectoryExists($dir);
        if (! is_file($this->cacheDir.'/.nobackup')) {
            File::put($this->cacheDir.'/.nobackup', "Réponses de Wikidata et de l'IGN gardées en cache (régénérables).\n");
        }
        File::put($file, (string) json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $json;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function fetch(array $query): array
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->networkCalls++;
            try {
                /** @var Response $res */
                $res = Http::withUserAgent(self::USER_AGENT)->timeout(90)->get(self::WFS_URL, $query);
            } catch (ConnectionException) {
                $res = null;
            }
            Sleep::usleep($this->pauseMs * 1000);
            if ($res !== null && $res->successful() && is_array($res->json())) {
                return (array) $res->json();
            }
            // Réponse 200 qui n'est pas du JSON (page d'attente, exception XML passagère) : nouvel essai.
            if ($res !== null && ! $res->successful() && ! in_array($res->status(), [429, 500, 502, 503, 504], true)) {
                throw new RuntimeException("WFS de l'IGN : HTTP {$res->status()}, ".mb_substr(trim(strip_tags($res->body())), 0, 300));
            }
            Sleep::sleep(min(60, 5 * $attempt));
        }
        throw new RuntimeException("WFS de l'IGN : trop de reprises.");
    }
}
