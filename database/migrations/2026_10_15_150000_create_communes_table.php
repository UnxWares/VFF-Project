<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gazetteer des communes (actuelles + anciennes communes associées/déléguées).
 *
 * Sert au rattachement des tracés bruts (vff:link-traces) :
 *   - garde-fou spatial des correspondances par nom : « Carhaix Morlaix » ne
 *     peut désigner la ligne de Carhaix à Morlaix que si le tracé passe près
 *     de ces communes ;
 *   - désambiguïsation des noms génériques (« Embranchement militaire de
 *     Metzervisse ») et nommage des tracés anonymes (« … près de <commune> »).
 *
 * Les anciennes communes (fusionnées) comptent : les gares portent souvent le
 * nom d'une commune disparue depuis. Source : API Découpage administratif
 * (geo.api.gouv.fr, COG INSEE + IGN, Licence Ouverte 2.0).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communes', function (Blueprint $table): void {
            $table->id();
            $table->string('code_insee', 5);
            $table->string('type', 20)->comment('commune-actuelle | commune-associee | commune-deleguee');
            $table->string('nom');
            $table->string('nom_norm')->comment('minuscules, sans accents ni ponctuation, St → saint');
            $table->string('departement', 3)->nullable();
            $table->integer('population')->nullable();
            $table->string('chef_lieu', 5)->nullable()->comment('Commune actuelle de rattachement (anciennes communes)');
            $table->timestamps();

            $table->unique(['code_insee', 'type']);
            $table->index('nom_norm');
        });
        DB::statement('ALTER TABLE communes ADD COLUMN geom geometry(Point, 4326) NOT NULL');
        DB::statement('CREATE INDEX communes_geom_gist ON communes USING GIST (geom)');
    }

    public function down(): void
    {
        Schema::dropIfExists('communes');
    }
};
