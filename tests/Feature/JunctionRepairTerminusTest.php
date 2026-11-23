<?php

namespace Tests\Feature;

use App\Models\Line;
use App\Models\Station;
use App\Services\JunctionRepair;
use App\Services\TraceConflation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Règle 10 de JunctionRepair : bout du tracé de référence arrêté avant sa gare terminus, prolongé jusqu'à elle le long
 * de la trace source (option extendToStations, allumée par défaut ; jamais le long d'une forme SNCF Réseau, généralisée).
 *
 * Extraits réels (tests/Fixtures/Terminus/*.json, lus en lecture seule sur vff le 22/11/2026 par
 * storage/app/reports/agents/aprem-2111/traces-jusqu-gare/sql/extrait.sql) : fenêtre de 300 m autour du segment bout -
 * gare, tracés de référence de toutes les lignes, traces sources (Lambert-93, au centimètre ; sans les traces
 * archeoferroviaire ni les morceaux de référence qui en gardent la géométrie brute), gare et lignes. Sources et licences :
 * tests/Fixtures/SOURCES.md.
 * Réseaux fabriqués en Lambert-93 autour de (650 000, 6 860 000) pour les cas limites.
 */
class JunctionRepairTerminusTest extends TestCase
{
    use RefreshDatabase;

    private const X0 = 650000;

    private const Y0 = 6860000;

    private Line $line;

    /** @var array{0: float, 1: float} */
    private array $station;

    /** @var array{0: float, 1: float} */
    private array $end;

    private function wkt(array $pts): string
    {
        return 'LINESTRING('.implode(',', array_map(fn ($p) => $p[0].' '.$p[1], $pts)).')';
    }

    private function trace(?int $lineId, string $source, ?string $layer, array $pts, array $props = [], ?float $score = null): void
    {
        DB::table('source_traces')->insert([
            'source' => $source, 'source_ref' => $source.'-'.uniqid('', true), 'layer' => $layer, 'line_id' => $lineId,
            'properties' => $props ? json_encode($props) : null, 'match_score' => $score,
            'seen_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            'geom' => DB::raw("ST_Multi(ST_Transform(ST_GeomFromText('".$this->wkt($pts)."', 2154), 4326))"),
        ]);
    }

    private function part(int $lineId, string $source, array $pts, ?string $matched = null): void
    {
        DB::table('line_reference_parts')->insert([
            'line_id' => $lineId, 'source' => $source, 'source_trace_ids' => '[]', 'matched_source' => $matched,
            'length_m' => 0, 'created_at' => now(), 'updated_at' => now(),
            'geom' => DB::raw("ST_Multi(ST_Transform(ST_GeomFromText('".$this->wkt($pts)."', 2154), 4326))"),
        ]);
    }

    private function station(int $lineId, string $name, array $xy): void
    {
        $id = Station::factory()->create(['name' => $name])->id;
        DB::update('UPDATE stations SET geom = ST_Transform(ST_SetSRID(ST_MakePoint(?, ?), 2154), 4326) WHERE id = ?', [$xy[0], $xy[1], $id]);
        DB::table('station_lines')->insert(['station_id' => $id, 'line_id' => $lineId, 'method' => 'wikidata_p81', 'created_at' => now(), 'updated_at' => now()]);
        $this->station = $xy;
    }

    /** Charge un extrait réel ; la ligne étudiée devient $this->line. */
    private function extract(string $name): void
    {
        $d = json_decode((string) file_get_contents(base_path("tests/Fixtures/Terminus/{$name}.json")), true);
        $ids = [];
        foreach ($d['lines'] as $l) {
            $line = Line::factory()->create(['code' => $l['code'] ?? 'x'.$l['id'], 'gauge_mm_default' => $l['gauge'], 'network' => $l['network']]);
            $ids[$l['id']] = $line->id;
            if ($l['id'] === $d['line']) {
                $this->line = $line;
            }
        }
        foreach ($d['parts'] as $p) {
            $this->part($ids[$p['line']], $p['source'], $p['pts'], $p['matched']);
        }
        foreach ($d['traces'] as $t) {
            $this->trace($t['line'] !== null ? $ids[$t['line']] : null, $t['source'], $t['layer'], $t['pts'], $t['props'] ?: [], $t['score']);
        }
        $this->station($this->line->id, $d['station']['name'], $d['station']['xy']);
        $this->end = $d['end'];
    }

    /** Décisions de la règle 10 au dernier passage (journal de diagnostic). */
    private array $decisions = [];

    private function repair(bool $extend = true): array
    {
        (new TraceConflation)->preparePool();
        $r = new JunctionRepair;
        $r->extendToStations = $extend;
        $r->debug = true;
        $out = $r->repair($this->line->id);
        $this->decisions = array_values(array_filter($r->log, fn ($e) => isset($e['terminus'])));
        if (getenv('JR_DEBUG')) {
            fwrite(STDERR, json_encode($this->decisions)."\n");
        }

        return $out;
    }

    /** Raisons des refus de la règle 10 au dernier passage. */
    private function refusals(): array
    {
        return array_values(array_filter(array_column($this->decisions, 'skip')));
    }

    /** Distance (m) du point au dessin d'une ligne (la ligne étudiée par défaut). */
    private function distToDrawing(array $p, ?int $lineId = null): float
    {
        return (float) DB::selectOne(<<<'SQL'
            SELECT ST_Distance(ST_SetSRID(ST_MakePoint(?, ?), 2154), ST_Collect(ST_Transform(geom, 2154))) AS d FROM line_reference_parts WHERE line_id = ?
            SQL, [$p[0], $p[1], $lineId ?? $this->line->id])->d;
    }

    /** Distance (m) du point au dessin des autres lignes. */
    private function distToOthers(array $p): float
    {
        return (float) DB::selectOne(<<<'SQL'
            SELECT ST_Distance(ST_SetSRID(ST_MakePoint(?, ?), 2154), ST_Collect(ST_Transform(geom, 2154))) AS d FROM line_reference_parts WHERE line_id <> ?
            SQL, [$p[0], $p[1], $this->line->id])->d;
    }

    /** Partie ajoutée par la règle (dernier morceau inséré de la ligne) : source, provenance, polyligne Lambert-93. */
    private function added(): object
    {
        return DB::selectOne(<<<'SQL'
            SELECT source, matched_source, source_trace_ids, ST_AsText(ST_Transform(ST_GeometryN(geom, 1), 2154)) AS wkt, ST_Length(ST_Transform(geom, 2154)) AS len
            FROM line_reference_parts WHERE line_id = ? ORDER BY id DESC LIMIT 1
            SQL, [$this->line->id]);
    }

    // ─── Extraits réels ──────────────────────────────────────────────────

    public function test_gannat_end_already_on_the_lines_it_runs_into_is_left(): void
    {
        // 707000 finit à 0,4 m du dessin de 789000 et 790000, qui portent les 439 derniers mètres jusqu'à Gannat (tronc
        // commun). Sur vff, seule la trace archeo du calque « exploitee » (fond de contexte, hors du pool de recalage,
        // absente de l'extrait) va jusqu'à la gare ; le prolongement doublerait de toute façon ces lignes.
        $this->extract('gannat');
        $before = $this->distToDrawing($this->station);

        $this->assertSame(0, $this->repair()['terminus_extended']);
        $this->assertContains('bout déjà sur une autre ligne', $this->refusals());
        $this->assertEqualsWithDelta($before, $this->distToDrawing($this->station), 0.5);
        $this->assertLessThan(0.5, $this->distToOthers($this->end));
    }

    public function test_commentry_end_already_on_the_line_it_runs_into_is_left(): void
    {
        // Autre bout de 707000 : posé sur le dessin de 705000 (0 m), qui mène à Commentry (632 m).
        $this->extract('commentry');
        $before = $this->distToDrawing($this->station);

        $this->assertSame(0, $this->repair()['terminus_extended']);
        $this->assertContains('bout déjà sur une autre ligne', $this->refusals());
        $this->assertEqualsWithDelta($before, $this->distToDrawing($this->station), 0.5);
        $this->assertLessThan(0.5, $this->distToOthers($this->end));
    }

    public function test_neuf_brisach_branch_running_on_the_main_line_track_is_left(): void
    {
        // L'embranchement de Volgelsheim part de 120000 et 123000 à 462 m de la gare ; sa trace déclarée passe par la gare
        // (0 m), mais sur la voie que ces lignes dessinent déjà (0,2 m) : rien à ajouter.
        $this->extract('neuf-brisach');
        $before = $this->distToDrawing($this->station);

        $this->assertSame(0, $this->repair()['terminus_extended']);
        $this->assertContains('bout déjà sur une autre ligne', $this->refusals());
        $this->assertEqualsWithDelta($before, $this->distToDrawing($this->station), 0.5);
    }

    public function test_vierzon_593000_end_without_a_source_trace_reaching_the_station_is_left(): void
    {
        // 593000 s'arrête à 207 m de Vierzon-Ville ; aucune trace rattachée à la ligne ne passe à moins de 15 m de la gare
        // (SNCF 183 m, OSM 208 m ; sur vff, la trace archeo à 25 m est rattachée à une autre ligne).
        $this->extract('vierzon-593000');
        $before = $this->distToDrawing($this->station);

        $this->assertSame(0, $this->repair()['terminus_extended']);
        $this->assertContains('aucune gare terminus que la trace source atteint', $this->refusals());
        $this->assertNotContains(null, array_column($this->decisions, 'skip'));
        $this->assertEqualsWithDelta($before, $this->distToDrawing($this->station), 0.5);
    }

    public function test_marseille_mourepiane_end_is_extended_on_the_precise_tracks_along_the_sncf_trace(): void
    {
        // 934100 s'arrête à 146 m de Marseille-Maritime-Mourepiane ; la trace SNCF de la ligne passe à la gare (0 m) et
        // au bout (1,7 m), et des voies précises la suivent jusqu'à la gare.
        $this->extract('marseille-mourepiane');

        $stats = $this->repair();

        $this->assertSame(1, $stats['terminus_extended']);
        $this->assertSame(0, $stats['terminus_rough']);
        $this->assertLessThan(3, $this->distToDrawing($this->station));
        $this->assertContains($this->added()->source, ['bdtopo', 'osm']);
        $this->assertLessThan(200, $stats['terminus_extended_m']);
    }

    public function test_esbly_end_is_not_extended_along_the_generalised_sncf_trace(): void
    {
        // 071000 s'arrête à 58 m d'Esbly ; seule la trace SNCF de la ligne y va (4 m de la gare, 3,7 m du bout), et aucune
        // voie précise ne la suit. Forme SNCF Réseau généralisée (jusqu'à 165 m d'écart mesurés) : jamais suivie.
        $this->extract('esbly');
        $before = $this->distToDrawing($this->station);
        $parts = DB::table('line_reference_parts')->where('line_id', $this->line->id)->count();

        $stats = $this->repair();

        $this->assertSame(0, $stats['terminus_extended']);
        $this->assertSame(0, $stats['terminus_rough']);
        $this->assertContains('pas de chemin le long de la trace source', $this->refusals());
        $this->assertSame($parts, DB::table('line_reference_parts')->where('line_id', $this->line->id)->count());
        $this->assertEqualsWithDelta($before, $this->distToDrawing($this->station), 0.5);
    }

    public function test_chateaubriant_end_is_not_extended_along_the_generalised_sncf_trace(): void
    {
        // 519000 (tram-train) s'arrête à 42 m de sa gare ; seule la trace SNCF mène vers la gare : rien n'est ajouté.
        $this->extract('chateaubriant');
        $before = $this->distToDrawing($this->station);

        $this->assertSame(0, $this->repair()['terminus_extended']);
        $this->assertContains('pas de chemin le long de la trace source', $this->refusals());
        $this->assertEqualsWithDelta($before, $this->distToDrawing($this->station), 0.5);
    }

    public function test_generalised_sncf_trace_is_never_followed_without_precise_track(): void
    {
        // Même réseau que le prolongement grossier, mais la seule trace qui mène à la gare est une forme SNCF Réseau.
        $this->terminusNetwork(source: [[950, 1], [1100, 1], [1250, 12], [1400, 41]], family: 'sncf');

        $this->assertSame(0, $this->repair()['terminus_extended']);
        $this->assertContains('pas de chemin le long de la trace source', $this->refusals());
        $this->assertEqualsWithDelta(404.5, $this->distToDrawing($this->station), 0.5);
    }

    public function test_mont_de_marsan_extension_alongside_another_line_from_the_end_is_refused(): void
    {
        // 654000 s'arrête à 31 m de Mont-de-Marsan, à moins de 2 m du dessin d'une autre ligne qui va à la gare : le
        // prolongement la doublerait.
        $this->extract('mont-de-marsan');
        $before = $this->distToDrawing($this->station);

        $this->assertSame(0, $this->repair()['terminus_extended']);
        $this->assertContains('doublerait une autre ligne dès le bout', $this->refusals());
        $this->assertEqualsWithDelta($before, $this->distToDrawing($this->station), 0.5);
    }

    // ─── Réseaux fabriqués ───────────────────────────────────────────────

    /** Point relatif (m) au repère des réseaux fabriqués. */
    private function at(float $x, float $y): array
    {
        return [self::X0 + $x, self::Y0 + $y];
    }

    /** @param list<array{0: float, 1: float}> $pts points relatifs */
    private function rel(array $pts): array
    {
        return array_map(fn ($p) => $this->at($p[0], $p[1]), $pts);
    }

    /**
     * Ligne dessinée sur la BD TOPO de (0, 0) à (1000, 0), gare à $station ; trace source de la ligne ($source, points
     * relatifs) qui passe par le bout. Sans trace : seulement le dessin et la gare.
     */
    private function terminusNetwork(array $station = [1402, 45], ?array $source = [[950, 0], [1100, 0], [1250, 10], [1400, 40]], string $family = 'osm'): void
    {
        $this->line = Line::factory()->create(['code' => '420000', 'gauge_mm_default' => 1435, 'network' => 'principal']);
        $this->trace($this->line->id, 'bdtopo', 'Voie ferrée principale', $this->rel([[0, 0], [1000, 0]]));
        $this->part($this->line->id, 'bdtopo', $this->rel([[0, 0], [1000, 0]]));
        if ($source !== null) {
            $this->trace($this->line->id, $family, ['osm' => 'rail', 'bdtopo' => 'Voie ferrée principale', 'archeoferroviaire' => 'principal', 'ohm' => 'rail', 'sncf' => null][$family], $this->rel($source));
        }
        $this->station($this->line->id, 'Gare d\'essai', $this->at(...$station));
        $this->end = $this->at(1000, 0);
    }

    /** Écart max (m) des sommets de la partie ajoutée à la polyligne $pts (relative). */
    private function addedOffsetFrom(array $pts): float
    {
        return (float) DB::selectOne(<<<'SQL'
            SELECT MAX(ST_Distance(v.geom, ST_GeomFromText(?, 2154))) AS d FROM ST_DumpPoints(ST_GeomFromText(?, 2154)) v
            SQL, [$this->wkt($this->rel($pts)), $this->added()->wkt])->d;
    }

    public function test_end_short_of_its_terminus_is_extended_along_the_precise_source_track(): void
    {
        $this->terminusNetwork();

        $stats = $this->repair();

        $this->assertSame(1, $stats['terminus_extended']);
        $this->assertSame(0, $stats['terminus_rough']);
        // Jusqu'au point de la voie le plus proche de la gare (5 m), le long de la voie OSM (pas une droite).
        $this->assertLessThan(6, $this->distToDrawing($this->station));
        $this->assertLessThan(0.01, $this->addedOffsetFrom([[950, 0], [1100, 0], [1250, 10], [1400, 40]]));
        $this->assertGreaterThan(400, $this->added()->len);
        $added = $this->added();
        $this->assertSame('osm', $added->source);
        $this->assertNull($added->matched_source);
        $this->assertSame(0, $this->repair()['terminus_extended'], 'idempotent');
    }

    public function test_rule_is_on_by_default_and_can_be_turned_off(): void
    {
        $this->assertTrue((new JunctionRepair)->extendToStations, 'un recalcul sans option garde les prolongements');
        $this->terminusNetwork();

        $this->assertSame(0, $this->repair(extend: false)['terminus_extended']);
        $this->assertEqualsWithDelta(404.5, $this->distToDrawing($this->station), 0.5);
    }

    public function test_end_is_extended_along_a_rough_source_trace_when_no_precise_track_follows_it(): void
    {
        $this->terminusNetwork(source: [[950, 1], [1100, 1], [1250, 12], [1400, 41]], family: 'archeoferroviaire');

        $stats = $this->repair();

        $this->assertSame(1, $stats['terminus_extended']);
        $this->assertSame(1, $stats['terminus_rough']);
        $this->assertLessThan(6, $this->distToDrawing($this->station));
        $added = $this->added();
        // Tracé grossier gardé pour ce qu'il est : source archeo, aucun recalage annoncé.
        $this->assertSame('archeoferroviaire', $added->source);
        $this->assertNull($added->matched_source);
        $this->assertLessThan(1.5, $this->addedOffsetFrom([[950, 1], [1100, 1], [1250, 12], [1400, 41]]));
    }

    public function test_extension_stops_on_another_line_drawing_it_runs_into(): void
    {
        $this->terminusNetwork();
        // Autre ligne qui arrive de l'ouest en biais doux sur la voie de la gare et la dessine jusqu'à la gare et au-delà.
        $m = Line::factory()->create(['code' => '421000', 'gauge_mm_default' => 1435]);
        $this->part($m->id, 'bdtopo', $this->rel([[900, -80], [1100, -20], [1250, 10], [1400, 40], [1700, 100]]));

        $stats = $this->repair();

        $this->assertSame(1, $stats['terminus_extended']);
        // Arrêt au contact, posé sur le dessin de l'autre ligne (jonction à 0 m), sans doubler celle-ci jusqu'à la gare.
        $end = DB::selectOne('SELECT ST_X(p) AS x, ST_Y(p) AS y FROM (SELECT ST_EndPoint(ST_GeomFromText(?, 2154)) AS p) z', [$this->added()->wkt]);
        $this->assertLessThan(0.01, $this->distToDrawing([(float) $end->x, (float) $end->y], $m->id));
        $this->assertLessThan(260, $this->added()->len);
        $this->assertGreaterThan(100, $this->distToDrawing($this->station));
    }

    public function test_extension_that_would_double_another_line_from_the_end_is_refused(): void
    {
        $this->terminusNetwork();
        // Autre ligne dessinée à 1,5 m le long de la voie, depuis le bout jusqu'à la gare : le prolongement la doublerait.
        $m = Line::factory()->create(['code' => '421000', 'gauge_mm_default' => 1435]);
        $this->part($m->id, 'bdtopo', $this->rel([[1000, 1.5], [1100, 1.5], [1250, 11.5], [1400, 41.5], [1600, 81.5]]));

        $this->assertSame(0, $this->repair()['terminus_extended']);
        $this->assertContains('doublerait une autre ligne dès le bout', $this->refusals());
        $this->assertEqualsWithDelta(404.5, $this->distToDrawing($this->station), 0.5);
    }

    public function test_source_passing_more_than_15_m_from_the_station_is_not_followed(): void
    {
        $this->terminusNetwork(station: [1400, 60]);

        $this->assertSame(0, $this->repair()['terminus_extended']);
        $this->assertSame(['aucune gare terminus que la trace source atteint'], array_unique($this->refusals()));
    }

    public function test_source_passing_more_than_5_m_from_the_end_is_not_followed(): void
    {
        // Trace de la ligne à 12 m du bout : ce n'est pas le prolongement de ce bout.
        $this->terminusNetwork(source: [[950, 12], [1100, 12], [1250, 22], [1400, 45]], family: 'archeoferroviaire');

        $this->assertSame(0, $this->repair()['terminus_extended']);
        $this->assertContains('pas de chemin le long de la trace source', $this->refusals());
    }

    public function test_station_more_than_2_km_beyond_the_end_is_not_reached(): void
    {
        $this->terminusNetwork(station: [3300, 0], source: [[950, 0], [3400, 0]]);

        $this->assertSame(0, $this->repair()['terminus_extended']);
        $this->assertSame(['aucune gare terminus que la trace source atteint'], array_unique($this->refusals()));
        $this->assertEqualsWithDelta(2300, $this->distToDrawing($this->station), 0.5);
    }

    public function test_extension_crossing_another_track_is_refused(): void
    {
        $this->terminusNetwork();
        // Voie BD TOPO rattachée à aucune ligne, en travers (traversée à niveau) : le prolongement la croiserait.
        $this->trace(null, 'bdtopo', 'Voie ferrée principale', $this->rel([[1200, -200], [1200, 200]]));

        $this->assertSame(0, $this->repair()['terminus_extended']);
        $this->assertContains('croise une voie', $this->refusals());
    }
}
