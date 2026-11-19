<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Signalements d'erreur envoyés depuis les fiches (ligne, gare, matériel, accident) par les
 * visiteurs et les membres, examinés par les validateurs et les administrateurs.
 *
 * Minimisation : ni adresse IP, ni navigateur, ni identifiant de suivi. Le texte et l'adresse de
 * contact facultative sont des données personnelles possibles : un signalement traité ou rejeté
 * est supprimé 12 mois après son traitement (App\Models\ErrorReport::prunable, planifié en
 * production) ; ceux d'un membre partent avec son compte (App\Services\Account\AccountDeletion).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('error_reports', function (Blueprint $table): void {
            $table->id();
            $table->string('subject_type', 20)->comment('App\Enums\ErrorReportSubject');
            $table->unsignedBigInteger('subject_id')->comment('Identifiant de la fiche (sans clé étrangère : quatre tables possibles)');
            $table->string('subject_slug', 255)->comment('Slug de la fiche au moment du signalement');
            $table->string('subject_label', 255)->comment('Nom de la fiche au moment du signalement');
            $table->text('description')->comment('Ce qui est faux, selon la personne qui signale');
            $table->string('source_url', 500)->nullable();
            $table->string('contact_email', 255)->nullable()->comment('Facultative, pour recontacter la personne');
            $table->string('locale', 5)->default('fr')->comment('Langue de la page au moment du signalement');
            $table->foreignId('user_id')->nullable()->comment('Membre connecté, sinon NULL')->constrained()->nullOnDelete();
            $table->string('status', 12)->default('open')->comment('App\Enums\ErrorReportStatus');
            $table->text('resolution_note')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
            $table->index('handled_at');
        });

        DB::statement("ALTER TABLE error_reports ADD CONSTRAINT error_reports_status_check CHECK (status IN ('open', 'resolved', 'rejected'))");
        DB::statement("ALTER TABLE error_reports ADD CONSTRAINT error_reports_subject_type_check CHECK (subject_type IN ('line', 'station', 'material', 'accident'))");
        // Un signalement traité ou rejeté porte toujours sa date de traitement (base de la purge à 12 mois).
        DB::statement("ALTER TABLE error_reports ADD CONSTRAINT error_reports_handled_check CHECK (status = 'open' OR handled_at IS NOT NULL)");
    }

    public function down(): void
    {
        Schema::dropIfExists('error_reports');
    }
};
