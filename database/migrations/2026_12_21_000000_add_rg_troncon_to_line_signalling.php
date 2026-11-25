<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rang du tronçon de SNCF Réseau d'une tranche de signalisation (`rg_troncon` des jeux mode-de-cantonnement-des-lignes
 * et lignes-equipees-de-kvb). Deux tronçons d'une même ligne peuvent avoir des PK communs (330000, 975900 : environ
 * 1 km) : ce sont deux endroits différents, et le KVB de l'un ne vaut pas pour l'autre. Colonne ADDITIVE et nulle :
 * null pour une tranche écrite avant elle ou venue d'une autre source ; la réimportation
 * (`vff:import-sncf-signalling --from-cache`) la remplit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('line_signalling', function (Blueprint $table): void {
            $table->smallInteger('rg_troncon')->nullable()
                ->comment('Rang du tronçon de SNCF Réseau (rg_troncon), null hors des jeux de SNCF Réseau ou avant la réimportation');
        });
    }

    public function down(): void
    {
        Schema::table('line_signalling', function (Blueprint $table): void {
            $table->dropColumn('rg_troncon');
        });
    }
};
