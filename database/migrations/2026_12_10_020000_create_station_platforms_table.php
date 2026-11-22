<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quais des gares de voyageurs, d'après le jeu SNCF Open Data « Liste des quais » (SNCF Réseau, ODbL),
 * importé par vff:import-sncf-platforms. Une ligne par enregistrement du jeu : un quai central qui borde
 * deux voies y figure deux fois (une fois par voie), le regroupement en quais se fait à la lecture
 * (App\Services\Sncf\StationPlatforms). `station_id` est nul quand l'enregistrement n'a pu être rattaché
 * à aucune gare de VFF (gardé pour l'audit). État actuel du réseau seulement, sans dates.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('station_platforms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('station_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('sncf_station_name', 255)->nullable()->comment('Nom de la gare dans le jeu SNCF (lib_gare)');
            $table->string('code_ligne', 16)->comment('Code SNCF Réseau de la ligne qui porte la voie du quai');
            $table->string('code_voie', 40)->nullable()->comment('Code SNCF de la voie bordée (code_voie)');
            $table->smallInteger('rg_troncon')->nullable();
            $table->string('label', 100)->comment('Libellé SNCF du quai (« Quai 1 », « Quai A »)');
            $table->integer('length_m')->nullable()->comment('Longueur du quai en mètres (champ longueur)');
            $table->integer('pk_start_m')->nullable()->comment('PK de début du quai, en mètres');
            $table->integer('pk_end_m')->nullable()->comment('PK de fin du quai, en mètres');
            $table->double('lat')->nullable()->comment('Milieu du quai (WGS 84)');
            $table->double('lon')->nullable();
            $table->string('match_method', 10)->nullable()->comment('Rattachement à la gare VFF : uic | name | null');
            $table->string('source', 30)->default('sncf');
            $table->date('dataset_modified')->nullable()->comment('Date de mise à jour du jeu (métadonnées SNCF)');
            $table->timestamp('fetched_at');
            $table->timestamps();

            $table->index('station_id');
            $table->index('code_ligne');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('station_platforms');
    }
};
