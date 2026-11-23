<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vitesses maximales des lignes, par points kilométriques (`vff:import-line-speeds`).
 *
 * `line_speed_ranges` : vitesse maximale nominale par tranche de PK, telle que SNCF Réseau la publie (jeu
 * `vitesse-maximale-nominale-sur-ligne`, ODbL), dans la numérotation de PK de SNCF Réseau, comme
 * `line_signalling`. Une ligne du jeu par tranche (identifiant GAIA `sncf_id`, rang du tronçon
 * `sncf_rank`) : les tranches voisines de même vitesse sont réunies à la lecture (App\Services\Speed\SpeedRanges).
 * Le jeu décrit le réseau au jour même : seule l'ère courante est renseignée. Additive :
 * `line_segments.max_speed_kmh` (tronçons de la carte, vff:import-sncf-vmax) n'est pas touché.
 *
 * `line_speed_events` : changements de vitesse datés lus dans l'article Wikipédia de la ligne
 * (App\Services\Speed\SpeedHistoryExtractor) : relèvement, abaissement, vitesse à l'ouverture, vitesse
 * autorisée à une date. Un fait par ligne, date, vitesse et portion (empreinte unique), jamais deviné ;
 * `confidence` : high (jour ou mois), medium (année), low (ambigu : gardé, jamais montré). Texte de
 * Wikipédia sous CC BY-SA 4.0 : seuls des faits sont repris, la phrase gardée (200 caractères au plus) sert à relire.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('line_speed_ranges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('line_id')->constrained()->cascadeOnDelete();
            $table->foreignId('era_id')->constrained()->cascadeOnDelete();
            $table->integer('pk_start_m');
            $table->integer('pk_end_m');
            $table->smallInteger('max_speed_kmh');
            $table->smallInteger('sncf_rank')->nullable();
            $table->string('sncf_id', 64)->nullable();
            $table->string('source', 12)->default('sncf');
            $table->timestamps();
            $table->index(['line_id', 'era_id', 'pk_start_m']);
        });

        Schema::create('line_speed_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('line_id')->constrained()->cascadeOnDelete();
            $table->date('event_date');
            $table->string('date_precision', 5);
            $table->string('kind', 10);
            $table->smallInteger('speed_kmh');
            $table->smallInteger('previous_speed_kmh')->nullable();
            $table->string('scope', 12)->default('unspecified');
            $table->string('section_from')->nullable();
            $table->string('section_to')->nullable();
            $table->integer('pk_start_m')->nullable();
            $table->integer('pk_end_m')->nullable();
            $table->string('confidence', 6);
            $table->string('source', 20)->default('wikipedia');
            $table->string('source_title');
            $table->unsignedBigInteger('source_revision');
            $table->string('source_url', 600);
            $table->string('source_excerpt', 255);
            $table->string('fingerprint', 40)->unique();
            $table->timestamps();
            $table->index(['line_id', 'event_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('line_speed_events');
        Schema::dropIfExists('line_speed_ranges');
    }
};
