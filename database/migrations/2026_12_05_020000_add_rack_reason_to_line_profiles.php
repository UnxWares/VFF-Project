<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Nouveau motif de non-publication de la déclivité : `rack`, ligne à crémaillère
 * (`database/data/rack_railways.php`). La déclivité mesurée n'y veut rien dire (le calcul écarte toute
 * pente au-delà de 120 ‰) ; le profil donne la valeur publiée par la source. Réversible : `down()`
 * range ces lignes sous `terrain_only` (déclivité toujours non publiée), puis remet la contrainte d'avant.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE line_profiles DROP CONSTRAINT line_profiles_grade_withheld_check');
        DB::statement("ALTER TABLE line_profiles ADD CONSTRAINT line_profiles_grade_withheld_check CHECK (max_grade_withheld IN ('terrain_only', 'tunnel', 'interpolated', 'rack'))");
        DB::statement("COMMENT ON COLUMN line_profiles.max_grade_withheld IS 'Motif de non-publication de la déclivité : terrain_only, tunnel, interpolated, rack'");
    }

    public function down(): void
    {
        DB::table('line_profiles')->where('max_grade_withheld', 'rack')->update(['max_grade_withheld' => 'terrain_only']);
        DB::statement('ALTER TABLE line_profiles DROP CONSTRAINT line_profiles_grade_withheld_check');
        DB::statement("ALTER TABLE line_profiles ADD CONSTRAINT line_profiles_grade_withheld_check CHECK (max_grade_withheld IN ('terrain_only', 'tunnel', 'interpolated'))");
        DB::statement("COMMENT ON COLUMN line_profiles.max_grade_withheld IS 'Motif de non-publication de la déclivité : terrain_only, tunnel, interpolated'");
    }
};
