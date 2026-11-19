<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Profil « partenaire » de l'API publique (config/api.php) : quota de requêtes plus élevé pour les
 * jetons du compte. Accordé et retiré par un administrateur (php artisan vff:api-partner) ;
 * `api_partner_at` non nul = partenaire depuis cette date.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('api_partner_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('api_partner_at');
        });
    }
};
