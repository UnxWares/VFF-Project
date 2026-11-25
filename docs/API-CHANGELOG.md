# Changements de l'API

Ce journal liste ce qui change pour qui appelle l'API de VFF : REST (`/api/v1`), GraphQL (`/graphql`) et le serveur MCP (`/mcp`).
Les entrées sont datées, la plus récente en premier. Un champ n'est retiré qu'après avoir été marqué déprécié (`deprecated` dans la
spécification OpenAPI, `@deprecated` dans le schéma GraphQL) et annoncé ici. Un test (`ApiContractTest`) compare l'API à un instantané
versionné (`tests/Fixtures/api-contract.json`) et échoue si un champ, un paramètre ou une valeur disparaît sans cette annonce.

## 25/11/2026

### Ajouts

- Livret de parcours (REST `GET /api/v1/routebook`, GraphQL `Routebook.non_passenger_m`, MCP `routebook`) : `non_passenger_m`,
  mètres parcourus sur des voies sans service voyageurs (fret seul, réseaux militaire ou particulier). Le livret ne prend plus
  que les voies ouvertes aux voyageurs (0) et ne passe sur ces voies que s'il n'existe aucun autre chemin ; `GET /api/v1/route`
  garde son mode voyageurs (voies sans voyageurs pénalisées). Un train de parc ou d'attraction sans ligne de VFF (« Petit Train
  de la Tête d'Or ») n'est plus une gare du graphe : il n'apparaît plus dans les itinéraires (`/route`, livret) après le
  prochain `vff:build-network-graph`.
- Paramètres RINF d'une ligne (REST `GET /api/v1/lines/{line}/rinf-parameters`, GraphQL, MCP `line_rinf_parameters`) :
  `max_speed` est comparé tronçon par tronçon (`rg_troncon` rempli) quand les tronçons du RINF correspondent à ceux de SNCF
  Réseau, et `train_protection` lit chaque tronçon du RINF à part quand la ligne en a plusieurs (deux tronçons d'une ligne
  peuvent avoir des PK communs : 610000, 890000, 330000, 525000, 975900) ; la partie de SNCF Réseau hors de tout tronçon du
  RINF a alors `rg_troncon` nul. Champs inchangés.

- Paramètres RINF d'une ligne (REST `GET /api/v1/lines/{line}/rinf-parameters`, GraphQL `LineRinfPlatformSummary`, MCP
  `line_rinf_parameters`) : statut de quai `length_unknown` (même gare, même nom de quai, longueur absente d'une des deux
  sources) et son nombre `platforms.summary.length_unknown` ; GraphQL `LineRinfPlatformSummary.length_unknown`.
- Livret de parcours (REST `GET /api/v1/routebook`, GraphQL `Routebook`, MCP `routebook`) : `buffer_stops` (heurtoirs, avec
  la voie qu'ils ferment) et `milestones` (bornes kilométriques, avec leur inscription), comme le schéma des voies, placés
  au kilomètre parcouru (`route_km`) ; GraphQL `Routebook.buffer_stops` et `Routebook.milestones`, MCP `kinds` :
  `buffer_stops`, `milestones` (pas servis par défaut). `objects` les compte toujours à part (inchangé).
- Paramètres RINF d'une ligne, comparés (REST `GET /api/v1/lines/{line}/rinf-parameters`, GraphQL `Line.rinf_parameters`,
  MCP `line_rinf_parameters`) : ce que le RINF déclare pour chaque voie de chaque section, par PK, côte à côte avec l'autre
  source de VFF (SNCF Réseau) et l'accord ou le désaccord des deux, sans rien remplacer. `parameters[]` : `electrification`
  (`25000V-50Hz`, `1500V-DC`, `none`), `max_speed`, `train_protection` (KVB, TVM 300, TVM 430, ETCS L1, ETCS L2 confrontés ;
  tous les systèmes déclarés dans `values`), `radio` (`gsm-r`), `gauging`, `load_capability`, `track_gauge` (les trois derniers
  sans autre source) ; chaque portion porte `status` (`agree`, `disagree`, `rinf_only`, `vff_only`), `rinf`, `vff` et
  `rg_troncon` ; `summary` en mètres par statut. `platforms` : quais du RINF (longueur, hauteur) rapprochés des quais de SNCF
  Réseau gare par gare. `sections[]` : sections du RINF. GraphQL : types `LineRinfParameters`, `LineRinfSection`,
  `LineRinfOperationalPoint`, `LineRinfParameter`, `LineRinfParameterSummary`, `LineRinfParameterPortion`,
  `LineRinfParameterValue`, `LineRinfPlatforms`, `LineRinfPlatformSummary`, `LineRinfPlatform`, `LineRinfPlatformStation`,
  `LineRinfPlatformRinf`, `LineRinfPlatformSncf`. Mesure du 25/11/2026 sur la France : électrification d'accord sur 99,7 % de
  la longueur comparable, vitesse 92,9 %, contrôle de vitesse 85,1 %, GSM-R 97,8 %.
- Voies d'une gare (REST `GET /api/v1/stations/{station}/track-layout`, GraphQL `Station.track_layout`, MCP
  `station_track_layout`) : pour chaque ligne qui passe par la gare et dont les voies sont décrites autour d'elle, le
  schéma des voies de la ligne (même contrat et mêmes règles que `GET /api/v1/lines/{line}/track-layout`) sur une fenêtre
  autour de la gare (`lines[].window`, `station_pk_m`, `station_pk_source`, `track_layout`), la ligne qui a le plus de voies
  d'abord ; les bords de quai de la gare (`platforms[]` : `ref`, `track_ref`, `line_code`, `track` = voie bordée dans le
  schéma, `length_m` et `height_mm` du RINF, `pk_from_m` et `pk_to_m` de la « Liste des quais » de SNCF Réseau ou placés
  autour du PK de la gare, `source`, `pk_source`, `precision`) ; `operational_points[]` (UOPID du RINF rattachés à la gare),
  `sources[]`, `counts`. Paramètres `line` (une ligne) et `kinds` (comme pour une ligne). GraphQL : types
  `StationTrackLayout`, `StationTrackLayoutStation`, `StationTrackLayoutPoint`, `StationTrackLayoutLine`,
  `StationTrackLayoutWindow`, `StationTrackPlatform`, `StationTrackLayoutCounts`. Paris-Austerlitz : lignes 570000 et
  984000, 11 quais ; Poitiers : 8 bords de quai (4 quais centraux), hauteurs de 30 à 58 cm.
- Page `/lines/{line}/voies` : `?pk=` (mètres) ouvre le schéma sur ce PK (lien « Schéma de toute la ligne » des voies
  d'une gare).

- Schéma des voies (REST `GET /api/v1/lines/{line}/track-layout`, GraphQL `Line.track_layout`, MCP `line_track_layout`) :
  `counts`, nombre d'objets de chaque famille sur la portion servie (`operational_points`, `tracks`, `switches`, `signals`,
  `crossings`, `buffer_stops`, `milestones`), quelles que soient les familles demandées. Un aperçu `kinds=operational_points`
  (110 Ko au lieu de 3,9 Mo pour Paris - Marseille) dit ainsi ce qu'une longue ligne a avant d'en lire les objets par
  portions (`from_pk_m`, `to_pk_m`) ; GraphQL : type `TrackLayoutCounts`.
- Schéma des voies : `strip.speeds[].tracks`, voies auxquelles la vitesse s'applique (références courtes : `V1`, `V2`,
  `V1S`…), `null` quand la source ne le dit pas. Deux sections parallèles d'une même ligne au RINF ont chacune leur vitesse
  au même PK (Paris-Austerlitz : 90 km/h sur `V1`, `V2`, 60 km/h sur `V1S`, `V2S`) ; GraphQL `TrackLayoutSpeed.tracks`.
- Schéma des voies : `strip.gradients[].tracks`, voies auxquelles la déclivité s'applique, `null` quand la source ne le dit
  pas : les voies de la section dont le profil déclaré au RINF donne cette valeur sur toute la portion (Paris-Austerlitz,
  PK 0 à 0+357 : -1,4 ‰ sur `V1`, `V2` et 0 ‰ sur `V1S`, `V2S`) ; GraphQL `TrackLayoutGradient.tracks`. La spécification
  REST décrit désormais aussi `strip.speeds[].tracks`. Les voies d'une vitesse ou d'une déclivité sont rangées dans l'ordre
  naturel (`V1`, `V2`, `V1S`), et non plus dans l'ordre de lecture de la base.

### Corrections

- Schéma des voies (REST `GET /api/v1/lines/{line}/track-layout` et `GET /api/v1/stations/{station}/track-layout`, GraphQL
  `Line.track_layout` et `Station.track_layout`, MCP `line_track_layout` et `station_track_layout`), sans champ nouveau ni
  retiré :
  - une voie d'OpenStreetMap n'est plus écartée sur une portion décrite par le RINF quand un de ses signaux, heurtoirs,
    aiguillages ou traversées la cite, ou quand elle est une voie d'un point d'exploitation du RINF (même numéro) : elle
    remplace alors la voie approchée du RINF (`rinf_id`, `rinf_ref`, `op_id`, `platform` vrai si elle longe un quai). À
    Paris-Nord, les parties à quai des voies 3 à 21 et 30 à 36 sont servies ; plus aucun signal, heurtoir, aiguillage ni
    traversée ne cite une voie absente de `tracks` (ligne 272000 entière : 89 signaux, 14 heurtoirs et 3 traversées avant ;
    Paris - Marseille : 28, 19 et 7) ;
  - un code de voie en lettres est le même avec ou sans le « V » de « Voie » : « DO » d'OpenStreetMap est la voie
    « VDO de Paris-10 à St-Denis » du RINF et n'est plus servi deux fois ; « 3 » de « Quai V1/V1BIS » (Brétigny) est V1BIS ;
  - un objet qui citait une voie absorbée par une voie du RINF en plusieurs pièces cite la pièce qui porte son PK (avant :
    la pièce la plus recouverte, parfois hors de la portion servie) ;
  - `lane` : les deux voies d'un même quai sont sur des rangées voisines, de part et d'autre de lui, la voie d'un quai
    partagé avec la voie 1 ou 2 juste au-delà d'elle, et les voies à quai d'une gare dans l'ordre de leurs numéros quand
    leurs décalages d'OpenStreetMap séparent un quai (Paris-Austerlitz : 3, 4, 5, 6, 7, 14, 15, 17… au lieu de 14, 18,
    15, 19…) ;
  - voies d'une gare, `platforms[].track` : la pièce de la voie qui porte le PK de la gare ; un bord de quai trouve sa voie
    par le nom de son quai (Brétigny « 3 » et « 4 » : V1BIS et V2BIS) et, sur une ligne à voie unique, la seule voie
    principale du point que le schéma n'a pas sous son nom est la voie unique (Cognac « Quai VD ») ; la position approchée
    d'un quai s'étend sur les pièces contiguës de sa voie.
- Schéma des voies : un signal d'OpenStreetMap cartographié sans catégorie (`railway:signal:<catégorie>` absent, 5 332 des
  17 818 signaux importés le 24/11/2026, sur 247 lignes) a pour `kind` `unknown` au lieu de `null` : la spécification REST
  le déclarait non nul et GraphQL `TrackLayoutSignal.kind` (non nul) faisait échouer tout le champ `Line.track_layout` qui
  le sélectionnait sur ces lignes (Paris - Marseille, Paris - Lille, Paris - Bordeaux…).
- Schéma des voies : la source `vff` donne sa mention à reproduire (`sources[].attribution`, nulle jusqu'ici) : données de
  SNCF Réseau (ODbL : vitesses, passages à niveau, PK des gares) et altitudes de l'IGN (Licence Ouverte 2.0) du profil en long.
- Livret de parcours : toutes les références sont préfixées du rang de la portion comme les objets qu'elles citent
  (`tracks[].op_id`, `crossings[].tracks`, `buffer_stops[].track`) ; la voie d'une gare de changement de ligne cite le point
  d'exploitation gardé (celui de la portion précédente). Jusqu'ici, `op_id` et les voies d'une traversée gardaient
  l'identifiant de leur ligne et ne désignaient aucun objet du livret (842 et 96 sur Lille-Flandres - Marseille-St-Charles).
- Paramètres RINF d'une ligne : un quai rapproché dont une des deux longueurs manque n'est plus compté d'accord (`agree`)
  mais `length_unknown` (77 quais sur la France le 25/11/2026). Les voies « Voie 1 BIS LAON », « Voie 2 QUATER », « Voie 2
  ter », « Voie 1 SP de … », « Voie 1 RV de … », « Voie unique VMA de … » ne sont plus prises pour la voie 1, 2 ou unique de
  leur section (une vingtaine de voies : le Crocodile de « Voie 1 BIS LAON » s'ajoutait au contrôle de vitesse de 261000).
- Schéma des voies : une voie bis ou ter écrite avec une espace (« 2 bis » d'OpenStreetMap) n'est plus prise pour la voie 2
  elle-même : elle n'est plus absorbée par la voie 2 du RINF qu'elle longe.
- MCP `routebook` : le bloc d'attribution crédite SNCF Réseau et l'IGN quand la source `vff` sert des vitesses, des passages
  à niveau ou des déclivités, comme `line_track_layout`.

- Schéma des voies : une voie d'OpenStreetMap dont l'étendue en PK dépasse deux fois sa longueur plus 2 km (calage du PK
  rompu : 496 voies de 40 lignes à l'import du 24/11/2026, dont une voie de 602 m placée du PK 1+516 au PK 523+044 de
  Paris - Marseille) n'est plus servie.
- Schéma des voies : une même voie décrite deux fois n'est plus servie qu'une fois. Une voie de ligne du RINF (identifiant
  officiel) absorbe les voies d'OpenStreetMap de même numéro (`1bis` et `V1BIS`, `U` et `VU`) ou sans numéro au même endroit
  (décalage latéral à 1,5 m près), et la voie de gare approchée du RINF de même numéro ; elle prend leur place latérale
  (`lane`), et les aiguillages, signaux, heurtoirs et traversées qui citaient une voie absorbée citent la voie gardée. Les
  pièces d'une même voie (voie du RINF coupée à chaque section) ont une seule `lane`. Paris - Bordeaux, PK 0 à 3 : 507
  voies au lieu de 558. Un passage à niveau « PN 1 » de SNCF Réseau et « 1 » d'OpenStreetMap au même endroit n'en font
  qu'un.

## 24/11/2026

### Ajouts

- Livret de parcours entre deux gares (REST `GET /api/v1/routebook?from=&to=`, GraphQL `routebook(from:, to:)`, outil MCP
  `routebook`) : le plus court chemin sur le réseau d'aujourd'hui (comme `GET /api/v1/route`), puis le schéma des voies de
  chaque ligne traversée, mis bout à bout dans le sens de marche. `legs` : lignes traversées dans l'ordre, avec les PK de leurs
  deux bouts (gare, ou changement de ligne en pleine voie), le sens (`up`, `down`) et la précision des PK ; `line_changes` ;
  objets au format de `track-layout` avec `leg`, `line_code`, `line_slug` et `route_km` (ou `route_from_km`, `route_to_km`),
  `route_lane` pour les voies, `travel` pour les signaux, `travel_facing` pour les aiguillages, déclivités dans le sens de
  marche (`permille`, et `line_permille`) ; `coverage` en kilomètres parcourus (`what` = `all` pour une portion sans aucun
  schéma). Départ et arrivée au centre de la gare (son PK sur la ligne). Erreurs de `GET /api/v1/route`. GraphQL rend les
  objets en JSON.
- Schéma des voies d'une ligne (REST `GET /api/v1/lines/{line}/track-layout`) : voies principales numérotées, voies de gare,
  d'évitement et de service, liaisons, aiguillages, traversées, signaux et points d'exploitation dans l'ordre des PK, avec une
  bande des vitesses et des déclivités et la couverture par source (`coverage`). Sources par ordre de préférence : RINF de
  l'Agence de l'Union européenne pour les chemins de fer (`rinf`), OpenStreetMap (`osm`), données de VFF (`vff`) ; provenance
  et précision (`exact`, `derived`, `approximate`) sur chaque objet, rangée du schéma (`lane`) calculée par le serveur.
  Paramètres `from_pk`, `to_pk` (kilomètres, `17+500` à encoder `17%2B500`), `from_pk_m`, `to_pk_m` (mètres), `kinds`. Une
  ligne sans donnée renvoie 200 et des listes vides. SNCF Réseau ne déclare au RINF ni aiguillage ni signal. Même contenu en
  GraphQL (`Line.track_layout(from_pk_m, to_pk_m, kinds)`, types `TrackLayout…`, énumération `TrackLayoutKind`) et en MCP
  (outil `line_track_layout`, avec le bloc d'attribution) ; fournisseur `rinf` ajouté à `sources_and_licences`. Chaque objet
  a tous les champs de sa famille (nuls si la source ne les donne pas) ; une source dont une table manque est ignorée (200).
  GraphQL et MCP servent aussi `buffer_stops` et `milestones` (types `TrackLayoutBufferStop`, `TrackLayoutMilestone`,
  valeurs `BUFFER_STOPS`, `MILESTONES` de `TrackLayoutKind`), `form` des signaux, `use`, `tracks` et `precision` des
  traversées, `station_slug` des voies.
- Schéma des voies, suite : `buffer_stops` (heurtoirs : `id`, `pk_m`, `track`, `source`, `precision`) et `milestones`
  (bornes kilométriques : `id`, `pk_m`, `label` tel qu'écrit sur la borne, `source`, `precision`), familles demandables
  par `kinds` ; voies, aiguillages, signaux, traversées, heurtoirs et bornes d'OpenStreetMap (`osm`, ODbL, « ©
  contributeurs OpenStreetMap ») là où le RINF n'en donne pas ; `form` (forme du signal), `use` (passage à niveau routier
  ou piéton), `station_slug` et `max_speed_kmh` des voies OSM en plus ; `kind` d'un aiguillage vaut `null` quand OSM
  montre trois voies qui se rejoignent sans dire le type d'appareil.

### À vérifier dans votre client

- Faits sans source redistribuable, suite du 23/11 (REST, GraphQL, MCP) : aucun fait, date, extrait ni lien d'une source dont
  la réutilisation hors de VFF n'est pas permise n'est plus servi ; seul son crédit reste (`archeo_url` et `sources` de la fiche
  d'une ligne, `GET /api/v1/sources`, `provenance` et `withheld` des tronçons). Aucun champ retiré : des valeurs deviennent
  nulles ou des éléments de liste disparaissent.
  - Histoire d'un événement (`GET /api/v1/lines/{line}/stories/{event}`) : `sources` ne liste plus un tel événement, `event`
    devient la première source servie du fait (301 vers son adresse si besoin), et l'histoire répond 404 quand l'événement
    demandé n'est pas servi (comme `GET /api/v1/events/{id}`) ou qu'aucune source du fait ne l'est (comme GraphQL
    `LineEvent.story` et le MCP `line_stories`). `url` de `GET /api/v1/lines/{line}/stories` pointe vers l'événement servi.
  - `GET /api/v1/lines/{line}/history` : `events` suit `GET /api/v1/events` (un tel événement n'y figure plus).
  - Histoire de l'électrification (`GET /api/v1/lines/{line}/electrification-history`, GraphQL
    `Line.electrification_history`, MCP `line_electrification_history`) : un événement qui ne cite qu'une telle source n'y
    figure plus.
  - Chronologie du réseau (`GET /api/v1/chronology`, `/chronology/{decade}`, MCP `network_chronology`) : un tel fait n'est
    plus compté dans `hidden`.
  - Autres noms d'une ligne (`names` de `GET /api/v1/lines/{line}`, GraphQL `sheet.line.names` et `alt_names`) : sans les
    noms tirés d'une telle source. GraphQL `Line.alt_names`, qui échouait (la colonne est un objet de provenance), renvoie
    désormais la liste des autres noms, comme `sheet.line.alt_names`.
  - Écartement lu dans une telle source : `gauge_mm` et `gauge_class` sont nuls dans `GET /api/v1/lines/{line}/segments`,
    `/geojson`, `GET /api/v1/eras/{year}/lines.geojson`, GraphQL `LineSegment`, `GET /api/v1/what-was-here` et le MCP
    `what_was_here` ; l'écartement dominant d'une ligne (`key_figures.gauge_mm`, MCP `get_line`) ne le compte plus. Le site
    garde ces écartements.

## 23/11/2026

### À vérifier dans votre client

- Dates sans source redistribuable (REST, GraphQL, MCP, données ouvertes) : une date de ligne ou de gare qui ne repose que
  sur une source dont la réutilisation hors de VFF n'est pas permise n'est plus servie. Un tel événement de ligne ne figure plus
  dans `GET /api/v1/events`, `GET /api/v1/lines/{line}/stories`, les GraphQL `lineEvents`, `Line.events`, `lineEvent(id:)`
  (`null`), `Media.event` et `LineEvent.story`, ni dans la chronique, les phases et le compte d'événements de la fiche d'une
  ligne (`GET /api/v1/lines/{line}`, GraphQL `sheet`) ; `GET /api/v1/events/{id}` répond 404. Un événement de gare déduit
  d'un tel événement de ligne (`inferred:line_event`) ne figure plus dans `GET /api/v1/stations/{station}/events`, GraphQL
  `Station.events` ni la chronologie de la fiche. L'ouverture et la fermeture d'une gare que seuls de tels événements donnent
  (`opened_at`, `closed_at` de l'annuaire, de la fiche de la gare et des gares de la fiche d'une ligne, GraphQL `Station`,
  `StationSheet`, `StationDirectoryItem`, `LineSheetStation`, MCP `list_stations`, `get_station`, `get_commune`) prennent la
  date et la précision d'un événement servi de même nature et de même année, ou deviennent nulles avec leur précision et leur
  borne (`opened_after`, `closed_after`) ; les tris `opened` et `closed` de l'annuaire suivent la date servie. Une borne de
  desserte (`service_start`, `service_end`) dont l'événement n'est pas servi prend un événement servi équivalent ou devient
  nulle ; les années de desserte, calculées par VFF, restent. Fiche d'un département (REST `GET /api/v1/departments/{code}/history`,
  comme le MCP `department_history`) : mêmes dates de lignes que le MCP, et une fermeture ne prend plus qu'une fermeture servie
  de même portée (totale, voyageurs ou fret). Fiche d'une commune (REST `GET /api/v1/communes/{insee}`) : mêmes dates de gares
  que le MCP `get_commune`. Données ouvertes : une date de gare déduite d'un tel événement est retirée (comptée dans le
  LISEZMOI). Aucun champ retiré : seules des valeurs deviennent nulles ou des éléments de liste disparaissent. Le site garde
  toutes ses dates, avec leur source.

- Gares, ouverture encadrée par une correction sourcée : une halte créée après sa ligne sans date connue (Compans, Renescure,
  Saint-Étienne-du-Rouvray, Sainte-Colombe-Septveilles) n'a plus la date de sa ligne mais un intervalle, comme les ouvertures
  de la règle G3 : `opened_precision` = `range`, `opened_at` = la borne haute (aujourd'hui sans borne haute connue) et
  `opened_after` = l'année qui précède la borne basse (REST, GraphQL, MCP, annuaire, liste des gares d'une ligne). La
  chronologie de la fiche en fait une seule entrée `range`. Ajouts : `opened_precision` et `opened_after` dans les propriétés
  de `GET /api/v1/eras/{year}/stations.geojson` (`opened_year` y reste la borne haute d'une ouverture `range`), et la colonne
  `opened_after` dans `gares.csv` des jeux ouverts. Une ouverture corrigée remplace désormais aussi sur la carte la date plus
  ancienne d'une autre source. Étaples-Le Touquet, Rue et Neufchâtel-Hardelot ouvrent le 21/12/1847 (et non le 21/11).

- Recherche (REST `GET /api/v1/search`, GraphQL `search`, MCP `search`, pages Gares et Accidents et leurs API de liste) : le
  classement change pour quelques requêtes. La gare ouverte aux voyageurs d'une commune dont le nom commence par celui de la
  commune vaut un nom exact (`strasbourg` donne Strasbourg-Ville avant l'ancienne gare Strasbourg) ; à pertinence égale, la gare
  la plus fréquentée passe devant (Mulhouse-Ville avant Mulhouse-Dornach). Les mots génériques (`gare`, `station`, `Bahnhof`,
  `ligne`, `line`, `Strecke`, `accident`, `UIC`, et `the`, `to`, `von`…) sont facultatifs : `Paris Nord station`, `ligne 830 000`
  et `UIC 87686006` trouvent leur fiche, et un code de ligne ou UIC se reconnaît malgré eux. Les noms allemands et anglais des
  villes valent le nom français (`Straßburg`, `Mülhausen`, `Diedenhofen`). Une année à côté d'autres mots limite les accidents à
  cette année (`Lagny 1933`, `accident gare de Lyon 1988`), et l'ancien slug d'une gare fusionnée ou d'une ligne renommée trouve
  la fiche actuelle, en premier. Aucun champ ne change.

- MCP : un outil appelé avec l'ancien slug d'une ligne renommée, d'une gare fondue dans une autre fiche ou d'un accident
  renommé ou fusionné (argument `line`, `station` ou `accident`, et filtres `line` et `station` de `network_events`, `media`,
  `accidents`, `materials`, `level_crossings`) répond avec la fiche actuelle comme avant, et le dit désormais en tête de
  réponse : `redirected`, liste de `kind`, `requested`, `current`, `reason` (`renamed` ou `merged`) et `note`. Les filtres
  `line` et `station` de `network_events`, `media`, `accidents` et `materials` acceptent maintenant un ancien slug (ils
  répondaient par une erreur de validation). Ajout seul ; la clé est absente sans ancienne adresse.

- Matériel d'une ligne (fiche `materials`, REST `GET /api/v1/lines/{line}/materials`, GraphQL `LineMaterialLink`, MCP `line_materials`) :
  une date sourcée ne se déplace plus. Une période datée par une source qui commence avant la première électrification connue dans VFF
  garde sa date (BB 25500 sur Grenoble - Montmélian depuis septembre 1985, BB 22200 sur Paris - Brest en 1983) : la borne
  `earliest_on_line` porte alors `conflict` = `true` (une électrification plus ancienne manque probablement dans VFF ; la borne n'est
  pas une date sûre), et `start_bounded_by` ne s'applique plus qu'aux périodes sans source. Les périodes servies portent toutes une
  source : aucune n'est déplacée. Ajout seul (`LineSheetMaterialBound.conflict`).

- Matériel d'une ligne : l'API REST (`GET /api/v1/lines/{line}/materials`, chaque lien), GraphQL (`LineMaterialLink`) et l'outil MCP
  `line_materials` (chaque série) donnent ce que la fiche de la ligne en dit : `link_doubt` (série seulement citée, en essais, en
  présentation, en commande...), `earliest_on_line` (borne d'électrification, avec `conflict`), `family` (famille dont la série est une
  version) et `folded_into_versions` (famille que la fiche ne montre pas parce qu'une de ses versions a circulé) ; MCP : `doubtful`
  compte les séries douteuses. Calculés sur tous les liens de la série sur la ligne, quels que soient les filtres. Aucun lien n'est
  retiré des listes. Sur la fiche, une famille qui a circulé reste montrée quand ses versions liées ne sont que douteuses. Ajout seul.

- Médias (REST `media`, galeries `lines|stations|materials/{slug}/media`, GraphQL `Media.captions`, outil MCP `media`) : nouveau champ
  `captions` (objet avec les clés `fr`, `en` et `de` présentes seulement pour les langues écrites, ou `null`), la légende par langue d'une
  image proposée par un contributeur. `caption` reste la légende principale (le français, sinon la première langue donnée ; pour une image
  de Wikimedia Commons, celle de Commons) : un client qui ignore `captions` continue de fonctionner. Pour une image de Commons, `captions`
  ne porte que les ajouts en anglais et en allemand, jamais de `fr`. Montrez `captions[lang]` quand elle existe, sinon `caption`. Ajout seul.

- Gares : dans `chronology[]` (REST, fiche web), une entrée `name` peut avoir `name.from` nul : nom dont on ne connaît que la fin
  (« Orléans-Ceinture » jusqu'aux années 1970), placé à sa fin avec la précision de sa date (`day`, `month`, `year` ou `decade`) et le lien de
  sa source (`vff:import-station-names` : historique des articles de Wikipédia, noms de Wikidata). Aucun champ n'est retiré.

- Accidents : `nearest_station` (REST, GraphQL, MCP `get_accident`) vaut désormais `null` quand le lieu n'est connu qu'à la commune
  près (`location.precision` = `commune`, centre de la commune) : la gare « la plus proche » d'un centre de commune n'était qu'une
  coïncidence (sept accidents de Paris rattachés à Paris-Nord « à 0 m »). Le filtre `station` de `GET /api/v1/accidents` et la
  chronologie des gares ne les renvoient plus. Aucun champ n'est retiré ni ajouté ; la fiche web affiche « À Paris, gare non précisée ».

- Accidents : la presse d'époque numérisée (Delpher, Deutsches Zeitungsportal, Europeana, Gallica, Le Temps...) compte désormais comme
  une famille de sources pour le statut `ok` (`vff:verify-accidents`) : un article lu confirme la fiche s'il en donne la date (au jour, ou
  « hier » dans le journal du lendemain), la commune et le bilan (le même, ou un bilan provisoire inférieur ou égal) ; plusieurs articles ne
  comptent que pour une famille. Dans `review.verification` (REST) et `verification` (GraphQL, JSON), `families.date` et `families.place`
  peuvent contenir `press`, les entrées de `confirmed_by` et `disagreeing` peuvent avoir `role` = `press` avec `deaths` et
  `deaths_provisional`, et un objet `deaths` (bilan de la fiche, articles qui le confirment ou en donnent un autre) apparaît quand un article
  donne un bilan. Ajout seul ; la fiche web dit quelles familles ont confirmé une fiche relue.

- MCP : les faits d'une source à usage non commercial ne sont plus servis par le serveur MCP, qu'elle soit
  nommée dans `source` ou seulement par le lien cité : `line_events`, `network_events` et `get_line` (`events`, `event_count`)
  peuvent donc renvoyer moins d'événements qu'avant, et `source` n'y prend plus la valeur de cette source. L'API REST et GraphQL ne
  changent pas. Aucun champ n'est retiré.

- Gares : l'ancien slug d'une fiche fondue dans une autre (deux fiches pour une même gare, action `merge` de
  `database/data/gares_corrections.php`) répond `301` vers la même adresse sous le slug de la fiche gardée, sous-chemin et paramètres
  gardés, pour toute route REST `GET` ou `HEAD` qui prend `{station}` (fiche, médias, fréquentation, horaires, chronologie...) ; GraphQL
  `station(slug:)` et les outils MCP renvoient la fiche gardée (`slug` fait foi). Un envoi (`POST`) n'est jamais redirigé. Ajout seul.
  `curl -sI https://vff-project.org/api/v1/stations/<ancien-slug>` : `HTTP/2 301`, `location: https://vff-project.org/api/v1/stations/<slug-actuel>`.
  GraphQL : `{ station(slug: "<ancien-slug>") { slug name } }` répond la fiche sous son slug actuel.

- Accidents : dans `review.verification.date.confirmed_by[]` et `place.confirmed_by[]` (REST ; `verification` en GraphQL, JSON), une source
  `bea-tt` dont la page d'enquête a été relue à la main porte `report` : `title`, `url`, et `number` et `published` quand ils sont connus.
  Le jour et la commune de cette page priment sur ceux du bilan des enquêtes, et une source primaire lève les notes d'import qu'elle
  tranche sur la commune ou la date : des fiches passent à `ok` (`rule` = `primary_source`) et deux dates décalées d'un jour par le
  bilan sont corrigées. Ajout seul. `curl -s https://vff-project.org/api/v1/accidents/<slug> | jq '.data.review.verification.date.confirmed_by[] | select(.report) | .report'`.
  GraphQL : `{ accident(slug: "<slug>") { verification } }`, puis `verification.date.confirmed_by[].report` dans le JSON.

- Accidents : nouvelle famille de sources `wikipedia-edition` dans `review.verification.families` (REST) et `verification.families`
  (GraphQL) : les listes d'accidents et les articles dédiés de Wikipédia en anglais et en allemand, lus comme une source de plus à côté
  des listes françaises (`vff:import-accidents --only=wikipedia-editions`). Un article ne crée jamais de fiche : il se rattache à la fiche
  du même jour et de la même commune. La règle `wikipedia_editions` de `rule` (Wikipédia en français plus une édition qui cite sa propre
  référence) passe ainsi sur 47 fiches au lieu de 34. Ajout seul : une valeur de plus dans une liste déjà ouverte.

- Signalisation : `section.location` de l'historique (REST `GET /api/v1/lines/{line}/signalling-history`, GraphQL
  `Line.signalling_history { section { location } }`, MCP `signalling_history`) prend la valeur `landmarks` : une portion dont une
  extrémité au moins est un point remarquable de la ligne (bifurcation, raccordement, origine, fin, frontière), placé par la table relue
  `database/data/line_landmarks.php`, avec la gare de l'autre bout s'il y en a une. Quatorze des quinze faits datés des lignes à grande
  vitesse, jusque-là `unlocated`, sont placés. Une valeur de plus dans une liste déjà ouverte : un client qui n'attend que `stations`,
  `article_pk` et `unlocated` doit la traiter comme une portion placée (`pk_start_m`, `pk_end_m`).
  `curl -s https://vff-project.org/api/v1/lines/rfn-752000/signalling-history | jq '.history[] | select(.section.location == "landmarks")'`.
  GraphQL : `{ line(slug: "rfn-752000") { signalling_history { system date section { from to location pk_start_m pk_end_m } } } }`.

- Signalisation : la commande centralisée (`CC` : CCVU, CCR, PCD) devient un système à part, avec le rôle `remote_control` (hors
  cantonnement : elle se combine à BAL, BAPR, block manuel ou téléphonique sans en remplacer aucun) et son propre groupe parmi les faits en
  vigueur à une ère (`in_force`). Valeur de plus de `system` et de `role` dans REST, GraphQL (`SignallingEvent.system`, `.role`) et MCP
  (`signalling_history`). 18 faits datés lus dans les articles de Wikipédia (Saumur - Thouars 1982, Epernay - Reims 1962,
  LGV Sud-Est 2024...). Un client qui filtre sur `role=block` ne les voit pas, comme avant. Ajout seul.
  `curl -s 'https://vff-project.org/api/v1/lines/rfn-752000/signalling-history?era=2030' | jq '.history[] | select(.system == "CC")'`.
  GraphQL : `{ line(slug: "rfn-752000") { signalling_history { system role date } } }`.

- Liens gare - ligne : `link_method` (REST `GET /api/v1/lines/{slug}`, `stations[]` ; GraphQL `LineSheetStation.link_method`) prend la
  valeur `reviewed_link` pour un couple gare - ligne ajouté à la main d'après une source (`database/data/station_line_inclusions.php`). Le
  lien compte comme un rattachement ferme (contrairement aux terminus nommés). Une valeur de plus dans une liste déjà ouverte ; ne vous en servez que pour l'afficher.

### Ajouts

- Matériel roulant : chaque période de circulation (`periods[]` de `GET /api/v1/lines/{line}/materials` et de
  `GET /api/v1/materials/{material}`, outils MCP `line_materials` et `get_material`, fiches web) porte `portion` : la portion de
  la ligne parcourue, placée sur le tracé quand la source la nomme (titre de section, relation citée par l'article de la série,
  portion écrite dans la phrase datée), avec `location` (`stations`, `landmarks`, ou `line` pour la ligne entière), `from` et `to`
  (`id` et `name` de la gare, `id` nul pour un point remarquable), `pk_start_m` et `pk_end_m` (PK calés, en mètres). `null` quand
  aucune portion n'est nommée ou qu'elle n'a pas pu être placée sûrement. GraphQL : `LineMaterialPeriod.portion`
  (`LineMaterialPeriodPortion`, `LineMaterialPortionEnd`). `section` reste la portion telle que l'écrit le titre de section. Ajout seul.
  `curl -s https://vff-project.org/api/v1/lines/<slug>/materials | jq '.data[].periods[] | select(.portion) | .portion'`.

- Recherche : le glossaire dans GraphQL et sur la page `/search`. La requête GraphQL `search` renvoie `glossary`
  (`SearchGlossaryTerm` : `slug`, `name(lang: "fr" | "en" | "de")`, `abbr` ; au plus `limit` termes, mêmes termes et même ordre
  que `glossary` de `GET /api/v1/search`). La page `/search` a un groupe « Glossaire » (`type=glossary`) entre le matériel
  roulant et les communes, avec le nom dans la langue de la page. Ajouts seuls.
  `curl -s https://vff-project.org/graphql -H 'Content-Type: application/json' -d '{"query":"{ search(query: \"tvm 430\") { glossary { slug name abbr } } }"}'`.

- Recherche : termes du glossaire. REST `GET /api/v1/search` renvoie `glossary` (5 termes au plus : `type`, `slug`, `name`,
  `names` en `fr`, `en` et `de`, `abbr`), le serveur MCP accepte `glossary` dans `kinds` de `search` (cherché par défaut, avec
  `vff_url` vers `/glossary#{slug}`). Trouvés par le nom, le sigle ou le développé dans l'une des trois langues (`TVM 430`,
  `tvm430`, `KVB`, `block automatique lumineux`, `overhead line`, `Fahrleitung`). Ajout seul.

- MCP : sept outils couvrent ce que REST était seul à servir. `bibliography` (REST `/lines/{line}/bibliography` et
  `/stations/{station}/bibliography`, paginé par `limit` et `offset`), `protected_heritage` (REST `/heritage`, mêmes filtres
  plus `department`, paginé, `facets.architects` limité aux 40 plus fréquents), `companies` (REST `/companies` et
  `/companies/{company}`, lignes rattachées paginées, sans le tracé `map`), `get_commune` (REST `/communes/{insee}`, par code
  INSEE ou par nom, erreur `commune_ambiguous` avec `candidates`, chronologie paginée), `department_history` (REST
  `/departments/{department}/history` et `/stats/departments`, par code ou par nom, lignes paginées, 60 communes au plus),
  `data_coverage` (REST `/stats/coverage`, filtre `set`) et `position_at_pk` (REST `/lines/{line}/at-pk`, `pk` ou `pk_m`).
  Chaque réponse porte `vff_url`, `attribution` et, pour une ancienne adresse de ligne ou de gare, `redirected`. Comme pour les
  autres outils, rien d'une source à usage non commercial n'est servi : dans `get_commune`, un événement de gare de cette seule
  source est retiré de la chronologie ; dans `department_history`, une date de ligne de cette seule source est remplacée par la
  date d'une source servie de la même année, ou nulle. `tools/list` donne désormais 57 outils, toujours sur une seule page.
  Ajout seul ; REST et GraphQL ne changent pas.

- MCP : trois outils, sur les mêmes services que l'API REST. `preserved_rolling_stock` (matériel préservé : exemplaires
  d'une série ou lieux et leurs pièces, état dit par la source, protection monument historique de la base Palissy, sources
  avec licence ; filtres `venue_type`, `venue`, `state`, `protected` ; `limit` et `offset`), `rail_projects` (projets
  ferroviaires et leurs étapes datées et sourcées ; filtres `state`, `kind`, `region`, `line` ; `limit` et `offset`) et
  `station_events` (chronologie d'une gare filtrée par type, source, date déduite ou relevée et période, paginée par
  curseur). `get_material` ajoute `preserved_count`. `tools/list` tient en une page de 80 outils.

- GraphQL : `EventContributionInput` (mutation `submitContribution`, `input.event`) reçoit `source_excerpt` (citation courte de la
  source, publiée avec l'événement à la place de sa référence), `corrects_event_id` et `correction_reason` : avec l'identifiant d'un
  événement de la ligne, la proposition corrige cet événement (contribution d'action `UPDATE`) ; à la publication, l'événement visé est
  neutralisé (type `other`, « correction sourcée »). La charge utile porte alors `corrects` et `correction_reason`, et
  `applied.corrected_event_ids`. Ajout seul.

- Profil en long (REST `GET /api/v1/lines/{line}/profile`, GraphQL `LineProfile`, MCP `line_profile`) : `sourced_grade_compared`
  donne, quand la déclivité mesurée est publiée, celle d'une source écrite (Wikipédia ou SNCF Réseau, même forme que
  `sourced_grade`) à côté de la mesure, avec `difference_permil` (mesure moins source, en ‰) ; `null` sinon. `trace_holes` dit
  ce qui manque au tracé de référence : `count`, `length_m` (à vol d'oiseau), `share` (part du parcours) et `ranges`, sans les
  trous conventionnels posés devant les embranchements (`gaps` reste inchangé). 37 déclivités de Wikipédia de plus (515 lignes),
  lues dans l'infobox ou le texte des articles par `vff:import-wikipedia-grades`. Ajouts seuls.

- Fiche d'une ligne (REST `GET /api/v1/lines/{slug}`, `materials` ; GraphQL `LineSheet.materials`) : chaque série porte `link_doubt`, la raison pour laquelle toutes les sources mettent en doute qu'elle ait assuré le service sur la ligne (`trial` essais, `one_off` circulation exceptionnelle ou présentation, `order` commande, `positioning` acheminement à vide, `preserved`, `possible`, `diverted`, `restricted`, `component`, `section`, `other`), `null` pour une série qui a circulé. La page web range ces séries dans un groupe replié. Ajout seul.
- Fiche d'une ligne (`materials`) : une série électrique (locomotive électrique, rame TGV, automotrice à systèmes d'alimentation déclarés) ne commence pas avant la première électrification connue dans VFF, de la ligne entière ou d'une seule de ses sections : le début d'une période qui la précède est ramené à cette date (`start_date`, `start_precision`) et la période porte `start_bounded_by` (`electrification`) ; la série porte `earliest_on_line` (`reason`, `date`, `precision`, et `section` : portion nommée quand c'est celle d'une section et non de la ligne entière, nulle sinon), qui dit la date la plus ancienne possible. Ce n'est pas une date de service.
- Fiche d'une ligne (`materials`) : une famille de séries (Autorail grande capacité, Regio 2N) n'est plus listée à côté de ses versions liées à la même ligne (X 76500, Z 27500, B 82500) ; chaque version porte `family` (`slug`, `name`). Une famille seule sur la ligne, ou qui porte des périodes que ses versions n'ont pas, reste listée. Les liens de la famille restent lisibles par `GET /api/v1/lines/{slug}/materials`.
- Bibliographie des lignes et des gares : ouvrages et articles qui en traitent (auteurs, titre, éditeur, lieu, année, ISBN, lien vers la
  notice du catalogue général de la BnF ou vers la référence d'origine, raison du rattachement) : REST `GET /api/v1/lines/{line}/bibliography`
  et `GET /api/v1/stations/{station}/bibliography`. Ajout seul.
- REST : `GET /heritage` (patrimoine ferroviaire protégé : bâtiments voyageurs, ouvrages de gare et ouvrages d'art classés ou inscrits monuments historiques ou labellisés Architecture contemporaine remarquable ; pour chaque objet `protection`, `protection_date`, `architects`, `built` et `era`, `style`, `commune`, `region`, `reference` (Mérimée ou Wikidata), `image` libre avec sa licence, `station` ou `line` ; filtres `protection`, `object`, `style`, `architect`, `region`, `era` et tri `sort` ; `facets` donne les valeurs de chaque filtre avec leur nombre). Même contenu que la page `/heritage`. Ajout seulement.
- REST : `GET /stats/coverage` (ce que sait l'encyclopédie : pour les lignes, les gares, les accidents et le matériel, chaque information renseignée en nombre et en part `share`, dont `open` issu de sources ouvertes, précision des dates dans `levels`, ventilation par décennie et par ère, date du calcul `computed_at`). Page : `/network/coverage`.
- REST : `GET /materials/preserved` (matériel préservé : lieux, musées, associations et lignes touristiques avec leurs pièces, numéro, état, protection au titre des monuments historiques d'après la base Palissy, sources ; `meta` donne la couverture) et `GET /materials/{material}/preserved` (exemplaires préservés d'une série). Page : `/materials/preserved` ; section « Exemplaires préservés » de la fiche de série. Ajout seul.
- REST : `GET /lines/{line}/at-pk?pk=` (ou `pk_m=` en mètres) : position d'un point kilométrique sur une ligne (`pk` : `120`, `120,5` ou
  `120+500`). Donne les gares qui l'encadrent (`between`, ou `at_station`), la commune, les bornes de la ligne (`bounds`) et un point
  de carte (`lat`, `lng`, `position_method` : `profile`, `stations` ou `station`). PK hors de la ligne : 404 `pk_out_of_range` avec les bornes.
  `GET /search` ajoute `pk_positions` (vide sauf pour une requête de position comme « 570000 PK 120 », « Paris - Bordeaux km 300 »,
  « ligne 570000 120+500 ») ; les autres catégories ne changent pas.

- REST, GraphQL : `lines[].neighbours` de la fiche de gare (`GET /api/v1/stations/{slug}`, type `StationLine`) : gares précédente (`previous`)
  et suivante (`next`) le long de la ligne, par PK, avec `slug`, `name`, `type`, `status` (aujourd'hui), `distance_m` (écart de PK),
  `last_open_era` (dernière ère ouverte d'une gare fermée) et, en REST, `links`. `null` quand le PK de la gare est inconnu.
- REST : `GET /companies` et `GET /companies/{company}` (compagnies de chemins de fer historiques : Nord, Est, Ouest, Paris-Orléans, PLM, Midi, État, Alsace-Lorraine, SNCF, Réseau ferré de France, SNCF Réseau, Grand-Central et réseaux secondaires ; dates de création et de fin, siège, événements sourcés, lignes rattachées par un événement de concession daté avec la raison de la fin du rattachement `to_basis`, réseau par décennie, tracé simplifié à l'ère de plus grande extension, couverture par ère). Chiffres en cache, calculés par `vff:compute-company-figures` ; 503 tant qu'ils ne le sont pas. Ajout seul.
- REST : `GET /projects` (projets ferroviaires pas encore en service : réouvertures voyageurs, lignes nouvelles et LGV, RER métropolitains, électrifications ; état `study`/`debate`/`dup`/`funding`/`works`/`suspended`, étapes avec date à leur précision, `done` = franchie ou prévue, source de chaque étape, porteurs, fiches de ligne ; filtres `state`, `kind` et `region`). Une date prévue n'est jamais certaine : à lire avec sa source. Page : `/projects`. Ajout seul.
- Accessibilité et services des gares (SNCF Gares & Connexions et SNCF Voyageurs, ODbL) : REST
  `GET /api/v1/stations/{station}/accessibility` et champ `accessibility` de la fiche `GET /api/v1/stations/{station}` (avec
  `links.accessibility`), GraphQL `Station.accessibility` (types `StationAccessibility`, `StationAccessibilityAccess`,
  `StationAccessibilityQuays`, `StationAccessibilityElevators`, `StationAccessibilityElevatorStatus`, `StationAccessibilityElevator`,
  `StationAccessibilityAssistance`, `StationAccessibilitySales`, `StationAccessibilityBikeParking`, `StationAccessibilityDataset`),
  MCP outil `station_accessibility` : accès des personnes à mobilité réduite (`mobility` : `partial` ou `none`, non déclarée
  accessible), ascenseurs recensés et leur état publié daté (`state_at`, non contractuel), équipements (boucle magnétique, bandes
  d'éveil, toilettes…), assistance en gare avec horaires, guichet et automates, stationnement vélo sécurisé, jeux lus avec leur date et
  leur licence. Filtre `accessible` (gares accessibles au moins en partie) de `GET /api/v1/stations` et de l'outil MCP `list_stations`.
  `null` quand les jeux n'ont rien pour la gare ; une absence d'équipement ne prouve rien. Ajout seul.
- REST : `GET /lines/{line}/schematic?era=AAAA` (schéma de ligne par PK : gares et haltes avec leurs correspondances, bifurcations en pleine voie, passages à niveau, ouvrages d'art, et quatre bandes `tracks`, `electrification`, `signalling`, `speed`, chacune avec `available` ; `eras[]` donne les ères proposées ; aux ères passées, les jeux de SNCF Réseau ne sont pas proposés). 404 pour une ligne ou une ère inconnue, 422 pour une ère illisible. En cache. Ajout seul.
- Desserte d'un jour ouvrable type (un mardi d'une semaine sans jour férié, d'après les horaires ouverts SNCF, Transilien et ZOU !,
  ODbL) : REST `GET /api/v1/stations/{station}/timetable` et champ `timetable` de la fiche `GET /api/v1/stations/{station}` (avec
  `links.timetable`) : trains par type et par sens, premières et dernières heures, destinations directes, jeu d'horaires utilisé ;
  champ `timetable` de la fiche d'une ligne `GET /api/v1/lines/{line}` : trains par jour sur la ligne, chacun compté une fois.
  GraphQL `Station.timetable` (types `StationTimetable`, `StationTimetableDirection`, `StationTimetablePlace`, `StationTimetableFeed`)
  et `LineSheet.timetable` (type `LineTimetable`), MCP outils `station_timetable` et `line_timetable`. `null` quand aucun train de
  voyageurs ne s'arrête dans la gare (ou sur la ligne) ce jour-là : ce n'est pas une preuve de fermeture. Ajout seul.
- REST : `GET /accidents/statistics` (les accidents en chiffres : par décennie, par nature, part des passages à niveau, quinze bilans les plus lourds ; filtres `from`, `to`, `kind`, `review=ok` ; bilans inconnus jamais estimés, `deaths_known` et `injuries_known` donnent le nombre de bilans connus). Page : `/accidents/statistics`.
- Départements : `GET /api/v1/departments/{code}/history` (le rail d'un département décennie par décennie : `open_km`, `closed_km`,
  `service_km`, `dismantled_km`, `razed_km`, gares, grandes dates, lignes, communes, accidents) et `GET /api/v1/stats/departments`
  (tableau des départements, part fermée depuis 1930) ; agrégats en cache (`vff:compute-department-figures`), 503 tant que rien n'est
  calculé. Pages : `/departments` et `/departments/{code}-{nom}`. Ajout seul.
- Carte : `GET /api/v1/what-was-here?summary=1` (page « Autour de moi » `/nearby`) : rayon jusqu'à 10 000 m, 15 lignes et 20 gares
  au plus, `history` de chaque ligne (années d'ouverture, de fermeture et de dépose, `sections` pour une portion), années d'ouverture et
  de fermeture des gares et `commune` du point ; sans signalisation, matériel, événements ni accidents. Ajout seul.
- Communes : `GET /api/v1/communes/{insee}` (le rail d'une commune : gares avec leur état et leurs dates, lignes qui la traversent
  avec au moins 50 m de voie, événements des portions qui y passent, accidents ; 404 sans gare ni ligne) et clé `communes` de
  `GET /api/v1/search` (communes qui ont du rail, par leur nom : `insee`, `slug`, département, population ; sans noms approchés).
  Page : `/communes/{insee}-{nom}`. Ajout seul.
- Matériel roulant : `GET /api/v1/materials/timeline` (frise des séries : traction, mise en service et retrait à leur précision,
  exemplaires construits ; filtres `group`, `builder`, `in_service_at` ; séries sans date comptées dans `meta.undated`, jamais
  estimées). Page : `/materials/timeline`. Ajout seul.
- Carte : paramètre `color` (`speed`, `electrification`, `gauge`, `signalling`) de `GET /api/v1/eras/{year}/in-view` : chaque ligne
  porte alors `classes` (`key`, `km`), ses classes dans l'emprise ; `color` inconnu : 422. Tuiles allégées (`fields=map`) : propriété
  `speed_class` (1 à 4, absente sans vitesse connue). Ajout seul.
- Carte : `GET /api/v1/what-was-here?lat=&lng=&year=` (« Qu'y avait-il ici ? », panneau de la carte `/maps`) : autour d'un point et pour
  une année, les lignes présentes avec leur état à l'ère, leur PK et leur gare la plus proche, les gares proches, la signalisation, le
  matériel daté, les événements et les accidents de l'année, avec source et lien de chaque fait. Même service que l'outil MCP
  `what_was_here`, dont chaque ligne gagne `nearest_station` (slug, name, distance_m). Le point est ramené au centre de sa case de
  0,001 degré (cache par case et par année) ; un tracé non redistribuable ne donne ni distance ni PK précis. Ajout seul.
- Patrimoine des gares (base Mérimée, Licence Ouverte 2.0 ; Wikidata, CC0) : REST `GET /api/v1/stations/{station}/heritage`, champ
  `heritage` et `links.heritage` de la fiche `GET /api/v1/stations/{station}`, filtre `heritage` de l'annuaire (REST, GraphQL
  `StationDirectoryFilter.heritage`, MCP `list_stations`), GraphQL `Station.heritage` (types `StationHeritage` et suivants), MCP outil
  `station_heritage`. `null` quand VFF ne connaît aucun patrimoine pour la gare. Ajout seul.
- REST : `GET /tourist-railways` (chemins de fer touristiques et vélorails exploités aujourd'hui : exploitant, type `steam`/`railcar`/`tram`/`rack`/`velorail`/`train`, écartement, longueur exploitée, année de début, régions, lignes, site officiel, source de chaque fait ; voies vertes sur ancienne voie dans `greenways` ; filtres `type` et `region`). Page : `/tourist-railways`.
- Ouvrages d'art d'une ligne (tunnels, viaducs, grands ponts), lignes déposées comprises, dans l'ordre des PK : longueur, PK de début
  et de fin estimés sur le profil en long, dates de construction, de mise en service et de fermeture avec leur précision, protection
  monument historique et référence Mérimée, image libre de Commons, ères où la ligne y passe, source de chaque champ (IGN BD TOPO,
  Licence Ouverte 2.0 ; Wikidata, CC0) : REST `GET /api/v1/lines/{line}/structures`, GraphQL `Line.structures` (types `LineStructures`,
  `LineStructureSummary`, `LineStructureKindCount`, `LineStructureLongest`, `LineStructure`, `StructureDate`, `StructureImage`,
  `StructureSource`), MCP outil `line_structures`. Ajout seul.
- Ce jour-là : REST `GET /api/v1/on-this-day/{MM-JJ}` (aujourd'hui sans date ; filtres `types` et `lang`, `meta.coverage`), page `/on-this-day` et
  outil MCP `on_this_day` : ce qui s'est passé ce jour du calendrier les années précédentes, faits datés au jour avec au moins une source
  publiable, accidents relus seulement. Ajout seul.
- REST : `GET /chronology` et `GET /chronology/{decade}` (chronologie du rail en France, année par année depuis 1827 : ouvertures, réouvertures, fermetures, électrifications, signalisation, séries de matériel, accidents graves et jalons nationaux ; filtres `types`, `region`, `lang` ; chaque fait avec sa date à sa précision, sa fiche et ses sources publiables, les faits sans source publiable comptés dans `hidden`). Page : `/chronology`. Calcul : `vff:compute-network-chronology`.
- REST : `GET /api/v1/materials/{material}/map`, les lignes d'une série avec leur tracé allégé (MultiLineString à 40 m près, 4 décimales),
  la fiabilité du lien, sa source (`source_url`, `article`, `quote`) et les périodes datées ; règle des droits des tracés comme
  `/lines/{line}/segments` (`geometry_withheld`). Ajout seul.
- Carte : `GET /api/v1/eras/{year}/in-view?bbox=ouest,sud,est,nord` liste les lignes (état à l'ère, kilomètres dans l'emprise) et les
  gares d'une emprise, sous une forme compacte : alternative textuelle à la carte `/maps` (bouton « Liste de ce qui est affiché »). Listes
  plafonnées à 300 entrées (`total`), emprise bornée à 4 degrés sur 3 (`too_wide`). Ajout seul.
- Histoire de l'électrification d'une ligne : tranches de PK électrifiées avec leur tension (SNCF Réseau, ODbL) et mises sous tension,
  conversions de tension et désélectrifications datées et sûres (Wikipédia, Wikidata) : REST `GET /api/v1/lines/{line}/electrification-history`,
  GraphQL `Line.electrification_history` (types `LineElectrificationHistory`, `LineElectrificationRange`, `LineElectrificationSummary`,
  `LineElectrificationShare`, `LineElectrificationEvent`, `LineElectrificationSection`, `LineElectrificationSource`,
  `LineElectrificationProvenance`), MCP outil `line_electrification_history`. Ajout seul.
- Lignes : l'ancien slug d'une ligne renommée ou fusionnée dans une autre (fichier versionné `database/data/line_slug_redirects.php`)
  répond `301` vers la même adresse sous le slug actuel, sous-chemin et paramètres gardés (`GET /api/v1/lines/{line}/…`, pages
  `/lines/{line}`, paramètre `?line=` des listes) ; GraphQL `line(slug:)` et les outils MCP qui prennent une ligne acceptent l'ancien
  slug et renvoient la ligne sous son slug actuel (champ `slug`). Un ancien slug n'est jamais redonné à une autre ligne. Ajout seul.
- Fréquentation annuelle des gares (SNCF Gares & Connexions, ODbL) : REST `GET /api/v1/stations/{station}/attendance` et champ
  `attendance` de la fiche `GET /api/v1/stations/{station}` (avec `links.attendance`), GraphQL `Station.attendance` (types
  `StationAttendance`, `StationAttendanceYear`, `StationAttendanceLatest`, `StationAttendanceSource`), MCP outil `station_attendance`.
  Voyageurs (`passengers`) et voyageurs avec non-voyageurs (`footfall`) par année depuis 2015, `latest`, `series_break` : SNCF précise que
  les chiffres de 2025 (autres sources, autre méthode) ne sont pas directement comparables à ceux de 2015 à 2024. `null` quand les jeux
  n'en donnent aucune pour la gare. Ajout seul.
- Fiche gare (REST `GET /api/v1/stations/{station}`, GraphQL `StationSheet`) : nouveau champ `chronology`, la vie de la gare en une
  liste datée (événements de la gare regroupés quand leurs dates se recouvrent, autres sources dans `also`, `retained: false` pour une
  date contredite par la fiche ; début et fin de desserte par chaque ligne ; noms portés et datés ; accidents dont la gare est la plus
  proche, à 3 km au plus), chaque date avec `precision`, `estimated` et sa source. REST : `links.aerial` vers
  `/stations/{station}/aerial`. GraphQL : `StationSheet.aerial_views` (vues aériennes de l'IGN, mêmes champs que le point d'accès REST),
  types `StationChronologyEntry` et `StationAerialView`. Ajout seul.
- MCP : cinq outils. `what_was_here` (autour d'un point et pour une année : lignes présentes et leur état à cet endroit, gares
  proches, signalisation en vigueur, matériel daté, événements et accidents de l'année), `network_in_area` (réseau d'un
  rectangle ou d'un cercle à une époque : km en service, fermés, déposés, lignes et gares ; erreurs `invalid_area`,
  `area_too_large`, `area_too_small`), `compare_line_eras` (une ligne à deux époques : km par catégorie, gares ouvertes ou
  fermées, signalisation, événements entre les deux), `line_stops` (gares d'une ligne dans l'ordre des PK à une époque, comme
  `GET /lines/{line}/stops`, avec le lien GTFS de l'ère) et `station_aerial` (vues aériennes historiques de l'IGN, comme
  `GET /stations/{station}/aerial`, adresses absolues).

## 22/11/2026

Aucun champ n'a été retiré ni renommé depuis le 21/11. Les changements qui peuvent gêner un client sont les suivants.

### À vérifier dans votre client

- Médias (REST `media`, galeries `lines|stations|materials/{slug}/media`, GraphQL `Media`, outil MCP `media`) : nouveau champ
  `derivatives_ok` (booléen, nul si inconnu) ; `false` signale une licence sans modification (ND) : affichez l'image entière, réduite,
  jamais recadrée. `date_precision` est désormais aussi rangé en colonne (mêmes valeurs). `provider` peut valoir `panoramax` (photos de
  rue, Licence Ouverte 2.0 ou CC BY-SA 4.0 selon l'instance). Selon le réglage de l'instance (`VFF_MEDIA_SHOW_NON_COMMERCIAL`), les médias
  `commercial_ok: false` peuvent être absents de toutes les réponses : un client ne doit pas supposer qu'un média vu une fois reste listé.
  Nouveau champ `rights_uncertain` (booléen, jamais nul) : vrai quand le fonds du média a une licence « incertaine » (accord à
  confirmer ou portée de la licence non lue : cartes postales d'Évry-Courcouronnes et du musée Gadagne, Pireneas) ; à signaler avec l'image.
  Le réglage `VFF_MEDIA_SHOW_UNCERTAIN` (faux : ces médias sont absents de toutes les réponses) agit comme celui des médias non commerciaux.
  `provider` peut aussi valoir `rosalis`, `pireneas`, `evry-cp`, `gadagne-cp`, `sl71-cp`, `getty`, `enpc-ph`, `numistral`, `albert-kahn`, `ad92-9fi`, `suresnes-cp`, `sevres-cp`.
- Accidents : la nature `person_struck` (personnes heurtées hors passage à niveau) est désormais publiée en REST, GraphQL, MCP, carte et
  filtres, mais seulement pour une fiche appuyée par une enquête officielle (source `bea-tt`) ou par un contexte sourcé relu ; les autres restent
  masquées. Une fiche qui double une autre (même accident, deux entrées) n'est jamais listée : `GET /api/v1/accidents/{slug}` répond 301 avec
  `Location` vers la fiche retenue (de même `/accidents/{slug}` sur le site) et l'outil MCP `get_accident` renvoie directement la fiche retenue ;
  GraphQL `accident(slug:)` renvoie `null` pour l'ancien slug, la fiche retenue étant celle du lien REST.
- Nouveau point d'accès REST `GET /api/v1/stations/{slug}/aerial` : vues aériennes historiques de l'IGN (1950-1965, 1965-1980,
  1980-1995, Licence Ouverte 2.0), adresse de l'image (`/media/aerial/{slug}/{période}.jpg`, 404 hors couverture), crédit et lien.
- GraphQL, `SignallingEventSource.revision` passe de `Int!` à `Int`. REST, `source.revision` de `signalling-history` est `null` pour un fait
  daté par des documents (`source.provider: manual`). Un client typé qui suppose un entier doit accepter `null`.
- GraphQL accepte au plus 5 opérations par lot (liste de requêtes dans un seul POST). Au-delà : HTTP 400, `extensions.code` `batch_too_large`,
  `extensions.max_batch`.
- GraphQL et MCP refusent un corps de plus de 2 Mio (2 097 152 octets) : HTTP 413, `extensions.code` `body_too_large`. Le MCP répond dans ce cas
  par une erreur JSON-RPC `-32600`.
- REST, `page` des listes d'accidents et de passages à niveau est limité à 1 000 000 ; au-delà, HTTP 422 (c'était une erreur 500).
- Nouvelles valeurs possibles : `kind` d'un accident peut valoir `war_action` (fait de guerre), et `toll_source` peut valoir `manual` (fiche rédigée
  à la main d'après un document cité). Le filtre `kind=war_action` existe en REST, en GraphQL et dans l'outil MCP `accidents`.
- `signalling-history` : `origin` peut valoir `document`, et `source.provider` peut valoir `manual`.
- `sourced_grade.source.provider` du profil en long peut valoir `sncf` (SNCF Réseau, licence `ODbL 1.0`) en plus de `wikipedia` : la valeur
  vient alors d'une partie seulement de la ligne (`covered_share`), et l'attribution « SNCF Réseau » doit l'accompagner.
- Profil en long : `source` d'un point (REST `profile/points`, GraphQL `LineProfileSample.source`, `sources` de l'altitude à un PK) peut valoir
  `rge_track` (altitude de la voie estimée d'après le terrain) sur les profils calculés avec cette estimation. `altitude_sources.rge_track`
  compte ces points (0 sinon). Nouveaux champs : `track_estimate_ranges` (parties concernées) et `track_estimate` (méthode et erreur mesurée
  contre le MNT LiDAR HD de l'IGN, nul sans point estimé), en REST, en GraphQL (`LineProfileTrackEstimate`) et dans l'outil MCP `line_profile`.
- Export GTFS (`/eras/{year}/gtfs.zip`) : `shape_dist_traveled` de `stop_times.txt` est la distance de la gare le long du tracé (même unité que
  `shapes.txt`), plus le point kilométrique ; les tracés sont orientés dans le sens du trajet ; un trajet exige au moins deux gares desservies ;
  les heures sont calculées à 60 km/h ; le flux contient en plus `attributions.txt` et un contact dans `feed_info.txt`.
- Export GTFS : quand le tracé d'une ligne s'arrête au début d'une voie qu'elle partage avec une autre ligne (tronc commun jusqu'à la gare
  terminus, comme à Gannat ou Neuf-Brisach), la forme de `shapes.txt` continue le long de cette voie jusqu'à la gare, sans segment droit ;
  `shape_dist_traveled` des arrêts est désormais mesuré en mètres sur l'ellipsoïde, comme celui de `shapes.txt`.
- Gares d'une fiche de ligne : `status_today` vaut `null` aussi pour une gare absente de la carte d'aujourd'hui ou fermée avant la décennie en
  cours ; `eras` vaut `null` à une ère où la gare n'est pas sur la carte.

### Ajouts

- REST : `GET /stats/network` (le réseau en chiffres, décennie par décennie : kilomètres ouverts par trafic, fermés et déposés, électrifiés, gares par statut, événements datés, accidents, part de double voie, date du calcul `computed_at`). Page : `/network`.
- Accidents : chaque entrée de `context.corrections` ajoute `single_source` (booléen, `true` quand la valeur corrigée ne repose que sur la liste des
  accidents de Wikipédia et reste à confirmer ; `false` sinon). REST (`GET /accidents/{accident}`), GraphQL (`AccidentCorrection.single_source`) et
  MCP (`get_accident`, `context.corrections`).
- Gares : `opened_after` (ouverte après cette année, nul hors précision `range`) accompagne `opened_precision` quand l'ouverture, déduite des lignes
  et bornée par la carte (règle G3 des ères de gares), n'est connue que par un intervalle : `opened_at` n'en est alors que la borne haute, et la gare
  a ouvert entre `opened_after` + 1 et l'année de `opened_at`. REST : fiche d'une gare, annuaire des gares, `stations` de la fiche d'une ligne ;
  GraphQL : `Station.opened_after` et `Station.opened_precision`, `StationSheet.opened_after`, `StationDirectoryItem.opened_after`,
  `LineSheetStation.opened_after` ; MCP : `get_station` (`station.opened_after`, et `station.opened` en précision `range` avec `from_year`, `to_year`
  et `when`) et `list_stations`. Tant que la règle reste désactivée, aucune gare n'a cette précision et le champ vaut `null`.
- REST : `GET /journal` et `GET /journal/{slug}` (articles du journal de bord), `GET /lines/{line}/archive-records` (notices des Archives SNCF).
- REST : `GET /eras/{year}/tiles/{z}/{x}/{y}.mvt` accepte `fields=map` (tuile allégée) ; une valeur inconnue donne HTTP 422. Sans `fields`, la tuile ne change pas.
- REST : `profile` d'une ligne ajoute `sourced_grade` (déclivité donnée par une source écrite, `null` sinon) ; `signalling-history` ajoute
  `corrections` et, dans `source`, `verification` et `documents`.
- GraphQL : requêtes `journalArticles` et `journalArticle`, champs `Line.archive_records`, `Line.signalling_corrections`, `LineProfile.sourced_grade`,
  et trois abonnements (`contributionApplied`, `myContributionChanged`, `reviewQueueChanged`), refusés tant que le temps réel est désactivé.
- REST, GraphQL et MCP : `sourced_grade` du profil en long ajoute `covered_share` (part de la ligne décrite par la source, `null` pour
  Wikipédia) et `source.attribution` (`Wikipédia` ou `SNCF Réseau`) ; GraphQL : `LineProfileSourcedGrade.covered_share`,
  `LineProfileRackSource.attribution` (nul pour une fiche de crémaillère).
- REST : `GET /lines/{line}/infrastructure` (nombre de voies par portion de PK, régime d'exploitation de SNCF Réseau, et liaison radio
  sol-train : `gsm-r`, `gsm-gfu`, `rst`, `rst-data`, `rst-id` ; ODbL) ; GraphQL : `Line.infrastructure` ; MCP : outil `line_infrastructure`.
  Le fait `tracks` de la fiche (`facts`) peut maintenant avoir `source: sncf`, `as_of: today` et `km` (régime partagé).
- REST : la fiche d'une gare (`GET /stations/{station}`) ajoute `platforms` (quais de SNCF Réseau : nombre, longueurs et PK, état actuel du
  réseau ; `null` quand le jeu n'en donne aucun) ; GraphQL : `Station.platforms` ; MCP : `get_station` (`station.platforms`). ODbL, avec attribution.
- WebSocket : un compte suspendu, rétrogradé ou supprimé est déconnecté, et un canal qui n'est pas privé est refusé aux abonnements. Aucun champ ne change ;
  le temps réel reste désactivé par défaut.
- MCP : outils `journal` et `line_archive_records` ; `line_profile` et `signalling_history` renvoient les nouveaux champs ci-dessus.
