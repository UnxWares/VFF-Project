<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tracés dédoublés DANS une même ligne (vff:build-line-references, étape
 * « un seul axe », App\Services\SingleAxis) : portions du tracé de référence
 * qui longeaient une autre portion de la même ligne à moins de 30 m
 * (double voie ou 4 voies saisies sur deux axes, voies de gare, voies OSM
 * séparées, sources qui se recouvrent après recalage) :
 *
 *   - action = removed      : portion retirée du tracé de référence ;
 *   - action = kept_distinct : portion gardée car c'est un tracé réellement
 *     distinct (ancien tracé daté, voie déposée le long de la voie actuelle).
 *
 * Provenance : source / matched_source / trace_ids de la portion concernée,
 * kept_* de la portion conservée qu'elle longeait. geom = la portion, sur sa
 * propre géométrie. Reconstruit ligne par ligne (idempotent).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('line_reference_duplicates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('line_id')->constrained()->cascadeOnDelete();
            $table->string('action', 16)->comment('removed | kept_distinct');
            $table->string('reason', 40)->comment('cause : bdtopo/bdtopo, osm/osm, entre sources, ancien tracé daté…');
            $table->string('source', 40);
            $table->string('matched_source', 40)->nullable();
            $table->jsonb('trace_ids')->nullable()->comment('tracés source_traces sous la portion');
            $table->string('kept_source', 40)->nullable();
            $table->string('kept_matched_source', 40)->nullable();
            $table->jsonb('kept_trace_ids')->nullable();
            $table->integer('length_m');
            $table->float('gap_median_m')->comment('écart médian (m) à la portion conservée');
            $table->timestamps();
            $table->index(['line_id']);
            $table->index(['action', 'reason']);
        });
        DB::statement('ALTER TABLE line_reference_duplicates ADD COLUMN geom geometry(LineString, 4326) NOT NULL');
        DB::statement('CREATE INDEX line_reference_duplicates_geom_gist ON line_reference_duplicates USING GIST (geom)');
    }

    public function down(): void
    {
        Schema::dropIfExists('line_reference_duplicates');
    }
};
