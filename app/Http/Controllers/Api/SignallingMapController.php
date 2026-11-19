<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Era;
use App\Services\Signalling\SignallingHistoryMap;
use App\Services\Signalling\SignallingMap;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

#[Group('Signalisation et passages à niveau', weight: 8)]
class SignallingMapController extends Controller
{
    /**
     * Tuiles de la signalisation (MVT).
     *
     * Tuile vectorielle Mapbox (protobuf), couche `vff-signalling`. À l'ère courante : les tranches de
     * signalisation des lignes (`GET /lines/{line}/signalling`) dessinées sur les formes des lignes de SNCF Réseau.
     * Propriétés : `signalling` (mode de cantonnement : `BAL`, `BAL-VB`, `BAPR`, `BAPR-VB`, `BM`,
     * `BMVU`, `CT`, `CT-VU`, `CAPI`, `TVM-300`, `TVM-430`, `ETCS_L1`, `ETCS_L2`, `BAL-Tram`),
     * `family` (`automatic_block`, `restricted_block`, `manual_block`, `telephone`, `cab` =
     * signalisation en cabine), `train_protection` (contrôle de vitesse, codes séparés par des
     * virgules : `KVB`, `TVM-300`, `TVM-430`, `ERTMS_L1`, `ERTMS_L2`), `kvb_equipped`,
     * `pk_start_m` et `pk_end_m` (PK de la tranche entière, en mètres), `line_slug`, `line_name`,
     * `line_code`.
     *
     * Le KVB est un contrôle de vitesse, jamais un mode de cantonnement : il n'a pas de `family`.
     *
     * **Placement** : le PK n'est pas la distance mesurée sur le tracé ; il est interpolé entre les
     * passages à niveau dont SNCF Réseau donne à la fois le PK et la position. Mesure du 19/11/2026 :
     * une limite de tranche tombe à 34 m près en médiane, à moins de 190 m dans 9 cas sur 10.
     * Environ 1 % des kilomètres ne sont pas dessinés (ligne sans forme SNCF Réseau).
     *
     * Ère courante (2030, le réseau d'aujourd'hui) : données SNCF Réseau (jeux `mode-de-cantonnement-des-lignes`
     * et `lignes-equipees-de-kvb`, ODbL), décrites ci-dessus.
     *
     * **Ères passées** (1830 à 2020) : historique daté, pas un état complet. Chaque trait est une mise en
     * service relevée dans un article Wikipédia (`GET /lines/{line}/signalling-history`, faits sûrs seulement),
     * encore en vigueur au 31 décembre de l'année de l'ère : un fait plus récent du même groupe (cantonnement,
     * TVM, ETCS) qui recouvre sa portion le remplace. Une ligne sans trait n'est pas forcément sans
     * signalisation : l'article ne datait rien. Propriétés : `history` (`true`), `signalling` (système :
     * `BAL`, `BAPR`, `BM`, `CT`, `TVM-300`, `TVM-430`, `TVM`, `ETCS_L1`, `ETCS_L2`, `ETCS`), `family` (comme
     * ci-dessus), `role` (`block` ou `cab`), `date` (`AAAA-MM-JJ`) et `date_precision` (`day`, `month`,
     * `year`), `scope` (`whole_line`, `section`, `unspecified`), `location` (`section` : trait sur la portion
     * placée par ses gares d'extrémité ou ses PK ; `line` : toute la ligne, d'après l'article ; `unknown` :
     * toute la ligne de l'ère, portion inconnue ou non placée), `section_from` et `section_to` (lieux tels
     * qu'écrits), `pk_start_m` et `pk_end_m` (PK de la portion placée, en mètres), `origin` (`text`, `list`,
     * `table`, `infobox`, `opening` : TVM d'une LGV datée de son ouverture), `source_title` et `source_url`
     * (permalien de la révision lue, CC BY-SA 4.0), `sort` (ordre d'empilement : les faits récents et les
     * portions placées dessus), `line_slug`, `line_name`, `line_code`. Le trait suit le tracé de la ligne à
     * cette ère (une portion fermée n'est pas dessinée). Le KVB, contrôle de vitesse, n'y figure pas.
     *
     * Tuiles en cache une journée (navigateurs et serveur, ce dernier jusqu'au zoom 10).
     */
    #[PathParameter('year', "Année de l'ère (décennie de 1830 à 2030) : 2030 pour l'état actuel de SNCF Réseau, une ère passée pour l'historique daté.", example: 2030)]
    #[PathParameter('z', 'Niveau de zoom (0 à 22 ; la carte VFF demande au plus 14).', example: 6)]
    #[PathParameter('x', 'Colonne de la tuile dans la grille XYZ.', example: 32)]
    #[PathParameter('y', 'Rangée de la tuile dans la grille XYZ.', example: 22)]
    #[ApiResponse(200, 'Tuile MVT (protobuf binaire), éventuellement vide.')]
    #[ApiResponse(404, 'Ère inconnue, ou tuile hors de la grille XYZ.', examples: [ErrorExamples::ERA_NOT_FOUND])]
    public function tile(int $year, int $z, int $x, int $y, SignallingMap $map, SignallingHistoryMap $history): Response|JsonResponse
    {
        abort_unless($z <= 22 && $x < (1 << $z) && $y < (1 << $z), 404);
        $era = Era::where('year', $year)->first();
        if ($era === null) {
            return response()->json(ErrorExamples::eraNotFound($year), 404);
        }

        $tile = $era->is_current ? $map->tile($era->id, $z, $x, $y) : $history->tile($era->id, $z, $x, $y);

        return response($tile, 200, [
            'Content-Type' => 'application/x-protobuf',
            'Cache-Control' => 'public, max-age=86400',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }
}
