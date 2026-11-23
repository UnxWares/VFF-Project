<?php

namespace Tests\Feature\Web;

use App\Models\Line;
use App\Models\Station;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * Paramètres d'URL malformés, hors bornes ou hostiles sur les pages publiques :
 * jamais d'erreur 500, filtres inconnus ignorés, recherche littérale (les
 * jokers LIKE « % » et « _ » et l'antislash sont des caractères comme les autres).
 *
 * Bugs corrigés :
 *   - « ?q[]=x » (tableau) → Array to string conversion → 500 sur /lines et /stations ;
 *   - « ?q=% » listait toutes les lignes / gares (joker LIKE non échappé).
 */
class QueryParameterRobustnessTest extends TestCase
{
    use BuildsRailNetwork;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRailNetwork();
    }

    /** @return array<string, array{0: string}> */
    public static function hostileMapQueries(): array
    {
        return [
            'ère texte' => ['era=abc'],
            'ère hors bornes' => ['era=99999'],
            'ère négative' => ['era=-1830'],
            'centre hors Terre' => ['center=999,-999'],
            'centre texte' => ['center=paris'],
            'zoom négatif' => ['zoom=-5'],
            'zoom énorme' => ['zoom=1e308'],
            'mode inconnu' => ['mode=%3Cscript%3Ealert(1)%3C%2Fscript%3E'],
            'tableaux' => ['era[]=1960&center[x]=1&zoom[]=3&mode[]=x'],
            'octets nuls' => ['era=1960%00&mode=%00'],
        ];
    }

    #[DataProvider('hostileMapQueries')]
    public function test_maps_ignore_malformed_parameters(string $query): void
    {
        foreach (['/maps' => 'Maps', '/embed/maps' => 'Embed/Maps'] as $uri => $component) {
            $this->get($uri.'?'.$query)
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component($component)
                    ->where('currentEraYear', 2030)
                    ->has('eras', 2)
                    ->where('eras.0', ['year' => 1960, 'label' => 'Années 1960', 'is_current' => false, 'segment_count' => 1, 'has_data' => true, 'open_km' => null, 'colorings' => ['electrification', 'speed']])
                    ->where('eras.1.is_current', true));
        }
    }

    public function test_map_data_version_follows_segment_updates(): void
    {
        $version = fn () => $this->get('/maps')->viewData('page')['props']['dataVersion'];

        $before = $version();
        $this->assertMatchesRegularExpression('/^\d+-2-m\d+$/', $before);

        DB::table('line_segments')->update(['updated_at' => now()->addMinute()]);
        $this->assertNotSame($before, $version());
    }

    public function test_map_without_any_era_falls_back_to_2030_and_v0(): void
    {
        DB::table('line_segments')->delete();
        DB::table('line_stops')->delete();
        DB::table('line_signalling')->delete();
        DB::table('eras')->delete();

        $this->get('/maps')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('eras', 0)->where('currentEraYear', 2030)->where('dataVersion', 'v0'));
    }

    public function test_map_without_current_flag_uses_the_latest_era(): void
    {
        DB::table('eras')->update(['is_current' => false]);

        $this->get('/maps')->assertInertia(fn (Assert $page) => $page->where('currentEraYear', 2030));
    }

    /** @return array<string, array{0: string}> */
    public static function hostileListQueries(): array
    {
        return [
            'recherche en tableau' => ['q[]=versailles'],
            'filtres en tableau' => ['network[]=principal&traffic[]=mixed&status[]=open&heritage[]=1'],
            'statut et département en tableau' => ['status[]=active&dept[]=75&era[]=1960&flagged[]=1'],
            'page texte' => ['page=abc'],
            'page négative' => ['page=-3'],
            'page énorme' => ['page=999999999999'],
            'ère texte' => ['era=mille-neuf-cent'],
            'ère inconnue' => ['era=1955'],
            'injection SQL' => ['q='.urlencode("'; DROP TABLE lines; --").'&dept='.urlencode("75' OR '1'='1")],
            'antislash final' => ['q='.urlencode('\\')],
            'unicode et apostrophe typographique' => ['q='.urlencode('Gare de l’Ouest – Saint-Lazare')],
            'emoji' => ['q='.urlencode('🚂')],
            'octet nul' => ['q=%00'],
            'texte très long' => ['q='.str_repeat('a', 5000)],
        ];
    }

    #[DataProvider('hostileListQueries')]
    public function test_lines_index_survives(string $query): void
    {
        $this->get('/lines?'.$query)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Lines/Index')->has('lines.data')->has('stats'));
        $this->assertDatabaseHas('lines', ['slug' => $this->line->slug]);
    }

    #[DataProvider('hostileListQueries')]
    public function test_stations_index_survives(string $query): void
    {
        $this->get('/stations?'.$query)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Stations/Index')->has('stations.data')->has('counts'));
        $this->assertDatabaseHas('stations', ['slug' => 'paris-saint-lazare']);
    }

    #[DataProvider('hostileListQueries')]
    public function test_materials_and_detail_pages_ignore_query_strings(string $query): void
    {
        $this->get('/materials?'.$query)->assertOk()->assertInertia(fn (Assert $page) => $page->component('Materials/Index'));
        $this->get('/lines/'.$this->line->slug.'?'.$query)->assertOk()->assertInertia(fn (Assert $page) => $page->component('Lines/Show'));
        $this->get('/stations/paris-saint-lazare?'.$query)->assertOk()->assertInertia(fn (Assert $page) => $page->component('Stations/Show'));
    }

    public function test_array_search_is_ignored_rather_than_crashing(): void
    {
        $this->get('/lines?q[]=zzz')->assertInertia(fn (Assert $page) => $page
            ->where('filters.q', '')->where('lines.total', 1));
        $this->get('/stations?q[]=zzz&status[]=gone')->assertInertia(fn (Assert $page) => $page
            ->where('filters.q', '')->where('filters.status', '')->where('stations.total', 2));
    }

    /** @return array<string, array{0: string, 1: string, 2: int}> */
    public static function literalSearches(): array
    {
        return [
            // [recherche, nom de la ligne témoin, nombre attendu]
            // Ponctuation seule : la recherche ignore la ponctuation (App\Support\Search), rien ne sort, jamais tout.
            'pourcent seul' => ['%', 'Ligne 100 % électrique', 0],
            'souligné seul' => ['_', 'Embranchement_privé', 0],
            'antislash' => ['\\', 'Voie \\ de service', 0],
            'pourcent sans témoin' => ['%', 'Ligne sans joker', 0],
            'souligné sans témoin' => ['_', 'Ligne sans joker', 0],
        ];
    }

    #[DataProvider('literalSearches')]
    public function test_line_search_treats_like_wildcards_literally(string $q, string $name, int $expected): void
    {
        Line::factory()->create(['name' => $name, 'code' => null, 'historical_operator' => null]);

        $this->get('/lines?q='.urlencode($q))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('filters.q', $q)
            ->where('lines.total', $expected)
            ->where('stats.total', $expected));
    }

    #[DataProvider('literalSearches')]
    public function test_station_search_treats_like_wildcards_literally(string $q, string $name, int $expected): void
    {
        Station::factory()->create(['name' => $name, 'city' => 'Nulle-part', 'uic_code' => null, 'alt_names' => null]);

        $this->get('/stations?q='.urlencode($q))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('filters.q', $q)
            ->where('stations.total', $expected));
    }

    public function test_search_is_case_insensitive_and_matches_code_and_operator(): void
    {
        $this->get('/lines?q=VERSAILLES')->assertInertia(fn (Assert $page) => $page->where('lines.total', 1));
        $this->get('/lines?q=999')->assertInertia(fn (Assert $page) => $page->where('lines.data.0.code', '999 000'));
        $this->get('/lines?q='.urlencode("compagnie de l'ouest"))->assertInertia(fn (Assert $page) => $page->where('lines.total', 1));
        $this->get('/lines?q=%20%20versailles%20%20')->assertInertia(fn (Assert $page) => $page
            ->where('filters.q', 'versailles')->where('lines.total', 1));
    }

    public function test_station_search_on_city_uic_code_and_alternate_names(): void
    {
        Station::factory()->create([
            'slug' => 'gare-test-alt', 'name' => 'Halte du Bois', 'city' => 'Sainte-Hélène',
            'uic_code' => '87654321', 'alt_names' => ['Le Bois-Joli'],
            'geom' => Point::makeGeodetic(45.0, 1.0),
        ]);

        foreach (['sainte-hél' => 1, '8765' => 1, '654321' => 0, 'bois-joli' => 1, 'Versailles' => 1] as $q => $n) {
            $this->get('/stations?q='.urlencode($q))->assertInertia(fn (Assert $page) => $page->where('stations.total', $n));
        }
    }
}
