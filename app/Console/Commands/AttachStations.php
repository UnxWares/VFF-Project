<?php

namespace App\Console\Commands;

use App\Services\StationLinkChanges;
use App\Services\StationTerminusOverrides;
use App\Support\StationNames;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

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
 *   4. terminus_name : gare de même nom qu'une extrémité d'une ligne sans
 *                  liste officielle, près d'un bout de son tracé
 *                  (attachNamedTermini ; seul, sans rien retirer :
 *                  --termini-only).
 *   5. terminus_reviewed : extrémités relues à la main
 *                  (database/data/station_terminus_overrides.php, StationTerminusOverrides) :
 *                  « attach » rattache la gare nommée par la source, « none » interdit le
 *                  rattachement par la règle 4 ; appliquées dans les trois modes (complet,
 *                  --termini-only, --years-only). Hors rattachement complet, une décision
 *                  retire aussi ce qu'elle contredit : le terminus nommé de son extrémité et
 *                  le terminus relu dont la gare n'est plus celle d'une décision « attach ».
 *
 * PK manquant : projection sur le tracé, interpolée entre les gares voisines
 * de PK connu de la même ligne (pk_source = projected), sinon abscisse
 * curviligne depuis le début du tronçon (pk_source = projected_raw).
 *
 * Période de desserte (first_year / last_year) : événements de la ligne
 * (`line_events`) dont la section couvre le PK de la gare, sinon tracés datés
 * rattachés à la ligne à proximité.
 *
 * Tout le calcul se fait dans une transaction : les lecteurs voient l'ancienne
 * table jusqu'à la fin. --dry-run l'annule et n'affiche que les changements
 * (rattachements ajoutés ou retirés, PK, années de desserte ajoutées, retirées,
 * avancées ou reculées) ; --report=<fichier.csv> en écrit le détail.
 *
 *   php artisan vff:attach-stations --years-only --dry-run --report=storage/app/reports/annees-desserte.csv
 */
class AttachStations extends Command
{
    /** Décisions relues à la main (étape 5), chargées par handle(). */
    private StationTerminusOverrides $overrides;

    protected $signature = 'vff:attach-stations {--buffer=60 : Distance max gare ↔ tracé (m) pour une position précise}
        {--years-only : Recalcule seulement les années de desserte (first_year / last_year) des rattachements existants, après les terminus relus à la main}
        {--except=* : Avec --years-only, codes de lignes à laisser de côté}
        {--dry-run : Calcule tout dans une transaction annulée : affiche les changements sans rien écrire}
        {--report= : Fichier CSV du détail des rattachements changés (chemin relatif à la racine du projet)}
        {--termini-only : Ajoute seulement les terminus nommés (étape 4) aux rattachements existants, puis recalcule les années}';

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

    /**
     * Événement de ligne d'alias $alias (au lieu de « le ») couvrant la gare : même règle
     * qu'EVENT_COVERS_STATION, pour une sous-requête (réouverture au PK de la gare).
     */
    public static function eventCovers(string $alias): string
    {
        return str_replace('le.', "{$alias}.", self::EVENT_COVERS_STATION);
    }

    public function handle(StationLinkChanges $changes, StationTerminusOverrides $overrides): int
    {
        $this->overrides = $overrides;
        $dryRun = (bool) $this->option('dry-run');
        DB::beginTransaction();
        try {
            $changes->snapshot();
            match (true) {
                (bool) $this->option('years-only') => $this->recomputeYears(),
                (bool) $this->option('termini-only') => $this->addNamedTermini(),
                default => $this->attach(),
            };
            $diff = $changes->compare();
            $dryRun ? DB::rollBack() : DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();

            throw $e;
        }

        $this->table(['changement', 'rattachements'], collect($changes->summary($diff))->map(fn ($n, $label) => [$label, $n])->values()->all());
        if ($report = $this->option('report')) {
            $path = $changes->writeCsv($diff, (string) $report);
            $this->line('  détail : '.(str_starts_with($path, base_path().'/') ? substr($path, strlen(base_path()) + 1) : $path));
        }
        if ($dryRun) {
            $this->warn('Passage à blanc (--dry-run) : rien n\'a été écrit.');
        }

        return self::SUCCESS;
    }

    /** --years-only : terminus relus à la main, puis années de desserte des rattachements existants, sauf lignes --except. */
    private function recomputeYears(): void
    {
        $added = $this->applyTerminusOverrides();
        if ($added !== []) {
            $this->projectMissingPk($added);
        }
        $except = DB::table('lines')->whereIn('code', (array) $this->option('except'))->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->computeServiceYears($except);
        $this->line('  années de desserte recalculées'.($except ? ' (lignes laissées de côté : '.implode(', ', (array) $this->option('except')).')' : ''));
    }

    /** --termini-only : terminus nommés en plus des rattachements existants, puis PK et années de desserte. */
    private function addNamedTermini(): void
    {
        $this->line('  terminus nommés : '.$this->attachNamedTermini());
        $this->applyTerminusOverrides();
        $this->projectMissingPk();
        $this->computeServiceYears();
    }

    /** Rattachement complet : station_lines vidée puis reconstruite (PK, période de desserte). */
    private function attach(): void
    {
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

        // 4. Terminus nommés des lignes sans liste officielle ; 5. terminus relus à la main.
        $this->line('  terminus nommés : '.$this->attachNamedTermini());
        $this->applyTerminusOverrides();

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
    }

    /**
     * Extrémités nommées d'une ligne, normalisées (StationNames) : « Ligne de Lorient à Gourin »
     * → [lorient, gourin] ; « Ligne du Mans à … » → le mans ; « … au Conquet » → le conquet. Précision
     * entre parenthèses et extrémité « frontière » écartées.
     *
     * @return list<string>
     */
    public static function terminiFromName(string $name): array
    {
        $s = trim((string) preg_replace('/\s*\([^)]*\)/u', '', $name));
        if (preg_match('/^(?:ligne|tramway|chemin de fer|voie ferrée|embranchement)\s+(de la |de l[\'’]|du |des |de |d[\'’])(.+)$/iu', $s, $m) !== 1) {
            return [];
        }
        $article = ['de la ' => 'La ', 'du ' => 'Le ', 'des ' => 'Les '][mb_strtolower($m[1])] ?? '';
        $parts = preg_split('/ (à la |à l[\'’]|au |aux |à )/u', $article.$m[2], 2, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        if (count($parts) === 3) {
            $parts = [$parts[0], (['au ' => 'Le ', 'aux ' => 'Les ', 'à la ' => 'La '][$parts[1]] ?? (str_starts_with($parts[1], 'à l') ? 'L\'' : '')).$parts[2]];
        }

        return array_values(array_filter(array_map(fn (string $p) => StationNames::normalize($p), $parts),
            fn (?string $t) => $t !== null && ! str_contains($t, 'frontiere')));
    }

    /**
     * Gare retenue pour une extrémité nommée parmi les gares proches du tracé : le même nom d'abord ;
     * sinon le nom prolongé (« montauban » → « montauban ville bourbon »), seulement si c'est la seule
     * gare de toute la base qui porte ce nom ($sameNameInBase : gares dont le nom est l'extrémité ou la
     * prolonge). Une ville à plusieurs gares (Lyon, Marseille, Mulhouse, Toulouse) ne désigne pas une
     * gare précise : rien. Une seule gare retenue, sinon aucune.
     *
     * @param  array<int, string>  $nearby  identifiant de gare → nom normalisé
     */
    public static function terminusStation(string $terminus, array $nearby, int $sameNameInBase): ?int
    {
        $exact = array_keys(array_filter($nearby, fn (string $n) => $n === $terminus));
        $longer = array_keys(array_filter($nearby, fn (string $n) => str_starts_with($n, $terminus.' ')));
        $found = $exact !== [] ? $exact : ($sameNameInBase === 1 ? $longer : []);

        return count($found) === 1 ? (int) $found[0] : null;
    }

    /** Provenance d'un terminus nommé (station_lines.source_ref) : « name: » suivi de l'extrémité normalisée. */
    public static function namedTerminusRef(string $terminus): string
    {
        return mb_substr('name:'.$terminus, 0, 160);
    }

    /**
     * 4. Terminus nommé (méthode terminus_name) : ligne sans liste officielle SNCF (réseau secondaire, tramway, ligne fermée avant les listes ; hors
     * lignes militaires, LGV et raccordements), gare dont le nom est celui d'une extrémité de la ligne, à
     * ≤ 500 m de l'un de ses tracés et d'un bout de ce tracé (Le Touquet-Aéroport, au milieu de « Ligne
     * d'Etaples au Touquet », n'en est pas le terminus). Le tracé d'une ligne secondaire s'arrête souvent
     * sur la place de la gare ou à la gare de sa compagnie, à 100-400 m : la proximité seule (≤ 60 / 150 m)
     * ne la rattachait pas, et 65 lignes secondaires de 10 km ou plus n'avaient aucune gare (Lorient -
     * Gourin, Caussade - Caylus…). Rien si la ligne a déjà une gare de ce nom, ou si plusieurs gares
     * conviennent (terminusStation : Nice-St-Roch et Nice-Pont-Michel pour Nice - Levens ; « Lyon », ville
     * à plusieurs gares).
     *
     * vff:build-station-eras n'exige pas la fermeture aux voyageurs de ces lignes pour dater celle de la
     * gare (sinon Gex, Mondoubleau, Droué… perdaient leur date sourcée : essai du 19/11/2026).
     */
    private function attachNamedTermini(): int
    {
        $lines = DB::table('lines as l')->where('l.network', '<>', 'militaire')->where('l.is_lgv', false)
            ->where('l.name', 'not ilike', '%(LGV)%')->where('l.name', 'not ilike', 'Raccordement%')
            ->whereNotExists(fn ($q) => $q->from('sncf_gares as sg')->whereColumn('sg.code_ligne', 'l.code'))
            ->pluck('l.name', 'l.id');
        $termini = $lines->map(fn (string $name) => self::terminiFromName($name))->filter();
        if ($termini->isEmpty()) {
            return 0;
        }
        $ids = '{'.$termini->keys()->implode(',').'}';
        $nearby = [];
        foreach (DB::select(<<<'SQL'
            WITH g AS (
                SELECT line_id, geom FROM line_segments WHERE line_id = ANY(:ids::bigint[])
                UNION ALL SELECT line_id, geom FROM source_traces WHERE line_id = ANY(:ids::bigint[])
                UNION ALL SELECT line_id, geom FROM line_reference_geometries WHERE line_id = ANY(:ids::bigint[])
            ),
            -- Bouts des tracés : morceaux de l'ère la plus longue de la ligne mis bout à bout, tracés des
            -- sources, tracé de référence ; une extrémité nommée est près d'un bout, pas au milieu de la ligne.
            eras AS (
                SELECT DISTINCT ON (line_id) line_id, era_id FROM (
                    SELECT line_id, era_id, SUM(length_meters) AS m FROM line_segments WHERE line_id = ANY(:ids::bigint[]) GROUP BY 1, 2) x
                ORDER BY line_id, m DESC, era_id
            ),
            pieces AS (
                SELECT ls.line_id, (ST_Dump(ST_LineMerge(ST_Collect(ST_Force2D(ls.geom))))).geom AS piece
                FROM line_segments ls JOIN eras e ON e.line_id = ls.line_id AND e.era_id = ls.era_id GROUP BY ls.line_id
                UNION ALL
                SELECT line_id, (ST_Dump(ST_LineMerge(ST_Force2D(geom)))).geom FROM source_traces WHERE line_id = ANY(:ids::bigint[])
                UNION ALL
                SELECT line_id, (ST_Dump(ST_LineMerge(ST_Force2D(geom)))).geom FROM line_reference_geometries WHERE line_id = ANY(:ids::bigint[])
            ),
            ends AS (
                SELECT line_id, ST_StartPoint(piece) AS pt FROM pieces WHERE GeometryType(piece) = 'LINESTRING'
                UNION ALL SELECT line_id, ST_EndPoint(piece) FROM pieces WHERE GeometryType(piece) = 'LINESTRING'
            )
            SELECT g.line_id, s.id, s.name, ROUND(MIN(ST_Distance(s.geom::geography, g.geom::geography))) AS d
            FROM g
            JOIN stations s ON s.geom && ST_Expand(g.geom, 0.007) AND ST_DWithin(s.geom::geography, g.geom::geography, 500)
            WHERE s.provenance->>'kind' IS NULL
              AND EXISTS (SELECT 1 FROM ends WHERE ends.line_id = g.line_id AND ST_DWithin(ends.pt::geography, s.geom::geography, 500))
            GROUP BY g.line_id, s.id, s.name
        SQL, ['ids' => $ids]) as $r) {
            $nearby[(int) $r->line_id][(int) $r->id] = ['name' => (string) StationNames::normalize($r->name), 'd' => (int) $r->d];
        }
        // Gares de toute la base par nom normalisé : un nom prolongé n'est retenu que s'il est unique.
        $allNames = DB::table('stations')->pluck('name')->map(fn (string $n) => (string) StationNames::normalize($n))->all();
        $sameName = fn (string $t) => count(array_filter($allNames, fn (string $n) => $n === $t || str_starts_with($n, $t.' ')));
        $attached = [];
        foreach (DB::table('station_lines as sl')->join('stations as s', 's.id', '=', 'sl.station_id')
            ->whereIn('sl.line_id', $termini->keys())->get(['sl.line_id', 's.name']) as $r) {
            $attached[(int) $r->line_id][] = (string) StationNames::normalize($r->name);
        }
        $reviewed = $this->reviewedTermini();
        $rows = [];
        foreach ($termini as $lineId => $lineTermini) {
            $near = array_map(fn (array $x) => $x['name'], $nearby[$lineId] ?? []);
            foreach (array_diff($lineTermini, $reviewed[$lineId] ?? []) as $terminus) {
                $already = array_filter($attached[$lineId] ?? [], fn (string $n) => $n === $terminus || str_starts_with($n, $terminus.' '));
                $sid = $already === [] ? self::terminusStation($terminus, $near, $sameName($terminus)) : null;
                if ($sid !== null) {
                    $rows[] = ['station_id' => $sid, 'line_id' => $lineId, 'method' => 'terminus_name', 'distance_m' => $nearby[$lineId][$sid]['d'],
                        'source_ref' => self::namedTerminusRef($terminus), 'created_at' => now(), 'updated_at' => now()];
                }
            }
        }

        return DB::table('station_lines')->insertOrIgnore($rows);
    }

    /**
     * Extrémités relues à la main par ligne (identifiant de ligne → extrémités normalisées) : la
     * règle 4 ne les traite pas, la décision relue l'emporte.
     *
     * @return array<int, list<string>>
     */
    private function reviewedTermini(): array
    {
        $ids = DB::table('lines')->whereIn('slug', array_column($this->overrides->all(), 'line'))->pluck('id', 'slug');
        $out = [];
        foreach ($this->overrides->all() as $e) {
            if (isset($ids[$e['line']]) && is_string($e['terminus'] ?? null)) {
                $out[(int) $ids[$e['line']]][] = StationTerminusOverrides::key($e['terminus']);
            }
        }

        return $out;
    }

    /**
     * 5. Terminus relus à la main (StationTerminusOverrides). Une décision valide l'emporte d'abord sur
     * les rattachements des règles des terminus déjà en base, qu'un rattachement complet ne referait pas
     * (retractTerminusLinks : --years-only, --termini-only). Puis chaque décision « attach » rattache la
     * gare nommée (méthode terminus_reviewed, distance au plus proche tracé de la ligne, provenance
     * « relu:<permalien> »), sans réécrire un rattachement d'une autre méthode. Une décision invalide ou
     * dont la gare est introuvable est signalée et ignorée (rien n'est retiré pour elle) ; celle d'une
     * ligne absente de la base est comptée.
     *
     * @return list<int> identifiants des rattachements ajoutés
     */
    private function applyTerminusOverrides(): array
    {
        $entries = $this->overrides->all();
        if ($entries === []) {
            return [];
        }
        $lines = DB::table('lines')->whereIn('slug', array_column($entries, 'line'))->get(['id', 'slug', 'name'])->keyBy('slug');
        $stations = DB::table('stations')->whereIn('slug', array_filter(array_column($entries, 'station')))->pluck('id', 'slug');
        /** @var list<array{line: int, terminus: string, station: ?int, entry: array<string, mixed>}> $valid */
        $valid = [];
        $absent = 0;
        foreach ($entries as $i => $e) {
            // Ligne absente de cette base (base de test, ligne fusionnée) : rien à faire.
            $line = $lines[$e['line'] ?? ''] ?? null;
            if ($line === null) {
                $absent++;

                continue;
            }
            $errors = StationTerminusOverrides::validate($e, $line->name);
            if (($e['decision'] ?? null) === 'attach' && ! isset($stations[$e['station'] ?? ''])) {
                $errors[] = 'gare introuvable';
            }
            if ($errors !== []) {
                $this->warn("  terminus relu n° {$i} ignoré (".($e['line'] ?? '?').') : '.implode(' ; ', $errors));

                continue;
            }
            $valid[] = ['line' => (int) $line->id, 'terminus' => StationTerminusOverrides::key($e['terminus']),
                'station' => $e['decision'] === 'attach' ? (int) $stations[$e['station']] : null, 'entry' => $e];
        }
        $removed = $this->retractTerminusLinks($valid);
        $added = [];
        $none = 0;
        foreach ($valid as ['line' => $lineId, 'station' => $stationId, 'entry' => $e]) {
            if ($stationId === null) {
                $none++;

                continue;
            }
            $distance = DB::selectOne(<<<'SQL'
                SELECT ROUND(MIN(ST_Distance(s.geom::geography, g.geom::geography)))::int AS d
                FROM stations s, (
                    SELECT geom FROM line_segments WHERE line_id = :l1
                    UNION ALL SELECT geom FROM source_traces WHERE line_id = :l2
                    UNION ALL SELECT geom FROM line_reference_geometries WHERE line_id = :l3
                ) g
                WHERE s.id = :s
            SQL, ['l1' => $lineId, 'l2' => $lineId, 'l3' => $lineId, 's' => $stationId])?->d;
            // Gare déjà rattachée à la ligne (autre méthode) : rien n'est réécrit.
            $inserted = DB::table('station_lines')->insertOrIgnore([
                'station_id' => $stationId, 'line_id' => $lineId, 'method' => StationTerminusOverrides::METHOD,
                'distance_m' => $distance, 'source_ref' => StationTerminusOverrides::sourceRef($e), 'created_at' => now(), 'updated_at' => now(),
            ]);
            if ($inserted === 1) {
                $added[] = (int) DB::table('station_lines')->where('station_id', $stationId)->where('line_id', $lineId)->value('id');
            }
        }
        $this->line('  terminus relus : '.count($added).' ajoutés'.($removed > 0 ? ", {$removed} retirés" : '').', '.$none.' sans gare'
            .($absent > 0 ? ", {$absent} lignes absentes de la base" : ''));

        return $added;
    }

    /**
     * Rattachements des règles des terminus que contredit une décision relue valide, retirés (nombre rendu) :
     *   - le terminus nommé (méthode terminus_name, provenance « name:<extrémité> ») de l'extrémité décidée,
     *     que la règle 4 ne traite plus (Sainte-Foy-l'Argentière sur la ligne inachevée de Pontcharra) ;
     *   - le terminus relu (terminus_reviewed) d'une ligne décidée dont la gare n'est celle d'aucune de ses
     *     décisions « attach » : décision passée à « none » ou changée de gare après une nouvelle lecture
     *     (Lyon-Est sur le tramway OTL de Lyon à La Balme, 19/11/2026).
     * Seules les lignes qui ont une décision valide sont touchées ; les autres méthodes jamais.
     *
     * @param  list<array{line: int, terminus: string, station: ?int, entry: array<string, mixed>}>  $decisions
     */
    private function retractTerminusLinks(array $decisions): int
    {
        $byLine = [];
        foreach ($decisions as $d) {
            $byLine[$d['line']]['refs'][] = self::namedTerminusRef($d['terminus']);
            $byLine[$d['line']]['stations'] ??= [];
            if ($d['station'] !== null) {
                $byLine[$d['line']]['stations'][] = $d['station'];
            }
        }
        $removed = 0;
        foreach ($byLine as $lineId => $d) {
            $removed += DB::table('station_lines')->where('line_id', $lineId)
                ->where(fn ($q) => $q->where(fn ($q) => $q->where('method', 'terminus_name')->whereIn('source_ref', $d['refs']))
                    ->orWhere(fn ($q) => $q->where('method', StationTerminusOverrides::METHOD)->whereNotIn('station_id', $d['stations'])))
                ->delete();
        }

        return $removed;
    }

    /**
     * PK des rattachements par proximité : projection sur le tronçon le plus
     * proche de la ligne, interpolée entre gares voisines de PK connu.
     *
     * @param  list<int>|null  $only  rattachements à traiter (--years-only : terminus relus ajoutés), sinon tous ceux sans PK
     */
    private function projectMissingPk(?array $only = null): void
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
        $onlySql = $only === null ? '' : ' AND sl.id IN ('.implode(',', array_map('intval', $only ?: [0])).')';
        $n = DB::affectingStatement(<<<SQL
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
            FROM t WHERE t.id = sl.id{$onlySql}
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
        // en 1910) ne termine pas la desserte. La réouverture doit couvrir la gare, comme la fermeture :
        // celle d'une autre portion de la ligne ne prolonge pas la desserte d'une gare restée fermée.
        $reopenCover = self::eventCovers('r');
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
                                  AND r.type IN ('reopening', 'reopening_passenger', 'opening_passenger') AND r.event_date > le.event_date
                                  AND {$reopenCover})
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
