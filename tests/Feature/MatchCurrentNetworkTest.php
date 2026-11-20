<?php

namespace Tests\Feature;

use App\Models\Line;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * vff:match-current-network : les lignes exploitées hors RFN (CFC en Corse…),
 * sans forme SNCF, reçoivent les voies BD TOPO / OSM qui suivent leur tracé
 * archeo « lignes exploitées » (sinon leur tracé de référence restait limité à
 * quelques morceaux OHM : 29 km sur 157 pour Bastia - Ajaccio).
 */
class MatchCurrentNetworkTest extends TestCase
{
    use RefreshDatabase;

    private function trace(string $source, string $ref, string $layer, ?int $lineId, array $props, float $lat, float $lon0 = 9.00, float $lon1 = 9.05): int
    {
        return (int) DB::selectOne(<<<'SQL'
            INSERT INTO source_traces (source, source_ref, layer, properties, length_m, line_id, match_method, seen_at, created_at, updated_at, geom)
            VALUES (?, ?, ?, CAST(? AS jsonb), 4000, ?, ?, NOW(), NOW(), NOW(),
                    ST_Multi(ST_SetSRID(ST_MakeLine(ST_MakePoint(?, ?), ST_MakePoint(?, ?)), 4326)))
            RETURNING id
            SQL, [$source, $ref, $layer, json_encode($props), $lineId, $lineId ? 'created' : null, $lon0, $lat, $lon1, $lat])->id;
    }

    public function test_exploited_line_outside_rfn_receives_the_precise_tracks_along_its_archeo_trace(): void
    {
        $line = Line::factory()->create(['code' => '995000', 'name' => 'Ligne de Bastia à Ajaccio']);
        $other = Line::factory()->create(['code' => '996000', 'name' => 'Ligne de Ponte-Leccia à Calvi']);
        $this->trace('archeoferroviaire', 'a1', 'exploitee', $line->id, [], 42.50);
        $this->trace('archeoferroviaire', 'a2', 'exploitee', $other->id, [], 42.60);
        // Voie BD TOPO en service à 2 m du tracé archeo de la première ligne ; une autre au loin.
        $on = $this->trace('bdtopo', 'b1', 'Voie ferrée principale', null, ['etat_de_l_objet' => 'En service'], 42.50002);
        $far = $this->trace('bdtopo', 'b2', 'Voie ferrée principale', null, ['etat_de_l_objet' => 'En service'], 42.70);
        // Way OSM disparu le long de la ligne exploitée : ancien tracé, refusé.
        $gone = $this->trace('osm', '123', 'abandoned', null, [], 42.50003);

        $this->artisan('vff:match-current-network', ['--line' => [(string) $line->id]])->assertSuccessful();

        $row = DB::table('source_traces')->where('id', $on)->first();
        $this->assertSame($line->id, (int) $row->line_id);
        $this->assertSame('exploited_overlap', $row->match_method);
        $this->assertNull(DB::table('source_traces')->where('id', $far)->value('line_id'));
        $this->assertNull(DB::table('source_traces')->where('id', $gone)->value('line_id'));
    }

    public function test_targeted_run_leaves_other_lines_attachments_untouched(): void
    {
        $line = Line::factory()->create(['code' => '995000', 'name' => 'Ligne de Bastia à Ajaccio']);
        $other = Line::factory()->create(['code' => '996000', 'name' => 'Ligne de Ponte-Leccia à Calvi']);
        $this->trace('archeoferroviaire', 'a1', 'exploitee', $line->id, [], 42.50);
        // Rattachement d'une autre ligne posé ailleurs (autre commande) : --line ne le touche pas.
        $kept = $this->trace('bdtopo', 'b9', 'Voie ferrée principale', $other->id, ['etat_de_l_objet' => 'En service'], 42.90);

        $this->artisan('vff:match-current-network', ['--line' => [(string) $line->id], '--source' => ['bdtopo']])->assertSuccessful();

        $this->assertSame($other->id, (int) DB::table('source_traces')->where('id', $kept)->value('line_id'));
    }
}
