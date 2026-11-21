<?php

namespace Tests\Feature\StationAudit;

use App\Services\StationAudit\BdtopoStationZones;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use RuntimeException;
use Tests\TestCase;

/**
 * Lecture des gares BD TOPO par le WFS de l'IGN (BdtopoStationZones::tile), sans réseau (Http::fake) :
 * pages de 1 000 objets, cache local, mode hors ligne, politesse (User-Agent, pauses) et reprises sur
 * erreur passagère. Aucune base de données.
 */
class BdtopoStationZonesTest extends TestCase
{
    private string $cacheDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cacheDir = sys_get_temp_dir().'/vff-bdtopo-zones-test-'.getmypid();
        Sleep::fake();
    }

    protected function tearDown(): void
    {
        try {
            parent::tearDown();
        } finally {
            File::deleteDirectory($this->cacheDir);
        }
    }

    /** @return array<string, string> paramètres de l'adresse demandée */
    private static function queryOf(Request $r): array
    {
        parse_str((string) parse_url($r->url(), PHP_URL_QUERY), $query);

        return array_map('strval', $query);
    }

    /** @return array<string, mixed> */
    private static function feature(int $i, string $nature = 'Gare voyageurs uniquement', ?string $name = 'Gare de Test'): array
    {
        return ['type' => 'Feature', 'properties' => ['cleabs' => 'EQ'.$i, 'nature' => $nature, 'toponyme' => $name, 'etat_de_l_objet' => 'En service'],
            'geometry' => ['type' => 'MultiPolygon', 'coordinates' => []]];
    }

    /** @return array<string, mixed> */
    private static function page(int $count, int $offset = 0): array
    {
        return ['type' => 'FeatureCollection', 'features' => array_map(fn (int $i): array => self::feature($offset + $i), $count > 0 ? range(1, $count) : [])];
    }

    public function test_pages_are_read_until_a_short_page_and_kept_in_cache(): void
    {
        Http::fake(fn (Request $r) => Http::response(self::page(self::queryOf($r)['STARTINDEX'] === '0' ? 1000 : 3, (int) self::queryOf($r)['STARTINDEX'])));
        $zones = new BdtopoStationZones($this->cacheDir, 0);

        $first = $zones->tile(-1, 48);
        $this->assertCount(1003, $first);
        $this->assertSame(['cleabs' => 'EQ1', 'nature' => 'Gare voyageurs uniquement', 'toponyme' => 'Gare de Test', 'state' => 'En service',
            'geometry' => ['type' => 'MultiPolygon', 'coordinates' => []]], $first[0]);
        $this->assertSame(2, $zones->networkCalls());
        Http::assertSent(fn (Request $r): bool => $r->hasHeader('User-Agent', BdtopoStationZones::USER_AGENT)
            && str_contains(self::queryOf($r)['CQL_FILTER'], "BBOX(geometrie,-1,48,0,49,'urn:ogc:def:crs:OGC:1.3:CRS84')"));
        $this->assertFileExists($this->cacheDir.'/.nobackup');

        // Seconde passe : tout vient du cache, même hors ligne.
        $again = new BdtopoStationZones($this->cacheDir, 0);
        $this->assertSame($first, $again->tile(-1, 48, offline: true));
        $this->assertSame($first, $again->tile(-1, 48));
        $this->assertSame(0, $again->networkCalls());
    }

    public function test_offline_without_cache_reads_nothing(): void
    {
        Http::fake();
        $zones = new BdtopoStationZones($this->cacheDir, 0);
        $this->assertSame([], $zones->tile(2, 47, offline: true));
        $this->assertSame(0, $zones->networkCalls());
        Http::assertNothingSent();
    }

    public function test_only_railway_stations_are_kept(): void
    {
        Http::fake(['*' => Http::response(['features' => [
            self::feature(1),
            self::feature(2, 'Arrêt voyageurs', 'Ancienne gare de Messei'),
            self::feature(3, 'Arrêt voyageurs', 'halte de Lison'),
            self::feature(4, 'Arrêt voyageurs', 'Gareloup'),
            self::feature(5, 'Gare routière', 'Gare routière'),
            ['properties' => ['nature' => 'Gare fret uniquement'], 'geometry' => []],
            ['properties' => ['cleabs' => 'EQ7', 'nature' => 'Gare fret uniquement'], 'geometry' => null],
            ['properties' => ['cleabs' => 'EQ8', 'nature' => 'Gare voyageurs et fret'], 'geometry' => ['type' => 'MultiPolygon', 'coordinates' => []]],
        ]])]);
        $zones = (new BdtopoStationZones($this->cacheDir, 0))->tile(0, 48);
        $this->assertSame(['EQ1', 'EQ2', 'EQ3', 'EQ8'], array_column($zones, 'cleabs'));
        $this->assertSame([null, null], [$zones[3]['toponyme'], $zones[3]['state']]);
    }

    public function test_transient_errors_are_retried_with_pauses(): void
    {
        $calls = 0;
        Http::fake(function () use (&$calls) {
            $calls++;

            return match ($calls) {
                1 => Http::failedConnection('coupure'),
                2 => Http::response('Service indisponible', 503),
                3 => Http::response('<html>attente</html>', 200),
                default => Http::response(self::page(2)),
            };
        });
        $zones = new BdtopoStationZones($this->cacheDir, 250);
        $this->assertCount(2, $zones->tile(5, 45));
        $this->assertSame(4, $zones->networkCalls());
        // Une pause de 250 ms après chaque appel, et une attente croissante (5, 10, 15 s) avant chaque reprise.
        Sleep::assertSleptTimes(7);
    }

    public function test_a_client_error_stops_at_once(): void
    {
        Http::fake(['*' => Http::response('<p>Paramètre CQL_FILTER invalide</p>', 400)]);
        $zones = new BdtopoStationZones($this->cacheDir, 0);
        try {
            $zones->tile(5, 45);
            $this->fail('une erreur 400 doit arrêter la lecture');
        } catch (RuntimeException $e) {
            $this->assertSame("WFS de l'IGN : HTTP 400, Paramètre CQL_FILTER invalide", $e->getMessage());
        }
        $this->assertSame(1, $zones->networkCalls());
        $this->assertDirectoryDoesNotExist($this->cacheDir.'/bdtopo');
    }

    public function test_too_many_retries_give_up(): void
    {
        Http::fake(['*' => Http::response('', 429)]);
        $zones = new BdtopoStationZones($this->cacheDir, 0);
        $this->expectExceptionMessage("WFS de l'IGN : trop de reprises.");
        try {
            $zones->tile(5, 45);
        } finally {
            $this->assertSame(5, $zones->networkCalls());
        }
    }

    public function test_an_unreadable_cache_file_is_fetched_again(): void
    {
        Http::fake(['*' => Http::response(self::page(1))]);
        $zones = new BdtopoStationZones($this->cacheDir, 0);
        $zones->tile(1, 46);
        foreach (File::files($this->cacheDir.'/bdtopo') as $file) {
            File::put($file->getPathname(), 'pas du JSON');
        }
        $retry = new BdtopoStationZones($this->cacheDir, 0);
        $this->assertCount(1, $retry->tile(1, 46));
        $this->assertSame(1, $retry->networkCalls());
    }
}
