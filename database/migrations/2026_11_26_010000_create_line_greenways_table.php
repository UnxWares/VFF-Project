<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Voies vertes OpenStreetMap installées sur l'emprise d'une ligne disparue ou fermée de VFF
 * (calculées par `vff:link-greenways`, App\Services\Greenways\GreenwayLinker).
 *
 * Une ligne par couple (ligne, way OSM) : un même way qui reprend l'emprise de deux lignes
 * superposées compte pour chacune. Le rattachement est mesuré, pas déclaré : `shared_length_m` est la
 * longueur du way qui suit l'axe de la ligne (à 8 m près) là où aucune voie n'est plus en service ni
 * en place, `shared_percent` sa part de la longueur du way. Voir le service pour les seuils.
 *
 *   osm_way_id        : identifiant du way OSM (https://www.openstreetmap.org/way/<id>)
 *   osm_relation_id   : relation route=bicycle|mtb dont le way est membre (itinéraire nommé), si elle existe
 *   relation_name     : nom de cette relation (« La Voie verte du Val de Sarthe »)
 *   shared_length_m   : longueur commune avec l'ancienne emprise, en mètres
 *   way_length_m      : longueur du way entier
 *   opened_year       : année de start_date / opening_date du way ou de sa relation, si OSM la donne
 *   osm_seen_at       : date de la lecture d'OSM (present_osm_ways.seen_at)
 *   geom              : partie du way qui suit l'ancienne emprise (dessinée sur la carte)
 * Données © contributeurs OpenStreetMap, ODbL (source = 'osm', cf. database/data/source_rights.php).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('line_greenways', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('line_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('osm_way_id');
            $table->unsignedBigInteger('osm_relation_id')->nullable();
            $table->text('name')->nullable();
            $table->text('relation_name')->nullable();
            $table->string('highway', 30)->nullable();
            $table->unsignedInteger('shared_length_m');
            $table->unsignedInteger('way_length_m');
            $table->unsignedSmallInteger('shared_percent');
            $table->smallInteger('opened_year')->nullable();
            $table->string('source', 20)->default('osm');
            $table->timestamp('osm_seen_at');
            $table->timestamps();
            $table->unique(['line_id', 'osm_way_id']);
            $table->index('osm_way_id');
        });
        DB::statement('ALTER TABLE line_greenways ADD COLUMN geom geometry(MultiLineString, 4326) NOT NULL');
        DB::statement('CREATE INDEX line_greenways_geom_gist ON line_greenways USING GIST (geom)');
    }

    public function down(): void
    {
        Schema::dropIfExists('line_greenways');
    }
};
