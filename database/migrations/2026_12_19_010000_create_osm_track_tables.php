<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Detailed track layout from OpenStreetMap (ODbL), written by vff:import-osm-track-detail from the France extract
 * filtered by bin/osm-track-detail. ADDITIVE tables, replaced line by line by each real import (no other command
 * writes them); the layout of today only (current era):
 *
 * - `osm_tracks`: an OSM way (or the stretch of it) attached to a VFF line in service: kind (running, siding, loop,
 *   yard, crossover, spur), track number as tagged (never guessed), PK range on the line chainage, median lateral
 *   offset to the reference trace (positive on the left facing increasing PKs), how it was attached (code of the
 *   way, corridor along the trace, station area) and its geometry;
 * - `osm_track_nodes`: switches, diamond crossings, buffer stops, level crossings, signals and milestones on those
 *   tracks, with their PK (`exact` when the node carries the PK of the line itself), the ways they join and the
 *   direction they face;
 * - `osm_track_imports`: one row per import, with the date of the newest OSM object read (date of the data).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('osm_track_imports', function (Blueprint $table): void {
            $table->id();
            $table->string('file', 255)->comment('Filtered OPL file read');
            $table->timestamp('data_at')->nullable()->comment('Newest OSM object timestamp of the file (date of the data)');
            $table->unsignedInteger('lines')->default(0);
            $table->unsignedInteger('tracks')->default(0);
            $table->unsignedInteger('nodes')->default(0);
            $table->timestamps();
        });

        Schema::create('osm_tracks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('line_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('osm_way_id');
            $table->smallInteger('part')->default(0)->comment('Stretch of the way on this line (0, 1...)');
            $table->string('kind', 10)->comment('running, siding, loop, yard, crossover or spur');
            $table->string('ref', 12)->nullable()->comment('Track number from railway:track_ref, normalised, never guessed');
            $table->string('service', 20)->nullable();
            $table->string('usage', 20)->nullable();
            $table->integer('pk_from_m')->nullable();
            $table->integer('pk_to_m')->nullable();
            $table->boolean('pk_up')->nullable()->comment('The way runs towards increasing PKs');
            $table->string('pk_precision', 12)->nullable()->comment('derived or approximate');
            $table->integer('length_m');
            $table->float('offset_m')->nullable()->comment('Median lateral offset to the reference trace, positive on the left facing increasing PKs');
            $table->boolean('electrified')->nullable();
            $table->integer('voltage_v')->nullable();
            $table->smallInteger('maxspeed_kmh')->nullable();
            $table->smallInteger('gauge_mm')->nullable();
            $table->foreignId('station_id')->nullable()->constrained()->nullOnDelete();
            $table->string('attach', 10)->comment('code, corridor or station');
            $table->bigInteger('start_node_id')->nullable();
            $table->bigInteger('end_node_id')->nullable();
            $table->jsonb('tags');
            $table->timestamps();
            $table->unique(['line_id', 'osm_way_id', 'part']);
            $table->index(['line_id', 'pk_from_m']);
        });
        DB::statement('ALTER TABLE osm_tracks ADD COLUMN geom geometry(LineString, 4326) NOT NULL');

        Schema::create('osm_track_nodes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('line_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('osm_node_id');
            $table->string('kind', 20)->comment('switch, railway_crossing, buffer_stop, level_crossing, crossing, signal or milestone');
            $table->string('subtype', 30)->nullable()->comment('Switch type, signal category, road or pedestrian crossing');
            $table->string('detail', 60)->nullable()->comment('Signal form (railway:signal:<category> value)');
            $table->string('ref', 40)->nullable();
            $table->integer('pk_m')->nullable();
            $table->string('pk_precision', 12)->nullable()->comment('exact, derived or approximate');
            $table->integer('osm_pk_m')->nullable()->comment('PK carried by the node (railway:position:exact, milestone)');
            $table->bigInteger('way_id')->nullable()->comment('Way the node stands on (toe side of a switch)');
            $table->bigInteger('to_way_id')->nullable()->comment('Way a switch leads to, second way of a crossing');
            $table->string('direction', 4)->nullable()->comment('up or down: trains facing a switch, trains a signal is for');
            $table->jsonb('tags');
            $table->timestamps();
            $table->unique(['line_id', 'osm_node_id']);
            $table->index(['line_id', 'kind', 'pk_m']);
        });
        DB::statement('ALTER TABLE osm_track_nodes ADD COLUMN geom geometry(Point, 4326) NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('osm_track_nodes');
        Schema::dropIfExists('osm_tracks');
        Schema::dropIfExists('osm_track_imports');
    }
};
