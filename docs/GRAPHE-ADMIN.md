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
- Époque sans graphe construit (aucune ligne dans `network_graph_builds`) : 404.

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
| `/validate/graph?era=1930&layer=stations` | page Inertia `Validate/Graph` (époque et couche facultatives : par défaut l'époque actuelle et la topologie) |
| `/validate/graph/{year}/diagnostics` | résumé : `{ era, built_at, summary: { topology, stations, lines }, checks, thresholds }` |
| `/validate/graph/{year}/diagnostics/{check}?page=1` | une page de 25 éléments d'une liste : `{ era, check, total, listed, page, per_page, last_page, items }` |
| `/validate/graph/{year}/tiles/{layer}/{z}/{x}/{y}.mvt` | tuile vectorielle (calques `edges` et `nodes`), zoom 0 à 14, `Cache-Control: private` |
| `/validate/graph/{year}/features/{kind}/{id}` | attributs d'un élément : `kind` vaut `node`, `edge`, `station_edge`, `station` (identifiant de la gare) ou `stop` (identifiant dans `line_stops`) |

La carte ne demande les tuiles qu'à partir du zoom 8 : une tuile de la France entière au zoom 5 ou 6
pèse de 0,5 à 1,3 Mo (mesuré sur 2030 et 1930). Les tuiles sont gardées en cache jusqu'au zoom 12.

### Listes (`{check}`)

Chaque liste est triée du cas le plus grave au moins grave et gardée en entier jusqu'à 2 000 éléments
(`total` donne le nombre exact, `listed` le nombre gardé).

| `check` | Contenu d'un élément |
| --- | --- |
| `islands` | composante de la topologie hors réseau principal : `id` (plus petit nœud), `nodes`, `edges`, `length_m`, `stations`, `bbox` [ouest, sud, est, nord], `lines` (3 au plus : `id`, `slug`, `name`, `code`) |
| `station_islands` | composante du graphe des gares hors réseau principal : `stations`, `edges`, `length_m`, `bbox`, `sample` (3 gares) |
| `isolated_nodes` | nœud sans arête : `id`, `kind`, `station_id`, `lng`, `lat` |
| `stations_without_edge` | gare ouverte sans arête gare à gare : `id`, `slug`, `name`, `reason` (`unattached` : aucun nœud, la gare est loin de toute voie ; `no_neighbour` : rattachée sans gare voisine), `lng`, `lat` |
| `edges` | arête anormale : `layer` (`topology` ou `stations`), `id`, `reason`, `length_m`, `measured_m` (longueur du tracé, ou distance à vol d'oiseau pour une arête de gare à gare), `line`, `lng`, `lat` |
| `junctions` | jonction écartée : `type` (`spread` : nœud dont les extrémités d'arêtes ne coïncident pas ; `connector` : raccord de lacune), `id` (nœud ou arête), `distance_m`, `kind`, `ends`, `lines`, `lng`, `lat` |
| `fragmented_lines` | ligne dont les voies de l'époque forment plusieurs morceaux : `line`, `pieces`, `edges`, `length_m`, `bbox` |

Défauts des arêtes (`reason`) : `zero_length` (longueur nulle), `no_geometry` (pas de tracé), `loop`
(même nœud aux deux bouts), `length_mismatch` (longueur enregistrée éloignée du tracé de plus de 5 m et
de plus de 1 %), `detour` (arête de gare à gare de plus de 5 km, plus de 4 fois plus longue qu'à vol
d'oiseau).

## Méthode

- Réseau principal : la composante connexe la plus longue en mètres de voie, comme dans
  `EraGraphBuilder::bridgeComponents`. Toutes les autres sont des îles, nœuds isolés compris.
- Jonction écartée : pour chaque nœud où arrivent au moins deux bouts d'arêtes, plus grande distance
  entre ces bouts, mesurée en Lambert-93 (`ST_MaxDistance`). Au-delà de 0,01 m (bruit des
  reprojections), le nœud est listé. Sur la carte, deux lignes doivent se toucher à 0 m : chaque écart
  est à corriger dans les tracés ou dans la construction du graphe.
- Le rapport d'une époque est calculé en une fois (1 à 2 s sur `vff`), gardé 7 jours en cache sous la
  date de construction du graphe : une reconstruction de l'époque change la clé et le rapport est
  recalculé à la visite suivante.
- Code : `App\Services\NetworkGraph\GraphDiagnostics`, `GraphTiles`, `GraphFeatureDetails`,
  `App\Http\Controllers\NetworkGraphAdminController` ; tests :
  `tests/Feature/Validate/NetworkGraphAdminTest.php`, `tests/js/graphAdmin.test.js`.

## Mesures du 20/11/2026 (base `vff`)

| Époque | Îles (topologie) | Nœuds isolés | Jonctions écartées (dont > 5 m) | Raccords de lacunes | Arêtes de longueur nulle | Îles (gares) | Gares sans arête (dont loin de toute voie) | Lignes en morceaux |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1930 | 59 | 0 | 6 461 (4 920) | 2 296 | 1 436 | 20 | 153 (146) | 197 |
| 1970 | 61 | 0 | 4 318 (3 367) | 1 572 | 1 008 | 37 | 86 (72) | 218 |
| 2030 | 113 | 0 | 3 876 (3 006) | 1 509 | 1 104 | 46 | 24 (6) | 189 |
