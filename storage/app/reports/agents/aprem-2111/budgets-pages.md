# Budget des pages (agent « budgets-pages », 23/11 soir)

## En bref

- **Plus gros gain, en local seulement : l'enregistrement Inertia DevTools.** En `APP_ENV=local`, Inertia 3.7
  enregistre chaque page sur disque (`storage/inertia-devtools`, 4 696 fichiers, 421 Mo en un jour) : 100 à 300 ms
  par page. Département du Rhône 233 ms → 39 ms, commune de Lyon 124 → 33 ms, fiche de Paris - Marseille à chaud
  552 → 263 ms, accueil 174 → 130 ms. Rien en production (désactivé hors `local`). `.env.example` porte désormais
  `INERTIA_DEVTOOLS_ENABLED=false` ; **à reporter à la main dans `.env`** (fichier de Baptiste, pas touché), puis
  redémarrer `artisan serve`. J'ai posé un `.nobackup` dans `storage/inertia-devtools` (enregistrements régénérables) ;
  les 421 Mo restent sur disque : suppression à décider par Baptiste (rien supprimé).
- **Départements traversés (`LineFacts::departments`) : 2,7 s → 0,45 s sur Paris - Marseille**, mêmes mètres au mètre
  près (12 départements comparés sur `vff`), 1,5 s → 0,3 s environ sur la LGV Sud-Est. Le calcul, gardé en cache tant
  que les tronçons ne changent pas, revient à chaque passage de la chaîne d'applications : la première visite de la
  fiche 830000 passe de 4,3 s à 2,1 s.
- **Quiz : 9 lectures du catalogue (`to_regclass`) → 1** (25 requêtes → 17 au premier calcul du vivier du jour).
- Aucune requête en boucle (N+1) sur les fiches mesurées ; aucun index manquant (toutes les lectures par élément sont
  sous 15 ms ; les lenteurs sont des calculs géographiques sur des lignes entières).

## Méthode

- Script jetable hors dépôt (`scratchpad/budgets-pages/measure.php`) : noyau HTTP de Laravel de mon arbre sur `vff`,
  requêtes une à une, `statement_timeout` 30 s, garde-fou `DB::beforeExecuting` qui refuse toute écriture hors tables
  temporaires `tmp_*` (la lecture seule PostgreSQL est impossible : `LinePhases` crée `tmp_line_portions`), session en
  mémoire. Première visite : cache en mémoire vide ; visite suivante : même processus. Compagnies et départements :
  fiches précalculées lues dans Valkey. Requête différée : groupe Inertia `sheet`, comme le navigateur.
- Contrôle sur le serveur de dev (`curl`, en-têtes Inertia) : mêmes ordres de grandeur (830000 à froid 4,75 s,
  département 0,26 s).

## Par page

Avant : HEAD `dc7f69cb`, DevTools actif (comme le serveur de dev). Après : DevTools coupé et correctifs de ce chantier.
Temps serveur complet (PHP et SQL) ; les temps à froid varient d'environ 30 % selon la charge de la base (chaîne
d'applications en cours).

| Page | Requêtes 1re / suivante | Avant 1re / suivante | Après 1re / suivante | Props (premier affichage) | Différé `sheet` (après) |
| --- | --- | --- | --- | --- | --- |
| Ligne 830000 Paris - Marseille | 47 / 25 | 4 330 / 552 ms | 2 073 / 263 à 360 ms | 245 Ko (stations 145, chronicle 42, phases 41) | 35 req., 261 ms, 195 Ko (materials 75, profile 44, structures 33) |
| Ligne 366000 Mantes - Cherbourg | 46 / 24 | 1 010 / 164 ms | 924 / 120 à 134 ms | 71 Ko (stations 36, chronicle 20) | 32 req., 101 ms, 77 Ko |
| LGV 752000 Sud-Est | 46 / 25 | 2 164 / 253 ms | 991 / 151 ms | 123 Ko (chronicle 83, phases 28) | 31 req., 109 ms, 83 Ko (structures 42, profile 22) |
| Gare Lyon-Part-Dieu | 8 / 7 | 114 / 49 ms | 54 ms (suivante) | 10,5 Ko | 6 à 9 req., 30 à 60 ms, 4 Ko |
| Gare Pleine-Fougères | 7 / 7 | 76 / 52 ms | | 8,5 Ko | 6 req., 15 ms |
| Gare fermée avec photos (Lyon-Brotteaux, 72 images) | 7 / 7 | 55 / 50 ms | | 10 Ko | 6 req., 14 ms |
| Matériel Regio 2N | 6 / 6 | 57 / 50 ms | 52 ms | 8,8 Ko | |
| Matériel Z 56800 | 6 / 6 | 48 / 53 ms | | 5,8 Ko | |
| Accident relu (2019-10-16 Boulzicourt) | 8 / 8 | 78 / 48 ms | 51 ms | 23 Ko | |
| Commune Lyon | 14 / 0 | 561 / 124 ms | 642 / 33 ms | 49 Ko (map 24, chronology 14) | |
| Département Rhône | 0 / 0 | 255 / 233 ms | 44 / 39 ms | 94 Ko (map 57, communes 18) | |
| Compagnie PLM | 0 / 0 | 48 / 35 ms | 54 / 40 ms | 11 Ko | |
| Accueil | 10 / 2 | 3 192 / 174 ms | 240 (cache Valkey) / 130 ms | 11 Ko | |
| Annuaire des gares | 7 / 7 | 122 / 102 ms | 98 ms | 27 Ko | |
| Quiz | 25 / 0 → **17** / 0 | 2 791 / 36 ms | 2 164 à 3 321 / 38 ms | 0,3 Ko | |
| Carte | 3 / 2 | 433 / 37 ms | 46 / 34 ms | 3,6 Ko | |

Les budgets de `PageQueryBudgetTest` (ligne 40 / 24, gare 8, différés 26 et 6) tiennent sur les vraies données à la
visite suivante (24-25) ; à froid les grandes lignes font 45-47 requêtes (plus de sections non vides que le petit
réseau de test, aucune requête par élément).

## Correctifs (commit `:zap:{perf}`)

1. `app/Services/LineFacts.php` : les départements traversés sont mesurés tracé par tracé (chaque tracé distinct de
   toutes les ères croisé avec les seuls contours de commune proches, par l'index) au lieu de la ligne fusionnée par
   `ST_LineMerge(ST_Collect(...))`, que chaque contour candidat croisait en entier. Même somme de longueurs (vérifié sur
   830000 : 12 départements, mêmes mètres). Test `LineFactsTest::test_departments_are_measured_track_by_track_with_the_same_lengths`
   (longueurs de deux tracés bout à bout, et aucune requête des contours qui fusionne la ligne ; échoue sans le correctif).
   Profite aussi au quiz des lignes (`LineQuiz`, une lecture par ligne proposée).
2. `app/Services/Quiz/FactPool.php` : `has()` lit une fois la présence de toutes les tables facultatives du vivier
   (`OPTIONAL_TABLES`), au lieu d'une lecture par famille ; une table absente de la liste lève une `LogicException` (pas
   de faux « table absente » silencieux). Test `FactQuizTest::test_the_pool_reads_the_catalogue_of_its_optional_tables_once`
   (1 lecture ; 9 sans le correctif).
3. `.env.example` : `INERTIA_DEVTOOLS_ENABLED=false` avec l'explication ; test
   `PageQueryBudgetTest::test_the_example_environment_does_not_record_every_page_for_the_inertia_devtools`.

Vérifications : tests ciblés à un processus sur `vff_jour_budgets_test` (`PageQueryBudgetTest`, `tests/Feature/Quiz`,
`LineFactsTest`, `tests/Feature/Communes`, `SheetTextRulesTest`, `LineInfrastructureApiTest`, `JsonLdPagesTest` :
139 tests verts), échec vérifié sans chaque correctif (`vendor` monté sur `/app/vendor`, classes de mon arbre),
Pint, PHPStan sans erreur ni ajout à la baseline. Pas de front touché.

## Ce qui reste (non fait)

- **Terminus (`LineStations`, `ST_Dump(ST_LineMerge(ST_Collect(geom)))`)** : 150 à 390 ms à froid, même remède
  possible (extrémités tracé par tracé).
- **Accueil à froid : 2,4 s** (kilomètres par ère avec `ST_Length(geom::geography)` quand `length_meters` manque) ;
  en cache ensuite. Remplir `length_meters` partout supprimerait le calcul géodésique.
- **Quiz à froid** : deux lectures lourdes (gares et fréquentation 1,2 s ; longueurs par ligne et par ère 1,1 s), une
  fois par jour.
- **Poids** : `stations` reste la prop la plus lourde du premier affichage de la fiche de ligne (145 Ko sur 830000) ;
  carte du département (57 Ko) et de la commune (24 Ko) candidates à `Inertia::defer` si elles sont sous le haut de page.
- `CommuneRail::codes()` lance `exists()` sur `commune_rail` à chaque appel avant le cache (une requête par requête
  différée de gare) : choix volontaire (la clé de cache en dépend), à revoir seulement si besoin.
- À faire par Baptiste : `INERTIA_DEVTOOLS_ENABLED=false` dans `.env` et redémarrer le serveur ; décider de la
  suppression de `storage/inertia-devtools/*.json` (421 Mo). Aucune commande en base.

## Arbre

L'arbre du scratchpad n'a pas pu être créé (`/tmp` presque plein : « unable to write file ») : arbre de secours
`.claude/worktrees/budgets-pages`, retiré à la fin.
