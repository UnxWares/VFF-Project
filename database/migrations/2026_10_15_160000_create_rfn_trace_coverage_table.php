<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Couverture des formes SNCF du RFN par les tracés précis (BD TOPO, OSM).
 *
 * Clé revue par 2026_10_15_160100 : une ligne par (code, statut SNCF, source).
 *
 * Une ligne par (tronçon de forme SNCF, source), calculée par
 * `vff:audit-current-network` : quelle part du tronçon est couverte par des
 * tracés rattachés à la même ligne, quelle part l'est par des tracés de la
 * source même non rattachés (donnée présente mais douteuse), et de combien la
 * forme SNCF s'écarte du tracé précis. Sert à savoir où la géométrie précise
 * est complète et quelles lignes restent à reprendre.
 *
 * Clé = idgaia (identifiant SNCF stable) et non une clé étrangère :
 * `vff:import-sncf-line-geometries` vide et recharge sncf_line_geometries.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rfn_trace_coverage', function (Blueprint $table): void {
            $table->id();
            $table->string('idgaia', 50)->comment('sncf_line_geometries.idgaia');
            $table->string('code_ligne', 20)->index();
            $table->string('statut', 150)->comment('sncf_line_geometries.libelle');
            $table->string('source', 40)->comment('bdtopo | osm');
            $table->integer('length_m')->comment('Longueur de la forme SNCF');
            $table->integer('covered_m')->comment('Longueur couverte par des tracés rattachés à la ligne');
            $table->integer('covered_any_m')->comment('Longueur couverte par un tracé de la source, rattaché ou non');
            $table->float('offset_median_m')->nullable()->comment('Écart forme SNCF → tracé rattaché (médiane)');
            $table->float('offset_p90_m')->nullable();
            $table->float('offset_max_m')->nullable();
            $table->timestamp('computed_at');

            $table->unique(['idgaia', 'source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rfn_trace_coverage');
    }
};
