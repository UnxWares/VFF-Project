<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrégats au niveau LIGNE (vitesse max, électrification, tension dominante),
 * calculés depuis les segments par LineClassifier. Permettent un affichage
 * COHÉRENT au clic sur la carte (le popup ne dépend plus du segment/voie cliqué).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lines', function (Blueprint $table): void {
            $table->smallInteger('max_speed_kmh')->nullable()->after('is_lgv');
            $table->boolean('electrified')->default(false)->after('max_speed_kmh');
            $table->integer('voltage_v')->nullable()->after('electrified');
        });
    }

    public function down(): void
    {
        Schema::table('lines', function (Blueprint $table): void {
            $table->dropColumn(['max_speed_kmh', 'electrified', 'voltage_v']);
        });
    }
};
