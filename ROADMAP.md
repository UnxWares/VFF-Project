# 🛤️ Roadmap VFF

> *De la page d'accueil à la plus grande encyclopédie ferroviaire de France.*

Ce document détaille les phases de développement de VFF. Les phases sont **indicatives, pas strictement séquentielles** : certaines tâches peuvent être menées en parallèle. Les cases cochées reflètent l'état réel du dépôt.

**Légende** : `[x]` fait · `[ ]` à faire · `[~]` en cours ou partiel · `🎯` jalon majeur · `🧪` expérimentation

---

## 📍 Où en est-on ? (2026-11-19)

| Phase | État |
|:-|:-|
| 0 - Fondations | ✅ faite, reste le suivi des erreurs |
| 1 - Modèle de données | ✅ faite, jalon atteint |
| 2 - Carte interactive | ✅ faite, géolocalisation comprise |
| 3 - Sélecteur temporel | ✅ faite (21 époques de 1830 à 2030), comparaison de deux époques et vue empilée comprises |
| 4 - Fiches détaillées | 🟡 lignes, gares, matériel, accidents, JSON-LD et profil en long faits ; profils de toutes les lignes à calculer |
| 5 - Contribution | 🟡 comptes, éditeur de tracé, validation à deux, suppression et export du compte, journal d'audit faits ; notifications sur le site et réputation à faire |
| 6 - API REST | 🟡 API publique complète et documentée (30 points d'accès) ; jetons, webhooks et exemples multi-langages à faire |
| 7 - GraphQL | 🟡 lecture publique faite ; mutations à faire |
| 8 - Graphe & itinéraires | 🟡 itinéraires historiques faits sur un graphe PostgreSQL ; Apache AGE pas activé |
| 9 - Reconstitutions 3D 🧪 | ⬜ pas commencée |
| 10 - Extension historique | ✅ couverture 1830-2030 ; modèle d'extraction des voies publié |
| 11 - Communauté | 🟡 trilingue, carte intégrable, application installable ; dons et blog à faire |
| 12 - Mise en production | 🟡 pages légales, engagements RGPD et audit RGAA (81,94 %) faits ; messagerie et hébergement final à faire |
| 13 - Nouveaux domaines | 🟡 matériel roulant, accidents, serveur MCP, signalisation et passages à niveau (carte, fiches, API) faits ; historique de la signalisation à faire |

**En base (développement)** : 2 705 lignes, 9 363 gares, 15 519 événements datés, 71 597 médias, 21 époques, 687 séries de matériel roulant (969 liens avec les lignes), 202 accidents.

**Qualité** : environ 4 200 tests PHPUnit et 440 tests vitest, Larastan sans erreur, contrôle des invariants de données (`vff:check-invariants`), des traductions (`vff:i18n-check`) et des droits des sources (`vff:source-rights`), le tout en CI ; contrôles avant commit Lefthook (à installer) ; audit RGAA 4.1.2 interne : 81,94 %.

**Priorités du moment** :

1. Préparer la mise en ligne (phase 12) : messagerie de vff-project.org, hébergement, aperçus de partage rendus côté serveur.
2. Trancher les questions ouvertes de la nuit du 19 au 20/11 (`storage/app/reports/agents/QUESTIONS.md`) : corrections de données, Argentan Web Ferroviaire, accidents, matériel.
3. Jonctions : phase « escaliers » (coudes et crochets restants).
4. Historique de la signalisation par époque (phase 13) : aucune source par date pour l'instant.

---

## 🟢 Phase 0 - Fondations (faite)

Base technique propre, sur laquelle tout le reste repose.

- [x] Page d'accueil avec identité visuelle (header parallax TGV, footer, dark mode)
- [x] Routes de base + pages internes (whoarewe, maps, contribute, donate, légales, api)
- [x] Structure CSS modulaire (general / header / footer / sections)
- [x] **Laravel 13** (structure `bootstrap/app.php`)
- [x] **Inertia 3 + Svelte 5** (toutes pages portées)
- [x] **PostgreSQL 18 + PostGIS 3.6**
- [x] **Valkey 8** (en remplacement de Redis, fork BSD-3 - souveraineté)
- [x] Environnement de développement en conteneurs **Podman sans sudo** (`bin/php`, `bin/composer`, `bin/pnpm`), base et Valkey en conteneurs
- [x] Rédaction des documents projet (README, ROADMAP, CONTRIBUTING, ARCHITECTURE, `docs/`)
- [x] i18n FR (langue de référence), EN et DE - `lang/*` + middleware SetLocale + i18next côté front + LanguageSwitcher ; clés vérifiées par `vff:i18n-check`
- [x] Dark mode fonctionnel (toggle persisté en cookie)
- [x] Perf : hero background PNG 2.7 MB → WebP 179 KB desktop / 117 KB tablet / 63 KB mobile (-93 %)
- [x] CI GitHub Actions : Pint, traductions, PHPStan + Larastan, contrôles statiques (tirets, clés i18n, palette carte, routes), PHPUnit, Prettier, ESLint, svelte-check, build Vite
- [x] Suites de tests parallèles, une base par suite (`VFF_TEST_DB=vff_xxx_test`)
- [~] Tests de mutation (Infection installé, pas encore exploité)
- [~] Mise au format Prettier de tout le dépôt (environ 70 fichiers encore non conformes, contrôle bloquant seulement sur les fichiers modifiés)
- [ ] Suivi des erreurs et de la disponibilité (Sentry ou équivalent auto-hébergé, Uptime Kuma)
- [~] Hooks avant commit (Lefthook) : configuration prête, alignée sur la CI, à installer

🎯 **Jalon "Foundations Done"** : atteint.

---

## 🟡 Phase 1 - Modèle de données & couche métier (faite)

Définir et implémenter le cœur du domaine ferroviaire.

### Schéma

- [x] Table `eras` : **21 époques, une par décennie de 1830 à 2030**. Les époques passées montrent le réseau au 31/12 de leur année ; l'époque 2030 montre le réseau **au jour même**
- [x] Table `lines` (nom officiel + populaires, code, gestionnaire historique, type, LGV, champs encyclopédiques)
- [x] Table `line_segments` (`geometry(LINESTRING, 4326)`, lien `line_id` + `era_id`, statut, trafic, électrification, vitesse, signalisation, PK, provenance de chaque attribut)
- [x] Table `stations` (gare/halte, `geometry(POINT, 4326)`, statut historique, provenance) + `station_eras`, `station_events`, `station_lines`, redirections de slugs
- [x] Table `materials` (matériel roulant)
- [x] Table `line_materials` (pivot matériel ↔ ligne, avec dates)
- [x] Table `line_events` : dates réelles au jour près (jj/mm/aaaa), précision de la date, sections de ligne concernées, source et citation
- [x] Table `media` (photos, documents, plans, y compris sources externes comme Wikimedia Commons)
- [x] Table `contributions` + `contribution_approvals`
- [x] Table `users` (rôle, réputation, présentation, avatar, langue préférée)
- [x] Tables de sources brutes pour le croisement : SNCF Réseau (lignes, électrification, signalisation, vitesses, PN, gares), Wikidata, Wikipédia, OpenHistoricalMap, IGN BD TOPO, communes et contours
- [x] Tables de géométrie de référence par ligne (`line_geometries`, `line_reference_*`, `line_sections`, `line_stops`, `line_signalling`)

### Couche applicative

- [x] Modèles Eloquent avec types spatiaux (Magellan)
- [x] Services de requêtes géo (résolution des sections, PK d'un point, axes uniques, portions voyageurs…)
- [x] Service `OsmImporter` et importeurs pour chaque source (plus de 50 commandes `vff:import-*`)
- [x] Service `GeoExporter` (GeoJSON, tuiles MVT, GTFS)
- [x] Factories pour chaque modèle
- [x] Pipeline de reconstruction de la carte historique (`vff:rebuild-history`) : segments par époque, classification du trafic, voies partagées, graphe, contrôles de cohérence
- [x] Recalcul quotidien de l'époque courante (`vff:refresh-current-era`, déclaré dans le planificateur Laravel)

🎯 **Jalon "Domain Ready"** : atteint (réseau national complet, requêtable, avec ses sources).

---

## 🟠 Phase 2 - Carte interactive (faite)

- [x] Page `/maps` en Svelte
- [x] Intégration MapLibre GL JS dans un composant `<RailMap>`
- [x] Fournisseur de tuiles : fond OpenStreetMap par défaut, MapTiler en option (clé facultative)
- [x] Endpoint `GET /api/v1/eras/{year}/lines.geojson` (et `stations.geojson`)
- [x] Style cartographique custom : rails en orange `#ff6500`, gares en pastilles, statuts et trafics différenciés
- [x] Calques facultatifs OpenRailwayMap et Carto Tchoo
- [x] Calques superposés (bouton « Calques ») : accidents, passages à niveau et signalisation cochés ensemble, un fond OpenRailwayMap au plus, état gardé dans l'adresse (`?layer=…&layer=…`)
- [x] Clic sur une ligne → panneau latéral (`LineDrawer`) avec fiche minimale
- [x] Recherche par nom de ligne / gare (`/api/v1/search`, autocomplétion)
- [x] Légende dynamique (`MapLegend`)
- [x] Tuiles vectorielles MVT (`/api/v1/eras/{year}/tiles/{z}/{x}/{y}.mvt`)
- [x] Géolocalisation de l'utilisateur (sur demande, position jamais envoyée)

🎯 **Jalon "Map MVP"** : atteint.

---

## 🔵 Phase 3 - Sélecteur temporel & couches d'époque (faite)

- [x] Composant `<TimelineSlider>` : 21 positions, de 1830 à 2030
- [x] Transition entre époques
- [x] URL `?era=` partageable
- [x] Époque 2030 = état du réseau au jour même, recalculée chaque jour à partir des événements datés
- [x] Vue « toutes les époques » : chaque tronçon teinté par sa dernière décennie en service (érosion du réseau)
- [x] Comparaison de deux époques (différences superposées, kilomètres par changement, adresse partageable)

🎯 **Jalon "Time Travel"** : atteint.

---

## 🟣 Phase 4 - Fiches détaillées (en cours)

Chaque ligne devient une vraie page d'encyclopédie.

- [x] Page `/lines/{slug}` avec :
  - [x] Carte du tracé par période (`LinePhaseMap`, `LinePhaseSelector`)
  - [x] Chronologie des événements (`LineChronicle`) et frise par décennie (`LineDecades`)
  - [x] Galerie des médias triés par date
  - [x] Liste des gares par époque
  - [x] Longueur, électrification, vitesses, signalisation
  - [x] Sources et notes de sources
  - [ ] Matériel roulant historique (la section existe, la table est vide)
  - [x] Dénivelé : profil en long (altitudes IGN BD TOPO et RGE ALTI, `vff:compute-line-profiles`, section de la fiche, `GET /api/v1/lines/{line}/profile`, GraphQL `Line.profile`, outil MCP `line_profile`) ; 17 lignes calculées, les autres à lancer
- [x] Page `/stations/{slug}` (ouverture, fermeture, lignes desservies, frise par époque, mini-carte)
- [x] Pages `/materials` et `/materials/{slug}` avec 687 séries (voir phase 13)
- [x] Schéma JSON-LD pour le référencement (WebSite, Place, BreadcrumbList, ImageObject, TrainStation) ; à rendre côté serveur pour les aperçus de partage

---

## 🟤 Phase 5 - Contribution communautaire (en cours)

Ouvrir l'édition aux passionnés extérieurs.

- [x] Inscription, connexion, mot de passe oublié, profil (interface Svelte)
- [x] Rôles `visitor`, `contributor`, `validator`, `admin`
- [x] Éditeur de tracé sur la carte, avec fonds historiques IGN (cartes et photographies aériennes anciennes) et brouillon conservé dans le navigateur
- [x] Correction d'un segment existant (`/contribute/edit-segment`)
- [x] Page « Mes contributions »
- [x] Validation : toute contribution est en attente jusqu'à **deux approbations** de validateurs
- [~] Formulaire d'ajout d'événement / média / matériel (tracés et segments faits)
- [ ] Notifications (sur le site + courriel) d'acceptation ou de refus
- [ ] Réputation et badges (le champ existe, aucun calcul)
- [~] Modération : journal d'audit et retrait d'une contribution par un administrateur faits ; liste de blocage à faire
- [x] Signalement d'une erreur depuis le site : bouton « Signaler une erreur » en bas des fiches ligne, gare, matériel et accident (visiteurs et membres, sans traceur ni service externe), liste des signalements dans l'espace de validation (`/validate/reports`), notification des validateurs, journal d'audit, purge 12 mois après le traitement
- [x] Suppression du compte par l'utilisateur, avec anonymisation des contributions, et export de ses données

🎯 **Jalon "Open Encyclopedia"** : un visiteur peut s'inscrire et soumettre sa première contribution en moins de 3 minutes. Presque atteint : il manque les notifications.

---

## 🌐 Phase 6 - API REST publique (en cours)

- [x] OpenAPI 3 généré par [Scramble](https://scramble.dedoc.co/)
- [x] Endpoints : lignes et gares par époque (GeoJSON), tuiles MVT, export GTFS par époque, recherche, historique, PK, arrêts, signalisation et médias d'une ligne, itinéraire, passages à niveau (liste, par ligne, tuiles), tuiles de la signalisation
- [x] Documentation : page `/api`, référence [Scalar](https://scalar.com/) sur `/docs/api`, Swagger UI sur `/docs/swagger`
- [x] Accès libre sans clé, limité à 300 requêtes par minute et par adresse IP
- [x] Exemples intégrés : curl, JavaScript (`fetch`) et Python (`requests`) sur la page `/api` et, pour les principaux points d'accès, dans la référence Scalar (`x-codeSamples`)
- [x] Points d'accès des annuaires, fiches complètes, tronçons, époques, sources, événements, médias, matériel et accidents
- [ ] Jetons API (Sanctum) et quotas par profil (anonyme / inscrit / partenaire)
- [ ] Webhooks pour notifier les changements

---

## 🔮 Phase 7 - GraphQL (en cours)

- [x] [Lighthouse](https://lighthouse-php.com/) installé
- [x] Schéma en lecture : Era, Line, LineSegment, LineEvent, LineStop, LineSignallingRange, LevelCrossing, Station, Material, Route
- [x] Documentation [Magidoc](https://magidoc.js.org/) sur `/docs/graphql` + GraphiQL
- [ ] Mutations pour l'édition (`submitContribution`, `validateContribution`), réservées aux comptes connectés
- [ ] Subscriptions (WebSocket) pour suivre les contributions en temps réel
- [x] **REST reste la référence** pour les consommateurs externes, GraphQL est un complément

---

## 🕸️ Phase 8 - Graphe & itinéraires historiques (en cours)

- [x] Graphe du réseau **par époque**, en trois couches (topologie, gares, lignes), construit par `vff:build-network-graph` dans des tables PostgreSQL
- [x] **Itinéraire historique** : « comment aller de Paris à Brest en 1950 ? » (`PathfindingService`, `/api/v1/route`, planificateur sur la carte)
- [x] Export du graphe au format GTFS
- [ ] [Apache AGE](https://age.apache.org/) et requêtes Cypher : pas activé, le graphe en tables suffit pour l'instant ; à réévaluer pour les requêtes complexes
- [x] **Comparaison d'un trajet entre deux époques** : « Angoulême - Limoges en 1930 et aujourd'hui » (`RouteComparison`, `/api/v1/route/compare`, champ GraphQL `routeComparison`, `compare_year` de l'outil MCP `historical_route`, choix « Comparer avec une autre époque » du planificateur)
- [ ] Visualisation du graphe en mode administration

---

## 🧠 Phase 9 - Reconstitutions 3D assistées par IA 🧪 (pas commencée)

- [ ] Recherche : NeRF, Gaussian Splatting, Stable Diffusion 3D
- [ ] POC : reconstruire une gare désaffectée à partir de 5-10 photos d'archive
- [ ] Pipeline batch : photos → preprocessing (calibration EXIF) → modèle 3D → mesh exportable
- [ ] Visualiseur 3D dans la fiche ligne/gare (three.js ou model-viewer)
- [ ] Annotations 3D collaboratives (placement d'objets disparus)
- [ ] Visite « à la première personne » du lieu reconstitué
- [ ] Microservice Python isolé (pipeline GPU séparé de l'app Laravel)

⚠️ **Cette phase est exploratoire** : faisabilité à confirmer selon la qualité des archives disponibles. Probable partenariat avec un labo / une école.

---

## 📜 Phase 10 - Extension historique (faite pour 1830-2030)

L'objectif initial (1900 → 1980) est dépassé : la carte couvre **1830 → 2030**.

- [x] 21 époques décennales, sans changement de schéma
- [x] UI Timeline adaptée (21 positions)
- [x] Sources historiques croisées : lignes-oubliées (dates et PK), Wikidata, Wikipédia, OpenHistoricalMap, IGN (BD TOPO, cartes et photographies aériennes anciennes), SNCF Open Data ; licences et conditions dans `database/data/source_rights.php`
- [x] **Modèle neuronal d'extraction des voies** sur les photographies aériennes anciennes de l'IGN, publié sous licence MIT : [UnxWares/VFF-Rail-Extraction](https://github.com/UnxWares/VFF-Rail-Extraction) (version `ortho-v1`)
- [x] Jonctions entre lignes : 0 m d'écart à chaque jonction sur les 21 époques, raccords en courbe
- [~] Jonctions : réduire les coudes et crochets restants (phase « escaliers »)
- [ ] Modèle : fusion de plusieurs dates de prises de vue (calcul GPU, sur accord), publication des poids complets
- [ ] Gallica (BnF) et fonds d'archives (SNCF, associations régionales)
- [ ] Distinction visuelle des époques anciennes sur la carte (sépia, dégradé temporel)

---

## 🎓 Phase 11 - Communauté & rayonnement (en cours)

- [x] Multilingue : FR, EN, DE
- [x] Carte intégrable sur d'autres sites (`/maps` en iframe, code d'intégration fourni)
- [x] Pages de crédits (techniques et médias)
- [~] Partenariats : soutien à la campagne AngouLim (réouverture Angoulême - Limoges, lien d'intérêts déclaré)
- [~] Page de dons : en place, plateforme finale (Patreon ou HelloAsso) selon le statut juridique de l'association ESS « UnxWares Opensource », à créer
- [ ] Blog / journal de bord du projet (articles longs sur des lignes emblématiques)
- [ ] Programme de partenariat avec les associations ferroviaires locales (CFTA, AAATV, MFCF…)
- [ ] Présence dans les conférences open data / patrimoine
- [x] Application installable (PWA), avec page hors ligne
- [ ] Mode hors ligne (cache de tuiles + données)

---

## 🚀 Phase 12 - Mise en production (en cours)

- [x] Domaine officiel `vff-project.org`
- [x] Pages légales complètes (2026-10-21) : mentions légales, politique de confidentialité, conditions générales d'utilisation, déclaration d'accessibilité
- [ ] Messagerie de `vff-project.org` : aucun enregistrement MX à ce jour, `contact@` et `privacy@` ne reçoivent rien
- [ ] Hébergement sur l'infrastructure d'UnxWares (le domaine pointe aujourd'hui sur un serveur Hetzner Cloud à Helsinki)
- [ ] Planificateur Laravel en production (recalcul quotidien de l'époque 2030)
- [x] Engagements des pages légales : journaux conservés 12 mois au plus, suppression des comptes inactifs depuis 3 ans (avec avertissement), suppression de compte (phase 5), information des membres avant un changement des CGU
- [x] Correction des défauts de l'audit de l'interface (interface unifiée, contrastes, cibles, clavier : novembre 2026)
- [x] Audit de conformité RGAA interne complet (81,94 %, partiellement conforme), taux publié dans la déclaration
- [ ] Suivi des erreurs et de la disponibilité (phase 0)

---

## 🧭 Phase 13 - Nouveaux domaines de l'encyclopédie (en cours)

- [x] **Signalisation** : données SNCF Réseau importées (`line_signalling`, `/api/v1/lines/{slug}/signalling`), fiche ligne (cantonnement et contrôle de vitesse par PK, trous des données signalés, lien vers la carte), calque « Signalisation » sur `/maps` (tranches placées sur les formes SNCF Réseau, PK calés sur les passages à niveau, tuiles `/api/v1/eras/{year}/signalling/tiles/…`) ; tranches à deux systèmes (ETCS niveau 2 et TVM sur les LGV Est, BPL et SEA) gardées entières (`signalling_systems`)
  - [ ] Historique par époque (seul l'état actuel est connu)
- [x] **Passages à niveau** : 16 801 PN de SNCF Réseau (ODbL), calque « Passages à niveau » sur `/maps` (à partir du zoom 9), section de la fiche ligne (nombre par type, liste repliable), API REST (`/api/v1/level-crossings`, `/api/v1/lines/{slug}/level-crossings`, tuiles), GraphQL (`Line.level_crossings`, `levelCrossings`) et outil MCP `level_crossings`
- [x] **Matériel roulant** : 687 séries importées de Wikidata (CC0) avec leur image Commons et la provenance de chaque champ (`vff:import-materials`), 969 liens ligne × matériel attestés par Wikidata ou par les articles Wikipédia (phrase citée, permalien, fiabilité), API REST (`/api/v1/materials`, `/api/v1/lines/{slug}/materials`) et GraphQL ; à faire : fiches enrichies sur le site, périodes de circulation par portion
- [x] **Accidents** : 202 fiches (Wikidata, Wikipédia, BEA-TT), pages, API, calque de carte
- [x] **Serveur MCP** pour que les assistants IA interrogent l'encyclopédie avec ses sources (17 outils dont accidents, comparaison d'ères et passages à niveau, lecture seule, `docs/MCP.md`)

---

## 🌱 Idées en vrac (backlog non priorisé)

- [x] Calcul d'itinéraire historique : « comment serais-je allé de A à B en 1950 ? » (phase 8)
- [x] Couches thématiques : électrification, signalisation, écartement
- [x] Recensement des passages à niveau (carte, fiches et API, phase 13)
- [ ] Mode « vue ferroviaire » : carte simplifiée façon schéma
- [x] Comparaison de deux époques (superposition, phase 3)
- [ ] Stories d'archives (carrousel de photos sur un événement précis)
- [ ] Quiz / mini-jeu : reconnaître la ligne / le matériel
- [ ] Export GPX/KML d'un tracé
- [ ] Voies vertes sur d'anciennes lignes (données OSM importées) : itinéraires pour les cyclistes

---

*Cette roadmap est un document vivant. Toute proposition est bienvenue : ouvre une [issue](https://github.com/UnxWares/VFF-Project/issues) avec le label `roadmap`.*

*À jour : 2026-10-21.*
