<?php

namespace Tests\Unit;

use App\Services\SectionResolver;
use App\Services\WikipediaLineParser;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Chemin d'une portion sur le tracé (SectionResolver::reversePieces) : parcourir une portion en
 * sens inverse renverse l'ordre des morceaux et échange le début et la fin de chacun, sans toucher
 * ni à leur longueur ni aux trous (raccords) ; deux inversions redonnent le chemin d'origine.
 */
class SectionResolverPiecesTest extends TestCase
{
    /** @param  list<array<string, mixed>>  $pieces */
    private function reverse(array $pieces): array
    {
        $resolver = new SectionResolver(new WikipediaLineParser);

        return (new ReflectionMethod($resolver, 'reversePieces'))->invoke($resolver, $pieces);
    }

    public function test_the_order_is_reversed_and_each_piece_is_walked_backwards(): void
    {
        $pieces = [
            ['part' => 1, 'f1' => 0.25, 'f2' => 1.0, 'len' => 750.0],
            ['part' => 2, 'f1' => 0.0, 'f2' => 0.5, 'len' => 400.0],
        ];

        $this->assertSame([
            ['part' => 2, 'f1' => 0.5, 'f2' => 0.0, 'len' => 400.0],
            ['part' => 1, 'f1' => 1.0, 'f2' => 0.25, 'len' => 750.0],
        ], $this->reverse($pieces));
    }

    public function test_gaps_keep_their_content_and_move_with_the_order(): void
    {
        $gap = ['gap' => true, 'len' => 12.0];
        $pieces = [
            ['part' => 1, 'f1' => 0.0, 'f2' => 1.0, 'len' => 100.0],
            $gap,
            ['part' => 2, 'f1' => 0.0, 'f2' => 1.0, 'len' => 200.0],
        ];

        $reversed = $this->reverse($pieces);

        $this->assertSame($gap, $reversed[1]);
        $this->assertSame(2, $reversed[0]['part']);
        $this->assertSame(1, $reversed[2]['part']);
    }

    public function test_reversing_twice_gives_the_original_path(): void
    {
        $pieces = [
            ['part' => 3, 'f1' => 0.1, 'f2' => 0.9, 'len' => 321.0],
            ['gap' => true, 'len' => 5.0],
            ['part' => 4, 'f1' => 1.0, 'f2' => 0.3, 'len' => 99.0],
        ];

        $this->assertSame($pieces, $this->reverse($this->reverse($pieces)));
    }

    public function test_the_total_length_is_unchanged(): void
    {
        $pieces = [['part' => 1, 'f1' => 0.0, 'f2' => 1.0, 'len' => 100.0], ['gap' => true, 'len' => 7.0], ['part' => 2, 'f1' => 0.0, 'f2' => 1.0, 'len' => 50.0]];

        $this->assertSame(157.0, array_sum(array_column($this->reverse($pieces), 'len')));
    }

    public function test_an_empty_path_stays_empty(): void
    {
        $this->assertSame([], $this->reverse([]));
    }
}
