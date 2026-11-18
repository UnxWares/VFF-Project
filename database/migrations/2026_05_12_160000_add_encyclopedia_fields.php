<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Schema encyclopédie multi-sources.
 *
 * Sur `lines` : caractéristiques structurées extraites de Wikipedia /
 * archeoferroviaire / SNCF. Trace de l'URL source pour rebuild facile.
 *
 * Sur `line_events` : section concernée + citation source + niveau de
 * confiance (utile pour distinguer fait certain vs estimation).
 *
 * Nouvelle table `line_sources` : trace **pour chaque champ** de la
 * ligne, sa source. Permet d'auditer ce qui vient d'où et de gérer
 * les conflits multi-sources.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lines', function (Blueprint $table): void {
            $table->integer('length_official_m')->nullable()->after('description')
                ->comment('Longueur officielle déclarée (Wikipedia/SNCF)');
            $table->smallInteger('gauge_mm_default')->nullable()->after('length_official_m')
                ->comment('Écartement par défaut (1435 = normale, 1000 = métrique)');
            $table->smallInteger('nb_voies_default')->nullable()->after('gauge_mm_default')
                ->comment('Nombre de voies par défaut');
            $table->smallInteger('max_speed_kmh_official')->nullable()->after('nb_voies_default')
                ->comment('Vitesse max officielle déclarée');
            $table->smallInteger('concession_year')->nullable()->after('max_speed_kmh_official');
            $table->string('wikipedia_title', 255)->nullable()->after('concession_year');
            $table->string('archeo_url', 500)->nullable()->after('wikipedia_title');
            $table->timestamp('encyclopedia_updated_at')->nullable()->after('archeo_url');
        });
        DB::statement('ALTER TABLE lines ADD COLUMN regions JSONB NULL');
        DB::statement('ALTER TABLE lines ADD COLUMN departements JSONB NULL');
        DB::statement('ALTER TABLE lines ADD COLUMN historical_operators JSONB NULL');

        Schema::table('line_events', function (Blueprint $table): void {
            $table->integer('section_pk_start_m')->nullable()->after('description');
            $table->integer('section_pk_end_m')->nullable()->after('section_pk_start_m');
            $table->text('source_quote')->nullable()->after('section_pk_end_m')
                ->comment('Citation exacte de la source (Wikipedia/archeo) pour cet event');
            $table->string('confidence', 10)->default('medium')->after('source_quote')
                ->comment('high (date précise sourcée) | medium (date approximative) | low (estimation)');
        });

        Schema::create('line_sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('line_id')->constrained()->cascadeOnDelete();
            $table->string('source_type', 30)
                ->comment('wikipedia | archeoferroviaire | sncf_open_data | osm | manual');
            $table->string('source_url', 500);
            $table->timestamp('fetched_at');
            $table->json('fields_provided')->nullable()
                ->comment('Liste des champs fournis par cette source');
            $table->timestamps();

            $table->unique(['line_id', 'source_type']);
            $table->index('source_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('line_sources');
        Schema::table('line_events', function (Blueprint $table): void {
            $table->dropColumn(['section_pk_start_m', 'section_pk_end_m', 'source_quote', 'confidence']);
        });
        Schema::table('lines', function (Blueprint $table): void {
            $table->dropColumn([
                'length_official_m', 'gauge_mm_default', 'nb_voies_default',
                'max_speed_kmh_official', 'concession_year', 'wikipedia_title',
                'archeo_url', 'encyclopedia_updated_at',
            ]);
        });
        DB::statement('ALTER TABLE lines DROP COLUMN IF EXISTS regions');
        DB::statement('ALTER TABLE lines DROP COLUMN IF EXISTS departements');
        DB::statement('ALTER TABLE lines DROP COLUMN IF EXISTS historical_operators');
    }
};
