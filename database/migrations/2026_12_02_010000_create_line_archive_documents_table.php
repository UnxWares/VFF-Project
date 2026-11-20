<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documents d'archives rattachés à une ligne (section « Dans les archives » de la fiche), trouvés par
 * `vff:import-gallica` dans les notices du catalogue général de la BnF qui décrivent un document numérisé
 * en accès libre dans Gallica. Seules les MÉTADONNÉES de la notice (Licence Ouverte) sont gardées, avec
 * les identifiants ark : aucune image, vignette ni texte numérisé (cf. database/data/source_rights.php,
 * fournisseur `gallica`).
 *
 *   gallica_ark    : document numérisé dans Gallica (« ark:/12148/bpt6k… », lien https://gallica.bnf.fr/<ark>)
 *   catalogue_ark  : notice du catalogue général (« ark:/12148/cb… »)
 *   doc_type       : monograph (texte imprimé), map (document cartographique), image (image fixe), manuscript
 *   authors        : auteurs et contributeurs, tels que la notice les écrit (« Villemandy, Alfred de »)
 *   date_label     : date telle que la notice l'imprime (« [ca 1850] », « 1er novembre 1873 »)
 *   year, year_to  : année (et dernière année d'un intervalle) ; exact_date : jour ou mois quand la notice le donne
 *   date_precision : day, month, year, circa (date approchée), range (entre year et year_to), unknown
 *   shelfmark      : cote du document original à la BnF (« V-54984 », « GE C-4520 »)
 *   score, score_reasons : score de rattachement et sa justification, point par point (ArchiveMatcher)
 *   matched_places : lieux de la ligne nommés dans le titre
 *   record_updated_on : dernière mise à jour de la notice à la BnF ; fetched_at : date de récupération
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('line_archive_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('line_id')->constrained()->cascadeOnDelete();
            $table->string('source', 20)->default('gallica');
            $table->string('gallica_ark', 80);
            $table->string('catalogue_ark', 80)->nullable();
            $table->text('title');
            $table->string('doc_type', 16);
            $table->jsonb('authors')->default('[]');
            $table->string('date_label', 120)->nullable();
            $table->smallInteger('year')->nullable();
            $table->smallInteger('year_to')->nullable();
            $table->date('exact_date')->nullable();
            $table->string('date_precision', 10)->default('unknown');
            $table->string('shelfmark', 120)->nullable();
            $table->smallInteger('score');
            $table->jsonb('score_reasons')->default('[]');
            $table->jsonb('matched_places')->default('[]');
            $table->date('record_updated_on')->nullable();
            $table->timestamp('fetched_at');
            $table->timestamps();
            $table->unique(['line_id', 'gallica_ark']);
            $table->index(['line_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('line_archive_documents');
    }
};
