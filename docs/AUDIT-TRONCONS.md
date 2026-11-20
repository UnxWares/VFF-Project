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
