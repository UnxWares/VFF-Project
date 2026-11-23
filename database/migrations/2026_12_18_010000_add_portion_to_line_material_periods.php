<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Portion de la ligne parcourue pendant une période de circulation, placée sur le tracé quand la source la
 * nomme (calculée par `vff:import-material-periods`, App\Services\Materials\MaterialPeriodPortion), comme les
 * faits de l'historique de la signalisation (`line_signalling_events`) :
 *   section_location     : stations (gares d'extrémité de la ligne, PK calés de `station_lines`), landmarks
 *                          (un point remarquable de la ligne au moins, database/data/line_landmarks.php),
 *                          line (la portion couvre la ligne entière), unlocated (titre de section dont les
 *                          lieux sont introuvables ou ambigus) ; null quand la source ne nomme aucune portion ;
 *   from_station_id / to_station_id : gares d'extrémité retenues, dans l'ordre des PK (`stations`) ;
 *   located_pk_start_m / located_pk_end_m : PK de la portion (ceux des gares ou des points), jamais inventés.
 * Une période sans portion vaut pour la ligne, sans précision. Ajout seulement : rien n'est retiré.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('line_material_periods', function (Blueprint $table): void {
            $table->string('section_location', 10)->nullable()->after('section_to');
            $table->foreignId('from_station_id')->nullable()->after('section_location')->constrained('stations')->nullOnDelete();
            $table->foreignId('to_station_id')->nullable()->after('from_station_id')->constrained('stations')->nullOnDelete();
            $table->integer('located_pk_start_m')->nullable()->after('to_station_id');
            $table->integer('located_pk_end_m')->nullable()->after('located_pk_start_m');
        });
    }

    public function down(): void
    {
        Schema::table('line_material_periods', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('from_station_id');
            $table->dropConstrainedForeignId('to_station_id');
            $table->dropColumn(['section_location', 'located_pk_start_m', 'located_pk_end_m']);
        });
    }
};
