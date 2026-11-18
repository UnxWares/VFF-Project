<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('line_segments', function (Blueprint $table): void {
            $table->string('traffic_type', 20)->default('unknown')->after('status')
                ->comment('passenger | freight | mixed | unknown - issu du statut SNCF');
            $table->index('traffic_type');
        });
    }

    public function down(): void
    {
        Schema::table('line_segments', function (Blueprint $table): void {
            $table->dropIndex(['traffic_type']);
            $table->dropColumn('traffic_type');
        });
    }
};
