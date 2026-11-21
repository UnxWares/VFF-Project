<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Altitude du référentiel, point par point (vff:compute-line-profiles, méthode du 21/11/2026).
 *
 * Le profil en long suit désormais le tracé de référence de la ligne entière (line_reference_parts,
 * toutes époques, lignes fermées et déposées comprises), morceaux séparés par des trous compris.
 * Chaque point est une ligne de `line_profile_points` : rang, distance le long du parcours, PK
 * calé sur les gares, altitude, provenance (BD TOPO, RGE ALTI), interpolé ou non, ouvrage, morceau
 * et position. La fiche, l'API REST, GraphQL et le serveur MCP lisent cette table.
 *
 * `line_profiles` perd sa colonne JSONB `points` (les points existants sont recopiés dans la
 * nouvelle table, provenance inconnue), son ère devient facultative (null : référentiel toutes
 * époques) et gagne la longueur mesurée, la longueur hors parcours, le nombre de gares de calage du
 * PK et la liste des embranchements.
 * Réversible : `down()` reconstruit la colonne JSONB à partir des points.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('line_profile_points', function (Blueprint $table): void {
            $table->foreignId('line_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('line_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('seq')->comment('Rang du point dans l\'ordre du parcours, à partir de 0');
            $table->unsignedInteger('distance_m')->comment('Distance le long du parcours depuis son origine, trous compris, en mètres');
            $table->integer('pk_m')->nullable()->comment('PK estimé en mètres, calé sur les PK officiels des gares (interpolation linéaire) ; null sans gare de calage');
            $table->float('altitude_m')->nullable()->comment('Altitude NGF-IGN69 en mètres, au décimètre ; null sans valeur');
            $table->string('source', 8)->nullable()->comment('Provenance de l\'altitude mesurée : bdtopo (voie, 3D) ou rge_alti (terrain) ; null si interpolée, absente ou inconnue (profil antérieur au 21/11/2026)');
            $table->boolean('interpolated')->default(false)->comment('Altitude interpolée entre deux points mesurés (tunnel, pont, aberration, petit trou)');
            $table->string('structure', 6)->nullable()->comment('Ouvrage de la BD TOPO sous le point : tunnel ou bridge');
            $table->unsignedSmallInteger('piece')->default(0)->comment('Morceau continu du parcours (numéroté à partir de 0, un nouveau après chaque trou)');
            $table->unsignedSmallInteger('branch')->nullable()->comment('Embranchement (1, 2…) mis bout à bout après la ligne principale ; null sur la ligne principale');
            $table->primary(['line_profile_id', 'seq']);
            $table->index(['line_id', 'pk_m']);
        });
        DB::statement('ALTER TABLE line_profile_points ADD COLUMN geom geometry(Point, 4326)');
        DB::statement('COMMENT ON COLUMN line_profile_points.geom IS \'Position du point sur le tracé de référence (WGS 84)\'');
        DB::statement("ALTER TABLE line_profile_points ADD CONSTRAINT line_profile_points_source_check CHECK (source IN ('bdtopo', 'rge_alti'))");
        DB::statement("ALTER TABLE line_profile_points ADD CONSTRAINT line_profile_points_structure_check CHECK (structure IN ('tunnel', 'bridge'))");

        // Points des profils déjà calculés (parcours de l'ère courante), provenance inconnue.
        DB::statement(<<<'SQL'
            INSERT INTO line_profile_points (line_profile_id, line_id, seq, distance_m, altitude_m)
            SELECT lp.id, lp.line_id, (p.ord - 1)::int, (p.v->>0)::int, (p.v->>1)::float
            FROM line_profiles lp, jsonb_array_elements(lp.points) WITH ORDINALITY AS p(v, ord)
            SQL);

        Schema::table('line_profiles', function (Blueprint $table): void {
            $table->dropColumn('points');
            $table->unsignedBigInteger('era_id')->nullable()->comment('Ère du tracé échantillonné ; null : tracé de référence de la ligne, toutes époques')->change();
            $table->unsignedInteger('covered_m')->nullable()->after('trace_length_m')->comment('Longueur du parcours hors trous, en mètres');
            $table->unsignedInteger('off_path_m')->nullable()->after('covered_m')->comment('Longueur du référentiel que le parcours n\'emprunte pas (épis, raccordements, tracés doubles), en mètres');
            $table->unsignedSmallInteger('pk_anchors')->default(0)->after('off_path_m')->comment('Gares dont le PK officiel sert à caler le PK des points');
            $table->jsonb('branches')->default('[]')->after('gaps')->comment('[{from_m, to_m}] : embranchements (épis, réseaux en arbre) mis bout à bout après la ligne principale, chacun après un trou');
        });
        DB::statement('COMMENT ON COLUMN line_profiles.trace_length_m IS \'Longueur du tracé de référence de la ligne (toutes époques), en mètres ; profils antérieurs au 21/11/2026 : tracé de l\'\'ère\'');
    }

    public function down(): void
    {
        Schema::table('line_profiles', function (Blueprint $table): void {
            $table->jsonb('points')->default('[]');
        });
        DB::statement(<<<'SQL'
            UPDATE line_profiles lp SET points = COALESCE((
                SELECT jsonb_agg(jsonb_build_array(p.distance_m, p.altitude_m) ORDER BY p.seq)
                FROM line_profile_points p WHERE p.line_profile_id = lp.id
            ), '[]'::jsonb)
            SQL);
        // Défaut provisoire (remplissage des lignes existantes) : la colonne d'origine n'en avait pas.
        DB::statement('ALTER TABLE line_profiles ALTER COLUMN points DROP DEFAULT');
        // Un profil du référentiel n'a pas d'ère : il ne peut pas revenir dans l'ancien schéma.
        DB::table('line_profiles')->whereNull('era_id')->delete();
        Schema::table('line_profiles', function (Blueprint $table): void {
            $table->unsignedBigInteger('era_id')->nullable(false)->change();
            $table->dropColumn(['covered_m', 'off_path_m', 'pk_anchors', 'branches']);
        });
        Schema::dropIfExists('line_profile_points');
    }
};
