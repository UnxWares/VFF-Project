<?php

namespace Tests\Feature\Bibliography;

use App\Models\BibliographyEntry;
use App\Models\BibliographyLink;
use App\Models\Era;
use App\Models\Line;
use App\Models\Station;
use App\Services\Bibliography\BibliographyImporter;
use App\Services\Bibliography\BibliographyRecord;
use App\Services\Bibliography\SheetBibliography;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Bibliographie d'une ligne et d'une gare : API REST, section « Pour aller plus loin » de la fiche, enregistrement sans
 * doublon (même ISBN = une référence), remplacement des liens d'une source, et fiches qui répondent sans les tables.
 */
class BibliographyTest extends TestCase
{
    use RefreshDatabase;

    private Line $line;

    private Station $station;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Era::factory()->current()->create(['year' => 2030]);
        $this->line = Line::factory()->create(['slug' => 'rfn-722000', 'code' => '722000', 'name' => 'Ligne de Béziers à Neussargues']);
        Line::factory()->create(['slug' => 'rfn-1000', 'code' => '001000', 'name' => 'Ligne sans bibliographie']);
        $this->station = Station::factory()->create(['slug' => 'limoges-benedictins', 'name' => 'Limoges-Bénédictins']);
    }

    private function record(string $title, ?int $year, string $provider = 'bnf', ?string $isbn = null, array $authors = ['Jean Dupont']): BibliographyRecord
    {
        return new BibliographyRecord('book', $title, $authors, 'Les éditions du Cabri', 'Breil-sur-Roya', $year, $isbn, null, $provider,
            $provider === 'bnf' ? 'https://catalogue.bnf.fr/ark:/12148/cb1' : 'https://fr.wikipedia.org/w/index.php?title=X&oldid=1',
            $provider === 'bnf' ? 'ark:/12148/cb1' : null);
    }

    /** @return array<string, mixed> */
    private function candidate(string $subject, int $id, BibliographyRecord $record, string $basis = 'title'): array
    {
        return ['subject' => $subject, 'id' => $id, 'record' => $record, 'provider' => $record->provider, 'basis' => $basis, 'score' => $basis === 'title' ? 10 : null,
            'reasons' => [['code' => 'rail_route', 'points' => 4]], 'origin_url' => $record->url, 'fetched_at' => null];
    }

    private function seedReferences(): void
    {
        (new BibliographyImporter)->store([
            $this->candidate('line', $this->line->id, $this->record("L'épopée de la ligne des Causses", 2025, 'bnf', '9782914603928')),
            $this->candidate('line', $this->line->id, $this->record('Le train des Causses', 1988, 'wikipedia'), 'article_bibliography'),
            $this->candidate('line', $this->line->id, $this->record('Étude sans date', null, 'wikipedia'), 'article_reference'),
            $this->candidate('station', $this->station->id, $this->record('La gare de Limoges-Bénédictins', 1999, 'wikipedia'), 'article_bibliography'),
        ], [
            'bnf' => ['lines' => [$this->line->id], 'stations' => []],
            'wikipedia' => ['lines' => [$this->line->id], 'stations' => [$this->station->id]],
        ]);
    }

    public function test_the_rest_api_lists_the_references_with_their_links_and_the_reason(): void
    {
        $this->seedReferences();

        $res = $this->getJson('/api/v1/lines/rfn-722000/bibliography')->assertOk()->assertHeader('Cache-Control');

        $this->assertSame(3, $res->json('total'));
        // Les plus récentes d'abord, sans date en dernier.
        $this->assertSame([2025, 1988, null], array_column($res->json('items'), 'year'));
        $first = $res->json('items.0');
        $this->assertSame('book', $first['kind']);
        $this->assertSame(['Jean Dupont'], $first['authors']);
        $this->assertSame('Les éditions du Cabri', $first['publisher']);
        $this->assertSame('9782914603928', $first['isbn']);
        $this->assertSame('https://catalogue.bnf.fr/ark:/12148/cb1', $first['url']);
        $this->assertSame('bnf', $first['source']);
        $this->assertSame('title', $first['match']['basis']);
        $this->assertSame(10, $first['match']['score']);
        $this->assertSame('gallica', $first['provenance']['provider']);
        $this->assertSame('wikipedia', $res->json('items.1.provenance.provider'));
        $this->assertSame(SheetBibliography::ATTRIBUTION, $res->json('attribution'));
        $this->assertSame(['slug' => 'rfn-722000', 'code' => '722000', 'name' => 'Ligne de Béziers à Neussargues'], $res->json('line'));
    }

    public function test_a_line_and_a_station_without_reference_answer_with_an_empty_list(): void
    {
        $this->seedReferences();

        $this->getJson('/api/v1/lines/rfn-1000/bibliography')->assertOk()->assertJson(['total' => 0, 'items' => []]);
        $this->getJson('/api/v1/lines/rfn-999999/bibliography')->assertNotFound();
        $this->getJson('/api/v1/stations/inconnue/bibliography')->assertNotFound();
        $station = $this->getJson('/api/v1/stations/limoges-benedictins/bibliography')->assertOk();
        $this->assertSame(1, $station->json('total'));
        $this->assertSame('article_bibliography', $station->json('items.0.match.basis'));
    }

    public function test_the_sheets_load_the_bibliography_on_demand(): void
    {
        $this->seedReferences();

        // Aucune lecture de plus pour les fiches : la section lit l'API quand la page est affichée.
        $this->get('/lines/rfn-722000')->assertOk()->assertInertia(fn (Assert $page) => $page->missing('bibliography'));
        $this->get('/stations/limoges-benedictins')->assertOk()->assertInertia(fn (Assert $page) => $page->missing('bibliography'));
        $this->getJson('/api/v1/lines/rfn-722000/bibliography')->assertOk()
            ->assertJsonPath('total', 3)->assertJsonPath('items.0.title', "L'épopée de la ligne des Causses");
        $this->getJson('/api/v1/stations/limoges-benedictins/bibliography')->assertOk()->assertJsonPath('total', 1);
    }

    public function test_the_sheets_and_the_api_answer_without_the_tables(): void
    {
        Schema::drop('bibliography_links');
        Schema::drop('bibliography_entries');
        Cache::flush();

        $this->get('/lines/rfn-722000')->assertOk();
        $this->get('/stations/limoges-benedictins')->assertOk();
        $this->getJson('/api/v1/lines/rfn-722000/bibliography')->assertOk()->assertJson(['total' => 0, 'items' => []]);
        $this->getJson('/api/v1/stations/limoges-benedictins/bibliography')->assertOk()->assertJson(['total' => 0]);
    }

    public function test_the_same_isbn_is_one_reference_shared_by_the_line_and_the_station_and_the_notice_of_the_bnf_wins(): void
    {
        $wikipedia = $this->record('Les trains des Causses', 2010, 'wikipedia', '9782914603928', []);
        $bnf = $this->record('Les trains des Causses : histoire', 2010, 'bnf', '9782914603928', ['Marie Martin']);
        $importer = new BibliographyImporter;
        $scope = fn (string $p): array => [$p => ['lines' => [$this->line->id], 'stations' => [$this->station->id]]];

        $importer->store([$this->candidate('line', $this->line->id, $wikipedia, 'article_bibliography'), $this->candidate('station', $this->station->id, $wikipedia, 'article_bibliography')], $scope('wikipedia'));
        $importer->store([$this->candidate('line', $this->line->id, $bnf)], $scope('bnf'));

        $entry = BibliographyEntry::query()->sole();
        $this->assertSame('isbn:9782914603928', $entry->dedup_key);
        $this->assertSame('bnf', $entry->provider);
        $this->assertSame('Les trains des Causses : histoire', $entry->title);
        $this->assertSame(['Marie Martin'], $entry->authors);
        $this->assertSame('https://catalogue.bnf.fr/ark:/12148/cb1', $entry->url);
        // La ligne garde le lien de Wikipédia (déjà posé) ; la gare le sien.
        $this->assertSame(2, BibliographyLink::query()->count());
    }

    public function test_a_rerun_replaces_the_links_of_the_processed_source_and_drops_orphans(): void
    {
        $this->seedReferences();
        $importer = new BibliographyImporter;

        // La BnF ne rattache plus rien à la ligne : son lien et sa référence disparaissent ; Wikipédia reste.
        $importer->store([], ['bnf' => ['lines' => [$this->line->id], 'stations' => []]]);

        $this->assertSame(3, BibliographyLink::query()->count());
        $this->assertSame(['Le train des Causses', 'Étude sans date', 'La gare de Limoges-Bénédictins'], BibliographyEntry::query()->orderBy('id')->pluck('title')->all());
        // Une source non traitée ne bouge pas : relance de Wikipédia seule sur une autre ligne.
        $importer->store([], ['wikipedia' => ['lines' => [(int) Line::query()->where('slug', 'rfn-1000')->value('id')], 'stations' => []]]);
        $this->assertSame(3, BibliographyLink::query()->count());
    }

    public function test_a_link_is_never_attached_to_both_a_line_and_a_station(): void
    {
        $this->seedReferences();
        $this->expectException(QueryException::class);

        BibliographyLink::query()->insert(['entry_id' => BibliographyEntry::query()->value('id'), 'line_id' => $this->line->id, 'station_id' => $this->station->id,
            'provider' => 'bnf', 'basis' => 'title']);
    }

    public function test_the_same_work_cited_with_and_without_isbn_is_one_reference(): void
    {
        $withIsbn = $this->record('La Petite Ceinture de Paris', 2017, 'bnf', '9782847125429');
        $without = $this->record('La petite ceinture de Paris', 2017, 'wikipedia', null, ['P. Menant']);

        (new BibliographyImporter)->store([
            $this->candidate('line', $this->line->id, $without, 'article_bibliography'),
            $this->candidate('station', $this->station->id, $withIsbn),
        ], ['wikipedia' => ['lines' => [$this->line->id], 'stations' => []], 'bnf' => ['lines' => [], 'stations' => [$this->station->id]]]);

        $this->assertSame(1, BibliographyEntry::query()->count());
        $this->assertSame(2, BibliographyLink::query()->count());
    }
}
