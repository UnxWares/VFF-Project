# Tests de bout en bout (fumée)

Une petite suite Playwright, lancée à la main contre un serveur déjà démarré. Elle ne remplace ni PHPUnit ni
vitest : elle vérifie que les pages principales s'ouvrent vraiment dans un navigateur.

## Lancer

```bash
bin/pnpm test:e2e                                   # contre le serveur de dev (http://192.168.1.12:8000)
E2E_BASE_URL=http://127.0.0.1:8059 bin/pnpm test:e2e   # contre un autre serveur
bin/pnpm test:e2e -g "carte"                        # un sous-ensemble
bin/pnpm test:e2e --project=mobile-390              # un seul viewport
```

Variables : `E2E_BASE_URL` (adresse cible), `E2E_WORKERS` (2 par défaut), `E2E_OUTPUT_DIR` (rapports et traces,
`storage/app/reports/e2e` par défaut, dossier marqué `.nobackup`).

`bin/pnpm` lance pnpm dans un conteneur Node : pour exécuter Playwright lui-même, utiliser le Node de l'hôte
(`npx playwright test`) ou l'image officielle ci-dessous. Le navigateur Chromium se trouve dans
`~/.cache/ms-playwright` ; s'il manque : `npx playwright install chromium`.

### Sans navigateur sur l'hôte : image officielle

```bash
podman run --rm --network=host --ipc=host -v "$PWD":/work:Z -w /work \
  -e E2E_BASE_URL=http://192.168.1.12:8000 \
  mcr.microsoft.com/playwright:v1.65.1-noble npx playwright test
```

La version de l'image doit suivre celle de `@playwright/test` dans `package.json`.

## Ce qui est couvert

Pour chaque page, à 1 280 px (`bureau-1280`) et à 390 px (`mobile-390`), en français puis en anglais :

- statut HTTP attendu (200, ou 404 pour la page d'erreur) ;
- titre de la page ;
- un élément clé visible (titre principal, champ de connexion, canvas de la carte) ;
- aucune erreur de console ni exception de la page ;
- aucune requête réseau en échec, aucune réponse HTTP 4xx ou 5xx (hors la page 404 elle-même) ;
- axe-core (`@axe-core/playwright`) sans violation grave (impact `serious` ou `critical`).

Pages : accueil, liste et fiche d'une ligne (Andelot - La Cluse), liste et fiche d'une gare, liste et fiche d'un
accident, `/api`, `/docs/api` (Scalar), connexion, 404. Scénarios : la carte `/maps` se crée, la frise des époques
change l'ère (`?era=`), la légende change le mode de coloration ; la recherche (Ctrl+K) trouve une ligne et un accident ;
le mode sombre sur l'accueil et sur la fiche de ligne.

## Fichiers

- `playwright.config.js` : adresse cible, projets (viewports), rapports.
- `tests/e2e/fixtures.js` : espion console et réseau, langue, thème, axe-core, liste des défauts connus, relais
  externes simulés.
- `tests/e2e/vue-aerienne.jpg` : image servie à la place des vues aériennes de l'IGN.

## Services externes

La suite vérifie nos pages, pas les services tiers. Le relais `/media/aerial/{gare}/{période}.jpg` lit le WMS de l'IGN
à la première demande : depuis un runner de CI, l'IGN répond parfois 503, et une période sans couverture au point de
la gare du jeu minimal répond 404 (série 45148007135 du 23/11/2026, fiche gare en échec). La fixture `relaisSimules`
répond donc à sa place avec `vue-aerienne.jpg`, pour chaque page et contre n'importe quel serveur ; le relais lui-même
est couvert par `tests/Feature/Media/AerialImageRelayTest.php`.
- `tests/e2e/fumee.spec.js` : les pages et les scénarios.

## Défauts connus

`DEFAUTS_CONNUS` dans `fixtures.js` liste les violations axe-core déjà constatées sur le site : elles sont notées en
annotation dans le rapport sans faire échouer la suite. Retirer l'entrée quand le défaut est corrigé.

## Jeu de données

La suite vise trois fiches (constantes `LIGNE`, `GARE`, `ACCIDENT` de `fumee.spec.js`) : la ligne `rfn-878000`
(Andelot-en-Montagne - La Cluse), la gare `andelot` et l'accident `1890-09-16-andelot-en-montagne`. Sur une base vide
migrée, `database/seeders/E2eSeeder.php` les crée avec le strict nécessaire : les ères (1830 à 2030), la ligne et une
seconde ligne, trois gares, une portion, les arrêts et les états de gare par ère depuis 1870, l'accident rattaché à la
ligne et à la gare. Ce sont des faits publics, sans compte ni donnée personnelle. Les articles du journal viennent des
fichiers versionnés de `resources/content/journal`. Le seeder est rejouable sans doublon et refuse toute base dont le
nom ne finit pas par `_test` (jamais `vff`). Test : `tests/Feature/E2eSeederTest.php`.

```bash
DB_DATABASE=vff_xxx_test bin/php artisan migrate --force
DB_DATABASE=vff_xxx_test bin/php artisan db:seed --class='Database\Seeders\E2eSeeder' --force
```

## Intégration continue

Le job `e2e` de `.github/workflows/ci.yml` démarre PostgreSQL + PostGIS et Valkey en services, migre la base
`vff_e2e_test`, lance `E2eSeeder`, construit le front (`pnpm build`), démarre `php artisan serve` sur 127.0.0.1:8000,
installe Chromium (`pnpm exec playwright install --with-deps chromium`) puis lance `pnpm test:e2e` (`CI=1` : une
relance par test, rapport HTML). En cas d'échec, `storage/app/reports/e2e/html` et `traces` sont publiés en artefact
`playwright-report`. Durée visée : moins de 10 minutes (la suite seule dure environ 1 min 10 en local).
