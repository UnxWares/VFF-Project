<?php

namespace App\Console\Commands;

use App\Services\PlaceNames;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Audit de couverture du réseau : aucune ligne oubliée, chaque ligne tracée,
 * nommée et catégorisée.
 *
 * Pourquoi : la carte par décennie n'est juste que si chaque ligne ayant
 * existé a un tracé et une catégorie. Cet audit croise toutes les sources de
 * `source_traces` (sncf, archeoferroviaire, ohm, osm, bdtopo…) et la liste
 * Wikidata des lignes absentes de VFF, et produit des listes de travail :
 *
 *   1. lignes sans aucun tracé (ou seulement le tracé SNCF généralisé) ;
 *   2. tracés non rattachés (archeoferroviaire : projets, lignes exploitées
 *      hors RFN… ; autres sources : comptes) ;
 *   3. tracés OHM / OSM qu'AUCUN tracé rattaché à une ligne VFF ne couvre
 *      (points tous les 100 m, tampon 30 m), regroupés par contiguïté :
 *      lignes potentiellement oubliées, avec une proposition (rattacher à la
 *      ligne touchée, ou créer « Ligne de A à B » + catégorie présumée) ;
 *      voies de service (faisceaux, embranchements particuliers, aiguilles)
 *      écartées ;
 *   4. lignes au nom encore générique (« près de », « secteur », numéroté) ;
 *   5. incohérences de catégorie (tracé archeo « particulier » sur une ligne
 *      RFN, ligne créée longeant le RFN, code RFN sur une ligne non
 *      principale…) ;
 *   6. lignes Wikidata absentes de VFF (storage/app/reports/wikidata-lignes-
 *      absentes.csv) : couvertes ou non par un tracé (nom, terminus, points),
 *      et liste à part de celles qu'aucun tracé ne couvre (à faire tracer) ;
 *   7. noms en double.
 *
 * Sorties : console + CSV dans storage/app/reports/audit-coverage/.
 *
 *   php artisan vff:audit-coverage
 *   php artisan vff:audit-coverage --skip-uncovered   # sans l'étape 3 (la plus longue)
 */
class AuditCoverage extends Command
{
    protected $signature = 'vff:audit-coverage
        {--skip-uncovered : ne pas chercher les tracés OHM/OSM non couverts}
        {--min-length=1000 : longueur minimale (m) d\'un ensemble OHM/OSM non couvert à signaler}';

    protected $description = 'Audit de couverture : lignes sans tracé, tracés orphelins, OHM/OSM non couverts, noms génériques, catégories, Wikidata.';

    private string $dir;

    /** @var array<string, string> */
    private array $outputs = [];

    public function handle(): int
    {
        DB::connection()->disableQueryLog();
        $this->dir = storage_path('app/reports/audit-coverage');
        @mkdir($this->dir, 0775, true);

        $this->summary();
        $this->linesWithoutTraces();
        $this->unlinkedTraces();
        if (! $this->option('skip-uncovered')) {
            $this->uncoveredOpenData();
        }
        $this->genericNames();
        $this->categoryConflicts();
        $this->wikidataAbsent();
        $this->duplicateNames();

        $this->newLine();
        $this->info('Fichiers produits :');
        foreach ($this->outputs as $what => $path) {
            $this->line("  {$what} : {$path}");
        }

        return self::SUCCESS;
    }

    // ───────────────────────────── 0. synthèse ─────────────────────────────

    private function summary(): void
    {
        $this->info('→ Synthèse par catégorie');
        $rows = DB::select("WITH t AS (
                SELECT line_id, source, SUM(length_m) AS m FROM source_traces WHERE line_id IS NOT NULL GROUP BY line_id, source)
            SELECT COALESCE(l.network, '(aucune)') AS network, COUNT(*) AS lines,
                   COUNT(*) FILTER (WHERE EXISTS (SELECT 1 FROM t WHERE t.line_id = l.id AND t.source NOT IN ('sncf', 'bdtopo'))) AS precise,
                   COUNT(*) FILTER (WHERE NOT EXISTS (SELECT 1 FROM t WHERE t.line_id = l.id)) AS none,
                   COUNT(*) FILTER (WHERE l.code IS NOT NULL) AS with_code,
                   ROUND(SUM((SELECT SUM(m) FROM t WHERE t.line_id = l.id AND t.source = 'archeoferroviaire')) / 1000.0) AS km_archeo,
                   ROUND(SUM((SELECT SUM(m) FROM t WHERE t.line_id = l.id AND t.source = 'sncf')) / 1000.0) AS km_sncf
            FROM lines l GROUP BY 1 ORDER BY 2 DESC");
        $this->table(['Catégorie', 'Lignes', 'avec tracé précis', 'sans aucun tracé', 'avec code RFN', 'km archeo', 'km SNCF'],
            array_map(fn ($r) => (array) $r, $rows));

        $methods = DB::select("SELECT source, COALESCE(match_method, 'non rattaché') AS m, COUNT(*) AS n, ROUND(SUM(length_m) / 1000.0) AS km
            FROM source_traces GROUP BY 1, 2 ORDER BY 1, 3 DESC");
        $this->table(['Source', 'Rattachement', 'Tracés', 'km'], array_map(fn ($r) => (array) $r, $methods));
    }

    // ───────────────────────────── 1. lignes sans tracé ─────────────────────────────

    private function linesWithoutTraces(): void
    {
        $rows = DB::select("SELECT l.id, l.slug, l.name, l.network, l.code, l.alt_names->>'name_source' AS name_source,
                   CASE WHEN NOT EXISTS (SELECT 1 FROM source_traces t WHERE t.line_id = l.id) THEN 'aucun tracé'
                        ELSE 'tracé SNCF généralisé seulement' END AS etat
            FROM lines l
            WHERE NOT EXISTS (SELECT 1 FROM source_traces t WHERE t.line_id = l.id AND t.source NOT IN ('sncf', 'bdtopo'))
            ORDER BY etat, l.network, l.name");
        $none = count(array_filter($rows, fn ($r) => $r->etat === 'aucun tracé'));
        $this->info('→ 1. Lignes sans tracé précis : '.count($rows)." (dont {$none} sans AUCUN tracé)");
        $this->writeCsv('lignes-sans-trace', 'Lignes sans tracé précis', ['id', 'slug', 'nom', 'catégorie', 'code', 'source du nom', 'état'], $rows);
    }

    // ───────────────────────────── 2. tracés non rattachés ─────────────────────────────

    private function unlinkedTraces(): void
    {
        $counts = DB::select('SELECT source, layer, COUNT(*) AS n, ROUND(SUM(length_m) / 1000.0) AS km
            FROM source_traces WHERE line_id IS NULL GROUP BY 1, 2 ORDER BY 1, 3 DESC');
        $this->info('→ 2. Tracés non rattachés');
        $this->table(['Source', 'Calque', 'Tracés', 'km'], array_map(fn ($r) => (array) $r, $counts));

        $rows = [];
        foreach (DB::select("SELECT t.id, t.layer, t.name, t.length_m,
                (SELECT c.nom FROM communes c WHERE c.type = 'commune-actuelle' ORDER BY c.geom <-> ST_PointOnSurface(t.geom) LIMIT 1) AS commune
            FROM source_traces t WHERE t.source = 'archeoferroviaire' AND t.line_id IS NULL ORDER BY t.layer, t.length_m DESC") as $r) {
            $p = PlaceNames::parseLabel($r->name);
            $why = match (true) {
                in_array('projet', $p['flags'], true) => 'projet jamais réalisé (volontairement non créé)',
                $r->layer === 'exploitee' => 'ligne exploitée hors RFN ou à l\'étranger (calque de contexte, pas de création)',
                default => 'à examiner',
            };
            $rows[] = [$r->id, $r->layer, $r->name, $r->length_m, $r->commune, $why];
        }
        $this->writeCsv('traces-archeo-non-rattaches', 'Tracés archeoferroviaire non rattachés',
            ['id', 'calque', 'libellé', 'longueur_m', 'commune proche', 'raison'], $rows);
    }

    // ───────────────────────────── 3. OHM / OSM non couverts ─────────────────────────────

    private function uncoveredOpenData(): void
    {
        $sources = array_column(DB::select("SELECT DISTINCT source FROM source_traces WHERE source IN ('ohm', 'osm')"), 'source');
        if (! $sources) {
            $this->warn('→ 3. Aucun tracé OHM / OSM en base : étape ignorée.');

            return;
        }
        $this->info('→ 3. Tracés '.implode(' / ', $sources).' non couverts par une ligne VFF…');
        $list = "'".implode("','", $sources)."'";
        DB::statement('DROP TABLE IF EXISTS au_ref, au_any, au_pts, au_unc');
        // Référence : tout tracé RATTACHÉ à une ligne VFF (archeo, SNCF, BD TOPO, OHM/OSM déjà rattachés).
        DB::statement('CREATE TEMP TABLE au_ref AS SELECT id, source, line_id, ST_Transform(geom, 2154) AS g FROM source_traces WHERE line_id IS NOT NULL');
        DB::statement('CREATE INDEX ON au_ref USING gist (g)');
        // Corroboration : tracés des autres sources, rattachés ou non.
        DB::statement("CREATE TEMP TABLE au_any AS SELECT id, source, ST_Transform(geom, 2154) AS g FROM source_traces WHERE source IN ({$list}, 'archeoferroviaire')");
        DB::statement('CREATE INDEX ON au_any USING gist (g)');
        // Points tous les 100 m le long des tracés à auditer (hors voies de service).
        DB::statement("CREATE TEMP TABLE au_pts AS
            SELECT d.id, d.source, (ST_Dump(ST_LineInterpolatePoints(d.g, LEAST(1.0, 100.0 / GREATEST(ST_Length(d.g), 1)), true))).geom AS p
            FROM (SELECT id, source, (ST_Dump(ST_Transform(geom, 2154))).geom AS g FROM source_traces
                  WHERE source IN ({$list}) AND line_id IS NULL
                    AND COALESCE(properties->>'service', '') NOT IN ('yard', 'siding', 'spur', 'crossover', 'parking_aisle', 'driveway')
                    AND layer NOT IN ('subway', 'proposed', 'construction')) d");
        $excluded = DB::selectOne("SELECT COUNT(*) AS n FROM source_traces WHERE source IN ({$list}) AND line_id IS NULL
            AND COALESCE(properties->>'service', '') IN ('yard', 'siding', 'spur', 'crossover', 'parking_aisle', 'driveway')")->n;
        DB::statement('CREATE INDEX ON au_pts (id)');
        DB::statement('CREATE TEMP TABLE au_unc AS
            SELECT p.id, p.source,
                   AVG((EXISTS (SELECT 1 FROM au_ref r WHERE r.source <> p.source AND ST_DWithin(r.g, p.p, 30)))::int) AS covered,
                   AVG((EXISTS (SELECT 1 FROM au_any a WHERE a.source <> p.source AND ST_DWithin(a.g, p.p, 30)))::int) AS corroborated
            FROM au_pts p GROUP BY p.id, p.source');
        DB::statement('DELETE FROM au_unc WHERE covered >= 0.2');

        $minLen = (int) $this->option('min-length');
        $clusters = DB::select("WITH u AS (
                SELECT u.id, u.source, u.corroborated, t.name, t.layer, t.properties, t.length_m, ST_Transform(t.geom, 2154) AS g
                FROM au_unc u JOIN source_traces t ON t.id = u.id),
            c AS (SELECT u.*, ST_ClusterDBSCAN(g, 50, 1) OVER (PARTITION BY source) AS cid FROM u)
            SELECT source, cid, COUNT(*) AS n, SUM(length_m) AS len, ROUND(AVG(corroborated)::numeric, 2) AS corr,
                   STRING_AGG(DISTINCT name, ' | ') AS names, STRING_AGG(DISTINCT layer, ',') AS layers,
                   STRING_AGG(DISTINCT properties->>'usage', ',') AS usages,
                   MIN(properties->>'start_date') AS start_date, MAX(properties->>'end_date') AS end_date,
                   STRING_AGG(DISTINCT properties->>'gauge', ',') AS gauges,
                   ST_AsEWKT(ST_Collect(g)) AS geom
            FROM c GROUP BY source, cid HAVING SUM(length_m) >= ?
            ORDER BY SUM(length_m) DESC", [$minLen]);

        // Contours absents ou pas encore importés (table vide) : part en France inconnue, et non nulle
        // (sinon chaque ensemble serait marqué « hors France »).
        $hasContours = DB::getSchemaBuilder()->hasTable('commune_contours') && DB::table('commune_contours')->exists();
        $rows = [];
        foreach ($clusters as $c) {
            // Ligne VFF touchée (≤ 50 m) : proposition de rattachement.
            $touch = DB::selectOne('SELECT l.id, l.name, l.network FROM au_ref r JOIN lines l ON l.id = r.line_id
                WHERE ST_DWithin(r.g, ST_GeomFromEWKT(?), 50) GROUP BY l.id, l.name, l.network
                ORDER BY COUNT(*) DESC LIMIT 1', [$c->geom]);
            $ends = DB::selectOne('WITH s AS (SELECT ST_LineMerge(ST_GeomFromEWKT(?)) AS g),
                    b AS (SELECT (ST_Dump(ST_Boundary(s.g))).geom AS p FROM s),
                    pr AS (SELECT b1.p AS p1, b2.p AS p2 FROM b b1, b b2 ORDER BY ST_Distance(b1.p, b2.p) DESC LIMIT 1)
                SELECT (SELECT nom FROM communes c ORDER BY c.geom <-> ST_Transform(pr.p1, 4326) LIMIT 1) AS a,
                       (SELECT nom FROM communes c ORDER BY c.geom <-> ST_Transform(pr.p2, 4326) LIMIT 1) AS b
                FROM pr', [$c->geom]);
            // Part en France : centre et extrémités dans un contour de commune (lignes allemandes, belges… à écarter).
            $fr = $hasContours ? DB::selectOne('WITH g AS (SELECT ST_Transform(ST_GeomFromEWKT(?), 4326) AS g),
                    p AS (SELECT ST_PointOnSurface(g) AS p FROM g UNION ALL SELECT (ST_Dump(ST_Boundary(ST_LineMerge(g)))).geom FROM g)
                SELECT ROUND(AVG((EXISTS (SELECT 1 FROM commune_contours c WHERE ST_Intersects(c.geom, p.p)))::int), 2) AS share FROM p', [$c->geom])->share : null;
            $network = $this->guessNetwork($c->layers, $c->usages, $c->gauges);
            $name = $this->proposedName($c->names, $ends, $network);
            $proposal = $touch
                ? "rattacher à « {$touch->name} » (#{$touch->id}, {$touch->network}) ou créer « {$name} »"
                : "créer « {$name} » ({$network})";
            if ($fr !== null && $fr < 0.5) {
                $proposal = 'hors France (contexte) : '.$proposal;
            }
            $rows[] = [$c->source, $c->n, round($c->len / 1000, 2), $c->corr, mb_substr((string) $c->names, 0, 200), $c->layers,
                $c->usages, $c->gauges, $c->start_date, $c->end_date, $ends->a ?? '', $ends->b ?? '', $network, $proposal, $fr];
        }
        $total = DB::selectOne('SELECT COUNT(*) AS n FROM au_unc')->n;
        $this->line("  {$total} tracés non couverts (< 20 % de leur longueur près d'une ligne VFF) ; "
            .count($rows)." ensembles de ≥ {$minLen} m à examiner ; {$excluded} voies de service écartées.");
        $byNet = [];
        foreach ($rows as $r) {
            $k = $r[0].' / '.$r[12].(($r[14] ?? 1) < 0.5 ? ' (hors France)' : '');
            $byNet[$k] = ($byNet[$k] ?? 0) + $r[2];
        }
        arsort($byNet);
        $this->table(['Source / catégorie présumée', 'km non couverts'], array_map(fn ($k, $v) => [$k, round($v)], array_keys($byNet), $byNet));
        $this->writeCsv('ohm-osm-non-couverts', 'Tracés OHM/OSM non couverts (lignes potentiellement oubliées)',
            ['source', 'tracés', 'km', 'corroboré par une autre source (part)', 'noms', 'calques', 'usage', 'écartement', 'début', 'fin',
                'extrémité A', 'extrémité B', 'catégorie présumée', 'proposition', 'part en France'], $rows);
    }

    private function guessNetwork(?string $layers, ?string $usages, ?string $gauges): string
    {
        $layers = (string) $layers;
        $usages = (string) $usages;

        return match (true) {
            str_contains($usages, 'military') => 'militaire',
            str_contains($usages, 'industrial') || str_contains($layers, 'funicular') => 'particulier',
            preg_match('/tram|light_rail/', $layers) === 1 => 'secondaire (tramway)',
            str_contains($layers, 'narrow_gauge') || preg_match('/\b(1000|600|700|750)\b/', (string) $gauges) === 1 => 'secondaire',
            str_contains($usages, 'main') || str_contains($usages, 'branch') => 'principal',
            default => 'à qualifier',
        };
    }

    private function proposedName(?string $names, ?object $ends, string $network): string
    {
        foreach (explode(' | ', (string) $names) as $n) {
            if (preg_match('/^(Ligne|Chemin de fer|Tramway|Voie|Raccordement|Embranchement)\b/iu', trim($n))) {
                return PlaceNames::ucfirst(trim($n));
            }
        }
        $prefix = match ($network) {
            'militaire' => 'Ligne militaire', 'particulier' => 'Voie industrielle', 'secondaire (tramway)' => 'Tramway', default => 'Ligne',
        };
        if ($ends && $ends->a && $ends->b && $ends->a !== $ends->b) {
            return PlaceNames::lineName($prefix, [$ends->a, $ends->b]);
        }

        return $prefix.' près '.PlaceNames::de($ends->a ?? 'France');
    }

    // ───────────────────────────── 4. noms génériques ─────────────────────────────

    private function genericNames(): void
    {
        $rows = DB::select("SELECT id, slug, name, network, code, alt_names->>'name_source' AS name_source
            FROM lines
            WHERE name ~* '(près d|\\(secteur|\\(\\d+\\)\$|^ligne [0-9 ]+\$|^[0-9 ]+\$)'
               OR alt_names->>'name_source' IN ('derived', 'derived_nearest')
            ORDER BY network, name");
        $this->info('→ 4. Lignes au nom encore générique ou dérivé : '.count($rows));
        $this->writeCsv('noms-generiques', 'Lignes au nom générique ou dérivé', ['id', 'slug', 'nom', 'catégorie', 'code', 'source du nom'], $rows);
    }

    // ───────────────────────────── 5. catégories ─────────────────────────────

    private function categoryConflicts(): void
    {
        $rows = [];
        // a. Tracés archeo d'un autre calque que la catégorie de leur ligne.
        foreach (DB::select("SELECT l.id, l.name, l.network, t.layer, COUNT(*) AS n, ROUND(SUM(t.length_m) / 1000.0, 1) AS km,
                    STRING_AGG(DISTINCT t.name, ' | ') AS labels, STRING_AGG(DISTINCT t.match_method, ',') AS methods
                FROM source_traces t JOIN lines l ON l.id = t.line_id
                WHERE t.source = 'archeoferroviaire' AND t.layer <> 'exploitee'
                  AND t.layer <> COALESCE(l.network, '')
                GROUP BY l.id, l.name, l.network, t.layer ORDER BY km DESC") as $r) {
            $rows[] = ['tracé archeo « '.$r->layer.' » sur une ligne « '.$r->network.' »', $r->id, $r->name, $r->network,
                $r->layer, $r->n, $r->km, mb_substr((string) $r->labels, 0, 150), $r->methods];
        }
        // b. Lignes créées hors « principal » dont les tracés longent le RFN sur ≥ 50 %.
        foreach (DB::select("SELECT l.id, l.name, l.network, rl.name AS rfn, ROUND(SUM(stl.overlap_m) / 1000.0, 1) AS km,
                    ROUND((SUM(stl.overlap_m)::numeric / NULLIF(SUM(t.length_m), 0)), 2) AS share
                FROM lines l JOIN source_traces t ON t.line_id = l.id
                JOIN source_trace_lines stl ON stl.source_trace_id = t.id AND stl.role = 'secondary'
                JOIN lines rl ON rl.id = stl.line_id
                WHERE l.alt_names->>'created_by' = 'vff:link-traces' AND l.network <> 'principal'
                GROUP BY l.id, l.name, l.network, rl.name
                HAVING SUM(stl.overlap_m)::numeric / NULLIF(SUM(t.length_m), 0) >= 0.5 ORDER BY km DESC") as $r) {
            $rows[] = ['ligne « '.$r->network.' » créée qui longe le RFN', $r->id, $r->name, $r->network, 'principal', '', $r->km,
                "longe « {$r->rfn} » sur {$r->share}", 'sncf_overlap'];
        }
        // c. Code RFN porté par une ligne non principale (intérêt général à voie métrique, ou catégorie à revoir).
        foreach (DB::select("SELECT id, name, network, code FROM lines WHERE code IS NOT NULL AND network <> 'principal' ORDER BY name") as $r) {
            $rows[] = ['code RFN sur une ligne « '.$r->network.' »', $r->id, $r->name, $r->network, 'principal', '', '', 'code '.$r->code, ''];
        }
        $this->info('→ 5. Incohérences de catégorie : '.count($rows));
        $this->writeCsv('incoherences-categorie', 'Incohérences de catégorie',
            ['cas', 'ligne_id', 'ligne', 'catégorie ligne', 'catégorie source', 'tracés', 'km', 'détail', 'méthodes'], $rows);
    }

    // ───────────────────────────── 6. Wikidata ─────────────────────────────

    private function wikidataAbsent(): void
    {
        $file = storage_path('app/reports/wikidata-lignes-absentes.csv');
        if (! is_file($file)) {
            $this->warn('→ 6. '.$file.' absent : étape Wikidata ignorée.');

            return;
        }
        $fh = fopen($file, 'r');
        $head = fgetcsv($fh);
        $items = [];
        while (($r = fgetcsv($fh)) !== false) {
            $items[] = array_combine($head, array_pad($r, count($head), ''));
        }
        fclose($fh);

        // Index des lignes VFF par toponymes de leur nom.
        $lines = [];
        $index = [];
        foreach (DB::select("SELECT id, name, slug, network, code, alt_names->>'created_by' AS created_by FROM lines") as $l) {
            $parsed = PlaceNames::parseLineName($l->name);
            if (! $parsed || count($parsed['places']) < 2) {
                continue;
            }
            $places = array_map(fn ($p) => PlaceNames::significant($p), $parsed['places']);
            if (in_array([], array_slice($places, 0, 2), true)) {
                continue;
            }
            $lines[$l->id] = ['row' => $l, 'places' => $places, 'all' => array_merge(...$places)];
            foreach (array_merge(...$places) as $w) {
                $index[$w][$l->id] = true;
            }
        }
        $points = [];
        if (DB::getSchemaBuilder()->hasTable('wikidata_lines')) {
            foreach (DB::select('SELECT qid, ST_AsEWKT(points) AS pts FROM wikidata_lines WHERE points IS NOT NULL') as $r) {
                $points[$r->qid] = $r->pts;
            }
        }

        DB::statement('DROP TABLE IF EXISTS au_wt');
        DB::statement("CREATE TEMP TABLE au_wt AS SELECT source, line_id, ST_Transform(geom, 2154) AS g FROM source_traces
            WHERE source IN ('archeoferroviaire', 'ohm', 'osm')");
        DB::statement('CREATE INDEX ON au_wt USING gist (g)');
        DB::statement('ANALYZE au_wt');

        $out = [];
        $none = [];
        $stats = [];
        foreach ($items as $it) {
            $qid = $it['qid'];
            $label = trim($it['libelle']);
            $res = ['statut' => '', 'ligne' => null, 'methode' => '', 'sources' => '', 'note' => ''];

            // a. Nom : mêmes extrémités qu'une ligne VFF.
            $nameHit = null;
            $wAll = [];
            $parsed = $label !== '' ? PlaceNames::parseLineName(PlaceNames::ucfirst($label)) : null;
            if ($parsed && count($parsed['places']) >= 2) {
                $wp = array_map(fn ($p) => PlaceNames::significant($p), $parsed['places']);
                $wAll = array_merge(...$wp);
                $cands = [];
                foreach ($wAll as $w) {
                    $cands += $index[$w] ?? [];
                }
                foreach (array_keys($cands) as $lid) {
                    $l = $lines[$lid];
                    if (array_intersect($wp[0], $l['all']) && array_intersect($wp[1], $l['all'])
                        && array_intersect($l['places'][0], $wAll) && array_intersect($l['places'][1], $wAll)) {
                        $nameHit = $l['row'];
                        break;
                    }
                }
            }

            // b. Points (terminus, gares) Wikidata : tracés à ≤ 1 km.
            $pts = $points[$qid] ?? null;
            if (! $pts && preg_match_all('/(-?\d+\.\d+),(-?\d+\.\d+)/', $it['terminus_coords'], $m, PREG_SET_ORDER)) {
                $pts = 'SRID=4326;MULTIPOINT('.implode(',', array_map(fn ($c) => "({$c[2]} {$c[1]})", $m)).')';
            }
            $geo = null;
            if ($pts) {
                $geo = DB::selectOne("WITH p AS (SELECT (ST_Dump(ST_Transform(ST_GeomFromEWKT(?), 2154))).geom AS p),
                        near AS (SELECT p.p, t.source, t.line_id FROM p JOIN LATERAL (
                            SELECT source, line_id FROM au_wt t WHERE ST_DWithin(t.g, p.p, 1000) LIMIT 50) t ON true)
                    SELECT (SELECT COUNT(*) FROM p) AS npts, COUNT(DISTINCT near.p) AS covered,
                           STRING_AGG(DISTINCT near.source, ',') AS sources,
                           MODE() WITHIN GROUP (ORDER BY near.line_id) AS line_id
                    FROM near", [$pts]);
            }

            $line = $nameHit;
            if ($line) {
                $res['methode'] = 'nom';
            } elseif ($geo && $geo->covered >= 2 && $geo->line_id) {
                $cand = DB::selectOne("SELECT id, name, slug, network, code, alt_names->>'created_by' AS created_by FROM lines WHERE id = ?", [$geo->line_id]);
                // Un libellé « ligne de X à Y » doit partager au moins un toponyme avec la ligne trouvée
                // (sinon ce sont deux lignes qui se croisent aux terminus : Bondy-Aulnay ≠ Paris-Strasbourg).
                $ok = true;
                if ($cand && $parsed && count($parsed['places']) >= 2) {
                    $lp = PlaceNames::parseLineName($cand->name);
                    $ok = $lp && array_intersect(array_merge(...array_map(fn ($p) => PlaceNames::significant($p), $lp['places'])), $wAll);
                }
                if ($cand && $ok) {
                    $line = $cand;
                    $res['methode'] = 'terminus/points';
                } elseif ($cand) {
                    $res['note'] = "terminus près de « {$cand->name} » (#{$cand->id}), autre ligne probable";
                }
            }
            $traced = $line && DB::table('source_traces')->where('line_id', $line->id)
                ->whereIn('source', ['archeoferroviaire', 'ohm', 'osm'])->exists();
            $res['sources'] = $line ? implode(',', array_column(DB::select("SELECT DISTINCT source FROM source_traces WHERE line_id = ? AND source NOT IN ('sncf', 'bdtopo')", [$line->id]), 'source')) : ($geo->sources ?? '');

            if ($line && $traced) {
                $res['statut'] = $line->created_by === 'vff:link-traces'
                    ? 'créée par vff:link-traces'
                    : 'déjà dans VFF (autre nom/code)';
                $res['ligne'] = $line;
                if ($line->created_by === 'vff:link-traces' && ! $line->code && $it['codes']) {
                    $res['note'] = 'code Wikidata '.$it['codes'].' non posé (ambigu ou déjà pris)';
                }
            } elseif ($line) {
                $res['statut'] = 'ligne VFF sans tracé précis';
                $res['ligne'] = $line;
            } elseif ($geo && $geo->covered >= 1) {
                $res['statut'] = 'tracé présent ('.$geo->sources.') mais non rattaché à cette ligne';
                $res['note'] = trim($geo->covered.'/'.$geo->npts.' points Wikidata près d\'un tracé ; '.$res['note'], ' ;');
            } else {
                $res['statut'] = ($label === '' && ! $pts) ? 'aucun tracé (item sans libellé ni coordonnées)' : 'aucun tracé';
            }
            $stats[$it['categorie']][$res['statut']] = ($stats[$it['categorie']][$res['statut']] ?? 0) + 1;

            $row = [$qid, $label, $it['categorie'], $it['codes'], $it['raison'], $it['ouverture'], $it['fermeture'],
                $it['longueur_km'], $it['terminus'], $res['statut'], $res['methode'], $res['ligne']->id ?? '',
                $res['ligne']->name ?? '', $res['ligne']->network ?? '', $res['ligne']->code ?? '', $res['sources'], $res['note'],
                $it['osm_relation'], $it['wikidata_url']];
            $out[] = $row;
            if (str_starts_with($res['statut'], 'aucun tracé')) {
                $none[] = $row;
            }
        }
        $this->info('→ 6. Lignes Wikidata absentes de VFF : '.count($items));
        $table = [];
        foreach ($stats as $cat => $byStatus) {
            foreach ($byStatus as $st => $n) {
                $table[] = [$cat, $st, $n];
            }
        }
        $this->table(['Catégorie Wikidata', 'Statut', 'Items'], $table);
        $head = ['qid', 'libellé', 'catégorie', 'codes', 'raison', 'ouverture', 'fermeture', 'longueur_km', 'terminus', 'statut',
            'méthode', 'ligne_vff_id', 'ligne_vff', 'catégorie VFF', 'code VFF', 'sources des tracés', 'note', 'relation OSM', 'wikidata'];
        $this->writeCsv('wikidata-couverture', 'Couverture des lignes Wikidata absentes', $head, $out);
        $this->writeCsv('wikidata-sans-trace', 'Lignes Wikidata SANS AUCUN tracé (à faire tracer)', $head, $none);
    }

    // ───────────────────────────── 7. doublons ─────────────────────────────

    private function duplicateNames(): void
    {
        $rows = DB::select("SELECT LOWER(name) AS nom, COUNT(*) AS n, STRING_AGG(id || ' ' || slug || ' (' || COALESCE(network, '?') || ')', ' ; ') AS lignes
            FROM lines GROUP BY LOWER(name) HAVING COUNT(*) > 1 ORDER BY n DESC, nom");
        $this->info('→ 7. Noms en double : '.count($rows));
        $this->writeCsv('noms-en-double', 'Noms de lignes en double', ['nom', 'nombre', 'lignes'], $rows);
    }

    // ───────────────────────────── utilitaires ─────────────────────────────

    /** @param  array<int, array<int, mixed>|object>  $rows */
    private function writeCsv(string $name, string $what, array $head, array $rows): void
    {
        $path = $this->dir.'/'.$name.'.csv';
        $fh = fopen($path, 'w');
        fputcsv($fh, $head);
        foreach ($rows as $r) {
            fputcsv($fh, array_values((array) $r));
        }
        fclose($fh);
        $this->outputs[$what.' ('.count($rows).')'] = $path;
    }
}
