<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Contours simplifiés (100 m) des communes actuelles - Etalab
 * « contours-administratifs » (IGN Admin Express, Licence Ouverte 2.0).
 *
 * Sert au géocodage inverse des gares (commune + département par
 * ST_Contains, exact aux limites contrairement au point le plus proche) et à
 * écarter les objets hors de France des sources à emprise rectangulaire (OHM).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commune_contours', function (Blueprint $table): void {
            $table->id();
            $table->string('code_insee', 5)->unique();
            $table->string('nom');
            $table->string('departement', 3)->nullable()->index();
            $table->timestamps();
        });
        DB::statement('ALTER TABLE commune_contours ADD COLUMN geom geometry(MultiPolygon, 4326) NOT NULL');
        DB::statement('CREATE INDEX commune_contours_geom_gist ON commune_contours USING GIST (geom)');
    }

    public function down(): void
    {
        Schema::dropIfExists('commune_contours');
    }
};
