<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Période de circulation proposée par un contributeur (contribution « matériel sur une ligne ») : la
 * source peut être un ouvrage ou une archive papier, citée par sa référence (`source_title`) sans
 * adresse. `source_url` devient facultative ; les périodes importées (Wikipédia, Wikidata) gardent
 * toujours leur permalien.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('line_material_periods', function (Blueprint $table): void {
            $table->string('source_url', 600)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Une période sans adresse garde sa référence (source_title) : l'adresse vide la remplace.
        DB::table('line_material_periods')->whereNull('source_url')->update(['source_url' => '']);
        Schema::table('line_material_periods', function (Blueprint $table): void {
            $table->string('source_url', 600)->nullable(false)->change();
        });
    }
};
