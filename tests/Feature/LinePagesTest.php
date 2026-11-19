<?php

namespace Tests\Feature;

use App\Models\Line;
use App\Models\Material;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\BuildsRailNetwork;
use Tests\Support\ReadsInertiaProps;
use Tests\TestCase;

class LinePagesTest extends TestCase
{
    use BuildsRailNetwork;
    use ReadsInertiaProps;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRailNetwork();
    }

    // NB : LineController évolue vite (facettes réseau/trafic/état) ; ces tests
    // verrouillent le contrat stable (composant, pagination, filtres, géométrie,
    // arrêts canoniques) plutôt que le détail de chaque compteur.

    public function test_index_paginates_lines(): void
    {
        Line::factory()->count(2)->create(['type' => 'freight']);

        $this->get('/lines')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Lines/Index')
                ->has('lines.data', 3, fn (Assert $line) => $line
                    ->hasAll(['slug', 'code', 'name', 'historical_operator'])
                    ->etc()
                )
                ->where('lines.current_page', 1)
                ->where('lines.last_page', 1)
                ->where('lines.total', 3)
                ->where('stats.total', 3)
                ->where('filters.q', '')
                ->where('filters.heritage', false)
            );
    }

    public function test_index_paginates_by_40(): void
    {
        Line::factory()->count(45)->create();

        $this->get('/lines?page=2')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('lines.current_page', 2)
                ->where('lines.last_page', 2)
                ->where('lines.total', 46)
                ->has('lines.data', 6)
            );
    }

    public function test_index_filters_by_search(): void
    {
        Line::factory()->create(['name' => 'Lyon–Modane']);

        $this->get('/lines?q=versailles')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('lines.total', 1)
                ->where('lines.data.0.slug', $this->line->slug)
                ->where('stats.total', 1)
                ->where('filters.q', 'versailles')
            );

        $this->get('/lines?q=aucune-ligne-ne-correspond')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('lines.total', 0)
                ->has('lines.data', 0)
            );
    }

    public function test_index_survives_hostile_filters(): void
    {
        $this->get('/lines?type=%27%3B+DROP+TABLE+lines%3B--&traffic=x&network=y&status=z&q=%25%27')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Lines/Index'));

        $this->assertDatabaseHas('lines', ['slug' => $this->line->slug]);
    }

    public function test_show_renders_line_with_geometry_stops_and_signalling(): void
    {
        // Rattachement des gares à la ligne (vff:attach-stations) : source de la liste des
        // gares de la fiche (App\Services\LineStations).
        foreach ([[$this->origin, 0], [$this->destination, 17_250]] as [$station, $pk]) {
            \DB::table('station_lines')->insert(['station_id' => $station->id, 'line_id' => $this->line->id, 'method' => 'sncf_pk',
                'pk_m' => $pk, 'created_at' => now(), 'updated_at' => now()]);
        }

        $this->get('/lines/'.$this->line->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Lines/Show')
                ->where('line.slug', $this->line->slug)
                ->where('line.name', $this->line->name)
                ->where('line.code', '999 000')
                ->where('line.is_threatened', false)
                // Groupes GeoJSON par ère : l'ère courante est présente, avec une vraie géométrie.
                ->where('segments', fn ($groups) => $groups->contains(
                    fn ($g) => $g['era_year'] === 2030
                        && $g['features'][0]['type'] === 'Feature'
                        && $g['features'][0]['geometry']['type'] === 'LineString'
                        && count($g['features'][0]['geometry']['coordinates']) === 3
                ))
                // Gares rattachées (station_lines), dans l'ordre des PK.
                ->has('stations', 2)
                ->where('stations.0.slug', 'paris-saint-lazare')
                ->where('stations.1.slug', 'versailles-rive-droite')
                ->where('stations.1.pk_label', '17+250')
                ->where('stations.1.is_terminal', true)
                ->has('stats')
                ->has('signalling', 1)
                ->where('signalling.0.signalling', 'BAL')
                ->where('signalling.0.pk_end_label', '17+250')
                ->where('signalling.0.train_protection', ['KVB'])
                ->has('events', 1)
                ->has('materials', 1)
                ->where('materials.0.slug', $this->material->slug)
                ->missing('media')
                ->has('sections', 0)
                // Pas de source de la ligne (line_sources) ; les sources des tronçons affichés sont citées
                // (signalisation SNCF : une plage de cantonnement).
                ->where('sources', fn ($s) => $this->rows($s)->pluck('type')->contains('sncf-signalling')
                    && ! $this->rows($s)->pluck('type')->contains('sncf-classification'))
            );
    }

    public function test_show_lists_rolling_stock_by_name_in_natural_order(): void
    {
        foreach (['BB 15000', 'Élément à turbine à gaz', 'BB 9200', 'Autorail X 2800'] as $i => $name) {
            $this->line->materials()->attach(Material::factory()->create(['slug' => "serie-{$i}", 'name' => $name])->id);
        }

        $this->get("/lines/{$this->line->slug}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('materials', fn ($materials) => $this->rows($materials)->pluck('name')->all()
                    === ['Autorail X 2800', 'BB 9200', 'BB 15000', 'Élément à turbine à gaz', 'Z 6400'])
            );
    }

    public function test_show_has_no_spatial_fallback_for_stations(): void
    {
        // Sans gare rattachée (station_lines), la fiche n'invente pas de gares le long du
        // tracé : la liste, l'en-tête et les décennies suivent App\Services\LineStations,
        // comme la carte et la fiche gare (les arrêts line_stops seuls ne suffisent pas).
        $this->get('/lines/'.$this->line->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Lines/Show')
                ->has('stations', 0)
                ->where('stats.station_count', 0)
            );
    }

    public function test_show_line_without_segments(): void
    {
        $empty = Line::factory()->create(['type' => null]);

        $this->get('/lines/'.$empty->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Lines/Show')
                ->where('line.type', null)
                ->has('segments', 0)
                ->has('stations', 0)
            );
    }

    public function test_unknown_line_is_404(): void
    {
        $this->get('/lines/ligne-qui-n-existe-pas')->assertNotFound();
    }
}
