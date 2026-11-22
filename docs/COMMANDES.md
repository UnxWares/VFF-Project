# Commandes artisan de données : options et ordre d'emploi

Ce document décrit les commandes `vff:*` entrées ou changées les 20 et 21 novembre 2026. La liste complète se lit
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
| `--report-only` | Mesure la précision du tracé actuel et réécrit les rapports, sans rien reconstruire. |
| `--shard=i/n` | Une ligne sur `n`, pour des processus parallèles. |

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

### `vff:media-thumbnails`

Crée les miniatures (320 et 1 280 px) des images déposées par les contributeurs ; `--force` refait aussi celles qui en
ont déjà. Demande GD (JPEG, PNG, WebP) dans l'image PHP.

### `vff:build-schematic-maps`

Plans schématiques de la vue ferroviaire, par ère, gardés en cache ([VUE-FERROVIAIRE.md](VUE-FERROVIAIRE.md)).
Options : `--era=` (année ou liste « 1930,1980 »), `--output=` (fichier du plan GeoJSON, une seule ère).

### `vff:remind-expiring-api-tokens`

Prévient par courriel les membres dont un jeton d'API expire dans 30 jours. Option : `--dry-run` (nombre de comptes et de
jetons concernés, sans envoi).

## Fond de carte libre

Pas de commande artisan : `scripts/basemap-pmtiles.sh [zone] [zoom] [date]` construit l'archive PMTiles
(`DRY_RUN=1` pour mesurer). Détails, réglages et service : [FOND-DE-CARTE.md](FOND-DE-CARTE.md).
