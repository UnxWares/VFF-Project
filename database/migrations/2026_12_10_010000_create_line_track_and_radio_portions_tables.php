<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Infrastructure actuelle des lignes par portion de PK, d'après deux jeux de SNCF Réseau Open Data (ODbL) :
 *
 * - `line_track_portions` : régime d'exploitation (« regime-dexploitation-des-lignes », vff:import-sncf-track-regime) :
 *   libellé SNCF tel quel et nombre de voies principales qu'il établit (2 double voie, 1 voie unique ou navette,
 *   null pour « voie banalisée » et « _Autre », qui ne le disent pas) ;
 * - `line_radio_portions` : liaison radio sol-train (« lignes-equipees-de-liaison-radio-sol-train »,
 *   vff:import-sncf-radio) : système (gsm-r, gsm-gfu, rst, rst-data, rst-id) et libellé SNCF tel quel.
 *
 * Les deux décrivent le réseau d'aujourd'hui, sans date : aucune ère. `source` vaut `sncf` (registre des droits,
 * database/data/source_rights.php). Une ligne supprimée emporte ses portions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('line_track_portions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('line_id')->constrained()->cascadeOnDelete();
            $table->string('code_ligne', 16)->comment('Code de ligne SNCF Réseau du jeu');
            $table->smallInteger('rg_troncon')->nullable()->comment('Rang du tronçon SNCF');
            $table->integer('pk_start_m')->nullable()->comment('PK de début (m), null si illisible');
            $table->integer('pk_end_m')->nullable()->comment('PK de fin (m), null si illisible');
            $table->string('regime', 120)->comment('Libellé SNCF du régime d\'exploitation, tel quel');
            $table->smallInteger('tracks')->nullable()->comment('Voies principales : 2, 1, null si le libellé ne le dit pas');
            $table->string('source', 30)->default('sncf');
            $table->date('dataset_modified')->nullable()->comment('Date de modification du jeu (métadonnées)');
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();

            $table->index(['line_id', 'pk_start_m']);
        });

        Schema::create('line_radio_portions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('line_id')->constrained()->cascadeOnDelete();
            $table->string('code_ligne', 16)->comment('Code de ligne SNCF Réseau du jeu');
            $table->smallInteger('rg_troncon')->nullable()->comment('Rang du tronçon SNCF');
            $table->integer('pk_start_m')->nullable()->comment('PK de début (m), null si illisible');
            $table->integer('pk_end_m')->nullable()->comment('PK de fin (m), null si illisible');
            $table->string('system', 20)->comment('gsm-r, gsm-gfu, rst, rst-data, rst-id');
            $table->string('label', 120)->comment('Libellé SNCF du système radio, tel quel');
            $table->string('source', 30)->default('sncf');
            $table->date('dataset_modified')->nullable()->comment('Date de modification du jeu (métadonnées)');
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();

            $table->index(['line_id', 'pk_start_m']);
            $table->index('system');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('line_radio_portions');
        Schema::dropIfExists('line_track_portions');
    }
};
