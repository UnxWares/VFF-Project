<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lignes recensées par archeoferroviaire.free.fr (tâche #118).
 *
 * Signal NÉGATIF : l'existence d'un ARTICLE régional catégorisé sur ce site
 * (rubriques FRANCE NORD/EST/OUEST/SUD-OUEST/SUD-EST, hors « Carte générale »)
 * atteste que la ligne est DISPARUE (fermée/déposée, jamais ré-exploitée).
 *
 * On NE déclasse PAS automatiquement les lignes VFF matchées : cette table
 * est une source de curation. matched_line_id relie l'article à une Line VFF
 * (via les villes-extrémités) quand le rapprochement est confiant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('archeo_lines', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('article_id')->unique(); // spip.php?articleNN
            $table->string('name');                          // paire de villes, ex « Mézidon Dives »
            $table->string('rubrique', 32)->index();         // FRANCE NORD/EST/OUEST/SUD-OUEST/SUD-EST
            $table->string('url');
            $table->string('gpx_url')->nullable();
            $table->date('opening_date')->nullable();
            $table->date('closure_date')->nullable();
            $table->foreignId('matched_line_id')->nullable()->constrained('lines')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archeo_lines');
    }
};
