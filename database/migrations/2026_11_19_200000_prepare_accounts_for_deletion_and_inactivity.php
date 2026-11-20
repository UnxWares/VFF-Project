<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Engagements de la politique de confidentialité sur les comptes :
 *
 *   - suppression du compte : les contributions publiées restent, détachées de leur auteur.
 *     `contributions.user_id` et `contribution_approvals.user_id` deviennent facultatifs et
 *     passent à NULL quand le compte disparaît (avant : ON DELETE CASCADE, la suppression d'un
 *     compte effaçait ses contributions publiées et les approbations des validateurs) ;
 *   - comptes inutilisés pendant 3 ans : `last_seen_at` (dernière visite connectée, au plus
 *     une écriture par jour) et `inactivity_warned_at` (avertissement envoyé un mois avant la
 *     suppression, remis à NULL au retour du membre).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('last_seen_at')->nullable()->after('remember_token')
                ->comment('Dernière visite connectée (jour près) : base du délai d’inactivité de 3 ans');
            $table->timestamp('inactivity_warned_at')->nullable()->after('last_seen_at')
                ->comment('Avertissement d’inactivité envoyé ; NULL dès que le membre revient');
            $table->index('last_seen_at');
            $table->index('inactivity_warned_at');
        });

        Schema::table('contributions', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
        });
        Schema::table('contributions', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('contribution_approvals', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
        });
        Schema::table('contribution_approvals', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Retour au schéma d'origine impossible sans perte si des contributions ont déjà été
        // détachées d'un compte supprimé : on refuse plutôt que d'effacer des données publiées.
        foreach (['contributions', 'contribution_approvals'] as $table) {
            if (DB::table($table)->whereNull('user_id')->exists()) {
                throw new RuntimeException("Retour arrière refusé : {$table} contient des lignes détachées d'un compte supprimé (user_id NULL).");
            }
        }

        foreach (['contributions', 'contribution_approvals'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropForeign(['user_id']);
            });
            Schema::table($name, function (Blueprint $table): void {
                $table->unsignedBigInteger('user_id')->nullable(false)->change();
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['last_seen_at']);
            $table->dropIndex(['inactivity_warned_at']);
            $table->dropColumn(['last_seen_at', 'inactivity_warned_at']);
        });
    }
};
