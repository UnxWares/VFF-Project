<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hybridation des 2 modèles de graphe :
 *
 *   COUCHE 1 (topologique, sub-mètre) - pour pathfinding fin, PK,
 *   recherches « à partir d'un point lat/lng » :
 *      `network_nodes`  : 1 nœud par endpoint de segment (snap 30m)
 *      `network_edges`  : 1 arête par segment, pondéré par longueur
 *
 *   COUCHE 2 (gare-à-gare, sémantique voyageur) - pour API publique,
 *   itinéraires « combien de gares entre A et B » :
 *      `station_edges`  : 1 arête entre 2 gares consécutives sur une
 *                        ligne, avec saut des gares déclassées.
 *
 * Cette migration renomme l'actuel `network_edges` (gare-à-gare) en
 * `station_edges`, puis recrée `network_nodes` et un nouveau
 * `network_edges` topologique. Les deux sont reconstruits par
 * `vff:build-network-graph` ensuite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('network_edges', 'station_edges');
        DB::statement('ALTER INDEX network_edges_pkey RENAME TO station_edges_pkey');
        DB::statement('ALTER INDEX network_edges_unique RENAME TO station_edges_unique');

        Schema::create('network_nodes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('era_id')->constrained()->cascadeOnDelete();
            $table->foreignId('station_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
        DB::statement('ALTER TABLE network_nodes ADD COLUMN geom geometry(Point, 4326) NOT NULL');
        DB::statement('CREATE INDEX network_nodes_geom_gist ON network_nodes USING GIST (geom)');
        DB::statement('CREATE INDEX network_nodes_era_idx ON network_nodes (era_id)');
        DB::statement('CREATE INDEX network_nodes_station_idx ON network_nodes (station_id) WHERE station_id IS NOT NULL');

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

    public function down(): void
    {
        Schema::dropIfExists('network_edges');
        Schema::dropIfExists('network_nodes');
        Schema::rename('station_edges', 'network_edges');
        DB::statement('ALTER INDEX station_edges_pkey RENAME TO network_edges_pkey');
        DB::statement('ALTER INDEX station_edges_unique RENAME TO network_edges_unique');
    }
};
