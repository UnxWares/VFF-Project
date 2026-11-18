<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tracé de référence de chaque ligne, toutes époques confondues, fusionné
 * depuis `source_traces` par ordre de précision (vff:build-line-references).
 *
 *   - line_reference_parts : les morceaux retenus, AVEC leur source (provenance
 *     affichable et réutilisée par le moteur par décennie) ;
 *   - line_reference_geometries : la géométrie fusionnée par ligne (utilisée
 *     par le résolveur de sections et les calculs de PK).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('line_reference_parts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('line_id')->constrained()->cascadeOnDelete();
            $table->string('source', 40);
            $table->jsonb('source_trace_ids')->comment('source_traces ayant fourni ce morceau');
            $table->integer('length_m');
            $table->timestamps();
            $table->index(['line_id', 'source']);
        });
        DB::statement('ALTER TABLE line_reference_parts ADD COLUMN geom geometry(MultiLineString, 4326) NOT NULL');
        DB::statement('CREATE INDEX line_reference_parts_geom_gist ON line_reference_parts USING GIST (geom)');

        Schema::create('line_reference_geometries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('line_id')->unique()->constrained()->cascadeOnDelete();
            $table->integer('length_m');
            $table->jsonb('sources')->comment('Longueur retenue par source, en mètres');
            $table->timestamps();
        });
        DB::statement('ALTER TABLE line_reference_geometries ADD COLUMN geom geometry(MultiLineString, 4326) NOT NULL');
        DB::statement('CREATE INDEX line_reference_geometries_geom_gist ON line_reference_geometries USING GIST (geom)');
    }

    public function down(): void
    {
        Schema::dropIfExists('line_reference_geometries');
        Schema::dropIfExists('line_reference_parts');
    }
};
