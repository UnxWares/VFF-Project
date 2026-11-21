# Audit de chaque tronçon : plan (à lancer plus tard)

Demande de Baptiste du 21/11/2026 : vérifier chaque tronçon de VFF, son classement (ouvert, fret seul, fermé,
déposé…), son placement, son exactitude et son tracé. Ce document fixe la méthode ; le travail se fera par vagues
d'agents, après la remise à zéro du quota de la semaine et après l'application du chantier « géométrie propre »
(raccords, allers-retours, trous, voies OSM non rattachées).

## Ce qu'il y a à vérifier

Au 21/11/2026 : 2 705 lignes, dont 2 191 avec un tracé, et 383 470 tronçons répartis sur 21 époques. À l'époque
actuelle : 18 271 tronçons (12 808 en service, 4 986 abandonnés, 410 déposés, 67 voies de service).

Chaque tronçon, à chaque époque, reçoit quatre notes, chacune avec ses preuves :

1. **Classement** : son état (en service, voie unique, fret seul, fermé, déposé, train touristique, jamais
   ouvert) comparé à toutes les sources datées : événements de la ligne (`line_events`), SNCF Open Data pour
   l'état actuel, lignes-oubliées (dates et PK), Wikipédia, Wikidata, OpenHistoricalMap. Règles déjà connues :
   voyageurs implique mixte sauf sur LGV ; une gare est fermée si toutes ses lignes le sont à l'époque. Note
   « d'accord », « une source contredit » ou « aucune source ».
2. **Placement** : distance du tracé à la voie réelle (BD TOPO, OSM, photographies aériennes anciennes de l'IGN
   pour les lignes disparues), médiane et 95e centile par tronçon ; cible 1 m, tolérance 5 m.
3. **Exactitude** : ordre des gares le long des PK, longueur comparée à la longueur officielle et aux PK de
   lignes-oubliées, 0 m d'écart aux jonctions, ni crochet, ni aller-retour, ni trou (indicateurs du chantier
   « géométrie propre » et de la page `/validate/graph`).
4. **Tracé** : source retenue et sa précision connue (BD TOPO 2,5 m, OSM environ 2 m, SNCF généralisé jusqu'à
   165 m, archeoferroviaire non recalé, tracé du modèle d'extraction), et traces disponibles non utilisées
   (voies OSM sans nom dans le couloir, par exemple la vallée de l'Iton sur 371000).

## Comment

1. **Passe automatique** : une commande en lecture seule (`vff:audit-segments`) calcule les quatre notes de
   chaque tronçon et les range dans une table d'audit à part, sans toucher aux tables du moteur. Une page
   d'administration (dans `/validate`, à côté du graphe) liste les lignes les plus mal notées, avec la carte et
   les preuves, et un export CSV.
2. **Contrôle à la main** : un tirage stratifié (par état, par source de tracé, par région) d'au moins 300
   tronçons, jugés un par un sur les photographies aériennes et les sources, pour mesurer la justesse réelle de
   chaque note et régler les seuils.
3. **Corrections** : toujours rejouables. Une règle du moteur quand le défaut est général, un fichier de
   corrections sourcées dans `database/data/` quand il est ponctuel, jamais une retouche à la main en base. Dump
   de `vff` avant chaque vague ; les recalculs du moteur restent soumis à l'accord de Baptiste.
4. **Ordre** : d'abord les lignes en service aujourd'hui (les plus vues), puis les lignes fermées par longueur
   décroissante, puis les époques anciennes. Une ligne témoin par région en premier, pour régler la méthode.

## Ce qu'on obtient

- Pour chaque ligne : une fiche d'audit (notes par tronçon et par époque, preuves, corrections faites).
- Pour tout VFF : la part des tronçons « sûrs » par note, avant et après, publiée dans le ROADMAP.
- Un invariant de non-régression par note, ajouté à `vff:check-invariants`.

## Passe automatique : en place (21/11/2026)

- Commande : `php artisan vff:audit-segments [--line=…] [--era=…] [--shard=i/n] [--dry-run]`, en lecture seule sur
  les tables du moteur et les sources, sans appel réseau. Résultats dans `segment_audits` (une ligne par tronçon et
  par ère, plus une synthèse par ligne et par ère ; l'état audité et la géométrie y sont recopiés).
- Page : `/validate/audit/segments` (administrateurs, comme le graphe), filtres par note, état, région, époque et nom,
  carte des tronçons colorés par note, preuves, export CSV.
- Lecture publique : `GET /api/v1/lines/{line}/audit?era=&grade=&limit=&offset=` (50 tronçons par défaut, 200 au plus, total dans `segments_total`), champ GraphQL `Line.audit(era:)`, outil MCP
  `line_audit`.
- Règles et seuils : `App\Services\SegmentAudit\AuditGrader` (version `RULE_VERSION`), mesures :
  `App\Services\SegmentAudit\SegmentAuditor`. Notes : `good` (sûr), `fair` (à revoir), `poor` (faux ou contredit),
  `unknown` (rien pour juger).
  - Classement : une chronologie `EraTimeline` par source d'événements (mêmes règles de portée que le moteur :
    toute la ligne, ou portion à 30 m du milieu du tronçon), et pour l'ère courante SNCF Réseau (200 m), BD TOPO
    (5 m) et OSM (10 m), voie de la ligne d'abord pour les deux (règles `.5` : une voie sans `line_id` passait
    avant, 508 tronçons en 2030 pour la BD TOPO), et l'état relevé par lignes-oubliées (15 m). Un avis contraire est
    faible s'il change à 3 ans près de la date, s'il vient d'une estimation, d'une décision administrative
    tardive, de Wikidata ou d'une observation non rattachée à la ligne. Une source qui ne date que des ouvertures
    ne contredit pas une fermeture ; une fermeture datée mais non localisée vaut accord faible pour un tronçon
    fermé. « Voyageurs seuls » hors LGV : `poor`.
    - Observation seule (règles `.4`, ère courante) : une seule contradiction forte, venue de la BD TOPO ou d'un
      statut SNCF « déclassée vendue », devient un avis faible quand deux observations actuelles fortes (SNCF,
      BD TOPO, OSM rattachée) sont d'accord ; raison `observation_outvoted`. Une voie OSM « abandoned » ou
      « razed » reste une vraie contradiction.
    - Continuité (règles `.4`, ères passées) : un tronçon en service que seules des sources faibles datent reste
      `good` (raison `continuous_service`) quand la ligne n'a aucune fin datée (fermeture, déclassement, dépose,
      localisée ou non) et qu'un tronçon de la même ligne, à 30 m au plus de son milieu, est en service et `good`
      pour le classement à l'ère courante. Une passe qui comprend l'ère courante l'audite en premier et lit son
      classement de la même passe ; une passe limitée aux ères passées (`--era=1990`) lit l'audit enregistré de
      l'ère courante.
    - Train touristique (règle R1, règles `.6`) : une exploitation associative (`heritage_start`, train
      touristique ou vélorail) n'est pas une première ouverture. Une source qui ne date, en fait d'ouvertures,
      que le train touristique ne dit rien de la ligne commerciale avant son début : seules ses fins comptent,
      comme pour une source sans ouverture. La source `heritage` ne contredit donc plus une ère antérieure
      (Salbris - Le Blanc en 1930, Vertaizon - Billom en 1970).
    - Fin des voyageurs (règle R3, règles `.6`) : une source qui attend une ligne fermée seulement à cause d'une
      fin des voyageurs (sans elle, la ligne serait ouverte) est d'accord avec un tronçon en fret ; son avis porte
      `passenger_end_only`. Une fermeture complète, une fin du fret ou un déclassement datés avant l'ère gardent la
      contradiction, et un tronçon mixte reste contredit.
  - Placement : un point tous les 50 m (3 à 200 par tronçon), voie la plus proche à moins de 50 m d'une source
    différente de celle du tracé (BD TOPO, OSM, photographies aériennes anciennes de l'IGN) ; `good` si médiane et
    95e centile ≤ 5 m sur au moins 80 % des points, `poor` si médiane > 5 m ou tronçon en service aujourd'hui sans
    voie réelle à côté.
    - Voies d'une autre ligne (règles `.4`) : une voie rattachée à une autre ligne (`source_traces.line_id`) ne sert
      jamais de référence ; seules comptent les voies de la ligne et les voies non rattachées. Un tronçon qui ne
      colle qu'à la voie d'une autre ligne (80 % des points, médiane et 95e centile ≤ 5 m sur toutes les voies, mais
      pas sur les voies libres ou de la ligne de la même source : règles `.5`, une voie libre qui colle n'est pas
      celle d'une autre ligne) est noté à part :
      - raison `on_other_line_track` quand une voie rattachée à sa propre ligne suit au moins la moitié des points
        entre 8 et 60 m ; la médiane donnée est l'écart à cette voie propre. `poor` pour un tronçon fermé ou
        déposé (voie-mère ou embranchement recalé sur la ligne voisine : Vitrolles, Saint-Amand, Kerpont), `fair`
        pour un tronçon en service à l'ère courante (surtout de courts morceaux dans les faisceaux de gare et les
        voies parallèles, où le rattachement des voies voisines est moins sûr) ;
      - sinon `good`, raison `shared_track` : tronc commun, voie de la ligne elle-même à moins de 8 m (même dans la
        source du tracé : la voie de l'autre ligne qui le confirme est alors un rattachement douteux), ou
        embranchement posé sur la voie de la ligne dont il part sans voie propre connue (la note d'avant les réglages, signalée pour la relecture). Une première version
        notait ces cas `fair` : 1 856 tronçons (830 km) en 2030, surtout des jonctions justes ; abandonnée.
    - Référence lointaine (règle R4, règles `.6`, ères passées seulement) : un tracé OSM que la voie OSM rattachée à
      la ligne suit sur tous les points (médiane de la voie propre et 95e centile à 1 m au plus) n'est plus
      « faux » quand la seule référence retenue est la BD TOPO à plus de 15 m (`DISTANT_REFERENCE_M`) : note
      `unknown`, raison `distant_reference_only`. La BD TOPO décrit la voie d'aujourd'hui (Bédarieux, Sarrebourg,
      La Châtre au troisième contrôle à la main). L'ère courante garde « faux ».
    - Preuves : `references.<source>` donne la mesure retenue (sans les voies des autres lignes), `all` la même
      mesure sur toutes les voies et `own` l'écart aux seules voies de la ligne (jusqu'à 60 m).
  - Exactitude : `poor` pour un aller-retour (virage > 150° sur 40 m), un écart de jonction > 1 m (bout libre à
    0,05 à 10 m d'une autre ligne), un trou face à un autre bout de la ligne (0,5 à 500 m, milieu non couvert), une
    gare à plus de 500 m ; `fair` pour un coude (> 60°), un écart de jonction ≤ 1 m, une gare à 150 à 500 m. La
    synthèse de ligne ajoute la longueur du tracé de référence comparée à la longueur officielle (10 % et 25 %)
    et l'ordre des gares le long des PK.
  - Tracé : `good` pour BD TOPO ou OSM, ou un tracé déjà calé sur la voie réelle ; `poor` pour une forme SNCF
    généralisée ou archeoferroviaire non recalé alors qu'une voie BD TOPO ou OSM suit le tronçon.
    - Confirmation (règles `.4`) : un tracé de précision moyenne ou faible est `good` (raison
    `confirmed_by_precise_source`) quand une voie BD TOPO ou OSM, autre que la source du tracé et jamais celle d'une
    autre ligne, suit au moins 80 % des points avec une médiane ≤ 1 m et un 95e centile ≤ 5 m (le seuil du 95e
    centile garde « faux » les tracés à coude).
  - Ligne : une note devient `poor` dès que 10 % de la longueur est `poor`, `fair` dès que 10 % est `poor` ou
    `fair`. Score de 0 (tout sûr) à 100 (tout faux), pondéré par la longueur.
  - Versions des règles (`rule_version`) : `.3` (nuit du 21/11) dépose probable, voies rattachées d'abord, train
    touristique ; `.4` (matin du 21/11, second contrôle à la main de 100 tronçons) voies d'une autre ligne, tracé
    confirmé par une source précise, observation seule, continuité aux ères passées ; `.5` (après-midi du 21/11)
    voie de la ligne vraiment d'abord pour l'état BD TOPO et OSM (`COALESCE` sur `attached`), et une voie libre
    qui colle au tronçon n'est plus « la voie d'une autre ligne ». ; `.6` (après-midi du 21/11, troisième contrôle à la main : 0 alerte
    juste sur 13 aux ères passées) train touristique muet avant son début (R1), fin des voyageurs seule qui n'est pas
    une fermeture (R3), référence BD TOPO lointaine d'un tracé OSM de la ligne (R4). L'élargissement de la
    continuité (R2) n'est pas appliqué : voir `storage/app/reports/agents/aprem-2111/audit-eres-passees.md`.
