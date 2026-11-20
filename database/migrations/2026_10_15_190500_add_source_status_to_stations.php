<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Statut actuel d'une gare SELON SES SOURCES (SNCF voyageurs/fret, état OSM,
 * Wikidata…), avant plafonnement par l'état des lignes qui la desservent.
 *
 *   active | tourist | no_commercial | declassified (existe, fermée) | gone (n'existe plus)
 *
 * `stations.status` (affiché) = ce statut plafonné par les tronçons de l'ère
 * courante (vff:build-station-eras) : une gare « fret » selon SNCF sur une
 * ligne fermée est fermée. Garder la valeur brute rend le calcul rejouable
 * après chaque régénération de line_segments.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stations', function (Blueprint $table): void {
            $table->string('source_status', 20)->nullable()->comment('Statut actuel selon les sources, avant plafonnement par les lignes');
        });
    }

    public function down(): void
    {
        Schema::table('stations', function (Blueprint $table): void {
            $table->dropColumn('source_status');
        });
    }
};
