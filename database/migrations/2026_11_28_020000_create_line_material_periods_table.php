<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Périodes de circulation d'une série sur une ligne, datées par une source (calculées par
 * `vff:import-material-periods`, App\Services\Materials\MaterialPeriodCollector).
 *
 * Rattachées au lien ligne × matériel importé (`line_materials`) : une période n'existe que pour un
 * lien attesté, et disparaît avec lui. Rien n'est deviné : une date absente reste nulle.
 *
 *   start_date / start_precision : début de la circulation (`day`, `month` ou `year`) ; nul si la source
 *                                   ne donne que la fin (« jusqu'en 1990 »)
 *   end_date / end_precision     : fin de la circulation ; nulle si la source ne la donne pas (« depuis 2009 »)
 *   section_from / section_to    : portion parcourue, quand le titre de section de l'article la nomme
 *   confidence                   : high (jour ou mois), medium (année), low (approximatif ou ambigu :
 *                                   gardé pour relecture, jamais montré)
 *   origin                       : line_article, material_article ou wikidata
 *   source_*                     : article lu et révision (permalien oldid), ou élément Wikidata
 *   source_excerpt               : courte phrase, pour relire seulement (jamais montrée ni servie)
 * Wikipédia : CC BY-SA 4.0, seuls des faits sont repris ; Wikidata : CC0 (database/data/source_rights.php).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('line_material_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('line_material_id')->constrained('line_materials')->cascadeOnDelete();
            $table->date('start_date')->nullable();
            $table->string('start_precision', 5)->nullable();
            $table->date('end_date')->nullable();
            $table->string('end_precision', 5)->nullable();
            $table->string('section_from')->nullable();
            $table->string('section_to')->nullable();
            $table->string('confidence', 6);
            $table->string('origin', 16);
            $table->string('source', 20);
            $table->string('source_title')->nullable();
            $table->unsignedBigInteger('source_revision')->nullable();
            $table->string('source_url', 600);
            $table->string('source_excerpt', 255)->nullable();
            $table->string('fingerprint', 40)->unique();
            $table->timestamps();
            $table->index(['line_material_id', 'start_date']);
        });
        DB::statement('ALTER TABLE line_material_periods ADD CONSTRAINT line_material_periods_dated CHECK (start_date IS NOT NULL OR end_date IS NOT NULL)');
    }

    public function down(): void
    {
        Schema::dropIfExists('line_material_periods');
    }
};
