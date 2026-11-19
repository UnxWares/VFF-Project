<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dates des événements de ligne qui donnent la période de desserte d'une gare (`station_lines.first_year` /
 * `last_year`) : ouverture la plus ancienne et fin de desserte retenues par `vff:attach-stations`, avec leur
 * précision (day, month, year, celle de `line_events.date_precision`). Null quand l'année vient d'un tracé daté
 * ou des dates de la gare. `vff:build-station-eras` (reconcileServiceYears) s'en sert pour reconnaître une
 * ligne fermée avant l'ouverture de la gare la même année (La Fouillouse sur 4014 : section fermée le
 * 26/09/1857, gare ouverte le 24/11/1857), que les seules années ne distinguaient pas.
 * Remplies au prochain `vff:attach-stations --years-only`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('station_lines', function (Blueprint $table): void {
            $table->date('first_event_date')->nullable()->after('last_year');
            $table->string('first_event_precision', 10)->nullable()->after('first_event_date');
            $table->date('last_event_date')->nullable()->after('first_event_precision');
            $table->string('last_event_precision', 10)->nullable()->after('last_event_date');
        });
    }

    public function down(): void
    {
        Schema::table('station_lines', function (Blueprint $table): void {
            $table->dropColumn(['first_event_date', 'first_event_precision', 'last_event_date', 'last_event_precision']);
        });
    }
};
