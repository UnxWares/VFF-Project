<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Voies partagées entre lignes DIFFÉRENTES (troncs communs, raccordements,
 * lignes particulières posées sur une ligne RFN…) : pour chaque portion
 * (line_segments) dont une partie est recouverte (< 10 m sur ≥ 100 m) par la
 * portion d'une autre ligne PLUS ACTIVE à la même ère, la partie à dessiner
 * et la partie masquée sur la carte (cf. App\Support\TrackPriority,
 * vff:build-shared-track).
 *
 * Purement dérivé : line_segments n'est pas modifié (la fiche ligne garde
 * toute l'information) ; les tuiles MVT découpent la portion en deux
 * entités, `covered` = false / true, et la carte n'affiche que la première.
 * Une ligne de cette table devient caduque (ignorée par les tuiles) dès que
 * la portion ou l'une des portions qui la recouvrent change d'état, est
 * régénérée ou supprimée : `signature` compare l'état enregistré à l'état
 * courant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('line_segment_covers', function (Blueprint $table): void {
            // Pas de clé étrangère vers line_segments : le moteur décennal (DELETE +
            // INSERT des portions) n'a ni cascade ni verrou de plus à gérer. Une
            // portion supprimée laisse une entrée orpheline, ignorée par les tuiles
            // (signature) et purgée au prochain vff:build-shared-track de l'ère.
            $table->unsignedBigInteger('segment_id')->primary();
            $table->foreignId('era_id')->constrained()->cascadeOnDelete();
            $table->jsonb('covering_line_ids')->comment('lignes plus actives qui recouvrent la portion');
            $table->integer('covered_m')->comment('longueur masquée (m)');
            $table->integer('visible_m')->comment('longueur restant dessinée (m)');
            $table->string('signature', 32)->comment('md5 de l\'état (id, statut, trafic, patrimoine) de la portion et de ses recouvrantes');
            $table->timestamps();
            $table->index(['era_id']);
        });
        // Portions recouvrantes (bigint[] : comparées à line_segments.id par = ANY dans les tuiles).
        DB::statement('ALTER TABLE line_segment_covers ADD COLUMN covering_ids bigint[] NOT NULL');
        DB::statement('ALTER TABLE line_segment_covers ADD COLUMN visible_geom geometry(MultiLineString, 4326)');
        DB::statement('ALTER TABLE line_segment_covers ADD COLUMN covered_geom geometry(MultiLineString, 4326) NOT NULL');
        DB::statement("COMMENT ON COLUMN line_segment_covers.visible_geom IS 'partie dessinée (NULL = portion entièrement recouverte)'");
    }

    public function down(): void
    {
        Schema::dropIfExists('line_segment_covers');
    }
};
