<?php

namespace Tests\Unit;

use App\Services\TraceConflation;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Appariement d'un tracé grossier à des voies précises (TraceConflation::absorbShortDivergences) :
 * une portion « tracé d'origine gardé » (état 0) coincée entre deux portions précises, plus courte que
 * 2,5 fois la somme des écarts aux deux bouts, ne laisserait que deux raccords en zigzag. On reste
 * alors sur la voie précise voisine, à condition qu'elle couvre toute la portion à moins de jump_max.
 */
class TraceConflationShortDivergenceTest extends TestCase
{
    private const CHUNK_M = 10.0;

    private const JUMP_MAX_M = 20.0;

    /**
     * Morceaux de 10 m. $reach : pour chaque morceau, l'écart (m) à la voie 1 et à la voie 2 ; un morceau
     * sans voie à portée n'a pas de candidat. Les raccords (px/py du précédent, qx/qy du suivant) sont
     * posés à $jump mètres du début de la portion gardée.
     *
     * @param  array<int, array{1?: float, 2?: float}>  $reach
     * @return list<array<string, mixed>>
     */
    private function chunks(array $reach, float $jump): array
    {
        $chunks = [];
        foreach ($reach as $i => $byTrace) {
            $cands = [];
            foreach ($byTrace as $trace => $distance) {
                $cands[$trace] = ['d' => $distance, 'px' => $jump, 'py' => 0.0, 'qx' => $jump, 'qy' => 0.0];
            }
            $chunks[$i] = ['sx' => 0.0, 'sy' => 0.0, 'cands' => $cands];
        }

        return $chunks;
    }

    /**
     * @param  list<array{s: int, i0: int, i1: int}>  $runs
     * @param  list<int>  $states
     * @param  list<array<string, mixed>>  $chunks
     * @return list<int>
     */
    private function absorb(array $runs, array $states, array $chunks): array
    {
        $conflation = (new ReflectionClass(TraceConflation::class))->newInstanceWithoutConstructor();

        return (new ReflectionMethod($conflation, 'absorbShortDivergences'))
            ->invoke($conflation, $runs, $states, $chunks, ['clen' => self::CHUNK_M, 'jump_max' => self::JUMP_MAX_M]);
    }

    /** Tracé 0..9 : voie 1, puis deux morceaux gardés (4 et 5), puis voie 2. */
    private const RUNS = [
        ['s' => 1, 'i0' => 0, 'i1' => 3],
        ['s' => 0, 'i0' => 4, 'i1' => 5],
        ['s' => 2, 'i0' => 6, 'i1' => 9],
    ];

    private const STATES = [1, 1, 1, 1, 0, 0, 2, 2, 2, 2];

    public function test_a_short_kept_portion_follows_the_neighbouring_precise_track(): void
    {
        // Raccords de 10 m aux deux bouts : 2,5 × 20 = 50 m > 20 m de portion.
        $reach = array_fill(0, 10, [1 => 5.0, 2 => 5.0]);

        $states = $this->absorb(self::RUNS, self::STATES, $this->chunks($reach, 10.0));

        $this->assertSame([1, 1, 1, 1, 1, 1, 2, 2, 2, 2], $states);
    }

    public function test_the_portion_is_split_between_both_neighbours_when_each_covers_a_part(): void
    {
        $reach = array_fill(0, 10, [1 => 5.0, 2 => 5.0]);
        $reach[4] = [1 => 5.0, 2 => 99.0];
        $reach[5] = [1 => 99.0, 2 => 5.0];

        $states = $this->absorb(self::RUNS, self::STATES, $this->chunks($reach, 10.0));

        $this->assertSame([1, 1, 1, 1, 1, 2, 2, 2, 2, 2], $states);
    }

    public function test_nothing_changes_when_a_morsel_is_out_of_reach_of_both_tracks(): void
    {
        $reach = array_fill(0, 10, [1 => 5.0, 2 => 5.0]);
        $reach[5] = [1 => 99.0, 2 => 99.0];

        $states = $this->absorb(self::RUNS, self::STATES, $this->chunks($reach, 10.0));

        $this->assertSame(self::STATES, $states, 'pas de remplissage partiel');
    }

    public function test_a_long_kept_portion_is_a_real_divergence(): void
    {
        // Portion de 100 m (10 morceaux) pour des raccords de 5 m : 100 >= 2,5 × 10.
        $runs = [
            ['s' => 1, 'i0' => 0, 'i1' => 1],
            ['s' => 0, 'i0' => 2, 'i1' => 11],
            ['s' => 2, 'i0' => 12, 'i1' => 13],
        ];
        $states = [1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 2, 2];
        $reach = array_fill(0, 14, [1 => 5.0, 2 => 5.0]);

        $this->assertSame($states, $this->absorb($runs, $states, $this->chunks($reach, 5.0)));
    }

    public function test_only_a_kept_portion_between_two_precise_ones_is_considered(): void
    {
        $reach = array_fill(0, 10, [1 => 5.0, 2 => 5.0]);
        $chunks = $this->chunks($reach, 10.0);

        // Portion précise coincée entre deux portions gardées : rien à absorber.
        $runs = [['s' => 0, 'i0' => 0, 'i1' => 3], ['s' => 1, 'i0' => 4, 'i1' => 5], ['s' => 0, 'i0' => 6, 'i1' => 9]];
        $states = [0, 0, 0, 0, 1, 1, 0, 0, 0, 0];
        $this->assertSame($states, $this->absorb($runs, $states, $chunks));

        // Portion gardée en tête ou en queue (un seul voisin) : jamais absorbée.
        $runs = [['s' => 0, 'i0' => 0, 'i1' => 1], ['s' => 1, 'i0' => 2, 'i1' => 9]];
        $states = [0, 0, 1, 1, 1, 1, 1, 1, 1, 1];
        $this->assertSame($states, $this->absorb($runs, $states, $chunks));
    }

    public function test_a_single_run_is_left_alone(): void
    {
        $states = array_fill(0, 4, 1);

        $this->assertSame($states, $this->absorb([['s' => 1, 'i0' => 0, 'i1' => 3]], $states, $this->chunks(array_fill(0, 4, [1 => 1.0]), 1.0)));
    }
}
