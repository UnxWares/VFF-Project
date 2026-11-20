<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gare « incertaine » à une ère : ses sources la disent ouverte, mais rien sur
 * la carte ne le confirme (aucune voie, même fermée, à moins de 300 m à cette
 * époque, ou lignes non datées / réseau non modélisé). Le statut des sources
 * reste dans `status` ; la carte et la fiche l'affichent comme « incertaine »,
 * jamais comme ouverte. Calculé par vff:build-station-eras.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('station_eras', function (Blueprint $table): void {
            $table->boolean('uncertain')->default(false)->after('status')
                ->comment('Statut non confirmé par les voies de l\'ère : affiché « incertaine », jamais ouverte');
        });
    }

    public function down(): void
    {
        Schema::table('station_eras', function (Blueprint $table): void {
            $table->dropColumn('uncertain');
        });
    }
};
