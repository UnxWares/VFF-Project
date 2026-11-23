<?php

namespace App\Http\Controllers;

use App\Services\NetworkChronology;
use App\Support\FrenchDepartments;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Page /chronology, « Chronologie du rail en France » : une décennie à la fois (`?decade=1930`), filtrable par
 * types de faits (`?types=opening,closure`) et par région (`?region=53`). Les faits viennent du cache rempli par
 * `vff:compute-network-chronology` (mêmes données que `GET /api/v1/chronology`). Un filtre inconnu est ignoré.
 */
class ChronologyController extends Controller
{
    public function index(Request $request, NetworkChronology $chronology): Response
    {
        [$types, $region] = self::filters($request);
        $overview = $chronology->overview($types, $region);
        [$first, $last] = NetworkChronology::decadeBounds($overview['last_year']);
        $asked = filter_var($request->query('decade'), FILTER_VALIDATE_INT);
        $decade = is_int($asked) && $asked % 10 === 0 && $asked >= $first && $asked <= $last ? $asked : $first;
        $regions = [];
        foreach (FrenchDepartments::REGIONS as $code => $name) {
            $regions[] = ['code' => (string) $code, 'name' => $name];
        }
        usort($regions, fn (array $a, array $b): int => strcoll($a['name'], $b['name']));

        return Inertia::render('Network/Chronology', [
            // Comptes de toutes les années inutiles ici : les années de la décennie portent les leurs.
            'chronology' => self::lighter($chronology->decade($decade, $types, $region, app()->getLocale())) + array_diff_key($overview, ['years' => true]),
            'kinds' => NetworkChronology::KINDS,
            'regions' => $regions,
            'thresholds' => ['deaths' => NetworkChronology::ACCIDENT_MIN_DEATHS, 'units' => NetworkChronology::MATERIAL_MIN_UNITS],
        ]);
    }

    /**
     * Faits sans ce que la page ne montre pas (régions, identifiant de fournisseur) : une décennie chargée pèse
     * jusqu'à 600 faits.
     *
     * @param  array{decade: int, years: list<array<string, mixed>>}  $decade
     * @return array{decade: int, years: list<array<string, mixed>>}
     */
    private static function lighter(array $decade): array
    {
        foreach ($decade['years'] as &$year) {
            $year['facts'] = array_map(fn (array $f): array => array_diff_key($f, ['regions' => true]), $year['facts']);
        }
        unset($year);

        return $decade;
    }

    /**
     * Filtres de l'adresse : types connus (liste séparée par des virgules), région connue ; le reste est ignoré.
     *
     * @return array{0: list<string>, 1: ?string}
     */
    public static function filters(Request $request): array
    {
        $raw = $request->query('types');
        $asked = is_string($raw) ? explode(',', $raw) : (is_array($raw) ? $raw : []);
        $types = array_values(array_filter(NetworkChronology::KINDS, fn (string $k): bool => in_array($k, $asked, true)));
        $region = $request->query('region');
        $region = is_string($region) && isset(FrenchDepartments::REGIONS[$region]) ? $region : null;

        return [$types, $region];
    }
}
