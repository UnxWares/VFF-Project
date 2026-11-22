<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Historique de la signalisation, troisième passe : des mises en service datées par d'autres documents que
 * l'article Wikipédia de la ligne (communiqués de SNCF Réseau, rapports du BEA-TT et de l'EPSF, Journal
 * officiel, presse), relevées à la main dans `database/data/signalling_history_sources.php` et rangées par
 * `vff:import-signalling-sources` dans `line_signalling_events`, avec `source = 'manual'` et
 * `origin = 'document'`.
 *
 *   source_revision : désormais nul pour ces faits (un document n'a pas de révision) ;
 *   documents       : documents qui datent le fait, dans l'ordre (le premier est aussi dans `source_title` et
 *                     `source_url`) : [{publisher, title, url, published}] ; nul pour un fait de Wikipédia ;
 *   verification    : `official` (au moins un document d'un organisme officiel : SNCF Réseau, État, EPSF,
 *                     BEA-TT, Journal officiel) ou `two_sources` (deux documents indépendants) ; nul pour
 *                     un fait de Wikipédia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('line_signalling_events', function (Blueprint $table): void {
            $table->unsignedBigInteger('source_revision')->nullable()->change();
            $table->jsonb('documents')->nullable()->after('source_excerpt');
            $table->string('verification', 12)->nullable()->after('documents');
        });
    }

    public function down(): void
    {
        DB::table('line_signalling_events')->where('source', 'manual')->delete();
        Schema::table('line_signalling_events', function (Blueprint $table): void {
            $table->dropColumn(['documents', 'verification']);
            $table->unsignedBigInteger('source_revision')->nullable(false)->change();
        });
    }
};
