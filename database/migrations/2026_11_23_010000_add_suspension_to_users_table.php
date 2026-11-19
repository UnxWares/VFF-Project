<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suspension d'un compte par un administrateur (CGU, « Validation et modération ») : le compte
 * ne peut plus proposer, valider ni signaler, mais garde l'accès à ses données.
 *
 * `suspended_at` non nul = suspension posée ; elle cesse à `suspended_until` (facultative) ou
 * quand un administrateur la lève (les quatre colonnes redeviennent nulles). `suspended_by` est
 * mis à null si l'administrateur supprime son compte : la suspension reste.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('suspended_until')->nullable();
            $table->text('suspension_reason')->nullable();
            $table->foreignId('suspended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->index('suspended_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('suspended_by');
            $table->dropIndex(['suspended_at']);
            $table->dropColumn(['suspended_at', 'suspended_until', 'suspension_reason']);
        });
    }
};
