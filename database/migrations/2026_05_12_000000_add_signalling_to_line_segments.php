<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('line_segments', function (Blueprint $table): void {
            $table->string('signalling', 50)->nullable()->after('voltage_v')
                ->comment('BAL, BAPR, BAPR-DV, BM, CAPI, BMVU, cantonnement_telephonique, etc.');
            $table->string('train_protection', 100)->nullable()->after('signalling')
                ->comment('KVB, KVBP, ERTMS_L1, ERTMS_L2, ETCS_L1, ETCS_L2, TVM-300, TVM-430, RPS, etc. (CSV si plusieurs)');
            $table->date('dismantled_at')->nullable()->after('train_protection')
                ->comment('Date de dépose physique de la voie sur ce segment (null si jamais déposée ou inconnue)');
        });
    }

    public function down(): void
    {
        Schema::table('line_segments', function (Blueprint $table): void {
            $table->dropColumn(['signalling', 'train_protection', 'dismantled_at']);
        });
    }
};
