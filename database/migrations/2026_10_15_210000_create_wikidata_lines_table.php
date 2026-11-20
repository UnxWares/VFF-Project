<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Items Wikidata « ligne de chemin de fer » situés en France (P17 = Q142),
 * TELS QU'IMPORTÉS par vff:import-wikidata-lines, rattachés ou non à une
 * ligne VFF.
 *
 * Pourquoi une table dédiée plutôt que de tout verser dans `lines` :
 *   - le lien item ↔ ligne doit survivre aux importeurs qui réécrivent
 *     `lines.alt_names` (on y recopie le QID, mais la vérité est ici) ;
 *   - on garde les faits normalisés (longueur, écartement, électrification,
 *     exploitants, terminus avec coordonnées…) MÊME quand la ligne VFF a déjà
 *     une valeur plus fiable : c'est la matière des rapports de conflits ;
 *   - les items NON rattachés (lignes absentes de VFF) restent consultables,
 *     avec leurs points (terminus + gares P81), par l'agent de couverture.
 *
 * Wikidata est sous CC0 ; on cite quand même l'item (source_url des events).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wikidata_lines', function (Blueprint $table): void {
            $table->id();
            $table->string('qid', 16)->unique();
            $table->string('label', 255)->nullable()->comment('Libellé fr (sinon en)');
            $table->string('frwiki_title', 255)->nullable();
            $table->string('category', 20)->comment('ferroviaire | urbain | funiculaire | service | projet');
            $table->jsonb('kinds')->nullable()->comment('QIDs P31 de l\'item');
            $table->jsonb('codes')->nullable()->comment('Codes P1671 bruts et normalisés (6 chiffres), avec portée (P518)');
            $table->foreignId('line_id')->nullable()->constrained('lines')->nullOnDelete();
            $table->string('match_method', 20)->nullable()
                ->comment('code | code_part | name_geo | name | geo | duplicate | ambiguous | none');
            $table->float('match_score')->nullable();
            $table->text('match_note')->nullable();
            $table->jsonb('facts')->nullable()->comment('Faits normalisés extraits de l\'item (longueur, écartement, événements…)');
            $table->jsonb('termini')->nullable()->comment('[{qid, label, lat, lon}] (P559 / P609 / P1427 / P1444)');
            $table->jsonb('conflicts')->nullable()->comment('Écarts avec les valeurs VFF / autres sources (non écrasées)');
            $table->integer('station_count')->default(0)->comment('Gares Wikidata dont P81 pointe vers l\'item');
            $table->timestamp('fetched_at');
            $table->timestamps();

            $table->index('line_id');
            $table->index(['category', 'match_method']);
        });
        DB::statement('ALTER TABLE wikidata_lines ADD COLUMN points geometry(MultiPoint, 4326)');
        DB::statement('CREATE INDEX wikidata_lines_points_gist ON wikidata_lines USING GIST (points)');
    }

    public function down(): void
    {
        Schema::dropIfExists('wikidata_lines');
    }
};
