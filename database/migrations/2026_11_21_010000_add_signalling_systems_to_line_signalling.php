<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Plusieurs systèmes de cantonnement sur une même tranche de signalisation.
 *
 * SNCF Réseau publie certaines tranches avec deux systèmes installés ensemble (« European train
 * control system 2/Transmission voie-machine 300 ») : `signalling` garde le premier (code principal,
 * inchangé pour les lecteurs existants) et `signalling_systems` la liste complète, dans l'ordre du
 * libellé (`["ETCS_L2", "TVM-300"]`). `source_label` garde le libellé SNCF tel quel (provenance).
 *
 * Les tranches existantes reçoivent la liste de leur seul code ; la réimportation
 * (`vff:import-sncf-signalling`) remplit ensuite les tranches doubles. Le retour en arrière retire
 * les deux colonnes sans toucher au reste.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('line_signalling', function (Blueprint $table): void {
            $table->jsonb('signalling_systems')->default(DB::raw("'[]'::jsonb"))
                ->comment('Tous les systèmes de cantonnement de la tranche, code principal en premier : ["ETCS_L2", "TVM-300"]');
            $table->string('source_label', 160)->nullable()
                ->comment('Libellé de la source tel quel (SNCF Réseau, mode-de-cantonnement-des-lignes)');
        });

        DB::statement("UPDATE line_signalling SET signalling_systems = jsonb_build_array(signalling) WHERE signalling IS NOT NULL AND signalling <> ''");
    }

    public function down(): void
    {
        Schema::table('line_signalling', function (Blueprint $table): void {
            $table->dropColumn(['signalling_systems', 'source_label']);
        });
    }
};
