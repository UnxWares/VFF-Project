<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Nouvelle provenance d'altitude des points du profil en long : `rge_track`, altitude de la voie
 * estimée d'après le terrain (RGE ALTI : plateforme repérée sur une coupe en travers, lissage le
 * long de la ligne ; App\Services\Profiles\TrackFromTerrain, option --estimate-track de
 * vff:compute-line-profiles), distincte de `bdtopo` (voie mesurée) et de `rge_alti` (terrain).
 *
 * Réversible : `down()` ramène les points estimés à `rge_alti` (c'est bien une altitude tirée du
 * terrain) avant de remettre la contrainte d'origine.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE line_profile_points DROP CONSTRAINT line_profile_points_source_check');
        DB::statement('ALTER TABLE line_profile_points ALTER COLUMN source TYPE varchar(12)');
        DB::statement("ALTER TABLE line_profile_points ADD CONSTRAINT line_profile_points_source_check CHECK (source IN ('bdtopo', 'rge_alti', 'rge_track'))");
        DB::statement(<<<'SQL'
            COMMENT ON COLUMN line_profile_points.source IS 'Provenance de l''altitude mesurée : bdtopo (voie, 3D), rge_alti (terrain) ou rge_track (voie estimée d''après le terrain) ; null si interpolée, absente ou inconnue (profil antérieur au 21/11/2026)'
            SQL);
    }

    public function down(): void
    {
        DB::table('line_profile_points')->where('source', 'rge_track')->update(['source' => 'rge_alti']);
        DB::statement('ALTER TABLE line_profile_points DROP CONSTRAINT line_profile_points_source_check');
        DB::statement('ALTER TABLE line_profile_points ALTER COLUMN source TYPE varchar(8)');
        DB::statement("ALTER TABLE line_profile_points ADD CONSTRAINT line_profile_points_source_check CHECK (source IN ('bdtopo', 'rge_alti'))");
        DB::statement(<<<'SQL'
            COMMENT ON COLUMN line_profile_points.source IS 'Provenance de l''altitude mesurée : bdtopo (voie, 3D) ou rge_alti (terrain) ; null si interpolée, absente ou inconnue (profil antérieur au 21/11/2026)'
            SQL);
    }
};
