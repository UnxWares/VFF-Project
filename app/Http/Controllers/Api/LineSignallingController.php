<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Era;
use App\Models\Line;
use App\Services\Signalling\SignallingRanges;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Lignes', "Une ligne en détail : chronologie, gares dans l'ordre des points kilométriques, conversion de coordonnées en PK, signalisation, médias.", weight: 2)]
class LineSignallingController extends Controller
{
    public function __construct(private readonly SignallingRanges $ranges) {}

    /**
     * Signalisation d'une ligne.
     *
     * Tranches de points kilométriques (PK) homogènes en signalisation, pour
     * l'ère choisie. Données de SNCF Réseau Open Data (jeux
     * `mode-de-cantonnement-des-lignes` et `lignes-equipees-de-kvb` de SNCF Réseau, ODbL :
     * mention « SNCF Réseau », partage à l'identique des bases dérivées), qui
     * couvrent aussi l'ETCS des lignes à grande vitesse récentes. Aujourd'hui seule l'ère courante (2030) est renseignée ; une
     * autre ère renvoie en général une liste vide.
     *
     * Deux notions distinctes :
     * - `signalling` : mode de **cantonnement**, qui espace les trains :
     *   `BAL` (block automatique lumineux), `BAPR` (block automatique à
     *   permissivité restreinte), `BM` (block manuel), `BMVU` (block manuel de
     *   voie unique), `CAPI` (cantonnement assisté par informatique),
     *   `CT` (cantonnement téléphonique de double voie), `TVM-300` / `TVM-430` (signalisation
     *   en cabine des LGV, sans signaux latéraux), `ETCS_L1` / `ETCS_L2`.
     *   Suffixes : `-VB` voie banalisée (circulation dans les deux sens sur
     *   chaque voie), `-VU` voie unique, `-Tram` section tram-train ;
     * - `signalling_systems` : **tous** les systèmes de cantonnement installés sur la
     *   tranche, code principal (`signalling`) en premier. En général un seul ; deux sur les
     *   LGV équipées à la fois de l'ETCS niveau 2 et de la TVM (`["ETCS_L2", "TVM-300"]` sur
     *   la LGV Bretagne - Pays de la Loire et la LGV SEA, `["ETCS_L2", "TVM-430"]` sur la
     *   LGV Est), que SNCF Réseau publie dans un seul libellé ;
     * - `source_label` : libellé de SNCF Réseau tel quel (ex. « European train control
     *   system 2/Transmission voie-machine 300 »), null pour une tranche d'une autre source ;
     * - `train_protection` : systèmes de **contrôle de vitesse** superposés
     *   au cantonnement (`KVB`, `KVBP`, `TVM-300`, `TVM-430`, `ERTMS_L1`,
     *   `ERTMS_L2`). Le KVB est un contrôle de vitesse, pas un mode de
     *   cantonnement ;
     * - `kvb_equipped` : raccourci, la tranche est équipée du KVB ou du KVBP.
     *
     * PK en mètres (`pk_start_m`, `pk_end_m`, `length_m`) et en notation
     * ferroviaire `km+mmm` (`pk_start_label`, `pk_end_label`), dans la
     * numérotation de SNCF Réseau. Les mêmes tranches sont dessinées sur la
     * carte par `GET /eras/{year}/signalling/tiles/{z}/{x}/{y}.mvt`.
     *
     * @response array{
     *     line: array{slug: string, code: ?string, name: string},
     *     era_year: int,
     *     ranges: array<array{
     *         pk_start_m: int, pk_end_m: int,
     *         pk_start_label: string, pk_end_label: string,
     *         length_m: int, signalling: string, signalling_systems: array<string>,
     *         train_protection: array<string>, kvb_equipped: bool,
     *         source_label: ?string, note: ?string
     *     }>
     * }
     */
    #[PathParameter('line', 'Identifiant (slug) de la ligne, tel que renvoyé par la recherche ou par les GeoJSON (`line_slug`). Les lignes du réseau ferré national sont nommées `rfn-` suivi de leur code à 6 chiffres.', example: 'rfn-420000')]
    #[QueryParameter('era', "Année de l'ère (décennie de 1830 à 2030). Par défaut : l'ère courante, 2030, c'est-à-dire le réseau d'aujourd'hui.", type: 'int', example: 2030)]
    #[ApiResponse(200, "Tranches de signalisation de la ligne à l'ère choisie.", examples: [[
        'line' => ['slug' => 'rfn-420000', 'code' => '420000', 'name' => 'Ligne de Paris-Montparnasse à Brest'],
        'era_year' => 2030,
        'ranges' => [[
            'pk_start_m' => 420110, 'pk_end_m' => 436505, 'pk_start_label' => '420+110', 'pk_end_label' => '436+505',
            'length_m' => 16395, 'signalling' => 'BAPR', 'signalling_systems' => ['BAPR'], 'train_protection' => ['KVB'], 'kvb_equipped' => true,
            'source_label' => 'Block automatique à permissivité restreinte de double voie',
            'note' => 'Source : SNCF Open Data, mode-de-cantonnement-des-lignes',
        ]],
    ]])]
    #[ApiResponse(404, 'Ligne ou ère inconnue.')]
    public function index(Line $line, Request $request): JsonResponse
    {
        $request->validate(['era' => 'nullable|integer']);

        $era = $request->filled('era')
            ? Era::where('year', $request->integer('era'))->firstOrFail()
            : Era::where('is_current', true)->firstOrFail();

        $ranges = $this->ranges->forLine($line->id, $era->id);

        return response()->json([
            'line' => ['slug' => $line->slug, 'code' => $line->code, 'name' => $line->name],
            'era_year' => $era->year,
            'ranges' => $ranges,
        ])->header('Cache-Control', 'public, max-age=600');
    }
}
