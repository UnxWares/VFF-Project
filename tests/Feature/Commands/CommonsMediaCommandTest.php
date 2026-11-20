<?php

namespace Tests\Feature\Commands;

use App\Models\Line;
use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * vff:import-commons-media : médias Wikimedia Commons d'une ligne (métadonnées
 * et droits seulement, jamais de copie de fichier). Wikidata (image P18,
 * catégorie P373) et l'API Commons sont simulés.
 */
class CommonsMediaCommandTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    private Line $line;

    /** @var array<int, array<string, mixed>> pageid → page Commons simulée */
    private array $files = [];

    /** @var array<string, list<array<string, mixed>>>|null membres des catégories (null : jeu par défaut), lu à chaque requête */
    private ?array $members = null;

    /** @var list<array<string, mixed>> réponse de la recherche géographique */
    private array $geo = [];

    /** @var array<int, list<string>> catégories visibles par fichier */
    private array $categories = [];

    private int $geoFailures = 0;

    private int $geoCalls = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
        Sleep::fake();
        $this->line = $this->rfnLine('482000', 'Ligne de Carhaix à Morlaix');
        $this->files = [
            100 => $this->file('File:Gare de Carhaix vers 1910.jpg', 'image/jpeg', 1200, 'CC BY-SA 4.0', 'Jean Dupont', 'vers 1910'),
            101 => $this->file('File:Viaduc de Morlaix.jpg', 'image/jpeg', 2000, 'Public domain', 'Anonyme', '1925'),
            102 => $this->file('File:Logo SNCF.svg', 'image/svg+xml', 300, 'Public domain', null, null),
            103 => $this->file('File:Carhaix station 1960.jpg', 'image/jpeg', 1600, 'CC BY 2.0', 'Marie', '1960'),
            104 => $this->file('File:Icône petite.png', 'image/png', 64, 'CC0', null, null),
        ];
    }

    /** @return array<string, mixed> */
    private function file(string $title, string $mime, int $width, string $license, ?string $author, ?string $date): array
    {
        return ['title' => $title, 'imageinfo' => [[
            'mime' => $mime, 'width' => $width, 'height' => (int) ($width * 0.75),
            'url' => 'https://upload.wikimedia.org/x/'.rawurlencode($title),
            'thumburl' => 'https://upload.wikimedia.org/thumb/'.rawurlencode($title),
            'descriptionurl' => 'https://commons.wikimedia.org/wiki/'.rawurlencode($title),
            'extmetadata' => array_filter([
                'LicenseShortName' => ['value' => $license],
                'Artist' => $author ? ['value' => $author] : null,
                'DateTimeOriginal' => $date ? ['value' => $date] : null,
                'Categories' => ['value' => 'Ligne de Carhaix à Morlaix'],
            ]),
        ]]];
    }

    private function fakeWikimedia(bool $wikidataDown = false): void
    {
        Http::fake([
            'query.wikidata.org/*' => $wikidataDown ? Http::response('', 500) : Http::response(['results' => ['bindings' => [[
                'item' => ['value' => 'http://www.wikidata.org/entity/Q3239771'],
                'code' => ['value' => '482 000'],
                'cat' => ['value' => 'Ligne de Carhaix à Morlaix'],
                'img' => ['value' => 'http://commons.wikimedia.org/wiki/Special:FilePath/Gare_de_Carhaix_vers_1910.jpg'],
            ]]]]),
            'commons.wikimedia.org/*' => function (Request $r) {
                if (($r['list'] ?? null) === 'categorymembers') {
                    $all = $this->members ?? [
                        'Category:Ligne de Carhaix à Morlaix' => [
                            ['pageid' => 101, 'title' => 'File:Viaduc de Morlaix.jpg', 'type' => 'file'],
                            ['pageid' => 102, 'title' => 'File:Logo SNCF.svg', 'type' => 'file'],
                            ['pageid' => 104, 'title' => 'File:Icône petite.png', 'type' => 'file'],
                            ['pageid' => 900, 'title' => 'Category:Gare de Carhaix', 'type' => 'subcat'],
                            ['pageid' => 901, 'title' => 'Category:Photographs by Marie', 'type' => 'subcat'],
                        ],
                        'Category:Gare de Carhaix' => [['pageid' => 103, 'title' => 'File:Carhaix station 1960.jpg', 'type' => 'file']],
                    ];

                    return Http::response(['query' => ['categorymembers' => $all[$r['cmtitle']] ?? []]]);
                }
                if (($r['list'] ?? null) === 'geosearch') {
                    if ($this->geoFailures > 0) {
                        $this->geoFailures--;

                        return Http::response('surchargé', 400);
                    }
                    $this->geoCalls++;

                    return Http::response(['query' => ['geosearch' => $this->geo]]);
                }
                if (($r['prop'] ?? null) === 'categories') {
                    $pages = array_map(fn ($id) => ['pageid' => (int) $id, 'categories' => array_map(fn ($c) => ['title' => 'Category:'.$c], $this->categories[(int) $id] ?? [])],
                        explode('|', $r['pageids']));

                    return Http::response(['query' => ['pages' => $pages]]);
                }
                if (($r['prop'] ?? null) === 'categoryinfo') {
                    $pages = array_map(fn ($t) => ['title' => $t, 'categoryinfo' => ['files' => 5, 'subcats' => 1]], explode('|', $r['titles']));

                    return Http::response(['query' => ['pages' => $pages]]);
                }
                if (($r['prop'] ?? null) === 'imageinfo') {
                    $pages = [];
                    foreach (explode('|', $r['pageids']) as $id) {
                        $pages[] = ['pageid' => (int) $id] + ($this->files[(int) $id] ?? ['missing' => true]);
                    }

                    return Http::response(['query' => ['pages' => $pages]]);
                }

                // Titres de fichiers (image P18) → pageid.
                return Http::response(['query' => ['pages' => [['pageid' => 100, 'title' => 'File:Gare de Carhaix vers 1910.jpg']]]]);
            },
        ]);
    }

    /** @return array<string, array<string, mixed>> */
    private function media(): array
    {
        return DB::table('media')->where('provider', Media::PROVIDER_COMMONS)->orderBy('external_id')->get()
            ->mapWithKeys(fn ($m) => ['p'.$m->external_id => (array) $m])->all();
    }

    public function test_line_media_come_from_wikidata_and_its_categories_with_their_rights(): void
    {
        $this->fakeWikimedia();

        $this->artisan('vff:import-commons-media', ['--line' => ['482000'], '--no-geosearch' => true])
            ->expectsOutputToContain('1 ligne(s) reliées à Wikidata')
            ->expectsOutputToContain('■ 482000 - Ligne de Carhaix à Morlaix [Q3239771]')
            ->expectsOutputToContain('Terminé : 1 ligne(s) traitée(s), 1 avec médias, 3 média(s) écrits, 0 retiré(s), 0 erreur(s)')
            ->assertSuccessful();

        $media = $this->media();
        $this->assertSame(['p100', 'p101', 'p103'], array_keys($media), 'logo et icône de 64 px écartés');
        $this->assertTrue((bool) $media['p100']['is_featured'], 'image Wikidata P18 à la une');
        $this->assertFalse((bool) $media['p101']['is_featured']);
        $this->assertSame([Line::class, $this->line->id], [$media['p101']['mediable_type'], (int) $media['p101']['mediable_id']]);
        $this->assertSame(['Public domain', Media::RIGHTS_PUBLIC_DOMAIN, 1925], [$media['p101']['license'], $media['p101']['rights_status'], $media['p101']['depicted_year']]);
        $this->assertSame(['CC BY-SA 4.0', Media::RIGHTS_OPEN_LICENSE, true, true], [$media['p100']['license'], $media['p100']['rights_status'],
            (bool) $media['p100']['commercial_ok'], (bool) $media['p100']['share_alike']]);
        $this->assertStringStartsWith('https://upload.wikimedia.org/thumb/', $media['p103']['thumb_url'], 'miniature en lien direct, aucune copie');
        $meta = json_decode($media['p103']['provider_meta'], true);
        $this->assertSame(['category', 'Gare de Carhaix', 1], [$meta['via'], $meta['category'], $meta['depth']]);
        $this->assertSame('wikidata_p18', json_decode($media['p100']['provider_meta'], true)['via']);

        // Ré-import : même résultat.
        $before = $this->snapshot(['media'], ['id']);
        $this->artisan('vff:import-commons-media', ['--line' => ['482000'], '--no-geosearch' => true])->assertSuccessful();
        $this->assertSame($before, $this->snapshot(['media'], ['id']));
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->fakeWikimedia();

        $this->artisan('vff:import-commons-media', ['--line' => ['482000'], '--no-geosearch' => true, '--dry-run' => true])
            ->expectsOutputToContain('· [photo] 1925 Viaduc de Morlaix')
            ->assertSuccessful();

        $this->assertSame(0, DB::table('media')->count());
    }

    public function test_prune_removes_media_no_longer_retained_but_keeps_other_providers(): void
    {
        $this->fakeWikimedia();
        $this->artisan('vff:import-commons-media', ['--line' => ['482000'], '--no-geosearch' => true])->assertSuccessful();
        DB::table('media')->insert(['mediable_type' => Line::class, 'mediable_id' => $this->line->id, 'provider' => 'upload',
            'file_path' => 'media/x.jpg', 'type' => 'photo', 'title' => 'Photo d\'un contributeur', 'created_at' => now(), 'updated_at' => now()]);

        // La catégorie est désormais vide.
        $this->members = ['Category:Ligne de Carhaix à Morlaix' => []];

        $this->artisan('vff:import-commons-media', ['--line' => ['482000'], '--no-geosearch' => true, '--prune' => true])
            ->expectsOutputToContain('2 retiré(s)')
            ->assertSuccessful();

        $this->assertSame(['p100'], array_keys($this->media()), 'seule l\'image P18 reste');
        $this->assertTrue(DB::table('media')->where('provider', 'upload')->exists(), 'les autres médias ne sont jamais touchés');
    }

    public function test_wikidata_outage_falls_back_on_the_category_named_after_the_line(): void
    {
        $this->fakeWikimedia(wikidataDown: true);

        $this->artisan('vff:import-commons-media', ['--line' => ['482000'], '--no-geosearch' => true, '--only-missing' => true])
            ->expectsOutputToContain('Wikidata indisponible')
            ->expectsOutputToContain('1 catégorie(s) Commons trouvée(s) par le nom de ligne')
            ->expectsOutputToContain('2 média(s) écrits')
            ->assertSuccessful();

        $this->assertSame(['p101', 'p103'], array_keys($this->media()), 'sans Wikidata, pas d\'image P18');
        // --only-missing : la ligne a désormais des médias, elle n'est plus traitée.
        $this->artisan('vff:import-commons-media', ['--line' => ['482000'], '--only-missing' => true])
            ->expectsOutputToContain('Aucune ligne sélectionnée.')
            ->assertSuccessful();
    }

    public function test_geosearch_keeps_railway_files_close_to_the_track(): void
    {
        $era = $this->era(2030, current: true);
        $this->track($this->line, $era, [[48.5, -3.9], [48.5, -3.8]]);
        $this->files += [
            200 => $this->file('File:Gare de Pleyber-Christ.jpg', 'image/jpeg', 1200, 'CC BY 4.0', 'Paul', '1950'),
            202 => $this->file('File:Paysage breton.jpg', 'image/jpeg', 1200, 'CC BY 4.0', 'Paul', '1980'),
        ];
        $this->geo = [
            ['pageid' => 200, 'title' => 'File:Gare de Pleyber-Christ.jpg', 'lat' => 48.5003, 'lon' => -3.85],
            ['pageid' => 201, 'title' => 'File:Gare routière de Carhaix.jpg', 'lat' => 48.5, 'lon' => -3.86],
            ['pageid' => 202, 'title' => 'File:Paysage breton.jpg', 'lat' => 48.5005, 'lon' => -3.87],
            ['pageid' => 203, 'title' => 'File:Rame à Brest.jpg', 'lat' => 48.5, 'lon' => -3.88],
            ['pageid' => 204, 'title' => 'File:Gare lointaine.jpg', 'lat' => 48.55, 'lon' => -3.85],
            ['pageid' => 101, 'title' => 'File:Viaduc de Morlaix.jpg', 'lat' => 48.5001, 'lon' => -3.89],
        ];
        $this->categories = [202 => ['Railway lines in Brittany'], 203 => ['Trams in Brest']];
        $this->files[202]['imageinfo'][0]['extmetadata']['Categories']['value'] = 'Railway lines in Brittany';
        $this->geoFailures = 1;
        $this->fakeWikimedia();

        $this->artisan('vff:import-commons-media', ['--line' => ['482000']])
            ->expectsOutputToContain('  géo : 1 point(s) sur 8 en échec (recherche Commons indisponible)')
            ->expectsOutputToContain('géo : 8 point(s), 4 fichier(s) à ≤ 150 m dont 2 ferroviaire(s)')
            ->assertSuccessful();

        $media = $this->media();
        $this->assertSame(['p100', 'p101', 'p103', 'p200', 'p202'], array_keys($media), 'gare routière, tramway et fichier lointain écartés');
        $meta = json_decode($media['p200']['provider_meta'], true);
        $this->assertSame(['geosearch', 32], [$meta['via'], $meta['distance_m']]);
        $this->assertSame(55, json_decode($media['p202']['provider_meta'], true)['distance_m']);
        $this->assertSame(11, json_decode($media['p101']['provider_meta'], true)['distance_m'], 'fichier de catégorie : distance au tracé ajoutée');
        $this->assertGreaterThan(0, $this->geoCalls);

        // --geo-max : seulement les plus proches.
        DB::table('media')->delete();
        $this->artisan('vff:import-commons-media', ['--line' => ['482000'], '--geo-max' => 1])->assertSuccessful();
        $this->assertSame(['p100', 'p101', 'p103', 'p200'], array_keys($this->media()));
    }
}
