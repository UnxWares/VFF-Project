<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Desserte d'un jour ouvrable type (un mardi, semaine sans jour férié) des gares et des lignes, mesurée dans les
 * horaires ouverts (GTFS SNCF et Transilien) par vff:import-station-timetables : trains par type et par sens, premières
 * et dernières heures, destinations directes ; pour les lignes, le nombre de trains compté une fois.
 *
 * Tables additives, remplacées entièrement à chaque calcul ; station_id et line_id sans clé étrangère (tables chaudes :
 * pas de verrou sur stations ni lines pendant la migration).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('station_timetables', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('station_id')->unique();
            $table->date('service_date')->comment('le mardi type lu dans les horaires');
            $table->unsignedSmallInteger('trains')->comment('trains qui s\'arrêtent dans la gare ce jour-là, comptés une fois');
            $table->jsonb('by_type')->comment('ter, intercites, tgv, transilien, other : trains par type');
            $table->string('first_time', 5)->nullable()->comment('première heure (HH:MM), après minuit comprise dans la journée de service');
            $table->string('last_time', 5)->nullable();
            $table->jsonb('directions')->comment('par sens : cap, trains, types, premières et dernières heures, destinations');
            $table->jsonb('destinations')->comment('principales destinations directes : name, slug, trains');
            $table->jsonb('origins')->comment('provenances des trains qui terminent ici (si aucun ne repart)');
            $table->jsonb('feeds')->comment('flux lus : feed, version, date, valid_to, name, licence, page');
            $table->string('source', 16)->default('sncf')->comment('fournisseur de droits (database/data/source_rights.php)');
            $table->timestamp('computed_at');
            $table->timestamps();
        });

        Schema::create('line_timetables', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('line_id')->unique();
            $table->date('service_date');
            $table->unsignedSmallInteger('trains')->comment('trains qui parcourent au moins deux gares consécutives de la ligne, comptés une fois');
            $table->jsonb('by_type');
            $table->string('source', 16)->default('sncf');
            $table->timestamp('computed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('line_timetables');
        Schema::dropIfExists('station_timetables');
    }
};
