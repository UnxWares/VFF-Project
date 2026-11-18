<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Graphe matérialisé du réseau ferroviaire pour le pathfinding.
 *
 * Stratégie : un nœud par "point de connexion" (extrémité de segment),
 * dédupliqué par snap à 30m (tolérance OSM/SNCF). Une arête par segment
 * de ligne, pondérée par la longueur en mètres.
 *
 * Le graphe est régénéré par `php artisan vff:build-network-graph`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('network_nodes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('era_id')->constrained()->cascadeOnDelete();
            $table->foreignId('station_id')->nullable()->constrained()->nullOnDelete()
                ->comment('Si le nœud coïncide avec une gare connue');
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

    public function down(): void
    {
        Schema::dropIfExists('network_edges');
        Schema::dropIfExists('network_nodes');
    }
};
