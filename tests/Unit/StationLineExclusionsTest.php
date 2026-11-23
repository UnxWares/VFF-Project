<?php

namespace Tests\Unit;

use App\Services\StationLineExclusions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Liens gare - ligne écartés à la main (database/data/station_line_exclusions.php) : forme des entrées et fichier versionné. */
class StationLineExclusionsTest extends TestCase
{
    private const SOURCE = 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Moret_-_Veneux-les-Sablons_%C3%A0_Lyon-Perrache&oldid=1';

    /** @return list<array<string, mixed>> */
    private static function file(): array
    {
        return require dirname(__DIR__, 2).'/database/data/station_line_exclusions.php';
    }

    /** @return iterable<string, array{array<string, mixed>, list<string>}> */
    public static function entries(): iterable
    {
        $ok = ['station' => 'fontainebleau-foret', 'line' => 'rfn-750000', 'reason' => 'halte au nord de la ligne', 'source_url' => self::SOURCE, 'reviewed_at' => '2026-11-22'];

        yield 'valide, gare par slug et ligne par slug' => [$ok, []];
        yield 'valide, gare par élément Wikidata et ligne par code' => [['station' => 'Q2260089', 'line' => '750000'] + $ok, []];
        yield 'gare absente' => [array_diff_key($ok, ['station' => 1]), ['station manquant']];
        yield 'ligne vide' => [['line' => ''] + $ok, ['line manquant']];
        yield 'raison absente' => [array_diff_key($ok, ['reason' => 1]), ['reason manquant']];
        yield 'source absente' => [array_diff_key($ok, ['source_url' => 1]), ['source_url manquant']];
        yield 'source non https' => [['source_url' => 'http://exemple.org/x'] + $ok, ['source_url : lien https']];
        yield 'relecture absente' => [array_diff_key($ok, ['reviewed_at' => 1]), ['reviewed_at manquant']];
        yield 'date au mauvais format' => [['reviewed_at' => '22/11/2026'] + $ok, ['reviewed_at : date AAAA-MM-JJ']];
        yield 'date impossible' => [['reviewed_at' => '2026-02-30'] + $ok, ['reviewed_at : date AAAA-MM-JJ']];
        yield 'tiret long' => [['reason' => "nord \u{2014} sud"] + $ok, ['reason : tiret long']];
        yield 'double tiret' => [['reason' => 'nord -- sud'] + $ok, ['reason : tiret long']];
        yield 'espaces autour de la gare' => [['station' => ' fontainebleau-foret'] + $ok, ['station : espaces autour']];
        yield 'clé inconnue' => [$ok + ['method' => 'trace'], ['clés inconnues : method']];
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  list<string>  $expected
     */
    #[DataProvider('entries')]
    public function test_entries_are_validated(array $entry, array $expected): void
    {
        $this->assertSame($expected, StationLineExclusions::validate($entry));
    }

    /** Le fichier versionné : entrées valides, un seul couple par gare et ligne, relues au plus tard aujourd'hui. */
    public function test_the_versioned_file_is_valid(): void
    {
        $keys = [];
        foreach (self::file() as $i => $e) {
            $this->assertSame([], StationLineExclusions::validate($e), "exclusion n° {$i} (".($e['station'] ?? '?').' sur '.($e['line'] ?? '?').')');
            $this->assertLessThanOrEqual(date('Y-m-d'), $e['reviewed_at'], "exclusion n° {$i} : relecture dans le futur");
            $keys[] = $e['station'].'|'.$e['line'];
        }
        $this->assertSame($keys, array_values(array_unique($keys)), 'un couple gare - ligne une seule fois');
    }
}
