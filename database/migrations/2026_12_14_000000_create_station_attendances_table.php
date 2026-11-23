<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fréquentation annuelle des gares (`vff:import-sncf-attendance`), d'après les jeux « Fréquentation en gares » de
 * SNCF Gares & Connexions (SNCF Open Data, ODbL) : `frequentation-gares-archive` (2015 à 2024) et
 * `frequentation-gares` (2025 et les années publiées ensuite).
 *
 * Une ligne par gare et par année. `passengers` : voyageurs (colonnes `total_voyageurs_AAAA` du jeu) ;
 * `footfall` : voyageurs et non-voyageurs réunis (`total_voyageurs_non_voyageurs_AAAA`), les non-voyageurs étant
 * ceux qui traversent ou fréquentent la gare sans prendre le train (estimation par enquêtes). `series` sépare les
 * deux méthodes de calcul : SNCF écrit que les chiffres de 2025 (autres sources, autre méthode) ne sont pas
 * directement comparables à ceux de 2015 à 2024, la courbe de la fiche marque la rupture. Le rattachement à la
 * gare de VFF se fait par code UIC (`match_method`) ; une gare du jeu sans gare de VFF n'est pas écrite (le
 * rapport de l'import la liste). Additive : aucune table existante n'est touchée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('station_attendances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('station_id')->constrained()->cascadeOnDelete();
            $table->smallInteger('year');
            $table->bigInteger('passengers')->nullable();
            $table->bigInteger('footfall')->nullable();
            $table->string('series', 12);
            $table->string('uic_code', 10);
            $table->string('source_name', 160)->nullable();
            $table->string('match_method', 20);
            $table->string('source', 12)->default('sncf');
            $table->string('dataset', 60);
            $table->date('dataset_modified')->nullable();
            $table->timestamps();
            $table->unique(['station_id', 'year']);
            $table->index('year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('station_attendances');
    }
};
