<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gares et points remarquables AVEC LEUR PK tels que les donne le schéma de
 * ligne (modèles {{BS…}}) des articles Wikipédia, remplis par
 * vff:import-wikipedia-chronology.
 *
 * Pourquoi : sur les sections déclassées, SNCF Réseau ne publie plus ni gares
 * ni passages à niveau ; le schéma Wikipédia reste souvent la seule source
 * « nom de gare ↔ PK ». Une fois la gare géocodée (stations, gares SNCF), c'est
 * un point de contrôle supplémentaire pour caler les PK sur le tracé
 * (App\Services\SectionResolver).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wikipedia_line_stations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('line_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->integer('pk_m');
            $table->string('icon', 60)->nullable()->comment('Pictogramme BS (BHF, exBHF, KBHFa…)');
            $table->string('source_url', 500)->comment('Permalien (oldid) de l\'article');
            $table->timestamps();
            $table->unique(['line_id', 'name', 'pk_m']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wikipedia_line_stations');
    }
};
