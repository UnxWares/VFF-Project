<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Droits par média, pour les fonds autres que Commons (Panoramax, archives, cartes postales, fonds NC ou ND).
 *
 * - `derivatives_ok` : modification permise (vrai), interdite par une clause ND (faux), inconnue (nul). Une image
 *   à `false` n'est jamais recadrée : vignette réduite seulement, image entière (CSS `object-fit: contain`).
 * - `date_precision` : précision de la date représentée (`depicted_year`, `captured_at`) ; jusqu'ici rangée
 *   dans `provider_meta.date_precision` pour Commons, reprise ici quand la valeur est documentée.
 *
 * `commercial_ok`, `license`, `license_url`, `attribution`, `source_url`, `captured_at` et `location` existent
 * déjà (2026_10_15_180000) : le réglage `vff.media.show_non_commercial` masque les lignes `commercial_ok = false`.
 *
 * Reprise des lignes existantes : Commons n'accepte que des licences qui permettent la modification, donc
 * `derivatives_ok = true` pour ses fichiers sous licence libre ou dans le domaine public. Réversible : `down()`
 * retire les deux colonnes, rien d'autre (la valeur de `provider_meta` n'a pas été touchée).
 */
return new class extends Migration
{
    /** Valeurs documentées de la précision (mêmes que `Media.date_precision` en GraphQL). */
    private const PRECISIONS = ['day', 'month', 'year', 'decade', 'circa', 'range', 'before', 'after'];

    public function up(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->boolean('derivatives_ok')->nullable()->after('share_alike')
                ->comment('Modification permise (NULL = inconnu, false = ND : réduite, jamais recadrée)');
            $table->string('date_precision', 10)->nullable()->after('depicted_year')
                ->comment('day | month | year | decade | circa | range | before | after');
        });

        $list = "'".implode("', '", self::PRECISIONS)."'";
        DB::statement("ALTER TABLE media ADD CONSTRAINT media_date_precision_check CHECK (date_precision IN ({$list}))");

        DB::statement("UPDATE media SET date_precision = provider_meta->>'date_precision'
            WHERE provider_meta->>'date_precision' IN ({$list})");
        DB::statement("UPDATE media SET derivatives_ok = true
            WHERE provider = 'commons' AND rights_status IN ('public_domain', 'open_license')");

        // Le filtre du réglage (commercial_ok = false masqué) ne porte que sur quelques lignes : index partiel.
        DB::statement('CREATE INDEX media_non_commercial_idx ON media (mediable_type, mediable_id) WHERE commercial_ok = false');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS media_non_commercial_idx');
        DB::statement('ALTER TABLE media DROP CONSTRAINT IF EXISTS media_date_precision_check');
        Schema::table('media', function (Blueprint $table): void {
            $table->dropColumn(['derivatives_ok', 'date_precision']);
        });
    }
};
