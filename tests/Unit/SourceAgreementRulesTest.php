<?php

namespace Tests\Unit;

use App\Services\SourceAgreement;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Croisement des sources le long d'un tracé de référence (SourceAgreement),
 * règles pures :
 *   - classe de confiance d'un échantillon : high si la géométrie retenue est
 *     précise ET une autre source précise concorde à 5 m, medium si précise
 *     seule, low sinon ;
 *   - écart à la source indépendante la plus proche (précises d'abord) ;
 *   - lissage : une portion de moins de 4 échantillons est absorbée par sa
 *     voisine la plus longue.
 */
class SourceAgreementRulesTest extends TestCase
{
    private function call(string $method, mixed ...$args): mixed
    {
        return (new \ReflectionMethod(SourceAgreement::class, $method))->invoke(new SourceAgreement, ...$args);
    }

    /**
     * Échantillon SQL simulé : écart (m) à la trace la plus proche par famille.
     *
     * @param  array<string, float>  $nearest  famille → écart
     * @param  array<string, float>  $mine  famille → écart d'une trace rattachée à la ligne
     */
    private function sample(array $nearest, array $mine = []): object
    {
        $s = ['lon' => '2.35', 'lat' => '48.85'];
        foreach (SourceAgreement::FAMILIES as $i => $f) {
            $a = match ($f) {
                'archeoferroviaire' => 'archeo',
                'ign-historique' => 'ignh',
                default => $f,
            };
            $s["nd_{$a}"] = $nearest[$f] ?? null;
            $s["ni_{$a}"] = isset($nearest[$f]) ? (string) (100 + $i) : null;
            $s["nl_{$a}"] = isset($nearest[$f]) ? 7 : null;
            $s["nc_{$a}"] = isset($nearest[$f]) ? 'rail' : null;
            $s["md_{$a}"] = $mine[$f] ?? null;
            $s["mi_{$a}"] = isset($mine[$f]) ? (string) (200 + $i) : null;
        }

        return (object) $s;
    }

    public function test_precise_geometry_confirmed_by_another_precise_source_is_high(): void
    {
        $e = $this->call('evaluate', $this->sample(['bdtopo' => 3.0, 'sncf' => 40.0]), 'osm', 1);

        $this->assertSame('high', $e['class']);
        $this->assertSame(['bdtopo', 'sncf'], $e['agree']);
        $this->assertSame(3.0, $e['gap']);
        $this->assertSame(['d' => 3.0, 'id' => 101, 'line' => 7, 'cls' => 'rail'], $e['d']['bdtopo']);
        $this->assertSame(2.35, $e['lon']);
        $this->assertSame(48.85, $e['lat']);
    }

    public function test_precise_geometry_alone_is_medium_and_gap_prefers_precise_sources(): void
    {
        // BD TOPO à 7 m (> 5 m : pas de concordance), SNCF à 2 m (concorde, mais imprécise).
        $e = $this->call('evaluate', $this->sample(['bdtopo' => 7.0, 'sncf' => 2.0]), 'osm', 1);

        $this->assertSame('medium', $e['class']);
        $this->assertSame(['sncf'], $e['agree']);
        $this->assertSame(7.0, $e['gap'], 'l\'écart se mesure d\'abord aux sources précises');
    }

    public function test_gap_falls_back_to_imprecise_sources_and_own_family_is_ignored(): void
    {
        $e = $this->call('evaluate', $this->sample(['osm' => 0.5, 'ohm' => 12.0, 'sncf' => 60.0], ['ohm' => 30.0]), 'osm', 1);

        $this->assertSame('medium', $e['class'], 'la famille retenue ne se confirme pas elle-même');
        $this->assertArrayNotHasKey('osm', $e['d']);
        $this->assertSame([], $e['agree'], 'OHM à 12 m (> 10 m) et SNCF à 60 m (> 50 m)');
        $this->assertSame(12.0, $e['gap']);
        $this->assertSame(['ohm' => ['d' => 30.0, 'id' => 200 + (int) array_search('ohm', SourceAgreement::FAMILIES, true)]], $e['mine']);
    }

    public function test_ign_historique_drawing_is_medium_unless_a_precise_source_agrees(): void
    {
        // Voie extraite des photos IGN (~2-5 m) : jamais « high » seule, même confirmée par archeo.
        $alone = $this->call('evaluate', $this->sample(['archeoferroviaire' => 3.0]), 'ign-historique', 1);
        $this->assertSame('medium', $alone['class']);
        $this->assertSame(['archeoferroviaire'], $alone['agree']);

        $confirmed = $this->call('evaluate', $this->sample(['bdtopo' => 2.0]), 'ign-historique', 1);
        $this->assertSame('high', $confirmed['class']);
    }

    public function test_imprecise_geometry_is_always_low(): void
    {
        foreach (['archeoferroviaire', 'ohm', 'sncf'] as $own) {
            $e = $this->call('evaluate', $this->sample(['bdtopo' => 1.0, 'osm' => 1.0]), $own, 1);
            $this->assertSame('low', $e['class'], $own);
        }
        $this->assertNull($this->call('evaluate', $this->sample([]), 'osm', 1)['gap']);
    }

    /** @return array<string, array{0: list<string>, 1: list<string>}> */
    public static function smoothProvider(): array
    {
        $h = 'high';
        $m = 'medium';
        $l = 'low';

        return [
            'vide' => [[], []],
            'une seule portion, même courte' => [[$l, $l], [$l, $l]],
            'portion courte au milieu' => [[$h, $h, $h, $h, $l, $h, $h, $h, $h], array_fill(0, 9, $h)],
            'portion courte en tête' => [[$l, $l, $m, $m, $m, $m, $m], array_fill(0, 7, $m)],
            'portion courte en queue' => [[$m, $m, $m, $m, $h, $h, $h], array_fill(0, 7, $m)],
            'voisine la plus longue' => [[$h, $h, $h, $h, $h, $l, $m, $m, $m, $m], [$h, $h, $h, $h, $h, $h, $m, $m, $m, $m]],
            'à égalité : la précédente' => [[$h, $h, $h, $h, $l, $m, $m, $m, $m], [$h, $h, $h, $h, $h, $m, $m, $m, $m]],
            'que des portions courtes' => [[$h, $m, $l], [$m, $m, $m]],
            'quatre échantillons suffisent' => [[$h, $h, $h, $h, $l, $l, $l, $l], [$h, $h, $h, $h, $l, $l, $l, $l]],
        ];
    }

    /**
     * @param  list<string>  $classes
     * @param  list<string>  $expected
     */
    #[DataProvider('smoothProvider')]
    public function test_short_runs_are_absorbed_by_the_longest_neighbour(array $classes, array $expected): void
    {
        $this->assertSame($expected, $this->call('smooth', $classes));
    }
}
