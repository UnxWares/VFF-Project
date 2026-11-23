<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mises en service de systèmes de signalisation, datées, tirées des articles Wikipédia des lignes
 * (calculées par `vff:import-signalling-history`, App\Services\Signalling\SignallingHistoryExtractor).
 *
 * Historique par époque : `line_signalling` ne garde que l'état actuel (SNCF Réseau). Cette table
 * est à part et ne touche ni la carte par ère ni `line_signalling`. Un fait par ligne, système,
 * date et portion (empreinte unique), jamais deviné : une date inconnue ou un système ambigu n'est pas gardé.
 *
 *   system          : BAL, BAPR, BM, CT, CC, TVM-300, TVM-430, TVM, ETCS_L1, ETCS_L2, ETCS, KVB
 *   role            : block (cantonnement), cab (signalisation en cabine, TVM et ETCS, cantonnement ET contrôle),
 *                     speed_control (contrôle de vitesse : KVB, jamais un cantonnement),
 *                     remote_control (commande centralisée CC : CCVU, CCR, PCD ; commande à distance des postes, jamais un cantonnement)
 *   event_date      : date de la mise en service, lue dans l'article ; `date_precision` : day, month, year
 *   scope           : whole_line (l'article le dit), section (portion nommée), unspecified (l'article ne dit pas)
 *   section_from/to : lieux tels qu'écrits ; pk_start_m / pk_end_m : PK en mètres si l'article les donne
 *   confidence      : high (jour ou mois précis), medium (année seule), low (ambigu : gardé, jamais montré)
 *   source_*        : titre de l'article, révision lue (permalien oldid), courte phrase pour vérification
 * Texte de Wikipédia sous CC BY-SA 4.0 (source = 'wikipedia', cf. database/data/source_rights.php) ; seuls des faits
 * sont repris, la phrase gardée (200 caractères au plus) sert à relire.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('line_signalling_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('line_id')->constrained()->cascadeOnDelete();
            $table->string('system', 12);
            $table->string('role', 14);
            $table->date('event_date');
            $table->string('date_precision', 5);
            $table->string('scope', 12)->default('unspecified');
            $table->string('section_from')->nullable();
            $table->string('section_to')->nullable();
            $table->integer('pk_start_m')->nullable();
            $table->integer('pk_end_m')->nullable();
            $table->string('confidence', 6);
            $table->string('origin', 8);
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
        Schema::dropIfExists('line_signalling_events');
    }
};
