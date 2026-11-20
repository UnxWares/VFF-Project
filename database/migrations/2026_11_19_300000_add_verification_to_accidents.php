<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vérification des fiches d'accidents (vff:verify-accidents, cf. App\Services\Accidents\AccidentVerifier) :
 *
 *   - accidents.verification / verified_at : ce que les sources disent de la date et du lieu, la règle
 *     qui a fait passer la fiche à « ok » (source primaire, ou deux sources indépendantes), et la date
 *     de la lecture ;
 *   - accidents.alias_of_id : fiche qui décrit le même accident qu'une autre (deux éléments Wikidata
 *     pour un seul accident) ; elle est gardée avec ses sources mais jamais publiée ;
 *   - accident_lines.cited_as / cited_url : titre de l'article de ligne cité par la source et permalien
 *     de la source qui le cite (un titre d'article est un fait, aucune phrase n'est recopiée).
 *
 * Colonnes ajoutées seulement : vff:import-accidents ne les écrit pas, elles survivent à un nouvel import
 * (sauf cited_*, car l'import refait les rattachements : rejouer vff:verify-accidents ensuite).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accidents', function (Blueprint $table): void {
            $table->jsonb('verification')->nullable()->comment('vérification de la date et du lieu : règle, sources qui concordent ou divergent, date de lecture');
            $table->timestamp('verified_at')->nullable()->comment('dernière exécution de vff:verify-accidents sur la fiche');
            $table->foreignId('alias_of_id')->nullable()->index()
                ->comment('fiche retenue quand celle-ci décrit le même accident (alias gardé avec ses sources, jamais publié)')
                ->constrained('accidents')->cascadeOnDelete();
        });
        Schema::table('accident_lines', function (Blueprint $table): void {
            $table->string('cited_as', 300)->nullable()->comment('titre de l\'article de ligne cité par la source');
            $table->string('cited_url', 500)->nullable()->comment('permalien de la source qui cite la ligne');
        });
    }

    public function down(): void
    {
        Schema::table('accident_lines', function (Blueprint $table): void {
            $table->dropColumn(['cited_as', 'cited_url']);
        });
        Schema::table('accidents', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('alias_of_id');
            $table->dropColumn(['verification', 'verified_at']);
        });
    }
};
