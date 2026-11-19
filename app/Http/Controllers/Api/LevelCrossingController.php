<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ListLevelCrossingsRequest;
use App\Http\Resources\LevelCrossingResource;
use App\Models\Line;
use App\Services\Api\Provenance;
use App\Services\LevelCrossings\LevelCrossingQuery;
use App\Services\LevelCrossings\LevelCrossingTiles;
use App\Services\LevelCrossings\LineLevelCrossings;
use App\Support\MapTiles;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

#[Group('Signalisation et passages à niveau', "L'équipement des lignes aujourd'hui, d'après SNCF Réseau (ODbL) : passages à niveau avec leur classe, leur PK et leur commune ; tranches de signalisation sur la carte.", weight: 8)]
class LevelCrossingController extends Controller
{
    /**
     * Liste des passages à niveau.
     *
     * Les quelque 16 800 passages à niveau (PN) du réseau ferré national publiés par SNCF Réseau
     * (jeu `liste-des-passages-a-niveau`), filtrables et paginés, dans l'ordre des lignes puis des
     * points kilométriques (PK inconnus en dernier). L'état est celui de la publication du jeu :
     * un PN supprimé avant n'y figure pas, et aucune date d'ouverture ou de suppression n'est connue.
     *
     * **Classes** (`class`, `group`) : définitions officielles de SNCF Réseau, reprises dans
     * `class_label`. Un même passage situé sur plusieurs lignes n'a sa classe que sur l'une d'elles ;
     * sur les autres, c'est un PN secondaire (classe 0, groupe `secondary`), dont `crossed_way` cite le
     * PN qui porte la classe. Pour compter des passages, excluez `secondary`.
     *
     * **Position** : coordonnées WGS 84 de SNCF Réseau, au centre du croisement de la route et de la
     * voie (à moins d'un mètre pour plus de 90 % des PN selon le producteur).
     *
     * **Licence** : ODbL 1.0. Mentionnez « SNCF Réseau » ; une base dérivée publiquement utilisée se
     * partage sous la même licence.
     *
     * **Pagination** : par pages (`page`, `per_page` de 1 à 200, 50 par défaut), avec `links` et
     * `meta` comme les autres listes.
     */
    #[QueryParameter('line', 'Slug d\'une ligne VFF (ex. `rfn-70000`) : PN de cette ligne, rattachés par son code SNCF Réseau. Ligne inconnue : HTTP 422.', type: 'string', example: 'rfn-70000')]
    #[QueryParameter('group', 'Groupe de classes : `automatic` (barrières automatiques, classes 17 à 19), `guarded` (gardés, 10 à 16), `no_barrier` (sans barrières, 20 et 21), `pedestrian` (piétons, 31 et 32), `private` (privés, 41 à 46), `secondary` (classe 0).', type: 'string', example: 'automatic')]
    #[QueryParameter('class', 'Classe SNCF Réseau exacte : 0, 10 à 21, 31, 32, 41 à 46.', type: 'int', example: 17)]
    #[QueryParameter('commune', 'Partie du nom de la commune, sans accents ni capitales (2 à 100 caractères).', type: 'string', example: 'courthiezy')]
    #[QueryParameter('bbox', 'Emprise `ouest,sud,est,nord` en degrés WGS 84 (point décimal), ouest < est et sud < nord.', type: 'string', example: '3.5,49.0,3.7,49.1')]
    #[QueryParameter('per_page', 'Nombre de PN par page (1 à 200).', type: 'int', default: 50, example: 50)]
    #[QueryParameter('page', 'Numéro de page, à partir de 1.', type: 'int', default: 1, example: 1)]
    #[ApiResponse(200, 'Une page de la liste des passages à niveau.', examples: [LevelCrossingExamples::LIST])]
    #[ApiResponse(422, 'Paramètre invalide (ligne inconnue, groupe ou classe inconnus, emprise mal formée, `per_page` hors limites) : détail par paramètre dans `errors`.', examples: [LevelCrossingExamples::INVALID])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function index(ListLevelCrossingsRequest $request, LevelCrossingQuery $query): JsonResponse
    {
        $page = $query->query($request->filters())->paginate($request->perPage())->withQueryString();

        return LevelCrossingResource::collection($page)->response()->header('Cache-Control', 'public, max-age=3600');
    }

    /**
     * Passages à niveau d'une ligne.
     *
     * Tous les PN d'une ligne, dans l'ordre des points kilométriques, avec leur nombre par groupe
     * (`summary`, groupes absents omis ; `unknown` pour une classe que la source ne donne pas). Les
     * PN secondaires (`secondary`) sont comptés à part : ils sont déjà comptés sur une autre ligne.
     * Liste vide pour une ligne sans code SNCF Réseau (ligne disparue ou hors réseau ferré national).
     * Champs de chaque PN : voir la liste des passages à niveau.
     */
    #[PathParameter('line', 'Identifiant (slug) de la ligne, tel que renvoyé par la recherche ou par les GeoJSON (`line_slug`).', example: 'rfn-70000')]
    #[ApiResponse(200, 'Passages à niveau de la ligne.', examples: [LevelCrossingExamples::LINE])]
    #[ApiResponse(404, 'Ligne inconnue.', examples: [ErrorExamples::LINE_NOT_FOUND])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function forLine(Line $line, LineLevelCrossings $crossings, Provenance $provenance): JsonResponse
    {
        $list = $crossings->list($line);

        return response()->json([
            'line' => ['slug' => $line->slug, 'code' => $line->code, 'name' => $line->name],
            /** Nombre de PN, secondaires compris. */
            'total' => $list->count(),
            /** @var list<array{group: string, count: int}> */
            'summary' => $crossings->summary($list),
            'level_crossings' => LevelCrossingResource::collection($list),
            'provenance' => $provenance->for('sncf_level_crossings', 'sncf'),
        ])->header('Cache-Control', 'public, max-age=3600');
    }

    /**
     * Tuiles des passages à niveau (MVT).
     *
     * Tuile vectorielle Mapbox (protobuf), couche `vff-level-crossings`, un point par PN : `id`,
     * `label`, `class`, `group`, `pk_m`, `commune`, `crossed_way`, `line_code`, `line_slug`,
     * `line_name` (mêmes sens que dans la liste). Les PN secondaires sont omis (déjà dessinés sur la
     * ligne qui porte leur classe). Tuiles vides sous le zoom 9 : à l'échelle de la France, 16 800
     * points ne se lisent pas. Mises en cache une journée.
     */
    #[PathParameter('z', 'Niveau de zoom, de 0 à 14 (vide sous 9 ; au-delà de 14, MapLibre agrandit la tuile 14).', example: 11)]
    #[PathParameter('x', 'Colonne de la tuile dans la grille XYZ.', example: 1044)]
    #[PathParameter('y', 'Rangée de la tuile dans la grille XYZ.', example: 709)]
    #[ApiResponse(200, 'Tuile MVT (protobuf binaire), éventuellement vide.')]
    #[ApiResponse(404, 'Tuile hors de la grille XYZ, ou zoom supérieur à 14.')]
    public function tile(int $z, int $x, int $y, LevelCrossingTiles $tiles): Response
    {
        abort_unless(MapTiles::onGrid($z, $x, $y), 404);

        return response($tiles->tile($z, $x, $y), 200, [
            'Content-Type' => 'application/x-protobuf',
            'Cache-Control' => 'public, max-age=86400',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }
}
