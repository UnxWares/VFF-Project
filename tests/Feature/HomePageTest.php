<?php

namespace Tests\Feature;

use App\Enums\SegmentStatus;
use App\Models\Era;
use App\Models\Line;
use App\Models\LineSegment;
use App\Models\Media;
use Clickbar\Magellan\Data\Geometries\LineString;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * Accueil : profil du réseau par décennie (`profile`), images d'archives réelles (`archives`) et
 * nombre de médias (`mediaCount`). Mini-réseau de BuildsRailNetwork : une ligne en service
 * en 1960 et aujourd'hui (ère 2030), environ 17 km.
 */
class HomePageTest extends TestCase
{
    use BuildsRailNetwork;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRailNetwork();
    }

    private function segment(Line $line, Era $era, string $status, ?int $lengthMeters): void
    {
        LineSegment::factory()->create([
            'line_id' => $line->id,
            'era_id' => $era->id,
            'geom' => LineString::make([Point::makeGeodetic(45.0, 1.0), Point::makeGeodetic(45.0, 1.1)]),
            'status' => $status,
            'length_meters' => $lengthMeters,
        ]);
    }

    private int $commonsId = 1000;

    /** @param  array<string, mixed>  $attributes */
    private function commonsImage(array $attributes = []): Media
    {
        return Media::factory()->create(array_merge([
            'mediable_type' => Line::class,
            'mediable_id' => $this->line->id,
            'type' => 'photo',
            'provider' => Media::PROVIDER_COMMONS,
            'external_id' => (string) $this->commonsId++,
            'file_path' => null,
            'is_featured' => true,
            'depicted_year' => 1960,
            'width' => 1600,
            'height' => 1000,
            'caption' => 'Gare de Versailles-Rive-Droite',
            'author' => 'Jean Dupont',
            'license' => 'CC BY-SA 4.0',
            'license_url' => 'https://creativecommons.org/licenses/by-sa/4.0',
            'source_url' => 'https://commons.wikimedia.org/wiki/File:Exemple.jpg',
            'thumb_url' => 'https://upload.wikimedia.org/thumb/Exemple.jpg/500px-Exemple.jpg',
            'display_url' => 'https://upload.wikimedia.org/thumb/Exemple.jpg/1280px-Exemple.jpg',
        ], $attributes));
    }

    public function test_profile_sums_open_lines_per_decade_in_chronological_order(): void
    {
        $other = Line::factory()->create();
        // Un segment fermé ne compte pas ; un segment à voie unique compte ; longueur enregistrée en mètres.
        $this->segment($other, $this->pastEra, SegmentStatus::Abandoned->value, 50_000);
        $this->segment($other, $this->currentEra, SegmentStatus::SingleTrack->value, 12_345);
        Era::factory()->create(['year' => 1900]);

        $this->get('/')->assertOk()->assertInertia(function (Assert $page) {
            $page->component('Home')
                ->has('profile', 3)
                ->where('profile.0', ['year' => 1900, 'km' => 0, 'lines' => 0, 'current' => false])
                ->where('profile.1.year', 1960)
                ->where('profile.1.lines', 1)
                ->where('profile.1.current', false)
                ->where('profile.2.year', 2030)
                ->where('profile.2.lines', 2)
                ->where('profile.2.current', true);

            $profile = $page->toArray()['props']['profile'];
            // Longueur de la ligne de test calculée par ST_Length (length_meters vide) : environ 17 km.
            $this->assertGreaterThan(10, $profile[1]['km']);
            $this->assertLessThan(25, $profile[1]['km']);
            $this->assertEqualsWithDelta($profile[1]['km'] + 12, $profile[2]['km'], 1);
        });
    }

    public function test_archives_are_real_featured_commons_images_with_credits(): void
    {
        foreach ([1905, 1930, 1955, 1985, 2000, 2015, 2020, 2022] as $year) {
            $this->commonsImage(['depicted_year' => $year, 'caption' => "Image de $year"]);
        }
        // Écartées : non mise en avant, fichier déposé, portrait, sans licence, sans année.
        $this->commonsImage(['is_featured' => false, 'depicted_year' => 1950]);
        $this->commonsImage(['provider' => Media::PROVIDER_UPLOAD, 'external_id' => null, 'file_path' => 'media/gare.jpg', 'depicted_year' => 1951]);
        $this->commonsImage(['width' => 800, 'height' => 1200, 'depicted_year' => 1952]);
        $this->commonsImage(['license' => null, 'depicted_year' => 1953]);
        $this->commonsImage(['depicted_year' => null]);

        $this->get('/')->assertOk()->assertInertia(function (Assert $page) {
            $page->component('Home')
                ->has('archives', 7)
                ->has('archives.0', fn (Assert $m) => $m
                    ->where('year', 1905)
                    ->where('decade', false)
                    ->where('caption', 'Image de 1905')
                    ->where('author', 'Jean Dupont')
                    ->where('license', 'CC BY-SA 4.0')
                    ->where('source_url', 'https://commons.wikimedia.org/wiki/File:Exemple.jpg')
                    ->where('line', ['slug' => $this->line->slug, 'name' => $this->line->name])
                    ->hasAll(['id', 'license_url', 'thumb_url', 'display_url', 'width', 'height'])
                );

            $years = array_column($page->toArray()['props']['archives'], 'year');
            // Sept au plus : une par période d'abord (six périodes), puis une deuxième de la première
            // période qui en a encore (ici la plus récente, trois images) ; rangées par année.
            $this->assertSame([1905, 1930, 1955, 1985, 2000], array_slice($years, 0, 5));
            $this->assertCount(2, array_intersect(array_slice($years, 5), [2015, 2020, 2022]));
            $sorted = $years;
            sort($sorted);
            $this->assertSame($sorted, $years);
        });
    }

    public function test_archives_flag_decade_precision(): void
    {
        $this->commonsImage(['depicted_year' => 1900, 'provider_meta' => ['date_precision' => 'decade']]);

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->has('archives', 1)
            ->where('archives.0.decade', true));
    }

    /** Adresses enregistrées avant le contrôle de l'import : nulles si elles ne sont pas en http(s). */
    public function test_archives_never_link_to_an_address_that_is_not_http(): void
    {
        $media = $this->commonsImage();
        DB::table('media')->where('id', $media->id)->update(['license_url' => ' javascript:alert(1)', 'source_url' => 'JaVaScRiPt:alert(1)']);

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->has('archives', 1)
            ->where('archives.0.license', 'CC BY-SA 4.0')
            ->where('archives.0.license_url', null)
            ->where('archives.0.source_url', null));
    }

    public function test_home_without_featured_images_has_empty_archives(): void
    {
        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->where('archives', [])
            ->where('mediaCount', 0));
    }

    public function test_stats_count_media(): void
    {
        $this->commonsImage();
        $this->commonsImage(['is_featured' => false]);

        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('mediaCount', 2));
    }
}
