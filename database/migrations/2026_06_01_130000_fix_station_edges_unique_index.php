<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Corrige l'index unique de `station_edges`.
 *
 * AVANT : UNIQUE (era_id, line_id, from_station_id, to_station_id) + line_id
 * NOT NULL. Problèmes :
 *   - une même arête physique A↔B était dupliquée une fois par ligne
 *     (148 paires multi-lignes → ~200 arêtes redondantes dans le graphe) ;
 *   - les arêtes non orientées canoniquement (from > to) échappaient à la
 *     contrainte (A→B et B→A coexistaient) ;
 *   - le builder topologique (`vff:build-station-edges-topo`) insérait
 *     parfois line_id = NULL → violation de NOT NULL, builder inutilisable.
 *
 * APRÈS : line_id devient NULLABLE et l'unicité porte sur la paire de gares
 * NON ORIENTÉE par ère : UNIQUE (era_id, LEAST(from,to), GREATEST(from,to)).
 * Une seule arête physique par couple de gares, peu importe le builder.
 *
 * Migration ADDITIVE/CORRECTIVE : ne touche pas aux données existantes
 * (déduplication assurée au prochain build via insertOrIgnore).
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Déduplique d'abord les doublons existants (garde l'id le plus
        //    petit par paire non orientée + ère). Sinon l'index unique échoue.
        DB::statement(<<<'SQL'
            DELETE FROM station_edges e
            USING station_edges k
            WHERE e.era_id = k.era_id
              AND LEAST(e.from_station_id, e.to_station_id)  = LEAST(k.from_station_id, k.to_station_id)
              AND GREATEST(e.from_station_id, e.to_station_id) = GREATEST(k.from_station_id, k.to_station_id)
              AND e.id > k.id
            SQL);

        // 2. line_id nullable (le builder topologique peut produire des arêtes
        //    de raccordement sans ligne canonique).
        DB::statement('ALTER TABLE station_edges ALTER COLUMN line_id DROP NOT NULL');

        // 3. Remplace l'ancien index unique par un index d'expression sur la
        //    paire NON ORIENTÉE.
        DB::statement('ALTER TABLE station_edges DROP CONSTRAINT IF EXISTS station_edges_unique');
        DB::statement('DROP INDEX IF EXISTS station_edges_unique');
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX station_edges_unique
            ON station_edges (
                era_id,
                LEAST(from_station_id, to_station_id),
                GREATEST(from_station_id, to_station_id)
            )
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS station_edges_unique');
        // Restaure l'ancienne CONTRAINTE unique (et non un simple index, sinon le
        // rollback de split_graph_topology retrouve un schéma différent)
        // ; best effort, suppose pas de doublons.
        DB::statement('ALTER TABLE station_edges ADD CONSTRAINT station_edges_unique UNIQUE (era_id, line_id, from_station_id, to_station_id)');
        // line_id repasse NOT NULL seulement si aucune valeur NULL n'existe.
        $hasNull = DB::table('station_edges')->whereNull('line_id')->exists();
        if (! $hasNull) {
            DB::statement('ALTER TABLE station_edges ALTER COLUMN line_id SET NOT NULL');
        }
    }
};
