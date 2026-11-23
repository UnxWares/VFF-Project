<?php

namespace Tests\Feature\Materials;

use App\Models\Line;
use App\Models\LineEvent;
use App\Models\LineMaterial;
use App\Models\LineMaterialPeriod;
use App\Models\Material;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\ReadsInertiaProps;
use Tests\TestCase;

/**
 * Section « Matériel roulant » de la fiche de ligne : séries citées sans service attesté, séries électriques
 * bornées par la première électrification de la ligne ou d'une section, famille non listée à côté de ses versions.
 */
class LineSheetMaterialsTest extends TestCase
{
    use ReadsInertiaProps;
    use RefreshDatabase;

    private Line $line;

    protected function setUp(): void
    {
        parent::setUp();
        $this->line = Line::factory()->create(['slug' => 'rfn-366000', 'code' => '366000', 'name' => 'Ligne de Mantes-la-Jolie à Cherbourg', 'length_official_m' => 313000]);
    }

    /** @param  list<array<string, mixed>>  $evidence */
    private function link(Material $m, string $confidence, array $evidence): LineMaterial
    {
        return LineMaterial::create(['line_id' => $this->line->id, 'material_id' => $m->id, 'source' => 'wikipedia', 'confidence' => $confidence, 'evidence' => $evidence]);
    }

    /** @return Collection<string, array<string, mixed>> */
    private function sheetMaterials(): Collection
    {
        $rows = new Collection;
        $this->getWithDeferredProps('/lines/rfn-366000')->assertOk()->assertInertia(function (Assert $page) use (&$rows): void {
            $page->where('materials', function ($materials) use (&$rows): bool {
                $rows = $this->rows($materials);

                return true;
            });
        });

        /** @var Collection<string, array<string, mixed>> $bySlug */
        $bySlug = $rows->keyBy('slug');

        return $bySlug;
    }

    /**
     * Entrées d'une réponse d'API rangées par slug de série (`material.slug`).
     *
     * @return Collection<string, array<string, mixed>>
     */
    private static function bySlug(mixed $items): Collection
    {
        /** @var list<array<string, mixed>> $list */
        $list = is_array($items) ? array_values($items) : [];
        /** @var Collection<string, array<string, mixed>> $bySlug */
        $bySlug = (new Collection($list))->keyBy('material.slug');

        return $bySlug;
    }

    public function test_a_series_cited_only_for_a_trial_or_an_order_is_flagged_with_the_reason(): void
    {
        $served = Material::factory()->create(['slug' => 'bb-67400', 'name' => 'BB 67400', 'type' => 'locomotive_diesel']);
        $trial = Material::factory()->create(['slug' => 'bb-26000', 'name' => 'BB 26000', 'type' => 'locomotive_electrique']);
        $order = Material::factory()->create(['slug' => 'z-99', 'name' => 'Z 99', 'type' => 'automotrice']);
        $mixed = Material::factory()->create(['slug' => 'bb-15000', 'name' => 'BB 15000', 'type' => 'locomotive_electrique']);
        $shared = Material::factory()->create(['slug' => 'x-2800', 'name' => 'X 2800', 'type' => 'autorail']);
        $this->link($served, 'high', [['kind' => 'wikipedia_line_article', 'quote' => 'Les BB 67400 assurent les trains.']]);
        $this->link($trial, 'low', [['kind' => 'wikipedia_line_article', 'quote' => 'Des essais ont eu lieu.', 'doubt' => 'trial']]);
        $this->link($order, 'low', [['kind' => 'wikipedia_line_article', 'quote' => 'Commande de rames.', 'doubt' => 'order'], ['kind' => 'wikipedia_line_article', 'quote' => 'Présentation.', 'doubt' => 'one_off']]);
        $this->link($mixed, 'medium', [['kind' => 'wikipedia_line_article', 'quote' => 'Essais.', 'doubt' => 'trial'], ['kind' => 'wikipedia_line_article', 'quote' => 'Elles assurent les trains.']]);
        $this->link($shared, 'low', [['kind' => 'wikipedia_line_article', 'quote' => 'Article de plusieurs lignes.', 'shared_article' => true]]);

        $rows = $this->sheetMaterials();

        $this->assertNull($rows['bb-67400']['link_doubt']);
        $this->assertSame('trial', $rows['bb-26000']['link_doubt']);
        $this->assertSame('one_off', $rows['z-99']['link_doubt'], 'la présentation est plus parlante que la commande');
        $this->assertNull($rows['bb-15000']['link_doubt'], 'une preuve sans doute suffit');
        $this->assertNull($rows['x-2800']['link_doubt'], 'article partagé : pas un doute sur la nature de la circulation');
    }

    public function test_a_series_with_dated_periods_is_never_flagged(): void
    {
        $m = Material::factory()->create(['slug' => 'bb-26000', 'name' => 'BB 26000', 'type' => 'locomotive_electrique']);
        $link = $this->link($m, 'low', [['kind' => 'wikipedia_line_article', 'quote' => 'Essais.', 'doubt' => 'trial']]);
        LineMaterialPeriod::create(['line_material_id' => $link->id, 'start_date' => '2001-01-01', 'start_precision' => 'year', 'confidence' => 'high', 'origin' => 'line_article', 'fingerprint' => md5((string) microtime()), 'source' => 'wikipedia', 'source_url' => 'https://fr.wikipedia.org/w/index.php?oldid=1']);

        $this->assertNull($this->sheetMaterials()['bb-26000']['link_doubt']);
    }

    public function test_a_sourced_period_older_than_the_electrification_keeps_its_date_and_flags_the_conflict(): void
    {
        LineEvent::factory()->create(['line_id' => $this->line->id, 'type' => 'electrification', 'event_date' => '1996-06-01', 'date_precision' => 'month', 'confidence' => 'high', 'section_from' => 'Paris', 'section_to' => 'Cherbourg']);
        LineEvent::factory()->create(['line_id' => $this->line->id, 'type' => 'electrification', 'event_date' => '1989-01-01', 'date_precision' => 'year', 'confidence' => 'low', 'section_from' => 'Bernay', 'section_to' => 'Lisieux', 'section_length_m' => 31295]);
        $electric = Material::factory()->create(['slug' => 'bb-16000', 'name' => 'BB 16000', 'type' => 'locomotive_electrique', 'in_service_from' => 1958, 'in_service_to' => null]);
        $retired = Material::factory()->create(['slug' => 'bb-9200', 'name' => 'BB 9200', 'type' => 'locomotive_electrique', 'in_service_from' => 1957, 'in_service_to' => 1990]);
        $diesel = Material::factory()->create(['slug' => 'bb-67400', 'name' => 'BB 67400', 'type' => 'locomotive_diesel', 'in_service_from' => 1969, 'in_service_to' => null]);
        $dated = Material::factory()->create(['slug' => 'bb-15000', 'name' => 'BB 15000', 'type' => 'locomotive_electrique', 'in_service_from' => 1971, 'in_service_to' => null]);
        foreach ([$electric, $retired, $diesel, $dated] as $m) {
            $this->link($m, 'high', [['kind' => 'wikipedia_line_article', 'quote' => 'Phrase.']]);
        }
        LineMaterialPeriod::create(['line_material_id' => LineMaterial::query()->where('material_id', $dated->id)->value('id'), 'start_date' => '1980-01-01', 'start_precision' => 'year', 'end_date' => '2010-01-01', 'end_precision' => 'year', 'confidence' => 'high', 'origin' => 'line_article', 'fingerprint' => md5((string) microtime()), 'source' => 'wikipedia', 'source_url' => 'https://fr.wikipedia.org/w/index.php?oldid=2']);

        $rows = $this->sheetMaterials();

        $this->assertSame(['reason' => 'electrification', 'date' => '1996-06-01', 'precision' => 'month', 'section' => null, 'conflict' => false], $rows['bb-16000']['earliest_on_line']);
        $this->assertNull($rows['bb-9200']['earliest_on_line'], 'retirée avant l\'électrification');
        $this->assertNull($rows['bb-67400']['earliest_on_line'], 'thermique');
        // Une date sourcée ne se déplace jamais : le début de 1980 reste 1980, et la borne signale le conflit
        // (électrification plus ancienne probablement absente de VFF).
        $this->assertSame('1980-01-01', $rows['bb-15000']['periods'][0]['start_date']);
        $this->assertSame('year', $rows['bb-15000']['periods'][0]['start_precision']);
        $this->assertArrayNotHasKey('start_bounded_by', $rows['bb-15000']['periods'][0]);
        $this->assertSame('2010-01-01', $rows['bb-15000']['periods'][0]['end_date']);
        $this->assertTrue($rows['bb-15000']['earliest_on_line']['conflict']);
    }

    public function test_the_bound_is_the_first_electrification_of_a_section_and_names_it(): void
    {
        LineEvent::factory()->create(['line_id' => $this->line->id, 'type' => 'electrification', 'event_date' => '1996-06-01', 'date_precision' => 'day', 'confidence' => 'high', 'section_from' => 'Paris', 'section_to' => 'Cherbourg']);
        LineEvent::factory()->create(['line_id' => $this->line->id, 'type' => 'electrification', 'event_date' => '1989-01-01', 'date_precision' => 'year', 'confidence' => 'medium', 'section_from' => 'Bernay', 'section_to' => 'Lisieux', 'section_length_m' => 31295]);
        $electric = Material::factory()->create(['slug' => 'bb-16000', 'name' => 'BB 16000', 'type' => 'locomotive_electrique', 'in_service_from' => 1958, 'in_service_to' => null]);
        $dated = Material::factory()->create(['slug' => 'bb-15000', 'name' => 'BB 15000', 'type' => 'locomotive_electrique', 'in_service_from' => 1971, 'in_service_to' => null]);
        foreach ([$electric, $dated] as $m) {
            $this->link($m, 'high', [['kind' => 'wikipedia_line_article', 'quote' => 'Phrase.']]);
        }
        LineMaterialPeriod::create(['line_material_id' => LineMaterial::query()->where('material_id', $dated->id)->value('id'), 'start_date' => '1980-01-01', 'start_precision' => 'year', 'end_date' => '2010-01-01', 'end_precision' => 'year', 'confidence' => 'high', 'origin' => 'line_article', 'fingerprint' => md5((string) microtime()), 'source' => 'wikipedia', 'source_url' => 'https://fr.wikipedia.org/w/index.php?oldid=2']);

        $rows = $this->sheetMaterials();

        $this->assertSame(['reason' => 'electrification', 'date' => '1989-01-01', 'precision' => 'year', 'section' => 'Bernay - Lisieux', 'conflict' => false], $rows['bb-16000']['earliest_on_line']);
        $this->assertSame('1980-01-01', $rows['bb-15000']['periods'][0]['start_date'], 'le début sourcé de 1980 garde sa date');
        $this->assertTrue($rows['bb-15000']['earliest_on_line']['conflict']);
    }

    public function test_a_line_without_a_reliable_electrification_has_no_bound(): void
    {
        LineEvent::factory()->create(['line_id' => $this->line->id, 'type' => 'electrification', 'event_date' => '1989-01-01', 'date_precision' => 'year', 'confidence' => 'low', 'section_length_m' => 31295]);
        $m = Material::factory()->create(['slug' => 'bb-16000', 'name' => 'BB 16000', 'type' => 'locomotive_electrique']);
        $this->link($m, 'high', [['kind' => 'wikipedia_line_article', 'quote' => 'Phrase.']]);

        $this->assertNull($this->sheetMaterials()['bb-16000']['earliest_on_line']);
    }

    public function test_a_family_that_ran_stays_listed_when_its_linked_versions_are_only_doubtful(): void
    {
        $family = Material::factory()->create(['slug' => 'autorail-grande-capacite', 'name' => 'Autorail grande capacité', 'type' => 'autorail']);
        $version = Material::factory()->create(['slug' => 'x-76500', 'name' => 'X 76500', 'type' => 'autorail']);
        $this->link($family, 'high', [['kind' => 'wikipedia_line_article', 'quote' => 'Phrase.']]);
        $this->link($version, 'low', [['kind' => 'wikipedia_material_article', 'quote' => 'Essais.', 'doubt' => 'trial']]);

        $rows = $this->sheetMaterials();

        $this->assertTrue($rows->has('autorail-grande-capacite'), 'la famille a circulé : elle ne disparaît pas derrière une version en essais');
        $this->assertNull($rows['autorail-grande-capacite']['link_doubt']);
        $this->assertSame('trial', $rows['x-76500']['link_doubt']);
    }

    public function test_a_family_is_folded_into_a_version_that_ran(): void
    {
        $family = Material::factory()->create(['slug' => 'autorail-grande-capacite', 'name' => 'Autorail grande capacité', 'type' => 'autorail']);
        $version = Material::factory()->create(['slug' => 'x-76500', 'name' => 'X 76500', 'type' => 'autorail']);
        $this->link($family, 'high', [['kind' => 'wikipedia_line_article', 'quote' => 'Phrase.']]);
        $this->link($version, 'high', [['kind' => 'wikipedia_line_article', 'quote' => 'Phrase.']]);

        $rows = $this->sheetMaterials();

        $this->assertFalse($rows->has('autorail-grande-capacite'));
        $this->assertSame(['slug' => 'autorail-grande-capacite', 'name' => 'Autorail grande capacité'], $rows['x-76500']['family']);
    }

    public function test_rest_graphql_and_mcp_give_the_doubt_the_bound_and_the_family_of_the_sheet(): void
    {
        LineEvent::factory()->create(['line_id' => $this->line->id, 'type' => 'electrification', 'event_date' => '1996-06-01', 'date_precision' => 'day', 'confidence' => 'high', 'section_from' => 'Paris', 'section_to' => 'Cherbourg']);
        $dated = Material::factory()->create(['slug' => 'bb-15000', 'name' => 'BB 15000', 'type' => 'locomotive_electrique', 'in_service_from' => 1971, 'in_service_to' => null]);
        $trial = Material::factory()->create(['slug' => 'bb-26000', 'name' => 'BB 26000', 'type' => 'locomotive_electrique', 'in_service_to' => null]);
        $family = Material::factory()->create(['slug' => 'autorail-grande-capacite', 'name' => 'Autorail grande capacité', 'type' => 'autorail']);
        $version = Material::factory()->create(['slug' => 'x-76500', 'name' => 'X 76500', 'type' => 'autorail']);
        foreach ([$dated, $family, $version] as $m) {
            $this->link($m, 'high', [['kind' => 'wikipedia_line_article', 'quote' => 'Phrase.']]);
        }
        $this->link($trial, 'low', [['kind' => 'wikipedia_material_article', 'quote' => 'Essais.', 'doubt' => 'trial']]);
        LineMaterialPeriod::create(['line_material_id' => LineMaterial::query()->where('material_id', $dated->id)->value('id'), 'start_date' => '1980-01-01', 'start_precision' => 'year', 'confidence' => 'high', 'origin' => 'line_article', 'fingerprint' => md5((string) microtime()), 'source' => 'wikipedia', 'source_url' => 'https://fr.wikipedia.org/w/index.php?oldid=2']);
        $bound = ['reason' => 'electrification', 'date' => '1996-06-01', 'precision' => 'day', 'section' => null];

        $rest = self::bySlug($this->getJson('/api/v1/lines/rfn-366000/materials')->assertOk()->json('data'));
        $this->assertSame('trial', $rest['bb-26000']['link_doubt']);
        $this->assertNull($rest['bb-15000']['link_doubt']);
        $this->assertSame($bound + ['conflict' => true], $rest['bb-15000']['earliest_on_line']);
        $this->assertSame($bound + ['conflict' => false], $rest['bb-26000']['earliest_on_line']);
        $this->assertSame('1980-01-01', $rest['bb-15000']['periods'][0]['start_date']);
        $this->assertSame(['slug' => 'autorail-grande-capacite', 'name' => 'Autorail grande capacité'], $rest['x-76500']['family']);
        $this->assertTrue($rest['autorail-grande-capacite']['folded_into_versions']);
        $this->assertFalse($rest['x-76500']['folded_into_versions']);
        $filtered = self::bySlug($this->getJson('/api/v1/lines/rfn-366000/materials?type=locomotive_electrique')->assertOk()->json('data'));
        $this->assertSame(['bb-15000', 'bb-26000'], $filtered->keys()->sort()->values()->all());
        $this->assertSame('trial', $filtered['bb-26000']['link_doubt']);

        $gql = self::bySlug($this->postJson('/graphql', ['query' => '{ line(slug: "rfn-366000") { material_links { material { slug } link_doubt earliest_on_line { date precision section conflict } family { slug } folded_into_versions } } }'])
            ->assertOk()->json('data.line.material_links'));
        $this->assertSame('trial', $gql['bb-26000']['link_doubt']);
        $this->assertSame(['date' => '1996-06-01', 'precision' => 'day', 'section' => null, 'conflict' => true], $gql['bb-15000']['earliest_on_line']);
        $this->assertSame(['slug' => 'autorail-grande-capacite'], $gql['x-76500']['family']);
        $this->assertTrue($gql['autorail-grande-capacite']['folded_into_versions']);

        $result = $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'line_materials', 'arguments' => (object) ['line' => 'rfn-366000']]])
            ->assertOk()->json('result');
        $this->assertFalse((bool) $result['isError']);
        $mcp = json_decode($result['content'][0]['text'], true);
        $this->assertSame(1, $mcp['doubtful']);
        $bySlug = self::bySlug($mcp['materials']);
        $this->assertSame('trial', $bySlug['bb-26000']['link_doubt']);
        $this->assertSame($bound + ['conflict' => true], $bySlug['bb-15000']['earliest_on_line']);
        $this->assertSame('1980-01-01', $bySlug['bb-15000']['periods'][0]['start_date']);
        $this->assertTrue($bySlug['autorail-grande-capacite']['folded_into_versions']);
        $this->assertSame('autorail-grande-capacite', $bySlug['x-76500']['family']['slug']);
    }

    public function test_the_conflicts_command_lists_a_sourced_period_older_than_the_electrification(): void
    {
        LineEvent::factory()->create(['line_id' => $this->line->id, 'type' => 'electrification', 'event_date' => '1996-06-01', 'date_precision' => 'day', 'confidence' => 'high', 'section_from' => 'Paris', 'section_to' => 'Cherbourg']);
        $dated = Material::factory()->create(['slug' => 'bb-15000', 'name' => 'BB 15000', 'type' => 'locomotive_electrique', 'in_service_from' => 1971, 'in_service_to' => null]);
        $this->link($dated, 'high', [['kind' => 'wikipedia_line_article', 'quote' => 'Phrase.']]);
        LineMaterialPeriod::create(['line_material_id' => LineMaterial::query()->where('material_id', $dated->id)->value('id'), 'start_date' => '1980-01-01', 'start_precision' => 'year', 'confidence' => 'high', 'origin' => 'line_article', 'fingerprint' => md5((string) microtime()), 'source' => 'wikipedia', 'source_url' => 'https://fr.wikipedia.org/w/index.php?oldid=2']);

        $this->artisan('vff:material-bound-conflicts')
            ->expectsOutputToContain('BB 15000')
            ->expectsOutputToContain('1 période(s) sourcée(s) avant la première électrification connue, sur 1 ligne(s).')
            ->assertSuccessful();
    }
}
