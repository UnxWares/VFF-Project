<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contexte sourcé des accidents (vff:enrich-accidents, fichiers database/data/accident_contexts/<slug>.php) :
 *
 *   - accident_references : documents consultés pour le contexte (rapports d'enquête, Journal officiel, débats
 *     parlementaires, presse, archives, ouvrages, sites d'histoire locale), avec leur fiabilité, leur accès et la
 *     date de consultation ;
 *   - accident_context_entries : énoncés du contexte rédigés par VFF (fr, en, de), rangés par section
 *     (circonstances, déroulement, victimes, causes, suites, mémoire, contradictions), chacun avec ses références ;
 *   - accident_images : images de Wikimedia Commons (auteur et licence relus sur Commons) ;
 *   - accidents.context_corrections / context_checked_on : corrections de date, d'heure ou de bilan appliquées
 *     d'après ces sources (valeur d'avant gardée), et date de la dernière recherche.
 *
 * Tables à part : vff:import-accidents ne les écrit pas (elles survivent à un nouvel import ; seules les
 * corrections sont à rejouer, voir la commande).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accident_references', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('accident_id')->constrained('accidents')->cascadeOnDelete();
            $table->string('key', 60)->comment('clé du document dans le fichier de données (citée par les énoncés)');
            $table->unsignedSmallInteger('position');
            $table->string('provider', 32)->comment('fournisseur (registre des droits, table accident_references)');
            $table->string('kind', 16)->comment('report, official, parliament, press, book, scholarly, archive, memorial, web, forum, encyclopedia, data, video');
            $table->string('reliability', 16)->comment('official, scholarly, press, local_history, association, encyclopedia, forum');
            $table->string('title', 500);
            $table->string('publisher', 200)->nullable();
            $table->string('author', 200)->nullable();
            $table->string('published', 10)->nullable()->comment('date du document : AAAA, AAAA-MM ou AAAA-MM-JJ');
            $table->string('locator', 160)->nullable()->comment('page, numéro, section ou cote');
            $table->string('url', 800);
            $table->string('access', 12)->comment('open (lu), paywall (payant, lien seulement), link_only (à consulter, non lu)');
            $table->date('consulted_on');
            $table->string('note', 500)->nullable()->comment('ce que le document apporte ou sa limite (français)');
            $table->timestamps();
            $table->unique(['accident_id', 'key']);
            $table->index('provider');
        });

        Schema::create('accident_context_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('accident_id')->constrained('accidents')->cascadeOnDelete();
            $table->string('section', 16)->comment('circumstances, course, victims, causes, aftermath, memory, disagreements');
            $table->unsignedSmallInteger('position');
            $table->jsonb('text')->comment('énoncé rédigé par VFF, par langue (fr, en, de)');
            $table->jsonb('reference_keys')->comment('clés des documents (accident_references.key) qui appuient l\'énoncé');
            $table->jsonb('quote')->nullable()->comment('courte citation exacte d\'une source ouverte : text, lang, reference');
            $table->timestamps();
            $table->index(['accident_id', 'section', 'position']);
        });

        Schema::create('accident_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('accident_id')->constrained('accidents')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('file', 255)->comment('nom du fichier sur Wikimedia Commons (File:…)');
            $table->string('page_url', 600);
            $table->string('image_url', 800);
            $table->string('thumb_url', 800)->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('author', 300)->comment('auteur lu sur Commons (texte brut)');
            $table->string('licence', 80);
            $table->string('licence_url', 300)->nullable();
            $table->string('date', 60)->nullable()->comment('date de l\'image lue sur Commons (texte brut)');
            $table->jsonb('caption')->comment('légende rédigée par VFF, par langue');
            $table->timestamp('retrieved_at');
            $table->timestamps();
            $table->unique(['accident_id', 'file']);
        });

        Schema::table('accidents', function (Blueprint $table): void {
            $table->jsonb('context_corrections')->nullable()->comment('corrections appliquées d\'après le contexte : champ, valeur d\'avant, valeur retenue, références, raison');
            $table->date('context_checked_on')->nullable()->comment('date de la recherche documentaire du contexte');
        });
    }

    public function down(): void
    {
        Schema::table('accidents', function (Blueprint $table): void {
            $table->dropColumn(['context_corrections', 'context_checked_on']);
        });
        Schema::dropIfExists('accident_images');
        Schema::dropIfExists('accident_context_entries');
        Schema::dropIfExists('accident_references');
    }
};
