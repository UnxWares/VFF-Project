<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Relation assurée pendant une période (« Paris-Montparnasse - Granville ») : gares d'extrémité, libellé et
 * type de service, quand la période vient d'une phrase qui cite une relation (MaterialRelations).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('line_material_periods', function (Blueprint $table): void {
            $table->jsonb('relation')->nullable()->comment('Relation citée : label, from {id, name}, to {id, name}, service');
        });
    }

    public function down(): void
    {
        Schema::table('line_material_periods', function (Blueprint $table): void {
            $table->dropColumn('relation');
        });
    }
};
