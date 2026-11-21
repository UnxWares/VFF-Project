# Audit de chaque gare : plan (à lancer plus tard)

Demande de Baptiste du 21/11/2026 : pour chaque gare, trouver ou estimer les données d'ouverture, de fermeture et de
service (voyageurs, fret), et vérifier parfaitement son placement. Même calendrier que l'audit des tronçons
(`docs/AUDIT-TRONCONS.md`) : après la remise à zéro du quota de la semaine et après la géométrie propre, les deux
audits se renforçant (une gare bien placée sert à caler les PK d'un tronçon, et l'inverse).

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
- `vff:import-station-wikidata-dates` (`--station=`, `--line=`, `--from-cache`, `--refresh`, `--dry-run`) : P1619 et
  P3999 avec précision, rang, qualificatifs et références → `station_date_candidates`, comparées aux dates publiées
  (`same`, `more_precise`, `less_precise`, `differs`, `new`, `station_open`). Aucune date du moteur n'est remplacée ;
  la règle de rang proposée est dans `SourceRank`.
- Relecture : `/validate/audit/stations` (administrateurs), export CSV. Lecture publique :
  `GET /api/v1/stations/{station}/audit`, champ GraphQL `Station.audit`, clé `audit` de l'outil MCP `get_station`.
