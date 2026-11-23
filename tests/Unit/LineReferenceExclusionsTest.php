<?php

namespace Tests\Unit;

use App\Services\LineReferenceExclusions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Retraits à la main des tracés de référence (database/data/line_reference_exclusions.php) : forme des entrées et fichier versionné. */
class LineReferenceExclusionsTest extends TestCase
{
    /** @return list<array<string, mixed>> */
    private static function file(): array
    {
        return require dirname(__DIR__, 2).'/database/data/line_reference_exclusions.php';
    }

    /** @return iterable<string, array{array<string, mixed>, list<string>}> */
    public static function entries(): iterable
    {
        $common = ['line' => '436000', 'reason' => 'voie de gare en double', 'source' => 'https://www.openstreetmap.org/way/1', 'reviewed_at' => '2026-11-23'];
        $trace = ['kind' => 'trace', 'source_name' => 'osm', 'source_ref' => '1'] + $common;
        $cut = ['near' => [-0.6672, 48.5866], 'radius_m' => 40] + $trace;
        $fragment = ['kind' => 'fragment', 'at' => [2.35763, 49.87951], 'max_m' => 70] + $common;

        yield 'voie entière' => [$trace, []];
        yield 'voie coupée autour d\'un point' => [$cut, []];
        yield 'fragment' => [$fragment, []];
        yield 'fragment, longueur décimale' => [['max_m' => 12.5] + $fragment, []];
        yield 'sorte inconnue' => [['kind' => 'portion'] + $trace, ['kind : « trace », « fragment » ou « shared »']];
        yield 'sorte absente' => [array_diff_key($trace, ['kind' => 1]), ['kind : « trace », « fragment » ou « shared »']];
        yield 'ligne absente' => [array_diff_key($trace, ['line' => 1]), ['line manquant']];
        yield 'raison vide' => [['reason' => ''] + $trace, ['reason manquant']];
        yield 'source absente' => [array_diff_key($fragment, ['source' => 1]), ['source manquant']];
        yield 'date impossible' => [['reviewed_at' => '2026-02-30'] + $trace, ['reviewed_at : date AAAA-MM-JJ']];
        yield 'tiret long' => [['reason' => "gare \u{2014} voie"] + $trace, ['reason : tiret long']];
        yield 'espaces autour' => [['line' => '436000 '] + $trace, ['line : espaces autour']];
        yield 'voie sans référence' => [array_diff_key($trace, ['source_ref' => 1]), ['source_ref manquant']];
        yield 'coupe sans rayon' => [array_diff_key($cut, ['radius_m' => 1]), ['radius_m : longueur de 1 à 500 m']];
        yield 'coupe sans point' => [array_diff_key($cut, ['near' => 1]), ['near : [lon, lat]']];
        yield 'rayon trop grand' => [['radius_m' => 900] + $cut, ['radius_m : longueur de 1 à 500 m']];
        yield 'point hors limites' => [['at' => [200, 49]] + $fragment, ['at : [lon, lat]']];
        yield 'point en texte' => [['at' => ['2.3', '49.8']] + $fragment, ['at : [lon, lat]']];
        yield 'fragment sans longueur' => [array_diff_key($fragment, ['max_m' => 1]), ['max_m : longueur de 1 à 500 m']];
        yield 'clé d\'une autre sorte' => [$fragment + ['source_ref' => '1'], ['clés inconnues : source_ref']];
        $shared = ['kind' => 'shared', 'over' => '805000', 'source_name' => 'archeoferroviaire', 'source_ref' => 'abc'] + $common;
        yield 'voie partagée' => [$shared, []];
        yield 'voie partagée dans un disque' => [['near' => [2.118, 48.7955], 'radius_m' => 2000] + $shared, []];
        yield 'voie partagée sans ligne porteuse' => [array_diff_key($shared, ['over' => 1]), ['over manquant']];
        yield 'voie partagée précise' => [['source_name' => 'osm'] + $shared, ['source_name : archeoferroviaire ou ohm']];
        yield 'voie partagée, disque trop grand' => [['near' => [2.118, 48.7955], 'radius_m' => 9000] + $shared, ['radius_m : longueur de 1 à 5000 m']];
        yield 'voie partagée, clé d\'un fragment' => [$shared + ['max_m' => 10], ['clés inconnues : max_m']];
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  list<string>  $expected
     */
    #[DataProvider('entries')]
    public function test_entries_are_validated(array $entry, array $expected): void
    {
        $this->assertSame($expected, LineReferenceExclusions::validate($entry));
    }

    /** Le fichier versionné : entrées valides, relues au plus tard aujourd'hui, une seule fois chacune. */
    public function test_the_versioned_file_is_valid(): void
    {
        $keys = [];
        foreach (self::file() as $i => $e) {
            $this->assertSame([], LineReferenceExclusions::validate($e), "retrait n° {$i} ({$e['line']})");
            $this->assertLessThanOrEqual(date('Y-m-d'), $e['reviewed_at'], "retrait n° {$i} : relecture dans le futur");
            $keys[] = json_encode([$e['line'], $e['source_ref'] ?? null, $e['near'] ?? $e['at'] ?? null]);
        }
        $this->assertNotSame([], $keys);
        $this->assertSame($keys, array_values(array_unique($keys)), 'un retrait une seule fois');
    }
}
