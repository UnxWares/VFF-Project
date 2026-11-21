<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Audit des tronçons (docs/AUDIT-TRONCONS.md) : table à part, écrite par `vff:audit-segments`, une
 * passe en lecture seule sur les tables du moteur (aucune table du moteur n'est modifiée).
 *
 * Une ligne par tronçon (`scope` = segment) et une ligne de synthèse par ligne et par ère
 * (`scope` = line, `segment_id` nul). Le tronçon audité peut disparaître au recalcul suivant du
 * moteur : `segment_id` n'est donc pas une clé étrangère, et l'état audité (statut, trafic, source
 * du tracé, géométrie) est recopié pour que la note reste lisible avec ses preuves.
 *
 *   classification_grade, placement_grade, accuracy_grade, trace_grade :
 *       good (sûr), fair (à revoir), poor (faux ou contredit), unknown (rien pour juger)
 *   worst_grade : la plus mauvaise des quatre (unknown passe après fair, avant good)
 *   score       : 0 (tout sûr) à 100 (tout faux), pondéré par la longueur pour une ligne
 *   evidence    : preuves de chaque note (sources comparées, distances, défauts), codes stables
 *                 décrits dans App\Services\SegmentAudit\AuditGrader
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('segment_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('era_id')->constrained()->cascadeOnDelete();
            $table->foreignId('line_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('segment_id')->nullable();
            $table->string('scope', 8);
            $table->string('status', 20)->nullable();
            $table->string('traffic_type', 20)->nullable();
            $table->string('trace_source', 40)->nullable();
            $table->integer('length_m')->default(0);
            $table->string('classification_grade', 8);
            $table->string('placement_grade', 8);
            $table->string('accuracy_grade', 8);
            $table->string('trace_grade', 8);
            $table->string('worst_grade', 8);
            $table->unsignedSmallInteger('score')->default(0);
            $table->jsonb('evidence')->default('{}');
            $table->geometry('geom', 'linestring', 4326)->nullable();
            $table->string('rule_version', 16);
            $table->timestamp('audited_at');
            $table->timestamps();
            $table->index(['line_id', 'era_id']);
            $table->index(['era_id', 'scope', 'worst_grade']);
            $table->index('segment_id');
        });

        // Une seule synthèse par ligne et par ère, un seul audit par tronçon.
        DB::statement("CREATE UNIQUE INDEX segment_audits_line_era_unique ON segment_audits (era_id, line_id) WHERE scope = 'line'");
        DB::statement("CREATE UNIQUE INDEX segment_audits_segment_unique ON segment_audits (segment_id) WHERE scope = 'segment'");
        DB::statement("ALTER TABLE segment_audits ADD CONSTRAINT segment_audits_scope_check CHECK (scope IN ('segment', 'line'))");
        foreach (['classification_grade', 'placement_grade', 'accuracy_grade', 'trace_grade', 'worst_grade'] as $column) {
            DB::statement("ALTER TABLE segment_audits ADD CONSTRAINT segment_audits_{$column}_check CHECK ({$column} IN ('good', 'fair', 'poor', 'unknown'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('segment_audits');
    }
};
