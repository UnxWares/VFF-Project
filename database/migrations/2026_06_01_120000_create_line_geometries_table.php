<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `line_geometries` : géométrie de ligne CONTINUE et fusionnée par ère.
 *
 * Table DÉRIVÉE et ADDITIVE - `line_segments` reste la source de vérité
 * intacte. Pour chaque ligne ayant des segments actifs (in_service /
 * single_track) à une ère, on stocke UNE géométrie fusionnée
 * (`ST_LineMerge(ST_Collect(...))`) qui recoud les micro-trous, plus la
 * longueur géodésique correspondante (route-km).
 *
 * Usages :
 *   - PK par projection : ST_LineLocatePoint(geom, point) × length_m
 *     (cf. commande `vff:line-pk-of-point`)
 *   - fiche ligne : tracé unique sans avoir à recoller les segments
 *   - rattachement topologique gare → ligne pour le graphe
 *
 * Reconstruit par `php artisan vff:build-line-geometry --era={year}`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('line_geometries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('line_id')->constrained()->cascadeOnDelete();
            $table->foreignId('era_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('length_m')->comment('Longueur géodésique de la géométrie fusionnée (m, route-km).');
            $table->integer('segment_count')->default(0)->comment('Nb de segments actifs fusionnés.');
            $table->integer('part_count')->default(1)->comment('Nb de sous-LineString après fusion (1 = ligne continue).');
            $table->timestamps();

            $table->unique(['line_id', 'era_id']);
            $table->index(['era_id']);
        });

        // Géométrie fusionnée. MultiLineString pour tolérer les lignes non
        // continues (trous OSM, antennes) ; une ligne propre tient dans une
        // seule sous-LineString.
        DB::statement('ALTER TABLE line_geometries ADD COLUMN geom geometry(MultiLineString, 4326) NOT NULL');
        DB::statement('CREATE INDEX line_geometries_geom_gist ON line_geometries USING GIST (geom)');
    }

    public function down(): void
    {
        Schema::dropIfExists('line_geometries');
    }
};
