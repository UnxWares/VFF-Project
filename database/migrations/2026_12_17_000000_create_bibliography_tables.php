<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bibliographie des lignes et des gares (`vff:import-bibliography`) : ouvrages et articles qui en traitent.
 *
 * Deux tables, additives (aucune table existante n'est touchée) :
 *
 * - `bibliography_entries` : une référence bibliographique, une seule fois même si plusieurs lignes ou gares la
 *   partagent. Seules les données de la notice sont gardées (auteurs, titre, éditeur, lieu, année, ISBN, revue),
 *   jamais le texte ni une page du livre. `dedup_key` : `isbn:<ISBN-13>`, sinon `ark:<ark de la notice BnF>`, sinon
 *   une empreinte du titre, du premier auteur et de l'année. `provider` : fournisseur de la notice retenue (`bnf` :
 *   catalogue général de la BnF, Licence Ouverte ; `wikipedia` : référence d'un article, CC BY-SA 4.0 ; `wikidata` :
 *   CC0) ; `url` : lien vers la notice de la BnF, à défaut vers la référence d'origine.
 * - `bibliography_links` : le rattachement d'une référence à une ligne ou à une gare (exactement l'une des deux).
 *   `provider` : d'où vient le rattachement (`bnf` : titre jugé par le même score que les documents de Gallica ;
 *   `wikipedia` : référence ou bibliographie de l'article ; `wikidata` : propriété « décrit par » ou « sujet principal »),
 *   `basis` : `title`, `title_alias` (le titre nomme la ligne par son nom d'usage), `article_bibliography`,
 *   `article_reference` ou `wikidata`, `score` et `reasons` : le jugement quand il est chiffré, `origin_url` : la
 *   révision de l'article ou l'item Wikidata qui porte le rattachement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bibliography_entries', function (Blueprint $table): void {
            $table->id();
            $table->string('dedup_key', 80)->unique();
            $table->string('kind', 8)->default('book');
            $table->string('title', 1000);
            $table->jsonb('authors')->nullable();
            $table->string('publisher', 255)->nullable();
            $table->string('place', 120)->nullable();
            $table->smallInteger('year')->nullable();
            $table->string('isbn', 13)->nullable()->index();
            $table->string('journal', 255)->nullable();
            $table->string('bnf_ark', 40)->nullable()->index();
            $table->string('provider', 10);
            $table->string('url', 600)->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();
        });

        Schema::create('bibliography_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('entry_id')->constrained('bibliography_entries')->cascadeOnDelete();
            $table->foreignId('line_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('station_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('provider', 10);
            $table->string('basis', 24);
            $table->smallInteger('score')->nullable();
            $table->jsonb('reasons')->nullable();
            $table->string('origin_url', 600)->nullable();
            $table->timestamps();
            $table->index(['line_id', 'provider']);
            $table->index(['station_id', 'provider']);
        });

        // Une référence par ligne et par gare ; exactement un des deux sujets.
        DB::statement('CREATE UNIQUE INDEX bibliography_links_line_entry_unique ON bibliography_links (line_id, entry_id) WHERE line_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX bibliography_links_station_entry_unique ON bibliography_links (station_id, entry_id) WHERE station_id IS NOT NULL');
        DB::statement('ALTER TABLE bibliography_links ADD CONSTRAINT bibliography_links_one_subject CHECK (num_nonnulls(line_id, station_id) = 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('bibliography_links');
        Schema::dropIfExists('bibliography_entries');
    }
};
