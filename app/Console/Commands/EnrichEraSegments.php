<?php

namespace App\Console\Commands;

use App\Services\Traction\ElectrificationText;
use App\Services\Traction\ElectrificationTimeline;
use App\Services\Traction\Gauge;
use App\Services\Traction\TractionCuration;
use App\Support\Lgv;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Enrichit les segments de CHAQUE ère (produits par vff:build-era-segments, qui
 * n'est pas modifié) avec l'ÉLECTRIFICATION (electrified, voltage_v) et
 * l'ÉCARTEMENT (gauge_mm, gauge_class) de chaque portion, avec leur provenance.
 * Étape séparée, rejouable : à lancer juste après build-era-segments.
 *
 * Méthode, ligne par ligne :
 *   1. Chaque portion (géométrie distincte de la ligne) est découpée en tronçons de
 *      STEP m ; pour chaque tronçon on relève :
 *        - l'état ACTUEL : SNCF Open Data (liste des lignes électrifiées, même code
 *          ligne, formes généralisées → tolérance 200 m), OSM (electrified, voltage,
 *          gauge, ≤ 10 m), BD TOPO (electrifie, largeur, ≤ 8 m) ;
 *        - l'état PASSÉ : OHM (gauge et dates), OSM désaffecté (electrified, gauge),
 *          noms des tracés archeoferroviaire (« V60 », « VN », « voie de 0m60 ») ;
 *        - les événements « electrification » localisés qui le couvrent (≤ 30 m).
 *   2. Les tronçons contigus de même état actuel / même écartement forment des
 *      sous-portions ; une portion hétérogène (ligne électrifiée sur une partie
 *      seulement, sans événement pour la couper) est DÉCOUPÉE en autant de segments
 *      (sous-portions de moins de --min-run m fusionnées avec leur voisine).
 *   3. Chaque sous-portion est rejouée à la date de chaque ère : chronologie des
 *      électrifications (App\Services\Traction\ElectrificationTimeline) et des
 *      changements d'écartement (App\Services\Traction\Gauge::at).
 *
 * Portions électrifiées aujourd'hui sans événement propre : date d'une portion voisine
 * de la même ligne (≤ 25 km si la ligne a une date d'achèvement), sinon événement
 * « toute la ligne », sinon événement non localisé ; à défaut, « date inconnue »
 * (electrification_source = unknown).
 *
 *   php artisan vff:enrich-era-segments
 *   php artisan vff:enrich-era-segments --line=830000
 *   php artisan vff:enrich-era-segments --shard=1/6
 *   php artisan vff:enrich-era-segments --report
 */
class EnrichEraSegments extends Command
{
    protected $signature = 'vff:enrich-era-segments
        {--line= : id ou code d\'une seule ligne}
        {--shard= : traitement parallèle « i/n » : lignes dont id % n = i-1}
        {--step=100 : longueur des tronçons d\'analyse (m)}
        {--min-run=400 : longueur minimale d\'une sous-portion découpée (m)}
        {--no-split : ne découpe pas les portions hétérogènes (valeur majoritaire)}
        {--missing : seulement les lignes dont des segments n\'ont pas encore été enrichis (régénérées depuis)}
        {--report : affiche seulement le bilan (km électrifiés par ère et tension, km par écartement)}';

    protected $description = 'Électrification (tension) et écartement de chaque portion à chaque ère, d\'après événements datés, SNCF, OSM, BD TOPO, OHM, archeo.';

    /** Couches OSM/OHM d'une voie encore posée (état physique actuel). */
    private const CURRENT_LAYERS = ['rail', 'narrow_gauge', 'light_rail', 'funicular', 'disused', 'preserved', 'construction', 'tram', 'subway', 'monorail', 'disused:railway', 'construction:railway'];

    /** Distance max (m) à la portion d'une portion voisine pour lui prêter sa date. */
    private const NEIGHBOUR_MAX_M = 25000;

    private array $stats = ['lines' => 0, 'pieces' => 0, 'split_pieces' => 0, 'new_rows' => 0, 'updated_rows' => 0];

    /** @var array<int, array{year: int, current: bool}> */
    private array $eras = [];

    private array $copyColumns = [];

    public function handle(): int
    {
        // Compteurs remis à zéro : vff:refresh-current-era appelle la commande ligne par ligne dans le
        // même processus (sinon chaque bilan cumulerait ceux des appels précédents).
        $this->stats = ['lines' => 0, 'pieces' => 0, 'split_pieces' => 0, 'new_rows' => 0, 'updated_rows' => 0];
        $this->eras = [];
        foreach (DB::select('SELECT id, year, is_current FROM eras ORDER BY year') as $e) {
            $this->eras[$e->id] = ['year' => (int) $e->year, 'current' => (bool) $e->is_current];
        }
        if ($this->option('report')) {
            $this->report();

            return self::SUCCESS;
        }
        if (! DB::table('sncf_line_electrification')->exists()) {
            $this->warn('sncf_line_electrification est vide : lancer vff:import-sncf-electrification (état actuel SNCF absent).');
        }
        $this->copyColumns = $this->copyableColumns();

        $lines = $this->targetLines();
        $this->info(count($lines).' lignes');
        $bar = $this->output->createProgressBar(count($lines));
        foreach ($lines as $line) {
            DB::transaction(fn () => $this->enrichLine($line));
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();
        $this->line(sprintf('  %d lignes, %d portions, %d découpées (%d segments créés), %d segments mis à jour',
            $this->stats['lines'], $this->stats['pieces'], $this->stats['split_pieces'], $this->stats['new_rows'], $this->stats['updated_rows']));
        if (! $this->option('shard') && ! $this->option('line')) {
            $this->report();
        }

        return self::SUCCESS;
    }

    private function targetLines(): array
    {
        $q = DB::table('lines')
            ->whereExists(fn ($w) => $w->select(DB::raw(1))->from('line_segments')->whereColumn('line_segments.line_id', 'lines.id'))
            ->select(['lines.id', 'lines.code', 'lines.name', 'lines.network', 'lines.gauge_mm_default', 'lines.description',
                DB::raw(Lgv::sql('lines', null).' AS is_lgv')])
            ->orderBy('lines.id');
        if ($shard = $this->option('shard')) {
            [$i, $n] = array_map('intval', explode('/', $shard));
            $q->whereRaw('lines.id % ? = ?', [$n, $i - 1]);
        }
        if ($opt = $this->option('line')) {
            $q->where(fn ($w) => $w->where('lines.code', $opt)->orWhere('lines.id', ctype_digit($opt) && strlen($opt) < 6 ? (int) $opt : 0));
        }
        if ($this->option('missing')) {
            // Segments régénérés par vff:build-era-segments depuis le dernier enrichissement.
            $q->whereExists(fn ($w) => $w->select(DB::raw(1))->from('line_segments as m')
                ->whereColumn('m.line_id', 'lines.id')->whereNull('m.electrification_source'));
        }

        return $q->get()->all();
    }

    /** Colonnes de line_segments recopiées telles quelles quand une portion est découpée. */
    private function copyableColumns(): array
    {
        $skip = ['id', 'geom', 'length_meters', 'created_at', 'updated_at', 'electrified', 'voltage_v', 'electrification_source',
            'electrified_year', 'gauge_mm', 'gauge_class', 'gauge_source', 'pk_start_m', 'pk_end_m'];

        return array_values(array_filter(
            array_map(fn ($c) => $c->column_name, DB::select("SELECT column_name FROM information_schema.columns WHERE table_name = 'line_segments' AND table_schema = current_schema() ORDER BY ordinal_position")),
            fn ($c) => ! in_array($c, $skip, true),
        ));
    }

    private function enrichLine(object $line): void
    {
        // Même verrou que vff:build-era-segments : la ligne n'est pas régénérée pendant l'enrichissement.
        DB::select('SELECT pg_advisory_xact_lock(?)', [$line->id]);

        $segments = DB::select('SELECT id, era_id, status, md5(ST_AsEWKB(geom)) AS h FROM line_segments WHERE line_id = ?', [$line->id]);
        if (! $segments) {
            return;
        }
        $this->stats['lines']++;
        $byPiece = [];
        foreach ($segments as $s) {
            $byPiece[$s->h][] = $s;
        }

        [$events, $locatedIds] = $this->electrificationEvents($line);
        $lineGauge = $this->lineGaugeEvidence($line);
        $catlig = $line->code ? DB::selectOne('SELECT catlig FROM sncf_line_catlig WHERE code_ligne = ? ORDER BY id LIMIT 1', [$line->code])?->catlig : null;
        [$catligMm, $catligClass] = Gauge::fromSncfCatlig($catlig);

        // Portions des événements localisés, indexées (cf. vff:build-era-segments).
        DB::statement('CREATE TEMP TABLE IF NOT EXISTS tmp_elec_parts (id bigint, g geometry)');
        DB::statement('CREATE INDEX IF NOT EXISTS tmp_elec_parts_g ON tmp_elec_parts USING GIST (g)');
        DB::statement('TRUNCATE tmp_elec_parts');
        if ($locatedIds) {
            DB::insert('INSERT INTO tmp_elec_parts SELECT id, ST_Subdivide(ST_Transform(section_geom, 2154), 32) FROM line_events WHERE id = ANY(CAST(? AS bigint[]))',
                ['{'.implode(',', $locatedIds).'}']);
        }
        DB::statement('ANALYZE tmp_elec_parts');

        // Portions exploitées à l'ère courante (voie en service aujourd'hui).
        $activeToday = [];
        foreach ($segments as $s) {
            if (($this->eras[$s->era_id]['current'] ?? false) && in_array($s->status, ['in_service', 'single_track', 'service_track'], true)) {
                $activeToday[$s->h] = true;
            }
        }
        $chunks = $this->chunks($line);
        $pieces = [];
        foreach ($chunks as $c) {
            $pieces[$c->h]['n'] = (int) $c->n;
            $pieces[$c->h]['source'] = $c->source;
            $pieces[$c->h]['len'] = (float) $c->len;
            $pieces[$c->h]['ends'] = [$c->x0, $c->y0, $c->x1, $c->y1];
            $pieces[$c->h]['chunks'][(int) $c->i] = $this->readChunk($c, $line, $catligMm, $catligClass, $activeToday[$c->h] ?? false);
        }

        // Voie étroite d'écartement non précisé (BD TOPO « Etroite », SNCF « voie étroite ») :
        // écartement étroit le plus fréquent relevé par OSM sur la même ligne.
        $narrowMm = [];
        foreach ($pieces as $p) {
            foreach ($p['chunks'] as $c) {
                if (($c['gcur']['source'] ?? null) === 'osm' && in_array($c['gcur']['class'], [Gauge::NARROW, Gauge::METRE], true)) {
                    $narrowMm[$c['gcur']['mm']] = ($narrowMm[$c['gcur']['mm']] ?? 0) + 1;
                }
            }
        }
        arsort($narrowMm);
        if ($mode = array_key_first($narrowMm)) {
            foreach ($pieces as $h => $p) {
                foreach ($p['chunks'] as $i => $c) {
                    if ($c['gcur'] && $c['gcur']['mm'] === null && $c['gcur']['class'] === Gauge::NARROW) {
                        $pieces[$h]['chunks'][$i]['gcur'] = ['mm' => (int) $mode, 'class' => Gauge::classOf((int) $mode), 'source' => $c['gcur']['source']];
                    }
                }
            }
        }

        // Sous-portions (runs) de chaque portion.
        $runs = [];
        foreach ($pieces as $h => $p) {
            if (! isset($byPiece[$h])) {
                continue;
            }
            $this->stats['pieces']++;
            ksort($p['chunks']);
            foreach ($this->splitRuns($p['chunks'], $p['n'], $p['len']) as $k => [$i0, $i1]) {
                $runs[] = $this->aggregateRun($h, $k, $i0, $i1, $p, $events);
            }
        }
        $this->propagateNeighbourDates($runs, $events, $pieces);
        foreach ($runs as $idx => $r) {
            $runs[$idx]['timeline'] = new ElectrificationTimeline(array_values($r['events']), $r['current'], (bool) $line->is_lgv, $r['hist']);
        }

        // Rejeu par ère et écriture.
        $updates = [];
        $inserts = [];
        $splitIds = [];
        $runsByPiece = [];
        foreach ($runs as $r) {
            $runsByPiece[$r['h']][] = $r;
        }
        foreach ($runsByPiece as $h => $pieceRuns) {
            $split = count($pieceRuns) > 1;
            if ($split) {
                $this->stats['split_pieces']++;
            }
            foreach ($byPiece[$h] as $seg) {
                $era = $this->eras[$seg->era_id] ?? null;
                if ($era === null) {
                    continue;
                }
                foreach ($pieceRuns as $r) {
                    $values = $this->valuesAt($r, $era, $seg->status, $lineGauge);
                    if ($split) {
                        $inserts[] = [$seg->id, $r['f0'], $r['f1'], ...$values];
                    } else {
                        $updates[] = [$seg->id, ...$values];
                    }
                }
                if ($split) {
                    $splitIds[] = $seg->id;
                }
            }
        }
        $this->write($updates, $inserts, $splitIds);
    }

    /**
     * Événements d'électrification de la ligne, classés par portée.
     *
     * @return array{0: array<int, array>, 1: array<int, int>} [événements (id => événement), ids localisés]
     */
    private function electrificationEvents(object $line): array
    {
        $curated = TractionCuration::electrification($line->code);
        $events = [];
        $located = [];
        if ($curated && $curated['replace']) {
            foreach ($curated['events'] as $k => $e) {
                $events['c'.$k] = $e + ['scope' => 'curated'];
            }

            return [$events, []];
        }

        $rows = DB::select("SELECT id, event_date::text AS d, section_resolution AS r, section_from, section_pk_start_m, section_geom IS NOT NULL AS has_geom,
                                   COALESCE(source_quote, '') AS q, COALESCE(description, '') AS descr
                            FROM line_events WHERE line_id = ? AND type = 'electrification' ORDER BY event_date", [$line->id]);
        $buckets = ['located' => [], 'line' => [], 'unlocated' => []];
        foreach ($rows as $row) {
            $text = trim($row->q.' '.$row->descr);
            $kind = ElectrificationText::kind($row->q !== '' ? $row->q : $row->descr);
            if ($kind === ElectrificationText::KIND_IGNORE) {
                continue;
            }
            $voltage = ElectrificationText::voltage($text);
            $whole = $row->r === 'whole_line' || ($row->r === null && $row->section_from === null && $row->section_pk_start_m === null);
            $scope = in_array($row->r, ['pk', 'places', 'trace'], true) && $row->has_geom ? 'located' : ($whole ? 'line' : 'unlocated');
            $buckets[$scope][$row->id] = ['date' => $row->d, 'kind' => $kind, 'voltage_v' => $voltage, 'scope' => $scope, 'text' => $text];
            if ($scope === 'located') {
                $located[] = $row->id;
            }
            // « …en 12000 V, conversion en 1923 en 1500 V » : seconde mise sous tension, même portion.
            if ($kind === ElectrificationText::KIND_ELECTRIFICATION
                && preg_match('/conversion\s+en\s+(\d{4})\s+en\s+(\d[\d\s]*)\s*V/iu', $text, $m)) {
                $buckets[$scope][$row->id.'c'] = ['date' => $m[1].'-01-01', 'kind' => 'electrification', 'voltage_v' => (int) preg_replace('/\D/', '', $m[2]),
                    'scope' => $scope, 'text' => '', 'same_as' => $row->id];
            }
            // Wikidata P582 : fin de ce régime (troisième rail remplacé, tension changée…).
            if ($kind === ElectrificationText::KIND_ELECTRIFICATION && ($end = ElectrificationText::wikidataEndYear($text))) {
                $buckets[$scope][$row->id.'e'] = ['date' => $end.'-01-01', 'kind' => 'deelectrification', 'voltage_v' => null,
                    'scope' => $scope, 'text' => '', 'same_as' => $row->id, 'end_of' => true];
            }
        }
        // Fin de régime suivie d'une nouvelle électrification (± 1 an, toute portée) : pas de dépose.
        $elecYears = [];
        foreach ($buckets as $list) {
            foreach ($list as $e) {
                if ($e['kind'] === 'electrification') {
                    $elecYears[] = (int) substr($e['date'], 0, 4);
                }
            }
        }
        foreach ($buckets as $scope => $list) {
            foreach ($list as $k => $e) {
                if (! empty($e['end_of'])) {
                    $y = (int) substr($e['date'], 0, 4);
                    if (array_filter($elecYears, fn ($ey) => abs($ey - $y) <= 1)) {
                        unset($buckets[$scope][$k]);
                    }
                }
            }
        }
        // Tension « voisine » : événement de la ligne (toute portée) cité avec sa tension à ± 5 ans
        // (« 1924 : Paris - Bécon électrifié » + « 1928 : troisième rail 750 V » → 750 V).
        $withVoltage = [];
        foreach ($buckets as $list) {
            foreach ($list as $e) {
                if ($e['kind'] === 'electrification' && $e['voltage_v'] !== null) {
                    $withVoltage[] = [(int) substr($e['date'], 0, 4), $e['voltage_v']];
                }
            }
        }
        foreach ($buckets as $scope => $list) {
            foreach ($list as $k => $e) {
                if ($e['kind'] !== 'electrification' || $e['voltage_v'] !== null) {
                    continue;
                }
                $y = (int) substr($e['date'], 0, 4);
                $best = null;
                foreach ($withVoltage as [$vy, $v]) {
                    if (abs($vy - $y) <= 5 && ($best === null || abs($vy - $y) < abs($best[0] - $y))) {
                        $best = [$vy, $v];
                    }
                }
                $buckets[$scope][$k]['voltage_hint'] = $best[1] ?? null;
            }
        }
        $events = $buckets['located'];
        $lineEvents = $buckets['line'];
        $unlocated = $buckets['unlocated'];

        // « Toute la ligne » : une date d'ACHÈVEMENT (« entièrement électrifiée », « entre 1926
        // et 1938 ») borne toute la ligne ; sinon la première date. Les dates postérieures restent
        // (ré-électrification).
        $completion = null;
        foreach ($lineEvents as $id => $e) {
            if ($e['kind'] !== 'electrification') {
                continue;
            }
            $y = ElectrificationText::completionYear($e['text'], (int) substr($e['date'], 0, 4));
            if ($y !== null) {
                $date = $y === (int) substr($e['date'], 0, 4) ? $e['date'] : $y.'-12-31';
                if ($completion === null || $date < $completion['date']) {
                    $completion = ['date' => $date, 'id' => $id];
                }
            }
        }
        $elecLine = array_filter($lineEvents, fn ($e) => $e['kind'] === 'electrification');
        uasort($elecLine, fn ($a, $b) => strcmp($a['date'], $b['date']));
        $firstLine = $completion['date'] ?? (reset($elecLine)['date'] ?? null);
        foreach ($lineEvents as $id => $e) {
            // L'événement d'achèvement prend sa date d'achèvement AVANT la comparaison : « entre 1926
            // et 1935 » (daté 1926) n'est pas antérieur à son propre achèvement (1935).
            if ($completion && $id === $completion['id']) {
                $e['date'] = $completion['date'];
            }
            if ($e['kind'] === 'electrification' && $firstLine !== null && $e['date'] < $firstLine) {
                continue; // antérieur à l'achèvement : partiel, portion inconnue
            }
            $e['completion'] = $completion !== null;
            $events['l'.$id] = $e;
        }

        // Non localisés : une seule date (la dernière si l'infobox dit « partiel », sinon la première).
        $elecUnloc = array_values(array_filter($unlocated, fn ($e) => $e['kind'] === 'electrification'));
        if ($elecUnloc) {
            usort($elecUnloc, fn ($a, $b) => strcmp($a['date'], $b['date']));
            $partial = (bool) array_filter($elecUnloc, fn ($e) => ElectrificationText::partialInfobox($e['text']));
            $events['u'] = $partial ? end($elecUnloc) : $elecUnloc[0];
        }

        if ($curated) {
            foreach ($curated['events'] as $k => $e) {
                $events['c'.$k] = $e + ['scope' => 'line', 'text' => ''];
            }
        }

        return [$events, $located];
    }

    /** Indices d'écartement au niveau de la ligne, par priorité. */
    private function lineGaugeEvidence(object $line): array
    {
        $out = ['line' => [], 'conversions' => [], 'network' => null];
        if ($mm = TractionCuration::gaugeForName($line->name)) {
            $out['line'][] = ['mm' => $mm, 'class' => Gauge::classOf($mm), 'source' => 'curated'];
        }
        if ($c = TractionCuration::gaugeConversion($line->code)) {
            $out['conversions'][] = ['year' => $c['year'], 'from_mm' => $c['from_mm'], 'to_mm' => $c['to_mm'], 'source' => 'curated'];
        }
        $texts = DB::select("SELECT event_date::text AS d, COALESCE(source_quote, description) AS q FROM line_events
                             WHERE line_id = ? AND (source_quote ~* '(voie|[ée]cartement)' OR description ~* '(voie|[ée]cartement)')", [$line->id]);
        if ($line->description) {
            foreach (preg_split('/(?<=[.!?])\s+/u', $line->description) as $sentence) {
                $texts[] = (object) ['d' => null, 'q' => $sentence];
            }
        }
        foreach ($texts as $t) {
            if (! $out['conversions'] && ($conv = Gauge::conversion($t->q, $t->d ? (int) substr($t->d, 0, 4) : null))) {
                $out['conversions'][] = $conv + ['source' => 'event'];
            }
            [$mm, $class] = Gauge::fromLineSentence($t->q, $line->name);
            if ($class !== null) {
                $out['line'][] = ['mm' => $mm, 'class' => $class, 'source' => 'event'];
            }
        }
        // Noms des tracés archeo rattachés à la ligne.
        foreach (DB::select("SELECT name FROM source_traces WHERE line_id = ? AND source = 'archeoferroviaire' AND name IS NOT NULL", [$line->id]) as $t) {
            [$mm, $class] = Gauge::fromName($t->name);
            if ($class !== null) {
                $out['line'][] = ['mm' => $mm, 'class' => $class, 'source' => 'archeo'];
                break;
            }
        }
        $wd = DB::selectOne("SELECT (facts->>'gauge_mm')::int AS g FROM wikidata_lines WHERE line_id = ? AND facts->>'gauge_mm' ~ '^[0-9]+$' LIMIT 1", [$line->id]);
        if ($wd?->g) {
            $out['line'][] = ['mm' => (int) $wd->g, 'class' => Gauge::classOf((int) $wd->g), 'source' => 'wikidata'];
        }
        if ($line->gauge_mm_default) {
            $out['line'][] = ['mm' => (int) $line->gauge_mm_default, 'class' => Gauge::classOf((int) $line->gauge_mm_default), 'source' => 'line'];
        }
        // Réseau d'intérêt général : voie normale, sauf indice contraire (voies métriques connues : OSM, BD TOPO, SNCF).
        if ($line->network === 'principal') {
            $out['network'] = 1435;
        }

        return $out;
    }

    /** Tronçons de STEP m de chaque portion de la ligne, avec les indices relevés. */
    private function chunks(object $line): array
    {
        // Tracés candidats (OSM, BD TOPO, OHM, archeo) le long de la ligne, rattachés à elle ou
        // non rattachés, projetés UNE fois en Lambert 93 et indexés : la recherche du tracé le
        // plus proche de chaque tronçon de 100 m devient une simple recherche KNN.
        DB::statement('CREATE TEMP TABLE IF NOT EXISTS tmp_traces (source text, layer text, name text, linked text, el text, volt text,
                       gauge text, sd text, ed text, b_el text, b_larg text, b_etat text, g geometry)');
        foreach (['osm', 'bdtopo', 'ohm', 'archeoferroviaire'] as $src) {
            DB::statement("CREATE INDEX IF NOT EXISTS tmp_traces_{$src} ON tmp_traces USING GIST (g) WHERE source = '{$src}'");
        }
        DB::statement('TRUNCATE tmp_traces');
        DB::insert(<<<'SQL'
            INSERT INTO tmp_traces
            SELECT st.source, st.layer, st.name, CASE WHEN st.line_id IS NULL THEN '0' ELSE '1' END,
                   COALESCE(st.properties->>'electrified', ''), COALESCE(st.properties->>'voltage', ''), COALESCE(st.properties->>'gauge', ''),
                   COALESCE(left(st.properties->>'start_date', 4), ''), COALESCE(left(st.properties->>'end_date', 4), ''),
                   COALESCE(st.properties->>'electrifie', ''), COALESCE(st.properties->>'largeur', ''), COALESCE(st.properties->>'etat_de_l_objet', ''),
                   ST_Transform(st.geom, 2154)
            FROM source_traces st
            WHERE st.id IN (
                SELECT st2.id FROM (
                    SELECT DISTINCT ST_Expand(ST_Envelope(d.geom), 0.0006) AS e
                    FROM (SELECT DISTINCT geom FROM line_segments WHERE line_id = :line) s, LATERAL ST_Subdivide(s.geom, 16) AS d(geom)
                ) b JOIN source_traces st2 ON st2.geom && b.e
                WHERE st2.source IN ('osm', 'bdtopo', 'ohm', 'archeoferroviaire')
            )
              AND (st.line_id IS NULL OR st.line_id = :lid OR EXISTS (SELECT 1 FROM source_trace_lines stl WHERE stl.source_trace_id = st.id AND stl.line_id = :lid2))
              AND (st.source <> 'archeoferroviaire'
                   OR st.name ~ '(V ?60|V ?75|\mVN\M|\mVM\M|0 ?[m,.] ?[67][05]|[67]0 ?cm|[Vv]oie (m[ée]trique|normale|[ée]troite|de 0)|[Dd]ecauville)')
            SQL, ['line' => $line->id, 'lid' => $line->id, 'lid2' => $line->id]);
        DB::statement('ANALYZE tmp_traces');

        return DB::select(<<<'SQL'
            WITH pieces AS MATERIALIZED (
                SELECT DISTINCT ON (h) h, source, g FROM (
                    SELECT md5(ST_AsEWKB(geom)) AS h, source, ST_Transform(geom, 2154) AS g
                    FROM line_segments WHERE line_id = :line
                ) t ORDER BY h
            ),
            chunks AS MATERIALIZED (
                SELECT h, source, n, i, ST_Length(g) AS len,
                       ST_X(ST_StartPoint(g)) AS x0, ST_Y(ST_StartPoint(g)) AS y0, ST_X(ST_EndPoint(g)) AS x1, ST_Y(ST_EndPoint(g)) AS y1, mid
                FROM pieces,
                     LATERAL (SELECT GREATEST(1, ROUND(ST_Length(g) / :step))::int AS n) k,
                     LATERAL generate_series(0, n - 1) AS i,
                     LATERAL ST_LineInterpolatePoint(g, (i + 0.5) / n) AS mid
            ),
            sncf_e AS MATERIALIZED (
                SELECT voltage_v, ST_Transform(geom, 2154) AS g FROM sncf_line_electrification WHERE code_ligne = :code
            ),
            sncf_l AS MATERIALIZED (
                SELECT ST_Transform(geom, 2154) AS g FROM sncf_line_geometries WHERE code_ligne = :code2
            )
            SELECT h, source, n, i, len, x0, y0, x1, y1,
                   (SELECT voltage_v FROM sncf_e WHERE ST_DWithin(sncf_e.g, mid, 200) ORDER BY sncf_e.g <-> mid LIMIT 1) AS sncf_v,
                   EXISTS (SELECT 1 FROM sncf_l WHERE ST_DWithin(sncf_l.g, mid, 200)) AS sncf_on,
                   (SELECT concat_ws('|', COALESCE(t.layer, ''), t.el, t.volt, t.gauge, t.linked)
                    FROM tmp_traces t WHERE t.source = 'osm' AND ST_DWithin(t.g, mid, 10) ORDER BY t.g <-> mid LIMIT 1) AS osm,
                   (SELECT concat_ws('|', t.b_el, t.b_larg, t.b_etat, t.linked)
                    FROM tmp_traces t WHERE t.source = 'bdtopo' AND ST_DWithin(t.g, mid, 8) ORDER BY t.g <-> mid LIMIT 1) AS bdt,
                   (SELECT concat_ws('|', COALESCE(t.layer, ''), t.gauge, t.sd, t.ed, t.el, t.volt, t.linked)
                    FROM tmp_traces t WHERE t.source = 'ohm' AND ST_DWithin(t.g, mid, 20) ORDER BY t.g <-> mid LIMIT 1) AS ohm,
                   (SELECT t.name FROM tmp_traces t WHERE t.source = 'archeoferroviaire' AND ST_DWithin(t.g, mid, 25) ORDER BY t.g <-> mid LIMIT 1) AS arch,
                   COALESCE((SELECT array_agg(DISTINCT id) FROM tmp_elec_parts WHERE ST_DWithin(tmp_elec_parts.g, mid, 30)), '{}') AS evs
            FROM chunks
            SQL, ['line' => $line->id, 'step' => (float) $this->option('step'), 'code' => (string) $line->code, 'code2' => (string) $line->code]);
    }

    /**
     * Lecture d'un tronçon : état actuel (électrification, écartement), indices passés, événements.
     */
    private function readChunk(object $c, object $line, ?int $catligMm, ?string $catligClass, bool $activeToday): array
    {
        $oldAlignment = in_array($c->source, ['archeoferroviaire', 'ohm'], true);

        // --- Électrification actuelle : SNCF (même code ligne) > OSM > BD TOPO.
        $osm = $c->osm !== null ? explode('|', $c->osm) + ['', '', '', '', ''] : null;
        $bdt = $c->bdt !== null ? explode('|', $c->bdt) + ['', '', '', ''] : null;
        // Portion disparue ou fermée aujourd'hui : une voie EXPLOITÉE non rattachée à la ligne qui
        // passe à moins de 10 m appartient à une autre ligne (gare commune, tronc partagé).
        if (! $activeToday) {
            if ($osm && $osm[4] === '0' && in_array($osm[0], ['rail', 'narrow_gauge', 'light_rail', 'subway', 'tram', 'funicular'], true)) {
                $osm = null;
            }
            if ($bdt && $bdt[3] === '0' && $bdt[2] === 'En service') {
                $bdt = null;
            }
        }
        $osmCurrent = $osm && in_array($osm[0], self::CURRENT_LAYERS, true);
        $osmElec = $osm ? ElectrificationText::osmElectrified($osm[1] !== '' ? $osm[1] : null) : null;
        $osmV = $osm ? ElectrificationText::osmVoltage($osm[2] !== '' ? $osm[2] : null) : null;
        $bdtElec = $bdt && $bdt[0] !== '' ? $bdt[0] === 'true' : null;

        $state = null;
        $voltage = null;
        $src = null;
        $sncfV = ! $oldAlignment && $c->sncf_v !== null ? (int) $c->sncf_v : null;
        // Tronçon d'une ligne du RFN (forme SNCF du même code à moins de 200 m) : la liste SNCF
        // des lignes électrifiées fait foi dans les deux sens (la voie la plus proche en gare
        // peut appartenir à une autre ligne, électrifiée elle).
        if ($sncfV !== null) {
            [$state, $voltage, $src] = [true, $sncfV, 'sncf'];
        } elseif (! $oldAlignment && $c->sncf_on) {
            [$state, $src] = [false, 'sncf'];
        } elseif ($osmCurrent && $osmElec === true && $bdtElec !== false) {
            [$state, $voltage, $src] = [true, $osmV, 'osm'];
        } elseif ($bdtElec === true && ! ($osmCurrent && $osmElec === false)) {
            [$state, $voltage, $src] = [true, $osmCurrent ? $osmV : null, 'bdtopo'];
        } elseif ($osmCurrent && $osmElec !== null) {
            [$state, $voltage, $src] = [$osmElec, $osmElec ? $osmV : null, 'osm'];
        } elseif ($bdtElec !== null) {
            [$state, $src] = [$bdtElec, 'bdtopo'];
        }
        // Voie disparue mais caténaire attestée (OSM abandonné / OHM) : électrifiée par le passé.
        $histElec = false;
        $histV = null;
        if ($osm && ! $osmCurrent && $osmElec === true) {
            [$histElec, $histV] = [true, $osmV];
        }
        $ohm = $c->ohm !== null ? explode('|', $c->ohm) + ['', '', '', '', '', '', ''] : null;
        // Voie exploitée aujourd'hui : un tracé OHM non rattaché à la ligne (tramway, ancienne ligne
        // voisine) ne dit rien de son écartement passé.
        if ($ohm && $activeToday && $ohm[6] === '0') {
            $ohm = null;
        }
        if ($ohm && ElectrificationText::osmElectrified($ohm[4] !== '' ? $ohm[4] : null) === true && $ohm[3] !== '') {
            [$histElec, $histV] = [true, $histV ?? ElectrificationText::osmVoltage($ohm[5] !== '' ? $ohm[5] : null)];
        }

        // --- Écartement actuel : OSM (voie posée) > BD TOPO > SNCF catlig.
        $gCur = null;
        $osmGauge = $osm ? Gauge::fromTag($osm[3] !== '' ? $osm[3] : null) : null;
        if ($osmCurrent && $osmGauge !== null) {
            $gCur = ['mm' => $osmGauge, 'class' => Gauge::classOf($osmGauge), 'source' => 'osm'];
        } elseif ($bdt && ($b = Gauge::fromBdtopo($bdt[1] !== '' ? $bdt[1] : null)) && $b[1] !== null) {
            $gCur = ['mm' => $b[0], 'class' => $b[1], 'source' => 'bdtopo'];
        } elseif (! $oldAlignment && $c->sncf_on && $catligClass !== null) {
            $gCur = ['mm' => $catligMm, 'class' => $catligClass, 'source' => 'sncf'];
        }
        // Catégorie SNCF de la ligne (écartement normal / voie étroite) : arbitre la CLASSE quand la
        // voie la plus proche en gare appartient à une ligne d'un autre écartement.
        if (! $oldAlignment && $c->sncf_on && $catligClass !== null && $gCur !== null) {
            $narrowish = fn (?string $k) => in_array($k, [Gauge::NARROW, Gauge::METRE], true);
            $agree = $catligClass === Gauge::NARROW ? $narrowish($gCur['class']) : $gCur['class'] === $catligClass;
            if (! $agree) {
                $gCur = ['mm' => $catligMm, 'class' => $catligClass, 'source' => 'sncf'];
            }
        }
        // --- Écartement passé : OHM daté > OSM désaffecté > nom archeo.
        $gHist = null;
        if ($ohm && ($g = Gauge::fromTag($ohm[1] !== '' ? $ohm[1] : null)) !== null) {
            $gHist = ['mm' => $g, 'class' => Gauge::classOf($g), 'source' => 'ohm',
                'from' => ctype_digit($ohm[2]) ? (int) $ohm[2] : null, 'until' => ctype_digit($ohm[3]) ? (int) $ohm[3] : null];
        } elseif ($osm && ! $osmCurrent && $osmGauge !== null) {
            $gHist = ['mm' => $osmGauge, 'class' => Gauge::classOf($osmGauge), 'source' => 'osm_hist'];
        } elseif ($c->arch !== null) {
            [$mm, $class] = Gauge::fromName($c->arch);
            if ($class !== null) {
                $gHist = ['mm' => $mm, 'class' => $class, 'source' => 'archeo'];
            }
        }

        return [
            'state' => $state, 'voltage' => $voltage, 'src' => $src, 'hist' => $histElec, 'hist_v' => $histV,
            'gcur' => $gCur, 'ghist' => $gHist,
            'evs' => array_filter(array_map('intval', explode(',', trim((string) $c->evs, '{}')))),
        ];
    }

    /**
     * Découpe une portion en sous-portions d'état homogène : [[i0, i1], …] (indices de tronçons inclus).
     * Les tronçons sans observation prennent l'état du voisin le plus proche (≤ 5 tronçons) ;
     * les sous-portions plus courtes que --min-run sont fusionnées dans leur voisine la plus longue.
     */
    private function splitRuns(array $chunks, int $n, float $len): array
    {
        if ($this->option('no-split') || $n < 2) {
            return [[0, $n - 1]];
        }
        $sig = [];
        foreach ($chunks as $i => $c) {
            $sig[$i] = $c['state'] === null ? null : ($c['state'] ? 'E'.($c['voltage'] ?? '?') : 'N');
            $sig[$i] .= '/'.($c['gcur']['class'] ?? ($c['ghist']['class'] ?? '-'));
        }
        // Comble les trous d'observation (gares, ponts) avec le voisin le plus proche.
        $keys = array_keys($sig);
        foreach ($keys as $i) {
            if (! str_starts_with((string) $sig[$i], '/')) {
                continue;
            }
            for ($d = 1; $d <= 5; $d++) {
                foreach ([$i - $d, $i + $d] as $j) {
                    if (isset($sig[$j]) && ! str_starts_with((string) $sig[$j], '/')) {
                        $sig[$i] = explode('/', $sig[$j])[0].substr($sig[$i], strpos($sig[$i], '/'));
                        break 2;
                    }
                }
            }
        }
        $runs = [];
        foreach ($keys as $i) {
            if ($runs && end($runs)[2] === $sig[$i]) {
                $runs[count($runs) - 1][1] = $i;
            } else {
                $runs[] = [$i, $i, $sig[$i]];
            }
        }
        $chunkLen = $len / max(1, $n);
        $minChunks = max(1, (int) ceil((float) $this->option('min-run') / max(1.0, $chunkLen)));
        while (count($runs) > 1) {
            $shortest = null;
            foreach ($runs as $k => $r) {
                $size = $r[1] - $r[0] + 1;
                if ($size < $minChunks && ($shortest === null || $size < $runs[$shortest][1] - $runs[$shortest][0] + 1)) {
                    $shortest = $k;
                }
            }
            if ($shortest === null) {
                break;
            }
            $left = $runs[$shortest - 1] ?? null;
            $right = $runs[$shortest + 1] ?? null;
            $into = ($right === null || ($left !== null && ($left[1] - $left[0]) >= ($right[1] - $right[0]))) ? $shortest - 1 : $shortest + 1;
            $runs[$into][0] = min($runs[$into][0], $runs[$shortest][0]);
            $runs[$into][1] = max($runs[$into][1], $runs[$shortest][1]);
            array_splice($runs, $shortest, 1);
            // Fusionne deux voisines devenues identiques.
            $merged = [];
            foreach ($runs as $r) {
                if ($merged && end($merged)[2] === $r[2]) {
                    $merged[count($merged) - 1][1] = $r[1];
                } else {
                    $merged[] = $r;
                }
            }
            $runs = $merged;
        }

        return array_map(fn ($r) => [$r[0], $r[1]], $runs);
    }

    /** Agrège les tronçons d'une sous-portion : état majoritaire, événements couvrant ≥ 50 %. */
    private function aggregateRun(string $h, int $k, int $i0, int $i1, array $piece, array $events): array
    {
        $n = $piece['n'];
        $count = $i1 - $i0 + 1;
        $states = [];
        $volts = [];
        $gcur = [];
        $ghist = [];
        $evCount = [];
        $hist = false;
        $histV = null;
        for ($i = $i0; $i <= $i1; $i++) {
            $c = $piece['chunks'][$i] ?? null;
            if ($c === null) {
                continue;
            }
            if ($c['state'] !== null) {
                $key = $c['state'] ? 'y' : 'n';
                $states[$key][$c['src']] = ($states[$key][$c['src']] ?? 0) + 1;
                if ($c['state'] && $c['voltage']) {
                    $volts[$c['voltage']] = ($volts[$c['voltage']] ?? 0) + 1;
                }
            }
            if ($c['hist']) {
                $hist = true;
                $histV ??= $c['hist_v'];
            }
            if ($c['gcur']) {
                $gk = json_encode($c['gcur']);
                $gcur[$gk] = ($gcur[$gk] ?? 0) + 1;
            }
            if ($c['ghist']) {
                $gk = json_encode($c['ghist']);
                $ghist[$gk] = ($ghist[$gk] ?? 0) + 1;
            }
            foreach ($c['evs'] as $id) {
                $evCount[$id] = ($evCount[$id] ?? 0) + 1;
            }
        }
        $yes = array_sum($states['y'] ?? []);
        $no = array_sum($states['n'] ?? []);
        $state = $yes + $no === 0 ? null : $yes >= $no;
        $srcCounts = $state === null ? [] : $states[$state ? 'y' : 'n'];
        arsort($srcCounts);
        arsort($volts);
        arsort($gcur);
        arsort($ghist);

        // Événements localisés couvrant la majorité de la sous-portion (+ leurs dérivés).
        $runEvents = [];
        foreach ($events as $id => $e) {
            if ($e['scope'] !== 'located') {
                $runEvents[$id] = $e;

                continue;
            }
            $ref = $e['same_as'] ?? $id;
            if (($evCount[$ref] ?? 0) * 2 >= $count) {
                $runEvents[$id] = $e;
            }
        }

        return [
            'h' => $h, 'k' => $k, 'f0' => $i0 / $n, 'f1' => ($i1 + 1) / $n, 'len' => $piece['len'] * $count / $n,
            'ends' => $piece['ends'], 'first' => $i0 === 0, 'last' => $i1 === $n - 1,
            'current' => ['state' => $state, 'voltage_v' => $state ? (array_key_first($volts) ?? null) : null, 'source' => array_key_first($srcCounts)],
            'hist' => $hist, 'hist_v' => $histV,
            'gcur' => $gcur ? json_decode(array_key_first($gcur), true) : null,
            'ghist' => $ghist ? json_decode(array_key_first($ghist), true) : null,
            'events' => $runEvents,
            'has_located' => (bool) array_filter($runEvents, fn ($e) => $e['scope'] === 'located' && $e['kind'] === 'electrification'),
        ];
    }

    /**
     * Portions électrifiées aujourd'hui sans événement localisé : date de la portion
     * électrifiée la plus proche (le long de la ligne) qui en a un, même tension actuelle.
     */
    private function propagateNeighbourDates(array &$runs, array $events, array $pieces): void
    {
        $sources = array_keys(array_filter($runs, fn ($r) => $r['has_located']));
        if (! $sources) {
            return;
        }
        $hasCompletion = (bool) array_filter($events, fn ($e) => $e['scope'] === 'line' && ($e['completion'] ?? false));
        // Graphe : sous-portions consécutives d'une portion + extrémités de portions qui se touchent (≤ 50 m).
        $adj = [];
        $byPiece = [];
        foreach ($runs as $idx => $r) {
            $byPiece[$r['h']][$r['k']] = $idx;
        }
        $ends = [];
        foreach ($byPiece as $h => $ks) {
            ksort($ks);
            $prev = null;
            foreach ($ks as $idx) {
                if ($prev !== null) {
                    $adj[$prev][] = $idx;
                    $adj[$idx][] = $prev;
                }
                $prev = $idx;
            }
            $e = $pieces[$h]['ends'];
            $ends[] = [$e[0], $e[1], reset($ks)];
            $ends[] = [$e[2], $e[3], end($ks)];
        }
        foreach ($ends as $a => [$xa, $ya, $ia]) {
            foreach ($ends as $b => [$xb, $yb, $ib]) {
                if ($b > $a && $ia !== $ib && hypot($xa - $xb, $ya - $yb) <= 50) {
                    $adj[$ia][] = $ib;
                    $adj[$ib][] = $ia;
                }
            }
        }
        // Dijkstra multi-sources (distance = longueur parcourue).
        $dist = [];
        $from = [];
        $queue = new \SplPriorityQueue;
        foreach ($sources as $s) {
            $dist[$s] = 0.0;
            $from[$s] = $s;
            $queue->insert($s, 0);
        }
        while (! $queue->isEmpty()) {
            $u = $queue->extract();
            foreach ($adj[$u] ?? [] as $v) {
                $r = $runs[$v];
                if ($r['has_located'] || $r['current']['state'] !== true) {
                    continue;
                }
                $src = $runs[$from[$u]];
                if ($src['current']['voltage_v'] && $r['current']['voltage_v'] && $src['current']['voltage_v'] !== $r['current']['voltage_v']) {
                    continue;
                }
                $d = $dist[$u] + ($r['len'] / 2) + ($runs[$u]['len'] / 2);
                if ($d < ($dist[$v] ?? INF)) {
                    $dist[$v] = $d;
                    $from[$v] = $from[$u];
                    $queue->insert($v, -$d);
                }
            }
        }
        foreach ($from as $v => $s) {
            if ($v === $s || ($hasCompletion && $dist[$v] > self::NEIGHBOUR_MAX_M)) {
                continue;
            }
            foreach ($runs[$s]['events'] as $id => $e) {
                if ($e['scope'] === 'located') {
                    $runs[$v]['events']['n'.$id] = ['scope' => 'neighbour'] + $e;
                }
            }
        }
    }

    /**
     * Valeurs d'une sous-portion à une ère : [electrified, voltage_v, electrification_source,
     * electrified_year, gauge_mm, gauge_class, gauge_source].
     */
    private function valuesAt(array $run, array $era, string $status, array $lineGauge): array
    {
        $year = $era['current'] ? min($era['year'], (int) date('Y')) : $era['year'];
        $e = $run['timeline']->stateAt($year, $era['current'], $status);
        $g = Gauge::at($year, $run['gcur'], $run['ghist'] ? [$run['ghist']] : [], $lineGauge['line'], $lineGauge['conversions'], $lineGauge['network']);

        return [$e['electrified'], $e['voltage_v'], $e['source'], $e['year'], $g['mm'], $g['class'], $g['source']];
    }

    private function write(array $updates, array $inserts, array $splitIds): void
    {
        $pg = fn (array $vals) => '{'.implode(',', array_map(fn ($v) => $v === null ? 'NULL' : (is_bool($v) ? ($v ? 't' : 'f') : '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], (string) $v).'"'), $vals)).'}';
        foreach (array_chunk($updates, 2000) as $chunk) {
            $cols = $this->transpose($chunk);
            DB::update(<<<'SQL'
                UPDATE line_segments s SET electrified = r.el, voltage_v = r.v, electrification_source = r.es, electrified_year = r.ey,
                       gauge_mm = r.gm, gauge_class = r.gc, gauge_source = r.gs
                FROM unnest(CAST(? AS bigint[]), CAST(? AS boolean[]), CAST(? AS int[]), CAST(? AS text[]), CAST(? AS smallint[]),
                            CAST(? AS smallint[]), CAST(? AS text[]), CAST(? AS text[])) AS r(id, el, v, es, ey, gm, gc, gs)
                WHERE s.id = r.id
                  AND (s.electrified, s.voltage_v, s.electrification_source, s.electrified_year, s.gauge_mm, s.gauge_class, s.gauge_source)
                      IS DISTINCT FROM (r.el, r.v, r.es, r.ey, r.gm, r.gc, r.gs)
                SQL, array_map($pg, $cols));
            $this->stats['updated_rows'] += count($chunk);
        }
        if (! $inserts) {
            return;
        }
        $copy = implode(', ', array_map(fn ($c) => '"'.$c.'"', $this->copyColumns));
        $copyS = implode(', ', array_map(fn ($c) => 's."'.$c.'"', $this->copyColumns));
        foreach (array_chunk($inserts, 2000) as $chunk) {
            $cols = $this->transpose($chunk);
            DB::insert(<<<SQL
                INSERT INTO line_segments ({$copy}, geom, length_meters, electrified, voltage_v, electrification_source, electrified_year,
                                           gauge_mm, gauge_class, gauge_source, created_at, updated_at)
                SELECT {$copyS}, sub.g, ROUND(ST_Length(sub.g::geography))::int, r.el, r.v, r.es, r.ey, r.gm, r.gc, r.gs, NOW(), NOW()
                FROM unnest(CAST(? AS bigint[]), CAST(? AS float8[]), CAST(? AS float8[]), CAST(? AS boolean[]), CAST(? AS int[]),
                            CAST(? AS text[]), CAST(? AS smallint[]), CAST(? AS smallint[]), CAST(? AS text[]), CAST(? AS text[]))
                         AS r(id, f0, f1, el, v, es, ey, gm, gc, gs)
                JOIN line_segments s ON s.id = r.id,
                     LATERAL (SELECT ST_Transform(ST_LineSubstring(ST_Transform(s.geom, 2154), r.f0, LEAST(1.0, r.f1)), 4326) AS g) sub
                WHERE GeometryType(sub.g) = 'LINESTRING' AND ST_NPoints(sub.g) >= 2
                SQL, array_map($pg, $cols));
            $this->stats['new_rows'] += count($chunk);
        }
        DB::delete('DELETE FROM line_segments WHERE id = ANY(CAST(? AS bigint[]))', ['{'.implode(',', array_unique($splitIds)).'}']);
    }

    /** Lignes → colonnes (array_map(null, …) ne transpose pas un tableau d'une seule ligne). */
    private function transpose(array $rows): array
    {
        $cols = [];
        foreach ($rows as $row) {
            foreach (array_values($row) as $j => $v) {
                $cols[$j][] = $v;
            }
        }

        return $cols;
    }

    /** Bilan (portions en service) : km électrifiés par ère (et par tension), km par classe d'écartement. */
    private function report(): void
    {
        $rows = DB::select(<<<'SQL'
            SELECT e.year,
                   ROUND(SUM(ls.length_meters) FILTER (WHERE ls.status IN ('in_service', 'single_track')) / 1000.0) AS km,
                   ROUND(SUM(ls.length_meters) FILTER (WHERE ls.electrified AND ls.status IN ('in_service', 'single_track')) / 1000.0) AS el,
                   ROUND(SUM(ls.length_meters) FILTER (WHERE ls.electrified AND ls.voltage_v = 1500 AND ls.status IN ('in_service', 'single_track')) / 1000.0) AS v1500,
                   ROUND(SUM(ls.length_meters) FILTER (WHERE ls.electrified AND ls.voltage_v = 25000 AND ls.status IN ('in_service', 'single_track')) / 1000.0) AS v25k,
                   ROUND(SUM(ls.length_meters) FILTER (WHERE ls.electrified AND ls.voltage_v < 1500 AND ls.status IN ('in_service', 'single_track')) / 1000.0) AS low,
                   ROUND(SUM(ls.length_meters) FILTER (WHERE ls.electrified AND ls.voltage_v NOT IN (1500, 25000) AND ls.voltage_v >= 3000 AND ls.status IN ('in_service', 'single_track')) / 1000.0) AS other,
                   ROUND(SUM(ls.length_meters) FILTER (WHERE ls.electrified AND ls.voltage_v IS NULL AND ls.status IN ('in_service', 'single_track')) / 1000.0) AS unk_v,
                   ROUND(SUM(ls.length_meters) FILTER (WHERE ls.electrification_source = 'unknown' AND ls.status IN ('in_service', 'single_track')) / 1000.0) AS unk_date,
                   ROUND(SUM(ls.length_meters) FILTER (WHERE ls.gauge_class = 'standard' AND ls.status IN ('in_service', 'single_track')) / 1000.0) AS g_std,
                   ROUND(SUM(ls.length_meters) FILTER (WHERE ls.gauge_class = 'metre' AND ls.status IN ('in_service', 'single_track')) / 1000.0) AS g_m,
                   ROUND(SUM(ls.length_meters) FILTER (WHERE ls.gauge_class = 'narrow' AND ls.status IN ('in_service', 'single_track')) / 1000.0) AS g_n,
                   ROUND(SUM(ls.length_meters) FILTER (WHERE ls.gauge_class IS NULL AND ls.status IN ('in_service', 'single_track')) / 1000.0) AS g_unk
            FROM line_segments ls JOIN eras e ON e.id = ls.era_id
            GROUP BY e.year ORDER BY e.year
            SQL);
        $this->table(
            ['Ère', 'km en service', 'électrifiés', '1500 V', '25 kV', '< 1500 V', 'autres', 'tension ?', 'date ?', 'normale', 'métrique', 'étroite', 'écart. ?'],
            array_map(fn ($r) => array_map(fn ($v) => $v ?? 0, (array) $r), $rows),
        );
    }
}
