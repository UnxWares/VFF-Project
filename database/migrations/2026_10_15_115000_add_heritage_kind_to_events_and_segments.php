<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Portions exploitées par des ASSOCIATIONS (trains touristiques, vélorails,
 * musées), datées par portion comme le reste.
 *
 * Événements `heritage_start` / `heritage_end` (App\Enums\LineEventType)
 * portant `heritage_kind` ; le moteur décennal en déduit, pour chaque ère,
 * line_segments.is_heritage + heritage_kind (train touristique : en service ;
 * vélorail : plus de trains, voie en place). L'ancien drapeau manuel par ligne
 * entière (vff:flag-heritage) était effacé à chaque régénération.
 */
return new class extends Migration
{
    private const VALUES = "('tourist_train', 'velorail', 'museum')";

    public function up(): void
    {
        Schema::table('line_events', function (Blueprint $table): void {
            $table->string('heritage_kind', 20)->nullable()->after('reuse')
                ->comment('tourist_train | velorail | museum');
        });
        Schema::table('line_segments', function (Blueprint $table): void {
            $table->string('heritage_kind', 20)->nullable()->after('is_heritage');
        });
        DB::statement('ALTER TABLE line_events ADD CONSTRAINT line_events_heritage_kind_check CHECK (heritage_kind IS NULL OR heritage_kind IN '.self::VALUES.')');
        DB::statement('ALTER TABLE line_segments ADD CONSTRAINT line_segments_heritage_kind_check CHECK (heritage_kind IS NULL OR heritage_kind IN '.self::VALUES.')');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE line_segments DROP CONSTRAINT IF EXISTS line_segments_heritage_kind_check');
        DB::statement('ALTER TABLE line_events DROP CONSTRAINT IF EXISTS line_events_heritage_kind_check');
        Schema::table('line_segments', fn (Blueprint $t) => $t->dropColumn('heritage_kind'));
        Schema::table('line_events', fn (Blueprint $t) => $t->dropColumn('heritage_kind'));
    }
};
