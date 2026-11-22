<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Corrections sourcées de l'historique de la signalisation (`database/data/signalling_history_corrections.php`,
 * lues par `vff:import-signalling-history` et `vff:import-signalling-sources`) : un fait que des documents
 * contredisent est gardé en base mais n'est plus montré (`confidence = 'hidden'`), avec sa correction :
 *
 *   correction : {action, reason, quote, sources: [{publisher, title, url, published}]} ; nul pour un fait
 *                que personne n'a corrigé. Consultable par `GET /api/v1/lines/{line}/signalling-history`
 *                (clé `corrections`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('line_signalling_events', function (Blueprint $table): void {
            $table->jsonb('correction')->nullable()->after('verification');
        });
    }

    public function down(): void
    {
        Schema::table('line_signalling_events', function (Blueprint $table): void {
            $table->dropColumn('correction');
        });
    }
};
