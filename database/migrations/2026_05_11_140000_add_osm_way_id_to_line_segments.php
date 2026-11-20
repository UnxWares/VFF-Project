<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('line_segments', function (Blueprint $table): void {
            // OSM way id du tronçon source, si import depuis OpenStreetMap.
            // Null pour les segments seedés à la main. Permet l'idempotence
            // des imports et la mise à jour de la géométrie d'un way précis.
            $table->bigInteger('osm_way_id')->nullable()->after('era_id');
            $table->index(['line_id', 'era_id', 'osm_way_id']);
        });
    }

    public function down(): void
    {
        Schema::table('line_segments', function (Blueprint $table): void {
            $table->dropIndex(['line_id', 'era_id', 'osm_way_id']);
            $table->dropColumn('osm_way_id');
        });
    }
};
