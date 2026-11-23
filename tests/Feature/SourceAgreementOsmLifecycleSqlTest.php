<?php

namespace Tests\Feature;

use App\Models\Line;
use App\Services\SourceAgreement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Drapeau « voie OSM abandonnée » d'un morceau du tracé de référence (SourceAgreement::osmLifecycleSql) : vrai quand
 * toutes les voies OSM du dessin sont abandonnées, déposées ou inutilisées, que le morceau soit recalé sur elles
 * (matched_trace_ids) ou gardé tel quel depuis la trace source OSM de la ligne (source_trace_ids).
 */
class SourceAgreementOsmLifecycleSqlTest extends TestCase
{
    use RefreshDatabase;

    private int $line;

    protected function setUp(): void
    {
        parent::setUp();
        $this->line = Line::factory()->create()->id;
    }

    private function trace(string $source, string $layer): int
    {
        return (int) DB::selectOne(<<<'SQL'
            INSERT INTO source_traces (source, source_ref, layer, line_id, properties, length_m, seen_at, created_at, updated_at, geom)
            VALUES (?, ?, ?, ?, '{}'::jsonb, 700, NOW(), NOW(), NOW(), ST_GeomFromText('LINESTRING(-0.37 49.25, -0.36 49.25)', 4326))
            RETURNING id
            SQL, [$source, uniqid($source, true), $layer, $this->line])->id;
    }

    /**
     * @param  list<int>  $sources
     * @param  list<int>|null  $matched
     */
    private function flag(string $source, array $sources, ?string $matchedSource, ?array $matched): bool
    {
        $id = (int) DB::selectOne(<<<'SQL'
            INSERT INTO line_reference_parts (line_id, source, source_trace_ids, matched_source, matched_trace_ids, length_m, geom, created_at, updated_at)
            VALUES (?, ?, CAST(? AS jsonb), ?, CAST(? AS jsonb), 700, ST_Multi(ST_GeomFromText('LINESTRING(-0.37 49.25, -0.36 49.25)', 4326)), NOW(), NOW())
            RETURNING id
            SQL, [$this->line, $source, json_encode($sources), $matchedSource, $matched === null ? null : json_encode($matched)])->id;

        return (bool) DB::selectOne('SELECT '.SourceAgreement::osmLifecycleSql().' AS f FROM line_reference_parts p WHERE p.id = ?', [$id])->f;
    }

    public function test_part_matched_on_abandoned_osm_tracks_only_is_flagged(): void
    {
        $archeo = $this->trace('archeoferroviaire', 'deposee');
        $this->assertTrue($this->flag('archeoferroviaire', [$archeo], 'osm', [$this->trace('osm', 'abandoned'), $this->trace('osm', 'dismantled')]));
        $this->assertFalse($this->flag('archeoferroviaire', [$archeo], 'osm', [$this->trace('osm', 'abandoned'), $this->trace('osm', 'rail')]));
    }

    public function test_part_kept_from_an_abandoned_osm_source_trace_is_flagged(): void
    {
        $this->assertTrue($this->flag('osm', [$this->trace('osm', 'razed')], null, null));
        $this->assertFalse($this->flag('osm', [$this->trace('osm', 'rail')], null, null));
    }

    public function test_other_families_are_never_flagged(): void
    {
        $osm = $this->trace('osm', 'abandoned');
        $this->assertFalse($this->flag('archeoferroviaire', [$this->trace('archeoferroviaire', 'deposee')], 'bdtopo', [$this->trace('bdtopo', 'Sans objet')]));
        $this->assertFalse($this->flag('osm', [$osm], 'fusion', [$this->trace('fusion', 'precis')]));
        $this->assertFalse($this->flag('osm', [], null, null));
    }
}
