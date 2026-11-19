<?php

namespace Tests\Feature\Materials;

use App\Models\Era;
use App\Models\Line;
use App\Models\LineMaterial;
use App\Models\LineSegment;
use App\Models\Material;
use App\Services\DataInvariants;
use App\Services\Materials\MaterialGaugeRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Invariant `line_materials.gauge_conflict` de vff:check-invariants : aucun lien ligne × matériel dont
 * l'écartement contredit celui de la ligne, sauf phrase de preuve qui nomme l'écartement ou exception relue.
 */
class MaterialGaugeInvariantTest extends TestCase
{
    use RefreshDatabase;

    private Line $salbrisArgent;

    private Material $x73500;

    private Material $x74500;

    protected function setUp(): void
    {
        parent::setUp();
        $era = Era::factory()->create(['year' => 2030]);
        $this->salbrisArgent = Line::factory()->create(['code' => '693000', 'name' => 'Ligne de Salbris à Argent']);
        LineSegment::factory()->create(['line_id' => $this->salbrisArgent->id, 'era_id' => $era->id, 'gauge_mm' => 1000, 'length_meters' => 40_000]);
        $this->x73500 = Material::factory()->create(['slug' => 'x-73500', 'name' => 'X 73500', 'wikidata_id' => 'Q2784315', 'gauge_mm' => 1435]);
        $this->x74500 = Material::factory()->create(['slug' => 'x-74500', 'name' => 'X 74500', 'wikidata_id' => 'Q3570157', 'gauge_mm' => 1000]);
    }

    /** @return array{count: int, examples: list<string>} */
    private function invariant(): array
    {
        $result = (new DataInvariants(['line_materials.gauge_conflict']))->run();
        $this->assertCount(1, $result);
        $this->assertArrayNotHasKey('error', $result[0]);

        return ['count' => $result[0]['count'], 'examples' => $result[0]['examples']];
    }

    public function test_a_link_against_the_line_gauge_is_reported(): void
    {
        LineMaterial::create(['line_id' => $this->salbrisArgent->id, 'material_id' => $this->x74500->id, 'source' => 'wikipedia', 'evidence' => []]);
        $bad = LineMaterial::create(['line_id' => $this->salbrisArgent->id, 'material_id' => $this->x73500->id, 'source' => 'wikipedia', 'evidence' => [
            ['kind' => 'wikipedia_line_article', 'quote' => 'Ce nouveau matériel offre un confort similaire aux autorails X 73500, avec notamment la climatisation.'],
        ]]);

        $this->assertSame([
            'count' => 1,
            'examples' => ["693000 × X 73500 (lien {$bad->id}) : série à 1 435 mm, ligne surtout à 1 000 mm (0 % de sa longueur à 1 435 mm)"],
        ], $this->invariant());
    }

    public function test_a_sourced_exception_or_a_sentence_naming_the_gauge_is_allowed(): void
    {
        $link = LineMaterial::create(['line_id' => $this->salbrisArgent->id, 'material_id' => $this->x73500->id, 'source' => 'wikipedia', 'evidence' => [
            ['kind' => 'wikipedia_line_article', 'quote' => 'Des X 73500 ont circulé sur le raccordement à voie normale de Salbris.'],
        ]]);
        $this->assertSame(0, $this->invariant()['count'], 'la phrase nomme l\'écartement de la série');

        $link->update(['evidence' => []]);
        $this->assertSame(1, $this->invariant()['count']);

        $exception = [['line' => '693000', 'material' => 'Q2784315', 'source' => 'https://fr.wikipedia.org/w/index.php?oldid=1', 'reason' => 'essai']];
        $this->assertSame([], MaterialGaugeRule::fromDatabase($exception)->storedConflicts(), 'exception relue, avec sa source');
        $this->assertCount(1, MaterialGaugeRule::fromDatabase([['line' => '693000', 'material' => 'Q2784315', 'source' => ' ', 'reason' => 'sans source']])->storedConflicts(), 'une exception sans source ne vaut rien');
    }

    public function test_the_exceptions_file_is_well_formed(): void
    {
        $exceptions = require database_path('data/line_material_gauge_exceptions.php');

        $this->assertIsArray($exceptions);
        foreach ($exceptions as $e) {
            $this->assertMatchesRegularExpression('/^\d{6}$/', $e['line']);
            $this->assertMatchesRegularExpression('/^Q\d+$/', $e['material']);
            $this->assertStringStartsWith('https://', $e['source']);
            $this->assertNotSame('', trim($e['reason']));
        }
    }
}
