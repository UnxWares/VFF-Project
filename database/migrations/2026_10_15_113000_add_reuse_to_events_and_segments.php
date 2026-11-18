<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reconversion de l'emprise d'une ligne déposée (voie verte, route…).
 *
 * Un événement `conversion` (App\Enums\LineEventType) porte `reuse` et rend la
 * portion DÉPOSÉE à partir de sa date ; le moteur décennal recopie `reuse` sur
 * line_segments pour l'affichage (« déposée, devenue voie verte »). Les libellés
 * SNCF « Fermée » / « Déclassée » ne prouvent pas que la voie est encore en
 * place : la preuve de terrain (OSM, lignes-oubliees, IGN) prime.
 */
return new class extends Migration
{
    private const VALUES = "('greenway', 'road', 'path', 'urban', 'other')";

    public function up(): void
    {
        Schema::table('line_events', function (Blueprint $table): void {
            $table->string('reuse', 20)->nullable()->after('type')
                ->comment('greenway (voie verte) | road | path | urban | other');
        });
        Schema::table('line_segments', function (Blueprint $table): void {
            $table->string('reuse', 20)->nullable()->after('status');
        });
        DB::statement('ALTER TABLE line_events ADD CONSTRAINT line_events_reuse_check CHECK (reuse IS NULL OR reuse IN '.self::VALUES.')');
        DB::statement('ALTER TABLE line_segments ADD CONSTRAINT line_segments_reuse_check CHECK (reuse IS NULL OR reuse IN '.self::VALUES.')');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE line_segments DROP CONSTRAINT IF EXISTS line_segments_reuse_check');
        DB::statement('ALTER TABLE line_events DROP CONSTRAINT IF EXISTS line_events_reuse_check');
        Schema::table('line_segments', fn (Blueprint $t) => $t->dropColumn('reuse'));
        Schema::table('line_events', fn (Blueprint $t) => $t->dropColumn('reuse'));
    }
};
