<?php

namespace Tests\Feature\Database;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Chaque migration est réversible : son down() défait EXACTEMENT son up()
 * (tables, colonnes, types, valeurs par défaut, index, contraintes).
 *
 * Méthode : migrate:fresh, puis rollback migration par migration en photographiant
 * le schéma à chaque pas ; puis ré-application fichier par fichier, la photo après
 * k migrations devant être identique à celle prise au retour en arrière.
 *
 * Pas de RefreshDatabase (on manipule le schéma lui-même) : le test laisse la
 * base ENTIÈREMENT migrée pour les tests suivants du même processus.
 */
class MigrationsRollbackTest extends TestCase
{
    /**
     * Écarts VOULUS et documentés dans le down() concerné, ignorés à la comparaison :
     *   - extend_media_for_external_sources ne rétablit pas media.license NOT NULL
     *     DEFAULT 'CC-BY-SA-4.0' (pas de licence présumée, même après rollback) ;
     *   - drop_graph_foreign_keys_to_hot_tables et extend_network_graph_per_era ne
     *     recréent pas les clés étrangères du graphe (dérivé, reconstruit ère par
     *     ère, il peut référencer des tronçons régénérés depuis).
     */
    private const DELIBERATE_DRIFT = [
        'media|license|',
        'network_edges|network_edges_from_node_id_foreign|',
        'network_edges|network_edges_to_node_id_foreign|',
        'network_edges|network_edges_segment_id_foreign|',
        'network_edges|network_edges_line_id_foreign|',
        'station_edges|network_edges_line_id_foreign|',
    ];

    /**
     * @param  array<string, list<string>>  $snapshot
     * @return array<string, list<string>>
     */
    private function withoutDeliberateDrift(array $snapshot): array
    {
        return array_map(fn (array $rows) => array_values(array_filter($rows,
            fn (string $row) => ! array_filter(self::DELIBERATE_DRIFT, fn ($prefix) => str_starts_with($row, $prefix)))), $snapshot);
    }

    /** @return list<string> noms de fichiers de migration, dans l'ordre d'exécution */
    private function migrationFiles(): array
    {
        $files = array_map('basename', glob(database_path('migrations/*.php')) ?: []);
        sort($files);

        return $files;
    }

    /**
     * Photo du schéma public, hors tables des extensions et de la table des migrations.
     *
     * @return array{columns: list<string>, indexes: list<string>, constraints: list<string>}
     */
    private function schemaSnapshot(): array
    {
        $excluded = "('migrations', 'spatial_ref_sys')";

        $columns = array_map(fn ($r) => implode('|', (array) $r), DB::select(<<<SQL
            SELECT table_name, column_name, data_type, udt_name, is_nullable, COALESCE(column_default, '') AS def,
                   COALESCE(character_maximum_length::text, '') AS len
            FROM information_schema.columns
            WHERE table_schema = 'public' AND table_name NOT IN {$excluded}
              -- Tables seulement : les vues de PostGIS (geometry_columns…) appartiennent à l'extension.
              AND table_name IN (SELECT table_name FROM information_schema.tables
                                 WHERE table_schema = 'public' AND table_type = 'BASE TABLE')
            ORDER BY table_name, column_name
        SQL));

        $indexes = array_map(fn ($r) => $r->indexdef, DB::select(<<<SQL
            SELECT indexdef FROM pg_indexes
            WHERE schemaname = 'public' AND tablename NOT IN {$excluded}
            ORDER BY indexdef
        SQL));

        $constraints = array_map(fn ($r) => "{$r->tbl}|{$r->conname}|{$r->def}", DB::select(<<<SQL
            SELECT c.conrelid::regclass::text AS tbl, c.conname, pg_get_constraintdef(c.oid) AS def
            FROM pg_constraint c
            JOIN pg_namespace n ON n.oid = c.connamespace
            WHERE n.nspname = 'public' AND c.conrelid <> 0
              -- NOT NULL : déjà dans is_nullable ; PostgreSQL 18 leur donne un nom
              -- dérivé de la table au moment de la pose, sans portée fonctionnelle.
              AND c.contype <> 'n'
              AND c.conrelid::regclass::text NOT IN {$excluded}
            ORDER BY 1, 2, 3
        SQL));

        return compact('columns', 'indexes', 'constraints');
    }

    /**
     * Écart lisible : seulement les lignes en trop ou manquantes (pas tout le schéma).
     *
     * @param  array<string, list<string>>  $expected  schéma photographié au retour en arrière
     * @param  array<string, list<string>>  $actual  schéma après ré-application
     */
    private function assertSameSchema(array $expected, array $actual, string $message): void
    {
        $expected = $this->withoutDeliberateDrift($expected);
        $actual = $this->withoutDeliberateDrift($actual);
        $drift = [];
        foreach ($expected as $kind => $rows) {
            foreach (array_diff($rows, $actual[$kind]) as $row) {
                $drift[] = "rollback seulement : {$kind} {$row}";
            }
            foreach (array_diff($actual[$kind], $rows) as $row) {
                $drift[] = "migrate seulement  : {$kind} {$row}";
            }
        }
        $this->assertSame([], $drift, $message);
    }

    public function test_every_down_exactly_reverts_its_up_and_the_schema_rebuilds_identically(): void
    {
        $files = $this->migrationFiles();
        $this->assertGreaterThan(50, count($files), 'migrations introuvables');

        $this->assertSame(0, Artisan::call('migrate:fresh', ['--force' => true]));
        $this->assertSame(count($files), DB::table('migrations')->count());
        $full = $this->schemaSnapshot();
        foreach (['lines', 'line_segments', 'stations', 'eras', 'line_events', 'media', 'contributions', 'users'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "table {$table} absente après migrate:fresh");
        }

        // Retour en arrière, une migration à la fois : $back[k] = schéma avec k migrations appliquées.
        $back = [count($files) => $full];
        for ($k = count($files) - 1; $k >= 0; $k--) {
            $this->assertSame(0, Artisan::call('migrate:rollback', ['--step' => 1, '--force' => true]),
                "rollback de {$files[$k]} en échec");
            $this->assertSame($k, DB::table('migrations')->count(), "{$files[$k]} toujours enregistrée");
            $back[$k] = $this->schemaSnapshot();
        }

        // Tout est défait : plus aucune table applicative.
        $this->assertSame([], $back[0]['columns'], 'tables restantes après rollback complet');
        $this->assertSame([], $back[0]['indexes']);

        // Ré-application fichier par fichier : chaque état doit être celui du retour en arrière.
        foreach ($files as $i => $file) {
            $this->assertSame(0, Artisan::call('migrate', ['--path' => 'database/migrations/'.$file, '--force' => true]),
                "re-migration de {$file} en échec");
            $this->assertSameSchema($back[$i + 1], $this->schemaSnapshot(),
                "le down() d'une migration postérieure ou égale à {$file} ne défait pas exactement son up()");
        }

        $this->assertSameSchema($full, $this->schemaSnapshot(), 'schéma final différent de migrate:fresh');
        $this->assertSame(count($files), DB::table('migrations')->count());
    }
}
