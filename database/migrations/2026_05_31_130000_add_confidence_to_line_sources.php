<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Score de confiance par source/ligne (audit 2026-05) : trace la fiabilité de
 * la classification (high/medium/low/insufficient) pour le tri de curation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('line_sources', function (Blueprint $table): void {
            $table->string('confidence', 12)->nullable()->after('fields_provided');
        });
    }

    public function down(): void
    {
        Schema::table('line_sources', function (Blueprint $table): void {
            $table->dropColumn('confidence');
        });
    }
};
