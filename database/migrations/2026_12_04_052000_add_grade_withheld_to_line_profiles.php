<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Déclivité la plus forte publiée seulement quand elle est mesurée sur la voie (décision du 21/11/2026) :
 * au moins les trois quarts des points de la fenêtre pris dans la BD TOPO, au plus un quart en tunnel ou
 * interpolés. Sinon la valeur et ses bornes sont nulles et `max_grade_withheld` dit pourquoi
 * (`terrain_only`, `tunnel`, `interpolated`). Réversible : `down()` remet 0 là où rien n'était publié.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Nullabilité changée en SQL brut : `->change()` réécrirait la colonne et la contrainte
        // line_profiles_direction_check, qui ne reviendrait pas à l'identique au retour arrière.
        foreach (['max_grade_permil', 'max_grade_from_m', 'max_grade_to_m', 'max_grade_direction'] as $column) {
            DB::statement("ALTER TABLE line_profiles ALTER COLUMN {$column} DROP NOT NULL");
        }
        Schema::table('line_profiles', function (Blueprint $table): void {
            $table->string('max_grade_withheld', 16)->nullable()->after('max_grade_direction')->comment('Motif de non-publication de la déclivité : terrain_only, tunnel, interpolated');
        });
        DB::statement("ALTER TABLE line_profiles ADD CONSTRAINT line_profiles_grade_withheld_check CHECK (max_grade_withheld IN ('terrain_only', 'tunnel', 'interpolated'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE line_profiles DROP CONSTRAINT line_profiles_grade_withheld_check');
        DB::table('line_profiles')->whereNull('max_grade_permil')->update([
            'max_grade_permil' => 0, 'max_grade_from_m' => 0, 'max_grade_to_m' => 0, 'max_grade_direction' => 'up',
        ]);
        Schema::table('line_profiles', function (Blueprint $table): void {
            $table->dropColumn('max_grade_withheld');
        });
        foreach (['max_grade_permil', 'max_grade_from_m', 'max_grade_to_m', 'max_grade_direction'] as $column) {
            DB::statement("ALTER TABLE line_profiles ALTER COLUMN {$column} SET NOT NULL");
        }
    }
};
