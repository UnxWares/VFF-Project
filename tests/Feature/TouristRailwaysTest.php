<?php

namespace Tests\Feature;

use App\Models\Line;
use App\Services\TouristRailways;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * « Chemins de fer touristiques et vélorails » : exploitations lues dans line_events (import heritage) croisées avec le
 * catalogue Wikipédia, voies vertes, page /tourist-railways et `GET /api/v1/tourist-railways`.
 */
class TouristRailwaysTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function line(string $slug, array $extra = []): Line
    {
        return Line::factory()->create(['slug' => $slug, 'name' => 'Ligne de '.$slug, 'regions' => json_encode(['Normandie']), 'gauge_mm_default' => 1435] + $extra);
    }

    private function heritage(Line $line, string $type, string $kind, string $date, string $description, int $length = 5000, string $source = 'heritage'): void
    {
        DB::table('line_events')->insert([
            'line_id' => $line->id, 'type' => $type, 'heritage_kind' => $kind, 'event_date' => $date, 'description' => $description,
            'source' => $source, 'section_length_m' => $length, 'confidence' => 'medium', 'date_precision' => 'year',
            'fingerprint' => $source === 'heritage' ? 'heritage:'.md5($line->slug.$type.$date.$description) : null,
            'source_url' => 'https://fr.wikipedia.org/wiki/Test', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** @return array<string, array<string, mixed>> exploitations par nom */
    private function railways(): array
    {
        $out = [];
        foreach (app(TouristRailways::class)->build()['railways'] as $r) {
            $out[$r['name']] = $r;
        }

        return $out;
    }

    public function test_a_running_operation_gets_its_length_year_lines_and_sources(): void
    {
        $a = $this->line('rfn-1');
        $b = $this->line('rfn-2');
        $this->heritage($a, 'heritage_start', 'tourist_train', '1986-01-01', 'Exploitation associative : Chemin de fer du Haut Forez (exploitant : CFHF), train touristique entre A et B, depuis 1986 (d\'après article Wikipédia de l\'exploitation) ; aussi nommé « X ». Sources : Wikipédia (liste), IGN BD TOPO.', 30000);
        $this->heritage($b, 'heritage_start', 'tourist_train', '1986-01-01', 'Exploitation associative : Chemin de fer du Haut Forez, train touristique entre B et C, depuis 1986 (d\'après article Wikipédia de l\'exploitation). Sources : IGN BD TOPO.', 13000);

        $r = $this->railways()['Chemin de fer du Haut Forez'];

        $this->assertSame('both', $r['origin']);
        $this->assertSame(43000, $r['length_m']);
        $this->assertSame(1986, $r['start_year']);
        $this->assertSame('CFHF', $r['operator']);
        $this->assertSame(['rfn-1', 'rfn-2'], array_column($r['lines'], 'slug'));
        $this->assertSame('ign', $r['sources']['length']);
        $this->assertSame('wp-article', $r['sources']['start_year']);
        $this->assertSame('wp-list', $r['sources']['gauge']);
        $this->assertSame(1435, $r['gauge_mm']);
        $this->assertSame(['Auvergne-Rhône-Alpes'], $r['regions']);
    }

    public function test_an_ended_operation_is_not_listed_but_a_resumed_one_is_with_its_first_start(): void
    {
        $ended = $this->line('rfn-3');
        $this->heritage($ended, 'heritage_start', 'velorail', '2005-01-01', 'Exploitation associative : Vélorail de Test, vélorail, depuis 2005 (d\'après article Wikipédia de la ligne). Sources : IGN BD TOPO.');
        $this->heritage($ended, 'heritage_end', 'velorail', '2012-01-01', 'Fin de l\'exploitation associative : Vélorail de Test, vélorail, en 2012 (d\'après article Wikipédia de la ligne).');

        $resumed = $this->line('rfn-4');
        $this->heritage($resumed, 'heritage_start', 'tourist_train', '1969-06-14', 'Exploitation associative : Train de Reprise, train touristique, depuis le 14 juin 1969 (d\'après article Wikipédia de l\'exploitation). Sources : IGN BD TOPO.', 28000);
        $this->heritage($resumed, 'heritage_end', 'tourist_train', '2008-01-01', 'Interruption de l\'exploitation associative : Train de Reprise, train touristique, en 2008 (d\'après l\'article Wikipédia de l\'exploitation).', 0);
        $this->heritage($resumed, 'heritage_start', 'tourist_train', '2013-07-02', 'Reprise de l\'exploitation associative : Train de Reprise, train touristique, le 2 juillet 2013 (d\'après l\'article Wikipédia de l\'exploitation).', 28000);

        $all = $this->railways();

        $this->assertArrayNotHasKey('Vélorail de Test', $all);
        $this->assertSame(1969, $all['Train de Reprise']['start_year']);
        $this->assertSame(28000, $all['Train de Reprise']['length_m'], 'une reprise rouvre la portion déjà comptée');
        $this->assertSame('database', $all['Train de Reprise']['origin']);
    }

    public function test_an_attested_operation_has_no_start_year_unless_a_correction_gives_one(): void
    {
        $line = $this->line('rfn-5');
        $this->heritage($line, 'heritage_start', 'velorail', '2026-01-01', 'Exploitation associative : Vélorail des Essais, vélorail, en activité en 2026 (date de début non trouvée ; attestation : IGN BD TOPO). Sources : IGN BD TOPO.');
        $this->assertNull($this->railways()['Vélorail des Essais']['start_year']);
        $this->assertNull($this->railways()['Vélorail des Essais']['sources']['start_year']);

        $this->heritage($line, 'heritage_start', 'velorail', '2010-05-01', 'Début du vélorail des Essais (correction sourcée : vélorail ouvert en 2010)', 0, 'manual');
        Cache::flush();

        $this->assertSame(2010, $this->railways()['Vélorail des Essais']['start_year']);
        $this->assertSame('correction', $this->railways()['Vélorail des Essais']['sources']['start_year']);
    }

    public function test_a_tourist_train_named_like_a_rail_bike_counts_as_a_rail_bike(): void
    {
        $line = $this->line('rfn-6');
        $this->heritage($line, 'heritage_start', 'tourist_train', '2001-01-01', 'Exploitation associative : Vélorail du Larzac, train touristique, depuis 2001 (d\'après article Wikipédia de l\'exploitation). Sources : IGN BD TOPO.', 8200);

        $this->assertSame('velorail', $this->railways()['Vélorail du Larzac']['type']);
    }

    public function test_the_section_season_and_article_version_of_the_catalogue_are_served(): void
    {
        $r = $this->railways()['Train Thur Doller Alsace'];

        $this->assertSame(['from' => 'Cernay-Saint-André', 'to' => 'Sentheim'], $r['section']);
        $this->assertStringContainsString('oldid=238310486', $r['detail_source_url']);
        $this->assertNull($r['opened_on']);
        $this->assertSame('d\'avril à octobre', $this->railways()['Le p\'tit train de Saint-Trojan']['season']);
        $this->assertSame('2012-06-28', $this->railways()['Panoramique des Dômes']['opened_on']);
        $this->assertNull($this->railways()['Vélo-rail du Grand Morin']['section']);
    }

    public function test_a_catalogue_operation_without_a_database_match_is_listed_without_length(): void
    {
        $r = $this->railways()['Chemin de fer touristique de la Sarthe'];

        $this->assertSame('catalogue', $r['origin']);
        $this->assertNull($r['length_m']);
        $this->assertNull($r['sources']['length']);
        $this->assertSame(['Pays de la Loire'], $r['regions']);
        $this->assertSame('wp-list', $r['sources']['region']);
    }

    public function test_a_catalogue_operation_gets_its_line_page_through_the_wikipedia_title(): void
    {
        $this->line('rfn-7', ['name' => 'Ligne de Mamers à Saint-Calais', 'wikipedia_title' => 'Chemin de fer Mamers - Saint-Calais']);

        $r = $this->railways()['Chemin de fer touristique de la Sarthe'];

        $this->assertSame(['rfn-7'], array_column($r['lines'], 'slug'));
    }

    public function test_the_same_database_operation_is_never_given_to_two_catalogue_names(): void
    {
        $line = $this->line('rfn-8');
        $this->heritage($line, 'heritage_start', 'tourist_train', '1982-01-01', 'Exploitation associative : Train à vapeur des Cévennes, train touristique, depuis 1982 (d\'après article Wikipédia de l\'exploitation). Sources : IGN BD TOPO.', 13700);

        $all = app(TouristRailways::class)->build()['railways'];
        $withLength = array_values(array_filter($all, fn (array $r): bool => $r['length_m'] === 13700));

        $this->assertCount(1, $withLength);
        $this->assertSame('Train à vapeur des Cévennes', $withLength[0]['name']);
    }

    public function test_greenways_of_five_kilometres_or_more_are_listed_with_their_line(): void
    {
        $line = $this->line('rfn-9');
        foreach ([[1, 'Voie verte du Test', 6000], [2, 'Trop courte', 1500]] as [$id, $name, $m]) {
            DB::statement(
                "INSERT INTO line_greenways (line_id, osm_way_id, name, display_name, highway, shared_length_m, way_length_m, shared_percent, source, osm_seen_at, geom)
                 VALUES (?, ?, ?, ?, 'cycleway', ?, ?, 90, 'osm', now(), ST_GeomFromText('MULTILINESTRING((2 48, 2.1 48.1))', 4326))",
                [$line->id, $id, $name, $name, $m, $m],
            );
        }

        $greenways = app(TouristRailways::class)->build()['greenways'];

        $this->assertSame(['Voie verte du Test'], array_column($greenways, 'name'));
        $this->assertSame(6000, $greenways[0]['length_m']);
        $this->assertSame('rfn-9', $greenways[0]['line']['slug']);
        $this->assertSame(['Normandie'], $greenways[0]['regions']);
    }

    public function test_the_page_renders_with_the_data(): void
    {
        $this->get('/tourist-railways')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Network/TouristRailways')
            ->has('data.railways')
            ->has('data.greenways')
            ->has('data.regions')
            ->has('data.sources.wp-list.label'));
    }

    public function test_the_api_returns_the_same_data_and_filters_by_type_and_region(): void
    {
        $all = $this->getJson('/api/v1/tourist-railways')->assertOk();
        $this->assertSame(count($all->json('data.railways')), $all->json('meta.railways'));
        $this->assertNotEmpty($all->json('data.sources'));

        $velo = $this->getJson('/api/v1/tourist-railways?type=velorail&region=Occitanie')->assertOk();
        $this->assertNotEmpty($velo->json('data.railways'));
        foreach ($velo->json('data.railways') as $r) {
            $this->assertSame('velorail', $r['type']);
            $this->assertContains('Occitanie', $r['regions']);
        }
        $this->assertSame([], $velo->json('data.greenways'));
        $this->assertStringEndsWith('/tourist-railways', $velo->json('links.web'));
    }

    public function test_the_api_rejects_an_unknown_type(): void
    {
        $this->getJson('/api/v1/tourist-railways?type=zeppelin')->assertStatus(422)->assertJsonValidationErrors('type');
    }

    public function test_the_page_is_in_the_sitemap(): void
    {
        $this->get('/sitemap-pages-1.xml')->assertOk()->assertSee('/tourist-railways', false);
    }
}
