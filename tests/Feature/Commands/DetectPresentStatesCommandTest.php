<?php

namespace Tests\Feature\Commands;

use App\Console\Commands\DetectPresentStates;
use App\Models\Line;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * vff:detect-present-states : état actuel des emprises (voie en place,
 * déposée, voie verte, route) d'après OSM, BD TOPO, SNCF et lignes-oubliees,
 * et reconversions datées (événements « conversion »).
 *
 * Repères : à 46° N, 0,01° de longitude ≈ 773 m ; 0,00018° de latitude ≈ 20 m.
 */
class DetectPresentStatesCommandTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
    }

    // ─── utilitaires ─────────────────────────────────────────────────────

    private function line(string $code, string $name, string $wkt): Line
    {
        $line = Line::factory()->create(['code' => $code, 'name' => $name, 'slug' => 'rfn-'.$code, 'network' => 'principal', 'type' => null]);
        DB::insert("INSERT INTO line_reference_geometries (line_id, length_m, sources, geom, created_at, updated_at)
            VALUES (?, ROUND(ST_Length(ST_GeomFromText(?, 4326)::geography)), '[]'::jsonb, ST_Multi(ST_GeomFromText(?, 4326)), now(), now())", [$line->id, $wkt, $wkt]);
        DB::insert("INSERT INTO line_reference_parts (line_id, source, source_trace_ids, length_m, geom, created_at, updated_at)
            VALUES (?, 'osm', '[]'::jsonb, ROUND(ST_Length(ST_GeomFromText(?, 4326)::geography)), ST_GeomFromText(?, 4326), now(), now())", [$line->id, $wkt, $wkt]);

        return $line;
    }

    /** @param  array<string, mixed>  $tags */
    private function osmWay(int $id, string $kind, string $wkt, array $tags, ?int $startYear = null, ?array $routes = null): void
    {
        DB::insert("INSERT INTO present_osm_ways (id, region, kind, highway, name, tags, routes, start_year, length_m, seen_at, geom, created_at, updated_at)
            VALUES (?, 'test', ?, ?, ?, ?::jsonb, ?::jsonb, ?, ROUND(ST_Length(ST_GeomFromText(?, 4326)::geography)), '2026-10-01', ST_GeomFromText(?, 4326), now(), now())",
            [$id, $kind, $tags['highway'] ?? null, $tags['name'] ?? null, json_encode($tags), $routes === null ? null : json_encode($routes), $startYear, $wkt, $wkt]);
    }

    /** @param  array<string, mixed>  $properties */
    private function trace(string $source, string $ref, string $wkt, array $properties, string $layer = 'rail'): void
    {
        DB::insert("INSERT INTO source_traces (source, source_ref, layer, properties, length_m, seen_at, geom)
            VALUES (?, ?, ?, ?::jsonb, ROUND(ST_Length(ST_GeomFromText(?, 4326)::geography)), '2026-09-01', ST_Multi(ST_GeomFromText(?, 4326)))",
            [$source, $ref, $layer, json_encode($properties), $wkt, $wkt]);
    }

    private function closure(Line $line, string $date, string $wkt, string $type = 'closure'): void
    {
        $id = DB::table('line_events')->insertGetId([
            'line_id' => $line->id, 'type' => $type, 'event_date' => $date, 'description' => 'Fermeture', 'source' => 'wikipedia',
            'confidence' => 'high', 'date_precision' => 'day', 'section_resolution' => 'whole_line', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::update('UPDATE line_events SET section_geom = ST_Multi(ST_GeomFromText(?, 4326)) WHERE id = ?', [$wkt, $id]);
    }

    /** @return array<string, object> */
    private function states(): array
    {
        return DB::table('present_states')->get()->keyBy(fn ($r) => $r->source.':'.$r->source_ref)->all();
    }

    /** @return array<string, mixed> */
    private function props(object $state): array
    {
        return json_decode($state->properties, true);
    }

    /** Réseau de cas : chaque latitude isole un cas. */
    private function scenario(): array
    {
        // 46° : ligne déclassée, voie verte datée sur l'axe, plateforme devenue chemin, rue, rails abandonnés.
        $mayenne = $this->line('436000', 'Ligne de La Chapelle-Anthenaise à Flers', 'LINESTRING(1.0 46.0, 1.1 46.0)');
        $this->closure($mayenne, '1995-05-01', 'LINESTRING(1.0 46.0, 1.1 46.0)');
        $this->osmWay(100, 'greenway', 'LINESTRING(1.0 46.0, 1.03 46.0)', ['highway' => 'cycleway', 'name' => 'Voie verte de la Mayenne'], 2006);
        $this->osmWay(200, 'railway', 'LINESTRING(1.04 46.0, 1.06 46.0)', ['railway' => 'abandoned', 'highway' => 'track']);
        $this->osmWay(201, 'railway', 'LINESTRING(1.07 46.0, 1.08 46.0)', ['railway' => 'razed', 'highway' => 'residential', 'name' => 'Rue de la Gare']);
        $this->osmWay(202, 'railway', 'LINESTRING(1.09 46.0, 1.1 46.0)', ['railway' => 'disused']);

        // 47° : voie verte ouverte 50 ans après la fermeture : elle ne date pas la dépose.
        $late = $this->line('437000', 'Ligne de Vire à Granville', 'LINESTRING(1.0 47.0, 1.1 47.0)');
        $this->closure($late, '1960-01-01', 'LINESTRING(1.0 47.0, 1.1 47.0)');
        $this->osmWay(300, 'greenway', 'LINESTRING(1.0 47.0, 1.03 47.0)', ['highway' => 'cycleway', 'name' => 'Voie verte de Vire'], 2010);

        // 48° : BD TOPO « Non exploité » contredite par une voie verte sur l'axe même.
        $this->trace('bdtopo', 'TRONFERR1', 'LINESTRING(1.0 48.0, 1.03 48.0)', ['etat_de_l_objet' => 'Non exploité'], 'Voie ferrée principale');
        $this->osmWay(301, 'greenway', 'LINESTRING(1.0 48.0, 1.03 48.0)', ['highway' => 'cycleway', 'name' => 'Véloroute']);

        // 49° : statuts SNCF.
        $this->trace('sncf', 'S-DEP', 'LINESTRING(1.0 49.0, 1.05 49.0)', ['statut' => 'Fermée et déposée (Plus utilisable)', 'code_ligne' => '438000', 'pk_debut_m' => 1000, 'pk_fin_m' => 4600]);
        $this->trace('sncf', 'S-NEU', 'LINESTRING(1.0 49.5, 1.05 49.5)', ['statut' => 'Neutralisée', 'code_ligne' => '439000']);
        $this->trace('sncf', 'S-EXP', 'LINESTRING(1.0 49.8, 1.05 49.8)', ['statut' => 'Exploitée', 'code_ligne' => '440000']);

        // 50° : voie verte À CÔTÉ d'une voie laissée en place (20 m).
        $this->trace('bdtopo', 'TRONFERR2', 'LINESTRING(1.0 50.0, 1.03 50.0)', ['etat_de_l_objet' => 'Non exploité'], 'Voie ferrée principale');
        $this->osmWay(302, 'greenway', 'LINESTRING(1.0 50.00018, 1.03 50.00018)', ['highway' => 'cycleway', 'name' => 'Voie verte du Morvan'], 2015);

        // 51° : voies vertes et chantiers OSM sur rails déposés.
        $this->osmWay(203, 'railway', 'LINESTRING(1.0 51.0, 1.02 51.0)', ['railway' => 'abandoned', 'highway' => 'cycleway', 'start_date' => '1975']);
        $this->osmWay(204, 'railway', 'LINESTRING(1.0 51.1, 1.02 51.1)', ['railway' => 'abandoned', 'highway' => 'proposed', 'name' => 'Projet de voie verte']);
        $this->osmWay(205, 'railway', 'LINESTRING(1.0 51.2, 1.02 51.2)', ['abandoned:railway' => 'rail', 'highway' => 'path', 'bicycle' => 'yes', 'start_date' => '2012-06']);
        $this->osmWay(206, 'railway', 'LINESTRING(1.0 51.3, 1.02 51.3)', ['railway' => 'tram']);
        $this->osmWay(207, 'railway', 'LINESTRING(1.0 51.4, 1.02 51.4)', ['disused:railway' => 'rail', 'highway' => 'track']);
        $this->osmWay(208, 'railway', 'LINESTRING(1.0 51.5, 1.02 51.5)', ['historic' => 'railway', 'highway' => 'path'],
            null, [['name' => 'Voie verte des Hautes-Vosges', 'start_date' => '2001']]);

        return compact('mayenne', 'late');
    }

    // ─── scénario complet ────────────────────────────────────────────────

    public function test_states_are_detected_from_every_source(): void
    {
        $f = $this->scenario();

        $this->artisan('vff:detect-present-states', ['--no-lo' => true])
            ->expectsOutputToContain('Zone : France entière')
            ->expectsOutputToContain('→ lignes-oubliees : état actuel…')
            ->expectsOutputToContain('Bilan sur les tracés de référence (tronçons de 100 m, observation à ≤ 15 m) :')
            ->assertSuccessful();

        $s = $this->states();
        $this->assertSame(['greenway', 2006], [$s['osm:way/100']->state, $s['osm:way/100']->since_year]);
        $this->assertSame('Voie verte de la Mayenne', $this->props($s['osm:way/100'])['name']);
        $this->assertSame(1.0, $this->props($s['osm:way/100'])['part_on_axis']);
        $this->assertSame(['dismantled', 'path'], [$s['osm:way/200']->state, $this->props($s['osm:way/200'])['reuse']]);
        $this->assertSame('road', $s['osm:way/201']->state);
        $this->assertSame(['track_in_place', 'disused'], [$s['osm:way/202']->state, $this->props($s['osm:way/202'])['detail']]);

        // Voie verte bien postérieure à la fermeture : année gardée en propriété, pas comme date de dépose.
        $this->assertNull($s['osm:way/300']->since_year);
        $this->assertEquals(['voie_verte_depuis' => 2010, 'fermeture_connue' => 1960],
            array_intersect_key($this->props($s['osm:way/300']), ['voie_verte_depuis' => 0, 'fermeture_connue' => 0]));

        $this->assertArrayNotHasKey('bdtopo:TRONFERR1', $s, 'BD TOPO contredite par une voie verte sur l\'axe');
        $this->assertSame('greenway', $s['osm:way/301']->state);

        $this->assertSame('dismantled', $s['sncf:S-DEP']->state);
        $this->assertEquals(['statut' => 'Fermée et déposée (Plus utilisable)', 'code_ligne' => '438000', 'pk_debut_m' => 1000, 'pk_fin_m' => 4600],
            array_intersect_key($this->props($s['sncf:S-DEP']), ['statut' => 0, 'code_ligne' => 0, 'pk_debut_m' => 0, 'pk_fin_m' => 0]));
        $this->assertSame('track_in_place', $s['sncf:S-NEU']->state);
        $this->assertArrayNotHasKey('sncf:S-EXP', $s, 'ligne exploitée : aucune observation');

        $this->assertSame('track_in_place', $s['bdtopo:TRONFERR2']->state);
        $this->assertSame(['way/302'], $this->props($s['bdtopo:TRONFERR2'])['voie_verte_a_cote']);
        $this->assertArrayNotHasKey('osm:way/302', $s, 'voie verte à côté de rails laissés en place : pas de reconversion');

        $this->assertSame(['greenway', null], [$s['osm:way/203']->state, $s['osm:way/203']->since_year], 'start_date < 1980 : ouverture de la ligne');
        $this->assertSame('dismantled', $s['osm:way/204']->state, 'projet de voie verte : rails déposés seulement');
        $this->assertSame(['greenway', 2012, 'lifecycle'], [$s['osm:way/205']->state, $s['osm:way/205']->since_year, $this->props($s['osm:way/205'])['railway']]);
        $this->assertArrayNotHasKey('osm:way/206', $s);
        $this->assertArrayNotHasKey('osm:way/207', $s, 'disused:railway devenu chemin : ambigu');
        $this->assertSame(['greenway', 2001], [$s['osm:way/208']->state, $s['osm:way/208']->since_year], 'relation vélo nommée « voie verte »');

        foreach ($s as $state) {
            $this->assertSame(DetectPresentStates::DETECTOR, $this->props($state)['detector']);
        }

        // Reconversion datée sur la seule ligne fermée moins de 15 ans avant.
        $events = DB::table('line_events')->where('type', 'conversion')->get();
        $this->assertCount(1, $events);
        $e = $events[0];
        $this->assertSame([$f['mayenne']->id, '2006-01-01', 'year', 'greenway', 'osm', 'trace', 'https://www.openstreetmap.org/way/100'],
            [$e->line_id, $e->event_date, $e->date_precision, $e->reuse, $e->source, $e->section_resolution, $e->source_url]);
        $this->assertSame('Reconversion en voie verte (Voie verte de la Mayenne), ouverte en 2006 d\'après OpenStreetMap', $e->description);
        $this->assertStringStartsWith('present:', $e->fingerprint);
        $this->assertEqualsWithDelta(2320, $e->section_length_m, 30);
        $this->assertNotNull(DB::table('line_events')->where('id', $e->id)->value('section_geom'));
    }

    public function test_a_second_run_replaces_its_own_rows_only(): void
    {
        $this->scenario();
        DB::table('present_states')->insert(['source' => 'manual', 'source_ref' => 'x', 'state' => 'road', 'properties' => '{}',
            'geom' => DB::raw("ST_Multi(ST_GeomFromText('LINESTRING(5 45, 5.1 45)', 4326))")]);
        $this->artisan('vff:detect-present-states', ['--no-lo' => true])->assertSuccessful();
        $before = $this->snapshot(['present_states', 'line_events'], ['id']);
        $n = DB::table('present_states')->count();

        $this->artisan('vff:detect-present-states', ['--no-lo' => true])
            ->expectsOutputToContain(($n - 1).' anciennes observations supprimées, '.($n - 1).' écrites')
            ->expectsOutputToContain('1 événements conversion écrits, 0 écartés (fermeture trop ancienne ou inconnue, dépose déjà connue), 0 obsolètes supprimés')
            ->assertSuccessful();

        $this->assertSame($before, $this->snapshot(['present_states', 'line_events'], ['id']));
        $this->assertDatabaseHas('present_states', ['source' => 'manual', 'source_ref' => 'x']);
    }

    public function test_conversion_is_withdrawn_when_the_dismantling_is_already_known(): void
    {
        $f = $this->scenario();
        $this->artisan('vff:detect-present-states', ['--no-lo' => true])->assertSuccessful();
        $this->assertSame(1, DB::table('line_events')->where('type', 'conversion')->count());

        // Dépose datée par une autre source : elle prime, la reconversion OSM est retirée.
        $this->closure($f['mayenne'], '1999-01-01', 'LINESTRING(1.0 46.0, 1.1 46.0)', 'dismantling');
        $this->artisan('vff:detect-present-states', ['--no-lo' => true])
            ->expectsOutputToContain('0 événements conversion écrits, 1 écartés (fermeture trop ancienne ou inconnue, dépose déjà connue), 1 obsolètes supprimés')
            ->assertSuccessful();
        $this->assertSame(0, DB::table('line_events')->where('type', 'conversion')->count());
    }

    public function test_conversion_needs_a_recent_localised_closure(): void
    {
        $line = $this->line('436000', 'Ligne de La Chapelle-Anthenaise à Flers', 'LINESTRING(1.0 46.0, 1.1 46.0)');
        $this->osmWay(100, 'greenway', 'LINESTRING(1.0 46.0, 1.03 46.0)', ['highway' => 'cycleway', 'name' => 'Voie verte'], 2006);
        // Fermeture ailleurs sur la ligne (à 30 km) : ne date pas cette portion.
        $this->closure($line, '1995-05-01', 'LINESTRING(1.5 46.0, 1.6 46.0)');

        $this->artisan('vff:detect-present-states', ['--no-lo' => true])
            ->expectsOutputToContain('0 événements conversion écrits, 1 écartés')
            ->assertSuccessful();
        $this->assertSame(2006, $this->states()['osm:way/100']->since_year, 'pas de fermeture localisée qui la contredise');
    }

    public function test_options_dry_run_no_events_and_report(): void
    {
        $this->scenario();

        $this->artisan('vff:detect-present-states', ['--no-lo' => true, '--dry-run' => true])
            ->expectsOutputToContain('--dry-run : rien n\'a été écrit.')
            ->doesntExpectOutputToContain('→ Écriture present_states')
            ->assertSuccessful();
        $this->assertSame(0, DB::table('present_states')->count());

        $this->artisan('vff:detect-present-states', ['--no-lo' => true, '--no-events' => true])
            ->doesntExpectOutputToContain('→ Événements datés (conversion)')
            ->assertSuccessful();
        $this->assertGreaterThan(0, DB::table('present_states')->count());
        $this->assertSame(0, DB::table('line_events')->where('type', 'conversion')->count());

        $before = $this->snapshot(['present_states']);
        $this->artisan('vff:detect-present-states', ['--report' => true])
            ->expectsOutputToContain('Bilan sur les tracés de référence')
            ->doesntExpectOutputToContain('→ OSM : ways ferroviaires')
            ->assertSuccessful();
        $this->assertSame($before, $this->snapshot(['present_states']), '--report ne lit que');
    }

    public function test_zone_limits_reading_and_writing_to_the_lines(): void
    {
        $f = $this->scenario();
        $this->artisan('vff:detect-present-states', ['--no-lo' => true])->assertSuccessful();
        $outside = DB::table('present_states')->where('source_ref', 'way/300')->first();

        // Hors zone : la ligne 437000 garde ses observations ; la zone (3 km autour de 436000) est réécrite.
        DB::table('present_states')->where('source_ref', 'way/300')->update(['state' => 'road']);
        DB::table('present_osm_ways')->where('id', 201)->delete();
        $this->artisan('vff:detect-present-states', ['--no-lo' => true, '--line' => ['436000']])
            ->expectsOutputToContain('Zone : autour de 436000')
            ->assertSuccessful();

        $s = $this->states();
        $this->assertArrayNotHasKey('osm:way/201', $s, 'observation disparue de la zone supprimée');
        $this->assertSame('road', $s['osm:way/300']->state, 'hors zone : intact');
        $this->assertSame(1, DB::table('line_events')->where('type', 'conversion')->where('line_id', $f['mayenne']->id)->count());
        unset($outside);

        // Par identifiant interne aussi ; ligne sans tracé : France entière.
        $this->artisan('vff:detect-present-states', ['--no-lo' => true, '--dry-run' => true, '--line' => [(string) $f['late']->id]])
            ->expectsOutputToContain('Zone : autour de '.$f['late']->id)
            ->assertSuccessful();
        $this->artisan('vff:detect-present-states', ['--no-lo' => true, '--dry-run' => true, '--line' => ['999000']])
            ->expectsOutputToContain('Aucun tracé de référence pour ces lignes : France entière.')
            ->expectsOutputToContain('Zone : France entière')
            ->assertSuccessful();
    }

    // ─── lignes-oubliees ─────────────────────────────────────────────────

    public function test_lignes_oubliees_current_state_sections(): void
    {
        $line = $this->line('436000', 'Ligne de La Chapelle-Anthenaise à Flers', 'LINESTRING(1.0 46.0, 1.1 46.0)');
        $url = 'https://www.lignes-oubliees.com/index.php?page=mayenne';
        DB::table('line_events')->insert(['line_id' => $line->id, 'type' => 'closure', 'event_date' => '1938-01-01', 'description' => 'Fermeture',
            'source' => 'lignes-oubliees', 'source_url' => $url, 'confidence' => 'high', 'date_precision' => 'year', 'created_at' => now(), 'updated_at' => now()]);
        $other = $this->line('437000', 'Ligne de Vire à Granville', 'LINESTRING(1.0 47.0, 1.1 47.0)');
        DB::table('line_events')->insert(['line_id' => $other->id, 'type' => 'closure', 'event_date' => '1938-01-01', 'description' => 'Fermeture',
            'source' => 'lignes-oubliees', 'source_url' => 'https://www.lignes-oubliees.com/index.php?page=vire', 'confidence' => 'high',
            'date_precision' => 'year', 'created_at' => now(), 'updated_at' => now()]);

        $dir = storage_path('app/sources/lignes-oubliees/fiches');
        File::ensureDirectoryExists($dir);
        File::put($dir.'/mayenne.html', <<<'HTML'
            <html><body><div id="contenu"><h1>Ligne de La Chapelle-Anthenaise à Flers</h1>
            <dl class="historique">
              <dt>Fermeture aux voyageurs :</dt><dd><span>Totalité de la ligne :</span>1938</dd>
              <dt>État actuel de la ligne :</dt><dd><span>Totalité de la ligne :</span>déposée (voie verte) (1)</dd>
            </dl>
            <ol><li>Une voie verte a été ouverte en 2006 entre Andouillé et Saint-Germain.</li></ol>
            </div></body></html>
            HTML);
        File::put($dir.'/vire.html', <<<'HTML'
            <html><body><div id="contenu"><h1>Ligne de Vire à Granville</h1>
            <dl class="historique">
              <dt>État actuel de la ligne :</dt><dd><span>Totalité de la ligne :</span>inexploitée</dd>
            </dl></div></body></html>
            HTML);
        File::put($dir.'/sans-ligne.html', '<html><body><dl class="historique"><dt>État actuel de la ligne :</dt><dd><span>Totalité de la ligne :</span>déposée</dd></dl></body></html>');

        $this->artisan('vff:detect-present-states')
            ->expectsOutputToContain('3 portions (2 localisées, 1 non localisées)')
            ->assertSuccessful();

        $s = $this->states();
        $this->assertSame('greenway', $s['lignes-oubliees:mayenne:1']->state);
        $p = $this->props($s['lignes-oubliees:mayenne:1']);
        $this->assertSame([$line->id, 'Totalité de la ligne', 'déposée (voie verte) (1)', $url], [$p['line_id'], $p['section'], $p['value'], $p['url']]);
        $this->assertSame('État actuel de la ligne : Totalité de la ligne : déposée (voie verte) (1) / Une voie verte a été ouverte en 2006 entre Andouillé et Saint-Germain.', $p['quote']);
        $this->assertSame('track_in_place', $s['lignes-oubliees:vire:1']->state);
        $this->assertArrayNotHasKey('lignes-oubliees:mayenne:1:vv1', $s, 'sous-section non localisée');
        $this->assertSame(0, DB::table('line_events')->where('source', 'present-tmp')->count(), 'événements temporaires annulés');
    }

    // ─── règles pures ────────────────────────────────────────────────────

    private function invokePrivate(string $method, mixed ...$args): mixed
    {
        return (new \ReflectionMethod(DetectPresentStates::class, $method))->invoke(app(DetectPresentStates::class), ...$args);
    }

    /** @return array<string, array{0: string, 1: ?string}> */
    public static function loStates(): array
    {
        return [
            'travaux' => ['en travaux de voie verte', null],
            'voie verte' => ['voie verte', 'greenway'],
            'piste' => ['piste cyclable sur la plateforme', 'greenway'],
            'déposée (voie verte)' => ['deposee (voie verte)', 'greenway'],
            'déposée, voie verte' => ['deposee, voie verte', 'greenway'],
            'déposée majoritairement reconvertie' => ['deposee (majoritairement reconvertie en voie verte)', 'greenway'],
            'déposée voie verte sauf' => ['deposee (voie verte (sauf tunnel))', 'greenway'],
            'déposée piste' => ['deposee (piste cyclable)', 'greenway'],
            'déposée seule' => ['deposee', 'dismantled'],
            'déposée voie verte partielle' => ['deposee (voie verte entre a et b)', 'dismantled'],
            'tunnel routier' => ['deposee (tunnel routier)', 'road'],
            'inexploitée' => ['inexploitee', 'track_in_place'],
            'vélorail' => ['velorail', 'track_in_place'],
            'vélo-rail' => ['velo-rail de la mayenne', 'track_in_place'],
            'service' => ['service marchandises', 'track_in_place'],
            'services' => ['services', 'track_in_place'],
            'trafic' => ['trafic fret', 'track_in_place'],
            'train touristique' => ['train touristique', 'track_in_place'],
            'exploitée' => ['exploitee', 'track_in_place'],
            'voie de service' => ['voie de service', 'track_in_place'],
            'inconnu' => ['emprise vendue', null],
            'milieu' => ['partiellement deposee', null],
        ];
    }

    #[DataProvider('loStates')]
    public function test_lignes_oubliees_values_map_to_states(string $value, ?string $state): void
    {
        $this->assertSame($state, $this->invokePrivate('loState', $value));
    }

    /** @return array<string, array{0: string, 1: list<array{0: string, 1: string, 2: ?int}>}> */
    public static function greenTexts(): array
    {
        return [
            'phrase datée' => ['Une voie verte a été ouverte en 2006 entre Audincourt et Beaucourt.', [['Audincourt', 'Beaucourt', 2006]]],
            'dès' => ['Piste cyclable dès 1998 entre Guer et Mauron.', [['Guer', 'Mauron', 1998]]],
            'depuis' => ['Chemin vert depuis 2001 entre Ernée et Gorron.', [['Ernée', 'Gorron', 2001]]],
            'jusqu’à' => ['La voie verte va entre Pontivy jusqu\'à Loudéac.', [['Pontivy', 'Loudéac', null]]],
            'année après' => ['Voie verte entre Vitré et Fougères en 2010.', [['Vitré', 'Fougères', 2010]]],
            'deux portions' => ['Coulée verte entre Sceaux et Massy et entre Antony et Verrières.', [['Sceaux', 'Massy', null], ['Antony', 'Verrières', null]]],
            'parenthèses' => ['Voie verte entre Médréac (gare) et Montauban.', [['Médréac', 'Montauban', null]]],
            'sur X - Y' => ['(voies vertes sur Chateaubriant - Rougé et Messac - Ploërmel)', [['Chateaubriant', 'Rougé', null], ['Messac', 'Ploërmel', null]]],
            'projet ignoré' => ['Un projet de voie verte entre Aa et Bb.', []],
            'travaux ignorés' => ['Voie verte en cours entre Aa et Bb.', []],
            'prévu ignoré' => ['Une voie verte est prévue entre Aa et Bb.', []],
            'sans voie verte' => ['La ligne est déposée entre Aa et Bb.', []],
            'année future' => ['Voie verte ouverte en 2099 entre Aa et Bb.', [['Aa', 'Bb', null]]],
            'lieu article' => ['Voie verte entre Aa et le tunnel du Tracol.', []],
            'plusieurs phrases' => ['La ligne ferme en 1950. Une voie verte a été ouverte en 2003 entre Aa et Bb. Le viaduc est classé.', [['Aa', 'Bb', 2003]]],
        ];
    }

    #[DataProvider('greenTexts')]
    public function test_green_subsections_are_read_from_the_notes(string $text, array $expected): void
    {
        $got = array_map(fn ($o) => [$o[0], $o[1], $o[2]], $this->invokePrivate('greenSubsections', $text));
        $this->assertSame($expected, $got);
    }
}
