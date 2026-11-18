<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // smallint (max 32767) trop étroit pour certaines valeurs OSM aberrantes
        // ou les tensions HVDC (rare, mais possible). On passe en integer (4 bytes).
        DB::statement('ALTER TABLE line_segments ALTER COLUMN voltage_v TYPE integer');
        DB::statement('ALTER TABLE line_segments ALTER COLUMN max_speed_kmh TYPE integer');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE line_segments ALTER COLUMN voltage_v TYPE smallint');
        DB::statement('ALTER TABLE line_segments ALTER COLUMN max_speed_kmh TYPE smallint');
    }
};
