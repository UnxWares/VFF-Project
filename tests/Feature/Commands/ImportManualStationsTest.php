<?php

namespace Tests\Feature\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * vff:import-station-sources --source=manual (database/data/gares_manuelles.php, App\Services\ManualStations) :
 * une entrée devenue mal formée est signalée et ignorée, mais l'objet déjà importé reste. Sinon il disparaîtrait
 * de la source, vff:consolidate-stations supprimerait la gare comme orpheline, et ses événements (fermetures
 * relues, rattachements) partiraient avec elle.
 */
class ImportManualStationsTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
        Http::preventStrayRequests();
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

    public function test_a_valid_entry_is_imported_and_a_removed_one_disappears(): void
    {
        $this->useDataFile('gares_manuelles.php', [self::entry()]);
        $this->artisan('vff:import-station-sources', ['--source' => ['manual']])->assertSuccessful();
        $this->assertSame(['exemple-ancienne-gare'], DB::table('station_sources')->where('source', 'manual')->pluck('source_ref')->all());

        $this->travel(5)->minutes();
        $this->useDataFile('gares_manuelles.php', []);
        $this->artisan('vff:import-station-sources', ['--source' => ['manual']])->assertSuccessful();

        $this->assertSame(0, DB::table('station_sources')->where('source', 'manual')->count());
    }

    public function test_an_entry_that_became_malformed_is_reported_and_keeps_its_imported_object(): void
    {
        $this->useDataFile('gares_manuelles.php', [self::entry()]);
        $this->artisan('vff:import-station-sources', ['--source' => ['manual']])->assertSuccessful();

        $this->travel(5)->minutes();
        // Une retouche fautive (tiret double dans la citation) : l'entrée ne passe plus la validation.
        $this->useDataFile('gares_manuelles.php', [['quote' => 'construite en 1855 -- rive gauche'] + self::entry()]);
        $this->artisan('vff:import-station-sources', ['--source' => ['manual']])
            ->expectsOutputToContain('gare manuelle « exemple-ancienne-gare » ignorée')
            ->assertSuccessful();

        $this->assertSame(['exemple-ancienne-gare'], DB::table('station_sources')->where('source', 'manual')->pluck('source_ref')->all());
    }
}
