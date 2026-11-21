<?php

namespace Tests\Feature;

use App\Models\Line;
use App\Models\Media;
use App\Services\HomeArchives;
use App\Services\Media\HomeImageSelector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * « Le rail en images » : rotation par fenêtres fixes, ordre chronologique, une image par période, et règle
 * de la réserve ancienne (HomeImageSelector).
 */
class HomeArchivesRotationTest extends TestCase
{
    use BuildsRailNetwork;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRailNetwork();
    }

    /** @return list<array<string, mixed>> */
    private function pool(): array
    {
        $years = [1890, 1895, 1900, 1905, 1920, 1925, 1930, 1950, 1955, 1960, 1975, 1980, 1985, 1995, 2000, 2005, 2015, 2018, 2020, 2023];
        $pool = [];
        foreach ($years as $i => $y) {
            $pool[] = ['id' => $i + 1, 'year' => $y];
        }

        return $pool;
    }

    public function test_same_window_gives_same_picks_and_windows_differ(): void
    {
        $archives = new HomeArchives(1800, 7);
        $pool = $this->pool();
        $this->assertSame($archives->pick($pool, 1_800_000, []), $archives->pick($pool, 1_800_000, []));

        $seeds = array_map(fn (int $w) => array_column($archives->pick($pool, $w * 1800, []), 'id'), range(1, 8));
        $this->assertGreaterThan(5, count(array_unique(array_map('json_encode', $seeds))));
    }

    public function test_window_boundaries(): void
    {
        $archives = new HomeArchives(1800, 7);
        $this->assertSame(0, $archives->windowStart(1799));
        $this->assertSame(1800, $archives->windowStart(1800));
        $this->assertSame(1800, $archives->windowStart(3599));
    }

    public function test_picks_are_chronological_with_one_per_period(): void
    {
        $archives = new HomeArchives(1800, 7);
        foreach (range(1, 30) as $w) {
            $picks = $archives->pick($this->pool(), $w * 1800, []);
            $this->assertCount(7, $picks);
            $years = array_column($picks, 'year');
            $sorted = $years;
            sort($sorted);
            $this->assertSame($sorted, $years);
            $this->assertSame($years, array_values(array_unique($years)), 'chaque année une seule fois');
            $periods = array_unique(array_map(fn ($m) => $archives->period($m['year']), $picks));
            $this->assertCount(6, $periods, 'les six périodes sont représentées');
        }
    }

    public function test_missing_period_is_skipped_and_pool_exhaustion_is_handled(): void
    {
        $archives = new HomeArchives(1800, 7);
        $pool = [['id' => 1, 'year' => 1900], ['id' => 2, 'year' => 1990], ['id' => 3, 'year' => 2020]];
        $this->assertSame([1900, 1990, 2020], array_column($archives->pick($pool, 5, []), 'year'));
        $this->assertSame([], $archives->pick([], 5, []));
    }

    public function test_previous_window_images_are_avoided_when_the_reserve_allows(): void
    {
        $archives = new HomeArchives(1800, 7);
        $pool = $this->pool();
        $previous = array_column($archives->pick($pool, 1800, []), 'id');
        $next = array_column($archives->pick($pool, 3600, $previous), 'id');
        $this->assertSame([], array_intersect($previous, $next));

        // Réserve minuscule : on reprend plutôt que de laisser un vide.
        $tiny = [['id' => 1, 'year' => 1900], ['id' => 2, 'year' => 2020]];
        $this->assertCount(2, $archives->pick($tiny, 3600, [1, 2]));
    }

    public function test_current_is_cached_for_the_window_and_changes_in_the_next(): void
    {
        $this->travelTo(now()->startOfDay()->addMinutes(5));
        foreach (range(1, 24) as $i) {
            $this->commons(['depicted_year' => 1880 + $i * 5, 'external_id' => 'a'.$i]);
        }
        $archives = new HomeArchives(1800, 3);
        $first = array_column($archives->current(), 'id');
        // Une image ajoutée dans la fenêtre n'y change rien (cache).
        $this->commons(['depicted_year' => 1881, 'external_id' => 'zz']);
        $this->assertSame($first, array_column($archives->current(), 'id'));

        $seen = [$first];
        foreach (range(1, 6) as $i) {
            $this->travelTo(now()->startOfDay()->addMinutes(5 + 30 * $i));
            $seen[] = array_column($archives->current(), 'id');
        }
        $this->assertGreaterThan(3, count(array_unique(array_map('json_encode', $seen))));
        Cache::flush();
    }

    /** @param  array<string, mixed>  $attributes */
    private function commons(array $attributes = []): Media
    {
        return Media::factory()->create(array_merge([
            'mediable_type' => Line::class,
            'mediable_id' => $this->line->id,
            'type' => 'photo',
            'provider' => Media::PROVIDER_COMMONS,
            'external_id' => 'x'.random_int(1, 1_000_000_000),
            'file_path' => null,
            'is_featured' => true,
            'depicted_year' => 1900,
            'width' => 1600,
            'height' => 1000,
            'title' => 'Gare de Test '.random_int(1, 1_000_000_000),
            'caption' => 'La gare de Test vers 1900',
            'author' => 'Jean Dupont',
            'license' => 'Public domain',
            'rights_status' => 'public_domain',
            'thumb_url' => 'https://upload.wikimedia.org/t.jpg',
            'display_url' => 'https://upload.wikimedia.org/d.jpg',
            'source_url' => 'https://commons.wikimedia.org/wiki/File:T.jpg',
        ], $attributes));
    }

    private function flagged(): array
    {
        return DB::table('media')->whereRaw("jsonb_exists(provider_meta, 'home_pick')")->pluck('id')->all();
    }

    public function test_selector_keeps_good_images_and_drops_the_rest(): void
    {
        $good = $this->commons(['is_featured' => false, 'depicted_year' => 1905]);
        $bad = [
            'trop récente' => $this->commons(['is_featured' => false, 'depicted_year' => 1995]),
            'étroite' => $this->commons(['is_featured' => false, 'width' => 900, 'height' => 500]),
            'portrait' => $this->commons(['is_featured' => false, 'width' => 1300, 'height' => 1600]),
            'carré' => $this->commons(['is_featured' => false, 'width' => 1300, 'height' => 1200]),
            'sans légende' => $this->commons(['is_featured' => false, 'caption' => null]),
            'sans auteur' => $this->commons(['is_featured' => false, 'author' => null]),
            'licence inconnue' => $this->commons(['is_featured' => false, 'rights_status' => 'unknown']),
            'hors rail' => $this->commons(['is_featured' => false, 'title' => 'Chat', 'caption' => 'Un chat dort dans un jardin']),
            'carte' => $this->commons(['is_featured' => false, 'caption' => 'Carte du chemin de fer du Nord']),
            'horaires' => $this->commons(['is_featured' => false, 'caption' => 'Horaire de la gare', 'depicted_year' => 1914]),
            'déjà mise en avant' => $this->commons(['is_featured' => true, 'depicted_year' => 1906]),
        ];
        // Doublons : même titre normalisé, même ligne ; même fichier sur une autre ligne.
        $twin = $this->commons(['is_featured' => false, 'depicted_year' => 1905, 'title' => $good->title.' (2)', 'author' => 'Autre', 'width' => 1500]);

        $r = (new HomeImageSelector)->apply(1970);
        $flagged = $this->flagged();
        $this->assertContains($good->id, $flagged);
        $this->assertNotContains($twin->id, $flagged);
        foreach ($bad as $label => $m) {
            $this->assertNotContains($m->id, $flagged, $label);
        }
        $this->assertSame(1, $r['after']);

        // Rejouable : un second passage donne le même résultat, et --dry-run n'écrit rien.
        $this->assertSame(1, (new HomeImageSelector)->apply(1970)['after']);
        DB::table('media')->update(['provider_meta' => null]);
        (new HomeImageSelector)->apply(1970, true);
        $this->assertSame([], $this->flagged());
    }

    public function test_selector_limits_images_per_line(): void
    {
        foreach (range(1, 6) as $i) {
            $this->commons(['is_featured' => false, 'depicted_year' => 1890 + $i * 7, 'title' => 'Gare '.str_repeat(chr(96 + $i), 3).' unique', 'author' => 'A'.$i]);
        }
        (new HomeImageSelector(3))->apply(1970);
        $this->assertCount(3, $this->flagged());
    }

    public function test_flagged_images_join_the_home_pool(): void
    {
        $m = $this->commons(['is_featured' => false, 'depicted_year' => 1905]);
        $this->assertSame([], (new HomeArchives)->pool());
        (new HomeImageSelector)->apply(1970);
        $this->assertSame([$m->id], array_column((new HomeArchives)->pool(), 'id'));
    }

    public function test_command_replays_and_supports_dry_run(): void
    {
        $m = $this->commons(['is_featured' => false, 'depicted_year' => 1905]);
        $this->artisan('vff:select-home-images', ['--dry-run' => true])->expectsOutputToContain('0 image(s) retenue(s) avant, 1 après')->assertSuccessful();
        $this->assertSame([], $this->flagged());
        $this->artisan('vff:select-home-images')->assertSuccessful();
        $this->artisan('vff:select-home-images')->expectsOutputToContain('1 image(s) retenue(s) avant, 1 après')->assertSuccessful();
        $this->assertSame([$m->id], $this->flagged());
    }
}
