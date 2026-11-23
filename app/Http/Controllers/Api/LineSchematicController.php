<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Era;
use App\Models\Line;
use App\Services\Lines\LineSchematic;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Lignes', "Une ligne en détail : chronologie, gares dans l'ordre des points kilométriques, conversion de coordonnées en PK, signalisation, médias.", weight: 2)]
class LineSchematicController extends Controller
{
    public function __construct(private readonly LineSchematic $schematic) {}

    /**
     * Schéma de ligne.
     *
     * La ligne déroulée par point kilométrique (PK), comme un schéma d'exploitation : gares et haltes avec
     * leur état à l'ère, bifurcations vers les autres lignes, passages à niveau, ouvrages d'art et quatre
     * bandes parallèles (nombre de voies, électrification, signalisation, vitesse maximale). Mêmes données
     * que les sections de la fiche et que les points `/stops`, `/infrastructure`, `/electrification-history`,
     * `/signalling`, `/speeds`, `/level-crossings` et `/structures`, réunies et alignées sur les PK de SNCF
     * Réseau.
     *
     * - `eras` : ères où la ligne a au moins une gare connue (et l'ère courante), avec le nombre de gares
     *   connues et ouvertes : pour une ligne fermée, choisir une ère où elle était ouverte.
     * - `stations[]` : `status` (active, tourist, no_commercial, declassified, uncertain, closed), `open`,
     *   `type` (`station` ou `halt`), `pk_approx` (PK projeté sur le tracé, sans source qui le donne),
     *   `connections` : autres lignes qui touchent la ligne à cette gare dans le
     *   graphe de l'ère.
     * - `junctions[]` : bifurcations en pleine voie, avec les lignes qui y partent ; `pk_approx` est toujours
     *   vrai (PK lu sur la forme SNCF de la ligne, généralisée, ou interpolé entre deux gares).
     * - `bands` : chaque bande dit si elle est renseignée à l'ère (`available`). Voies, électrification et
     *   passages à niveau décrivent le réseau d'aujourd'hui (SNCF Réseau Open Data, ODbL) : vides pour une
     *   ère passée. `tracks[].tracks` est nul pour une voie banalisée ou un régime « _Autre ».
     *   `signalling[].family` : automatic_block, restricted_block, manual_block, telephone, cab, ou nul (sans
     *   cantonnement).
     *
     * Une portion sans donnée n'est pas une portion sans voie, sans courant ou sans vitesse : la source ne la
     * décrit pas.
     *
     * @response array{
     *     line: array{slug: string, code: ?string, name: string},
     *     era_year: int,
     *     is_current_era: bool,
     *     eras: array<array{year: int, is_current: bool, stations: int, open: int}>,
     *     extent: ?array{pk_start_m: int, pk_end_m: int, pk_start_label: string, pk_end_label: string},
     *     stations: array<array{id: int, slug: string, name: string, type: string, pk_m: int, pk_label: string,
     *         pk_approx: bool, status: string, open: bool, is_terminal: bool,
     *         connections: array<array{slug: string, code: ?string, name: string}>}>,
     *     junctions: array<array{pk_m: int, pk_label: string, pk_approx: bool, lines: array<array{slug: string, code: ?string, name: string}>}>,
     *     level_crossings: array{available: bool, items: array<array{pk_m: int, pk_label: string, name: ?string, group: ?string}>},
     *     structures: array{available: bool, items: array<array{id: int, kind: string, name: ?string, length_m: ?int, pk_start_m: int, pk_end_m: int}>},
     *     bands: array{
     *         tracks: array{available: bool, as_of: ?string, ranges: array<array{pk_start_m: int, pk_end_m: int, tracks: ?int, regime: string, banalised: bool}>},
     *         electrification: array{available: bool, as_of: ?string, ranges: array<array{pk_start_m: int, pk_end_m: int, voltage_v: ?int, system: string}>},
     *         signalling: array{available: bool, ranges: array<array{pk_start_m: int, pk_end_m: int, signalling: string, family: ?string, systems: array<string>, train_protection: array<string>}>},
     *         speed: array{available: bool, ranges: array<array{pk_start_m: int, pk_end_m: int, max_speed_kmh: int}>}
     *     },
     *     provenance: array<string, string>
     * }
     */
    #[PathParameter('line', 'Identifiant (slug) de la ligne, tel que renvoyé par la recherche ou par les GeoJSON (`line_slug`). Les lignes du réseau ferré national sont nommées `rfn-` suivi de leur code à 6 chiffres.', example: 'rfn-830000')]
    #[QueryParameter('era', "Année de l'ère (décennie de 1830 à 2030). Par défaut : l'ère courante, 2030, c'est-à-dire le réseau d'aujourd'hui.", type: 'int', example: 2030)]
    #[ApiResponse(200, "Schéma de la ligne à l'ère choisie.", examples: [[
        'line' => ['slug' => 'rfn-536000', 'code' => '536000', 'name' => 'Ligne de Sainte-Pazanne à Pornic'],
        'era_year' => 2030,
        'is_current_era' => true,
        'eras' => [['year' => 2030, 'is_current' => true, 'stations' => 4, 'open' => 4]],
        'extent' => ['pk_start_m' => 34650, 'pk_end_m' => 61190, 'pk_start_label' => '34+650', 'pk_end_label' => '61+190'],
        'stations' => [[
            'id' => 1, 'slug' => 'sainte-pazanne', 'name' => 'Sainte-Pazanne', 'type' => 'station', 'pk_m' => 34650,
            'pk_label' => '34+650', 'pk_approx' => false, 'status' => 'active', 'open' => true, 'is_terminal' => true,
            'connections' => [['slug' => 'rfn-535000', 'code' => '535000', 'name' => 'Ligne de Nantes-Orléans à Saint-Gilles-Croix-de-Vie']],
        ]],
        'junctions' => [],
        'level_crossings' => ['available' => true, 'items' => [['pk_m' => 40120, 'pk_label' => '40+120', 'name' => 'PN 12', 'group' => 'automatic']]],
        'structures' => ['available' => true, 'items' => []],
        'bands' => [
            'tracks' => ['available' => true, 'as_of' => 'today', 'ranges' => [['pk_start_m' => 34650, 'pk_end_m' => 61190, 'tracks' => 1, 'regime' => 'Voie unique', 'banalised' => false]]],
            'electrification' => ['available' => false, 'as_of' => 'today', 'ranges' => []],
            'signalling' => ['available' => true, 'ranges' => [['pk_start_m' => 34650, 'pk_end_m' => 61190, 'signalling' => 'BAPR-VB', 'family' => 'restricted_block', 'systems' => ['BAPR-VB'], 'train_protection' => ['KVB']]]],
            'speed' => ['available' => true, 'ranges' => [['pk_start_m' => 34650, 'pk_end_m' => 61190, 'max_speed_kmh' => 140]]],
        ],
        'provenance' => ['speed' => 'SNCF Réseau Open Data, « vitesse-maximale-nominale-sur-ligne » (ODbL)'],
    ]])]
    #[ApiResponse(404, 'Ligne ou ère inconnue.')]
    public function show(Line $line, Request $request): JsonResponse
    {
        $request->validate(['era' => 'nullable|integer']);

        $era = $request->filled('era')
            ? Era::where('year', $request->integer('era'))->firstOrFail()
            : Era::where('is_current', true)->firstOrFail();

        return response()->json($this->schematic->forLine($line, $era))
            ->header('Cache-Control', 'public, max-age=600');
    }
}
