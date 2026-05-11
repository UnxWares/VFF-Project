# Architecture VFF

Document de référence sur la structure technique du projet. Public visé : développeurs qui veulent contribuer en profondeur.

---

## Vue d'ensemble

VFF est une **application web Laravel + Inertia + Svelte**, adossée à une **base PostgreSQL/PostGIS**, qui rend une **carte interactive du patrimoine ferroviaire français** sur plusieurs époques.

```
┌──────────────────────────────────────────────────────────┐
│                       Navigateur                          │
│  ┌──────────────┐  ┌──────────────┐  ┌────────────────┐  │
│  │ Svelte page  │  │ MapLibre GL  │  │ Inertia router │  │
│  │ (Pages/*.sv) │  │  + tuiles    │  │  (XHR / nav)   │  │
│  └──────┬───────┘  └──────┬───────┘  └────────┬───────┘  │
└─────────┼─────────────────┼───────────────────┼──────────┘
          │ Inertia JSON    │ MVT/GeoJSON       │
          ▼                 ▼                   ▼
┌──────────────────────────────────────────────────────────┐
│                     Laravel 12                            │
│  ┌─────────────┐  ┌──────────────┐  ┌────────────────┐   │
│  │ HTTP        │  │ Actions /    │  │ API publique   │   │
│  │ Controllers │  │ Services     │  │ /api/* (REST)  │   │
│  │ (Inertia)   │  │              │  │                │   │
│  └──────┬──────┘  └──────┬───────┘  └────────┬───────┘   │
│         └────────────────┴────────────────────┘          │
│                          │                                │
│                ┌─────────▼──────────┐                     │
│                │ Models Eloquent +  │                     │
│                │ Spatial Casts      │                     │
│                └─────────┬──────────┘                     │
└──────────────────────────┼──────────────────────────────┘
                           ▼
           ┌──────────────────────────────────────┐
           │  PostgreSQL 18 + PostGIS 3.6         │
           │  + Apache AGE (graphe, Cypher)       │
           │  LINESTRING + POINT en 4326          │
           │  Index GIST sur les géométries       │
           └──────────────────────────────────────┘

           ┌────────────────────────────────┐
           │  Valkey 8 (cache + queue)      │
           │  Compatible RESP / Redis       │
           └────────────────────────────────┘

           ┌────────────────────────────────┐
           │  Tuiles de fond (provider OSM) │
           │  MapTiler / Stadia Maps        │
           └────────────────────────────────┘

           ┌────────────────────────────────┐
           │  Overlay OpenRailwayMap        │
           │  (optionnel, raster)           │
           └────────────────────────────────┘
```

---

## Stack en une phrase

> Laravel 12 sert des pages Inertia rendues côté client par Svelte 5 ; la carte MapLibre GL JS consomme des données spatiales servies par Laravel depuis Postgres/PostGIS, sur fond de tuiles vectorielles OSM.

---

## Couches

### 1. Présentation — Svelte 5

- Toutes les pages sont des **composants Svelte** dans `resources/js/Pages/`.
- Inertia.js fait le mapping `route → page` côté serveur.
- Composants UI réutilisables dans `resources/js/Components/`.
- Stores globaux (thème, utilisateur courant, ère sélectionnée) dans `resources/js/lib/stores/`.
- Pas de SSR pour démarrer (peut être ajouté plus tard via `@inertiajs/svelte/server`).

**Pourquoi Svelte ?** Compilé, zéro runtime, syntaxe lisible, parfait pour des UIs riches (sélecteur temporel, panneau d'édition de tracé) sans la lourdeur d'un React/Vue.

### 2. Pont — Inertia.js

- Une seule route Blade : `resources/views/app.blade.php` (le shell).
- Chaque contrôleur retourne `Inertia::render('PageName', $props)`.
- Navigation gérée côté client par le router Inertia (pas de full page reload).

**Pourquoi Inertia ?** On garde le routage Laravel, le middleware, les form requests, l'auth — mais on a une UX SPA. Pas de duplication d'API pour le rendu.

### 3. Application — Laravel 12

Structure suivant les principes Laravel 11+ (plus de `Http/Kernel.php`, tout dans `bootstrap/app.php`).

- **Contrôleurs** : minces, ils n'orchestrent que la requête HTTP et délèguent.
- **Actions** (single-action classes dans `app/Actions/`) : opérations d'écriture (CreateLine, ImportOsmRegion, SubmitContribution).
- **Services** (`app/Services/`) : logiques transverses (OsmImporter, GeoExporter, MediaUploader).
- **Form Requests** : validation centralisée.
- **Policies** : autorisation par modèle.
- **Jobs** : tâches asynchrones (import OSM, génération de tuiles MVT, traitements 3D IA).

### 4. Domaine — Modèles Eloquent

Modèles principaux (voir [migrations](database/migrations/) pour le détail) :

| Modèle | Description | Géométrie |
|---|---|---|
| `Era` | Décennie de référence (1990, 2000, 2010, 2020, 2030) | — |
| `Line` | Ligne ferroviaire (concept, agnostique de l'époque) | — |
| `LineSegment` | Tronçon d'une ligne à une époque donnée, avec statut | `LINESTRING(4326)` |
| `Station` | Gare / halte / passage à niveau | `POINT(4326)` |
| `Material` | Matériel roulant (locomotive, automotrice, rame) | — |
| `LineMaterial` | Pivot : quel matériel sur quelle ligne, quand | — |
| `LineEvent` | Date clé d'une ligne (inauguration, fermeture…) | — |
| `Media` | Photo / document, polymorphe (sur ligne / gare / matériel / event) | — |
| `Contribution` | Édit collaboratif en attente de validation | — |
| `User` | Utilisateur + rôle + réputation | — |

**Casts spatiaux custom** dans `app/Casts/` :
- `LineStringCast` : convertit WKT/WKB ↔ tableau de coordonnées
- `PointCast` : idem pour les points

### 5. Données — PostgreSQL 18 + PostGIS 3.6 + Apache AGE

**Pourquoi PostGIS** (spatial) :
- Stockage natif `geometry(LINESTRING, 4326)` + index GIST
- Requêtes spatiales puissantes : `ST_Intersects`, `ST_Within`, `ST_Length`, `ST_Buffer`, `ST_Simplify`
- Permet de servir des MVT à la volée via `ST_AsMVT` (énorme gain de perf pour la carte)
- Permet l'historisation propre via une colonne `era_id` sur les `line_segments`

**Pourquoi [Apache AGE](https://age.apache.org/)** (graphe, Phase 8) :
- Extension officielle Apache qui ajoute le support graphe (Cypher) à PostgreSQL — **dans la même base**
- VFF a une structure naturellement graphe : stations = nœuds, segments = arêtes, lignes×matériels temporel
- Permet le pathfinding élégant via Cypher : « comment aller de A à B en 1950 ? »
- Évite la duplication multi-DB (pas de Neo4j séparé à synchroniser)
- Côté Laravel : requêtes Cypher en raw query (`DB::select("SELECT * FROM cypher(...)")`)

**Pourquoi Valkey** (cache/queue) plutôt que Redis :
- Fork BSD-3-Clause maintenu par la Linux Foundation depuis le pivot SSPL de Redis Labs en 2024
- 100 % protocole-compatible (RESP) — Laravel utilise la connexion `redis` standard
- Choix éthique + souveraineté ouverte

Migrations :
- Première migration métier : active les extensions
  ```php
  DB::statement('CREATE EXTENSION IF NOT EXISTS postgis;');
  DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm;');
  DB::statement('CREATE EXTENSION IF NOT EXISTS unaccent;');
  // Apache AGE activé plus tard, quand on en a besoin :
  // DB::statement('CREATE EXTENSION IF NOT EXISTS age;');
  ```
- Index obligatoires sur toutes les colonnes spatiales : `$table->index('geom', null, 'gist');`

---

## Flux principaux

### Affichage de la carte

```
1. GET /maps                                        [Inertia controller]
2. → Inertia::render('Maps', ['initial_era' => 2030])
3. → app.blade.php sert le shell + bundle Svelte
4. → Pages/Maps.svelte se monte, instancie <RailMap era={2030}/>
5. → <RailMap> initialise MapLibre avec une pile de layers :
       - 'osm-background'     = tuiles vectorielles OSM (MapTiler/Stadia)         [toujours visible]
       - 'openrailwaymap'     = tuiles raster ORM (infrastructure)                [toggle, défaut OFF]
       - 'vff-lines'          = '/api/tiles/{z}/{x}/{y}.mvt?era=2030'             [toujours visible]
       - style : orange #ff6500 pour railway en service, dégradé de gris pour les statuts d'abandon
6. → MapLibre charge les tuiles à la demande selon le viewport
7. → Backend Laravel renvoie ST_AsMVT(geom) filtré sur era_id
```

### Couches cartographiques

| Layer | Fournisseur | Toujours actif ? | Donnée |
|---|---|---|---|
| Fond | MapTiler / Stadia | Oui | Tuiles vectorielles OSM (relief, villes, routes) |
| OpenRailwayMap | tiles.openrailwaymap.org | Non (toggle) | Tuiles raster CC-BY-SA 2.0 — infrastructure ferroviaire détaillée (signaux, électrification, vitesses, écartement) |
| VFF tracés | Notre Postgres+PostGIS | Oui | MVT générés à la volée par `ST_AsMVT`, filtrés par `era_id`, stylisés selon statut |
| VFF stations | Idem | Oui | POINTs en pastilles, taille selon zoom |

L'utilisateur peut activer **OpenRailwayMap** en overlay quand il veut comparer notre tracé historique au rendu OSM contemporain — révélateur pour identifier visuellement les portions disparues.

### Import du réseau depuis OSM

```
1. Admin : php artisan vff:import-osm --region=france
2. → OsmImporter::import() (job dispatché)
3. → Construit la requête Overpass : way["railway"~"rail|abandoned|disused"] (FR)
4. → Récupère le GeoJSON
5. → Pour chaque way :
     - Identifie/crée la Line (par ref ou nom)
     - Crée un LineSegment pour l'ère courante (2030)
     - Détermine le statut depuis les tags OSM
6. → Indexation GIST déjà en place
```

### Contribution communautaire

```
1. User connecté ouvre l'éditeur sur la fiche d'une ligne
2. → Modifie le tracé / ajoute un event / upload une photo
3. → POST /contributions { type, payload, target }
4. → SubmitContributionAction crée une Contribution en 'pending'
5. → Notification aux validators
6. → Validators ouvrent la file, comparent before/after, valident ou refusent
7. → ApplyContributionAction (à la 2e validation) applique le diff au modèle cible
8. → Notification au contributeur
```

---

## Performance

- **Tuiles MVT** plutôt que GeoJSON pour la carte : 10–100× plus léger
- **Cache HTTP** sur les MVT (`Cache-Control: public, max-age=3600`)
- **CDN** devant les assets statiques (photos, tuiles cachées)
- **Queue Redis** pour les jobs lourds (import OSM, génération 3D)
- **Index GIST** systématiques sur les colonnes spatiales
- **Lazy loading** des fiches détaillées
- **Composants Svelte** = bundle minimal (vs React/Vue qui embarquent un runtime)

---

## Sécurité

- CSRF activé (sauf API publique authentifiée par Sanctum token)
- Échappement Blade par défaut, jamais de `{!! !!}` sur du contenu user
- Form Requests pour toute donnée entrante
- Policies pour toute autorisation
- Rate limiting sur les endpoints sensibles (auth, contributions)
- Logs d'audit sur toute modification de donnée métier (table `contributions`)

---

## Déploiement (cible)

- **Hébergement** : [UnxWares — Division Cloud](https://www.unxwares.com), infrastructure souveraine française
- **Domaine** : `vff-project.org`
- **Docker Compose** : nginx + php-fpm + postgres-postgis(+age) + valkey
- **Reverse proxy** Cloudflare devant pour la cache et l'anti-DDoS
- **CI** GitHub Actions → SSH déploiement zero-downtime via [Deployer](https://deployer.org/)
- **Backup** quotidien Postgres → S3-compatible (chez UnxWares ou Backblaze B2)
- **Monitoring** : Uptime Kuma + Sentry pour les erreurs applicatives
- **Logs** : agrégés via Loki + Grafana

---

## API publique (Phase 6+)

### REST en premier (phases 1–6)

- Endpoints documentés via [Scramble](https://scramble.dedoc.co/) — génération OpenAPI auto depuis le code Laravel, sans annotations verbeuses
- Documentation publique servie via [Stoplight Elements](https://stoplight.io/open-source/elements) (composant web open-source TypeScript) embarqué dans une page Svelte avec branding VFF complet (orange `#ff6500`, fonts Lato/Gabarito, dark mode)
- Page d'accès : `/api`
- Formats : JSON (REST), GeoJSON (cartographie), MVT (`ST_AsMVT` Mapbox Vector Tiles pour MapLibre/Leaflet)
- Authentification : Laravel Sanctum, quotas par tier (anonymous / inscrit / partenaire)

### GraphQL en complément (Phase 7)

- Ajouté via [Lighthouse](https://lighthouse-php.com/) pour les **écrans riches admin/édition** uniquement
- Le REST reste canonique pour les consommateurs externes (cache HTTP, MVT natifs)
- Pas de migration vers full-GraphQL : on perdrait la cache HTTP et le support natif MVT

---

## Modèle économique / structure juridique

- **Hébergement & infrastructure** : [UnxWares](https://www.unxwares.com), studio porteur initial
- **Association ESS** (Économie Sociale et Solidaire) en cours de constitution sous le nom de travail **« UnxWares Opensource »** — portera juridiquement et financièrement VFF et les autres projets open-source d'UnxWares
- **Plateforme de dons** : à arbitrer entre **Patreon** (récurrent, sans statut associatif requis) et **HelloAsso** (déductibilité fiscale possible une fois l'asso reconnue d'intérêt général)

---

## Évolutions architecturales envisagées

- **Tile server dédié** (Martin ou pg_tileserv) si la charge augmente
- **OpenSearch** pour la recherche full-text avancée (titres de lignes, noms de gares)
- **Module 3D** isolé en microservice Python (pour les pipelines NeRF / Gaussian Splatting de la Phase 9)
- **PWA** mode hors-ligne (cache de tuiles + données récentes)

---

*Ce document évolue avec le projet. À jour : 2026-05-11.*
