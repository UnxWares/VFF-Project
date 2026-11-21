<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit des gares (docs/AUDIT-GARES.md), deux tables à part : aucune table du moteur n'est modifiée.
 *
 * station_audits : une ligne par gare, écrite par `vff:audit-stations` (passe en lecture seule sur
 * les tables du moteur).
 *   placement_grade   : agree (deux sources indépendantes à moins de 20 m), close (la plus proche
 *                       entre 20 et 100 m), isolated (une seule famille de sources), disagree (une
 *                       source précise à plus de 100 m, ou aucune à moins de 100 m), none (aucune source)
 *   nearest_agreeing_m: écart entre la position retenue et la source indépendante la plus proche
 *   farthest_precise_m: écart à la source précise la plus éloignée
 *   track_offset_m    : écart entre la position retenue et la voie la plus proche de ses lignes
 *   positions         : positions comparées (source, référence, lon, lat, distance_m, track_m, famille)
 *   opening_grade, closure_grade : sourced, estimated, unknown (closure : not_applicable si la gare est ouverte)
 *   services          : voyageurs et fret séparés (état, dates, note)
 *   checks            : contrôles croisés en échec (code, détail)
 *
 * station_date_candidates : dates trouvées dans une source (Wikidata pour commencer) et comparées
 * aux dates actuelles de VFF, SANS les remplacer.
 *   kind       : opening, closure, closure_passenger, closure_freight, reopening
 *   precision  : day, month, year, decade, century (précision Wikidata 11, 10, 9, 8, 7)
 *   comparison : same, more_precise, less_precise, differs, new, station_open (fermeture d'une gare ouverte)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('station_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('station_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('placement_grade', 10);
            $table->unsignedSmallInteger('families')->default(0);
            $table->integer('nearest_agreeing_m')->nullable();
            $table->integer('farthest_precise_m')->nullable();
            $table->integer('track_offset_m')->nullable();
            $table->jsonb('positions')->default('[]');
            $table->string('opening_grade', 16);
            $table->string('closure_grade', 16);
            $table->jsonb('services')->default('{}');
            $table->jsonb('checks')->default('[]');
            $table->unsignedSmallInteger('checks_count')->default(0);
            $table->string('region_code', 3)->nullable();
            $table->string('rule_version', 16);
            $table->timestamp('audited_at');
            $table->timestamps();
            $table->index('placement_grade');
            $table->index(['opening_grade', 'closure_grade']);
            $table->index('region_code');
        });

        Schema::create('station_date_candidates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('station_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 24);
            $table->date('date');
            $table->string('precision', 10);
            $table->string('source', 20);
            $table->string('source_ref', 120);
            $table->string('source_url', 300)->nullable();
            $table->string('rank', 12)->default('normal');
            $table->jsonb('qualifiers')->default('{}');
            $table->jsonb('references')->default('[]');
            $table->date('vff_date')->nullable();
            $table->string('vff_precision', 10)->nullable();
            $table->string('vff_source', 30)->nullable();
            $table->string('comparison', 14);
            $table->integer('delta_days')->nullable();
            $table->timestamp('fetched_at');
            $table->timestamps();
            $table->unique(['station_id', 'kind', 'source', 'source_ref']);
            $table->index(['source', 'comparison']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('station_date_candidates');
        Schema::dropIfExists('station_audits');
    }
};
