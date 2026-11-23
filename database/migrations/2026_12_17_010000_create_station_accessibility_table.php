<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Accessibilité et services des gares d'aujourd'hui, d'après les jeux ouverts de SNCF Gares & Connexions (ODbL) lus par
 * vff:import-station-accessibility : accès pour les personnes à mobilité réduite (NeTEx « Accessibilité des gares »), ascenseurs
 * et leur état publié, équipements d'accessibilité (boucle magnétique, bandes d'éveil, toilettes…), assistance en gare, points
 * de vente et stationnement vélo sécurisé.
 *
 * Table additive, remplacée entièrement à chaque import ; station_id sans clé étrangère (table chaude : pas de verrou sur
 * stations pendant la migration).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('station_accessibility', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('station_id')->unique();
            $table->string('uic_code', 8)->comment('code UIC de la gare dans les jeux de SNCF');
            $table->uuid('sncf_station_id')->comment('identifiant de la gare dans le référentiel « Gares de voyageurs »');
            $table->string('mobility', 8)->nullable()->comment('none ou partial : accès des personnes à mobilité réduite (NeTEx), null si le jeu ne dit rien');
            $table->boolean('accessible')->default(false)->comment('mobility = partial : accessible au moins en partie (filtre de l\'annuaire)');
            $table->boolean('step_free')->nullable()->comment('cheminement sans marche (NeTEx, niveau gare)');
            $table->boolean('tactile_guidance')->nullable()->comment('guidage tactile (NeTEx)');
            $table->unsignedSmallInteger('quays_total')->nullable();
            $table->unsignedSmallInteger('quays_step_free')->nullable();
            $table->unsignedSmallInteger('lifts')->nullable()->comment('ascenseurs recensés (NeTEx)');
            $table->unsignedSmallInteger('escalators')->nullable();
            $table->unsignedSmallInteger('travelators')->nullable();
            $table->text('netex_comment')->nullable()->comment('commentaire de SNCF sur l\'accessibilité de la gare');
            $table->jsonb('elevators')->nullable()->comment('état publié des ascenseurs : state_at, ok, ko, unknown, items[label, location, state]');
            $table->jsonb('amenities')->comment('équipements d\'accessibilité et services recensés (clés de StationAccessibility::AMENITIES)');
            $table->jsonb('assistance')->nullable()->comment('assistance en gare : mode, tariff, meeting_point, hours, since');
            $table->jsonb('sales')->nullable()->comment('points de vente : counter, machines[], assisted');
            $table->jsonb('bike_parking')->nullable()->comment('stationnement vélo sécurisé : places, as_of, lom_target, lom');
            $table->jsonb('datasets')->comment('jeux lus : dataset, title, publisher, license, modified');
            $table->string('source', 16)->default('sncf')->comment('fournisseur de droits (database/data/source_rights.php)');
            $table->timestamp('computed_at');
            $table->timestamps();
            $table->index('accessible');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('station_accessibility');
    }
};
