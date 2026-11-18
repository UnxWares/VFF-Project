<?php

namespace Tests\Feature\Commands;

use App\Models\Line;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * vff:import-wikidata-lines : rattachement des items aux lignes VFF
 * (QID déclaré, code RFN, code de portion, doublons, nom + position,
 * homonyme lointain, ambiguïté, gares + position). Cache local, aucun réseau.
 *
 * Repères : à 48° N, 0,1° de longitude ≈ 7,4 km.
 */
class WikidataLinesMatchingTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    /** @var array<string, array<string, mixed>> */
    private array $entities = [];

    /** @var array<string, list<array<string, mixed>>> */
    private array $stations = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
        Http::fake();
        $this->era(2030, current: true);
    }

    private function item(string $qid, string $label, array $codes = [], array $stations = []): void
    {
        $claims = [];
        foreach ($codes as $c) {
            $claims['P1671'][] = ['mainsnak' => ['snaktype' => 'value', 'property' => 'P1671', 'datavalue' => ['value' => $c]], 'rank' => 'normal', 'id' => $qid.'$'.$c];
        }
        $this->entities[$qid] = ['labels' => ['fr' => ['value' => $label]], 'claims' => $claims];
        $this->stations[$qid] = array_map(fn ($p) => ['qid' => 'S'.md5(json_encode($p)), 'lon' => $p[0], 'lat' => $p[1], 'adm' => []], $stations);
    }

    private function writeCache(): void
    {
        $dir = storage_path('app/sources/wikidata');
        File::ensureDirectoryExists($dir);
        File::put($dir.'/items.json', json_encode(['fetched_at' => '2026-10-15 10:00:00', 'items' => array_map(fn () => ['Q728937'], $this->entities)]));
        File::put($dir.'/entities.json', json_encode($this->entities));
        File::put($dir.'/refs.json', json_encode([]));
        File::put($dir.'/stations.json', json_encode($this->stations));
        File::put($dir.'/admin.json', json_encode([]));
    }

    /** @param  array<string, mixed>  $attributes */
    private function line(string $name, ?string $code, ?float $lat = null, array $attributes = []): Line
    {
        $line = Line::factory()->create($attributes + ['name' => $name, 'code' => $code, 'type' => null, 'network' => $code ? 'principal' : 'secondaire',
            'slug' => Str::slug($name).'-'.random_int(1000, 99999), 'wikipedia_title' => null, 'length_official_m' => null]);
        if ($lat !== null) {
            DB::insert("INSERT INTO source_traces (source, source_ref, layer, line_id, length_m, seen_at, geom)
                VALUES ('osm', ?, 'rail', ?, 7400, now(), ST_Multi(ST_GeomFromText(?, 4326)))", ['w'.$line->id, $line->id, "LINESTRING(2.0 {$lat}, 2.1 {$lat})"]);
        }

        return $line;
    }

    /** @return array<string, object> */
    private function staging(): array
    {
        return DB::table('wikidata_lines')->get(['qid', 'line_id', 'match_method', 'match_note'])->keyBy('qid')->all();
    }

    public function test_items_are_linked_by_declaration_code_name_position_or_stations(): void
    {
        $declared = $this->line('Ligne de Carhaix à Morlaix', '482000', null, ['alt_names' => ['wikidata_qid' => 'Q1']]);
        $this->item('Q1', 'ligne de Carhaix à Morlaix');
        $this->item('Q2', 'ligne Carhaix - Morlaix (doublon)', ['482 000']);

        $coded = $this->line('Ligne de Guingamp à Paimpol', '483000');
        $this->item('Q3', 'ligne de Guingamp à Paimpol', ['483 000']);
        $this->item('Q4', 'chemin de fer de Paimpol', ['483000']);

        $partA = $this->line('Ligne de Dol à Dinan', '484000');
        $partB = $this->line('Ligne de Dinan à Lamballe', '485000');
        $this->item('Q5', 'ligne de Dol à Lamballe', ['484 000', '485 000']);

        $geo = $this->line('Ligne de Pontivy à Loudéac', null, 48.0);
        $this->item('Q6', 'ligne de Pontivy à Loudéac', [], [[2.0, 48.0], [2.05, 48.0], [2.1, 48.0]]);

        $nameOnly = $this->line('Ligne de Vannes à Plaudren', null);
        $this->item('Q7', 'ligne de Vannes à Plaudren');

        $this->line('Ligne de Guer à Mauron', null, 48.2);
        $this->item('Q8', 'ligne de Guer à Mauron', [], [[3.0, 48.9], [3.1, 48.9]]);

        $this->line('Ligne de Saint-Méen à Loudéac', null);
        $this->line('Ligne de Saint-Méen à Loudéac', null);
        $this->item('Q9', 'ligne de Saint-Méen à Loudéac');

        $corse = $this->line('Ligne de Casamozza à Porto-Vecchio', null, 48.4);
        $this->item('Q10', 'ligne de la côte orientale corse', [], [[2.0, 48.4], [2.03, 48.4], [2.06, 48.4], [2.09, 48.4]]);

        $this->item('Q11', 'ligne de Nulle-Part à Ailleurs');
        $this->writeCache();

        $this->artisan('vff:import-wikidata-lines', ['--from-cache' => true])->assertSuccessful();

        $s = $this->staging();
        $this->assertSame(['declared', $declared->id], [$s['Q1']->match_method, (int) $s['Q1']->line_id]);
        $this->assertSame(['duplicate', null, 'code 482000 : ligne liée à son QID déclaré Q1'], [$s['Q2']->match_method, $s['Q2']->line_id, $s['Q2']->match_note]);
        $this->assertSame(['code', $coded->id], [$s['Q3']->match_method, (int) $s['Q3']->line_id]);
        $this->assertSame(['duplicate', 'code 483000 déjà rattaché à Q3 (Ligne de Guingamp à Paimpol)'], [$s['Q4']->match_method, $s['Q4']->match_note]);
        $this->assertSame('code_part', $s['Q5']->match_method);
        $this->assertContains((int) $s['Q5']->line_id, [$partA->id, $partB->id]);
        $this->assertSame(['name_geo', $geo->id], [$s['Q6']->match_method, (int) $s['Q6']->line_id]);
        $this->assertSame(['name', $nameOnly->id], [$s['Q7']->match_method, (int) $s['Q7']->line_id]);
        $this->assertSame(['none', null], [$s['Q8']->match_method, $s['Q8']->line_id]);
        $this->assertSame('homonyme rejeté : terminus / gares loin du tracé de « Ligne de Guer à Mauron »', $s['Q8']->match_note);
        $this->assertSame('ambiguous', $s['Q9']->match_method);
        $this->assertStringStartsWith('candidats trop proches : Ligne de Saint-Méen à Loudéac (0.6)', $s['Q9']->match_note);
        $this->assertSame(['stations_geo', $corse->id], [$s['Q10']->match_method, (int) $s['Q10']->line_id]);
        $this->assertSame(['none', null], [$s['Q11']->match_method, $s['Q11']->line_id]);
    }

    public function test_name_matching_guards(): void
    {
        // Item portant un AUTRE code que la ligne VFF homonyme : jamais rattaché par le nom.
        $this->line('Ligne de Lannion à Perros', '486000', 48.0);
        $this->item('Q12', 'ligne de Lannion à Perros', ['999 000']);
        // Deux items pour la même ligne : aucun ne l'emporte.
        $redon = $this->line('Ligne de Rennes à Redon', null);
        $this->item('Q13', 'ligne de Rennes à Redon');
        $this->item('Q14', 'Ligne de Rennes à Redon');
        // Ligne déjà prise par son code : l'item homonyme sans code en est un doublon.
        $roscoff = $this->line('Ligne de Morlaix à Roscoff', '487000');
        $this->item('Q15', 'ligne 487000', ['487 000']);
        $this->item('Q16', 'ligne de Morlaix à Roscoff');
        $this->writeCache();

        $this->artisan('vff:import-wikidata-lines', ['--from-cache' => true])->assertSuccessful();

        $s = $this->staging();
        $this->assertSame(['none', null], [$s['Q12']->match_method, $s['Q12']->line_id]);
        foreach (['Q13', 'Q14'] as $q) {
            $this->assertSame(['ambiguous', null, 'plusieurs items Wikidata pour « Ligne de Rennes à Redon »'],
                [$s[$q]->match_method, $s[$q]->line_id, $s[$q]->match_note]);
        }
        $this->assertSame(['code', $roscoff->id], [$s['Q15']->match_method, (int) $s['Q15']->line_id]);
        $this->assertSame(['duplicate', 'décrit « Ligne de Morlaix à Roscoff » (nom + position), déjà rattachée à Q15'],
            [$s['Q16']->match_method, $s['Q16']->match_note]);
        unset($redon);
    }
}
