<?php

namespace Tests\Feature\Commands;

use App\Models\Line;
use App\Models\Station;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * vff:import-argentanwebferro et vff:import-argentanwebferro-media sur un faux site (pages
 * WordPress et galerie Piwigo simulées) : faits en tables de source, comparaison avec VFF,
 * photos classées, aucune écriture dans les tables du moteur, politesse (robots.txt,
 * User-Agent, cache réutilisé), simulation de l'import des médias.
 */
class ImportArgentanWebFerroCommandTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    private const ARTICLE = '<p>Les dates:</p>'
        .'<p>1883: Ouverture de la ligne Montsecret-Vassy jusqu’à la gare des Maures.</p>'
        .'<p>1918: Fermeture du service marchandises, réquisition et dépose de la voie par l’armée entre Tinchebray et Les Maures</p>'
        .'<p>1964: Fermeture de la ligne.</p>'
        .'<figure><img src="x.jpg"/><figcaption>Les Maures '."\u{2013}".' 1986</figcaption></figure>'
        .'<p>La ligne desservait les arrêts, haltes et gares de:</p>'
        .'<p>Montsecret-Vassy (gare)</p><p>St Pierre d’Entremont (arrêt)</p><p>Tinchebray (gare)</p><p>Les Maures (gare)</p>'
        .'<p>1888->1891: Exploitation par la CGFCS</p>';

    private Line $montsecret;

    private Station $tinchebray;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
        Sleep::fake();

        $this->montsecret = Line::factory()->create(['slug' => 'archeo-ligne-de-montsecret-aux-maures', 'name' => 'Ligne de Montsecret aux Maures',
            'code' => null, 'network' => 'secondaire']);
        foreach ([['montsecret-vassy', 'Montsecret-Vassy', 0], ['tinchebray', 'Tinchebray', 11510], ['les-maures', 'Les Maures', 19445]] as [$slug, $name, $pk]) {
            $station = $this->station($slug, $name, 48.8, -0.7 + $pk / 100000);
            DB::table('station_lines')->insert(['station_id' => $station->id, 'line_id' => $this->montsecret->id, 'method' => 'source_pk', 'pk_m' => $pk,
                'created_at' => now(), 'updated_at' => now()]);
            if ($slug === 'tinchebray') {
                $this->tinchebray = $station;
            }
        }
        DB::table('line_events')->insert(['line_id' => $this->montsecret->id, 'type' => 'closure', 'event_date' => '1964-01-01', 'date_precision' => 'year',
            'description' => 'closure', 'source' => 'wikipedia-reseaux', 'confidence' => 'medium', 'created_at' => now(), 'updated_at' => now()]);

        $this->useDataFile('argentanwebferro.php', [
            'articles' => ['de-montsecret-vassy-aux-maures' => ['lines' => ['archeo-ligne-de-montsecret-aux-maures']]],
            'facts' => [],
            'added' => [],
            'photo_albums' => ['Montsecret-Les Maures' => ['archeo-ligne-de-montsecret-aux-maures']],
            'photos' => ['own_work' => ['photo couleur de l\'auteur' => [501]]],
        ]);
        $this->fakeSite();
    }

    private function fakeSite(): void
    {
        $gallery = fn (array $result) => "<br />\n<b>Warning</b>: Undefined array key 21411<br />\n".json_encode(['stat' => 'ok', 'result' => $result]);
        $image = fn (int $id, string $name, string $file) => [
            'id' => $id, 'name' => $name, 'comment' => '', 'file' => $file, 'width' => 1024, 'height' => 683,
            'date_creation' => '2019-04-17 10:12:13', 'date_available' => '2019-04-18 08:00:00',
            'page_url' => "https://argentanwebferro.fr/AWF/picture.php?/{$id}",
            'derivatives' => ['thumb' => ['url' => "https://argentanwebferro.fr/AWF/_data/i/upload/{$id}-th.jpg"], 'medium' => ['url' => "https://argentanwebferro.fr/AWF/upload/{$id}.jpg"]],
            'categories' => [['id' => 290], ['id' => 103]],
        ];

        Http::fake([
            'argentanwebferro.fr/robots.txt' => Http::response("User-agent: *\nDisallow: /wp-admin/\n"),
            'argentanwebferro.fr/wp-json/wp/v2/categories*' => Http::response([['id' => 15, 'slug' => 'les-lignes']]),
            'argentanwebferro.fr/wp-json/wp/v2/pages*' => Http::response([[
                'id' => 151, 'slug' => 'de-montsecret-vassy-aux-maures', 'link' => 'https://argentanwebferro.fr/de-montsecret-vassy-aux-maures/',
                'title' => ['rendered' => 'De Montsecret-Vassy aux Maures &#8230;'], 'date' => '2019-10-07T10:00:00', 'modified' => '2021-01-06T12:00:00',
                'content' => ['rendered' => self::ARTICLE],
            ]]),
            'argentanwebferro.fr/wp-json/wp/v2/posts*' => Http::response([[
                'id' => 9, 'slug' => 'musique', 'link' => 'https://argentanwebferro.fr/musique/', 'title' => ['rendered' => 'Musique'],
                'date' => '2020-01-01T10:00:00', 'modified' => '2020-01-01T10:00:00', 'categories' => [15],
                'content' => ['rendered' => '<p>En 1964, fermeture d’un concert.</p>'],
            ]]),
            'argentanwebferro.fr/AWF/ws.php?format=json&method=pwg.categories.getList*' => Http::response($gallery(['categories' => [
                ['id' => 28, 'name' => 'Ferroviaire / Lignes Normandes'],
                ['id' => 290, 'name' => 'Ferroviaire / Lignes Normandes / Montsecret-Les Maures / Tinchebray'],
                ['id' => 103, 'name' => 'Carroussel'],
            ]])),
            'argentanwebferro.fr/AWF/ws.php?format=json&method=pwg.categories.getImages*' => Http::response($gallery(['images' => [
                $image(501, 'Tinchebray', 'tinchebray-201.JPG'), $image(502, 'Tinchebray', 'tinchebray-001.jpg'),
            ]])),
            'argentanwebferro.fr/AWF/ws.php?format=json&method=pwg.tags.getImages*' => Http::response($gallery(['images' => []])),
        ]);
    }

    public function test_import_writes_only_source_tables_and_compares_with_vff(): void
    {
        $engine = $this->snapshot();

        $this->artisan('vff:import-argentanwebferro')->assertSuccessful();

        $this->assertSame($engine, $this->snapshot(), 'aucune écriture dans les tables du moteur');
        $this->assertSame(2, DB::table('argentanwebferro_articles')->count());
        $this->assertSame(1, DB::table('argentanwebferro_articles')->where('is_line_article', true)->count());
        $this->assertSame(0, DB::table('argentanwebferro_facts')->where('source_url', 'like', '%musique%')->count(), 'article hors ligne : inventorié, sans fait');

        $closure = DB::table('argentanwebferro_facts')->where('event_type', 'closure')->where('year', 1964)->first();
        $this->assertSame('confirmé', $closure->vff_status);
        $this->assertSame($this->montsecret->id, (int) $closure->line_id);
        $this->assertSame('https://argentanwebferro.fr/de-montsecret-vassy-aux-maures/', $closure->source_url);

        $dismantling = DB::table('argentanwebferro_facts')->where('event_type', 'dismantling')->first();
        $this->assertSame(['1918-01-01', 'year', 'Tinchebray', 'Les Maures', 'nouveau'],
            [$dismantling->event_date, $dismantling->date_precision, $dismantling->section_from, $dismantling->section_to, $dismantling->vff_status]);

        $missing = DB::table('argentanwebferro_facts')->where('kind', 'stop')->where('stop_name', "St Pierre d'Entremont")->first();
        $this->assertSame(['point_arret', 'absent de VFF'], [$missing->stop_nature, $missing->vff_status]);
        $this->assertSame(0, DB::table('argentanwebferro_facts')->where('stop_name', 'like', '%1986%')->count(), 'légende de photo ignorée');
        $this->assertSame('CGFCS', DB::table('argentanwebferro_facts')->where('kind', 'operator')->value('operator'));
        $this->assertSame(0, DB::table('argentanwebferro_facts')->whereRaw("source_quote ~ '[\u{2013}\u{2014}]'")->count(), 'pas de tiret long');
        $this->assertSame(0, DB::table('argentanwebferro_facts')->whereRaw('char_length(source_quote) > 240')->count());

        $own = DB::table('argentanwebferro_photos')->where('piwigo_id', 501)->first();
        $this->assertSame(['own_work', true, $this->tinchebray->id], [$own->photo_class, (bool) $own->republishable, (int) $own->station_id]);
        $payload = json_decode($own->media_payload, true);
        $this->assertSame(['argentanwebferro', '501', 'CC BY-SA 4.0', 'Thierry Danglos', true, Line::class, $this->montsecret->id, $this->tinchebray->id, 2019],
            [$payload['provider'], $payload['external_id'], $payload['license'], $payload['author'], $payload['share_alike'],
                $payload['mediable_type'], $payload['mediable_id'], $payload['provider_meta']['station_id'], $payload['depicted_year']]);
        $other = DB::table('argentanwebferro_photos')->where('piwigo_id', 502)->first();
        $this->assertSame(['unknown', false, null], [$other->photo_class, (bool) $other->republishable, $other->media_payload], 'non relue : jamais republiée');

        $csv = storage_path('app/reports/agents/source-argentan-comparaison.csv');
        $this->assertFileExists($csv);
        $this->assertStringContainsString("St Pierre d'Entremont", (string) file_get_contents($csv));

        Http::assertSent(fn (Request $r) => $r->header('User-Agent') === ['VFF-Bot/1.0 (+https://vff-project.org)']);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/wp-admin/'));
    }

    public function test_second_run_reads_the_cache_and_changes_nothing(): void
    {
        $this->artisan('vff:import-argentanwebferro')->assertSuccessful();
        $sent = count(Http::recorded());
        $before = $this->snapshot(['argentanwebferro_facts', 'argentanwebferro_photos'], ['id', 'article_id']);

        $this->artisan('vff:import-argentanwebferro --offline')->assertSuccessful();
        $this->artisan('vff:import-argentanwebferro')->assertSuccessful();

        $this->assertSame($sent, count(Http::recorded()), 'tout vient du cache local');
        $this->assertSame($before, $this->snapshot(['argentanwebferro_facts', 'argentanwebferro_photos'], ['id', 'article_id']));
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->artisan('vff:import-argentanwebferro --dry-run')->assertSuccessful();

        $this->assertSame(0, DB::table('argentanwebferro_articles')->count());
        $this->assertSame(0, DB::table('argentanwebferro_facts')->count());
        $this->assertSame(0, DB::table('argentanwebferro_photos')->count());
    }

    public function test_curated_correction_and_rejection(): void
    {
        $this->artisan('vff:import-argentanwebferro --dry-run --no-gallery')->assertSuccessful();
        $this->artisan('vff:import-argentanwebferro --no-gallery')->assertSuccessful();
        $opening = DB::table('argentanwebferro_facts')->where('event_type', 'opening')->first();
        $operator = DB::table('argentanwebferro_facts')->where('kind', 'operator')->first();

        $this->useDataFile('argentanwebferro.php', [
            'articles' => ['de-montsecret-vassy-aux-maures' => ['lines' => ['archeo-ligne-de-montsecret-aux-maures']]],
            'facts' => [
                $opening->fingerprint => ['from' => 'Montsecret-Vassy', 'to' => 'Les Maures', 'note' => 'ligne entière'],
                $operator->fingerprint => ['reject' => 'essai'],
            ],
            'added' => ['de-montsecret-vassy-aux-maures' => [
                ['kind' => 'event', 'type' => 'closure_passenger', 'year' => 1938, 'precision' => 'year', 'quote' => 'Arrêt du dernier MV entre Montsecret et Tinchebray.'],
            ]],
            'photo_albums' => [], 'photos' => [],
        ]);
        $this->artisan('vff:import-argentanwebferro --no-gallery')->assertSuccessful();

        $corrected = DB::table('argentanwebferro_facts')->where('fingerprint', $opening->fingerprint)->first();
        $this->assertSame(['curated', 'Montsecret-Vassy', 'Les Maures', 'ligne entière'], [$corrected->review, $corrected->section_from, $corrected->section_to, $corrected->note]);
        $this->assertSame('rejected', DB::table('argentanwebferro_facts')->where('fingerprint', $operator->fingerprint)->value('review'));
        $added = DB::table('argentanwebferro_facts')->where('event_type', 'closure_passenger')->first();
        $this->assertSame(['curated', 'nouveau'], [$added->review, $added->vff_status]);
    }

    public function test_media_apply_copies_the_authors_photos_once_and_credits_them(): void
    {
        $this->artisan('vff:import-argentanwebferro')->assertSuccessful();
        Http::fake(['argentanwebferro.fr/AWF/upload/*' => Http::response('JPEG', 200, ['Content-Type' => 'image/jpeg'])]);

        $this->artisan('vff:import-argentanwebferro-media --apply')->expectsOutputToContain('1 photo(s) copiée(s)')->assertSuccessful();

        $this->assertTrue(Storage::disk('public')->exists('media/argentanwebferro/501.jpg'));
        $this->assertTrue(Storage::disk('public')->exists('media/argentanwebferro/.nobackup'), 'copies régénérables : hors sauvegardes');
        $this->assertFalse(Storage::disk('public')->exists('media/argentanwebferro/502.jpg'), 'photo non relue : jamais copiée');
        $media = DB::table('media')->where('provider', 'argentanwebferro')->sole();
        $this->assertSame(
            ['501', Line::class, $this->montsecret->id, 'Thierry Danglos', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0',
                'https://argentanwebferro.fr/AWF/picture.php?/501', 'open_license', true, 'Thierry Danglos, CC BY-SA 4.0, via Argentan Web Ferroviaire', null],
            [$media->external_id, $media->mediable_type, (int) $media->mediable_id, $media->author, $media->license, $media->license_url,
                $media->source_url, $media->rights_status, (bool) $media->share_alike, $media->attribution, $media->thumb_url],
        );

        $api = $this->getJson("/api/v1/lines/{$this->montsecret->slug}/media")->assertOk()->json('data.0');
        $this->assertSame(['Argentan Web Ferroviaire', Storage::url('media/argentanwebferro/501.jpg'), 'Thierry Danglos'],
            [$api['provider_label'], $api['thumb_url'], $api['author']]);

        $sent = count(Http::recorded());
        $this->artisan('vff:import-argentanwebferro-media --apply')->assertSuccessful();
        $this->assertSame($sent, count(Http::recorded()), 'déjà copiée : pas de nouveau téléchargement');
        $this->assertSame(1, DB::table('media')->where('provider', 'argentanwebferro')->count(), 'rejouable');
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/AWF/upload/501.jpg') && $r->header('User-Agent') === ['VFF-Bot/1.0 (+https://vff-project.org)']);
    }

    public function test_media_import_is_a_costed_simulation_by_default(): void
    {
        $this->artisan('vff:import-argentanwebferro')->assertSuccessful();
        $media = DB::table('media')->count();
        $sent = count(Http::recorded());

        $this->artisan('vff:import-argentanwebferro-media')
            ->expectsOutputToContain('1 photo(s) republiable(s), 1 rattachée(s)')
            ->expectsOutputToContain('Simulation')
            ->assertSuccessful();

        $this->assertSame($media, DB::table('media')->count(), 'rien n\'est inséré sans --apply');
        $this->assertSame($sent, count(Http::recorded()), 'rien n\'est téléchargé sans --apply');
    }
}
