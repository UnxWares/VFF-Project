# Vue ferroviaire : plan schématique d'une époque

Choix « Vue ferroviaire » de la carte `/maps`, à côté de « Une époque », « Comparer » et « Toutes les
époques ». Il dessine le réseau en service à l'époque choisie comme un plan de transport : axes à 0, 45
ou 90 degrés, villes, bifurcations, correspondances et terminus en cercles, autres gares en petits traits, sans
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
   moins de 3 km (tiroir, faisceau, bout de voie) est retirée. Tronçons isolés (voies en service
   qu'aucune autre voie en service ne relie au reste du réseau à l'époque) :
   - un bout de voie qui s'arrête à moins de 0,45 pas de grille (2,7 km sur le plan, moins de 2 km sur
     le terrain) d'un nœud d'un morceau de réseau plus grand y est raccordé. À cette distance, le plan
     fond de toute façon deux nœuds reliés, et l'écart vient presque toujours d'une lacune des données :
     la voie s'arrête à quelques centaines de mètres de sa jonction ;
   - un tronçon qui reste isolé, sans gare et plus court que 5 km, n'est pas dessiné (bout de voie,
     embranchement, raccord coupé) ;
   - les autres restent à leur place, sans trait qui les relie au reste du plan : aucune voie en
     service ne les y relie à l'époque. Ce sont surtout des trains touristiques (en vert), des lignes à
     voie métrique, des restes de lignes frontalières et la Corse. Un trait fin aurait fait croire à une
     liaison qui n'existe pas.
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
   réel, et surtout les pas déjà pris (30), les croisements et le passage par un autre nœud (150). Un
   croisement coûte 6 quand les deux voies se croisent aussi sur le terrain (pont, saut-de-mouton :
   tracés réels simplifiés qui se coupent ailleurs qu'à un nœud commun), 60 sinon. Ces coûts sont finis :
   un axe trouve toujours un chemin. Au-delà de 60 000 états explorés, l'axe est tracé avec un seul
   coude ; sur les données du 20/11/2026, aucun axe n'en a eu besoin.
   Reprise : les axes routés tôt ne voyaient pas ceux routés après eux. Chaque axe qui en croise un
   autre, le longe ou traverse un nœud est donc routé de nouveau, tous les autres en place, jusqu'à
   trois passes.
7. Gares intermédiaires : posées le long de leur axe, à mi-chemin entre leur position réelle sur la
   ligne et un espacement régulier, dans leur ordre ; les changements de ligne et de catégorie suivent.

Les réglages sont dans `SchematicOptions`. Ils ont été choisis en regardant les plans de 1930, 1970 et
d'aujourd'hui, à l'échelle de la France et des grandes villes : un coude moins cher fait des escaliers
sur les longues lignes en biais, une marge d'écart plus petite aussi.

### Noms des villes et des gares

Niveaux d'étiquette (`tier`) : 24 villes pour la France entière, 96 de plus au zoom 6,3, 480 gares de
plus au zoom 7,6, toutes les autres au zoom 9,2.

- Niveaux 0 et 1 : des villes. Les communes sont prises par population décroissante (table `communes`,
  arrondissements ramenés à la commune), une gare chacune. Une ville du niveau 0 est à 80 km au moins
  des autres sur le plan, une ville du niveau 1 à 30 km au moins des villes déjà nommées : la France
  entière montre Amiens ou Annecy plutôt que trois communes de la banlieue de Paris. Le nœud porte le
  nom de la commune (« Nantes », « Marseille », champ `city_label`) jusqu'au zoom 7,6, puis celui de sa
  gare. Une ville nommée a son cercle même quand sa gare est une gare intermédiaire de l'axe (Toulon,
  Caen).
- Gare qui représente une ville : celle qui porte le nom de la ville seul ou suivi de « Ville » ou
  « Centre » (« Nantes », « Metz-Ville », « Avignon-Centre »), sinon une gare dont le nom commence
  par celui de la ville (« Marseille-St-Charles » avant « L'Estaque »), une gare avant une halte, un
  nœud du plan avant une gare intermédiaire, le nœud où se rejoignent le plus d'axes.
- Niveaux 2 et 3 : les gares, par rang (population de la commune, gare avant halte, gare principale,
  nombre de lignes desservies à l'époque, gare de voyageurs avant gare de fret, rôle dans le plan).

Côté du nom (`SchematicLabels`) : MapLibre écarte une étiquette des autres étiquettes, jamais des
traits. Le serveur mesure donc, pour chaque gare, la part du nom qui recouvrirait un axe de chaque côté
(à droite, à gauche, dessus, dessous et les quatre coins), au zoom où le nom apparaît et un zoom et
demi plus près, et pour un nœud de ville aussi aux zooms où il porte le nom de sa gare. Il envoie les
quatre meilleurs côtés (`label_anchors`) ; le navigateur les essaie dans cet ordre, puis les autres, et
passe au suivant quand une autre étiquette occupe la place. Les calques des noms sont empilés du moins
important au plus important : MapLibre place d'abord le calque du dessus, donc les grandes villes
passent avant les petites gares.

## Calcul, cache et commande

`App\Services\Schematic\SchematicMap` garde le plan de chaque époque dans le cache de l'application
(30 jours), sous une clé faite de l'époque, de la date de construction de son graphe
(`network_graph_builds.built_at`), de `SchematicMap::FORMAT` et des réglages : reconstruire le graphe
ou changer un réglage donne un nouveau plan. Un verrou évite deux calculs de la même époque à la fois.

Mesures du 20/11/2026 sur `vff` (`vff:build-schematic-maps`, lecture du graphe, plan et étiquettes) :
de 0,1 s (1830) à 3,3 s (1930), 1,8 s pour 1970, 1,4 s pour aujourd'hui. Taille de la réponse : 3,8 Mo
pour 1930 (432 Ko compressée en gzip), 2,7 Mo pour 1970 (308 Ko), 1,5 Mo pour aujourd'hui (177 Ko).

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
`category`) et gares (`kind: station`, `slug`, `name`, `role`, `angle`, `rank`, `tier`, `city_label`,
`label_anchors`…), avec les membres `era`, `graph_built_at`, `grid_m`, `summary` et `lines` (fiche
courte de chaque ligne). 404 pour une époque inconnue, sans graphe, ou quand la vue est désactivée.

## Droits des tracés

`api.withhold_unlicensed_geometry` retient la géométrie des tronçons dont la source n'est pas
redistribuable. Le plan ne la contient pas : le tracé réel ne sert qu'à guider le routage (écart au
tracé simplifié à 0,35 pas, directions de départ et d'arrivée) et n'est jamais recopié. Les nœuds sont
des points d'une grille de 6 km Web Mercator, parfois déplacés de quelques pas et grossis par les
loupes ; les gares intermédiaires sont posées sur des segments redressés, à des positions recalculées.
Le plan est donc moins précis que les tuiles de la carte (0,3 m au zoom 14, grille de 8 192, toutes sources comprises),
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

## Mesures

Mesures du 20/11/2026 sur `vff`, avant puis après la seconde passe, faites sur le GeoJSON produit
(méthode dans `storage/app/reports/agents/matin/vue-ferroviaire-2.md`). Tronçons isolés : morceaux du
plan qu'aucun axe ne relie au plus grand. Étiquettes : placement de MapLibre simulé sur des boîtes
estimées ; « sur un axe » veut dire plus de 3 px d'axe sous le nom. Le calcul du plan compte aussi
ses croisements, ses tronçons isolés, ses raccords et ses tronçons retirés (`SchematicPlan::stats`).

| Époque | Tronçons isolés | Croisements sans jonction | Dont croisements qui existent sur le terrain, après |
| --- | --- | --- | --- |
| 1930 | 45, puis 26 | 166, puis 98 | 46 |
| 1970 | 34, puis 25 | 39, puis 25 | 8 |
| Aujourd'hui | 40, puis 27 | 43, puis 23 | 15 |

Étiquettes posées sur un axe, avant puis après (entre parenthèses, celles qui ne trouvent pas de
place) :

| Époque | France (zoom 5) | Zoom 7,6 | Zoom 9,2 | Zoom 10,5 |
| --- | --- | --- | --- | --- |
| 1930 | 20 (2), puis 20 (0) | 306 (225), puis 222 (163) | 2 415 (2 319), puis 935 (1 443) | 2 482 (697), puis 354 (193) |
| 1970 | 19 (3), puis 17 (0) | 253 (235), puis 153 (178) | 1 423 (1 582), puis 452 (910) | 1 649 (461), puis 184 (96) |
| Aujourd'hui | 17 (4), puis 16 (0) | 217 (246), puis 123 (185) | 874 (742), puis 278 (461) | 949 (253), puis 129 (79) |

Sur la France entière, un nom de ville couvre 150 km de plan ou plus : en 1930, il tombe presque
toujours sur un axe, et le halo du texte garde le nom lisible.

## Limites connues

- Les lignes coupées dans les données (« lignes en morceaux » de `docs/GRAPHE-ADMIN.md`) finissent en
  bout d'axe sans gare. Seul un tronçon isolé dont le bout s'arrête à moins de 0,45 pas d'un nœud est
  raccordé ; une coupure à l'intérieur du réseau reste visible.
- Des axes se croisent encore sans jonction. Une partie est réelle (ponts, sauts-de-mouton). Les autres
  viennent de la place des nœuds sur la grille, que le routage ne déplace pas : deux nœuds voisins
  d'une même ville (Le Mans et Le Mans-les-Halles en 1930) entremêlent leurs axes.
- Une étiquette peut encore passer sur un axe quand aucun côté n'est libre (France entière en 1930,
  nœuds denses).
- Les tronçons isolés restent détachés, sans trait vers le reste du plan.

## Code et tests

`app/Services/Schematic/` (`SchematicLayout`, `OctilinearRouter`, `SchematicGraphLoader`,
`SchematicMap`, `SchematicLabels`, `SchematicOptions`, `Mercator`),
`app/Http/Controllers/Api/SchematicMapController.php`,
`app/Console/Commands/BuildSchematicMaps.php`, `resources/js/Components/Map/SchematicMap.svelte`,
`resources/js/lib/schematicMap.js`. Tests : `tests/Unit/Schematic/`,
`tests/Feature/Api/SchematicMapApiTest.php`, `tests/js/schematicMap.test.js`.
