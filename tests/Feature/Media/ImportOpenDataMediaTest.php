<?php

namespace Tests\Feature\Media;

use App\Models\Line;
use App\Models\Media;
use App\Models\Station;
use App\Services\Media\OpenData\OpenDataFetcher;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * vff:import-open-data-media : lecture réseau polie (API tabulaire de data.gouv.fr, jamais l'API des portails), cache
 * local, passe à blanc sans écriture, écriture idempotente avec droits et crédit exacts, rattachement aux gares et
 * aux lignes. Les lignes viennent d'extraits réels (tests/Fixtures/OpenData), sans image.
 */
class ImportOpenDataMediaTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
        Sleep::fake();
    }

    private function seedCache(string $key): void
    {
        File::ensureDirectoryExists(OpenDataFetcher::cacheDir());
        File::copy($this->fixturePath("OpenData/{$key}-extrait.jsonl"), OpenDataFetcher::cachePath($key));
    }

    private function stationAt(string $name, string $city, float $lat, float $lng, string $type = 'gare'): Station
    {
        return Station::factory()->create(['name' => $name, 'city' => $city, 'type' => $type, 'geom' => Point::makeGeodetic($lat, $lng)]);
    }

    public function test_the_fetcher_reads_the_tabular_api_politely_and_caches_without_backup(): void
    {
        $first = ['data' => [['__id' => 1, 'Identifiant Fakir' => 'IF1', 'Légende' => 'La gare']], 'links' => ['next' => 'https://tabular-api.data.gouv.fr/api/resources/abc/data/?page=2']];
        $second = ['data' => [['__id' => 2, 'Identifiant Fakir' => 'IF2', 'Légende' => 'Autre gare'], ['__id' => 3, 'Identifiant Fakir' => 'IF1', 'Légende' => 'La gare']], 'links' => ['next' => null]];
        Http::fake([
            'tabular-api.data.gouv.fr/*page=2*' => Http::response($second),
            'tabular-api.data.gouv.fr/*' => Http::response($first),
        ]);
        $dataset = ['driver' => 'tabular', 'resource' => 'abc', 'fields' => ['id' => 'Identifiant Fakir'], 'prefilter' => [['Pays__exact' => 'France', 'Sujets__contains' => 'gare']]];

        $fetcher = new OpenDataFetcher;
        $this->assertSame(2, $fetcher->refresh('albert-kahn', $dataset));

        Http::assertSent(fn (Request $r): bool => $r->hasHeader('User-Agent', 'VFF-Bot/1.0 (+https://vff-project.org)')
            && str_contains($r->url(), 'tabular-api.data.gouv.fr/api/resources/abc/data/')
            && ! str_contains($r->url(), '/api/explore/'));
        // Le filtre de pré-sélection reste dans l'adresse de la première page (une option `query` vide l'effacerait).
        Http::assertSent(fn (Request $r): bool => str_contains($r->url(), 'Pays__exact=France') && str_contains($r->url(), 'Sujets__contains=gare') && str_contains($r->url(), 'page_size=50'));
        Http::assertSentCount(2);
        // Une pause de 2 s avant la seconde requête (la première part sans attente).
        Sleep::assertSleptTimes(1);
        $this->assertFileExists(OpenDataFetcher::cacheDir().'/.nobackup');
        $this->assertCount(2, iterator_to_array(OpenDataFetcher::cached('albert-kahn')));
        $meta = json_decode((string) file_get_contents(OpenDataFetcher::cacheDir().'/albert-kahn.meta.json'), true);
        $this->assertSame(2, $meta['rows']);
        $this->assertSame(2, $meta['requests']);
    }

    public function test_the_fetcher_merges_prefilter_queries_without_duplicates(): void
    {
        Http::fake(['tabular-api.data.gouv.fr/*' => Http::response(['data' => [['Identifiant' => 'A'], ['Identifiant' => 'B']], 'links' => ['next' => null]])]);
        $dataset = ['driver' => 'tabular', 'resource' => 'abc', 'fields' => ['id' => 'Identifiant'], 'prefilter' => [['Titre__contains' => 'gare'], ['Titre__contains' => 'viaduc']]];

        $this->assertSame(2, (new OpenDataFetcher)->refresh('ad92-9fi', $dataset));
        Http::assertSentCount(2);
    }

    public function test_the_wfs_features_keep_their_point(): void
    {
        Http::fake(['data.grandlyon.com/*' => Http::response(['features' => [['properties' => ['numero_inventaire' => '004.12.1', 'titre_cp' => 'Gare'], 'geometry' => ['coordinates' => [4.8, 45.7]]]]])]);

        (new OpenDataFetcher)->refresh('gadagne-cp', ['driver' => 'wfs', 'resource' => 'https://data.grandlyon.com/geoserver/x?outputFormat=application/json', 'fields' => ['id' => 'numero_inventaire']]);
        $row = iterator_to_array(OpenDataFetcher::cached('gadagne-cp'))[0];

        $this->assertSame(45.7, $row['__lat']);
        $this->assertSame(4.8, $row['__lng']);
    }

    public function test_a_failed_read_is_reported_and_does_not_stop_the_other_datasets(): void
    {
        Http::fake(['*' => Http::response('', 500)]);

        $this->artisan('vff:import-open-data-media', ['source' => ['sevres-cp'], '--dry-run' => true])
            ->expectsOutputToContain('lecture impossible')
            ->assertFailed();
    }

    public function test_dry_run_counts_and_writes_nothing(): void
    {
        $this->seedCache('albert-kahn');
        $this->stationAt('Colmar', 'Colmar', 48.0, 7.35);
        $this->stationAt('Paris-Est', 'Paris 10e Arrondissement', 48.8765, 2.359);
        $before = DB::table('media')->count();

        $this->artisan('vff:import-open-data-media', ['source' => ['albert-kahn'], '--dry-run' => true, '--offline' => true, '--sample' => 3])
            ->expectsOutputToContain('Mesure à blanc')
            ->assertSuccessful();

        $this->assertSame($before, DB::table('media')->count());
        Http::assertNothingSent();
    }

    public function test_albert_kahn_images_are_written_with_their_own_licence_and_credit_and_the_run_is_idempotent(): void
    {
        $this->seedCache('albert-kahn');
        $foch = $this->stationAt('Avenue-Foch', 'Paris 16e Arrondissement', 48.8736, 2.2757);
        $this->stationAt('Colmar', 'Colmar', 48.0, 7.35);

        $this->artisan('vff:import-open-data-media', ['source' => ['albert-kahn'], '--offline' => true])->assertSuccessful();
        $count = Media::query()->where('provider', 'albert-kahn')->count();
        $this->assertGreaterThan(0, $count);
        $this->artisan('vff:import-open-data-media', ['source' => ['albert-kahn'], '--offline' => true])->assertSuccessful();
        $this->assertSame($count, Media::query()->where('provider', 'albert-kahn')->count());

        $m = Media::query()->where('provider', 'albert-kahn')->where('mediable_type', Station::class)->where('mediable_id', $foch->id)->where('external_id', 'IF46057')->firstOrFail();
        $this->assertSame('public_domain', $m->rights_status);
        $this->assertSame('CC0 1.0', $m->license);
        $this->assertTrue($m->commercial_ok);
        $this->assertTrue((bool) DB::table('media')->where('id', $m->id)->value('derivatives_ok'));
        $this->assertStringContainsString('musée départemental Albert-Kahn', (string) $m->attribution);
        $this->assertStringContainsString('CC0 1.0', (string) $m->attribution);
        $this->assertStringStartsWith('Auguste Léon', (string) $m->attribution);
        $this->assertSame('photo', $m->type->value);
        $this->assertSame('1918-12-05', $m->captured_at->toDateString());
        $this->assertStringContainsString('opendata.hauts-de-seine.fr', (string) $m->source_url);
        $this->assertStringEndsWith('/300/', (string) $m->thumb_url);
        $this->assertSame(1918, $m->depicted_year);
        $this->assertSame('open-data-v1', $m->provider_meta['rule']);
    }

    public function test_every_written_row_passes_the_rights_check_of_the_writer(): void
    {
        foreach (['albert-kahn', 'ad92-9fi', 'suresnes-cp', 'evry-cp', 'sevres-cp', 'gadagne-cp'] as $key) {
            $this->seedCache($key);
        }
        $this->stationAt('Paris-Est', 'Paris 10e Arrondissement', 48.8765, 2.359);
        $this->stationAt('Clamart', 'Clamart', 48.8143, 2.275);
        $this->stationAt('Sèvres-Ville-d\'Avray', 'Sèvres', 48.82, 2.2);
        $this->stationAt('Lyon-Perrache', 'Lyon 2e Arrondissement', 45.7487, 4.8264);
        $this->stationAt('Evry-Val de Seine', 'Évry-Courcouronnes', 48.626, 2.44);

        $this->artisan('vff:import-open-data-media', ['--offline' => true])->expectsOutputToContain('0 erreur(s)')->assertSuccessful();

        $this->assertGreaterThan(0, Media::query()->count());
        foreach (Media::query()->get() as $m) {
            $this->assertNotNull($m->license ?? ($m->rights_status === 'public_domain' ? 'pd' : null), "{$m->provider}:{$m->external_id} sans licence");
            $this->assertNotSame('', trim((string) $m->attribution));
            $this->assertNotNull($m->source_url);
            $this->assertNotNull($m->thumb_url);
            $this->assertNotNull($m->commercial_ok);
            $this->assertNotNull(DB::table('media')->where('id', $m->id)->value('derivatives_ok'));
        }
    }

    public function test_a_railway_view_with_a_precise_point_is_attached_to_the_line_under_it(): void
    {
        $this->seedCache('ad92-9fi');
        $line = Line::factory()->create(['name' => 'Ligne des Invalides à Versailles-Rive-Gauche']);
        // Le viaduc de Meudon (9FI/MEU_328, point 48.8131389, 2.2464167) : un tracé passe à moins de 20 m.
        DB::table('line_reference_parts')->insert(['line_id' => $line->id, 'source' => 'osm', 'source_trace_ids' => '[]', 'length_m' => 500,
            'geom' => DB::raw("ST_Multi(ST_GeomFromText('LINESTRING(2.2440 48.8131, 2.2490 48.8132)', 4326))")]);
        // Une gare dans la commune ne doit pas la prendre : la vue ne parle pas de gare.
        $this->stationAt('Meudon', 'Meudon', 48.8130, 2.2350);

        $this->artisan('vff:import-open-data-media', ['source' => ['ad92-9fi'], '--offline' => true])->assertSuccessful();

        $m = Media::query()->where('provider', 'ad92-9fi')->where('external_id', '9FI/MEU_328')->firstOrFail();
        $this->assertSame(Line::class, $m->mediable_type);
        $this->assertSame($line->id, $m->mediable_id);
        $this->assertSame('ligne-point', $m->provider_meta['method']);
        $this->assertSame('Licence Ouverte (Etalab)', $m->license);
        $this->assertSame('Archives départementales des Hauts-de-Seine, 9FI/MEU_328, vers 1900, Licence Ouverte (Etalab)', $m->attribution);
    }

    public function test_street_names_and_funicular_stations_are_left_for_review(): void
    {
        $this->seedCache('ad92-9fi');
        $this->stationAt('Le Bas-Meudon', 'Meudon', 48.8220, 2.2320);
        $this->stationAt('Chaville-Vélizy', 'Viroflay', 48.8084, 2.1892);
        $this->stationAt('Chaville-Rive-Gauche', 'Chaville', 48.8081, 2.1887);
        $review = OpenDataFetcher::cacheDir().'/review.jsonl';

        $this->artisan('vff:import-open-data-media', ['source' => ['ad92-9fi'], '--offline' => true, '--dry-run' => true, '--review' => $review])->assertSuccessful();

        $rows = array_map(fn (string $l) => json_decode($l, true), file($review, FILE_IGNORE_NEW_LINES) ?: []);
        $reasons = array_column($rows, 'reason', 'id');
        $this->assertSame('sans_mot_gare', $reasons['9FI/MEU_105'] ?? null, 'Gare du Funiculaire : vue de funiculaire, pas de la gare du Bas-Meudon');
        $this->assertSame('sans_mot_gare', $reasons['9FI/CHA_594'] ?? null, 'Café-Restaurant de la Gare : un commerce, pas la gare');
    }

    public function test_an_unknown_dataset_key_fails(): void
    {
        $this->artisan('vff:import-open-data-media', ['source' => ['inconnu'], '--offline' => true])->assertFailed();
    }

    public function test_without_cache_and_offline_the_dataset_is_skipped(): void
    {
        $this->artisan('vff:import-open-data-media', ['source' => ['sevres-cp'], '--offline' => true, '--dry-run' => true])
            ->expectsOutputToContain('pas de cache')
            ->assertSuccessful();
        Http::assertNothingSent();
    }
}
