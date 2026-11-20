<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute `route_length_m` à `line_geometries`.
 *
 * `length_m` = longueur géodésique de la géométrie fusionnée brute
 * (méthode prescrite ST_LineMerge). Sur une ligne à 2 voies, les deux
 * voies parallèles sont comptées → ≈ 2× le route-km réel.
 *
 * `route_length_m` = route-km DÉDUPLIQUÉ : les sous-LineString quasi
 * superposées (voies parallèles) ne sont comptées qu'une fois. C'est la
 * longueur « commerciale » d'une ligne (ex : LGV Sud-Est ≈ 712 km au lieu
 * de 1 434 km en comptant les 2 voies).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('line_geometries', function (Blueprint $table): void {
            $table->bigInteger('route_length_m')->nullable()->after('length_m')
                ->comment('Route-km dédupliqué (voies parallèles comptées une fois).');
        });
    }

    public function down(): void
    {
        Schema::table('line_geometries', function (Blueprint $table): void {
            $table->dropColumn('route_length_m');
        });
    }
};
