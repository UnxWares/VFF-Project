<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Matériel roulant (phase 13) : champs encyclopédiques tirés de Wikidata (CC0) et de Wikimedia
 * Commons, provenance gardée champ par champ (`provenance`), et preuve de chaque lien
 * matériel ↔ ligne (`line_materials.source`, `source_url`, `evidence`).
 *
 * Remplie par `vff:import-materials`. Les champs restent nuls quand la source ne dit rien :
 * aucune valeur n'est estimée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table): void {
            $table->string('wikidata_id', 16)->nullable()->unique()->comment('Élément Wikidata de la série (Q746572…)');
            $table->jsonb('designations')->nullable()->comment('Autres noms : libellés, alias, surnoms (liste de chaînes)');
            $table->string('fleet_numbers', 255)->nullable()->comment('Numéros des engins (« 15001 - 15065 »), Wikidata P2802');
            $table->jsonb('builders')->nullable()->comment('Constructeurs : [{name, wikidata_id, units}], Wikidata P176');
            $table->jsonb('operators')->nullable()->comment('Exploitants : [{name, wikidata_id, from_year, to_year, units}], Wikidata P137');
            $table->integer('units_built')->nullable()->comment('Nombre d\'engins construits, Wikidata P1092');
            $table->smallInteger('max_speed_kmh')->nullable()->comment('Vitesse maximale en km/h, Wikidata P2052');
            $table->integer('power_kw')->nullable()->comment('Puissance nominale en kW, Wikidata P2109 (converti si besoin)');
            $table->jsonb('electrification')->nullable()->comment('Alimentations électriques : [{label, wikidata_id}], Wikidata P930');
            $table->smallInteger('gauge_mm')->nullable()->comment('Écartement en mm quand la série n\'en a qu\'un, Wikidata P1064');
            $table->jsonb('gauges')->nullable()->comment('Écartements : [{label, mm, wikidata_id}]');
            $table->string('wheel_arrangement', 60)->nullable()->comment('Disposition d\'essieux (BB, 141…), Wikidata P2978');
            $table->jsonb('image')->nullable()->comment('Image Commons : fichier, adresses, auteur, licence, attribution');
            $table->string('commons_category', 255)->nullable()->comment('Catégorie Wikimedia Commons, Wikidata P373');
            $table->string('wikipedia_fr', 255)->nullable()->comment('Titre de l\'article Wikipédia en français');
            $table->string('wikipedia_en', 255)->nullable()->comment('Titre de l\'article Wikipédia en anglais');
            $table->string('source', 30)->nullable()->comment('Provenance de la fiche (wikidata) ; nul pour une fiche saisie dans VFF');
            $table->jsonb('provenance')->nullable()->comment('Provenance par champ : {champ: {source, ref}}');
            $table->timestamp('fetched_at')->nullable()->comment('Dernière lecture de la source');

            $table->index('in_service_from');
            $table->index('source');
        });

        Schema::table('line_materials', function (Blueprint $table): void {
            $table->string('source', 30)->nullable()->comment('Provenance du lien : wikidata, wikipedia ; nul pour un lien saisi dans VFF');
            $table->string('source_url', 500)->nullable()->comment('Permalien de la source (révision Wikipédia, élément Wikidata)');
            $table->string('confidence', 12)->nullable()->comment('high | medium | low');
            $table->jsonb('evidence')->nullable()->comment('Preuves : [{kind, article, revid, section, quote, url}]');
            $table->timestamp('fetched_at')->nullable();

            // Un lien par source : réimporter met à jour la preuve au lieu de dupliquer le lien.
            $table->unique(['line_id', 'material_id', 'source'], 'line_materials_unique_source');
            $table->index('source');
        });
    }

    public function down(): void
    {
        Schema::table('line_materials', function (Blueprint $table): void {
            $table->dropUnique('line_materials_unique_source');
            $table->dropIndex(['source']);
            $table->dropColumn(['source', 'source_url', 'confidence', 'evidence', 'fetched_at']);
        });

        Schema::table('materials', function (Blueprint $table): void {
            $table->dropIndex(['in_service_from']);
            $table->dropIndex(['source']);
            $table->dropUnique(['wikidata_id']);
            $table->dropColumn([
                'wikidata_id', 'designations', 'fleet_numbers', 'builders', 'operators', 'units_built', 'max_speed_kmh',
                'power_kw', 'electrification', 'gauge_mm', 'gauges', 'wheel_arrangement', 'image', 'commons_category',
                'wikipedia_fr', 'wikipedia_en', 'source', 'provenance', 'fetched_at',
            ]);
        });
    }
};
