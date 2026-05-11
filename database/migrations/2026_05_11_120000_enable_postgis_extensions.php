<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // PostGIS pour les géométries (LINESTRING, POINT, ...)
        DB::statement('CREATE EXTENSION IF NOT EXISTS postgis');

        // Recherche fuzzy / sans accents pour les noms de lignes et gares
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement('CREATE EXTENSION IF NOT EXISTS unaccent');

        // Apache AGE (graphe) — activé en Phase 8 quand on en aura besoin
        // DB::statement('CREATE EXTENSION IF NOT EXISTS age');
    }

    public function down(): void
    {
        // On ne drop pas les extensions au down() : elles peuvent être
        // partagées par d'autres applications sur la même base.
    }
};
