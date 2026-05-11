# Contribuer à VFF

Merci de l'intérêt que tu portes à **Voies Ferrées de France** ! Que tu sois passionné de rail, photographe d'archive, archiviste ou développeur, il y a une place pour toi.

Ce document explique :
- [Code de conduite](#code-de-conduite)
- [Types de contributions](#types-de-contributions)
- [Workflow Git](#workflow-git)
- [Conventions de code](#conventions-de-code)
- [Tests](#tests)
- [Médias & archives](#médias--archives)
- [Signaler un bug / proposer une fonctionnalité](#signaler-un-bug--proposer-une-fonctionnalité)

---

## Code de conduite

VFF est une communauté **inclusive, bienveillante et passionnée**. Toute forme de harcèlement, de discrimination ou de comportement irrespectueux entraînera l'exclusion sans préavis.

- Sois patient·e avec les nouveaux contributeurs
- Reste factuel·le dans les revues de code
- Présume la bonne foi
- Cite tes sources (archives, photos, dates)

---

## Types de contributions

### 🚂 Données ferroviaires

Le plus précieux. Tu peux :
- Documenter une **ligne** (tracé, dates clés, historique) directement depuis l'interface web une fois inscrit·e
- Identifier le **matériel roulant** qui a circulé sur une ligne donnée
- Apporter des **photographies datées** (avec leur licence)
- Corriger des erreurs (mauvaises dates, tracés imprécis)
- Importer un fonds d'archive (contacte-nous d'abord sur [Discord](https://discord.com/invite/6SwTfXBx4h))

Toute contribution est **validée par les pairs** (minimum 2 validators) avant publication.

### 💻 Code

Voir [Workflow Git](#workflow-git) et [Conventions de code](#conventions-de-code).

### 📝 Documentation

Améliorations du README, ROADMAP, traductions, tutoriels — toutes les PR sur les `.md` sont les bienvenues.

### 🐛 Bugs & idées

Voir [Signaler un bug / proposer une fonctionnalité](#signaler-un-bug--proposer-une-fonctionnalité).

---

## Workflow Git

### 1. Fork & clone

```bash
gh repo fork vff-project/vff --clone
cd vff
```

### 2. Branche

Une PR = une feature / un fix. Branche depuis `main` :

```bash
git switch -c feat/timeline-slider
```

**Convention de nommage des branches** :
- `feat/<short-slug>` — nouvelle fonctionnalité
- `fix/<short-slug>` — correction de bug
- `refactor/<short-slug>` — refactoring sans changement de comportement
- `docs/<short-slug>` — documentation uniquement
- `chore/<short-slug>` — outillage, deps, CI

### 3. Commits

On suit **[Conventional Commits](https://www.conventionalcommits.org/fr/v1.0.0/)** :

```
feat(maps): ajout du sélecteur d'époque
fix(import): corriger le parsing des tags OSM railway=razed
refactor(geo): extraire LineStringCast dans Services\Geo
docs(readme): documenter la procédure d'import OSM
chore(deps): bump laravel/framework to ^12.5
```

Types autorisés : `feat`, `fix`, `refactor`, `perf`, `style`, `test`, `docs`, `chore`, `ci`, `build`.

### 4. Push & PR

```bash
git push -u origin feat/timeline-slider
gh pr create --base main
```

**Template de PR** :

```markdown
## Quoi
<une à deux phrases sur la nature du changement>

## Pourquoi
<contexte / motivation>

## Comment tester
- [ ] étape 1
- [ ] étape 2

## Captures (si UI)
<avant / après>

## Checklist
- [ ] Les tests passent (`php artisan test`)
- [ ] Le code est formaté (`./vendor/bin/pint` + `npm run format`)
- [ ] La doc est à jour si nécessaire
```

### 5. Revue & merge

- Au moins **1 approbation** d'un mainteneur
- CI verte (Pint, ESLint, Prettier, PHPUnit, build Vite)
- Pas de conflit avec `main`
- Merge en **squash** (un commit final propre par PR)

---

## Conventions de code

### PHP

- **PSR-12** strict, appliqué via [Laravel Pint](https://github.com/laravel/pint) → `./vendor/bin/pint`
- Types stricts partout : `declare(strict_types=1);` en tête de chaque fichier
- Préférer **types nominaux** sur arrays associatifs (DTO / value objects)
- Pas de logique dans les contrôleurs : ils délèguent à des **actions** ou **services**
- Modèles Eloquent : pas de logique métier lourde, pas de scopes magiques cachant des side-effects
- Migrations idempotentes (utiliser `Schema::hasTable` / `Schema::hasColumn` si besoin)

### JavaScript / Svelte

- **ESLint** + **Prettier** (config dans `.eslintrc.cjs` et `.prettierrc`)
- Composants Svelte : **PascalCase** (`TimelineSlider.svelte`)
- Pas de jQuery, pas de Vue, pas de React — uniquement Svelte 5
- Préférer les **runes** Svelte 5 (`$state`, `$derived`, `$effect`) à l'ancienne syntaxe
- Stores partagés dans `resources/js/lib/stores/`

#### ⚠ Pièges Svelte 5 spécifiques à VFF

1. **`bind:this` doit utiliser `$state()`** — sinon Svelte 5 émet un warning « not reactive ». Exemple :
   ```svelte
   let headerEl = $state();  // ✅ correct
   let headerEl;             // ❌ warning
   ```

2. **Pas de `<header>` dans les sections internes** — le CSS d'origine a un sélecteur bare `header { height: 200vh }` pour le hero parallax. Toute balise `<header>` ailleurs hérite des 200vh et casse le layout. Utiliser `<div>` ou `<hgroup>`.

3. **Classes globales pour les boutons** — les `<Link>` Inertia rendent leur `<a>` dans le scope d'un sous-composant. Les rules `.vff-btn` scopées dans une page ne s'y appliquent pas. Toujours utiliser les classes globales `.vff-btn` / `.vff-btn.primary` / `.vff-btn.on-cta` / `.vff-btn.ghost-on-cta` définies dans `general.css`.

4. **`all: unset` pour les `<button>` icônes** — sinon ils héritent du style natif du navigateur (bordure grise). Voir `LanguageSwitcher.svelte` pour le pattern.

### CSS

- Préserver les **tokens existants** : couleur d'accent `#ff6500`, polices Lato/Gabarito/Inter/Montserrat
- Préférer les **CSS custom properties** (`--vff-accent`, `--vff-font-display`, `--vff-bg`, `--vff-text`…) pour que le dark mode bascule automatiquement
- Scoped CSS dans les composants Svelte par défaut, CSS global uniquement pour ce qui s'applique vraiment globalement (`general.css`, `header.css`, `footer.css`)
- BEM facultatif mais cohérent : `.maps-container > .maps-box > .maps-box__title`
- **Ne pas modifier** `header.css`, `footer.css`, `whoarewe.css`, `maps.css` (CSS historique du site — uniquement écrit du CSS additif dans `general.css` ou scoped Svelte)
- Dark mode : ajouter les overrides dans `general.css` sous `[data-theme="dark"]` (cascade additive, pas de `!important` sauf cas extrême)

### Nommage

- **Anglais** pour les identifiants (variables, fonctions, classes, tables) — convention universelle
- **Français** pour les libellés UI et les commentaires inline expliquant un *pourquoi*
- Slugs URL en kebab-case anglais : `/lines/paris-brest` (mais on peut accepter des alias français)

### Géo

- Toujours stocker en **SRID 4326** (WGS 84, lat/lon)
- Pour les calculs de distance, projeter en **3857** ou utiliser `ST_DistanceSphere`
- Geometry plutôt que Geography pour la perf, sauf cas particuliers
- Indexer toutes les colonnes spatiales (`CREATE INDEX ... USING GIST(geom)`)

---

## Tests

```bash
php artisan test                    # tous les tests
php artisan test --filter=Line      # uniquement les tests sur Line
php artisan test --parallel         # exécution parallèle
```

**Règles** :
- Toute action métier nouvelle → test feature
- Tout bug fix → test de non-régression d'abord
- Les tests géo utilisent une vraie base Postgres+PostGIS (pas de mock) — voir `tests/TestCase.php`
- Garder les tests rapides : factories légères, pas de seed massif

---

## Médias & archives

Les photos et documents apportés à VFF doivent respecter :

1. **Licence claire** : domaine public, CC, ou droits cédés explicitement par l'auteur
2. **Métadonnées** : date de prise de vue (au moins année), lieu (au moins commune), auteur si connu
3. **Format** : JPEG / PNG pour photos, PDF pour documents, max 10 Mo par fichier
4. **Pas de filigrane intrusif** — un crédit discret en coin est OK

Si tu apportes un fonds important (>50 documents), contacte-nous d'abord sur Discord pour qu'on organise l'import.

---

## Signaler un bug / proposer une fonctionnalité

### Bug

Ouvre une [issue](https://github.com/vff-project/vff/issues/new) avec le template `bug` :
- Comportement attendu
- Comportement observé
- Étapes de reproduction
- Captures / logs
- Environnement (navigateur, OS)

### Idée / feature

Issue avec le template `idea`. Avant de coder, **discute-la d'abord** : c'est plus efficace pour tout le monde.

### Question / aide

Plutôt sur [Discord](https://discord.com/invite/6SwTfXBx4h) — réponse plus rapide que sur GitHub.

---

## Merci 🚂

Chaque contribution, même un typo, fait avancer la plus grande encyclopédie ferroviaire de France. À très vite sur les rails.
