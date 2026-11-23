<div align="center">

<img src="resources/images/vff.png" alt="Logo VFF" width="180"/>

# VFF - Voies Ferrées de France

<picture>
  <source media="(prefers-color-scheme: dark)" srcset="docs/assets/uwosc/uwosc-badge-maintained-en-couleur-inverse-44px.svg">
  <img src="docs/assets/uwosc/uwosc-badge-maintained-en-couleur-44px.svg" alt="UnxWares Open Source & Community - Supported and maintained by UnxWares" height="44">
</picture>

**L'encyclopédie collaborative du rail français.**

*À la croisée des rails.*

[![PHP](https://img.shields.io/badge/PHP-8.5-777BB4?logo=php&logoColor=white)](https://www.php.net)
[![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)](https://laravel.com)
[![Inertia](https://img.shields.io/badge/Inertia.js-3-9553E9?logo=inertia&logoColor=white)](https://inertiajs.com)
[![Svelte](https://img.shields.io/badge/Svelte-5-FF3E00?logo=svelte&logoColor=white)](https://svelte.dev)
[![Node.js](https://img.shields.io/badge/Node.js-26_LTS-5FA04E?logo=nodedotjs&logoColor=white)](https://nodejs.org)
[![pnpm](https://img.shields.io/badge/pnpm-12-F69220?logo=pnpm&logoColor=white)](https://pnpm.io)
[![Vite](https://img.shields.io/badge/Vite-8-646CFF?logo=vite&logoColor=white)](https://vite.dev)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-18-4169E1?logo=postgresql&logoColor=white)](https://www.postgresql.org)
[![PostGIS](https://img.shields.io/badge/PostGIS-3.7-336791)](https://postgis.net)
[![Valkey](https://img.shields.io/badge/Valkey-9-DC382D)](https://valkey.io)
[![MapLibre](https://img.shields.io/badge/MapLibre_GL_JS-6-396CB2?logo=maplibre&logoColor=white)](https://maplibre.org)
[![Licence](https://img.shields.io/badge/Licence-MIT-green.svg)](LICENCE)

[Présentation](#présentation) ·
[Fonctionnalités](#fonctionnalités) ·
[Documentation](#documentation) ·
[Stack](#stack-technique) ·
[Installation](#installation-locale) ·
[Qualité](#tests-et-qualité) ·
[Contribuer](CONTRIBUTING.md) ·
[Roadmap](ROADMAP.md) ·
[Architecture](ARCHITECTURE.md)

</div>

## Présentation

VFF (Voies Ferrées de France) documente le réseau ferré français, des lignes en service à celles qui ont disparu
il y a plus d'un siècle. Le projet est édité et maintenu par [UnxWares](https://www.unxwares.com) ; le code est
libre (MIT) et les contributions sont ouvertes à tous.

Le site part d'une carte du réseau à chaque décennie, de 1830 à 2030. Les époques passées montrent le réseau au
31 décembre de leur année ; l'époque 2030 montre le réseau au jour même, recalculé à partir des événements datés
(ouvertures, fermetures, électrifications, au jour près quand une source le permet). Chaque donnée garde sa source
et sa licence : SNCF Open Data, IGN, Wikidata, Wikipédia, OpenHistoricalMap, OpenStreetMap, Gallica et les
contributions validées. Les tracés de plusieurs sources sont croisés pour viser le mètre, sans écart aux jonctions
entre lignes.

## Documentation

Le dossier `docs/` contient une note par sujet. Pour reprendre le projet, commencer par `docs/REPRISE.md`.

| Document | Contenu |
|:-|:-|
| [REPRISE.md](docs/REPRISE.md) | État du projet, environnement, contrôles, règles de sécurité, où lire les bilans. |
| [COMMANDES.md](docs/COMMANDES.md) | Options et ordre d'emploi des commandes `vff:*` de données. |
| [API.md](docs/API.md) | Les trois interfaces de données (REST, GraphQL, MCP) : parité, pagination, limites, temps réel. |
| [MCP.md](docs/MCP.md) | Serveur MCP : outils, invites, exemples. |
| [API-CHANGELOG.md](docs/API-CHANGELOG.md) | Changements de l'API par date, contrat versionné (`vff:api-contract`) et règle de dépréciation. |
| [CONTRIBUTIONS.md](docs/CONTRIBUTIONS.md) | Types de contribution, relecture à deux, publication. |
| [REDACTION.md](docs/REDACTION.md) | Rédaction des textes, relecture, format des articles du journal. |
| [METHODE-CLASSIFICATION.md](docs/METHODE-CLASSIFICATION.md) | Règles de classement du trafic et du statut des tronçons. |
| [LINE-SECTIONS.md](docs/LINE-SECTIONS.md) | Sections nommées d'une ligne. |
| [AUDIT-TRONCONS.md](docs/AUDIT-TRONCONS.md), [AUDIT-GARES.md](docs/AUDIT-GARES.md) | Audit automatique des tronçons et des gares : méthode, notes, état. |
| [GRAPHE-ADMIN.md](docs/GRAPHE-ADMIN.md) | Page d'administration du graphe et ses diagnostics. |
| [VUE-FERROVIAIRE.md](docs/VUE-FERROVIAIRE.md) | Plan schématique d'une époque. |
| [FOND-DE-CARTE.md](docs/FOND-DE-CARTE.md) | Fond de carte libre auto-hébergé (PMTiles). |
| [ARCHIVES.md](docs/ARCHIVES.md) | Fonds d'archives rattachés aux lignes. |
| [AUTORISATIONS-SOURCES.md](docs/AUTORISATIONS-SOURCES.md) | Autorisations d'usage des sources tierces. |
| [PLANIFICATION.md](docs/PLANIFICATION.md) | Tâches planifiées, battement et entretien, mise en place de `schedule:run`. |
| [DEPLOIEMENT-NGINX.md](docs/DEPLOIEMENT-NGINX.md) | Configuration nginx de référence pour la production. |
| [SECURITE-CSP.md](docs/SECURITE-CSP.md) | Politique de sécurité du contenu. |
| [SUIVI-ERREURS.md](docs/SUIVI-ERREURS.md) | Page de santé, erreurs du serveur et du navigateur. |
| [SUIVI-PRODUCTION.md](docs/SUIVI-PRODUCTION.md) | Suivi en production : disponibilité avec Kener, erreurs avec GlitchTip. |
| [TESTS.md](docs/TESTS.md) | Suites PHPUnit ciblées et parallèles (`bin/test`), pièges de l'ordre des options et de la base de départ. |
| [TESTS-E2E.md](docs/TESTS-E2E.md) | Suite Playwright de fumée, en local et en CI. |
| `A-FAIRE-*.md` | Chantiers ouverts : fiche ligne, fiche gare, jonctions à 3 m. |
| [AUDIT-2026-05-29.md](docs/AUDIT-2026-05-29.md) | Audit complet de mai 2026 (historique). |

## Fonctionnalités

### Carte

- Carte MapLibre du réseau à 21 époques (1830 à 2030), adresse partageable (`?era=1930`), comparaison de deux
  époques (différences superposées, ou rideau déplaçable entre deux époques sur une même carte, lui aussi dans
  l'adresse) et vue « toutes les époques » où chaque tronçon prend la couleur de sa dernière décennie en service.
- Vue ferroviaire (`/maps?view=schema`) : plan schématique de chaque époque, axes à 0, 45 ou 90 degrés, calculé par
  le serveur depuis le graphe de l'époque ([docs/VUE-FERROVIAIRE.md](docs/VUE-FERROVIAIRE.md)).
- Calques superposables : accidents, passages à niveau, signalisation (y compris son historique par époque), voies
  vertes sur d'anciennes lignes, fond OpenRailwayMap ; fonds historiques de l'IGN et teinte des époques anciennes.
- Zoom jusqu'au niveau 19 sur le fond de carte libre ; le centre et le zoom sont dans l'adresse de `/maps` et se partagent.
- Recherche de lignes, gares et accidents, la même partout (en-tête, carte, annuaires, API) : sans égard pour les accents, les
  tirets et les abréviations, avec tolérance aux fautes de frappe, et une page de résultats complets (`/search`,
  ouverte par Entrée ou « Tous les résultats ») avec les correspondances surlignées et une suggestion « vouliez-vous
  dire » ; itinéraire historique (« Paris - Brest en 1950 ») et comparaison d'un
  trajet entre deux époques.
- Lecture des époques : une barre de lecture parcourt la carte décennie par décennie jusqu'à aujourd'hui (mouvement
  réduit respecté, aussi sur la carte intégrée). Coloration des lignes par vitesse maximale ou par signalisation, grisée
  quand une époque n'a pas de données datées.
- « Qu'y avait-il ici ? » (clic droit, appui long ou bouton) donne ce qui existait en un lieu à l'époque choisie ;
  « Liste de ce qui est affiché » est l'équivalent texte de la carte, avec ses filtres.
- Position par point kilométrique : « 570000 PK 120 » (champ de recherche, page `/search`, `GET /api/v1/lines/{line}/at-pk`)
  donne les gares autour, la commune et un point sur la carte.
- Carte intégrable sur un autre site (iframe).

### Fiches

- Lignes (`/lines/{slug}`) : tracé par période, schéma de la ligne par PK (gares, bifurcations, passages à niveau,
  ouvrages, voies, énergie, signalisation et vitesses, par époque), tunnels, viaducs et grands ponts dans l'ordre des PK
  (IGN BD TOPO et Wikidata), histoire de l'électrification par section, bibliographie « Pour aller plus loin »
  (catalogue de la BnF, références de Wikipédia, Wikidata), schéma « Où et quand » (une bande par phase, mises bout à bout le
  long de la ligne, relié à la carte de la phase), chronologie datée, frise par décennie, gares par époque, longueur,
  électrification (ligne dite électrifiée quand la majorité de ses tronçons en service l'est), vitesses maximales par PK
  (SNCF Réseau, ODbL) et changements de vitesse datés lus dans Wikipédia, signalisation
  par PK, nombre de voies et liaison radio sol-train (GSM-R) par portion de PK (SNCF Réseau, ODbL), profil en long de toute
  la ligne (altitudes de l'IGN, déclivité maximale mesurée sur la voie ou, à défaut, reprise de l'infobox de Wikipédia ou
  de SNCF Réseau avec la source et la part de ligne couverte, crémaillères ; là où la base de l'IGN n'a pas la voie,
  altitude estimée d'après le terrain et étalonnée sur le LiDAR HD, avec son erreur, en cours de mise en service), matériel roulant avec photos, passages à niveau, voies
  vertes, archives de Gallica et des Archives SNCF (titre, classement et lien, jamais l'image), galerie de médias
  crédités, sources, et « histoires » d'archives sur un événement.
- Gares (`/stations/{slug}`) : ouverture, fermeture, lignes desservies et années de desserte, quais (longueur et PK, SNCF Réseau), frise, mini-carte, et la vie de la gare en une chronologie
  datée (événements regroupés, dessertes, noms, accidents proches, vues aériennes) ; fréquentation annuelle (SNCF Gares &
  Connexions, ODbL), noms successifs, accessibilité et services (ascenseurs, assistance, vélos), desserte d'un mardi type
  (horaires ouverts SNCF et Transilien), patrimoine protégé du bâtiment, gares voisines par PK, lectures conseillées ;
  une date déduite de la ligne ou connue à la décennie seulement est dite telle quelle. Une gare fermée aux voyageurs
  mais encore desservie par le fret (Onnaing) est dite « fret seul » ; sur la carte d'une époque, une gare fermée
  n'apparaît fermée qu'à l'époque de sa fermeture, puis disparaît. Une ouverture connue à un intervalle s'écrit « entre A et B ».
- Matériel roulant (`/materials`) : 885 séries tirées de Wikidata, avec photo Commons créditée, une frise par traction
  (`/materials/timeline`) et le matériel préservé des musées, associations et lignes touristiques (`/materials/preserved`). Le matériel
  d'une ligne vient de liens attestés (Wikidata, articles cités) et, depuis le 21/11/2026, des relations
  (Intercités, TER) qui la parcourent : la ligne d'Argentan à Granville affiche ainsi les X 72500, l'AGC et le
  Régiolis. Les périodes de circulation sont datées quand une source le dit ; la fiche d'une série garde ses rames
  remarquables (le TGV Sud-Est 01 « Patrick », qui traverse l'écran quand on tape son nom hors d'un champ de saisie).
- Accidents (`/accidents`) : fiches tirées de Wikidata, de Wikipédia et des rapports du BEA-TT, avec la confiance
  de chaque fiche, une page de liste filtrable et un calque de carte. Pour 548 fiches, un contexte sourcé (archives,
  enquêtes, presse de l'époque, images de Commons) et les corrections de lieu, de date et de bilan que ces sources
  imposent, chacune avec sa référence. Une fiche corrigée prend le nom de sa nouvelle date ou commune, retenu dans
  `database/data/accident_slugs.php`, et un accident que seule la presse décrit s'écrit à la main
  (`database/data/accident_records_manual.php`, voir [docs/COMMANDES.md](docs/COMMANDES.md)). Une personne heurtée par un
  train n'est publiée que si le BEA-TT ou un contexte sourcé l'établit ; les doublons de la liste de Wikipédia sont fondus
  dans la fiche sourcée, avec redirection.
- Territoire et histoire : « Le rail à <commune> » (`/communes/{slug}`), fiches de départements avec la longueur de rail
  et les gares décennie par décennie, compagnies historiques (`/companies`), projets de lignes pas encore en service
  (`/projects`), chemins de fer touristiques, vélorails et voies vertes (`/tourist-railways`), patrimoine ferroviaire
  protégé (`/heritage`), « Autour de moi » (`/nearby`), chronologie du rail en France depuis 1827 (`/chronology`), « Ce
  jour-là » (`/on-this-day`), glossaire (`/glossary`).
- Accueil : une image d'archive du rail renouvelée toutes les 30 minutes (« Le rail en images »), les campagnes de
  réouverture et de maintien d'une ligne avec leurs sources chiffrées (AngouLim, Hirondelles, Argentan - Granville).
- Journal de bord (`/journal`) : articles longs sur des lignes emblématiques, écrits en Markdown dans
  `resources/content/journal` (français, anglais, allemand), flux Atom et RSS, lus aussi par l'API et le serveur MCP
  ([docs/REDACTION.md](docs/REDACTION.md)).
- Quiz (`/quiz`) : reconnaître une ligne ou un matériel, ou répondre à des questions de culture ferroviaire dont chaque
  réponse est sourcée.
- Le réseau en chiffres (`/network`) : longueurs, gares, électrification, fermetures et accidents par décennie ;
  « Ce que sait l'encyclopédie » (`/network/coverage`) mesure la couverture des données ; les accidents en chiffres
  (`/accidents/statistics`) ne devinent jamais un bilan inconnu.
- Données ouvertes (`/open-data`) : jeux sous ODbL (réseau par époque en CSV et GeoJSON, événements datés, accidents,
  matériel roulant), limités aux sources dont la licence le permet, avec leur archive ZIP et leur licence.
- Impression : les fiches s'impriment et s'enregistrent en PDF A4 propre.

### Contributions

- Comptes (inscription, profil, réputation, notifications, export et suppression du compte).
- Propositions de tracé (éditeur sur la carte avec fonds historiques), de correction de segment, d'événement daté,
  d'image Wikimedia Commons, de lien ligne × matériel et de nouvelle série.
- Toute proposition attend deux approbations de validateurs avant publication ; modération, journal d'audit et
  signalement d'une erreur depuis chaque fiche ([docs/CONTRIBUTIONS.md](docs/CONTRIBUTIONS.md)).

### API et serveur MCP

- API REST publique sans clé : GeoJSON et tuiles MVT par époque, fiches complètes, annuaires, tronçons,
  événements, médias, matériel, accidents, passages à niveau, itinéraires, exports GTFS, GPX et KML. Les tuiles des
  époques acceptent `?fields=map` (seulement les propriétés dont la carte du site a besoin, environ 60 % d'octets de
  moins). Le GTFS passe sans erreur le validateur de MobilityData, ses trajets suivent la voie d'une autre ligne quand elle
  est partagée jusqu'au terminus ; ses horaires sont fictifs. Spécification
  OpenAPI générée par Scramble, référence sur `/docs/api` (Scalar) et `/docs/swagger` (Swagger UI), exemples curl,
  JavaScript et Python. Quotas de 300 requêtes par minute sans jeton, davantage avec un jeton personnel ; webhooks
  signés.
- GraphQL (Lighthouse) en complément : lecture de tout le modèle et mutations de contribution ; référence Magidoc
  sur `/docs/graphql` et GraphiQL sur `/graphiql`.
- Serveur MCP en lecture seule (`POST /mcp`, ou `php artisan mcp:start vff` en local) pour que les assistants IA
  interrogent l'encyclopédie avec ses sources et ses licences : 47 outils, dont `what_was_here` (ce qui existait en
  un lieu à une époque), `network_in_area`, `compare_line_eras`, `line_speeds`, `line_structures`, `station_heritage`,
  `network_chronology`, `on_this_day` et `tourist_railways` ; un fait venu d'une source non commerciale n'est jamais
  servi ([docs/MCP.md](docs/MCP.md)).
- Les chiffres du réseau par décennie sont servis par `GET /api/v1/stats/network`, les vitesses d'une ligne par
  `GET /api/v1/lines/{line}/speeds` et `Line.speeds`, la chronologie d'une gare par sa fiche (`chronology`).
- Les nouvelles pages ont leur équivalent en REST, sans clé : `/api/v1/chronology`, `/api/v1/on-this-day`,
  `/api/v1/tourist-railways`, `/api/v1/heritage`, `/api/v1/projects`, `/api/v1/companies`, `/api/v1/stats/coverage`,
  `/api/v1/accidents/statistics`, et par ligne ou par gare `/structures`, `/schematic`, `/at-pk` et `/heritage` ; le
  contrat versionné suit chaque ajout ([docs/API-CHANGELOG.md](docs/API-CHANGELOG.md)).
- Le nombre de voies et la liaison radio d'une ligne sont servis par `GET /api/v1/lines/{line}/infrastructure`,
  `Line.infrastructure` et l'outil `line_infrastructure`, les quais par la fiche d'une gare. Un instantané versionné
  du contrat de l'API fait échouer un test si un champ disparaît sans avoir été annoncé dans
  [docs/API-CHANGELOG.md](docs/API-CHANGELOG.md).
- Temps réel préparé et désactivé par défaut (`REALTIME_ENABLED`) : Laravel Reverb, canaux privés, abonnements GraphQL
  aux contributions ; les connexions sont coupées quand un compte est suspendu ou supprimé ([docs/API.md](docs/API.md)).
- Une réponse de l'API donne la provenance et la licence de chaque donnée ; un tracé dont la licence interdit la
  redistribution n'est pas exporté, et le profil en long ne publie pas non plus ses positions.
- Les audits (notes des tronçons et des gares avec leurs preuves), le profil en long point par point et l'altitude à
  un PK sont lus par les trois interfaces, avec la même pagination ([docs/API.md](docs/API.md)). GraphQL refuse une
  requête au-delà de 5 000 points de complexité ou de 15 niveaux, de 400 requêtes groupées ou de 2 Mio de corps
  (413). Les corrections sourcées de la signalisation datée sont servies avec le fait qu'elles masquent. La recherche, la même
  que celle du site, est lue par les trois interfaces.

### Site

- Interface en français, anglais et allemand ; thème sombre ; application installable (PWA) qui garde lisibles hors
  ligne les pages publiques, les images et les tuiles déjà consultées ; un fond de carte libre (OpenStreetMap, PMTiles,
  6,2 Go pour toute la France) sert les cartes hors ligne ([docs/FOND-DE-CARTE.md](docs/FOND-DE-CARTE.md)).
- Accessibilité : audit RGAA 4.1.2 interne de 32 pages, totalement conforme, publié dans la déclaration
  d'accessibilité.
- Exploitation : plans du site (`/sitemap.xml`, un plan par famille de pages), en-têtes de sécurité sur toutes les
  réponses, dont une politique de sécurité du contenu bloquante en production ([docs/SECURITE-CSP.md](docs/SECURITE-CSP.md)), `/up` et `/up/details` pour la disponibilité, erreurs du
  serveur et du navigateur journalisées ou envoyées à GlitchTip ou Bugsink sans donnée personnelle
  ([docs/SUIVI-ERREURS.md](docs/SUIVI-ERREURS.md)) ; Kener pour la disponibilité et GlitchTip pour les erreurs sont choisis et
  décrits dans [docs/SUIVI-PRODUCTION.md](docs/SUIVI-PRODUCTION.md) ; configuration nginx de référence
  ([docs/DEPLOIEMENT-NGINX.md](docs/DEPLOIEMENT-NGINX.md)), tâches planifiées avec un battement lu par `/up/details`
  ([docs/PLANIFICATION.md](docs/PLANIFICATION.md)). Les flux du journal et les plans du site sont servis sans session ni
  cookie.

### En cours et prévu

Le détail est dans [ROADMAP.md](ROADMAP.md).

- Audits des données : le contrôle automatique des tronçons (`vff:audit-segments`) et des gares
  (`vff:audit-stations`), avec leurs pages de relecture et leur API, est écrit et réglé sur des contrôles à la main
  ([docs/AUDIT-TRONCONS.md](docs/AUDIT-TRONCONS.md), [docs/AUDIT-GARES.md](docs/AUDIT-GARES.md)) ; reste à repasser
  les ères passées et les gares sur la base complète.
- Matériel : les relations ambiguës dont aucun itinéraire proche ne tranche, et le retrait de deux liens de Z 20500 jugés faux à la relecture.
- Moteur des tracés et jonctions : les règles de raccords en équerre lissés, de bouts prolongés jusqu'à la gare et de
  raccords pendants retirés, sont appliquées au réseau depuis la nuit du 22 au 23/11 et les jonctions latérales sont en
  cours d'application ; restent les retraits à la main de trois fragments (arrêtés par prudence à Domfront), la fusion des lignes du même couloir et le recalcul des gares et de
  leurs ères.
- Fusion des sources de géométrie à 1 m : l'erreur de chaque source (vecteurs, photos aériennes, LiDAR HD) est mesurée et la
  fusion validée sur six lignes ; un morceau n'est dit « précis » que sous 3 m d'erreur estimée. À faire : pilote sur le
  Calvados, passe nationale, puis un modèle sur photos aériennes centré sur l'axe de la voie.
- Médias : droits propres à chaque image, comblement par Wikidata et Commons, premiers fonds ouverts (autochromes,
  cartes postales d'archives, Rosalis, Pireneas) appliqués ; photos de rue Panoramax triées par un modèle de vision, import
  en attente ; d'autres fonds d'images libres à intégrer.
- Altitude de la voie estimée d'après le terrain et étalonnée sur le LiDAR HD : code commité, à lancer sur la base.
- Signalisation datée lue dans Wikipédia (mises en service de BAL et de BAPR par section) et exports de données ouvertes
  complets par époque : code commité, à lancer sur la base.
- Temps réel en production (lancer Reverb et le proxy WebSocket, code prêt) ; relais des vignettes de Wikimedia Commons
  (`VFF_COMMONS_PROXY`, code prêt, désactivé) ; décision sur le fond de carte libre en ligne (`VFF_BASEMAP_ONLINE`).
- Adresses de lignes neutres : `vff:neutralize-line-slugs` retire le préfixe de source des adresses et garde une
  redirection 301 de chaque ancienne (acceptées par GraphQL et le MCP) ; le renommage reste à appliquer à la base.
- Mise en production : messagerie de `vff-project.org`, hébergement sur l'infrastructure d'UnxWares, installation de Kener et
  de GlitchTip (la part application et la documentation sont faites), sauvegarde de la base de production.
- Reconstitutions 3D de lieux disparus à partir de photos d'archives (recherche, pas commencée).

## Stack technique

| Couche | Outil | Rôle |
|---|---|---|
| Backend | [Laravel 13](https://laravel.com) sur PHP 8.5 | Application, API REST, files d'attente, planificateur |
| Base de données | [PostgreSQL 18](https://www.postgresql.org) + [PostGIS 3.7](https://postgis.net) | Géométries `LINESTRING` / `POINT` (SRID 4326), tuiles `ST_AsMVT`, graphe du réseau en tables |
| PostGIS dans Eloquent | [Clickbar Magellan 2](https://github.com/Clickbar/laravel-magellan) | Casts et requêtes spatiales |
| Cache et files | [Valkey 9](https://valkey.io) | Fork BSD de Redis, compatible RESP |
| Pont front / back | [Inertia.js 3](https://inertiajs.com) | Pages Svelte servies par les routes Laravel |
| Front | [Svelte 5](https://svelte.dev) (runes) | Composants de l'interface |
| Cartographie | [MapLibre GL JS 6](https://maplibre.org) | Carte vectorielle, couches par époque |
| Traductions | [i18next 27](https://www.i18next.com) | Chaînes de `lang/{fr,en,de}` partagées par Inertia, exposées aux composants par un store Svelte |
| GraphQL | [Lighthouse 6](https://lighthouse-php.com) | Schéma `graphql/schema.graphql` |
| MCP | [laravel/mcp](https://laravel.com/docs/mcp) | Serveur pour assistants IA, `app/Mcp/` |
| Documentation d'API | [Scramble](https://scramble.dedoc.co), [Scalar](https://scalar.com), Swagger UI, [Magidoc](https://magidoc.js.org) | OpenAPI généré depuis le code, références REST et GraphQL |
| Build | [Vite 8](https://vite.dev), Node.js 26 (LTS active), [pnpm 12](https://pnpm.io) | Front et service worker |
| Authentification | [Laravel Sanctum](https://laravel.com/docs/sanctum) | Sessions et jetons d'API |
| Conteneurs | [Podman](https://podman.io) sans root (Docker possible) | PHP, Composer et Node lancés par `bin/php`, `bin/composer`, `bin/pnpm` ; base et Valkey par `docker-compose.yml` |
| Tests | [PHPUnit 13](https://phpunit.de) + [ParaTest](https://github.com/paratestphp/paratest), [Vitest 5](https://vitest.dev), [Infection](https://infection.github.io) | Tests unitaires et fonctionnels en parallèle, tests JS, tests de mutation |
| Analyse et style | [PHPStan 2](https://phpstan.org) + [Larastan 3](https://github.com/larastan/larastan), [Pint](https://github.com/laravel/pint), [ESLint 10](https://eslint.org) + eslint-plugin-svelte, [Prettier](https://prettier.io), [svelte-check](https://github.com/sveltejs/language-tools), [Lefthook](https://lefthook.dev) | Contrôles bloquants en CI et avant commit |
| Extraction des voies | [PyTorch](https://pytorch.org), U-Net ResNet-34 | Recalage des tracés disparus sur les photos aériennes IGN 1950-1965, lecture de l'axe de plateforme dans le LiDAR HD et affinage des tracés grossiers : [UnxWares/VFF-Rail-Extraction](https://github.com/UnxWares/VFF-Rail-Extraction) |
| Sources | [SNCF Open Data](https://ressources.data.sncf.com) (ODbL), [IGN](https://geoservices.ign.fr) (Licence Ouverte 2.0), [OpenStreetMap](https://www.openstreetmap.org) (ODbL), [Wikidata](https://www.wikidata.org) et [OpenHistoricalMap](https://www.openhistoricalmap.org) (CC0), [Wikipédia](https://fr.wikipedia.org) et [Wikimedia Commons](https://commons.wikimedia.org), [Gallica](https://gallica.bnf.fr), [lignes-oubliees](https://www.lignes-oubliees.com) | Licence de chaque source déclarée dans `database/data/source_rights.php`, vérifiée par `vff:source-rights --check` |

## Installation locale

Rien n'est à installer sur l'hôte en dehors de Podman (sans sudo) ou de Docker, de git et de make. PHP, Composer et
Node tournent dans des conteneurs jetables lancés par les scripts de `bin/` :

| Script | Image | Remplace |
|---|---|---|
| `bin/php` | `localhost/vff-php:8.5`, construite depuis `docker/php/Containerfile` (PHP 8.5 CLI, `pdo_pgsql`, `intl`, `zip`, `bcmath`, `pcntl`, Composer 2) | `php …` |
| `bin/composer` | la même image | `composer …` |
| `bin/pnpm` | `docker.io/library/node:26-slim` ; pnpm à la version exacte de `packageManager` (lancé par `npx`, Node 26 ne livrant plus corepack) | `pnpm …` |

Les conteneurs tournent avec votre UID (`--userns=keep-id` sous Podman, donc aucun fichier `root` dans le dépôt)
et sur le réseau de l'hôte : la base reste joignable sur `127.0.0.1:5432` et Vite sert sur `localhost:5173`.

```bash
git clone https://github.com/UnxWares/VFF-Project.git
cd VFF-Project

make setup        # .env, image PHP, services (PostgreSQL + PostGIS, Valkey, Adminer), dépendances, clé, migrations
make dev          # artisan serve (:8000) + file d'attente + Vite (:5173) ; Ctrl+C arrête tout
```

Le site répond sur http://localhost:8000 et Adminer sur http://localhost:8080.

Les mêmes étapes, à la main :

```bash
cp .env.example .env
make php-image                     # podman build -t localhost/vff-php:8.5 docker/php
make up                            # podman-compose up -d (ou docker compose up -d)
bin/composer install
bin/pnpm install
bin/php artisan key:generate
bin/php artisan migrate
make dev
```

- Le Makefile prend `php`, `composer` et `pnpm` de l'hôte s'ils existent, sinon les scripts de `bin/` ;
  `make tools` affiche ce qu'il a trouvé.
- Variables utiles : `CONTAINER_ENGINE=docker`, `VFF_PHP_IMAGE=…`, `VFF_NODE_IMAGE=…`, `VFF_NPM_CACHE=…` (cache npm
  de `bin/pnpm`, par défaut `~/.cache/vff-npm`), `make up DC="docker compose"`.
- Seules `VFF_TEST_DB` et `DB_DATABASE` passent de l'hôte au conteneur PHP ; le reste de la configuration est dans
  `.env` (et `phpunit.xml` pour les tests).
- Fedora et SELinux : les scripts désactivent l'étiquetage (`label=disable`) pour le montage du dépôt, et le volume
  d'initialisation de PostgreSQL utilise `:z`.
- pnpm n'exécute aucun script d'installation des dépendances (`allowBuilds` dans `pnpm-workspace.yaml`) et ne
  résout pas un paquet publié depuis moins d'un jour.

### Mise à jour des services

Après un changement d'image dans `docker-compose.yml`, `make up` recrée le conteneur. Une base créée avec PostGIS
3.6 se met à niveau une fois, dans `make psql` : `ALTER EXTENSION postgis UPDATE;`. Les données de Valkey 8 se
relisent sans conversion avec Valkey 9.

### Production

```bash
bin/pnpm build
bin/php artisan optimize
```

Le planificateur Laravel déclare les tâches de production (recalcul quotidien de l'époque 2030, comptes
inactifs, purge des journaux) ; aucune n'est lancée sur un poste de développement.

## Commandes utiles

```bash
make help                    # toutes les cibles
make dev                     # serve + file d'attente + Vite
make up / make down          # services de données
make psql / make valkey-cli  # consoles
make tinker                  # REPL Laravel
make migrate                 # migrations
make docs-graphql            # après une modification du schéma GraphQL : référence Magidoc
bin/php artisan list vff     # commandes du projet (imports, moteur de la carte, audits, contrôles)
bin/php artisan vff:check-invariants   # invariants des données, en lecture seule
bin/php artisan vff:export-open-data   # jeux de données ouverts (ODbL) dans public/downloads/open-data
# options et ordre des commandes de données : docs/COMMANDES.md
```

La documentation de l'API REST (`/docs/api`, `/docs/swagger`, `/docs/api.json`) se met à jour toute seule à
partir des contrôleurs.

## Tests et qualité

Les tests fonctionnels vident et recréent leur base à chaque passage. Ils tournent donc sur une base dédiée dont le
nom finit par `_test` ; `tests/CreatesApplication.php` refuse toute autre base, et `App\Support\DatabaseGuard` refuse
`migrate:fresh`, `db:wipe` et leurs voisines hors des bases `_test`.

```bash
make test-db                 # une fois : base vff_test et ses extensions
make test                    # PHPUnit en 4 processus
VFF_TEST_DB=vff_moi_test bin/php artisan test --parallel --processes=4 --recreate-databases
bin/pnpm test                # vitest
bin/pnpm test:e2e            # Playwright (fumée) contre le serveur de dev, voir docs/TESTS-E2E.md
```

Plus de 10 000 tests PHPUnit et 1 200 tests vitest passent au 23/11/2026.

| Outil | Commande | Ce qu'il vérifie |
|---|---|---|
| Pint | `make pint-dirty`, `bin/php vendor/bin/pint --test` | Style PHP de tout le dépôt |
| PHPStan + Larastan, niveau 6 | `make analyse` | `app/`, `database/`, `routes/`, `tests/` : types, PHPDoc, méthodes et colonnes inconnues, code mort ; dette gelée dans `phpstan-baseline.neon`, aucune erreur nouvelle admise |
| Prettier | `bin/pnpm lint` | Style du front, des tests JS, des scripts et des fichiers de configuration |
| ESLint (configuration plate) | `bin/pnpm lint:js` | Variables inutilisées ou non définies, erreurs Svelte 5 |
| svelte-check | `bin/pnpm check` | Types du front (JSDoc, `jsconfig.json`) ; dette gelée dans `svelte-check-baseline.json` |
| Contrôles du projet | `make test-static` | Pas de tiret long dans les textes, clés de traduction du front présentes, palette de la carte, routes et pages Inertia |
| Traductions | `bin/php artisan vff:i18n-check --strict` | Clés et variables identiques en français, anglais et allemand |
| Sources | `bin/php artisan vff:source-rights --check` | Licence déclarée pour chaque source |
| Failles | `bin/composer audit`, `bin/pnpm audit` | Dépendances PHP et JS sans faille connue |

`make check` enchaîne la plupart de ces contrôles. `lefthook.yml` en rejoue une partie avant chaque commit (Pint,
Prettier, ESLint, tirets interdits, traductions) et avant chaque push (svelte-check, contrôles statiques) ;
installation depuis l'hôte : `bin/pnpm install`, puis `node_modules/.bin/lefthook install` (voir
[CONTRIBUTING.md](CONTRIBUTING.md)).

L'intégration continue (`.github/workflows/ci.yml`) lance sur chaque PR : `composer audit`, Pint, traductions,
registre des sources, PHPStan, contrôles statiques et PHPUnit sur un service `postgis/postgis:18-3.7` ; puis
`pnpm audit`, Prettier, ESLint, vitest, svelte-check et le build Vite sur Node 26, et une tâche Playwright de
fumée sur un jeu de données minimal (`E2eSeeder`, [docs/TESTS-E2E.md](docs/TESTS-E2E.md)). Les actions sont épinglées par
SHA de commit et chaque tâche n'a que le droit de lire le dépôt.

## Contribuer

- Documenter une ligne, une gare ou un matériel depuis le site, une fois inscrit : chaque proposition est relue par
  deux validateurs.
- Apporter des médias libres (photos d'archives, plans, documents) : voir [CONTRIBUTING.md](CONTRIBUTING.md),
  section Médias.
- Contribuer au code : fork, branche, PR, en suivant les conventions de [CONTRIBUTING.md](CONTRIBUTING.md).

## Communauté et liens

- Site : [vff-project.org](https://vff-project.org) (mise en ligne à venir)
- Discord : https://discord.com/invite/6SwTfXBx4h
- Instagram : [@vff_project](https://www.instagram.com/vff_project)
- GitHub : [UnxWares/VFF-Project](https://github.com/UnxWares/VFF-Project)
- Éditeur : [UnxWares](https://www.unxwares.com)
- Contact : `contact@vff-project.org` (la messagerie du domaine n'est pas encore ouverte)

## Soutenir le projet

Les dons passeront par UnxWares Open Source & Community (UWOSC), l'association soutenue par UnxWares pour ses
projets libres, en cours de création. La page `/donate` est en place ; la plateforme (Patreon ou HelloAsso) sera
choisie avec le statut de l'association.

## Licence

Le code est sous licence [MIT](LICENCE).

Les données gardent la licence de leur source : ODbL pour OpenStreetMap et SNCF Open Data, Licence Ouverte 2.0
pour l'IGN, CC0 pour Wikidata et OpenHistoricalMap, CC BY-SA pour les textes cités de Wikipédia. Chaque réponse de
l'API donne la mention à reproduire.

Les polices (Inter, Lato, Gabarito, Montserrat), servies par les paquets `@fontsource/*` sans CDN, sont sous
[SIL Open Font License 1.1](https://openfontlicense.org) ; textes dans `resources/fonts/OFL-*.txt`.

Les tuiles OpenRailwayMap du calque facultatif sont sous [CC BY-SA 2.0](https://creativecommons.org/licenses/by-sa/2.0/).

Les médias contribués restent à leurs auteurs, qui les publient sous [CC BY-SA 4.0](https://creativecommons.org/licenses/by-sa/4.0/)
sauf mention contraire.

## Crédits

Projet édité et maintenu par [UnxWares](https://www.unxwares.com), avec ses contributeurs. Fond cartographique ©
contributeurs [OpenStreetMap](https://www.openstreetmap.org/copyright) ; calque ferroviaire © contributeurs
[OpenRailwayMap](https://www.openrailwaymap.org/). Les crédits complets sont sur les pages `/credits/tech` et
`/credits/media` du site.
