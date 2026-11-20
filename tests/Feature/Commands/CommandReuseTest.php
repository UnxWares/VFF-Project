<?php

namespace Tests\Feature\Commands;

use App\Models\Era;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * Une commande appelée plusieurs fois dans un même processus réutilise la
 * MÊME instance (Symfony Console) : vff:rebuild-history enchaîne ainsi
 * vff:link-traces (archeoferroviaire puis ohm) et vff:resolve-event-sections
 * (5 fois) par $this->call(). Tout état gardé dans les propriétés de la
 * commande doit être remis à zéro au début de handle().
 */
class CommandReuseTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
        $this->era(2030, current: true);
        $line = $this->rfnLine('100000', 'Ligne de Alpha à Bravo');
        foreach ([['archeoferroviaire', 'a-1', 'principal', 'Ligne de Alpha à Bravo'], ['ohm', 'w1', 'principal', 'Alpha - Bravo']] as [$source, $ref, $layer, $name]) {
            DB::table('source_traces')->insert([
                'source' => $source, 'source_ref' => $ref, 'layer' => $layer, 'name' => $name, 'length_m' => 11_000,
                'line_id' => null, 'seen_at' => now(), 'created_at' => now(), 'updated_at' => now(),
                'geom' => DB::raw("ST_GeomFromText('LINESTRING(2 48, 2 48.1)', 4326)"),
            ]);
        }
        $this->track($line, Era::where('is_current', true)->first(), [[48.0, 2.0], [48.1, 2.0]]);
    }

    public function test_consolidate_stations_twice_in_one_process_gives_the_same_stations(): void
    {
        foreach ([['sncf', '87000001', 'Alpha', 2.0001], ['osm', 'n1', "Gare d'Alpha", 2.0003]] as [$source, $ref, $name, $lng]) {
            DB::table('station_sources')->insert([
                'source' => $source, 'source_ref' => $ref, 'name' => $name, 'name_norm' => 'alpha', 'kind' => 'gare', 'lifecycle' => 'active',
                'seen_at' => now(), 'created_at' => now(), 'updated_at' => now(),
                'geom' => DB::raw(sprintf('ST_SetSRID(ST_MakePoint(%F, 48.05), 4326)', $lng)),
            ]);
        }

        $this->artisan('vff:consolidate-stations')->expectsOutputToContain('1 gares après dédoublonnage')->assertSuccessful();
        $this->artisan('vff:consolidate-stations')->expectsOutputToContain('1 gares après dédoublonnage')->assertSuccessful();

        $this->assertSame(1, DB::table('stations')->count());
    }

    /** Témoin du test known-bug ci-dessous : seul, le passage « ohm » compte bien 1 tracé. */
    public function test_link_traces_alone_counts_only_its_source(): void
    {
        $this->artisan('vff:link-traces', ['--source' => 'ohm', '--dry-run' => true])
            ->expectsOutputToContain('1 tracés « ohm »')->assertSuccessful();
    }

    /**
     * Régression : $traces, $assign, $planned… n'étaient jamais remis à zéro. Dans
     * vff:rebuild-history, le passage « ohm » qui suit le passage « archeoferroviaire »
     * retraitait (et réécrivait) aussi les tracés archeoferroviaire du passage précédent.
     */
    public function test_link_traces_does_not_carry_traces_from_a_previous_source(): void
    {
        $this->artisan('vff:link-traces', ['--dry-run' => true])
            ->expectsOutputToContain('1 tracés « archeoferroviaire »')->assertSuccessful();

        $this->artisan('vff:link-traces', ['--source' => 'ohm', '--dry-run' => true])
            ->expectsOutputToContain('1 tracés « ohm »')->assertSuccessful();
    }
}
