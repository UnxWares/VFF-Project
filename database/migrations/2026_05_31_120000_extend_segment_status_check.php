<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Étend les statuts de segment : 'service_track' (voie de service / portuaire,
 * roule encore mais hors ligne RFN exploitée - ex « Transférée en voie de
 * service », Vport) et 'construction' (ligne en travaux). Met à jour la
 * contrainte CHECK ajoutée précédemment.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE line_segments DROP CONSTRAINT IF EXISTS line_segments_status_check');
        DB::statement(<<<'SQL'
            ALTER TABLE line_segments
            ADD CONSTRAINT line_segments_status_check
            CHECK (status IN ('in_service','single_track','abandoned','dismantled','razed','service_track','construction'))
        SQL);
    }

    public function down(): void
    {
        // Rapatrie les nouveaux statuts vers des valeurs de l'ancien CHECK.
        DB::statement("UPDATE line_segments SET status='abandoned' WHERE status='service_track'");
        DB::statement("UPDATE line_segments SET status='in_service' WHERE status='construction'");
        DB::statement('ALTER TABLE line_segments DROP CONSTRAINT IF EXISTS line_segments_status_check');
        DB::statement(<<<'SQL'
            ALTER TABLE line_segments
            ADD CONSTRAINT line_segments_status_check
            CHECK (status IN ('in_service','single_track','abandoned','dismantled','razed'))
        SQL);
    }
};
