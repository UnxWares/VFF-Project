<?php

namespace Tests\Unit\Mcp;

use App\Mcp\Support\ResponseBudget;
use PHPUnit\Framework\TestCase;

class ResponseBudgetTest extends TestCase
{
    public function test_small_payload_is_untouched(): void
    {
        $payload = ['events' => [1, 2, 3]];

        $this->assertSame($payload, ResponseBudget::fit($payload, ['events'], 1000));
    }

    public function test_longest_list_is_shortened_from_the_end_and_reported(): void
    {
        $payload = ['line' => ['events' => range(1, 400), 'phases' => range(1, 10)], 'name' => 'x'];

        $fit = ResponseBudget::fit($payload, ['line.events', 'line.phases'], 600);

        $this->assertLessThanOrEqual(600, ResponseBudget::size(array_diff_key($fit, ['truncated' => true])));
        $this->assertSame(range(1, count($fit['line']['events'])), $fit['line']['events'], 'début de la liste conservé');
        $this->assertSame(400 - count($fit['line']['events']), $fit['truncated']['line.events']);
        $this->assertSame(range(1, 10), $fit['line']['phases'], 'liste courte gardée tant que la longue suffit');
    }

    public function test_stops_when_nothing_is_left_to_shorten(): void
    {
        $fit = ResponseBudget::fit(['text' => str_repeat('a', 500), 'events' => [1]], ['events'], 100);

        $this->assertSame([1], $fit['events']);
        $this->assertArrayNotHasKey('truncated', $fit);
    }
}
