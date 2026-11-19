<?php

namespace Tests\Unit;

use App\Services\StationTerminusOverrides;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Terminus relus à la main (database/data/station_terminus_overrides.php) : forme des décisions et fichier versionné. */
class StationTerminusOverridesTest extends TestCase
{
    private const SOURCE = 'https://fr.wikipedia.org/w/index.php?title=Gare_de_Toulouse-Roguet&oldid=240777604';

    /** @return list<array<string, mixed>> */
    private static function file(): array
    {
        return require dirname(__DIR__, 2).'/database/data/station_terminus_overrides.php';
    }

    /** @return iterable<string, array{array<string, mixed>, ?string, list<string>}> */
    public static function decisions(): iterable
    {
        $attach = ['line' => 'l', 'terminus' => 'Toulouse', 'decision' => 'attach', 'station' => 'toulouse-roguet', 'source_url' => self::SOURCE, 'reason' => 'tête de ligne'];
        $none = ['line' => 'l', 'terminus' => 'Toulouse', 'decision' => 'none', 'source_url' => null, 'reason' => 'terminus sur une place'];

        yield 'attach valide' => [$attach, 'Ligne de Toulouse à Cadours', []];
        yield 'none valide, sans source' => [$none, 'Ligne de Toulouse à Cadours', []];
        yield 'attach sans source' => [['source_url' => null] + $attach, null, ['attach : station (slug) et source_url']];
        yield 'attach sans gare' => [array_diff_key($attach, ['station' => 1]), null, ['attach : station (slug) et source_url']];
        yield 'none avec une gare' => [['station' => 'x'] + $none, null, ['none : pas de station']];
        yield 'décision inconnue' => [['decision' => 'maybe'] + $none, null, ['decision : attach ou none']];
        yield 'lien non https' => [['source_url' => 'http://exemple'] + $none, null, ['source_url : lien https ou null']];
        yield 'raison vide' => [['reason' => ' '] + $none, null, ['reason manquant']];
        yield 'tiret long' => [['reason' => "place \u{2014} gare"] + $none, null, ['reason : tiret long']];
        yield 'extrémité absente du nom' => [$attach, 'Ligne de Muret à Sabarat', ['terminus : « Toulouse » n\'est pas une extrémité de « Ligne de Muret à Sabarat »']];
        yield 'extrémité au nom normalisé' => [['terminus' => 'Saint-Vaast'] + $none, 'Ligne de Valognes à Saint-Vaast', []];
    }

    /**
     * @param  array<string, mixed>  $decision
     * @param  list<string>  $expected
     */
    #[DataProvider('decisions')]
    public function test_decisions_are_validated(array $decision, ?string $lineName, array $expected): void
    {
        $this->assertSame($expected, StationTerminusOverrides::validate($decision, $lineName));
    }

    public function test_source_reference_keeps_the_permalink_within_the_column(): void
    {
        $this->assertSame('relu:'.self::SOURCE, StationTerminusOverrides::sourceRef(['source_url' => self::SOURCE]));
        $this->assertSame(160, mb_strlen(StationTerminusOverrides::sourceRef(['source_url' => 'https://'.str_repeat('a', 200)])));
    }

    /** Les 42 cas relus le 19/11/2026 : tous valides, une seule décision par extrémité, chaque rattachement sourcé par un permalien. */
    public function test_the_versioned_file_is_valid(): void
    {
        $entries = self::file();
        $this->assertCount(42, $entries);
        $keys = [];
        foreach ($entries as $i => $e) {
            $this->assertSame([], StationTerminusOverrides::validate($e), "décision n° {$i} ({$e['line']})");
            $keys[] = $e['line'].'|'.StationTerminusOverrides::key($e['terminus']);
            if ($e['decision'] === 'attach') {
                $this->assertMatchesRegularExpression('#^https://fr\.wikipedia\.org/w/index\.php\?title=[^&]+&oldid=\d+$#', $e['source_url'], $e['line']);
            }
        }
        $this->assertSame($keys, array_values(array_unique($keys)), 'une décision par ligne et par extrémité');
        $this->assertCount(8, array_filter($entries, fn (array $e) => $e['decision'] === 'attach'));
    }

    public function test_constructor_takes_the_given_entries(): void
    {
        $this->assertSame([], (new StationTerminusOverrides([]))->all());
    }
}
