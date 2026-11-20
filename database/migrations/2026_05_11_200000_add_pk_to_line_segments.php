<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('line_segments', function (Blueprint $table): void {
            // PK officiels SNCF en mètres depuis l'origine de la ligne.
            // Format SNCF "123+450" → 123450 mètres.
            // Permet aux contributions de référencer une portion précise.
            $table->integer('pk_start_m')->nullable()->after('osm_way_id');
            $table->integer('pk_end_m')->nullable()->after('pk_start_m');
            $table->index(['line_id', 'pk_start_m']);
        });
    }

    public function down(): void
    {
        Schema::table('line_segments', function (Blueprint $table): void {
            $table->dropIndex(['line_id', 'pk_start_m']);
            $table->dropColumn(['pk_start_m', 'pk_end_m']);
        });
    }
};
