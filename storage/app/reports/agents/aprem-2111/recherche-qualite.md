# Qualité de la recherche (23/11, soir)

Agent « recherche-qualite ». Suite du chantier « recherche » du 22/11 (`recherche.md`, 78 requêtes, 97 % en premier) :
un second banc de requêtes réelles, mesuré par famille, puis les défauts corrigés à la cause, sans changer de moteur
(PostgreSQL, clés `search_name` et `search_all`, trigrammes).

## Banc

`tests/Fixtures/Search/queries-qualite.php` : 84 requêtes réelles, chacune avec sa fiche attendue et sa famille.

| famille | exemples |
|:-|:-|
| gares (15) | « Saint-Etienne Chateaucreux », « pleine fougeres », « besancon viotte », « paris gare de lyon », « strasbourg », « metz », « mulhouse » |
| anciens noms (12) | « capelles » (Lardenne), « la garenne bezons », « montceaux les mines », « archeo-ligne-de-nouvel-avricourt-a-dieuze » (ligne fusionnée), « archeo-bifurcation-de-mirambeau », « lgv est europeenne », « artere imperiale », « polt » |
| codes (8) | « 005000 », « 640 000 », « ligne 570000 », « ligne 830 000 », « rfn-752000 », « 87271007 », « uic 87686006 » |
| homonymes (6) | « saint denis », « valence », « valence d agen », « montreuil », « tours », « vienne » |
| matériel (13) | « Z 56800 », « Regio2N », « regio 2n », « BB 22200 », « bb22200 », « omneo », « sybic », « agc », « picasso », « z2n », « nez casse » |
| accidents (8) | « Lagny 1933 », « lagny pomponne », « catastrophe de melun 1913 », « bretigny 2013 », « accident gare de lyon 1988 », « zoufftgen » |
| glossaire (8) | « TVM 430 », « tvm430 », « kvb », « block automatique lumineux », « caténaire », « ertms niveau 2 », « overhead line », « fahrleitung » |
| fautes (6) | « strasbourd », « marseile saint charles », « bordeau saint jean », « toulouse matabio », « eckwershiem », « regiolys » |
| anglais et allemand (8) | « Paris Nord station », « Bahnhof Straßburg », « Lyon Part-Dieu railway station », « Mülhausen Bahnhof », « Paris to Bordeaux line », « Strecke Paris Lille », « Lagny rail accident », « Eisenbahnunfall von Melun » |

Mesure : `bin/php artisan vff:search-eval --file=tests/Fixtures/Search/queries-qualite.php --failures` (lecture
seule ; la commande donne maintenant le détail par famille et lit le glossaire).

## Avant, après (base de développement `vff`)

| famille | requêtes | premier juste, avant | premier juste, après |
|:-|-:|-:|-:|
| gares | 15 | 12 | 15 |
| anciens noms | 12 | 12 | 12 |
| codes | 8 | 6 | 8 |
| homonymes | 6 | 6 | 6 |
| matériel | 13 | 13 | 13 |
| accidents | 8 | 6 | 8 |
| glossaire | 8 | 0 | 8 |
| fautes | 6 | 6 | 6 |
| anglais et allemand | 8 | 2 | 8 |
| **total** | **84** | **63 (75,0 %)** | **84 (100 %)** |

Dans les 5 premiers : 67 (79,8 %) avant, 84 (100 %) après.

Dernière mesure, à 21 h 25 : 82 sur 84. Les deux requêtes perdues ne viennent pas de la recherche : « la garenne bezons »
et « plourivo lezardrieux » ne trouvent plus rien parce que les anciens noms `wikipedia_history` et `wikidata_names` des
gares ont disparu de `stations.alt_names` pendant la chaîne de 21 h 24 (912 gares avec d'autres noms à 20 h 55, 893 à
21 h 25 ; La Garenne-Colombes et Lancerf n'en ont plus aucun). À vérifier par le coordinateur : la consolidation semble ne
plus reprendre ces deux sources.

Premier banc (`queries.php`, 78 requêtes) : inchangé, 76 en premier, 77 dans les 5. « st hilaire chalons » reste 2e
(faute de frappe, déjà notée le 22/11). « pissy poville » ne sort plus parce que l'accident est passé « à revoir »
(commune corrigée en Barentin) : donnée, pas recherche.

Temps : identiques à charge égale. Mesure alternée ancien et nouveau code, machine chargée (uptime 9,7), recherche du
catalogue seule sur les 78 requêtes du premier banc : médiane 21 à 22 ms dans les deux cas, 95e centile 34 à 45 ms avant,
29 à 45 ms après. Le glossaire ajoute 0,2 ms par recherche (17 ms pour bâtir l'index la première fois dans un processus).

## Défauts et correctifs

1. **Gare d'une commune derrière une ancienne gare du même nom.** « strasbourg » donnait d'abord Strasbourg (gare
   fermée, nom exact), puis Strasbourg-Ville ; « metz » pareil ; « mulhouse » donnait Mulhouse-Dornach avant
   Mulhouse-Ville. Correctif :
   - la gare ouverte aux voyageurs dont la commune est la requête et dont le nom commence par elle vaut un nom exact
     (`SearchQuery::citySql`) ;
   - à pertinence égale, la gare la plus fréquentée d'abord (`SearchFields::stationAttendance`, meilleure année de
     `station_attendances`), avant le nombre de lignes.

   Seulement pour les correspondances exactes : lue pour chaque fiche approchée, la fréquentation coûtait 35 ms sur
   « st hilaire chaleons ». Même tri dans l'annuaire des gares et la page `/search` (`StationDirectory::orderByRelevance`).
2. **Mots génériques exigés.** « Paris Nord station », « Bahnhof Straßburg », « ligne 830 000 », « uic 87686006 »,
   « Paris to Bordeaux line », « Strecke Paris Lille » ne trouvaient rien ou mal. Correctif : `SearchText::GENERIC_WORDS`
   (gare, halte, station, railway, rail, train, Bahnhof, Hbf, ligne, line, Strecke, accident, catastrophe, disaster,
   crash, Unfall, Eisenbahnunfall, UIC, code, et the, of, to, at, and, von, nach, am, im, und). Ces mots sont
   facultatifs même en dernier, sauf quand la requête n'a rien d'autre (« gare »). Les articles allemands der, die, das
   n'y sont pas, parce que Die est une gare.

   `digits()` ignore ces mots : « ligne 830 000 » est le code 830000, « UIC 87686006 » un code UIC. Le rang compte la
   requête entière et la requête sans ses mots génériques : « Paris Nord station » est le nom exact de Paris-Nord.
3. **Noms étrangers des villes.** `SearchText::EXONYMS` (Straßburg, Mülhausen, Kolmar, Schlettstadt, Hagenau, Zabern,
   Diedenhofen, Saarburg, Saargemünd, Basel, Genf, Geneva, Dunkirk, Marseilles, Nizza) : le mot vaut aussi le nom
   français. Ce sont aussi les noms des gares d'Alsace-Moselle de 1871 à 1918. Pas de « Lyons », qui est une commune.
4. **Année à côté d'un lieu.** « Lagny 1933 » et « accident gare de lyon 1988 » ne trouvaient rien : « 1933 » devait
   être un mot du nom. Correctif : `SearchQuery::splitYear`. Une année de 1800 à 2099 limite les accidents à cette
   année, et les autres mots cherchent le nom. « accident 1933 » donne tous les accidents de 1933. Même règle dans
   l'en-tête, l'API et la page Accidents (`AccidentQuery`).
5. **Requête entière dans un autre nom.** « Eisenbahnunfall von Melun » donnait d'abord la catastrophe de 1991, par
   ordre alphabétique du nom français. Nouveau rang 3 : une requête de deux mots ou plus trouvée telle quelle dans un
   autre nom (ici le nom allemand de l'accident de 1913). Rangs de `rankSql`, du meilleur au moins bon :
   - 0 : nom exact ;
   - 1 : début du nom ;
   - 2 : requête dans le nom ;
   - 3 : requête entière dans un autre nom ;
   - 4 : requête sans ses mots génériques dans le nom ;
   - 5 : tous les mots dans le nom ;
   - 6 : tous les mots dans un autre nom ;
   - 7 : milieu d'un mot.
6. **Glossaire absent de la recherche.** Nouveau service `GlossarySearch` (sans SQL), avec la même lecture et le même
   rang que les fiches (`SearchQuery::rankKeys`, écrit en PHP comme `rankSql`). Il cherche le nom en fr, en et de, le
   sigle, le développé et la clé du terme. Il sert à trois endroits :
   - REST `GET /api/v1/search` renvoie `glossary` (5 termes au plus : slug, name, names, abbr) ;
   - le serveur MCP accepte `glossary` dans `kinds` (cherché par défaut, `vff_url` vers `/glossary#slug`) ;
   - la barre de recherche montre un groupe « Glossaire » (« Glossary », « Glossar ») entre les accidents et les
     communes, avec le nom dans la langue de la page.
7. **Anciennes adresses.** L'ancien slug d'une gare fusionnée (`station_redirects`) ou d'une ligne renommée
   (`LineSlugRedirects`) tapé dans la recherche trouve la fiche qui l'a remplacé, en premier. Le coût est d'une
   lecture indexée, seulement pour une requête en forme de slug. Pour les lignes `archeo-…` du banc, les mots suffisaient
   déjà. Sur `vff`, `station_redirects` est encore vide (fusions du 23/11 non appliquées) ; le test le vérifie avec
   une redirection posée.

Matériel (synonymes) et normalisation des accents et des tirets : rien à corriger. Les désignations en base couvrent
Regio2N, Omneo, Sybic, AGC, Picasso, Francilien, TER 2N NG et « nez cassés », et la clé sépare lettres et chiffres
(« bb22200 », « tvm430 »).

## Tests

- `tests/Feature/Search/SearchQualityTest.php` rejoue le banc sur un petit corpus tiré de la base
  (`tests/Fixtures/Search/corpus-qualite.json` : 149 gares avec fréquentation et nombre de lignes, 130 lignes,
  35 matériels, 44 accidents, soit les fiches attendues et toutes celles qui sortaient dans les 8 premiers, avant et
  après). Seuil de rang : chaque fiche en premier (`MAX_RANK = 1`). Le fichier teste aussi :
  - le budget de requêtes SQL d'une recherche de l'en-tête : 16 au plus (6 pour un code, 11 à 15 avec les noms
    approchés, 16 pour un ancien slug) ;
  - l'ancien slug d'une gare fusionnée ;
  - le glossaire par l'API.
- Le même test, lancé sur l'ancien code (fichiers de test montés sur le dossier principal), échoue sur 21 requêtes :
  les 13 défauts ci-dessus et les 8 du glossaire.
- Tests unitaires : mots génériques, noms étrangers, `digits`, `splitYear`, `rankKeys` (`SearchQueryTest`), rangs 3, 4
  et 5 (`SearchQuerySqlTest`), groupe Glossaire (`tests/js/siteSearch.test.js`).
- Tests mis à jour :
  - `ApiRobustnessTest` : Versailles-Rive-Droite, gare de la commune, passe maintenant devant les « Versailles halte n »
    de la fabrique ; la réponse a la clé `glossary` ;
  - `AppPagesTest` (clé `glossary`) ;
  - `AccidentSearchTest` (valeur `glossary` de `kinds`) ;
  - `SearchQueryBoundaryTest` (« g » devient « gare », mot générique).
- Contrôles passés : 680 tests ciblés (recherche, API, MCP, GraphQL, annuaires, glossaire), Pint, PHPStan sans ajout à
  la baseline, ESLint, Prettier, vitest, `vff:i18n-check --strict`.
- Contrat d'API : 10 ajouts seulement (`glossary` du REST, valeur `glossary` du MCP).
- Documentation : `docs/MCP.md` et `docs/API-CHANGELOG.md`. Le schéma GraphQL ne change pas.

Le contrat régénéré sur le HEAD du soir ajoutait aussi 85 entrées des nouveaux outils MCP d'un autre agent
(bibliography, companies, data_coverage…), absentes de l'instantané : je ne les ai pas commitées. À régénérer par leur
auteur (`bin/php artisan vff:api-contract`).

## Ce qui reste

- La page `/search` n'a pas de groupe Glossaire (seulement la barre de recherche, l'API et MCP). Il faudrait un type de
  plus dans `SearchResultsPage` et `resources/js/lib/searchPage.js`.
- GraphQL `search` ne renvoie pas le glossaire (il faudrait un type `SearchGlossaryTerm` et `make docs-graphql`).
- Les anciens noms de gares perdus pendant la chaîne de 21 h 24 (voir plus haut).
- Deux fiches Lagny pour le même accident du 23/12/1933 (`1933-12-23-pomponne` et `1933-12-23-lagny`) : doublon à
  fusionner côté accidents.
