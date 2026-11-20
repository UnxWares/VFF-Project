<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Era;
use App\Services\Schematic\SchematicMap;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\Response;

#[Group('Cartographie par ère', "Le réseau tel qu'il était à la fin d'une décennie : tracés et gares en GeoJSON, tuiles vectorielles MVT pour MapLibre.", weight: 1)]
class SchematicMapController extends Controller
{
    public function __construct(private readonly SchematicMap $schematic) {}

    /**
     * Plan schématique d'une ère (vue ferroviaire).
     *
     * Le réseau en service à une ère dessiné comme un plan de transport : c'est la « vue
     * ferroviaire » de la carte `/maps` (`/maps?view=schema&era=1930`). Les axes sont redressés à
     * 0, 45 ou 90 degrés, les nœuds (bifurcations, gares de correspondance, terminus) posés sur une
     * grille, les gares intermédiaires réparties le long de leur axe. Les grandes agglomérations
     * (Paris, Lyon, Lille, Marseille) sont agrandies, comme sur les plans de réseau.
     *
     * **Un schéma, pas une carte** : aucune coordonnée n'est une position réelle. Les nœuds sont
     * arrondis à une grille de `grid_m` mètres Web Mercator (6 000 m, environ 4 km sur le terrain en
     * France) et parfois déplacés de quelques pas ; une gare intermédiaire est posée sur un segment
     * redressé, à mi-chemin entre sa position réelle le long de la ligne et un espacement régulier.
     * Les coordonnées (WGS 84, longitude puis latitude, 5 décimales) servent à dessiner le plan dans
     * MapLibre ou OpenLayers (projection Web Mercator : les segments y restent droits et à 45°). Pour
     * la géographie, servez-vous de `/eras/{year}/lines.geojson` et `/eras/{year}/stations.geojson`.
     *
     * Le plan est calculé à partir du graphe de l'ère (voies en service, gares ouvertes) et gardé en
     * cache jusqu'à la reconstruction de ce graphe (`graph_built_at`). Il n'existe pas pour une ère
     * sans graphe (404).
     *
     * **Entités** (`features`) :
     * - `kind: axis` (LineString) : morceau d'axe d'une seule ligne, `line_id` (clé de `lines`, ou
     *   `null` pour un raccord) et `category` : `lgv` (grande vitesse), `mixed` (réseau classique
     *   ouvert aux voyageurs, donc aussi au fret), `freight` (fret seul), `unknown` (trafic non
     *   renseigné), `heritage` (exploitée par une association). Sur une voie partagée, la ligne la
     *   plus active est dessinée.
     * - `kind: station` (Point) : gare ouverte à l'ère, `id`, `slug`, `name`, `city`,
     *   `department_code`, `status` (`active`, `tourist`, `no_commercial`, `uncertain`) et sa place
     *   dans le plan : `role` (`stop` gare intermédiaire, `hub` nœud d'au moins trois axes, `end`
     *   terminus), `main` (vrai pour la gare qui nomme son nœud ; les autres gares fondues dans le
     *   même nœud sont au même point), `angle` (direction de l'axe en degrés, 0 = est, 90 = nord : une gare intermédiaire se dessine
     *   en trait perpendiculaire), `rank` (importance : population de la commune, rôle) et `tier`
     *   (niveau d'étiquette : 0 pour les 24 premières villes, une gare par commune, 1 jusqu'à 120,
     *   2 jusqu'à 600, 3 pour les autres).
     *
     * **Autres membres** : `era`, `is_current` (vrai pour le réseau d'aujourd'hui), `graph_built_at`,
     * `grid_m`, `summary` (`km` : longueur des voies en service du graphe par catégorie, en km
     * arrondis ; `stations`, `axes`, `lines`) et `lines` (fiche courte de chaque ligne dessinée, par
     * identifiant : `slug`, `name`, `code`, `network`, `is_lgv`, `max_speed_kmh`, `electrified`,
     * `voltage_v`).
     *
     * **Droits** : le plan ne recopie aucun tracé. Il sort pour toutes les lignes, y compris celles
     * dont `/eras/{year}/lines.geojson` retient la géométrie (`geometry_withheld`), parce qu'il ne
     * contient que des points de grille et des segments redressés, moins précis que les tuiles de
     * la carte.
     *
     * @param  int  $year  Année de l'ère (décennie de 1830 à 2030 ; 2030 = aujourd'hui).
     */
    #[PathParameter('year', "Année de l'ère : une décennie de 1830 à 2030 (2030 = réseau actuel).", example: 1930)]
    #[ApiResponse(200, "Plan schématique de l'ère (FeatureCollection GeoJSON).", examples: [self::EXAMPLE])]
    #[ApiResponse(404, 'Ère inconnue, ère sans graphe construit, ou vue ferroviaire désactivée sur ce serveur (`VFF_SCHEMATIC_MAP=false`).')]
    public function show(int $year): Response
    {
        abort_unless((bool) config('maps.schematic'), 404);
        $era = Era::where('year', $year)->firstOrFail();
        $json = $this->schematic->json($era);
        abort_if($json === null, 404);

        return response($json, 200, [
            'Content-Type' => 'application/json',
            'Cache-Control' => 'public, max-age=600',
        ]);
    }

    /** Extrait réel du plan de 1930 : la gare d'Orléans et l'axe de la ligne de Paris à Bordeaux qui y arrive. */
    private const EXAMPLE = [
        'type' => 'FeatureCollection',
        'era' => 1930,
        'is_current' => false,
        'graph_built_at' => '2026-10-20T16:01:45+02:00',
        'grid_m' => 6000,
        'summary' => ['km' => ['freight' => 168, 'mixed' => 58193, 'unknown' => 258], 'stations' => 8295, 'axes' => 2649, 'lines' => 1236],
        'lines' => [
            '183' => [
                'slug' => 'rfn-570000', 'name' => 'Ligne de Paris-Austerlitz à Bordeaux-St-Jean', 'code' => '570000',
                'network' => 'principal', 'is_lgv' => false, 'max_speed_kmh' => 220, 'electrified' => true, 'voltage_v' => 1500,
            ],
        ],
        'features' => [
            [
                'type' => 'Feature',
                'geometry' => ['type' => 'LineString', 'coordinates' => [[1.34747, 47.60869], [1.83256, 47.93471], [1.88646, 47.93471]]],
                'properties' => ['kind' => 'axis', 'line_id' => 183, 'category' => 'mixed'],
            ],
            [
                'type' => 'Feature',
                'geometry' => ['type' => 'Point', 'coordinates' => [1.88646, 47.93471]],
                'properties' => [
                    'kind' => 'station', 'id' => 16620, 'slug' => 'orleans', 'name' => 'Orléans', 'city' => 'Orléans',
                    'department_code' => '45', 'status' => 'active', 'role' => 'hub', 'main' => true, 'angle' => 0,
                    'rank' => 65, 'tier' => 1,
                ],
            ],
        ],
    ];
}
