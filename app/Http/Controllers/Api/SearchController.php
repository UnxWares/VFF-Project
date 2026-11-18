<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Line;
use App\Models\Material;
use App\Models\Station;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

#[Group('Recherche', 'Trouver une ligne, une gare ou un matériel roulant par son nom.', weight: 0)]
class SearchController extends Controller
{
    /**
     * Recherche de lignes, gares et matériels.
     *
     * Recherche par nom pour l'autocomplétion : jusqu'à 8 lignes (nom ou code
     * à 6 chiffres), 8 gares (celles dont le nom commence par le texte
     * d'abord, avec leur ville et le code de leur département pour distinguer
     * les homonymes) et 5 matériels roulants. Insensible à la casse et aux
     * accents (« saint-etienne » trouve « Saint-Étienne »). Moins de 2
     * caractères : listes vides. Les `slug` renvoyés servent d'identifiants dans les autres
     * endpoints (`/lines/{line}/…`, `from` et `to` de l'itinéraire).
     *
     * @response array{
     *     lines: array<array{type: 'line', slug: string, name: string, code: ?string}>,
     *     stations: array<array{type: 'station', slug: string, name: string, city: ?string, department_code: ?string, lat: float, lng: float}>,
     *     materials: array<array{type: 'material', slug: string, name: string, kind: string, builder: ?string}>
     * }
     */
    #[QueryParameter('q', 'Texte recherché, 2 caractères au moins : nom de ligne, code de ligne (ex. `420000`), nom de gare ou de matériel.', required: true, type: 'string', example: 'brest')]
    #[ApiResponse(200, 'Résultats groupés par catégorie.', examples: [[
        'lines' => [['type' => 'line', 'slug' => 'rfn-420000', 'name' => 'Ligne de Paris-Montparnasse à Brest', 'code' => '420000']],
        'stations' => [['type' => 'station', 'slug' => 'brest', 'name' => 'Brest', 'city' => 'Brest', 'department_code' => '29', 'lat' => 48.3876941, 'lng' => -4.4800927]],
        'materials' => [],
    ]])]
    public function index(Request $request): JsonResponse
    {
        $q = self::normalizeQuery($request->query('q'));
        if (mb_strlen($q) < 2) {
            return response()->json(['lines' => [], 'stations' => [], 'materials' => []]);
        }

        // Jokers LIKE (% _) et caractère d'échappement (\) pris au pied de la
        // lettre : « 100% » cherche « 100% », « %% » ne renvoie pas tout.
        $needle = addcslashes($q, '\\%_');
        $like = '%'.$needle.'%';

        // unaccent (extension installée par la migration PostGIS) : « Saint-Etienne »
        // trouve « Saint-Étienne », comme on le tape souvent.
        $lines = Line::query()
            ->where(function ($w) use ($like) {
                $w->whereRaw('unaccent(name) ILIKE unaccent(?)', [$like])
                    ->orWhereRaw('unaccent(code) ILIKE unaccent(?)', [$like]);
            })
            ->limit(8)
            ->get(['slug', 'name', 'code'])
            ->map(fn ($l) => [
                'type' => 'line',
                'slug' => $l->slug,
                'name' => $l->name,
                'code' => $l->code,
            ]);

        $stations = DB::select(
            <<<'SQL'
            SELECT slug, name, city, department_code,
                   ST_X(geom) AS lng, ST_Y(geom) AS lat
            FROM stations
            WHERE unaccent(name) ILIKE unaccent(?)
            ORDER BY
              CASE WHEN unaccent(name) ILIKE unaccent(?) THEN 0 ELSE 1 END,
              name ASC
            LIMIT 8
            SQL,
            [$like, $needle.'%'],
        );

        $stationsOut = array_map(
            fn ($r) => [
                'type' => 'station',
                'slug' => $r->slug,
                'name' => $r->name,
                'city' => $r->city,
                'department_code' => $r->department_code,
                'lat' => (float) $r->lat,
                'lng' => (float) $r->lng,
            ],
            $stations,
        );

        $materials = Material::query()
            ->whereRaw('unaccent(name) ILIKE unaccent(?)', [$like])
            ->limit(5)
            ->get(['slug', 'name', 'type', 'builder'])
            ->map(fn ($m) => [
                'type' => 'material',
                'slug' => $m->slug,
                'name' => $m->name,
                'kind' => $m->type,
                'builder' => $m->builder,
            ]);

        return response()->json([
            'lines' => $lines,
            'stations' => $stationsOut,
            'materials' => $materials,
        ])->header('Cache-Control', 'public, max-age=30');
    }

    /**
     * Requête utilisable telle quelle par PostgreSQL : chaîne seulement (?q[]=…
     * provoquait « Array to string conversion »), UTF-8 valide et sans caractère
     * de contrôle (l'octet nul ou un octet invalide faisaient échouer la requête SQL).
     */
    private static function normalizeQuery(mixed $raw): string
    {
        if (! is_string($raw)) {
            return '';
        }
        $q = mb_scrub($raw, 'UTF-8');

        return trim((string) preg_replace('/[\x{0000}-\x{001F}\x{007F}]+/u', ' ', $q));
    }
}
