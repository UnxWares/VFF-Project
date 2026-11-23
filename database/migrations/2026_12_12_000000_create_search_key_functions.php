<?php

use App\Support\Search\SearchFields;
use App\Support\Search\SearchText;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Recherche par nom (App\Support\Search\SearchText) :
 *
 *   - fonctions immuables `vff_unaccent` (unaccent à dictionnaire fixé) et `vff_search_key` (clé de recherche, même
 *     formule que SearchText::key en PHP ; PL/pgSQL pour qu'elle ne soit pas développée dans chaque requête) ;
 *   - colonnes calculées `search_name` (clé du nom) et `search_all` (clé de tous les noms, SearchFields) sur
 *     `stations`, `lines`, `accidents` et `materials`, tenues à jour par PostgreSQL à chaque écriture : la clé
 *     n'est plus recalculée à la lecture (sans elles, revérifier 2 000 gares candidates prenait 100 ms) ;
 *   - index GIN trigrammes sur ces colonnes, pour les LIKE « début de mot » et la ressemblance `<%`.
 *
 * Noms qualifiés par `public.` : PostgreSQL 17 et suivants calculent une colonne ou un index avec le chemin de
 * recherche `pg_catalog, pg_temp`. Les colonnes réécrivent des tables de 5 à 20 Mo (une seconde) sous verrou
 * exclusif : `lock_timeout` de 10 s, la migration échoue plutôt que de bloquer une lecture longue. Index
 * CONCURRENTLY, hors transaction. Aucune donnée existante n'est modifiée.
 *
 * Base de développement : `bin/php artisan migrate --path=database/migrations/2026_12_12_000000_create_search_key_functions.php`.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement("CREATE OR REPLACE FUNCTION vff_unaccent(text) RETURNS text
            LANGUAGE sql IMMUTABLE PARALLEL SAFE STRICT
            AS \$\$ SELECT public.unaccent('public.unaccent'::regdictionary, \$1) \$\$");
        DB::statement('CREATE OR REPLACE FUNCTION vff_search_key(t text) RETURNS text
            LANGUAGE plpgsql IMMUTABLE PARALLEL SAFE
            AS $$ BEGIN RETURN '.SearchText::sqlKeyFormula('t').'; END $$');

        DB::statement("SET lock_timeout = '10s'");
        foreach ($this->columns() as $table => [$name, $all]) {
            DB::statement("ALTER TABLE {$table}
                ADD COLUMN IF NOT EXISTS search_name text GENERATED ALWAYS AS (public.vff_search_key({$name})) STORED,
                ADD COLUMN IF NOT EXISTS search_all text GENERATED ALWAYS AS (public.vff_search_key({$all})) STORED");
        }
        DB::statement('RESET lock_timeout');
        foreach (array_keys($this->columns()) as $table) {
            foreach (['search_name', 'search_all'] as $column) {
                DB::statement("CREATE INDEX CONCURRENTLY IF NOT EXISTS {$table}_{$column}_trgm ON {$table} USING gin ({$column} gin_trgm_ops)");
            }
        }
        SearchText::forgetSqlState();
    }

    public function down(): void
    {
        foreach (array_keys($this->columns()) as $table) {
            DB::statement("ALTER TABLE {$table} DROP COLUMN IF EXISTS search_name, DROP COLUMN IF EXISTS search_all");
        }
        DB::statement('DROP FUNCTION IF EXISTS vff_search_key(text)');
        DB::statement('DROP FUNCTION IF EXISTS vff_unaccent(text)');
        SearchText::forgetSqlState();
    }

    /** @return array<string, array{0: string, 1: string}> table => [nom, tous les noms] */
    private function columns(): array
    {
        return [
            'stations' => ['name', SearchFields::stationAll()],
            'lines' => ['name', SearchFields::lineAll()],
            'accidents' => ["name->>'fr'", SearchFields::accidentAll('')],
            'materials' => ['name', SearchFields::materialAll('')],
        ];
    }
};
