<?php

namespace Tests\Feature\Commands;

use App\Enums\AccidentKind;
use App\Models\Accident;
use App\Models\Line;
use App\Services\Accidents\AccidentCandidate;
use App\Services\Accidents\AccidentImporter;
use App\Services\Accidents\WikipediaEditionCollector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Sleep;
use ReflectionMethod;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * vff:import-accidents sur de fausses sources (Wikidata, Wikipédia, page et bilan du BEA-TT) et
 * une petite ligne : fusion des sources d'un même accident, bilan de l'enquête préféré et
 * désaccord signalé, rattachement à la ligne avec portion et confiance, accident connu du seul
 * BEA-TT localisé à la commune près, idempotence, simulation, aucune écriture dans les tables du
 * moteur, politesse (User-Agent, cache réutilisé hors ligne).
 */
class ImportAccidentsCommandTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    private const ACCIDENT_TABLES = ['accidents', 'accident_lines', 'accident_sources'];

    private Line $line;

    /** Les listes d'accidents de Wikipédia sont fausses ou vides selon le test. */
    private bool $withList = false;

    /** Texte de la page de la liste pour les années 2010, quand un test le remplace. */
    private ?string $decadeText = null;

    /** Pages des listes de Wikipédia en anglais et en allemand (langue → titre → wikicode), vides sauf pour les tests qui les remplissent. */
    private array $editionPages = ['en' => [], 'de' => []];

    /** Blessés que donne l'article de Wikipédia (Wikidata en donne 14). */
    private int $articleInjuries = 18;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
        Sleep::fake();
        // Conteneur sans poppler : la conversion du PDF déjà en cache est relue.
        Process::fake(['*' => Process::result('', 'pdftohtml: introuvable', 127)]);

        $this->line = $this->rfnLine('679000', 'Ligne de Perpignan à Villefranche-Vernet-les-Bains', [
            'slug' => 'rfn-679000', 'wikipedia_title' => 'Ligne de Perpignan à Villefranche-Vernet-les-Bains',
        ]);
        foreach ([2010, 2020, 2030] as $year) {
            $this->track($this->line, $this->era($year, $year === 2030), [[42.685, 2.66], [42.685, 2.76]]);
        }
        $millas = $this->station('millas', 'Millas', 42.6855, 2.700);
        $feliu = $this->station('st-feliu-davall', 'St-Féliu-d\'Avall', 42.6855, 2.730);
        foreach ([[$millas, 480000], [$feliu, 484000]] as [$station, $pk]) {
            DB::table('station_lines')->insert(['station_id' => $station->id, 'line_id' => $this->line->id, 'method' => 'sncf_pk',
                'pk_m' => $pk, 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::statement("INSERT INTO communes (code_insee, type, nom, nom_norm, departement, geom, created_at, updated_at)
            VALUES ('66108', 'commune-actuelle', 'Millas', 'millas', '66', ST_SetSRID(ST_MakePoint(2.69, 42.69), 4326), now(), now())");
        DB::table('commune_contours')->insert(['code_insee' => '66108', 'nom' => 'Millas', 'departement' => '66',
            'geom' => DB::raw('ST_Multi(ST_MakeEnvelope(2.66, 42.66, 2.76, 42.72, 4326))'), 'created_at' => now(), 'updated_at' => now()]);

        $this->fakeSources();
        $this->cacheBeaTtConversion();
    }

    public function test_imports_merges_and_links_accidents(): void
    {
        $engineBefore = $this->snapshot();

        $this->artisan('vff:import-accidents')->assertSuccessful();

        $this->assertSame(2, Accident::count());
        $this->assertSame($engineBefore, $this->snapshot(), 'les tables du moteur ne doivent pas changer');

        $millas = Accident::query()->withCoordinates()->where('wikidata_id', 'Q45757847')->firstOrFail();
        $this->assertSame('2017-12-14-millas', $millas->slug);
        $this->assertSame('level_crossing', $millas->kind->value);
        $this->assertSame(6, $millas->deaths);
        $this->assertSame('bea-tt', $millas->toll_source);
        $this->assertSame(14, $millas->injuries);
        $this->assertSame('to_review', $millas->review_status->value);
        $this->assertContains('toll_mismatch', array_column($millas->review_notes ?? [], 'code'));
        $this->assertSame(['bea-tt', 'wikidata', 'wikipedia'], $millas->sources()->orderBy('provider')->pluck('provider')->all());
        $this->assertSame('Accident de Millas', $millas->name['fr']);
        $this->assertSame('Millas train-bus collision', $millas->name['en']);
        // Pas de libellé allemand dans Wikidata : nom construit (nature et commune).
        $this->assertSame('Zusammenstoß an einem Bahnübergang in Millas', $millas->name['de']);
        $this->assertStringStartsWith('Le 14 décembre 2017, une collision survient à un passage à niveau à Millas (Pyrénées-Orientales).', $millas->summary['fr']);
        $this->assertSame('exact', $millas->location_precision?->value);
        $this->assertSame('66', $millas->department_code);

        $link = $millas->lineLinks()->firstOrFail();
        $this->assertTrue($link->is_primary);
        $this->assertSame($this->line->id, $link->line_id);
        $this->assertSame('high', $link->confidence->value);
        $this->assertSame('proximity+wikipedia', $link->method);
        $this->assertSame(2010, $link->era_year);
        $this->assertSame('millas', $link->sectionFrom?->slug);
        $this->assertSame('st-feliu-davall', $link->sectionTo?->slug);
        $this->assertEqualsWithDelta(480500, (int) $link->pk_m, 400);
        $this->assertSame('millas', $millas->nearestStation?->slug);

        $freight = Accident::query()->whereNull('wikidata_id')->firstOrFail();
        $this->assertSame('2026-01-11-millas', $freight->slug);
        $this->assertSame('derailment', $freight->kind->value);
        $this->assertSame('Déraillement à Millas', $freight->name['fr']);
        $this->assertSame('Derailment at Millas', $freight->name['en']);
        $this->assertSame('commune', $freight->location_precision?->value);
        $this->assertContains('location_commune_only', array_column($freight->review_notes ?? [], 'code'));
        $this->assertSame('low', $freight->lineLinks()->firstOrFail()->confidence->value);
        $this->assertNull($freight->lineLinks()->firstOrFail()->section_from_station_id, 'pas de portion à la commune près');
        $this->assertEquals([['label' => null, 'number' => null, 'category' => 'freight', 'material' => null, 'operator' => null, 'source' => 'bea-tt']], $freight->trains);

        Http::assertSent(fn (Request $r) => str_starts_with($r->header('User-Agent')[0] ?? '', 'VFF-Bot/1.0'));
        Http::assertNotSent(fn (Request $r) => ! str_starts_with($r->header('User-Agent')[0] ?? '', 'VFF-Bot/1.0'));
        $this->assertFileExists(storage_path('app/data/accidents/.nobackup'));
    }

    public function test_the_import_never_confirms_a_fiche_only_the_verification_does(): void
    {
        // Bilans concordants : aucun point à vérifier à l'import, la fiche serait passée « ok » autrefois.
        $this->articleInjuries = 14;

        $this->artisan('vff:import-accidents')->assertSuccessful();

        $millas = Accident::query()->where('wikidata_id', 'Q45757847')->firstOrFail();
        $this->assertSame([], array_intersect(array_column($millas->review_notes ?? [], 'code'), AccidentImporter::REVIEW_CODES));
        $this->assertSame('to_review', $millas->review_status->value);
        $this->assertSame(0, Accident::query()->where('review_status', 'ok')->count());

        // Une fiche que la vérification a confirmée le reste quand l'import est rejoué ; une fiche « ok »
        // sans règle de vérification repasse « à vérifier ».
        $millas->forceFill(['review_status' => 'ok', 'verification' => ['rule' => 'primary_source']])->save();
        $freight = Accident::query()->whereNull('wikidata_id')->firstOrFail();
        $freight->forceFill(['review_status' => 'ok'])->save();
        $this->artisan('vff:import-accidents', ['--offline' => true])->assertSuccessful();
        $this->assertSame('ok', $millas->refresh()->review_status->value);
        $this->assertSame('to_review', $freight->refresh()->review_status->value);
    }

    public function test_second_run_offline_changes_nothing_and_sends_nothing(): void
    {
        $this->artisan('vff:import-accidents')->assertSuccessful();
        $before = $this->snapshot(self::ACCIDENT_TABLES, ['id', 'accident_id', 'imported_at', 'retrieved_at']);
        $slugs = Accident::query()->orderBy('slug')->pluck('slug')->all();
        $sent = count(Http::recorded());

        $this->artisan('vff:import-accidents', ['--offline' => true])->assertSuccessful();

        $this->assertSame($before, $this->snapshot(self::ACCIDENT_TABLES, ['id', 'accident_id', 'imported_at', 'retrieved_at']));
        $this->assertSame($slugs, Accident::query()->orderBy('slug')->pluck('slug')->all());
        $this->assertCount($sent, Http::recorded(), 'hors ligne : aucune requête');
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->artisan('vff:import-accidents', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame(0, Accident::count());
        $this->assertSame(0, DB::table('accident_sources')->count());
    }

    public function test_only_bea_tt_and_unknown_source(): void
    {
        $this->artisan('vff:import-accidents', ['--only' => ['bea-tt']])->assertSuccessful();
        $this->assertSame(2, Accident::count(), 'deux rangées gardées : la belge et la routière sont écartées');
        $this->assertSame(0, Accident::whereNotNull('wikidata_id')->count());

        $this->artisan('vff:import-accidents', ['--only' => ['twitter']])->assertExitCode(2);
    }

    public function test_stale_record_is_kept_unless_pruned_after_a_full_import(): void
    {
        Accident::factory()->create(['slug' => '1971-07-21-niffer', 'imported_at' => now()->subDay()]);

        $this->artisan('vff:import-accidents')->assertSuccessful();
        $this->assertTrue(Accident::where('slug', '1971-07-21-niffer')->exists(), 'signalée, jamais supprimée sans --prune');

        $this->artisan('vff:import-accidents', ['--only' => ['bea-tt'], '--prune' => true])->assertSuccessful();
        $this->assertTrue(Accident::where('slug', '1971-07-21-niffer')->exists(), 'import partiel : rien n\'est retiré');

        $this->artisan('vff:import-accidents', ['--prune' => true])->assertSuccessful();
        $this->assertFalse(Accident::where('slug', '1971-07-21-niffer')->exists());
        $this->assertSame(2, Accident::count());
    }

    public function test_report_file_lists_doubtful_cases(): void
    {
        $path = storage_path('app/data/accidents/bilan.json');
        $this->artisan('vff:import-accidents', ['--report' => $path])->assertSuccessful();

        $report = json_decode((string) File::get($path), true);
        $this->assertSame(['wikidata' => 1, 'bea-tt' => 2, 'wikipedia-list' => 0, 'wikipedia-editions' => 0], $report['collected']);
        $this->assertSame(1, $report['merged']);
        $this->assertArrayHasKey('2026-01-11-millas', $report['toReview']);
        $this->assertSame(['outside_france' => 1, 'other_mode' => 1], $report['rejected']['bea-tt']);
    }

    public function test_wikipedia_lists_complete_the_other_sources_with_facts_only(): void
    {
        $this->withList = true;

        $this->artisan('vff:import-accidents')->assertSuccessful();

        // Les deux fiches des autres sources, plus la puce de 2014 : le métro, le heurt d'un piéton et la puce sans lieu sont écartés.
        $this->assertSame(3, Accident::count());

        // La puce de 2017 décrit l'accident de Millas déjà connu : une source de plus, rien d'autre ne change.
        $millas = Accident::query()->where('wikidata_id', 'Q45757847')->firstOrFail();
        $this->assertSame(['bea-tt', 'wikidata', 'wikipedia', 'wikipedia'], $millas->sources()->orderBy('provider')->orderBy('role')->pluck('provider')->all());
        $list = $millas->sources()->where('role', 'list')->firstOrFail();
        $this->assertSame("Liste d'accidents ferroviaires en France dans les années 2010", $list->title);
        $this->assertSame('https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_2010&oldid=240000001#2017', $list->url);
        $this->assertSame(240000001, $list->revision);
        $this->assertSame('CC BY-SA 4.0', $list->licence);
        $this->assertEqualsCanonicalizing(['date' => '2017-12-14', 'deaths' => 6, 'injuries' => 20, 'place' => 'Millas', 'department' => '66'], $list->facts);
        $this->assertSame(6, $millas->deaths);
        $this->assertSame('bea-tt', $millas->toll_source, 'le bilan de l\'enquête reste retenu');
        $this->assertSame(14, $millas->injuries);

        // La puce de 2014 est une nouvelle fiche : commune seule, jamais sûre, ses faits et sa source seulement.
        $new = Accident::query()->whereDate('occurred_on', '2014-02-03')->firstOrFail();
        $this->assertSame('2014-02-03-millas', $new->slug);
        $this->assertSame('derailment', $new->kind->value);
        $this->assertSame(3, $new->deaths);
        $this->assertSame('wikipedia', $new->toll_source);
        $this->assertSame('commune', $new->location_precision?->value);
        $this->assertSame('to_review', $new->review_status->value);
        $this->assertEqualsCanonicalizing(['location_commune_only', 'place_from_list'], array_column($new->review_notes ?? [], 'code'));
        $this->assertSame('Déraillement à Millas', $new->name['fr']);
        $source = $new->sources()->firstOrFail();
        $this->assertSame(['wikipedia', 'list'], [$source->provider, $source->role]);
        $this->assertStringNotContainsString('marchandises', (string) json_encode([$new->summary, $new->name, $source->facts]), 'aucun texte de la liste n\'est repris');

        // Ni métro, ni heurt de personne, ni puce sans lieu.
        $this->assertSame(0, Accident::query()->withoutGlobalScopes()->whereIn('occurred_on', ['2000-03-05', '2001-03-06', '2002-03-07'])->count());
    }

    public function test_second_run_with_lists_is_idempotent_and_adds_no_duplicate(): void
    {
        $this->withList = true;
        $this->artisan('vff:import-accidents')->assertSuccessful();
        $before = $this->snapshot(self::ACCIDENT_TABLES, ['id', 'accident_id', 'imported_at', 'retrieved_at']);

        $this->artisan('vff:import-accidents', ['--offline' => true])->assertSuccessful();
        $this->assertSame($before, $this->snapshot(self::ACCIDENT_TABLES, ['id', 'accident_id', 'imported_at', 'retrieved_at']));

        // Listes seules sur une base déjà remplie : les puces retrouvent leurs fiches, sans doublon.
        $this->artisan('vff:import-accidents', ['--only' => ['wikipedia-list'], '--offline' => true])->assertSuccessful();
        $this->assertEquals($before, $this->snapshot(self::ACCIDENT_TABLES, ['id', 'accident_id', 'imported_at', 'retrieved_at']));
    }

    public function test_lists_alone_attach_to_a_known_accident_without_changing_it(): void
    {
        $this->withList = true;
        $this->artisan('vff:import-accidents', ['--only' => ['bea-tt', 'wikidata']])->assertSuccessful();
        $millas = Accident::query()->where('wikidata_id', 'Q45757847')->firstOrFail();
        $attributes = $millas->only(['slug', 'deaths', 'injuries', 'review_status', 'commune_name']);

        $this->artisan('vff:import-accidents', ['--only' => ['wikipedia-list']])->assertSuccessful();

        $this->assertSame(3, Accident::count(), 'les deux fiches, la puce de 2014 et rien de plus');
        $millas->refresh();
        $this->assertSame($attributes, $millas->only(['slug', 'deaths', 'injuries', 'review_status', 'commune_name']));
        $this->assertSame(1, $millas->sources()->where('role', 'list')->count());
    }

    public function test_a_bullet_without_a_place_never_attaches_to_an_accident_of_the_same_day(): void
    {
        $this->withList = true;
        $this->artisan('vff:import-accidents', ['--only' => ['bea-tt', 'wikidata']])->assertSuccessful();
        $millas = Accident::query()->where('wikidata_id', 'Q45757847')->firstOrFail();
        $this->decadeText = "== 2017 ==\n* {{Date-|14|12|2017}} - un train déraille sur une ligne de montagne, quatre morts.\n";

        $this->artisan('vff:import-accidents', ['--only' => ['wikipedia-list']])->assertSuccessful();

        $this->assertSame(0, $millas->sources()->where('role', 'list')->count(), 'aucune source d\'un autre accident');
        $this->assertSame(6, $millas->refresh()->deaths);
        $this->assertSame(2, Accident::count());
    }

    public function test_a_bullet_alone_never_hides_a_record_of_another_source(): void
    {
        $target = new AccidentCandidate('wikidata:Q1');
        $target->kind = AccidentKind::Other;
        $target->kindSource = 'label';
        $listed = new AccidentCandidate('wikipedia-list:fr:1950-05-01-meudon-0');
        $listed->kind = AccidentKind::PersonStruck;
        $listed->kindSource = 'wikipedia';

        (new ReflectionMethod(AccidentImporter::class, 'absorbListed'))->invoke(app(AccidentImporter::class), $target, $listed);

        $this->assertSame(AccidentKind::Other, $target->kind, 'la nature de la cible est gardée');
        $this->assertTrue($target->hasNote('list_kind_hidden'), 'la fiche est signalée pour relecture');
    }

    public function test_list_bullets_keep_their_record_when_wikipedia_reorders_or_corrects_them(): void
    {
        $this->withList = true;
        DB::statement("INSERT INTO communes (code_insee, type, nom, nom_norm, departement, geom, created_at, updated_at)
            VALUES ('66200', 'commune-actuelle', 'Saint-Féliu-d''Avall', 'saint feliu d avall', '66', ST_SetSRID(ST_MakePoint(2.80, 42.69), 4326), now(), now())");
        DB::table('commune_contours')->insert(['code_insee' => '66200', 'nom' => 'Saint-Féliu-d\'Avall', 'departement' => '66',
            'geom' => DB::raw('ST_Multi(ST_MakeEnvelope(2.76, 42.66, 2.86, 42.72, 4326))'), 'created_at' => now(), 'updated_at' => now()]);
        $freight = "* {{Date-|3|2|2014}} - [[Millas]] : un train de marchandises déraille, trois morts.\n";
        $passengers = "* {{Date-|3|2|2014}} - [[Millas]] : un train de voyageurs déraille, un mort.\n";
        $this->decadeText = "== 2014 ==\n".$freight.$passengers;
        $this->artisan('vff:import-accidents')->assertSuccessful();
        $deathsBySlug = fn (): array => Accident::query()->whereDate('occurred_on', '2014-02-03')->orderBy('slug')->pluck('deaths', 'slug')->all();
        $before = $deathsBySlug();
        $this->assertCount(2, $before);

        // Une puce insérée au-dessus de l'autre : chaque fiche garde son accident.
        $this->decadeText = "== 2014 ==\n".$passengers.$freight;
        $this->artisan('vff:import-accidents', ['--refresh' => true])->assertSuccessful();
        $this->assertSame($before, $deathsBySlug());

        // Le lieu de la première puce est corrigé : la même fiche suit, sans doublon.
        $this->decadeText = "== 2014 ==\n".$passengers.str_replace('[[Millas]]', "[[Saint-Féliu-d'Avall]]", $freight);
        $this->artisan('vff:import-accidents', ['--refresh' => true])->assertSuccessful();
        $this->assertSame($before, $deathsBySlug());
        $slug = array_search(3, $before, true);
        $this->assertSame('66200', Accident::query()->where('slug', $slug)->value('commune_insee'));
        $this->assertSame(1, Accident::query()->where('slug', $slug)->firstOrFail()->sources()->count(), 'l\'ancienne source de la puce est remplacée');
    }

    public function test_a_list_bullet_read_under_a_new_identifier_replaces_its_old_source_on_a_known_accident(): void
    {
        $this->withList = true;
        $this->artisan('vff:import-accidents')->assertSuccessful();
        $millas = Accident::query()->where('wikidata_id', 'Q45757847')->firstOrFail();
        // Ancien identifiant de la puce (avant le changement du schéma d'identifiants), même lien.
        $millas->sources()->where('role', 'list')->update(['external_id' => 'fr:2017-12-14-millas-0']);

        $this->artisan('vff:import-accidents', ['--only' => ['wikipedia-list'], '--offline' => true])->assertSuccessful();

        $this->assertSame(1, $millas->sources()->where('role', 'list')->count(), 'une seule ligne par lien et par accident');
        $this->assertNotSame('fr:2017-12-14-millas-0', $millas->sources()->where('role', 'list')->value('external_id'));
    }

    public function test_english_and_german_lists_add_a_second_source_to_existing_records_only(): void
    {
        $this->editionPages['en'] = ['List of rail accidents (2010–2019)' => "== 2014 ==\n* February 3 – ''France'' – At [[Millas]], a goods train derailed.\n== 2017 ==\n"
            ."* December 14 – ''France'' – At [[Millas]], a TER collided with a school bus on a level crossing.{{sfn|Semmens|1994|p=12}}\n"
            ."* December 14 – ''France'' – Between [[Millas]] and [[Saint-Féliu-d'Avall]], another accident.\n"];
        $this->editionPages['de'] = ['Liste schwerer Unfälle im Schienenverkehr' => "== 2010er ==\n* [[Millas]] ({{FRA-1999}}) – Zusammenstoß: ''14. Dezember 2017'' – Ein Zug stieß mit einem Bus zusammen.<ref>Quelle</ref>\n"];

        $this->artisan('vff:import-accidents')->assertSuccessful();

        // Aucune fiche de plus : l'entrée de 2014 n'a pas de fiche, celle de 2017 en a une.
        $this->assertSame(2, Accident::count());
        $millas = Accident::query()->where('wikidata_id', 'Q45757847')->firstOrFail();
        $en = $millas->sources()->where('provider', 'wikipedia-en')->firstOrFail();
        $this->assertSame('list', $en->role);
        $this->assertSame('CC BY-SA 4.0', $en->licence);
        $this->assertSame('List of rail accidents (2010–2019)', $en->title);
        $this->assertStringStartsWith('https://en.wikipedia.org/w/index.php?title=List_of_rail_accidents_%282010%E2%80%932019%29&oldid=', $en->url);
        $this->assertStringEndsWith('#2017', $en->url);
        $this->assertEqualsCanonicalizing(['date' => '2017-12-14', 'commune_insee' => '66108', 'place' => 'Millas', 'referenced' => true, 'references' => ['Semmens 1994']], $en->facts);
        $de = $millas->sources()->where('provider', 'wikipedia-de')->firstOrFail();
        $this->assertTrue($de->facts['referenced']);
        $this->assertSame(2, $millas->sources()->whereIn('provider', ['wikipedia-en', 'wikipedia-de'])->count(), 'l\'entrée « between » ne rapproche rien');
        $this->assertSame('to_review', $millas->review_status->value, 'l\'import ne confirme rien : seule la vérification le fait');

        // Rejoué hors ligne : mêmes lignes ; entrée retirée de la page : source retirée à l'import suivant.
        $before = $this->snapshot(self::ACCIDENT_TABLES, ['id', 'accident_id', 'imported_at', 'retrieved_at']);
        $this->artisan('vff:import-accidents', ['--offline' => true])->assertSuccessful();
        $this->assertSame($before, $this->snapshot(self::ACCIDENT_TABLES, ['id', 'accident_id', 'imported_at', 'retrieved_at']));
        // Une page absente ne prouve rien (rien n'est retiré) ; toutes les pages lues sans l'entrée : source retirée.
        $this->editionPages = ['en' => [], 'de' => []];
        $this->artisan('vff:import-accidents', ['--only' => ['wikipedia-editions'], '--refresh' => true])->assertSuccessful();
        $this->assertSame(2, DB::table('accident_sources')->whereIn('provider', ['wikipedia-en', 'wikipedia-de'])->count());
        foreach ([...WikipediaEditionCollector::EN_DECADES, WikipediaEditionCollector::EN_FRANCE] as $title) {
            $this->editionPages['en'][$title] = "== 2017 ==\n";
        }
        $this->editionPages['de'][WikipediaEditionCollector::DE_LIST] = "== 2010er ==\n";
        $this->artisan('vff:import-accidents', ['--only' => ['wikipedia-editions'], '--refresh' => true])->assertSuccessful();
        $this->assertSame(0, DB::table('accident_sources')->whereIn('provider', ['wikipedia-en', 'wikipedia-de'])->count());
    }

    public function test_the_edition_lists_respect_dry_run(): void
    {
        $this->artisan('vff:import-accidents')->assertSuccessful();
        $this->editionPages['en'] = ['List of rail accidents (2010–2019)' => "== 2017 ==\n* December 14 – ''France'' – At [[Millas]], a TER collided with a school bus.{{sfn|Semmens|1994|p=12}}\n"];

        $this->artisan('vff:import-accidents', ['--only' => ['wikipedia-editions'], '--dry-run' => true])->assertSuccessful();
        $this->assertSame(0, $this->englishSources());

        $this->artisan('vff:import-accidents', ['--only' => ['wikipedia-editions']])->assertSuccessful();
        $this->assertSame(1, $this->englishSources());
        // Un import complet suivant (sans les listes) ne les efface pas.
        $this->artisan('vff:import-accidents', ['--only' => ['wikidata', 'bea-tt'], '--offline' => true])->assertSuccessful();
        $this->assertSame(1, $this->englishSources());
    }

    public function test_the_place_table_recovers_a_bullet_whose_place_has_no_link(): void
    {
        $this->withList = true;
        DB::statement("INSERT INTO communes (code_insee, type, nom, nom_norm, departement, geom, created_at, updated_at)
            VALUES ('60175', 'commune-actuelle', 'Creil', 'creil', '60', ST_SetSRID(ST_MakePoint(2.48, 49.26), 4326), now(), now())");
        DB::table('commune_contours')->insert(['code_insee' => '60175', 'nom' => 'Creil', 'departement' => '60',
            'geom' => DB::raw('ST_Multi(ST_MakeEnvelope(2.43, 49.23, 2.53, 49.29, 4326))'), 'created_at' => now(), 'updated_at' => now()]);
        $this->decadeText = "== 1869 ==\n* {{date|3|juillet|1869}} - En gare de Creil, une locomotive au repos, heurtée lors d'une manœuvre, part seule sur une voie et tamponne des wagons.<ref>Presse.</ref>.\n"
            ."* {{date|4|juillet|1869}} - En gare de Creil, un autre accident sans ligne dans la table.\n";

        $this->artisan('vff:import-accidents', ['--only' => ['wikipedia-list']])->assertSuccessful();

        $mapped = Accident::query()->whereDate('occurred_on', '1869-07-03')->firstOrFail();
        $this->assertSame('60175', $mapped->commune_insee);
        $this->assertContains('place_from_table', array_column($mapped->review_notes ?? [], 'code'));
        $this->assertSame('to_review', $mapped->review_status->value);
        $this->assertSame(0, Accident::query()->whereDate('occurred_on', '1869-07-04')->count(), 'sans ligne dans la table, la puce reste écartée (aucun lieu)');
    }

    /** @phpstan-impure */
    private function englishSources(): int
    {
        return DB::table('accident_sources')->where('provider', 'wikipedia-en')->count();
    }

    /** @return array<string, array{title: string, revid: int, content: string}> */
    private function listPages(): array
    {
        $home = "La '''liste''' est constituée de plusieurs pages :\n"
            ."** [[Liste d'accidents ferroviaires en France dans les années 2010|Années 2010]]\n";
        $decade = "== 2014 ==\n"
            ."* {{Date-|3|2|2014}} - [[Millas]] : un train de marchandises déraille, trois morts.\n"
            ."* {{Date-|5|3|2000}} - [[Millas]] : incendie dans le métro.\n"
            ."* {{Date-|6|3|2001}} - [[Millas]] : heurt d'un piéton par un train, un mort.\n"
            ."* {{Date-|7|3|2002}} - un train déraille sur une ligne de montagne.\n"
            ."== 2017 ==\n"
            ."* {{Date-|14|12|2017}} - [[Millas]] ([[Pyrénées-Orientales]]) : collision entre un autocar et un TER à un passage à niveau, six morts et vingt blessés.\n";

        $decade = $this->decadeText ?? $decade;

        return [
            "Liste d'accidents ferroviaires en France" => ['title' => "Liste d'accidents ferroviaires en France", 'revid' => 240000000, 'content' => $home],
            "Liste d'accidents ferroviaires en France dans les années 2010" => ['title' => "Liste d'accidents ferroviaires en France dans les années 2010", 'revid' => 240000001, 'content' => $decade],
        ];
    }

    private function fakeSources(): void
    {
        $entities = [
            'Q45757847' => [
                'id' => 'Q45757847', 'lastrevid' => 2520152318,
                'labels' => ['fr' => ['value' => 'accident de Millas'], 'en' => ['value' => 'Millas train-bus collision']],
                'claims' => [
                    'P31' => [$this->item('Q19710423')],
                    'P17' => [$this->item('Q142')],
                    'P585' => [['rank' => 'normal', 'mainsnak' => ['datavalue' => ['value' => ['time' => '+2017-12-14T00:00:00Z', 'precision' => 11]]]]],
                    'P625' => [['rank' => 'normal', 'mainsnak' => ['datavalue' => ['value' => ['latitude' => 42.6852, 'longitude' => 2.7038]]]]],
                    'P1120' => [['rank' => 'normal', 'mainsnak' => ['datavalue' => ['value' => ['amount' => '+6', 'unit' => '1']]]]],
                    'P1339' => [['rank' => 'normal', 'mainsnak' => ['datavalue' => ['value' => ['amount' => '+14', 'unit' => '1']]]]],
                ],
                'sitelinks' => ['frwiki' => ['title' => 'Accident de Millas'], 'enwiki' => ['title' => 'Millas train-bus collision']],
            ],
        ];
        $lead = fn (): string => "{{Infobox Accident de transport\n | date = {{date|14|décembre|2017}}\n | morts = 6\n | blessés = {$this->articleInjuries}\n | appareil = TER\n}}\n"
            ."L'accident a eu lieu sur la [[Ligne de Perpignan à Villefranche-Vernet-les-Bains|ligne de Cerdagne]].";

        Http::fake(function (Request $request) use ($entities, $lead) {
            $url = $request->url();
            if (str_starts_with($url, 'https://query.wikidata.org/sparql')) {
                return Http::response(['results' => ['bindings' => [
                    ['i' => ['value' => 'http://www.wikidata.org/entity/Q45757847'], 'c' => ['value' => 'http://www.wikidata.org/entity/Q142'], 'co' => ['value' => 'Point(2.7038 42.6852)']],
                    // Accident allemand : hors de l'emprise, jamais demandé.
                    ['i' => ['value' => 'http://www.wikidata.org/entity/Q999'], 'c' => ['value' => 'http://www.wikidata.org/entity/Q183'], 'co' => ['value' => 'Point(13.4 52.5)']],
                ]]]);
            }
            if (str_starts_with($url, 'https://www.wikidata.org/w/api.php')) {
                $ids = explode('|', (string) $request['ids']);

                return Http::response(['entities' => array_intersect_key($entities, array_flip($ids))
                    + array_fill_keys(array_diff($ids, array_keys($entities)), ['missing' => '']), ]);
            }
            foreach (['en', 'de'] as $lang) {
                if (str_starts_with($url, "https://{$lang}.wikipedia.org/w/api.php")) {
                    $pages = [];
                    foreach (explode('|', (string) ($request['titles'] ?? '')) as $title) {
                        if (isset($this->editionPages[$lang][$title])) {
                            $pages[] = ['title' => $title, 'revisions' => [['revid' => 777000 + strlen($title), 'slots' => ['main' => ['content' => $this->editionPages[$lang][$title]]]]]];
                        }
                    }

                    return Http::response(['query' => ['pages' => $pages]]);
                }
            }
            if (str_starts_with($url, 'https://fr.wikipedia.org/w/api.php') && str_starts_with((string) ($request['titles'] ?? ''), "Liste d'accidents")) {
                $pages = [];
                foreach ($this->withList ? $this->listPages() : [] as $title => $page) {
                    if (in_array($title, explode('|', (string) $request['titles']), true)) {
                        $pages[] = ['title' => $title, 'revisions' => [['revid' => $page['revid'], 'slots' => ['main' => ['content' => $page['content']]]]]];
                    }
                }

                return Http::response(['query' => ['pages' => $pages]]);
            }
            if (str_starts_with($url, 'https://fr.wikipedia.org/w/api.php')) {
                if (($request['generator'] ?? null) === 'categorymembers') {
                    $pages = $request['gcmtitle'] === 'Catégorie:Accident ferroviaire en France'
                        ? [['title' => 'Accident de Millas', 'pageprops' => ['wikibase_item' => 'Q45757847']]] : [];

                    return Http::response(['query' => ['pages' => $pages]]);
                }

                return Http::response(['query' => ['pages' => [[
                    'title' => 'Accident de Millas',
                    'revisions' => [['revid' => 241196701, 'slots' => ['main' => ['content' => $lead()]]]],
                ]]]]);
            }
            if (str_ends_with($url, 'historique-des-enquetes-a1187.html')) {
                return Http::response('<a href="IMG/pdf/20261109_bilan-general-enquetes-beatt.pdf">Bilan général</a>');
            }
            if (str_ends_with($url, '.pdf')) {
                return Http::response('%PDF-1.7 faux');
            }

            return Http::response('', 404);
        });
    }

    /** Conversion pdftohtml du bilan, déjà en cache (quatre rangées : PN de Millas, fret, Belgique, route). */
    private function cacheBeaTtConversion(): void
    {
        $row = fn (int $top, string $text, string $mode, int $deaths, string $href) => <<<XML
            <text top="{$top}" left="35" width="438" height="15" font="3">{$text}</text>
            <text top="{$top}" left="759" width="34" height="15" font="5"><a href="{$href}">rapport</a></text>
            <text top="{$top}" left="863" width="7" height="15" font="3">{$mode}</text>
            <text top="{$top}" left="918" width="6" height="15" font="3">{$deaths}</text>
            XML;
        $xml = '<?xml version="1.0" encoding="UTF-8"?><pdf2xml><page number="1">'
            .$row(164, '14/12/2017 Collision entre un TER et un autocar scolaire sur le PN 25 à Millas (66)', 'PN', 6, 'https://www.bea-tt.developpement-durable.gouv.fr/millas-66-14-12-2017-collision-r264.html')
            .$row(190, '11/01/2026 Déraillement d’un train de fret à Millas (66)', 'F', 0, 'https://www.bea-tt.developpement-durable.gouv.fr/IMG/pdf/fret.pdf')
            .$row(210, '10/07/2023 Décès par électrocution d’un élagueur à Quévy (Belgique)', 'F', 1, 'https://www.bea-tt.developpement-durable.gouv.fr/quevy-belgique-r448.html')
            .$row(230, '24/02/2016 Collision entre un PL et un VUL sur la RN 79 à Montbeugny (03)', 'R', 12, 'https://www.bea-tt.developpement-durable.gouv.fr/montbeugny.html')
            .'</page></pdf2xml>';
        $path = storage_path('app/data/accidents/bea-tt/20261109_bilan-general-enquetes-beatt.xml');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $xml);
        touch($path, time() - 3600);
    }

    /** @return array<string, mixed> */
    private function item(string $id): array
    {
        return ['rank' => 'normal', 'mainsnak' => ['datavalue' => ['value' => ['id' => $id]]]];
    }
}
