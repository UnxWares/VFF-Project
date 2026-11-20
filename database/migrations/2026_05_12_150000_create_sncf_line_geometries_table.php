<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Source de vérité géométrique SNCF Réseau pour les lignes du RFN.
 *
 * Importé depuis le dataset `formes-des-lignes-du-rfn` :
 *   - code_ligne (string) : ex "752000"
 *   - libelle : statut SNCF (Exploitée, Neutralisée, Déposée, Retranchée, …)
 *   - pk_debut_m / pk_fin_m : bornes de PK en mètres
 *   - rg_troncon : numéro de tronçon
 *   - geom : LineString WGS84 (geometry, 4326)
 *
 * Cette table sert de référence pour audit et ré-attribution des segments
 * VFF mal classés (notamment les imports OSM qui mélangent plusieurs lignes
 * dans le même code).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sncf_line_geometries', function (Blueprint $table): void {
            $table->id();
            $table->string('code_ligne', 20)->index();
            $table->string('libelle', 50)
                ->comment('Statut SNCF : Exploitée, Neutralisée, Déposée, Retranchée…');
            $table->string('mnemo', 30)->nullable();
            $table->integer('pk_debut_m')->nullable();
            $table->integer('pk_fin_m')->nullable();
            $table->integer('rg_troncon')->nullable();
            $table->string('idgaia', 50)->nullable()->unique();
            $table->timestamps();
        });
        DB::statement('ALTER TABLE sncf_line_geometries ADD COLUMN geom geometry(LineString, 4326) NOT NULL');
        DB::statement('CREATE INDEX sncf_line_geometries_geom_gist ON sncf_line_geometries USING GIST (geom)');
        DB::statement('CREATE INDEX sncf_line_geometries_libelle_idx ON sncf_line_geometries (libelle)');
    }

    public function down(): void
    {
        Schema::dropIfExists('sncf_line_geometries');
    }
};
