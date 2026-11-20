<?php

namespace Tests\Feature\Mutation;

use App\Models\Era;
use App\Models\Line;
use App\Models\LineMaterial;
use App\Models\LineSegment;
use App\Models\Material;
use App\Services\Materials\MaterialGaugeRule;
use App\Services\Materials\MaterialTractionRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Règles d'écartement et d'électrification chargées depuis la base (fromDatabase, storedConflicts) :
 * longueurs additionnées par ère et par tension, années de service lues, chaque lien en conflit
 * rapporté. Écrit pour les tests de mutation (tests/Mutation/Critical).
 */
class MaterialRulesFromDatabaseTest extends TestCase
{
    use RefreshDatabase;

    private const DC_1500 = ['label' => 'électrification ferroviaire 1500 V continu', 'wikidata_id' => 'Q21253457'];

    private Era $old;

    private Era $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->old = Era::factory()->create(['year' => 1990]);
        $this->now = Era::factory()->create(['year' => 2030]);
    }

    /** Ligne de 200 m dans l'ère courante, dont `$electrified` mètres sous 1 500 V. */
    private function shortLine(string $code, int $electrified): Line
    {
        $line = Line::factory()->create(['code' => $code, 'slug' => "rfn-{$code}", 'name' => "Ligne {$code}"]);
        $segment = ['line_id' => $line->id, 'era_id' => $this->now->id, 'electrification_source' => 'sncf'];
        LineSegment::factory()->create($segment + ['electrified' => true, 'voltage_v' => 1500, 'length_meters' => $electrified]);
        LineSegment::factory()->create($segment + ['electrified' => false, 'voltage_v' => null, 'length_meters' => 200 - $electrified]);

        return $line;
    }

    private function electricSeries(string $slug, ?int $from = null): Material
    {
        return Material::factory()->create(['slug' => $slug, 'name' => strtoupper($slug), 'type' => 'locomotive_electrique',
            'wikidata_id' => 'Q'.crc32($slug), 'electrification' => [self::DC_1500], 'in_service_from' => $from, 'in_service_to' => null]);
    }

    public function test_lengths_are_added_exactly_from_the_segments(): void
    {
        // 10 m sur 200 : 5 %, le seuil, le lien tient ; 9 m sur 200 : 4,5 %, il est refusé.
        $enough = $this->shortLine('900001', 10);
        $short = $this->shortLine('900002', 9);
        $series = $this->electricSeries('bb-1');
        $rule = MaterialTractionRule::fromDatabase([]);

        $this->assertNull($rule->conflict($enough->id, $series->id));
        $this->assertSame(['volts' => [1500], 'share' => 5, 'partial' => true], $rule->details($short->id, $series->id));
    }

    public function test_eras_before_the_series_entered_service_do_not_count(): void
    {
        $line = Line::factory()->create(['code' => '900003', 'slug' => 'rfn-900003', 'name' => 'Ligne désélectrifiée']);
        $segment = ['line_id' => $line->id, 'electrification_source' => 'sncf', 'length_meters' => 1000];
        LineSegment::factory()->create($segment + ['era_id' => $this->old->id, 'electrified' => true, 'voltage_v' => 1500]);
        LineSegment::factory()->create($segment + ['era_id' => $this->now->id, 'electrified' => false, 'voltage_v' => null]);
        $older = $this->electricSeries('bb-ancienne', 1980);
        $recent = $this->electricSeries('bb-recente', 2000);
        $rule = MaterialTractionRule::fromDatabase([]);

        $this->assertNull($rule->conflict($line->id, $older->id));
        $this->assertNotNull($rule->conflict($line->id, $recent->id));
    }

    public function test_every_conflicting_traction_link_is_reported(): void
    {
        $line = $this->shortLine('900004', 0);
        $first = LineMaterial::create(['line_id' => $line->id, 'material_id' => $this->electricSeries('bb-a')->id, 'source' => 'wikipedia', 'evidence' => []]);
        $second = LineMaterial::create(['line_id' => $line->id, 'material_id' => $this->electricSeries('bb-b')->id, 'source' => 'wikipedia', 'evidence' => []]);

        $conflicts = MaterialTractionRule::fromDatabase([])->storedConflicts();

        $this->assertSame([$first->id, $second->id], array_column($conflicts, 'id'));
        $this->assertSame(['900004', '900004'], array_column($conflicts, 'line'));
    }

    public function test_every_conflicting_gauge_link_is_reported(): void
    {
        $line = Line::factory()->create(['code' => '900005', 'slug' => 'rfn-900005', 'name' => 'Ligne métrique']);
        LineSegment::factory()->create(['line_id' => $line->id, 'era_id' => $this->now->id, 'gauge_mm' => 1000, 'length_meters' => 5000]);
        $links = [];
        foreach (['x-2100', 'x-2400'] as $slug) {
            $series = Material::factory()->create(['slug' => $slug, 'name' => strtoupper($slug), 'type' => 'autorail', 'gauge_mm' => 1435]);
            $links[] = LineMaterial::create(['line_id' => $line->id, 'material_id' => $series->id, 'source' => 'wikipedia', 'evidence' => []])->id;
        }

        $conflicts = MaterialGaugeRule::fromDatabase([])->storedConflicts();

        $this->assertSame($links, array_column($conflicts, 'id'));
        $this->assertSame('série à 1 435 mm, ligne surtout à 1 000 mm (0 % de sa longueur à 1 435 mm)', $conflicts[0]['reason'] ?? null);
    }
}
