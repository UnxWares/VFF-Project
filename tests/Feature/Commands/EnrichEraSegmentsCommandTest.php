<?php

namespace Tests\Feature\Commands;

use App\Models\Era;
use App\Models\Line;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * vff:enrich-era-segments : électrification (tension, date, provenance) et
 * écartement de chaque portion à chaque ère, découpe des portions
 * hétérogènes, options de sélection et bilan.
 *
 * Repères : à 46° N, 0,1° de longitude ≈ 7,7 km.
 */
class EnrichEraSegmentsCommandTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    private Era $e1910;

    private Era $e1930;

    private Era $e2030;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
        $this->e1910 = $this->era(1910);
        $this->e1930 = $this->era(1930);
        $this->e2030 = $this->era(2030, current: true);
    }

    // ─── utilitaires ─────────────────────────────────────────────────────

    /** Ligne avec un tronçon par ère, même géométrie. */
    private function lineWithSegments(string $code, string $name, float $lat, array $attributes = [], string $status = 'in_service'): Line
    {
        $line = $this->rfnLine($code, $name, $attributes + ['description' => null, 'gauge_mm_default' => null]);
        foreach ([$this->e1910, $this->e1930, $this->e2030] as $era) {
            $this->track($line, $era, [[$lat, 2.0], [$lat, 2.1]], ['status' => $status, 'electrified' => false, 'gauge_mm' => null,
                'electrification_source' => null, 'length_meters' => 7730]);
        }

        return $line;
    }

    private function sncfElec(string $code, int $voltage, string $wkt): void
    {
        DB::insert("INSERT INTO sncf_line_electrification (code_ligne, elect, voltage_v, third_rail, geom, created_at, updated_at)
            VALUES (?, 'Électrifiée', ?, false, ST_Multi(ST_GeomFromText(?, 4326)), now(), now())", [$code, $voltage, $wkt]);
    }

    private function sncfShape(string $code, string $wkt): void
    {
        DB::insert('INSERT INTO sncf_line_geometries (code_ligne, libelle, geom, created_at, updated_at)
            VALUES (?, ?, ST_GeomFromText(?, 4326), now(), now())', [$code, 'Ligne '.$code, $wkt]);
    }

    private function elecEvent(Line $line, string $date, string $text, ?string $wkt = null): int
    {
        $id = DB::table('line_events')->insertGetId([
            'line_id' => $line->id, 'type' => 'electrification', 'event_date' => $date, 'description' => $text,
            'source' => 'wikipedia', 'confidence' => 'high', 'date_precision' => 'day',
            'section_resolution' => $wkt ? 'places' : 'whole_line', 'created_at' => now(), 'updated_at' => now(),
        ]);
        if ($wkt) {
            DB::update('UPDATE line_events SET section_geom = ST_Multi(ST_GeomFromText(?, 4326)) WHERE id = ?', [$wkt, $id]);
        }

        return $id;
    }

    /** @param  array<string, mixed>  $properties */
    private function trace(string $source, string $ref, string $wkt, array $properties, string $layer = 'rail', ?string $name = null, ?int $lineId = null): void
    {
        DB::insert('INSERT INTO source_traces (source, source_ref, layer, name, line_id, properties, length_m, seen_at, geom)
            VALUES (?, ?, ?, ?, ?, ?::jsonb, 1000, now(), ST_Multi(ST_GeomFromText(?, 4326)))',
            [$source, $ref, $layer, $name, $lineId, json_encode($properties), $wkt]);
    }

    /** @return list<object> tronçons de la ligne à une ère, d'ouest en est */
    private function segments(Line $line, Era $era): array
    {
        return DB::select('SELECT id, electrified, voltage_v, electrification_source, electrified_year, gauge_mm, gauge_class, gauge_source,
                length_meters, ST_X(ST_StartPoint(geom)) AS x0, ST_X(ST_EndPoint(geom)) AS x1
            FROM line_segments WHERE line_id = ? AND era_id = ? ORDER BY ST_X(ST_StartPoint(geom))', [$line->id, $era->id]);
    }

    private function one(Line $line, Era $era): object
    {
        $s = $this->segments($line, $era);
        $this->assertCount(1, $s);

        return $s[0];
    }

    // ─── électrification ─────────────────────────────────────────────────

    public function test_whole_line_electrification_is_replayed_per_era(): void
    {
        $line = $this->lineWithSegments('830000', 'Ligne de Paris à Lyon', 46.0);
        $this->sncfElec('830000', 1500, 'LINESTRING(2.0 46.0, 2.1 46.0)');
        $this->sncfShape('830000', 'LINESTRING(2.0 46.0, 2.1 46.0)');
        $this->elecEvent($line, '1925-05-01', 'Électrification en 1500 V continu de toute la ligne');

        $this->artisan('vff:enrich-era-segments')
            ->expectsOutputToContain('  1 lignes, 1 portions, 0 découpées (0 segments créés), 3 segments mis à jour')
            ->assertSuccessful();

        $old = $this->one($line, $this->e1910);
        $this->assertFalse($old->electrified, 'avant 1925');
        $this->assertNull($old->voltage_v);
        $mid = $this->one($line, $this->e1930);
        $this->assertSame([true, 1500, 1925], [$mid->electrified, $mid->voltage_v, $mid->electrified_year]);
        $now = $this->one($line, $this->e2030);
        $this->assertSame([true, 1500, 'sncf'], [$now->electrified, $now->voltage_v, $now->electrification_source]);
        foreach ([$old, $mid, $now] as $s) {
            $this->assertSame([1435, 'standard'], [$s->gauge_mm, $s->gauge_class], 'réseau principal : voie normale');
        }
    }

    public function test_heterogeneous_portion_is_split_and_undated_part_is_unknown(): void
    {
        // Électrifiée sur la moitié ouest seulement (liste SNCF), pas d'événement : date inconnue.
        $line = $this->lineWithSegments('831000', 'Ligne de Moret à Montargis', 46.0);
        $this->sncfElec('831000', 25000, 'LINESTRING(2.0 46.0, 2.05 46.0)');
        $this->sncfShape('831000', 'LINESTRING(2.0 46.0, 2.1 46.0)');

        $this->artisan('vff:enrich-era-segments', ['--line' => '831000'])
            ->expectsOutputToContain('  1 lignes, 1 portions, 1 découpées (6 segments créés), 0 segments mis à jour')
            ->doesntExpectOutputToContain('Ère')
            ->assertSuccessful();

        $now = $this->segments($line, $this->e2030);
        $this->assertCount(2, $now);
        $this->assertSame([true, 25000, 'sncf'], [$now[0]->electrified, $now[0]->voltage_v, $now[0]->electrification_source]);
        $this->assertSame([false, null], [$now[1]->electrified, $now[1]->voltage_v]);
        $this->assertEqualsWithDelta(2.0, $now[0]->x0, 1e-6);
        $this->assertEqualsWithDelta($now[0]->x1, $now[1]->x0, 1e-6, 'sous-portions contiguës');
        $this->assertEqualsWithDelta(2.1, $now[1]->x1, 1e-6);
        $this->assertEqualsWithDelta(7730, $now[0]->length_meters + $now[1]->length_meters, 40);
        $this->assertSame(6, DB::table('line_segments')->where('line_id', $line->id)->count(), 'chaque ère découpée, originaux supprimés');

        // Rejouable : les sous-portions sont désormais homogènes, rien ne change.
        $before = $this->snapshot(['line_segments'], ['id']);
        $this->artisan('vff:enrich-era-segments', ['--line' => (string) $line->id])
            ->expectsOutputToContain('  1 lignes, 2 portions, 0 découpées (0 segments créés), 6 segments mis à jour')
            ->assertSuccessful();
        $this->assertSame($before, $this->snapshot(['line_segments'], ['id']));
    }

    public function test_no_split_keeps_the_majority_value(): void
    {
        $line = $this->lineWithSegments('831000', 'Ligne de Moret à Montargis', 46.0);
        $this->sncfElec('831000', 25000, 'LINESTRING(2.0 46.0, 2.07 46.0)');
        $this->sncfShape('831000', 'LINESTRING(2.0 46.0, 2.1 46.0)');

        $this->artisan('vff:enrich-era-segments', ['--no-split' => true])->assertSuccessful();

        $now = $this->one($line, $this->e2030);
        $this->assertSame([true, 25000], [$now->electrified, $now->voltage_v], '70 % électrifiés');
    }

    public function test_located_event_dates_its_portion_and_its_neighbour(): void
    {
        // Deux portions contiguës électrifiées aujourd'hui ; seul l'ouest a un événement localisé.
        $line = $this->rfnLine('832000', 'Ligne de Culoz à Modane', ['description' => null]);
        foreach ([$this->e1930, $this->e2030] as $era) {
            $this->track($line, $era, [[46.0, 2.0], [46.0, 2.05]], ['electrified' => false]);
            $this->track($line, $era, [[46.0, 2.05], [46.0, 2.1]], ['electrified' => false]);
        }
        $this->sncfElec('832000', 1500, 'LINESTRING(2.0 46.0, 2.1 46.0)');
        $this->sncfShape('832000', 'LINESTRING(2.0 46.0, 2.1 46.0)');
        $this->elecEvent($line, '1925-01-01', 'Mise sous tension 1500 V', 'LINESTRING(2.0 46.0, 2.05 46.0)');

        $this->artisan('vff:enrich-era-segments')->assertSuccessful();

        $old = $this->segments($line, $this->e1930);
        $this->assertCount(2, $old);
        $this->assertSame([true, 1925], [$old[0]->electrified, $old[0]->electrified_year]);
        $this->assertSame([true, 1925], [$old[1]->electrified, $old[1]->electrified_year], 'date de la portion voisine');
    }

    public function test_old_alignment_without_any_evidence_is_not_electrified(): void
    {
        $line = $this->lineWithSegments('833000', 'Ligne de Vic à Polminhac', 46.0, [], 'abandoned');
        $this->trace('ohm', 'r1', 'LINESTRING(2.0 46.0, 2.1 46.0)', ['gauge' => '1000', 'start_date' => '1905', 'end_date' => '1950'], 'abandoned', null, $line->id);

        $this->artisan('vff:enrich-era-segments')
            ->expectsOutputToContain('sncf_line_electrification est vide')
            ->assertSuccessful();

        $old = $this->one($line, $this->e1930);
        $this->assertFalse($old->electrified);
        $this->assertSame([1000, 'metre', 'ohm'], [$old->gauge_mm, $old->gauge_class, $old->gauge_source], 'écartement OHM daté');
    }

    // ─── écartement ──────────────────────────────────────────────────────

    public function test_gauge_comes_from_osm_bdtopo_archeo_or_line(): void
    {
        $osm = $this->lineWithSegments('840000', 'Ligne de Saint-Gervais à Vallorcine', 46.0, ['network' => 'secondaire']);
        $this->trace('osm', '1', 'LINESTRING(2.0 46.0, 2.1 46.0)', ['gauge' => '1000', 'electrified' => 'rail', 'voltage' => '850']);

        $bdt = $this->lineWithSegments('841000', 'Ligne de Blanc-Argent', 46.5, ['network' => 'secondaire']);
        $this->trace('bdtopo', 'b1', 'LINESTRING(2.0 46.5, 2.1 46.5)', ['largeur' => 'Etroite', 'electrifie' => 'false', 'etat_de_l_objet' => 'En service'], 'Voie ferrée principale');

        $arch = $this->lineWithSegments('842000', 'Tramway de Pithiviers', 47.0, ['network' => 'secondaire'], 'abandoned');
        $this->trace('archeoferroviaire', 'a1', 'LINESTRING(2.0 47.0, 2.1 47.0)', [], 'secondaire', 'Pithiviers Toury (V60)', null);

        $default = $this->lineWithSegments('843000', 'Ligne de Bort à Ussel', 47.5, ['network' => 'secondaire', 'gauge_mm_default' => 1000]);

        $this->artisan('vff:enrich-era-segments')->assertSuccessful();

        $s = $this->one($osm, $this->e2030);
        $this->assertSame([1000, 'metre', 'osm'], [$s->gauge_mm, $s->gauge_class, $s->gauge_source]);
        $this->assertSame([true, 850, 'osm'], [$s->electrified, $s->voltage_v, $s->electrification_source], 'troisième rail OSM');

        $s = $this->one($bdt, $this->e2030);
        $this->assertSame(['narrow', 'bdtopo'], [$s->gauge_class, $s->gauge_source]);
        $this->assertSame([false, 'bdtopo'], [$s->electrified, $s->electrification_source]);

        $s = $this->one($arch, $this->e1930);
        $this->assertSame([600, 'narrow', 'archeo'], [$s->gauge_mm, $s->gauge_class, $s->gauge_source]);

        $s = $this->one($default, $this->e1930);
        $this->assertSame([1000, 'metre'], [$s->gauge_mm, $s->gauge_class]);
    }

    // ─── sélection, bilan ────────────────────────────────────────────────

    public function test_selection_options_and_report(): void
    {
        $a = $this->lineWithSegments('850000', 'Ligne A', 46.0);
        $b = $this->lineWithSegments('851000', 'Ligne B', 46.5);

        // --shard : seulement les lignes dont id % n = i - 1.
        $shard = ($a->id % 2) + 1;
        $this->artisan('vff:enrich-era-segments', ['--shard' => "{$shard}/2"])
            ->expectsOutputToContain('  1 lignes,')
            ->assertSuccessful();
        $this->assertNotNull($this->one($a, $this->e2030)->electrification_source);
        $this->assertNull($this->one($b, $this->e2030)->electrification_source);

        // --missing : la ligne restante seulement.
        Artisan::call('vff:enrich-era-segments', ['--missing' => true]);
        $this->assertStringContainsString('  1 lignes,', Artisan::output());
        $this->assertNotNull($this->one($b, $this->e2030)->electrification_source);
        Artisan::call('vff:enrich-era-segments', ['--missing' => true]);
        $this->assertStringContainsString('0 lignes', Artisan::output());

        // Ligne inconnue : rien.
        $this->artisan('vff:enrich-era-segments', ['--line' => '999999'])->expectsOutputToContain('0 lignes')->assertSuccessful();

        $before = $this->snapshot(['line_segments']);
        $this->artisan('vff:enrich-era-segments', ['--report' => true])
            ->expectsTable(
                ['Ère', 'km en service', 'électrifiés', '1500 V', '25 kV', '< 1500 V', 'autres', 'tension ?', 'date ?', 'normale', 'métrique', 'étroite', 'écart. ?'],
                [
                    ['1910', '15', '0', '0', '0', '0', '0', '0', '0', '15', '0', '0', '0'],
                    ['1930', '15', '0', '0', '0', '0', '0', '0', '0', '15', '0', '0', '0'],
                    ['2030', '15', '0', '0', '0', '0', '0', '0', '0', '15', '0', '0', '0'],
                ],
            )
            ->doesntExpectOutputToContain('lignes,')
            ->assertSuccessful();
        $this->assertSame($before, $this->snapshot(['line_segments']), '--report ne lit que');
    }

    // ─── règles des événements ───────────────────────────────────────────

    /** Ligne à une seule portion, non électrifiée aujourd'hui (aucune source actuelle) : seuls les événements jouent. */
    private function eventsLine(string $code, float $lat, array $events, array $eras): array
    {
        $line = $this->rfnLine($code, 'Ligne '.$code, ['description' => null]);
        foreach ($eras as $era) {
            $this->track($line, $era, [[$lat, 2.0], [$lat, 2.1]], ['electrified' => false, 'length_meters' => 7730]);
        }
        foreach ($events as $i => [$date, $text, $attrs]) {
            DB::table('line_events')->insert($attrs + [
                'line_id' => $line->id, 'type' => 'electrification', 'event_date' => $date, 'description' => $text,
                'source' => 'wikipedia', 'confidence' => 'high', 'date_precision' => 'year', 'section_resolution' => 'whole_line',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return [$line];
    }

    public function test_electrification_events_rules(): void
    {
        $e1920 = $this->era(1920);
        $e1950 = $this->era(1950);
        $eras = [$this->e1910, $e1920, $this->e1930, $e1950];
        $cases = [
            'conversion' => [['1912-01-01', 'Électrification en 12000 V, conversion en 1923 en 1500 V', []]],
            'fin wikidata' => [['1905-01-01', 'Électrification 1500 V (P582 = 1925)', []]],
            'fin puis reprise' => [['1905-01-01', 'Électrification 1500 V (P582 = 1925)', []], ['1926-01-01', 'Électrification 1500 V', []]],
            'achèvement' => [['1915-01-01', 'Électrification partielle 1500 V', []], ['1926-01-01', 'Ligne entièrement électrifiée en 1500 V entre 1926 et 1935', []]],
            'non localisés' => [['1915-01-01', 'Électrification 1500 V', ['section_from' => 'A', 'section_resolution' => 'unresolved']], ['1928-01-01', 'Électrification 1500 V', ['section_from' => 'B', 'section_resolution' => 'unresolved']]],
            'infobox partielle' => [['1915-01-01', 'électrification = partielle 1500 V', ['section_from' => 'A', 'section_resolution' => 'unresolved']], ['1928-01-01', 'électrification = partielle 1500 V', ['section_from' => 'B', 'section_resolution' => 'unresolved']]],
            'tension voisine' => [['1924-01-01', 'Paris - Bécon électrifié', []], ['1928-01-01', 'troisième rail 750 V', ['section_from' => 'X', 'section_resolution' => 'unresolved']]],
            'dépose' => [['1912-01-01', 'Électrification 1500 V', []], ['1940-01-01', 'Dépose de la caténaire, ligne désélectrifiée', ['type' => 'electrification']]],
            'ignoré' => [['1912-01-01', 'Projet d\'électrification en 1500 V', []]],
        ];
        $expected = [
            'conversion' => ['1910:N', '1920:E12000/1912', '1930:E1500/1923', '1950:E1500/1923'],
            'fin wikidata' => ['1910:E1500/1905', '1920:E1500/1905', '1930:N', '1950:N'],
            'fin puis reprise' => ['1910:E1500/1905', '1920:E1500/1905', '1930:E1500/1905', '1950:E1500/1905'],
            'achèvement' => ['1910:N', '1920:N', '1930:N', '1950:E1500/1935'],
            'non localisés' => ['1910:N', '1920:E1500/1915', '1930:E1500/1915', '1950:E1500/1915'],
            'infobox partielle' => ['1910:N', '1920:N', '1930:E1500/1928', '1950:E1500/1928'],
            'tension voisine' => ['1910:N', '1920:N', '1930:E750/1924', '1950:E750/1924'],
            'dépose' => ['1910:N', '1920:E1500/1912', '1930:E1500/1912', '1950:N'],
            'ignoré' => ['1910:N', '1920:N', '1930:N', '1950:N'],
        ];
        $sources = ['conversion' => 'event_line', 'non localisés' => 'event_unlocated', 'ignoré' => 'none'];
        $lat = 44.0;
        foreach ($cases as $name => $events) {
            [$line] = $this->eventsLine((string) (700000 + (int) ($lat * 10)), $lat, $events, $eras);
            $lat += 0.2;
            $this->artisan('vff:enrich-era-segments', ['--line' => (string) $line->id])->assertSuccessful();
            $out = [];
            foreach ($eras as $era) {
                $seg = $this->one($line, $era);
                $out[] = $era->year.':'.($seg->electrified ? 'E'.$seg->voltage_v.'/'.$seg->electrified_year : 'N');
                if (isset($sources[$name])) {
                    $this->assertSame($sources[$name], $seg->electrification_source, $name);
                }
            }
            $this->assertSame($expected[$name], $out, $name);
        }
    }

    public function test_current_evidence_arbitration(): void
    {
        $w = fn (float $lat, float $a = 2.0, float $b = 2.1) => "LINESTRING({$a} {$lat}, {$b} {$lat})";
        $cases = [];
        // a. OSM caténaire 25 kV, BD TOPO non électrifiée : OSM l'emporte (tension connue).
        $cases['a'] = [$this->lineWithSegments('860000', 'A', 45.0), fn ($l) => [
            $this->trace('osm', 'a1', $w(45.0), ['electrified' => 'contact_line', 'voltage' => '25000']),
            $this->trace('bdtopo', 'a2', $w(45.0), ['electrifie' => 'false', 'etat_de_l_objet' => 'En service'], 'Voie ferrée principale')]];
        // b. OSM non électrifiée, BD TOPO électrifiée : OSM (voie posée) l'emporte.
        $cases['b'] = [$this->lineWithSegments('861000', 'B', 45.2), fn ($l) => [
            $this->trace('osm', 'b1', $w(45.2), ['electrified' => 'no']),
            $this->trace('bdtopo', 'b2', $w(45.2), ['electrifie' => 'true', 'etat_de_l_objet' => 'En service'], 'Voie ferrée principale')]];
        // c. BD TOPO seule, électrifiée : tension inconnue.
        $cases['c'] = [$this->lineWithSegments('862000', 'C', 45.4), fn ($l) => [
            $this->trace('bdtopo', 'c1', $w(45.4), ['electrifie' => 'true', 'etat_de_l_objet' => 'En service'], 'Voie ferrée principale')]];
        // d. Voie disparue, caténaire attestée par OSM abandonné : électrifiée par le passé, date inconnue.
        $cases['d'] = [$this->lineWithSegments('863000', 'D', 45.6, [], 'abandoned'), fn ($l) => [
            $this->trace('osm', 'd1', $w(45.6), ['electrified' => 'contact_line', 'voltage' => '1500'], 'abandoned:railway', null, $l->id)]];
        // e. Voie disparue : une voie exploitée voisine NON rattachée appartient à une autre ligne.
        $cases['e'] = [$this->lineWithSegments('864000', 'E', 45.8, [], 'abandoned'), fn ($l) => [
            $this->trace('osm', 'e1', $w(45.8), ['electrified' => 'contact_line', 'voltage' => '25000'])]];
        // f. Voie métrique : BD TOPO « Etroite » complétée par l'écartement OSM de la même ligne.
        $cases['f'] = [$this->lineWithSegments('865000', 'F', 46.0, ['network' => 'secondaire']), fn ($l) => [
            $this->trace('osm', 'f1', $w(46.0, 2.0, 2.03), ['gauge' => '1000']),
            $this->trace('bdtopo', 'f2', $w(46.0), ['largeur' => 'Etroite', 'etat_de_l_objet' => 'En service'], 'Voie ferrée principale')]];
        // g. Catégorie SNCF « voie étroite » : arbitre une voie voisine à écartement normal.
        $cases['g'] = [$this->lineWithSegments('866000', 'G', 46.2), function ($l) use ($w) {
            DB::table('sncf_line_catlig')->insert(['code_ligne' => '866000', 'catlig' => 'Ligne à voie étroite']);
            $this->sncfShape('866000', $w(46.2));
            $this->trace('osm', 'g1', $w(46.2), ['gauge' => '1435']);
        }];
        foreach ($cases as [$line, $setup]) {
            $setup($line);
        }

        $this->artisan('vff:enrich-era-segments', ['--no-split' => true])->assertSuccessful();

        $got = [];
        foreach ($cases as $k => [$line]) {
            foreach ([$this->e1930, $this->e2030] as $era) {
                $s = $this->one($line, $era);
                $got[$k][] = $era->year.':'.($s->electrified ? 'E'.$s->voltage_v : 'N').'/'.$s->electrification_source.'/'.$s->gauge_mm.'/'.$s->gauge_class.'/'.$s->gauge_source;
            }
        }
        $this->assertSame([
            'a' => ['1930:N/rule/1435/standard/network', '2030:E25000/osm/1435/standard/network'],
            'b' => ['1930:N/osm/1435/standard/network', '2030:N/osm/1435/standard/network'],
            'c' => ['1930:N/unknown/1435/standard/network', '2030:E/bdtopo/1435/standard/network'],
            'd' => ['1930:N/unknown/1435/standard/network', '2030:N/unknown/1435/standard/network'],
            'e' => ['1930:N/none/1435/standard/network', '2030:N/none/1435/standard/network'],
            'f' => ['1930:N/none/1000/metre/bdtopo', '2030:N/none/1000/metre/bdtopo'],
            'g' => ['1930:N/sncf//narrow/sncf', '2030:N/sncf//narrow/sncf'],
        ], $got);
    }
}
