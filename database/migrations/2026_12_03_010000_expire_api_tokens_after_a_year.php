<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Jetons d'API : expiration à un an (décision du 20/11/2026), avec un rappel par courriel 30 jours
 * avant (vff:remind-expiring-api-tokens).
 *
 *   - `expiry_reminded_at` : date du rappel envoyé pour l'échéance en cours (remis à vide quand le
 *     jeton est renouvelé) ;
 *   - jetons existants, tous créés sans échéance : un an après leur création, et au plus tôt dans
 *     30 jours, pour que leur titulaire reçoive le rappel avant l'échéance.
 *
 * Retour arrière : la colonne est retirée et les échéances sont effacées (avant cette migration,
 * aucun jeton n'en avait).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->timestamp('expiry_reminded_at')->nullable()->after('expires_at');
        });

        // Dates naïves dans le fuseau de l'application, comme Laravel les écrit (pas NOW() du serveur SQL).
        $now = now()->toDateTimeString();
        DB::update("UPDATE personal_access_tokens
            SET expires_at = GREATEST(COALESCE(created_at, CAST(? AS timestamp)) + INTERVAL '1 year', CAST(? AS timestamp) + INTERVAL '30 days')
            WHERE expires_at IS NULL", [$now, $now]);
    }

    public function down(): void
    {
        DB::table('personal_access_tokens')->update(['expires_at' => null]);

        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->dropColumn('expiry_reminded_at');
        });
    }
};
