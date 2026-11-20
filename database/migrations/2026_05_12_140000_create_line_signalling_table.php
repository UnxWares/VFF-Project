<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Signalisation par tranche de PK pour chaque ligne et ère.
 *
 * Une ligne peut avoir plusieurs systèmes différents le long de son
 * tracé (ex : BAL sur Paris-Lyon entre PK 0 et PK 100, BAPR au-delà).
 * Plusieurs systèmes de protection peuvent coexister (ex : TVM-300 +
 * ETCS L1 sur certaines LGV).
 *
 * `signalling` = système de cantonnement (BAL, BAPR, BM, BMVU…).
 * `train_protection` = array JSON des systèmes de contrôle (KVB,
 *  ETCS_L1, ETCS_L2, TVM-300, TVM-430, RPS, KVBP…).
 * `kvb_equipped` = flag dédié car c'est l'info la plus demandée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('line_signalling', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('line_id')->constrained()->cascadeOnDelete();
            $table->foreignId('era_id')->constrained()->cascadeOnDelete();
            $table->integer('pk_start_m')->comment('PK début de la tranche (m)');
            $table->integer('pk_end_m')->comment('PK fin de la tranche (m)');
            $table->string('signalling', 50)
                ->comment('Système de cantonnement : BAL, BAPR, BAPR-DV, BM, BMVU, CAPI, cantonnement_telephonique');
            $table->json('train_protection')->nullable()
                ->comment('Array JSON : KVB, KVBP, ETCS_L1, ETCS_L2, TVM-300, TVM-430, RPS');
            $table->boolean('kvb_equipped')->default(false)
                ->comment('Raccourci : la tranche est équipée KVB ou KVBP');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['line_id', 'era_id', 'pk_start_m']);
            $table->index(['era_id', 'signalling']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('line_signalling');
    }
};
