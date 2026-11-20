<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chronologie d'une gare : ouvertures, fermetures (aux voyageurs ou totales),
 * réouvertures, démolition. Une gare fermée puis rouverte a donc plusieurs
 * événements ; `station_eras` (par décennie) est DÉRIVÉ de cette table
 * (vff:build-station-eras), jamais saisi à la main.
 *
 * Mêmes conventions que `line_events` : vraie date jj/mm/aaaa + précision
 * (day | month | year), provenance (source + référence + URL).
 * `is_inferred` = date déduite (ex. ouverture du tracé OHM daté qui dessert la
 * gare) et non lue dans une source qui parle de la gare elle-même.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('station_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('station_id')->constrained('stations')->cascadeOnDelete();
            $table->string('type', 24)->comment('opening | closure_passenger | closure | reopening | demolition');
            $table->date('event_date');
            $table->string('date_precision', 5)->default('day')->comment('day | month | year');
            $table->string('source', 30)->comment('wikidata | ohm | osm | sncf | inferred:ohm_trace | inferred:line_event…');
            $table->string('source_ref', 160)->nullable();
            $table->string('source_url', 500)->nullable();
            $table->boolean('is_inferred')->default(false);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['station_id', 'type', 'event_date', 'source']);
            $table->index(['station_id', 'event_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('station_events');
    }
};
