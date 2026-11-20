<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Journal d'audit : décisions de modération (approbation, application, refus, retrait d'une
 * contribution), suppressions de comptes, avertissements d'inactivité, information des membres.
 *
 * Minimisation : ni adresse IP, ni nom, ni courriel, ni texte libre. `actor_id` (le validateur ou
 * l'administrateur qui décide) sert à réexaminer une décision contestée « par une autre personne
 * que celle qui l'a prise » (CGU) ; il passe à NULL si ce compte est supprimé. Une suppression de
 * compte est journalisée sans aucun identifiant du compte supprimé.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table): void {
            $table->id();
            $table->string('action', 40)->comment('App\Enums\AuditAction');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('contribution_id')->nullable()->constrained()->nullOnDelete();
            $table->jsonb('context')->default('{}')->comment('Compteurs et états, sans donnée personnelle');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }
};
