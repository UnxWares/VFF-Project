<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Référentiel `stations` multi-sources : identifiants externes, précision des
 * dates, provenance attribut par attribut et contrôle de cohérence des
 * positions entre sources.
 *
 *   - provenance : {"name": "sncf", "geom": "sncf", "opened_at": "wikidata", …}
 *     (clé = attribut, valeur = source retenue ; « inferred:ohm_trace » pour une
 *     date déduite des tracés datés voisins) ;
 *   - position_spread_m : plus grand écart entre la position retenue et celle
 *     d'une autre source rattachée ; position_flagged = écart > 100 m ;
 *   - lifecycle : état physique actuel (active | disused | abandoned | razed).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stations', function (Blueprint $table): void {
            $table->string('uic_code', 10)->nullable()->index();
            $table->string('wikidata_id', 20)->nullable()->index();
            $table->string('opened_precision', 5)->nullable()->comment('day | month | year');
            $table->string('closed_precision', 5)->nullable();
            $table->string('lifecycle', 20)->nullable()->comment('active | disused | abandoned | razed | unknown');
            $table->jsonb('provenance')->nullable()->comment('Attribut → source retenue');
            $table->string('position_source', 20)->nullable();
            $table->integer('position_spread_m')->nullable()->comment('Écart max entre sources (m)');
            $table->boolean('position_flagged')->default(false)->index()->comment('Écart > 100 m entre sources');
            $table->smallInteger('sources_count')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('stations', function (Blueprint $table): void {
            $table->dropColumn([
                'uic_code', 'wikidata_id', 'opened_precision', 'closed_precision', 'lifecycle',
                'provenance', 'position_source', 'position_spread_m', 'position_flagged', 'sources_count',
            ]);
        });
    }
};
