<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Index unique de la vue matérialisée `line_signalling_map` : PostgreSQL n'accepte
 * `REFRESH MATERIALIZED VIEW CONCURRENTLY` (recalcul sans bloquer les lectures des tuiles, environ
 * 9 s) qu'avec un index unique couvrant des colonnes seules. Un morceau de tracé est identifié par
 * sa tranche (`range_id`, ligne de `line_signalling`) et son tronçon de forme SNCF Réseau
 * (`troncon_id`). La vue elle-même est créée par 2026_11_20_020000, qu'on ne modifie pas.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS line_signalling_map_piece_unique ON line_signalling_map (range_id, troncon_id)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS line_signalling_map_piece_unique');
    }
};
