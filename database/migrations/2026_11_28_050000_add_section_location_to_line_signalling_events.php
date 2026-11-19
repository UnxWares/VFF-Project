<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Historique de la signalisation, deuxième passe : rattachement des faits au tracé, et carte par ère.
 *
 * Colonnes ajoutées à `line_signalling_events` (calculées par `vff:import-signalling-history`,
 * App\Services\Signalling\SignallingSectionLocator) :
 *   section_location     : article_pk (PK cités par l'article), stations (gares d'extrémité trouvées sur la
 *                          ligne, PK calés de `station_lines`), unlocated (lieux cités mais introuvables,
 *                          ou ambigus) ; null hors portion nommée ;
 *   from_station_id / to_station_id : gares retenues (`stations`) ;
 *   located_pk_start_m / located_pk_end_m : PK de la portion (ceux de l'article ou des gares), jamais
 *                          inventés : null si la portion n'est pas placée ;
 *   section_geom         : la portion sur les formes SNCF Réseau (PK calés sur les passages à niveau, comme
 *                          `line_signalling_map`), null si la ligne n'a pas de forme SNCF ou si la portion
 *                          n'est pas placée.
 *
 * Table `line_signalling_history_map` : ce que montre le calque « Signalisation » de /maps à une ère passée,
 * d'après les faits sûrs (high, medium) en vigueur à la fin de l'ère (App\Services\Signalling\SignallingHistoryMap).
 * Une ligne par ère et par fait dessiné : sur sa portion (location = section) ou sur toute la ligne de l'ère
 * (location = line : l'article dit toute la ligne ; unknown : portion inconnue ou non placée). L'ère courante
 * garde l'état de SNCF Réseau (`line_signalling_map`) : cette table ne la remplit jamais.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('line_signalling_events', function (Blueprint $table): void {
            $table->string('section_location', 10)->nullable()->after('pk_end_m');
            $table->foreignId('from_station_id')->nullable()->after('section_location')->constrained('stations')->nullOnDelete();
            $table->foreignId('to_station_id')->nullable()->after('from_station_id')->constrained('stations')->nullOnDelete();
            $table->integer('located_pk_start_m')->nullable()->after('to_station_id');
            $table->integer('located_pk_end_m')->nullable()->after('located_pk_start_m');
        });
        DB::statement('ALTER TABLE line_signalling_events ADD COLUMN section_geom geometry(MultiLineString, 4326)');

        Schema::create('line_signalling_history_map', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('era_id')->constrained()->cascadeOnDelete();
            $table->foreignId('line_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('line_signalling_events')->cascadeOnDelete();
            $table->string('family', 20);
            $table->string('location', 8);
            $table->integer('sort');
            $table->timestamps();
            $table->index(['era_id', 'line_id']);
        });
        DB::statement('ALTER TABLE line_signalling_history_map ADD COLUMN geom geometry(MultiLineString, 4326) NOT NULL');
        DB::statement('CREATE INDEX line_signalling_history_map_geom_gist ON line_signalling_history_map USING GIST (geom)');
    }

    public function down(): void
    {
        Schema::dropIfExists('line_signalling_history_map');
        Schema::table('line_signalling_events', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('from_station_id');
            $table->dropConstrainedForeignId('to_station_id');
            $table->dropColumn(['section_location', 'located_pk_start_m', 'located_pk_end_m', 'section_geom']);
        });
    }
};
