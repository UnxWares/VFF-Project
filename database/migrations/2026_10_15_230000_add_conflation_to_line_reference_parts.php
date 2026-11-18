<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Provenance géométrique et fiabilité des morceaux du tracé de référence
 * (vff:build-line-references).
 *
 * `source` reste la source qui DÉFINIT le morceau (ex. archeoferroviaire pour
 * une ligne disparue : le moteur décennal s'en sert pour savoir qu'elle a
 * disparu). Quand ce morceau peu précis a été recalé sur une géométrie précise
 * (BD TOPO, OSM, contribution) qui suit le même axe, `matched_source` et
 * `matched_trace_ids` disent d'où vient le DESSIN. `connector` = raccord droit
 * court entre deux géométries précises (trou de données < 150 m).
 *
 * Croisement des sources, mesuré tous les 25 m le long du morceau :
 *   - sources_agreeing : nombre de familles de sources concordantes sur l'axe
 *     (géométrie retenue comprise) ;
 *   - gap_median_m / gap_max_m : écart à la source indépendante la plus proche ;
 *   - confidence : high (≥ 2 sources précises concordantes à 5 m), medium
 *     (1 source précise), low (archeo / OHM / SNCF seuls ou raccord) ;
 *   - agreement : détail par famille {median, max, coverage, agree}.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('line_reference_parts', function (Blueprint $table): void {
            $table->string('matched_source', 40)->nullable()->after('source_trace_ids')
                ->comment('Source du dessin quand le morceau a été recalé (bdtopo, osm, contrib, connector)');
            $table->jsonb('matched_trace_ids')->nullable()->after('matched_source');
            $table->string('confidence', 8)->nullable()->after('matched_trace_ids')->comment('high | medium | low');
            $table->smallInteger('sources_agreeing')->nullable()->after('confidence');
            $table->float('gap_median_m')->nullable()->after('sources_agreeing');
            $table->float('gap_max_m')->nullable()->after('gap_median_m');
            $table->jsonb('agreement')->nullable()->after('gap_max_m');
            $table->index(['line_id', 'confidence']);
        });
    }

    public function down(): void
    {
        Schema::table('line_reference_parts', function (Blueprint $table): void {
            $table->dropIndex(['line_id', 'confidence']);
            $table->dropColumn(['matched_source', 'matched_trace_ids', 'confidence', 'sources_agreeing', 'gap_median_m', 'gap_max_m', 'agreement']);
        });
    }
};
