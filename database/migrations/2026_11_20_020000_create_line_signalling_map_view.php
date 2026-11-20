<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tracé des tranches de signalisation (calque « Signalisation » de la carte /maps).
 *
 * `line_signalling` donne chaque tranche par points kilométriques (PK) ; pour la dessiner, on la
 * place sur les formes officielles des lignes de SNCF Réseau (`sncf_line_geometries`, un tronçon
 * avec son PK de début et de fin). Le PK n'est pas la distance mesurée sur le tracé : il est
 * interpolé entre des points de calage dont on connaît à la fois le PK et la position, les
 * passages à niveau (`sncf_level_crossings`, à moins de 30 m du tronçon) et les deux extrémités
 * du tronçon. Mesure du 20/11/2026 en gardant un passage à niveau sur deux comme calage : l'autre
 * moitié retombe à 34 m près en médiane, à moins de 190 m dans 9 cas sur 10.
 *
 * Vue matérialisée, recalculée par `vff:refresh-signalling-map` (et à la fin de
 * `vff:import-sncf-signalling`) : environ 9 s pour 1 400 tranches. Aucune donnée source modifiée.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE MATERIALIZED VIEW line_signalling_map AS
            WITH troncons AS (
                SELECT g.id, g.code_ligne,
                       LEAST(g.pk_debut_m, g.pk_fin_m) AS d, GREATEST(g.pk_debut_m, g.pk_fin_m) AS f,
                       CASE WHEN g.pk_debut_m <= g.pk_fin_m THEN g.geom ELSE ST_Reverse(g.geom) END AS geom
                FROM sncf_line_geometries g
                WHERE g.pk_debut_m IS NOT NULL AND g.pk_fin_m IS NOT NULL AND g.pk_debut_m <> g.pk_fin_m
            ),
            raw_controls AS (
                SELECT t.id AS troncon_id, pn.pk_m AS pk, ST_LineLocatePoint(t.geom, pn.geom) AS frac
                FROM troncons t
                JOIN sncf_level_crossings pn ON pn.code_ligne = t.code_ligne AND pn.pk_m > t.d AND pn.pk_m < t.f
                WHERE ST_DWithin(t.geom::geography, pn.geom::geography, 30)
                UNION ALL SELECT id, d, 0 FROM troncons
                UNION ALL SELECT id, f, 1 FROM troncons
            ),
            controls AS (
                SELECT troncon_id, pk, frac FROM (
                    SELECT c.*, MAX(frac) OVER (PARTITION BY troncon_id ORDER BY pk, frac ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING) AS before
                    FROM raw_controls c
                ) x WHERE before IS NULL OR frac >= before
            ),
            pieces AS (
                SELECT ls.id AS range_id, ls.line_id, ls.era_id, ls.signalling, ls.train_protection, ls.kvb_equipped,
                       ls.pk_start_m, ls.pk_end_m, t.id AS troncon_id, t.geom,
                       GREATEST(ls.pk_start_m, t.d) AS pa, LEAST(ls.pk_end_m, t.f) AS pb
                FROM line_signalling ls
                JOIN lines l ON l.id = ls.line_id
                JOIN troncons t ON t.code_ligne = l.code AND ls.pk_start_m < t.f AND ls.pk_end_m > t.d
            )
            SELECT p.range_id, p.troncon_id, p.line_id, p.era_id, p.signalling, p.train_protection, p.kvb_equipped, p.pk_start_m, p.pk_end_m,
                   ST_LineSubstring(p.geom, LEAST(fa.v, fb.v), GREATEST(fa.v, fb.v)) AS geom
            FROM pieces p
            CROSS JOIN LATERAL (
                SELECT CASE WHEN hi.pk = lo.pk THEN lo.frac ELSE lo.frac + (hi.frac - lo.frac) * (p.pa - lo.pk)::float8 / (hi.pk - lo.pk) END AS v
                FROM (SELECT pk, frac FROM controls c WHERE c.troncon_id = p.troncon_id AND c.pk <= p.pa ORDER BY pk DESC LIMIT 1) lo,
                     (SELECT pk, frac FROM controls c WHERE c.troncon_id = p.troncon_id AND c.pk >= p.pa ORDER BY pk LIMIT 1) hi
            ) fa
            CROSS JOIN LATERAL (
                SELECT CASE WHEN hi.pk = lo.pk THEN lo.frac ELSE lo.frac + (hi.frac - lo.frac) * (p.pb - lo.pk)::float8 / (hi.pk - lo.pk) END AS v
                FROM (SELECT pk, frac FROM controls c WHERE c.troncon_id = p.troncon_id AND c.pk <= p.pb ORDER BY pk DESC LIMIT 1) lo,
                     (SELECT pk, frac FROM controls c WHERE c.troncon_id = p.troncon_id AND c.pk >= p.pb ORDER BY pk LIMIT 1) hi
            ) fb
            WITH NO DATA
            SQL);
        DB::statement('CREATE INDEX line_signalling_map_geom_gist ON line_signalling_map USING GIST (geom)');
        DB::statement('CREATE INDEX line_signalling_map_era_index ON line_signalling_map (era_id)');
        DB::statement('REFRESH MATERIALIZED VIEW line_signalling_map');
    }

    public function down(): void
    {
        DB::statement('DROP MATERIALIZED VIEW IF EXISTS line_signalling_map');
    }
};
