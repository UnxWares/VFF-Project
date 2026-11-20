<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fermetures imprécises d'une gare (vff:import-station-closures) :
 *   decade = « fermée dans les années 1980 » (date = 1980-01-01) ;
 *   after  = « fermée dans la deuxième moitié du XXe siècle » : la gare était
 *            ouverte à cette date, fermeture postérieure inconnue ;
 *   before = « fermée avant 1984 » : fermée au plus tard à cette date.
 * varchar(5) ne contenait pas « decade » / « before ».
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE station_events ALTER COLUMN date_precision TYPE varchar(10)');
        DB::statement("COMMENT ON COLUMN station_events.date_precision IS 'day | month | year | decade | after | before'");
    }

    public function down(): void
    {
        DB::statement("UPDATE station_events SET date_precision = 'year' WHERE date_precision IN ('decade', 'after', 'before')");
        DB::statement('ALTER TABLE station_events ALTER COLUMN date_precision TYPE varchar(5)');
    }
};
