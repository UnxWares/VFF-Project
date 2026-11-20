<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Divergences entre sources le long du tracé de référence d'une ligne
 * (vff:build-line-references, croisement des sources) :
 *
 *   - kind = precise     : deux sources précises du même axe (BD TOPO / OSM /
 *     contribution) s'écartent de plus de 8 m ;
 *   - kind = line_source : une source rattachée à la ligne (archeo, OHM, SNCF…)
 *     s'écarte de la géométrie retenue puis la rejoint (déviation, ancien
 *     tracé ou erreur de rattachement).
 *
 * Reconstruit ligne par ligne (idempotent). geom = la portion concernée, sur
 * la géométrie retenue. score = écart médian × longueur (m·km) pour trier.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('line_reference_divergences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('line_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 12)->comment('precise | line_source');
            $table->string('retained_source', 40)->comment('Famille de la géométrie retenue');
            $table->string('other_source', 40)->comment('Famille de la source divergente');
            $table->integer('length_m');
            $table->float('gap_median_m');
            $table->float('gap_max_m');
            $table->float('score')->comment('écart médian × longueur (m·km)');
            $table->jsonb('retained_trace_ids')->nullable();
            $table->jsonb('other_trace_ids')->nullable();
            $table->timestamps();
            $table->index(['line_id']);
            $table->index(['score']);
        });
        DB::statement('ALTER TABLE line_reference_divergences ADD COLUMN geom geometry(LineString, 4326) NOT NULL');
        DB::statement('CREATE INDEX line_reference_divergences_geom_gist ON line_reference_divergences USING GIST (geom)');
    }

    public function down(): void
    {
        Schema::dropIfExists('line_reference_divergences');
    }
};
