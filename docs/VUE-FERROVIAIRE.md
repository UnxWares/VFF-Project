# Vue ferroviaire : plan schématique d'une époque

Choix « Vue ferroviaire » de la carte `/maps`, à côté de « Une époque », « Comparer » et « Toutes les
époques ». Il dessine le réseau en service à l'époque choisie comme un plan de transport : axes à 0, 45
ou 90 degrés, bifurcations, correspondances et terminus en cercles, autres gares en petits traits, sans
fond de carte. L'adresse garde le choix et l'époque : `/maps?view=schema&era=1930` (`/maps?view=schema`
pour aujourd'hui). La frise des époques, la légende (catégories masquables), la recherche (vol vers la
gare sur le plan) et la fiche latérale d'une ligne fonctionnent comme sur la carte ; un clic sur une
gare ouvre la même bulle que sur la carte. Les calques (accidents, passages à niveau…) restent propres
à la carte géographique.

Réglage : `config/maps.php`, clé `schematic` (variable `VFF_SCHEMATIC_MAP`, vraie par défaut). À
`false`, le choix n'est plus proposé et le point d'accès répond 404.

## Méthode

Le plan part du graphe de l'époque construit par `vff:build-network-graph` (voies en service et gares
ouvertes : `network_nodes`, `network_edges`, voir `docs/GRAPHE-ADMIN.md`). Il est calculé en PHP par
`App\Services\Schematic\SchematicLayout`, en mètres Web Mercator, la projection de MapLibre : un segment
droit et ses 45 degrés le restent à l'écran.

1. Loupes : Paris (grossissement 3), Lyon, Lille (2,2) et Marseille (2) sont agrandies, avec un
   grossissement qui décroît en gaussienne, sans bord. Sans elles, les gares parisiennes tombaient sur
   un seul point de la grille.
2. Axes : chaînes de voies entre deux nœuds qui ne sont pas de simple passage. Une antenne sans gare de
   moins de 3 km (tiroir, faisceau, bout de voie) est retirée.
3. Nœuds du plan : les extrémités reliées par moins de 0,45 pas de grille sont fondues, ainsi que
   celles qui tombent sur le même point et sont reliées par moins de 1,6 pas ; un complexe de
   bifurcations devient un nœud. Les longueurs sont mesurées sur le plan (après les loupes).
4. Une boucle (axe qui revient à son nœud, voie double en impasse) est coupée en son point le plus
   éloigné. Deux axes entre les mêmes nœuds et de longueur voisine (deux lignes tracées côte à côte)
   n'en font qu'un, dessiné dans la catégorie la plus active. Un nœud où ne se rejoignent que deux axes
   disparaît : les axes sont mis bout à bout.
5. Grille de 6 000 m (environ 4 km sur le terrain en France) : chaque nœud prend le point le plus
   proche, ou un point libre à moins de 3 pas, les nœuds de plus d'axes d'abord.
6. Routage (`OctilinearRouter`), axes de la grande vitesse et du réseau mixte d'abord, puis du plus long
   au plus court : plus court chemin A* sur la grille en 8 directions. Un chemin coûte sa longueur, plus
   chaque coude (2 à 45°, 4 à 90°, 9 à 135°), l'écart au tracé réel simplifié au-delà d'une marge
   (12 % de la distance entre les nœuds), un départ ou une arrivée dans une autre direction que le tracé
   réel, et surtout les pas déjà pris (30), les croisements (6) et le passage par un autre nœud (80).
   Ces coûts sont finis : un axe trouve toujours un chemin. Au-delà de 60 000 états explorés, l'axe est
   tracé avec un seul coude. Sur les données du 20/11/2026, aucun axe n'en a eu besoin, et seuls 11 pas
   de grille sont partagés par deux axes en 1930 (aucun en 1970 ni aujourd'hui).
7. Gares intermédiaires : posées le long de leur axe, à mi-chemin entre leur position réelle sur la
   ligne et un espacement régulier, dans leur ordre ; les changements de ligne et de catégorie suivent.

Les réglages sont dans `SchematicOptions`. Ils ont été choisis en regardant les plans de 1930, 1970 et
d'aujourd'hui, à l'échelle de la France et des grandes villes : un coude moins cher fait des escaliers
sur les longues lignes en biais, une marge d'écart plus petite aussi.

Gare qui nomme un nœud et ordre des étiquettes : population de la commune (table `communes`,
arrondissements ramenés à la commune), puis gare avant halte, nom qui commence par celui de la ville,
nombre de lignes desservies à l'époque, gare de voyageurs avant gare de fret. Niveaux d'étiquette
(`tier`) : 24 villes différentes pour la France entière, 96 de plus au zoom 6,3, 480 de plus au zoom
7,6, toutes les autres au zoom 9,2. MapLibre retire les étiquettes qui se chevauchent.

## Calcul, cache et commande

`App\Services\Schematic\SchematicMap` garde le plan de chaque époque dans le cache de l'application
(30 jours), sous une clé faite de l'époque, de la date de construction de son graphe
(`network_graph_builds.built_at`), de `SchematicMap::FORMAT` et des réglages : reconstruire le graphe
ou changer un réglage donne un nouveau plan. Un verrou évite deux calculs de la même époque à la fois.

Mesures du 20/11/2026 sur `vff` (lecture du graphe et calcul) : de 0,1 s (1830) à 2 s environ (1920
et 1930), 1 s pour aujourd'hui. Taille de la réponse : 3,1 Mo pour 1930 (400 Ko compressée en gzip),
2,2 Mo pour 1970 (286 Ko), 1,3 Mo pour aujourd'hui (164 Ko).

```
php artisan vff:build-schematic-maps                    # toutes les époques qui ont un graphe
php artisan vff:build-schematic-maps --era=1930,2030
php artisan vff:build-schematic-maps --era=1930 --output=storage/app/plan-1930.geojson
```

La commande ne lit que la base et n'écrit que dans le cache. Sans elle, le plan d'une époque est
calculé à sa première demande. En production, la lancer après `vff:build-network-graph`.

## Point d'accès

`GET /api/v1/eras/{year}/schematic.geojson`, documenté dans Scalar (`/docs/api`, groupe « Cartographie
par ère ») et sur la page `/api`. FeatureCollection GeoJSON : axes (`kind: axis`, `line_id`,
`category`) et gares (`kind: station`, `slug`, `name`, `role`, `angle`, `rank`, `tier`…), avec les
membres `era`, `graph_built_at`, `grid_m`, `summary` et `lines` (fiche courte de chaque ligne). 404 pour
une époque inconnue, sans graphe, ou quand la vue est désactivée.

## Droits des tracés

`api.withhold_unlicensed_geometry` retient la géométrie des tronçons dont la source n'est pas
redistribuable. Le plan ne la contient pas : le tracé réel ne sert qu'à guider le routage (écart au
tracé simplifié à 0,35 pas, directions de départ et d'arrivée) et n'est jamais recopié. Les nœuds sont
des points d'une grille de 6 km Web Mercator, parfois déplacés de quelques pas et grossis par les
loupes ; les gares intermédiaires sont posées sur des segments redressés, à des positions recalculées.
Le plan est donc moins précis que les tuiles de la carte (0,6 m au zoom 14, toutes sources comprises),
et il sort pour toutes les lignes, comme leurs fiches. Le réglage ne change rien au plan (test
`SchematicMapApiTest`) ; `GeometryRightsInvariantTest` parcourt aussi ce point d'accès.

## Accessibilité

Le plan reste une image : le canevas porte le nom « Vue ferroviaire du réseau (plan schématique) » et
une description (`aria-describedby`) qui donne la longueur en service par catégorie et le nombre de
gares de l'époque. La légende dit que les positions et les distances ne sont pas à l'échelle ;
« Lire la carte » explique la vue. Clavier : flèches pour se déplacer, `+` et `-` pour zoomer (MapLibre),
boutons de zoom et plein écran ; la recherche de la barre d'outils mène à une gare. Couleurs : celles
de la carte ; sur le fond clair, le liseré foncé donne 3:1 aux couleurs claires ; sur le fond sombre, le
gris du trafic inconnu est éclairci (#94a3b8), seule couleur sous 3:1. Contrastes vérifiés par
`tests/js/schematicMap.test.js`.

## Limites connues

- Les lignes coupées dans les données (« lignes en morceaux » de `docs/GRAPHE-ADMIN.md`) finissent en
  bout d'axe sans gare.
- Des axes se croisent sans correspondance, comme des voies qui se croisent sur un pont : le routage
  les évite quand un autre chemin coûte moins de 6.
- Les étiquettes peuvent passer sur un axe (MapLibre n'évite que les autres étiquettes).
- La gare qui nomme une grande ville dépend des données des gares (exemples en 1930 : Nantes-Legé,
  Marseille-Blancarde).

## Code et tests

`app/Services/Schematic/` (`SchematicLayout`, `OctilinearRouter`, `SchematicGraphLoader`,
`SchematicMap`, `SchematicOptions`, `Mercator`), `app/Http/Controllers/Api/SchematicMapController.php`,
`app/Console/Commands/BuildSchematicMaps.php`, `resources/js/Components/Map/SchematicMap.svelte`,
`resources/js/lib/schematicMap.js`. Tests : `tests/Unit/Schematic/`,
`tests/Feature/Api/SchematicMapApiTest.php`, `tests/js/schematicMap.test.js`.
