<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notifications affichées sur le site (canal `database` de Laravel), en plus du courriel : suite
 * donnée à une contribution (validation, publication, refus avec son motif, retrait) et, pour les
 * validateurs, contribution qui attend leur relecture.
 *
 * `data` garde le texte déjà rédigé dans la langue du destinataire (titre, phrase, motif, lien
 * relatif) : jamais le nom ni le courriel d'un autre membre. Supprimées avec le compte
 * (AccountDeletion) ; une notification lue depuis plus de 12 mois est purgée
 * (App\Models\DatabaseNotification::prunable).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->jsonb('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // Purge des notifications lues depuis plus de 12 mois.
            $table->index('read_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
