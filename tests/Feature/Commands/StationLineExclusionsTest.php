<?php

namespace Tests\Feature\Commands;

use App\Models\Era;
use App\Services\StationLineExclusions;
use App\Services\StationLineInclusions;
use App\Services\StationTerminusOverrides;
use App\Support\StationNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * Couples gare - ligne exclus à la main (database/data/station_line_exclusions.php) : jamais recréés par
 * vff:attach-stations, quelle que soit la méthode de rattachement, et les autres liens de la gare et de la ligne
 * restent intacts.
 */
class StationLineExclusionsTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    private const SOURCE = 'https://fr.wikipedia.org/w/index.php?title=Exemple&oldid=1';

    private Era $current;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
        $this->current = $this->era(2030, current: true);
    }

    /** @param  list<array<string, mixed>>  $entries */
    private function exclude(array $entries): void
    {
        $this->app->instance(StationLineExclusions::class, new StationLineExclusions(array_map(
            fn (array $e) => $e + ['reason' => 'gare hors de la ligne', 'source_url' => self::SOURCE, 'reviewed_at' => '2026-11-22'],
            $entries,
        )));
    }

    private function source(string $source, string $ref, string $name, float $lat, float $lng, int $stationId, ?array $properties = null): void
    {
        DB::table('station_sources')->insert([
            'source' => $source, 'source_ref' => $ref, 'name' => $name, 'name_norm' => StationNames::normalize($name), 'station_id' => $stationId,
            'kind' => 'gare', 'lifecycle' => 'active', 'properties' => $properties === null ? null : json_encode($properties),
            'seen_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            'geom' => DB::raw(sprintf('ST_SetSRID(ST_MakePoint(%F, %F), 4326)', $lng, $lat)),
        ]);
    }

    /** @return array<string, string> méthode du lien, par « gare@ligne » (slugs) */
    private function links(): array
    {
        $links = DB::table('station_lines as sl')->join('stations as s', 's.id', '=', 'sl.station_id')->join('lines as l', 'l.id', '=', 'sl.line_id')
            ->orderBy('s.slug')->orderBy('l.slug')->get(['s.slug as s', 'l.slug as l', 'sl.method'])
            ->mapWithKeys(fn ($r) => [$r->s.'@'.$r->l => $r->method])->all();
        ksort($links);

        return $links;
    }

    /**
     * Une gare par méthode de rattachement : chacune est sur sa ligne par sncf_pk, source_pk, wikidata_p81, segment
     * (proximité), terminus_name et terminus_reviewed ; l'exclusion de chaque couple l'empêche, et le même couple
     * hors exclusion est bien créé (contrôle). Ligne et gare par slug, par code ou par élément Wikidata.
     */
    public function test_an_excluded_link_is_never_created_whatever_the_method(): void
    {
        $secondary = ['code' => null, 'network' => 'secondaire'];
        $alpha = $this->rfnLine('100000', 'Ligne de Alpha à Bravo');
        $this->track($alpha, $this->current, [[48.00, 2.0], [48.10, 2.0]]);
        $schema = $this->rfnLine('', 'Ligne de Kappa à Lambda', $secondary);
        $this->track($schema, $this->current, [[47.0, 3.0], [47.1, 3.0]], ['status' => 'dismantled', 'traffic_type' => 'unknown']);
        $named = $this->rfnLine('', 'Ligne de Romeo à Tango', $secondary);
        $this->track($named, $this->current, [[47.2, 5.0], [47.3, 5.0]], ['status' => 'dismantled', 'traffic_type' => 'unknown']);
        $reviewed = $this->rfnLine('', 'Ligne de Quebec à Sierra', $secondary);
        $this->track($reviewed, $this->current, [[47.4, 6.0], [47.5, 6.0]], ['status' => 'dismantled', 'traffic_type' => 'unknown']);

        $bySncf = $this->station('par-sncf', 'Par-Sncf', 48.0450, 2.0000);
        $this->source('sncf', '87000001', 'Par-Sncf', 48.0450, 2.0000, $bySncf->id, ['lines' => [['code_ligne' => '100000', 'pk_m' => 5_000]]]);
        $byWikidata = $this->station('par-wikidata', 'Par-Wikidata', 48.0200, 2.0100, ['wikidata_id' => 'Q4242']);
        DB::table('wikidata_lines')->insert(['qid' => 'Q9', 'category' => 'line', 'line_id' => $alpha->id, 'fetched_at' => now()]);
        $this->source('wikidata', 'Q4242', 'Par-Wikidata', 48.0200, 2.0100, $byWikidata->id, ['lines' => ['Q9']]);
        $bySegment = $this->station('par-segment', 'Par-Segment', 48.0900, 2.0004);
        DB::table('stations')->where('id', $bySegment->id)->update(['position_source' => 'osm']);
        $this->source('osm', 'n2', 'Par-Segment', 48.0900, 2.0004, $bySegment->id);
        $bySchema = $this->station('par-schema', 'Par-Schema', 47.0630, 3.0150);
        $this->source('wikipedia_schema', 'L1:par-schema', 'Par-Schema', 47.0630, 3.0150, $bySchema->id, ['line_id' => $schema->id, 'pk_m' => 7_000, 'geocoding' => 'pk', 'precision_m' => 450]);
        $this->station('romeo', 'Romeo', 47.1982, 5.0);
        $this->station('quebec-est', 'Quebec-Est', 47.3919, 6.0);
        $this->app->instance(StationTerminusOverrides::class, new StationTerminusOverrides([
            ['line' => $reviewed->slug, 'terminus' => 'Quebec', 'decision' => 'attach', 'station' => 'quebec-est', 'source_url' => self::SOURCE, 'reason' => 'tête de ligne'],
        ]));

        $all = [
            'par-sncf@'.$alpha->slug => 'sncf_pk',
            'par-wikidata@'.$alpha->slug => 'wikidata_p81',
            'par-segment@'.$alpha->slug => 'segment',
            'par-schema@'.$schema->slug => 'source_pk',
            'romeo@'.$named->slug => 'terminus_name',
            'quebec-est@'.$reviewed->slug => 'terminus_reviewed',
        ];
        ksort($all);
        $this->exclude([]);
        $this->artisan('vff:attach-stations')->assertSuccessful();
        $this->assertSame($all, $this->links(), 'sans exclusion, chaque méthode crée son lien');

        $this->exclude([
            ['station' => 'par-sncf', 'line' => $alpha->slug],
            ['station' => 'Q4242', 'line' => '100000'],
            ['station' => 'par-segment', 'line' => $alpha->slug],
            ['station' => 'par-schema', 'line' => $schema->slug],
            ['station' => 'romeo', 'line' => $named->slug],
            ['station' => 'quebec-est', 'line' => $reviewed->slug],
        ]);
        $this->artisan('vff:attach-stations')->expectsOutputToContain('exclusions : 6 liens retirés sur 6 exclusions valides')->assertSuccessful();
        $this->assertSame([], $this->links(), 'rattachement complet : aucun des six liens recréés');
        foreach ([[], ['--termini-only' => true], ['--years-only' => true]] as $options) {
            $this->artisan('vff:attach-stations', $options)->assertSuccessful();
            $this->assertSame([], $this->links(), 'options : '.json_encode($options));
        }
    }

    /**
     * Le lien exclu est retiré d'une base qui l'a déjà, dans les trois modes de la commande ; les autres liens de
     * la gare et de la ligne ne bougent pas ; --dry-run n'écrit rien.
     */
    public function test_existing_link_is_removed_in_every_mode_and_other_links_stay(): void
    {
        $alpha = $this->rfnLine('100000', 'Ligne de Alpha à Bravo');
        $this->track($alpha, $this->current, [[48.00, 2.0], [48.10, 2.0]]);
        $beta = $this->rfnLine('200000', 'Ligne de Charlie à Delta');
        $this->track($beta, $this->current, [[48.00, 2.0003], [48.10, 2.0003]]);
        $hub = $this->station('hub', 'Hub', 48.0450, 2.0001);
        $other = $this->station('autre', 'Autre', 48.0800, 2.0001);
        foreach ([$hub, $other] as $s) {
            $this->source('osm', 'n'.$s->id, $s->name, 48.0450, 2.0001, $s->id);
            DB::table('stations')->where('id', $s->id)->update(['position_source' => 'osm']);
        }
        $this->exclude([]);
        $this->artisan('vff:attach-stations')->assertSuccessful();
        $before = $this->links();
        $this->assertCount(4, $before);
        $this->assertSame(['segment'], array_values(array_unique($before)));

        $this->exclude([['station' => 'hub', 'line' => '100000']]);
        $kept = array_diff_key($before, ['hub@'.$alpha->slug => 1]);
        $snapshot = $this->snapshot(['station_lines']);
        $this->artisan('vff:attach-stations', ['--years-only' => true, '--dry-run' => true])->assertSuccessful();
        $this->assertSame($snapshot, $this->snapshot(['station_lines']), '--dry-run : rien d\'écrit');

        $this->artisan('vff:attach-stations', ['--years-only' => true])->expectsOutputToContain('exclusions : 1 liens retirés sur 1 exclusions valides')->assertSuccessful();
        $this->assertSame($kept, $this->links());

        // Les deux autres modes : le lien exclu revient d'abord par un ajout direct en base, puis est retiré.
        foreach (['--termini-only', 'complet'] as $mode) {
            DB::table('station_lines')->insert(['station_id' => $hub->id, 'line_id' => $alpha->id, 'method' => 'segment', 'created_at' => now(), 'updated_at' => now()]);
            $this->artisan('vff:attach-stations', $mode === 'complet' ? [] : [$mode => true])->assertSuccessful();
            $this->assertSame($kept, $this->links(), $mode);
        }
    }

    /**
     * Real case (liste-des-gares of SNCF Open Data, 19/11/2026): the record « Grasse » (UIC 87757724) is placed at
     * Marseille-Saint-Charles on line 930000 at PK 0+025; the versioned file keeps that record off lines 930000 and 830000
     * (where it was the end of the line), while the real gare de Grasse stays on the line of Cannes-la Bocca to Grasse.
     */
    public function test_the_versioned_file_keeps_the_grasse_record_at_marseille_off_its_lines(): void
    {
        $this->app->instance(StationLineExclusions::class, new StationLineExclusions);
        $vintimille = $this->rfnLine('930000', 'Ligne de Marseille-St-Charles à Vintimille (frontière)', ['slug' => 'rfn-930000']);
        $this->track($vintimille, $this->current, [[43.30275, 5.38063], [43.29600, 5.40000]]);
        $plm = $this->rfnLine('830000', 'Ligne de Paris-Lyon à Marseille-St-Charles', ['slug' => 'rfn-830000']);
        $this->track($plm, $this->current, [[43.32000, 5.37000], [43.30280, 5.38050]]);
        $grasseLine = $this->rfnLine('944000', 'Ligne de Cannes-la Bocca à Grasse', ['slug' => 'rfn-944000']);
        $this->track($grasseLine, $this->current, [[43.54000, 6.96000], [43.65332, 6.92552]]);

        $marseille = $this->station('grasse', 'Grasse', 43.3027541, 5.3806291, ['uic_code' => '87757724']);
        $this->source('sncf', '87757724', 'Grasse', 43.3027541, 5.3806291, $marseille->id, ['lines' => [['fret' => false, 'pk_m' => 25, 'voyageurs' => true, 'code_ligne' => '930000', 'rg_troncon' => 1]], 'commune' => 'MARSEILLE']);
        $grasse = $this->station('grasse-06', 'Grasse', 43.653317, 6.925524, ['uic_code' => '87757724']);
        DB::table('stations')->where('id', $grasse->id)->update(['position_source' => 'osm']);
        $this->source('osm', 'n206149230', 'Grasse', 43.653317, 6.925524, $grasse->id);

        $this->artisan('vff:attach-stations')->assertSuccessful();

        $links = $this->links();
        $this->assertArrayNotHasKey('grasse@rfn-930000', $links);
        $this->assertArrayNotHasKey('grasse@rfn-830000', $links);
        $this->assertSame('segment', $links['grasse-06@rfn-944000'] ?? null);
    }

    /** Entrées invalides, en double ou introuvables : signalées, ignorées, rien d'autre retiré. */
    public function test_invalid_duplicate_and_unknown_exclusions_are_reported_and_ignored(): void
    {
        $alpha = $this->rfnLine('100000', 'Ligne de Alpha à Bravo');
        $this->track($alpha, $this->current, [[48.00, 2.0], [48.10, 2.0]]);
        $hub = $this->station('hub', 'Hub', 48.0450, 2.0001);
        $this->station('hub-bis', 'Hub-Bis', 48.0460, 2.0001, ['wikidata_id' => 'Q7']);
        $this->station('hub-ter', 'Hub-Ter', 48.0470, 2.0001, ['wikidata_id' => 'Q7']);
        $this->source('osm', 'n1', 'Hub', 48.0450, 2.0001, $hub->id);
        DB::table('stations')->where('id', $hub->id)->update(['position_source' => 'osm']);

        $this->exclude([
            ['station' => 'hub', 'line' => '100000'],
            ['station' => 'hub', 'line' => $alpha->slug],                      // doublon du premier
            ['station' => 'gare-absente', 'line' => '100000'],
            ['station' => 'hub', 'line' => '999999'],
            ['station' => 'Q7', 'line' => '100000'],                          // élément partagé par deux gares
            ['station' => 'hub', 'line' => '100000', 'source_url' => 'http://x'],  // source non https
        ]);
        $this->artisan('vff:attach-stations')
            ->expectsOutputToContain('exclusions : 1 liens retirés sur 1 exclusions valides, 5 ignorées')
            ->expectsOutputToContain('doublon de l\'exclusion n° 0')
            ->expectsOutputToContain('gare introuvable')
            ->expectsOutputToContain('ligne introuvable')
            ->expectsOutputToContain('gare ambiguë (2 correspondances)')
            ->expectsOutputToContain('source_url : lien https')
            ->assertSuccessful();
        // Seul le couple valide est retiré : les gares dont l'exclusion est ambiguë gardent leur lien.
        $this->assertSame(['hub-bis@'.$alpha->slug => 'segment', 'hub-ter@'.$alpha->slug => 'segment'], $this->links());
    }

    /**
     * Inclusion : le lien manquant (gare du RER C, ligne fret seul voisine) est créé avec la méthode reviewed_link, dans les
     * trois modes, sans réécrire un lien existant ; une exclusion du même couple l'emporte.
     */
    public function test_an_included_link_is_created_and_an_exclusion_wins_over_it(): void
    {
        $alpha = $this->rfnLine('100000', 'Ligne de Alpha à Bravo');
        $this->track($alpha, $this->current, [[48.00, 2.0], [48.10, 2.0]]);
        $far = $this->rfnLine('200000', 'Ligne de Charlie à Delta');
        $this->track($far, $this->current, [[49.00, 3.0], [49.10, 3.0]]);
        $this->station('hub', 'Hub', 48.0450, 2.0100);
        $entry = ['station' => 'hub', 'line' => '200000', 'reason' => 'gare desservie par cette ligne', 'source_url' => self::SOURCE, 'reviewed_at' => '2026-11-23'];
        $this->exclude([]);

        $this->app->instance(StationLineInclusions::class, new StationLineInclusions([$entry]));
        $this->artisan('vff:attach-stations')->expectsOutputToContain('inclusions : 1 ajoutées sur 1 valides')->assertSuccessful();
        $this->assertSame(['hub@'.$far->slug => 'reviewed_link'], array_filter($this->links(), fn ($m) => $m === 'reviewed_link'));
        foreach ([['--years-only' => true], ['--termini-only' => true]] as $options) {
            $this->artisan('vff:attach-stations', $options)->expectsOutputToContain('inclusions : 0 ajoutées sur 1 valides')->assertSuccessful();
            $this->assertSame('reviewed_link', $this->links()['hub@'.$far->slug]);
        }

        $this->exclude([['station' => 'hub', 'line' => '200000']]);
        $this->artisan('vff:attach-stations')->assertSuccessful();
        $this->assertArrayNotHasKey('hub@'.$far->slug, $this->links());
    }
}
