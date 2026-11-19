<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Profils en long des lignes (altitude le long du tracé), calculés par vff:compute-line-profiles
 * à partir de l'IGN (Licence Ouverte 2.0) : altitude de la voie dans la BD TOPO (géométrie 3D),
 * à défaut altitude du terrain (RGE ALTI, service d'altimétrie de la Géoplateforme), interpolée
 * dans les tunnels et sur les ponts sans altitude. Cache durable :
 * la page et l'API lisent cette table, elles n'appellent jamais l'IGN.
 *
 * Table indépendante du moteur de l'historique : une ligne par fiche, recalculée en entier.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('line_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('line_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('era_id')->comment('Ère du tracé échantillonné (ère courante, sinon la dernière qui a un tracé)')->constrained()->cascadeOnDelete();
            $table->string('source', 32)->default('bdtopo-rge-alti')->comment('Provenance des altitudes (registre database/data/source_rights.php)');
            $table->string('resource', 64)->comment('Ressource du service d\'altimétrie (ign_rge_alti_wld)');
            $table->unsignedSmallInteger('step_m')->comment('Pas d\'échantillonnage le long du tracé, en mètres');
            $table->unsignedInteger('length_m')->comment('Longueur du parcours profilé, lacunes comprises, en mètres');
            $table->unsignedInteger('trace_length_m')->comment('Longueur totale du tracé de la ligne à cette ère, en mètres');
            $table->jsonb('points')->comment('[[distance_m, altitude_m|null], ...] dans l\'ordre du parcours');
            $table->jsonb('structures')->comment('[{from_m, to_m, kind: tunnel|bridge}] : ouvrages de la BD TOPO sous le parcours');
            $table->jsonb('altitude_sources')->comment('Nombre de points par origine : {bdtopo, rge_alti, interpolated, missing}');
            $table->jsonb('gaps')->comment('[{from_m, to_m}] : lacunes du tracé, sans altitude');
            $table->jsonb('stations')->comment('[{slug, name, distance_m, altitude_m, pk_m}] : gares placées sur le profil');
            $table->float('min_altitude_m');
            $table->float('max_altitude_m');
            $table->float('ascent_m')->comment('Dénivelé positif cumulé dans le sens du parcours (seuil de 3 m)');
            $table->float('descent_m')->comment('Dénivelé négatif cumulé dans le sens du parcours (seuil de 3 m)');
            $table->float('max_grade_permil')->comment('Déclivité la plus forte sur une fenêtre de 1 km, en ‰ (valeur absolue)');
            $table->unsignedInteger('max_grade_from_m');
            $table->unsignedInteger('max_grade_to_m');
            $table->string('max_grade_direction', 4)->comment('up : rampe dans le sens du parcours ; down : pente');
            $table->timestamp('computed_at');
            $table->timestamps();
        });

        DB::statement("ALTER TABLE line_profiles ADD CONSTRAINT line_profiles_direction_check CHECK (max_grade_direction IN ('up', 'down'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('line_profiles');
    }
};
