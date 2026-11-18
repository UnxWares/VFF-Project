<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Graphe ferroviaire PAR DÉCENNIE (vff:build-network-graph) :
 *
 *   - network_nodes : nature du nœud (extrémité, jonction, gare ouverte,
 *     gare fermée traversée, raccord de lacune) ;
 *   - network_edges : géométrie de chaque tronçon élémentaire (portion de
 *     line_segments entre deux nœuds) + trafic, patrimoine, écartement ;
 *   - station_edges : géométrie gare-à-gare, lignes empruntées dans l'ordre,
 *     longueurs sans service voyageurs / associatives, raccords de lacunes ;
 *   - network_graph_builds : mesures de chaque construction (temps, nœuds,
 *     arêtes, composantes connexes, gares non reliées).
 *
 * Les tables du graphe sont DÉRIVÉES et reconstruites ère par ère, en bloc.
 * Cette migration ne verrouille QUE les tables du graphe : les clés
 * étrangères vers network_nodes sont retirées, et chaque colonne encore
 * référencée (tronçon, ligne, gare) reçoit un index, pour que les cascades
 * déclenchées par le moteur décennal ou le dédoublonnage restent rapides.
 * Le retrait des clés vers line_segments / lines (tables très sollicitées)
 * est fait à part, avec un délai de verrou court :
 * 2026_10_19_170100_drop_graph_foreign_keys_to_hot_tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Jamais d'attente derrière une longue requête d'un autre traitement.
        DB::statement("SET LOCAL lock_timeout = '5s'");
        foreach (['network_edges_from_node_id_foreign', 'network_edges_to_node_id_foreign'] as $fk) {
            DB::statement("ALTER TABLE network_edges DROP CONSTRAINT IF EXISTS {$fk}");
        }
        DB::statement('CREATE INDEX IF NOT EXISTS network_edges_segment_idx ON network_edges (segment_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS network_edges_line_idx ON network_edges (line_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS station_edges_line_idx ON station_edges (line_id)');

        Schema::table('network_nodes', function (Blueprint $table): void {
            $table->string('kind', 16)->nullable()
                ->comment('end | link | junction | station | closed_station | gap');
        });

        Schema::table('network_edges', function (Blueprint $table): void {
            $table->boolean('is_heritage')->default(false);
            $table->boolean('is_connector')->default(false)
                ->comment('Raccord de lacune entre deux tracés (aucun tronçon source)');
            $table->string('traffic_type', 20)->nullable();
            $table->string('network', 20)->nullable()->comment('lines.network au moment du build');
            $table->smallInteger('gauge_mm')->nullable();
        });
        DB::statement('ALTER TABLE network_edges ADD COLUMN geom geometry(LineString, 4326)');

        Schema::table('station_edges', function (Blueprint $table): void {
            $table->integer('non_passenger_m')->default(0)
                ->comment('Longueur sur des voies sans service voyageurs (fret seul, réseau militaire ou particulier)');
            $table->integer('heritage_m')->default(0)->comment('Longueur sur des voies exploitées par une association');
            $table->smallInteger('gap_joins')->default(0)->comment('Raccords de lacunes de tracé empruntés');
        });
        DB::statement('ALTER TABLE station_edges ADD COLUMN line_ids bigint[]');
        DB::statement('ALTER TABLE station_edges ADD COLUMN line_lengths integer[]');
        DB::statement('ALTER TABLE station_edges ADD COLUMN gauges smallint[]');
        DB::statement('ALTER TABLE station_edges ADD COLUMN geom geometry(MultiLineString, 4326)');
        DB::statement('CREATE INDEX IF NOT EXISTS station_edges_from_station_idx ON station_edges (from_station_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS station_edges_to_station_idx ON station_edges (to_station_id)');

        Schema::create('network_graph_builds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('era_id')->unique()->constrained()->cascadeOnDelete();
            $table->timestamp('built_at');
            $table->integer('duration_ms');
            $table->string('stations_source', 20)->comment('station_eras | chronology (repli : dates des gares)');
            $table->integer('segments');
            $table->integer('nodes');
            $table->integer('edges');
            $table->integer('station_nodes')->comment('Gares ouvertes rattachées au réseau en service');
            $table->integer('closed_station_nodes');
            $table->integer('station_edges');
            $table->integer('heritage_station_edges');
            $table->integer('gap_joins');
            $table->integer('open_stations')->comment('Gares ouvertes à cette ère (station_eras)');
            $table->integer('unattached_stations')->comment('Gares ouvertes sans voie en service à proximité');
            $table->integer('isolated_stations')->comment('Gares rattachées mais sans aucune gare voisine');
            $table->integer('components')->comment('Composantes connexes (gares ouvertes rattachées, associatif compris)');
            $table->integer('largest_component')->comment('Gares de la plus grande composante');
            $table->jsonb('params')->nullable();
            $table->jsonb('timings')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_graph_builds');
        DB::statement('DROP INDEX IF EXISTS network_edges_segment_idx');
        DB::statement('DROP INDEX IF EXISTS network_edges_line_idx');
        DB::statement('DROP INDEX IF EXISTS station_edges_line_idx');
        DB::statement('DROP INDEX IF EXISTS station_edges_from_station_idx');
        DB::statement('DROP INDEX IF EXISTS station_edges_to_station_idx');
        Schema::table('station_edges', function (Blueprint $table): void {
            $table->dropColumn(['non_passenger_m', 'heritage_m', 'gap_joins', 'line_ids', 'line_lengths', 'gauges', 'geom']);
        });
        Schema::table('network_edges', function (Blueprint $table): void {
            $table->dropColumn(['is_heritage', 'is_connector', 'traffic_type', 'network', 'gauge_mm', 'geom']);
        });
        Schema::table('network_nodes', function (Blueprint $table): void {
            $table->dropColumn('kind');
        });
        // Les clés vers network_nodes ne sont pas recréées : le graphe est dérivé
        // et reconstruit en bloc, ère par ère.
    }
};
