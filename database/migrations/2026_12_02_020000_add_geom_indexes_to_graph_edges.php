<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Index GiST sur le tracé des arêtes du graphe (`network_edges.geom`, `station_edges.geom`), pour
 * les tuiles de la carte d'administration (App\Services\NetworkGraph\GraphTiles, filtre
 * `geom && emprise de la tuile`). Sans index, chaque tuile relisait toutes les arêtes de l'ère par
 * l'index (era_id, from_node_id) avant de filtrer sur l'emprise. Les nœuds ont déjà le leur
 * (network_nodes_geom_gist).
 *
 * EraGraphBuilder remplace le graphe d'une ère par DELETE puis INSERT, sans supprimer les tables :
 * les index restent en place d'une reconstruction à l'autre.
 *
 * CONCURRENTLY : la création ne bloque pas les lectures ni une reconstruction en cours. PostgreSQL
 * l'interdit dans une transaction, d'où `$withinTransaction = false`.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS network_edges_geom_gist ON network_edges USING gist (geom)');
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS station_edges_geom_gist ON station_edges USING gist (geom)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS station_edges_geom_gist');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS network_edges_geom_gist');
    }
};
