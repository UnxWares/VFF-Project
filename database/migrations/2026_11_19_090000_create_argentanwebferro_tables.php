<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Source « Argentan Web Ferroviaire » (argentanwebferro.fr, Thierry Danglos), tables de
 * SOURCE seulement, remplies par vff:import-argentanwebferro (cf. App\Services\ArgentanWebFerro).
 *
 * Droits (database/data/source_rights.php, fournisseur argentanwebferro) :
 *   - articles WordPress « tous droits réservés » : on n'en garde que des FAITS (dates,
 *     sections, arrêts, exploitants), avec le lien de l'article et au plus une phrase
 *     citée (source_quote, 240 caractères au plus). Aucun texte, tableau ni photo d'article ;
 *   - galerie Piwigo affichée sous CC BY-SA 4.0 : inventaire des photos, avec leur classe de
 *     droits (œuvre propre de l'auteur ou œuvre d'un tiers : carte postale, archive, photo
 *     créditée à un autre photographe). Rien n'est copié dans `media` par cette migration.
 *
 * Aucune écriture dans les tables du moteur (line_events, stations, media…) : l'utilisateur
 * décide sur rapport (storage/app/reports/agents/source-argentan.md) de ce qui y entrera.
 * line_id / station_id sans clé étrangère (tables chaudes, données recalculées à chaque import).
 */
return new class extends Migration
{
    /** Valeurs de App\Enums\ArgentanFactKind. */
    private const KINDS = "('event', 'stop', 'operator')";

    /** Valeurs de App\Enums\FactDatePrecision. */
    private const PRECISIONS = "('day', 'month', 'year', 'range')";

    /** Valeurs de App\Enums\ArgentanPhotoClass. */
    private const PHOTO_CLASSES = "('own_work', 'postcard', 'archive', 'credited', 'unknown')";

    public function up(): void
    {
        Schema::create('argentanwebferro_articles', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('wp_id')->comment('identifiant WordPress');
            $table->string('wp_type', 10)->comment('page | post');
            $table->string('slug', 200);
            $table->string('url', 500);
            $table->string('title', 300);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('modified_at')->nullable()->comment('date de mise à jour de l\'article (champ modified de WordPress)');
            $table->jsonb('categories')->comment('slugs des catégories WordPress');
            $table->boolean('is_line_article')->default(false)->comment('article d\'histoire d\'une ligne ou d\'un embranchement');
            $table->jsonb('line_ids')->comment('lignes VFF rattachées à l\'article (curation ou rattachement automatique)');
            $table->string('line_match', 12)->comment('curated | auto | doubtful | none');
            $table->text('line_match_note')->nullable();
            $table->char('content_sha1', 40)->comment('empreinte du HTML lu (le texte n\'est pas conservé)');
            $table->timestamp('fetched_at');
            $table->timestamps();
            $table->unique(['wp_type', 'wp_id']);
        });

        Schema::create('argentanwebferro_facts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('article_id')->constrained('argentanwebferro_articles')->cascadeOnDelete();
            $table->char('fingerprint', 40)->unique()->comment('empreinte stable du fait (article, nature, type, date, section, arrêt)');
            $table->string('kind', 10)->index();
            $table->unsignedBigInteger('line_id')->nullable()->index()->comment('ligne VFF visée (lecture seule)');
            $table->string('line_match', 12)->comment('curated | auto | doubtful | none');
            $table->string('event_type', 24)->nullable()->comment('App\Enums\LineEventType');
            $table->date('event_date')->nullable()->comment('premier jour de la période connue (jamais une date inventée : voir date_precision)');
            $table->smallInteger('year')->nullable();
            $table->smallInteger('year_to')->nullable()->comment('fin d\'une période (range) ou d\'une exploitation');
            $table->string('date_precision', 5)->nullable();
            $table->string('section_from', 160)->nullable();
            $table->string('section_to', 160)->nullable();
            $table->string('stop_name', 160)->nullable();
            $table->string('stop_nature', 20)->nullable()->comment('App\Enums\StationType : gare, halte, point_arret, embranchement');
            $table->integer('pk_m')->nullable()->comment('point kilométrique cité par l\'article, en mètres');
            $table->string('operator', 200)->nullable();
            $table->string('source_quote', 240)->nullable()->comment('au plus une phrase de l\'article, pour la traçabilité');
            $table->string('source_url', 500);
            $table->timestamp('article_modified_at')->nullable();
            $table->string('review', 10)->default('auto')->comment('auto | curated | rejected');
            $table->string('vff_status', 30)->nullable()->comment('comparaison avec VFF : confirmé, contradictoire, nouveau, présent, absent de VFF…');
            $table->text('vff_detail')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['line_id', 'kind']);
        });

        Schema::create('argentanwebferro_photos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('piwigo_id')->unique();
            $table->string('name', 300)->nullable();
            $table->string('file', 300)->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->timestamp('date_creation')->nullable()->comment('date de prise de vue déclarée par la galerie');
            $table->timestamp('date_available')->nullable()->comment('date de mise en ligne');
            $table->double('lat')->nullable();
            $table->double('lon')->nullable();
            $table->jsonb('albums')->comment('chemins des albums (ex. Lignes Normandes / Paris-Granville / Surdon)');
            $table->jsonb('tags')->comment('mots-clés de la galerie');
            $table->string('page_url', 500);
            $table->string('thumb_source_url', 500)->nullable();
            $table->string('display_source_url', 500)->nullable();
            $table->string('photo_class', 10)->index();
            $table->text('class_basis')->comment('règle ou relecture qui fonde la classe');
            $table->boolean('republishable')->default(false)->index();
            $table->string('author', 200)->nullable();
            $table->smallInteger('depicted_year')->nullable();
            $table->unsignedBigInteger('line_id')->nullable()->index();
            $table->unsignedBigInteger('station_id')->nullable()->index();
            $table->jsonb('media_payload')->nullable()->comment('entrée prête pour la table media (non insérée)');
            $table->timestamps();
        });

        DB::statement('ALTER TABLE argentanwebferro_facts ADD CONSTRAINT argentanwebferro_facts_kind_check CHECK (kind IN '.self::KINDS.')');
        DB::statement('ALTER TABLE argentanwebferro_facts ADD CONSTRAINT argentanwebferro_facts_precision_check
            CHECK (date_precision IS NULL OR date_precision IN '.self::PRECISIONS.')');
        DB::statement("ALTER TABLE argentanwebferro_facts ADD CONSTRAINT argentanwebferro_facts_review_check
            CHECK (review IN ('auto', 'curated', 'rejected'))");
        DB::statement('ALTER TABLE argentanwebferro_photos ADD CONSTRAINT argentanwebferro_photos_class_check CHECK (photo_class IN '.self::PHOTO_CLASSES.')');
        // Seule une œuvre propre de l'auteur de la galerie est republiable (licence CC BY-SA 4.0 affichée).
        DB::statement("ALTER TABLE argentanwebferro_photos ADD CONSTRAINT argentanwebferro_photos_republishable_check
            CHECK (NOT republishable OR photo_class = 'own_work')");
    }

    public function down(): void
    {
        Schema::dropIfExists('argentanwebferro_photos');
        Schema::dropIfExists('argentanwebferro_facts');
        Schema::dropIfExists('argentanwebferro_articles');
    }
};
