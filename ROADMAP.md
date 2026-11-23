# 🛤️ Roadmap VFF

> *De la page d'accueil à la plus grande encyclopédie ferroviaire de France.*

Ce document détaille les phases de développement de VFF. Les phases sont **indicatives, pas strictement séquentielles** : certaines tâches peuvent être menées en parallèle. Les cases cochées reflètent l'état réel du dépôt.

**Légende** : `[x]` fait · `[ ]` à faire · `[~]` en cours ou partiel · `🎯` jalon majeur · `🧪` expérimentation

---

## 📍 Où en est-on ? (2026-11-23)

| Phase | État |
|:-|:-|
| 0 - Fondations | ✅ faite, reste l'installation des outils de suivi des erreurs sur l'hébergement |
| 1 - Modèle de données | ✅ faite, jalon atteint |
| 2 - Carte interactive | ✅ faite, géolocalisation comprise |
| 3 - Sélecteur temporel | ✅ faite (21 époques de 1830 à 2030), comparaison de deux époques (différences ou rideau déplaçable) et vue empilée comprises |
| 4 - Fiches détaillées | 🟡 lignes (schéma « Où et quand », schéma par PK, ouvrages d'art, vitesses, histoire de l'électrification, bibliographie), gares (chronologie de la vie, fréquentation, noms, accessibilité, desserte, patrimoine), communes, départements, compagnies, projets, matériel, accidents, impression, JSON-LD, profil en long (2 630 lignes, déclivité sourcée à côté de la mesure, trous du tracé dits), contexte sourcé de 548 accidents, audits automatiques et journal de bord faits ; altitude de la voie estimée d'après le terrain écrite (à appliquer), profils des lignes restantes et relecture des audits à faire |
| 5 - Contribution | 🟡 comptes, éditeur de tracé, validation à deux, notifications, réputation, modération, signalements, suppression et export du compte faits ; formulaire de média fait (fichier déposé ou Commons, licence et version en liste fermée, légende fr, en, de, position GPS reprise seulement sur demande) ; formulaire d'événement fait (nouvel événement, ou correction d'un événement depuis la chronologie de la fiche, avec citation courte de la source) |
| 6 - API REST | ✅ API publique documentée, jetons et quotas, webhooks, exemples curl, JavaScript et Python, départements et régions ; tracés retenus quand leur licence ne permet pas la redistribution ; audits, profil par point et altitude, pagination commune aux trois API ; chiffres du réseau (`/api/v1/stats/network`) et vitesses d'une ligne, GTFS validé par MobilityData et prolongé le long des voies partagées, tuiles légères `fields=map`, en-têtes de sécurité et limites, recherche commune à l'en-tête, à la carte et aux trois API, puis chronologie, « Ce jour-là », chemins de fer touristiques, patrimoine, projets, compagnies, couverture des données, accidents en chiffres et position par PK |
| 7 - GraphQL | 🟡 lecture publique et mutations de contribution faites, limite de complexité (5 000 points, 15 niveaux), abonnements écrits et désactivés par défaut ; Reverb en production à faire |
| 8 - Graphe & itinéraires | 🟡 itinéraires historiques faits sur un graphe PostgreSQL ; Apache AGE pas activé |
| 9 - Reconstitutions 3D 🧪 | ⬜ pas commencée |
| 10 - Extension historique | 🟡 couverture 1830-2030 et modèle d'extraction des voies publiés ; fusion des sources à 1 m (LiDAR, photos aériennes, vecteurs) en cours, passe nationale et `ortho-v2` à faire |
| 11 - Communauté | 🟡 trilingue, données ouvertes ODbL (`/open-data`), page « Le réseau en chiffres », carte intégrable, application installable, lisible hors ligne avec images et fond de carte libre, journal de bord (`/journal`) et plans du site faits ; dons à faire |
| 12 - Mise en production | 🟡 pages légales, engagements RGPD, audit RGAA (100 %, totalement conforme), configuration nginx de référence et part application du suivi des erreurs faits ; messagerie, hébergement final et outils de suivi à faire |
| 13 - Nouveaux domaines | 🟡 matériel roulant, accidents, serveur MCP (47 outils), signalisation et passages à niveau (carte, fiches, API), matériel préservé, patrimoine protégé et glossaire faits ; historique de la signalisation fait (59 faits relus dans Wikipédia le 23/11, dont 10 sur des lignes classiques et 18 de commande centralisée, nouveau système `CC` à part du cantonnement ; la source libre est tarie : relevé à poursuivre par documents officiels) |

**En base (développement, lecture du 23/11/2026 à 6 h 40)** : 2 690 lignes, 9 395 gares, 15 769 événements datés, 114 726 médias, 21 époques, 885 séries de matériel roulant (665 datées), 951 fiches d'accidents dont 548 avec un contexte sourcé, 16 024 communes avec du rail, 3 145 ouvrages d'art, fréquentation de 3 020 gares, accessibilité de 2 796 gares, dessertes de 2 832 gares, 887 références bibliographiques, 729 534 points de profil en long sur 2 630 lignes, 105 faits datés de signalisation. Le moteur de la carte est rejoué par étapes depuis le 21/11 : la série des règles de jonction est appliquée, les jonctions latérales sont en cours, certains chiffres bougent encore.

**Qualité** : plus de 10 000 tests PHPUnit, plus de 1 200 tests vitest et une suite Playwright de fumée (54 tests, tâche de CI), Larastan sans erreur, contrôle des invariants de données (`vff:check-invariants`), des traductions (`vff:i18n-check`) et des droits des sources (`vff:source-rights`), le tout en CI ; contrôles avant commit Lefthook (installés) ; `bin/test` pour lancer des suites ciblées en parallèle (`docs/TESTS.md`) ; audit RGAA 4.1.2 interne : 100 % (totalement conforme, 32 pages).

**Priorités du moment** :

1. Préparer la mise en ligne (phase 12) : messagerie de vff-project.org, hébergement.
2. Trancher les questions ouvertes (`storage/app/reports/agents/QUESTIONS.md`) : comptes et domaines, Argentan Web Ferroviaire, partenaires, consentement du cache hors ligne, formes GTFS.
3. Moteur des tracés : la série des règles de jonction (7 e, 10, 8 ter) est appliquée et les jonctions latérales sont en cours ; restent les retraits à la main de Domfront (arrêtés par prudence, décision dans `QUESTIONS.md`), la passe « couloir » de la fusion des lignes, le recalcul des gares et de leurs ères, puis les chiffres du réseau (`aprem-2111/APREM.md`, points 52 à 59).
4. Audit de chaque tronçon (classement, placement, exactitude, tracé) et de chaque gare (dates d'ouverture, de fermeture et de service trouvées ou estimées, placement) : passes automatiques écrites et réglées sur quatre contrôles à la main, passes sur `vff` des ères passées et des gares à lancer après le rejeu du moteur ; méthodes dans [docs/AUDIT-TRONCONS.md](docs/AUDIT-TRONCONS.md) et [docs/AUDIT-GARES.md](docs/AUDIT-GARES.md).
5. Fusion des sources de géométrie à 1 m : pilote sur le Calvados, puis passe nationale (phase 10).
6. Médias : droits par image et premiers fonds ouverts appliqués ; lancer l'import des photos de rue Panoramax triées par vision, intégrer d'autres fonds d'images ouvertes (phase 13).
7. Historique de la signalisation par époque (phase 13) : appliquer les faits relus dans Wikipédia ; d'autres sources datées restent à trouver.
8. Adresses de lignes neutres : appliquer le renommage des 1 614 adresses à préfixe de source, avec les redirections 301 déjà commitées (phase 4).

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
- [ ] Suivi des erreurs et de la disponibilité, part hébergement : installer Kener et GlitchTip (docs/SUIVI-PRODUCTION.md) sur l'infrastructure d'UnxWares, poser `HEALTH_TOKEN`, `SENTRY_LARAVEL_DSN` et `APP_VERSION`, configurer moniteurs et alertes
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
  - [~] Altitude de la voie estimée d'après des coupes RGE ALTI là où BD TOPO n'a pas la voie, étalonnée sur le LiDAR HD de l'IGN (93 % des points à moins de 1 m quand le tracé est à 5 m de la voie), servie avec son erreur mesurée (`99d608af`) ; code commité, migration et recalcul à lancer sur `vff`
  - [x] Profil : déclivité d'une source écrite à côté de la mesure publiée, avec l'écart (`sourced_grade_compared`, 317 lignes), jamais une déclivité estimée présentée comme mesurée (lecture au survol dite « estimée » sur le terrain) ; 515 déclivités de Wikipédia lues dans l'infobox ou le texte des articles en cache par `vff:import-wikipedia-grades` (`--dry-run`, 37 de plus), 251 lignes sans mesure publiée en ont une ; tracé de référence incomplet dit sur la fiche et dans les trois API (`trace_holes` : 605 lignes, 937 trous, 1 391 km à vol d'oiseau, liste par `vff:profile-trace-holes`)
  - [ ] Profil : 1 622 lignes sans déclivité ni mesurée sur la voie ni sourcée (terrain seul sous un tracé ancien), 605 lignes dont le tracé de référence a des trous à combler (dont 19 où ils font 25 % du parcours ou plus), 60 lignes sans profil
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
- [~] Adresses de lignes neutres : `vff:neutralize-line-slugs` retire le préfixe de source des adresses, redirections 301 des anciennes (REST, GraphQL, MCP) commitées ; renommage de la base à appliquer
- [x] Pages `/materials` et `/materials/{slug}` avec 727 séries (voir phase 13)
- [x] Schéma JSON-LD pour le référencement (WebSite, Place, BreadcrumbList, ImageObject, TrainStation) ; à rendre côté serveur pour les aperçus de partage

---

## 🟤 Phase 5 - Contribution communautaire (en cours)

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

## 🌐 Phase 6 - API REST publique (en cours)

- [x] OpenAPI 3 généré par [Scramble](https://scramble.dedoc.co/)
- [x] Endpoints : lignes et gares par époque (GeoJSON), tuiles MVT, export GTFS par époque, recherche, historique, PK, arrêts, signalisation et médias d'une ligne, itinéraire, passages à niveau (liste, par ligne, tuiles), tuiles de la signalisation
- [x] Documentation : page `/api`, référence [Scalar](https://scalar.com/) sur `/docs/api`, Swagger UI sur `/docs/swagger`
- [x] Accès libre sans clé, limité à 300 requêtes par minute et par adresse IP (davantage avec un jeton personnel)
- [x] Exemples intégrés : curl, JavaScript (`fetch`) et Python (`requests`) sur la page `/api` et, pour les principaux points d'accès, dans la référence Scalar (`x-codeSamples`)
- [x] Points d'accès des annuaires, fiches complètes, tronçons, époques, sources, événements, médias, matériel et accidents
- [x] Jetons API (Sanctum, lecture seule, créés depuis le profil) et quotas par profil (anonyme 300, membre 1 200, partenaire 6 000 requêtes par minute, `config/api.php`), compteur commun à REST, GraphQL et MCP ; échéance à un an, rappel par courriel 30 jours avant et renouvellement d'un clic depuis le profil
- [x] Contrat de l'API versionné : instantané `tests/Fixtures/api-contract.json` (REST, GraphQL, MCP) mis à jour par `vff:api-contract`, test qui échoue si un champ, un paramètre ou une valeur disparaît sans annonce, journal daté `docs/API-CHANGELOG.md`
- [~] Infrastructure SNCF Réseau : nombre de voies et régime d'exploitation (`vff:import-sncf-track-regime`), liaison radio sol-train dont GSM-R (`vff:import-sncf-radio`) et quais des gares (`vff:import-sncf-platforms`), servis par `GET /api/v1/lines/{line}/infrastructure`, `Station.platforms`, GraphQL `Line.infrastructure` et l'outil MCP `line_infrastructure` ; code commité et testé, reste à lancer les trois imports sur la base (`aprem-2111/apres-sncf-infra.sh`)
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
- [~] Temps réel en production : prêt, reste à poser sur le serveur (unités `deploy/systemd/production/`, script `deploy/bin/verify-production.sh`, `docs/DEPLOIEMENT-SERVICES.md`) ; à faire : lancer Reverb (`php artisan reverb:start` sous superviseur), activer le proxy WebSocket nginx (`deploy/nginx/snippets/vff-reverb.conf`) et les variables `REALTIME_*` et `REVERB_*` (`docs/DEPLOIEMENT-NGINX.md`, section « Temps réel ») ; les webhooks `contribution.applied` couvrent le besoin en attendant
- [x] **REST reste la référence** pour les consommateurs externes, GraphQL est un complément

---

## 🕸️ Phase 8 - Graphe & itinéraires historiques (en cours)

- [x] Graphe du réseau **par époque**, en trois couches (topologie, gares, lignes), construit par `vff:build-network-graph` dans des tables PostgreSQL
- [x] **Itinéraire historique** : « comment aller de Paris à Brest en 1950 ? » (`PathfindingService`, `/api/v1/route`, planificateur sur la carte)
- [x] Export du graphe au format GTFS
- [ ] [Apache AGE](https://age.apache.org/) et requêtes Cypher : pas activé, le graphe en tables suffit pour l'instant ; à réévaluer pour les requêtes complexes
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
- [~] Jonctions : réduire les coudes et crochets restants (phase « escaliers »). Appliqués sur `vff` le 22/11 : bout posé sur la voie d'une autre ligne (5 bis), fragments isolés de moins de 60 m retirés. Appliqués la nuit du 22 au 23/11 : raccords en équerre lissés (7 e), bouts prolongés jusqu'à la gare sans forme SNCF (10), raccords droits pendants retirés (8 ter) ; jonctions latérales en cours d'application (193 lignes rejouées) ; retraits à la main des fragments 2936, 45 et 151 arrêtés par prudence à Domfront (7 jonctions à 0 m perdues pour 6 tolérées). Nouvel invariant `lines.junction_gap_lateral` pour un bout de ligne qui s'arrête à côté d'une voie directe dans une gare commune
- [x] Lignes de section fusionnées dans leur ligne à code incomplète, même sans extrémité partagée (`vff:merge-split-lines`, passe `sections` : 12 tracés complétés, 470 km de plus mesurés sur la copie) ; passe « couloir » commitée le 22/11 : une ligne créée qui longe sa ligne codée dans le même couloir, avec la même catégorie et les mêmes noms de lieux, y est fusionnée (4 fusions prévues) ; application au réseau à lancer après les jonctions latérales (`doublons-couloirs/applique.sh`)
- [~] Chronologies par étapes : l'import Wikipédia lit une date par section nommée, les dates partagées, « respectivement », les verbes au présent et les gabarits de date en barres obliques, ISO ou numérotés ; mesure sur les 860 articles en cache : 62 événements gagnés, 1 faux positif perdu, 65 lignes touchées. Code commité, réimport sur `vff` prévu à la fin de la chaîne du moteur. Neutralisés : 17 retranchements issus de décrets annulés par le Conseil d'État, deux fermetures OpenHistoricalMap et un déclassement de 2008 annulés par les tribunaux
- [~] **Fusion des sources de géométrie à 1 m** : tracé visé à 1 m, 5 m tolérés, 0 m d'écart à une jonction ; un morceau n'est « précis » que si son erreur estimée est sous 3 m, sinon « estimé » (rapport du 22/11 : erreur de chaque source mesurée sur 282 lignes en service, algorithme validé, prototype sur 6 lignes). Fait et commité : lecture de la plateforme LiDAR HD avec sa fiabilité par morceau, commande `vff:import-fused-traces` et lecture des morceaux fusionnés avec leur erreur estimée (source `fusion` dans `source_traces`, rien d'écrit sans `--write`). Reste : intégration au moteur (patch prêt, à appliquer)
- [ ] Fusion nationale : pilote sur le Calvados après la série du moteur, puis les lignes à tracé grossier (874 lignes, 2 117 défauts relevés), plateforme LiDAR sur les 41 000 km à fusionner, 2 000 lignes à calculer (3 à 8 h) ; règle validée : une voie OpenStreetMap abandonnée ou déposée est « estimée » sauf seconde source à 3 m (patch prêt, à appliquer)
- [~] Audit de chaque tronçon de VFF, à chaque époque : classement, placement, exactitude et tracé, notés avec leurs preuves, contrôle à la main sur un tirage stratifié, corrections rejouables (méthode : `docs/AUDIT-TRONCONS.md`). Fait : `vff:audit-segments` (381 731 tronçons, 21 ères, 19 201 synthèses de ligne), page `/validate/audit/segments`, API, GraphQL, MCP, trois contrôles à la main (100, 200 puis 13 cas), 81,6 % puis 83,4 % des km sûrs en 2030. Reste : repasser les ères passées sur `vff` avec les règles `.6`, élargir la continuité (R2), un invariant par note
- [~] Audit de chaque gare : dates d'ouverture, de fermeture, voyageurs et fret trouvées dans les sources ou, à défaut, estimées avec une précision honnête ; placement vérifié sur toutes les sources et les photographies anciennes, avec une file de relecture (méthode : `docs/AUDIT-GARES.md`). Fait : `vff:audit-stations` (9 389 gares), `vff:import-station-wikidata-dates` (3 750 dates comparées), `vff:import-station-passenger-ends` (342 fins des voyageurs lues dans les infobox), page `/validate/audit/stations`, API, GraphQL, MCP, deux contrôles à la main de 100 gares ; gares en désaccord 342 puis 217. Règles facultatives G3 (ouverture déduite bornée par la carte) et G6 (fermeture tirée de la fin des voyageurs par PK) écrites et testées, désactivées (`vff:build-station-eras --rules=`) ; source `manual` des gares absentes de toutes les autres (`database/data/gares_manuelles.php`). Reste : reRelecture du 23/11/2026 : 62 gares ouvertes aux voyageurs et parmi les plus fréquentées, dont l'ouverture était à l'année ou en conflit entre sources, relues contre l'article de la gare et celui de la ligne (révisions citées) : 24 ouvertures corrigées au jour (section 36 de `database/data/gares_corrections.php`), 24 confirmées, 14 à décider (liste dans `storage/app/reports/agents/aprem-2111/audit-gares-jour/verifiees.csv`) ; suite le même jour : 10 de ces 14 cas tranchés par une seconde source et 60 gares de plus relues, soit 26 ouvertures corrigées (section 37), 38 confirmées et 10 à décider (liste dans `storage/app/reports/agents/aprem-2111/audit-gares-2/verifiees.csv`), donc 122 gares relues au total : 50 corrigées, 62 confirmées, 10 à décider. calcul des gares (`vff:consolidate-stations`, `vff:build-station-eras`) pour lire les corrections, mesure du placement le long de la voie
- [ ] Modèle : fusion de plusieurs dates de prises de vue (calcul GPU, sur accord), publication des poids complets (seuls les poids `ortho-v1` en fp16 restent après l'incident du 28/10)
- [~] Gallica (BnF) : 236 documents numérisés rattachés à 61 lignes par le titre de leur notice (score expliqué, seuil prudent ; trajets avec étape « en passant par » et listes « entre X, Y et Z » lus ; précision mesurée sur 40 documents tirés au hasard : 95 % après la relecture du 20/11, puis 36 justes, 4 douteux et aucun faux au second et au troisième tirage, après le retrait du projet Lyon - Marseille de 1837 donné à la ligne par Grenoble), section « Dans les archives » de la fiche ligne, `GET /api/v1/lines/{slug}/archives`, `Line.archives` en GraphQL et outil MCP `line_archives` ; seules les métadonnées de la BnF (Licence Ouverte) et le lien vers Gallica sont repris, aucune image. Reste : la presse, les documents des bibliothèques partenaires et l'API de Gallica elle-même (refusée à la machine de développement)
- [~] Fonds d'archives (SNCF, associations régionales) : 198 notices des Archives SNCF (dossiers, photographies de la Médiathèque, films ; jeu de données « archives-sncf-sardo », ODbL) rattachées à 65 lignes par leur titre, avec le score de Gallica (noms de compagnies retirés, listes « sur les lignes de X à Y et de Z à W » lues, thèmes sur des personnes écartés) ; précision mesurée sur deux tirages de 40 notices : 78 justes, 2 douteuses, aucune fausse ; même section « Dans les archives » de la fiche, `GET /api/v1/lines/{slug}/archive-records`, `Line.archive_records` en GraphQL et outil MCP `line_archive_records` ; titre, type, cote, date et lien seulement, ni description ni image. Répertoire des fonds dans `docs/ARCHIVES.md`. Reste : les inventaires de FranceArchives (archive APE-EAD sous Licence Ouverte), les descriptions des notices SNCF, les associations (aucun inventaire en ligne trouvé)
- [x] Distinction visuelle des époques anciennes sur la carte : fond pâli et teinté de sépia selon l'ère (rien aujourd'hui), réglage « Teinte des époques anciennes » et `?tint=off`

---

## 🎓 Phase 11 - Communauté & rayonnement (en cours)

- [x] Multilingue : FR, EN, DE
- [x] Carte intégrable sur d'autres sites (`/maps` en iframe, code d'intégration fourni)
- [x] Pages de crédits (techniques et médias)
- [~] Partenariats : soutien à la campagne AngouLim (réouverture Angoulême - Limoges, engagement déclaré)
- [~] Page de dons : en place, plateforme finale (Patreon ou HelloAsso) selon le statut juridique de l'association ESS « UnxWares Opensource », à créer
- [x] Blog / journal de bord du projet (articles longs sur des lignes emblématiques) : `/journal`, articles Markdown versionnés (`resources/content/journal`, format dans `docs/REDACTION.md`), flux Atom et RSS, plan du site, API REST, GraphQL et MCP ; deux premiers articles (les Hirondelles, Angoulême - Limoges) ; traductions des articles à venir
- [ ] Programme de partenariat avec des associations ferroviaires locales (aucun partenariat à ce jour)
- [ ] Présence dans les conférences open data / patrimoine
- [x] Plans du site : `/sitemap.xml` (index) et un plan par famille de pages (lignes, gares, matériel, accidents, pages éditoriales, journal), 50 000 adresses au plus par fichier, publié seulement (`App\Services\Seo\SitemapBuilder`)
- [x] Application installable (PWA), avec page hors ligne
- [x] Mode hors ligne : pages publiques déjà consultées sans être connecté (HTML et Inertia, 60 au plus, 30 jours) et tuiles vectorielles de VFF déjà affichées (500 au plus, 7 jours), avec la date de la copie ; jamais les pages de compte, de contribution ni de validation, ni les tuiles OpenStreetMap
- [x] Mode hors ligne, suite : données de la carte déjà affichées (gares d'une ère, accidents : 8 fichiers au plus, 7 jours) ; hors ligne, les cartes montrent les tracés de VFF sur un fond neutre et le signal de copie le dit (style écrit dans le code, noms de gares en police locale, aucune ressource tierce gardée)
- [~] Mode hors ligne, fin : images des fiches déjà vues (vignettes de Commons, médias publiés : 300 au plus, 40 Mo, 30 jours, jamais pour une page de compte, de contribution ni de validation) ; fond de carte libre servi par VFF (PMTiles OpenStreetMap au schéma Protomaps, France et abords, zooms 0 à 15, 6,2 Go, style clair et sombre sans pictogramme, polices auto-hébergées), fond des cartes hors ligne sur les zones déjà vues (2 000 morceaux, 60 Mo, 30 jours). Reste : décider s'il devient le fond en ligne (`VFF_BASEMAP_ONLINE`, désactivé), le servir par nginx en production et le brancher sur les petites cartes des fiches (docs/FOND-DE-CARTE.md)

---

## 🚀 Phase 12 - Mise en production (en cours)

- [x] Domaine officiel `vff-project.org`
- [x] Configuration nginx de référence pour la production : en-têtes de sécurité, cache, plages PMTiles, journaux anonymisés (`deploy/nginx/`, `docs/DEPLOIEMENT-NGINX.md`)
- [x] Pages légales complètes (2026-10-21) : mentions légales, politique de confidentialité, conditions générales d'utilisation, déclaration d'accessibilité
- [ ] Messagerie de `vff-project.org` : aucun enregistrement MX à ce jour, `contact@` et `privacy@` ne reçoivent rien
- [ ] Hébergement sur l'infrastructure d'UnxWares (le domaine pointe aujourd'hui sur un serveur Hetzner Cloud à Helsinki)
- [~] Planificateur Laravel en production (recalcul quotidien de l'époque 2030) : code prêt (tâches, verrous, journal, signalement d'échec, battement vers `/up/details`, entretien, tests, `docs/PLANIFICATION.md`) ; prêt, reste à poser sur le serveur d'UnxWares (minuteur et service `deploy/systemd/production/`, `docs/DEPLOIEMENT-SERVICES.md`)
- [~] Relais des vignettes de Wikimedia Commons par VFF (`VFF_COMMONS_PROXY`, désactivé par défaut) : liste blanche stricte des chemins (seuls les fichiers que VFF cite, liste recalculée toutes les dix minutes dans Valkey), cache disque borné (`vff:commons-cache-prune`), vignettes WebP, aucun cookie tiers ; reste à l'activer en production avec le cache nginx (`docs/DEPLOIEMENT-NGINX.md`)
- [x] Flux du journal et plans du site servis sans session ni cookie, adresses absolues construites depuis `app.url`
- [x] Engagements des pages légales : journaux conservés 12 mois au plus, suppression des comptes inactifs depuis 3 ans (avec avertissement), suppression de compte (phase 5), information des membres avant un changement des CGU
- [x] Correction des défauts de l'audit de l'interface (interface unifiée, contrastes, cibles, clavier : novembre 2026)
- [x] Audit de conformité RGAA interne complet (100 % après la correction de Swagger UI et Magidoc le soir du 19/11, totalement conforme), taux publié dans la déclaration
- [ ] Suivi des erreurs et de la disponibilité (phase 0) : Kener et GlitchTip choisis, installation et moniteurs décrits dans `docs/SUIVI-PRODUCTION.md`
- [x] Requêtes vers Wikimédia polies : une requête à la fois pour Wikipédia, `maxlag` partout, User-Agent du projet
- [x] Sauvegardes du poste de développement : dépôts chiffrés côté client, rétention quotidienne, hebdomadaire et mensuelle, contrôle d'intégrité chaque dimanche ; dumps de la base ciblés avant chaque écriture
- [~] Sauvegarde de la base de production : kit prêt et essayé sur une base de test (`deploy/backup/`, `docs/SAUVEGARDE.md` : dump quotidien contrôlé, rotation 7/4/6, envoi chiffré restic, battement, essai de restauration dans une base temporaire), reste à choisir la destination avec l'hébergement

---

## 🧭 Phase 13 - Nouveaux domaines de l'encyclopédie (en cours)

- [x] **Signalisation** : données SNCF Réseau importées (`line_signalling`, `/api/v1/lines/{slug}/signalling`), fiche ligne (cantonnement et contrôle de vitesse par PK, trous des données signalés, lien vers la carte), calque « Signalisation » sur `/maps` (tranches placées sur les formes SNCF Réseau, PK calés sur les passages à niveau, tuiles `/api/v1/eras/{year}/signalling/tiles/…`) ; tranches à deux systèmes (ETCS niveau 2 et TVM sur les LGV Est, BPL et SEA) gardées entières (`signalling_systems`)
  - [x] Commande centralisée (CCVU, CCR, PCD) : système `CC` de rôle `remote_control`, distinct du cantonnement (elle commande les postes et se combine avec BAL, BAPR, block manuel ou cantonnement téléphonique sans en remplacer un), famille `centralized_control` dessinée en trait fin pointillé par-dessus le cantonnement sur la carte des époques passées, libellés fr, en et de, termes « Commande centralisée » et « CCVU » du glossaire, 18 faits relus (Saumur - Thouars 1982, Épernay - Reims 1962, Saint-Benoît - La Rochelle 1993, Corse 2025, LGV Sud-Est 2024...) ; l'état actuel de SNCF Réseau ne la porte pas, elle n'apparaît donc pas à l'ère courante
  - [x] Historique par époque : 66 mises en service datées relevées dans les articles Wikipédia des lignes (texte, listes, tableaux, infobox ; TVM des LGV datée de l'ouverture), 50 montrées sur 43 lignes (BAL, BAPR, block manuel, cantonnement téléphonique, TVM, ETCS ; le seul fait KVB est peu sûr et caché), portions placées par leurs gares d'extrémité et leurs PK calés (`vff:import-signalling-history`, table `line_signalling_events`, CC BY-SA, faits et permalien de la révision), frise « Signalisation au fil du temps » sur la fiche ligne, calque « Signalisation » de `/maps` aux époques passées (faits en vigueur à la fin de l'ère ; l'état actuel de SNCF Réseau reste celui d'aujourd'hui), API REST (`/lines/{slug}/signalling-history?era=`, tuiles par ère), GraphQL (`Line.signalling_history(era:)`, `sheet.signalling.history`) et outil MCP `signalling_history`
  - [x] Historique par époque, autres sources datées : 35 mises en service relevées dans des documents (décisions et rapports d'activité de l'EPSF, bilans LOTI de SNCF Réseau et de l'État, presse), chacune confirmée par un document officiel ou par deux sources (`database/data/signalling_history_sources.php`, `vff:import-signalling-sources`, `source.provider = manual` et `source.documents` en REST, GraphQL et MCP, un lien par document sur la frise) ; un fait de Wikipédia qu'un document redit n'est plus répété (9 dates à l'année remplacées par la date au jour)
  - [x] Fichier de corrections sourcées : un fait de Wikipédia ou d'un document peut être neutralisé ou remplacé (raison et source), masqué partout et servi par REST, GraphQL (`Line.signalling_corrections`) et MCP ; l'ETCS faux de la LGV Sud-Est est masqué
  - [~] Historique par époque, faits relus dans Wikipédia (mises en service de BAL et de BAPR par section, citation vérifiée à chaque import) : code commité, à lancer après les jonctions latérales
  - [ ] Historique par époque : relevé à poursuivre sur les lignes classiques (BAL et BAPR des années 1950 à 2020 : peu de documents datés en accès libre), et placer les portions des LGV nommées par des bifurcations (15 faits non placés)
- [x] **Passages à niveau** : 16 801 PN de SNCF Réseau (ODbL), calque « Passages à niveau » sur `/maps` (à partir du zoom 9), section de la fiche ligne (nombre par type, liste repliable), API REST (`/api/v1/level-crossings`, `/api/v1/lines/{slug}/level-crossings`, tuiles), GraphQL (`Line.level_crossings`, `levelCrossings`) et outil MCP `level_crossings`
- [x] **Matériel roulant** : 727 séries importées de Wikidata (CC0) avec leur image Commons et la provenance de chaque champ (`vff:import-materials`), 2 609 liens ligne × matériel attestés par Wikidata, par les articles Wikipédia (phrase citée, permalien, fiabilité) ou par les relations qui parcourent la ligne, API REST (`/api/v1/materials`, `/api/v1/lines/{slug}/materials`) et GraphQL ; fiches enrichies sur le site ; périodes de circulation datées (`vff:import-material-periods`, table `line_material_periods` : 369 périodes, lues dans les articles Wikipédia des lignes et des séries, permalien de la révision, fiabilité ; jamais de phrase sur le site ni dans le MCP), sur les fiches ligne et série, en REST, GraphQL (`LineMaterialLink.periods`) et MCP (`line_materials`) ; à faire : lire les tableaux des articles, rattacher les périodes aux portions du tracé, dates Wikidata quand les lignes auront des déclarations P3438 datées
- [x] **Accidents** : fiches de Wikidata, de Wikipédia et du BEA-TT, pages, API, calque de carte ; contexte sourcé pour 548 fiches (`vff:enrich-accidents`, fichiers de `database/data/accident_contexts`) : archives, enquêtes et presse de l'époque en français, anglais et allemand, documents cités (Gallica en lien seulement), images de Commons, corrections sourcées des lieux, dates, natures et bilans, doublons gardés en alias ; noms de fiche durables (`database/data/accident_slugs.php`) et fiches rédigées à la main pour un accident que seule la presse décrit (`vff:import-accidents --only=manual`) ; sections « Contexte » et « Documents et archives » en REST, GraphQL et MCP. Le 22/11 : « personne heurtée » publiée quand le BEA-TT ou un contexte sourcé l'établit (12 fiches), six doublons de la liste de Wikipédia fondus avec redirection 301, nature et commune corrigées affichées en clair, correction fondée sur une seule liste de Wikipédia signalée, Delpher crédité. Reste : les fiches qui ne reposent que sur la liste de Wikipédia (Gallica et Chronicling America refusent la machine), sources à valider (Victimes du rail)
- [~] **Médias, droits par image et fonds ouverts** : droits propres à chaque média (`derivatives_ok`, précision de la date) et réglage « non commercial » appliqué partout où un média est servi ; comblement des manques d'images des gares, lignes et séries par Wikidata (P18, P373) et les catégories de Commons, avec fichier de candidats rejouable ; import des autochromes d'Albert-Kahn, de cartes postales d'archives départementales et municipales (Hauts-de-Seine, Suresnes, Sèvres, Évry, Gadagne, Saône-et-Loire), de Getty Open Content, de l'École des ponts et de Numistral (environ 610 images, 129 gares, droits par fonds). Code commité le 22/11 ; appliqués la nuit suivante : migration des droits, candidats Commons rattachés (71 438 médias avant, 114 726 après), Rosalis, Pireneas, Évry et Gadagne (134 images sur 26 gares, licence marquée incertaine)
  - [ ] Fonds d'images à intégrer : associations, archives et bibliothèques, Flickr sous licence libre, licence vérifiée image par image ; courriels aux détenteurs préparés (`aprem-2111/sources-images*.md`)
  - [~] Photos de rue Panoramax triées par un modèle de vision (CLIP, poids MIT, processeur seul) : score « gare visible » réglé sur 250 photos étiquetées à la main, 4 % hors sujet mesurés sur 100 autres, instance d'OpenStreetMap France jamais lue ; import à lancer par le coordinateur seul sur la machine (`aprem-2111/panoramax-appliquer.sh` : contrôles, sauvegarde ciblée de `media`, import à blanc puis réel, droits et auteur de chaque photo, 5 fiches de gares ; cache actuel 189 photos sur 151 gares, national environ 5 h 30). Contrôle à la main du 23/11 sur 60 photos gardées et 30 écartées : 18 % hors sujet au seuil 0,7 (8 % à 0,85, 4 % à 0,95), d'où l'option `--min-score` ; choix du seuil à Baptiste (`aprem-2111/panoramax-import.md`). Avant lui, un tri géométrique : bâtiment voyageur d'OpenStreetMap ou BD TOPO dans le champ de la caméra, autres bâtiments BD TOPO comme obstacles, panoramas à cap incertain écartés. Photos hors sujet de 65 % à 10 % sur un tirage de 60 gares, 12 % au recensement de 34 photos sur 1 500 gares, mais environ 160 gares gardées au lieu de 800 (IGN seul) ; objectif de moins de 5 % non atteint ; aucun import lancé (`aprem-2111/panoramax-tri.md`)
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
