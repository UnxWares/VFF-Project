<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plaidoyer / mobilisation : permet de signaler une ligne MENACÉE de fermeture
 * et d'afficher un message de soutien (fiche + carte + accueil).
 *   - is_threatened : flag rapide (carte/MVT, requêtes)
 *   - advocacy      : contenu {title, message, cta_label, cta_url}
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lines', function (Blueprint $table): void {
            $table->boolean('is_threatened')->default(false)->index()->after('is_lgv');
            $table->json('advocacy')->nullable()->after('is_threatened');
        });
    }

    public function down(): void
    {
        Schema::table('lines', function (Blueprint $table): void {
            $table->dropColumn(['is_threatened', 'advocacy']);
        });
    }
};
