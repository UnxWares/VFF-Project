<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rattachements MULTIPLES d'un tracé brut à des lignes (vff:link-traces).
 *
 * source_traces.line_id ne porte que la ligne PRINCIPALE du tracé. Or un tracé
 * archeoferroviaire « Auray Saint-Brieuc » couvre deux lignes RFN (475000 et
 * 474000), et un tracé de raccordement touche deux lignes. Cette table garde
 * toutes les lignes concernées avec la part du tracé qui leur revient, pour
 * que la géométrie de référence d'une ligne ne prenne que SA portion
 * (part recouverte par le tampon des tronçons SNCF du code).
 *
 *   role = primary   : ligne de source_traces.line_id ;
 *          secondary : autre ligne RFN recouverte (≥ 10 % ou ≥ 500 m) ;
 *          touching  : ligne voisine touchée par une extrémité (contexte).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('source_trace_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_trace_id')->constrained('source_traces')->cascadeOnDelete();
            $table->foreignId('line_id')->constrained('lines')->cascadeOnDelete();
            $table->string('role', 12)->comment('primary | secondary | touching');
            $table->string('method', 30)->comment('sncf_overlap | name | touching | created | code');
            $table->float('share')->nullable()->comment('Part du tracé recouverte par la ligne (0-1)');
            $table->integer('overlap_m')->nullable()->comment('Longueur recouverte (m)');
            $table->timestamps();

            $table->unique(['source_trace_id', 'line_id']);
            $table->index('line_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_trace_lines');
    }
};
