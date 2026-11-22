<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Line;
use App\Services\Sncf\LineInfrastructure;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;

#[Group('Lignes', "Une ligne en détail : chronologie, gares dans l'ordre des points kilométriques, conversion de coordonnées en PK, signalisation, médias.", weight: 2)]
class LineInfrastructureController extends Controller
{
    /**
     * Voies et radio d'une ligne.
     *
     * Infrastructure actuelle d'une ligne du réseau ferré national, par portion de points kilométriques (PK),
     * d'après deux jeux de SNCF Réseau Open Data. Le réseau décrit est celui d'aujourd'hui (jeux modifiés le
     * `dataset_modified` de chaque bloc) : aucune ère, aucune date de mise en service. Une ligne absente des
     * jeux (ligne fermée, ligne hors du réseau ferré national) renvoie des listes vides et un `summary` nul.
     *
     * **Voies** (`tracks`, jeu `regime-dexploitation-des-lignes`) : régime d'exploitation de chaque portion.
     * - `portions[]` : PK en mètres (`pk_start_m`, `pk_end_m`, `length_m`) et en notation `km+mmm`
     *   (`pk_start_label`, `pk_end_label`), dans la numérotation de SNCF Réseau ; `regime`, libellé de SNCF
     *   Réseau tel quel (« Double voie », « Voie unique », « Voie unique à signalisation simplifiée »,
     *   « Voie banalisée », « Régime en navette »…) ; `tracks`, nombre de voies principales qu'il établit
     *   (2 pour la double voie, 1 pour la voie unique et ses variantes ou la navette), **nul** pour la
     *   « voie banalisée » (chaque voie parcourue dans les deux sens, sans nombre de voies dit : Saint-Gervais -
     *   Vallorcine est à voie unique, Saint-André-le-Gaz - Chambéry à double voie) et pour « _Autre » ;
     *   `banalised` vrai pour une portion banalisée. Un PK illisible dans le jeu donne des PK nuls.
     * - `summary` (nul sans portion au nombre établi) : `tracks`, nombre dominant en longueur ; `uniform`,
     *   vrai quand il couvre 90 % de la longueur décrite ou plus, portions banalisées et « _Autre »
     *   comprises dans le total ; `dominant_share` (0 à 1, même total) et `dominant_length_m` ;
     *   `length_m_by_tracks` (longueur par nombre de voies) ; `banalised_length_m`. Une portion au PK illisible
     *   pèse 1 000 m dans ces sommes.
     *
     * **Radio** (`radio`, jeu `lignes-equipees-de-liaison-radio-sol-train`) : liaison radio sol-train.
     * `system` vaut `gsm-r` (GSM-R, réseau GSM ferroviaire), `gsm-gfu` (GSM d'un opérateur public en groupe
     * fermé d'utilisateurs), `rst` (radio sol-train analogique sans transmission de données), `rst-data`
     * (analogique avec transmission de données) ou `rst-id` (analogique sans données, avec identification) ;
     * `label` garde le libellé de SNCF Réseau tel quel, fautes comprises. `systems` donne la longueur par
     * système, du plus récent au plus ancien ; `gsm_r` et `gsm_r_length_m` résument l'équipement GSM-R.
     *
     * **Licence** : données SNCF Réseau, ODbL 1.0. Reproduisez la mention du champ `attribution` et publiez
     * sous la même licence toute base dérivée que vous diffusez ; `provenance` donne le fournisseur et la
     * licence, comme les autres réponses de l'API. Le nombre de voies de la fiche (`facts`, clé `tracks`, de
     * `GET /lines/{line}`) en est tiré.
     *
     * @response array{
     *     line: array{slug: string, code: ?string, name: string},
     *     tracks: array{
     *         summary: ?array{tracks: int, uniform: bool, dominant_share: float, dominant_length_m: int,
     *             length_m_by_tracks: array<array{tracks: int, length_m: int}>, banalised_length_m: int},
     *         portions: array<array{pk_start_m: ?int, pk_end_m: ?int, pk_start_label: ?string, pk_end_label: ?string, length_m: ?int,
     *             tracks: ?int, regime: string, banalised: bool}>,
     *         dataset_modified: ?string
     *     },
     *     radio: array{
     *         systems: array<array{system: string, length_m: int}>,
     *         gsm_r: bool,
     *         gsm_r_length_m: int,
     *         portions: array<array{pk_start_m: ?int, pk_end_m: ?int, pk_start_label: ?string, pk_end_label: ?string, length_m: ?int,
     *             system: string, label: string}>,
     *         dataset_modified: ?string
     *     },
     *     attribution: string,
     *     provenance: array{provider: ?string, name: ?string, license: ?string, license_status: ?string}
     * }
     */
    #[PathParameter('line', 'Identifiant (slug) de la ligne, tel que renvoyé par la recherche ou par les GeoJSON (`line_slug`). Les lignes du réseau ferré national sont nommées `rfn-` suivi de leur code à 6 chiffres.', example: 'rfn-579000')]
    #[ApiResponse(200, 'Voies et radio de la ligne (listes vides si les jeux ne la couvrent pas).', examples: [[
        'line' => ['slug' => 'rfn-579000', 'code' => '579000', 'name' => 'Ligne de Beillant à Angoulême'],
        'tracks' => [
            'summary' => [
                'tracks' => 1, 'uniform' => true, 'dominant_share' => 0.983, 'dominant_length_m' => 63812,
                'length_m_by_tracks' => [['tracks' => 1, 'length_m' => 63812], ['tracks' => 2, 'length_m' => 1107]], 'banalised_length_m' => 0,
            ],
            'portions' => [[
                'pk_start_m' => -252, 'pk_end_m' => 855, 'pk_start_label' => '-0+252', 'pk_end_label' => '0+855', 'length_m' => 1107,
                'tracks' => 2, 'regime' => 'Double voie', 'banalised' => false,
            ], [
                'pk_start_m' => 855, 'pk_end_m' => 64667, 'pk_start_label' => '0+855', 'pk_end_label' => '64+667', 'length_m' => 63812,
                'tracks' => 1, 'regime' => 'Voie unique', 'banalised' => false,
            ]],
            'dataset_modified' => '2022-03-24',
        ],
        'radio' => [
            'systems' => [['system' => 'gsm-gfu', 'length_m' => 64287]],
            'gsm_r' => false, 'gsm_r_length_m' => 0,
            'portions' => [[
                'pk_start_m' => -252, 'pk_end_m' => 64035, 'pk_start_label' => '-0+252', 'pk_end_label' => '64+035', 'length_m' => 64287,
                'system' => 'gsm-gfu', 'label' => "Global System for Mobil- Groupe Fermé d'utilisateurs",
            ]],
            'dataset_modified' => '2022-03-24',
        ],
        'attribution' => LineInfrastructure::ATTRIBUTION,
        'provenance' => ['provider' => 'sncf', 'name' => 'SNCF Open Data (SNCF Réseau, Gares & Connexions, GTFS voyageurs)', 'license' => "ODbL 1.0 (chaque jeu utilisé : champ metas.default.license de l'API)", 'license_status' => 'verified'],
    ]])]
    #[ApiResponse(404, 'Ligne inconnue.', examples: [ErrorExamples::LINE_NOT_FOUND])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function index(Line $line, LineInfrastructure $infrastructure): JsonResponse
    {
        return response()->json([
            'line' => ['slug' => $line->slug, 'code' => $line->code, 'name' => $line->name],
            ...$infrastructure->forLine((int) $line->id),
            'attribution' => LineInfrastructure::ATTRIBUTION,
            'provenance' => $infrastructure->provenance(),
        ])->header('Cache-Control', 'public, max-age=3600');
    }
}
