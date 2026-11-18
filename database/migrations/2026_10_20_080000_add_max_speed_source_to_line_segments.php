<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Provenance de la vitesse maximale d'un tronçon (vff:import-sncf-vmax) :
 *   sncf : jeu SNCF Réseau « vitesse-maximale-nominale-sur-ligne », même code ligne ;
 *   osm  : dernier recours, tag maxspeed de la voie OSM (lignes absentes du jeu SNCF :
 *          Corse, RATP, codes hors RFN) ;
 *   NULL : pas de vitesse, ou vitesse posée avant cette colonne.
 */
return new class extends Migration
{
    public function up(): void
    {
        // line_segments est lue en continu (tuiles, moteur) : échouer vite et relancer.
        DB::statement("SET lock_timeout = '5s'");
        Schema::table('line_segments', function (Blueprint $table): void {
            $table->string('max_speed_source', 8)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('line_segments', function (Blueprint $table): void {
            $table->dropColumn('max_speed_source');
        });
    }
};
