<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Acceptation des CGU à l'inscription : date et version acceptée (date de la version en vigueur,
 * config/legal.php). Nulles pour les comptes créés avant la case à cocher (19/11/2026) : leur
 * acceptation n'a pas été enregistrée, on ne l'invente pas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('terms_accepted_at')->nullable();
            $table->string('terms_version', 10)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['terms_accepted_at', 'terms_version']);
        });
    }
};
