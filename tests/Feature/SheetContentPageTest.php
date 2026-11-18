<?php

namespace Tests\Feature;

use App\Models\Era;
use App\Models\Line;
use App\Models\Station;
use App\Services\LineStations;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Contenu calculé des fiches ligne et gare (chantier « UI unifiée », agent donnees-fiches), lu dans
 * les props de la page : autres noms, sources, chronologie rédigée, technique, électrification,
 * exploitants, gares voisines, fermetures par décennie.
 *
 * Ligne fictive inspirée de 610000 (Limoges - Angoulême) : portion A en service, électrifiée sur
 * 300 m seulement, portion B fermée le 13/03/2018 ; une gare « Saillat-Chassenon » que la source
 * écrit « Saillat - Chassenon ».
 */
class SheetContentPageTest extends TestCase
{
    use RefreshDatabase;

    private Line $line;

    /** @var array<int, Era> */
    private array $eras = [];

    private const A = 'LINESTRING(1.00 45.80, 1.10 45.80)';

    private const A_ELEC = 'LINESTRING(0.996 45.80, 1.00 45.80)';

    private const B = 'LINESTRING(1.10 45.80, 1.25 45.80)';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeader('Accept-Language', 'fr');
        foreach ([1880, 2010, 2020, 2030] as $y) {
            $this->eras[$y] = $y === 2030 ? Era::factory()->current()->create(['year' => $y]) : Era::factory()->create(['year' => $y]);
        }
        Line::factory()->create(['slug' => 'raccordement', 'code' => '590306', 'name' => 'Raccordement de Limoges-Puy-Imbert à Limoges-Montjovis', 'type' => null]);
        $this->line = Line::factory()->create([
            'slug' => 'ligne-test-610000', 'code' => '610000', 'name' => 'Ligne de Limoges-Bénédictins à Angoulême', 'type' => null,
            'network' => 'principal', 'is_lgv' => false, 'wikipedia_title' => 'Ligne de Limoges-Bénédictins à Angoulême', 'description' => null,
            'historical_operators' => json_encode([
                ['role' => 'exploitant', 'operator' => 'Compagnie des chemins de fer des Charentes', 'wikidata' => 'Q2989936', 'from_year' => 1868, 'to_year' => 1878],
                ['role' => 'exploitant', 'operator' => 'SNCF Réseau', 'wikidata' => 'Q21605526', 'from_year' => 2015, 'to_year' => null],
            ]),
            'alt_names' => ['wikidata' => 'Q3239643', 'names' => array_map(fn ($n) => ['name' => $n, 'kind' => 'name', 'source' => 'wikidata'], [
                'Voie ferrée Angoulême-Limoges', 'Ligne Limoges-Angoulême', 'Ligne Angoulême-Limoges',
                'Ligne Limoges-Bénédictins - Angoulême', 'Raccordement de Limoges-Puy-Imbert à Limoges-Montjovis',
            ])],
        ]);

        $seg = fn (int $year, string $wkt, string $status, array $x = []) => DB::table('line_segments')->insert($x + [
            'line_id' => $this->line->id, 'era_id' => $this->eras[$year]->id, 'geom' => DB::raw("ST_GeomFromText('{$wkt}', 4326)"),
            'status' => $status, 'traffic_type' => 'mixed', 'source' => 'osm', 'created_at' => now(), 'updated_at' => now()]);
        foreach ([1880, 2010] as $y) {
            $seg($y, self::A, 'in_service');
            $seg($y, self::B, 'in_service');
        }
        foreach ([2020, 2030] as $y) {
            $seg($y, self::A, 'in_service', ['max_speed_kmh' => 110, 'max_speed_source' => 'sncf']);
            $seg($y, self::A_ELEC, 'in_service', ['electrified' => true, 'voltage_v' => 1500, 'electrification_source' => 'sncf', 'source' => 'bdtopo']);
            $seg($y, self::B, 'abandoned', ['traffic_type' => 'unknown']);
        }
        DB::table('line_signalling')->insert(['line_id' => $this->line->id, 'era_id' => $this->eras[2030]->id, 'pk_start_m' => 0, 'pk_end_m' => 8000,
            'signalling' => 'TVM-300', 'train_protection' => json_encode(['TVM-300', 'KVB']), 'kvb_equipped' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('line_signalling')->insert(['line_id' => $this->line->id, 'era_id' => $this->eras[2030]->id, 'pk_start_m' => 8000, 'pk_end_m' => 9000,
            'signalling' => 'BAL', 'train_protection' => null, 'kvb_equipped' => false, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('line_sources')->insert([
            ['line_id' => $this->line->id, 'source_type' => 'sncf-classification', 'fetched_at' => '2026-10-20 10:59:27',
                'source_url' => 'https://ressources.data.sncf.com/ (liste-des-gares, lignes-par-type, lignes-lgv,…)',
                'fields_provided' => json_encode(['traffic_type', 'signal' => 'sncf-liste-des-gares'])],
        ]);

        $event = fn (string $type, string $date, ?string $from, ?string $to, string $description, ?string $quote = null, string $source = 'wikipedia', ?string $wkt = null) => DB::table('line_events')->insert([
            'line_id' => $this->line->id, 'type' => $type, 'event_date' => $date, 'date_precision' => 'day', 'description' => $description,
            'source_quote' => $quote, 'source' => $source, 'source_url' => 'https://example.org/'.$source, 'confidence' => 'high',
            'section_from' => $from, 'section_to' => $to, 'section_resolution' => $wkt ? 'places' : null,
            'section_geom' => $wkt ? DB::raw("ST_Multi(ST_GeomFromText('{$wkt}', 4326))") : null, 'created_at' => now(), 'updated_at' => now()]);
        $event('opening', '1875-04-26', null, null, 'Ouverture de toute la ligne (Wikipédia, texte, introduction)');
        $event('concession', '1878-01-01', null, null, 'Concession à État (Non concédée) (infobox Wikipédia)', 'Infobox : concessionnaire2 = État (Non concédée) ; dateconc2 = 1878');
        $event('closure', '2018-03-13', 'Saillat - Chassenon', 'Angoulême', 'Fermeture (tout trafic) de Saillat - Chassenon à Angoulême (correction sourcée : date de l’article, section « Déclin ».)',
            'Depuis le 13 mars 2018, la portion entre Saillat - Chassenon et Angoulême est fermée', 'manual', self::B);

        // Gares : Saillat-Chassenon (PK 8+000, ouverte), Angoulême (PK 23+000) fermée le 13/03/2018 mais
        // « non confirmée » à l'ère 2020 dans les données : listée parmi les fermetures à l'ère où son fil
        // passe à « fermée » (2030), comme le compteur.
        $station = fn (string $slug, string $name, float $lng, ?string $closed, array $x = []) => Station::factory()->create($x + [
            'slug' => $slug, 'name' => $name, 'geom' => Point::makeGeodetic(45.80, $lng), 'status' => $closed ? 'declassified' : 'active',
            'opened_at' => '1875-04-26', 'opened_precision' => 'day', 'closed_at' => $closed, 'closed_precision' => $closed ? 'day' : null]);
        $saillat = $station('saillat-chassenon', 'Saillat-Chassenon', 1.10, null);
        $angouleme = $station('angouleme', 'Angoulême', 1.25, '2018-03-13');
        $station('safari-train', 'Safari Train', 1.105, null, ['provenance' => ['kind' => 'excluded']]);
        foreach ([[$saillat, 8000], [$angouleme, 23000]] as [$s, $pk]) {
            DB::table('station_lines')->insert(['station_id' => $s->id, 'line_id' => $this->line->id, 'method' => 'sncf_pk', 'pk_m' => $pk,
                'first_year' => 1875, 'created_at' => now(), 'updated_at' => now()]);
        }
        foreach ([[$saillat, [1880 => 'active', 2010 => 'active', 2020 => 'active', 2030 => 'active']],
            [$angouleme, [1880 => 'active', 2010 => 'active', 2020 => 'uncertain', 2030 => 'declassified']]] as [$s, $states]) {
            foreach ($states as $y => $st) {
                DB::table('station_eras')->insert(['station_id' => $s->id, 'era_id' => $this->eras[$y]->id, 'status' => $st === 'uncertain' ? 'active' : $st,
                    'uncertain' => $st === 'uncertain', 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function test_other_names_without_duplicates_nor_another_line(): void
    {
        $this->get('/lines/ligne-test-610000')->assertOk()->assertInertia(fn (Assert $page) => $page
            // « Ligne Limoges-Bénédictins - Angoulême » = le titre ; « Ligne Angoulême-Limoges » et « Voie ferrée
            // Angoulême-Limoges » = « Ligne Limoges-Angoulême » ; le raccordement est la ligne 590306.
            ->where('line.alt_names', ['Ligne Limoges-Angoulême'])
            ->where('line.name_lang', null)
        );
        $this->withHeader('Accept-Language', 'en')->get('/lines/ligne-test-610000')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('line.name_lang', 'fr'));
    }

    public function test_one_length_and_technical_summary(): void
    {
        $this->get('/lines/ligne-test-610000')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('stats.length_greatest_km', null)
            ->where('stats.length_km', fn ($km) => $km > 19 && $km < 21)
            ->where('stats.active_length_km', fn ($km) => $km > 8 && $km < 8.5)
            // TVM d'abord (plus grande longueur), et pas deux fois : signalisation en cabine, pas « contrôle de vitesse ».
            ->where('stats.signalling', 'TVM-300, BAL')
            ->where('stats.train_protection', 'KVB')
            ->where('signalling.0.train_protection', ['KVB'])
            ->where('stats.electrification.state', 'marginal')
            ->where('stats.electrification.voltages.0.voltage_v', 1500)
            ->where('line.is_lgv', false)
            ->where('line.historical_operators.0.from_year', 1875)
            ->where('line.historical_operators.1.role', 'gestionnaire')
            ->where('line.historical_operators.1.ongoing', true)
        );
    }

    public function test_data_sources_have_valid_links_and_name_what_was_used(): void
    {
        $this->get('/lines/ligne-test-610000')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('sources', function ($sources) {
                $byType = collect($sources)->keyBy('type');
                $this->assertSame('https://ressources.data.sncf.com/', $byType['sncf-classification']['url']);
                $this->assertSame(['liste-des-gares', 'lignes-par-type', 'lignes-lgv'], $byType['sncf-classification']['datasets']);
                foreach (['osm', 'bdtopo', 'sncf-speed', 'sncf-electrification', 'sncf-signalling'] as $type) {
                    $this->assertTrue($byType->has($type), $type);
                    $this->assertMatchesRegularExpression('#^https://[^ ]+$#', $byType[$type]['url'], $type);
                }

                return true;
            })
        );
        // Aucun signal SNCF (ligne hors réseau ferré national) : pas de « classement SNCF » cité.
        DB::table('line_sources')->update(['fields_provided' => json_encode(['signal' => 'aucun'])]);
        $this->get('/lines/ligne-test-610000')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('sources', fn ($s) => ! collect($s)->pluck('type')->contains('sncf-classification')));
    }

    public function test_chronicle_is_written_for_readers(): void
    {
        $this->get('/lines/ligne-test-610000')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('chronicle', function ($c) {
                $events = collect($c['events'])->keyBy('type');
                $closure = $events['closure'];
                $this->assertSame('Saillat-Chassenon', $closure['section_from'], 'nom de la gare de la ligne');
                $this->assertSame('de Saillat-Chassenon à Angoulême', $closure['section_text']);
                $this->assertSame('Fermeture (tout trafic) de Saillat-Chassenon à Angoulême', $closure['description']);
                $this->assertSame('Date de l’article, section « Déclin ».', $closure['correction']);
                $this->assertSame('citation', $closure['quote_kind']);
                $this->assertStringContainsString('Saillat - Chassenon', $closure['quote'], 'citation intacte');
                $concession = $events['concession'];
                $this->assertSame('Exploitation sans concession : Administration des chemins de fer de l’État (infobox Wikipédia)', $concession['description']);
                $this->assertSame(['Infobox de l’article Wikipédia (concessionnaire : État (Non concédée) ; début de la concession : 1878)', 'data'],
                    [$concession['quote'], $concession['quote_kind']]);
                $groups = collect($c['groups'])->keyBy('key');
                $this->assertSame('Toute la ligne', $groups['whole']['title']);
                $this->assertSame('De Saillat-Chassenon à Angoulême', $groups['angouleme|saillat chassenon']['title']);
                $this->assertSame(15000, $groups['angouleme|saillat chassenon']['length_m'], 'écart des PK des deux gares');
                $this->assertContains('retained', $c['statuses']);

                return true;
            })
        );
        $this->withHeader('Accept-Language', 'en')->get('/lines/ligne-test-610000')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('chronicle.events', fn ($e) => collect($e)->firstWhere('type', 'closure')['correction_lang'] === 'fr'
                && collect($e)->firstWhere('type', 'closure')['description'] === 'Closure (all traffic) from Saillat-Chassenon to Angoulême'));
    }

    public function test_closures_per_decade_match_the_counts(): void
    {
        $byEra = collect(app(LineStations::class)->byEra($this->line->id))->keyBy('year');
        // Angoulême : fermée le 13/03/2018 (date retenue) mais « non confirmée » en 2020 : listée en 2030 avec le
        // compteur, jamais en 2020 où elle n'est pas comptée fermée.
        $this->assertSame([0, []], [$byEra[2020]['closed'], $byEra[2020]['closures']]);
        $this->assertSame([1, ['angouleme'], '2018-03-13'], [$byEra[2030]['closed'], array_column($byEra[2030]['closures'], 'slug'), $byEra[2030]['closures'][0]['closed_at']]);
    }

    public function test_station_neighbours_leave_out_excluded_records(): void
    {
        Station::factory()->create(['slug' => 'st-junien', 'name' => 'St-Junien', 'geom' => Point::makeGeodetic(45.80, 1.12)]);
        $this->get('/stations/saillat-chassenon')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('neighbors', fn ($n) => collect($n)->pluck('slug')->all() === ['st-junien'])
            ->where('station.description_lang', null));
        // Description en français sur la page anglaise : signalée pour l'attribut lang.
        $this->withHeader('Accept-Language', 'en')->get('/stations/saillat-chassenon')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('station.description_lang', 'fr'));
        Station::where('slug', 'saillat-chassenon')->update(['description' => null]);
        $this->withHeader('Accept-Language', 'de')->get('/stations/saillat-chassenon')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('station.description_lang', null));
    }

    public function test_known_traffic_heads_the_sheet_and_the_extract_keeps_its_numero_sign(): void
    {
        // B rouverte sans trafic renseigné (11,6 km) : le trafic connu de A (voyageurs et fret, 8 km) l'emporte.
        DB::table('line_segments')->where('line_id', $this->line->id)->where('era_id', $this->eras[2030]->id)->where('status', 'abandoned')
            ->update(['status' => 'in_service', 'traffic_type' => 'unknown']);
        $this->line->update(['description' => 'Elle constitue la ligne no 752 000 du réseau ferré national.']);
        $this->get('/lines/ligne-test-610000')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('line.current_traffic', 'mixed')
            ->where('line.description', 'Elle constitue la ligne n° 752 000 du réseau ferré national.'));
    }
}
