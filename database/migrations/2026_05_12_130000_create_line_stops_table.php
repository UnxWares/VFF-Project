<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `line_stops` : ordre des gares sur chaque ligne, par ère.
 *
 * Inspiré du modèle GTFS `stop_times.txt` (sans horaires - VFF est un
 * référentiel d'infrastructure, pas un planificateur).
 *
 * Source de vérité dérivée de :
 *   - position spatiale (ST_LineLocatePoint sur tracé fusionné)
 *   - statut effectif via `station_eras` (fallback `stations.status`)
 *
 * Sert de pivot pour :
 *   - fiche ligne : liste ordonnée des arrêts avec PK
 *   - construction du graphe `station_edges` (paires consécutives actives)
 *   - API publique GET /api/v1/lines/{slug}/stops?era=2030
 *
 * Reconstruit par `vff:build-network-graph`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('line_stops', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('line_id')->constrained()->cascadeOnDelete();
            $table->foreignId('era_id')->constrained()->cascadeOnDelete();
            $table->foreignId('station_id')->constrained()->cascadeOnDelete();
            $table->integer('position')->comment('Rang dans la séquence (0..N-1)');
            $table->integer('pk_m')->comment('Distance le long du tracé depuis l\'origine (m)');
            $table->string('effective_status', 20)
                ->comment('Statut de la gare à cette ère : active|tourist|no_commercial|declassified');
            $table->boolean('is_terminal')->default(false)
                ->comment('Vraie pour la première et la dernière gare de la séquence');
            $table->timestamps();

            $table->unique(['line_id', 'era_id', 'station_id']);
            $table->index(['line_id', 'era_id', 'position']);
            $table->index(['era_id', 'effective_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('line_stops');
    }
};
