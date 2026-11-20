<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * lines.type devient nullable : une ligne sans signal de trafic (aucune source
 * SNCF voyageurs/fret, raccordement sans voisin classé) a un type indéterminé
 * = NULL, plutôt qu'une valeur fausse héritée du seed. Aligné sur la classe
 * 'unknown' de LineClassifier (vff:classify-lines).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lines', function (Blueprint $table): void {
            $table->string('type')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Rétablit un défaut avant de remettre NOT NULL
        DB::statement("UPDATE lines SET type='passenger' WHERE type IS NULL");
        // ->change() redéfinit TOUTE la colonne (Laravel 11+) : sans répéter le
        // défaut de create_lines_table, le rollback le perdait.
        Schema::table('lines', function (Blueprint $table): void {
            $table->string('type')->default('passenger')->nullable(false)->change();
        });
    }
};
