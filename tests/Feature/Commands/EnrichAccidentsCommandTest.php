<?php

namespace Tests\Feature\Commands;

use App\Enums\AccidentLinkConfidence;
use App\Models\Accident;
use App\Models\AccidentContextEntry;
use App\Models\AccidentImage;
use App\Models\AccidentLine;
use App\Models\AccidentReference;
use App\Services\Accidents\AccidentCandidate;
use App\Services\Accidents\AccidentLineMatcher;
use App\Services\Accidents\Context\AccidentContextImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * vff:enrich-accidents : contrôle des fichiers de contexte, écriture rejouable des documents, énoncés et images,
 * corrections appliquées puis rendues, retrait du contexte d'un fichier disparu, simulation, images non libres
 * écartées, politesse envers Commons (User-Agent, cache réutilisé hors ligne).
 */
class EnrichAccidentsCommandTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    private const FIXTURES = __DIR__.'/../../Fixtures/AccidentContexts';

    private Accident $mande;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
        Sleep::fake();
        $this->mande = Accident::factory()->on('1891-07-26')->create([
            'slug' => '1891-07-26-saint-mande', 'kind' => 'collision', 'deaths' => 43, 'injuries' => null,
            'commune_name' => 'Saint-Mandé', 'department_code' => '94', 'toll_source' => 'wikidata',
            'provenance' => ['deaths' => 'wikidata'],
        ]);
        Accident::factory()->on('2013-07-12')->create(['slug' => '2013-07-12-bretigny-sur-orge', 'kind' => 'derailment', 'deaths' => 7]);
        Accident::factory()->on('2013-07-12')->create(['slug' => '2013-07-12-doublon', 'kind' => 'derailment', 'deaths' => 7]);
        DB::statement("INSERT INTO communes (code_insee, type, nom, nom_norm, departement, geom, created_at, updated_at)
            VALUES ('94080', 'commune-actuelle', 'Vincennes', 'vincennes', '94', ST_SetSRID(ST_MakePoint(2.44, 48.85), 4326), now(), now())");
        $this->fakeCommons();
    }

    public function test_check_reports_every_broken_rule_and_writes_nothing(): void
    {
        $this->artisan('vff:enrich-accidents', ['--check' => true, '--dir' => self::FIXTURES.'/invalid'])
            ->expectsOutputToContain('fournisseur « inconnu » absent du registre')
            ->expectsOutputToContain('clé inconnue « unknown »')
            ->expectsOutputToContain('checked_on : date AAAA-MM-JJ attendue')
            ->expectsOutputToContain('tiret long')
            ->expectsOutputToContain('texte « de » manquant')
            ->expectsOutputToContain('document « payant » non lu')
            ->expectsOutputToContain('citation permise seulement')
            ->expectsOutputToContain('champ « commune_name » non corrigeable')
            ->assertFailed();

        $this->assertSame(0, AccidentReference::query()->count());
    }

    public function test_writes_context_references_images_and_corrections(): void
    {
        $this->artisan('vff:enrich-accidents', ['--dir' => self::FIXTURES.'/full'])
            ->expectsOutputToContain('Image écartée : 1891-07-26-saint-mande : File:Image non libre.jpg (licence non libre')
            ->expectsOutputToContain('Correction : 1891-07-26-saint-mande : deaths 43 -> 49')
            ->assertSuccessful();

        $this->assertSame(['rapport', 'journal', 'retronews'], AccidentReference::query()->where('accident_id', $this->mande->id)->orderBy('position')->pluck('key')->all());
        $entries = AccidentContextEntry::query()->where('accident_id', $this->mande->id)->orderBy('position')->get();
        $this->assertSame(['circumstances', 'causes', 'disagreements'], $entries->map(fn ($e) => $e->section->value)->all());
        // Typographie : apostrophe courbe, espaces insécables dans les nombres et les heures.
        $this->assertSame("Deux trains de retour de la fête se suivent vers 21\u{00A0}h\u{00A0}18, environ 1\u{00A0}000 voyageurs.", $entries[0]->text['fr']);
        $this->assertSame("L\u{2019}enquête retient un signal mal observé.", $entries[1]->text['fr']);
        $this->assertEquals(['text' => 'Le signal était fermé.', 'lang' => 'fr', 'reference' => 'rapport'], $entries[1]->quote);

        $image = AccidentImage::query()->sole();
        $this->assertSame('File:Saint-Mandé essai.jpg', $image->file);
        $this->assertSame('Jean Dupont', $image->author);
        $this->assertSame('CC BY-SA 4.0', $image->licence);

        $mande = $this->mande->fresh();
        $this->assertSame(49, $mande->deaths);
        $this->assertSame('21:18:00', $mande->occurred_at_time);
        $this->assertSame('context', $mande->toll_source);
        $this->assertSame('context', $mande->provenance['deaths']);
        $this->assertSame('2026-11-21', $mande->context_checked_on?->toDateString());
        $this->assertSame(43, $mande->context_corrections[0]['before']);
        $this->assertStringContainsString('49', $mande->summary['fr']);
        // Nature et commune corrigées : fiche placée au centre de la commune, provenance « context », nom refait.
        $this->assertSame('derailment', $mande->kind->value);
        $this->assertSame(['94080', 'Vincennes', '94', 'commune', 'context'], [$mande->commune_insee, $mande->commune_name, $mande->department_code, $mande->location_precision?->value, $mande->location_source]);
        $this->assertSame('context', $mande->provenance['location']);
        $point = DB::selectOne('SELECT ST_X(geom) AS lng FROM accidents WHERE id = ?', [$mande->id]);
        $this->assertEqualsWithDelta(2.44, (float) $point->lng, 0.0001);

        // Doublon déclaré : la fiche devient un alias de Brétigny, jamais publiée.
        $doublon = Accident::withoutGlobalScopes()->where('slug', '2013-07-12-doublon')->sole();
        $this->assertSame(Accident::query()->where('slug', '2013-07-12-bretigny-sur-orge')->value('id'), $doublon->alias_of_id);
        $this->assertNull(Accident::query()->where('slug', '2013-07-12-doublon')->first());

        // Politesse : User-Agent générique de VFF.
        Http::assertSent(fn (Request $r) => $r->hasHeader('User-Agent', 'VFF-Bot/1.0 (+https://vff-project.org)'));
    }

    public function test_second_run_offline_changes_nothing(): void
    {
        $this->artisan('vff:enrich-accidents', ['--dir' => self::FIXTURES.'/full'])->assertSuccessful();
        $before = $this->snapshot();
        Http::fake(fn () => throw new \RuntimeException('aucune requête attendue'));

        $this->artisan('vff:enrich-accidents', ['--dir' => self::FIXTURES.'/full', '--offline' => true])->assertSuccessful();

        $this->assertSame($before, $this->snapshot());
    }

    public function test_a_removed_correction_restores_the_value_and_a_missing_file_removes_the_context(): void
    {
        $this->artisan('vff:enrich-accidents', ['--dir' => self::FIXTURES.'/full'])->assertSuccessful();
        $this->artisan('vff:enrich-accidents', ['--dir' => self::FIXTURES.'/reduced'])->assertSuccessful();

        $mande = $this->mande->fresh();
        $this->assertSame(43, $mande->deaths);
        $this->assertNull($mande->occurred_at_time);
        $this->assertSame('wikidata', $mande->toll_source);
        $this->assertSame('wikidata', $mande->provenance['deaths']);
        $this->assertNull($mande->context_corrections);
        $this->assertSame('collision', $mande->kind->value);
        $this->assertSame('Saint-Mandé', $mande->commune_name);
        // Le doublon n'est plus déclaré : il redevient une fiche publiée.
        $this->assertNull(Accident::withoutGlobalScopes()->where('slug', '2013-07-12-doublon')->value('alias_of_id'));
        $this->assertSame(0, AccidentImage::query()->count());

        // La fiche de Brétigny n'a plus de fichier dans ce dossier : son contexte est retiré.
        $bretigny = Accident::query()->where('slug', '2013-07-12-bretigny-sur-orge')->sole();
        $this->assertSame(0, AccidentContextEntry::query()->where('accident_id', $bretigny->id)->count());
        $this->assertNull($bretigny->context_checked_on);
    }

    public function test_a_file_in_error_keeps_the_aliases_it_declares(): void
    {
        $this->artisan('vff:enrich-accidents', ['--dir' => self::FIXTURES.'/full'])->assertSuccessful();

        // Le fichier de Brétigny devient illisible pour le contrôle (date de recherche invalide) : il n'écrit
        // rien, donc le doublon qu'il déclare reste un alias.
        $dir = $this->sandbox.'/contexts';
        File::ensureDirectoryExists($dir);
        File::copy(self::FIXTURES.'/full/1891-07-26-saint-mande.php', $dir.'/1891-07-26-saint-mande.php');
        File::put($dir.'/2013-07-12-bretigny-sur-orge.php', str_replace(
            "'checked_on' => '2026-11-20'", "'checked_on' => 'hier'",
            File::get(self::FIXTURES.'/full/2013-07-12-bretigny-sur-orge.php'),
        ));
        $this->artisan('vff:enrich-accidents', ['--dir' => $dir])->assertFailed();

        $bretigny = Accident::query()->where('slug', '2013-07-12-bretigny-sur-orge')->value('id');
        $this->assertSame($bretigny, Accident::withoutGlobalScopes()->where('slug', '2013-07-12-doublon')->value('alias_of_id'));
    }

    public function test_an_alias_no_longer_declared_gets_back_its_previous_state(): void
    {
        // Doublon déjà repéré avant le fichier (vff:verify-accidents) : le fichier le redit, puis disparaît.
        $bretigny = (int) Accident::query()->where('slug', '2013-07-12-bretigny-sur-orge')->value('id');
        DB::table('accidents')->where('slug', '2013-07-12-doublon')->update(['alias_of_id' => $bretigny]);

        $this->artisan('vff:enrich-accidents', ['--dir' => self::FIXTURES.'/full'])->assertSuccessful();
        $this->artisan('vff:enrich-accidents', ['--dir' => self::FIXTURES.'/reduced'])->assertSuccessful();

        $doublon = Accident::withoutGlobalScopes()->where('slug', '2013-07-12-doublon')->sole();
        $this->assertSame($bretigny, $doublon->alias_of_id);
        $this->assertNull($doublon->context_corrections);
    }

    public function test_a_removed_commune_correction_gives_back_the_line_links(): void
    {
        // Rattachement d'origine (import) : ligne citée par un article, méthode et PK de l'import.
        AccidentLine::query()->create([
            'accident_id' => $this->mande->id, 'line_id' => 101, 'is_primary' => true, 'era_year' => 1890,
            'distance_m' => 12, 'pk_m' => 4200, 'method' => 'wikipedia+proximity', 'confidence' => AccidentLinkConfidence::High,
            'score' => 0.95, 'cited_as' => 'Ligne de Vincennes', 'cited_url' => 'https://fr.wikipedia.org/w/index.php?oldid=1',
        ]);
        // Rattacheur qui trouve une autre ligne, quelle que soit la position.
        $this->app->instance(AccidentLineMatcher::class, new class extends AccidentLineMatcher
        {
            public function match(AccidentCandidate $c): array
            {
                return [['line_id' => 202, 'is_primary' => true, 'era_year' => 1890, 'distance_m' => 80, 'pk_m' => null,
                    'section_from_station_id' => null, 'section_to_station_id' => null, 'method' => 'proximity',
                    'confidence' => AccidentLinkConfidence::Low, 'score' => 0.4]];
            }

            public function nearestStation(AccidentCandidate $c): ?array
            {
                return null;
            }
        });

        $this->artisan('vff:enrich-accidents', ['--dir' => self::FIXTURES.'/full'])->assertSuccessful();
        $this->assertSame([202], AccidentLine::query()->where('accident_id', $this->mande->id)->pluck('line_id')->all());

        $this->artisan('vff:enrich-accidents', ['--dir' => self::FIXTURES.'/reduced'])->assertSuccessful();
        $links = AccidentLine::query()->where('accident_id', $this->mande->id)->get();
        $this->assertSame([101], $links->pluck('line_id')->all());
        $this->assertSame(['wikipedia+proximity', 4200, 'Ligne de Vincennes'], [$links[0]->method, $links[0]->pk_m, $links[0]->cited_as]);
    }

    public function test_only_touches_the_named_record_and_dry_run_writes_nothing(): void
    {
        $this->artisan('vff:enrich-accidents', ['--dir' => self::FIXTURES.'/full', '--dry-run' => true])
            ->expectsOutputToContain('Simulation : 2 fiche(s) écrite(s)')->assertSuccessful();
        $this->assertSame(0, AccidentReference::query()->count());
        $this->assertSame(43, $this->mande->fresh()->deaths);

        $this->artisan('vff:enrich-accidents', ['--dir' => self::FIXTURES.'/full', '--only' => ['2013-07-12-bretigny-sur-orge']])->assertSuccessful();
        $this->assertSame(1, AccidentReference::query()->count());
        $this->assertSame(0, AccidentReference::query()->where('accident_id', $this->mande->id)->count());
    }

    public function test_an_alias_or_unknown_record_is_refused(): void
    {
        DB::table('accidents')->where('id', $this->mande->id)->update(['alias_of_id' => Accident::query()->where('slug', '2013-07-12-bretigny-sur-orge')->value('id')]);

        $this->artisan('vff:enrich-accidents', ['--dir' => self::FIXTURES.'/full', '--check' => true])
            ->expectsOutputToContain('est un alias')->assertFailed();
    }

    public function test_typography_keeps_numbers_and_units_together(): void
    {
        $this->assertSame("1\u{00A0}300\u{00A0}m, 40\u{00A0}km/h, 5 morts, 22\u{00A0}h\u{00A0}47", AccidentContextImporter::typography(' 1 300 m, 40 km/h, 5 morts, 22 h 47 '));
    }

    /** @return array<string, mixed> */
    private function snapshot(): array
    {
        $rows = fn (string $table) => DB::table($table)->orderBy('id')->get()->map(fn ($r) => collect((array) $r)->except(['id', 'created_at', 'updated_at', 'retrieved_at'])->all())->all();

        return [
            'references' => $rows('accident_references'),
            'entries' => $rows('accident_context_entries'),
            'images' => $rows('accident_images'),
            'accidents' => DB::table('accidents')->orderBy('id')->get(['deaths', 'occurred_at_time', 'context_corrections', 'summary'])->map(fn ($r) => (array) $r)->all(),
        ];
    }

    /** API de Commons : un fichier libre (CC BY-SA 4.0), un fichier non libre. */
    private function fakeCommons(): void
    {
        Http::fake(function (Request $request) {
            $title = (string) ($request->data()['titles'] ?? '');
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $title = $title !== '' ? $title : (string) ($query['titles'] ?? '');
            $free = $title === 'File:Saint-Mandé essai.jpg';

            return Http::response(['batchcomplete' => true, 'query' => ['pages' => [[
                'pageid' => $free ? 1 : 2, 'ns' => 6, 'title' => $title,
                'imageinfo' => [[
                    'width' => 1200, 'height' => 800, 'mime' => 'image/jpeg',
                    'url' => 'https://upload.wikimedia.org/wikipedia/commons/a/ab/Essai.jpg',
                    'thumburl' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/ab/Essai.jpg/500px-Essai.jpg',
                    'descriptionurl' => 'https://commons.wikimedia.org/wiki/'.rawurlencode(str_replace(' ', '_', $title)),
                    'extmetadata' => $free ? [
                        'Artist' => ['value' => '<a href="//commons.wikimedia.org/wiki/User:JD">Jean Dupont</a>'],
                        'LicenseShortName' => ['value' => 'CC BY-SA 4.0'],
                        'LicenseUrl' => ['value' => 'https://creativecommons.org/licenses/by-sa/4.0'],
                        'DateTimeOriginal' => ['value' => '1891'],
                    ] : [
                        'Artist' => ['value' => 'X'],
                        'LicenseShortName' => ['value' => 'CC BY-NC 2.0'],
                    ],
                ]],
            ]]]]);
        });
    }
}
