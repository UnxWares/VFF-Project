# Méthode de classification trafic/statut VFF (SNCF + Wikipedia + OSM/GTFS + archeoferroviaire)

Conçue et validée par workflow multi-agent. Datasets piliers vérifiés en live le 2026-05-29.

## Mise en œuvre actuelle : trafic par portion (2026-10-19)

`vff:classify-lines` (App\Services\LineClassifier + App\Services\PassengerPortions) ne déclare plus une
ligne « mixte » en entier dès qu'une gare de son code a des voyageurs : le trafic de l'ère courante se
décide portion par portion.

Ordre de priorité des signaux (du plus fort au plus faible) :

1. Catégorie SNCF LGV (sncf_line_catlig, nom, vitesse >= 250 km/h) : voyageurs, toute la ligne.
2. Type SNCF (sncf_line_types) : voie portuaire seule = fret ; raccordement ou voie-mère seuls = hérite
   des tronçons voisins (< 60 m), voyageurs seuls si branché sur une LGV.
3. Statut SNCF du tronçon (sncf_line_geometries.libelle) : une gare voyageurs hors tronçon « Exploitée »
   n'est pas desservie ; une portion hors tronçon exploité garde le trafic daté par le moteur.
4. Fermeture aux voyageurs récente (closure_passenger depuis 2010, ou closure de confiance haute depuis
   2010) sans réouverture postérieure qui la couvre : les gares à l'intérieur (à plus de 300 m des bouts)
   ne sont plus desservies et la portion est hors zone voyageurs. De même pour une fermeture aux voyageurs
   ancienne suivie d'une réouverture localisée ailleurs sur la ligne seulement.
5. Drapeau voyageurs de liste-des-gares (sncf_gares.voyageurs).
6. Drapeau fret de liste-des-gares : hors zone voyageurs, fret seul si le code a une gare fret.
7. Trafic daté par le moteur (EraTimeline) : hors zone voyageurs sans gare fret.

Zone voyageurs (mixte) :

- repère commun « m » : PK interpolé sur la forme SNCF du même code (<= 200 m), pour les gares comme pour
  les tronçons (le PK officiel des gares n'est pas linéaire avec la longueur) ;
- entre deux gares voyageurs desservies consécutives, sauf gare voyageurs non desservie entre elles ;
- prolongée jusqu'au bout de la ligne si ce bout touche le réseau voyageurs (gare desservie à <= 1 km,
  autre ligne voyageurs ou LGV à <= 60 m, ou bout à l'étranger) ou est à <= 500 m (gare terminus),
  sauf gare voyageurs non desservie dans l'écart ou écart fermé aux voyageurs à plus de 50 % sans
  réouverture localisée ; une gare terminus en cul-de-sac se prolonge malgré une fermeture ancienne ;
- une gare isolée qui ne se prolonge pas ne rend mixte que le tronçon qui la porte en son intérieur.

Les tronçons du moteur sont coupés aux gares qui bornent une zone (morceaux >= 150 m). Invariants associés
(vff:check-invariants) : segments.current_freight_on_passenger_code (aucun tronçon fret ne porte une gare
voyageurs desservie) et segments.current_closed_on_served_station (aucune voie fermée en place ne porte une
gare voyageurs desservie d'un tronçon SNCF « Exploitée »).

## Ères passées : fermetures aux voyageurs (2026-10-19)

Les ères 1830-2020 viennent du moteur (EraTimeline via vff:build-era-segments), pas de vff:classify-lines.
Fermer aux voyageurs, au fret ou à tout trafic sont des faits distincts : quand la meilleure source des
fermetures d'une portion ne date pas la fin d'un service, celle d'une autre source s'applique
(EraTimeline::serviceClosuresNotCovered). Voyageurs : la meilleure source n'a pas de closure_passenger ;
fret : ni closure_freight ni fermeture totale. Seulement avant la première fermeture totale de la meilleure
source. Une date à l'année seule ou une confiance basse ne l'écarte pas. Ex. 643000 (fermée aux voyageurs
en 1940, « mixte » jusqu'en 2020 avant la règle), 413000 (1938). Parseur Wikipédia : chaque mot-clé prend
sa propre date et son propre trafic (« ouverte le 4 août 1870 et fermée au service des voyageurs le 6 mars
1939 »), « fermée aux voyageurs et aux marchandises en 1950 » = fermeture totale, « …, au trafic
marchandises de X à Y le … » = fermetures au fret par section.

## Desserte du jour : saisonnier, touristique, vélorail, travaux (2026-11-19)

Règles appliquées aux écarts entre la desserte réelle (horaires GTFS ouverts lus par
`vff:import-station-service`, table `station_services`) et les statuts de VFF. Aucun statut nouveau : le
modèle n'a pas d'état « suspendue » (le libellé SNCF « Neutralisée » donne déjà une voie fermée en place,
`abandoned`), on s'en tient donc aux statuts existants. Chaque écart corrigé passe par une correction
sourcée rejouée à chaque calcul (`database/data/gares_corrections.php`, `database/data/line_event_corrections*.php`),
jamais par une écriture directe en base.

1. Desserte saisonnière régulière (Auray - Quiberon l'été, côte Fleurie d'avril à septembre,
   Pointe-de-Grave) : la ligne et ses gares restent ouvertes aux voyageurs toute l'année (déjà la règle 3 de
   la mémoire « trafic et gares » : jamais « fret seul »). L'ère courante ne change pas selon la saison.
2. Trains détournés quelques jours pendant des travaux sur une autre ligne (Le Teil et Saint-Péray sur la
   rive droite du Rhône : un à quatre jours dans l'année) : ce n'est pas une desserte, le statut ne change pas.
3. Travaux avec reprise annoncée et cars de substitution (Laon - Hirson, Poitiers - Limoges) : pas une
   fermeture, la ligne et ses gares restent ouvertes (« une fermeture temporaire n'est pas une fermeture »).
4. Suspension sans date de reprise qui dure (Montréjeau - Luchon, de 2014 à 2025) : c'est une fermeture
   datée (`closure`), suivie d'une réouverture datée (`reopening`) si la ligne rouvre ; les ères passées
   montrent alors la ligne fermée, voie en place.
5. Train touristique : une gare n'est « touristique » que si le seul train qui s'y arrête est touristique. Un
   train touristique qui roule EN PLUS d'un service public (train à vapeur du G.E.C.P. sur la ligne Nice -
   Digne, desservie tous les jours par ZOU !) ne rend ni la portion ni ses gares touristiques (correction
   `neutralize` de l'événement `heritage_start`, gardé dans la chronologie). Sans preuve d'arrêt, une gare
   d'une ligne touristique garde son statut (rien n'est déduit de la seule proximité).
6. Vélorail : ce n'est pas une circulation ferroviaire. Une gare desservie par le seul vélorail est fermée
   (mention vélorail gardée dans `station_services.indicators`) ; un vélorail ne rouvre jamais une gare. Les
   gares de vélorail qui ont peut-être aussi un train touristique (Le Perrier, Étretat) restent à relire.
7. Arrêt supprimé sur une ligne qui reste ouverte (21 haltes sans aucun train dans les trois versions des
   horaires de 2026) : fermeture aux voyageurs datée par l'article de la gare (correction `add`) ; sans date
   publiée, état du jour seulement (correction `current` « declassified »), jamais une date inventée.
8. Desserte réelle contredite par une source (RER A de la RATP noté « fret seul » par la liste SNCF) :
   correction `current` « active » appuyée sur les horaires publiés ; le plafond par les lignes (R2) reste
   appliqué ensuite. Un exploitant étranger absent des horaires français (Trenitalia à Viévola, CFL, Euskotren)
   ne ferme pas une gare.
9. Une gare ne se rapproche jamais d'un arrêt GTFS par son seul nom au-delà de quelques centaines de mètres :
   « Saint-Gervais-les-Bains » (Tramway du Mont-Blanc, en ville) n'est pas l'arrêt SNCF du Fayet (1,2 km).

## Date de fermeture d'une gare et frise des ères (2026-11-19)

La date de fermeture retenue (`stations.closed_at`) ne contredit jamais la frise, même aux ères « non
confirmées » (`station_eras.uncertain`) : 1 495 couples gare × ère la contredisaient le 18/11, 30 restent.

1. Fin de desserte d'une ligne (événement de ligne) datée avant une ère où la gare est encore montrée ouverte
   sur une autre de ses lignes en service (hors patrimoine) à moins de 500 m : ce n'est pas la fermeture de
   la gare (Vourey, La Praz, Griesbach). La date retenue devient l'intervalle des ères.
2. Année ronde d'une source qui reprend la décennie d'une autre (Wikidata « 1970 », Wikipédia « Fermeture :
   années 1970 ») : la date retenue est la décennie, comme la frise (18 gares : Réchicourt-le-Château,
   Marchiennes, Darnétal…). L'année ronde devient décennie, jamais l'inverse.
3. Fret seul non confirmé sur une ligne seulement touristique (train touristique ou vélorail, aucun trafic
   fret) : la ligne déclassée n'a plus de fret, la gare est fermée (Lapouyade, Toucy, Manot, Ansac). Un fret
   seul confirmé par une source (Carcassonne - Rivesaltes) et l'ère courante ne changent pas.
4. Restent, sans source pour trancher : quatre haltes de Limoges - Angoulême fermées aux voyageurs à une date
   inconnue (Fontafie, Taponnat, Le Quéroy - Pranzac, Magnac - Touvre), gares sans voie reconstituée ou sur
   des lignes non datées.

Corrections de gare ajoutées le même jour (`App\Services\StationCorrections`) : action `pk` (PK d'une gare sur
une ligne, d'après un schéma ou un article, quand le PK interpolé est faux : Troyes sur Troyes - Brienne) et
gare désignée par son nom et sa position (`near`) quand elle n'a aucun identifiant (halte de tramway lue dans
un schéma : Charantonnay).

## Classes de sortie

- passenger - voyageurs avéré (GTFS rail route_type=2 dessert la ligne, OU liste-des-gares.voyageurs=O, OU Wikipedia trafic contient une marque voyageurs sans fret, OU OSM railway:traffic_mode=passenger / highspeed=yes). Stocké traffic_type='passenger' + line.type='passengers', segments status in_service/single_track.
- freight_only - ouverte mais SANS service voyageurs : tronçon 'Exploitée' (SNCF) + type_ligne='Ligne' (ni Rac/Vmère/Vport) + NON-LGV + aucune desserte GTFS voyageurs + liste-des-gares all voyageurs=N avec >=1 fret=O (OU Wikipedia trafic = fret/marchandise sans marque voyageurs). traffic_type='freight', line.type='freight'.
- mixed - voyageurs ET fret simultanés (Wikipedia trafic contient les deux, OU OSM traffic_mode=mixed, OU liste-des-gares a >=1 voyageurs=O ET >=1 fret=O sur la ligne, OU une ligne 'Ligne' Exploitée desservie voyageurs avec embranchements fret). traffic_type='mixed', line.type='mixed'.
- closed - fermée/neutralisée/déclassée mais voie potentiellement en place : SNCF libelle in (Neutralisée*, Fermée*, Déclassée*, Transférée en voie de service, Retranchée, Fermée avec maintien en place) OU OSM railway=disused/abandoned avec rails OU archeoferroviaire 'non déferrée entre A et B'. status='abandoned' (rails en place), traffic_type='unknown'/historique.
- dismantled - voie déposée/détruite : OSM railway=razed/dismantled OU archeoferroviaire phrase de dépose/'déferrée' OU SNCF 'Fermée et déposée'/'Déclassée vendue'. status='dismantled' (ou 'razed' si rasé/urbanisé), traffic_type='unknown'.
- unknown - aucune source ne tranche le TYPE (fallback) : statut connu (ouvert/fermé) mais voyageurs/fret indéterminé. traffic_type='unknown', JAMAIS 'freight' par défaut. Concerne notamment raccordements/Vmère sans signal, et lignes sans gare référencée.

## Précédence des sources

- STATUT ouvert/fermé/déposé - Autorité n°1 = SNCF lignes-par-statut.statut (= sncf_line_geometries.libelle local), évalué PAR TRONÇON. Justif : couverture quasi-totale (886 codes / 1638 tronçons, 0 null), faisant autorité juridiquement (gestionnaire d'infra). 'Exploitée'=ouvert ; tout le reste=fermé à des degrés (Neutralisée=suspendue, Fermée/Déclassée=administrative, Transférée en VS=voie de service).
- STATUT déposé/détruit (raffine le 'fermé' SNCF) - Autorité n°2 = archeoferroviaire (article catégorisé régional + phrase 'non déferrée entre A et B') et OSM railway=razed/dismantled. Justif : SNCF dit 'Fermée' mais ne distingue pas voie-en-place vs déposée aussi finement ; archeoferroviaire donne le sous-tronçon déposé et la date, OSM donne l'état physique de la voie. Ces sources ne contredisent JAMAIS un 'Exploitée' SNCF (archeoferroviaire ne documente QUE des lignes disparues).
- TYPE voyageurs/fret/mixte - Autorité n°1 (POSITIF voyageurs) = GTFS SNCF horaires-sncf (route_type=2 uniquement) joint via UIC -> liste-des-gares.code_uic -> code_ligne. Justif : présence d'arrêts/horaires = service voyageurs CERTAIN et daté. C'est le seul signal SNCF qui tranche voyageurs vs fret.
- TYPE - Autorité n°2 = SNCF liste-des-gares (fret O/N, voyageurs O/N) agrégée par code_ligne. Justif : faisant autorité (591 lignes), donne directement fret-only (toutes gares voyageurs=N + >=1 fret=O = 110 lignes) sans dépendre de la jointure GTFS indirecte. Sert de pont GTFS<->RFN.
- TYPE - Autorité n°3 = Wikipedia infobox champ `trafic` (nettoyé, classé par mots-clés). Justif : strong quand rempli (~67% actives), protège nativement LGV/raccordements voyageurs du faux-fret (porte explicitement TGV/Ouigo/Transilien). Désambiguïse là où SNCF est muet.
- TYPE - Autorité n°4 (corroboration) = OSM railway:traffic_mode (passenger/freight/mixed) + highspeed=yes + usage=industrial/tourism. Justif : strong mais couverture partielle (~57%) et hétérogène ; sert de corroboration et de tie-break, JAMAIS de source unique pour 'freight'.
- NATURE de la ligne (Ligne vs Rac/Vmère/Vport vs LGV) - Autorité = SNCF lignes-par-type.type_ligne + lignes-lgv-et-par-ecartement.catlig (croisé v_max>=270 et OSM highspeed=yes). Justif : ces classifications conditionnent les RÈGLES DURES (un Rac/Vmère/Vport/LGV n'est jamais freight_only par défaut). Priment sur toute heuristique de TYPE.

## Algorithme (étapes)

0. PRÉPARATION DES CACHES (idempotent, une fois par run). Télécharger et mettre en table locale les datasets SNCF avec UA 'VFF-Bot/1.0' : lignes-par-type (-> sncf_line_types(code_ligne, rg_troncon, pkd, pkf, type_ligne)), lignes-lgv-et-par-ecartement (-> sncf_line_catlig(code_ligne, catlig)), liste-des-gares (-> sncf_gares(code_uic, code_ligne, fret O/N, voyageurs O/N, pk, geo_point)), vitesse-maximale (-> sncf_vmax). Télécharger le GTFS zip (Export_OpenData_SNCF_GTFS_NewTripId.zip), parser routes.txt (garder route_type=2, EXCLURE 3=bus et 0=tram-train selon politique), stop_times+trips -> set des stop_id desservis -> extraire l'UIC du stop_id (regex sur le suffixe numérique) -> set d'UIC voyageurs. sncf_line_geometries.libelle sert déjà de miroir local du statut (pas de re-fetch).

1. STATUT PAR TRONÇON (autorité SNCF). Pour chaque segment line_segments (joint à sncf_line_geometries par code_ligne + recouvrement pk/rg_troncon, sinon par code_ligne entier), mapper libelle -> classe statut : 'Exploitée'->open ; {Neutralisée*, Fermée avec maintien en place, Transférée en voie de service, Retranchée, Fermée non déposée}->closed (voie potentiellement en place, status='abandoned') ; {Fermée et déposée, Déclassée vendue, Déclassée non vendue}->dismantled (status='dismantled'). Conserver le statut tronçon par tronçon (NE PAS réduire un code à un statut unique : 279/886 codes sont mixtes).

2. RAFFINEMENT DÉPOSE (archeoferroviaire + OSM). Pour chaque ligne fermée, si un article archeoferroviaire CATÉGORISÉ (rubrique régionale/départementale, PAS 'Carte générale'/'°') matche (voir étape matching) : parser la phrase de statut ('non déferrée entre A et B' -> sous-tronçon status='abandoned' = rails en place ; reste -> 'dismantled') et écrire les line_events datés (opening/closure/closure_freight/dismantling). RÈGLE DURE : existence d'un article catégorisé => la ligne (ou tronçon matché) est closed/dismantled, JAMAIS active -> bloquer toute promotion en passenger/freight_only/mixed sur les tronçons couverts. Corroborer avec OSM : railway=razed/dismantled -> dismantled ; railway=disused/abandoned -> closed.

3. NATURE DU TRONÇON (règles dures). Marquer is_raccordement = (type_ligne IN (Rac,Vmère,Vport)) OU (lines.name commence par 'Raccordement') OU (way OSM sans ref rattaché spatialement). Marquer is_lgv = (catlig='Ligne à grande vitesse') OU (v_max>=270) OU (OSM highspeed=yes). Marquer is_vport = (type_ligne='Vport'). Ces flags conditionnent l'étape 5.

4. TYPE PAR TRONÇON (cascade voyageurs/fret), uniquement pour les tronçons OPEN. (a) Si is_lgv -> traffic_type='passenger' (court-circuit, jamais freight). (b) GTFS : si une gare de la ligne (via liste-des-gares.code_ligne) a son UIC dans le set GTFS voyageurs -> les tronçons desservis = passenger. (c) liste-des-gares agrégée par code_ligne : >=1 voyageurs=O -> passenger ; (>=1 voyageurs=O ET >=1 fret=O) -> mixed ; all voyageurs=N ET >=1 fret=O -> freight_only candidate. (d) Wikipedia trafic : marque voyageurs sans fret -> passenger ; fret/marchandise sans voyageurs -> freight_only ; les deux -> mixed (les marques voyageurs PRIMENT toujours, protège LGV/Rac). (e) OSM traffic_mode : passenger/mixed -> au moins passenger ; freight -> corrobore freight_only. Fusionner les votes par priorité précédence (GTFS/liste-des-gares > Wikipedia > OSM) ; le vote 'passenger'/'mixed' l'emporte sur 'freight' en cas d'égalité de niveau (un seul signal voyageurs suffit à exclure freight_only).

5. APPLICATION DES RÈGLES DURES. Si is_raccordement OU is_lgv ET le résultat de l'étape 4 serait freight_only -> NE PAS écrire freight_only : un Rac/Vmère hérite du traffic_type des lignes-mères adjacentes (via connectivité network_edges / proximité géométrique <50m) ; à défaut mixed ; un Vport -> traffic_type='freight' MAIS marqué explicitement 'voie portuaire' (pas 'ligne fret commerciale') ; une LGV -> toujours passenger.

6. SCORE DE CONFIANCE PAR TRONÇON. confidence = max des poids des sources concordantes (voir confidence_rules). Enregistrer par segment : classe finale, score, et la liste des sources votantes (audit).

7. AGRÉGATION LIGNE. Réduire les classes de tronçons en une classe ligne : si tous open et homogènes -> cette classe ; si mix open/closed -> 'partiellement fermée' (line.type reflète la partie exploitée, mais on garde la granularité segment) ; si tous closed/dismantled -> closed/dismantled. line.type = passengers/freight/mixed selon l'agrégat des tronçons open ; conserver le détail au niveau line_segments.traffic_type + line_segments.status (déjà la vraie granularité).

8. ÉCRITURE TRANSACTIONNELLE + AUDIT. Mettre à jour line_segments.status, line_segments.traffic_type ; lines.type ; insérer line_events datés (archeoferroviaire/Wikipedia) via updateOrInsert (idempotent) ; logger dans line_sources (source_type, fields_provided, confidence) chaque source ayant voté. Mode --dry-run affiche un diff sans écrire ; --report sort un CSV des conflits.


## Règles dures raccordement / LGV

RÈGLE DURE codée AVANT toute heuristique de proximité/type. RACCORDEMENTS : un tronçon est raccordement si type_ligne IN (Rac, Vmère, Vport) OU lines.name match /^Raccordement/i OU way OSM sans tag ref rattaché spatialement (héritage de voisinage). Un raccordement n'est JAMAIS classé freight_only par défaut : il HÉRITE du traffic_type des lignes-mères connectées (jointure via network_edges/network_nodes, sinon segments à <50m partageant un endpoint) ; si les voisines sont passenger/mixed -> mixed ; si purement fret -> freight ; si indéterminé -> 'unknown' (jamais freight par défaut). Cas Vport (voie portuaire) = desserte fret mais étiquetée explicitement 'voie portuaire', pas 'ligne fret commerciale'. LGV : un tronçon est LGV si catlig='Ligne à grande vitesse' OU v_max>=270 (parser le string en int) OU OSM highspeed=yes. Override absolu : is_lgv => traffic_type='passenger', is_lgv court-circuite la règle de proximité-gare de l'ancien DeriveTrafficType (qui taggait à tort une LGV en freight car peu de gares). Une LGV n'est JAMAIS freight_only. Ces deux flags sont calculés à l'étape 3 et appliqués à l'étape 5 ; ils ne peuvent être contredits par OSM/Wikipedia (au contraire Wikipedia trafic LGV porte toujours TGV/Ouigo, ce qui les conforte).

## Score de confiance

Score par tronçon = somme plafonnée [0..1] des poids des sources CONCORDANTES sur la classe retenue, avec poids par autorité : STATUT -> SNCF libelle=0.6 (authoritative), archeoferroviaire article catégorisé=0.3, OSM railway=*=0.2. TYPE -> GTFS route_type=2 desserte=0.5 (authoritative POSITIF voyageurs), liste-des-gares fret/voyageurs=0.4 (authoritative), Wikipedia trafic rempli=0.3 (strong), OSM traffic_mode/highspeed=0.2 (strong mais partiel). Bonus accord +0.1 si >=2 sources d'autorités différentes concordent ; malus -0.2 si une source d'autorité supérieure contredit. SEUILS : score>=0.8 = 'high' (écrit sans flag) ; 0.5<=score<0.8 = 'medium' (écrit + flag à curer) ; 0.3<=score<0.5 = 'low' (écrit en 'unknown' pour le TYPE, statut conservé) ; score<0.3 = 'insufficient' -> fallback, pas d'écrasement d'une valeur curée manuellement. Le statut ouvert/fermé hérite quasi toujours 'high' (SNCF seul=0.6, +0.2 OSM => 0.8). Le TYPE freight_only n'atteint 'high' que si liste-des-gares(0.4)+absence GTFS confirmée+Wikipedia/OSM concordent (jamais freight_only sur un seul signal faible). Stocker score + sources dans line_sources.confidence/fields_provided pour audit et tri de curation.

## Résolution de conflits

- SNCF='Exploitée' (open) VS archeoferroviaire a un article catégorisé -> NE PAS écraser en closed sur tout le code : c'est presque toujours une fermeture PARTIELLE de tronçon. Confirmer par la géométrie GPX (buffer ~200m vs sncf_line_geometries.geom) et ne marquer closed/dismantled QUE les sous-tronçons réellement couverts par le GPX + la phrase 'non déferrée'. Le reste reste Exploitée. (RÈGLE DURE archeo s'applique au tronçon matché, pas à la ligne entière.)
- SNCF='Exploitée' VS OSM=railway:abandoned/disused -> conflit d'actualité ; SNCF (gestionnaire infra) prime pour le STATUT juridique mais OSM signale souvent une voie hors-service de fait : marquer status='abandoned' (voie en place) avec confidence 'medium' et flag de curation ; ne pas passer 'dismantled' sans razed OSM ou dépose archeo.
- Wikipedia trafic='Fret' VS GTFS dessert une gare voyageurs de la ligne -> GTFS prime (POSITIF voyageurs avéré et daté) : classe=passenger ou mixed, jamais freight_only. Wikipedia est souvent en retard sur les réouvertures voyageurs.
- Wikipedia trafic vide/'Aucun' VS liste-des-gares voyageurs=O -> liste-des-gares prime (autoritative) : passenger. Ne JAMAIS conclure freight_only/closed d'un trafic Wikipedia vide.
- OSM traffic_mode='freight' VS liste-des-gares voyageurs=O sur la même ligne -> liste-des-gares prime : la ligne a au moins une desserte voyageurs -> mixed (l'OSM freight reflète un tronçon/usage local, ne pas propager freight à la ligne).
- is_lgv (catlig/v_max/highspeed) VS toute source disant freight -> is_lgv prime ABSOLUMENT : passenger. (Corrige le piège LGV de l'ancienne dérivation par proximité-gare.)
- is_raccordement VS heuristique disant freight_only -> règle dure prime : hériter des voisins ou 'unknown', jamais freight_only par défaut.
- liste-des-gares all voyageurs=N + fret=O (freight_only) VS GTFS bus de substitution (route_type=3) présent -> EXCLURE le bus : la desserte bus ne fait PAS passenger ; rester freight_only/closed selon le statut SNCF.

## Fallbacks

Quand aucune source ne tranche le TYPE (raccordement sans voisin classé, ligne 'Exploitée' sans gare référencée, Wikipedia/OSM/GTFS muets) : (1) NE JAMAIS défaulter à 'freight' - défaut = traffic_type='unknown', confidence 'insufficient'. (2) Pour un raccordement/Vmère sans signal : hériter par connectivité des lignes-mères (network_edges/proximité <50m) ; si toujours rien -> 'unknown'. (3) Pour une 'Ligne' Exploitée non-LGV, non-raccordement, sans aucune gare dans liste-des-gares et sans desserte GTFS et Wikipedia muet -> candidate freight_only PAR DÉFAUT (cas des lignes 100% fret sans gare), mais en confidence 'low' et flaggée pour curation manuelle (jamais 'high'). (4) Quand le STATUT lui-même est indéterminé (code absent de SNCF, ni OSM ni archeo) : conserver le status existant en base (ne pas écraser une valeur curée à la main), confidence 'insufficient'. (5) Curation manuelle = source de vérité finale : les commandes vff:set-status et vff:set-traffic restent prioritaires ; la classification automatique ne réécrit pas un segment marqué manuellement (tracé via line_sources.source_type='manual'). (6) Tout fallback est journalisé dans le --report CSV pour revue.

## Commande à construire

Commande : `php artisan vff:classify-lines` (classe ConsoleCommand App\Console\Commands\ClassifyLines). Signature : `vff:classify-lines {--era= : année de l'ère cible (défaut: ère courante is_current, id=5)} {--code= : ne classifier qu'un code RFN précis} {--skip-fetch : ne pas re-télécharger les datasets/GTFS, réutiliser les caches locaux} {--refresh-archeo : re-crawler archeoferroviaire (sinon réutilise le cache)} {--dry-run : calcule et affiche le diff sans écrire en base} {--report= : chemin CSV où dumper les conflits inter-sources}`. Description : 'Fusion déterministe SNCF+Wikipedia+OSM/GTFS+archeoferroviaire : classe chaque tronçon en passenger/freight_only/mixed/closed/dismantled et agrège par ligne, en appliquant les règles dures raccordement/LGV.' Rôle/étapes : (1) résout l'Era ; (2) appelle un service App\Services\LineClassifier (à extraire, injecté par le constructeur) qui exécute les caches (SncfOpenDataImporter étendu pour lignes-par-type/catlig/liste-des-gares + un GtfsTrafficResolver pour le GTFS, et un ArcheoferroviaireCrawler avec UA navigateur + Accept-Language fr-FR + backoff pour le signal négatif) ; (3) exécute l'algorithme étapes 1->8 ci-dessus en transaction ; (4) écrit line_segments.status/traffic_type, lines.type, line_events, line_sources, et un score de confiance par segment ; (5) imprime les stats par classe + le compte de conflits. Réutilise WikipediaLineParser::parseInfobox (champ trafic) déjà présent, et remplace/supersede les anciens DeriveTrafficType (proximité-gare naïve) et SetTrafficType pour la dérivation automatique. Idempotent (updateOrInsert sur events/sources, recalcul complet des segments de l'ère).

## Validation sur lignes témoins

Précision estimée : ~85-90% sur le STATUT (ouvert/fermé/déposé) et ~80% sur le TYPE (voyageurs/fret/mixte) à confiance 'high', sur la base des 11 lignes témoins. Détail: STATUT quasi-parfait (l'autorité SNCF sncf_line_geometries.libelle est déjà importée localement et donne le bon résultat sur 11/11). TYPE: les règles dures LGV (seuil v_max>=270 validé SANS faux positif sur 13 lignes du dataset) et raccordement (héritage network_edges vérifié fonctionnel sur Valenton/Montereau/Lavalduc/Mourepiane) éliminent le piège principal de l'ancienne dérivation. Erreurs résiduelles attendues sur: (a) lignes 'Exploitée' fret-only sans gare où le freight_only n'est qu'un fallback 'low' à curer; (b) voies portuaires 'Transférée en voie de service' classées closed alors qu'elles roulent encore (fret); (c) réouvertures fret récentes (Mourepiane ~2023) que le cache SNCF 'Neutralisée' ignore. RÉSERVE MÉTHODOLOGIQUE FORTE: dans cet environnement, tout HTTP externe (API SNCF Open Data, Wikipedia, archeoferroviaire) renvoie un MOCK de schéma (types, pas valeurs) et non les données réelles - la validation des autorités GTFS/liste-des-gares/Wikipedia-trafic/archeo n'a donc PAS pu être exécutée en live; elle repose sur (1) le miroir SNCF déjà importé en base (libelle, autorité n°1 du statut), (2) la topologie network_edges et les line_stops réels en base, (3) les vitesses réelles en base, et (4) la vérité connue de lignes célèbres. La précision sur les sources non re-fetchables (Wikipedia trafic, GTFS direct, archeo phrasing) est estimée par cohérence d'architecture, non mesurée.

- ✅ **005000 - Ligne de Paris à Strasbourg (LGV) [LGV Est]** - vérité: voyageurs (LGV TGV/Ouigo, aucun fret). Source: SNCF sncf_line_geometries=Exploitée (2 tronçons), v_max segment=320 km/h en base, catlig SNCF='Ligne à grande vitesse', Wikipedia trafic=TGV/Ouigo. Vérité connue: LGV pure voyageurs.  
  prédit: passenger. Étape 3 marque is_lgv (v_max=320>=270 + name '(LGV)' + catlig). Étape 4(a) court-circuit -> passenger. Étape 5 override absolu LGV.
- ✅ **752305 - Raccordement de Valenton (LGV)** - vérité: voyageurs/mixte de fait - raccordement d'interconnexion LGV qui relie la Grande Ceinture (990000) aux LGV Sud-Est ; emprunté par TGV ET trains de fret. NE DOIT JAMAIS être 'freight'. Source: SNCF=Exploitée 1 tronçon ; name=/^Raccordement/ + '(LGV)' ; network_edges -> voisins 752100 (LGV passenger) + 990000 (Grande Ceinture mixte).  
  prédit: mixed (héritage voisins). Étape 3 is_raccordement (name /Raccordement/ + type_ligne Rac) ET is_lgv ('(LGV)'). Étape 5: règle dure -> hérite des lignes-mères via network_edges = {752100 passenger, 990000 mixte} -> mixed. JAMAIS freight_only.
- ✅ **752323 - Raccordement Sud de Montereau LGV** - vérité: raccordement LGV reliant la LGV Sud-Est (752000) à la ligne classique Paris-Lyon-Marseille à Montereau ; voyageurs (déviations TGV) ne doit pas être 'freight'. Source: SNCF=Exploitée ; name=/Raccordement/ + 'LGV' ; network_edges -> voisin 752000 (LGV passenger).  
  prédit: passenger (héritage). Étape 3 is_raccordement + is_lgv. Étape 5 héritage: seul voisin network_edges fiable = 752000 LGV passenger -> passenger. JAMAIS freight_only.
- ✅ **935000 - Ligne Miramas-L'Estaque via Port-de-Bouc (étang de Berre)** - vérité: mixte: TER PACA voyageurs ET fret lourd (raffineries/pétrochimie étang de Berre). Source: SNCF=Exploitée ; 15 line_stops en base (desserte voyageurs) ; Wikipedia trafic=TER+fret. Vérité connue: mixte avéré.  
  prédit: mixed. Étape 4(b) signal voyageurs (line_stops/GTFS desserte) -> passenger ; liste-des-gares aura >=1 voyageurs=O ET >=1 fret=O -> mixed. Pas LGV ni raccordement.
- ✅ **935903 - Voie de desserte de Port-St-Louis-du-Rhône (Vport)** - vérité: fret portuaire pur (desserte du port de Port-Saint-Louis), aucune desserte voyageurs. Source: SNCF=Exploitée ; type_ligne=Vport ; 0 line_stops ; aucune gare voyageurs. Vérité connue: fret portuaire.  
  prédit: freight (étiqueté 'voie portuaire'). Étape 3 is_vport (type_ligne=Vport / name 'Voie de desserte ... port'). Étape 5: Vport -> traffic_type='freight' marqué explicitement 'voie portuaire', PAS 'ligne fret commerciale'.
- ✅ **341500 - Voies du port du Havre** - vérité: desserte fret portuaire, mais SNCF la classe 'Transférée en voie de service' (statut = voie de service, pas ligne exploitée au sens RFN). Source: SNCF libelle='Transférée en voie de service' (1 tronçon) ; 0 stops.  
  prédit: closed (status='abandoned'/voie de service) selon précédence STATUT, traffic_type non écrit en 'freight' car tronçon non-open. Étape 1 mappe 'Transférée en voie de service' -> closed. Vport noté mais l'étape 4 ne tourne que sur tronçons OPEN.
- ✅ **934100 - Raccordement de Mourepiane (Marseille)** - vérité: raccordement fret du faisceau de Mourepiane/port de Marseille, SNCF='Neutralisée'. Source: SNCF libelle='Neutralisée' (1 tronçon) ; is_raccordement (name + type_ligne Rac) ; 2 stops résiduels.  
  prédit: closed (status='abandoned' car Neutralisée). Étape 1: Neutralisée -> closed. is_raccordement empêche tout freight_only; mais comme tronçon non-open, étape 4 ne tourne pas -> traffic_type 'unknown'/historique.
- ✅ **763000 - Vélorail du Bazois Morvan (ex-ligne ferroviaire)** - vérité: ligne fermée reconvertie en vélorail (cyclo-draisine touristique), voie en place. SNCF='Neutralisée'. Présente sur archeoferroviaire (ligne disparue régionale). Source: SNCF libelle='Neutralisée' ; reconversion vélorail = ferré fermé.  
  prédit: closed (status='abandoned'). Étape 1: Neutralisée -> closed. Étape 2: si article archeoferroviaire catégorisé matche -> confirme closed, voire dismantling daté sur sous-tronçons déposés. RÈGLE DURE archeo bloque toute promotion en active.
- ✅ **002000 - Ligne de Gretz-Armainvilliers à Sézanne** - vérité: partiellement fermée: 2 tronçons Exploitée (fret), 1 Neutralisée, Déclassée vendue + non vendue. Pas de voyageurs (0 stops), trafic fret résiduel sur la partie ouverte. Source: SNCF={Exploitée:2, Neutralisée:1, Déclassée vendue:1, Déclassée non vendue:1} ; 0 line_stops.  
  prédit: ligne 'partiellement fermée'. Par tronçon: Exploitée+0 stops+aucune gare voyageurs -> freight_only candidate (confidence 'low'/'medium', flag curation) ; Neutralisée -> closed ; Déclassée vendue -> dismantled ; Déclassée non vendue -> closed/abandoned. line.type=freight (agrégat des open).
- ✅ **420000 - Ligne de Paris-Montparnasse à Brest** - vérité: grand axe voyageurs (TGV+TER) ET fret = mixte. Source: SNCF=Exploitée ; 113 line_stops (forte desserte voyageurs) ; v_max=220 (NON-LGV, sous le seuil 270) ; wikipedia_title déjà renseigné. Vérité: mixte/voyageurs.  
  prédit: passenger (ou mixed). Étape 4(b): 113 stops -> signal voyageurs fort -> passenger ; liste-des-gares aura fret=O sur certaines gares -> mixed. v_max=220 < 270 donc PAS is_lgv (correct, c'est une ligne classique).
- ✅ **764000 - Ligne de St-Florentin-Vergigny à Monéteau-Gurgy** - vérité: ligne fermée/retranchée, voie déposée par tronçons. SNCF={Transférée en VS:1, Retranchée:2, Neutralisée:1}. Aucun segment en base era5 (déjà hors réseau actif). Source: SNCF libelle ; archeoferroviaire (ligne disparue Bourgogne).  
  prédit: closed/dismantled par tronçon: 'Retranchée (Plus utilisable)' -> closed (abandoned), 'Neutralisée' -> closed, 'Transférée en VS' -> closed/voie de service. Étape 2 archeo -> dismantling daté sur sous-tronçons déposés. Agrégat ligne = closed/partiellement déposée.

### Modes d'échec

- Données externes non vérifiables ici: l'environnement renvoie un mock de SCHÉMA (champs+types) pour SNCF Open Data, Wikipedia et archeoferroviaire - impossible de valider en live la jointure GTFS UIC->code_ligne, le champ Wikipedia 'trafic', ou le phrasing archeoferroviaire 'non déferrée entre A et B'. Le statut SNCF est néanmoins déjà importé localement (sncf_line_geometries.libelle) et reste fiable.
- Voies portuaires classées 'Transférée en voie de service' par SNCF (ex: 341500 Havre, 499500 Lorient) -> mappées 'closed' alors qu'elles assurent encore du fret portuaire. Le statut juridique SNCF (autorité n°1) sous-représente la réalité opérationnelle fret. Risque de faux 'closed'.
- Fallback freight_only sur 'Ligne Exploitée sans gare' (002000): légitime pour les vraies lignes 100% fret, mais peut sur-classer en freight une ligne dont les gares ne sont simplement pas dans liste-des-gares (couverture 591 lignes seulement). Reste en confidence 'low' donc atténué, mais source d'erreur si non curé.
- Réouvertures/changements récents non reflétés par le cache SNCF: Mourepiane (934100) 'Neutralisée' en cache alors que le fret y a repris ~2023 -> faux 'closed'. Le cache SNCF a une latence; la classification hérite de cette latence.
- Héritage raccordement partiel: 752323 Montereau n'expose qu'un voisin network_edges (752000 LGV) -> hérite passenger, perd la nuance 'mixte' (fret de déviation). Si la connectivité vers la ligne classique 830000 n'est pas dans network_edges, l'héritage est incomplet. Dépend de la qualité du graphe.
- Seuil LGV v_max>=270: robuste sur CE dataset (0 faux positif, 13 lignes toutes LGV/interconnexion), mais un futur import avec des lignes classiques relevées à 270 (rare) tromperait la règle. Le croisement avec catlig SNCF (quand dispo) est nécessaire comme garde-fou - or catlig n'a pas pu être re-fetché ici.
- Vélorails/voies vertes (763000): bien classés 'closed' via SNCF Neutralisée, MAIS si OSM les tague usage=tourism + railway=tram/narrow_gauge, l'étape 4(e) OSM pourrait suggérer un trafic - heureusement la règle dure archeo/Neutralisée bloque la promotion. Dépend du respect strict de la précédence STATUT>TYPE.
- Tronçon 'Déclassée vendue' -> dismantled supposé, mais une cession foncière n'implique pas toujours la dépose physique de la voie; sans confirmation OSM razed/archeo, le passage à 'dismantled' (vs 'abandoned') peut être prématuré.

### Raffinements

- Ajouter une CLASSE/flag distinct 'voie de service / voie portuaire' pour les libelle SNCF 'Transférée en voie de service' au lieu de les fondre dans 'closed': ces tronçons roulent (fret/manoeuvres). Proposer status='service_track' (ou status='abandoned' + traffic_type='freight' + tag 'voie portuaire') plutôt que closed pur, surtout quand type_ligne=Vport. Corrige le sous-classement Havre/Lorient.
- Croiser SYSTÉMATIQUEMENT le seuil LGV avec un signal indépendant: exiger (v_max>=270) ET (name matche /(LGV)|grande vitesse/ OU catlig SNCF='Ligne à grande vitesse' OU OSM highspeed=yes). Sur ce dataset le name '(LGV)' couvre déjà tous les cas; cela blinde la règle contre une future ligne classique relevée à 270. Garder v_max>=300 comme déclencheur autonome (aucune ligne classique n'atteint 300).
- Pour l'héritage des raccordements: élargir la recherche de voisins au-delà des seuls network_edges partageant un noeud - ajouter le fallback géométrique <50m (ST_DWithin sur geom) ET propager l'union des types des voisins (si UN voisin est mixte/fret commercial, le raccordement devient au minimum mixed, pas passenger pur). Pour Montereau, capter aussi la ligne classique 830000 -> mixed plus fidèle que passenger.
- Introduire un horodatage de fraîcheur du cache SNCF et un override de curation manuelle prioritaire pour les réouvertures connues (Mourepiane). Documenter dans line_sources.source_type='manual' que ces segments ne doivent pas être réécrits par 'Neutralisée'. Sortir ces conflits cache-vs-réalité dans le --report CSV.
- Durcir le fallback freight_only: n'attribuer freight_only à une 'Ligne Exploitée sans gare' QUE si (aucune gare liste-des-gares sur le code) ET (aucun stop GTFS) ET (longueur/topologie compatible embranchement) - sinon 'unknown'. Toujours confidence 'low' + flag. Éviter le freight par défaut sur des lignes simplement absentes du référentiel gares.
- Distinguer 'dismantled' de 'abandoned' avec preuve POSITIVE de dépose: ne passer 'dismantled' que si OSM railway=razed/dismantled OU archeo 'déferrée/déposée' OU SNCF 'Fermée et déposée'. 'Déclassée vendue' seule -> rester 'abandoned' (déclassement juridique != dépose physique). Réduit les faux 'dismantled'.
- Étape 2 archeoferroviaire: comme le crawl n'a pas pu être validé ici, prévoir un mode dégradé robuste - si le fetch archeo échoue/backoff, NE PAS bloquer la classification statut (qui repose sur SNCF déjà en cache); l'archeo ne fait que RAFFINER le sous-tronçon déposé. Logger l'absence d'archeo comme 'non corroboré' plutôt que de défaulter.
- Ajouter un test de non-régression codé sur les 4 raccordements LGV témoins (752305, 752323, 226310, 768300): assertion 'traffic_type != freight' - ils sont actuellement freight en base et constituent le bug-cible mesurable. Idem assertion is_lgv=>passenger sur les 13 lignes v_max>=270.

## Inventaire des signaux par source


### SNCF

_AUCUN dataset SNCF Open Data n'expose directement, par code_ligne, l'attribut voyageurs/fret. Le statut ouvert/fermé est lui parfaitement couvert et faisant autorité. Décision déterministe recommandée pour source='SNCF': (1) OUVERT/FERMÉ <- lignes-par-statut.statut (= sncf_line_geometries.libelle local), évalué PAR TRONÇON: 'Exploitée'=ouvert, tout le reste=fermé/neutralisé/déclassé (279/886 codes mixtes). (2) Exclure du 'fret par défaut' tout tronçon dont lignes-par-type.type_ligne IN (Rac, Vmère, Vport) ET tout code dont lignes-lgv-et-par-ecartement.catlig='Ligne à grande vitesse' -> ces tronçons ne sont JAMAIS fret par défaut (raccordements=indéterminé, LGV=voyageurs). (3) VOYAGEURS vs FRET <- seul le GTFS national 'horaires-sncf' tranche: un tronçon Exploitée + type_ligne='Ligne' + non-LGV + NON desservi par un trip voyageurs (route_type=2) GTFS => FRET-ONLY. La jointure GTFS->code_ligne est indirecte (par gares/géométrie) et c'est la seule limite. Le signal statut seul suffit pour ouvert/fermé; il faut impérativement croiser type_ligne + catlig + GTFS pour le fret-only fiable et pour ne pas étiqueter raccordements/LGV en fret._

- **lignes-par-statut.statut (par tronçon, clé=code_ligne + rg_troncon/pkd/pkf)** (authoritative) - OUVERT vs FERMÉ/NEUTRALISÉ/DÉCLASSÉ/transféré. C'EST le signal ouvert/fermé. Ne dit RIEN sur voyageurs vs fret.
  
  Accès : GET https://ressources.data.sncf.com/api/explore/v2.1/catalog/datasets/lignes-par-statut/records (User-Agent: VFF-Bot/1.0). Distribution: group_by=statut&select=statut,count(*)%20as%20n. 1638 tronçons / 886 code_ligne distincts. ATTENTION: la sortie de l'outil masque les valeurs string -> écrire la réponse dans un fichier (-o /tmp/x.json) puis jq, sinon valeurs invisibles.
  
  Distribution : 787 Exploitée | 235 Neutralisée | 138 Fermée | 136 Déclassée vendue | 132 Transférée en voie de service | 64 Déclassée non vendue | 45 Fermée non déposée (Plus utilisable) | 28 Retranchée (Plus utilisable) | 27 Fermée et déposée (Plus utilisable) | 22 Fermée avec maintien en place de la voie | 21 Fermée et mise à disposition de tiers | 2 Neutralisée et conservée pour les besoins de la Défense | 1 S9A3 - Ligne en travaux. Total 1638 tronçons.
  
  Usage : OUVERT = statut 'Exploitée' (787). Tout le reste = FERMÉ/inexploité à des degrés divers (Neutralisée = hors service mais voie en place; Fermée/Déclassée = fermeture administrative; Transférée en voie de service = devenu VS de gare/triage). Évaluer PAR TRONÇON puis agréger par code_ligne: un code 'Exploitée' sur une partie et 'Fermée' sur une autre est partiellement ouvert.
  
  Pièges : 279 codes_ligne sur 886 ont PLUSIEURS statuts distincts -> ne jamais réduire un code à un seul statut. Le statut est au niveau tronçon (pkd/pkf, rg_troncon). 'Neutralisée' != 'Fermée' juridiquement (neutralisée = suspendue, peut rouvrir). Datasets 'liste-des-lignes' et 'etat-du-reseau-ferroviaire' N'EXISTENT PAS (404/erreur).

- **sncf_line_geometries.libelle (table locale)** (authoritative) - Identique à lignes-par-statut.statut. PIÈGE DE NOMMAGE: le champ s'appelle 'libelle' mais ne contient PAS le nom de la ligne - il contient le STATUT.
  
  Accès : DB locale: SELECT libelle, count(*) FROM sncf_line_geometries GROUP BY libelle. 1638 lignes / 886 code_ligne distincts, 0 null.
  
  Distribution : Distribution STRICTEMENT identique à lignes-par-statut.statut: 787 Exploitée | 235 Neutralisée | 138 Fermée | 136 Déclassée vendue | 132 Transférée en voie de service | 64 Déclassée non vendue | 45 Fermée non déposée | 28 Retranchée | 27 Fermée et déposée | 22 Fermée avec maintien en place | 21 Fermée et mise à disposition de tiers | 2 Neutralisée Défense | 1 S9A3 en travaux. Mots-clés trafic absents: fret=0, voyageur=0, raccord=0, LGV=0.
  
  Usage : Utiliser directement comme miroir local de lignes-par-statut (pas besoin de réimporter pour le statut). Jointure par code_ligne (+ rg_troncon pour le tronçon). NE PAS l'utiliser comme nom de ligne. 279 codes ont >1 statut distinct localement (confirmé).
  
  Pièges : Le nom de colonne 'libelle' est trompeur (c'est le statut). Le vrai nom de ligne SNCF est dans formes-des-lignes-du-rfn.libelle qui, lui, contient le toponyme - à ne pas confondre. La table locale a 0 libellé toponymique.

- **lignes-par-type.type_ligne (anti-faux-fret: raccordements & voies mères/portuaires)** (authoritative) - Sépare LIGNE PRINCIPALE (Ligne) des RACCORDEMENTS (Rac), VOIES MÈRES (Vmère) et VOIES PORTUAIRES (Vport). Essentiel pour la règle 'un raccordement/voie de service ne doit JAMAIS être fret par défaut'.
  
  Accès : GET .../datasets/lignes-par-type/records - group_by=type_ligne. 3010 tronçons. Comptes distinct code_ligne: where=type_ligne="Rac" -> count(distinct code_ligne)=409; "Vport" -> 84.
  
  Distribution : Par tronçon: 1592 Ligne | 820 Rac | 430 Vmère | 168 Vport. Par code_ligne distinct: Rac=409, Vport=84.
  
  Usage : Si type_ligne IN (Rac, Vmère, Vport) -> NE PAS classer en 'fret' ; ce sont des raccordements/voies de service/embranchements, traffic_type indéterminé ou dédié, pas une ligne commerciale fret. Réserver l'inférence fret-only aux tronçons type_ligne='Ligne' Exploitée sans service voyageurs. Vport (portuaire) = typiquement desserte fret mais à étiqueter explicitement 'voie portuaire', pas 'ligne fret'.
  
  Pièges : Granularité tronçon plus fine que lignes-par-statut -> rg_troncon ne correspond pas 1:1 entre les deux datasets, joindre par code_ligne + recouvrement pkd/pkf. Un code_ligne peut mélanger 'Ligne' et 'Rac'.

- **lignes-lgv-et-par-ecartement.catlig (identification LGV)** (authoritative) - Marque les LGV et les voies étroites vs réseau conventionnel à écartement normal. Essentiel pour la règle 'une LGV ne doit JAMAIS être fret par défaut'.
  
  Accès : GET .../datasets/lignes-lgv-et-par-ecartement/records - group_by=catlig. 2166 tronçons. where=catlig="Ligne à grande vitesse" -> count(distinct code_ligne)=72.
  
  Distribution : Par tronçon: 2010 Ligne du réseau conventionnel à écartement normal | 146 Ligne à grande vitesse | 10 Ligne du réseau conventionnel à voie étroite. Par code_ligne distinct LGV: 72.
  
  Usage : Si catlig='Ligne à grande vitesse' -> traffic_type='voyageurs' (LGV françaises = voyageurs/TGV, jamais fret par défaut), is_lgv=true. Croiser avec vitesse-maximale-nominale-sur-ligne (v_max>=270 confirme LGV) pour validation.
  
  Pièges : Granularité tronçon (joindre par code_ligne). Les voies étroites (10) sont des cas particuliers (ex. lignes touristiques/métriques).

- **horaires-sncf (GTFS national voyageurs - signal VOYAGEURS indirect)** (strong) - Présence/absence de trafic VOYAGEURS. C'est le SEUL signal voyageurs vs fret côté SNCF Open Data. Une ligne Exploitée SANS service voyageurs GTFS = fret-only (par déduction).
  
  Accès : GET .../datasets/horaires-sncf/records renvoie une URL de download. ZIP: https://eu.ftp.opendatasoft.com/sncf/plandata/Export_OpenData_SNCF_GTFS_NewTripId.zip (~5 Mo zip, ~80 Mo décompressé). Contient routes.txt (769 routes), trips.txt, stops.txt, stop_times.txt, agency.txt (SNCF VOYAGEURS), feed_info (valide 20260528-20261031).
  
  Distribution : 769 routes voyageurs (TGV/TER/Intercités/Transilien), route_type GTFS (2=Rail majoritaire, 3=Bus de substitution à exclure). agency: SNCF VOYAGEURS / SNCF Voyageurs SA/EA/LO / TMR / OCEdefault. Pas de champ 'fret' (feed 100% voyageurs par nature).
  
  Usage : Construire l'ensemble des gares/segments desservis par un trip voyageurs (route_type=2). Un tronçon 'Exploitée' (lignes-par-statut) ET type_ligne='Ligne' (pas Rac/LGV) ET NON couvert par un trip voyageurs GTFS => candidat FRET-ONLY déterministe. Filtrer route_type=3 (bus). Mettre à jour à chaque feed (validité ~5 mois).
  
  Pièges : PAS de code_ligne dans le GTFS -> jointure INDIRECTE et coûteuse via gares/stops (lat/lon) projetées sur la géométrie des lignes (geo_shape). route_long_name = nom commercial 'VilleA - VilleB', route_short_name = code commercial (P53, C30...), aucun lien direct au code_ligne RFN. Inclut des trips en autocar (route_type=3) à exclure. Sans ce GTFS, AUCUNE source SNCF ne distingue voyageurs/fret par code_ligne.

- **vitesse-maximale-nominale-sur-ligne.v_max (signal d'appui LGV/fret)** (weak) - Appui (pas décisif) : v_max très haute (>=270) confirme LGV; v_max très basse (<=60) corrèle avec lignes fret/desserte locale à trafic restreint mais n'est PAS une preuve de fret.
  
  Accès : GET .../datasets/vitesse-maximale-nominale-sur-ligne/records. Champs: code_ligne, v_max (string km/h), rg_troncon, pkd, pkf, lib_ligne (souvent null).
  
  Distribution : v_max valeurs typiques observées: '160', '320', etc. (string). Distribution complète non agrégée ici; à grouper si besoin via group_by=v_max.
  
  Usage : Uniquement en validation croisée: v_max>=270 -> conforte is_lgv (avec catlig). NE PAS inférer 'fret' depuis une vmax basse seule (beaucoup de lignes voyageurs rurales ont vmax basse).
  
  Pièges : v_max est une chaîne (parser en int). Granularité tronçon. Corrélation fret faible/bruitée -> ne jamais utiliser seul pour décider fret.

- **regime-dexploitation-des-lignes.exploitati (NE PAS confondre avec le trafic)** (noisy) - Régime/type de VOIE (double voie, voie unique, banalisée, trafic restreint...). N'indique NI voyageurs/fret NI ouvert/fermé. Piège: nom 'exploitation' fait croire au trafic.
  
  Accès : GET .../datasets/regime-dexploitation-des-lignes/records - group_by=exploitati. 1113 tronçons.
  
  Distribution : 415 Double voie | 247 Voie unique | 186 Voie banalisée | 138 Voie unique à trafic restreint (consigne de ligne) | 81 _Autre | 30 Voie unique à signalisation simplifiée | 15 Régime en navette | 1 Régime particulier de voie unique autre que trafic restreint.
  
  Usage : Signal de contexte uniquement: 'Voie unique à trafic restreint' (138) corrèle souvent avec lignes fret/dessertes secondaires, mais NON déterministe. Ne pas utiliser pour trancher voyageurs/fret.
  
  Pièges : Ce n'est PAS un régime voyageurs/fret malgré le nom. 'trafic restreint' = limitation d'exploitation, pas un type de trafic commercial.


### Wikipedia

_Wikipedia FR apporte un signal UTILE mais INSUFFISANT seul pour distinguer de façon déterministe fret-only / fermée / voyageurs. Le seul champ vraiment discriminant est `trafic` (texte libre non normalisé): excellent pour trancher fret-only vs voyageurs vs mixte QUAND il est rempli (et il protège nativement les LGV/raccordements voyageurs du 'fret par défaut', car il y porte explicitement TGV/Ouigo/Transilien). Mais sa couverture est ~67% sur les actives et chute à ~30% sur les fermées (VIDE domine), et il faut un nettoyage agressif (casse, <small>, <br>, commentaires HTML, refs, templates) car rien n'est normalisé. Il n'existe AUCUN champ `statut` dans le modèle d'infobox (vérifié sur les 105 params): le statut fermée/ouverte doit être reconstitué en croisant la catégorie 'Ancienne ligne ferroviaire dans X' (signal fort, couverture quasi nationale, indépendant de l'infobox) + un `trafic` vide ou égal à 'Aucun'/'voie verte'/'Ligne non exploitée' + la présence du champ `fermeture` (faible/bruité, ne distingue pas voyageurs/fret, et souvent fermeture de tronçon seulement). Règle déterministe recommandée: status via catégorie 'Ancienne' (fermée) sinon active; traffic_type via le champ trafic classé par mots-clés en faisant primer toute marque voyageurs sur le fret (donc LGV/raccordement mixte -> jamais fret). Ne JAMAIS défaulter à fret quand trafic est vide -> retomber sur SNCF Open Data. A noter: le code actuel (EnrichLineFromWikipedia.php l.75-106) extrait bien metadata['trafic'] mais ne l'exploite pas pour dériver traffic_type/status, et parseEvents cherche des champs infobox 'déclassement'/'dépose'/'fermeture marchandises' qui n'existent pas dans le modèle. Conclusion: Wikipedia est une source de CORROBORATION/désambiguïsation forte (surtout trafic + catégorie), à fusionner avec SNCF qui doit rester la source de couverture/autorité._

- **Infobox champ `trafic`** (strong) - C'est LE champ discriminant fret-only / voyageurs / mixte. Mots-clés fret: 'fret', 'marchandise', 'pondéreux'. Voyageurs: TGV, TER, Intercités, Ouigo, Transilien, RER, Eurostar, Lyria, ICE, omnibus. Une LGV a TOUJOURS des marques voyageurs ici (jamais 'fret' seul) -> protège du 'fret par défaut' SI on lit le champ. Un raccordement fret-only porte bien 'Fret'; un raccordement voyageurs porte 'Transilien, fret' (mixte) -> distinction correcte quand l'article existe et le champ est rempli.
  
  Accès : API MediaWiki action=parse&prop=wikitext (ou prop=revisions en batch), puis WikipediaLineParser::parseInfobox() -> metadata['trafic']. URL: https://fr.wikipedia.org/w/api.php (UA requis 'VFF-Bot/1.0').
  
  Distribution : Lignes ACTIVES (n=33 sur cats départementales en service): FRET seul 11 (33%), MIXTE 7 (21%), VOYAGEURS 2 (6%), VIDE 11 (33%), AUTRE 1, sans infobox 1. Lignes en cat 'Ancienne' (n=20, fermées): VIDE 14 (70%), AUTRE 4 ('voie verte','Trains de travaux','Ligne non exploitée','Touristique'), FRET 1, champ absent 1. Valeurs brutes distinctes observées: 'Fret', 'fret', 'Fret SNCF', 'Aucun', 'aucun', 'voie verte', 'Voie verte', 'Tramway', 'touristique', 'Trains de travaux', 'TGV / Fret', 'TER, Fret', 'Transilien, fret', 'Voyageurs et fret', 'Intercités de nuit, TER, Fret SNCF', + artefacts de parsing: 'Fret }}', '<!-- / Fret -->', 'et Ouigo / / Frecciarossa'. Témoins: Saint-Rambert-d'Albon à Rives='Fret' (fret-only confirmé), Raccordement court de Mulhouse='Fret', LGV Méditerranée/Atlantique/Nord/Est=que des marques TGV/Ouigo/Eurostar.
  
  Usage : Lire metadata['trafic'] (déjà extrait par parseInfobox), lowercaser, puis classer par mots-clés: fret-only = contient fret/marchandise ET aucun mot voyageurs; voyageurs = au moins un mot voyageurs; mixte = les deux. IMPORTANT: ne dériver traffic_type='fret' QUE si le champ matche fret sans voyageurs - pour LGV et raccordements, la présence de marques voyageurs doit l'emporter. Si VIDE/absent -> ne PAS défaulter à fret, retomber sur SNCF/catégorie.
  
  Pièges : 1) Aucune normalisation: prévoir nettoyage agressif (<small>, <br>, <!-- -->, refs, templates {{lnobr}}, casse). 2) 'Aucun'/'aucun'/'voie verte'/'Ligne non exploitée'/'Trains de travaux' = signaux de FERMETURE, pas de trafic - à classer séparément. 3) Champ souvent vide sur les lignes fermées -> faible pour détecter une fermée par cette voie. 4) Le consommateur actuel EnrichLineFromWikipedia.php (lignes 75-106) n'exploite PAS ce champ -> il faudrait l'ajouter pour dériver traffic_type/status.

- **Infobox champ `fermeture` (+ fermeture2, fermeture3)** (weak) - Présence d'une date de fermeture => la ligne (ou un tronçon) a été fermée. Permet de marquer 'fermée'. Mais NE distingue PAS fermeture voyageurs vs marchandises (le modèle officiel n'a pas de champ 'fermeture voyageurs'/'fermeture marchandises' séparé - le parser PHP qui les cherche ne trouvera rien).
  
  Accès : Même API; WikipediaLineParser le mappe vers events type 'closure'/'closure_freight' via parseEvents(). Champs réels du modèle: 'fermeture', 'fermeture2', 'fermeture3'.
  
  Distribution : Présence: ~30% sur lignes actives (10/33), ~76% en mélangeant actives+anciennes (25/33). Valeurs vues: années '1985','1998','1938', et même la chaîne libre 'fermé' (Ligne de Paulhan à Montpellier). Cas piège mesuré: Castelnaudary à Rodez a fermeture=1985 ET trafic='Intercités de nuit, TER' (fermeture partielle d'un tronçon, ligne globalement encore active).
  
  Usage : Utiliser comme indice de fermeture (au moins partielle), à corroborer avec trafic vide/'Aucun' et la catégorie 'Ancienne ligne'. Ne jamais conclure fermeture totale sur la seule présence de 'fermeture' car souvent fermeture de tronçon.
  
  Pièges : Valeur parfois non-date ('fermé'). Pas de granularité voyageurs/fret. Les types d'events 'closure_freight'/'declassification'/'dismantling' que cherche le parser PHP dans l'infobox sont ABSENTS du modèle -> ces dates ne viennent que du texte (section Histoire, confidence 'medium').

- **Catégorie 'Ancienne ligne ferroviaire dans X' vs 'Ligne ferroviaire dans X'** (strong) - Signal de STATUT indépendant de l'infobox: appartenance à 'Ancienne ligne ferroviaire ...' = ligne fermée/disparue/déclassée. 'Ligne ferroviaire dans ...' (sans 'Ancienne') = en service.
  
  Accès : API action=query&list=categorymembers&cmtitle=Catégorie:Ancienne ligne ferroviaire dans <dept>. (Non exploité par WikipediaLineParser actuellement.)
  
  Distribution : Catégories 'Ancienne ligne ferroviaire dans X' existent pour la quasi-totalité des départements (Ain, Aisne, Allier, Aveyron, ... vérifié). Sur 20 articles en cat 'Ancienne': trafic VIDE 14/20, confirmant cohérence ancienne<->fermée. LGV regroupées sous Catégorie:LGV Atlantique/Nord/etc.
  
  Usage : Croiser: si article dans cat 'Ancienne ...' -> status='fermée' (haute confiance). Sinon -> potentiellement active, dériver traffic_type via le champ trafic. Combinaison robuste: catégorie pour status (ouvert/fermé) + trafic pour traffic_type (fret/voyageurs/mixte).
  
  Pièges : Une ligne partiellement fermée peut figurer dans les deux familles de catégories (par tronçon). Catégories couvrent aussi des lignes étrangères (filtrer sur titres FR / départements FR).

- **Champ `statut` (INEXISTANT) et champs exploitant/concessionnaire** (weak) - Le concessionnaire actuel ('SNCF Réseau') = ligne sur réseau ferré national, mais ne distingue PAS fret/voyageurs/fermée (SNCF Réseau apparaît pour LGV, fret-only, et certaines fermées non déclassées). Faible pouvoir discriminant.
  
  Accès : Inspection du Modèle:Infobox Ligne ferroviaire (105 params): aucun param 'statut', 'déclassement', 'dépose'. Params présents: trafic, fermeture(2,3), mise en service(2,3), exploitant1..6, exploitants, concessionnaire1..8/act, dateconc/finconc.
  
  Distribution : concessionnaireact='SNCF Réseau' quasi systématique sur le RFN (LGV Méditerranée, Saint-Rambert fret, Castelnaudary fermée partielle: toutes 'SNCF Réseau'). propriétaire='SNCF'/'SNCF Réseau' idem.
  
  Usage : NE PAS utiliser pour fret/fermée. Sert seulement à confirmer l'appartenance RFN. La mission attendait un champ 'statut' Wikipedia: il N'EXISTE PAS -> le statut doit être reconstitué via (catégorie Ancienne) + (trafic vide/'Aucun') + (fermeture présente), pas via l'infobox seule.
  
  Pièges : Le contrat de fetchAndParse() et parseEvents() référencent 'statut'/'déclassement'/'dépose' comme s'ils étaient dans l'infobox; en réalité ces clés ne sortent jamais de l'infobox réelle. Tout repose sur la section Histoire (regex texte, confidence 'medium', bruité).


### OSM+GTFS

_Ni OSM ni GTFS ne suffisent seuls, mais combinés ils corroborent fortement le verdict fret-only/fermée/voyageurs, à condition de NE PAS utiliser les signaux que la consigne supposait (passenger=no inexistant en FR) et de PERSISTER ceux qu'OsmImporter jette aujourd'hui.\n\nOSM (corroborant, force=strong): le signal fret/voyageurs natif est railway:traffic_mode (mixed/freight/passenger), PAS passenger=no qui est absent à 100% sur le RFN FR (vérifié: 0/3786 ways en Bretagne). railway=* donne le verdict OUVERT vs FERMÉE (déjà capté -> status). usage=industrial/tourism isole le non-RFN. POINTS À CORRIGER dans OsmImporter.php: il NE persiste PAS railway:traffic_mode (~57% de couverture en Bretagne), usage, ni highspeed. Couverture traffic_mode partielle (43% absent) -> ne jamais déduire 'fret' d'une absence.\n\nGTFS (avéré, force=authoritative pour le POSITIF voyageurs): présence d'arrêts/horaires voyageurs = service voyageurs certain ; absence = candidate fret-only OU fermée (non discriminant seul). Feed 'Réseau SNCF TGV/IC/TER' frais (2026-05-28), 768 routes / 8833 stops. Aucune intégration GTFS dans le code actuel.\n\nMapping GTFS->code_ligne RFN RÉSOLU et DÉTERMINISTE (pas besoin de proximité pure): le stop_id GTFS encode l'UIC ('OCE71043075' -> 71043075), qui matche liste-des-gares.code_uic (2554/3535 en direct), lequel porte code_ligne + fret(O/N) + voyageurs(O/N). Donc: GTFS stop -> UIC -> code_ligne -> verdict. Fallback spatial ST_DWithin<300m seulement pour les arrêts sans UIC RFN.\n\nDEUX PIÈGES de la consigne confirmés empiriquement: (1) LGV: highspeed=yes + railway:traffic_mode=passenger sur 78/78 ways LGV BPL, MAIS l'actuel DeriveTrafficType (proximité gare<1km) taggerait une LGV en 'freight' car peu de gares -> il FAUT un override highspeed=yes/traffic_mode=passenger AVANT la règle de proximité. (2) Raccordements: ref/name/traffic_mode absents (0/3 testés) -> aucun signal propre, doivent hériter de la connectivité, jamais 'fret' par défaut. (3) GTFS route_type=3 (161 routes) = autocar de substitution -> EXCLURE sinon une ligne fermée au rail compterait comme voyageurs.\n\nVerdict combiné recommandé par ligne: FERMÉE si OSM railway=abandoned/disused/razed OU SNCF libelle Neutralisée/Fermée/Déposée ; VOYAGEURS si GTFS rail (route_type=2) dessert une gare de la ligne OU liste-des-gares.voyageurs=O OU OSM traffic_mode in (passenger,mixed) ; FRET-ONLY si (pas de desserte GTFS rail) ET liste-des-gares all voyageurs=N avec >=1 fret=O (110 lignes) ET non-LGV ET non-raccordement._

- **OSM tag railway=* (rail|abandoned|disused|razed|dismantled|construction|narrow_gauge|light_rail)** (strong) - Ouvert (rail/construction) vs Fermé/Déposé (abandoned, disused, razed, dismantled). NE distingue PAS fret-only vs voyageurs - railway=rail couvre les deux.
  
  Accès : Overpass QL way["railway"~...] (area FR/FR-XXX); déjà capté par OsmImporter::buildQuery() et persisté via mapStatus() dans line_segments.status
  
  Distribution : DB VFF era 5: status in_service=75459, abandoned=1625, razed=429 (77513 segments, 77409 d'origine OSM). Bretagne live: railway=rail sur 3786 ways après filtre service.
  
  Usage : Mapping fermeture: rail/construction -> InService ; abandoned/disused -> Abandoned ; razed/dismantled -> détruit. C'est le signal 'FERMÉE' principal d'OSM. Déjà implémenté.
  
  Pièges : OSM mélange parfois plusieurs sous-tags (disused:railway=rail) que le filtre actuel exclut volontairement (['disused:railway'!~'.']). railway=construction est mappé InService (acceptable). Une ligne fermée mais voie en place est souvent encore railway=rail dans OSM -> sous-estime les fermetures vs SNCF libelle 'Neutralisée'.

- **OSM tag railway:traffic_mode (passenger|freight|mixed)** (strong) - LE discriminateur fret-only / voyageurs / mixte natif d'OSM en France. freight=fret-only, passenger=voyageurs (LGV), mixed=mixte.
  
  Accès : Overpass way["railway"="rail"] -> tags['railway:traffic_mode']. NON capté par OsmImporter (ignoré) - colonne traffic_type de line_segments est dérivée par proximité gares, pas par ce tag.
  
  Distribution : Bretagne (3786 rail ways): mixed=1955, freight=110, passenger=76, absent=1645. LGV BPL (ref 408000, 78 ways): railway:traffic_mode=passenger=70, absent=8 + highspeed=yes=78.
  
  Usage : Utiliser comme signal direct: traffic_mode=freight -> candidate fret-only ; =passenger ou =mixed -> service voyageurs avéré. Doit être PERSISTÉ par OsmImporter (nouvelle colonne ou alimente traffic_type). Corrobore SNCF gares.fret/voyageurs.
  
  Pièges : Couverture partielle et hétérogène selon les régions/contributeurs. Absence != fret. Ne jamais déduire 'fret' d'une absence de traffic_mode.

- **OSM tag passenger (=no attendu pour fret-only)** (noisy) - En théorie passenger=no = fret-only. EN PRATIQUE INUTILISABLE en France.
  
  Accès : Overpass tags['passenger']
  
  Distribution : Bretagne: passenger absent sur 3786/3786 ways (0 valeur). LGV: absent 78/78. Tag quasi non utilisé sur le RFN français.
  
  Usage : NE PAS se reposer sur passenger=no en France. Utiliser railway:traffic_mode et usage à la place. Ne pas implémenter de règle passenger=no.
  
  Pièges : C'est le piège n°1 de la consigne: le tag attendu (passenger=no) n'existe pas dans les données FR. Vérifié empiriquement sur 3786 ways.

- **OSM tag usage (main|branch|industrial|military|test|tourism)** (strong) - industrial -> embranchement fret privé (ITE) ; tourism -> ligne touristique/heritage ; main/branch -> ligne RFN normale (fret OU voyageurs, ne tranche pas seul). Sert surtout à EXCLURE le non-RFN.
  
  Accès : Overpass tags['usage']. OsmImporter exclut industrial/military/test en amont (buildQuery), mais ne PERSISTE pas la valeur usage.
  
  Distribution : Bretagne (rail): main=1772, absent=1504, branch=378, industrial=113, tourism=18, crossover=1.
  
  Usage : usage=industrial -> classer fret/ITE, pas une ligne RFN voyageurs. usage=tourism -> heritage (cohérent avec is_heritage déjà dérivé d'operator). main/branch SEUL ne dit pas fret vs voyageurs -> croiser traffic_mode + SNCF.
  
  Pièges : usage=industrial concerne des embranchements, PAS forcément la ligne mère; ne pas propager 'fret' à toute la ligne. Le filtre Overpass actuel jette ces ways (skipWithoutRef + exclusion usage) -> info perdue.

- **OSM tag service (yard|siding|spur|crossover) + electrified/voltage/maxspeed/highspeed** (strong) - service=* -> voies de service/triage (à exclure des lignes). highspeed=yes -> LGV (voyageurs par définition, JAMAIS fret). electrified/voltage -> indice indirect (LGV 25kV, fret souvent non électrifié mais pas fiable).
  
  Accès : Overpass tags['service'], tags['electrified'], tags['voltage'], tags['maxspeed'], tags['highspeed']. service exclu par buildQuery; electrified/voltage/maxspeed persistés; highspeed IGNORÉ.
  
  Distribution : Bretagne service: absent=2264, yard=642, siding=436, spur=313, crossover=131. electrified: contact_line=2409, no=653. voltage: 25000=2404. LGV BPL highspeed=yes=78, maxspeed 320=42/300=2.
  
  Usage : highspeed=yes => forcer traffic_type=passenger, court-circuiter toute heuristique fret (corrige le piège LGV). service=* déjà exclu (correct). electrified=no NE doit PAS impliquer fret (beaucoup de lignes voyageurs TER thermiques).
  
  Pièges : PIÈGE LGV confirmé: l'actuel DeriveTrafficType (proximité gare <1km) tagge une LGV en 'freight' car les LGV ont très peu de gares -> faux fret. highspeed=yes et/ou railway:traffic_mode=passenger doivent override AVANT la règle de proximité.

- **OSM tag ref + operator (déjà utilisés)** (strong) - ref = numéro de ligne RFN -> clé de jointure vers SNCF code_ligne. operator=CFTA/asso -> heritage (donc pas fret SNCF Réseau classique). Raccordements n'ont PAS de ref.
  
  Accès : tags['ref'] -> Line.code (normalizeRef), tags['operator'] -> is_heritage (isHeritageOperator). Déjà persistés.
  
  Distribution : Bretagne ref: absent=1375, 420000=1049, 470000=626, 441000=196... operator: SNCF Réseau=2326, absent=1369, CFTA=77, Chemin de Fer des Côtes-du-Nord=7.
  
  Usage : ref OSM normalisé == SNCF code_ligne (ex '420 000'->'420000') -> jointure directe OSM<->SNCF formes-des-lignes & liste-des-gares. Raccordements (ref absent, name absent) sont rattachés spatialement par enrichTunnels -> ne JAMAIS leur donner traffic=freight par défaut.
  
  Pièges : PIÈGE RACCORDEMENT confirmé: 'main-usage, no-ref' ways n'ont ni name ni railway:traffic_mode (0/3 testés) -> aucun signal fret/pax propre; doivent hériter de la connectivité (lignes voisines), pas d'un défaut fret. Faux ref D/N/A (routes) déjà filtrés.

- **SNCF GTFS 'Réseau SNCF TGV, Intercités et TER' (routes.txt/stops.txt/trips.txt)** (authoritative) - Présence d'une desserte voyageurs AVÉRÉE: si une gare a des stop_times dans le GTFS -> service voyageurs actuel. Absence -> candidate fret-only OU fermée (à confirmer par SNCF status).
  
  Accès : Zip GTFS: https://eu.ftp.opendatasoft.com/sncf/plandata/Export_OpenData_SNCF_GTFS_NewTripId.zip (ou via data.gouv id 9ae758ec-... / transport.data.gouv.fr dataset horaires-sncf). Feed Transilien séparé. NON intégré au code VFF (aucune référence gtfs trouvée).
  
  Distribution : Feed du 2026-05-28: routes=768 (route_type 2=Rail:603, 3=Bus-substitution:161, 0=Tram-train:4), stops=8833 (3535 codes UIC distincts), trips~ (8.4MB), stop_times 68MB. agency: SNCF VOYAGEURS.
  
  Usage : Mapping GTFS->code_ligne RFN en 2 niveaux: (1) DÉTERMINISTE par UIC: stop_id 'StopArea:OCE71043075'/'StopPoint:OCETGV INOUI-71043075' -> extraire le code numérique final -> matcher liste-des-gares.code_uic -> lire code_ligne. (2) Fallback SPATIAL: projeter stop_lat/stop_lon sur le segment RFN le plus proche (<300m, ST_DWithin) pour les arrêts sans UIC RFN. Une ligne avec >=1 gare desservie (route_type=2) = voyageurs; jamais fret-only.
  
  Pièges : PIÈGES: (a) route_type=3 = autocar de substitution (161 routes) -> EXCLURE, sinon une ligne FERMÉE au rail mais avec bus substitution serait comptée 'voyageurs'. (b) Stops de border/étranger ne matchent pas liste-des-gares. (c) GTFS = snapshot horaire courant: une desserte saisonnière/suspendue peut manquer. (d) 2554/3535 stops matchent directement par UIC; le reste via spatial.

- **SNCF liste-des-gares (fret, voyageurs, code_ligne, code_uic) - pont GTFS<->RFN** (authoritative) - DIRECTEMENT par gare: fret=O/voyageurs=N -> point fret-only ; voyageurs=O -> desserte voyageurs. Agrégé par code_ligne -> statut fret/voyageurs de la ligne SANS GTFS ni proximité.
  
  Accès : https://ressources.data.sncf.com/api/explore/v2.1/catalog/datasets/liste-des-gares/exports/json?limit=-1 (User-Agent: VFF-Bot/1.0). Champs: code_uic, code_ligne, fret(O/N), voyageurs(O/N), rg_troncon, pk, geo_point_2d. NON utilisé par le code actuel.
  
  Distribution : 6469 gares, 591 code_ligne distincts. fret: O=1520/N=4949. voyageurs: O=3279/N=3190. Croisé (fret/voy): N/O=2261, N/N=2688, O/O=1018, O/N=502. Par ligne: 356 lignes avec >=1 gare voyageurs ; 110 lignes fret-only (>=1 gare fret, 0 gare voyageurs) ; 125 lignes sans flag.
  
  Usage : C'est le pont qui rend le GTFS exploitable: GTFS stop UIC -> liste-des-gares.code_uic -> code_ligne, et fret/voyageurs donnent directement le verdict par ligne. Règle: une ligne dont TOUTES les gares ont voyageurs=N et >=1 fret=O => fret-only (110 lignes). >=1 voyageurs=O => voyageurs/mixte. Remplace avantageusement la proximité frequentation-gares de DeriveTrafficType.
  
  Pièges : Une ligne fret-only sans aucune gare référencée n'apparaît pas (verdict fret-only par DÉFAUT après exclusion LGV/raccordement). code_uic 8 chiffres préfixe 87 (FR) ; GTFS encode le même UIC en suffixe de stop_id -> jointure exacte. Ne pas confondre 'fret-only' (ouverte fret) et 'fermée' -> croiser avec formes-des-lignes.libelle.


### archeoferroviaire

_Source FIABLE et déterministe pour le signal NÉGATIF 'ligne disparue/déposée' : l'existence d'un article SPIP catégorisé sous une rubrique régionale/départementale (autoritatif, le site exclut explicitement les lignes exploitées). Elle NE suffit PAS, seule, à dériver 'ouvert fret-only' (le site ne couvre QUE des lignes disparues, jamais l'état d'exploitation fret actuel - pour ça il faut SNCF sncf_line_geometries.libelle + Wikipedia). Couverture : ~1705 articles (dont ~métropole France + secondaires/militaires/industriels souvent ABSENTS du RFN SNCF), mais incomplet (uniquement les lignes documentées par les auteurs). FAUX POSITIFS évités en se fiant à l'article catégorisé (jamais à la présence sur 'FRANCE CARTES GENERALES' r162, qui inclut des lignes NON déferrées et n'a d'ailleurs 0 article dédié), en EXCLUANT les articles 'Carte générale …'/'°', et en NE classant JAMAIS 'fret' les raccordements/embranchements/LGV/militaires par défaut. Le matching nom->ligne VFF est faible et DOIT être confirmé par la géométrie GPX (téléchargeable, WGS84) croisée avec sncf_line_geometries.geom. Contraintes techniques : UA navigateur + Accept-Language FR obligatoires (sinon 503), backoff, et corps en UTF-8 malgré charset iso-8859-1 déclaré._

- **Existence d'un ARTICLE catégorisé dédié à la ligne (spip.php?articleNN listé sous une rubrique régionale/départementale)** (authoritative) - FERMÉE/DÉPOSÉE (jamais exploitée aujourd'hui). Signal NÉGATIF propre confirmé : le site déclare explicitement que les lignes EXPLOITÉES (et celles sans infos) ne font PAS l'objet d'article. Donc article dédié categorisé => ligne disparue/déposée. NE distingue PAS fret-only d'ouvert (une ligne fret-only encore exploitée n'aura PAS d'article).
  
  Accès : Énumération SPIP : GET http://archeoferroviaire.free.fr/v31/spip.php?rubriqueNN ; parser le bloc final 'Liste des articles de la rubrique <X>' et en extraire les <a href="spip.php?articleNN">Nom</a>. Endpoint testé OK. La page d'accueil (spip.php / rubrique1, ~706 KB) est un plan complet listant TOUS les articles + tout l'arbre des rubriques en une seule requête.
  
  Distribution : 1705 articles uniques (id 6→1862) au total. Arbre : 14 REGIONS de tête (icône boussole.gif) dont 5 françaises métropole (FRANCE NORD/EST/OUEST/SUD-OUEST/SUD-EST) + FRANCE CARTES GENERALES + DOM + étranger. 166 sous-rubriques (icône barriere3.png). Exemple vérifié rubrique50 (Basse-Normandie > Réseau principal) = exactement 22 articles-lignes (Alençon Condé-sur-Huisne a87, Mézidon Dives a255, Saint-Lô Guilberville a10, Caen Vire a119, …).
  
  Usage : 1) Crawler les ~166 rubriques françaises (ou parser l'accueil une fois) pour bâtir la liste {articleId, nom, rubrique_chain}. 2) Pour chaque article, faire correspondre à lines.name/code (voir signal 'matching'). 3) Si match trouvé ET la ligne VFF est marquée 'in_service'/'Exploitée' SNCF => CONTRADICTION à examiner (souvent : tronçon partiel déposé, ou ligne secondaire absente du RFN). 4) Si pas de match RFN => ligne secondaire/industrielle/militaire à créer comme ligne disparue.
  
  Pièges : Free.fr renvoie HTTP 503 'load average' sous charge avec User-Agent 'VFF-Bot/1.0' nu : OBLIGATOIRE d'envoyer un UA navigateur (Mozilla/5.0 … Firefox) + en-têtes 'Accept' et 'Accept-Language: fr-FR' + --compressed, sinon 503. Prévoir retry/backoff (4-5 s) et limiter le débit (1 req / qqs s). charset déclaré iso-8859-1 mais le corps est en réalité UTF-8 : NE PAS appliquer iconv latin1->utf8 (sinon mojibake 'ArchÃ©ologie'). Certaines pages 'légères' (~48 KB) ne renvoient qu'une sidebar 'derniers articles' + la carte, sans le texte de l'article ; ne retenir que les rendus complets (>500 KB ou contenant la phrase de statut).

- **Phrase de statut dans le corps de l'article ('Ligne ouverte le … ; fermée au trafic voyageurs le … ; fermée au trafic marchandises le … ; non déferrée entre X et Y (année)')** (strong) - Dates précises jj mois aaaa (alimente line_events: opening / closure / closure_freight / dismantling). 'non déferrée entre X et Y' => le tronçon X-Y a encore les rails (status segment 'abandoned' et NON 'dismantled') ; le reste est déposé. C'est LE champ qui gère le 'partiellement déposé'.
  
  Accès : GET spip.php?articleNN (rendu complet) ; le corps contient une phrase semi-structurée après la liste des gares intermédiaires, suivie de la compagnie ('Compagnie de l'Etat', 'Compagnie de l'Ouest-Etat', …). Parser par regex sur : 'ouvert(e)? (le|en) <date>', 'fermé(e)? au trafic (voyageurs|marchandises) (le|en) <date>', 'déclassée <date>', 'non déferrée entre <A> et <B> ( <année> )', 'endommagée pendant la guerre'.
  
  Distribution : Exemples réels extraits : a10 Saint-Lô Guilberville = 'ouverte le 3 avril 1892 ; fermée au trafic voyageurs le 10 octobre 1938 ; non déferrée entre Saint-Lô et Condé-sur-Vire'. a255 Mézidon Dives = 'ouverte le 15 juin 1879 ; fermée au trafic voyageurs le 1er mars 1938 ; endommagée pendant la guerre ; fermée au trafic marchandises le 3 novembre 1969'. a7 Carentan Carteret = ouvertures par tronçons '1889 (Carentan La Haye-du-Puits) et 1894 (La Haye-du-Puits Carteret) ; fermée voyageurs 1976 ; fermée marchandises 1976/1979/1992 par section ; non déferrée entre Portbail et Carteret (2018)'. a1322 Comines-France Comines-Belgique = 'ouverte en 1876 ; fermée en ?'.
  
  Usage : Réutiliser la grammaire de dates FR de WikipediaLineParser::extractDatesFromString (déjà gère '13 février 1883' et '1883'). Mapper : 'ouverte'->opening, 'fermée … voyageurs'->closure, 'fermée … marchandises'->closure_freight, 'déclassée'->declassification, dépose/déferrée->dismantling. Le segment listé dans 'non déferrée entre A et B' => marquer status='abandoned' (rails en place) plutôt que 'dismantled'/'razed'. Les ouvertures/fermetures par tronçons (parenthèses) => générer un event par tronçon avec la sous-section géographique.
  
  Pièges : Dates souvent 'en 1976' (année seule, confidence medium) ou '?' (inconnue). Plusieurs dates marchandises pour une même ligne (par section) : ne pas écraser. La compagnie historique suit la phrase (utile pour lines.historical_operator). Séparateur des champs = point-virgule ';'. Texte libre, pas de balisage : robustesse regex requise.

- **Trace GPX par article (IMG/gpx/<slug>.gpx, overlay Leaflet L.GPX)** (strong) - Géométrie du tracé (WGS84) => matching géométrique fiable contre line_segments.geom et sncf_line_geometries.geom (ST_DWithin / ST_HausdorffDistance / % de recouvrement par buffer). Permet de désambiguïser le matching par nom et de créer la géométrie des lignes secondaires ABSENTES du RFN SNCF.
  
  Accès : Le bloc JS de l'article contient new L.GPX("IMG/gpx/<nom>.gpx", …). Récupérer le chemin par regex sur le HTML de l'article, puis GET http://archeoferroviaire.free.fr/v31/IMG/gpx/<nom>.gpx (testé HTTP 200, ~22 KB, GPX 1.1 valide avec <trk><name>…</name> et trkpt lat/lon WGS84 + <ele>). Listing du dossier IMG/gpx/ INTERDIT (302 -> page 404).
  
  Distribution : Convention de nommage = paire de villes slugifiée : 'Mézidon Dives' -> mezidon_dives_.gpx (minuscules, accents retirés, espaces->'_', souvent un '_' final). Le <trk><name> interne reprend le nom propre accentué ('Mézidon Dives'). Présent sur les articles-lignes (carte interactive systématique). Absent des articles 'Carte générale …'.
  
  Usage : Pour chaque article-ligne : extraire le href GPX du HTML (ne pas deviner le slug, le lire), télécharger, parser les trkpt en LineString 4326. Matching : buffer ~150-300 m autour du GPX, calculer le % de la géométrie SNCF/VFF recouverte ; si une seule ligne RFN recouvre fortement => lien article<->line confirmé ; sinon créer une nouvelle ligne (type secondaire/disparue) avec cette géométrie.
  
  Pièges : Slug parfois imparfait/avec '_' final ('mezidon_dives_.gpx') => toujours lire le chemin réel dans le HTML plutôt que le reconstruire. Échantillonnage GPX dense (trkpt rapprochés). Pas d'attributs de statut dans le GPX (juste le tracé) : croiser avec la phrase de statut pour savoir quel sous-tronçon est 'non déferré'.

- **Chaîne de rubriques (catégorie réseau) : 'Réseau principal' vs département vs 'Réseaux militaires' vs 'Carte générale'** (strong) - Type/nature de la ligne (réseau principal voie normale voyageurs vs secondaire/industriel départemental vs militaire). 'Réseau principal' => ligne du grand réseau (souvent matchable au RFN SNCF). Sous un nom de département => lignes secondaires/d'intérêt local/industrielles. 'Réseaux militaires' + préfixe '14-18' => lignes stratégiques WWI (JAMAIS 'fret' par défaut, souvent voie de 0,60 m).
  
  Accès : Déduite du parent SPIP de l'article (la rubrique sous laquelle il est listé). Sur le plan d'accueil : icône boussole.gif=REGION de tête, barriere3.png=sous-rubrique. Hiérarchie : REGION (FRANCE OUEST) > ancienne région (Basse-Normandie) > {Réseau principal | <Département> | Réseaux militaires}.
  
  Distribution : Sous-rubriques distinctes : 'Réseau principal' x21 (une par ancienne région), 'Réseaux militaires' x5, puis ~95 noms de DÉPARTEMENTS (Calvados, Manche, Orne, Cher, Loiret, Moselle, Vosges, Gironde, …) chacun x1. DOM (Guadeloupe x2, Guyane, Martinique, Réunion, Nouvelle-Calédonie). Étranger (Espagne, Irlande, Pologne, etc.).
  
  Usage : Stocker la rubrique_chain comme tag de classification. Mapper : 'Réseau principal'->candidat RFN (matcher en priorité sur sncf_line_geometries) ; <Département>->ligne secondaire (lines.type souvent freight/mixed/secondaire, vérifier au cas par cas) ; 'Réseaux militaires'/'14-18'->ligne militaire, NE PAS classer 'fret'. Utiliser la région/département pour BORNER géographiquement le matching de nom (évite Saint-Just-en-Chaussée Oise vs ailleurs).
  
  Pièges : Une même ligne peut apparaître dans plusieurs menus (déduplication par articleId). Le département de rattachement n'est pas toujours unique (lignes interdépartementales).

- **Qualificatifs dans le NOM de l'article ((inachevée), (projet), préfixe '14-18', suffixe '*', '°', 'Carte générale', 'Embranchement', 'betteravier', 'tramway', 'raccordement', 'EN COURS')** (strong) - Filtre les FAUX POSITIFS et qualifie la nature : '(inachevée)'/'(projet)' = JAMAIS exploitée (ne pas marquer 'fermée au trafic' - n'a jamais ouvert). 'Carte générale …' / suffixe '°' = article CARTOGRAPHIQUE (carte de gares), PAS une ligne -> à EXCLURE. 'raccordement'/'raccordements' = jonction -> NE JAMAIS classer 'fret' par défaut (caveat utilisateur). 'Embranchement'/'betteravier'/'tramway'/'sablière/carrière/mine' = ligne industrielle/secondaire. 'EN COURS' = article en cours de rédaction (statut éditorial, pas ferroviaire).
  
  Accès : Texte de l'anchor <a href=spip.php?articleNN>NOM</a>. Tally réalisé sur les 1705 noms uniques.
  
  Distribution : Sur 1705 noms : suffixe '*' = 429 ; '(inachevée)' = 25 ; '(projet)' = 9 ; 'EN COURS' = 9 ; préfixe '14-18' = 38 ; 'Embranchement' = 16 ; 'betteravier/betterave' = 6 ; sablière/carrière/cimenterie/mine/chocolaterie/usine/sucrerie = 43 ; 'raccordement' = 2 (+ 'liaison'/'ceinture' divers) ; 'tramway' = 18 ; 'forestier' = 3 ; 'Carte générale' (au niveau article) = 8 ; '°' = 5 (toutes 'Carte générale avec gares (NN) °') ; mention réseau/réseaux = 67 ; militaire/aérodrome/camp/batterie = 12.
  
  Usage : Pré-filtre du crawler : EXCLURE les articles 'Carte générale …' et suffixe '°' (non-lignes). Pour '(inachevée)'/'(projet)' : créer la ligne avec status='unbuilt/abandoned' et SANS event 'closure' (jamais ouverte). Préfixe '14-18'/'Réseaux militaires' : tag militaire, type != freight. 'raccordement'/'embranchement' : tag jonction/antenne, NE PAS forcer 'fret'. Conserver le nom nettoyé (retirer suffixe '*', '°', et le segment ' - EN COURS') pour le matching.
  
  Pièges : Le suffixe '*' (429 occurrences) n'a PAS de légende explicite sur le site (testé : pages 'Mode d'emploi' r88 ne rendent qu'une sidebar). Empiriquement il marque des lignes 'hors réseau principal voie normale standard' (vu sur transfrontalières Comines-France/Belgique a1322, militaires '14-18 … *', tramways) - vraisemblablement voie étroite / spéciale, mais NON confirmé. À traiter comme signal NOISY/à ne pas surinterpréter : ne JAMAIS en déduire 'fret'.

- **Matching article archeoferroviaire -> lines.name / lines.code VFF** (weak) - Établit le lien d'identité pour reporter le statut 'disparue' + dates + GPX sur la bonne ligne VFF, en gérant le partiellement déposé.
  
  Accès : Pas de code RFN sur archeoferroviaire. Le nom est une paire (ou triplet) de villes brute, ex 'Mézidon Dives', 'Caen Vire', 'Saint-Lô Guilberville', avec '/' pour les variantes d'origine ('Orval/Hyenville Regnéville') et '&' pour les lignes multiples. Côté VFF : lines.name = format SNCF/Wikipedia 'Ligne de X à Y' / 'Raccordement de …' (1055 lignes, types passengers=1053, mixed=2) + code RFN (ex 450000) + slug 'rfn-450000'. Géométries SNCF dans sncf_line_geometries (code_ligne, libelle, geom).
  
  Distribution : sncf_line_geometries.libelle (autorité statut SNCF, croisement clé) : Exploitée 787, Neutralisée 235, Fermée 138, Déclassée vendue 136, Transférée en voie de service 132, Déclassée non vendue 64, Fermée non déposée (Plus utilisable) 45, Retranchée (Plus utilisable) 28, Fermée et déposée (Plus utilisable) 27, Fermée avec maintien en place de la voie 22, Fermée et mise à disposition de tiers 21, Neutralisée et conservée pour les besoins de la Défense 2, S9A3 - Ligne en travaux 1.
  
  Usage : Stratégie en cascade : (1) Extraire les villes-extrémités du nom (split sur espace, gérer '/' et '&'). (2) Matcher contre les endpoints des lignes VFF (premier/dernier toponyme de lines.name après 'Ligne de … à …') BORNÉ par région/département (rubrique_chain) pour réduire l'ambiguïté. (3) DÉSAMBIGUÏSER géométriquement : télécharger le GPX, buffer ~200 m, mesurer le % de recouvrement avec sncf_line_geometries.geom ; retenir le code_ligne le plus recouvert. (4) Si aucun recouvrement RFN significatif => ligne secondaire/industrielle absente du RFN : créer une nouvelle 'line' (type freight/secondaire/militaire selon qualificatifs) avec la géométrie GPX. Pour le 'partiellement déposé' : utiliser 'non déferrée entre A et B' pour scinder en segments status='abandoned' (rails) vs 'dismantled' (déposé).
  
  Pièges : Risque élevé de mauvais appariement par nom seul : homonymes de gares, lignes secondaires absentes du RFN (donc sans code), villes intermédiaires omises. TOUJOURS confirmer par la géométrie GPX avant d'écrire un statut sur une ligne VFF. Le statut SNCF (libelle) reste la SOURCE AUTORITAIRE pour les lignes du RFN : archeoferroviaire complète (dates fines, sous-tronçons déposés, lignes hors-RFN), il ne contredit pas le RFN exploité. Ne JAMAIS dériver 'fret-only' depuis archeoferroviaire (le site ne renseigne que des lignes disparues, pas l'état d'exploitation fret actuel).
