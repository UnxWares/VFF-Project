<?php

namespace Tests\Feature\Media;

use App\Models\Line;
use App\Models\Media;
use App\Models\Station;
use App\Services\Media\Sources\CommonsStationSource;
use App\Services\Media\Sources\MediaWriter;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Rattachement hors ligne aux gares des fichiers Commons déjà en base (vff:attach-commons-stations) : catégorie
 * P373 lue dans le cache Wikidata, nom de la gare dans une catégorie ou le titre, position, date, homonymes.
 */
class AttachCommonsToStationsTest extends TestCase
{
    use RefreshDatabase;

    private string $wikidataDir;

    private Line $line;

    private Station $genlis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wikidataDir = sys_get_temp_dir().'/vff-attach-commons-'.bin2hex(random_bytes(6));
        $dir = $this->wikidataDir;
        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($dir));
        File::ensureDirectoryExists($dir);
        file_put_contents($dir.'/entities-test.json', json_encode(['entities' => [
            'Q1' => ['claims' => [
                'P373' => [['mainsnak' => ['datavalue' => ['value' => 'Gare de Genlis']]]],
                'P18' => [['mainsnak' => ['datavalue' => ['value' => 'Gare de Genlis.JPG']]]],
            ]],
        ]]));

        $this->line = Line::factory()->create();
        $this->genlis = $this->station('Genlis', 47.2400, 5.2240, 'Q1', 1851);
    }

    private function station(string $name, float $lat, float $lng, ?string $qid = null, int $opened = 1860, bool $onLine = true): Station
    {
        $s = Station::factory()->create([
            'name' => $name, 'wikidata_id' => $qid, 'opened_at' => "{$opened}-01-01",
            'geom' => Point::makeGeodetic($lat, $lng),
        ]);
        if ($onLine) {
            DB::table('station_lines')->insert(['station_id' => $s->id, 'line_id' => $this->line->id, 'method' => 'test', 'created_at' => now(), 'updated_at' => now()]);
        }

        return $s;
    }

    /** @param  list<string>  $categories */
    private function file(string $title, array $categories, ?float $lat = null, ?float $lng = null, ?int $year = 2009, ?int $lineId = null): void
    {
        $id = (string) crc32($title);
        DB::table('media')->insert([
            'mediable_type' => Line::class, 'mediable_id' => $lineId ?? $this->line->id,
            'provider' => 'commons', 'external_id' => $id, 'type' => 'photo',
            'title' => $title, 'author' => 'Auteur', 'attribution' => 'Auteur, CC BY-SA 3.0, via Wikimedia Commons',
            'license' => 'CC BY-SA 3.0', 'license_url' => 'https://creativecommons.org/licenses/by-sa/3.0',
            'rights_status' => 'open_license', 'commercial_ok' => true, 'share_alike' => true,
            'source_url' => 'https://commons.wikimedia.org/wiki/File:'.rawurlencode($title),
            'thumb_url' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/ab/x.jpg/500px-x.jpg',
            'display_url' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/ab/x.jpg/1280px-x.jpg',
            'depicted_year' => $year,
            'location' => $lat !== null ? DB::raw("ST_SetSRID(ST_MakePoint({$lng}, {$lat}), 4326)") : null,
            'provider_meta' => json_encode(['file_title' => 'File:'.$title, 'categories' => $categories, 'via' => 'category', 'date_precision' => 'day']),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function run_source(): array
    {
        $source = new CommonsStationSource($this->wikidataDir);
        app(MediaWriter::class)->write($source->records());

        return $source->accepted;
    }

    public function test_p373_category_attaches_the_file_and_p18_is_featured(): void
    {
        $this->file('Gare de Genlis.JPG', ['Gare de Genlis', '2009 in Côte-d\'Or'], 47.2401, 5.2241);

        $this->run_source();

        $m = Media::query()->where('mediable_type', Station::class)->firstOrFail();
        $this->assertSame($this->genlis->id, (int) $m->mediable_id);
        $this->assertTrue($m->is_featured);
        $this->assertTrue($m->derivatives_ok);
        $this->assertSame('day', $m->date_precision);
        $this->assertSame(CommonsStationSource::VIA_P373, $m->provider_meta['via']);
        $this->assertSame('CC BY-SA 3.0', $m->license);
        // La ligne d'origine reste attachée à la ligne.
        $this->assertSame(1, Media::query()->where('mediable_type', Line::class)->count());
    }

    public function test_named_category_needs_a_served_station_or_a_close_position(): void
    {
        $this->station('Auxonne', 47.19, 5.39);
        $this->station('Dole', 47.09, 5.49, onLine: false);
        $this->file('Auxonne BV.jpg', ['Exterior of Gare d\'Auxonne']);
        $this->file('Dole 1.jpg', ['Gare de Dole']);
        $this->file('Dole 2.jpg', ['Gare de Dole'], 47.0902, 5.4902);

        $accepted = $this->run_source();

        $this->assertEqualsCanonicalizing(['Auxonne BV.jpg', 'Dole 2.jpg'], array_map(fn (array $a): string => preg_replace('/^File:/', '', $a['file']), $accepted));
    }

    public function test_far_position_before_opening_and_homonyms_are_refused(): void
    {
        $this->station('Saint-Jean', 45.0, 1.0);
        $this->station('Saint-Jean', 46.0, 2.0);
        $this->file('Loin.jpg', ['Gare de Genlis'], 47.2500, 5.2240);
        $this->file('Avant.jpg', ['Gare de Genlis'], null, null, 1840);
        $this->file('Homonyme.jpg', ['Gare de Saint-Jean']);

        $source = new CommonsStationSource($this->wikidataDir);
        $records = iterator_to_array($source->records(), false);

        $this->assertSame([], $records);
        $this->assertSame(1, $source->rejected['too_far']);
        $this->assertSame(1, $source->rejected['before_opening']);
        $this->assertSame(2, $source->rejected['ambiguous']);
    }

    public function test_title_naming_a_longer_station_name_is_refused(): void
    {
        $this->station('Valence', 44.93, 4.89);
        $this->station('Valence TGV', 44.99, 4.97, onLine: false);
        $this->file('Gare de Valence TGV côté TER (1).jpg', ['Valence TGV']);
        $this->file('Gare de Valence 2010.jpg', ['Valence']);

        $accepted = $this->run_source();

        $this->assertSame(['File:Gare de Valence 2010.jpg'], array_column($accepted, 'file'));
    }

    public function test_command_dry_run_writes_nothing_and_real_run_is_idempotent(): void
    {
        $this->file('Gare de Genlis.JPG', ['Gare de Genlis']);
        $this->app->bind(CommonsStationSource::class, fn () => new CommonsStationSource($this->wikidataDir));

        $this->artisan('vff:attach-commons-stations', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame(0, Media::query()->where('mediable_type', Station::class)->count());

        $this->artisan('vff:attach-commons-stations')->assertSuccessful();
        $this->artisan('vff:attach-commons-stations')->assertSuccessful();
        $this->assertSame(1, Media::query()->where('mediable_type', Station::class)->count());
    }
}
