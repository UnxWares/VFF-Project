<?php

namespace Tests\Feature\StationAudit;

use App\Services\Materials\SourceCache;
use App\Services\StationAudit\WikidataStationDates;
use App\Services\WikimediaClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Ouverture encadrée (précision `range`, règle G3) : `vff:import-station-wikidata-dates` compare la
 * date Wikidata à l'intervalle ouvert entre `opened_after` + 1 et l'année de `opened_at`, pas à
 * l'ère décennale.
 *
 * Gares ouvertes « entre 1955 et 1963 » (opened_after 1954, opened_at 1963-12-31) : l'ère décennale
 * vaudrait 1961 à 1970, ce qui change le verdict pour 1957 et 1964.
 */
class ImportStationWikidataDatesRangeTest extends TestCase
{
    use RefreshDatabase;

    private string $cacheDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cacheDir = sys_get_temp_dir().'/vff-wikidata-range-test-'.getmypid();
        $this->app->instance(WikidataStationDates::class, new WikidataStationDates(new WikimediaClient, new SourceCache($this->cacheDir), 0));
    }

    protected function tearDown(): void
    {
        try {
            parent::tearDown();
        } finally {
            File::deleteDirectory($this->cacheDir);
        }
    }

    private function station(string $slug, string $qid, ?int $after): void
    {
        DB::table('stations')->insert([
            'slug' => $slug, 'name' => $slug, 'type' => 'gare', 'status' => 'active', 'department_code' => '61',
            'position_source' => 'sncf', 'wikidata_id' => $qid, 'opened_at' => '1963-12-31', 'opened_precision' => 'range',
            'provenance' => json_encode(['opened_at' => 'inferred:line_event'] + ($after !== null ? ['opened_after' => $after] : [])),
            'geom' => DB::raw('ST_SetSRID(ST_MakePoint(2, 48), 4326)'), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** @return array<string, mixed> */
    private function claim(int $year): array
    {
        return ['claims' => ['P1619' => [['id' => 'x$'.$year, 'rank' => 'normal',
            'mainsnak' => ['datavalue' => ['value' => ['time' => sprintf('+%d-01-01T00:00:00Z', $year), 'precision' => 9]]]]]]];
    }

    /**
     * @param  array<string, int>  $years  année Wikidata par gare
     * @param  array<string, int|null>  $afters  opened_after par gare
     * @return array<string, array{comparison: string, delta: int|null}>
     */
    private function compareAll(array $years, array $afters): array
    {
        $entities = [];
        foreach ($years as $slug => $year) {
            $qid = 'Q'.(100 + count($entities));
            $this->station($slug, $qid, $afters[$slug]);
            $entities[$qid] = $this->claim($year);
        }
        Http::fake(['www.wikidata.org/*' => Http::response(['entities' => $entities])]);
        $this->artisan('vff:import-station-wikidata-dates')->assertSuccessful();

        $out = [];
        foreach (DB::table('station_date_candidates')->join('stations', 'stations.id', '=', 'station_id')->get(['slug', 'comparison', 'delta_days']) as $r) {
            $out[(string) $r->slug] = ['comparison' => (string) $r->comparison, 'delta' => $r->delta_days === null ? null : (int) $r->delta_days];
        }

        return $out;
    }

    public function test_the_lower_bound_is_opened_after_plus_one(): void
    {
        $same = array_fill_keys(['dans', 'borne-basse', 'veille', 'apres', 'loin'], 1954);
        $r = $this->compareAll(['dans' => 1957, 'borne-basse' => 1955, 'veille' => 1954, 'apres' => 1964, 'loin' => 1940], $same);

        // 1957 est dans « entre 1955 et 1963 » (hors de l'ère décennale 1961-1970) : concordante.
        $this->assertSame(['more_precise', 0], [$r['dans']['comparison'], $r['dans']['delta']]);
        $this->assertSame(['more_precise', 0], [$r['borne-basse']['comparison'], $r['borne-basse']['delta']]);
        // 1954 et 1964 : à moins d'un an de l'intervalle, proches.
        $this->assertSame(['close', -1], [$r['veille']['comparison'], $r['veille']['delta']]);
        $this->assertSame(['close', 1], [$r['apres']['comparison'], $r['apres']['delta']]);
        // 1940 : hors de l'intervalle, contradictoire.
        $this->assertSame('differs', $r['loin']['comparison']);
    }

    public function test_without_opened_after_the_decennial_era_is_the_lower_bound(): void
    {
        $r = $this->compareAll(['dans' => 1957], ['dans' => null]);

        // Ère décennale 1961-1970 : 1957 est hors intervalle, à plus d'un an.
        $this->assertSame('differs', $r['dans']['comparison']);
    }
}
