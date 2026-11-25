<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RINF, registre de l'infrastructure de l'Agence de l'Union européenne pour les chemins de fer (ERA), lu par
 * vff:import-rinf dans le graphe de connaissances de l'ERA (point SPARQL public, graphe de SNCF Réseau).
 * Tables ADDITIVES, remplacées d'un coup par chaque import réel (aucune autre commande n'y écrit) :
 *
 * - `rinf_operational_points` : points d'exploitation (UOPID, nom, type RINF, position WGS 84) et leur gare de
 *   VFF (rattachement par nom et distance, méthode et distance gardées) ;
 * - `rinf_sections` : sections de ligne entre deux points d'exploitation, sur une ligne nationale (code SNCF
 *   Réseau à 6 chiffres = `lines.code`), PK de début et de fin en mètres dans la numérotation de SNCF Réseau ;
 * - `rinf_tracks` : voies, celles des sections (voie 1, voie 2, voie unique…) et celles des points
 *   d'exploitation (voies de gare, voies de service, liaisons), avec sens, vitesse, déclivités déclarées ;
 * - `rinf_platforms` : bords de quai, rattachés à leur voie.
 *
 * Chaque ligne porte sa provenance : `dataset` (graphe et point SPARQL lus) et `fetched_at` (date de lecture).
 * Le RINF décrit le réseau d'aujourd'hui : aucune ère (schéma des voies de l'ère courante seulement).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rinf_operational_points', function (Blueprint $table): void {
            $table->id();
            $table->string('uopid', 20)->unique()->comment('Identifiant unique du point d\'exploitation (UOPID, ex. FR0000003513)');
            $table->string('name', 160)->comment('Nom RINF (opName), tel quel');
            $table->smallInteger('op_type')->nullable()->comment('Type RINF (concept op-types : 10 gare, 70 halte, 80 bifurcation…)');
            $table->string('kind', 20)->comment('station, halt, junction, passing_loop, siding, border ou other');
            $table->double('lat')->nullable();
            $table->double('lon')->nullable();
            $table->foreignId('station_id')->nullable()->constrained()->nullOnDelete();
            $table->string('station_match', 20)->nullable()->comment('Rattachement à la gare : name_distance, name, distance');
            $table->integer('station_distance_m')->nullable()->comment('Distance entre le point RINF et la gare, en mètres');
            $table->string('uri', 255)->comment('URI du point dans le graphe de l\'ERA');
            $table->string('dataset', 200)->comment('Graphe et point SPARQL lus');
            $table->timestamp('fetched_at')->nullable()->comment('Date de lecture du jeu');
            $table->timestamps();

            $table->index('station_id');
        });

        Schema::create('rinf_sections', function (Blueprint $table): void {
            $table->id();
            $table->string('uri', 255)->unique()->comment('URI canonique de la section (sectionOfLine/<ligne>_<UOPID>_<UOPID>)');
            $table->string('national_line', 20)->comment('Ligne nationale RINF (lineId), ex. 579000-1');
            $table->string('line_code', 16)->comment('Code de ligne SNCF Réseau (6 chiffres), comparé à lines.code');
            $table->foreignId('line_id')->nullable()->constrained()->nullOnDelete();
            $table->string('op_start_uopid', 20);
            $table->string('op_end_uopid', 20);
            $table->integer('pk_start_m')->nullable()->comment('PK du début (m), numérotation SNCF Réseau');
            $table->integer('pk_end_m')->nullable()->comment('PK de la fin (m)');
            $table->integer('length_m')->nullable()->comment('Longueur déclarée (lengthOfSectionOfLine), en mètres');
            $table->string('nature', 10)->comment('regular (section ordinaire) ou link (liaison)');
            $table->string('dataset', 200);
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();

            $table->index(['line_code', 'pk_start_m']);
            $table->index('line_id');
        });

        Schema::create('rinf_tracks', function (Blueprint $table): void {
            $table->id();
            $table->string('uri', 255)->unique()->comment('URI de la voie ou de la voie de service dans le graphe');
            $table->string('parent', 10)->comment('section (voie d\'une section de ligne) ou op (voie d\'un point d\'exploitation)');
            $table->string('section_uri', 255)->nullable()->comment('Section de ligne de la voie (parent = section)');
            $table->string('op_uopid', 20)->nullable()->comment('Point d\'exploitation de la voie (parent = op)');
            $table->string('rinf_class', 12)->comment('running (era:RunningTrack) ou siding (era:Siding)');
            $table->string('ref', 160)->comment('Identifiant RINF tel quel (trackId ou sidingId)');
            $table->string('direction', 4)->nullable()->comment('Sens normal déclaré : N (sens de la section), O (inverse), B (les deux)');
            $table->smallInteger('max_speed_kmh')->nullable();
            $table->integer('length_m')->nullable()->comment('Longueur déclarée (voie de service), en mètres');
            $table->float('gradient_permille')->nullable()->comment('Déclivité déclarée d\'une voie de service, en ‰');
            $table->jsonb('gradient_profile')->nullable()->comment('Profil de déclivités déclaré : [{permille, pk_m}] dans l\'ordre de la voie');
            $table->integer('min_radius_m')->nullable();
            $table->boolean('electrified')->nullable()->comment('Ligne de contact déclarée (null : non dit)');
            $table->string('dataset', 200);
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();

            $table->index('section_uri');
            $table->index('op_uopid');
        });

        Schema::create('rinf_platforms', function (Blueprint $table): void {
            $table->id();
            $table->string('uri', 255)->unique();
            $table->string('track_uri', 255)->comment('Voie bordée par le quai');
            $table->string('op_uopid', 20)->nullable();
            $table->string('ref', 120)->comment('Identifiant RINF du bord de quai (platformId), tel quel');
            $table->integer('length_m')->nullable();
            $table->smallInteger('height_mm')->nullable();
            $table->string('dataset', 200);
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();

            $table->index('track_uri');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rinf_platforms');
        Schema::dropIfExists('rinf_tracks');
        Schema::dropIfExists('rinf_sections');
        Schema::dropIfExists('rinf_operational_points');
    }
};
