<?php

namespace Tests\Feature\Web;

use App\Models\Era;
use App\Models\Line;
use App\Models\LineSegment;
use App\Models\Station;
use App\Services\LineDirectory;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\ReadsInertiaProps;
use Tests\TestCase;

/**
 * Annuaires /lines et /stations : ordre alphabétique français (accents et capitales sans effet),
 * correspondance exacte en tête d'une recherche, tris au choix, chapeau non filtré par la recherche,
 * dates déduites ou non datées exposées à la page, lignes desservant chaque gare.
 */
class DirectoryIndexTest extends TestCase
{
    use ReadsInertiaProps;
    use RefreshDatabase;

    public function test_lines_are_sorted_in_french_alphabetical_order(): void
    {
        foreach (['Desserte du port', 'DESSERTE d\'Aix', 'Déviation', 'Dépôt de Longwy', 'ardoisières', 'aérodrome', 'Zone'] as $name) {
            Line::factory()->create(['name' => $name, 'code' => null, 'historical_operator' => null]);
        }

        $this->get('/lines')->assertInertia(fn (Assert $page) => $page
            ->where('lines.data', fn ($data) => $this->rows($data)->pluck('name')->all() === [
                'aérodrome', 'ardoisières', 'Dépôt de Longwy', 'DESSERTE d\'Aix', 'Desserte du port', 'Déviation', 'Zone',
            ]));

        $this->get('/lines?sort=name-desc')->assertInertia(fn (Assert $page) => $page
            ->where('filters.sort', 'name-desc')
            ->where('lines.data.0.name', 'Zone'));
    }

    public function test_lines_sorted_by_code_put_missing_codes_last(): void
    {
        Line::factory()->create(['name' => 'Sans code', 'code' => null, 'historical_operator' => null]);
        Line::factory()->create(['name' => 'B', 'code' => '002000', 'historical_operator' => null]);
        Line::factory()->create(['name' => 'A', 'code' => '001000', 'historical_operator' => null]);

        $this->get('/lines?sort=code')->assertInertia(fn (Assert $page) => $page
            ->where('lines.data', fn ($data) => $this->rows($data)->pluck('name')->all() === ['A', 'B', 'Sans code']));
    }

    public function test_unknown_sort_falls_back_to_name(): void
    {
        $this->get('/lines?sort=drop')->assertInertia(fn (Assert $page) => $page->where('filters.sort', 'name'));
        $this->get('/stations?sort[]=x')->assertInertia(fn (Assert $page) => $page->where('filters.sort', 'name'));
    }

    public function test_exact_line_name_comes_first_in_a_search(): void
    {
        foreach (['Ligne de Vire à Romagny', 'Embranchement de Vire', 'Vire', 'Zone de Vire'] as $name) {
            Line::factory()->create(['name' => $name, 'code' => null, 'historical_operator' => null]);
        }

        $this->get('/lines?q=vire')->assertInertia(fn (Assert $page) => $page
            ->where('lines.data', fn ($data) => $this->rows($data)->pluck('name')->all() === [
                'Vire', 'Embranchement de Vire', 'Ligne de Vire à Romagny', 'Zone de Vire',
            ]));
    }

    public function test_overview_is_not_filtered_by_the_search(): void
    {
        Line::factory()->create(['name' => 'Alpha', 'code' => null, 'historical_operator' => null]);
        Line::factory()->create(['name' => 'Beta', 'code' => null, 'historical_operator' => null]);

        $this->get('/lines?q=alpha')->assertInertia(fn (Assert $page) => $page
            ->where('lines.total', 1)
            ->where('overview.total', 2)
            ->where('overview.unknown', 2));
    }

    public function test_overview_counts_states_in_one_query_like_the_unfiltered_facets(): void
    {
        $current = Era::factory()->current()->create(['year' => 2030]);
        $past = Era::factory()->create(['year' => 1930]);
        $open = Line::factory()->create(['name' => 'Ouverte']);
        LineSegment::factory()->for($open)->for($current)->inService()->create();
        LineSegment::factory()->for($open)->for($current)->abandoned()->create();
        LineSegment::factory()->for(Line::factory()->create(['name' => 'Fermée']))->for($current)->abandoned()->create();
        LineSegment::factory()->for(Line::factory()->create(['name' => 'Ancienne']))->for($past)->inService()->create();
        Line::factory()->create(['name' => 'Sans tracé']);
        $directory = app(LineDirectory::class);

        DB::enableQueryLog();
        $overview = $directory->overview($current->id);
        $this->assertCount(1, DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(['total' => 4, 'open' => 1, 'closed' => 1, 'unknown' => 2], $overview);
        $this->assertSame(array_intersect_key($directory->facets([], $current->id), $overview), $overview);
        $this->assertSame(['total' => 4, 'open' => 1, 'closed' => 0, 'unknown' => 3], $directory->overview($past->id));

        $this->get('/lines?q=ferm')->assertInertia(fn (Assert $page) => $page
            ->where('lines.total', 1)
            ->where('overview', $overview)
            ->where('stats.total', 1)
            ->where('stats.closed', 1));
    }

    public function test_stations_sorted_in_french_order_with_exact_match_first(): void
    {
        foreach (['Vireux-Molhain', 'Évires', 'Condé-sur-Vire', 'Vire', 'Zone'] as $i => $name) {
            Station::factory()->create(['slug' => 'g-'.$i, 'name' => $name, 'geom' => Point::makeGeodetic(48.0 + $i / 100, 2.0)]);
        }

        $this->get('/stations')->assertInertia(fn (Assert $page) => $page
            ->where('stations.data', fn ($data) => $this->rows($data)->pluck('name')->all() === [
                'Condé-sur-Vire', 'Évires', 'Vire', 'Vireux-Molhain', 'Zone',
            ])
            ->where('overall', 5));

        $this->get('/stations?q=vire')->assertInertia(fn (Assert $page) => $page
            ->where('stations.data.0.name', 'Vire')
            ->where('stations.data', fn ($data) => $this->rows($data)->pluck('name')->last() === 'Évires'));
    }

    public function test_station_dates_expose_their_source_and_undated_closure(): void
    {
        Station::factory()->create([
            'slug' => 'sans-date', 'name' => 'Sans date', 'status' => 'declassified', 'source_status' => 'declassified',
            'opened_at' => '1862-01-01', 'closed_at' => '2026-10-20',
            'provenance' => ['opened_at' => 'inferred:line_event', 'closed_at' => 'vff:eras', 'closed_after' => 1850],
            'geom' => Point::makeGeodetic(48.0, 2.0),
        ]);

        $this->get('/stations')->assertInertia(fn (Assert $page) => $page
            ->where('stations.data.0.opened_source', 'inferred:line_event')
            ->where('stations.data.0.closed_source', 'vff:eras')
            ->where('stations.data.0.closed_after', 1850)
            ->where('stations.data.0.lines', ['items' => [], 'more' => 0])
            ->missing('stations.data.0.id'));
    }
}
