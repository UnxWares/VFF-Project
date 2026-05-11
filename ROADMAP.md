# 🛤️ Roadmap VFF

> *De la page d'accueil à la plus grande encyclopédie ferroviaire de France.*

Ce document détaille les phases de développement de VFF. Les phases sont **indicatives, pas strictement séquentielles** : certaines tâches peuvent être menées en parallèle. Les cases cochées reflètent l'état réel du dépôt.

**Légende** : `[x]` fait · `[ ]` à faire · `[~]` en cours · `🎯` jalon majeur · `🧪` expérimentation

---

## 🟢 Phase 0 — Fondations (largement faite)

Base technique propre, sur laquelle tout le reste repose.

- [x] Page d'accueil avec identité visuelle (header parallax TGV, footer, dark mode)
- [x] Routes de base + pages internes (whoarewe, maps, contribute, donate, légales, api)
- [x] Structure CSS modulaire (general / header / footer / sections)
- [x] **Upgrade Laravel 10 → 12** (nouvelle structure `bootstrap/app.php`)
- [x] **Migration vers Inertia 2 + Svelte 5** (toutes pages portées)
- [x] **Bascule MySQL → PostgreSQL 18 + PostGIS 3.6** (en Docker)
- [x] **Valkey 8** (en remplacement de Redis, fork BSD-3 — souveraineté)
- [x] Setup Docker Compose pour services data (postgis + valkey + adminer)
- [x] Rédaction des documents projet (README, ROADMAP, CONTRIBUTING, ARCHITECTURE)
- [x] i18n complet (FR canonique, EN complet, DE partiel) — `lang/*` + middleware SetLocale + i18next côté front + LanguageSwitcher minimaliste
- [x] Dark mode fonctionnel (toggle persisté en cookie)
- [x] Perf : hero background PNG 2.7 MB → WebP 179 KB desktop / 117 KB tablet / 63 KB mobile (-93 %)
- [ ] CI GitHub Actions (Pint, ESLint, Prettier, PHPUnit, build Vite)
- [ ] Configuration Sentry / log aggregator pour monitoring d'erreurs
- [ ] Pre-commit hooks (Lefthook ou Husky)

🎯 **Jalon "Foundations Done"** : `make setup && composer run dev` suffit à voir le site complet, sans aucune étape manuelle.

---

## 🟡 Phase 1 — Modèle de données & couche métier

Définir et implémenter le cœur du domaine ferroviaire.

### Schéma

- [ ] Table `eras` (décennies de référence : 1990, 2000, 2010, 2020, 2030 — la 2030 mutable)
- [ ] Table `lines` (ligne ferroviaire : nom officiel + populaires, code, gestionnaire historique, type)
- [ ] Table `line_segments` (`geometry(LINESTRING, 4326)` PostGIS, lien `line_id` + `era_id`, statut : en_service / voie_unique / fermée / déposée / rasée)
- [ ] Table `stations` (gare/halte/PN, `geometry(POINT, 4326)`, statut historique)
- [ ] Table `materials` (matériel roulant : locomotives, automotrices, rames — fiche, périodes d'exploitation)
- [ ] Table `line_materials` (pivot : quel matériel a roulé sur quelle ligne, à quelles dates)
- [ ] Table `line_events` (dates clés : inauguration, électrification, fermeture, démantèlement, transfert)
- [ ] Table `media` (photos, documents, plans — métadonnées EXIF, date capture, copyright, lien polymorphe)
- [ ] Table `contributions` (édit collaboratif : qui, quand, quoi, état de validation)
- [ ] Table `users` (extension du défaut : rôle, badge contributeur, réputation)

### Couche applicative

- [ ] Modèles Eloquent avec casts spatiaux (custom `LineStringCast`, `PointCast`)
- [ ] Repositories ou query objects pour les requêtes géo complexes
- [ ] Service `OsmImporter` (Overpass API → mapping vers nos tables)
- [ ] Service `GeoExporter` (export en GeoJSON / MVT)
- [ ] Factories + seeders réalistes pour le dev

🎯 **Jalon "Domain Ready"** : une ligne complète (Paris-Brest par ex.) est entièrement modélisée et requêtable via Tinker.

---

## 🟠 Phase 2 — Carte interactive (MVP)

Première version fonctionnelle de la carte, sans encore le sélecteur temporel.

- [ ] Page `/maps` portée en Svelte
- [ ] Intégration MapLibre GL JS dans un composant `<RailMap>`
- [ ] Choix et configuration du fournisseur de tuiles (MapTiler vs Stadia — tester les deux)
- [ ] Endpoint API `GET /api/lines.geojson` qui sert toutes les lignes en service de l'ère courante
- [ ] Style cartographique custom : rails en orange `#ff6500`, gares en pastilles blanches
- [ ] Toggle overlay [OpenRailwayMap](https://www.openrailwaymap.org/) (infrastructure / électrification / vitesses) en raster CC-BY-SA
- [ ] Clic sur une ligne → panneau latéral avec fiche minimale
- [ ] Recherche par nom de ligne / gare (autocomplete)
- [ ] Géolocalisation utilisateur
- [ ] Légende dynamique (statut des lignes : en service / fermée / déposée…)
- [ ] Performances : chargement progressif des tuiles vectorielles MVT (`/api/tiles/{z}/{x}/{y}.mvt`)

🎯 **Jalon "Map MVP"** : on peut voir la totalité du réseau français en service en 2030, cliquer sur n'importe quelle ligne, lire sa fiche.

---

## 🔵 Phase 3 — Sélecteur temporel & couches d'époque

Le cœur de l'identité VFF : remonter le temps.

- [ ] Composant `<TimelineSlider>` (Svelte) : positions 1990 / 2000 / 2010 / 2020 / 2030
- [ ] Animation de transition fluide entre époques (fade des polylignes obsolètes, apparition des nouvelles)
- [ ] Endpoint API `GET /api/lines.geojson?era=2000`
- [ ] Calque "lignes disparues" superposable, faded en gris sur l'époque courante
- [ ] Bouton "voir tout en empilé" : toutes les époques en transparence pour visualiser l'érosion du réseau
- [ ] URL `?era=2000` partageable / bookmarkable
- [ ] Snapshot 2030 marqué `editable=true` (modifiable jusqu'au 31/12/2029 puis figé automatiquement)

🎯 **Jalon "Time Travel"** : on peut comparer visuellement le réseau de 1990 et 2020 d'un mouvement de slider.

---

## 🟣 Phase 4 — Fiches détaillées

Chaque ligne devient une vraie page d'encyclopédie.

- [ ] Page `/lines/{slug}` avec :
  - [ ] Mini-carte du tracé
  - [ ] Chronologie verticale des `line_events`
  - [ ] Galerie des `media` triés par date
  - [ ] Liste du matériel roulant historique (cartes cliquables → fiche matériel)
  - [ ] Statistiques (longueur, dénivelé via PostGIS `ST_Length`/`ST_Z`, nombre de gares)
  - [ ] Bibliographie / sources
- [ ] Page `/materials/{slug}` (fiche matériel : photos, périodes, lignes parcourues)
- [ ] Page `/stations/{slug}` (fiche gare : ouverture/fermeture, lignes desservies, photos)
- [ ] Schéma JSON-LD pour SEO (BreadcrumbList, ImageObject, Place)

---

## 🟤 Phase 5 — Contribution communautaire

Ouvrir l'édition aux passionnés extérieurs.

- [ ] Inscription / connexion (Laravel Breeze ou Fortify + UI Svelte)
- [ ] Système de rôles : `visitor`, `contributor`, `validator`, `admin`
- [ ] Éditeur de tracé dans la carte (digitalisation à la souris, snap sur OSM)
- [ ] Formulaire d'ajout d'événement / média / matériel
- [ ] Workflow de validation : toute contribution = `contribution` en `pending`, validée par ≥ 2 validators
- [ ] Notifications (in-app + email) sur acceptation/refus
- [ ] Système de réputation / badges (mérite, ancienneté, qualité des contribs)
- [ ] Modération : signalement, blacklist, journal d'audit

🎯 **Jalon "Open Encyclopedia"** : un visiteur peut s'inscrire et soumettre sa première contribution en moins de 3 minutes.

---

## 🌐 Phase 6 — API REST publique

Ouvrir les données VFF à l'écosystème, en REST canonique.

- [ ] OpenAPI 3 auto-généré via [Scramble](https://scramble.dedoc.co/) (sans annotations verbeuses)
- [ ] Endpoints REST documentés : lignes, gares, matériels, événements, médias
- [ ] Format GeoJSON et MVT (`ST_AsMVT`) pour les couches spatiales
- [ ] Tokens API gérés par Sanctum, quotas par tier (anon / inscrit / partenaire)
- [ ] Webhooks pour notifier les changements
- [ ] Page `/api` avec rendu [Stoplight Elements](https://stoplight.io/open-source/elements) custom — branding VFF (orange `#ff6500`, fonts Lato/Gabarito, dark mode auto)
- [ ] Exemples curl, JS, Python intégrés

---

## 🔮 Phase 7 — GraphQL pour l'admin

Compléter l'API REST par du GraphQL pour les écrans riches d'administration et d'édition.

- [ ] [Lighthouse](https://lighthouse-php.com/) installé
- [ ] Schema GraphQL pour les entités principales (Line, LineSegment, Station, Material, Event, Media, Contribution)
- [ ] Endpoint `/graphql` accessible aux utilisateurs authentifiés avec rôle `validator` ou `admin`
- [ ] Mutations pour édition : `submitContribution`, `validateContribution`
- [ ] Subscriptions optionnelles (WebSocket) pour notifications temps réel des contributions
- [ ] **REST reste canonique** pour les consommateurs externes — GraphQL est un complément interne

---

## 🕸️ Phase 8 — Apache AGE & pathfinding historique

Activer la dimension graphe sur la base PostgreSQL existante.

- [ ] Installation de [Apache AGE](https://age.apache.org/) sur le conteneur Postgres (`CREATE EXTENSION age`)
- [ ] Construction du graphe : stations = nœuds, segments (par époque) = arêtes étiquetées avec statut, distance, durée
- [ ] Requêtes Cypher exposées via service Laravel
- [ ] **Pathfinding historique** : « comment aller de Paris à Brest en 1950 ? » → algorithme Dijkstra sur le sous-graphe `era=1950`, statut `en_service`
- [ ] Comparaison de trajets entre époques (split-screen / timeline)
- [ ] Visualisation du graphe (force-directed) en mode admin pour explorer les connexions

---

## 🧠 Phase 9 — Reconstitutions 3D assistées par IA 🧪

Le pari ambitieux : redonner vie aux lieux disparus.

- [ ] Recherche : NeRF, Gaussian Splatting, Stable Diffusion 3D
- [ ] POC : reconstruire une gare désaffectée à partir de 5–10 photos d'archive
- [ ] Pipeline batch : photos → preprocessing (calibration EXIF) → modèle 3D → mesh exportable
- [ ] Visualiseur 3D embed dans la fiche ligne/gare (three.js ou model-viewer)
- [ ] Annotations 3D collaboratives (placement d'objets disparus)
- [ ] Possibilité de visite "first-person" du lieu reconstitué
- [ ] Microservice Python isolé (pipeline GPU séparé de l'app Laravel)

⚠️ **Cette phase est exploratoire** : faisabilité à confirmer selon la qualité des archives disponibles. Probable partenariat avec un labo / une école.

---

## 📜 Phase 10 — Extension historique (1900 → 1980)

Étendre la couverture temporelle du périmètre actuel (1990–2030) vers le début du XXe siècle.

- [ ] Seeder `HistoricalErasSeeder` : ajout des époques 1900, 1910, …, 1980 dans la table `eras` (le schéma supporte déjà n'importe quel `year`, aucune migration nécessaire)
- [ ] UI Timeline home : adapter pour 14 époques (slider continu avec marqueurs décennaux, OU pagination horizontale)
- [ ] Sources d'archive primaires :
  - Fonds Réseau Ferré de France (héritage de RFN)
  - Cartes IGN historiques (cartes Cassini, État-Major, etc.)
  - BNF Gallica (numérisation cartes/plans/horaires)
  - Associations ferroviaires régionales
- [ ] Pipeline d'import semi-automatique depuis cartes scannées (géoréférencement + tracé manuel/AI-assisté)
- [ ] Distinction visuelle des époques anciennes sur la carte (sépia, dégradé temporel)

⚠️ **Cette phase est massive** : OSM ne contient quasiment pas de données historiques avant 1990. Le gros du travail est de la **numérisation et géoréférencement d'archives papier**. Requiert un partenariat institutionnel.

---

## 🎓 Phase 11 — Communauté & rayonnement

- [ ] Blog / journal de bord du projet (articles longs sur des lignes emblématiques)
- [ ] Programme partenariat avec associations ferroviaires locales (CFTA, AAATV, MFCF…)
- [ ] Présence dans les conférences open data / patrimoine
- [ ] Application mobile (PWA en première intention, app native si demandée)
- [ ] Mode hors-ligne (cache de tuiles + données)
- [ ] Multilingue (FR ✓, EN ✓, DE partiel — à compléter)
- [ ] Page de dons activée — plateforme finale (Patreon ou HelloAsso) selon le statut juridique de l'asso ESS UnxWares Opensource

---

## 🌱 Idées en vrac (backlog non priorisé)

- [ ] Mode "vue ferroviaire" : carte simplifiée à la BD-RGE style schéma
- [ ] Comparaison de deux époques en split-screen
- [ ] Calcul d'itinéraire historique : "comment serais-je allé de A à B en 1950 ?"
- [ ] Stories d'archives (carrousel de photos sur un événement précis)
- [ ] Quiz / mini-jeu : reconnaître la ligne / le matériel
- [ ] Export GPX/KML d'un tracé
- [ ] Intégration STRAVA pour les cyclistes parcourant les voies vertes ex-ferroviaires
- [ ] Recensement des passages à niveau (avec ou sans annonceur)
- [ ] Couches thématiques : électrification, écartement, types de signalisation

---

*Cette roadmap est un document vivant. Toute proposition est bienvenue : ouvre une [issue](https://github.com/vff-project/vff/issues) avec le label `roadmap`.*

*À jour : 2026-05-11.*
