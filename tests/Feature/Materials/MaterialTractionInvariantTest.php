<?php

namespace Tests\Feature\Materials;

use App\Models\Era;
use App\Models\Line;
use App\Models\LineMaterial;
use App\Models\LineSegment;
use App\Models\Material;
use App\Services\DataInvariants;
use App\Services\Materials\LineMaterialExclusions;
use App\Services\Materials\MaterialTractionRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Invariant `line_materials.traction_conflict` de vff:check-invariants (aucune série électrique reliée à une
 * ligne jamais électrifiée dans un de ses systèmes), exceptions sourcées et exclusions relues.
 */
class MaterialTractionInvariantTest extends TestCase
{
    use RefreshDatabase;

    private const DC_1500 = ['label' => 'électrification ferroviaire 1500 V continu', 'wikidata_id' => 'Q21253457'];

    private const AC_25K = ['label' => 'électrification ferroviaire 25 kV 50 Hz', 'wikidata_id' => 'Q106530439'];

    private Line $valenceMoirans;

    private Material $bb7200;

    private Material $bb22200;

    protected function setUp(): void
    {
        parent::setUp();
        $old = Era::factory()->create(['year' => 1990]);
        $now = Era::factory()->create(['year' => 2030]);
        // Valence - Moirans : 25 kV aujourd'hui, sauf 2,2 km de 1 500 V côté Valence-Ville ; rien en 1990.
        $this->valenceMoirans = Line::factory()->create(['code' => '908000', 'slug' => 'rfn-908000', 'name' => 'Ligne de Valence à Moirans']);
        $segment = ['line_id' => $this->valenceMoirans->id, 'electrification_source' => 'sncf'];
        LineSegment::factory()->create($segment + ['era_id' => $old->id, 'electrified' => false, 'voltage_v' => null, 'length_meters' => 79_000]);
        LineSegment::factory()->create($segment + ['era_id' => $now->id, 'electrified' => true, 'voltage_v' => 1500, 'length_meters' => 2_200]);
        LineSegment::factory()->create($segment + ['era_id' => $now->id, 'electrified' => true, 'voltage_v' => 25000, 'length_meters' => 76_800]);
        $this->bb7200 = Material::factory()->create(['slug' => 'bb-7200', 'name' => 'BB 7200', 'type' => 'locomotive_electrique', 'wikidata_id' => 'Q607033', 'electrification' => [self::DC_1500], 'in_service_from' => 1976, 'in_service_to' => null]);
        $this->bb22200 = Material::factory()->create(['slug' => 'bb-22200', 'name' => 'BB 22200', 'type' => 'locomotive_electrique', 'wikidata_id' => 'Q768349', 'electrification' => [self::DC_1500, self::AC_25K], 'in_service_from' => 1976, 'in_service_to' => null]);
    }

    /** @return array{count: int, examples: list<string>} */
    private function invariant(): array
    {
        $result = (new DataInvariants(['line_materials.traction_conflict']))->run();
        $this->assertCount(1, $result);
        $this->assertArrayNotHasKey('error', $result[0]);

        return ['count' => $result[0]['count'], 'examples' => $result[0]['examples']];
    }

    public function test_a_link_against_the_line_electrification_is_reported(): void
    {
        LineMaterial::create(['line_id' => $this->valenceMoirans->id, 'material_id' => $this->bb22200->id, 'source' => 'wikipedia', 'evidence' => []]);
        $bad = LineMaterial::create(['line_id' => $this->valenceMoirans->id, 'material_id' => $this->bb7200->id, 'source' => 'wikipedia', 'evidence' => [
            ['kind' => 'wikipedia_line_article', 'doubt' => 'diverted', 'quote' => 'Par suite de la coupure totale de la ligne, en 2007, 2008, 2009 et 2013, les trains de pèlerinages circulent entièrement en traction électrique'],
        ]]);

        $this->assertSame([
            'count' => 1,
            'examples' => ["908000 × BB 7200 (lien {$bad->id}) : série à 1 500 V, ligne au plus à 3 % de sa longueur sous un courant compatible"],
        ], $this->invariant());
    }

    public function test_unknown_electrification_and_thermal_series_are_not_judged(): void
    {
        $autorail = Material::factory()->create(['slug' => 'b-82500', 'name' => 'B 82500', 'type' => 'bimode', 'wikidata_id' => 'Q2876', 'electrification' => [self::DC_1500]]);
        $unknown = Line::factory()->create(['code' => '999000', 'slug' => 'rfn-999000', 'name' => 'Ligne sans source']);
        LineSegment::factory()->create(['line_id' => $unknown->id, 'era_id' => Era::query()->where('year', 2030)->value('id'), 'electrified' => false, 'voltage_v' => null, 'electrification_source' => 'none', 'length_meters' => 10_000]);
        LineMaterial::create(['line_id' => $unknown->id, 'material_id' => $this->bb7200->id, 'source' => 'wikipedia', 'evidence' => []]);
        LineMaterial::create(['line_id' => $this->valenceMoirans->id, 'material_id' => $autorail->id, 'source' => 'wikipedia', 'evidence' => []]);

        $this->assertSame(0, $this->invariant()['count'], 'source « none » : pas de jugement ; bimode : roule sans caténaire');
    }

    public function test_a_sourced_exception_or_a_sentence_naming_the_voltage_is_allowed(): void
    {
        $link = LineMaterial::create(['line_id' => $this->valenceMoirans->id, 'material_id' => $this->bb7200->id, 'source' => 'wikipedia', 'evidence' => [
            ['kind' => 'wikipedia_line_article', 'quote' => 'Les BB 7200 tractent les trains sur la section en 1 500 V de Valence-Ville.'],
        ]]);
        $this->assertSame(0, $this->invariant()['count'], 'la phrase nomme la tension de la série');

        $link->update(['evidence' => []]);
        $this->assertSame(1, $this->invariant()['count']);

        $exception = [['line' => '908000', 'material' => 'Q607033', 'source' => 'https://fr.wikipedia.org/w/index.php?oldid=1', 'reason' => 'essai']];
        $this->assertSame([], MaterialTractionRule::fromDatabase($exception)->storedConflicts(), 'exception relue, avec sa source');
        $this->assertCount(1, MaterialTractionRule::fromDatabase([['line' => '908000', 'material' => 'Q607033', 'source' => ' ', 'reason' => 'sans source']])->storedConflicts(), 'une exception sans source ne vaut rien');
        $this->assertSame([], MaterialTractionRule::fromDatabase($exception)->unresolvedEntries());
        // Revue des PR #19 et #20 : une exception qui ne trouve pas sa ligne est signalée, pas ignorée en silence.
        $this->assertSame(
            ['line_material_traction_exceptions.php n° 0 (ligne « ligne-disparue », série Q607033) : ligne introuvable (ni lines.code ni lines.slug)'],
            MaterialTractionRule::fromDatabase([['line' => 'ligne-disparue', 'material' => 'Q607033', 'source' => 'https://fr.wikipedia.org/w/index.php?oldid=1', 'reason' => 'essai']])->unresolvedEntries(),
        );
    }

    public function test_exclusions_remove_one_sentence_or_the_whole_link(): void
    {
        $rille = Line::factory()->create(['code' => null, 'slug' => 'hors-rfn-chemin-de-fer-de-rille', 'name' => 'Chemin de fer du lac de Rillé']);
        $pechot = Material::factory()->create(['slug' => 'systeme-pechot', 'name' => 'Système Péchot', 'type' => 'wagon', 'wikidata_id' => 'Q2120514']);
        $exclusions = LineMaterialExclusions::fromDatabase([
            ['line' => 'hors-rfn-chemin-de-fer-de-rille', 'material' => 'Q2120514', 'quote' => 'en cours de remise en état historique', 'source' => 'https://fr.wikipedia.org/w/index.php?oldid=241314245', 'reason' => 'en restauration'],
            ['line' => '908000', 'material' => 'Q607033', 'source' => 'https://fr.wikipedia.org/w/index.php?oldid=239909508', 'reason' => 'série à 1 500 V seulement'],
            ['line' => '908000', 'material' => 'Q768349', 'source' => '', 'reason' => 'sans source, ignorée'],
        ]);

        $restored = ['quote' => '1 plateforme Péchot provenant du Chemin de fer des Chanteraines, en cours de remise en état historique ;'];
        $running = ['quote' => 'Des plateformes Péchot circulent dans les trains de travaux.'];
        $evidence = [$restored, $running];
        $this->assertNull($exclusions->apply($rille->id, $pechot->id, $evidence), 'une autre phrase prouve encore le lien');
        $this->assertSame([$running], $evidence);

        $evidence = [$restored];
        $this->assertSame('exclusion relue : en restauration', $exclusions->apply($rille->id, $pechot->id, $evidence));

        $evidence = [['kind' => 'wikidata']];
        $this->assertSame('exclusion relue : série à 1 500 V seulement', $exclusions->apply($this->valenceMoirans->id, $this->bb7200->id, $evidence), 'sans citation : le couple entier');
        $this->assertNull($exclusions->apply($this->valenceMoirans->id, $this->bb22200->id, $evidence), 'une entrée sans source ne vaut rien');
    }

    public function test_the_data_files_are_well_formed(): void
    {
        foreach (['line_material_traction_exceptions', 'line_material_exclusions'] as $file) {
            $entries = require database_path("data/{$file}.php");
            $this->assertIsArray($entries);
            foreach ($entries as $e) {
                $this->assertMatchesRegularExpression('/^(\d{6}|[a-z0-9-]+)$/', $e['line'], $file);
                $this->assertMatchesRegularExpression('/^Q\d+$/', $e['material'], $file);
                $this->assertStringStartsWith('https://', $e['source'], $file);
                $this->assertMatchesRegularExpression('/[?&]oldid=\d+/', $e['source'], "{$file} : révision figée");
                $this->assertNotSame('', trim($e['reason']), $file);
                if (isset($e['quote'])) {
                    $this->assertGreaterThanOrEqual(15, mb_strlen($e['quote']), "{$file} : citation assez précise");
                }
            }
        }
    }
}
