<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Refonte du graphe : modèle gare-à-gare (et non plus topologique).
 *
 * Règles :
 *   - 1 nœud = 1 gare (donc `network_nodes` disparaît, on travaille
 *     directement avec `station_id`).
 *   - Une arête relie deux gares **consécutives** sur la même ligne.
 *   - Les gares "abandonnées/déclassées" à l'ère cible sont sautées :
 *     on connecte directement la gare active précédente à la gare active
 *     suivante, en sommant la distance le long du tracé.
 *   - Poids = distance ferroviaire en mètres (ST_LineLocatePoint × longueur).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('network_edges');
        Schema::dropIfExists('network_nodes');

        Schema::create('network_edges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('era_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_station_id')->constrained('stations')->cascadeOnDelete();
            $table->foreignId('to_station_id')->constrained('stations')->cascadeOnDelete();
            $table->foreignId('line_id')->constrained('lines')->cascadeOnDelete();
            $table->integer('length_m')->comment('Distance ferroviaire entre les 2 gares (m).');
            $table->integer('skipped_count')->default(0)
                ->comment('Nombre de gares déclassées sautées entre from et to.');
            $table->timestamps();

            $table->index(['era_id', 'from_station_id']);
            $table->index(['era_id', 'to_station_id']);
            $table->index(['era_id', 'line_id']);
            $table->unique(['era_id', 'line_id', 'from_station_id', 'to_station_id'], 'network_edges_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_edges');

        // Rétablit le graphe topologique de create_network_graph_tables (tables
        // dérivées, reconstruites par vff:build-network-graph) : sans lui, le
        // rollback suivant ne trouvait plus network_nodes.
        Schema::create('network_nodes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('era_id')->constrained()->cascadeOnDelete();
            $table->foreignId('station_id')->nullable()->comment('Si le nœud coïncide avec une gare connue')
                ->constrained()->nullOnDelete();
            $table->timestamps();
        });
        DB::statement('ALTER TABLE network_nodes ADD COLUMN geom geometry(Point, 4326) NOT NULL');
        DB::statement('CREATE INDEX network_nodes_geom_gist ON network_nodes USING GIST (geom)');
        DB::statement('CREATE INDEX network_nodes_era_idx ON network_nodes (era_id)');

        Schema::create('network_edges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('era_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_node_id')->constrained('network_nodes')->cascadeOnDelete();
            $table->foreignId('to_node_id')->constrained('network_nodes')->cascadeOnDelete();
            $table->foreignId('segment_id')->nullable()->constrained('line_segments')->nullOnDelete();
            $table->foreignId('line_id')->nullable()->constrained('lines')->nullOnDelete();
            $table->integer('length_m');
            $table->string('status', 30);
            $table->timestamps();

            $table->index(['era_id', 'from_node_id']);
            $table->index(['era_id', 'to_node_id']);
        });
    }
};
