# Graphe du réseau : page d'administration et diagnostics

Page réservée aux administrateurs : `/validate/graph` (lien « Graphe du réseau » dans la navigation de
l'espace de validation). Elle montre le graphe ferroviaire calculé par `vff:build-network-graph` pour une
époque, couche par couche, et liste ses défauts. Rien n'y est modifié : toutes les requêtes sont des
lectures.

Ces points d'accès ne font pas partie de l'API publique (ni REST documentée par Scramble, ni GraphQL) :
ils demandent une session d'administrateur.

## Accès

- Session web (`auth`), compte non suspendu (`not.suspended`), rôle administrateur
  (`UserPolicy::viewNetworkGraph`, même règle que la liste des comptes).
- Visiteur non connecté : redirection vers `/login` (401 en JSON). Contributeur, validateur ou
  administrateur suspendu : 403.
- Époque sans graphe construit (aucune ligne dans `network_graph_builds`) : 404 sur les adresses JSON et
  les tuiles. Sur la page, `?era=1855` montre l'époque actuelle avec un message qui le dit
  (propriété `missing_era`).

## Les trois couches

| Couche | Tables | Arêtes | Nœuds |
| --- | --- | --- | --- |
| `topology` | `network_nodes`, `network_edges` | voie (`track`), LGV (`lgv`), sans voyageurs (`non_passenger`), associative (`heritage`), raccord de lacune (`connector`) | jonction, extrémité, gare ouverte, gare fermée traversée, bout de raccord, simple passage (`link`, à partir du zoom 11) |
| `stations` | `station_edges`, `station_eras` | de gare à gare (`commercial`), surtout sans voyageurs, associative, avec raccord de lacune (`gap`) | gare reliée (`linked`), sans voisine (`no_neighbour`), loin de toute voie (`unattached`) |
| `lines` | `network_edges` par ligne, `line_stops` | voies d'une ligne (couleur selon l'identifiant de la ligne), raccords | arrêts de la ligne (rang, PK, statut) |

## Points d'accès (JSON et tuiles)

Tous en `GET`, sous `/validate/graph`. `{year}` est l'année de l'époque (1930, 1970, 2030 pour
aujourd'hui).

| Adresse | Réponse |
| --- | --- |
| `/validate/graph?era=1930&layer=stations` | page Inertia `Validate/Graph` (époque et couche facultatives : par défaut l'époque actuelle et la topologie ; `missing_era` vaut l'année demandée quand elle n'a pas de graphe) |
| `/validate/graph/{year}/diagnostics` | résumé : `{ era, built_at, summary: { topology, stations, lines }, checks, thresholds }` |
| `/validate/graph/{year}/diagnostics/{check}?page=1` | une page de 25 éléments d'une liste : `{ era, check, total, filtered, page, per_page, last_page, items, filters }` ; pour les jonctions, filtres `type` et `line` (voir plus bas) |
| `/validate/graph/{year}/diagnostics/{check}.csv` | toute la liste en CSV (mêmes filtres), écrite au fil de la réponse ; fichier `graphe-{year}-{liste}.csv`, par exemple `graphe-1930-jonctions-sauts-rfn-420000.csv` |
| `/validate/graph/{year}/tiles/{layer}/{z}/{x}/{y}.mvt` | tuile vectorielle (calques `edges` et `nodes`), zoom 8 à 14 (404 en dessous), `Cache-Control: private` |
| `/validate/graph/{year}/features/{kind}/{id}` | attributs d'un élément de l'époque (404 s'il n'en fait pas partie) : `kind` vaut `node`, `edge`, `station_edge`, `station` (identifiant de la gare, présente à l'époque par `station_eras` ou par un nœud) ou `stop` (identifiant dans `line_stops`) ; `id` a 18 chiffres au plus. Une arête sans tracé donne `nodes_gap_m`, la distance entre ses deux nœuds |

Le serveur ne sert pas de tuile sous le zoom 8 (`GraphTiles::MIN_ZOOM`, `GRAPH_MIN_ZOOM` côté carte) :
une tuile de la France entière au zoom 5 ou 6 pèse de 0,5 à 1,3 Mo (mesuré sur 2030 et 1930). Les tuiles
sont gardées 7 jours en cache jusqu'au zoom 12. Aux zooms 13 et 14, elles sont calculées à chaque
demande : grâce aux index GiST des tracés, une tuile y prend quelques millisecondes (voir les mesures
plus bas), et le navigateur la garde une heure.

### Listes (`{check}`)

Chaque liste est triée du cas le plus grave au moins grave et gardée en entier dans le cache (le rapport
de 1930, avec ses 8 992 jonctions, pèse 3 Mo ; 2 Mo pour 2030). `total` compte toute la liste, `filtered` les éléments qui
passent le filtre (égal à `total` sans filtre).

| `check` | Contenu d'un élément |
| --- | --- |
| `islands` | composante de la topologie hors réseau principal : `id` (plus petit nœud), `nodes`, `edges`, `length_m`, `stations`, `bbox` [ouest, sud, est, nord], `lines` (3 au plus : `id`, `slug`, `name`, `code`) |
| `station_islands` | composante du graphe des gares hors réseau principal : `stations`, `edges`, `length_m`, `bbox`, `sample` (3 gares) |
| `isolated_nodes` | nœud sans arête : `id`, `kind`, `station_id`, `lng`, `lat` |
| `stations_without_edge` | gare ouverte sans arête gare à gare : `id`, `slug`, `name`, `reason` (`unattached` : aucun nœud, la gare est loin de toute voie ; `no_neighbour` : rattachée sans gare voisine), `lng`, `lat` |
| `edges` | arête anormale : `layer` (`topology` ou `stations`), `id`, `reason`, `length_m`, `measured_m` (longueur du tracé ; pour une arête de la topologie sans tracé, distance entre ses deux nœuds ; pour une arête de gare à gare, distance à vol d'oiseau), `line`, `lng`, `lat` |
| `junctions` | jonction écartée : `type` (`spread` : nœud dont les extrémités d'arêtes ne coïncident pas ; `connector` : raccord de lacune ; `jump` : arête sans tracé dont les deux nœuds sont à plus de 0,01 m l'un de l'autre), `id` (nœud pour `spread`, arête sinon), `distance_m`, `kind`, `ends`, `lines` (nombre de lignes), `line_ids` et `line_refs` (lignes concernées : `id`, `slug`, `name`, `code`), `lng`, `lat` |
| `fragmented_lines` | ligne dont les voies de l'époque forment plusieurs morceaux : `line`, `pieces`, `edges`, `length_m`, `bbox` |

Lignes d'une jonction : pour `spread`, celles des tracés qui se rejoignent au nœud ; pour `connector`
(le raccord n'a pas de ligne), celles des voies qui arrivent à ses deux bouts ; pour `jump`, celle de
l'arête sans tracé.

### Filtre des jonctions

Seule la liste `junctions` se filtre ; les autres ignorent ces paramètres.

- `type` : `spread`, `connector` ou `jump` (une autre valeur répond 422) ;
- `line` : slug de la ligne (`rfn-420000`) ou numéro à 6 chiffres, avec ou sans espace (`420000`,
  `420 000`). Une jonction passe si la ligne fait partie de ses lignes. Ligne introuvable : liste vide
  et `filters.unknown_line` vaut le texte demandé (404 pour l'export CSV).

La réponse rappelle le filtre appliqué : `filters: { type, line: { id, slug, name, code } | null,
unknown_line }`. Sur la page, le formulaire « Filtrer les jonctions » apparaît au-dessus du tableau
des jonctions.

### Export CSV

Le lien « Télécharger en CSV », à côté du nombre de cas, donne toute la liste affichée, filtre
compris, sans plafond. Format : UTF-8 avec BOM, virgule entre les champs, point décimal, en-têtes en
français quelle que soit la langue de l'interface, types, défauts et couches écrits en clair en
français, distances en mètres, coordonnées en degrés WGS 84 ; plusieurs lignes ou gares dans une
cellule sont séparées par « ; ». Un texte qui commence par `=`, `+`, `-`, `@` ou une tabulation est
précédé d'une apostrophe, pour qu'un tableur ne l'exécute pas comme une formule. Réponse
`Cache-Control: private, no-store`. Code : `GraphDiagnosticsCsv`.

Défauts des arêtes (`reason`) : `no_geometry` (pas de tracé, testé en premier : le constructeur écrit
une longueur nulle pour ces arêtes), `zero_length` (tracé de longueur nulle), `loop`
(même nœud aux deux bouts), `length_mismatch` (longueur enregistrée éloignée du tracé de plus de 30 m et
de plus de 1 % ; le constructeur recolle chaque bout d'arête sur son nœud, jusqu'à 15 m, sans changer la
longueur mesurée sur la voie d'origine), `detour` (arête de gare à gare de plus de 5 km, plus de 4 fois plus longue qu'à vol
d'oiseau).

## Méthode

- Réseau principal : la composante connexe la plus longue en mètres de voie, comme dans
  `EraGraphBuilder::bridgeComponents`. Toutes les autres sont des îles, nœuds isolés compris.
- Arêtes sans tracé : elles comptent comme des liaisons dans les composantes connexes, comme les
  raccords de lacunes. Le graphe les suit pour les itinéraires, et une île doit rester une partie que
  ces itinéraires n'atteignent pas. L'écart qu'elles laissent sur la carte est mesuré à part : quand
  leurs deux nœuds sont à plus de 0,01 m, elles sont listées dans les jonctions (`jump`), avec la
  distance entre les nœuds sur l'ellipsoïde.
- Jonction écartée : pour chaque nœud où arrivent au moins deux bouts d'arêtes, plus grande distance
  entre ces bouts, mesurée en Lambert-93 (`ST_MaxDistance`). Au-delà de 0,01 m (bruit des
  reprojections), le nœud est listé. Sur la carte, deux lignes doivent se toucher à 0 m : chaque écart
  est à corriger dans les tracés ou dans la construction du graphe.
- Le calcul prend 64 Mo de `work_mem` le temps du rapport, puis remet la valeur d'origine de la
  connexion.
- Le rapport d'une époque est calculé en une fois (1 à 2 s sur `vff`), gardé 7 jours en cache sous la
  date de construction du graphe : une reconstruction de l'époque change la clé et le rapport est
  recalculé à la visite suivante. Chaque liste a sa propre entrée de cache, à côté d'un en-tête (résumé
  chiffré) : une page de 25 lignes ne relit que sa liste. Mesuré en 1930 : une page des jonctions passe
  de 21 ms (rapport entier de 3 Mo relu) à 15 ms (2,3 Mo), une page des îles à 0,5 ms (22 Ko), l'en-tête
  à 0,4 ms (1,2 Ko).
- Page : changer d'époque ou de couche se fait par le bouton « Afficher » (une flèche sur les boutons
  radio ne recharge rien) ; la page garde alors son état (carte, cadrage, fiche affichée). Pendant le
  chargement d'une page du tableau, la précédente reste affichée et le chargement est annoncé aux
  lecteurs d'écran. « Voir sur la carte » sur une île ou une ligne en morceaux recadre la carte et
  affiche un résumé de l'élément dans le panneau.
- Code : `App\Services\NetworkGraph\GraphDiagnostics`, `DiagnosticsFilter`, `GraphDiagnosticsCsv`, `GraphTiles`, `GraphFeatureDetails`,
  `App\Http\Controllers\NetworkGraphAdminController` ; tests :
  `tests/Feature/Validate/NetworkGraphAdminTest.php`, `tests/js/graphAdmin.test.js`.

## Mesures du 20/11/2026 (base `vff`)

| Époque | Îles (topologie) | Nœuds isolés | Jonctions écartées (dont > 5 m) | Raccords de lacunes | Arêtes de longueur nulle | Îles (gares) | Gares sans arête (dont loin de toute voie) | Lignes en morceaux |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1930 | 59 | 0 | 6 461 (4 920) | 2 296 | 1 436 | 20 | 153 (146) | 197 |
| 1970 | 61 | 0 | 4 318 (3 367) | 1 572 | 1 008 | 37 | 86 (72) | 218 |
| 2030 | 113 | 0 | 3 876 (3 006) | 1 509 | 1 104 | 46 | 24 (6) | 189 |

Arêtes de la topologie sans tracé (mesures du 20/11/2026, SELECT en lecture seule) :

| Époque | Sans tracé | dont sauts (nœuds à plus de 0,01 m) | dont plus de 5 m | Plus grand saut | Tracés de longueur nulle |
| --- | --- | --- | --- | --- | --- |
| 1930 | 1 415 | 235 | 126 | 311,3 m | 21 |
| 1970 | 992 | 174 | 103 | 278,8 m | 16 |
| 2030 | 1 080 | 217 | 134 | 279,2 m | 24 |

Après la reconstruction du 20/11/2026 au soir (21 époques), avec le recollage des extrémités et la fusion des
arêtes de longueur nulle dans `EraGraphBuilder` :

| Époque | Îles (topologie) | Jonctions écartées (dont > 5 m) | Jonctions écartées de 15 m au plus | Arêtes de longueur nulle (sans tracé) | Sauts | Arêtes anormales |
| --- | --- | --- | --- | --- | --- | --- |
| 1930 | 57 | 1 267 (1 267) | 1 | 17 (17) | 7 | 79 |
| 1970 | 61 | 704 (704) | 0 | 14 (14) | 4 | 68 |
| 2030 | 113 | 632 (632) | 0 | 15 (14) | 5 | 77 |

Les jonctions écartées qui restent sont des gares rattachées à des voies de lignes différentes, à plus de
15 m l'une de l'autre (jusqu'à 597 m) : il faut corriger les tracés. Les arêtes de longueur nulle qui
restent relient deux gares distinctes, que le constructeur ne fusionne jamais.

## Index des tracés et temps des tuiles

Migration `2026_12_02_020000_add_geom_indexes_to_graph_edges` : index GiST `network_edges_geom_gist`
(22 Mo) et `station_edges_geom_gist` (6 Mo), créés avec `CONCURRENTLY`. `EraGraphBuilder` remplace le
graphe d'une époque par `DELETE` puis `INSERT` sans supprimer les tables : les index restent d'une
reconstruction à l'autre. Les nœuds avaient déjà le leur.

Temps d'exécution d'un calque (EXPLAIN ANALYZE en lecture seule, époque 1930, à chaud, 20/11/2026) :

| Tuile | Calque | Sans index | Avec index |
| --- | --- | --- | --- |
| 14/8300/5638 (Paris) | arêtes de la topologie | 59 à 65 ms | 3,4 à 3,9 ms |
| 14/8300/5638 | arêtes de gare à gare | 35 à 50 ms | 1,1 à 1,4 ms |
| 14/8300/5638 | voies des lignes | 54 à 56 ms | 2,4 à 2,9 ms |
| 12/2075/1409 | arêtes de la topologie | 51 à 57 ms | 4,3 ms |
| 8/129/88 | arêtes de la topologie | 86 à 98 ms | 90 ms (mise en forme MVT, tuile en cache) |

Au zoom 8, le temps vient de la simplification et de l'encodage des milliers d'arêtes de la tuile, pas
de la recherche : l'index n'y change rien, et ces tuiles restent en cache.
