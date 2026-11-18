<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tables miroir des datasets SNCF discriminants nécessaires à la
 * classification voyageurs/fret/LGV (vff:classify-lines). Vérifiés réels :
 *   - liste-des-gares           → sncf_gares (fret O/N, voyageurs O/N par code_ligne)
 *   - lignes-par-type           → sncf_line_types (Ligne/Rac/Vmère/Vport)
 *   - lignes-lgv-et-par-ecartement → sncf_line_catlig (LGV vs conventionnel)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sncf_gares', function (Blueprint $table): void {
            $table->id();
            $table->string('code_uic', 16)->nullable()->index();
            $table->string('code_ligne', 16)->index();
            $table->boolean('fret')->default(false);
            $table->boolean('voyageurs')->default(false);
            $table->string('libelle')->nullable();
            $table->unsignedSmallInteger('rg_troncon')->nullable();
            $table->integer('pk_m')->nullable();
            $table->string('commune')->nullable();
            $table->string('department', 64)->nullable();
            $table->double('lat')->nullable();
            $table->double('lon')->nullable();
        });

        Schema::create('sncf_line_types', function (Blueprint $table): void {
            $table->id();
            $table->string('code_ligne', 16)->index();
            $table->unsignedSmallInteger('rg_troncon')->nullable();
            $table->integer('pkd_m')->nullable();
            $table->integer('pkf_m')->nullable();
            $table->string('type_ligne', 16); // Ligne | Rac | Vmère | Vport
        });

        Schema::create('sncf_line_catlig', function (Blueprint $table): void {
            $table->id();
            $table->string('code_ligne', 16)->index();
            $table->unsignedSmallInteger('rg_troncon')->nullable();
            $table->integer('pkd_m')->nullable();
            $table->integer('pkf_m')->nullable();
            $table->string('catlig', 80); // 'Ligne à grande vitesse' | conventionnel normal | voie étroite
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sncf_gares');
        Schema::dropIfExists('sncf_line_types');
        Schema::dropIfExists('sncf_line_catlig');
    }
};
