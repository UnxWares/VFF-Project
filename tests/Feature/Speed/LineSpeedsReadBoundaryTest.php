<?php

namespace Tests\Feature\Speed;

use App\Models\Era;
use App\Models\Line;
use App\Services\Speed\LineSpeeds;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Lecture des vitesses d'une ligne : ères, tranches voisines réunies, confiance publiée, ordre des faits, extrait,
 * portée « section », absence de données.
 */
class LineSpeedsReadBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private Line $line;

    private Era $era;

    protected function setUp(): void
    {
        parent::setUp();
        $this->era = Era::factory()->current()->create(['year' => 2030]);
        $this->line = Line::factory()->create();
    }

    private function range(int $from, int $to, int $kmh, ?Line $line = null, ?Era $era = null): void
    {
        DB::table('line_speed_ranges')->insert(['line_id' => ($line ?? $this->line)->id, 'era_id' => ($era ?? $this->era)->id,
            'pk_start_m' => $from, 'pk_end_m' => $to, 'max_speed_kmh' => $kmh, 'source' => 'sncf', 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @param  array<string, mixed>  $over */
    private function event(string $date, string $confidence, array $over = []): int
    {
        static $n = 0;
        $n++;

        return (int) DB::table('line_speed_events')->insertGetId($over + ['line_id' => $this->line->id, 'event_date' => $date, 'date_precision' => 'day',
            'kind' => 'raise', 'speed_kmh' => 140, 'previous_speed_kmh' => null, 'scope' => 'whole_line', 'confidence' => $confidence,
            'source' => 'wikipedia', 'source_title' => 'Ligne X', 'source_revision' => 123, 'source_url' => 'https://fr.wikipedia.org/w/index.php?oldid=123',
            'source_excerpt' => 'Un extrait.', 'fingerprint' => sha1("f{$n}"), 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_the_tables_are_available_and_the_answer_is_cached(): void
    {
        $this->assertTrue(LineSpeeds::available());
        $this->assertTrue(LineSpeeds::available());
    }

    public function test_ranges_are_those_of_the_line_and_the_era_only_and_in_pk_order(): void
    {
        $other = Line::factory()->create();
        $past = Era::factory()->create(['year' => 1960]);
        $this->range(5000, 8000, 80);
        $this->range(0, 5000, 160);
        $this->range(0, 9000, 40, $other);
        $this->range(0, 9000, 40, null, $past);

        $ranges = app(LineSpeeds::class)->ranges($this->line->id, $this->era->id);

        $this->assertSame([[0, 5000, 160], [5000, 8000, 80]], array_map(fn (array $r): array => [$r['pk_start_m'], $r['pk_end_m'], $r['max_speed_kmh']], $ranges));
        $this->assertSame([5000, 3000], array_column($ranges, 'length_m'));
        $this->assertSame([[0, 9000, 40]], array_map(fn (array $r): array => [$r['pk_start_m'], $r['pk_end_m'], $r['max_speed_kmh']], app(LineSpeeds::class)->ranges($this->line->id, $past->id)));
        $this->assertSame([], app(LineSpeeds::class)->ranges($this->line->id, Era::factory()->create(['year' => 1970])->id));
        $this->assertSame([], app(LineSpeeds::class)->ranges(0, $this->era->id));
    }

    public function test_overlapping_ranges_come_in_order_of_their_start_then_their_end(): void
    {
        $this->range(1000, 2000, 80);
        $this->range(0, 9000, 40);
        $this->range(1000, 1500, 60);

        $ranges = app(LineSpeeds::class)->ranges($this->line->id, $this->era->id);

        $this->assertSame([[0, 9000], [1000, 1500], [1000, 2000]], array_map(fn (array $r): array => [$r['pk_start_m'], $r['pk_end_m']], $ranges));
    }

    public function test_neighbouring_ranges_of_the_same_speed_are_merged_with_their_labels(): void
    {
        $this->range(0, 1000, 100);
        $this->range(1050, 3000, 100);
        $this->range(3051, 4000, 100);

        $ranges = app(LineSpeeds::class)->ranges($this->line->id, $this->era->id);

        $this->assertCount(2, $ranges);
        $this->assertSame([0, 3000, 3000], [$ranges[0]['pk_start_m'], $ranges[0]['pk_end_m'], $ranges[0]['length_m']]);
        $this->assertSame([3051, 4000], [$ranges[1]['pk_start_m'], $ranges[1]['pk_end_m']]);
        $this->assertNotSame('', $ranges[1]['pk_start_label']);
        $this->assertNotSame($ranges[0]['pk_start_label'], $ranges[1]['pk_start_label']);
    }

    public function test_only_high_and_medium_facts_are_served_oldest_first_then_by_id(): void
    {
        $this->event('1995-05-05', 'low');
        $this->event('2010-01-01', 'high');
        $this->event('2000-01-01', 'medium');
        $this->event('2010-01-01', 'medium');
        $this->event('2005-01-01', 'other');

        $history = app(LineSpeeds::class)->history($this->line->id);

        $this->assertSame(['2000-01-01', '2010-01-01', '2010-01-01'], array_column($history, 'date'));
        $this->assertSame(['medium', 'high', 'medium'], array_column($history, 'reliability'));
    }

    public function test_the_excerpt_is_dropped_on_request_and_the_section_only_for_section_scope(): void
    {
        $this->event('2001-01-01', 'high', ['scope' => 'section', 'section_from' => 'A', 'section_to' => 'B', 'pk_start_m' => 100, 'pk_end_m' => 900, 'previous_speed_kmh' => 80]);
        $this->event('2002-01-01', 'high', ['scope' => 'whole_line', 'section_from' => 'A', 'section_to' => 'B']);
        $this->event('2003-01-01', 'high', ['scope' => 'section', 'section_from' => 'A', 'section_to' => null]);

        $with = app(LineSpeeds::class)->history($this->line->id);
        $without = app(LineSpeeds::class)->history($this->line->id, false);

        $this->assertSame(['from' => 'A', 'to' => 'B', 'pk_start_m' => 100, 'pk_end_m' => 900], $with[0]['section']);
        $this->assertSame(80, $with[0]['previous_speed_kmh']);
        $this->assertNull($with[1]['section']);
        $this->assertNull($with[1]['previous_speed_kmh']);
        $this->assertSame(['from' => 'A', 'to' => null, 'pk_start_m' => null, 'pk_end_m' => null], $with[2]['section']);
        $this->assertSame('Un extrait.', $with[0]['source']['excerpt']);
        $this->assertNull($without[0]['source']['excerpt']);
        $this->assertSame(123, $with[0]['source']['revision']);
    }

    public function test_the_history_of_another_line_is_not_mixed_in(): void
    {
        $other = Line::factory()->create();
        $this->event('2001-01-01', 'high', ['line_id' => $other->id]);

        $this->assertSame([], app(LineSpeeds::class)->history($this->line->id));
    }

    public function test_for_line_carries_the_era_year_the_summary_and_the_provenance(): void
    {
        $this->range(0, 1000, 120);
        $this->event('2001-01-01', 'high');

        $all = app(LineSpeeds::class)->forLine($this->line->id, $this->era->id, 2030, false);

        $this->assertSame(2030, $all['era_year']);
        $this->assertSame(120, $all['summary']['max_speed_kmh']);
        $this->assertNull($all['history'][0]['source']['excerpt']);
        $this->assertSame('SNCF Réseau', $all['provenance']['ranges']['provider']);
    }

    public function test_a_line_without_data_gives_empty_lists_and_a_null_summary(): void
    {
        $all = app(LineSpeeds::class)->forLine($this->line->id, $this->era->id, 2030);

        $this->assertSame([], $all['ranges']);
        $this->assertSame([], $all['history']);
        $this->assertNull($all['summary']['max_speed_kmh']);
        $this->assertSame(0, $all['summary']['covered_m']);
    }

    public function test_without_the_tables_everything_is_empty_without_error_and_the_check_is_redone(): void
    {
        $this->range(0, 1000, 120);
        $reset = function (): void {
            (new \ReflectionProperty(LineSpeeds::class, 'ready'))->setValue(null, null);
            Cache::flush();
        };
        $reset();
        try {
            Schema::drop('line_speed_events');
            Schema::drop('line_speed_ranges');

            $this->assertFalse(LineSpeeds::available());
            $this->assertFalse(LineSpeeds::available());
            $this->assertSame([], app(LineSpeeds::class)->ranges($this->line->id, $this->era->id));
            $this->assertSame([], app(LineSpeeds::class)->history($this->line->id));
            $this->assertSame([], app(LineSpeeds::class)->forLine($this->line->id, $this->era->id, 2030)['ranges']);
        } finally {
            $reset();
        }
    }
}
