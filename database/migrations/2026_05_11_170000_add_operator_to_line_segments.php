<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('line_segments', function (Blueprint $table): void {
            $table->string('operator')->nullable()->after('gauge_mm')
                ->comment('Operator from OSM (SNCF, RATP, Transilien, assoc tourism…)');
            $table->boolean('is_heritage')->default(false)->after('operator')
                ->comment('true si exploité par une asso/tourist, pas par SNCF');
            $table->index('is_heritage');
        });
    }

    public function down(): void
    {
        Schema::table('line_segments', function (Blueprint $table): void {
            $table->dropIndex(['is_heritage']);
            $table->dropColumn(['operator', 'is_heritage']);
        });
    }
};
