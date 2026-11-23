# Commandes artisan de données : options et ordre d'emploi

Ce document décrit les commandes `vff:*` entrées ou changées du 20 au 22 novembre 2026. La liste complète se lit
avec `bin/php artisan list vff` et l'aide d'une commande avec `bin/php artisan help <commande>`. Chaque docblock de
classe, dans `app/Console/Commands/`, garde les règles détaillées.

Règles communes :

- `--dry-run` calcule et affiche sans rien écrire dans la base (sauf mention contraire).
- Une commande qui écrit dans une table du moteur (`line_segments`, `line_reference_parts`, `station_eras`, graphe)
  se lance sur une base dont on a fait le dump, jamais en parallèle d'une autre.
- Les commandes d'audit ne modifient ni le moteur ni les sources : elles ne remplissent que leur table d'audit.

## Ordre du recalcul des gares

`vff:stations-pipeline` enchaîne : `vff:import-station-sources`, `vff:consolidate-stations`,
`vff:place-listed-stations`, `vff:attach-stations`, `vff:snap-stations`, `vff:import-station-closures`, puis
`vff:build-station-eras`. Les deux imports de dates ci-dessous (`vff:import-station-passenger-ends`,
`vff:import-station-wikidata-dates`) sont lancés à part et lus au prochain `vff:build-station-eras`.

## Gares

### `vff:consolidate-stations`

Dédoublonne `station_sources` (SNCF, Wikidata, OSM, OHM) en un référentiel `stations` et `station_events`, avec la
provenance de chaque attribut ; un écart de position de plus de 100 m entre sources est signalé
(`stations.position_flagged`). Idempotent : une gare garde son id et son slug.

| Option | Effet |
|:-|:-|
| `--dry-run` | Calcule le dédoublonnage sans rien écrire. |
| `--fresh` | Supprime et recrée toutes les gares (identifiants et slugs changent). À ne jamais lancer une fois des contributions ou des médias rattachés, ni sans l'accord du responsable des données. |

Règles ajoutées le 21/11 (`ecca8ad`) :

- Un objet OpenHistoricalMap terminé d'un autre réseau que le grand réseau (nom en « (CFD) » ou « tram… », arrêt de
  tramway, exploitant secondaire comme les TVT, le NStCM ou les CFSO) n'est plus rattaché par son nom à la gare SNCF
  homonyme : sa fin datée fermait la gare SNCF. Les trains touristiques roulent dans la gare du grand réseau et restent
  rattachés.
- Quand la SNCF dit la gare ouverte aux voyageurs aujourd'hui, la fin datée de tous ses objets OHM n'est une
  fermeture que si une réouverture d'une autre source la suit. Une gare fret seul ou touristique garde sa fermeture OHM.

### `vff:import-station-passenger-ends`

Lit la fin du service voyageurs dans le champ « fermeture » de l'infobox de l'article Wikipédia de la gare
(« 27 septembre 1969 (voyageurs) ») et l'écrit dans `station_events` (type `closure_passenger`, source `wikipedia`,
permalien de la révision, citation courte du champ). La fin du service marchandises du même champ, quand elle est
explicite (« 30 novembre 1990 (marchandises) », « aux frets », « fret »), est écrite à côté (type `closure_freight`).
342 fins des voyageurs et 150 fins du fret lues au 21/11. Seul ce qui est explicite est repris : une date qualifiée
« voyageurs » ou « marchandises », au jour, au mois, à l'année ou à la décennie ; ni date sans qualificatif ou « à tout
trafic », ni date incertaine, ni « avant 1950 ».

| Option | Effet |
|:-|:-|
| `--from-cache` | Rejoue les articles déjà téléchargés, sans requête réseau. |
| `--refresh` | Re-télécharge les articles en cache. |
| `--station=` | Une seule gare (identifiant ou slug). |
| `--prune` | Retire aussi les lignes des gares qui n'ont plus d'article lié (gare rouverte, lien disparu). Jamais celles d'une gare dont l'article est seulement absent du cache. |
| `--csv=` | Écrit le bilan de relecture (une ligne par date, avec les dates déjà connues et la frise actuelle). |
| `--dry-run` | Lit et compare sans écrire. |

Les articles sont rattachés par lien (élément Wikidata de la gare, sinon tag OSM `wikipedia=fr:…`), jamais par le nom ;
seules les gares fermées, disparues ou fret seul aujourd'hui sont retenues. Sont écartés et comptés : un article lié à
plusieurs gares, plusieurs fins des voyageurs (ou du fret) différentes pour une gare, une date antérieure à l'ouverture.
La commande ne remplace que ses propres lignes (`source_ref` en `#infobox-voyageurs` et `#infobox-fret`), et
seulement pour les gares dont l'article a été lu dans la passe (cache ou réseau) : avec `--from-cache` et un cache
incomplet, les gares dont l'article manque gardent leurs lignes (le résumé annonce « N gares dont l'article n'a pas été
lu »). `--prune` retire en plus les lignes des gares sans article lié, et l'annonce (« M retirées »). Deux passes de suite
donnent le même résultat.
`vff:build-station-eras` la lit au prochain calcul : plus de voyageurs à partir de l'ère de la date ; une fermeture
Wikidata (ou OSM) de la même année devient la fin des voyageurs sur une gare fret seul, et une fermeture Wikidata, OSM
ou OHM (ou une « démolition » Wikidata) de la même année aussi sur une gare fermée ou disparue dont une source plus sûre
date la fin du fret une année postérieure (Livarot : voyageurs 1938, marchandises 1990). Après la fin du fret et celle
des voyageurs, la gare est fermée : montrée fermée à l'ère de la fin du fret, puis absente de la carte, qu'elle soit
debout ou disparue (règle du 22/11). Les 80 gares de la section 9 de
`database/data/gares_corrections.php` étaient corrigées à la main pour ce cas ; la règle en couvre 66.

### `vff:import-station-closures`

Dates de fermeture des gares (Wikidata, articles de gare et de ligne de Wikipédia, OSM) dans `station_events`.

| Option | Effet |
|:-|:-|
| `--source=` | `wikidata`, `wikipedia-stations`, `wikipedia-lines` ou `osm`, répétable ; toutes par défaut. |
| `--from-cache` | Rejoue les articles et les éléments déjà en cache, sans requête réseau. Refusé (rien n'est écrit) quand plus de 5 % des éléments d'une source manquent au cache : une relecture partielle effaçait des fermetures (21/11 : 1 729 événements effacés, 4 réécrits). Avec `--dry-run`, la simulation va jusqu'au bout et le dit. |
| `--refresh` | Re-télécharge les articles en cache. |
| `--station=` | Une gare (identifiant ou slug), pour vérifier. |
| `--dry-run` | Affiche les événements sans écrire. |

Le parseur lit aussi les noms de gare qui commencent par « L » (Lyon, Lille, Laon, Lens), retire les dates en fin de
nom, lit « années 1980 » comme une décennie et accepte les listes à puces. Pour remplir le cache Wikidata sans toucher
la base : `vff:import-station-sources --source=wikidata --cache-only`.

### `vff:import-station-sources`

Brut multi-sources des gares (SNCF, OSM, OHM, Wikidata, communes, PK) dans `station_sources`, avec la provenance.

Source `manual` : gares absentes de toutes les autres sources, saisies à la main dans `database/data/gares_manuelles.php`
(nom, coordonnées, dates, permalien et citation). `vff:consolidate-stations` en fait une gare à part, jamais fondue dans
une autre par son nom ou sa proximité. Contrôler avant d'ajouter une entrée qu'aucune fiche ne porte déjà la gare (nom et
position).

Source `wikidata` : les éléments rattachés à une gare par une correction `link` de `database/data/gares_corrections.php` sont lus
en plus de la recherche, même si leur classe est exclue (gare de marchandises) : leurs déclarations (UIC, dates de mise en service,
coordonnées comme source secondaire) entrent dans `station_sources`, et `vff:consolidate-stations` les joint à la gare qui porte
leur Q-id. La position et l'état du jour restent ceux de la liste SNCF.

| Option | Effet |
|:-|:-|
| `--source=` | `communes`, `sncf`, `osm`, `ohm`, `wikidata`, `manual` ou `pk`, répétable ; toutes par défaut, dans cet ordre. |
| `--from-cache` | Rejoue les réponses déjà téléchargées, sans requête réseau. |
| `--cache-only` | Avec `--source=wikidata` seul : télécharge les réponses dans le cache disque (dossier `.nobackup`), sans rien écrire dans la base. Une liste ou un lot Wikidata en échec ne supprime plus aucune gare. |
| `--region=` | OSM : régions ISO 3166-2 (`FR-BRE`), répétable. |
| `--pause=20` | OSM : pause en secondes entre deux requêtes Overpass. |
| `--line=` | PK : limite aux lignes données, sans supprimer les autres. |
| `--dry-run` | PK : place les gares et affiche le bilan sans écrire. |
| `--validate` | PK : validation croisée des calages propres aux schémas (plus lent). |

### `vff:import-station-wikidata-dates`

Dates de mise en service (P1619) et de fermeture (P3999) des gares dans Wikidata, avec précision, rang, qualificatifs et
références, rangées dans `station_date_candidates` et comparées aux dates de VFF (`same`, `more_precise`,
`less_precise`, `close`, `differs`, `new`, `station_open`). Aucune date du moteur n'est remplacée.

| Option | Effet |
|:-|:-|
| `--station=` | Une gare (slug ou identifiant), répétable. |
| `--line=` | Une ligne (code, slug ou identifiant) : toutes ses gares, répétable. |
| `--from-cache` | Aucune requête : seulement les éléments déjà en cache. |
| `--refresh` | Redemande tous les éléments à Wikidata. |
| `--dry-run` | Lit et compare sans écrire. |

### `vff:audit-stations`

Audit des gares : note de placement (`agree`, `close`, `isolated`, `disagree`, `none`), notes des dates et des services,
contrôles croisés, dans `station_audits` (une ligne par gare). Méthode et règles : [AUDIT-GARES.md](AUDIT-GARES.md).

| Option | Effet |
|:-|:-|
| `--station=` | Une gare (slug ou identifiant), répétable. |
| `--line=` | Une ligne (code à six chiffres, slug ou identifiant) : toutes ses gares, répétable. |
| `--shard=i/n` | Une gare sur `n` (identifiant modulo `n` égal à `i`), pour plusieurs processus. |
| `--no-bdtopo` | Sans les gares de la BD TOPO (aucun appel à l'IGN). |
| `--offline` | BD TOPO lue seulement dans le cache local (`storage/app/data/gares-audit/`). |
| `--dry-run` | Calcule sans écrire dans `station_audits`. |

### `vff:build-station-eras`

Gares par décennie (`station_eras`, statut plafonné par l'état des lignes de l'ère) et `line_stops`. Une gare rouverte
n'est dite fermée avant sa réouverture que si toutes ses lignes l'étaient (règle du 21/11). Une gare fermée l'est à la
seule ère de sa fermeture, puis disparaît des ères suivantes (debout, démolie ou réutilisée) jusqu'à une réouverture
(règle du 22/11, voir [METHODE-CLASSIFICATION.md](METHODE-CLASSIFICATION.md)) : cette dernière étape relit toutes les
ères, il faut donc relancer toutes les ères après un `--era=` sur une ère passée.

| Option | Effet |
|:-|:-|
| `--era=` | Une seule ère (année). |
| `--no-stops` | Ne reconstruit pas `line_stops`. |
| `--parallel=1` | Processus parallèles, une ère par processus (4 au plus conseillé). |
| `--today=` | Date du jour (AAAA-MM-JJ) de l'ère courante, aujourd'hui par défaut. |
| `--skip-infer` | Interne : dates déduites déjà recalculées par le processus parent. |
| `--rules=` | Règles facultatives de l'audit des gares, séparées par des virgules : `g3` (ouverture déduite bornée par la carte), `g6` (fermeture tirée de la fin des voyageurs de la ligne rattachée par PK), `g7` (gare à code UIC sans fermeture datée et absente de la fréquentation SNCF 2015-2024 : fermée à l'ère 2020, fermeture datée « avant 2015 ») ; `none` : aucune. Défaut : clé `stations.era_rules` (`VFF_STATION_ERA_RULES`, vide). Voir [AUDIT-GARES.md](AUDIT-GARES.md). |

## Lignes, tracés et audits

### `vff:audit-segments`

Quatre notes par tronçon et par ère (classement, placement, exactitude, tracé) avec leurs preuves, dans
`segment_audits`, sans appel réseau. Méthode, règles et versions : [AUDIT-TRONCONS.md](AUDIT-TRONCONS.md).

| Option | Effet |
|:-|:-|
| `--line=` | Ligne à auditer : identifiant, code à six chiffres ou slug, répétable ; toutes les lignes qui ont des tronçons par défaut. |
| `--era=` | Année d'une ère, répétable ; toutes les ères de la ligne par défaut. Une passe limitée aux ères passées lit l'audit enregistré de l'ère courante. |
| `--shard=i/n` | Morceau `i` sur `n` des lignes (identifiant modulo `n` égal à `i - 1`). |
| `--dry-run` | Calcule et affiche les notes sans écrire. |

Exemples : `vff:audit-segments --line=405000 --line=371000` (lignes témoins), `vff:audit-segments --era=2030
--dry-run`, ou quatre processus `--shard=1/4` à `--shard=4/4` pour tout le réseau.

### `vff:compute-coverage-figures`

Mesure ce que sait l'encyclopédie (page `/network/coverage`, `GET /api/v1/stats/coverage`) : pour les lignes (ouverture et
fermeture datées au jour, au mois ou à l'année, tracé précis à moins de 3 m, électrification, signalisation, vitesses,
ouvrages d'art), les gares (dates, photo, fréquentation, desserte, patrimoine, accessibilité, anciens noms), les accidents
(fiches relues) et le matériel (séries datées), en nombre et en part, avec la part issue de sources sous licence ouverte
(registre `database/data/source_rights.php`), puis par ère. Lecture seule, chaque requête bornée à 30 s, résultat rangé dans
le cache sans expiration avec sa date de calcul ; une table facultative absente (fréquentation, dessertes, patrimoine,
accessibilité) donne une mesure « pas encore importée », jamais une erreur. Aucune planification : à relancer après un import.

| Option | Effet |
|:-|:-|
| `--dry-run` | Calcule et affiche les mesures sans toucher au cache. |

### `vff:compute-line-profiles`

Profil en long de chaque ligne sur son tracé de référence entier, toutes époques : altitude du terrain (RGE ALTI) et de
la voie (BD TOPO) de l'IGN, rangée point par point dans `line_profile_points` (avec le PK estimé) et résumée dans
`line_profiles`. Le sens suit les PK officiels des gares, sinon le nom de la ligne ; la déclivité maximale n'est
publiée que si elle est mesurée sur la voie, et les crémaillères gardent leur déclivité sourcée. Requêtes polies (une à
la fois, une seconde de pause), réponses gardées par tuile dans `storage/app/data/altimetrie/` (dossier `.nobackup`).

| Option | Effet |
|:-|:-|
| `--line=` | Slug d'une ligne, répétable. |
| `--limit=10` | Sans `--line`, nombre de lignes au plus (lignes sans profil, les plus longues d'abord). |
| `--all` | Toutes les lignes qui ont un tracé, sans limite. |
| `--force` | Recalcule aussi les lignes qui ont déjà un profil. |
| `--dry-run` | Affiche le parcours et le nombre de points sans appeler l'IGN ni écrire. |
| `--report=` | Avec `--dry-run`, fichier CSV de la couverture de chaque ligne. |
| `--prefill-tiles` | Remplit le cache des tuiles de la BD TOPO des lignes choisies, sans calculer. |
| `--stats-only` | Recalcule seulement les chiffres (déclivité, dénivelés) depuis les points déjà en base, sans appeler l'IGN. |
| `--max-cache-mb=4096` | Taille du cache au-delà de laquelle la commande s'arrête. |

Une ligne dont le profil ne peut plus être calculé (tracé retiré, aucune altitude) perd son ancien profil, et la sortie
le signale. Lecture : `GET /api/v1/lines/{line}/profile`, `/profile/points`, `/altitude?pk_m=` ([API.md](API.md)).

### `vff:import-sncf-grades`

Déclivités du jeu SNCF Open Data « Caractéristique des voies et déclivité » (SNCF Réseau, ODbL) : segment le plus raide
de chaque ligne du RFN, confronté à Wikipédia (`database/data/sourced_grades.php`) et au profil mesuré en base, puis écrit
dans `database/data/sourced_grades_sncf.php`, lu par la fiche et l'API (`sourced_grade`). La base n'est jamais modifiée.
Le jeu ne décrit qu'une partie des voies : une ligne couverte à moins de 10 % est laissée de côté. Une valeur au-dessus
de 60 ‰ hors ligne de montagne connue, ou de plus de 3 fois la déclivité mesurée, est écartée et listée. Si Wikipédia
donne une valeur proche, elle est gardée ; si les deux divergent, la SNCF n'est retenue que si elle reste cohérente avec
la voie et dépasse Wikipédia, ou couvre presque toute la ligne. Deux requêtes (métadonnées, dont la licence vérifiée,
puis export), jeu gardé dans `storage/app/sources/sncf-declivites/` (dossier `.nobackup`). Le fichier est réécrit trié :
relancer la commande sur les mêmes données donne le même fichier.

| Option | Effet |
|:-|:-|
| `--dry-run` | Rapport seulement, aucun fichier écrit. |
| `--refresh` | Télécharge de nouveau le jeu au lieu du cache local. |
| `--min-coverage=0.1` | Part de la ligne (0 à 1) que les segments du jeu doivent couvrir. |
| `--min-steep-m=500` | Longueur minimale (m) de la portion la plus raide pour que la valeur SNCF remplace celle de Wikipédia (0 : pas de contrôle). |
| `--cache-dir=` | Dossier du cache. |
| `--output=` | Fichier de données écrit (obligatoire pendant les tests). |
| `--csv=` | Rapport détaillé par ligne (code, slug, résultat, valeurs SNCF, Wikipédia et mesurée, part couverte, longueur de la portion la plus raide). |

### `vff:import-wikipedia-grades`

Déclivités maximales lues dans les articles de Wikipédia des lignes, déjà en cache
(`storage/app/data/signalisation-historique/wikipedia`, rempli par `vff:import-signalling-history`) : aucun appel réseau, la
base n'est jamais modifiée. Champ `pente` de l'infobox quand l'article est bien celui de la ligne (longueur à 25 % près, ou
numéro de ligne égal à son code), sinon déclivité maximale écrite dans le texte (une seule valeur, sans fourchette ni portion
nommée), reprise seulement si elle s'accorde avec la mesure du profil. Article partagé par plusieurs lignes et lignes à
crémaillère écartés. Résultat fusionné dans `database/data/sourced_grades.php` (lu par la fiche et l'API : `sourced_grade`,
`sourced_grade_compared`) : ajouts et mises à jour seulement, rien n'est effacé, une entrée tirée du texte n'est jamais
remplacée. Règles : `App\Services\Profiles\WikipediaGrades`. Relancer la commande sur les mêmes articles donne le même fichier.

| Option | Effet |
|:-|:-|
| `--dry-run` | Bilan seulement, aucun fichier écrit. |
| `--articles-dir=` | Dossier du cache des articles. |
| `--output=` | Fichier de données écrit (obligatoire pendant les tests). |
| `--csv=` | Rapport détaillé par ligne (slug, titre, lecture, effet sur le fichier, valeur de Wikipédia, mesure, motif de non-publication). |

### `vff:profile-trace-holes`

Lignes dont le tracé de référence a de vrais trous sous le profil en long (les trous conventionnels posés devant chaque
embranchement ne comptent pas) : nombre de lignes, de trous, km de trous à vol d'oiseau, trous de plus de 5 km, lignes dont
les trous font 25 % du parcours ou plus, lignes sans profil, puis les lignes aux plus grands trous. Lecture seule.

| Option | Effet |
|:-|:-|
| `--min-m=0` | Ne compter que les trous d'au moins cette longueur (mètres). |
| `--top=30` | Nombre de lignes affichées. |
| `--csv=` | Liste complète, un trou par ligne du CSV (slug, nom, nombre et longueur des trous, part, motif, bornes du trou). |

### `vff:import-sncf-track-regime`

Importe le jeu SNCF Open Data « Régime d'exploitation des lignes » (SNCF Réseau, ODbL) : nombre de voies principales par
portion de PK. Chaque tronçon rattaché à une ligne de VFF par son code va dans `line_track_portions`, libellé SNCF tel quel
(toutes les portions `sncf` sont remplacées d'un coup). « Voie banalisée » et « _Autre » ne disent pas le nombre de voies :
leurs portions sont gardées avec un nombre nul et comptent seulement dans la longueur de la ligne. Le nombre de voies de la
fiche (`lines.nb_voies_default`) n'est écrit que pour une ligne au régime uniforme (90 % de sa longueur ou plus, voie
banalisée comprise dans le total) et seulement si le champ est vide ou vient encore de cette commande (`line_sources`
« sncf-regime », et valeur égale à celle du passage précédent) : une valeur d'une autre source n'est jamais remplacée ni
effacée, même écrite après un passage de la commande, et un nombre différent est listé comme contradiction. Une ligne qui
mêle voie unique et double voie, ou dont plus de 10 % est banalisée, garde un champ vide ; sa fiche dit le nombre dominant
et sa longueur. Le jeu décrit le réseau actuel (aucune ère) ; il est gardé dans
`storage/app/sources/sncf-regime/` (dossier `.nobackup`), licence et éditeur vérifiés au téléchargement. Servi par
`GET /api/v1/lines/{line}/infrastructure`, `Line.infrastructure` et l'outil MCP `line_infrastructure`.

| Option | Effet |
|:-|:-|
| `--dry-run` | Rapport seulement : lignes touchées, portions, nombres écrits, confirmés, contradictions, régimes partagés, contrôle Wikidata. |
| `--refresh` | Télécharge de nouveau le jeu au lieu du cache local. |
| `--cache-dir=` | Dossier du cache. |
| `--csv=` | Rapport détaillé par ligne (action prévue pour chacune). |

### `vff:import-sncf-radio`

Importe le jeu SNCF Open Data « Lignes équipées de liaison radio sol-train » (SNCF Réseau, ODbL) dans `line_radio_portions` :
système par portion de PK (`gsm-r` GSM-R, `gsm-gfu` GSM d'opérateur public en groupe fermé d'utilisateurs, `rst` radio
sol-train analogique sans données, `rst-data` avec données, `rst-id` avec identification), libellé SNCF tel quel. Un libellé
inconnu est écarté et signalé. Toutes les portions `sncf` sont remplacées d'un coup ; aucune autre table n'est écrite. Cache
`storage/app/sources/sncf-radio/` (dossier `.nobackup`), licence et éditeur vérifiés au téléchargement. Mêmes points d'accès
que le régime d'exploitation.

| Option | Effet |
|:-|:-|
| `--dry-run` | Rapport seulement : lignes touchées, portions, lignes au GSM-R, longueur par système. |
| `--refresh` | Télécharge de nouveau le jeu au lieu du cache local. |
| `--cache-dir=` | Dossier du cache. |
| `--csv=` | Rapport par ligne (km par système). |

### `vff:import-station-timetables`

Calcule la desserte d'un jour ouvrable type des gares et des lignes d'après les horaires ouverts : GTFS SNCF (TER, Intercités, TGV INOUI,
OUIGO), Transilien (RER compris) et, pour les gares que ces deux flux ne desservent pas, les trains régionaux ZOU ! (flux déclarés dans
`database/data/station_service_feeds.php`, lus par `StationService\GtfsFeedStore`, cache `storage/app/data/gtfs`, dossier `.nobackup`,
User-Agent du robot VFF). Le jour type est un mardi de semaine sans jour férié, médian en nombre de trains parmi les mardis proches que
couvrent tous les horaires de référence ; les cars sont écartés. Rattachement des arrêts aux gares par code UIC, puis nom, puis position
(`StationStopMatcher`, comme `vff:import-station-service`). Écrit `station_timetables` (trains par type et par sens, premières et dernières
heures, destinations directes, horaires utilisés) et `line_timetables` (trains par jour, chacun compté une fois par ligne dont il parcourt
deux gares consécutives), remplacées entièrement. Points d'accès : `GET /api/v1/stations/{station}/timetable`, `timetable` des fiches REST,
GraphQL `Station.timetable` et `LineSheet.timetable`, outils MCP `station_timetable` et `line_timetable`.

| Option | Effet |
|---|---|
| `--no-store` | Mesure seule : résumé et rapports, rien n'est écrit en base. |
| `--from-cache` | Réutilise les flux déjà téléchargés. |
| `--date=AAAA-MM-JJ` | Jour à lire, au lieu du mardi type choisi dans les horaires. |
| `--today=AAAA-MM-JJ` | Date du jour (défaut : aujourd'hui). |
| `--report=dossier` | Écrit `couverture.csv`, `gares-sans-desserte.csv` et `flux.json`. |

### `vff:import-station-accessibility`

Importe l'accessibilité et les services des gares d'aujourd'hui d'après sept jeux ouverts de SNCF Gares & Connexions et SNCF
Voyageurs (ODbL) : « Accessibilité des gares » (NeTEx : accès des personnes à mobilité réduite, quais, ascenseurs recensés),
« Equipements d'accessibilité en gare » (boucle magnétique, bandes d'éveil, toilettes…), assistance en gare (horaires), état de
fonctionnement des ascenseurs, points de vente (guichet, automates), stationnement vélo sécurisé, et le référentiel « Gares de
voyageurs » qui relie codes UIC et trigrammes à l'identifiant commun. Une gare du référentiel peut porter plusieurs codes UIC :
chaque fiche de VFF reconnue (`StationAttendanceMatcher`) reçoit les informations. Une absence n'est jamais une négation : seul le NeTEx
répond oui ou non (`mobility` : `partial` ou `none`, c'est-à-dire non déclarée accessible par SNCF). Toutes les lignes `sncf` de
`station_accessibility` sont remplacées d'un coup, dans une transaction ; aucune autre table n'est écrite. Cache
`storage/app/sources/sncf-accessibility/` (dossier `.nobackup`), licence et éditeur vérifiés au téléchargement ; l'état des ascenseurs est
celui de la lecture (`--refresh` pour celui du jour). Points d'accès : `GET /api/v1/stations/{station}/accessibility`, `accessibility`
de la fiche REST, filtre `accessible` de l'annuaire (web, REST, MCP), GraphQL `Station.accessibility`, outil MCP `station_accessibility`.
Migration `2026_12_17_010000_create_station_accessibility_table` ; application : `storage/app/reports/agents/aprem-2111/gares-accessibilite-appliquer.sh`.

| Option | Effet |
|:-|:-|
| `--dry-run` | Rapport seulement : couverture par statut, gares non rattachées, gares ouvertes sans information. |
| `--refresh` | Télécharge de nouveau les jeux au lieu du cache local. |
| `--cache-dir=dossier` | Dossier du cache (défaut : `storage/app/sources/sncf-accessibility`). |
| `--csv=dossier` | Écrit `non-rattachees.csv`, `doublons.csv` et `sans-information.csv`. |

### `vff:import-sncf-attendance`

Importe la fréquentation annuelle des gares : jeux SNCF Open Data « Fréquentation en gares » (SNCF Gares & Connexions, ODbL),
archive 2015-2024 (`frequentation-gares-archive`) et jeu courant 2025 et suivantes (`frequentation-gares`), dans
`station_attendances` : une ligne par gare et par année, voyageurs (`passengers`) et voyageurs avec non-voyageurs (`footfall`).
Rattachement à la gare de VFF par code UIC ; les doublons de fiches (« Grasse », « Virville-Manneville ») sont départagés par le
département du code postal, quelques gares sans code connu par le nom et le département, un alias vérifié à la main
(`StationAttendanceMatcher::ALIASES`). `series` sépare 2015-2024 de 2025 : SNCF écrit que les deux méthodes de calcul ne sont pas
directement comparables. Une gare du jeu sans gare de VFF n'est pas écrite (rapport `non-rattachees.csv`). Toutes les lignes
`sncf` sont remplacées d'un coup ; aucune autre table n'est écrite. Cache `storage/app/sources/sncf-attendance/` (dossier
`.nobackup`), licence et éditeur vérifiés au téléchargement. Points d'accès : `GET /api/v1/stations/{station}/attendance`,
`attendance` de la fiche REST, GraphQL `Station.attendance`, outil MCP `station_attendance`.

| Option | Effet |
|:-|:-|
| `--dry-run` | Rapport seulement : rattachement par règle, gares non rattachées, couverture par statut, gares ouvertes sans fréquentation. |
| `--refresh` | Télécharge de nouveau les jeux au lieu du cache local. |
| `--cache-dir=` | Dossier du cache. |
| `--csv=` | Dossier des rapports CSV (lignes prévues, non rattachées, doublons, gares ouvertes sans fréquentation). |

### `vff:import-sncf-platforms`

Importe les quais des gares de voyageurs du jeu SNCF Open Data « Liste des quais » (SNCF Réseau, ODbL) dans
`station_platforms` : un enregistrement par voie bordée, avec sa longueur et ses PK. Le jeu n'a pas de code UIC : la gare
est cherchée par son nom et sa ligne dans le référentiel des gares SNCF (`sncf_gares`), puis par son seul nom, pour le code
UIC qui la relie à `stations` ; à défaut, la gare de VFF de même nom la plus proche du milieu des quais, à 1,5 km au plus.
Un enregistrement sans gare est gardé, `station_id` nul. L'import remplace toutes les lignes `source = 'sncf'` de la table
dans une transaction et ne touche aucune autre table. Les quais sont servis par `GET /api/v1/stations/{station}`
(`platforms`), `Station.platforms` en GraphQL et `get_station` en MCP. Le jeu est gardé dans `storage/app/sources/sncf-quais/`
(dossier `.nobackup`), licence et éditeur revérifiés à chaque téléchargement.

La passe à blanc compte les rattachements (code UIC, nom), les gares non rattachées, les gares de VFF ouvertes aux voyageurs
sans quai connu, et les contradictions : gare de VFF fermée, disparue ou projetée qui a des quais dans le jeu, gare rattachée
par son code UIC à plus de 2 km de ses quais, quai dont la longueur s'écarte de l'étendue de ses PK, nom SNCF rattaché à
plusieurs gares.

| Option | Effet |
|:-|:-|
| `--dry-run` | Rapport seulement, aucune écriture en base. |
| `--refresh` | Télécharge de nouveau le jeu au lieu du cache local. |
| `--cache-dir=` | Dossier du cache (défaut : `storage/app/sources/sncf-quais`). |
| `--csv=` | Rapport détaillé par gare SNCF et ligne (quais, plus long, gare de VFF, rattachement, distance, note). |

### `vff:build-line-references`

Fusionne les tracés de chaque ligne (IGN, OSM, OHM, SNCF…), recale les morceaux peu précis sur la voie réelle et croise
les sources. Écrit dans les tables du moteur.

| Option | Effet |
|:-|:-|
| `--line=` | Identifiant ou code d'une ligne, répétable. |
| `--no-conflate` | Garde les morceaux peu précis tels quels (ancien comportement). |
| `--no-agreement` | Sans croisement des sources (ni confiance ni divergences). |
| `--no-single-axis` | Garde les portions dédoublées d'une même ligne. |
| `--no-junction-repair` | Sans l'étape de continuité sur les voies réelles (sauts, trous, jonctions). |
| `--no-rounding` | Sans l'arrondi des sommets (segments droits). |
| `--no-extend-to-stations` | Éteint la règle 10 (allumée par défaut), qui prolonge jusqu'à sa gare terminus, le long de la trace source, un bout arrêté avant elle. |
| `--no-join-lateral` | Éteint la règle 11 (allumée par défaut), qui raccorde au dessin de la voie traversante d'une autre ligne un bout arrêté à côté d'elle dans une gare commune. |
| `--report-only` | Mesure la précision du tracé actuel et réécrit les rapports, sans rien reconstruire. |
| `--shard=i/n` | Une ligne sur `n`, pour des processus parallèles. |

### `vff:aggregate-line-electrification`

Recalcule `lines.electrified` et `lines.voltage_v` depuis les tronçons de l'ère courante : une ligne est dite électrifiée
quand la part de ses tronçons en service électrifiés atteint le seuil de la fiche, pas quand un seul tronçon l'est
(commit `462354b`). N'écrit que `lines` et vide le cache des chiffres de l'accueil. À lancer après
`vff:enrich-era-segments` (électrification des tronçons) et `vff:classify-lines`.

| Option | Effet |
|:-|:-|
| `--era=` | Année de l'ère (la courante par défaut). |
| `--dry-run` | Calcule et affiche les lignes qui changent de sens, sans écrire. |
| `--show=40` | Nombre de lignes affichées par sens. |

### `vff:electrification-history-coverage`

Lecture seule : mesure la couverture de l'histoire de l'électrification (service `LineElectrificationHistory`, lue à la
volée dans `line_events` et `sncf_line_electrification`, rien à importer). Affiche les lignes électrifiées qui ont au moins
une date de mise sous tension sûre, avec et sans les raccordements, le nombre de faits rendus par nature et le nombre
d'événements écartés comme ambigus.

| Option | Effet |
|:-|:-|
| `--line=` | Code ou slug d'une ligne : ses tranches SNCF et ses faits datés, un par ligne, pour vérifier à la main. |
| `--missing=0` | Liste N lignes électrifiées (hors raccordements) sans date sûre. |

### `vff:merge-split-lines`

Fusionne les lignes en double et recolle les événements rattachés à la mauvaise ligne, en six passes rejouables : `sections`,
`article`, `couverture`, `orphelines`, `homonymes`, `couloir`. Écrit dans les tables du moteur ; `vff:rebuild-history` l'enchaîne,
et il faut relancer ensuite `vff:build-line-references` sur les lignes touchées. Rapport CSV de relecture dans
`storage/app/reports/merge-split-lines[-dry-run].csv`.

| Option | Effet |
|:-|:-|
| `--only=` | Passes séparées par des virgules. |
| `--dry-run` | Calcule et affiche sans rien écrire. |

Règles de la passe `sections` ajoutées les 21 et 22/11 (fusion des lignes de section) :

- « au » et « aux » valent « à le » et « à les » dans les deux noms (Port-de-Piles au Blanc), et une section nommée dans
  l'autre sens que sa ligne est reconnue (Bressuire à Parthenay, Abbeville à Saint-Pol).
- Une section sans extrémité partagée avec sa ligne à code (section du milieu, ou nom de gare en origine) est fusionnée
  si un bout est à 1 km au plus du tracé connu, si le tracé est incomplet (moins de 85 % de la longueur officielle) et si
  la section le complète à 70 à 115 %, avec une preuve : ses deux toponymes sont des lieux de la ligne, ou un seul l'est
  au bout qui prolonge le tracé, ou trois gares sourcées de la ligne l'appuient. Une seule ligne à code doit tout remplir,
  sinon le cas reste « à relire ». Une ligne qui part d'une gare de la ligne ou aboutit à son tracé est refusée.
- Mesure du 22/11 sur la copie : 12 tracés complétés (470 km de plus), `lines.junction_gap` à 0.

Passe `couloir` (22/11) : une ligne créée par `vff:link-traces` (sans code) dont le tracé de référence court dans le
couloir d'une ligne à code est cette ligne dessinée une seconde fois. Elle y est fusionnée si au moins 60 % de son tracé
est à 40 m au plus du tracé de la ligne à code (et 80 % à 40 m de lignes à code), si les deux lignes sont de la même
catégorie et si ses deux toponymes sont des lieux de la ligne, ou l'un d'eux et l'autre une commune ou une gare à 3 km au
plus de son tracé. Une ligne secondaire, militaire ou industrielle qui longe une ligne du réseau principal est refusée,
une ligne à code n'est jamais fusionnée, et un tracé dans le couloir de deux lignes à code reste « à relire ». Passe à
blanc du 22/11 sur `vff` : 4 fusions (Aire-sur-la-Lys à Isbergues, Avesnes à Flaumont, Gravigny à Amfreville, Louviers à
Caudebec-lès-Elbeuf), 7 refus.

### `vff:neutralize-line-slugs`

Slug neutre des lignes dont le slug porte un préfixe de source (`archeo-`) : le préfixe est retiré, suffixe `-2`, `-3`… en cas de
collision (`App\Services\Lines\NeutralLineSlugs`). Chaque ancien slug, et celui d'une ligne fusionnée dans une autre
(`alt_names.merged`), va dans `database/data/line_slug_redirects.php` : 301 du site et de l'API, ancien slug accepté par GraphQL et
le MCP. Idempotente.

```bash
bin/php artisan vff:neutralize-line-slugs --dry-run [-v]     # liste (toute avec -v) et collisions, rien d'écrit
bin/php artisan vff:neutralize-line-slugs --map-only         # fichier des correspondances seulement
bin/php artisan vff:neutralize-line-slugs --rewrite-files    # renomme (une transaction), écrit le fichier, réécrit les slugs cités
bin/php artisan cache:clear                                  # ensuite : carte, tuiles, plan du site
```

`--rewrite-files` remplace les anciens slugs entiers cités dans `app`, `database/data`, `docs`, `graphql`, `resources/js` et `tests`.
Les lignes créées ensuite par `vff:link-traces` reçoivent directement un slug neutre.

### Règles du moteur ajoutées à `vff:build-line-references`

Les options sont celles du tableau plus haut. Trois règles de continuité du tracé de référence (étape `--no-junction-repair`
et nettoyage `--no-single-axis`) sont arrivées les 21 et 22/11 ; leur docblock complet est dans
`app/Services/JunctionRepair.php` et `app/Services/SingleAxis.php` :

- Nettoyage : un fragment de référence isolé de moins de 60 m est retiré, sauf si un bout de ligne, une autre ligne, une
  gare ou une voie en service le rattache au réseau (`d17df80`).
- Règle 5 bis : un bout libre posé sur la voie source d'une autre ligne dessinée à côté (jusqu'à 6 m) est raccordé à
  0 m au dessin de cette ligne par une courbe lissée marquée « raccord-lisse » (estimée, jamais présentée comme précise) ;
  refusée si les écartements diffèrent, si le bout croise déjà le dessin ou si la courbe double la ligne.
- Règle 8 bis : un raccord droit de moins de 60 m, coudé de plus de 35 degrés, qui ferme une boucle (ses deux bouts déjà reliés par le reste de la
  ligne) est retiré avec le bout de portion qu'il laissait en l'air, sauf si une autre ligne finit sur lui (`3cc2e0d`).
- Règle 10 (allumée par défaut, `--no-extend-to-stations` l'éteint) : un bout libre arrêté à plus de 30 m d'une gare de sa
  ligne qui se trouve au-delà de lui, quand une trace rattachée à la ligne passe à moins de 5 m du bout et à moins de
  15 m de la gare, est prolongé jusqu'à la gare le long de cette trace (sur les voies précises qui la suivent, sinon sur
  la trace archeo ou OHM elle-même, gardée comme tracé grossier ; jamais sur une forme SNCF Réseau, généralisée, dont
  les 5 m ne sont pas garantis). Le prolongement s'arrête au premier contact avec le dessin d'une autre ligne (jonction
  à 0 m) et il est refusé s'il double une autre ligne dès le bout, s'il croise une voie ou s'il dépasse 2 km.
- Règle 11 (allumée par défaut, `--no-join-lateral` l'éteint) : un bout libre arrêté à côté du dessin d'une autre ligne
  (de 0,5 à 30 m, presque parallèle, voie traversante sur au moins 60 m autour du bout), quand une gare est desservie par
  les deux lignes à moins d'1 km, que les écartements sont compatibles, que les deux lignes sont en service à une même ère
  près du bout et qu'elles sont au même niveau (jamais une voie en tunnel contre une voie au sol), est raccordé au dessin
  de l'autre ligne : par les voies précises réelles (au plus 400 m, sans s'écarter de plus de 35 m, sans longer l'autre
  ligne à l'écart sur plus de 150 m, sans la traverser, sans doubler la ligne), sinon par une courbe estimée
  « raccord-lisse » tangente aux deux voies, devant le bout, de rayon plausible, qui se rapproche toujours de l'autre
  ligne et ne croise ni une voie précise ni le dessin d'une ligne (refusée devant une voie précise en impasse). La
  jonction tombe à 0 m ; ce sont les bouts que compte l'invariant `lines.junction_gap_lateral`.

Ces règles ne s'appliquent au réseau qu'au prochain recalcul des lignes (scripts d'application des rapports d'agents, voir
`REPRISE.md`).

### `vff:apply-argentanwebferro`

Faits relus d'Argentan Web Ferroviaire (table `argentanwebferro_facts`) vers le moteur : événements de lignes
(`line_events`, source `argentanwebferro`) et arrêts des listes par ligne (`source_stations`). Rejouable : un fait
retiré disparaît des tables au passage suivant, sauf avec `--line`.

| Option | Effet |
|:-|:-|
| `--line=` | Code SNCF ou slug d'une ligne. |
| `--dry-run` | Affiche ce qui serait écrit. |

`vff:rebuild-history` l'enchaîne avant `vff:apply-event-corrections`, puis `vff:resolve-event-sections
--source=argentanwebferro`.

### `vff:apply-event-corrections`

Applique les corrections sourcées des événements de lignes (`database/data/line_event_corrections.php` : ajouts, fausses
fermetures neutralisées, portions déplacées). Options : `--line=` (code SNCF ou identifiant), `--dry-run`.

### `vff:flag-threatened`

Affiche le soutien de VFF à une ligne menacée ou à une campagne de réouverture : `vff:flag-threatened <code>` avec
`--kind=threatened|reopening`, les textes `--title`, `--message`, `--cta` (et leurs formes `-en` et `-de`), `--url`,
`--source="Libellé|AAAA-MM-JJ|https://…"` (répétable) et `--off` pour retirer le message. Depuis le 21/11, trois options
règlent l'accueil :

| Option | Effet |
|:-|:-|
| `--home-rank=` | Rang sur l'accueil (1 d'abord) ; sans rang, après les lignes classées. |
| `--home=card\|mention` | Encart complet (défaut) ou phrase courte. |
| `--home-summary=`, `--home-summary-en=`, `--home-summary-de=` | Texte de la mention courte, obligatoire avec `--home=mention`. |

## Contenus

### `vff:import-accidents`

Importe les accidents du rail français (Wikidata, bilan des enquêtes du BEA-TT, listes d'accidents de Wikipédia, fiches
rédigées à la main) dans `accidents`, `accident_lines` et `accident_sources`. Idempotent : une fiche est retrouvée par
son élément Wikidata ou par l'identifiant d'une de ses sources, jamais par son slug. N'écrit dans aucune table du moteur
mais lit celui-ci pour rattacher chaque fiche aux lignes : ne pas le lancer pendant un recalcul du moteur.

| Option | Effet |
|:-|:-|
| `--only=` | Source à lire, répétable : `wikidata`, `bea-tt`, `wikipedia-list`, `wikipedia-editions`, `manual` ; toutes par défaut. |
| `--offline` | Cache local seulement, aucune requête réseau. |
| `--refresh` | Ignore le cache et relit les sources. |
| `--dry-run` | Calcule tout sans écrire en base. |
| `--prune` | Retire les fiches absentes des sources (import complet seulement). |
| `--report=` | Fichier JSON du bilan détaillé. |

Deux fichiers de données versionnés complètent l'import :

- **Noms de fiche retenus** : `database/data/accident_slugs.php`. Quand la date ou la commune d'une source est corrigée
  d'après le contexte, la fiche change de slug (voir `vff:enrich-accidents`). À la création d'une fiche, l'import forme
  le slug d'après la source, donc sur une base vide il redonnerait l'ancien nom et le fichier de contexte ne trouverait
  plus sa fiche. Chaque ligne relie une source de la fiche (`fournisseur:identifiant`, ceux de `accident_sources`) au
  slug retenu (`slug`), avec l'ancien nom (`from`, qui n'est plus donné à une autre fiche) et la raison. Renommer une
  fiche : renommer le fichier de contexte et sa clé `slug`, changer le slug en base, puis ajouter ou mettre à jour la
  ligne ici. Une base qui contient déjà la fiche n'est jamais modifiée (le slug n'est pas recalculé).
- **Fiches rédigées à la main** : `database/data/accident_records_manual.php`, pour un accident qu'aucune source de
  l'import ne décrit mais qu'un document lu appuie (presse d'époque, ouvrage). Lu comme une source de plus
  (`--only=manual`) : la fiche passe par la localisation et le rattachement aux lignes, se fond dans une source
  automatique si elle décrit un jour le même accident, reste « à vérifier » (note `place_from_manual`) et porte la
  source citée (`accident_sources`, rôle `press`). Un enregistrement : `id` (AAAA-MM-JJ-commune), `date`, `commune` et
  `department`, `kind`, `deaths` et `injuries` (ou `null`), `line_articles` (titres d'articles de Wikipédia des lignes),
  `source` (`provider` déclaré dans `source_rights.php`, `title`, `url`, `licence`, `published`). Des faits et un lien,
  jamais le texte du document. Le récit et ses sources vont dans le fichier de contexte, créé ensuite
  (`database/data/accident_contexts/<slug>.php`) puis enregistré par `vff:enrich-accidents`. Le bilan retenu d'une
  fiche rédigée à la main a pour `toll_source` la valeur `manual`.

### `vff:enrich-accidents`

Enregistre le contexte sourcé des accidents (archives, presse, enquêtes, images de Commons, corrections de lieu, de
date, de nature et de bilan) depuis `database/data/accident_contexts` ; 359 fiches au 21/11.

| Option | Effet |
|:-|:-|
| `--check` | Contrôle les fichiers de contexte sans rien écrire. |
| `--only=` | Slug d'une fiche à traiter, répétable ; sans cette option, tous les fichiers, et le contexte d'une fiche dont le fichier a disparu est retiré. |
| `--dry-run` | Calcule tout dans une transaction annulée. |
| `--offline` | N'interroge pas Commons : métadonnées des images lues dans le cache. |
| `--dir=` | Dossier des fichiers de contexte (`database/data/accident_contexts` par défaut). |
| `--report=` | Fichier JSON du bilan détaillé. |

Règles de rédaction validées par Baptiste (22/11/2026) :
- bilan : quand la dépêche de presse compte moins de morts que la liste de Wikipédia, le chiffre de la liste est gardé
  et l'écart est écrit dans la section des contradictions (`disagreements`), avec les deux sources ;
- nom de fiche : une fiche dont la commune ou la date est corrigée prend le slug de la nouvelle commune ou de la
  nouvelle date (règle de l'import : date, puis commune), sans alias tant que le site n'est pas en production ; le
  fichier de contexte est renommé de même et sa clé `slug` suit.

### `vff:select-home-images`

Retient des images anciennes de Commons pour « Le rail en images » de l'accueil (tirage renouvelé toutes les 30
minutes, du plus ancien au plus récent). Options : `--max-year=1970` (dernière année représentée prise en compte),
`--dry-run`. Écrit dans `media` : faire le dump de cette table avant.

### `vff:import-gallica`

Rattache aux lignes les documents numérisés de Gallica qui en parlent (métadonnées de la BnF et lien, jamais l'image).

| Option | Effet |
|:-|:-|
| `--line=` | Slug d'une ligne dont les requêtes sont relancées, répétable ; toutes par défaut. |
| `--limit=` | Nombre de lignes traitées au plus, dans l'ordre des identifiants. |
| `--threshold=` | Score minimal d'un document rattaché (9 par défaut, sur 11). |
| `--offline` | Cache local seulement, aucune requête réseau. |
| `--refresh` | Ignore le cache et relit le catalogue. |
| `--dry-run` | Calcule tout sans écrire. |
| `--report=` | Fichier CSV de tous les documents jugés, retenus ou non, avec leur score et ses raisons. |

### `vff:import-bibliography`

Rattache aux lignes et aux gares les ouvrages et articles qui en traitent (tables `bibliography_entries` et
`bibliography_links`, section « Pour aller plus loin » des fiches, `GET /api/v1/lines/{line}/bibliography` et
`GET /api/v1/stations/{station}/bibliography`). Trois sources : livres du catalogue général de la BnF (API SRU
documentée, notices sous Licence Ouverte), références `{{Ouvrage}}` / `{{Article}}` et section « Bibliographie » des
articles de Wikipédia des lignes et des gares, « décrit par » (P1343) et « sujet principal » (P921) de Wikidata.
Seules les notices sont reprises (auteurs, titre, éditeur, année, ISBN, lien) ; aucune page de livre n'est lue. Une
requête toutes les 5 secondes au plus, User-Agent du projet, cache disque (`storage/app/data/bibliographie`,
`storage/app/data/gallica`, dossiers `.nobackup`). Les liens de chaque source traitée sont remplacés pour les lignes
et gares traitées ; une ligne dont une requête échoue garde ses liens. Table absente (migration non appliquée) :
`--dry-run` fonctionne, l'écriture est refusée. Méthode, rattachement prudent et couverture :
`storage/app/reports/agents/aprem-2111/bibliographie.md`.

| Option | Effet |
|:-|:-|
| `--source=` | `bnf`, `wikipedia` ou `wikidata`, répétable ; les trois par défaut. |
| `--line=` / `--station=` | Slug traité, répétable ; toutes les lignes (articles en premier) et les gares ayant un article ou un élément Wikidata par défaut. |
| `--no-stations` | Ne traite aucune gare. |
| `--no-isbn-lookup` | Ne cherche pas dans la BnF les ISBN des références de Wikipédia et de Wikidata (sinon une requête pour quinze ISBN, pour lier la notice). |
| `--bnf-stations` | Interroge aussi la BnF pour chaque gare (une requête par gare, long). |
| `--limit=` | Nombre de lignes (et de gares avec `--bnf-stations`) interrogées à la BnF au plus. |
| `--threshold=` | Score minimal d'une notice de la BnF rattachée à une ligne (9 par défaut, sur 11). |
| `--interval=` | Millisecondes entre deux requêtes (5000 au moins, 5000 par défaut). |
| `--offline` / `--from-cache` | Caches seulement (BnF, Wikidata, Wikipédia) / articles de Wikipédia en cache seulement. |
| `--refresh` | Ignore les caches et relit les sources. |
| `--dry-run` | Calcule tout sans écrire. |
| `--report=` | Fichier CSV de tous les rattachements retenus. |

### `vff:import-sncf-archives`

Rattache aux lignes les notices du jeu de données « Archives SNCF » (dossiers, photographies et films du Service
Archives Documentation du groupe SNCF, ressources.data.sncf.com, ODbL) dont le titre les désigne, avec le score de
`vff:import-gallica` (table `line_archive_records`). Seuls le titre, le type, le classement, la boîte, la date et le
lien vers archives.sncf.com sont gardés ; ni description, ni image. Chaque import remplace toutes les notices de la
source. Méthode et précision mesurée : [ARCHIVES.md](ARCHIVES.md).

| Option | Effet |
|:-|:-|
| `--download` | Télécharge l'export JSON du jeu de données (une requête) dans `storage/app/data/archives-sncf/` ; sinon la copie locale est lue. |
| `--file=` | Fichier JSON à lire à la place de la copie locale. |
| `--threshold=` | Score minimal d'une notice rattachée (9 par défaut, sur 11). |
| `--dry-run` | Calcule tout sans écrire. |
| `--report=` | Fichier CSV de toutes les notices jugées, retenues ou non, avec leur score et ses raisons. |

### `vff:import-structures`

Ouvrages d'art des lignes (tunnels, viaducs, grands ponts) : tables `structures` et `line_structures`. Assemble les tronçons
de voie de la BD TOPO en tunnel ou sur pont (cache de tuiles du profil en long, `storage/app/data/altimetrie/bdtopo-tuiles/`),
les nomme par les constructions de la BD TOPO et par Wikidata (nom, dates, protection, Mérimée, image), garde les tunnels et
ponts nommés d'une ligne déposée, puis rattache chaque ouvrage aux lignes dont le tracé le suit (toutes ères), avec ses PK
estimés sur le profil en long. Caches régénérables dans `storage/app/data/ouvrages/` (`.nobackup`). Lit `line_segments`,
`eras` et `line_profile_points`, n'écrit que les deux tables des ouvrages, qu'il remplace à chaque passage. Méthode et
seuils : `App\Services\Structures\StructureImporter`. Environ 2 minutes avec les caches.

| Option | Effet |
|:-|:-|
| `--dry-run` | Calcule tout sans écrire. |
| `--offline` | N'utilise que les caches (tuiles absentes ignorées, Wikidata et Commons non relus). |
| `--refresh-wikidata` | Relit Wikidata au lieu du cache `wikidata.json`. |

### `vff:import-materials`

Séries de matériel roulant et liens ligne x série. Option ajoutée le 22/11 : `--no-planned` ne lit pas les liens directs
vers des lignes des sections « Dessertes réalisées ou prévues » des articles (lus par défaut, après des contrôles de phrase).
Autres options : `--refresh`, `--links-only`, `--no-links`, `--no-attested`, `--dry-run`.

### `vff:media-thumbnails`

Crée les miniatures (320 et 1 280 px) des images déposées par les contributeurs ; `--force` refait aussi celles qui en
ont déjà. Demande GD (JPEG, PNG, WebP) dans l'image PHP.

### `vff:build-schematic-maps`

Plans schématiques de la vue ferroviaire, par ère, gardés en cache ([VUE-FERROVIAIRE.md](VUE-FERROVIAIRE.md)).
Options : `--era=` (année ou liste « 1930,1980 »), `--output=` (fichier du plan GeoJSON, une seule ère).

### `vff:commons-cache-prune`

Entretien du cache disque du relais des vignettes de Wikimedia Commons (`VFF_COMMONS_PROXY`, désactivé par défaut) : retire les
vignettes plus vieilles que `VFF_COMMONS_PROXY_TTL_DAYS` (30 jours), puis les plus anciennes jusqu'à revenir à 90 % de
`VFF_COMMONS_PROXY_CACHE_MB` (2 048 Mo), et les fichiers temporaires de plus d'une heure. Aucune option. Planifiée chaque heure en production quand le relais est activé
([PLANIFICATION.md](PLANIFICATION.md)) ; réglages et nginx dans [DEPLOIEMENT-NGINX.md](DEPLOIEMENT-NGINX.md).

### `vff:commons-allowlist`

Liste des fichiers de Wikimedia Commons que VFF cite, les seuls que le relais des vignettes sert (`VFF_COMMONS_PROXY`).
Sans option : état de la liste (nombre de fichiers, date, adresses retenues par source). `--refresh` la reconstruit depuis `media`,
`accident_images`, `materials.image`, le journal et les propositions en attente (lecture seule de la base ; ensemble Valkey remplacé d'un
coup ; une liste vide ne remplace jamais l'existante). `--dry-run` compte sans rien écrire. À lancer une fois avant d'activer le relais ;
planifiée toutes les dix minutes en production quand le relais est activé ([PLANIFICATION.md](PLANIFICATION.md)) ; fonctionnement dans
[DEPLOIEMENT-NGINX.md](DEPLOIEMENT-NGINX.md).

### `vff:remind-expiring-api-tokens`

Prévient par courriel les membres dont un jeton d'API expire dans 30 jours. Option : `--dry-run` (nombre de comptes et de
jetons concernés, sans envoi).

### `vff:import-signalling-history` et `vff:import-signalling-sources`

Historique daté de la signalisation : le premier lit les articles Wikipédia des lignes, le second les documents relevés
dans `database/data/signalling_history_sources.php`. Les deux ne touchent que `line_signalling_events` et la carte par
ère (`line_signalling_history_map`), et lisent le fichier des corrections sourcées
`database/data/signalling_history_corrections.php` (contrôlé par `App\Services\Signalling\SignallingCorrectionsFile`).

| Option | Effet |
|:-|:-|
| `--dry-run` | Affiche le bilan sans écrire (avec `-v`, les faits ; un fait corrigé y apparaît en fiabilité `hidden`). |
| `--corrections=` | Autre fichier de corrections que celui de `database/data/`. |
| `--line=`, `--limit=`, `--offline`, `--refresh`, `--cache-dir=` | Import Wikipédia seulement : lignes lues, articles du cache. |
| `--supplements=` | Import Wikipédia seulement : autre fichier de faits relus que celui de `database/data/`. |
| `--file=` | Import des documents seulement : autre fichier de faits. |

Faits relus (`database/data/signalling_history_wikipedia_supplements.php`, contrôlé par
`App\Services\Signalling\SignallingSupplementsFile`) : l'import Wikipédia range aussi les faits que l'extracteur prudent
laisse passer et qu'une personne a relus dans l'article de la ligne, d'une gare ou d'une LGV. Chaque fait porte sa phrase
(`quote`, liens ôtés, tirets écrits « - ») ; elle est cherchée dans l'article en cache à chaque import, absente (l'article
a changé) la commande échoue après son bilan et ne range pas le fait. Le fait est rangé comme un fait de Wikipédia (source
Wikipédia, permalien de la révision lue, origine `text`, fiabilité `high` pour un jour ou un mois, `medium` pour une année).
Un complément se neutralise par une correction comme tout fait de Wikipédia. Un complément d'une ligne absente de la base
est ignoré.

La commande centralisée (système `CC` : CCVU, CCR, PCD, rôle `remote_control`) ne se lit que dans ces faits relus : l'extracteur
ne la repère pas dans les textes. Ce n'est pas un cantonnement : un fait `CC` ne remplace aucun BAL, BAPR ou block manuel (et
n'est remplacé que par une autre commande centralisée), et la carte des époques passées la dessine en trait fin pointillé
par-dessus le cantonnement.

Une correction vise un fait par sa ligne, son système, sa date et sa portion, et porte une action, des sources (un
document officiel ou deux sites différents), une citation et une raison :

- `neutralize` : le fait reste en base (`confidence = 'hidden'`, colonne `correction`) mais n'est plus montré (fiche,
  API, carte par ère) ; la raison et les sources se lisent dans la clé `corrections` de
  `GET /api/v1/lines/{line}/signalling-history` ;
- `replace` : idem, et le fait juste (`with`, au format du fichier des documents) est rangé par
  `vff:import-signalling-sources`.

Un fait visé introuvable est une erreur : l'import Wikipédia échoue après son bilan (article changé), celui des
documents échoue sans rien écrire. Une correction d'une ligne absente de la base est ignorée. Un fait corrigé revient
au prochain import si sa correction est retirée du fichier. Première correction : l'ETCS du 12/11/2024 de la LGV
Sud-Est (752000), qui date du basculement des postes et du centre de commande de Lyon, pas de l'ETCS (visé pour 2026).

### `vff:api-contract`

Met à jour l'instantané du contrat de l'API publique (`tests/Fixtures/api-contract.json` : REST, GraphQL, MCP). Les ajouts y
entrent seuls ; `--accept-removals` accepte aussi un retrait ou un changement de type, à annoncer dans
[API-CHANGELOG.md](API-CHANGELOG.md) (le test `ApiContractTest` échoue sinon).

### `vff:export-open-data`

Jeux de données ouverts sous ODbL 1.0, lancés à la main (aucune tâche planifiée) : réseau d'une ère (`lignes`,
`sections`, `gares` en CSV et GeoJSON), `evenements`, `accidents` (avec `accidents-sources.csv` : familles de
sources des accidents relus, sans titre ni auteur), `materiel`, puis `signalisation` (faits datés et état en vigueur
à chaque décennie), `materiel-preserve`, `projets` (projets et étapes), `infrastructure-sncf` (voies, radio, quais,
cantonnement, SNCF Réseau ODbL), `profils` (profils en long mesurés, points, déclivités sourcées) et `redirections`
(gares fusionnées, lignes renommées), chacun avec `LISEZMOI.txt` (fr, en, de), `LICENCE.txt`, `dictionnaire.csv`
(champ par champ : description en trois langues, provenance, licence) et une archive ZIP, plus `catalogue.json` lu par la page `/open-data`. Écrit dans
`public/downloads/open-data/` (servi en statique, ignoré par git). Lecture seule de la base, idempotente (même base
et même `--date`, mêmes octets). Seules les sources autorisées par `database/data/source_rights.php` entrent
(`App\Services\OpenData\OpenDataRights`) ; le reste est retiré ou remplacé par une donnée libre et compté (code des nouveaux jeux :
`App\Services\OpenData\OpenDataSuiteExporter`, champs : `OpenDataDictionary`). Règles propres aux jeux récents : aucun
texte rédigé ni donnée personnelle ; une étape de projet, rédigée par VFF, est attribuée au fournisseur libre du domaine de son lien,
ou sort comme relevé de VFF quand le lien (presse, ministère, collectivité : domaine absent du registre ou `tiers-cites`)
n'est qu'une citation (colonne `link_role`), et reste retirée sans lien ou pour toute autre source (non commerciale, accord) ; un ancien slug qui porte un préfixe de source non libre (`archeo-`) est retiré ; un profil en long
mesuré sur un tracé non libre n'est publié ni en mesures ni en points.

| Option | Effet |
|:-|:-|
| `--era=` | Ère(s) du réseau, répétable ; défaut : l'ère courante. |
| `--all-eras` | Réseau de toutes les ères. |
| `--only=` | `network`, `events`, `accidents`, `materials`, `signalling`, `preserved`, `projects`, `infrastructure`, `profiles`, `redirects`, répétable (défaut : tous). |
| `--lines=` | Échantillon : slugs séparés par des virgules ; écrit dans `storage/app/open-data-sample`, jamais dans le dossier public. |
| `--output=` | Autre dossier de sortie. |
| `--date=` | Date du jeu (AAAA-MM-JJ). |

## Contrôles

### `vff:check-invariants`

Contrôle en lecture seule des invariants de données (`App\Services\DataInvariants::INVARIANTS`) : nombre de cas et
exemples par invariant violé. Options : `--only=` (clés ou familles séparées par des virgules, par exemple
`segments,texts.long_dash`) et `--json=` (écrit aussi le rapport dans ce fichier). Invariants de gares ajoutés le 22/11 :

- `stations.opened_before_lines` : gare ouverte (date au jour, sourcée) plus de 3 jours avant l'ouverture au jour de toutes
  ses lignes à son PK, en ne comparant qu'à des sections de ligne résolues ;
- `stations.ohm_opened_before_lines` et `stations.ohm_opened_off_section` : date d'ouverture venue d'OpenHistoricalMap,
  sans source propre, qui ressemble à la date de la loi d'utilité publique (plus de 6 mois avant toute ouverture de section)
  ou à celle de la première section recopiée.

## Hors commandes

Le battement du planificateur (`vff:scheduler-heartbeat`) est une tâche planifiée, pas une commande artisan : voir
[PLANIFICATION.md](PLANIFICATION.md). Autres changements du 21 au 22/11, décrits dans leur document : tuiles `?fields=map` de l'API, limites GraphQL et corrections
de signalisation en GraphQL ([API.md](API.md)), suite Playwright et tâche de CI ([TESTS-E2E.md](TESTS-E2E.md)), configuration
nginx ([DEPLOIEMENT-NGINX.md](DEPLOIEMENT-NGINX.md)), page de santé et erreurs ([SUIVI-ERREURS.md](SUIVI-ERREURS.md)),
journal de bord ([REDACTION.md](REDACTION.md)), plans du site (`/sitemap.xml`, `App\Services\Seo\SitemapBuilder`).

## Fond de carte libre

Pas de commande artisan : `scripts/basemap-pmtiles.sh [zone] [zoom] [date]` construit l'archive PMTiles
(`DRY_RUN=1` pour mesurer). Détails, réglages et service : [FOND-DE-CARTE.md](FOND-DE-CARTE.md).

## Carte

### `vff:warm-tiles`

Remplit le cache serveur (Valkey) des tuiles de la carte aux petits zooms (0 à 6 par défaut) pour toutes les ères, sous la clé de la route `GET /api/v1/eras/{year}/tiles/{z}/{x}/{y}.mvt`. Sans elle, la première visite après un recalcul des données paie 0,5 à 1 s par tuile (2 à 3 s sous charge) ; une tuile réchauffée vit 24 h. Seules les tuiles qui touchent l'emprise des tracés sont calculées (22 par ère jusqu'au zoom 6, 462 pour les 22 ères). Ne modifie aucune table.

- `--era=` (répétable), `--max-zoom=6` (0 à 10), `--fields=map|all|both` (défaut `map`, ce que lit la carte du site), `--pause-ms=50` et `--statement-timeout=30` (débit borné), `--refresh` (recalcule aussi l'existant), `--dry-run` (compte, ne calcule rien).
- Durée et place mesurées le 23/11/2026 : environ 0,2 à 0,5 s par tuile, 1 Mo gzip par ère (0,5 à 1,3), soit 2 à 3 minutes et une vingtaine de Mo pour `map` ; trois fois plus avec `both`.
- À lancer à la main ou en dernière ligne de l'application du moteur (aucun cron) : `bin/php artisan vff:warm-tiles`. La clé contient la version des données (`map-data:version`, relue toutes les 60 s) : lancer après la dernière écriture.
