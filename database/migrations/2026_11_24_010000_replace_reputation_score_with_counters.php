<?php

use App\Services\Reputation\ReputationCalculator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La réputation n'est plus un score : trois compteurs que chacun comprend (contributions
 * acceptées et appliquées, contributions refusées, relectures faites), recalculés à partir des
 * contributions et des approbations (App\Services\Reputation\ReputationCalculator). Le niveau
 * s'en déduit avec les seuils de config/reputation.php et n'est pas stocké.
 *
 * L'ancien champ `reputation` (entier, jamais calculé) est retiré. Les compteurs des comptes
 * existants sont remplis à la fin de la migration (même calcul que `vff:recalculate-reputation`),
 * pour qu'aucun membre n'affiche zéro après le déploiement ; sans compte, rien n'est lu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedInteger('reputation_applied')->default(0)->after('role');
            $table->unsignedInteger('reputation_rejected')->default(0)->after('reputation_applied');
            $table->unsignedInteger('reputation_reviews')->default(0)->after('reputation_rejected');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('reputation');
        });

        if (DB::table('users')->exists()) {
            app(ReputationCalculator::class)->recalculateAll();
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->integer('reputation')->default(0)->after('role');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['reputation_applied', 'reputation_rejected', 'reputation_reviews']);
        });
    }
};
