<?php

use App\Services\Greenways\GreenwayLinker;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nom affiché des voies vertes (`line_greenways.display_name`) : celui de l'itinéraire cyclable OSM, sinon
 * celui du way, à condition qu'il désigne un itinéraire (App\Services\Greenways\GreenwayNames::displayName :
 * ni lieu, ni type de voie, ni nom de rue), sinon null. Calculé par `vff:link-greenways` et lu tel quel par
 * la fiche ligne, l'API REST, GraphQL, le serveur MCP et les tuiles du calque « Voies vertes », qui
 * montraient jusqu'ici le nom OSM brut. Les colonnes `name` et `relation_name` gardent les noms d'OSM.
 * Les couples déjà rattachés reçoivent leur nom affiché ici.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('line_greenways', function (Blueprint $table): void {
            $table->text('display_name')->nullable()->after('relation_name');
        });
        app(GreenwayLinker::class)->fillDisplayNames();
    }

    public function down(): void
    {
        Schema::table('line_greenways', function (Blueprint $table): void {
            $table->dropColumn('display_name');
        });
    }
};
