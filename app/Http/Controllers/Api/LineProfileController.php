<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Line;
use App\Services\Profiles\LineProfiles;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Lignes', "Une ligne en détail : chronologie, gares dans l'ordre des points kilométriques, conversion de coordonnées en PK, signalisation, médias.", weight: 2)]
class LineProfileController extends Controller
{
    /** Exemple réel (Saint-Gervais-les-Bains-Le Fayet - Vallorcine, calculé le 2026-11-19), listes raccourcies. */
    public const EXAMPLE = [
        'line' => ['slug' => 'rfn-896000', 'code' => '896000', 'name' => 'Ligne de St-Gervais-les-Bains-Le Fayet à Vallorcine (frontière)'],
        'era_year' => 2030,
        'step_m' => 100,
        'length_m' => 36651,
        'trace_length_m' => 36651,
        'summary' => [
            'min_altitude_m' => 578.3, 'max_altitude_m' => 1379.4, 'ascent_m' => 805, 'descent_m' => 254,
            'max_grade_permil' => 90.9, 'max_grade_from_m' => 4600, 'max_grade_to_m' => 5600, 'max_grade_direction' => 'up',
        ],
        'points' => [[0, 578.9], [100, 578.3], [200, 578.4], [300, 579.5]],
        'structures' => [['from_m' => 4100, 'to_m' => 4300, 'kind' => 'tunnel']],
        'altitude_sources' => ['bdtopo' => 339, 'rge_alti' => 5, 'interpolated' => 24, 'missing' => 0],
        'gaps' => [],
        'stations' => [
            ['slug' => 'st-gervais-les-bains-le-fayet', 'name' => 'St-Gervais-les-Bains-Le Fayet', 'distance_m' => 145, 'altitude_m' => 578.4, 'pk_m' => 0],
            ['slug' => 'vallorcine', 'name' => 'Vallorcine', 'distance_m' => 34146, 'altitude_m' => 1260.7, 'pk_m' => 34115],
        ],
        'computed_at' => '2026-11-19T06:05:28+01:00',
        'provenance' => [
            'provider' => 'ign',
            'name' => 'IGN (BD TOPO, BD ORTHO historique, RGE ALTI)',
            'license' => 'Licence Ouverte 2.0 (Etalab), données publiques de l\'IGN libres depuis le 1er janvier 2021',
            'license_status' => 'verified',
        ],
    ];

    /**
     * Profil en long d'une ligne.
     *
     * Altitude le long du tracé, comme le profil d'un livret de ligne, avec les gares placées
     * dessus et quelques chiffres : altitudes extrêmes, dénivelés cumulés, déclivité la plus forte.
     *
     * **Parcours** : le tracé de l'ère courante (sinon de la dernière ère qui a un tracé), voies de
     * service exclues, assemblé de bout en bout le long de l'artère principale (épis et
     * raccordements laissés de côté) et orienté de la première gare de la ligne (plus petit PK)
     * vers la dernière. `points` donne `[distance_m, altitude_m]` : distance en mètres mesurée le
     * long de ce parcours depuis son origine (ce n'est PAS le PK officiel, dont l'origine et les
     * sauts diffèrent : le PK de chaque gare est dans `stations[].pk_m`), altitude en mètres
     * (NGF-IGN69), arrondie au décimètre, `null` sans valeur. Un point tous les `step_m` mètres
     * (100 à 250 m selon la longueur de la ligne).
     *
     * **Altitudes**, de l'IGN, sous Licence Ouverte 2.0 (mention « IGN ») : altitude de la voie
     * dans la BD TOPO (géométrie 3D, tronçon à moins de 20 m du parcours) ; à défaut, altitude du
     * terrain (RGE ALTI, service de calcul altimétrique de la Géoplateforme) ; dans un tunnel ou
     * sur un pont sans altitude, interpolation linéaire entre les deux têtes de l'ouvrage ;
     * rien hors de France. Les points qui s'écartent de plus de 20 m de leurs voisins sont
     * interpolés. `altitude_sources` compte les points de chaque origine (`bdtopo`, `rge_alti`,
     * `interpolated`, `missing`).
     *
     * **Chiffres** (`summary`) : `ascent_m` et `descent_m` = dénivelés positif et négatif cumulés
     * dans le sens du parcours, sans compter les variations de moins de 3 m (bruit) ;
     * `max_grade_permil` = déclivité la plus forte en ‰ (mètres par kilomètre), pente moyenne par
     * moindres carrés sur une fenêtre de 1 km, entre `max_grade_from_m` et `max_grade_to_m` ;
     * `max_grade_direction` : `up` (rampe dans le sens du parcours) ou `down` (pente). Valeurs
     * indicatives : quelques ‰ d'écart avec la déclivité officielle sont courants.
     *
     * **Autres listes** : `structures` = tunnels (`tunnel`) et ponts (`bridge`) de la BD TOPO sous
     * le parcours, en distances ; `gaps` = lacunes du tracé, sans altitude ; `stations` = gares de
     * la ligne à moins de 500 m du parcours, avec leur distance, leur altitude et leur PK officiel
     * (`pk_m`, en mètres, null s'il est inconnu).
     *
     * Les profils sont calculés à l'avance, ligne par ligne : une ligne sans profil répond 404 avec
     * `error` = `profile_not_computed`.
     *
     * @response array{
     *     line: array{slug: string, code: ?string, name: string},
     *     era_year: int, step_m: int, length_m: int, trace_length_m: int,
     *     summary: array{min_altitude_m: float, max_altitude_m: float, ascent_m: float, descent_m: float,
     *         max_grade_permil: float, max_grade_from_m: int, max_grade_to_m: int, max_grade_direction: string},
     *     points: array<array{0: int, 1: float|null}>,
     *     structures: array<array{from_m: int, to_m: int, kind: string}>,
     *     altitude_sources: array{bdtopo: int, rge_alti: int, interpolated: int, missing: int},
     *     gaps: array<array{from_m: int, to_m: int}>,
     *     stations: array<array{slug: string, name: string, distance_m: int, altitude_m: float|null, pk_m: int|null}>,
     *     computed_at: string,
     *     provenance: array{provider: ?string, name: ?string, license: ?string, license_status: ?string}
     * }
     */
    #[PathParameter('line', 'Identifiant (slug) de la ligne, tel que renvoyé par la recherche ou par les GeoJSON (`line_slug`). Les lignes du réseau ferré national sont nommées `rfn-` suivi de leur code à 6 chiffres.', example: 'rfn-896000')]
    #[QueryParameter('max_points', 'Nombre de points au plus (10 à 5 000) : profil allégé, points régulièrement espacés plus les deux bouts, le point le plus bas, le plus haut et les bords des trous. Par défaut : tous les points.', type: 'int', example: 200)]
    #[ApiResponse(200, 'Profil en long de la ligne.', examples: [self::EXAMPLE])]
    #[ApiResponse(404, 'Ligne inconnue, ou profil pas encore calculé pour cette ligne (`error` = `profile_not_computed`).', examples: [['error' => 'profile_not_computed', 'message' => 'Le profil en long de cette ligne n\'est pas encore calculé.']])]
    #[ApiResponse(422, 'Paramètre invalide (`max_points` hors de 10 à 5 000).', examples: [['message' => 'The max points field must be between 10 and 5000.', 'errors' => ['max_points' => ['The max points field must be between 10 and 5000.']]]])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function show(Line $line, Request $request, LineProfiles $profiles): JsonResponse
    {
        $request->validate(['max_points' => 'nullable|integer|between:10,5000']);

        $profile = $profiles->forLine($line, $request->filled('max_points') ? $request->integer('max_points') : null);
        if ($profile === null) {
            return response()->json([
                'error' => 'profile_not_computed',
                'message' => 'Le profil en long de cette ligne n\'est pas encore calculé.',
            ], 404);
        }

        return response()->json([
            'line' => ['slug' => $line->slug, 'code' => $line->code, 'name' => $line->name],
            ...$profile,
        ])->header('Cache-Control', 'public, max-age=3600');
    }
}
