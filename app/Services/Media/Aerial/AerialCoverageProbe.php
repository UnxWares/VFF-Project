<?php

namespace App\Services\Media\Aerial;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

/**
 * Sonde de couverture des vues aériennes historiques de l'IGN, sans télécharger d'image : les graphes de
 * mosaïquage des missions (WFS de la Géoplateforme, couches `ORTHOIMAGERY.ORTHOPHOTOS.GRAPHE.<période>`) donnent les
 * emprises réellement couvertes ; une requête de quelques centaines d'octets dit si un point tombe dans une mission.
 * Plusieurs points peuvent être testés d'un coup (filtre OR) : une réponse vide les écarte tous.
 */
final class AerialCoverageProbe
{
    public const WFS = 'https://data.geopf.fr/wfs/ows';

    /**
     * Couche du graphe de mosaïquage par période : type, attribut géométrique, attributs lus (mission, date du vol).
     * Le graphe de 1950-1965 est celui de `...1950-1965.GRAPHE-MOSAIQUAGE` : la couche `GRAPHE.1950-1965` est
     * incomplète (Moux, dans l'Aude, y est absente alors que le WMS y a une vue : mesure du 23/11/2026).
     */
    public const LAYERS = [
        '1950-1965' => ['ORTHOIMAGERY.ORTHOPHOTOS.1950-1965.GRAPHE-MOSAIQUAGE:graphe', 'geom', 'nom', 'begin_date'],
        '1965-1980' => ['ORTHOIMAGERY.ORTHOPHOTOS.GRAPHE.1965-1980:graphe_bdortho', 'st_snaptogrid', 'pva', 'date_vol'],
        '1980-1995' => ['ORTHOIMAGERY.ORTHOPHOTOS.GRAPHE.1980-1995:graphe_bdortho', 'st_snaptogrid', 'pva', 'date_vol'],
    ];

    /** Nombre de requêtes et octets reçus, pour le bilan. */
    public int $requests = 0;

    public int $bytes = 0;

    public int $errors = 0;

    /**
     * @param  list<array{0: int, 1: float, 2: float}>  $points  [id, latitude, longitude]
     * @return array<int, array{covered: bool, date: ?string, pva: string|int|null}|null> par id ; null = erreur, à retenter
     */
    public function probe(string $period, array $points): array
    {
        $out = [];
        if ($points === []) {
            return $out;
        }
        if (count($points) === 1) {
            $id = $points[0][0];
            $res = $this->query($period, [$points[0]], 1);
            if ($res === null) {
                return [$id => null];
            }
            $f = $res[0] ?? null;
            [, , $mission, $date] = self::LAYERS[$period];

            return [$id => ['covered' => $f !== null, 'date' => $f[$date] ?? null, 'pva' => $f[$mission] ?? null]];
        }
        $res = $this->query($period, $points, 1);
        if ($res === null) {
            return array_fill_keys(array_map(fn (array $p): int => $p[0], $points), null);
        }
        if ($res === []) {
            return array_fill_keys(array_map(fn (array $p): int => $p[0], $points), ['covered' => false, 'date' => null, 'pva' => null]);
        }
        $half = intdiv(count($points), 2);

        return $this->probe($period, array_slice($points, 0, $half)) + $this->probe($period, array_slice($points, $half));
    }

    /**
     * @param  list<array{0: int, 1: float, 2: float}>  $points
     * @return list<array<string, mixed>>|null propriétés des missions trouvées, null si la requête échoue
     */
    private function query(string $period, array $points, int $count): ?array
    {
        [, $geom] = self::LAYERS[$period];
        $filter = implode(' OR ', array_map(fn (array $p): string => sprintf('INTERSECTS(%s,SRID=4326;POINT(%.6F %.6F))', $geom, $p[2], $p[1]), $points));
        $res = null;
        for ($try = 0; $try < 3; $try++) {
            $res = $this->get($period, $filter, $count);
            if ($res !== null) {
                break;
            }
            $this->errors++;
            Sleep::sleep(2);
        }
        if ($res === null) {
            return null;
        }
        $this->bytes += strlen($res->body());
        $json = $res->json();
        if (! is_array($json) || ! is_array($json['features'] ?? null)) {
            $this->errors++;

            return null;
        }

        return array_values(array_map(fn ($f): array => is_array($f['properties'] ?? null) ? $f['properties'] : [], $json['features']));
    }

    /**
     * Une requête. Le serveur répond parfois (environ 1 % des cas) par la page de métriques d'un de ses nœuds (300 Ko,
     * en `text/plain`, code 200) : l'en-tête est contrôlé avant de lire le corps, qui n'est alors pas téléchargé.
     */
    private function get(string $period, string $filter, int $count): ?Response
    {
        $this->requests++;
        try {
            $res = Http::withHeaders(['User-Agent' => AerialImages::USER_AGENT])->connectTimeout(5)->timeout(30)
                ->withOptions(['on_headers' => function (ResponseInterface $r): void {
                    if (! str_contains($r->getHeaderLine('Content-Type'), 'json')) {
                        throw new RuntimeException('Réponse qui n\'est pas du JSON');
                    }
                }])
                ->get(self::WFS, [
                    'SERVICE' => 'WFS', 'VERSION' => '2.0.0', 'REQUEST' => 'GetFeature',
                    'TYPENAMES' => self::LAYERS[$period][0],
                    'CQL_FILTER' => $filter, 'propertyName' => self::LAYERS[$period][2].','.self::LAYERS[$period][3], 'count' => (string) $count,
                    'outputFormat' => 'application/json',
                ]);
        } catch (Throwable) {
            return null;
        }

        return $res->successful() ? $res : null;
    }
}
