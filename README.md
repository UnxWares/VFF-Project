<div align="center">

<img src="resources/images/vff.png" alt="Logo VFF" width="180"/>

# VFF — Voies Ferrées de France

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

**VFF (Voies Ferrées de France)** est une plateforme en ligne créée par des passionnés du rail. Notre mission : **documenter, cartographier et partager** l'intégralité du patrimoine ferroviaire français — qu'il s'agisse des lignes en service aujourd'hui ou de celles qui ont disparu il y a plus d'un siècle.

Au cœur du projet : une **carte interactive temporelle** qui permet de remonter le temps et de visualiser le réseau ferré tel qu'il existait en **1990, 2000, 2010, 2020** — et tel qu'il se dessine aujourd'hui, dans un snapshot vivant **2030** qui évoluera jusqu'à cette date avant d'être figé à son tour.

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
- [x] **Service d'import OSM** (Overpass API) — squelette : `php artisan vff:import-osm --region=FR`
- [x] Footer riche avec liens communauté, légal et partenaires
- [x] Performance hero : `background.png` 2.7 MB → WebP 179 KB desktop / 117 KB tablet / 63 KB mobile
- [x] Accessibilité : `prefers-reduced-motion`, focus-visible, `all: unset` sur les buttons icônes

### En développement (voir [ROADMAP.md](ROADMAP.md))
- [ ] Carte interactive MVP (MapLibre GL JS + tuiles OSM)
- [ ] Sélecteur temporel fonctionnel par décennie (1990 / 2000 / 2010 / 2020 / 2030)
- [ ] Couche optionnelle [OpenRailwayMap](https://www.openrailwaymap.org/) (infra, électrification, vitesses)
- [ ] Fiche détaillée par ligne (matériel, dates, médias)
- [ ] Système de contribution communautaire avec validation par les pairs
- [ ] Encyclopédie du matériel roulant
- [ ] API publique REST + GeoJSON + MVT (documentée via Scramble + Stoplight Elements)
- [ ] API GraphQL pour l'admin (Lighthouse, Phase 7)
- [ ] [Apache AGE](https://age.apache.org/) — extension graphe pour pathfinding historique (Phase 8)
- [ ] Reconstitutions 3D IA de lieux ferroviaires disparus (Phase 9)
- [ ] Mode hors-ligne (PWA)
- [ ] Extension historique 1900 → 1980 (Phase 10)

---

## 🧰 Stack technique

| Couche | Techno | Pourquoi |
|---|---|---|
| **Framework backend** | [Laravel 13](https://laravel.com) | Dernière stable, structure minimaliste `bootstrap/app.php`, support long terme |
| **Langage backend** | PHP 8.2+ | Types stricts, enums backés, performance |
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
| **Tests** | [PHPUnit 13](https://phpunit.de) + [Pest](https://pestphp.com) (à venir) | Tests unitaires & feature |
| **Qualité** | [Laravel Pint](https://github.com/laravel/pint), Prettier, ESLint | Formatage automatique |
| **Auth** | [Laravel Sanctum](https://laravel.com/docs/sanctum) | Sessions + tokens API |

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

# 2. Tout-en-un (recommandé) — env + services Docker + deps + key + migrations
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
│   └── seeders/              # EraSeeder (5 époques 1990–2030)
├── lang/                     # i18n FR canonique + EN complet + DE partiel
│   ├── fr/                   # nav, footer, home, whoarewe, maps, contribute, donate, api, légales, common…
│   ├── en/
│   └── de/
├── resources/
│   ├── css/                  # general (tokens + dark mode), header, footer, sections (intouchables hors additif)
│   ├── fonts/                # TTF auto-hébergées (Lato, Gabarito, Inter, Montserrat)
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
│   ├── Feature/
│   └── Unit/
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
make pint                    # formater PHP
make format                  # formater JS/Svelte/CSS (Prettier)
make lint                    # vérifier le formatage
make test                    # PHPUnit

# Production
pnpm build
php artisan optimize
```

---

## 🤝 Contribuer

VFF est **collaboratif par essence**. Trois façons d'aider :

1. **Documenter des lignes** — directement depuis l'interface web une fois inscrit, ou en ouvrant une PR sur les seeders.
2. **Apporter des médias** — photos d'archives, plans, documents (voir [CONTRIBUTING.md](CONTRIBUTING.md) section *Médias*).
3. **Contribuer au code** — fork, branche, PR. Voir [CONTRIBUTING.md](CONTRIBUTING.md) pour les conventions (commits conventionnels, style, tests).

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

Le projet sera juridiquement porté par une **association ESS** en cours de constitution (« UnxWares Opensource ») qui gérera les dons et les actifs des projets open-source d'UnxWares — dont VFF.

Plateforme de dons à arbitrer :

- **[Patreon](https://www.patreon.com)** — soutien récurrent mensuel, populaire dans la communauté tech open-source
- **[HelloAsso](https://www.helloasso.com)** — don ponctuel ou récurrent, déductible fiscalement une fois l'asso reconnue d'intérêt général

→ Choix définitif en cours.

---

## 📜 Licence

Le **code** est distribué sous licence [MIT](LICENCE).

Les **données ferroviaires** importées depuis OpenStreetMap sont sous licence [ODbL](https://opendatacommons.org/licenses/odbl/) — toute redistribution doit respecter l'attribution OSM.

Les **tuiles OpenRailwayMap** utilisées en overlay sont sous [CC BY-SA 2.0](https://creativecommons.org/licenses/by-sa/2.0/).

Les **médias contribués** (photos, documents) restent la propriété de leurs auteurs ; ceux-ci en cèdent l'usage à VFF sous licence Creative Commons [CC BY-SA 4.0](https://creativecommons.org/licenses/by-sa/4.0/) sauf mention contraire.

---

## 🙏 Crédits

Développé par **Baptiste Gosselin** · Hébergement [UnxWares — Division Cloud](https://www.unxwares.com) · Fond cartographique © contributeurs [OpenStreetMap](https://www.openstreetmap.org/copyright) · Overlay rail © contributeurs [OpenRailwayMap](https://www.openrailwaymap.org/)
