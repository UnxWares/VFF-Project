<?php

namespace Tests\Feature;

use App\Models\Era;
use App\Models\Line;
use App\Models\LineSegment;
use App\Models\Station;
use App\Services\LineStations;
use Clickbar\Magellan\Data\Geometries\LineString;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Ère courante = le jour même (vff:build-station-eras --today) et règle
 * « une gare est aussi fermée si juste elle n'est plus exploitée » : une gare
 * fermée sur une ligne restée en service est fermée sur la carte, la fiche gare
 * et la fiche ligne, avec la même date.
 */
class StationCurrentEraTest extends TestCase
{
    use RefreshDatabase;

    private Era $current;

    private Era $past;

    private Line $line;

    protected function setUp(): void
    {
        parent::setUp();
        $this->past = Era::factory()->create(['year' => 2020]);
        $this->current = Era::factory()->current()->create(['year' => 2030, 'label' => 'Aujourd\'hui']);
        $this->line = Line::factory()->create(['code' => '900100', 'name' => 'Ligne témoin']);
        foreach ([$this->past, $this->current] as $era) {
            LineSegment::factory()->create([
                'line_id' => $this->line->id, 'era_id' => $era->id, 'status' => 'in_service', 'traffic_type' => 'mixed', 'is_heritage' => false,
                'geom' => LineString::make([Point::makeGeodetic(47.50, 2.00), Point::makeGeodetic(47.50, 2.20)]),
            ]);
        }
    }

    /** Gare de la ligne témoin, sources données (source => cycle de vie). */
    private function station(string $name, array $sources, string $status = 'active', string $statusSource = 'sncf', float $lon = 2.10, int $pk = 1000): Station
    {
        $station = Station::factory()->create([
            'name' => $name, 'geom' => Point::makeGeodetic(47.50, $lon), 'status' => $status, 'source_status' => $status,
            'position_source' => 'sncf', 'provenance' => ['status' => $statusSource], 'opened_at' => null,
        ]);
        foreach ($sources as $source => $lifecycle) {
            DB::table('station_sources')->insert([
                'source' => $source, 'source_ref' => "{$name}-{$source}", 'station_id' => $station->id, 'name' => $name,
                'lifecycle' => $lifecycle, 'passenger' => $source === 'sncf', 'freight' => false,
                'seen_at' => '2026-10-15 17:08:34', 'created_at' => now(), 'updated_at' => now(),
                'geom' => DB::raw("ST_SetSRID(ST_MakePoint({$lon}, 47.50), 4326)"),
            ]);
        }
        DB::table('station_lines')->insert(['station_id' => $station->id, 'line_id' => $this->line->id, 'method' => 'sncf_pk',
            'pk_m' => $pk, 'pk_source' => 'sncf', 'distance_m' => 0, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('station_events')->insert(['station_id' => $station->id, 'type' => 'opening', 'event_date' => '1880-01-01',
            'date_precision' => 'year', 'source' => 'wikipedia', 'is_inferred' => false, 'created_at' => now(), 'updated_at' => now()]);

        return $station;
    }

    private function eraStatus(Station $station, Era $era): ?string
    {
        return DB::table('station_eras')->where('station_id', $station->id)->where('era_id', $era->id)->value('status');
    }

    private function build(string $today): void
    {
        $this->artisan('vff:build-station-eras', ['--today' => $today])->assertSuccessful();
    }

    public function test_closed_at_today_closes_the_station_although_its_line_stays_open(): void
    {
        // Relevé gares-de-voyageurs du 15/10 : la gare était desservie ; fermeture saisie au 19/10.
        $station = $this->station('Gare fermée ce jour', ['sncf' => 'active', 'sncf_voyageurs' => 'active', 'osm' => 'active']);
        $station->update(['closed_at' => '2026-10-19', 'closed_precision' => 'day']);

        $this->build('2026-10-18');
        $this->assertSame('active', $this->eraStatus($station, $this->current), 'veille : encore ouverte');
        $this->assertSame('2026-10-19', $station->fresh()->closed_at->format('Y-m-d'), 'fermeture à venir gardée');

        $this->build('2026-10-19');
        $this->assertSame('declassified', $this->eraStatus($station, $this->current));
        $this->assertSame('active', $this->eraStatus($station, $this->past));
        $fresh = $station->fresh();
        $this->assertSame('declassified', $fresh->status->value);
        $this->assertSame(['2026-10-19', 'day', 'manual'], [$fresh->closed_at->format('Y-m-d'), $fresh->closed_precision, $fresh->provenance['closed_at']]);

        // Carte, fiche gare et fiche ligne : fermée, même date.
        $feature = collect($this->getJson('/api/v1/eras/2030/stations.geojson')->assertOk()->json('features'))->firstWhere('properties.name', 'Gare fermée ce jour');
        $this->assertSame(['closed', '2026-10-19'], [$feature['properties']['service'], $feature['properties']['closed_at']]);
        $page = $this->get('/stations/'.$station->slug)->assertOk()->viewData('page')['props'];
        $this->assertSame('2026-10-19', $page['station']['closed_at']);
        $this->assertSame('declassified', collect($page['eras'])->firstWhere('year', 2030)['status']);
        $row = collect((new LineStations)->forLine($this->line->id)['stations'])->firstWhere('id', $station->id);
        $this->assertSame(['2026-10-19', 'declassified'], [$row['closed_at'], $row['status_today']]);
    }

    public function test_dated_station_event_today_closes_and_tomorrow_waits(): void
    {
        $today = $this->station('Halte du jour', ['sncf' => 'active', 'sncf_voyageurs' => 'active']);
        $tomorrow = $this->station('Halte de demain', ['sncf' => 'active', 'sncf_voyageurs' => 'active'], lon: 2.12, pk: 3000);
        foreach ([[$today, '2026-10-19'], [$tomorrow, '2026-10-20']] as [$s, $date]) {
            DB::table('station_events')->insert(['station_id' => $s->id, 'type' => 'closure', 'event_date' => $date, 'date_precision' => 'day',
                'source' => 'wikipedia', 'is_inferred' => false, 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->build('2026-10-19');
        $this->assertSame('declassified', $this->eraStatus($today, $this->current));
        $this->assertSame('active', $this->eraStatus($tomorrow, $this->current));
        $this->assertNull($tomorrow->fresh()->closed_at);

        $this->build('2026-10-20');
        $this->assertSame('declassified', $this->eraStatus($tomorrow, $this->current));
        $this->assertSame('2026-10-20', $tomorrow->fresh()->closed_at->format('Y-m-d'));
    }

    public function test_station_no_longer_served_is_closed_on_an_open_line(): void
    {
        // Ère 1890 : l'ouverture de 1880 n'est une preuve que pour elle.
        $era1890 = Era::factory()->create(['year' => 1890]);
        LineSegment::factory()->create([
            'line_id' => $this->line->id, 'era_id' => $era1890->id, 'status' => 'in_service', 'traffic_type' => 'mixed', 'is_heritage' => false,
            'geom' => LineString::make([Point::makeGeodetic(47.50, 2.00), Point::makeGeodetic(47.50, 2.20)]),
        ]);
        // Drapeau « voyageurs » de liste-des-gares seul, OSM et Wikidata la disent désaffectée.
        $stale = $this->station('Halte désaffectée', ['sncf' => 'active', 'osm' => 'disused', 'wikidata' => 'disused']);
        // Absente de gares-de-voyageurs mais en service selon OSM : ouverte.
        $osm = $this->station('Halte OSM', ['sncf' => 'active', 'osm' => 'active'], lon: 2.12, pk: 3000);
        // Aucune preuve de fermeture (tram-train hors gares-de-voyageurs) : ouverte.
        $unknown = $this->station('Halte tram-train', ['sncf' => 'active'], lon: 2.14, pk: 5000);
        $this->build('2026-10-19');

        $this->assertSame('declassified', $this->eraStatus($stale, $this->current));
        $this->assertStringContainsString('Plus desservie', (string) DB::table('station_eras')->where('station_id', $stale->id)->where('era_id', $this->current->id)->value('note'));
        $this->assertSame('active', $this->eraStatus($osm, $this->current));
        $this->assertSame('active', $this->eraStatus($unknown, $this->current));

        // Fermeture non datée : l'ère passée n'est plus affirmée ouverte, la date est l'intervalle des ères.
        $past = DB::table('station_eras')->where('station_id', $stale->id)->where('era_id', $this->past->id)->first();
        $this->assertTrue((bool) $past->uncertain);
        $fresh = $stale->fresh();
        $this->assertSame(['range', '2026-10-19', 1890], [$fresh->closed_precision, $fresh->closed_at->format('Y-m-d'), $fresh->provenance['closed_after']]);
    }

    public function test_line_keeps_all_its_stations_with_their_dates(): void
    {
        $open = $this->station('Gare ouverte', ['sncf' => 'active', 'sncf_voyageurs' => 'active']);
        $closed = $this->station('Gare fermée 2011', ['sncf' => 'disused', 'wikidata' => 'disused'], 'declassified', 'sncf', 2.12, 3000);
        DB::table('station_events')->insert(['station_id' => $closed->id, 'type' => 'closure', 'event_date' => '2011-12-11', 'date_precision' => 'day',
            'source' => 'wikipedia', 'is_inferred' => false, 'created_at' => now(), 'updated_at' => now()]);
        $this->build('2026-10-19');

        $data = (new LineStations)->forLine($this->line->id);
        $this->assertSame(['Gare ouverte', 'Gare fermée 2011'], array_column($data['stations'], 'name'));
        // Terminus = gare à moins de 3 km d'un bout du tracé (LineStations::terminals, contrôle qualité
        // du 2026-10-20), plus « première et dernière gare à PK » : ces deux gares sont à 7,5 km et 6 km
        // des bouts de la ligne témoin (2,00 - 2,20° E), ce sont des gares intermédiaires.
        $this->assertSame([false, false], array_column($data['stations'], 'is_terminal'));
        $this->assertSame(['1+000', '3+000'], array_column($data['stations'], 'pk_label'));
        [$o, $c] = $data['stations'];
        $this->assertSame(['active', 'active'], [$o['eras'][2020]['status'], $o['eras'][2030]['status']]);
        $this->assertSame('2011-12-11', $c['closed_at']);
        $this->assertSame(2011, $c['closed_year']);
        $this->assertSame('declassified', $c['eras'][2020]['status']);

        $atToday = (new LineStations)->atEra($this->line->id, 2030);
        $this->assertSame(['active', 'declassified'], array_column($atToday, 'era_status'));

        $byEra = collect((new LineStations)->byEra($this->line->id))->keyBy('year');
        $this->assertSame(['11/12/2011'], array_column($byEra[2020]['closures'], 'label'));
        $this->assertSame([1, 1, 0], [$byEra[2020]['open'], $byEra[2020]['closed'], $byEra[2020]['uncertain']]);
        $this->assertSame([], $byEra[2030]['closures']);
    }
}
