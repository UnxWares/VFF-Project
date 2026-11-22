# Audit de chaque gare : méthode et état

Demande de Baptiste du 21/11/2026 : pour chaque gare, trouver ou estimer les données d'ouverture, de fermeture et de
service (voyageurs, fret), et vérifier parfaitement son placement. Même calendrier que l'audit des tronçons
(`docs/AUDIT-TRONCONS.md`) : après la remise à zéro du quota de la semaine et après la géométrie propre, les deux
audits se renforçant (une gare bien placée sert à caler les PK d'un tronçon, et l'inverse). La passe automatique est écrite, réglée et passée
sur `vff` ; l'état des mesures est en fin de document.

## Point de départ (21/11/2026)

9 363 gares. Dates : 515 sans date d'ouverture, 754 fermées sans date de fermeture, 6 789 ouvertures connues à
l'année seulement, 5 503 ouvertures déduites de la ligne (`inferred:line_event`), 1 414 fermetures données par un
intervalle. Positions : 1 306 signalées douteuses, 2 230 avec plus de 50 m d'écart entre leurs sources, 3 368 avec
une seule source ou aucune.

## Dates et services : trouver d'abord, estimer ensuite

Pour chaque gare, et pour chaque ligne qui la dessert (`station_lines`) :

1. **Trouver** une date sourcée, par ordre de sûreté : texte officiel (Journal officiel, décisions de fermeture),
   articles et infobox Wikipédia de la gare et de la ligne, Wikidata (P1619 mise en service, P3999 fermeture),
   lignes-oubliées (listes de gares avec dates), OpenHistoricalMap, SNCF Open Data (gares en service, fret),
   indicateurs Chaix et documents de Gallica quand ils sont datés. Chaque date garde sa source, sa phrase et sa
   précision (jour, mois, année).
2. **Estimer** seulement à défaut, avec une méthode écrite et une précision honnête (« entre 1885 et 1890 »,
   jamais un faux jour) : ouverture de la portion de ligne qui la dessert, présence sur les cartes anciennes de
   l'IGN, apparition dans les indicateurs, gares voisines ouvertes ensemble. Une estimation est toujours affichée
   comme telle.
3. **Services** : voyageurs et fret séparés, avec leurs dates (une gare peut fermer aux voyageurs et garder le fret
   des décennies), à partir des mêmes sources et des embranchements particuliers connus.
4. Contrôles croisés : une gare ne peut pas ouvrir avant sa ligne ni rester ouverte après la fermeture de toutes
   ses lignes (règles déjà en place), une fermeture au fret ne précède pas une ouverture au fret, etc.

## Placement : vérifier parfaitement

1. Comparer toutes les positions connues : SNCF (gares et PK), OSM (nœud `railway=station` ou `halt`, bâtiment
   voyageurs), BD TOPO (gares de l'IGN), Wikidata, OpenHistoricalMap ; et pour les gares disparues, cartes et
   photographies aériennes anciennes de l'IGN (bâtiment, quais, voies d'évitement).
2. Règle de placement écrite et unique : la gare est posée sur la voie de sa ligne, au droit du bâtiment voyageurs
   ou du milieu des quais, à son PK ; écart à la voie 0 m après calage, écart au bâtiment dit dans la fiche.
3. Chaque gare reçoit une note de placement (sources d'accord à moins de 20 m, une source isolée, désaccord) et la
   liste de ses preuves ; les gares en désaccord passent dans une file de relecture, avec la carte et les
   photographies, dans l'espace d'administration.
4. Contrôle à la main d'un tirage stratifié d'au moins 300 gares (par note, par région, gares en service et
   disparues) pour mesurer la justesse réelle et régler les seuils.

## Corrections et résultat

- Toujours rejouables : règles des commandes existantes (`vff:attach-stations`, `vff:build-station-eras`,
  `vff:check-station-witnesses`) ou fichiers sourcés dans `database/data/`, jamais de retouche à la main en base ;
  dump de `vff` avant chaque vague, recalculs soumis à l'accord de Baptiste.
- Résultat publié : part des gares avec une date sourcée, estimée ou inconnue, et part des gares bien placées,
  avant et après ; invariants de non-régression dans `vff:check-invariants`.

## Mise en œuvre (nuit du 21/11/2026)

- `vff:audit-stations` (`--station=`, `--line=`, `--shard=i/n`, `--no-bdtopo`, `--offline`, `--dry-run`) : passe en
  lecture seule sur le moteur, résultat dans `station_audits` (une ligne par gare). Règles :
  `App\Services\StationAudit\PlacementGrader` (notes `agree` < 20 m, `close` < 100 m, `isolated`, `disagree` : source
  précise à plus de 100 m ou grossière à plus de 1 km, `none`) et `DateAuditor` (notes `sourced`, `estimated`,
  `unknown`, `not_applicable` ; services voyageurs et fret ; contrôles croisés). Positions comparées : SNCF, OSM,
  Wikidata, OHM, listes par PK (une position recopiée d'une gare connue est montrée, jamais comptée) et zones de gare
  de la BD TOPO (WFS de la Géoplateforme, cache `storage/app/data/gares-audit/`). Contrôle `retained_outvoted` : deux
  autres familles d'accord entre elles à plus de 100 m de la position retenue.

## Réglages du 21/11/2026 (règles `2026-11-21.2`)

Tirés du second contrôle à la main de 100 gares (`storage/app/reports/agents/matin-2111/controle-main.md`) :

- **Source isolée** : une seule famille lointaine (précise à plus de 100 m, ou grossière à plus de 1 km) ne fait plus
  `disagree` quand une autre famille, non recopiée, est à 50 m au plus de la position retenue
  (`PlacementGrader::OUTLIER_CONFIRM_M`). La note se calcule sans elle (`agree` ou `close`) et le contrôle
  `outlier_source` donne la source écartée et son écart. Deux familles lointaines, ou aucune famille proche : toujours
  `disagree`.
- **Fermetures du référentiel SNCF** : une fermeture prise dans Wikidata dont toutes les références renvoient au
  référentiel SNCF des gares de voyageurs (`referentiel-gares-voyageurs`, date de sortie du référentiel, souvent
  décalée de plusieurs années) est notée `estimated`, comme la fin des voyageurs qui en découle. Le contrôle
  `closure_sncf_referential` donne la date et les autres dates de fermeture connues (autre valeur Wikidata, événement
  d'une autre source), à préférer quand elles existent. Le moteur garde sa date : seul l'audit change.
- **Inchangé** : la mesure du placement reste la distance directe (la mesure le long de la voie attend la décision de
  Baptiste).
- `vff:import-station-wikidata-dates` (`--station=`, `--line=`, `--from-cache`, `--refresh`, `--dry-run`) : P1619 et
  P3999 avec précision, rang, qualificatifs et références → `station_date_candidates`, comparées aux dates publiées
  (`same`, `more_precise`, `less_precise`, `close`, `differs`, `new`, `station_open`). Une date VFF de précision
  `range` (fermeture « entre 1951 et 1960 », bornée par les ères) se compare à tout l'intervalle : d'accord si la
  candidate y tombe, `close` si elle en reste à moins d'un an, `differs` au-delà ; `delta_days` est alors l'écart à
  la borne la plus proche (règle du 21/11 après-midi ; avant, la décennie de la borne haute). Aucune date du moteur n'est remplacée ;
  la règle de rang proposée est dans `SourceRank`.
- Relecture : `/validate/audit/stations` (administrateurs), export CSV. Lecture publique :
  `GET /api/v1/stations/{station}/audit`, champ GraphQL `Station.audit`, clé `audit` de l'outil MCP `get_station`.

## État des mesures (21/11/2026, fin d'après-midi)

- Première passe sur les 9 389 gares : placement « d'accord » 31 %, « proche » 14 %, « isolé » 51 %, « en désaccord »
  4 % (342 gares) ; ouvertures sourcées 35 %, estimées 59 %, inconnues 5 % ; 3 750 dates de Wikidata comparées pour
  3 097 gares, sans rien remplacer. Après les réglages `2026-11-21.2` : 217 gares en désaccord.
- Contrôles à la main sur orthophotos : nuit, 50 gares sur 50 justes pour « d'accord » et « proche », mais 15 justes,
  5 douteuses et 5 fausses sur 25 « en désaccord » ; après-midi, « agree » juste 10 fois sur 10, « close » 7 sur 10,
  « isolated » 9 sur 10, et 13 alertes fondées sur 20 « disagree » (65 %, contre 36 % et 40 % avant les réglages),
  ouvertures « sourced » justes à l'année 21 fois sur 22.
- Fins des voyageurs : 552 gares passaient de « voyageurs » à « fret seul » sur la frise sans date.
  `vff:import-station-passenger-ends` lit la fin des voyageurs explicite de l'infobox Wikipédia de la gare (342 dates),
  `vff:build-station-eras` la lit au prochain calcul ; pour 80 gares que Wikidata fermait à la fin des voyageurs alors
  que le fret a continué, la fin du fret (infobox, article de la ligne, lignes-oubliées) est posée dans
  `database/data/gares_corrections.php` (207 ères « fret seul » de plus attendues). Les 38 gares restantes, sans date de
  fin du fret trouvée, gardent la fermeture de Wikidata.
- Règles du moteur corrigées d'après le contrôle de cohérence entre gares et lignes :
  - `vff:build-station-eras` : une gare rouverte n'est fermée avant sa réouverture que si toutes ses lignes l'étaient
    (Orléans et Bellegarde ne sont plus fermées par une ligne voisine) ;
  - `vff:consolidate-stations` : un objet OHM d'un autre réseau (chemin de fer départemental, tramway, exploitant
    secondaire) n'est plus rattaché par son nom à la gare SNCF homonyme, et une fin OHM ne ferme pas une gare que la SNCF
    dit ouverte aux voyageurs (Sens, Montereau, Nogent-sur-Seine, Morez, Lavaur, Maubeuge, Lourches).
- Corrections sourcées des contrôles (`database/data/gares_corrections.php`, section 8) : 27 gares (3 exclusions, dates, fins des voyageurs ou du fret, 6 positions). Elles sont lues au prochain calcul des
  gares, avec les règles ci-dessus : `vff:consolidate-stations` puis `vff:stations-pipeline --from-attach --from-cache`.
- Commandes et options : [COMMANDES.md](COMMANDES.md).

## Invariant `stations.opened_before_lines` (règles du 22/11/2026)

Signale une gare ouverte (date au jour, sourcée, non déduite des lignes) avant l'ouverture au jour de toutes ses
lignes. Trois règles écartent les faux positifs relevés par les agents `gares-avant-lignes` et
`gares-avant-lignes-2` (43 cas avant, 34 après sur `vff`) :

- la gare n'est comparée qu'aux événements d'ouverture dont la section est résolue (`section_resolution` = `trace`
  ou `places`) et qui la couvrent ; « whole_line », « unresolved » et « pending » ne disent pas quelle portion ils
  visent ;
- les rattachements `terminus_name` et ceux dont le PK est projeté (`pk_source` = `projected_raw`) ne comptent pas :
  une gare ancienne, terminus d'une ligne secondaire plus tardive (Dormans, Plancoët), n'est pas ouverte « avant sa
  ligne » ;
- un écart de 3 jours au plus (`DataInvariants::STATION_OPENED_TOLERANCE_DAYS`) est toléré : inauguration la veille
  de l'ouverture au public.

Un cas qui reste est une contradiction à trancher (date de gare, date de ligne ou rattachement). Tests :
`tests/Feature/StationServiceYearsTest.php`.

## Invariants `stations.ohm_opened_before_lines` et `stations.ohm_opened_shared_date` (22/11/2026)

Détection seule, sans correction automatique : une date d'ouverture de gare venue d'OpenHistoricalMap (provenance
`opened_at` = `ohm`, précision au jour) est parfois la date de la loi qui déclare la ligne d'utilité publique (Lens -
Frévent : 08/08/1890 pour une mise en service le 01/12/1895), ou la date de la première section recopiée sur toute la
ligne. Les deux règles sont des listes de travail pour la recherche de sources, ligne par ligne.

- `stations.ohm_opened_before_lines` : gare dont l'ouverture OHM précède de plus de 6 mois
  (`DataInvariants::STATION_OHM_LAW_GAP_MONTHS`) toute ouverture de section résolue (`trace` ou `places`) de ses lignes
  à son PK. Mêmes rattachements que `stations.opened_before_lines` : ni `terminus_name`, ni PK projeté
  (`projected_raw`) ; une ligne plus ancienne que la gare l'absout. Écartés : autre origine que OHM, date au mois ou à
  l'année, sections `whole_line`, `unresolved` ou `pending`.
- `stations.ohm_opened_shared_date` : ligne dont au moins 3 gares (`STATION_OHM_SHARED_MIN`) portent exactement la même
  date OHM au jour alors que ses ouvertures de section résolues tombent à au moins deux dates. Une ligne ouverte d'un
  seul tenant, deux gares seulement, ou des gares d'une autre source ne sont pas signalées.

Mesure sur `vff` (22/11/2026) : 5 gares pour la première règle (Andrézieux et St-Just-sur-Loire 1827, Aurec, Clermain,
Etoile d'Alaï) ; 11 lignes pour la seconde (Lyon-St-Paul - Montbrison 17 gares au 17/01/1876, Paray-le-Monial -
Givors-Canal, Montereau - Château-Landon, Firminy - St-Rambert-d'Albon, St-Germain-des-Fossés - Darsac...). Tests :
`tests/Feature/OhmLawDateInvariantsTest.php`.
