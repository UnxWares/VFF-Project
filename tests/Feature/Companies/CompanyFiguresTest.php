<?php

namespace Tests\Feature\Companies;

use App\Models\Line;
use App\Models\LineEvent;
use App\Models\LineSegment;
use App\Services\Companies\CompanyFigures;
use Clickbar\Magellan\Data\Geometries\LineString;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * Fiches des compagnies : rattachement des lignes par événement de concession daté (jamais deviné), intervalle et
 * raison de la fin, longueurs par ère, couverture, lecture en cache sans calcul, pages, API REST et lien des fiches
 * de lignes.
 *
 * Mini-réseau : la ligne A (SNCF dès 1938), la ligne C (Nord dès 1870, puis SNCF dès 1938), la ligne B (Est de 1860
 * à 1937, sans tracé), la ligne D (un concessionnaire inconnu), la ligne E (État avant sa création), la ligne F (CFD).
 */
class CompanyFiguresTest extends TestCase
{
    use BuildsRailNetwork;
    use RefreshDatabase;

    private Line $c;

    private Line $b;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        CompanyFigures::forget();
        $this->buildRailNetwork();
        $this->concession($this->line, 'SNCF', '1938-01-01');

        $this->c = Line::factory()->create(['slug' => 'ligne-c', 'name' => 'Ligne C', 'code' => '777 000']);
        $geom = LineString::make([Point::makeGeodetic(48.90, 2.10), Point::makeGeodetic(48.90, 2.30)]);
        foreach ([$this->pastEra, $this->currentEra] as $era) {
            LineSegment::factory()->create(['line_id' => $this->c->id, 'era_id' => $era->id, 'geom' => $geom, 'status' => 'in_service', 'source' => 'osm']);
        }
        $this->concession($this->c, 'Nord', '1870-01-01');
        $this->concession($this->c, 'SNCF', '1938-01-01');

        $this->b = Line::factory()->create(['slug' => 'ligne-b', 'name' => 'Ligne B', 'code' => '666 000']);
        $this->concession($this->b, 'Est', '1860-01-01');
        $this->concession($this->b, 'Est', '1937-01-01', end: true);

        $this->concession(Line::factory()->create(['slug' => 'ligne-d', 'name' => 'Ligne D']), 'Dubus et Debains', '1873-01-01');
        $this->concession(Line::factory()->create(['slug' => 'ligne-e', 'name' => 'Ligne E']), 'État (Non concédée)', '1830-01-01');
        $this->concession(Line::factory()->create(['slug' => 'ligne-f', 'name' => 'Ligne F']), 'CFD', '1890-01-01');
    }

    private function concession(Line $line, string $label, string $date, bool $end = false): void
    {
        LineEvent::create([
            'line_id' => $line->id, 'type' => $end ? 'concession_end' : 'concession', 'event_date' => $date, 'date_precision' => 'year',
            'description' => ($end ? "Fin de concession de {$label}" : "Concession à {$label}").' (infobox Wikipédia)',
            'source' => 'wikipedia', 'confidence' => 'medium',
            'fingerprint' => md5($line->id.$label.$date.$end),
        ]);
    }

    private function compute(): array
    {
        return (new CompanyFigures)->refresh();
    }

    public function test_labels_are_normalised_before_they_are_matched(): void
    {
        $this->assertSame('etat', CompanyFigures::normalize('État (Non concédée)'));
        $this->assertSame('ch de fer de l est', CompanyFigures::normalize('Ch. de fer de l’Est'));
        $this->assertSame('sncf', CompanyFigures::normalize('• SNCF [source]'));
        $this->assertSame('plm', CompanyFigures::normalize('PLM (France)'));
    }

    public function test_every_company_of_the_data_file_is_sourced_and_consistent(): void
    {
        $slugs = [];
        foreach (CompanyFigures::definitions() as $c) {
            $slugs[] = $c['slug'];
            $this->assertNotEmpty($c['sources'], $c['slug'].' : sources');
            $this->assertNotEmpty($c['events'], $c['slug'].' : événements');
            foreach (['fr', 'en', 'de'] as $lang) {
                $this->assertNotEmpty($c['summary'][$lang] ?? null, $c['slug']." : résumé {$lang}");
                foreach ($c['events'] as $e) {
                    $this->assertNotEmpty($e[2][$lang] ?? null, $c['slug']." : événement {$lang}");
                }
            }
            $this->assertContains($c['kind'], ['grande-compagnie', 'etat', 'sncf', 'infrastructure', 'secondaire']);
            $this->assertContains($c['end_policy'], ['company_end', 'open', 'unknown']);
            $this->assertTrue($c['end_policy'] !== 'company_end' || $c['ended'] !== null, $c['slug'].' : fin de compagnie sans date de fin');
            $this->assertTrue($c['end_policy'] !== 'open' || $c['ended'] === null, $c['slug'].' : sans fin mais disparue');
        }
        $this->assertSame($slugs, array_values(array_unique($slugs)));
        foreach (CompanyFigures::definitions() as $c) {
            foreach ([...$c['predecessors'], ...$c['successors']] as $other) {
                $this->assertContains($other, $slugs, $c['slug']." : voisin {$other}");
            }
        }
    }

    public function test_lines_are_attached_by_dated_events_only(): void
    {
        $result = $this->compute();
        $sncf = array_column($result['sheets']['sncf']['lines'], null, 'slug');
        $this->assertEqualsCanonicalizing(['paris-saint-lazare-versailles-rd', 'ligne-c'], array_keys($sncf));
        $this->assertSame('open', $sncf['ligne-c']['to_basis']);
        $this->assertSame(1938, $sncf['ligne-c']['from_year']);

        // Le Nord de la ligne C s'arrête à la concession suivante de la ligne (SNCF, 1938).
        $nord = $result['sheets']['nord']['lines'][0];
        $this->assertSame(['ligne-c', 1870, 1938, 'successor'], [$nord['slug'], $nord['from_year'], $nord['to_year'], $nord['to_basis']]);

        // L'Est de la ligne B s'arrête à l'événement de fin de concession.
        $est = $result['sheets']['est']['lines'][0];
        $this->assertSame(['ligne-b', 1860, 1937, 'event'], [$est['slug'], $est['from_year'], $est['to_year'], $est['to_basis']]);

        // Concessionnaire inconnu : rattaché à rien ; événement antérieur à la création de l'État : ignoré.
        $this->assertSame([], $result['sheets']['etat']['lines']);
        $quality = $result['index']['quality'];
        $this->assertSame(1, $quality['before_founding']);
        $this->assertSame(1, $quality['unmatched_total']);
        $this->assertSame(['dubus et debains' => 1], $quality['unmatched_labels']);
        $this->assertSame(1, $quality['end_event']);
        $this->assertSame(1, $quality['end_successor']);
    }

    public function test_a_line_without_a_known_end_is_listed_but_never_counted(): void
    {
        $sheet = $this->compute()['sheets']['cfd'];
        $this->assertSame('unknown', $sheet['lines'][0]['to_basis']);
        $this->assertNull($sheet['peak']);
        $this->assertSame(1, $sheet['summary']['lines']);
        $this->assertSame(0, $sheet['summary']['lines_counted']);
    }

    public function test_network_by_decade_counts_the_open_track_of_attached_lines(): void
    {
        $sheet = $this->compute()['sheets']['sncf'];
        $this->assertSame([1960, 2030], array_column($sheet['decades'], 'year'));
        [$then, $now] = $sheet['decades'];
        $this->assertSame(2, $then['lines']);
        $this->assertGreaterThan(25, $then['km']);
        $this->assertSame($then['km'], $now['km']);
        $this->assertSame(1960, $sheet['peak']['year']);
        $this->assertSame(2, $sheet['map']['lines']);
        $this->assertNotEmpty($sheet['map']['paths']);
        $this->assertCount(4, $sheet['map']['bbox']);
        // Une compagnie disparue avant la première carte n'a ni courbe ni carte.
        $this->assertNull($this->compute()['sheets']['nord']['peak']);
    }

    public function test_coverage_counts_the_open_kilometres_attached_to_a_company(): void
    {
        $coverage = array_column($this->compute()['index']['coverage'], null, 'year');
        $this->assertSame(2, $coverage[1960]['lines_open']);
        $this->assertSame(2, $coverage[1960]['lines_attached']);
        $this->assertSame(1.0, $coverage[1960]['share']);
    }

    public function test_reading_the_cache_gives_nothing_before_the_computation(): void
    {
        $figures = new CompanyFigures;
        $this->assertNull($figures->index());
        $this->assertNull($figures->sheet('sncf', 'fr'));
        $this->assertSame([], CompanyFigures::forLine($this->c->id));
    }

    public function test_sheet_texts_follow_the_locale_and_name_their_neighbours(): void
    {
        $this->compute();
        $en = (new CompanyFigures)->sheet('nord', 'en');
        $this->assertSame('The company is created.', $en['events'][0]['text']);
        $this->assertStringStartsWith('Company of northern France', $en['company']['summary']);
        $fr = (new CompanyFigures)->sheet('nord', 'fr');
        $this->assertSame('Création de la compagnie.', $fr['events'][0]['text']);
        $this->assertSame([['slug' => 'sncf', 'short' => 'SNCF']], $fr['successors']);
        $this->assertSame(['nord', 'est', 'paris-orleans', 'plm', 'midi', 'etat', 'alsace-lorraine'], array_column((new CompanyFigures)->sheet('sncf', 'fr')['predecessors'], 'slug'));
    }

    public function test_the_companies_of_a_line_come_from_the_cache_in_date_order(): void
    {
        $this->compute();
        $slugs = array_column(CompanyFigures::forLine($this->c->id), 'slug');
        $this->assertSame(['nord', 'sncf'], $slugs);
        $this->assertSame([], CompanyFigures::forLine(999999));
    }

    public function test_the_index_page_and_a_sheet_page_render_from_the_cache(): void
    {
        $this->get('/companies')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Companies/Index')->where('figures', null));
        $this->get('/companies/sncf')->assertNotFound();

        $this->compute();
        $this->get('/companies')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Companies/Index')->has('figures.companies', 14)->has('figures.coverage'));
        $this->get('/companies/sncf')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Companies/Show')->where('company.slug', 'sncf')->has('lines', 2)->has('decades', 2)->has('events')->where('map_info.lines', 2)->where('lines_total', 2));
        $this->get('/companies/inconnue')->assertNotFound();
    }

    public function test_the_map_and_the_other_lines_are_served_apart_from_the_page(): void
    {
        $this->compute();
        $map = $this->getJson('/companies/sncf/map.json')->assertOk()->json();
        $this->assertSame(1960, $map['year']);
        $this->assertNotEmpty($map['paths']);
        $this->getJson('/companies/sncf/lines.json')->assertOk()->assertExactJson([]);
        $this->getJson('/companies/nord/map.json')->assertNotFound();
        $this->getJson('/companies/inconnue/lines.json')->assertNotFound();
        // Débit limité comme l'API (les deux adresses sont lues sans passer par la page).
        $this->getJson('/companies/sncf/map.json')->assertHeader('X-RateLimit-Limit');
        $this->getJson('/companies/sncf/lines.json')->assertHeader('X-RateLimit-Limit');
        $this->assertArrayNotHasKey('map', (new CompanyFigures)->sheet('sncf', 'fr'));
        $this->assertNotEmpty($this->getJson('/api/v1/companies/sncf')->json('data.map.paths'));
    }

    public function test_the_line_page_links_to_its_companies(): void
    {
        $this->compute();
        $this->get('/lines/ligne-c')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('companies.0.slug', 'nord')->where('companies.1.slug', 'sncf'));
    }

    public function test_rest_api_lists_and_details_the_companies(): void
    {
        $this->getJson('/api/v1/companies')->assertStatus(503);
        $this->getJson('/api/v1/companies/sncf')->assertStatus(503);

        $this->compute();
        $list = $this->getJson('/api/v1/companies')->assertOk()->json();
        $this->assertCount(14, $list['data']);
        $this->assertSame(url('/companies/nord'), array_column($list['data'], null, 'slug')['nord']['links']['page']);
        $this->assertNotEmpty($list['meta']['coverage']);

        $one = $this->getJson('/api/v1/companies/sncf')->assertOk()->json('data');
        $this->assertSame('sncf', $one['company']['slug']);
        $this->assertCount(2, $one['lines']);
        $this->assertArrayHasKey('to_basis', $one['lines'][0]);
        $this->assertArrayNotHasKey('summary', $one);
        $this->getJson('/api/v1/companies/inconnue')->assertNotFound();
        $this->getJson('/api/v1/companies/NOT_A_SLUG!')->assertNotFound();
    }

    public function test_the_command_dry_run_computes_without_touching_the_cache(): void
    {
        $this->artisan('vff:compute-company-figures', ['--dry-run' => true])->assertSuccessful();
        $this->assertNull((new CompanyFigures)->index());
        $this->artisan('vff:compute-company-figures')->assertSuccessful();
        $this->assertNotNull((new CompanyFigures)->index());
    }
}
