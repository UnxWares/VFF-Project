<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SNCF Réseau publie désormais des libellés de statut de plus de 50 caractères
 * (« Fermée avec maintien en place de la voie… », « Fermée non déposée (Plus
 * utilisable) »…) : l'import de `formes-des-lignes-du-rfn` échouait.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sncf_line_geometries', function (Blueprint $table): void {
            $table->string('libelle', 150)->change();
        });
    }

    public function down(): void
    {
        Schema::table('sncf_line_geometries', function (Blueprint $table): void {
            $table->string('libelle', 50)->change();
        });
    }
};
