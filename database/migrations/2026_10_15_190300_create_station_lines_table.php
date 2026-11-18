<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rattachement gare ↔ ligne, indépendant des ères (vff:attach-stations).
 *
 *   - method : sncf_pk (liste des gares SNCF : code ligne + PK officiel),
 *     source_pk (source_stations : PK donné par lignes-oubliees, Wikipédia…),
 *     trace (proximité d'un tracé source_traces rattaché à la ligne),
 *     segment (proximité d'un tronçon line_segments de la ligne) ;
 *   - pk_m / pk_source : PK en mètres et son origine (sncf | <source> |
 *     projected = projection sur le tracé, approximative) ;
 *   - first_year / last_year : période de desserte de la gare par CETTE ligne
 *     déduite des tracés datés et des événements de ligne (NULL = inconnue /
 *     toujours en service).
 *
 * `line_stops` (ordre + PK par ère) est ensuite matérialisé à partir d'ici et
 * de `station_eras`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('station_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('station_id')->constrained('stations')->cascadeOnDelete();
            $table->foreignId('line_id')->constrained('lines')->cascadeOnDelete();
            $table->string('method', 20);
            $table->integer('pk_m')->nullable();
            $table->string('pk_source', 30)->nullable();
            $table->integer('distance_m')->nullable()->comment('Distance gare ↔ tracé (m)');
            $table->string('source_ref', 160)->nullable();
            $table->smallInteger('first_year')->nullable();
            $table->smallInteger('last_year')->nullable();
            $table->string('years_source', 30)->nullable();
            $table->timestamps();

            $table->unique(['station_id', 'line_id']);
            $table->index('line_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('station_lines');
    }
};
