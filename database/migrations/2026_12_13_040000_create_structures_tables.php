<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ouvrages d'art des lignes (tunnels, viaducs, grands ponts), importés par vff:import-structures.
 *
 * - `structures` : l'ouvrage lui-même, une fois, même s'il porte plusieurs lignes. Tracé et longueur de la
 *   BD TOPO de l'IGN (tronçons de voie ferrée en tunnel ou sur pont, Licence Ouverte 2.0) ; nom, dates,
 *   protection au titre des monuments historiques (référence Mérimée) et image de Wikidata (CC0) ; nom de
 *   la BD TOPO (`construction_lineaire`) à défaut. Un ouvrage d'une ligne déposée reste dans la table.
 * - `line_structures` : rattachement à une ligne, avec les PK de début et de fin (calés sur le profil en
 *   long de la ligne), la distance le long du profil (repères du graphique) et les ères où la ligne est en
 *   service à l'ouvrage.
 *
 * Table additive : rien d'existant n'est modifié.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('structures', function (Blueprint $table): void {
            $table->id();
            $table->string('kind', 10)->comment('tunnel | viaduct | bridge');
            $table->string('name', 255)->nullable();
            $table->string('name_source', 12)->nullable()->comment('wikidata | bdtopo');
            $table->integer('length_m')->nullable();
            $table->string('length_source', 12)->nullable()->comment('bdtopo (tracé mesuré) | wikidata');
            $table->double('lat')->comment('Milieu de l\'ouvrage (WGS 84)');
            $table->double('lon');
            $table->date('built_on')->nullable()->comment('Construction ou début de construction (Wikidata P571)');
            $table->string('built_precision', 5)->nullable()->comment('year | month | day');
            $table->date('opened_on')->nullable()->comment('Mise en service (Wikidata P729, à défaut P1619)');
            $table->string('opened_precision', 5)->nullable();
            $table->date('closed_on')->nullable()->comment('Fermeture ou démolition (Wikidata P730, à défaut P576)');
            $table->string('closed_precision', 5)->nullable();
            $table->string('heritage', 12)->nullable()->comment('classified (classé MH) | registered (inscrit MH) | protected (notice Mérimée PA, statut non précisé)');
            $table->string('merimee_ref', 16)->nullable()->comment('Référence de la base Mérimée (Wikidata P380)');
            $table->jsonb('image')->nullable()->comment('Image libre de Commons (Wikidata P18) : fichier, vignette, auteur, licence');
            $table->string('wikidata_id', 16)->nullable()->unique();
            $table->string('wikipedia_title', 255)->nullable()->comment('Article de Wikipédia en français');
            $table->string('bdtopo_state', 16)->nullable()->comment('in_service | not_exploited : voie de la BD TOPO');
            $table->jsonb('sources')->comment('Sources de chaque information : [{source, url, fields}]');
            $table->string('match_key', 64)->unique()->comment('Clé stable de l\'import (identifiant Wikidata, sinon empreinte du tracé)');
            $table->timestamp('fetched_at');
            $table->timestamps();

            $table->index('kind');
        });
        DB::statement('ALTER TABLE structures ADD COLUMN geom geometry(LineString, 4326)');
        DB::statement('CREATE INDEX structures_geom_idx ON structures USING gist (geom)');
        DB::statement("COMMENT ON COLUMN structures.geom IS 'Tracé de l''ouvrage (BD TOPO) ; nul pour un ouvrage connu de Wikidata seulement'");

        Schema::create('line_structures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('line_id')->constrained()->cascadeOnDelete();
            $table->foreignId('structure_id')->constrained()->cascadeOnDelete();
            $table->integer('pk_start_m')->nullable()->comment('PK de début (m), calé sur les gares du profil en long');
            $table->integer('pk_end_m')->nullable();
            $table->integer('distance_from_m')->nullable()->comment('Distance le long du profil en long (m), début');
            $table->integer('distance_to_m')->nullable();
            $table->smallInteger('offset_m')->comment('Écart entre l\'ouvrage et le tracé de la ligne (m)');
            $table->jsonb('era_years')->comment('Ères (années) où la ligne est en service à l\'ouvrage');
            $table->timestamps();

            $table->unique(['line_id', 'structure_id']);
            $table->index('structure_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('line_structures');
        Schema::dropIfExists('structures');
    }
};
