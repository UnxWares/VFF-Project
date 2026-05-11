# 🌙 TODO — Reprise demain

Récap des points soulevés pendant la session du 2026-05-11 qui n'ont pas encore été tranchés / fixés visuellement. Liste ordonnée par priorité, avec captures à fournir si besoin.

## 🐛 Bugs à vérifier (fixes appliqués mais non confirmés visuellement)

- [ ] **Toggle thème** : `applyTheme()` réinjecté, `data-theme` posé sur `<html>`. Vérifier que le clic sur sun/moon bascule bien tout le site.
- [ ] **Langue sans refresh** : approche client-side cookie + `router.reload({ only: ['locale', 'translations'] })` au lieu du POST `/locale` qui foirait peut-être en Inertia 3. Confirmer que cliquer sur EN bascule tout immédiatement.
- [ ] **Logo navbar cliquable** : `nav-title` wrappé dans `<Link href="/">` + règles CSS additives pour neutraliser le style natif du lien. Vérifier qu'on revient bien à l'accueil et qu'il n'y a pas d'underline parasite.
- [ ] **Timeline jalons** : barre verticale bornée `top: 40px; bottom: 40px` (= centre des dots extrêmes). Plus de bouts de ligne flottants.
- [ ] **Transition CTA orange → footer** : `margin-bottom: -30px` sur `.section-block.cta`. Plus de bande blanche entre dégradé orange et footer.
- [ ] **Gradient PageHero sur toutes les pages** : `transparent 70%` au lieu de `100%` → fade complet bien avant le bord bas. Vérifier sur whoarewe / contribute / donate / maps / api / légales.
- [ ] **Whoarewe — Genèse/Vision** : titres maintenant centrés via `title=` prop de SectionBlock. Texte en `text-align: left` au lieu de `justify`.

## ❓ Issues remontées sans précision (besoin de captures / détails demain)

- [ ] **Police / typographie** (mentionné dans le dernier message) — où ? quelles polices ? quel comportement attendu ? Capture d'écran utile.
- [ ] **Page API — infos manquantes** (mentionné dans le dernier message) — quelles infos ? La capture montre la section "Formats supportés" — est-ce les icônes inconsistantes (`{}` vs globe vs grille) ? Le contenu insuffisant ? L'organisation ?
- [ ] **Marges** (mentionné plus tôt) — sur quelle(s) page(s) ? Entre quelles sections ? Trop ou pas assez ?
- [ ] **LanguageSwitcher visuel** (mentionné plus tôt) — qu'est-ce qui ne va pas ? Le `🇫🇷 FR` minimal façon UnxWares, le dropdown, le hover ? Capture utile.

## 📋 Améliorations / features en attente

### Couche front
- [ ] Propager `text-align: left` (au lieu de `justify`) sur toutes les pages internes (`/contribute`, `/donate`, `/api`, légales) — passer en règle globale dans `general.css` pour cohérence.
- [ ] Audit complet en dark mode de toutes les pages (jamais testé visuellement)
- [ ] Audit complet responsive 375/768/1024/1440 (jamais testé)
- [ ] Améliorer le mockup `/maps` (slider d'époque interactif, mais juste pour preview — la vraie carte arrive en Phase 2)
- [ ] Lazy-load des images d'archive sur la home

### Couche domaine
- [ ] Factories Eloquent pour chaque modèle (`EraFactory`, `LineFactory`, `LineSegmentFactory`, etc.) — utile pour tests
- [ ] `ParisBrestSeeder` complet : 1 ligne réelle, ses segments par époque, ses gares principales (Le Mans, Rennes, Saint-Brieuc, Brest), ses matériels historiques (Atlantique TGV, autorails X2700), ses événements (inauguration 1857, électrification 1937, LGV connexion 1989, etc.)
- [ ] Premier vrai run `php artisan vff:import-osm --region=FR-BRE` (Bretagne, taille gérable) pour valider le pipeline end-to-end
- [ ] Affiner le mapping OSM → modèle métier (gestion fine des statuts `railway=*`)

### Phase 2 — Carte MapLibre
- [ ] Endpoint `GET /api/v1/lines.geojson?era=2030`
- [ ] Endpoint `GET /api/v1/tiles/{z}/{x}/{y}.mvt?era=2030` via `ST_AsMVT`
- [ ] Composant `<RailMap>` Svelte + MapLibre GL JS
- [ ] Style cartographique custom (rails orange `#ff6500`, gares pastilles, statuts dégradés)
- [ ] Remplacer le mockup de `/maps` par la vraie carte

### Phase 0 — Outillage manquant
- [ ] CI GitHub Actions (Pint, ESLint, Prettier, PHPUnit, build Vite)
- [ ] Sentry + Uptime Kuma pour monitoring
- [ ] Pre-commit hooks (Lefthook)
- [ ] Premiers tests feature (`HomePageTest`, `LocaleTest`)

### Auth (Phase 5)
- [ ] Login / register pages (Laravel Breeze ou Fortify)
- [ ] Roles & policies (visitor / contributor / validator / admin)
- [ ] Profile / settings page

## 🎯 Décisions à arbitrer

- [ ] **Élargissement temporel** : à terme, étendre les époques de 1900 à 2030 (cf. memory `vff_temporal_scope.md`). Pas pour cette phase mais à garder en tête dans la UI Timeline.
- [ ] **Plateforme de dons** : Patreon vs HelloAsso — décision finale dépend du statut juridique de l'asso ESS « UnxWares Opensource » (cf. memory `vff_organization.md`).
- [ ] **Apache AGE** : décision validée mais implémentation reportée en Phase 8. Pas à activer maintenant.

## 📝 Notes diverses

- Build actuel : 232 KB JS / 70 KB gzip, 91 KB CSS / 16 KB gzip — propre.
- Smoke test PostGIS OK (LineString créé/lu, `ST_Length` calculé via Magellan).
- 11 migrations passent en `migrate:fresh`.
- Stack à jour : Laravel 13.8, Inertia 3.1, Svelte 5.55, Magellan 2.1, Vite 8, PHP 8.5.
- Toutes les pages internes (whoarewe, contribute, donate, maps, api, légales) traduites FR + EN. DE partiel (nav/footer/common).

— Bonne nuit, on reprend demain.
