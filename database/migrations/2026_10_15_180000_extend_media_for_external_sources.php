<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Médias EXTERNES (Wikimedia Commons, Wikidata, Gallica…) sans copie de fichier.
 *
 * Pourquoi : VFF veut illustrer chaque ligne (photos, cartes postales, plans,
 * archives) sans héberger ni redistribuer les fichiers. On stocke uniquement
 * les métadonnées + les URL de miniatures servies par le fournisseur (hotlink
 * autorisé par Wikimedia), et surtout les informations de DROITS fichier par
 * fichier : licence telle que déclarée par la source (jamais réécrite), ligne
 * de crédit prête à afficher, lien vers la licence et vers la page source.
 *
 * Correctif de sécurité juridique : la colonne `license` avait une valeur par
 * défaut « CC-BY-SA-4.0 », ce qui attribuait silencieusement une licence à des
 * fichiers dont on ignore les droits. Désormais NULL = « licence inconnue »
 * (et `rights_status = unknown`), jamais une licence présumée.
 *
 * `file_path`, `mime_type`, `file_size` deviennent nullables : un média
 * externe n'a pas de fichier local. Les envois locaux (provider = upload)
 * continuent de les renseigner.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->string('provider', 20)->default('upload')->after('mediable_id')
                ->comment('commons | wikidata | upload | gallica | …');
            $table->string('external_id', 191)->nullable()->after('provider')
                ->comment('Identifiant stable chez le fournisseur (ex. pageid Commons)');
            $table->text('source_url')->nullable()->after('external_id')
                ->comment('Page de description du fichier chez le fournisseur');
            $table->text('thumb_url')->nullable()->after('source_url')
                ->comment('Miniature (~500 px) servie par le fournisseur (hotlink)');
            $table->text('display_url')->nullable()->after('thumb_url')
                ->comment('Version ≤ 1280 px pour la visionneuse (hotlink)');
            $table->text('attribution')->nullable()->after('copyright')
                ->comment('Ligne de crédit prête à afficher (auteur, licence, source)');
            $table->text('license_url')->nullable()->after('license');
            $table->string('rights_status', 20)->default('unknown')->after('license_url')
                ->comment('public_domain | open_license | permission | unknown');
            $table->boolean('commercial_ok')->nullable()->after('rights_status')
                ->comment('Réutilisation commerciale permise (NULL = inconnu)');
            $table->boolean('share_alike')->nullable()->after('commercial_ok')
                ->comment('Partage à l’identique exigé (NULL = inconnu)');
            $table->integer('width')->nullable()->after('file_size');
            $table->integer('height')->nullable()->after('width');
            $table->smallInteger('depicted_year')->nullable()->after('captured_at')
                ->comment('Année représentée/de prise de vue si connue (≠ date de téléversement)');
            $table->boolean('is_featured')->default(false)->after('depicted_year')
                ->comment('Image de référence de l’entité (ex. Wikidata P18)');
            $table->jsonb('provider_meta')->nullable()->after('exif_data')
                ->comment('Métadonnées brutes utiles à l’audit (catégories, crédit, méthode de rattachement…)');
        });

        // Colonnes de fichier local : facultatives pour les médias externes.
        DB::statement('ALTER TABLE media ALTER COLUMN file_path DROP NOT NULL');
        DB::statement('ALTER TABLE media ALTER COLUMN mime_type DROP NOT NULL');
        DB::statement('ALTER TABLE media ALTER COLUMN file_size DROP NOT NULL');

        // Plus de licence présumée : NULL = inconnue.
        DB::statement('ALTER TABLE media ALTER COLUMN license DROP DEFAULT');
        DB::statement('ALTER TABLE media ALTER COLUMN license DROP NOT NULL');
        DB::statement('ALTER TABLE media ALTER COLUMN license TYPE varchar(191)');

        DB::statement("ALTER TABLE media ADD CONSTRAINT media_rights_status_check
            CHECK (rights_status IN ('public_domain', 'open_license', 'permission', 'unknown'))");
        // Un fichier local doit garder son chemin ; un externe, son identifiant.
        DB::statement("ALTER TABLE media ADD CONSTRAINT media_source_present_check
            CHECK ((provider = 'upload' AND file_path IS NOT NULL)
                OR (provider <> 'upload' AND external_id IS NOT NULL))");

        Schema::table('media', function (Blueprint $table): void {
            $table->unique(['provider', 'external_id', 'mediable_type', 'mediable_id'], 'media_provider_external_mediable_unique');
            $table->index('depicted_year');
            $table->index('rights_status');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->dropUnique('media_provider_external_mediable_unique');
            $table->dropIndex(['depicted_year']);
            $table->dropIndex(['rights_status']);
        });
        DB::statement('ALTER TABLE media DROP CONSTRAINT IF EXISTS media_rights_status_check');
        DB::statement('ALTER TABLE media DROP CONSTRAINT IF EXISTS media_source_present_check');
        // Les médias externes n'ont pas de fichier : on ne peut pas rétablir NOT NULL.
        DB::statement("DELETE FROM media WHERE provider <> 'upload'");

        Schema::table('media', function (Blueprint $table): void {
            $table->dropColumn([
                'provider', 'external_id', 'source_url', 'thumb_url', 'display_url',
                'attribution', 'license_url', 'rights_status', 'commercial_ok',
                'share_alike', 'width', 'height', 'depicted_year', 'is_featured', 'provider_meta',
            ]);
        });
        DB::statement('ALTER TABLE media ALTER COLUMN file_path SET NOT NULL');
        DB::statement('ALTER TABLE media ALTER COLUMN mime_type SET NOT NULL');
        DB::statement('ALTER TABLE media ALTER COLUMN file_size SET NOT NULL');
        // On ne rétablit volontairement PAS la valeur par défaut « CC-BY-SA-4.0 ».
    }
};
