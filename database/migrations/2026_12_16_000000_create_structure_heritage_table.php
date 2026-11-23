<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Protections des ouvrages d'art (viaducs, ponts, tunnels) lues dans la base Mérimée du ministère de la Culture (Licence
 * Ouverte 2.0) et rapprochées des ouvrages de `structures` (`vff:import-structure-heritage`).
 *
 * Table à part, car `vff:import-structures` réécrit les colonnes `heritage` et `merimee_ref` de `structures` à chaque import
 * (Wikidata seul) : ce que Mérimée apporte en plus (arrêté, auteurs, datation, notices supplémentaires) y serait perdu.
 *
 * Une ligne par ouvrage et par notice (`ref` : référence Mérimée PA/IA). `protection` : `classified`, `registered` ou `acr` ;
 * `designations` : toutes les mentions (type, date). `match_score` et `match_distance_m` : points du rapprochement et écart
 * corrigé de la demi-longueur de l'ouvrage (100 points : la référence était déjà celle de l'ouvrage).
 *
 * Additive : aucune table existante n'est touchée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('structure_heritage', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('structure_id')->constrained()->cascadeOnDelete();
            $table->string('protection', 12);
            $table->date('protection_date')->nullable();
            $table->string('scope', 8)->nullable();
            $table->jsonb('designations')->nullable();
            $table->string('title', 255)->nullable();
            $table->string('denomination', 120)->nullable();
            $table->jsonb('architects')->nullable();
            $table->smallInteger('built_from')->nullable();
            $table->smallInteger('built_to')->nullable();
            $table->string('built_period', 60)->nullable();
            $table->string('ref', 20);
            $table->string('ref_url', 255)->nullable();
            $table->smallInteger('match_score');
            $table->smallInteger('match_distance_m')->nullable();
            $table->string('source', 12)->default('merimee');
            $table->date('source_updated')->nullable();
            $table->timestamps();
            $table->unique(['structure_id', 'ref']);
            $table->index('protection');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('structure_heritage');
    }
};
