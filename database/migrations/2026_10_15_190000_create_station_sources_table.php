<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gares et haltes TELLES QUE DÉCRITES PAR CHAQUE SOURCE GÉOLOCALISÉE, avant
 * fusion dans le référentiel `stations`.
 *
 * Une ligne = un objet d'une source (gare SNCF par code UIC, nœud/way OSM,
 * objet OpenHistoricalMap, élément Wikidata). Le dédoublonnage
 * (vff:consolidate-stations) renseigne `station_id` + la méthode et la distance
 * de rapprochement ; la provenance de chaque attribut de `stations` pointe vers
 * ces lignes. Rien n'est jamais fusionné « à la main » ici : on peut rejouer la
 * consolidation à tout moment.
 *
 * Droits : SNCF Licence Ouverte 2.0 · OSM ODbL · OHM CC0 · Wikidata CC0.
 *
 * Différence avec `source_stations` (autre table) : celle-ci décrit des gares
 * PAR LIGNE avec leur PK, souvent sans coordonnées (lignes-oubliees,
 * Wikipédia). Ici, chaque objet a une position.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('station_sources', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 20)->comment('sncf | osm | ohm | wikidata');
            $table->string('source_ref', 64)->comment('UIC (sncf), n123/w123/r123 (osm, ohm), Q-id (wikidata)');
            $table->foreignId('station_id')->nullable()->constrained('stations')->nullOnDelete();
            $table->string('name')->nullable();
            $table->string('name_norm')->nullable()->comment('minuscules, sans accents ni préfixe « gare de », St → saint');
            $table->string('kind', 20)->nullable()->comment('gare | halte');
            $table->string('lifecycle', 20)->default('unknown')
                ->comment('active | disused | abandoned | razed | historic | unknown (état dans la source)');
            $table->string('uic_code', 10)->nullable();
            $table->string('wikidata_id', 20)->nullable();
            $table->date('opened_on')->nullable();
            $table->string('opened_precision', 5)->nullable()->comment('day | month | year');
            $table->date('closed_on')->nullable();
            $table->string('closed_precision', 5)->nullable();
            $table->string('closed_scope', 12)->nullable()->comment('all | passenger (fermeture totale ou aux voyageurs)');
            $table->boolean('passenger')->nullable()->comment('Service voyageurs actuel (SNCF)');
            $table->boolean('freight')->nullable()->comment('Service fret actuel (SNCF)');
            $table->jsonb('events')->nullable()->comment('Dates multiples [{type, date, precision}] (ex. Wikidata : fermée puis rouverte)');
            $table->jsonb('properties')->nullable()->comment('Attributs bruts (tags OSM/OHM, lignes et PK SNCF, P31 Wikidata…)');
            $table->string('match_method', 24)->nullable()->comment('seed | uic | wikidata | osm_ref | name_near | near');
            $table->integer('match_distance_m')->nullable();
            $table->timestamp('seen_at');
            $table->timestamps();

            $table->unique(['source', 'source_ref']);
            $table->index('station_id');
            $table->index('uic_code');
            $table->index('wikidata_id');
            $table->index('name_norm');
        });
        DB::statement('ALTER TABLE station_sources ADD COLUMN geom geometry(Point, 4326) NOT NULL');
        DB::statement('CREATE INDEX station_sources_geom_gist ON station_sources USING GIST (geom)');
    }

    public function down(): void
    {
        Schema::dropIfExists('station_sources');
    }
};
