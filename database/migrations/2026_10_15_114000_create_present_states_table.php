<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * État ACTUEL observé de l'emprise des voies (preuve de terrain), par portion.
 *
 * Sert à trancher ce que les libellés administratifs SNCF (« Fermée »,
 * « Déclassée vendue ») ne disent pas : la voie est-elle encore EN PLACE ou a-t-elle
 * DISPARU (déposée, devenue voie verte, route, bâtie) ? Le moteur décennal en
 * déduit l'affichage : une voie disparue ne se voit « déposée » que sur la carte
 * de la décennie qui suit sa dépose, puis n'apparaît plus.
 *
 *   state : track_in_place | dismantled | greenway | road | built_over
 *   source : osm | bdtopo | lignes-oubliees | sncf | contrib…
 *   observed_at : date de l'observation (ou de la source)
 *   since_year : année du changement d'état si connue (ex. ouverture de la voie verte)
 * Une voie verte construite À CÔTÉ d'une voie laissée en place donne
 * track_in_place sur la voie (et rien sur la voie verte elle-même).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('present_states', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 40);
            $table->string('source_ref', 128);
            $table->string('state', 20);
            $table->date('observed_at')->nullable();
            $table->smallInteger('since_year')->nullable();
            $table->jsonb('properties')->nullable();
            $table->timestamps();
            $table->unique(['source', 'source_ref']);
            $table->index('state');
        });
        DB::statement('ALTER TABLE present_states ADD COLUMN geom geometry(MultiLineString, 4326) NOT NULL');
        DB::statement('CREATE INDEX present_states_geom_gist ON present_states USING GIST (geom)');
        DB::statement("ALTER TABLE present_states ADD CONSTRAINT present_states_state_check CHECK (state IN ('track_in_place', 'dismantled', 'greenway', 'road', 'built_over'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('present_states');
    }
};
