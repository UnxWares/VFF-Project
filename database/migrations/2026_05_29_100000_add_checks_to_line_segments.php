<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sécurise l'intégrité de line_segments (audit 2026-05) :
 *   - electrified NOT NULL DEFAULT false (2 nulls résiduels backfillés)
 *   - CHECK sur status (enum SegmentStatus) et traffic_type (enum TrafficType)
 *
 * Sans ces CHECK, une valeur hors-enum (ex le typo 'passengers') passait en
 * silence et faisait planter les casts Eloquent au runtime. Les CHECK alignent
 * la base sur les enums PHP.
 *
 * NOTE : si un futur statut (service_track, construction) est ajouté à l'enum
 * SegmentStatus, mettre à jour la contrainte line_segments_status_check.
 */
return new class extends Migration
{
    public function up(): void
    {
        // electrified : backfill des nulls puis NOT NULL DEFAULT false
        DB::statement('UPDATE line_segments SET electrified = false WHERE electrified IS NULL');

        Schema::table('line_segments', function (Blueprint $table): void {
            $table->boolean('electrified')->default(false)->nullable(false)->change();
        });

        // CHECK alignés sur les enums (valeurs en base déjà toutes conformes)
        DB::statement(<<<'SQL'
            ALTER TABLE line_segments
            ADD CONSTRAINT line_segments_status_check
            CHECK (status IN ('in_service','single_track','abandoned','dismantled','razed'))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE line_segments
            ADD CONSTRAINT line_segments_traffic_type_check
            CHECK (traffic_type IN ('passenger','freight','mixed','unknown'))
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE line_segments DROP CONSTRAINT IF EXISTS line_segments_status_check');
        DB::statement('ALTER TABLE line_segments DROP CONSTRAINT IF EXISTS line_segments_traffic_type_check');

        Schema::table('line_segments', function (Blueprint $table): void {
            $table->boolean('electrified')->nullable()->change();
        });
    }
};
