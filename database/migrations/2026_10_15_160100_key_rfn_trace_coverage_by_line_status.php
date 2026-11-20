<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * rfn_trace_coverage : clé (code_ligne, statut, source) au lieu d'idgaia.
 *
 * idgaia est vide dans tout le jeu « formes-des-lignes-du-rfn » importé, et
 * (code, PK début, PK fin) n'est pas unique (géométries découpées). La portion
 * de ligne de même statut SNCF est l'unité utile pour l'encyclopédie
 * (« ligne X : partie exploitée couverte à 99 %, partie neutralisée à 80 % »).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rfn_trace_coverage', function (Blueprint $table): void {
            $table->dropUnique(['idgaia', 'source']);
            $table->dropColumn('idgaia');
            $table->unique(['code_ligne', 'statut', 'source']);
        });
    }

    public function down(): void
    {
        // Table dérivée (vff:audit-current-network la recalcule) dont les lignes
        // n'ont plus d'idgaia : on la vide pour rétablir la colonne NOT NULL
        // d'origine (un ajout nullable laissait un schéma différent).
        DB::table('rfn_trace_coverage')->delete();
        Schema::table('rfn_trace_coverage', function (Blueprint $table): void {
            $table->dropUnique(['code_ligne', 'statut', 'source']);
            $table->string('idgaia', 50)->comment('sncf_line_geometries.idgaia');
            $table->unique(['idgaia', 'source']);
        });
    }
};
