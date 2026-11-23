# troncons-surs : kilomètres sûrs de 2030 (23/11/2026, soir)

Agent « troncons-surs ». Lecture seule sur `vff` (`SegmentAuditor::auditLine` rejoué en mémoire, session en lecture
seule, `statement_timeout` 30 s), rien d'écrit. Arrêt anticipé à la demande du coordinateur (plafond d'usage) : la
mesure complète « après » n'a couvert que 542 lignes sur 1 542.

## Avant

- Table `segment_audits` (règles `.8`) : 84,0 % des km sûrs, mais 2 788 lignes d'audit de 2030 (7 279 km) décrivent un
  tronçon disparu, et 2 915 tronçons actuels n'ont pas d'audit : le chiffre stocké est périmé.
- Mesure fraîche avec le code de HEAD (règles `2026-11-23.1`), toutes les lignes de 2030 : **83,84 %** (35 888 km,
  5 800 km non sûrs : 3 914 à revoir, 1 279 faux, 608 inconnus). En service 91,1 %, fermé 54,6 %, déposé 17,4 %.

## Décomposition des 5 800 km non sûrs (contrôle, note, raison, état ; entre parenthèses : seule note non sûre)

| cause | km |
|:-|-:|
| placement à revoir, 95e centile > 5 m, en service | 924 (848) |
| classement à revoir, sources en désaccord, en service | 645 (571) |
| classement à revoir, sources en désaccord, fermé | 573 (459) |
| placement à revoir, 95e centile > 5 m, fermé | 516 (350) |
| classement à revoir, contradiction faible, fermé | 510 (416) |
| tracé à revoir, meilleure source disponible, fermé | 455 |
| classement à revoir, dépose probable (`track_unconfirmed`), fermé | 447 + 426 |
| placement inconnu, hors de France | 412 + 84 |
| placement inconnu, seule la source du tracé, fermé et déposé | 396 + 226 |
| exactitude faux, jonctions et trous | 630 |
| autres | reste |

Lignes qui pèsent le plus (km non sûrs / km de la ligne, identifiants `lines.id`) : 327 (96,6/150,8), 49 (90,6/505,2,
Chartres - Bordeaux), 462 (77,9/369,5), 4459 (74,4, Train des Pignes), 176 (74,4, Carcassonne - Rivesaltes), 4251,
52, 241 (Saint-Denis - Dieppe), 695, 4250, 92, 74, 80, 10, 4240, 60, 604, 202, 78, 487, 31, 11, 196, 679, 589, 360,
847, 413, 238, 4462, 144, 306, 315, 6, 485, 317, 664, 687, 374, 533, 341, 302, 430, 4479, 230, 481, 645, 4238, 167,
4125 (liste complète : `brouillons-agents-2311/troncons-surs/avant.jsonl`, script `ana.py … lines`).

## Corrigé, par cause

1. **R8, tunnels (faux positif de règle).** Sur cinq tronçons « 95e centile trop fort » (Livron - Aspres, Andelot -
   La Cluse, Coni - Vintimille, Épernay - Reims, Brive - Toulouse), 132 des 147 points à plus de 5 m étaient en tunnel ;
   à l'air libre, 1 à 2 m. Ni OSM ni la BD TOPO n'y sont relevés d'avion (la BD TOPO y déclare 10 à 20 m pour une
   partie de ses tronçons souterrains). Les points à moins de 30 m d'une voie OSM `tunnel` ou d'une voie BD TOPO
   `position_par_rapport_au_sol` négative sortent de la mesure ; tout en souterrain : `unknown` (non jugé, pas « sûr »).
   Sur un tirage de 30 cas, environ la moitié sont des tunnels ; l'autre moitié (fermés surtout) reste à revoir.
2. **R9, trains touristiques (faux positif).** SNCF Réseau « neutralisée », « déclassée non vendue », « mise à
   disposition de tiers », OSM `disused` contredisaient le train touristique (676000, 757000, 600000, 947000, 721000,
   418000…, environ 200 km). Désormais d'accord si la source `heritage` confirme le train touristique ; voie déposée :
   toujours contradiction.
3. **R10, chronologie dépassée (défaut de règle).** Une chronologie que trois observations actuelles fortes contredisent
   était déjà rendue faible (`outweighed`), mais son rang (saisie à la main 0, lignes-oubliées 2) la faisait encore
   passer devant SNCF Réseau (3) : note à revoir. Elle ne retient plus la note.
4. **R11, étiquette OSM seule.** OSM `disused` contre SNCF Réseau « exploitée » et BD TOPO « en service »
   (Castelsarrasin - Beaumont, Longueville - Esternay) ; OSM `abandoned` contre une voie que SNCF Réseau dit maintenue en
   place : avis faible. OSM `abandoned` contre SNCF « fermée » (sans dire si la voie est en place) reste une vraie
   contradiction (environ 290 km : vraie question de dépose).
5. **Donnée : 330000 Gisors - Serqueux.** La correction « manual » de la fermeture de 2009 n'avait pas sa réouverture du
   15/12/2013 : la source finissait fermée et contredisait 50 km en service. Ajout de la réouverture
   (`database/data/line_event_corrections_desserte.php`, même citation Wikipédia, permalien 239854982).

## Après (mesure partielle)

Sur les 542 premières lignes (22 726 km, 63 % du réseau de 2030), avec R8 (rayon de 15 m alors) à R11 sans
l'extension `disused` : **84,95 % vers 88,56 %** (+820 km sûrs : 790 km à revoir vers sûr, 55 faux vers sûr ; 40 km
sûrs ou à revoir vers inconnu, les tunnels entiers). Estimation sur tout le réseau : de 83,8 % à environ 87,5 %. Le
seuil de 90 % n'est pas atteint par les règles seules ; aucune note n'a été forcée.

## Ce qui reste (vrais doutes, pas des faux positifs)

- **Défaut du moteur, ère courante** (non corrigé, à décider) : `BuildEraSegments::eraStates` garde en service une
  portion que SNCF Réseau dit « neutralisée » ou « fermée » dès que la BD TOPO la voit « en service » (règle faite pour
  la RATP, « déclassée vendue »). Environ 200 km hors trains touristiques : 892000, 085000 (19 km), 500000, 583000,
  600000, 409000, 867000, 055000, 687000, 210000… Piste : ne garder l'exception IGN que pour « déclassée vendue » ou
  « transférée ». Change la carte de 2030 : accord de Baptiste.
- **Trafic de l'ère courante** : 500000 Thouars - Parthenay (Gourgé, Airvault, 24 km) est « mixte » en 2030 alors que
  la chronologie donne fret seul (voyageurs fermés en 1980, fret rouvert en 2023) : le trafic de 2030 vient d'une
  autre étape (`classify-lines` / `DeriveTrafficType`), à relire.
- Hors de France (496 km) et « seule la source du tracé » (700 km) : non mesurables sans autre relevé.
- Dépose probable (870 km) et OSM `abandoned` contre SNCF « fermée » (290 km) : vraie question de dépose.
- Effet de bord de section (relecture-audits.md) : non retenu ici. Au cas de Robiac (805000, 1860), la section de
  lignes-oubliées recouvre vraiment le tronçon de la gare (4 points sur 5 à moins de 5 m) : un test géométrique ne le
  distingue pas ; effet nul en 2030.

## Appliquer (coordinateur, non lancé)

1. Correction de données : `bin/php artisan vff:apply-event-corrections --line=330000`, puis la chaîne habituelle des
   tronçons de cette ligne (tronçons des ères, voies partagées, invariants).
2. Réaudit : `bin/php artisan vff:audit-segments --stale`, puis `bin/php artisan vff:audit-segments --era=2030`
   (règles `2026-11-23.2`), puis les ères passées en `--shard=i/4`.
3. Recalculer le pourcentage de 2030 sur la table (worst_grade, pondéré par `length_m`).
