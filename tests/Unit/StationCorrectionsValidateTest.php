<?php

namespace Tests\Unit;

use App\Services\StationCorrections;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Forme des corrections de gares (StationCorrections::validate) et liens défaits (action « unlink »).
 */
class StationCorrectionsValidateTest extends TestCase
{
    /** @return array<string, mixed> */
    private function base(array $override = []): array
    {
        return $override + ['station' => ['name' => 'St-Vidal', 'uic' => '87731844'], 'action' => 'unlink', 'source' => 'wikidata', 'ref' => 'Q123',
            'source_url' => 'https://example.org/x', 'reason' => 'Le lien désigne une autre gare.'];
    }

    public function test_a_complete_unlink_is_valid(): void
    {
        $this->assertSame([], StationCorrections::validate($this->base()));
        $osm = $this->base(['station' => ['name' => 'St-Vidal', 'osm' => 'n42']]);
        $this->assertSame([], StationCorrections::validate($osm));
    }

    public function test_a_link_needs_a_wikidata_id_and_a_station_known_by_the_sources(): void
    {
        $link = fn (array $o = []) => $o + ['station' => ['name' => 'Autet', 'uic' => '87185538'], 'action' => 'link', 'source' => 'wikidata', 'ref' => 'Q120757199',
            'source_url' => 'https://www.wikidata.org/wiki/Q120757199', 'reason' => 'Meme UIC.'];
        $this->assertSame([], StationCorrections::validate($link()));
        $this->assertSame([], StationCorrections::validate($link(['station' => ['name' => 'Faulx', 'near' => [6.2, 48.8, 0.3]]])));
        $this->assertNotEmpty(StationCorrections::validate($link(['ref' => 'Q12x'])));
        $this->assertNotEmpty(StationCorrections::validate($link(['source' => 'osm'])));
        $this->assertNotEmpty(StationCorrections::validate($link(['station' => ['name' => 'Autet', 'wikidata' => 'Q1']])));
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function invalid(): array
    {
        return [
            'action inconnue' => [['action' => 'explode'], 'action inconnue'],
            'sans action' => [['action' => null], 'action inconnue'],
            'raison vide' => [['reason' => '  '], 'reason manquante'],
            'raison absente' => [['reason' => null], 'reason manquante'],
            'source_url sans http' => [['source_url' => 'ftp://x'], 'source_url manquante'],
            'gare sans nom' => [['station' => ['uic' => '1']], 'gare : name'],
            'gare sans identifiant' => [['station' => ['name' => 'X']], 'gare : name'],
            'near trop large' => [['station' => ['name' => 'X', 'near' => [2.0, 48.0, 6]]], 'near'],
            'near nul' => [['station' => ['name' => 'X', 'near' => [2.0, 48.0, 0]]], 'near'],
            'near incomplet' => [['station' => ['name' => 'X', 'near' => [2.0, 48.0]]], 'near'],
            'near non numérique' => [['station' => ['name' => 'X', 'near' => [2.0, 'a', 1]]], 'near'],
            'near pas un tableau' => [['station' => ['name' => 'X', 'near' => 'ici']], 'near'],
            'unlink : source inconnue' => [['source' => 'osm'], 'unlink'],
            'unlink : Q-id mal formé' => [['ref' => 'Q12x'], 'unlink'],
            'unlink : gare sans uic ni osm' => [['station' => ['name' => 'X', 'wikidata' => 'Q1']], 'unlink'],
            'add : type inconnu' => [['action' => 'add', 'type' => 'fusion', 'date' => '1900-01-01'], 'add'],
            'add : date mal formée' => [['action' => 'add', 'type' => 'closure', 'date' => '1900'], 'add'],
            'add : précision inconnue' => [['action' => 'add', 'type' => 'closure', 'date' => '1900-01-01', 'precision' => 'hour'], 'add'],
            'neutralize sans match' => [['action' => 'neutralize'], 'neutralize'],
            'neutralize date mal formée' => [['action' => 'neutralize', 'match' => ['source' => 'osm', 'type' => 'closure', 'date' => '1900']], 'neutralize'],
            'kind inconnu' => [['action' => 'kind', 'kind' => 'banane'], 'kind'],
            'current inconnu' => [['action' => 'current', 'status' => 'closed'], 'current'],
            'position sans lng' => [['action' => 'position', 'lat' => 48.0], 'position'],
            'position non numérique' => [['action' => 'position', 'lng' => 'x', 'lat' => 'y'], 'position'],
            'pk : code court' => [['action' => 'pk', 'line' => '1234', 'pk_m' => 10], 'pk'],
            'pk : mètres négatifs' => [['action' => 'pk', 'line' => '123456', 'pk_m' => -1], 'pk'],
            'pk : mètres en texte' => [['action' => 'pk', 'line' => '123456', 'pk_m' => '10'], 'pk'],
            'tiret long dans la raison' => [['reason' => "Gare \u{2014} fermée"], 'tiret long'],
            'double tiret dans la citation' => [['quote' => 'a -- b'], 'tiret long'],
        ];
    }

    /** @param  array<string, mixed>  $override */
    #[DataProvider('invalid')]
    public function test_each_malformed_correction_reports_its_error(array $override, string $fragment): void
    {
        $errors = StationCorrections::validate($this->base($override));

        $this->assertNotSame([], $errors);
        $this->assertStringContainsString($fragment, implode(' | ', $errors));
    }

    public function test_valid_actions_pass(): void
    {
        $cases = [
            ['action' => 'add', 'type' => 'closure_freight', 'date' => '1990-09-30', 'precision' => 'month'],
            ['action' => 'add', 'type' => 'closure', 'date' => '1990-09-30', 'precision' => 'day'],
            ['action' => 'neutralize', 'match' => ['source' => 'osm', 'type' => 'closure', 'date' => '1990-09-30']],
            ['action' => 'kind', 'kind' => 'excluded'],
            ['action' => 'current', 'status' => 'tourist'],
            ['action' => 'current', 'status' => 'no_commercial'],
            ['action' => 'position', 'lng' => 2.1, 'lat' => 48.2],
            ['action' => 'pk', 'line' => '123456', 'pk_m' => 0],
            ['action' => 'tourist'],
        ];
        foreach ($cases as $case) {
            $this->assertSame([], StationCorrections::validate($this->base($case)), json_encode($case));
        }
    }

    public function test_unlinks_lists_only_valid_unlink_corrections(): void
    {
        $corrections = new StationCorrections([
            $this->base(),
            $this->base(['station' => ['name' => 'Néronde', 'osm' => 'n7'], 'ref' => 'Q9']),
            $this->base(['ref' => 'pas-un-qid']),
            $this->base(['action' => 'tourist']),
            $this->base(['reason' => '']),
        ]);

        $this->assertSame([
            ['name' => 'St-Vidal', 'uic' => '87731844', 'osm' => null, 'source' => 'wikidata', 'ref' => 'Q123'],
            ['name' => 'Néronde', 'uic' => null, 'osm' => 'n7', 'source' => 'wikidata', 'ref' => 'Q9'],
        ], $corrections->unlinks());
    }
}
