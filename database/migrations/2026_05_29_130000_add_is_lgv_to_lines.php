<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drapeau LGV explicite sur lines, dérivé de la donnée SNCF (sncf_line_catlig)
 * au lieu de l'heuristique fragile nom/vitesse côté GeoExporter. Peuplé par
 * LineClassifier (vff:classify-lines) : catlig « grande vitesse », ou nom LGV,
 * ou vitesse ≥ 270, + héritage aux raccordements connectés à une LGV.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lines', function (Blueprint $table): void {
            $table->boolean('is_lgv')->default(false)->index()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('lines', function (Blueprint $table): void {
            $table->dropColumn('is_lgv');
        });
    }
};
