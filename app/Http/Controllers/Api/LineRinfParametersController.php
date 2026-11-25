<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Line;
use App\Services\Rinf\RinfDataset;
use App\Services\Rinf\RinfParameterComparison;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;

#[Group('Lignes', "Une ligne en détail : chronologie, gares dans l'ordre des points kilométriques, conversion de coordonnées en PK, signalisation, médias.", weight: 2)]
class LineRinfParametersController extends Controller
{
    /**
     * Paramètres RINF d'une ligne, comparés.
     *
     * Paramètres que le RINF (registre de l'infrastructure de l'Agence de l'Union européenne pour les chemins de fer,
     * déclaré par SNCF Réseau et les autres gestionnaires) donne pour chaque voie de chaque section de la ligne, par
     * point kilométrique (PK), **côte à côte** avec ce que VFF tient d'une autre source, et l'accord ou le désaccord
     * des deux. Aucune donnée n'est remplacée : les deux sources sont servies telles quelles. Réseau d'aujourd'hui.
     *
     * **Paramètres** (`parameters[]`, toujours les sept, dans cet ordre ; `compared_with` : fournisseur de l'autre
     * source, nul quand VFF n'en a pas) :
     * - `electrification` : ligne de contact, tension et fréquence ; autre source : jeu des lignes électrifiées de
     *   SNCF Réseau. Une portion d'une ligne que SNCF Réseau décrit, absente de ce jeu, vaut `none` ;
     * - `max_speed` : vitesse maximale (km/h) ; autre source : vitesses de SNCF Réseau. Le RINF donne une vitesse par
     *   voie et par section (d'un point d'exploitation au suivant) : elle est comparée à la plus forte vitesse de SNCF
     *   Réseau sur la section ; hors des sections, la vitesse de SNCF Réseau est servie telle quelle ;
     * - `train_protection` : contrôle de vitesse et protection hérités, niveau ETCS ; autre source : signalisation de
     *   SNCF Réseau (`GET /lines/{line}/signalling`). `key` ne retient que les systèmes que les deux savent nommer
     *   (`KVB`, le KVBP compté comme KVB, `TVM 300`, `TVM 430`, `ETCS L1`, `ETCS L2`, joints par « + », `none` sans
     *   aucun) ; `values` donne tous les systèmes déclarés (Crocodile, DAAT, KCVB, KCVP…) ;
     * - `radio` : `gsm-r` ou `none` ; autre source : radio sol-train de SNCF Réseau ;
     * - `gauging` (gabarits : `GB1`, `FR-3.3`, `GI2`…), `load_capability` (catégorie de ligne EN 15528 dans `key`,
     *   classe nationale du gestionnaire telle quelle dans `values`), `track_gauge` (écartement en mm) : sans autre
     *   source dans VFF (portions `rinf_only`).
     *
     * **Portions** (`portions[]`, dans l'ordre des PK) : `status` vaut `agree`, `disagree`, `rinf_only` (seul le
     * RINF dit quelque chose) ou `vff_only` (seule l'autre source) ; `rinf` et `vff` portent la valeur de chaque
     * côté (nulle quand la source ne dit rien) : `key` (forme comparée : `25000V-50Hz`, `1500V-DC`, `none`, `160`,
     * `KVB+TVM 430`, `gsm-r`), `values` (détail), `voltage_v`, `frequency_hz` (0 en courant continu), `speed_kmh`,
     * `tracks` (voies du RINF d'où vient la valeur : voies principales 1, 2 et unique seules quand la section en a)
     * et `source_label` (libellé de l'autre source tel quel). `rg_troncon` : rang du tronçon de la ligne chez SNCF
     * Réseau (suffixe « -2 » de la ligne nationale du RINF ; deux tronçons peuvent avoir des PK communs), nul pour
     * une comparaison faite sur toute la ligne (vitesses, signalisation). `summary` : longueurs en mètres par statut,
     * et longueurs décrites par chaque source.
     *
     * **Sections** (`sections[]`) : sections du RINF de la ligne (URI, ligne nationale, PK, points d'exploitation
     * de début et de fin, voies). **Quais** (`platforms`) : bords de quai du RINF (longueur, hauteur) des points
     * d'exploitation de la ligne, rapprochés des quais de SNCF Réseau gare par gare (même gare, même nom de quai) ;
     * `agree` quand les longueurs diffèrent de `tolerance_m` mètres au plus ; `pk_m` est le PK du quai chez SNCF
     * Réseau, sinon celui du point d'exploitation.
     *
     * **Licence** : RINF, © Agence de l'Union européenne pour les chemins de fer, dump officiel sous EUPL 1.2 ;
     * données de SNCF Réseau sous ODbL 1.0. Reprenez la mention `attribution` de chaque fiche de `sources[]`.
     * Une ligne que le RINF ne décrit pas renvoie 200 et des listes vides.
     *
     * @response array{
     *     line: array{slug: string, code: ?string, name: string},
     *     sections: array<array{uri: string, national_line: string, rg_troncon: int, pk_start_m: int, pk_end_m: int, pk_start_label: string, pk_end_label: string, length_m: int,
     *         from: array{uopid: string, name: ?string}, to: array{uopid: string, name: ?string}, tracks: array<string>}>,
     *     parameters: array<array{key: string, compared_with: ?string,
     *         summary: array{rinf_m: int, vff_m: int, agree_m: int, disagree_m: int, rinf_only_m: int, vff_only_m: int},
     *         portions: array<array{rg_troncon: ?int, pk_start_m: int, pk_end_m: int, pk_start_label: string, pk_end_label: string, length_m: int, status: string,
     *             rinf: ?array{key: string, values: array<string>, voltage_v: ?int, frequency_hz: ?float, speed_kmh: ?int, tracks: array<string>, source_label: ?string},
     *             vff: ?array{key: string, values: array<string>, voltage_v: ?int, frequency_hz: ?float, speed_kmh: ?int, tracks: array<string>, source_label: ?string}}>}>,
     *     platforms: array{
     *         summary: array{rinf: int, vff: int, agree: int, disagree: int, rinf_only: int, vff_only: int, tolerance_m: int},
     *         items: array<array{pk_m: ?int, station: ?array{slug: string, name: string}, operational_point: ?array{uopid: string, name: string}, ref: string, track: ?string, status: string,
     *             rinf: ?array{length_m: ?int, height_mm: ?int}, vff: ?array{label: string, length_m: ?int, pk_start_m: ?int, pk_end_m: ?int}}>
     *     },
     *     sources: array<array{key: string, label: string, licence: string, dataset: ?string, fetched_at: ?string, url: ?string, attribution: ?string, licence_url: ?string}>
     * }
     */
    #[PathParameter('line', 'Identifiant (slug) de la ligne, tel que renvoyé par la recherche ou par les GeoJSON (`line_slug`). Les lignes du réseau ferré national sont nommées `rfn-` suivi de leur code à 6 chiffres.', example: 'rfn-579000')]
    #[ApiResponse(200, 'Paramètres RINF de la ligne et comparaison (listes vides si le RINF ne la décrit pas).', examples: [[
        'line' => ['slug' => 'rfn-579000', 'code' => '579000', 'name' => 'Ligne de Beillant à Angoulême'],
        'sections' => [[
            'uri' => 'http://data.europa.eu/949/sectionOfLine/579000-1_FR0000003513_FR9900004367', 'national_line' => '579000-1', 'rg_troncon' => 1,
            'pk_start_m' => 17277, 'pk_end_m' => 17917, 'pk_start_label' => '17+277', 'pk_end_label' => '17+917', 'length_m' => 640,
            'from' => ['uopid' => 'FR0000003513', 'name' => 'Cognac'], 'to' => ['uopid' => 'FR9900004367', 'name' => 'Accès à Faisceau Pair'], 'tracks' => ['VU'],
        ]],
        'parameters' => [[
            'key' => 'max_speed', 'compared_with' => 'sncf',
            'summary' => ['rinf_m' => 64902, 'vff_m' => 62866, 'agree_m' => 62866, 'disagree_m' => 0, 'rinf_only_m' => 2036, 'vff_only_m' => 0],
            'portions' => [[
                'rg_troncon' => null, 'pk_start_m' => 17277, 'pk_end_m' => 44427, 'pk_start_label' => '17+277', 'pk_end_label' => '44+427', 'length_m' => 27150, 'status' => 'agree',
                'rinf' => ['key' => '140', 'values' => ['140'], 'voltage_v' => null, 'frequency_hz' => null, 'speed_kmh' => 140, 'tracks' => ['VU'], 'source_label' => null],
                'vff' => ['key' => '140', 'values' => ['140', '110'], 'voltage_v' => null, 'frequency_hz' => null, 'speed_kmh' => 140, 'tracks' => [], 'source_label' => null],
            ]],
        ], [
            'key' => 'train_protection', 'compared_with' => 'sncf',
            'summary' => ['rinf_m' => 64902, 'vff_m' => 64919, 'agree_m' => 64902, 'disagree_m' => 0, 'rinf_only_m' => 0, 'vff_only_m' => 17],
            'portions' => [[
                'rg_troncon' => null, 'pk_start_m' => -235, 'pk_end_m' => 64667, 'pk_start_label' => '-0+235', 'pk_end_label' => '64+667', 'length_m' => 64902, 'status' => 'agree',
                'rinf' => ['key' => 'KVB', 'values' => ['KVB', 'DAAT', 'Crocodile'], 'voltage_v' => null, 'frequency_hz' => null, 'speed_kmh' => null, 'tracks' => ['E', 'V1', 'V2', 'VU'], 'source_label' => null],
                'vff' => ['key' => 'KVB', 'values' => ['KVB', 'BAL', 'BAPR-VB'], 'voltage_v' => null, 'frequency_hz' => null, 'speed_kmh' => null, 'tracks' => [], 'source_label' => 'Block automatique lumineux'],
            ]],
        ]],
        'platforms' => [
            'summary' => ['rinf' => 8, 'vff' => 2, 'agree' => 1, 'disagree' => 1, 'rinf_only' => 6, 'vff_only' => 0, 'tolerance_m' => 5],
            'items' => [[
                'pk_m' => 17240, 'station' => ['slug' => 'cognac', 'name' => 'Cognac'], 'operational_point' => ['uopid' => 'FR0000003513', 'name' => 'Cognac'],
                'ref' => 'Quai VE', 'track' => 'E', 'status' => 'disagree', 'rinf' => ['length_m' => 145, 'height_mm' => 300],
                'vff' => ['label' => 'Quai VE', 'length_m' => 210, 'pk_start_m' => 17240, 'pk_end_m' => 17450],
            ]],
        ],
        'sources' => [RinfDataset::SOURCE_EXAMPLE, RinfParameterComparison::SNCF_SOURCE],
    ]])]
    #[ApiResponse(404, 'Ligne inconnue.', examples: [ErrorExamples::LINE_NOT_FOUND])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function show(Line $line, RinfParameterComparison $comparison): JsonResponse
    {
        return response()->json(['line' => ['slug' => $line->slug, 'code' => $line->code, 'name' => $line->name], ...$comparison->forLine($line)])
            ->header('Cache-Control', 'public, max-age=3600');
    }
}
