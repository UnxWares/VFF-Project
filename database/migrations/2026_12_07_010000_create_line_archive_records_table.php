<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notices d'inventaire des fonds d'archives rattachées à une ligne (section « Dans les archives » de la fiche,
 * sous les documents de Gallica). Première source : le jeu de données « Archives SNCF » du Service Archives
 * Documentation du groupe SNCF (SARDO), publié sur ressources.data.sncf.com sous ODbL, importé par
 * `vff:import-sncf-archives`. Seules les métadonnées de la notice sont gardées avec le lien vers
 * archives.sncf.com : ni la description (qui peut nommer des agents), ni l'image, ni le document numérisé.
 *
 *   source         : fonds d'origine (`sncf-archives`)
 *   reference      : numéro du dossier dans le fonds (« 0390LM0048-018 », « 12138 »), qui n'est pas toujours unique ;
 *                    la notice est identifiée par son lien (url)
 *   doc_type       : document (dossier d'archives), image (photographie, affiche), video (film)
 *   theme, sub_theme : classement thématique du fonds
 *   holder         : service versant ou propriétaire (« Org. Territoriale \ Direction Régionale de Paris Est »)
 *   box            : boîte d'archives (« 0390LM0048 »)
 *   rights         : titulaire des droits indiqué par la notice (« SNCF-MEDIATHEQUE »)
 *   date_label, year, year_to, exact_date, date_precision : date de la notice (début et fin du dossier, ou
 *                    date de prise de vue), précision day, month, year, range ou unknown
 *   url            : notice et document sur le site du fonds
 *   score, score_reasons, matched_places : rattachement (ArchiveMatcher, même méthode que Gallica)
 *   fetched_at     : date de récupération du jeu de données
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('line_archive_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('line_id')->constrained()->cascadeOnDelete();
            $table->string('source', 20);
            $table->string('reference', 60)->nullable();
            $table->text('title');
            $table->string('doc_type', 16);
            $table->text('theme')->nullable();
            $table->text('sub_theme')->nullable();
            $table->string('holder', 200)->nullable();
            $table->string('box', 40)->nullable();
            $table->string('rights', 120)->nullable();
            $table->string('date_label', 60)->nullable();
            $table->smallInteger('year')->nullable();
            $table->smallInteger('year_to')->nullable();
            $table->date('exact_date')->nullable();
            $table->string('date_precision', 10)->default('unknown');
            $table->string('url', 500);
            $table->smallInteger('score');
            $table->jsonb('score_reasons')->default('[]');
            $table->jsonb('matched_places')->default('[]');
            $table->timestamp('fetched_at');
            $table->timestamps();
            $table->unique(['line_id', 'url']);
            $table->index(['line_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('line_archive_records');
    }
};
