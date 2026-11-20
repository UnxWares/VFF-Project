<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sections nommées d'une ligne - appellations commerciales/historiques
 * (ex: "LGV Sud-Est", "LGV Méditerranée") qui ne sont pas des lignes
 * SNCF distinctes mais qui méritent d'être affichées sur la fiche.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('line_sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('line_id')->constrained()->cascadeOnDelete();
            $table->string('name', 200);
            $table->json('alt_names')->nullable();
            $table->integer('pk_start_m');
            $table->integer('pk_end_m');
            $table->smallInteger('opening_year_from')->nullable();
            $table->smallInteger('opening_year_to')->nullable();
            $table->text('description')->nullable();
            $table->string('source_url', 500)->nullable();
            $table->timestamps();

            $table->index(['line_id', 'pk_start_m']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('line_sections');
    }
};
