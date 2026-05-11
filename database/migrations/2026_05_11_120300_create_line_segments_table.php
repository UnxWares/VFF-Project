<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('line_segments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('line_id')->constrained()->cascadeOnDelete();
            $table->foreignId('era_id')->constrained()->cascadeOnDelete();
            $table->magellanLineString('geom', srid: 4326);
            $table->string('status')->comment('in_service | single_track | abandoned | dismantled | razed');
            $table->integer('length_meters')->nullable()->comment('Cache de ST_Length pour la perf');
            $table->smallInteger('max_speed_kmh')->nullable();
            $table->boolean('electrified')->nullable();
            $table->smallInteger('voltage_v')->nullable()->comment('25000, 1500, 750, …');
            $table->smallInteger('gauge_mm')->default(1435)->comment('1435 standard, 1000 métrique, …');
            $table->timestamps();

            $table->index(['line_id', 'era_id']);
            $table->index('status');
        });

        // Index GIST sur la colonne géo pour les requêtes spatiales (ST_Intersects, ST_DWithin, etc.)
        DB::statement('CREATE INDEX line_segments_geom_idx ON line_segments USING GIST (geom)');
    }

    public function down(): void
    {
        Schema::dropIfExists('line_segments');
    }
};
