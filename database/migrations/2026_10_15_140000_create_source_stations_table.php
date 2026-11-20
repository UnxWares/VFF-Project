<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gares et points remarquables TELS QUE DÉCRITS PAR UNE SOURCE, avec leur
 * point kilométrique (PK SNCF) sur une ligne.
 *
 * Pourquoi une table à part plutôt que `stations` : les sources historiques
 * (lignes-oubliees, Wikipédia…) donnent des gares disparues, souvent sans
 * coordonnées, mais AVEC leur PK sur la ligne. C'est exactement ce dont le
 * résolveur de sections a besoin pour transformer « Autun - Dracy-Saint-Loup »
 * (section_from / section_to de line_events) en intervalle de PK puis en
 * géométrie. On garde la donnée brute de chaque source (provenance + attributs)
 * sans la fusionner dans `stations`, qui reste le référentiel de gares géolocalisées.
 *
 *   - source : clé de la source (lignes-oubliees | wikipedia | …) ;
 *   - source_ref : identifiant stable dans la source (ex. « concarneau:la-boissiere ») ;
 *   - pk_m : PK du bâtiment voyageurs en mètres (peut être négatif : origine
 *     de ligne décalée, ex. Annecy -0,078) ;
 *   - lat / lon : NULL tant que la gare n'est pas géolocalisée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('source_stations', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 40);
            $table->string('source_ref', 160);
            $table->foreignId('line_id')->nullable()->constrained('lines')->nullOnDelete();
            $table->string('name');
            $table->integer('pk_m')->nullable()->comment('PK SNCF en mètres');
            $table->double('lat')->nullable();
            $table->double('lon')->nullable();
            $table->jsonb('properties')->nullable()->comment('Attributs bruts de la source (ordre, PK texte, URL…)');
            $table->timestamps();

            $table->unique(['source', 'source_ref']);
            $table->index('line_id');
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_stations');
    }
};
