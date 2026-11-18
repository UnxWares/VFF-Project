<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rattache les gares aux lignes (y compris disparues) → `station_lines`.
 *
 *   1. sncf_pk   : liste-des-gares SNCF (code ligne + PK officiel) ;
 *   2. source_pk : gares par ligne des listes historiques (`source_stations` :
 *                  lignes-oubliees ; schémas de ligne Wikipédia, source
 *                  « wikipedia_schema ») posées sur le tracé par
 *                  vff:import-station-sources --source=pk, PK de la source
 *                  (schéma : PK recalé dans le repère officiel, sinon projeté) ;
 *      wikidata_p81 : « ligne desservie » (P81) de l'élément Wikidata de la
 *                  gare, via la correspondance wikidata_lines → lines ;
 *   3. proximité : une des positions de la gare (retenue ou d'une source) à
 *                  ≤ --buffer m (150 m pour une position Wikidata ou
 *                  interpolée par PK) d'un tracé rattaché à la
 *                  ligne (line_segments, source_traces.line_id,
 *                  line_reference_geometries, géométries SNCF par code).
 *                  Une gare SNCF n'est jamais rattachée par simple proximité à
 *                  une ligne du RFN dont la liste officielle ne la cite pas
 *                  (ponts, croisements), SAUF si elle en est le terminus
 *                  (method = junction) : liste-des-gares ne cite une gare que
 *                  sous UNE ligne, les gares de bifurcation manquaient donc à
 *                  toutes leurs autres lignes (Briouze, origine de la ligne
 *                  434000 Briouze - La Ferté-Macé). Terminus = nom de la gare
 *                  dans le nom de la ligne (« Ligne de Briouze à … »), ou
 *                  extrémité du tracé SNCF de la ligne à ≤ 300 m (hors LGV).
 *
 * PK manquant : projection sur le tracé, interpolée entre les gares voisines
 * de PK connu de la même ligne (pk_source = projected), sinon abscisse
 * curviligne depuis le début du tronçon (pk_source = projected_raw).
 *
 * Période de desserte (first_year / last_year) : événements de la ligne
 * (`line_events`) dont la section couvre le PK de la gare, sinon tracés datés
 * rattachés à la ligne à proximité.
 */
class AttachStations extends Command
{
    protected $signature = 'vff:attach-stations {--buffer=60 : Distance max gare ↔ tracé (m) pour une position précise}
        {--years-only : Recalcule seulement les années de desserte (first_year / last_year) des rattachements existants}
        {--except=* : Avec --years-only, codes de lignes à laisser de côté}';

    protected $description = 'Rattache les gares aux lignes (PK SNCF, PK des listes historiques, proximité des tracés) → station_lines';

    /**
     * Un événement de ligne (alias le) concerne la gare (alias s, rattachement
     * sl) si sa section par PK couvre le PK de la gare (± 500 m), sinon si sa
     * géométrie de section passe à < 300 m, sinon s'il porte sur toute la
     * ligne. Les sections nommées non résolues (pending / unresolved) sont
     * ignorées : on ne sait pas quelle portion elles visent.
     */
    public const EVENT_COVERS_STATION = <<<'SQL'
        (
          (le.section_pk_start_m IS NOT NULL AND le.section_pk_end_m IS NOT NULL AND sl.pk_m IS NOT NULL
           AND sl.pk_m BETWEEN LEAST(le.section_pk_start_m, le.section_pk_end_m) - 500
                           AND GREATEST(le.section_pk_start_m, le.section_pk_end_m) + 500)
          OR (le.section_geom IS NOT NULL AND (le.section_pk_start_m IS NULL OR sl.pk_m IS NULL)
              AND ST_DWithin(le.section_geom::geography, s.geom::geography, 300))
          OR (le.section_pk_start_m IS NULL AND le.section_geom IS NULL
              AND (le.section_resolution = 'whole_line' OR (le.section_from IS NULL AND le.section_to IS NULL)))
        )
        SQL;

    /**
     * La gare (alias st, position p) est un terminus de la ligne (alias l) :
     * son nom figure dans le nom de la ligne (« Ligne de Briouze à La
     * Ferté-Macé », mot entier, sans la précision entre parenthèses), ou une
     * extrémité du tracé SNCF de la ligne (tmp_line_ends, hors LGV) est à
     * ≤ 300 m.
     */
    public const TERMINUS = <<<'SQL'
        (
          lower(unaccent(l.name)) ~ ('\m' || regexp_replace(lower(unaccent(regexp_replace(st.name, '\s*\(.*\)$', ''))),
                                                             '([.()+*?\[\]\\^$|{}])', '\\\1', 'g') || '\M')
          OR EXISTS (SELECT 1 FROM tmp_line_ends te WHERE te.line_id = l.id
                     AND ST_DWithin(te.pt::geography, p.geom::geography, 300))
        )
        SQL;

    public function handle(): int
    {
        if ($this->option('years-only')) {
            $except = DB::table('lines')->whereIn('code', (array) $this->option('except'))->pluck('id')->map(fn ($id) => (int) $id)->all();
            $this->computeServiceYears($except);
            $this->line('  années de desserte recalculées'.($except ? ' (lignes laissées de côté : '.implode(', ', (array) $this->option('except')).')' : ''));

            return self::SUCCESS;
        }
        $buffer = (int) $this->option('buffer');
        DB::table('station_lines')->delete();
        $now = now();

        // 1. Liste officielle SNCF.
        $n1 = DB::affectingStatement(<<<'SQL'
            INSERT INTO station_lines (station_id, line_id, method, pk_m, pk_source, distance_m, source_ref, created_at, updated_at)
            SELECT DISTINCT ON (ss.station_id, l.id) ss.station_id, l.id, 'sncf_pk', (x->>'pk_m')::int, 'sncf', 0,
                   'sncf:'||ss.source_ref, NOW(), NOW()
            FROM station_sources ss
            CROSS JOIN LATERAL jsonb_array_elements(ss.properties->'lines') x
            JOIN lines l ON l.code = x->>'code_ligne'
            WHERE ss.source = 'sncf' AND ss.station_id IS NOT NULL
            ORDER BY ss.station_id, l.id, (x->>'pk_m')::int
            ON CONFLICT (station_id, line_id) DO NOTHING
        SQL);
        $this->line("  sncf_pk : {$n1}");

        // 2. Listes historiques par ligne (PK de la source).
        $n2 = DB::affectingStatement(<<<'SQL'
            INSERT INTO station_lines (station_id, line_id, method, pk_m, pk_source, distance_m, source_ref, created_at, updated_at)
            SELECT DISTINCT ON (ss.station_id, (ss.properties->>'line_id')::bigint)
                   ss.station_id, (ss.properties->>'line_id')::bigint, 'source_pk', (ss.properties->>'pk_m')::int, ss.source,
                   ss.match_distance_m, ss.source||':'||ss.source_ref, NOW(), NOW()
            FROM station_sources ss
            JOIN lines l ON l.id = (ss.properties->>'line_id')::bigint
            WHERE (ss.properties->>'source_station_id' IS NOT NULL OR ss.source = 'wikipedia_schema') AND ss.station_id IS NOT NULL
            ORDER BY ss.station_id, (ss.properties->>'line_id')::bigint, (ss.properties->>'pk_m') IS NULL, ss.id
            ON CONFLICT (station_id, line_id) DO UPDATE
               SET pk_m = COALESCE(station_lines.pk_m, EXCLUDED.pk_m),
                   pk_source = COALESCE(station_lines.pk_source, EXCLUDED.pk_source)
        SQL);
        $this->line("  source_pk : {$n2}");

        // 2 bis. Wikidata P81 (« ligne desservie ») via wikidata_lines → lines.
        if (Schema::hasTable('wikidata_lines')) {
            $n2b = DB::affectingStatement(<<<'SQL'
                INSERT INTO station_lines (station_id, line_id, method, source_ref, created_at, updated_at)
                SELECT DISTINCT ss.station_id, wl.line_id, 'wikidata_p81', 'wikidata:'||ss.source_ref||'#P81', NOW(), NOW()
                FROM station_sources ss
                CROSS JOIN LATERAL jsonb_array_elements_text(ss.properties->'lines') q
                JOIN wikidata_lines wl ON wl.qid = q AND wl.line_id IS NOT NULL
                WHERE ss.source = 'wikidata' AND ss.station_id IS NOT NULL
                ON CONFLICT (station_id, line_id) DO NOTHING
            SQL);
            $this->line("  wikidata_p81 : {$n2b}");
        }

        // 3. Proximité des tracés rattachés aux lignes.
        $geoms = [
            'segment' => 'SELECT line_id, geom FROM line_segments WHERE line_id IS NOT NULL',
            'trace' => 'SELECT line_id, geom FROM source_traces WHERE line_id IS NOT NULL',
            'reference' => Schema::hasTable('line_reference_geometries') ? 'SELECT line_id, geom FROM line_reference_geometries' : null,
            'sncf_geometry' => 'SELECT l.id AS line_id, lg.geom FROM sncf_line_geometries lg JOIN lines l ON l.code = lg.code_ligne',
        ];
        // Toutes les positions connues de chaque gare (retenue + sources) : la
        // position SNCF d'une gare peut être à 200 m du tracé OSM / OHM.
        DB::statement('DROP TABLE IF EXISTS tmp_pos');
        DB::statement(<<<'SQL'
            CREATE TEMP TABLE tmp_pos AS
            SELECT station_id, geom, source FROM station_sources WHERE station_id IS NOT NULL
            UNION ALL SELECT id, geom, position_source FROM stations
        SQL);
        DB::statement('CREATE INDEX ON tmp_pos USING GIST (geom)');
        DB::statement('ANALYZE tmp_pos');
        // Extrémités du tracé SNCF de chaque ligne (début du premier tronçon, fin du dernier).
        DB::statement('DROP TABLE IF EXISTS tmp_line_ends');
        DB::statement(<<<'SQL'
            CREATE TEMP TABLE tmp_line_ends AS
            SELECT l.id AS line_id, e.pt FROM lines l
            CROSS JOIN LATERAL (
                (SELECT ST_StartPoint(g.geom) AS pt FROM sncf_line_geometries g WHERE g.code_ligne = l.code ORDER BY g.pk_debut_m NULLS LAST LIMIT 1)
                UNION ALL
                (SELECT ST_EndPoint(g.geom) FROM sncf_line_geometries g WHERE g.code_ligne = l.code ORDER BY g.pk_fin_m DESC NULLS LAST LIMIT 1)
            ) e
            WHERE l.code IS NOT NULL AND NOT l.is_lgv
        SQL);
        DB::statement('CREATE INDEX ON tmp_line_ends (line_id)');
        foreach (array_filter($geoms) as $method => $sql) {
            $rfn = "EXISTS (SELECT 1 FROM station_sources x WHERE x.station_id = p.station_id AND x.source = 'sncf')
                    AND EXISTS (SELECT 1 FROM sncf_gares sg WHERE sg.code_ligne = l.code)";
            $terminus = self::TERMINUS;
            $n = DB::affectingStatement(<<<SQL
                INSERT INTO station_lines (station_id, line_id, method, distance_m, created_at, updated_at)
                SELECT DISTINCT ON (p.station_id, g.line_id) p.station_id, g.line_id,
                       CASE WHEN {$rfn} THEN 'junction' ELSE '{$method}' END,
                       ROUND(ST_Distance(p.geom::geography, g.geom::geography)), NOW(), NOW()
                FROM ({$sql}) g
                JOIN tmp_pos p ON ST_DWithin(p.geom, g.geom, 0.0025)
                 AND ST_DWithin(p.geom::geography, g.geom::geography,
                     CASE WHEN p.source IN ('sncf', 'sncf_voyageurs', 'osm', 'ohm') THEN :buffer ELSE GREATEST(:buffer2, 150) END)
                JOIN lines l ON l.id = g.line_id
                JOIN stations st ON st.id = p.station_id
                WHERE NOT (
                    -- RFN : la liste officielle fait foi pour les gares SNCF, sauf terminus de la ligne
                    -- (gare de bifurcation que liste-des-gares ne cite que sous une autre ligne).
                    {$rfn} AND NOT {$terminus}
                )
                ORDER BY p.station_id, g.line_id, ST_Distance(p.geom::geography, g.geom::geography)
                ON CONFLICT (station_id, line_id) DO NOTHING
            SQL, ['buffer' => $buffer, 'buffer2' => $buffer]);
            $this->line("  {$method} : {$n}");
        }

        $this->projectMissingPk();
        $this->computeServiceYears();

        $r = DB::selectOne(<<<'SQL'
            SELECT COUNT(*) AS links, COUNT(DISTINCT station_id) AS stations, COUNT(DISTINCT line_id) AS lines,
                   COUNT(pk_m) AS with_pk, COUNT(first_year) AS with_first, COUNT(last_year) AS with_last
            FROM station_lines
        SQL);
        $total = DB::table('stations')->count();
        $this->table(['rattachements', 'gares rattachées', 'lignes', 'avec PK', 'début daté', 'fin datée'],
            [[(int) $r->links, "{$r->stations} / {$total}", (int) $r->lines, (int) $r->with_pk, (int) $r->with_first, (int) $r->with_last]]);

        return self::SUCCESS;
    }

    /**
     * PK des rattachements par proximité : projection sur le tronçon le plus
     * proche de la ligne, interpolée entre gares voisines de PK connu.
     */
    private function projectMissingPk(): void
    {
        DB::statement('DROP TABLE IF EXISTS tmp_line_tr');
        // Tronçons SNCF avec leurs PK officiels de début et de fin (pk_debut / pk_fin) : une gare sans
        // voisine de PK connu sur le même tronçon prend le PK interpolé dans le tronçon, pas son abscisse
        // depuis le début du tronçon (Angoulême affichait « PK 2+083 » sur Limoges - Angoulême, au lieu
        // d'environ 520 km, et passait en tête de la liste des gares).
        DB::statement('CREATE TEMP TABLE tmp_line_tr (tid serial PRIMARY KEY, line_id bigint, geom geometry(LineString, 4326), pk_debut int, pk_fin int)');
        DB::statement(<<<'SQL'
            INSERT INTO tmp_line_tr (line_id, geom, pk_debut, pk_fin)
            SELECT l.id, lg.geom, lg.pk_debut_m, lg.pk_fin_m FROM sncf_line_geometries lg JOIN lines l ON l.code = lg.code_ligne
            WHERE l.id IN (SELECT line_id FROM station_lines WHERE pk_m IS NULL)
        SQL);
        $fallbacks = array_filter([
            Schema::hasTable('line_reference_geometries') ? 'SELECT line_id, geom FROM line_reference_geometries' : null,
            'SELECT line_id, geom FROM source_traces WHERE line_id IS NOT NULL',
            'SELECT line_id, geom FROM line_segments WHERE line_id IS NOT NULL',
        ]);
        // Morceaux d'un tracé séparés de quelques mètres (tracés de deux sources bout à bout : 433000
        // Couterne - La Ferté-Macé, 50 m) : extrémités recollées au début du morceau suivant (≈ 100 m),
        // sinon une gare seule sur son morceau prenait son abscisse depuis le début du morceau
        // (La Ferté-Macé « PK 4,736 », entre Couterne et Bagnoles dans la liste des gares).
        foreach ($fallbacks as $sql) {
            DB::statement(<<<SQL
                INSERT INTO tmp_line_tr (line_id, geom)
                SELECT line_id, (ST_Dump(CASE WHEN ST_NumGeometries(g) > 1
                    THEN ST_LineMerge(ST_Snap(g, (SELECT ST_Collect(ST_StartPoint(d.geom)) FROM ST_Dump(g) d), 0.0015)) ELSE g END)).geom
                FROM (SELECT line_id, ST_LineMerge(ST_Collect(ST_Force2D(geom))) AS g
                      FROM ({$sql}) x
                      WHERE line_id IN (SELECT line_id FROM station_lines WHERE pk_m IS NULL)
                        AND line_id NOT IN (SELECT line_id FROM tmp_line_tr)
                      GROUP BY line_id) y
            SQL);
        }
        DB::statement("DELETE FROM tmp_line_tr WHERE GeometryType(geom) <> 'LINESTRING'");
        DB::statement('CREATE INDEX ON tmp_line_tr USING GIST (geom)');

        // Position de chaque gare rattachée sur le tronçon le plus proche de sa ligne.
        DB::statement('DROP TABLE IF EXISTS tmp_sl_loc');
        DB::statement(<<<'SQL'
            CREATE TEMP TABLE tmp_sl_loc AS
            SELECT sl.id, sl.line_id, sl.pk_m, tr.tid, ST_LineLocatePoint(tr.geom, s.geom) AS frac,
                   ST_Length(tr.geom::geography) AS len, tr.pk_debut, tr.pk_fin
            FROM station_lines sl
            JOIN stations s ON s.id = sl.station_id
            JOIN LATERAL (SELECT tid, geom, pk_debut, pk_fin FROM tmp_line_tr t WHERE t.line_id = sl.line_id
                          ORDER BY t.geom <-> s.geom LIMIT 1) tr ON TRUE
        SQL);
        $n = DB::affectingStatement(<<<'SQL'
            WITH t AS (
                SELECT a.id, a.tid, a.frac, a.len, a.pk_debut, a.pk_fin,
                       lo.pk_m AS lpk, lo.frac AS lfrac, hi.pk_m AS hpk, hi.frac AS hfrac
                FROM tmp_sl_loc a
                LEFT JOIN LATERAL (SELECT pk_m, frac FROM tmp_sl_loc b WHERE b.tid = a.tid AND b.pk_m IS NOT NULL AND b.frac <= a.frac
                                   ORDER BY b.frac DESC LIMIT 1) lo ON TRUE
                LEFT JOIN LATERAL (SELECT pk_m, frac FROM tmp_sl_loc b WHERE b.tid = a.tid AND b.pk_m IS NOT NULL AND b.frac >= a.frac
                                   ORDER BY b.frac ASC LIMIT 1) hi ON TRUE
                WHERE a.pk_m IS NULL
            )
            UPDATE station_lines sl SET
                pk_m = ROUND(CASE
                    WHEN t.lpk IS NOT NULL AND t.hpk IS NOT NULL AND t.hfrac > t.lfrac
                        THEN t.lpk + (t.frac - t.lfrac) * (t.hpk - t.lpk) / (t.hfrac - t.lfrac)
                    WHEN t.lpk IS NOT NULL AND t.hpk IS NOT NULL THEN t.lpk
                    WHEN t.lpk IS NOT NULL THEN t.lpk + (t.frac - t.lfrac) * t.len
                    WHEN t.hpk IS NOT NULL THEN t.hpk - (t.hfrac - t.frac) * t.len
                    WHEN t.pk_debut IS NOT NULL AND t.pk_fin IS NOT NULL THEN t.pk_debut + t.frac * (t.pk_fin - t.pk_debut)
                    ELSE t.frac * t.len END),
                pk_source = CASE WHEN t.lpk IS NOT NULL OR t.hpk IS NOT NULL OR (t.pk_debut IS NOT NULL AND t.pk_fin IS NOT NULL)
                    THEN 'projected' ELSE 'projected_raw' END
            FROM t WHERE t.id = sl.id
        SQL);
        $this->line("  PK projetés : {$n}");
    }

    /**
     * Période de desserte de la gare par chaque ligne : événements de ligne
     * dont la section couvre le PK (ou sans section = toute la ligne), sinon
     * tracés datés de la ligne à < 100 m.
     *
     * Fin de desserte (2026-10-20, contrôle qualité : « 434000 desservie de 1869 à 2006 » à
     * Lonlay-le-Tesson, fermée le 26/05/1991, sur une ligne fermée en 1992) : la DERNIÈRE fin du
     * service voyageurs ou fermeture totale couvrant la gare qu'aucune réouverture ne suit ; un
     * déclassement ou une dépose (décisions tardives, souvent des années après) ne sert qu'à défaut,
     * et alors le plus ancien. Les bornes par les dates de la gare elle-même (ouverture, fermeture
     * retenue) sont posées par vff:build-station-eras (BuildStationEras::reconcileServiceYears),
     * une fois ces dates connues : une seule source de vérité pour la fiche gare, la fiche ligne
     * et la carte (line_stops).
     *
     * @param  list<int>  $exceptLines  lignes laissées de côté (--years-only --except)
     */
    private function computeServiceYears(array $exceptLines = []): void
    {
        $cover = self::EVENT_COVERS_STATION;
        $except = $exceptLines === [] ? '' : ' AND sl.line_id NOT IN ('.implode(',', array_map('intval', $exceptLines)).')';
        DB::statement("UPDATE station_lines sl SET first_year = NULL, last_year = NULL, years_source = NULL WHERE TRUE{$except}");
        // Ouverture : plus ancienne ouverture couvrant le PK.
        DB::statement(<<<SQL
            WITH ev AS (
                SELECT sl.id, MIN(EXTRACT(YEAR FROM le.event_date))::int AS y
                FROM station_lines sl
                JOIN stations s ON s.id = sl.station_id
                JOIN line_events le ON le.line_id = sl.line_id
                 AND le.type IN ('opening', 'opening_passenger', 'opening_freight')
                 AND {$cover}
                WHERE TRUE{$except}
                GROUP BY sl.id
            )
            UPDATE station_lines sl SET first_year = ev.y, years_source = 'line_events' FROM ev WHERE ev.id = sl.id
        SQL);
        // Fin : dernière fin du service voyageurs / fermeture totale couvrant le PK sans réouverture
        // ensuite ; à défaut, le plus ancien déclassement ou la plus ancienne dépose. Une fin antérieure
        // à l'ouverture (déclassement d'un projet antérieur, 794000 « déclassée … le 07/07/1905 », ouverte
        // en 1910) ne termine pas la desserte.
        DB::statement(<<<SQL
            WITH ev AS (
                SELECT sl.id, le.event_date, le.type IN ('closure_passenger', 'closure') AS service_end
                FROM station_lines sl
                JOIN stations s ON s.id = sl.station_id
                JOIN line_events le ON le.line_id = sl.line_id
                 AND le.type IN ('closure_passenger', 'closure', 'declassification', 'dismantling')
                 AND {$cover}
                WHERE TRUE{$except}
                  AND (sl.first_year IS NULL OR EXTRACT(YEAR FROM le.event_date) >= sl.first_year)
                  AND NOT EXISTS (SELECT 1 FROM line_events r WHERE r.line_id = sl.line_id
                                  AND r.type IN ('reopening', 'reopening_passenger', 'opening_passenger') AND r.event_date > le.event_date)
            ), pick AS (
                SELECT id, COALESCE(MAX(event_date) FILTER (WHERE service_end), MIN(event_date) FILTER (WHERE NOT service_end)) AS d
                FROM ev GROUP BY id
            )
            UPDATE station_lines sl SET last_year = EXTRACT(YEAR FROM pick.d)::int,
                   years_source = COALESCE(sl.years_source, 'line_events')
            FROM pick WHERE pick.id = sl.id AND pick.d IS NOT NULL
        SQL);
        // Tracés datés rattachés à la ligne, à proximité de la gare.
        DB::statement(<<<SQL
            WITH tr AS (
                SELECT sl.id, MIN(st.opened_year) AS o,
                       CASE WHEN BOOL_AND(st.closed_year IS NOT NULL) THEN MAX(st.closed_year) END AS c
                FROM station_lines sl
                JOIN stations s ON s.id = sl.station_id
                JOIN source_traces st ON st.line_id = sl.line_id AND st.geom && ST_Expand(s.geom, 0.002)
                 AND ST_DWithin(st.geom::geography, s.geom::geography, 100) AND st.opened_year IS NOT NULL
                GROUP BY sl.id
            )
            UPDATE station_lines sl SET first_year = COALESCE(sl.first_year, tr.o), last_year = COALESCE(sl.last_year, tr.c),
                   years_source = COALESCE(sl.years_source, 'traces')
            FROM tr WHERE tr.id = sl.id{$except}
        SQL);
        // Ligne en service aujourd'hui (tronçons de l'ère courante) : pas de fin.
        DB::statement(<<<SQL
            UPDATE station_lines sl SET last_year = NULL
            WHERE last_year IS NOT NULL{$except} AND EXISTS (
                SELECT 1 FROM line_segments ls JOIN eras e ON e.id = ls.era_id AND e.is_current
                JOIN stations s ON s.id = sl.station_id
                WHERE ls.line_id = sl.line_id AND ls.status IN ('in_service', 'single_track')
                  AND ls.geom && ST_Expand(s.geom, 0.003) AND ST_DWithin(ls.geom::geography, s.geom::geography, 200))
        SQL);
    }
}
