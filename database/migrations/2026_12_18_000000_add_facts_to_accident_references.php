<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Faits qu'un article de presse numérisée dit de l'accident (date, commune, bilan), écrits par le fichier de contexte
 * (vff:enrich-accidents) et relus par vff:verify-accidents : la presse ancienne compte comme une famille de sources
 * pour le statut « relue ». Colonne additive, nulle pour tout document qui ne donne pas ces faits.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accident_references', function (Blueprint $table): void {
            $table->jsonb('facts')->nullable()->comment('faits lus dans un article de presse : date ou date_relative, commune_insee, deaths, deaths_provisional');
        });
    }

    public function down(): void
    {
        Schema::table('accident_references', function (Blueprint $table): void {
            $table->dropColumn('facts');
        });
    }
};
