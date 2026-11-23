<?php

namespace Tests\Feature;

use App\Console\Commands\ConsolidateStations;
use App\Services\StationCorrections;
use App\Support\StationNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * vff:consolidate-stations : deux règles de rapprochement des sources.
 *   - UIC ET nom identiques au-delà de 5 km : la position de la source est fausse
 *     (Wikidata d'Aigueperse à 6,8 km de la gare), ce n'est pas une autre gare ;
 *   - « X-Marchandises » (gare fret) n'est pas la gare de voyageurs de X voisine
 *     (Gif-sur-Yvette-Marchandises à 230 m de la gare RER de Gif-sur-Yvette) ;
 *   - un lien défait (correction « unlink ») : la gare ne prend pas le Q-id d'une autre gare.
 */
class ConsolidateStationsRulesTest extends TestCase
{
    use RefreshDatabase;

    /** @param  array<string, mixed>  $attributes */
    private function source(string $source, string $ref, string $name, float $lat, float $lng, array $attributes = []): int
    {
        return DB::table('station_sources')->insertGetId($attributes + [
            'source' => $source, 'source_ref' => $ref, 'name' => $name, 'name_norm' => StationNames::normalize($name),
            'kind' => 'gare', 'lifecycle' => 'active', 'seen_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            'geom' => DB::raw(sprintf('ST_SetSRID(ST_MakePoint(%F, %F), 4326)', $lng, $lat)),
        ]);
    }

    public function test_same_uic_and_name_far_away_is_the_same_station_with_the_sncf_position(): void
    {
        $sncf = $this->source('sncf', '87734129', 'Aigueperse', 46.01474, 3.19820, ['uic_code' => '87734129', 'passenger' => true]);
        // Coordonnées Wikidata dans une autre commune, à 6,8 km : même UIC (sans clé), même nom.
        $wd = $this->source('wikidata', 'Q3095589', 'Aigueperse', 46.05512, 3.12175, ['uic_code' => '8773412', 'wikidata_id' => 'Q3095589']);
        // Même UIC mais AUTRE nom à 7 km : rien ne prouve que c'est la même gare.
        $other = $this->source('wikidata', 'Q999', 'Effiat', 46.05, 3.27, ['uic_code' => '8773412', 'wikidata_id' => 'Q999']);

        $this->artisan('vff:consolidate-stations')->assertSuccessful();

        $links = DB::table('station_sources')->pluck('station_id', 'id');
        $this->assertSame($links[$sncf], $links[$wd]);
        $this->assertNotSame($links[$sncf], $links[$other]);
        $station = DB::table('stations')->where('id', $links[$sncf])->first(['name', 'wikidata_id', 'position_source']);
        $this->assertSame(['Aigueperse', 'Q3095589', 'sncf'], [$station->name, $station->wikidata_id, $station->position_source]);
    }

    public function test_station_known_only_from_line_lists_takes_the_most_precise_placement(): void
    {
        // Même gare placée par deux listes par ligne (vff:import-station-sources --source=pk) : par sa commune
        // (lignes-oubliées, ± 900 m) et par son PK calé (schéma Wikipédia, ± 180 m), à 300 m l'une de l'autre.
        $lo = $this->source('lignes-oubliees', 'lo-1', 'Saint-Oustrille', 47.0, 2.1000, ['lifecycle' => 'unknown',
            'properties' => json_encode(['precision_m' => 900, 'geocoding' => 'commune'])]);
        $wp = $this->source('wikipedia_schema', 'L1:abc', 'Saint-Oustrille', 47.0, 2.1040, ['lifecycle' => 'unknown',
            'properties' => json_encode(['precision_m' => 180, 'geocoding' => 'pk'])]);

        $this->artisan('vff:consolidate-stations')->assertSuccessful();

        $links = DB::table('station_sources')->pluck('station_id', 'id');
        $this->assertSame($links[$lo], $links[$wp]);
        $st = DB::table('stations')->where('id', $links[$wp])->selectRaw('ST_X(geom) AS lon, position_source, provenance')->first();
        $this->assertEqualsWithDelta(2.1040, (float) $st->lon, 1e-6);
        $this->assertSame('wikipedia_schema', $st->position_source);
        $prov = json_decode($st->provenance, true);
        $this->assertSame([180, 'pk'], [$prov['geom_precision_m'], $prov['geom_method']]);
    }

    public function test_same_station_placed_by_two_line_lists_up_to_3_km_apart_is_one_station(): void
    {
        // Gare de jonction placée par les listes de ses deux lignes (PK et commune), à 2,4 km l'une de l'autre.
        $a = $this->source('wikipedia_schema', 'L1:a', 'Castéra-Verduzan', 43.80, 0.4300, ['lifecycle' => 'unknown',
            'properties' => json_encode(['precision_m' => 1200, 'geocoding' => 'pk'])]);
        $b = $this->source('lignes-oubliees', 'lo-b', 'Castéra-Verduzan', 43.80, 0.4600, ['lifecycle' => 'unknown',
            'properties' => json_encode(['precision_m' => 800, 'geocoding' => 'commune'])]);
        // Deux objets OSM homonymes à la même distance : rien ne dit que c'est la même gare (règle des 1 500 m).
        $c = $this->source('osm', 'n1', 'Saint-Oustrille', 44.80, 0.4300);
        $d = $this->source('osm', 'n2', 'Saint-Oustrille', 44.80, 0.4600);

        $this->artisan('vff:consolidate-stations')->assertSuccessful();

        $links = DB::table('station_sources')->pluck('station_id', 'id');
        $this->assertSame($links[$a], $links[$b]);
        $this->assertNotSame($links[$c], $links[$d]);
        $this->assertEqualsWithDelta(0.4600, (float) DB::selectOne('SELECT ST_X(geom) AS x FROM stations WHERE id = ?', [$links[$a]])->x, 1e-6,
            'position la plus précise');
    }

    public function test_line_list_station_copied_from_another_sncf_position_joins_the_sncf_station(): void
    {
        // Liste des gares SNCF : Pouillenay à 47,543° N sous une ligne, 47,526° N dans la liste des gares (1,9 km).
        $sncf = $this->source('sncf', '87000010', 'Pouillenay', 47.525751, 4.46336, ['uic_code' => '87000010']);
        $lo = $this->source('lignes-oubliees', 'lo-p', 'Pouillenay', 47.543264, 4.46309, ['lifecycle' => 'unknown',
            'properties' => json_encode(['geocoding' => 'station', 'precision_m' => null])]);

        $this->artisan('vff:consolidate-stations')->assertSuccessful();

        $links = DB::table('station_sources')->pluck('station_id', 'id');
        $this->assertSame($links[$sncf], $links[$lo]);
    }

    public function test_line_list_station_takes_an_old_station_id_only_if_the_names_agree(): void
    {
        // Ancien placement erroné : la gare « Puisieux » d'une liste était rattachée à la gare « Faubourg-de-Guise ».
        $old = DB::table('stations')->insertGetId(['slug' => 'faubourg-de-guise', 'name' => 'Faubourg-de-Guise', 'type' => 'gare',
            'status' => 'closed', 'geom' => DB::raw('ST_SetSRID(ST_MakePoint(3.6, 49.9), 4326)'), 'created_at' => now(), 'updated_at' => now()]);
        $lo = $this->source('lignes-oubliees', 'lo-pu', 'Puisieux', 49.95, 3.62, ['lifecycle' => 'unknown', 'station_id' => $old,
            'properties' => json_encode(['geocoding' => 'pk', 'precision_m' => 250])]);

        $this->artisan('vff:consolidate-stations')->assertSuccessful();

        $st = DB::table('stations')->where('id', DB::table('station_sources')->where('id', $lo)->value('station_id'))->first(['id', 'slug', 'name']);
        $this->assertSame(['puisieux', 'Puisieux'], [$st->slug, $st->name]);
        $this->assertNotSame($old, $st->id);

        // Même cas, gare déjà renommée « Puisieux » mais restée sous le slug « faubourg-de-guise ».
        DB::table('stations')->where('id', $st->id)->update(['slug' => 'faubourg-de-guise-2']);
        $this->artisan('vff:consolidate-stations')->assertSuccessful();
        $this->assertSame('puisieux', DB::table('stations')->where('id', DB::table('station_sources')->where('id', $lo)->value('station_id'))->value('slug'));
    }

    public function test_freight_station_is_not_the_neighbouring_passenger_station(): void
    {
        $fret = $this->source('sncf', '87393330', 'Gif-sur-Yvette-Marchandises', 48.69795, 2.13439,
            ['uic_code' => '87393330', 'lifecycle' => 'disused', 'passenger' => false, 'freight' => false]);
        $wd = $this->source('wikidata', 'Q2276723', 'Gif-sur-Yvette', 48.69833, 2.13750, ['wikidata_id' => 'Q2276723']);
        $osm = $this->source('osm', 'n5838690266', 'Gif-sur-Yvette', 48.69821, 2.13674, ['uic_code' => '8775887', 'wikidata_id' => 'Q2276723']);

        $this->artisan('vff:consolidate-stations')->assertSuccessful();

        $links = DB::table('station_sources')->pluck('station_id', 'id');
        $this->assertNotSame($links[$fret], $links[$wd]);
        $this->assertSame($links[$wd], $links[$osm]);
        $this->assertEqualsCanonicalizing(['Gif-sur-Yvette', 'Gif-sur-Yvette-Marchandises'], DB::table('stations')->pluck('name')->all());
    }

    /**
     * Sens : l'objet OHM « Sens (CFD) » (1901 à 1948, chemins de fer départementaux) à 103 m de la gare SNCF
     * est une autre gare ; son nom ne le rattache plus à Sens, et sa fin ne ferme plus la gare SNCF. Un
     * train touristique (AGRIVAP à Ambert) roule dans la gare du grand réseau : même gare.
     */
    public function test_ended_ohm_station_of_another_network_is_not_joined_by_name_to_the_sncf_station(): void
    {
        $sncf = $this->source('sncf', '87683003', 'Sens', 48.1990, 3.2830, ['uic_code' => '87683003', 'passenger' => true]);
        $cfd = $this->source('ohm', 'n2181759785', 'Sens (CFD)', 48.1999, 3.2832, ['lifecycle' => 'historic',
            'opened_on' => '1901-11-10', 'opened_precision' => 'day', 'closed_on' => '1948-03-31', 'closed_precision' => 'day',
            'properties' => json_encode(['name' => 'Sens (CFD)', 'railway' => 'station'])]);
        $amb = $this->source('sncf', '87734004', 'Ambert', 45.5490, 3.7400, ['uic_code' => '87734004', 'freight' => true]);
        $agrivap = $this->source('ohm', 'n2135527209', 'Gare d\'Ambert', 45.5490, 3.74001, ['lifecycle' => 'historic',
            'opened_on' => '1992-09-18', 'opened_precision' => 'day', 'closed_on' => '2025-06-22', 'closed_precision' => 'day',
            'properties' => json_encode(['railway' => 'station', 'operator' => 'AGRIVAP - Les Trains de la Découverte'])]);

        $this->artisan('vff:consolidate-stations')->assertSuccessful();

        $links = DB::table('station_sources')->pluck('station_id', 'id');
        $this->assertNotSame($links[$sncf], $links[$cfd]);
        $this->assertSame($links[$amb], $links[$agrivap]);
        $this->assertFalse(DB::table('station_events')->where('station_id', $links[$sncf])->where('type', 'closure')->exists());
    }

    /**
     * Maubeuge : le bâtiment OHM de 1863 à 1976 (building=train_station) se rattache à la gare SNCF au même
     * endroit, mais sa fin n'est pas une fermeture de la gare, ouverte aux voyageurs aujourd'hui. Une gare
     * fret seul (Bavay, voyageurs supprimés en 1969) garde la fermeture OHM, comme une gare dont une autre
     * source donne la réouverture après.
     */
    public function test_ohm_end_does_not_close_a_station_the_sncf_says_open_to_passengers(): void
    {
        $ohm = fn (string $closed) => ['lifecycle' => 'historic', 'opened_on' => '1863-07-01',
            'opened_precision' => 'day', 'closed_on' => $closed, 'closed_precision' => 'day',
            'properties' => json_encode(['railway' => 'station', 'building' => 'train_station'])];
        $maubeuge = $this->source('sncf', '87295006', 'Maubeuge', 50.2780, 3.9660, ['uic_code' => '87295006', 'passenger' => true]);
        $this->source('ohm', 'r2892705', 'Maubeuge', 50.2780, 3.9660, $ohm('1976-06-30'));
        $bavay = $this->source('sncf', '87295105', 'Bavay', 50.3000, 3.8000, ['uic_code' => '87295105', 'freight' => true]);
        $this->source('ohm', 'n2136877816', 'Bavay', 50.3000, 3.8000, $ohm('1969-09-28'));

        $this->artisan('vff:consolidate-stations')->assertSuccessful();

        $links = DB::table('station_sources')->pluck('station_id', 'id');
        $closure = fn (int $source) => DB::table('station_events')->where('station_id', $links[$source])->where('type', 'closure')->value('event_date');
        $this->assertNull($closure($maubeuge));
        $this->assertSame('1969-09-28', $closure($bavay));
    }

    public function test_ohm_network_and_closure_rules(): void
    {
        $other = [ConsolidateStations::class, 'ohmOtherNetwork'];
        $this->assertTrue($other([], 'Souppes (CFD)'));
        $this->assertTrue($other(['operator' => 'TVT'], 'Lavaur'));
        $this->assertTrue($other(['operator' => 'NStCM'], 'Morez PLM'));
        $this->assertTrue($other(['operator' => 'CFSO (compagnie des chemins de fer du Sud-Ouest)'], 'X'));
        $this->assertTrue($other(['railway' => 'tram_stop'], 'X'));
        $this->assertFalse($other([], 'Montereau'));
        $this->assertFalse($other(['operator' => 'PLM puis SNCF'], 'X'));
        $this->assertFalse($other(['operator' => 'Chemin de Fer du Haut-Forez (CFHF)'], 'X'));
        $this->assertFalse($other(['operator' => 'AGRIVAP - Les Trains de la Découverte'], 'X'));

        $keeps = [ConsolidateStations::class, 'keepsOhmClosure'];
        $this->assertTrue($keeps('1959-01-01', false, []));
        $this->assertFalse($keeps('1959-01-01', true, []));
        $this->assertTrue($keeps('1980-01-01', true, [['type' => 'reopening', 'event_date' => '2012-12-09']]));
        $this->assertFalse($keeps('1980-01-01', true, [['type' => 'reopening', 'event_date' => '1950-01-01'], ['type' => 'opening', 'event_date' => '2000-01-01']]));
    }

    /** @return array<string, mixed> */
    private function unlink(string $ref, string $name = 'St-Vidal'): array
    {
        return ['station' => ['name' => $name, 'uic' => '87731844'], 'action' => 'unlink', 'source' => 'wikidata', 'ref' => $ref,
            'source_url' => 'https://www.wikidata.org/wiki/'.$ref, 'reason' => $ref.' est une autre gare'];
    }

    private function stationOf(int $source): object
    {
        return DB::table('stations')->where('id', DB::table('station_sources')->where('id', $source)->value('station_id'))->first();
    }

    public function test_unlinked_wikidata_tag_is_not_taken_by_the_station(): void
    {
        $vidal = $this->source('sncf', '87731844', 'St-Vidal', 45.07190, 3.80322, ['uic_code' => '87731844', 'lifecycle' => 'disused']);
        // Objet OHM de la halte, tagué avec le Q-id de la gare du Puy (lien faux) ; l'élément Wikidata est au Puy,
        // à 7 km : sans correction, la halte prend ce Q-id par le tag.
        $ohm = $this->source('ohm', 'n2125740909', 'Gare du Saint-Vidal', 45.07210, 3.80340, ['wikidata_id' => 'Q1891124', 'lifecycle' => 'unknown']);
        $wd = $this->source('wikidata', 'Q1891124', 'Le Puy-en-Velay', 45.04278, 3.89257, ['wikidata_id' => 'Q1891124']);

        $this->app->instance(StationCorrections::class, new StationCorrections([]));
        $this->artisan('vff:consolidate-stations')->assertSuccessful();
        $this->assertSame('Q1891124', $this->stationOf($vidal)->wikidata_id);

        $this->app->instance(StationCorrections::class, new StationCorrections([$this->unlink('Q1891124')]));
        $this->artisan('vff:consolidate-stations')->expectsOutputToContain('lien défait St-Vidal / wikidata Q1891124 : 1 gare(s)')->assertSuccessful();

        $this->assertNull($this->stationOf($vidal)->wikidata_id, 'le Q-id faux est ignoré');
        $this->assertSame('St-Vidal', $this->stationOf($vidal)->name);
        $this->assertSame($this->stationOf($vidal)->id, $this->stationOf($ohm)->id, 'l\'objet OHM reste rattaché à la halte (ses dates aussi)');
        $this->assertSame('Q1891124', $this->stationOf($wd)->wikidata_id);
    }

    public function test_unlinked_wikidata_item_does_not_join_the_station(): void
    {
        $vidal = $this->source('sncf', '87731844', 'St-Vidal', 45.07190, 3.80322, ['uic_code' => '87731844', 'lifecycle' => 'disused']);
        // Élément Wikidata d'une autre gare posé par erreur à 30 m de la halte : rattaché par la distance (≤ 60 m).
        $near = $this->source('wikidata', 'Q777', 'Le Monteil', 45.07215, 3.80310, ['wikidata_id' => 'Q777']);

        $this->app->instance(StationCorrections::class, new StationCorrections([]));
        $this->artisan('vff:consolidate-stations')->assertSuccessful();
        $this->assertSame($this->stationOf($vidal)->id, $this->stationOf($near)->id);
        $this->assertSame('Q777', $this->stationOf($vidal)->wikidata_id);

        $this->app->instance(StationCorrections::class, new StationCorrections([$this->unlink('Q777')]));
        $this->artisan('vff:consolidate-stations')->assertSuccessful();

        $this->assertNull($this->stationOf($vidal)->wikidata_id);
        $this->assertNotSame($this->stationOf($vidal)->id, $this->stationOf($near)->id);
        $this->assertSame('Q777', $this->stationOf($near)->wikidata_id);
    }

    /** @return array<string, mixed> */
    private function link(array $station, string $ref): array
    {
        return ['station' => $station, 'action' => 'link', 'source' => 'wikidata', 'ref' => $ref,
            'source_url' => 'https://www.wikidata.org/wiki/'.$ref, 'reason' => $ref.' porte le meme UIC'];
    }

    public function test_link_gives_the_wikidata_id_found_by_uic_or_by_name_and_position(): void
    {
        $autet = $this->source('sncf', '87185538', 'Autet', 47.50000, 5.80000, ['uic_code' => '87185538', 'lifecycle' => 'disused']);
        $faulx = $this->source('osm', 'n7', 'Faulx', 48.80000, 6.20000, ['lifecycle' => 'disused']);
        $other = $this->source('osm', 'n8', 'Faulx', 48.90000, 6.20000, ['lifecycle' => 'disused']);

        $this->app->instance(StationCorrections::class, new StationCorrections([]));
        $this->artisan('vff:consolidate-stations')->assertSuccessful();
        $this->assertNull($this->stationOf($autet)->wikidata_id);

        $this->app->instance(StationCorrections::class, new StationCorrections([
            $this->link(['name' => 'Autet', 'uic' => '87185538'], 'Q120757199'),
            $this->link(['name' => 'Faulx', 'near' => [6.2, 48.8, 0.3]], 'Q106938310'),
        ]));
        $this->artisan('vff:consolidate-stations')->expectsOutputToContain('rattachement Autet / wikidata Q120757199 : 1 objet(s)')->assertSuccessful();

        $this->assertSame('Q120757199', $this->stationOf($autet)->wikidata_id);
        $this->assertSame('Q106938310', $this->stationOf($faulx)->wikidata_id);
        $this->assertNull($this->stationOf($other)->wikidata_id, 'l\'homonyme a 11 km n\'est pas rattache');
        $this->assertSame('correction', json_decode((string) $this->stationOf($autet)->provenance, true)['wikidata_id'] ?? null);
    }

    public function test_link_never_replaces_a_wikidata_id_carried_by_the_station(): void
    {
        $autet = $this->source('sncf', '87185538', 'Autet', 47.50000, 5.80000, ['uic_code' => '87185538', 'lifecycle' => 'disused']);
        $this->source('wikidata', 'Q1', 'Autet', 47.50001, 5.80001, ['wikidata_id' => 'Q1', 'uic_code' => '87185538']);

        $this->app->instance(StationCorrections::class, new StationCorrections([$this->link(['name' => 'Autet', 'uic' => '87185538'], 'Q2')]));
        $this->artisan('vff:consolidate-stations')->assertSuccessful();

        $this->assertSame('Q1', $this->stationOf($autet)->wikidata_id);
    }

    public function test_wikidata_item_linked_by_a_correction_joins_its_station_with_its_declarations(): void
    {
        $sncf = $this->source('sncf', '87000001', 'Grandpuits', 48.60000, 2.94000, ['uic_code' => '87000001', 'freight' => true, 'lifecycle' => 'disused']);
        // Élément de marchandises : autre nom, pas d'UIC, à 800 m (coordonnées d'élément placées sur l'emprise).
        $wd = $this->source('wikidata', 'Q55', 'Terminal du raffineur', 48.60000, 2.95100, ['wikidata_id' => 'Q55', 'lifecycle' => 'disused',
            'opened_on' => '1902-01-01', 'opened_precision' => 'year',
            'events' => json_encode([['type' => 'opening', 'date' => '1902-01-01', 'precision' => 'year', 'prop' => 'P1619', 'applies_to' => []]])]);

        $this->app->instance(StationCorrections::class, new StationCorrections([]));
        $this->artisan('vff:consolidate-stations')->assertSuccessful();
        $this->assertNotSame($this->stationOf($sncf)->id, $this->stationOf($wd)->id, 'sans correction : deux gares');

        $this->app->instance(StationCorrections::class, new StationCorrections([$this->link(['name' => 'Grandpuits', 'uic' => '87000001'], 'Q55')]));
        $this->artisan('vff:consolidate-stations')->assertSuccessful();

        $this->assertSame($this->stationOf($sncf)->id, $this->stationOf($wd)->id);
        $this->assertSame('Q55', $this->stationOf($sncf)->wikidata_id);
        $this->assertSame('sncf', $this->stationOf($sncf)->position_source, 'la position reste celle de la liste SNCF');
        $this->assertTrue(DB::table('station_events')->where('station_id', $this->stationOf($sncf)->id)->where('type', 'opening')->where('source', 'wikidata')->exists());
    }

    public function test_unlink_naming_another_station_has_no_effect(): void
    {
        $vidal = $this->source('sncf', '87731844', 'St-Vidal', 45.07190, 3.80322, ['uic_code' => '87731844']);
        $this->source('ohm', 'n2125740909', 'Gare du Saint-Vidal', 45.07210, 3.80340, ['wikidata_id' => 'Q1891124', 'lifecycle' => 'unknown']);
        // Bon UIC, mauvais nom (contrôle) : la correction ne désigne aucune gare.
        $this->app->instance(StationCorrections::class, new StationCorrections([$this->unlink('Q1891124', 'Le Puy-en-Velay')]));

        $this->artisan('vff:consolidate-stations')->expectsOutputToContain('gare introuvable dans les sources')->assertSuccessful();

        $this->assertSame('Q1891124', $this->stationOf($vidal)->wikidata_id);
    }
}
