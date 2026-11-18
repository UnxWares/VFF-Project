<?php

namespace Tests\Feature\Web;

use App\Models\Station;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * Annuaire et fiche des gares : filtres (décennie, statut, département,
 * position signalée), compteurs par statut, pagination, redirection 301 des
 * anciens slugs, liens vers les sources, présence par décennie.
 */
class StationPagesTest extends TestCase
{
    use BuildsRailNetwork;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRailNetwork();
        $this->origin->update(['department_code' => '75', 'uic_code' => '87384008']);
        $this->destination->update(['department_code' => '78']);
    }

    private function stationEra(Station $station, int $eraId, string $status, bool $uncertain = false, ?string $note = null): void
    {
        DB::table('station_eras')->insert([
            'station_id' => $station->id, 'era_id' => $eraId, 'status' => $status, 'uncertain' => $uncertain,
            'note' => $note, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_index_lists_stations_with_filters_and_counts(): void
    {
        $gone = Station::factory()->create([
            'slug' => 'gare-disparue', 'name' => 'Gare disparue', 'status' => 'declassified', 'source_status' => 'gone',
            'department_code' => '78', 'position_flagged' => true, 'opened_at' => '1880-05-01', 'closed_at' => '1939-01-01',
            'geom' => Point::makeGeodetic(48.9, 2.0),
        ]);

        $this->get('/stations')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Stations/Index')
                ->where('stations.total', 3)
                ->where('stations.current_page', 1)
                ->where('stations.last_page', 1)
                // Tri alphabétique ; « gone » prime sur le statut stocké.
                ->where('stations.data.0.slug', 'gare-disparue')
                ->where('stations.data.0.status', 'gone')
                ->where('stations.data.0.opened_year', 1880)
                ->where('stations.data.0.closed_year', 1939)
                ->where('stations.data.0.position_flagged', true)
                ->where('stations.data.1.slug', 'paris-saint-lazare')
                ->where('counts', ['active' => 2, 'gone' => 1])
                ->where('eras', [1960, 2030])
                ->where('departments', ['75', '78'])
                ->where('filters', ['q' => '', 'status' => '', 'dept' => '', 'era' => null, 'flagged' => false, 'sort' => 'name']));

        $this->get('/stations?status=gone')->assertInertia(fn (Assert $page) => $page
            ->where('stations.total', 1)->where('stations.data.0.slug', $gone->slug)
            // Les compteurs ignorent le filtre de statut (facettes).
            ->where('counts', ['active' => 2, 'gone' => 1]));
        $this->get('/stations?dept=78')->assertInertia(fn (Assert $page) => $page
            ->where('stations.total', 2)->where('counts', ['active' => 1, 'gone' => 1])->where('filters.dept', '78'));
        $this->get('/stations?flagged=1')->assertInertia(fn (Assert $page) => $page
            ->where('stations.total', 1)->where('filters.flagged', true));
        $this->get('/stations?status=inconnu')->assertInertia(fn (Assert $page) => $page
            ->where('stations.total', 3)->where('filters.status', 'inconnu'));
    }

    public function test_era_filter_uses_station_eras(): void
    {
        $this->stationEra($this->origin, $this->pastEra->id, 'active');
        $this->stationEra($this->destination, $this->pastEra->id, 'declassified');

        $this->get('/stations?era=1960')->assertInertia(fn (Assert $page) => $page
            ->where('filters.era', 1960)
            ->where('stations.total', 2)
            ->where('counts', ['active' => 1, 'declassified' => 1]));

        $this->get('/stations?era=1960&status=declassified')->assertInertia(fn (Assert $page) => $page
            ->where('stations.total', 1)->where('stations.data.0.slug', 'versailles-rive-droite')
            ->where('stations.data.0.status', 'declassified'));

        // Décennie sans données de gares : liste vide, pas d'erreur.
        $this->get('/stations?era=2030')->assertInertia(fn (Assert $page) => $page
            ->where('filters.era', 2030)->where('stations.total', 0)->where('counts', []));
    }

    public function test_index_paginates_by_fifty_and_keeps_the_query_string(): void
    {
        Station::factory()->count(51)->create(['name' => 'Halte témoin', 'department_code' => '01']);

        $this->get('/stations?dept=01&page=2')->assertInertia(fn (Assert $page) => $page
            ->where('stations.total', 51)
            ->where('stations.current_page', 2)
            ->where('stations.last_page', 2)
            ->has('stations.data', 1));
    }

    public function test_show_exposes_station_timeline_lines_sources_and_neighbors(): void
    {
        $this->origin->update(['alt_names' => ['Saint-Lazare'], 'wikidata_id' => 'Q1', 'provenance' => ['opened_at' => 'wikidata']]);
        $this->stationEra($this->origin, $this->pastEra->id, 'active', true, 'Ouverte selon Wikidata');
        $this->stationEra($this->origin, $this->currentEra->id, 'active');
        DB::table('station_events')->insert([
            ['station_id' => $this->origin->id, 'type' => 'opening', 'event_date' => '1837-08-26', 'date_precision' => 'day',
                'source' => 'wikidata', 'source_url' => 'https://www.wikidata.org/wiki/Q1', 'is_inferred' => false, 'note' => null,
                'created_at' => now(), 'updated_at' => now()],
            ['station_id' => $this->origin->id, 'type' => 'reopening', 'event_date' => '1900-01-01', 'date_precision' => 'year',
                'source' => 'inferred:line_event', 'source_url' => null, 'is_inferred' => true, 'note' => 'Déduit',
                'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('station_lines')->insert([
            'station_id' => $this->origin->id, 'line_id' => $this->line->id, 'method' => 'line_stops', 'pk_m' => 0,
            'pk_source' => 'sncf', 'first_year' => 1837, 'last_year' => null, 'years_source' => 'line_events',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $sources = [
            ['wikidata', 'Q1', null, 'https://www.wikidata.org/wiki/Q1'],
            ['osm', 'n123', null, 'https://www.openstreetmap.org/node/123'],
            ['ohm', 'w45', null, 'https://www.openhistoricalmap.org/way/45'],
            ['ohm', 'r7', null, 'https://www.openhistoricalmap.org/relation/7'],
            ['sncf', '87384008', null, 'https://ressources.data.sncf.com/explore/dataset/liste-des-gares/table/?q=87384008'],
            ['sncf_voyageurs', 'gv-1', null, 'https://ressources.data.sncf.com/explore/dataset/gares-de-voyageurs/table/?q=Paris+Saint-Lazare'],
            ['lignes_oubliees', 'lo-9', 'https://example.org/fiche', 'https://example.org/fiche'],
        ];
        foreach ($sources as [$source, $ref, $url]) {
            DB::table('station_sources')->insert([
                'source' => $source, 'source_ref' => $ref, 'station_id' => $this->origin->id, 'name' => 'Paris Saint-Lazare',
                'lifecycle' => 'active', 'properties' => json_encode($url ? ['source_url' => $url] : []),
                'geom' => DB::raw('ST_SetSRID(ST_MakePoint(2.3253, 48.8763), 4326)'),
                'seen_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->get('/stations/paris-saint-lazare')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Stations/Show')
                ->where('station.name', 'Paris-Saint-Lazare')
                ->where('station.alt_names', ['Saint-Lazare'])
                ->where('station.opened_at', '1837-08-26')
                ->where('station.closed_at', null)
                ->where('station.uic_code', '87384008')
                ->where('station.provenance', ['opened_at' => 'wikidata'])
                ->where('station.lat', 48.8763)
                ->where('station.lng', 2.3253)
                ->where('eras', [
                    // Requête de test en anglais (Accept-Language par défaut) : note libre non générée, restée en français et signalée.
                    ['year' => 1960, 'is_current' => false, 'status' => 'uncertain', 'note' => 'Ouverte selon Wikidata', 'note_lang' => 'fr', 'undated' => false],
                    ['year' => 2030, 'is_current' => true, 'status' => 'active', 'note' => null, 'note_lang' => null, 'undated' => false],
                ])
                ->has('events', 2)
                ->where('events.0.type', 'opening')
                ->where('events.0.inferred', false)
                ->where('events.1.inferred', true)
                ->where('events.1.precision', 'year')
                ->has('lines', 1)
                ->where('lines.0.slug', $this->line->slug)
                ->where('lines.0.method', 'line_stops')
                ->where('lines.0.first_year', 1837)
                ->where('lines.0.last_year', null)
                ->where('lines.0.active_current', true)
                ->where('lines.0.era_years', [2030])
                // Ordre : SNCF, SNCF voyageurs, OSM, Wikidata, OHM, autres ; lien selon la source.
                ->where('sources', fn ($s) => $s->pluck('url')->all() === [
                    'https://ressources.data.sncf.com/explore/dataset/liste-des-gares/table/?q=87384008',
                    'https://ressources.data.sncf.com/explore/dataset/gares-de-voyageurs/table/?q=Paris+Saint-Lazare',
                    'https://www.openstreetmap.org/node/123',
                    'https://www.wikidata.org/wiki/Q1',
                    'https://www.openhistoricalmap.org/way/45',
                    'https://www.openhistoricalmap.org/relation/7',
                    'https://example.org/fiche',
                ] && $s->every(fn ($row) => $row['distance_m'] === 0))
                ->has('neighbors', 0));
    }

    public function test_show_falls_back_to_nearby_segments_when_not_attached(): void
    {
        $this->get('/stations/versailles-rive-droite')->assertInertia(fn (Assert $page) => $page
            ->where('lines.0.slug', $this->line->slug)
            ->where('lines.0.method', 'segment')
            ->where('lines.0.era_years', [])
            ->where('lines.0.active_current', true)
            ->where('eras', [
                ['year' => 1960, 'is_current' => false, 'status' => null, 'note' => null, 'note_lang' => null, 'undated' => false],
                ['year' => 2030, 'is_current' => true, 'status' => null, 'note' => null, 'note_lang' => null, 'undated' => false],
            ]));
    }

    public function test_era_without_closure_date_is_flagged_undated_for_the_timeline_legend(): void
    {
        // Ère incertaine faute de date de fermeture (StationTimeline::unconfirmedNote) : seule celle-ci est « non datée ».
        $this->stationEra($this->origin, $this->pastEra->id, 'active', true, 'Fermeture non datée (après 1950)');
        $this->stationEra($this->origin, $this->currentEra->id, 'closed', false, 'Fermeture non datée (après 1950)');

        $this->get('/stations/paris-saint-lazare')->assertInertia(fn (Assert $page) => $page
            ->where('eras.0.status', 'uncertain')
            ->where('eras.0.undated', true)
            ->where('eras.1.status', 'closed')
            ->where('eras.1.undated', false));
    }

    public function test_gone_station_is_shown_as_gone(): void
    {
        $this->destination->update(['source_status' => 'gone']);

        $this->get('/stations/versailles-rive-droite')->assertInertia(fn (Assert $page) => $page
            ->where('station.status', 'gone')->where('station.source_status', 'gone'));
    }

    public function test_neighbors_within_ten_km_ordered_by_distance(): void
    {
        $near = Station::factory()->create(['slug' => 'proche', 'name' => 'Proche', 'geom' => Point::makeGeodetic(48.8800, 2.3300)]);
        $far = Station::factory()->create(['slug' => 'plus-loin', 'name' => 'Plus loin', 'geom' => Point::makeGeodetic(48.9000, 2.3500)]);
        Station::factory()->create(['slug' => 'hors-rayon', 'name' => 'Hors rayon', 'geom' => Point::makeGeodetic(49.5, 2.3)]);

        $this->get('/stations/paris-saint-lazare')->assertInertia(fn (Assert $page) => $page
            ->has('neighbors', 2)
            ->where('neighbors.0.slug', $near->slug)
            ->where('neighbors.1.slug', $far->slug)
            ->where('neighbors.0.distance_m', fn ($d) => $d > 400 && $d < 700));
    }

    public function test_old_slug_redirects_permanently_to_the_canonical_one(): void
    {
        DB::table('station_redirects')->insert([
            'old_slug' => 'osm-12345-paris-saint-lazare', 'station_id' => $this->origin->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->get('/stations/osm-12345-paris-saint-lazare')
            ->assertStatus(301)
            ->assertRedirect('/stations/paris-saint-lazare');

        $this->get('/stations/osm-99999-inconnue')->assertNotFound();
        $this->get('/stations/'.rawurlencode("x' OR '1'='1"))->assertNotFound();
    }
}
