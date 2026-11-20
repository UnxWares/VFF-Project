<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SNCF Réseau Open Data « Liste des lignes électrifiées » (liste-des-lignes-electrifiees) :
 * électrification ACTUELLE et tension par tronçon (code ligne + PK), avec la forme
 * généralisée du tronçon. Alimentée par vff:import-sncf-electrification.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sncf_line_electrification', function (Blueprint $table): void {
            $table->id();
            $table->string('code_ligne', 16)->index();
            $table->smallInteger('rg_troncon')->nullable();
            $table->integer('pkd_m')->nullable();
            $table->integer('pkf_m')->nullable();
            $table->string('elect', 80)->comment('libellé SNCF, ex. « 25000 volts alternatif »');
            $table->integer('voltage_v')->nullable();
            $table->decimal('frequency_hz', 5, 2)->nullable()->comment('0 = continu');
            $table->boolean('third_rail')->default(false);
            $table->string('idgaia', 50)->nullable();
            $table->timestamps();
        });
        DB::statement('ALTER TABLE sncf_line_electrification ADD COLUMN geom geometry(MultiLineString, 4326)');
        DB::statement('CREATE INDEX sncf_line_electrification_geom_gist ON sncf_line_electrification USING GIST (geom)');
    }

    public function down(): void
    {
        Schema::dropIfExists('sncf_line_electrification');
    }
};
