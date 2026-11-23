# 🛤️ Roadmap VFF

> *De la page d'accueil à la plus grande encyclopédie ferroviaire de France.*

Ce document détaille les phases de développement de VFF. Les phases sont **indicatives, pas strictement séquentielles** : certaines tâches peuvent être menées en parallèle. Les cases cochées reflètent l'état réel du dépôt.

**Légende** : `[x]` fait · `[ ]` à faire · `[~]` en cours ou partiel · `🎯` jalon majeur · `🧪` expérimentation

---

## 📍 Où en est-on ? (2026-11-23)

| Phase | État |
|:-|:-|
| 0 - Fondations | ✅ faite ; seule l'installation du suivi des erreurs (Kener, GlitchTip) attend le serveur de production |
| 1 - Modèle de données | ✅ faite |
| 2 - Carte interactive | ✅ faite |
| 3 - Sélecteur temporel | ✅ faite (21 époques de 1830 à 2030, comparaison, rideau, vue empilée) |
| 4 - Fiches détaillées | 🟡 toutes les fiches existent ; restent trois applications en base (altitude de la voie estimée d'après le terrain, trous du tracé sous le profil, 70 ouvertures datées au jour) et le profil des lignes sans tracé ouvert |
| 5 - Contribution | ✅ faite ; le courriel des notifications attend la messagerie du domaine |
| 6 - API REST | ✅ faite |
| 7 - GraphQL | 🟡 faite dans le code ; le temps réel (Reverb) attend le serveur de production |
| 8 - Graphe & itinéraires | ✅ faite : itinéraires historiques et comparaison sur un graphe en tables ; Apache AGE non retenu, à rouvrir si une requête l'exige (décision du 23/11) |
| 9 - Reconstitutions 3D 🧪 | ⬜ pas commencée, hors de ce bilan |
| 10 - Extension historique | 🟡 couverture 1830-2030 faite ; fusion des sources à 1 m : pilote du Calvados à appliquer, puis passe nationale ; modèle sur photos aériennes (GPU, sur accord) |
| 11 - Communauté | 🟡 faite côté site ; dons : rien avant la création de l'association UWOSC, ensuite HelloAsso ; partenariats et conférences suivis dans `docs/PLAN-COMMUNICATION.md` ; fond de carte libre en ligne à la mise en production (décision du 23/11) |
| 12 - Mise en production | 🟡 tout ce qui se prépare sans serveur est prêt et documenté, en un seul parcours (`docs/MISE-EN-PRODUCTION.md`, `deploy/bin/mise-en-production.sh --verifier`) ; restent la messagerie (chez Hetzner, comme le reste d'UnxWares : MX à poser), l'hébergement et le déroulé du parcours sur le serveur |
| 13 - Nouveaux domaines | 🟡 domaines en place ; signalisation datée (164 faits, sources libres lues), médias et accidents en enrichissement continu |

**En base (développement, lecture du 23/11/2026 vers 15 h)** : 2 690 lignes, 9 395 gares, 15 841 événements datés, 114 803 médias (4 697 gares et 623 lignes illustrées), 21 époques, 885 séries de matériel roulant, 951 fiches d'accidents, 164 faits datés de signalisation, profil en long de 2 630 lignes (729 534 points), 1 094 portions de voies, 1 528 de radio sol-train et 5 507 quais de SNCF Réseau. Les adresses de lignes neutres sont appliquées (aucune adresse `archeo-` restante). Le moteur de la carte est rejoué par étapes : certains chiffres bougent encore.

**Qualité** : plus de 10 000 tests PHPUnit, plus de 1 200 tests vitest et une suite Playwright de fumée (54 tests, tâche de CI), Larastan sans erreur, contrôle des invariants de données (`vff:check-invariants`), des traductions (`vff:i18n-check`) et des droits des sources (`vff:source-rights`), le tout en CI ; contrôles avant commit Lefthook (installés) ; `bin/test` pour lancer des suites ciblées en parallèle (`docs/TESTS.md`) ; audit RGAA 4.1.2 interne : 100 % (totalement conforme, 32 pages).

**Ce qui reste**, hors 3D (bilan point par point : `storage/app/reports/agents/aprem-2111/roadmap-cloture.md`) :

1. **À appliquer en base** (code commité et testé, scripts prêts, lancés par le coordinateur dans l'ordre de `aprem-2111/APREM.md`, points 72 à 75) : gares et lignes, matériel, fusion des lignes du même couloir, seconde série d'ouvertures datées au jour, pilote de fusion du Calvados, altitude de la voie estimée, passe réseau Panoramax, trous du tracé, accidents relus dans la presse, fiches de gares fusionnées ; puis repasser les audits des tronçons et des gares.
2. **Serveur de production** : messagerie de `vff-project.org`, hébergement chez UnxWares, Kener et GlitchTip, planificateur, Reverb, relais des vignettes de Commons, fond de carte servi par nginx, sauvegarde de la base : un seul parcours en 19 étapes, `docs/MISE-EN-PRODUCTION.md` (`deploy/bin/mise-en-production.sh --verifier` dit ce qui manque).
3. **Décisions de Baptiste** : toutes les recommandations de la liste du 23/11 sont retenues (réponses de 21 h 25, en tête de `storage/app/reports/agents/QUESTIONS.md`) et reportées dans les documents : messagerie de `vff-project.org` chez Hetzner (`contact@`, `privacy@`), sauvegarde de la base de production chez Infomaniak Swiss Backup, dons seulement après la création de UWOSC (HelloAsso), partenariats et conférences dans `docs/PLAN-COMMUNICATION.md`, gares desservies seulement par car laissées actives jusqu'à une source, fragments de Domfront : retour à l'archive d'avant puis relecture des raccords 724 et 3205.
4. **Travail de données continu**, avec le seuil qui le rend « fait » (seuils validés le 23/11) :
   - audit des gares : toutes les gares de plus de 100 000 voyageurs par an relues (634 gares relues dans l'ordre de fréquentation, jusqu'à 50 000 voyageurs) et chaque cas « à décider » tranché (62 restent) ;
   - audit des tronçons : les 21 ères repassées avec les règles actuelles et au moins 90 % des kilomètres sûrs en 2030 (83,4 % au dernier calcul) ;
   - profil en long : chaque ligne dont le tracé est ouvert a un profil, avec l'erreur de l'altitude estimée quand la voie n'est pas mesurée ;
   - fusion à 1 m : passe nationale appliquée, tracés en service à 5 m au plus de la plateforme mesurée, 0 m aux jonctions ;
   - signalisation datée : chaque ligne classique en service a sa mise en service datée et sourcée, ou la mention qu'aucun document libre ne la date ;
   - médias : chaque gare ouverte aux voyageurs illustrée, et chaque fonds repéré (`aprem-2111/sources-images*.md`) intégré ou écarté avec sa raison ;
   - archives : chaque fonds du répertoire `docs/ARCHIVES.md` rattaché ou écarté avec sa raison.

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
- [x] CI GitHub Actions : Pint, traductions, PHPStan + Larastan, contrôles statiques (tirets, clés i18n, palette carte, routes), PHPUnit, Prettier, ESLint, svelte-check, build Vite, suite Playwright de fumée sur un jeu de données minimal (`E2eSeeder`)
- [x] Suites de tests parallèles, une base par suite (`VFF_TEST_DB=vff_xxx_test`)
- [x] Contrat des commandes : toute commande qui écrit accepte `--dry-run`, vérifié par un test (`DryRunCommandsTest`, qui couvre aussi les chiffres du réseau et les vitesses)
- [x] Tests de mutation : services purs (`make mutation`) et règles métier critiques (`make mutation-critical` : moteur de la carte historique, desserte des gares, validation à deux, dates partielles, énumérations, matériel, images), 93 % des mutants tués, seuil de 92 % qui bloque les régressions ; hors CI bloquante (un quart d'heure)
- [x] Suite Playwright de fumée avec axe-core (54 tests : pages principales, deux largeurs d'écran, français et anglais), en local et en CI (`docs/TESTS-E2E.md`)
- [x] Mise au format de tout le dépôt : Pint (PHP) et Prettier (front, tests JS, scripts, configuration JS) contrôlent tout le dépôt et bloquent en CI
- [x] Suivi des erreurs et de la disponibilité, part application : page de santé `/up/details` (base, Valkey, file, disque, âge du calcul de l'ère, version), erreurs du serveur vers GlitchTip ou Bugsink si une adresse DSN est configurée (sans donnée personnelle), erreurs JavaScript du navigateur journalisées, `docs/SUIVI-ERREURS.md`
- [ ] Suivi des erreurs et de la disponibilité, part hébergement : installer Kener et GlitchTip sur l'infrastructure d'UnxWares, poser `HEALTH_TOKEN`, `SENTRY_LARAVEL_DSN` et `APP_VERSION`, configurer moniteurs et alertes (étape 17 de `docs/MISE-EN-PRODUCTION.md`, détail dans `docs/SUIVI-PRODUCTION.md`)
- [x] Hooks avant commit (Lefthook) : installés (`node_modules/.bin/lefthook install --force` avec un `core.hooksPath` local au dépôt ; `lefthook-local.yml`, non suivi, relance les crochets globaux de chaque poste)

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
- [x] Service `OsmImporter` et importeurs pour chaque source (plus de 40 commandes `vff:import-*`)
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
- [x] Calque facultatif OpenRailwayMap (le calque Carto Tchoo a été retiré)
- [x] Calques superposés (bouton « Calques ») : accidents, passages à niveau et signalisation cochés ensemble, un fond OpenRailwayMap au plus, état gardé dans l'adresse (`?layer=…&layer=…`)
- [x] Clic sur une ligne → panneau latéral (`LineDrawer`) avec fiche minimale
- [x] Recherche par nom de ligne / gare (`/api/v1/search`, autocomplétion)
- [x] Page de résultats complets `/search` (Entrée ou « Tous les résultats » depuis la recherche), correspondances surlignées et suggestion « vouliez-vous dire », même moteur que l'en-tête et les trois API
- [x] Légende dynamique (`MapLegend`)
- [x] Tuiles vectorielles MVT (`/api/v1/eras/{year}/tiles/{z}/{x}/{y}.mvt`), avec `?fields=map` pour la carte du site : seulement les propriétés dont elle a besoin, environ 60 % d'octets de moins (réponse par défaut inchangée)
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
- [x] Rideau de comparaison : deux époques sur une même carte, séparées par un rideau qu'on déplace, dans l'adresse

🎯 **Jalon "Time Travel"** : atteint.

---

## 🟣 Phase 4 - Fiches détaillées (en cours)

Chaque ligne devient une vraie page d'encyclopédie.

- [x] Page `/lines/{slug}` avec :
  - [x] Carte du tracé par période (`LinePhaseMap`, `LinePhaseSelector`)
  - [x] Chronologie des événements (`LineChronicle`) et frise par décennie (`LineDecades`)
  - [x] Galerie des médias triés par date (même galerie sur les fiches gare et série de matériel quand elles ont des images ; images des événements dans la chronologie)
  - [x] Liste des gares par époque
  - [x] Longueur, électrification, vitesses, signalisation
  - [x] « Où et quand » : schéma espace-temps de la ligne, une bande par phase, mises bout à bout le long de la ligne et reliées à la carte de la phase
  - [x] Vitesses maximales par PK (SNCF Réseau, ODbL) et changements de vitesse datés lus dans Wikipédia (`vff:import-line-speeds`), section de la fiche, `GET /api/v1/lines/{line}/speeds`, GraphQL `Line.speeds` et outil MCP `line_speeds`
  - [x] Sources et notes de sources
  - [x] Matériel roulant historique : 2 586 liens ligne × série attestés (relations Intercités et TER routées par les gares et les extrémités nommées, trois règles de routage écrites, aucun lien réimporté sans relecture), avec les années de circulation quand une source les date (`vff:import-material-periods`) ; séries les plus récentes d'abord, avec photo et année de fin de circulation sur la fiche de ligne (243 séries reliées sur 266 ont une image)
  - [x] Dénivelé : profil en long (altitudes IGN BD TOPO et RGE ALTI, `vff:compute-line-profiles`, section de la fiche, `GET /api/v1/lines/{line}/profile`, GraphQL `Line.profile`, outil MCP `line_profile`)
  - [x] Profil suivi sur le tracé de référence de la ligne entière, toutes époques : 2 630 lignes au lieu de 1 522 (lecture du 23/11 ; 2 645 avant les fusions de lignes) ; 729 534 points rangés dans `line_profile_points` avec leur PK estimé (sur la voie en BD TOPO ou sur le terrain en RGE ALTI) ; `GET /api/v1/lines/{line}/profile/points` et `/altitude?pk_m=` ; sens des PK officiels, sinon du nom de la ligne ; déclivité maximale publiée seulement si mesurée sur la voie (751 lignes) ; 11 lignes à crémaillère avec leur déclivité sourcée ; 213 lignes de plus avec la déclivité de l'infobox de Wikipédia et son permalien (`database/data/sourced_grades.php`) ; positions de tracés non redistribuables retenues (`position_withheld`)
  - [~] Altitude de la voie estimée d'après des coupes RGE ALTI là où BD TOPO n'a pas la voie, étalonnée sur le LiDAR HD de l'IGN (93 % des points à moins de 1 m quand le tracé est à 5 m de la voie), servie avec son erreur mesurée (`99d608af`) ; mesurée de nouveau de bout en bout sur 5 lignes mesurées par la BD TOPO : 84 % des points à moins de 1 m avec un tracé sur la voie, 81 % à 5 m, 57 % à 10 m (le terrain seul : 78, 66 et 49 %) ; recalcul par lots des 2 106 lignes qui n'ont que le terrain (434 224 points, `--terrain-only`, 4 à 6 h de requêtes à l'IGN) et migration à lancer sur `vff`
  - [x] Profil : déclivité d'une source écrite à côté de la mesure publiée, avec l'écart (`sourced_grade_compared`, 317 lignes), jamais une déclivité estimée présentée comme mesurée (lecture au survol dite « estimée » sur le terrain) ; 515 déclivités de Wikipédia lues dans l'infobox ou le texte des articles en cache par `vff:import-wikipedia-grades` (`--dry-run`, 37 de plus), 251 lignes sans mesure publiée en ont une ; tracé de référence incomplet dit sur la fiche et dans les trois API (`trace_holes` : 605 lignes, 937 trous, 1 391 km à vol d'oiseau, liste par `vff:profile-trace-holes`)
  - [~] Trous du tracé sous le profil, cause par cause (23/11) : les trois quarts n'étaient pas des trous mais un défaut d'assemblage du parcours (raccord en T posé au milieu d'un morceau, que le profil ne savait pas prendre) ; corrigé, relu sur le tracé actuel : 902 trous et 1 203 km à vol d'oiseau deviennent 244 trous et 235 km, 57 trous de plus de 5 km deviennent 9 ; section portée par une autre ligne VFF (Saint-Georges-Motel - Grand-Quevilly sur Dreux - Acquigny, Bourges - Miécaze sur Eygurande - Bort, Saint-Germain-des-Fossés - Darsac, Grande Ceinture, Ouest-Ceinture - Chartres) reprise du dessin de cette ligne, au bit près ; voie de Coni - Vintimille retirée de Nice - Breil-sur-Roya. Application à lancer (`storage/app/reports/agents/aprem-2111/trous-trace-appliquer.sh`, 1 h environ, puis les profils de 637 lignes)
  - [ ] Profil : 1 622 lignes sans déclivité ni mesurée sur la voie ni sourcée (terrain seul sous un tracé ancien), 60 lignes sans profil ; restent après l'application une trentaine de lignes disparues sans tracé ouvert (voies industrielles, minières et militaires connues seulement par archeo) et Somain - Halluin vers Tourcoing (7,9 km)
- [x] Page `/stations/{slug}` (ouverture, fermeture, lignes desservies, frise par époque, mini-carte) ; une ouverture connue à un intervalle s'écrit « entre A et B » (fiche, liste de la ligne, annuaire, `opened_after` en REST, GraphQL et MCP) ; liens gare × ligne faux exclus par un fichier de corrections lu par `vff:attach-stations`
- [x] Vie d'une gare en une chronologie datée sur sa fiche : événements regroupés, dessertes, noms, accidents proches, vues aériennes ; servie aussi par REST (`chronology` de la fiche) et GraphQL
- [x] Schéma d'une ligne par PK (gares, bifurcations, passages à niveau, ouvrages, voies, énergie, signalisation, vitesses, par époque), chargé à la demande : `GET /api/v1/lines/{line}/schematic`
- [x] Ouvrages d'art : tunnels, viaducs et grands ponts de chaque ligne dans l'ordre des PK, lignes fermées comprises (IGN BD TOPO et Wikidata, `vff:import-structures`, 3 145 ouvrages), `GET /api/v1/lines/{line}/structures`, `Line.structures`, outil MCP `line_structures`
- [x] Histoire de l'électrification d'une ligne par section : plages de PK sous leur tension (SNCF Réseau) et mises sous tension, conversions et dépose datées lues dans Wikipédia et Wikidata, en REST, GraphQL et MCP
- [x] Vitesses maximales par PK et changements datés appliqués à la base (`vff:import-line-speeds`, 2 373 tranches, le 23/11)
- [x] Fiche de gare complétée : fréquentation annuelle de SNCF Gares & Connexions (3 020 gares, rupture de série 2024-2025 signalée), noms successifs datés (Wikipédia et Wikidata), accessibilité et services (2 796 gares), desserte d'un mardi type (2 832 gares et 262 lignes, horaires ouverts SNCF et Transilien), patrimoine bâti protégé (Mérimée et Wikidata, 211 gares), gares voisines par PK
- [x] Bibliographie « Pour aller plus loin » sur les fiches de ligne et de gare : livres et articles du catalogue de la BnF, références de Wikipédia et Wikidata, chacun avec son lien et la raison du rattachement (887 références, 914 liens)
- [x] Pages « Le rail à <commune> » (16 024 communes avec du rail), fiches et tableau des départements (longueur et gares décennie par décennie), « Autour de moi » (gares et lignes proches de la position ou d'une commune)
- [x] Compagnies ferroviaires historiques (`/companies`) : dates, événements sourcés, lignes rattachées par concessions datées, réseau par décennie et carte légère
- [x] Projets de lignes pas encore en service (`/projects`) : réouvertures, lignes nouvelles, LGV, RER métropolitains, électrifications, chaque étape datée et sourcée
- [x] Impression : feuille de style dédiée, les fiches s'impriment et s'enregistrent en PDF A4 propre
- [x] Page « Le réseau en chiffres » (`/network`) : agrégats par décennie calculés par `vff:compute-network-figures`, `GET /api/v1/stats/network`
- [x] Page « Ce que sait l'encyclopédie » (`/network/coverage`) : couverture mesurée des données (lignes, gares, accidents, matériel), en nombre et en part, avec la part de sources ouvertes, calculée par `vff:compute-coverage-figures`, `GET /api/v1/stats/coverage`
- [x] Adresses de lignes neutres : `vff:neutralize-line-slugs` retire le préfixe de source des adresses, redirections 301 des anciennes (REST, GraphQL, MCP) ; appliqué à la base le 23/11 (1 614 lignes renommées, 0 collision, aucune adresse `archeo-` restante ; `aprem-2111/appliquer-slugs-neutres/`)
- [x] Pages `/materials` et `/materials/{slug}` avec 727 séries (voir phase 13)
- [x] Schéma JSON-LD pour le référencement (WebSite, Place, BreadcrumbList, ImageObject, TrainStation), rendu par le serveur avec les balises de partage (`resources/views/partials/share-tags.blade.php`, 1c6ee8db et 1735c043)

---

## 🟤 Phase 5 - Contribution communautaire (faite)

Ouvrir l'édition aux passionnés extérieurs.

- [x] Inscription, connexion, mot de passe oublié, profil (interface Svelte)
- [x] Rôles `visitor`, `contributor`, `validator`, `admin`
- [x] Éditeur de tracé sur la carte, avec fonds historiques IGN (cartes et photographies aériennes anciennes) et brouillon conservé dans le navigateur
- [x] Correction d'un segment existant (`/contribute/edit-segment`)
- [x] Correction d'un événement daté depuis la chronologie de la fiche (lien « Corriger », formulaire prérempli, raison publiée, citation courte de la source) : après deux approbations, l'événement corrigé est publié et l'ancien neutralisé comme une correction sourcée, neutralisation rejouée par `vff:apply-event-corrections` après chaque import ; aussi par la mutation (`corrects_event_id`)
- [x] Page « Mes contributions »
- [x] Validation : toute contribution est en attente jusqu'à **deux approbations** de validateurs
- [x] Formulaires d'ajout : tracés, segments, événements datés (`/contribute/event`, publiés dans `line_events` avec leur provenance `contribution:<id>`, portion résolue ensuite par `vff:resolve-event-sections`), images (`/contribute/media` : fichier de Wikimedia Commons ou fichier déposé pour une ligne, une gare, un événement ou une série de matériel, métadonnées et position GPS retirées avant l'enregistrement, fichier privé jusqu'à la publication ; images d'une série dans `GET /api/v1/media?material=<slug>`), matériel sur une ligne (`/contribute/material` : règles d'écartement et d'électrification de l'import, exception acceptée seulement avec la phrase de la source qui nomme l'écartement ou la tension ; lien et période publiés dans `line_materials` et `line_material_periods` avec la provenance `contribution:<id>`) et séries de matériel (`/contribute/series` : nouvelle série ou correction, identifiant Wikidata vérifié, doublon renvoyé vers la correction), aussi par la mutation GraphQL `submitContribution` ; liens « Proposer » sur les fiches ligne, gare et matériel
- [x] Notifications (sur le site + courriel) d'acceptation ou de refus
- [x] Réputation : trois chiffres (contributions acceptées, refusées, relectures faites) et un niveau tiré de seuils en configuration (`config/reputation.php`), recalculés à chaque décision de validation et par `vff:recalculate-reputation`, affichés sur le profil et dans l'espace de validation, expliqués sur `/contribute/reputation`, jamais dans l'API ; pas de badges
- [x] Modération : journal d'audit, retrait d'une contribution par un administrateur, suspension d'un compte (motif, fin facultative, levée, notification, export) faits
- [x] Signalement d'une erreur depuis le site : bouton « Signaler une erreur » en bas des fiches ligne, gare, matériel et accident (visiteurs et membres, sans traceur ni service externe), liste des signalements dans l'espace de validation (`/validate/reports`), notification des validateurs, journal d'audit, purge 12 mois après le traitement
- [x] Suppression du compte par l'utilisateur, avec anonymisation des contributions, et export de ses données

🎯 **Jalon "Open Encyclopedia"** : un visiteur peut s'inscrire et soumettre sa première contribution en moins de 3 minutes. Atteint : notifications sur le site faites, le courriel attend la messagerie de vff-project.org.

---

## 🌐 Phase 6 - API REST publique (faite)

- [x] OpenAPI 3 généré par [Scramble](https://scramble.dedoc.co/)
- [x] Endpoints : lignes et gares par époque (GeoJSON), tuiles MVT, export GTFS par époque, recherche, historique, PK, arrêts, signalisation et médias d'une ligne, itinéraire, passages à niveau (liste, par ligne, tuiles), tuiles de la signalisation
- [x] Documentation : page `/api`, référence [Scalar](https://scalar.com/) sur `/docs/api`, Swagger UI sur `/docs/swagger`
- [x] Accès libre sans clé, limité à 300 requêtes par minute et par adresse IP (davantage avec un jeton personnel)
- [x] Exemples intégrés : curl, JavaScript (`fetch`) et Python (`requests`) sur la page `/api` et, pour les principaux points d'accès, dans la référence Scalar (`x-codeSamples`)
- [x] Points d'accès des annuaires, fiches complètes, tronçons, époques, sources, événements, médias, matériel et accidents
- [x] Jetons API (Sanctum, lecture seule, créés depuis le profil) et quotas par profil (anonyme 300, membre 1 200, partenaire 6 000 requêtes par minute, `config/api.php`), compteur commun à REST, GraphQL et MCP ; échéance à un an, rappel par courriel 30 jours avant et renouvellement d'un clic depuis le profil
- [x] Contrat de l'API versionné : instantané `tests/Fixtures/api-contract.json` (REST, GraphQL, MCP) mis à jour par `vff:api-contract`, test qui échoue si un champ, un paramètre ou une valeur disparaît sans annonce, journal daté `docs/API-CHANGELOG.md`
- [x] Infrastructure SNCF Réseau : nombre de voies et régime d'exploitation (`vff:import-sncf-track-regime`), liaison radio sol-train dont GSM-R (`vff:import-sncf-radio`) et quais des gares (`vff:import-sncf-platforms`), servis par `GET /api/v1/lines/{line}/infrastructure`, `Station.platforms`, GraphQL `Line.infrastructure` et l'outil MCP `line_infrastructure` ; imports appliqués à la base le 23/11 (1 094 portions de voies, 1 528 de radio, 5 507 quais ; `aprem-2111/apres-sncf-infra/`)
- [x] Politique de sécurité du contenu bloquante en production (`CSP_ENFORCE=true`, décision du 22/11 ; en observation en développement), rapports sans donnée personnelle, aucune violation sur les pages principales avec la politique bloquante (`docs/SECURITE-CSP.md`)
- [x] Export GTFS valide au validateur de MobilityData 8.0.1 (0 erreur en 1930, 1980 et 2030) : distances d'arrêts le long de la forme, trajets de deux arrêts au moins, horaires synthétiques à 60 km/h (fictifs), contact du flux et `attributions.txt` (ODbL) ; formes des trajets prolongées le long de la voie d'une autre ligne jusqu'à la gare terminus
- [x] Recherche commune à l'en-tête, à la carte, aux annuaires, au REST, au GraphQL et au MCP : insensible aux accents, aux tirets et aux abréviations, préfixes de mots, tolérance aux fautes de frappe (trigrammes), tri par pertinence, clés de recherche générées et indexées (migration appliquée) ; bonne fiche en premier dans 98,7 % de 78 requêtes d'essai (14 % avant), mesure du 22/11 (`aprem-2111/recherche.md`)
- [x] En-têtes de sécurité sur toutes les réponses (HSTS, `nosniff`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`), limites GraphQL (400 requêtes par lot, 2 Mio de corps, erreur 413) et limites par IP sur les formulaires d'authentification, cookie de session sécurisé par défaut (`docs/API.md`, `docs/DEPLOIEMENT-NGINX.md`)
- [x] Données ouvertes : `vff:export-open-data` produit des jeux ODbL (réseau par époque, événements datés, accidents, matériel roulant) avec LISEZMOI, licence et archive ZIP, limités aux sources dont la licence le permet, lus par la page `/open-data` ; lancée à la main, jamais planifiée
- [x] Points d'accès de la nuit du 22 au 23/11 : `GET /api/v1/chronology`, `/on-this-day`, `/tourist-railways`, `/heritage`, `/projects`, `/companies`, `/stats/coverage`, `/accidents/statistics`, `/lines/{line}/at-pk`, `/lines/{line}/structures`, `/lines/{line}/schematic`, `/stations/{station}/heritage`, `/eras/{year}/in-view` et `/materials/{material}/map`, tous au contrat versionné
- [x] Webhooks pour notifier les changements : abonnement depuis le profil (adresse HTTPS, événements `contribution.applied`, `current_era.refreshed`, `accident.published`, `material.published`), messages JSON signés HMAC-SHA256 avec horodatage, envoi par la file avec essais espacés et désactivation après échecs répétés, protection SSRF à chaque envoi

---

## 🔮 Phase 7 - GraphQL (en cours)

- [x] [Lighthouse](https://lighthouse-php.com/) installé
- [x] Schéma en lecture : Era, Line, LineSegment, LineEvent, LineStop, LineSignallingRange, LevelCrossing, Station, Material, Route
- [x] Documentation [Magidoc](https://magidoc.js.org/) sur `/docs/graphql` + GraphiQL
- [x] Mutations pour l'édition (`submitContribution`, `validateContribution`), réservées aux comptes connectés : jeton personnel avec la capacité `contribute` (case du profil, décochée par défaut) ou session du site avec contrôle CSRF ; mêmes règles et même code que l'espace de validation du site (`App\Services\Contributions`)
- [x] Subscriptions (WebSocket) pour suivre les contributions en temps réel, côté application : Laravel Reverb, canaux Laravel Echo (`contributions` public, `review-queue` et `users.{id}.contributions` privés), abonnements GraphQL `contributionApplied`, `myContributionChanged` et `reviewQueueChanged`, file de validation mise à jour sans recharger la page ; désactivé par défaut (`REALTIME_ENABLED`, `docs/API.md` section « Temps réel »)
- [~] Temps réel en production : prêt, reste à poser sur le serveur (étape 12 de `docs/MISE-EN-PRODUCTION.md` : variables `REALTIME_*` et `REVERB_*`, `vff-reverb.service` ; l'étape 14 active le proxy WebSocket nginx `deploy/nginx/snippets/vff-reverb.conf`) ; les webhooks `contribution.applied` couvrent le besoin en attendant
- [x] **REST reste la référence** pour les consommateurs externes, GraphQL est un complément

---

## 🕸️ Phase 8 - Graphe & itinéraires historiques (faite)

- [x] Graphe du réseau **par époque**, en trois couches (topologie, gares, lignes), construit par `vff:build-network-graph` dans des tables PostgreSQL
- [x] **Itinéraire historique** : « comment aller de Paris à Brest en 1950 ? » (`PathfindingService`, `/api/v1/route`, planificateur sur la carte)
- [x] Export du graphe au format GTFS
- Apache AGE et requêtes Cypher : non retenu (décision du 23/11), le graphe en tables suffit (itinéraires, comparaison, carte du graphe) ; à rouvrir si une requête l'exige. Point sorti des tâches.
- [x] **Comparaison d'un trajet entre deux époques** : « Angoulême - Limoges en 1930 et aujourd'hui » (`RouteComparison`, `/api/v1/route/compare`, champ GraphQL `routeComparison`, `compare_year` de l'outil MCP `historical_route`, choix « Comparer avec une autre époque » du planificateur)
- [x] **Visualisation du graphe en mode administration** : carte des trois couches par époque, attributs au clic, diagnostics (îles, nœuds isolés, arêtes anormales, jonctions écartées, gares sans arête, lignes en morceaux), jonctions filtrées par type et par ligne, export CSV de chaque liste, sur `/validate/graph`, réservée aux administrateurs (`docs/GRAPHE-ADMIN.md`)

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
- [x] **Modèle neuronal d'extraction des voies** sur les photographies aériennes anciennes de l'IGN, publié sous licence MIT : [UnxWares/VFF-Rail-Extraction](https://github.com/UnxWares/VFF-Rail-Extraction) (version `ortho-v1`) ; le dépôt gagne le 22/11 la lecture de l'axe de plateforme dans le LiDAR HD de l'IGN, avec une erreur estimée par point, et l'affinage des tracés grossiers sur les photos recalées (décalages par couche, fusion de plusieurs dates, filtre à 3 m)
- [~] Affinage sur photos aériennes : aucun faux « précis » sur le contrôle à vérité BD TOPO (47,6 km), mais un rendement nul avec deux dates exigées, car `ortho-v1` rend des plateaux de 20 à 30 m de large (`aprem-2111/affinage-ortho.md`)
- [ ] `ortho-v2` : modèle centré sur l'axe de la voie (2 à 3 h de GPU), à lancer sur accord avant toute passe nationale
- [x] Jonctions entre lignes : 0 m d'écart à chaque jonction sur les 21 époques, raccords en courbe
- [~] Jonctions : réduire les coudes et crochets restants (phase « escaliers »). Appliqués sur `vff` le 22/11 : bout posé sur la voie d'une autre ligne (5 bis), fragments isolés de moins de 60 m retirés. Appliqués la nuit du 22 au 23/11 : raccords en équerre lissés (7 e), bouts prolongés jusqu'à la gare sans forme SNCF (10), raccords droits pendants retirés (8 ter) ; jonctions latérales en cours d'application (193 lignes rejouées) ; retraits à la main des fragments 2936, 45 et 151 arrêtés par prudence à Domfront (7 jonctions à 0 m perdues pour 6 tolérées) ; décision du 23/11 : option (a), revenir à l'archive d'avant, relire les raccords 724 et 3205 sur la carte, puis relancer. Nouvel invariant `lines.junction_gap_lateral` pour un bout de ligne qui s'arrête à côté d'une voie directe dans une gare commune
- [x] Lignes de section fusionnées dans leur ligne à code incomplète, même sans extrémité partagée (`vff:merge-split-lines`, passe `sections` : 12 tracés complétés, 470 km de plus mesurés sur la copie) ; passe « couloir » commitée le 22/11 : une ligne créée qui longe sa ligne codée dans le même couloir, avec la même catégorie et les mêmes noms de lieux, y est fusionnée (4 fusions prévues) ; application au réseau à lancer après les jonctions latérales (`doublons-couloirs/applique.sh`)
- [~] Chronologies par étapes : l'import Wikipédia lit une date par section nommée, les dates partagées, « respectivement », les verbes au présent et les gabarits de date en barres obliques, ISO ou numérotés ; mesure sur les 860 articles en cache : 62 événements gagnés, 1 faux positif perdu, 65 lignes touchées. Réimport appliqué sur `vff` le 23/11 à 2 h 04 (`aprem-2111/apres-chronologie/`). Neutralisés : 17 retranchements issus de décrets annulés par le Conseil d'État, deux fermetures OpenHistoricalMap et un déclassement de 2008 annulés par les tribunaux ; 70 ouvertures de 27 lignes voyageurs restées à l'année (ou au 1er janvier) datées au jour d'après la chronologie de l'article, puis 70 ouvertures de plus sur 56 lignes (les lignes restées sans jour dans leur article, lues dans les gares, la bibliographie Palau et les listes chronologiques ; Creil - Beauvais et Rennes - Saint-Malo tranchés par une seconde source, l'inauguration du 28/06/1857 neutralisée), soit 140 ouvertures au jour (`line_event_corrections_sections_dates.php`) : la première série appliquée le 23/11 à 13 h 56 (`aprem-2111/sections-dates-application/`), la seconde en file (`sections-dates-2-chaine.sh`), puis une troisième passe : 11 ouvertures de plus lues dans les articles des gares (570000, 690000, 720000, 304000, 468000, 420000), Compiègne - Noyon tranché (ouverture officielle du 26/02/1849, inauguration du 25) et 68 fermetures, réouvertures temporaires de guerre et ouvertures aux voyageurs au jour sur 1930-1990 (`line_event_corrections_fermetures_dates.php`, deux réouvertures mal lues neutralisées sur 840000)
- [~] **Fusion des sources de géométrie à 1 m** : tracé visé à 1 m, 5 m tolérés, 0 m d'écart à une jonction ; un morceau n'est « précis » que si son erreur estimée est sous 3 m, sinon « estimé » (rapport du 22/11 : erreur de chaque source mesurée sur 282 lignes en service, algorithme validé, prototype sur 6 lignes). Fait et commité : lecture de la plateforme LiDAR HD avec sa fiabilité par morceau, commande `vff:import-fused-traces` et lecture des morceaux fusionnés avec leur erreur estimée (source `fusion` dans `source_traces`, rien d'écrit sans `--write`). Intégration au moteur commitée le 23/11 : morceau fusionné précis candidat au dessin après la BD TOPO et OSM, faible aux jonctions
- [~] Fusion nationale : pilote sur le Calvados prêt à appliquer (31 lignes fusionnées, 38 rejouées), mesuré de bout en bout sur une copie de l'emprise : aucun invariant aggravé, aucune jonction à 0 m perdue, 20 min de calcul. Règle validée et commitée : une voie OpenStreetMap abandonnée ou déposée est « estimée » sauf seconde source à 3 m. Mesure : le recalage garde encore la voie OpenStreetMap abandonnée devant le morceau fusionné (19 km dessinés sur la fusion pour 515 km précis importés) ; avec la priorité au morceau fusionné, 325 km dessinés et 0,96 m d'écart médian à la plateforme LiDAR au lieu de 1,69 m, mais un bout reste à 1 m de sa jonction à Caen : décision et reprise des jonctions avant l'application. Ensuite les lignes à tracé grossier (874 lignes, 2 117 défauts relevés), plateforme LiDAR sur les 41 000 km à fusionner, 2 000 lignes à calculer (3 à 8 h)
- [~] Audit de chaque tronçon de VFF, à chaque époque : classement, placement, exactitude et tracé, notés avec leurs preuves, contrôle à la main sur un tirage stratifié, corrections rejouables (méthode : `docs/AUDIT-TRONCONS.md`). Fait : `vff:audit-segments` (381 731 tronçons, 21 ères, 19 201 synthèses de ligne), page `/validate/audit/segments`, API, GraphQL, MCP, quatre contrôles à la main (100, 200, 100 cas aux nœuds et aux ères passées, puis 120 constats tirés par type, gravité et époque le 23/11 : classement faux positif dans 9 cas sur 14, règles `2026-11-23.1`, six « dépose » d'une seule voie neutralisées), 81,6 % puis 83,4 % des km sûrs en 2030. Reste : relancer `vff:audit-segments --stale` (14 % des lignes d'audit décrivent un tronçon que le moteur a remplacé), repasser les ères passées sur `vff` avec les règles `2026-11-23.1`, un invariant par note
- [~] Audit de chaque gare : dates d'ouverture, de fermeture, voyageurs et fret trouvées dans les sources ou, à défaut, estimées avec une précision honnête ; placement vérifié sur toutes les sources et les photographies anciennes, avec une file de relecture (méthode : `docs/AUDIT-GARES.md`). Fait : `vff:audit-stations` (9 389 gares), `vff:import-station-wikidata-dates` (3 750 dates comparées), `vff:import-station-passenger-ends` (342 fins des voyageurs lues dans les infobox), page `/validate/audit/stations`, API, GraphQL, MCP, deux contrôles à la main de 100 gares ; gares en désaccord 342 puis 217. Règles facultatives G3 (ouverture déduite bornée par la carte) et G6 (fermeture tirée de la fin des voyageurs par PK) écrites et testées, désactivées (`vff:build-station-eras --rules=`) ; source `manual` des gares absentes de toutes les autres (`database/data/gares_manuelles.php`). Reste : le recalcul des gares (`vff:consolidate-stations`, `vff:build-station-eras`) pour lire les corrections et la mesure du placement le long de la voie. Relecture du 23/11/2026 : 62 gares ouvertes aux voyageurs et parmi les plus fréquentées, dont l'ouverture était à l'année ou en conflit entre sources, relues contre l'article de la gare et celui de la ligne (révisions citées) : 24 ouvertures corrigées au jour (section 36 de `database/data/gares_corrections.php`), 24 confirmées, 14 à décider (liste dans `storage/app/reports/agents/aprem-2111/audit-gares-jour/verifiees.csv`) ; suite le même jour : 10 de ces 14 cas tranchés par une seconde source et 60 gares de plus relues, soit 26 ouvertures corrigées (section 37), 38 confirmées et 10 à décider (liste dans `storage/app/reports/agents/aprem-2111/audit-gares-2/verifiees.csv`), donc 122 gares relues au total : 50 corrigées, 62 confirmées, 10 à décider. Troisième passe le même jour : 3 des 10 cas tranchés par une seconde source (Marseille-Saint-Charles, Montpellier-Saint-Roch corrigées, Cormeilles-en-Parisis confirmée) et 80 gares de plus relues dans l'ordre de fréquentation, soit 20 ouvertures corrigées (section 39), 58 confirmées et 8 à décider (liste dans `storage/app/reports/agents/aprem-2111/audit-gares-3/verifiees.csv`), donc 202 gares relues au total : 70 corrigées, 120 confirmées, 12 à décider. Quatrième passe : 100 gares de plus (de Saint-Sulpice 0,76 M à Boulogne-Tintelleries 0,39 M voyageurs), 28 ouvertures corrigées (section 40 : Saint-Sulpice 1864, Anse 1854, Saint-Jean-de-Maurienne 1856, Osny 1891, Cahors 1869, Igny, Vauboyen et Bièvres 1883, Issoire 1855, Sainte-Pazanne 1875...), 58 confirmées et 14 à décider (liste dans `storage/app/reports/agents/aprem-2111/audit-gares-4/verifiees.csv`), soit 302 gares relues au total : 98 corrigées, 178 confirmées, 26 à décider. Cinquième passe : 120 gares de plus (de Montchanin 0,39 M à Vernaison 0,18 M voyageurs), 42 ouvertures corrigées (section 41 : Montchanin 1861, Colomiers 1877, Ingwiller 1889, Chasse-sur-Rhône 1857, Oudon et La Possonnière 1851, Presles-Courcelles 1877, Reignier et Groisy 1884, La Borne-Blanche 1964...), 51 confirmées et 27 à décider (liste dans `storage/app/reports/agents/aprem-2111/audit-gares-5/verifiees.csv`), soit 422 gares relues au total : 140 corrigées, 229 confirmées, 53 à décider. Sixième passe (grandes gares oubliées des cinq premières, dont Paris-Est, Magenta, Nantes, Bondy, Toulouse-Matabiau, Poissy, Nice-Ville, Lyon-Perrache, Chelles, Saint-Étienne-Châteaucreux, Mâcon, Vichy, Alès) : 62 gares de 0,15 M à 53,9 M de voyageurs, 14 ouvertures corrigées (section 42 : Nantes 1851 en gare provisoire, Toulouse-Matabiau 1856, Lyon-Perrache 1856, Saint-Étienne-Châteaucreux 1857, Viroflay-Rive-Gauche 1840, Montereau 1849, Bellegarde 1858, Thiviers 1861, Chauny, Rumilly, Grand-Lemps, Livry-sur-Seine, Saint-Sulpice-Izon, Stains-La Cerisaie), 42 confirmées et 6 à décider (liste dans `storage/app/reports/agents/aprem-2111/audit-grandes-gares/verifiees.csv`), soit 484 gares relues au total : 154 corrigées, 271 confirmées, 59 à décider. Septième passe : 150 gares de plus (d'Arnèke 0,15 M à Chevrières 0,05 M voyageurs), 20 ouvertures corrigées (section 45 : Bagneaux-sur-Loing 1916, Saint-Roch 1876, Pauillac 1870, Artix et Puyoô 1863, Pléchâtel 1864, Guéthary 1884, Margaux 1868, Nœux-les-Mines, Mende, Morez, Delle, Saujon, Pont-Saint-Vincent et Oyonnax précisées au jour, Istres et Port-de-Bouc 1882...), 127 confirmées et 3 à décider (liste dans `storage/app/reports/agents/aprem-2111/audit-gares-6/verifiees.csv`), soit 634 gares relues au total : 174 corrigées, 398 confirmées, 62 à décider. Huitième passe (audit-gares-7) : relecture à la main des 127 « confirmées » de la septième passe contre le texte détaillé de la ligne, soit 11 corrigées (Gannat 1855, Brassac-les-Mines 1855, Estrées-Saint-Denis 12/05/1880, Vallorcine 1908, La Crau, Vayres, Bassens, Arches, Brebières-Sud, Marseille-en-Beauvaisis, Port-Brillet) et 8 passées à décider (Hesdin, Poligny...), puis 120 gares de plus (de Santes 0,048 M à Le Trayas 0,018 M voyageurs), 19 ouvertures corrigées (section 47 : Monestier-de-Clermont 1878, Régny 1866, Saint-Junien 1875, Lauterbourg 1876, Oderen et Fellering 1905, Corps-Nuds, Vern et Saint-Armel 28/12/1881, Casino-Lacroix-Laval 1997, Longuerue-Vieux-Manoir 1874...), 70 confirmées et 31 à décider (liste dans `storage/app/reports/agents/aprem-2111/audit-gares-7/verifiees.csv`), soit 754 gares relues au total : 204 corrigées, 449 confirmées, 101 à décider. Reprise des cas à décider (gares-a-decider, section 48) : 21 ouvertures corrigées par la chronologie de leur ligne (les huit gares de Pontoise à Gisors le 04/10/1868, Le Theil-La Rouge et Champagné le 01/06/1854, Logelbach 1868, Breitenbach et Muhlbach 1893, Rang-du-Fliers 21/12/1847, Pontarlier 24/07/1860, Avesnes 30/10/1869, Alès, La Grand-Combe, Longueil-Sainte-Marie 1847, Cap-d'Ail 1881, Èze 1868), 8 confirmées (Nantes - La Roche-sur-Yon ouverte le 26/12/1866 et inaugurée le 30, La Brohinière 1884, Neufchâteau, Évron, Condé-sur-Huisne, Saint-Pol-sur-Ternoise), soit au total 225 corrigées, 457 confirmées et 72 à décider (liste dans `storage/app/reports/agents/aprem-2111/gares-a-decider/verifiees.csv`). Relecture des FERMETURES du même jour : 83 gares fermées sur des lignes encore ouvertes (date de fermeture déduite d'un événement de la ligne) relues contre l'article de la gare ou de la ligne (révisions citées) : 9 fermetures corrigées (section 38 : Dommartin-Lissieu 2012, Bercy-Ceinture 1942, Fresnoy-Morangles 1935, Gavarry 1940, Romanswiller 1988, Gattières, Vence, Loup et Peymeinade 1944), 39 confirmées, 30 à décider (déclassement ou fin du fret pris pour la fermeture, date d'une autre antenne, doublons probables), 5 sans source numérisée (liste dans `storage/app/reports/agents/aprem-2111/audit-fermetures/verifiees.csv`). Gares desservies seulement par car (Laon - Hirson : 8 gares, Corbigny, Flez-Cuzy-Tannay) : laissées actives jusqu'à une source datée de la fin des trains (décision du 23/11, rien à coder) ; Poitiers - Limoges est une interruption pour travaux, laissée ouverte.
- [ ] Modèle : fusion de plusieurs dates de prises de vue (calcul GPU, sur accord), publication des poids complets (seuls les poids `ortho-v1` en fp16 restent après l'incident du 28/10)
- [~] Gallica (BnF) : 236 documents numérisés rattachés à 61 lignes par le titre de leur notice (score expliqué, seuil prudent ; trajets avec étape « en passant par » et listes « entre X, Y et Z » lus ; précision mesurée sur 40 documents tirés au hasard : 95 % après la relecture du 20/11, puis 36 justes, 4 douteux et aucun faux au second et au troisième tirage, après le retrait du projet Lyon - Marseille de 1837 donné à la ligne par Grenoble), section « Dans les archives » de la fiche ligne, `GET /api/v1/lines/{slug}/archives`, `Line.archives` en GraphQL et outil MCP `line_archives` ; seules les métadonnées de la BnF (Licence Ouverte) et le lien vers Gallica sont repris, aucune image. Reste : la presse, les documents des bibliothèques partenaires et l'API de Gallica elle-même (refusée à la machine de développement)
- [~] Fonds d'archives (SNCF, associations régionales) : 198 notices des Archives SNCF (dossiers, photographies de la Médiathèque, films ; jeu de données « archives-sncf-sardo », ODbL) rattachées à 65 lignes par leur titre, avec le score de Gallica (noms de compagnies retirés, listes « sur les lignes de X à Y et de Z à W » lues, thèmes sur des personnes écartés) ; précision mesurée sur deux tirages de 40 notices : 78 justes, 2 douteuses, aucune fausse ; même section « Dans les archives » de la fiche, `GET /api/v1/lines/{slug}/archive-records`, `Line.archive_records` en GraphQL et outil MCP `line_archive_records` ; titre, type, cote, date et lien seulement, ni description ni image. Répertoire des fonds dans `docs/ARCHIVES.md`. Répertoire tranché le 23/11 : Archives nationales, ANMT, ministère de la Transition écologique, Isère, OpenEdition écartés avec raison (robots.txt, anti-robots, niveau fonds, articles et non archives) ; Calames sondé (10 rattachements justes, reporté faute de volume) ; reste à demander par Baptiste : clé Europeana, accès ANMT et FranceArchives (brouillons dans `aprem-2111/demandes-archives-fonds.md`), puis les descriptions des notices SNCF (à appliquer) et les associations (aucun inventaire en ligne trouvé)
- [x] Distinction visuelle des époques anciennes sur la carte : fond pâli et teinté de sépia selon l'ère (rien aujourd'hui), réglage « Teinte des époques anciennes » et `?tint=off`

---

## 🎓 Phase 11 - Communauté & rayonnement (en cours)

- [x] Multilingue : FR, EN, DE
- [x] Carte intégrable sur d'autres sites (`/maps` en iframe, code d'intégration fourni)
- [x] Pages de crédits (techniques et médias)
- [x] Soutien à la campagne AngouLim (réouverture Angoulême - Limoges) ; les partenariats et les conférences sont suivis dans `docs/PLAN-COMMUNICATION.md`, plus dans cette feuille de route
- [~] Dons : rien à brancher avant la création de l'association UWOSC, ensuite HelloAsso (décision du 23/11) ; la page `/donate` reste au futur d'ici là
- [x] Blog / journal de bord du projet (articles longs sur des lignes emblématiques) : `/journal`, articles Markdown versionnés (`resources/content/journal`, format dans `docs/REDACTION.md`), flux Atom et RSS, plan du site, API REST, GraphQL et MCP ; deux premiers articles (les Hirondelles, Angoulême - Limoges), traduits en anglais et en allemand (`*.en.md`, `*.de.md`)
- [x] Plans du site : `/sitemap.xml` (index) et un plan par famille de pages (lignes, gares, matériel, accidents, pages éditoriales, journal), 50 000 adresses au plus par fichier, publié seulement (`App\Services\Seo\SitemapBuilder`)
- [x] Application installable (PWA), avec page hors ligne
- [x] Mode hors ligne : pages publiques déjà consultées sans être connecté (HTML et Inertia, 60 au plus, 30 jours) et tuiles vectorielles de VFF déjà affichées (500 au plus, 7 jours), avec la date de la copie ; jamais les pages de compte, de contribution ni de validation, ni les tuiles OpenStreetMap
- [x] Mode hors ligne, suite : données de la carte déjà affichées (gares d'une ère, accidents : 8 fichiers au plus, 7 jours) ; hors ligne, les cartes montrent les tracés de VFF sur un fond neutre et le signal de copie le dit (style écrit dans le code, noms de gares en police locale, aucune ressource tierce gardée)
- [~] Mode hors ligne, fin : images des fiches déjà vues (vignettes de Commons, médias publiés : 300 au plus, 40 Mo, 30 jours, jamais pour une page de compte, de contribution ni de validation) ; fond de carte libre servi par VFF (PMTiles OpenStreetMap au schéma Protomaps, France et abords, zooms 0 à 15, 6,2 Go, style clair et sombre sans pictogramme, polices auto-hébergées), fond des cartes hors ligne sur les zones déjà vues (2 000 morceaux, 60 Mo, 30 jours). petites cartes des fiches branchées sur le même fond derrière le même réglage (une seule fabrique de style). Décision du 23/11 : il devient le fond en ligne à la mise en production (`VFF_BASEMAP_ONLINE`, désactivé en développement), archive auto-hébergée jusqu'au zoom 15 et mise à jour à la main, OpenFreeMap seulement en secours. Reste : le servir par nginx en production (docs/FOND-DE-CARTE.md)

---

## 🚀 Phase 12 - Mise en production (en cours)

- [x] Domaine officiel `vff-project.org`
- [x] Configuration nginx de référence pour la production : en-têtes de sécurité, cache, plages PMTiles, journaux anonymisés (`deploy/nginx/`, `docs/DEPLOIEMENT-NGINX.md`)
- [x] Pages légales complètes (2026-10-21) : mentions légales, politique de confidentialité, conditions générales d'utilisation, déclaration d'accessibilité
- [ ] Messagerie de `vff-project.org` chez Hetzner, comme le reste d'UnxWares, avec `contact@` et `privacy@` (décision du 23/11) : aucun enregistrement MX à ce jour, donc ces deux adresses ne reçoivent rien ; MX, SPF, DKIM, DMARC et CAA attendus listés à l'étape 19 de `docs/MISE-EN-PRODUCTION.md` (à poser par Baptiste)
- [ ] Hébergement sur l'infrastructure d'UnxWares : parcours complet dans `docs/MISE-EN-PRODUCTION.md` (prérequis, 19 étapes avec commande, contrôle et retour arrière), script idempotent `deploy/bin/mise-en-production.sh` (`--verifier` ne change rien et dit ce qui manque, `--appliquer` s'arrête à la première erreur, aucune suppression), pool PHP-FPM `deploy/php-fpm/vff.conf` ; reste à l'exécuter sur le serveur
- [~] Planificateur Laravel en production (recalcul quotidien de l'époque 2030) : code prêt (tâches, verrous, journal, signalement d'échec, battement vers `/up/details`, entretien, tests, `docs/PLANIFICATION.md`) ; reste à poser sur le serveur d'UnxWares (étape 11 de `docs/MISE-EN-PRODUCTION.md`, minuteur et service `deploy/systemd/production/`)
- [~] Relais des vignettes de Wikimedia Commons par VFF (`VFF_COMMONS_PROXY`, désactivé par défaut) : liste blanche stricte des chemins (seuls les fichiers que VFF cite, liste recalculée toutes les dix minutes dans Valkey), cache disque borné (`vff:commons-cache-prune`), vignettes WebP, aucun cookie tiers ; reste à l'activer en production avec le cache nginx (étape 16 de `docs/MISE-EN-PRODUCTION.md`, `docs/DEPLOIEMENT-NGINX.md`)
- [x] Flux du journal et plans du site servis sans session ni cookie, adresses absolues construites depuis `app.url`
- [x] Engagements des pages légales : journaux conservés 12 mois au plus, suppression des comptes inactifs depuis 3 ans (avec avertissement), suppression de compte (phase 5), information des membres avant un changement des CGU
- [x] Correction des défauts de l'audit de l'interface (interface unifiée, contrastes, cibles, clavier : novembre 2026)
- [x] Audit de conformité RGAA interne complet (100 % après la correction de Swagger UI et Magidoc le soir du 19/11, totalement conforme), taux publié dans la déclaration
- [ ] Suivi des erreurs et de la disponibilité (phase 0) : Kener et GlitchTip choisis, installation et moniteurs décrits dans `docs/SUIVI-PRODUCTION.md`, commandes à l'étape 17 de `docs/MISE-EN-PRODUCTION.md`
- [x] Requêtes vers Wikimédia polies : une requête à la fois pour Wikipédia, `maxlag` partout, User-Agent du projet
- [x] Sauvegardes du poste de développement : dépôts chiffrés côté client, rétention quotidienne, hebdomadaire et mensuelle, contrôle d'intégrité chaque dimanche ; dumps de la base ciblés avant chaque écriture
- [~] Sauvegarde de la base de production : kit prêt et essayé sur une base de test (`deploy/backup/`, `docs/SAUVEGARDE.md` : dump quotidien contrôlé, rotation 7/4/6, envoi chiffré restic, battement, essai de restauration dans une base temporaire), essai de restauration corrigé pour la configuration en cache de la production (il lisait la base en service) ; destination choisie le 23/11 : Infomaniak Swiss Backup, compartiment à part, chiffré par restic ; reste à créer le compartiment et à poser le kit (étape 7 de `docs/MISE-EN-PRODUCTION.md`)

---

## 🧭 Phase 13 - Nouveaux domaines de l'encyclopédie (en cours)

- [x] **Signalisation** : données SNCF Réseau importées (`line_signalling`, `/api/v1/lines/{slug}/signalling`), fiche ligne (cantonnement et contrôle de vitesse par PK, trous des données signalés, lien vers la carte), calque « Signalisation » sur `/maps` (tranches placées sur les formes SNCF Réseau, PK calés sur les passages à niveau, tuiles `/api/v1/eras/{year}/signalling/tiles/…`) ; tranches à deux systèmes (ETCS niveau 2 et TVM sur les LGV Est, BPL et SEA) gardées entières (`signalling_systems`)
  - [x] Commande centralisée (CCVU, CCR, PCD) : système `CC` de rôle `remote_control`, distinct du cantonnement (elle commande les postes et se combine avec BAL, BAPR, block manuel ou cantonnement téléphonique sans en remplacer un), famille `centralized_control` dessinée en trait fin pointillé par-dessus le cantonnement sur la carte des époques passées, libellés fr, en et de, termes « Commande centralisée » et « CCVU » du glossaire, 18 faits relus (Saumur - Thouars 1982, Épernay - Reims 1962, Saint-Benoît - La Rochelle 1993, Corse 2025, LGV Sud-Est 2024...) ; l'état actuel de SNCF Réseau ne la porte pas, elle n'apparaît donc pas à l'ère courante
  - [x] Historique par époque : 66 mises en service datées relevées dans les articles Wikipédia des lignes (texte, listes, tableaux, infobox ; TVM des LGV datée de l'ouverture), 50 montrées sur 43 lignes (BAL, BAPR, block manuel, cantonnement téléphonique, TVM, ETCS ; le seul fait KVB est peu sûr et caché), portions placées par leurs gares d'extrémité et leurs PK calés (`vff:import-signalling-history`, table `line_signalling_events`, CC BY-SA, faits et permalien de la révision), frise « Signalisation au fil du temps » sur la fiche ligne, calque « Signalisation » de `/maps` aux époques passées (faits en vigueur à la fin de l'ère ; l'état actuel de SNCF Réseau reste celui d'aujourd'hui), API REST (`/lines/{slug}/signalling-history?era=`, tuiles par ère), GraphQL (`Line.signalling_history(era:)`, `sheet.signalling.history`) et outil MCP `signalling_history`
  - [x] Historique par époque, autres sources datées : 35 mises en service relevées dans des documents (décisions et rapports d'activité de l'EPSF, bilans LOTI de SNCF Réseau et de l'État, presse), chacune confirmée par un document officiel ou par deux sources (`database/data/signalling_history_sources.php`, `vff:import-signalling-sources`, `source.provider = manual` et `source.documents` en REST, GraphQL et MCP, un lien par document sur la frise) ; un fait de Wikipédia qu'un document redit n'est plus répété (9 dates à l'année remplacées par la date au jour)
  - [x] Fichier de corrections sourcées : un fait de Wikipédia ou d'un document peut être neutralisé ou remplacé (raison et source), masqué partout et servi par REST, GraphQL (`Line.signalling_corrections`) et MCP ; l'ETCS faux de la LGV Sud-Est est masqué
  - [x] Historique par époque, faits relus dans Wikipédia (mises en service de BAL et de BAPR par section, citation vérifiée à chaque import) : appliqué le 23/11 à 12 h 05 (124 faits sur 70 lignes, 164 faits en base avec les documents ; `aprem-2111/suite-application/`) ; les faits relus ensuite entrent à la prochaine application
  - [x] Portions des LGV nommées par des bifurcations, raccordements, origines ou frontières : 14 des 15 faits placés par 16 points remarquables (`database/data/line_landmarks.php` : PK et position tirés des formes des lignes et de la liste des gares de SNCF Réseau, des bornes hectométriques d'OpenStreetMap et de Wikipédia), qui calent aussi le tracé des LGV sans passage à niveau ; Montrouge, début de la LGV Atlantique, reste sans PK sûr
  - [ ] Historique par époque : relevé à poursuivre sur les lignes classiques (BAL et BAPR des années 1950 à 2020 : peu de documents datés en accès libre)
- [x] **Passages à niveau** : 16 801 PN de SNCF Réseau (ODbL), calque « Passages à niveau » sur `/maps` (à partir du zoom 9), section de la fiche ligne (nombre par type, liste repliable), API REST (`/api/v1/level-crossings`, `/api/v1/lines/{slug}/level-crossings`, tuiles), GraphQL (`Line.level_crossings`, `levelCrossings`) et outil MCP `level_crossings`
- [x] **Matériel roulant** : 727 séries importées de Wikidata (CC0) avec leur image Commons et la provenance de chaque champ (`vff:import-materials`), 2 609 liens ligne × matériel attestés par Wikidata, par les articles Wikipédia (phrase citée, permalien, fiabilité) ou par les relations qui parcourent la ligne, API REST (`/api/v1/materials`, `/api/v1/lines/{slug}/materials`) et GraphQL ; fiches enrichies sur le site ; périodes de circulation datées (`vff:import-material-periods`, table `line_material_periods` : 369 périodes, lues dans les articles Wikipédia des lignes et des séries, permalien de la révision, fiabilité ; jamais de phrase sur le site ni dans le MCP), sur les fiches ligne et série, en REST, GraphQL (`LineMaterialLink.periods`) et MCP (`line_materials`) ; à faire : lire les tableaux des articles, rattacher les périodes aux portions du tracé, dates Wikidata quand les lignes auront des déclarations P3438 datées
- [x] **Accidents** : fiches de Wikidata, de Wikipédia et du BEA-TT, pages, API, calque de carte ; contexte sourcé pour 548 fiches (`vff:enrich-accidents`, fichiers de `database/data/accident_contexts`) : archives, enquêtes et presse de l'époque en français, anglais et allemand, documents cités (Gallica en lien seulement), images de Commons, corrections sourcées des lieux, dates, natures et bilans, doublons gardés en alias ; noms de fiche durables (`database/data/accident_slugs.php`) et fiches rédigées à la main pour un accident que seule la presse décrit (`vff:import-accidents --only=manual`) ; sections « Contexte » et « Documents et archives » en REST, GraphQL et MCP. Le 22/11 : « personne heurtée » publiée quand le BEA-TT ou un contexte sourcé l'établit (12 fiches), six doublons de la liste de Wikipédia fondus avec redirection 301, nature et commune corrigées affichées en clair, correction fondée sur une seule liste de Wikipédia signalée, Delpher crédité. Reste : les fiches qui ne reposent que sur la liste de Wikipédia (Gallica et Chronicling America refusent la machine), sources à valider (Victimes du rail)
- [~] **Médias, droits par image et fonds ouverts** : droits propres à chaque média (`derivatives_ok`, précision de la date) et réglage « non commercial » appliqué partout où un média est servi ; comblement des manques d'images des gares, lignes et séries par Wikidata (P18, P373) et les catégories de Commons, avec fichier de candidats rejouable ; import des autochromes d'Albert-Kahn, de cartes postales d'archives départementales et municipales (Hauts-de-Seine, Suresnes, Sèvres, Évry, Gadagne, Saône-et-Loire), de Getty Open Content, de l'École des ponts et de Numistral (environ 610 images, 129 gares, droits par fonds). Code commité le 22/11 ; appliqués la nuit suivante : migration des droits, candidats Commons rattachés (71 438 médias avant, 114 726 après), Rosalis, Pireneas, Évry et Gadagne (134 images sur 26 gares, licence marquée incertaine)
  - [ ] Fonds d'images à intégrer : associations, archives et bibliothèques, Flickr sous licence libre, licence vérifiée image par image ; courriels aux détenteurs préparés (`aprem-2111/sources-images*.md`)
  - [~] Photos de rue Panoramax triées par un modèle de vision (CLIP, poids MIT, processeur seul) : score « gare visible » réglé sur 250 photos étiquetées à la main, 4 % hors sujet mesurés sur 100 autres, instance d'OpenStreetMap France jamais lue ; import à lancer par le coordinateur seul sur la machine (`aprem-2111/panoramax-appliquer.sh` : contrôles, sauvegarde ciblée de `media`, import à blanc puis réel, droits et auteur de chaque photo, 5 fiches de gares ; cache actuel 189 photos sur 151 gares, national environ 5 h 30). Contrôle à la main du 23/11 sur 60 photos gardées et 30 écartées : 18 % hors sujet au seuil 0,7 (8 % à 0,85, 4 % à 0,95), d'où l'option `--min-score` ; seuil de 0,95 choisi par Baptiste, 99 photos importées depuis le cache le 23/11 (`aprem-2111/suite-application/`), passe réseau (4 895 gares, environ 5 h 30) à lancer un soir (`aprem-2111/panoramax-import.md`). Avant lui, un tri géométrique : bâtiment voyageur d'OpenStreetMap ou BD TOPO dans le champ de la caméra, autres bâtiments BD TOPO comme obstacles, panoramas à cap incertain écartés. Photos hors sujet de 65 % à 10 % sur un tirage de 60 gares, 12 % au recensement de 34 photos sur 1 500 gares, mais environ 160 gares gardées au lieu de 800 (IGN seul) ; objectif de moins de 5 % non atteint ; aucun import lancé (`aprem-2111/panoramax-tri.md`)
- [x] **Matériel préservé** : musées, associations et lignes touristiques avec leurs pièces, leur nombre, leur état et la protection au titre des monuments historiques (base Palissy), chaque fait avec sa source (`/materials/preserved`) ; frise du matériel par traction et décennie (`/materials/timeline`), 665 séries datées sur 885 (infobox Wikipédia et liste relue à la main)
- [x] **Chemins de fer touristiques et vélorails** exploités aujourd'hui (`/tourist-railways`, `GET /api/v1/tourist-railways`, outil MCP `tourist_railways`) : exploitant, type, écartement, longueur, année de début, régions, lignes et source de chaque fait ; voies vertes à part
- [x] **Patrimoine ferroviaire protégé** (`/heritage`, `GET /api/v1/heritage`) : bâtiments et ouvrages classés ou inscrits, label « architecture contemporaine remarquable », architectes (142 graphies réduites à 79), dates, styles, carte légère et filtres ; filtre « patrimoine » de l'annuaire des gares
- [x] **Glossaire** du vocabulaire ferroviaire (`/glossary`) : chaque terme vérifié à sa source et lié depuis les textes qui l'emploient ; quiz de culture ferroviaire (ouvertures, compagnies, plus longs tunnels, architectes, gares les plus fréquentées, premières séries) à réponses sourcées
- [x] **Rames remarquables** : la rame TGV Sud-Est 01 « Patrick » (fiche de la série, section « Rames remarquables » en REST, GraphQL et MCP, recherche du site) et son clin d'œil sur toutes les pages (taper « patrick » hors d'un champ de saisie ; mouvement réduit respecté, rien envoyé ni stocké), redessinée en SVG à l'échelle
- [x] **Serveur MCP** pour que les assistants IA interrogent l'encyclopédie avec ses sources (47 outils dont accidents, comparaison d'ères, passages à niveau, historique de la signalisation, matériel, vitesses et ouvrages par ligne, chronologie, « Ce jour-là », chemins de fer touristiques, patrimoine des gares, ce qui existait en un lieu (`what_was_here`), réseau dans une zone, arrêts d'une ligne et vues aériennes d'une gare ; 3 invites, lecture seule, aucun fait d'une source non commerciale, `docs/MCP.md`)

---

## 🌱 Idées en vrac (backlog non priorisé)

- [x] Calcul d'itinéraire historique : « comment serais-je allé de A à B en 1950 ? » (phase 8)
- [x] Couches thématiques : électrification, signalisation, écartement
- [x] Recensement des passages à niveau (carte, fiches et API, phase 13)
- [x] Mode « vue ferroviaire » : carte simplifiée façon schéma (choix « Vue ferroviaire » de `/maps`, `?view=schema&era=1930` : axes à 0, 45 ou 90 degrés routés sur une grille, grandes villes agrandies, gares en traits ; plan calculé par le serveur depuis le graphe de l'époque et gardé en cache, `vff:build-schematic-maps`, `/api/v1/eras/{year}/schematic.geojson`, `docs/VUE-FERROVIAIRE.md`)
- [x] Comparaison de deux époques (superposition, phase 3)
- [x] Stories d'archives (carrousel de photos sur un événement précis) : page `/lines/{slug}/stories/{event}` ouverte depuis la chronologie (« Voir l'histoire »), images de la ligne datées à 5 ans au plus avec leur écart et leurs crédits, documents de Gallica de la période ; seuil de 3 images et documents dont 2 images (983 événements sur 264 lignes) ; API REST `/api/v1/lines/{slug}/stories`, GraphQL `LineEvent.story`, outil MCP `line_stories`
- [x] Quiz / mini-jeu : reconnaître la ligne / le matériel (`/quiz` : dix questions tirées d'une graine partageable, tracés redistribuables seulement, photos Commons créditées, réponse vérifiée par le serveur, rien d'enregistré)
- [x] Export GPX/KML d'un tracé (`/api/v1/lines/{slug}/gpx` et `/kml`, sauf les géométries que l'API retient)
- [x] Voies vertes sur d'anciennes lignes (données OSM importées) : rattachement mesuré (vff:link-greenways), section de la fiche ligne, calque de la carte, API REST et GraphQL
- [x] « Ce jour-là » (`/on-this-day`), « Qu'y avait-il ici ? » par clic sur la carte, chronologie du rail en France (`/chronology`), chemins de fer touristiques, histoire de l'électrification par section, patrimoine protégé des gares, fréquentation des gares, liste textuelle de ce que montre la carte, lecture des époques sur la carte, préchauffage des tuiles (`vff:warm-tiles`, à lancer après la dernière écriture en base)

---

*Cette roadmap est un document vivant. Toute proposition est bienvenue : ouvre une [issue](https://github.com/UnxWares/VFF-Project/issues) avec le label `roadmap`.*

*À jour : 2026-11-23.*
