<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Passages à niveau du RFN (jeu SNCF Open Data « liste-des-passages-a-niveau »,
 * ~16 900 PN, Licence Ouverte), importés par vff:import-sncf-level-crossings.
 *
 * Pourquoi : chaque PN porte un PK officiel ET des coordonnées précises. Ce
 * sont des points de contrôle denses (un tous les 1 à 2 km sur les lignes
 * rurales) pour caler les PK le long du tracé : le PK SNCF n'est pas la
 * distance géométrique (lacunes, raccourcis, rectifications de tracé), il
 * faut donc l'interpoler entre points connus (cf. App\Services\SectionResolver).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sncf_level_crossings', function (Blueprint $table): void {
            $table->id();
            $table->string('id_if', 20)->unique()->comment('Identifiant de l\'installation fixe SNCF Réseau');
            $table->string('libelle', 120)->nullable()->comment('Ex. « PN 35 »');
            $table->string('mnemo', 40)->nullable()->comment('Type / classe du PN');
            $table->string('obstacle', 160)->nullable()->comment('Voie franchie (route, chemin…)');
            $table->string('code_ligne', 16)->index();
            $table->smallInteger('rg_troncon')->nullable();
            $table->integer('pk_m')->nullable()->comment('PK officiel en mètres (« 019+663 » → 19663)');
            $table->string('commune', 120)->nullable();
            $table->double('lat');
            $table->double('lon');
            $table->timestamps();
            $table->index(['code_ligne', 'pk_m']);
        });
        DB::statement('ALTER TABLE sncf_level_crossings ADD COLUMN geom geometry(Point, 4326) NOT NULL');
        DB::statement('CREATE INDEX sncf_level_crossings_geom_gist ON sncf_level_crossings USING GIST (geom)');
    }

    public function down(): void
    {
        Schema::dropIfExists('sncf_level_crossings');
    }
};
