# Budget des pages, suite (agent « budgets-suite », 23/11 soir)

Suite de `budgets-pages.md` (commit `b3fd698b`) : les pistes laissées de côté, traitées à la cause, mesurées avant et
après, chacune avec un test qui échoue sans elle.

## En bref

| Piste | Avant | Après | Correctif |
| --- | --- | --- | --- |
| Terminus de ligne (`LineStations::terminals`, à froid) | 165 ms sur 830000, 48 ms sur 752000 | 47 ms, 8 ms | bouts des tracés sans recoller la ligne |
| Accueil à froid (profil du réseau, `SiteStats::profile`) | 2 183 ms (requête), 3,1 s la page | 330 ms, 1,3 s la page | longueur lue avant le regroupement |
| Quiz à froid, gares et fréquentation (`DailyFactPool`) | 1 089 ms | 24 à 64 ms | homonymes comptés une fois |
| Fiche 830000 : prop `stations` au premier affichage | 145 Ko (premier affichage 245 Ko) | 70 Ko (171 Ko) | détails de la liste en prop différée |
| Département du Rhône : premier affichage | 94 Ko (carte 57 Ko) | 37 Ko | carte en prop différée |
| Commune de Lyon : premier affichage | 49 Ko (carte 24 Ko) | 25 Ko | carte en prop différée |

Les résultats ne changent pas : terminus comparés sur les 2 175 lignes de `vff`, profil comparé ère par ère, gares du
quiz comparées ligne par ligne (175 sur 175), tout en lecture seule.

## Méthode

- Arbre `.claude/worktrees/budgets-suite`, `vendor` monté en lecture seule sur `/app/vendor` (classes de l'arbre),
  base de test `vff_jour_budgets2_test`, tests à un processus.
- Lectures sur `vff` par `psql` en transaction en lecture seule (`default_transaction_read_only=on`,
  `statement_timeout` 30 s), une ligne à la fois ; mesures des pages par le noyau HTTP de l'arbre (script hors dépôt,
  garde-fou contre toute écriture hors tables temporaires).
- **JIT coupé dans les mesures, comme sur le site.** `AppServiceProvider` coupe le JIT pour les pages, mais pas en
  console : le script de mesure du chantier précédent tournait avec le JIT, ce qui gonflait certains temps (requête des
  terminus : 300 ms dont 160 de compilation). Les chiffres ci-dessus sont sans JIT.
- Mesure « avant » sur le serveur de dev (127.0.0.1:8000, en-têtes Inertia) : fiche 830000 0,72 s puis 0,53 s,
  `stations` 158 Ko en JSON indenté (145 Ko compact) ; Rhône 96 Ko (carte 63 Ko) ; Lyon 51 Ko (carte 26 Ko). Le
  serveur de dev sert la copie de travail principale : la mesure « après » est celle de l'arbre, à refaire sur le
  serveur de dev une fois le commit reporté.

## Correctifs

### 1. Terminus de ligne (`app/Services/LineStations.php`)

Les deux bouts de la ligne étaient pris parmi les bouts des morceaux de `ST_LineMerge(ST_Collect(...))` de tous les
tracés distincts. Le recollement coûtait cher (`DISTINCT geom` trie les géométries entières : 96 ms sur 830000, puis
le recollement de 548 000 points). Les bouts des morceaux recollés sont connus sans recoller : un nœud où se touchent
exactement deux tracés est l'intérieur d'un morceau, tout autre nœud (bout libre, bifurcation) en est un bout, ainsi
que le départ d'un tracé fermé sur lui-même. Seuls les points de départ et d'arrivée de chaque tracé distinct sont lus
(dédoublonnage par `md5(ST_AsBinary(geom))`). Une ligne faite seulement de boucles de plusieurs tracés (une sur
2 175 : aucun bout) garde l'ancien calcul, seul à donner le point de départ choisi par le recollement.

Vérifié sur les 2 175 lignes : même paire de bouts partout ; quatre lignes ont deux points à égalité de distance qui
diffèrent au quinzième chiffre (moins d'un nanomètre), sans effet sur la gare la plus proche. Les extrémités gardées en
cache restent valables (même résultat, `LineDataSignature::FORMAT` inchangé).

Tests (`LineStationsTerminalsTest`) : `test_the_ends_are_found_without_merging_the_track` (tracé en double sur deux
ères, embranchement ; aucune requête `ST_LineMerge` ; échoue sans le correctif) et
`test_a_line_made_only_of_a_loop_keeps_a_terminus_at_its_start` (repli). `PageQueryBudgetTest` suit la nouvelle
requête des terminus.

### 2. Accueil à froid (`app/Services/SiteStats.php`)

La piste annoncée (`length_meters` manquant, recalculé à chaque fois) n'était pas la cause : **un seul** tronçon sur
392 657 n'a pas de longueur (source vide, créé le 15/10). La cause : la requête joignait les ères aux 330 000 tronçons
ouverts puis faisait un `COUNT(DISTINCT line_id)` ; le planificateur gardait le tracé de chaque tronçon (1,3 Ko, lu
pour le seul repli `ST_Length`) dans les lignes à trier, et le tri débordait sur disque (280 Mo écrits). La longueur
de chaque tronçon est maintenant lue dans une sous-requête fermée par `OFFSET 0`, avant le regroupement : le tri ne
porte plus que trois colonnes (11 Mo). 2,2 s → 0,3 s (0,8 s quand la table n'est pas en mémoire), mêmes valeurs pour
les 21 ères. Pas de commande de remplissage nécessaire.

Test : `tests/Feature/Performance/SiteStatsProfileTest.php` (valeurs par ère, ère vide, tronçon sans longueur, voie
fermée écartée, et longueur lue avant le regroupement ; échoue sans le correctif).

### 3. Quiz à froid, gares et fréquentation (`app/Services/Quiz/DailyFactPool.php`)

La question « quelle année a ouvert cette grande gare ? » écarte les gares qui ont un homonyme. Le compte se faisait
par gare candidate (`SELECT COUNT(*) FROM stations t WHERE lower(btrim(t.name)) = ...`, sans index possible) : les
9 400 noms relus deux cents fois, 1,1 s. Il est fait une fois pour toutes les gares (`COUNT(*) OVER (PARTITION BY
lower(btrim(name)))`) : 24 à 64 ms, les 175 mêmes gares.

Test : `DailyFactQuizTest::test_a_station_with_a_namesake_is_left_out_and_namesakes_are_counted_once` (homonyme écarté,
comptage en une passe ; échoue sans le correctif).

L'autre lecture lourde du quiz (`LineQuiz::pool`, 0,8 à 1 s, en cache une heure) n'est pas changée : 400 ms de lecture
de toute la table des tronçons par index (pas de tri), 370 ms de centroïdes de 231 lignes. La réécrire ne gagnerait
qu'en changeant le résultat (centroïde pris en WGS84) ou avec un index couvrant (`line_segments (line_id, era_id)
INCLUDE (length_meters, source)`), à décider : c'est une écriture en base (migration).

### 4. Gares de la fiche de ligne (`LineSheet`, `LineController`, `Lines/Show.svelte`)

La carte du tracé, le diagramme « Où et quand » et les données structurées ne lisent de chaque gare que sa position,
son nom, son terminus et ses états (aujourd'hui et à chaque ère) : `LineSheet::PAGE_STATION_FIRST`. Le reste (PK,
commune, type, dates et leur provenance), lu par la seule liste des gares plus bas, arrive dans la prop différée
`station_details` du groupe `sheet` (`LineSheet::pageStationDetails`, même requête différée que les autres sections) ;
`lib/lineStationDetails.js` les rejoint par `slug`, et la liste affiche « Chargement de la suite de la fiche… »
jusque-là. Rien ne disparaît de l'affichage ; l'API REST et GraphQL ne changent pas.

Sur 830000 : `stations` 145 → 70 Ko au premier affichage (171 Ko en tout au lieu de 245), `station_details` 77 Ko dans
la requête différée. Le reste du premier affichage tient aux états par ère (`eras` : 54 Ko), que la carte lit à la phase
choisie ; un codage par plages de décennies les réduirait encore, mais demande de changer le format lu par trois
composants : non fait.

Contrepartie : la requête différée relit les gares de la ligne (cinq lectures indexées, 70 ms sur 830000) ; son budget
passe de 26 à 32 requêtes (`PageQueryBudgetTest`, commentaire daté ; la 32e vient du matériel, `LineMaterialFacts`,
arrivé pendant le chantier).

Tests : `PageQueryBudgetTest::test_line_sheet_sends_first_only_what_the_map_reads_of_each_station` (champs du premier
affichage, détails dans la requête différée ; échoue sans le correctif), `test_the_api_keeps_the_complete_line_sheet`
(premier affichage, détails et sections différées réunis = fiche de l'API, valeur pour valeur), et les tests de page qui
lisaient PK et dates (`LineSheetPageTest`, `LinePagesTest`, `StationOpeningRangeTest`, `LineStationsOpenedSourceTest`)
les lisent dans `station_details`. vitest : `tests/js/lineStationDetails.test.js`.

### 5. Cartes du département et de la commune (`DepartmentController`, `CommuneController`)

La carte (`map`) devient une prop différée (`Inertia::defer`) ; la page garde le cadre de la carte à sa hauteur et y
monte la carte à l'arrivée des données (`aria-busy` jusque-là). Le département reçoit d'emblée le nombre de gares cité
sous la carte (`map_station_count`). Rhône : 94 → 37 Ko au premier affichage, carte 57 Ko juste après ; Lyon : 49 →
25 Ko, carte 24 Ko. Aucun temps serveur en plus (fiches en cache, aucune requête SQL).

Tests : `DepartmentFiguresTest::test_the_page_redirects_to_the_canonical_address` et `CommuneSheetTest` (carte absente
du premier affichage, présente dans la requête différée ; échouent sans le correctif).

Contrepartie à connaître : la copie hors ligne d'une page (service worker) ne garde que le premier affichage ; la carte
d'une commune ou d'un département lue hors ligne reste vide, comme les sections différées des fiches de ligne et de gare
depuis le chantier « budget des fiches ».

## Contrôles

- Tests ciblés à un processus : performances, fiches de ligne et de gare, API des lignes et contrat d'API, quiz,
  départements, communes, `NetworkFiguresTest`, `LineFactsTest`, `SheetTextRulesTest`, `tests/Feature/Web`, rejoués
  après rebase sur `678897ce`. Chaque nouveau test vérifié en échec sans son correctif (`vendor` monté sur `/app/vendor`).
- Pint, PHPStan (fichiers touchés, sans erreur, baseline inchangée), Prettier, ESLint, vitest du nouveau module.
  `pnpm check` : 0 erreur sur la base du chantier ; après rebase, 4 erreurs nouvelles dans
  `resources/js/Components/Search/SearchCombobox.svelte` (propriétés `plate` et `detail`, lignes 342 à 345), venues
  du commit de la recherche (`46335fd1`), pas de ce chantier : à corriger par son auteur. Aucun texte traduit ajouté
  (`common.sheet_loading` existant).

## Ce qui reste

- `LineQuiz::pool` (0,8 à 1 s à froid, une fois par heure) : voir plus haut (index couvrant à décider).
- Accueil à froid : restent les images d'archives (`HomeArchives::pool`, 300 à 430 ms, lecture de `media`) et la
  longueur des formes SNCF exploitées (`NetworkStats::totalRouteKm`, 190 ms de `ST_Length` géodésique), en cache ensuite.
- Le tronçon sans `length_meters` (un sur 392 657) : sans effet mesurable, à remplir à l'occasion par la chaîne du
  coordinateur (`UPDATE line_segments SET length_meters = ROUND(ST_Length(geom::geography)) WHERE length_meters IS NULL`),
  non lancé.
- Fiche 830000 : `eras` des gares (54 Ko) au premier affichage, voir le point 4.
- Mesure « après » sur le serveur de dev une fois les fichiers reportés dans la copie principale.

## Arbre

`.claude/worktrees/budgets-suite`, retiré à la fin (`git worktree remove`, sans `--force`). Brouillons hors dépôt :
`brouillons-agents-2311/budgets-suite/` (scripts de mesure et de comparaison, `.nobackup`).
