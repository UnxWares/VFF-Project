<?php

declare(strict_types=1);

namespace App\Services\Structures;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use RuntimeException;

/**
 * Constructions linéaires de la BD TOPO de l'IGN (`BDTOPO_V3:construction_lineaire`, Licence Ouverte 2.0)
 * utiles aux ouvrages ferroviaires : tous les tunnels (nommés ou non : ceux d'une ligne déposée n'ont plus
 * de voie dans la BD TOPO, l'ouvrage, lui, y reste) et les ponts nommés (le nom d'un viaduc).
 *
 * Requêtes WFS bornées par une tuile de TILE_DEG degré de côté, page par page, une à la fois avec une
 * pause ; réponses gardées sous forme compacte dans `storage/app/data/ouvrages/constructions/`, dossier
 * marqué `.nobackup` (régénérable, quelques mégaoctets).
 */
final class BdTopoConstructions
{
    public const WFS_URL = 'https://data.geopf.fr/wfs/ows';

    public const USER_AGENT = 'VFF-Bot/1.0 (+https://vff-project.org)';

    public const TILE_DEG = 1.0;

    private const PAGE = 2000;

    private int $networkCalls = 0;

    public function __construct(private string $cacheDir = '', private readonly int $pauseMs = 1000)
    {
        $this->cacheDir = $cacheDir !== '' ? $cacheDir : storage_path('app/data/ouvrages');
    }

    public function networkCalls(): int
    {
        return $this->networkCalls;
    }

    /**
     * Tunnels et ponts nommés d'une tuile.
     *
     * @return list<array{nature: string, name: ?string, coords: list<array{0: float, 1: float}>}>
     */
    public function inTile(int $x, int $y, bool $offline = false): array
    {
        $file = $this->cacheDir.'/constructions/'.$x.'_'.$y.'.json.gz';
        if (is_file($file)) {
            $raw = gzdecode((string) file_get_contents($file));
            $rows = $raw === false ? null : json_decode($raw, true);
        } elseif ($offline) {
            return [];
        } else {
            $rows = $this->fetch($x, $y);
            $this->write($file, $rows);
        }

        return array_map(fn (array $r): array => [
            'nature' => (string) $r[0],
            'name' => $r[1] === null ? null : (string) $r[1],
            'coords' => array_map(fn (array $c): array => [(float) $c[0], (float) $c[1]], $r[2]),
        ], is_array($rows) ? $rows : []);
    }

    /** @return list<array{0: string, 1: ?string, 2: list<array{0: float, 1: float}>}> */
    private function fetch(int $x, int $y): array
    {
        $bbox = [$x * self::TILE_DEG, $y * self::TILE_DEG, ($x + 1) * self::TILE_DEG, ($y + 1) * self::TILE_DEG];
        $out = [];
        for ($start = 0; ; $start += self::PAGE) {
            $query = [
                'SERVICE' => 'WFS',
                'VERSION' => '2.0.0',
                'REQUEST' => 'GetFeature',
                'TYPENAMES' => 'BDTOPO_V3:construction_lineaire',
                'OUTPUTFORMAT' => 'application/json',
                'PROPERTYNAME' => 'nature,toponyme,geometrie',
                'COUNT' => self::PAGE,
                'STARTINDEX' => $start,
                'SORTBY' => 'cleabs',
                // Axe CRS84 (lon, lat), comme pour les tronçons de voie (GeoplateformeClient).
                'CQL_FILTER' => sprintf(
                    "(nature = 'Tunnel' OR (nature = 'Pont' AND toponyme IS NOT NULL)) AND BBOX(geometrie,%.6f,%.6f,%.6f,%.6f,'urn:ogc:def:crs:OGC:1.3:CRS84')",
                    ...$bbox,
                ),
            ];
            $features = $this->get($query)['features'] ?? [];
            foreach ($features as $f) {
                $geometry = $f['geometry'] ?? [];
                $lines = match ($geometry['type'] ?? null) {
                    'LineString' => [$geometry['coordinates']],
                    'MultiLineString' => $geometry['coordinates'],
                    default => [],
                };
                foreach ($lines as $coords) {
                    if (count($coords) < 2) {
                        continue;
                    }
                    $name = isset($f['properties']['toponyme']) ? trim((string) $f['properties']['toponyme']) : '';
                    $out[] = [
                        (string) ($f['properties']['nature'] ?? ''),
                        $name === '' ? null : $name,
                        array_map(fn (array $c): array => [round((float) $c[0], 7), round((float) $c[1], 7)], $coords),
                    ];
                }
            }
            if (count($features) < self::PAGE) {
                break;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, scalar>  $query
     * @return array<string, mixed>
     */
    private function get(array $query): array
    {
        for ($attempt = 1; ; $attempt++) {
            $this->networkCalls++;
            $response = Http::withUserAgent(self::USER_AGENT)->timeout(90)->get(self::WFS_URL, $query);
            Sleep::usleep($this->pauseMs * 1000);
            if ($response->successful() && is_array($response->json())) {
                return $response->json();
            }
            if ($attempt >= 3 || ! ($response->successful() || $response->status() === 429 || $response->serverError())) {
                throw new RuntimeException("Service WFS de l'IGN : HTTP {$response->status()}");
            }
            Sleep::usleep($this->pauseMs * 5000 * $attempt);
        }
    }

    /** @param  list<array{0: string, 1: ?string, 2: list<array{0: float, 1: float}>}>  $rows */
    private function write(string $file, array $rows): void
    {
        $dir = dirname($file);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $marker = $this->cacheDir.'/.nobackup';
        if (! is_file($marker)) {
            file_put_contents($marker, "Réponses de l'IGN et de Wikidata gardées en cache pour vff:import-structures (régénérables).\n");
        }
        $tmp = $file.'.'.getmypid().'.tmp';
        file_put_contents($tmp, gzencode((string) json_encode($rows), 6));
        rename($tmp, $file);
    }
}
