<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bâtiments voyageurs et ouvrages de gare protégés ou recensés (`vff:import-station-heritage`).
 *
 * Une ligne par gare et par objet patrimonial (`ref` : référence Mérimée PA/IA, à défaut identifiant Wikidata).
 * Sources : base Mérimée du ministère de la Culture (POP, Licence Ouverte 2.0 : arrêté, date, architecte, datation)
 * et Wikidata (CC0 : protections P1435, référence P380, architecte P84, construction P571, style P149, image P18).
 *
 * - `element` : `building` (bâtiment voyageurs) ou `works` (ouvrage de la gare : remise, halle, quai monumental) ;
 * - `protection` : la plus forte des protections de l'objet, `classified`, `registered` (monuments historiques),
 *   `acr` (label Architecture contemporaine remarquable, ex-« Patrimoine du XXe siècle ») ou `inventory`
 *   (recensé à l'Inventaire général, sans protection) ; `protection_date` : date de l'arrêté, `scope` : `partial`
 *   quand seules certaines parties sont protégées ; `designations` : toutes les mentions (type, date) ;
 * - `architects` : liste `{name, role}` ; `built_from` / `built_to` : années de construction ; `built_period` :
 *   texte de la datation par siècle quand Mérimée n'en donne pas d'années ;
 * - `image_*` : fichier de Wikimedia Commons lié par Wikidata, avec sa vignette (`image_url`), sa page (`image_source_url`), sa licence et son auteur lus sur Commons ;
 * - `match_method` : comment la gare a été trouvée (`wikidata`, `name-commune`, `proximity`).
 *
 * Additive : aucune table existante n'est touchée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('station_heritage', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('station_id')->constrained()->cascadeOnDelete();
            $table->string('element', 12)->default('building');
            $table->string('protection', 12);
            $table->date('protection_date')->nullable();
            $table->string('scope', 8)->nullable();
            $table->jsonb('designations')->nullable();
            $table->string('title', 255)->nullable();
            $table->string('denomination', 120)->nullable();
            $table->jsonb('architects')->nullable();
            $table->smallInteger('built_from')->nullable();
            $table->smallInteger('built_to')->nullable();
            $table->string('built_period', 60)->nullable();
            $table->string('style', 120)->nullable();
            $table->string('ref', 20);
            $table->string('ref_kind', 2);
            $table->string('ref_url', 255)->nullable();
            $table->string('wikidata_id', 20)->nullable();
            $table->string('image_file', 255)->nullable();
            $table->string('image_url', 500)->nullable();
            $table->string('image_source_url', 500)->nullable();
            $table->string('image_license', 80)->nullable();
            $table->string('image_author', 255)->nullable();
            $table->string('source', 12);
            $table->string('match_method', 20);
            $table->smallInteger('match_distance_m')->nullable();
            $table->date('source_updated')->nullable();
            $table->timestamps();
            $table->unique(['station_id', 'ref']);
            $table->index('protection');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('station_heritage');
    }
};
