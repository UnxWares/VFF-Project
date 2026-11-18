<div align="center">

<img src="resources/images/vff.png" alt="Logo VFF" width="180"/>

# VFF - Voies Ferrées de France

**La plus grande encyclopédie collaborative du rail français.**

*À la croisée des rails.*

[![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?logo=php)](https://www.php.net)
[![Svelte](https://img.shields.io/badge/Svelte-5-FF3E00?logo=svelte)](https://svelte.dev)
[![Inertia](https://img.shields.io/badge/Inertia.js-3-9553E9?logo=inertia)](https://inertiajs.com)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-18-4169E1?logo=postgresql)](https://www.postgresql.org)
[![PostGIS](https://img.shields.io/badge/PostGIS-3.6-336791)](https://postgis.net)
[![Magellan](https://img.shields.io/badge/Magellan-2.1-336791)](https://github.com/Clickbar/laravel-magellan)
[![Valkey](https://img.shields.io/badge/Valkey-8-DC382D?logo=redis&logoColor=white)](https://valkey.io)
[![MapLibre](https://img.shields.io/badge/MapLibre_GL_JS-5-396CB2)](https://maplibre.org)
[![i18next](https://img.shields.io/badge/i18next-26-26A69A)](https://www.i18next.com)
[![Licence](https://img.shields.io/badge/Licence-MIT-green.svg)](LICENCE)

[Présentation](#-présentation) ·
[Fonctionnalités](#-fonctionnalités) ·
[Stack](#-stack-technique) ·
[Installation](#-installation-locale) ·
[Contribuer](CONTRIBUTING.md) ·
[Roadmap](ROADMAP.md) ·
[Architecture](ARCHITECTURE.md)

</div>

---

## 📖 Présentation

**VFF (Voies Ferrées de France)** est une plateforme en ligne créée par des passionnés du rail. Notre mission : **documenter, cartographier et partager** l'intégralité du patrimoine ferroviaire français - qu'il s'agisse des lignes en service aujourd'hui ou de celles qui ont disparu il y a plus d'un siècle.

Au cœur du projet : une **carte interactive temporelle** qui permet de remonter le temps et de visualiser le réseau ferré tel qu'il existait en **1990, 2000, 2010, 2020** - et tel qu'il se dessine aujourd'hui, dans un snapshot vivant **2030** qui évoluera jusqu'à cette date avant d'être figé à son tour.

Chaque ligne est documentée par :

- son **tracé géoréférencé** (en service, voie unique, abandonnée, déposée, rasée) ;
- son **matériel roulant historique** (locomotives, automotrices, rames qui l'ont parcourue, en photos) ;
- ses **dates clés** (inauguration, électrification, fermeture, démantèlement) ;
- ses **archives** (photographies, plans, documents datés) ;
- à terme, des **reconstitutions 3D assistées par IA** des lieux disparus, générées à partir des éléments existants.

Le projet est **100 % collaboratif** : passionnés, archivistes, photographes, historiens locaux et développeurs sont invités à enrichir la base. Une **API publique** permettra à d'autres développeurs de construire leurs propres outils par-dessus.

---

## ✨ Fonctionnalités

### Disponibles aujourd'hui
- [x] Page d'accueil enrichie : header parallax TGV, sections Genèse / Maps / Features / Timeline interactive (5 époques) / Galerie d'archives / Stats / Contribute teaser / Final CTA
- [x] Pages internes complètes : `/whoarewe`, `/maps` (teaser), `/contribute`, `/donate`, `/api`, `/legal`, `/privacy`, `/use-conditions`
- [x] **Internationalisation FR / EN / DE** (i18next + svelte-i18next) avec détection auto via `Accept-Language` + cookie persistant 1 an + endpoint `GET /locale/{code}`
- [x] **Dark mode** complet (cookie 1 an) avec design tokens CSS custom properties
- [x] **Modèle de données ferroviaire** complet (PostgreSQL 18 + PostGIS 3.6 via Magellan) : eras, lignes, segments `LINESTRING`, gares `POINT`, matériels, événements, médias polymorphes, contributions
- [x] **Service d'import OSM** (Overpass API) - squelette : `php artisan vff:import-osm --region=FR`
- [x] Footer riche avec liens communauté, légal et partenaires
- [x] Performance hero : `background.png` 2.7 MB → WebP 179 KB desktop / 117 KB tablet / 63 KB mobile
- [x] Accessibilité : `prefers-reduced-motion`, focus-visible, `all: unset` sur les buttons icônes
- [x] **Tracés des lignes disparues recalés par un réseau de neurones** : modèle de détection des voies ferrées entraîné par VFF (U-Net, encodeur ResNet-34 pré-entraîné sur ImageNet, spécialisé sur 1 653 vignettes de photos aériennes IGN 1950-1965 étiquetées automatiquement à partir de lignes au tracé connu), exécuté sur carte graphique (PyTorch, CUDA) le long d'environ 12 000 km de tracés approximatifs. Écart médian de 2,2 m au tracé réel sur des lignes de contrôle ; résultat versé comme source `ign-historique` dans la fusion des tracés. Détails : [ml/rail-extraction/README.md](ml/rail-extraction/README.md)

### En développement (voir [ROADMAP.md](ROADMAP.md))
- [ ] Carte interactive MVP (MapLibre GL JS + tuiles OSM)
- [ ] Sélecteur temporel fonctionnel par décennie (1990 / 2000 / 2010 / 2020 / 2030)
- [ ] Couche optionnelle [OpenRailwayMap](https://www.openrailwaymap.org/) (infra, électrification, vitesses)
- [ ] Fiche détaillée par ligne (matériel, dates, médias)
- [ ] Système de contribution communautaire avec validation par les pairs
- [ ] Encyclopédie du matériel roulant
- [ ] API publique REST + GeoJSON + MVT (spécification OpenAPI par Scramble, documentée avec Scalar sur `/docs/api` et Swagger UI sur `/docs/swagger`)
- [ ] API GraphQL pour l'admin (Lighthouse, Phase 7)
- [ ] [Apache AGE](https://age.apache.org/) - extension graphe pour pathfinding historique (Phase 8)
- [ ] Reconstitutions 3D IA de lieux ferroviaires disparus (Phase 9)
- [ ] Mode hors-ligne (PWA)
- [ ] Extension historique 1900 → 1980 (Phase 10)

---

## 🧰 Stack technique

| Couche | Techno | Pourquoi |
|---|---|---|
| **Framework backend** | [Laravel 13](https://laravel.com) | Dernière stable, structure minimaliste `bootstrap/app.php`, support long terme |
| **Langage backend** | PHP 8.5 | Types stricts, enums backés, performance |
| **Base de données** | [PostgreSQL 18](https://www.postgresql.org) + [PostGIS 3.6](https://postgis.net) + [Apache AGE](https://age.apache.org/) (Phase 8) | Stockage natif `LINESTRING` / `POINT` (SRID 4326), requêtes spatiales (`ST_Intersects`, `ST_Within`, `ST_Length`, `ST_AsMVT`…) **et requêtes graphe** via Cypher pour pathfinding historique |
| **PostGIS ↔ Laravel** | [Clickbar Magellan 2.1](https://github.com/Clickbar/laravel-magellan) | Casts Eloquent (`Point`, `LineString`…), Blueprint Schema (`$table->magellanLineString()`), query builder spatial |
| **Cache & queue** | [Valkey 8](https://valkey.io) | Fork BSD de Redis (souveraineté, éthique), 100 % compatible RESP |
| **Pont front/back** | [Inertia.js 3](https://inertiajs.com) | App quasi-SPA sans réécriture en pure SPA, routing Laravel conservé |
| **Framework front** | [Svelte 5](https://svelte.dev) | Composants compilés (zéro runtime), runes `$state`/`$derived`/`$effect`, parfait pour UI riche |
| **i18n** | [i18next 26](https://www.i18next.com) + [svelte-i18next](https://github.com/i18next/svelte-i18next) | FR canonique côté Laravel `lang/{locale}`, partagés à Inertia, consommés via store réactif |
| **Cartographie** | [MapLibre GL JS 5](https://maplibre.org) | Vectoriel + GPU, multi-couches temporelles fluides, libre (fork de Mapbox GL JS pré-licence proprio) |
| **Fond carte** | [OpenStreetMap](https://www.openstreetmap.org) via [MapTiler](https://www.maptiler.com) ou [Stadia Maps](https://stadiamaps.com) | Tuiles vectorielles, attribution communautaire |
| **Overlay rail** | [OpenRailwayMap](https://www.openrailwaymap.org) | Couche optionnelle dédiée infrastructure ferroviaire (signaux, électrification, vitesses, écartement) |
| **Données ferroviaires** | OSM via [Overpass API](https://overpass-turbo.eu) → import Postgres | Pré-peuplement massif, pas de saisie manuelle from scratch |
| **Build front** | [Vite 8](https://vitejs.dev) | HMR rapide, intégration Laravel via `laravel-vite-plugin` 3 |
| **Iconographie** | [Bootstrap Icons](https://icons.getbootstrap.com) | Déjà en place, cohérent avec l'existant |
| **Typographie** | Lato · Gabarito · Inter · Montserrat | Auto-hébergées, identité visuelle déjà posée |
| **Tests** | [PHPUnit 13](https://phpunit.de) + [ParaTest](https://github.com/paratestphp/paratest), [Vitest](https://vitest.dev), [Infection](https://infection.github.io) + pcov | Plus de 2 200 tests unitaires et feature (en parallèle), tests JS, tests de mutation et mesure de couverture |
| **Qualité** | [Laravel Pint](https://github.com/laravel/pint), Prettier ; [PHPStan](https://phpstan.org) + [Larastan](https://github.com/larastan/larastan), [ESLint](https://eslint.org) + eslint-plugin-svelte, [svelte-check](https://github.com/sveltejs/language-tools) | Formatage automatique ; analyse statique (bugs, types, code mort) |
| **Auth** | [Laravel Sanctum](https://laravel.com/docs/sanctum) | Sessions + tokens API |
| **Conteneurs** | [Podman](https://podman.io) (sans root) + podman-compose ; compatible [Docker](https://www.docker.com) / Compose | Tout tourne en conteneurs, rien à installer sur l'hôte : PHP, Composer et Node via `bin/php`, `bin/composer`, `bin/pnpm` ; base et cache via `docker-compose.yml` |
| **Gestionnaires de paquets** | [Composer](https://getcomposer.org), [pnpm](https://pnpm.io) | Dépendances PHP et JS reproductibles |
| **Calcul sur carte graphique** | [NVIDIA Container Toolkit](https://github.com/NVIDIA/nvidia-container-toolkit) (CDI) + Podman | Accès au GPU depuis un conteneur sans root (`--device nvidia.com/gpu=all`), plafonné à 80 % |
| **Intelligence artificielle** | [PyTorch](https://pytorch.org) + [Segmentation Models PyTorch](https://github.com/qubvel-org/segmentation_models.pytorch) (U-Net, encodeur ResNet-34 pré-entraîné ImageNet), Python | Modèle de détection des voies ferrées entraîné par VFF sur photos aériennes IGN 1950-1965, pour recaler les tracés des lignes disparues (voir [ml/rail-extraction](ml/rail-extraction/README.md)) |
| **Sources géographiques et historiques** | [IGN](https://geoservices.ign.fr) (BD TOPO, BD ORTHO historique, cartes anciennes ; Licence Ouverte 2.0), [SNCF Open Data](https://ressources.data.sncf.com) (ODbL), [OpenHistoricalMap](https://www.openhistoricalmap.org), [Wikidata](https://www.wikidata.org), [Wikipédia](https://fr.wikipedia.org), [Wikimedia Commons](https://commons.wikimedia.org), [lignes-oubliees](https://www.lignes-oubliees.com) | Tracés croisés entre sources au mètre près, dates par portion, photos |

---

## 🚀 Installation locale

### Prérequis (sur l'hôte)

- **PHP 8.2+** avec extensions : `pdo_pgsql`, `mbstring`, `xml`, `curl`, `gd`, `intl`, `bcmath`, `zip`
- **Composer 2.5+**
- **Node.js 20+** et **pnpm 9+**
- **Docker + Docker Compose v2** (pour Postgres + Valkey)

> 💡 Seules **Postgres+PostGIS** et **Valkey** tournent en Docker. L'app Laravel et Vite tournent sur l'hôte pour un cycle de dev rapide.

### Étapes

```bash
# 1. Cloner
git clone https://github.com/UnxWares/VFF-Project.git
cd VFF-Project

# 2. Tout-en-un (recommandé) - env + services Docker + deps + key + migrations
make setup

# 3. Lancer le dev (serve + queue + pail + vite en parallèle)
make dev
```

→ Le site est accessible sur **http://localhost:8000**.
→ Vite HMR sur **http://localhost:5173**.
→ Adminer (UI DB) sur **http://localhost:8080**.

### En manuel (sans Make)

```bash
cp .env.example .env

# Services de données
docker compose up -d         # postgres + valkey + adminer

# Dépendances
pnpm install
composer install

# App
php artisan key:generate
php artisan migrate

# Dev (orchestre serve + queue + pail + vite via concurrently)
composer run dev
```

### Environnement de dev sans PHP/Node sur l'hôte

Pas de PHP, Composer ni Node installés ? Tout passe par **Podman** (ou Docker) grâce à trois
wrappers versionnés dans `bin/`. Ils lancent un conteneur jetable (`--rm`) qui monte le dépôt
sur `/app`, tourne avec **votre UID** (`--userns=keep-id` sous Podman → pas de fichiers
`root` dans le dépôt) et partage le **réseau de l'hôte** (la base reste joignable sur
`127.0.0.1:5432`, Vite sert sur `localhost:5173`).

| Wrapper | Image | Remplace |
|---|---|---|
| `bin/php` | `localhost/vff-php:8.5` (construite depuis `docker/php/Containerfile` : PHP 8.5 CLI + `pdo_pgsql`, `intl`, `zip`, `bcmath`, `pcntl`, Composer 2) | `php …` |
| `bin/composer` | même image (lien vers `bin/php`, appelle `composer`) | `composer …` |
| `bin/pnpm` | `docker.io/library/node:24-slim` + corepack (version de pnpm figée par `packageManager`) | `pnpm …` |

```bash
# 1. Image PHP (une fois, puis après chaque modif de docker/php/Containerfile)
make php-image                       # = podman build -t localhost/vff-php:8.5 docker/php

# 2. Services de données - Podman : podman-compose (ou `podman compose`)
podman-compose up -d                 # postgres+postgis, valkey, adminer
make up                              # idem : le Makefile choisit podman-compose/docker compose

# 3. Dépendances et application
bin/composer install
bin/pnpm install
bin/php artisan key:generate
bin/php artisan migrate

# 4. Dev : serve (:8000) + queue + Vite (:5173), Ctrl+C arrête tout
make dev
```

- **Le Makefile s'adapte tout seul** : chaque cible utilise `php`/`composer`/`pnpm` s'ils
  existent sur l'hôte, sinon `bin/php`/`bin/composer`/`bin/pnpm`. `make tools` affiche ce qui
  a été détecté (outils, moteur de conteneurs, outil compose).
- Variables utiles : `CONTAINER_ENGINE=docker` (forcer Docker), `VFF_PHP_IMAGE=…`,
  `VFF_NODE_IMAGE=…` (autres images), `make up DC="docker compose"` (autre outil compose).
- Les variables d'environnement de l'hôte **ne sont pas transmises** au conteneur : la
  configuration passe par `.env` (et `phpunit.xml` pour les tests).
- Fedora/SELinux : les wrappers désactivent l'étiquetage (`label=disable`) pour le montage du
  dépôt ; le volume d'init Postgres utilise `:z`.

### (Optionnel) Importer le réseau ferré depuis OSM

```bash
php artisan vff:import-osm --region=france   # à venir en phase 1
```

### Build de production

```bash
npm run build
php artisan optimize
php artisan view:cache
php artisan config:cache
php artisan route:cache
```

---

## 🗂 Structure du projet

```
VFF-Project/
├── app/
│   ├── Console/Commands/     # ImportOsmRegion (php artisan vff:import-osm)
│   ├── Enums/                # LineType, SegmentStatus, StationType, MaterialType…
│   ├── Http/
│   │   ├── Controllers/      # Contrôleurs Inertia + LocaleController
│   │   └── Middleware/       # HandleInertiaRequests, SetLocale
│   ├── Models/               # Era, Line, LineSegment, Station, Material, LineMaterial, LineEvent, Media, Contribution, User
│   ├── Services/             # OsmImporter
│   └── Support/              # Translations (aplatissement des lang/*.php pour Inertia)
├── bootstrap/
│   ├── app.php               # Configuration Laravel 13 (routes, middleware, exceptions)
│   └── providers.php         # Service providers app
├── config/                   # Standard Laravel + services.osm + services.cartographie
├── database/
│   ├── factories/
│   ├── migrations/           # 11 migrations métier : extensions PostGIS, eras, lignes, segments, gares, matériels…
│   └── seeders/              # EraSeeder (5 époques 1990-2030)
├── lang/                     # i18n FR canonique + EN + DE (complétude : php artisan vff:i18n-check)
│   ├── fr/                   # nav, footer, home, whoarewe, maps, contribute, donate, api, légales, common…
│   ├── en/
│   └── de/
├── resources/
│   ├── css/                  # general (tokens + dark mode), header, footer, sections (intouchables hors additif)
│   ├── fonts/                # licences OFL des polices (WOFF2 servies par @fontsource, auto-hébergées)
│   ├── images/               # Logo VFF, TGV, UnxWares, backgrounds WebP (3 tailles)
│   ├── js/
│   │   ├── app.js            # Entrée Inertia + Svelte + i18next bootstrap
│   │   ├── Components/       # Header, Footer, Layout, LanguageSwitcher, PageHero, IconCard, SectionBlock, LegalPage, Sections/*
│   │   ├── Pages/            # Home, Placeholder, WhoAreWe, Maps, Contribute, Donate, ApiDocs, Legal, Privacy, UseConditions
│   │   └── lib/
│   │       ├── i18n.js       # setup i18next + LOCALES
│   │       ├── stores/       # theme.svelte.js
│   │       └── actions/      # navbarFade, parallaxTGV, scrollReveal, smoothScrollTo
│   └── views/
│       └── app.blade.php     # Shell Inertia (1 seul template Blade)
├── routes/
│   ├── web.php               # Routes Inertia + GET /locale/{code}
│   ├── api.php               # API publique (à venir Phase 6)
│   └── console.php
├── tests/
│   ├── Feature/              # Pages publiques, API GeoJSON/MVT, doc OpenAPI, vff:i18n-check (base vff_test)
│   ├── Support/              # BuildsRailNetwork : mini-réseau déterministe pour les tests
│   └── Unit/
├── .github/workflows/ci.yml  # CI : Pint, i18n, PHPUnit (PostGIS), Prettier, build Vite
├── bin/                      # php, composer, pnpm conteneurisés (Podman/Docker)
├── docker/php/Containerfile  # image PHP 8.5 CLI pour bin/php et bin/composer
├── docker/postgres/init/     # 01-postgis.sql (extension PostGIS auto-créée)
├── docker-compose.yml        # postgres-postgis-18 + valkey-8 + adminer-5
├── Makefile                  # raccourcis dev (make setup, make dev, make psql, …)
└── public/                   # Assets compilés + favicon
```

---

## 🛠 Commandes utiles

```bash
# Dev (raccourcis Makefile)
make help                    # liste de toutes les cibles
make dev                     # serve + queue + pail + vite (concurrently)
make up / make down          # services Docker (postgres + valkey + adminer)
make psql                    # console psql dans le conteneur db
make valkey-cli              # console valkey

# Dev (sans Make)
composer run dev             # idem make dev
php artisan tinker           # REPL Laravel
docker compose up -d         # services data

# Base de données
make migrate                 # ou : php artisan migrate
make migrate-fresh           # reset complet + seed
make seed                    # php artisan db:seed

# Imports métier (à venir)
php artisan vff:import-osm --region=france
php artisan vff:import-photos /chemin/vers/dossier

# Qualité
make check                   # mini-CI locale : Pint (fichiers modifiés) + i18n + analyse + front + tests
make analyse                 # PHPStan + Larastan (ou : composer analyse)
make check-front             # ESLint + svelte-check (ou : pnpm lint:js && pnpm check)
make test-static             # contrôles statiques du projet, sans base (ou : composer test:static)
make test                    # PHPUnit (base vff_test, jamais la base de dev)
make test-db                 # crée la base vff_test + PostGIS si absente
make i18n                    # php artisan vff:i18n-check (clés FR manquantes en EN/DE)
make pint-dirty              # formater les fichiers PHP modifiés
make pint                    # formater TOUT le PHP (éviter pendant des chantiers parallèles)
make format                  # formater JS/Svelte/CSS (Prettier)
make lint                    # vérifier le formatage
make tools                   # outils détectés (hôte ou bin/*)

# Documentation de l'API
#   REST : /docs/api (Scalar), /docs/swagger (Swagger UI), /docs/api.json (OpenAPI, Scramble),
#          à jour automatiquement (docblocks et attributs Scramble des contrôleurs Api).
#   GraphQL : /docs/graphql (Magidoc, statique) et /graphiql ; descriptions dans graphql/schema.graphql.
make docs-graphql            # après une modification du schéma GraphQL : schéma imprimé + site Magidoc

# Production
pnpm build
php artisan optimize
```

### Tests

Les tests Feature utilisent `RefreshDatabase` : la base est **vidée et re-migrée** à chaque
run. Ils tournent donc sur une base dédiée **`vff_test`** (PostgreSQL + PostGIS, même
conteneur `vff-db`), forcée par `phpunit.xml`. Un garde-fou (`tests/CreatesApplication.php`)
refuse de démarrer si la base visée ne se termine pas par `_test` : la base `vff` et ses
imports ne peuvent pas être effacés par un test.

```bash
make test-db                 # une fois : CREATE DATABASE vff_test + extensions
make test                    # ou : bin/php artisan test --filter=LinePagesTest
```

Le mini-réseau de test (`tests/Support/BuildsRailNetwork.php` : 2 ères, une ligne Paris - 
Versailles, 2 gares, arrêts, signalisation) sert aux tests des pages publiques et de l'API
GeoJSON / MVT.

### Qualité

Analyse statique, sans base de données ; seules les erreurs **nouvelles** bloquent, la dette
existante est gelée dans une liste d'exceptions à faire maigrir en corrigeant.

| Outil | Commande | Ce qu'il vérifie | Exceptions |
|---|---|---|---|
| PHPStan + Larastan, niveau 6 | `make analyse` · `composer analyse` | `app/`, `database/`, `routes/`, `tests/` : méthodes et propriétés inexistantes, types et PHPDoc faux, colonnes inconnues des modèles, conditions toujours vraies, code mort | `phpstan-baseline.neon` |
| ESLint + eslint-plugin-svelte | `pnpm lint:js` | variables non définies ou inutilisées, erreurs Svelte 5 (le style reste à Prettier) | avertissements non bloquants |
| svelte-check (JS + JSDoc) | `pnpm check` | types du front d'après `jsconfig.json`, avertissements du compilateur Svelte (a11y, CSS inutilisé) | `svelte-check-baseline.json` |
| Contrôles du projet | `make test-static` · `composer test:static` | pas de tiret long « - », « - » ni « - » dans les textes affichés ; clés `t('…')` et `labelKey` du front présentes dans `lang/fr` ; palette de la carte (`railStyle.js`) et légende ; routes nommées, pages Inertia et URL internes du front | aucune |

Les contrôles du projet sont des tests PHPUnit (`tests/Unit/Static`) : ils tournent aussi avec
`make test`. Après correction d'erreurs existantes, régénérer les listes d'exceptions :
`make analyse-baseline` (ou `composer analyse:baseline`) et `pnpm check --update-baseline`.
Ne jamais régénérer pour faire taire une erreur nouvelle sans l'avoir comprise.

### Traductions

Le FR (`lang/fr`) est la source. `php artisan vff:i18n-check` échoue si une clé FR manque en
EN ou DE, si un fichier manque ou si les placeholders (`:name`) diffèrent ; il signale aussi
les clés orphelines et les clés `t('…')` du front absentes du FR. Les fichiers des chantiers
en cours (`contribute`, `editor`, `lines`, `maps`, `media`, `stations`) ne sont que
signalés ; `--strict` les rend bloquants.

### Intégration continue

`.github/workflows/ci.yml` : PHP 8.5 (Pint, `vff:i18n-check`, PHPStan, contrôles statiques,
PHPUnit sur un service `postgis/postgis:18-3.6`) et front (pnpm, Prettier, ESLint, svelte-check,
build Vite). Sur une pull request, Pint et
Prettier ne bloquent que sur les **fichiers modifiés** ; sur `main`, le contrôle complet est
informatif tant que le dépôt n'a pas été reformaté d'un bloc (`make pint` + `make format`, à
faire quand aucun chantier parallèle n'est en cours).

---

## 🤝 Contribuer

VFF est **collaboratif par essence**. Trois façons d'aider :

1. **Documenter des lignes** - directement depuis l'interface web une fois inscrit, ou en ouvrant une PR sur les seeders.
2. **Apporter des médias** - photos d'archives, plans, documents (voir [CONTRIBUTING.md](CONTRIBUTING.md) section *Médias*).
3. **Contribuer au code** - fork, branche, PR. Voir [CONTRIBUTING.md](CONTRIBUTING.md) pour les conventions (commits conventionnels, style, tests).

Toutes les contributions sont validées par les pairs avant publication.

---

## 🌐 Communauté & liens

- 🌍 **Site officiel** : [vff-project.org](https://vff-project.org) *(à venir)*
- 💬 **Discord** : https://discord.com/invite/6SwTfXBx4h
- 📸 **Instagram** : [@vff_project](https://www.instagram.com/vff_project)
- 🐦 **X / Twitter** : [@vff-project](https://www.x.com/vff-project)
- 🐙 **GitHub** : [UnxWares/VFF-Project](https://github.com/UnxWares/VFF-Project)
- 🏢 **Studio porteur** : [UnxWares](https://www.unxwares.com) (et sa Division Cloud pour l'hébergement)
- 📧 **Contact** : `contact@vff-project.org`

## 💸 Soutenir le projet

Le projet sera juridiquement porté par une **association ESS** en cours de constitution (« UnxWares Opensource ») qui gérera les dons et les actifs des projets open-source d'UnxWares - dont VFF.

Plateforme de dons à arbitrer :

- **[Patreon](https://www.patreon.com)** - soutien récurrent mensuel, populaire dans la communauté tech open-source
- **[HelloAsso](https://www.helloasso.com)** - don ponctuel ou récurrent, déductible fiscalement une fois l'asso reconnue d'intérêt général

→ Choix définitif en cours.

---

## 📜 Licence

Le **code** est distribué sous licence [MIT](LICENCE).

Les **données ferroviaires** importées depuis OpenStreetMap sont sous licence [ODbL](https://opendatacommons.org/licenses/odbl/) - toute redistribution doit respecter l'attribution OSM.

Les **polices** (Inter, Lato, Gabarito, Montserrat), servies en WOFF2 par les paquets `@fontsource/*` 5.3.0 (auto-hébergées, aucun CDN), sont sous [SIL Open Font License 1.1](https://openfontlicense.org) : textes dans `resources/fonts/OFL-*.txt`.

Les **tuiles OpenRailwayMap** utilisées en overlay sont sous [CC BY-SA 2.0](https://creativecommons.org/licenses/by-sa/2.0/).

Les **médias contribués** (photos, documents) restent la propriété de leurs auteurs ; ceux-ci en cèdent l'usage à VFF sous licence Creative Commons [CC BY-SA 4.0](https://creativecommons.org/licenses/by-sa/4.0/) sauf mention contraire.

---

## 🙏 Crédits

Développé par **Baptiste Gosselin** · Hébergement [UnxWares - Division Cloud](https://www.unxwares.com) · Fond cartographique © contributeurs [OpenStreetMap](https://www.openstreetmap.org/copyright) · Overlay rail © contributeurs [OpenRailwayMap](https://www.openrailwaymap.org/)
