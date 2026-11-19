<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Accidents et événements graves du rail français (ROADMAP, phase 13), remplis par
 * vff:import-accidents (cf. App\Services\Accidents\AccidentImporter).
 *
 *   - accidents : un accident daté (avec sa précision), localisé, son bilan et la source du
 *     bilan, un résumé rédigé par VFF à partir des faits (jamais un texte recopié) ;
 *   - accident_lines : rattachement aux lignes VFF (proximité du tracé de l'époque, nom de la
 *     ligne cité par une source), avec la portion entre deux gares et un indice de confiance ;
 *   - accident_sources : chaque source consultée (Wikidata, Wikipédia, BEA-TT), son lien, sa
 *     licence et les faits qu'on y a lus.
 *
 * line_id, station_id et era_id sans clé étrangère, comme les autres tables de source : les
 * tables du moteur sont recalculées (le pipeline ne doit jamais buter sur une référence), et le
 * rattachement est refait à chaque import. Aucune écriture dans les tables du moteur.
 */
return new class extends Migration
{
    /** Valeurs de App\Enums\AccidentKind. */
    private const KINDS = "('collision', 'derailment', 'level_crossing', 'fire', 'explosion', 'structure_failure', 'attack', 'person_struck', 'runaway', 'incident', 'other')";

    /** Valeurs de App\Enums\AccidentDatePrecision. */
    private const PRECISIONS = "('day', 'month', 'year')";

    /** Valeurs de App\Enums\AccidentLocationPrecision. */
    private const LOCATIONS = "('exact', 'level_crossing', 'station', 'commune')";

    /** Valeurs de App\Enums\AccidentLinkConfidence. */
    private const CONFIDENCES = "('high', 'medium', 'low')";

    public function up(): void
    {
        Schema::create('accidents', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 160)->unique()->comment('date puis lieu, stable une fois créé (ex. 2013-07-12-bretigny-sur-orge)');
            $table->string('wikidata_id', 16)->nullable()->unique()->comment('élément Wikidata (Q…)');
            $table->date('occurred_on')->comment('date de l\'accident ; au 1er du mois ou au 1er janvier si la précision est le mois ou l\'année');
            $table->string('date_precision', 5)->default('day');
            $table->time('occurred_at_time')->nullable()->comment('heure locale, quand une source la donne');
            $table->string('kind', 20)->index();
            $table->jsonb('name')->comment('nom usuel par langue (fr, en, de) : libellé Wikidata, sinon nom construit (nature et commune)');
            $table->string('commune_insee', 5)->nullable();
            $table->string('commune_name', 120)->nullable();
            $table->string('department_code', 3)->nullable()->index()->comment('code INSEE du département actuel');
            $table->string('location_precision', 16)->nullable();
            $table->string('location_source', 24)->nullable()->comment('wikidata, wikipedia, sncf-pn, station, commune');
            $table->unsignedBigInteger('nearest_station_id')->nullable()->comment('gare VFF la plus proche du lieu (sans clé étrangère)');
            $table->integer('nearest_station_distance_m')->nullable();
            $table->integer('deaths')->nullable()->comment('nombre de morts ; null = inconnu des sources consultées');
            $table->integer('injuries')->nullable()->comment('nombre de blessés ; null = inconnu des sources consultées');
            $table->string('toll_source', 24)->nullable()->comment('fournisseur dont vient le bilan retenu (bea-tt, wikidata, wikipedia)');
            $table->string('toll_source_url', 500)->nullable();
            $table->jsonb('trains')->nullable()->comment('trains et matériel impliqués : [{label, category, number, material, operator}]');
            $table->jsonb('wikipedia')->nullable()->comment('articles par langue : {fr: {title, url, revid}, en: …, de: …}');
            $table->jsonb('summary')->comment('résumé rédigé par VFF à partir des faits, par langue (fr, en, de)');
            $table->string('review_status', 10)->default('ok')->index();
            $table->jsonb('review_notes')->nullable()->comment('cas douteux : [{code, detail}]');
            $table->jsonb('provenance')->nullable()->comment('fournisseur de chaque fait retenu : {date, location, deaths, injuries, trains}');
            $table->timestamp('imported_at');
            $table->timestamps();
            $table->index('occurred_on');
        });
        DB::statement('ALTER TABLE accidents ADD COLUMN geom geometry(Point, 4326) NULL');
        DB::statement('CREATE INDEX accidents_geom_gist ON accidents USING GIST (geom)');
        DB::statement('ALTER TABLE accidents ADD CONSTRAINT accidents_kind_check CHECK (kind IN '.self::KINDS.')');
        DB::statement('ALTER TABLE accidents ADD CONSTRAINT accidents_date_precision_check CHECK (date_precision IN '.self::PRECISIONS.')');
        DB::statement('ALTER TABLE accidents ADD CONSTRAINT accidents_location_precision_check CHECK (location_precision IS NULL OR location_precision IN '.self::LOCATIONS.')');
        DB::statement("ALTER TABLE accidents ADD CONSTRAINT accidents_review_status_check CHECK (review_status IN ('ok', 'to_review'))");
        DB::statement('ALTER TABLE accidents ADD CONSTRAINT accidents_toll_check CHECK ((deaths IS NULL OR deaths >= 0) AND (injuries IS NULL OR injuries >= 0))');

        Schema::create('accident_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('accident_id')->constrained('accidents')->cascadeOnDelete();
            $table->unsignedBigInteger('line_id')->index()->comment('ligne VFF (sans clé étrangère)');
            $table->boolean('is_primary')->default(false)->comment('ligne retenue comme lieu de l\'accident');
            $table->smallInteger('era_year')->nullable()->comment('ère dont le tracé a servi au rattachement');
            $table->integer('distance_m')->nullable()->comment('distance du lieu au tracé de la ligne');
            $table->integer('pk_m')->nullable()->comment('point kilométrique estimé (PK du passage à niveau, ou interpolé entre deux gares)');
            $table->unsignedBigInteger('section_from_station_id')->nullable()->comment('portion : gare d\'un côté du lieu');
            $table->unsignedBigInteger('section_to_station_id')->nullable()->comment('portion : gare de l\'autre côté');
            $table->string('method', 40)->comment('proximity, wikidata, wikipedia, level_crossing, name (plusieurs, séparés par « + »)');
            $table->string('confidence', 6);
            $table->decimal('score', 4, 3)->comment('0 à 1 : distance et concordance des sources');
            $table->timestamps();
            $table->unique(['accident_id', 'line_id']);
        });
        DB::statement('ALTER TABLE accident_lines ADD CONSTRAINT accident_lines_confidence_check CHECK (confidence IN '.self::CONFIDENCES.')');

        Schema::create('accident_sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('accident_id')->constrained('accidents')->cascadeOnDelete();
            $table->string('provider', 24)->comment('clé du fournisseur dans database/data/source_rights.php');
            $table->string('external_id', 191)->nullable()->comment('identifiant chez le fournisseur (Q…, titre d\'article, clé BEA-TT)');
            $table->string('role', 12)->comment('data (données structurées), article (encyclopédie), report (enquête), inventory (liste officielle)');
            $table->string('title', 300);
            $table->string('url', 500);
            $table->string('licence', 80);
            $table->string('licence_url', 300)->nullable();
            $table->unsignedBigInteger('revision')->nullable()->comment('révision lue (oldid Wikipédia, lastrevid Wikidata)');
            $table->jsonb('facts')->nullable()->comment('faits lus dans cette source : date, morts, blessés, lieu…');
            $table->timestamp('retrieved_at');
            $table->timestamps();
            $table->unique(['provider', 'external_id']);
            $table->unique(['accident_id', 'url']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accident_sources');
        Schema::dropIfExists('accident_lines');
        Schema::dropIfExists('accidents');
    }
};
