<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paramètres déclarés au RINF par voie de section de ligne (vff:import-rinf, RinfQueries::trackParameters) :
 * électrification, contrôle de vitesse et protection hérités, niveau ETCS, GSM-R, gabarits, catégorie de charge,
 * écartement. Colonnes ADDITIVES et nulles de rinf_tracks : la provenance reste celle de la voie (`dataset`,
 * `fetched_at`). Trois états par paramètre : une valeur ; « aucun » quand le gestionnaire le déclare sans objet
 * (liste vide, `none`, faux) ; null quand rien n'est dit. Voies des points d'exploitation : toujours null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rinf_tracks', function (Blueprint $table): void {
            $table->string('contact_line', 12)->nullable()->comment('Ligne de contact : overhead, third_rail, fourth_rail, none');
            $table->string('energy_supply', 8)->nullable()->comment('Système d\'alimentation RINF (concept energy-supply-systems : AC10, DC40…)');
            $table->integer('voltage_v')->nullable()->comment('Tension nominale du système d\'alimentation, en volts');
            $table->decimal('frequency_hz', 5, 2)->nullable()->comment('Fréquence en Hz (0 : courant continu)');
            $table->jsonb('train_protection')->nullable()->comment('Contrôle de vitesse et protection hérités (KVB, TVM 430, Crocodile…) ; [] : aucun déclaré');
            $table->string('etcs_level', 20)->nullable()->comment('Niveau ETCS : none, 0, 1, 2, 3, ntc, under_construction');
            $table->boolean('gsm_r')->nullable()->comment('GSM-R déclaré (vrai), déclaré sans objet (faux), non dit (null)');
            $table->string('gsm_r_version', 40)->nullable()->comment('Version GSM-R déclarée (Baseline 1…)');
            $table->jsonb('gauging_profiles')->nullable()->comment('Gabarits déclarés (GB1, FR-3.3, GI2…)');
            $table->string('load_category', 8)->nullable()->comment('Catégorie de ligne EN 15528 (charge à l\'essieu et par mètre : C4, D4…)');
            $table->string('national_load_class', 20)->nullable()->comment('Classe de charge nationale déclarée par le gestionnaire, telle quelle');
            $table->smallInteger('track_gauge_mm')->nullable()->comment('Écartement nominal, en mm');
        });
    }

    public function down(): void
    {
        Schema::table('rinf_tracks', function (Blueprint $table): void {
            $table->dropColumn(['contact_line', 'energy_supply', 'voltage_v', 'frequency_hz', 'train_protection', 'etcs_level', 'gsm_r',
                'gsm_r_version', 'gauging_profiles', 'load_category', 'national_load_class', 'track_gauge_mm']);
        });
    }
};
