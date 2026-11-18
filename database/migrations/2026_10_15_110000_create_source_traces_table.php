<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tracés bruts multi-sources (archeoferroviaire, OpenHistoricalMap, IGN, OSM…).
 *
 * `source_traces` conserve chaque tracé TEL QU'IMPORTÉ, avec sa provenance,
 * avant toute fusion : c'est la matière première à partir de laquelle on
 * matérialise `line_segments` par ère (décennie). Les droits d'usage de chaque
 * source sont décrits dans database/data/source_rights.php.
 *
 * Ajouts associés :
 *   - lines.network : catégorie du réseau (principal = intérêt général,
 *     secondaire = intérêt local / CFD / tramways, militaire, particulier =
 *     mines, forêts, industries) ;
 *   - line_segments.source / source_trace_id : provenance du tronçon ;
 *   - line_segments.gauge_mm nullable : écartement inconnu ≠ voie normale.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('source_traces', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 40)->comment('archeoferroviaire | ohm | bdtopo | osm | contrib');
            $table->string('source_ref', 64)->comment('Identifiant stable dans la source (ou empreinte md5 si absent)');
            $table->string('layer', 40)->nullable()->comment('Calque / catégorie dans la source');
            $table->text('name')->nullable()->comment('Nom dans la source (parfois une longue liste de lieux)');
            $table->jsonb('properties')->nullable()->comment('Attributs bruts de la source');
            $table->integer('length_m')->nullable();
            $table->foreignId('line_id')->nullable()->constrained('lines')->nullOnDelete();
            $table->string('match_method', 30)->nullable()->comment('sncf_overlap | name | touching | created');
            $table->float('match_score')->nullable()->comment('Part du tracé recouverte (0-1) quand pertinent');
            $table->smallInteger('opened_year')->nullable();
            $table->smallInteger('closed_year')->nullable();
            $table->string('dates_source', 40)->nullable()->comment('Source des dates : ohm | wikidata | name | …');
            $table->float('dates_score')->nullable()->comment('Part du tracé couverte par la source des dates');
            $table->timestamp('seen_at')->comment('Dernière fois que la source a renvoyé ce tracé');
            $table->timestamps();

            $table->unique(['source', 'source_ref']);
            $table->index(['source', 'layer']);
            $table->index('line_id');
        });
        DB::statement('ALTER TABLE source_traces ADD COLUMN geom geometry(MultiLineString, 4326) NOT NULL');
        DB::statement('CREATE INDEX source_traces_geom_gist ON source_traces USING GIST (geom)');

        Schema::table('lines', function (Blueprint $table): void {
            $table->string('network', 20)->nullable()->index()
                ->comment('principal | secondaire | militaire | particulier');
        });
        DB::statement("ALTER TABLE lines ADD CONSTRAINT lines_network_check CHECK (network IN ('principal', 'secondaire', 'militaire', 'particulier'))");
        // Les lignes déjà en base viennent du RFN (codes SNCF) : intérêt général.
        DB::statement("UPDATE lines SET network = 'principal' WHERE code IS NOT NULL");

        Schema::table('line_segments', function (Blueprint $table): void {
            $table->string('source', 40)->nullable()->index()
                ->comment('Provenance du tronçon : osm | sncf | archeoferroviaire | …');
            $table->foreignId('source_trace_id')->nullable()->constrained('source_traces')->nullOnDelete();
        });
        DB::statement('ALTER TABLE line_segments ALTER COLUMN gauge_mm DROP NOT NULL');
    }

    public function down(): void
    {
        DB::statement('UPDATE line_segments SET gauge_mm = 1435 WHERE gauge_mm IS NULL');
        DB::statement('ALTER TABLE line_segments ALTER COLUMN gauge_mm SET NOT NULL');
        Schema::table('line_segments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('source_trace_id');
            $table->dropColumn('source');
        });
        DB::statement('ALTER TABLE lines DROP CONSTRAINT IF EXISTS lines_network_check');
        Schema::table('lines', function (Blueprint $table): void {
            $table->dropColumn('network');
        });
        Schema::dropIfExists('source_traces');
    }
};
