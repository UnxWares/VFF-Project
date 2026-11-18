<?php

namespace Tests\Feature;

use App\Models\Era;
use App\Models\Line;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Fiche ligne (LineController::show) : synthèse par décennie `era_summary`
 * (frise « évolution ») : km en service / fermés / déposés, km patrimoine,
 * trafic dominant, avec « heritage » quand le train touristique est majoritaire.
 */
class LineEraSummaryTest extends TestCase
{
    use RefreshDatabase;

    private Line $line;

    /** @var array<int, Era> */
    private array $eras = [];

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([1960, 1990, 2000, 2010, 2020, 2030] as $year) {
            $this->eras[$year] = $year === 2030
                ? Era::factory()->current()->create(['year' => $year])
                : Era::factory()->create(['year' => $year]);
        }
        $this->line = Line::factory()->create(['name' => 'Ligne de Alpha à Bravo', 'slug' => 'ligne-alpha-bravo']);
    }

    public function test_summary_per_decade(): void
    {
        $this->segment(1960, 8000, 'in_service', 'mixed');
        $this->segment(1960, 2000, 'in_service', 'freight');
        $this->segment(1990, 10000, 'abandoned', 'unknown');
        $this->segment(2000, 10000, 'dismantled', 'unknown');
        // 2010 : train touristique majoritaire (6 km sur 10 km en service).
        $this->segment(2010, 6000, 'in_service', 'unknown', 'tourist_train');
        $this->segment(2010, 4000, 'in_service', 'mixed');
        // 2020 : vélorail (voie fermée exploitée par une association).
        $this->segment(2020, 10000, 'abandoned', 'unknown', 'velorail');
        // 2030 : train touristique minoritaire (3 km sur 10).
        $this->segment(2030, 3000, 'in_service', 'unknown', 'tourist_train');
        $this->segment(2030, 7000, 'in_service', 'mixed');

        $summary = collect($this->summary())->keyBy('year');

        $this->assertSame([1960, 1990, 2000, 2010, 2020, 2030], $summary->keys()->all(), 'ordre chronologique');
        $this->assertSame(['active_km' => 10.0, 'closed_km' => 0.0, 'gone_km' => 0.0, 'heritage_km' => 0.0, 'traffic' => 'mixed', 'state' => 'open'],
            $this->only($summary[1960]));
        $this->assertSame(['active_km' => 0.0, 'closed_km' => 10.0, 'gone_km' => 0.0, 'heritage_km' => 0.0, 'traffic' => null, 'state' => 'closed'],
            $this->only($summary[1990]));
        $this->assertSame('gone', $summary[2000]['state']);
        $this->assertSame(10.0, $summary[2000]['gone_km']);
        $this->assertSame(['active_km' => 10.0, 'heritage_km' => 6.0, 'traffic' => 'heritage', 'state' => 'open'],
            array_intersect_key($summary[2010], array_flip(['active_km', 'heritage_km', 'traffic', 'state'])));
        // Vélorail : km patrimoine comptés, mais voie fermée (pas de train), pas de couleur « heritage ».
        $this->assertSame(['active_km' => 0.0, 'closed_km' => 10.0, 'heritage_km' => 10.0, 'traffic' => null, 'state' => 'closed'],
            array_intersect_key($summary[2020], array_flip(['active_km', 'closed_km', 'heritage_km', 'traffic', 'state'])));
        $this->assertSame(['heritage_km' => 3.0, 'traffic' => 'mixed'], array_intersect_key($summary[2030], array_flip(['heritage_km', 'traffic'])));
        $this->assertTrue($summary[2030]['is_current']);
        $this->assertFalse($summary[1960]['is_current']);
    }

    public function test_tourist_train_exactly_half_of_the_line_counts_as_heritage(): void
    {
        $this->segment(2030, 5000, 'in_service', 'unknown', 'tourist_train');
        $this->segment(2030, 5000, 'in_service', 'mixed');

        $this->assertSame('heritage', collect($this->summary())->firstWhere('year', 2030)['traffic']);
    }

    public function test_dominant_traffic_ignores_heritage_segments(): void
    {
        $this->segment(2030, 1000, 'in_service', 'unknown', 'tourist_train');
        $this->segment(2030, 3000, 'in_service', 'freight');
        $this->segment(2030, 2000, 'in_service', 'mixed');

        $this->assertSame('freight', collect($this->summary())->firstWhere('year', 2030)['traffic']);
    }

    public function test_decade_without_segment_is_not_listed(): void
    {
        $this->segment(1960, 1000, 'in_service', 'mixed');

        $this->assertSame([1960], array_column($this->summary(), 'year'));
    }

    // ─── Fabrique ────────────────────────────────────────────────────────

    /** @return list<array<string, mixed>> */
    private function summary(): array
    {
        $props = $this->get('/lines/'.$this->line->slug)->assertOk()->viewData('page')['props'];

        return collect($props['era_summary'])->toArray();
    }

    /** @return array<string, mixed> */
    private function only(array $row): array
    {
        return array_intersect_key($row, array_flip(['active_km', 'closed_km', 'gone_km', 'heritage_km', 'traffic', 'state']));
    }

    private function segment(int $year, int $length, string $status, string $traffic, ?string $heritage = null): void
    {
        static $n = 0;
        $lat = 48.0 + 0.01 * $n++;
        DB::table('line_segments')->insert([
            'line_id' => $this->line->id, 'era_id' => $this->eras[$year]->id, 'status' => $status, 'traffic_type' => $traffic,
            'is_heritage' => $heritage !== null, 'heritage_kind' => $heritage, 'length_meters' => $length,
            'geom' => DB::raw(sprintf("ST_GeomFromText('LINESTRING(2.0 %F, 2.1 %F)', 4326)", $lat, $lat)),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
