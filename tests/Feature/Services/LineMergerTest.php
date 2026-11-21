<?php

namespace Tests\Feature\Services;

use App\Models\Line;
use App\Services\LineMerger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Fusion de deux lignes (App\Services\LineMerger) : tracés, rattachements,
 * événements (sans doublon, portions à recalculer), références externes,
 * médias, mémoire de la fusion ; déplacement d'événements seuls.
 */
class LineMergerTest extends TestCase
{
    use RefreshDatabase;

    private LineMerger $merger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->merger = app(LineMerger::class);
    }

    private function line(string $name): Line
    {
        return Line::factory()->create(['name' => $name, 'slug' => Str::slug($name), 'code' => null, 'type' => null, 'alt_names' => null]);
    }

    private function trace(string $ref, ?int $lineId, ?string $method): int
    {
        return (int) DB::selectOne("INSERT INTO source_traces (source, source_ref, layer, line_id, match_method, length_m, seen_at, geom)
            VALUES ('ohm', ?, 'rail', ?, ?, 1000, now(), ST_Multi(ST_GeomFromText('LINESTRING(1 46, 1.01 46)', 4326))) RETURNING id", [$ref, $lineId, $method])->id;
    }

    /** @param  array<string, mixed>  $attributes */
    private function event(Line $line, array $attributes = []): int
    {
        return (int) DB::table('line_events')->insertGetId($attributes + [
            'line_id' => $line->id, 'type' => 'opening', 'event_date' => '1880-06-01', 'description' => 'Ouverture', 'source' => 'lignes-oubliees',
            'confidence' => 'high', 'date_precision' => 'day', 'section_resolution' => 'places', 'section_from' => 'A', 'section_to' => 'B',
            'section_length_m' => 1234, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_merge_moves_everything_and_remembers_the_merged_line(): void
    {
        $from = $this->line('Ligne de Dol à Dinan');
        $into = $this->line('Ligne de Dol à Lamballe');
        $kept = $this->trace('t1', $from->id, 'name');
        $overlap = $this->trace('t2', $from->id, 'trace_overlap');
        $shared = $this->trace('t3', $into->id, 'code');
        DB::table('source_trace_lines')->insert([
            ['source_trace_id' => $shared, 'line_id' => $from->id, 'role' => 'secondary', 'method' => 'sncf_overlap', 'created_at' => now(), 'updated_at' => now()],
            ['source_trace_id' => $shared, 'line_id' => $into->id, 'role' => 'primary', 'method' => 'code', 'created_at' => now(), 'updated_at' => now()],
            ['source_trace_id' => $kept, 'line_id' => $from->id, 'role' => 'primary', 'method' => 'name', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $moved = $this->event($from);
        $this->event($from, ['type' => 'closure', 'event_date' => '1950-01-01']);
        $this->event($into, ['type' => 'closure', 'event_date' => '1950-01-01']);
        DB::table('wikidata_lines')->insert(['qid' => 'Q1', 'category' => 'ferroviaire', 'line_id' => $from->id, 'fetched_at' => now()]);
        DB::table('source_stations')->insert(['source' => 'osm', 'source_ref' => 'n1', 'line_id' => $from->id, 'name' => 'Dinan']);
        DB::table('archeo_lines')->insert(['article_id' => 1, 'name' => 'x', 'rubrique' => 'r', 'url' => 'u', 'matched_line_id' => $from->id]);
        DB::table('line_sources')->insert([
            ['line_id' => $from->id, 'source_type' => 'wikipedia', 'source_url' => 'a', 'fetched_at' => now()],
            ['line_id' => $from->id, 'source_type' => 'osm', 'source_url' => 'b', 'fetched_at' => now()],
            ['line_id' => $into->id, 'source_type' => 'wikipedia', 'source_url' => 'c', 'fetched_at' => now()],
        ]);
        foreach ([[$from->id, 'p1'], [$from->id, 'p2'], [$into->id, 'p2']] as [$owner, $ext]) {
            DB::table('media')->insert(['mediable_type' => 'App\\Models\\Line', 'mediable_id' => $owner, 'type' => 'photo', 'provider' => 'commons',
                'external_id' => $ext, 'rights_status' => 'open_license', 'is_featured' => false]);
        }

        $r = $this->merger->merge($from->id, $into->id, 'merged_section', 'section de la ligne');

        $this->assertSame(['traces' => 2, 'events_moved' => 1, 'events_dropped' => 1], $r);
        $this->assertDatabaseMissing('lines', ['id' => $from->id]);
        $this->assertDatabaseHas('source_traces', ['id' => $kept, 'line_id' => $into->id, 'match_method' => 'merged_section']);
        $this->assertDatabaseHas('source_traces', ['id' => $overlap, 'line_id' => $into->id, 'match_method' => 'trace_overlap']);
        $this->assertSame(2, DB::table('source_trace_lines')->where('line_id', $into->id)->count(), 'rattachement en double retiré');
        $this->assertSame('primary', DB::table('source_trace_lines')->where('source_trace_id', $shared)->value('role'));
        $this->assertDatabaseHas('line_events', ['id' => $moved, 'line_id' => $into->id, 'section_resolution' => 'pending', 'section_length_m' => null]);
        $this->assertSame(1, DB::table('line_events')->where('line_id', $into->id)->where('type', 'closure')->count());
        $this->assertDatabaseHas('wikidata_lines', ['qid' => 'Q1', 'line_id' => $into->id]);
        $this->assertDatabaseHas('source_stations', ['source_ref' => 'n1', 'line_id' => $into->id]);
        $this->assertDatabaseHas('archeo_lines', ['matched_line_id' => $into->id]);
        $this->assertSame(['c', 'b'], DB::table('line_sources')->where('line_id', $into->id)->orderBy('source_type', 'desc')->pluck('source_url')->all());
        $this->assertSame(['p1', 'p2'], DB::table('media')->where('mediable_id', $into->id)->orderBy('external_id')->pluck('external_id')->all());
        $this->assertSame(0, DB::table('media')->where('mediable_id', $from->id)->count());
        $alt = json_decode(DB::table('lines')->where('id', $into->id)->value('alt_names'), true);
        $this->assertSame([['name' => 'Ligne de Dol à Dinan', 'slug' => 'ligne-de-dol-a-dinan', 'reason' => 'section de la ligne']], $alt['merged']);

        // Seconde fusion : la mémoire s'allonge.
        $other = $this->line('Ligne de Dinan à Lamballe');
        $this->merger->merge($other->id, $into->id, null, 'doublon');
        $alt = json_decode(DB::table('lines')->where('id', $into->id)->value('alt_names'), true);
        $this->assertSame(['Ligne de Dol à Dinan', 'Ligne de Dinan à Lamballe'], array_column($alt['merged'], 'name'));
    }

    public function test_merge_without_method_keeps_trace_methods(): void
    {
        $from = $this->line('A');
        $into = $this->line('B');
        $t = $this->trace('t1', $from->id, 'name');

        $this->assertSame(['traces' => 1, 'events_moved' => 0, 'events_dropped' => 0], $this->merger->merge($from->id, $into->id, null, 'x'));
        $this->assertDatabaseHas('source_traces', ['id' => $t, 'line_id' => $into->id, 'match_method' => 'name']);
    }

    public function test_merge_refuses_self_unknown_or_missing_lines(): void
    {
        $a = $this->line('A');
        $zero = ['traces' => 0, 'events_moved' => 0, 'events_dropped' => 0];

        $this->assertSame($zero, $this->merger->merge($a->id, $a->id, null, 'x'));
        $this->assertSame($zero, $this->merger->merge($a->id, 999999, null, 'x'));
        $this->assertSame($zero, $this->merger->merge(999999, $a->id, null, 'x'));
        $this->assertDatabaseHas('lines', ['id' => $a->id]);
    }

    public function test_move_events_drops_duplicates_and_resets_portions(): void
    {
        $from = $this->line('A');
        $into = $this->line('B');
        // Wikipédia : même empreinte déjà présente sur la cible → supprimé.
        $wp = ['source' => 'wikipedia', 'section_from' => 'X', 'section_to' => 'Y'];
        $dupWp = $this->event($from, $wp);
        $target = $this->event($into, $wp);
        DB::table('line_events')->where('id', $target)->update(['fingerprint' => $this->merger->wikipediaFingerprint($into->id, DB::table('line_events')->find($target))]);
        // Wikipédia nouveau sur la cible : déplacé, empreinte recalculée ; « unresolved » le reste.
        $newWp = $this->event($from, $wp + ['event_date' => '1890-01-01', 'section_resolution' => 'unresolved', 'section_resolution_note' => 'Portion non précisée']);
        // Autre source : portion recalculée sauf si posée depuis un tracé.
        $traced = $this->event($from, ['source' => 'ohm', 'event_date' => '1900-01-01', 'source_trace_id' => $this->trace('t9', null, null)]);
        $plain = $this->event($from, ['source' => 'ohm', 'event_date' => '1901-01-01']);
        // Équivalent hors Wikipédia (même source, type, date, précision, portion) → supprimé.
        $dup = $this->event($from, ['event_date' => '1902-01-01', 'section_from' => 'Gare de A']);
        $this->event($into, ['event_date' => '1902-01-01', 'section_from' => 'gare de a']);
        // Portion différente : pas un doublon.
        $other = $this->event($from, ['event_date' => '1903-01-01', 'section_pk_start_m' => 1000]);
        $this->event($into, ['event_date' => '1903-01-01', 'section_pk_start_m' => 2000]);
        // Déjà sur la cible : ignoré.
        $already = $this->event($into, ['event_date' => '1904-01-01']);

        $r = $this->merger->moveEvents([$dupWp, $newWp, $traced, $plain, $dup, $other, $already], $into->id);

        $this->assertSame(['events_moved' => 4, 'events_dropped' => 2], $r);
        $this->assertDatabaseMissing('line_events', ['id' => $dupWp]);
        $this->assertDatabaseMissing('line_events', ['id' => $dup]);
        /** @var \stdClass $e */
        $e = DB::table('line_events')->find($newWp);
        $this->assertSame([$into->id, 'unresolved', 'Portion non précisée'], [$e->line_id, $e->section_resolution, $e->section_resolution_note]);
        $this->assertSame($this->merger->wikipediaFingerprint($into->id, $e), $e->fingerprint);
        $this->assertDatabaseHas('line_events', ['id' => $traced, 'line_id' => $into->id, 'section_resolution' => 'places', 'section_length_m' => 1234]);
        $this->assertDatabaseHas('line_events', ['id' => $plain, 'line_id' => $into->id, 'section_resolution' => 'pending', 'section_length_m' => null]);
        $this->assertDatabaseHas('line_events', ['id' => $other, 'line_id' => $into->id]);
        $this->assertDatabaseHas('line_events', ['id' => $already, 'line_id' => $into->id]);
    }

    public function test_wikipedia_title_of_an_url(): void
    {
        $this->assertNull(LineMerger::wikipediaTitle(null));
        $this->assertNull(LineMerger::wikipediaTitle(''));
        $this->assertNull(LineMerger::wikipediaTitle('https://example.org/page'));
        $this->assertSame('Ligne de Dol à Dinan', LineMerger::wikipediaTitle('https://fr.wikipedia.org/wiki/Ligne_de_Dol_%C3%A0_Dinan#Histoire'));
        $this->assertSame('Ligne de Dol à Dinan', LineMerger::wikipediaTitle('https://fr.wikipedia.org/w/index.php?title=Ligne_de_Dol_%C3%A0_Dinan&oldid=12'));
    }
}
