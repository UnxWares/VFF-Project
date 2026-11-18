<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Voies vertes / pistes cyclables OSM proches des voies ferrées et ways
 * ferroviaires OSM (vff:import-osm-greenways), matière première de
 * vff:detect-present-states.
 *
 * Pourquoi une table à part : source_traces (source='osm') ne contient que les
 * ways FERROVIAIRES. Or beaucoup de voies vertes posées sur une ancienne
 * plateforme ne portent aucun tag ferroviaire (simple highway=cycleway) : il
 * faut les comparer géométriquement à l'axe de l'ancienne voie.
 *
 *   kind   : greenway (highway=cycleway, ou chemin bicycle=designated) |
 *            railway (way ferroviaire : rails en place ou cycle de vie)
 *   routes : relations route=bicycle|mtb dont le way est membre
 *            [{id, name, ref, network, start_date}]
 *   start_year : année du tag start_date / opening_date du way (pour un way
 *                ferroviaire, c'est souvent l'ouverture de la LIGNE)
 * Données © contributeurs OpenStreetMap, ODbL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('present_osm_ways', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary()->comment('Identifiant du way OSM');
            $table->string('region', 10);
            $table->string('kind', 20);
            $table->string('highway', 30)->nullable();
            $table->text('name')->nullable();
            $table->jsonb('tags');
            $table->jsonb('routes')->nullable();
            $table->smallInteger('start_year')->nullable();
            $table->integer('length_m');
            $table->timestamp('seen_at');
            $table->timestamps();
            $table->index('region');
        });
        DB::statement('ALTER TABLE present_osm_ways ADD COLUMN geom geometry(LineString, 4326) NOT NULL');
        DB::statement('CREATE INDEX present_osm_ways_geom_gist ON present_osm_ways USING GIST (geom)');
    }

    public function down(): void
    {
        Schema::dropIfExists('present_osm_ways');
    }
};
