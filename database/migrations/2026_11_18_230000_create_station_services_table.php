<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Desserte voyageurs des gares AUJOURD'HUI, mesurée dans les horaires ouverts (GTFS)
 * par vff:import-station-service : classe (App\Enums\StationServiceClass), motif
 * (App\Enums\ServicePattern), desserte retenue et preuves par flux. Table entière
 * recalculée à chaque passage ; elle ne modifie pas le statut des gares.
 *
 * station_id sans clé étrangère : la table est remplacée à chaque calcul, et une clé
 * vers stations (table chaude) poserait un verrou sur stations pendant la migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('station_services', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('station_id')->unique();
            $table->string('service_class', 12)->index()->comment('regular, periodic, tourist, velorail, bus_only, none, unknown');
            $table->string('pattern', 16)->nullable()->comment('App\Enums\ServicePattern');
            $table->unsignedInteger('calls')->nullable()->comment('arrêts de la desserte retenue sur la fenêtre');
            $table->unsignedSmallInteger('served_days')->nullable();
            $table->date('window_start')->nullable()->comment('fenêtre lue du flux de la desserte retenue');
            $table->date('window_end')->nullable();
            $table->date('first_service')->nullable();
            $table->date('last_service')->nullable();
            $table->jsonb('evidence')->comment('un élément par arrêt GTFS rapproché : flux, arrêt, méthode, desserte par mode');
            $table->jsonb('indicators')->comment('indices sans horaires : lignes touristiques, vélorails, OSM, fréquentation');
            $table->text('note')->nullable();
            $table->timestamp('computed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('station_services');
    }
};
