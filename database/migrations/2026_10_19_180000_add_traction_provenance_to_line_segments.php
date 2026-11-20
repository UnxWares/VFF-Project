<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Électrification et écartement PAR PORTION et PAR ÈRE (vff:enrich-era-segments) :
 * provenance des valeurs posées sur line_segments, pour que la carte distingue
 * « non électrifiée » (connu) de « date d'électrification inconnue », et une voie
 * étroite d'écartement exact inconnu (BD TOPO « Etroite ») d'un écartement inconnu.
 *
 *   electrification_source : curated | event | event_neighbour | event_line | event_unlocated
 *                            | sncf | osm | bdtopo | lgv | status | rule | none | unknown
 *                            (unknown = électrifiée aujourd'hui, date inconnue : l'état à l'ère est incertain ;
 *                             none = aucune source, non électrifiée par défaut ; NULL = pas encore enrichi)
 *   electrified_year       : année de l'électrification en vigueur à l'ère (si connue)
 *   gauge_class            : standard | metre | narrow | broad (NULL = inconnu)
 *   gauge_source           : osm | bdtopo | sncf | ohm | osm_hist | archeo | curated | event | wikidata | line | network
 */
return new class extends Migration
{
    public function up(): void
    {
        // line_segments est lue en continu (tuiles, moteur) : ne pas bloquer la file
        // des verrous derrière une longue requête, échouer vite et relancer.
        DB::statement("SET lock_timeout = '5s'");
        Schema::table('line_segments', function (Blueprint $table): void {
            $table->string('electrification_source', 24)->nullable();
            $table->smallInteger('electrified_year')->nullable();
            $table->string('gauge_class', 10)->nullable();
            $table->string('gauge_source', 24)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('line_segments', function (Blueprint $table): void {
            $table->dropColumn(['electrification_source', 'electrified_year', 'gauge_class', 'gauge_source']);
        });
    }
};
