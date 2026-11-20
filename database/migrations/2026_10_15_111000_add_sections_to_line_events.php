<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Événements datés PAR PORTION de ligne, pour découper correctement le réseau
 * par décennie (une ligne ouverte en entier en 1970 puis fermée en partie en
 * 1980 ne doit être active en 1980 que sur la partie restée ouverte), et
 * suivre le trafic par période (voyageurs + fret → fret seul…).
 *
 * Contrat commun à tous les importeurs d'événements (Wikipédia,
 * lignes-oubliees, OpenHistoricalMap, Wikidata…) :
 *   - type : cf. App\Enums\LineEventType (closure_passenger / closure_freight /
 *     closure = tout trafic, reopening_*, opening_*, dismantling…) ;
 *   - event_date + date_precision (day | month | year) : une date connue à
 *     l'année est stockée au 1er janvier avec precision = 'year' ;
 *   - portion concernée, telle que la source la décrit : section_from /
 *     section_to (noms de lieux) et/ou section_pk_start_m / section_pk_end_m ;
 *     les deux à NULL = toute la ligne ;
 *   - section_geom : portion RÉSOLUE sur le tracé (rempli par le résolveur de
 *     sections), section_resolution = pending | pk | places | trace |
 *     whole_line | unresolved ;
 *   - source (clé : wikipedia | lignes-oubliees | ohm | wikidata | sncf | manual…),
 *     source_url, fingerprint = empreinte stable propre à la source (upsert idempotent).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('line_events', function (Blueprint $table): void {
            $table->string('date_precision', 5)->default('day')->after('event_date');
            $table->string('section_from')->nullable()->after('description');
            $table->string('section_to')->nullable()->after('section_from');
            $table->string('section_resolution', 20)->nullable()->after('section_pk_end_m');
            $table->string('source_url', 500)->nullable()->after('source');
            $table->foreignId('source_trace_id')->nullable()->after('source_url')
                ->constrained('source_traces')->nullOnDelete();
            $table->string('fingerprint', 64)->nullable()->unique();
            $table->index(['line_id', 'type']);
            $table->index('source');
        });
        DB::statement('ALTER TABLE line_events ADD COLUMN section_geom geometry(MultiLineString, 4326)');
        DB::statement('CREATE INDEX line_events_section_geom_gist ON line_events USING GIST (section_geom)');
        DB::statement("ALTER TABLE line_events ADD CONSTRAINT line_events_date_precision_check CHECK (date_precision IN ('day', 'month', 'year'))");
        // Les dates historiques importées au 1er janvier étaient en réalité des années.
        DB::statement("UPDATE line_events SET date_precision = 'year' WHERE EXTRACT(MONTH FROM event_date) = 1 AND EXTRACT(DAY FROM event_date) = 1");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE line_events DROP CONSTRAINT IF EXISTS line_events_date_precision_check');
        DB::statement('DROP INDEX IF EXISTS line_events_section_geom_gist');
        Schema::table('line_events', function (Blueprint $table): void {
            $table->dropIndex(['line_id', 'type']);
            $table->dropIndex(['source']);
            $table->dropUnique(['fingerprint']);
            $table->dropConstrainedForeignId('source_trace_id');
            $table->dropColumn(['date_precision', 'section_from', 'section_to', 'section_resolution', 'source_url', 'fingerprint', 'section_geom']);
        });
    }
};
