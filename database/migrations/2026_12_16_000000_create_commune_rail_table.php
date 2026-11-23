<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Communes qui ont du rail (`vff:build-commune-rail`) : une ligne par commune actuelle où se trouve au moins une
 * gare ou une halte, ou que traverse ou traversait au moins une ligne (toutes les ères). Sert à la recherche et au
 * plan du site des fiches « Le rail à <commune> » (/communes/{insee}-{nom}) : sans elle, ces listes se limitent aux
 * communes qui ont une gare, calculées à la volée et gardées en cache ; les fiches, elles, répondent sans la table.
 * Additive : aucune table existante n'est touchée, la table se reconstruit à volonté.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commune_rail', function (Blueprint $table): void {
            $table->string('code_insee', 5)->primary();
            $table->unsignedInteger('stations_count')->default(0)->comment('gares et haltes dont le point est dans la commune');
            $table->unsignedInteger('lines_count')->default(0)->comment('lignes dont une portion de 50 m au moins est dans la commune, toutes ères');
            $table->timestamp('built_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commune_rail');
    }
};
