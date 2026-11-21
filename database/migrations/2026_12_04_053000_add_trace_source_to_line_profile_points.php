<?php

use App\Services\Profiles\LineProfileBuilder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Source du tracé sous chaque point du profil (`line_profile_points.trace_source`) : la règle des
 * droits des tracés (App\Services\TraceExport\LineTraceExport) retient la position d'un point dont le tracé
 * vient d'une source non redistribuable (archeoferroviaire, tous droits réservés) dans l'API et le serveur
 * MCP. Remplie pour les points existants d'après le morceau du référentiel le plus proche ; réversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('line_profile_points', function (Blueprint $table): void {
            $table->string('trace_source', 40)->nullable()->comment('Source du tracé sous le point (line_reference_parts.source, registre des droits) ; null : inconnue, position retenue');
        });
        DB::statement(LineProfileBuilder::TRACE_SOURCE_SQL.' WHERE p.geom IS NOT NULL');
    }

    public function down(): void
    {
        Schema::table('line_profile_points', function (Blueprint $table): void {
            $table->dropColumn('trace_source');
        });
    }
};
