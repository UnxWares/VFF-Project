<?php

namespace Tests\Feature\Media;

use App\Models\Material;
use App\Models\Media;
use App\Models\Station;
use App\Services\Media\CommonsCoverage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * vff:import-commons-candidates : passe à blanc sans écriture, écriture idempotente, droits revérifiés,
 * fiche aux images historiques laissée telle quelle, image à la une venue de la P18.
 */
class ImportCommonsCandidatesTest extends TestCase
{
    use RefreshDatabase;

    private string $file;

    protected function setUp(): void
    {
        parent::setUp();
        $this->file = tempnam(sys_get_temp_dir(), 'cand');
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        parent::tearDown();
    }

    /** @return array<string, mixed> */
    private function meta(string $title, array $over = []): array
    {
        return $over + [
            'file_title' => $title, 'title' => $title, 'type' => 'photo', 'mime' => 'image/jpeg', 'width' => 1600, 'height' => 1000,
            'rights_status' => 'open_license', 'commercial_ok' => true, 'share_alike' => true, 'license' => 'CC BY-SA 4.0',
            'author' => 'Auteur', 'attribution' => 'Auteur, CC BY-SA 4.0, via Wikimedia Commons',
            'source_url' => 'https://commons.wikimedia.org/wiki/'.rawurlencode($title),
            'thumb_url' => 'https://upload.wikimedia.org/thumb/'.rawurlencode($title), 'display_url' => null, 'license_url' => null, 'categories' => ['Gare'],
            'lat' => null, 'lon' => null, 'credit' => null, 'caption' => null, 'depicted_year' => 2010, 'captured_at' => null,
        ];
    }

    /** @param  list<array<string, mixed>>  $files */
    private function write(Station $station, array $files): void
    {
        file_put_contents($this->file, json_encode([
            'rules' => CommonsCoverage::RULES_VERSION, 'subject' => 'station', 'id' => $station->id, 'slug' => $station->slug,
            'name' => $station->name, 'qid' => 'Q1', 'category' => null, 'files' => $files,
        ])."\n");
    }

    public function test_dry_run_counts_and_writes_nothing(): void
    {
        $station = Station::factory()->create(['wikidata_id' => 'Q1']);
        $this->write($station, [
            ['pageid' => 11, 'via' => 'p18', 'category' => null, 'distance_m' => null, 'meta' => $this->meta('File:A.jpg')],
            ['pageid' => 12, 'via' => 'category', 'category' => 'Gare', 'distance_m' => 40, 'meta' => $this->meta('File:B.jpg')],
            ['pageid' => 13, 'via' => 'category', 'category' => 'Gare', 'distance_m' => null, 'meta' => $this->meta('File:C.jpg', ['license' => 'GFDL'])],
        ]);

        $this->artisan('vff:import-commons-candidates', ['file' => $this->file, '--dry-run' => true, '--sample' => 2])
            ->expectsOutputToContain('"fiches_gagnantes":1,"images":2')
            ->assertSuccessful();
        $this->assertSame(0, Media::query()->count());
    }

    public function test_apply_writes_rows_once_and_features_the_p18(): void
    {
        $station = Station::factory()->create(['wikidata_id' => 'Q1']);
        $this->write($station, [
            ['pageid' => 12, 'via' => 'category', 'category' => 'Gare', 'distance_m' => 40, 'meta' => $this->meta('File:B.jpg')],
            ['pageid' => 11, 'via' => 'p18', 'category' => null, 'distance_m' => null, 'meta' => $this->meta('File:A.jpg')],
            ['pageid' => 13, 'via' => 'category', 'category' => 'Gare', 'distance_m' => null, 'meta' => $this->meta('File:C.jpg', ['author' => null])],
        ]);

        $this->artisan('vff:import-commons-candidates', ['file' => $this->file])->assertSuccessful();
        $this->artisan('vff:import-commons-candidates', ['file' => $this->file])->assertSuccessful();

        $rows = Media::query()->where('mediable_type', Station::class)->where('mediable_id', $station->id)->orderBy('external_id')->get();
        $this->assertCount(2, $rows, 'idempotent, fichier sans auteur écarté');
        $this->assertSame(['11', '12'], $rows->pluck('external_id')->all());
        $this->assertTrue($rows[0]->is_featured);
        $this->assertFalse($rows[1]->is_featured);
        $this->assertSame('coverage-v'.CommonsCoverage::RULES_VERSION, $rows[0]->provider_meta['rule']);
        $this->assertSame('commons', $rows[0]->provider);
    }

    public function test_station_with_historic_commons_media_is_left_alone(): void
    {
        $station = Station::factory()->create(['wikidata_id' => 'Q1']);
        Media::factory()->create([
            'mediable_type' => Station::class, 'mediable_id' => $station->id, 'provider' => 'commons', 'external_id' => '999', 'provider_meta' => ['via' => 'category'],
        ]);
        $this->write($station, [['pageid' => 11, 'via' => 'p18', 'category' => null, 'distance_m' => null, 'meta' => $this->meta('File:A.jpg')]]);

        $this->artisan('vff:import-commons-candidates', ['file' => $this->file])->expectsOutputToContain('"deja_images_historiques":1')->assertSuccessful();
        $this->assertSame(1, Media::query()->count());
    }

    public function test_unknown_station_and_old_rules_are_skipped(): void
    {
        $station = Station::factory()->create(['wikidata_id' => 'Q1']);
        file_put_contents($this->file, json_encode(['rules' => 0, 'subject' => 'station', 'id' => $station->id, 'slug' => $station->slug, 'files' => [['pageid' => 1, 'via' => 'p18', 'meta' => $this->meta('File:A.jpg')]]])."\n"
            .json_encode(['rules' => CommonsCoverage::RULES_VERSION, 'subject' => 'station', 'id' => 99999999, 'slug' => 'nope', 'files' => [['pageid' => 2, 'via' => 'p18', 'meta' => $this->meta('File:B.jpg')]]])."\n");

        $this->artisan('vff:import-commons-candidates', ['file' => $this->file])
            ->expectsOutputToContain('"fiches_absentes_ou_deja_illustrees":1')
            ->assertSuccessful();
        $this->assertSame(0, Media::query()->count());
    }

    public function test_material_that_already_has_its_image_is_not_a_coverage_gap(): void
    {
        $with = Material::factory()->create(['image' => ['file' => 'File:X.jpg'], 'wikidata_id' => 'Q2']);
        $without = Material::factory()->create(['image' => null, 'wikidata_id' => 'Q3']);
        $rec = fn (Material $m): array => [
            'rules' => CommonsCoverage::RULES_VERSION, 'subject' => 'material', 'id' => $m->id, 'slug' => $m->slug, 'name' => $m->name, 'qid' => $m->wikidata_id,
            'files' => [['pageid' => $m->id, 'via' => 'p18', 'category' => null, 'distance_m' => null, 'meta' => $this->meta('File:M'.$m->id.'.jpg')]],
        ];
        file_put_contents($this->file, json_encode($rec($with))."\n".json_encode($rec($without))."\n");

        $this->artisan('vff:import-commons-candidates', ['file' => $this->file])->assertSuccessful();
        $this->assertSame([$without->id], Media::query()->where('mediable_type', Material::class)->pluck('mediable_id')->all());
    }

    public function test_station_whose_wikidata_item_changed_is_skipped(): void
    {
        $station = Station::factory()->create(['wikidata_id' => 'Q222']);
        file_put_contents($this->file, json_encode([
            'rules' => CommonsCoverage::RULES_VERSION, 'subject' => 'station', 'id' => $station->id, 'slug' => $station->slug, 'name' => $station->name, 'qid' => 'Q111',
            'files' => [['pageid' => 1, 'via' => 'p18', 'category' => null, 'distance_m' => null, 'meta' => $this->meta('File:A.jpg')]],
        ])."\n");

        $this->artisan('vff:import-commons-candidates', ['file' => $this->file])->assertSuccessful();
        $this->assertSame(0, Media::query()->count());
    }

    public function test_geosearch_file_must_name_the_station(): void
    {
        $station = Station::factory()->create(['name' => 'Vaugirard', 'wikidata_id' => 'Q1']);
        $this->write($station, [
            ['pageid' => 1, 'via' => 'geosearch', 'category' => null, 'distance_m' => 100, 'meta' => $this->meta('File:Gare de Paris-Montparnasse 3.jpg', ['categories' => []])],
            ['pageid' => 2, 'via' => 'geosearch', 'category' => null, 'distance_m' => 100, 'meta' => $this->meta('File:Ancienne gare de Vaugirard.jpg', ['categories' => []])],
        ]);

        $this->artisan('vff:import-commons-candidates', ['file' => $this->file])->assertSuccessful();
        $this->assertSame(['2'], Media::query()->pluck('external_id')->all());
    }

    public function test_weak_category_file_must_name_the_station_too(): void
    {
        $station = Station::factory()->create(['name' => 'Itancourt', 'wikidata_id' => 'Q1']);
        $this->write($station, [
            ['pageid' => 1, 'via' => 'weak_category', 'category' => 'CFT Vermandois', 'distance_m' => null, 'meta' => $this->meta('File:BB 66252 sur la rotonde de Longueau.jpg', ['categories' => ['CFT Vermandois', 'Gare']])],
            ['pageid' => 2, 'via' => 'weak_category', 'category' => 'CFT Vermandois', 'distance_m' => null, 'meta' => $this->meta('File:Gare d\'Itancourt.jpg', ['categories' => ['CFT Vermandois']])],
        ]);

        $this->artisan('vff:import-commons-candidates', ['file' => $this->file])->assertSuccessful();
        $this->assertSame(['2'], Media::query()->pluck('external_id')->all());
    }

    public function test_geosearch_is_limited_to_150_m_and_street_names_are_refused(): void
    {
        $station = Station::factory()->create(['name' => 'Vaugirard', 'wikidata_id' => 'Q1']);
        $this->write($station, [
            ['pageid' => 1, 'via' => 'geosearch', 'category' => null, 'distance_m' => 200, 'meta' => $this->meta('File:Gare de Vaugirard loin.jpg', ['categories' => []])],
            ['pageid' => 2, 'via' => 'name_category', 'category' => 'Gare de Vaugirard', 'distance_m' => null, 'meta' => $this->meta('File:Rue de la Gare Vaugirard.jpg', ['categories' => []])],
            ['pageid' => 3, 'via' => 'geosearch', 'category' => null, 'distance_m' => 120, 'meta' => $this->meta('File:Gare de Vaugirard.jpg', ['categories' => []])],
        ]);

        $this->artisan('vff:import-commons-candidates', ['file' => $this->file])->assertSuccessful();
        $this->assertSame(['3'], Media::query()->pluck('external_id')->all());
    }
}
