# Changements de l'API

Ce journal liste ce qui change pour qui appelle l'API de VFF : REST (`/api/v1`), GraphQL (`/graphql`) et le serveur MCP (`/mcp`).
Les entrées sont datées, la plus récente en premier. Un champ n'est retiré qu'après avoir été marqué déprécié (`deprecated` dans la
spécification OpenAPI, `@deprecated` dans le schéma GraphQL) et annoncé ici. Un test (`ApiContractTest`) compare l'API à un instantané
versionné (`tests/Fixtures/api-contract.json`) et échoue si un champ, un paramètre ou une valeur disparaît sans cette annonce.

## 23/11/2026

### À vérifier dans votre client

- Accidents : `nearest_station` (REST, GraphQL, MCP `get_accident`) vaut désormais `null` quand le lieu n'est connu qu'à la commune
  près (`location.precision` = `commune`, centre de la commune) : la gare « la plus proche » d'un centre de commune n'était qu'une
  coïncidence (sept accidents de Paris rattachés à Paris-Nord « à 0 m »). Le filtre `station` de `GET /api/v1/accidents` et la
  chronologie des gares ne les renvoient plus. Aucun champ n'est retiré ni ajouté ; la fiche web affiche « À Paris, gare non précisée ».

- MCP : les faits d'une source à usage non commercial ne sont plus servis par le serveur MCP, qu'elle soit
  nommée dans `source` ou seulement par le lien cité : `line_events`, `network_events` et `get_line` (`events`, `event_count`)
  peuvent donc renvoyer moins d'événements qu'avant, et `source` n'y prend plus la valeur de cette source. L'API REST et GraphQL ne
  changent pas. Aucun champ n'est retiré.

### Ajouts

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
