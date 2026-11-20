<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * vff:seed-off-network-lines : lignes exploitées hors RFN absentes des sources
 * de tracés (RER de la RATP, trains touristiques…) déclarées avec leurs sources ;
 * leur forme déclarée sert de forme à vff:match-current-network.
 */
class SeedOffNetworkLinesTest extends TestCase
{
    use RefreshDatabase;

    private string $file;

    protected function setUp(): void
    {
        parent::setUp();
        $this->file = tempnam(sys_get_temp_dir(), 'vff-hors-rfn-').'.php';
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        parent::tearDown();
    }

    private function trace(string $source, string $ref, string $layer, array $props, float $lat, float $lon0 = 2.30, float $lon1 = 2.35): int
    {
        return (int) DB::selectOne(<<<'SQL'
            INSERT INTO source_traces (source, source_ref, layer, properties, length_m, seen_at, created_at, updated_at, geom)
            VALUES (?, ?, ?, CAST(? AS jsonb), 3600, NOW(), NOW(), NOW(),
                    ST_Multi(ST_SetSRID(ST_MakeLine(ST_MakePoint(?, ?), ST_MakePoint(?, ?)), 4326)))
            RETURNING id
            SQL, [$source, $ref, $layer, json_encode($props), $lon0, $lat, $lon1, $lat])->id;
    }

    private function declare(array $entries): void
    {
        file_put_contents($this->file, '<?php return '.var_export($entries, true).';');
    }

    private function entry(array $override = []): array
    {
        return array_merge([
            'slug' => 'hors-rfn-test-rer',
            'name' => 'Ligne de Alpha à Bravo (RER A)',
            'network' => 'principal',
            'gauge_mm' => 1435,
            'wikidata' => 'Q1',
            'sources' => ['https://fr.wikipedia.org/w/index.php?title=Test&oldid=1'],
            'trace' => ['bbox' => [2.29, 48.80, 2.36, 48.90], 'osm' => ['layers' => ['rail'], 'operator' => '^RATP', 'name' => '^RER A']],
            'events' => [
                ['type' => 'opening', 'date' => '1977-12-09', 'from' => 'Alpha', 'to' => 'Bravo',
                    'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Test&oldid=1', 'quote' => 'Ouverture le 9 décembre 1977 – entre Alpha et Bravo.'],
                ['type' => 'heritage_start', 'heritage_kind' => 'tourist_train', 'date' => '1990-06-01', 'precision' => 'month',
                    'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Test&oldid=1', 'quote' => 'Train touristique depuis juin 1990 entre Alpha et Bravo.'],
            ],
        ], $override);
    }

    public function test_declared_line_gets_its_form_events_and_then_the_precise_tracks(): void
    {
        $rer = $this->trace('osm', '1', 'rail', ['name' => 'RER A', 'operator' => 'RATP'], 48.85);
        $other = $this->trace('osm', '2', 'rail', ['name' => 'Autre ligne', 'operator' => 'SNCF Réseau'], 48.88);
        $ign = $this->trace('bdtopo', 'b1', 'Voie ferrée principale', ['etat_de_l_objet' => 'En service'], 48.85002);
        $this->declare([$this->entry()]);

        $this->artisan('vff:seed-off-network-lines', ['--file' => $this->file])->assertSuccessful();

        $line = DB::table('lines')->where('slug', 'hors-rfn-test-rer')->first();
        $this->assertNotNull($line);
        $this->assertSame('principal', $line->network);
        $alt = json_decode($line->alt_names, true);
        $this->assertSame('vff:seed-off-network-lines', $alt['created_by']);
        $this->assertSame('Q1', $alt['wikidata_qid']);

        // Forme déclarée : le way RER A seulement, rattachée à la ligne.
        $form = DB::table('source_traces')->where('source', 'declared')->where('source_ref', 'hors-rfn-test-rer')->first();
        $this->assertSame((int) $line->id, (int) $form->line_id);
        $this->assertSame(['osm:1'], json_decode($form->properties, true)['members']);

        // Événements : manual, portion à résoudre ou toute la ligne déclarée ; pas de tiret demi-cadratin.
        $events = DB::table('line_events')->where('line_id', $line->id)->orderBy('event_date')->get();
        $this->assertCount(2, $events);
        $this->assertSame(['manual', 'manual'], $events->pluck('source')->all());
        $this->assertSame('pending', $events[0]->section_resolution);
        $this->assertSame('Alpha', $events[0]->section_from);
        $this->assertStringNotContainsString('–', $events[0]->source_quote);
        $this->assertSame('whole_line', $events[1]->section_resolution);
        $this->assertSame('tourist_train', $events[1]->heritage_kind);
        $this->assertSame('month', $events[1]->date_precision);

        // La forme joue le rôle d'une forme SNCF : la voie IGN qui la suit est rattachée à la ligne.
        $this->artisan('vff:match-current-network', ['--line' => [(string) $line->id]])->assertSuccessful();
        $this->assertSame((int) $line->id, (int) DB::table('source_traces')->where('id', $ign)->value('line_id'));
        $this->assertSame('exploited_overlap', DB::table('source_traces')->where('id', $rer)->value('match_method'));
        $this->assertNull(DB::table('source_traces')->where('id', $other)->value('line_id'));
    }

    public function test_rerun_is_idempotent_and_removed_entries_are_cleaned(): void
    {
        $this->trace('osm', '1', 'rail', ['name' => 'RER A', 'operator' => 'RATP'], 48.85);
        $this->declare([$this->entry()]);
        $this->artisan('vff:seed-off-network-lines', ['--file' => $this->file])->assertSuccessful();
        $this->artisan('vff:seed-off-network-lines', ['--file' => $this->file])->assertSuccessful();
        $this->assertSame(1, DB::table('lines')->where('slug', 'hors-rfn-test-rer')->count());
        $this->assertSame(2, DB::table('line_events')->where('fingerprint', 'like', 'hors-rfn:%')->count());

        // Un événement retiré, puis la ligne entière retirée du fichier.
        $this->declare([$this->entry(['events' => [$this->entry()['events'][0]]])]);
        $this->artisan('vff:seed-off-network-lines', ['--file' => $this->file])->assertSuccessful();
        $this->assertSame(1, DB::table('line_events')->where('fingerprint', 'like', 'hors-rfn:%')->count());

        $this->declare([]);
        $this->artisan('vff:seed-off-network-lines', ['--file' => $this->file])->assertSuccessful();
        $this->assertSame(0, DB::table('lines')->where('slug', 'hors-rfn-test-rer')->count());
        $this->assertSame(0, DB::table('source_traces')->where('source', 'declared')->count());
        $this->assertSame(0, DB::table('line_events')->where('fingerprint', 'like', 'hors-rfn:%')->count());
    }

    public function test_existing_line_receives_events_without_being_modified(): void
    {
        $id = DB::table('lines')->insertGetId(['slug' => 'archeo-ligne-existante', 'name' => 'Ligne existante', 'network' => 'secondaire',
            'created_at' => now(), 'updated_at' => now()]);
        $this->declare([['line' => 'archeo-ligne-existante', 'events' => [$this->entry()['events'][1]]]]);

        $this->artisan('vff:seed-off-network-lines', ['--file' => $this->file])->assertSuccessful();

        $this->assertSame('secondaire', DB::table('lines')->where('id', $id)->value('network'));
        $this->assertSame('heritage_start', DB::table('line_events')->where('line_id', $id)->value('type'));
    }

    public function test_declared_portion_cuts_the_form_to_its_bbox(): void
    {
        // Way de 2,30 à 2,35 °E (≈ 3,7 km) ; portion déclarée de 2,30 à 2,32 (≈ 1,5 km).
        $this->trace('osm', '1', 'rail', ['name' => 'RER A', 'operator' => 'RATP'], 48.85);
        $event = ['type' => 'opening', 'date' => '1980-12-19', 'from' => 'Alpha', 'to' => 'Charlie', 'portion' => [2.29, 48.80, 2.32, 48.90],
            'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Test&oldid=1', 'quote' => 'Prolongement le 19 décembre 1980.'];
        $this->declare([$this->entry(['events' => [$event]])]);

        $this->artisan('vff:seed-off-network-lines', ['--file' => $this->file])->assertSuccessful();

        $e = DB::table('line_events')->where('fingerprint', 'like', 'hors-rfn:%')->first();
        $this->assertSame('trace', $e->section_resolution);
        $this->assertSame((int) DB::table('source_traces')->where('source', 'declared')->value('id'), (int) $e->source_trace_id);
        $this->assertEqualsWithDelta(1466, $e->section_length_m, 30);
    }

    public function test_invalid_declarations_are_refused(): void
    {
        $this->declare([$this->entry(['name' => 'Ligne de Alpha — Bravo'])]);
        $this->artisan('vff:seed-off-network-lines', ['--file' => $this->file])->assertFailed();

        $this->declare([$this->entry(['network' => 'urbain'])]);
        $this->artisan('vff:seed-off-network-lines', ['--file' => $this->file])->assertFailed();

        $bad = $this->entry();
        unset($bad['events'][0]['source_url']);
        $this->declare([$bad]);
        $this->artisan('vff:seed-off-network-lines', ['--file' => $this->file])->assertFailed();
        $this->assertSame(0, DB::table('lines')->count());
    }

    public function test_rfn_line_without_sncf_form_is_declared_with_its_code_slug(): void
    {
        // Ligne du RFN fermée que les formes SNCF ne décrivent plus (ex. 433000 Couterne - La Ferté-Macé).
        $rfn = $this->entry(['slug' => 'rfn-433000', 'code' => '433000', 'name' => 'Ligne de Couterne à La Ferté-Macé']);
        unset($rfn['trace'], $rfn['events']);
        $this->declare([$rfn]);
        $this->artisan('vff:seed-off-network-lines', ['--file' => $this->file])->assertSuccessful();
        $this->assertSame('433000', DB::table('lines')->where('slug', 'rfn-433000')->value('code'));

        // Slug « rfn- » d'un autre code, ou code déjà porté par une autre ligne : refusés.
        $this->declare([array_merge($rfn, ['slug' => 'rfn-433001'])]);
        $this->artisan('vff:seed-off-network-lines', ['--file' => $this->file])->assertFailed();
        $this->declare([array_merge($rfn, ['slug' => 'hors-rfn-couterne'])]);
        $this->artisan('vff:seed-off-network-lines', ['--file' => $this->file])->assertFailed();
    }

    public function test_the_project_declarations_are_valid(): void
    {
        $this->artisan('vff:seed-off-network-lines', ['--dry-run' => true])->assertSuccessful();
    }
}
