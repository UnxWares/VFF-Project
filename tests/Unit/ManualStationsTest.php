<?php

namespace Tests\Unit;

use App\Services\ManualStations;
use App\Services\StationTerminusOverrides;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Gares saisies à la main (database/data/gares_manuelles.php) : forme des entrées et fichier versionné. */
class ManualStationsTest extends TestCase
{
    /** @return list<array<string, mixed>> */
    private static function file(): array
    {
        return require dirname(__DIR__, 2).'/database/data/gares_manuelles.php';
    }

    /** @return array<string, mixed> */
    private static function entry(): array
    {
        return [
            'ref' => 'exemple-ancienne-gare', 'name' => 'Exemple (ancienne gare)', 'lat' => 50.7, 'lon' => 1.6,
            'opened_on' => '1855-01-01', 'opened_precision' => 'year', 'closed_on' => '1962-01-01', 'closed_precision' => 'year',
            'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Gare&oldid=1', 'quote' => 'construite en 1855', 'reason' => 'absente de la base',
        ];
    }

    /** @return iterable<string, array{array<string, mixed>, list<string>}> */
    public static function entries(): iterable
    {
        yield 'valide' => [self::entry(), []];
        yield 'sans dates' => [array_diff_key(self::entry(), array_flip(['opened_on', 'opened_precision', 'closed_on', 'closed_precision'])), []];
        yield 'ref non slug' => [['ref' => 'Exemple'] + self::entry(), ['ref : slug minuscule']];
        yield 'nom manquant' => [['name' => ' '] + self::entry(), ['name manquant']];
        yield 'source non https' => [['source_url' => 'http://exemple'] + self::entry(), ['source_url : lien https']];
        yield 'latitude et longitude permutées' => [['lat' => 1.6, 'lon' => 50.7] + self::entry(), ['lat, lon : coordonnées WGS 84 de France métropolitaine']];
        yield 'date impossible' => [['opened_on' => '1855-02-30', 'opened_precision' => 'day'] + self::entry(), ['opened_on : date AAAA-MM-JJ']];
        yield 'précision inconnue' => [['closed_precision' => 'decade'] + self::entry(), ['closed_precision : day, month ou year']];
        yield 'fermeture avant ouverture' => [['closed_on' => '1800-01-01'] + self::entry(), ['fermeture avant l\'ouverture']];
        yield 'tiret long' => [['reason' => "absente \u{2014} gare"] + self::entry(), ['reason : tiret long']];
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  list<string>  $expected
     */
    #[DataProvider('entries')]
    public function test_entries_are_validated(array $entry, array $expected): void
    {
        $this->assertSame($expected, ManualStations::validate($entry));
    }

    public function test_records_keep_valid_entries_and_report_the_others(): void
    {
        $bad = ['ref' => 'mauvaise', 'lat' => 0] + self::entry();
        $result = (new ManualStations([self::entry(), $bad, self::entry()]))->records();

        $this->assertCount(1, $result['records']);
        $this->assertSame('exemple-ancienne-gare', $result['records'][0]['source_ref']);
        $this->assertSame('historic', $result['records'][0]['lifecycle']);
        $this->assertSame(['https://fr.wikipedia.org/w/index.php?title=Gare&oldid=1', 'construite en 1855', 'absente de la base'],
            [$result['records'][0]['properties']['source_url'], $result['records'][0]['properties']['quote'], $result['records'][0]['properties']['reason']]);
        $this->assertSame(['mauvaise', 'exemple-ancienne-gare'], array_keys($result['errors']));
        $this->assertContains('ref en double', $result['errors']['exemple-ancienne-gare']);
    }

    public function test_an_open_station_is_active(): void
    {
        $e = array_diff_key(self::entry(), array_flip(['closed_on', 'closed_precision']));

        $this->assertSame('active', (new ManualStations([$e]))->records()['records'][0]['lifecycle']);
    }

    public function test_the_versioned_file_is_valid_and_sourced(): void
    {
        $entries = self::file();
        $this->assertNotSame([], $entries);
        $refs = [];
        foreach ($entries as $i => $e) {
            $this->assertSame([], ManualStations::validate($e), "gare manuelle n° {$i}");
            $this->assertMatchesRegularExpression('#^https://fr\.wikipedia\.org/w/index\.php\?title=[^&]+&oldid=\d+$#', $e['source_url'], $e['ref']);
            $refs[] = $e['ref'];
        }
        $this->assertSame($refs, array_values(array_unique($refs)));
        $this->assertSame([], (new ManualStations(self::file()))->records()['errors']);
    }

    /** Le slug qu'une gare manuelle reçoit à la consolidation est celui que les terminus relus désignent. */
    public function test_terminus_decisions_point_at_the_slug_the_station_will_get(): void
    {
        $slugs = array_map(fn (array $e) => Str::slug($e['name']), self::file());
        $attached = array_unique(array_column(array_filter(
            require dirname(__DIR__, 2).'/database/data/station_terminus_overrides.php',
            fn (array $d) => $d['decision'] === 'attach' && str_contains((string) $d['source_url'], 'Bonningues'),
        ), 'station'));

        $this->assertSame($slugs, array_values($attached));
        $this->assertSame([], StationTerminusOverrides::validate(['line' => 'l', 'terminus' => 'Boulogne', 'decision' => 'attach', 'station' => $slugs[0],
            'source_url' => 'https://fr.wikipedia.org/w/index.php?title=X&oldid=1', 'reason' => 'r']));
    }
}
