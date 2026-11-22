# Tests PHPUnit : suites ciblées et parallèles

## Commande recommandée

```bash
VFF_TEST_DB=vff_<sujet>_test bin/test --filter=Schedule              # parallèle, 2 processus
VFF_TEST_DB=vff_<sujet>_test bin/test --processes=4 tests/Feature/Journal
VFF_TEST_DB=vff_<sujet>_test bin/test --recreate-databases --group=x
VFF_TEST_DB=vff_<sujet>_test bin/test --sequential --filter=Schedule # sans parallèle
```

`bin/test` exige `VFF_TEST_DB` (suffixe `_test`), met `--parallel --processes=2` par défaut et place les options de
parallélisme avant `--filter`, `--group` et les chemins. La suite complète reste
`VFF_TEST_DB=vff_<sujet>_test bin/php artisan test --parallel --processes=4 --recreate-databases` (`make test`).
Avec plusieurs agents sur la machine, voir le verrou commun et le plafond de processus de leurs consignes.

## Les deux pièges (analyse du 22/11/2026)

1. **Ordre des options.** `artisan test` ne reconnaît `--parallel`, `--processes` et `--recreate-databases` que s'ils
   précèdent les options qu'il ne déclare pas (`--filter`, `--group`, chemins). Après elles, PHPUnit lit la ligne :
   `--filter=X --parallel` donne `Unknown option "--parallel"`, et `--filter=X --processes=2` (sans `--parallel`)
   donne `Unknown option "--processes"`. Écrire `--parallel --processes=2 --filter=X`, ou utiliser `bin/test`.
2. **Base de départ absente.** En parallèle, Laravel se connecte à la base nommée par `VFF_TEST_DB` pour créer
   `<base>_test_<n>`. Si cette base n'existe pas, chaque test échoue à la mise en place (`database "..." does not
   exist` dans le journal PostgreSQL : 459 occurrences pour 457 erreurs rapportées). `tests/CreatesApplication.php`
   la crée désormais (une fois par processus, seulement si le nom finit par `_test` ou `_test_<n>`) ; les extensions
   PostGIS viennent de la migration `enable_postgis_extensions`. Plus besoin de `make test-db` pour un nouveau nom.

## Vérifier

```bash
export VFF_TEST_DB=vff_<sujet>_test            # nom jamais utilisé
bin/test --recreate-databases --filter=ScheduleTest   # doit finir « OK » sans créer la base à la main
bin/php artisan test --filter=DatabaseGuard --parallel   # doit répondre « Unknown option "--parallel" » (ordre)
bin/php artisan test --filter=EnsureBaseTestDatabase     # test unitaire de la création
```

Nettoyage : `DROP DATABASE` (ou `dropdb`) sur les noms littéraux `<base>`, `<base>_test_1`, `<base>_test_2`…, après avoir
vérifié qu'ils finissent par `_test` ou `_test_<n>`. Jamais de boucle sur un motif.
