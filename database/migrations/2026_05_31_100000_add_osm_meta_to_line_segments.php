<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conserve les tags OSM discriminants ignorés jusqu'ici (corroboration de la
 * classification trafic/LGV) : railway:traffic_mode (passenger/freight/mixed),
 * highspeed (LGV), usage (main/branch/industrial/tourism). Peuplés à l'import OSM.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('line_segments', function (Blueprint $table): void {
            $table->string('osm_traffic_mode', 16)->nullable()->after('operator');
            $table->boolean('osm_highspeed')->default(false)->after('osm_traffic_mode');
            $table->string('osm_usage', 24)->nullable()->after('osm_highspeed');
        });
    }

    public function down(): void
    {
        Schema::table('line_segments', function (Blueprint $table): void {
            $table->dropColumn(['osm_traffic_mode', 'osm_highspeed', 'osm_usage']);
        });
    }
};
