<?php

namespace Tests\Feature\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * vff:import-station-passenger-ends : fins du service voyageurs lues dans les
 * infobox Wikipédia enregistrées (cache disque, jamais de réseau), rattachées
 * par lien aux seules gares fermées ou fret seul aujourd'hui.
 */
class ImportStationPassengerEndsTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
        Http::fake(['*' => Http::response([], 500)]); // --from-cache : jamais de réseau
    }

    private function cachePage(string $title, string $infobox, string $body = 'La gare est fermée.', int $revid = 4242): void
    {
        $dir = storage_path('app/sources/stations/wikipedia');
        File::ensureDirectoryExists($dir);
        File::put($dir.'/'.sha1($title).'.json', json_encode([
            'title' => $title, 'revid' => $revid, 'content' => "{{Infobox Gare\n | nom = x\n{$infobox}\n}}\n{$body}", 'requested' => $title,
        ]));
    }

    /** Gare liée à un article par un élément Wikidata ($qid principal ou secondaire) ou par le tag OSM. */
    private function closedStation(string $slug, string $article, string $status = 'no_commercial', ?string $qid = null, string $via = 'wikidata'): int
    {
        $qid ??= 'Q'.abs(crc32($slug));
        $id = $this->station($slug, ucfirst($slug), 48.0, 2.0, ['status' => $status === 'gone' ? 'declassified' : $status,
            'source_status' => $status, 'wikidata_id' => $qid])->id;
        $this->link($id, $article, $via === 'wikidata' ? $qid : null);

        return $id;
    }

    private function link(int $stationId, string $article, ?string $qid): void
    {
        DB::table('station_sources')->insert([
            'source' => $qid ? 'wikidata' : 'osm', 'source_ref' => $qid ?? 'n'.$stationId, 'station_id' => $stationId, 'name' => 'x', 'lifecycle' => 'disused',
            'properties' => json_encode($qid ? ['frwiki' => $article] : ['wikipedia' => 'fr:'.$article]),
            'seen_at' => now(), 'created_at' => now(), 'updated_at' => now(), 'geom' => DB::raw('ST_SetSRID(ST_MakePoint(2.0, 48.0), 4326)'),
        ]);
    }

    /** @return list<string> */
    private function events(int $stationId): array
    {
        return DB::table('station_events')->where('station_id', $stationId)->orderBy('event_date')
            ->get(['type', 'event_date', 'date_precision', 'source', 'source_ref'])
            ->map(fn ($e) => "{$e->type} {$e->event_date} {$e->date_precision} {$e->source} {$e->source_ref}")->all();
    }

    public function test_date_formats_of_the_infobox_give_one_passenger_end(): void
    {
        $day = $this->closedStation('quittebeuf', 'Gare de Quittebeuf');
        $this->cachePage('Gare de Quittebeuf', ' | fermeture = {{date|27 septembre 1969}} <small>(voyageurs)</small><br>{{date|30 novembre 1990}} <small>(marchandises)</small>');
        $year = $this->closedStation('blain', 'Gare de Blain', 'gone');
        $this->cachePage('Gare de Blain', ' | fermeture = 1939 voyageurs<br />1952 marchandises');
        $before = $this->closedStation('angoville', "Gare d'Angoville", 'gone');
        $this->cachePage("Gare d'Angoville", ' | fermeture = voyageurs : 1970<br />marchandises : {{date|24 janvier 1988}}');
        $decade = $this->closedStation('rehon', 'Gare de Réhon');
        $this->cachePage('Gare de Réhon', ' | fermeture = années 1970 (voyageurs)<br />Halte détruite en 1985');
        $month = $this->closedStation('aigues', 'Gare d\'Aigues', 'declassified', via: 'osm');
        $this->cachePage('Gare d\'Aigues', ' | fermeture = 2 novembre 2009 (Fret)<br />décembre 2011 (voyageurs)');
        // La portée « (ligne) » de la seconde date n'est pas celle de la première.
        $line = $this->closedStation('anderny', "Gare d'Anderny", 'gone');
        $this->cachePage("Gare d'Anderny", ' | fermeture = 15 mai 1939 (voyageurs)<br />1991 (ligne)');

        $this->artisan('vff:import-station-passenger-ends', ['--from-cache' => true])->assertSuccessful();

        $this->assertSame(['closure_passenger 1969-09-27 day wikipedia gare:Gare de Quittebeuf#infobox-voyageurs',
            'closure_freight 1990-11-30 day wikipedia gare:Gare de Quittebeuf#infobox-fret'], $this->events($day));
        $this->assertSame(['closure_passenger 1939-01-01 year wikipedia gare:Gare de Blain#infobox-voyageurs',
            'closure_freight 1952-01-01 year wikipedia gare:Gare de Blain#infobox-fret'], $this->events($year));
        $this->assertSame(["closure_passenger 1970-01-01 year wikipedia gare:Gare d'Angoville#infobox-voyageurs",
            "closure_freight 1988-01-24 day wikipedia gare:Gare d'Angoville#infobox-fret"], $this->events($before));
        $this->assertSame(['closure_passenger 1970-01-01 decade wikipedia gare:Gare de Réhon#infobox-voyageurs'], $this->events($decade));
        $this->assertSame(["closure_freight 2009-11-02 day wikipedia gare:Gare d'Aigues#infobox-fret",
            "closure_passenger 2011-12-01 month wikipedia gare:Gare d'Aigues#infobox-voyageurs"], $this->events($month));
        $this->assertSame(["closure_passenger 1939-05-15 day wikipedia gare:Gare d'Anderny#infobox-voyageurs"], $this->events($line));

        $row = DB::table('station_events')->where('station_id', $day)->first();
        $this->assertFalse((bool) $row->is_inferred);
        $this->assertSame('https://fr.wikipedia.org/w/index.php?title=Gare_de_Quittebeuf&oldid=4242', $row->source_url);
        $this->assertStringStartsWith('« Fermeture : 27 septembre 1969 (voyageurs)', $row->note);
    }

    public function test_nothing_without_an_explicit_passenger_end(): void
    {
        $absent = $this->closedStation('sans-date', 'Gare sans date');
        $this->cachePage('Gare sans date', ' | mise en service = 1872');
        $plain = $this->closedStation('tout', 'Gare tout trafic');
        $this->cachePage('Gare tout trafic', ' | fermeture = {{date|6 mars 1972}}');
        $hedged = $this->closedStation('incertaine', 'Gare incertaine');
        $this->cachePage('Gare incertaine', ' | fermeture = vers 1950 (voyageurs)');
        $notStation = $this->closedStation('ligne', 'Ligne de Alpha à Bravo');
        File::put(storage_path('app/sources/stations/wikipedia/'.sha1('Ligne de Alpha à Bravo').'.json'), json_encode([
            'title' => 'Ligne de Alpha à Bravo', 'revid' => 1, 'content' => "{{Infobox Ligne ferroviaire\n | fermeture = 1938 (voyageurs)\n}}",
        ]));
        $twoDates = $this->closedStation('deux', 'Gare de Soual');
        $this->cachePage('Gare de Soual', ' | fermeture = 15 mai 1939 officielle du service voyageurs 1945 définitive du service voyageurs');

        $this->artisan('vff:import-station-passenger-ends', ['--from-cache' => true])->assertSuccessful();

        foreach ([$absent, $plain, $hedged, $notStation, $twoDates] as $sid) {
            $this->assertSame([], $this->events($sid));
        }
    }

    /**
     * Fin du fret du même champ : après, avant ou le même jour que les voyageurs, seule, à divers formats ;
     * deux fins du fret différentes ou une fin du fret avant l'ouverture ne sont pas écrites.
     */
    public function test_freight_end_of_the_same_infobox_field_is_written_as_a_sourced_freight_end(): void
    {
        $after = $this->closedStation('livarot', 'Gare de Livarot', 'declassified');
        $this->cachePage('Gare de Livarot', ' | fermeture = 5 mai 1938 (voyageurs)<br />30 septembre 1990 (marchandises)', revid: 7001);
        $sameDay = $this->closedStation('airvault', 'Gare d\'Airvault-Gare');
        $this->cachePage('Gare d\'Airvault-Gare', ' | fermeture = décembre 2015 (voyageurs)<br />décembre 2015 (fret)');
        $only = $this->closedStation('fontenay', 'Gare de Fontenay-le-Comte', 'gone');
        $this->cachePage('Gare de Fontenay-le-Comte', ' | fermeture = 2007 (marchandises)');
        $plural = $this->closedStation('aubignas', 'Gare d\'Aubignas - Alba', 'gone');
        $this->cachePage('Gare d\'Aubignas - Alba', ' | fermeture = 9 mars 1969 aux voyageurs 1 décembre 1971 aux frets');
        $colon = $this->closedStation('cornimont', 'Gare de Cornimont');
        $this->cachePage('Gare de Cornimont', ' | fermeture = 28 mai 1989 voyageurs 27 septembre 1992 marchandises');
        $decade = $this->closedStation('clermont', 'Gare de Clermont-l\'Hérault', 'declassified');
        $this->cachePage('Gare de Clermont-l\'Hérault', ' | fermeture = 15 mai 1939 (voyageurs) Années 1990 (marchandises)');
        $two = $this->closedStation('deux-frets', 'Gare aux deux frets');
        $this->cachePage('Gare aux deux frets', ' | fermeture = 1950 (voyageurs)<br />1960 (marchandises)<br />1965 (marchandises)');
        $early = $this->closedStation('precoce', 'Gare précoce', 'declassified');
        DB::table('station_events')->insert(['station_id' => $early, 'type' => 'opening', 'event_date' => '1900-01-01', 'date_precision' => 'year',
            'source' => 'wikidata', 'is_inferred' => false, 'created_at' => now(), 'updated_at' => now()]);
        $this->cachePage('Gare précoce', ' | fermeture = 1880 (marchandises)');

        $this->artisan('vff:import-station-passenger-ends', ['--from-cache' => true])->assertSuccessful();

        $this->assertSame(['closure_passenger 1938-05-05 day wikipedia gare:Gare de Livarot#infobox-voyageurs',
            'closure_freight 1990-09-30 day wikipedia gare:Gare de Livarot#infobox-fret'], $this->events($after));
        $row = DB::table('station_events')->where('station_id', $after)->where('type', 'closure_freight')->first();
        $this->assertFalse((bool) $row->is_inferred);
        $this->assertSame('https://fr.wikipedia.org/w/index.php?title=Gare_de_Livarot&oldid=7001', $row->source_url);
        $this->assertStringContainsString('30 septembre 1990 (marchandises)', $row->note);
        $this->assertSame(["closure_passenger 2015-12-01 month wikipedia gare:Gare d'Airvault-Gare#infobox-voyageurs",
            "closure_freight 2015-12-01 month wikipedia gare:Gare d'Airvault-Gare#infobox-fret"], $this->events($sameDay));
        $this->assertSame(['closure_freight 2007-01-01 year wikipedia gare:Gare de Fontenay-le-Comte#infobox-fret'], $this->events($only));
        $this->assertSame(["closure_passenger 1969-03-09 day wikipedia gare:Gare d'Aubignas - Alba#infobox-voyageurs",
            "closure_freight 1971-12-01 day wikipedia gare:Gare d'Aubignas - Alba#infobox-fret"], $this->events($plural));
        $this->assertSame(['closure_passenger 1989-05-28 day wikipedia gare:Gare de Cornimont#infobox-voyageurs',
            'closure_freight 1992-09-27 day wikipedia gare:Gare de Cornimont#infobox-fret'], $this->events($colon));
        $this->assertSame(["closure_passenger 1939-05-15 day wikipedia gare:Gare de Clermont-l'Hérault#infobox-voyageurs",
            "closure_freight 1990-01-01 decade wikipedia gare:Gare de Clermont-l'Hérault#infobox-fret"], $this->events($decade));
        // Deux fins du fret : relecture à la main, la fin des voyageurs reste écrite.
        $this->assertSame(['closure_passenger 1950-01-01 year wikipedia gare:Gare aux deux frets#infobox-voyageurs'], $this->events($two));
        $this->assertSame(['opening 1900-01-01 year wikidata '], $this->events($early));

        // Rejeu : la fin du fret disparue de l'article est retirée, la ligne d'une autre commande reste.
        DB::table('station_events')->insert(['station_id' => $after, 'type' => 'closure_freight', 'event_date' => '1991-01-01', 'date_precision' => 'year',
            'source' => 'wikipedia', 'source_ref' => 'gare:Gare de Livarot#texte', 'is_inferred' => false, 'created_at' => now(), 'updated_at' => now()]);
        $this->cachePage('Gare de Livarot', ' | fermeture = 5 mai 1938 (voyageurs)', revid: 7002);
        $this->artisan('vff:import-station-passenger-ends', ['--from-cache' => true])->assertSuccessful();
        $this->assertSame(['closure_passenger 1938-05-05 day wikipedia gare:Gare de Livarot#infobox-voyageurs',
            'closure_freight 1991-01-01 year wikipedia gare:Gare de Livarot#texte'], $this->events($after));
    }

    public function test_link_decides_never_the_name_and_open_stations_are_left_alone(): void
    {
        // Deux gares « Bry-sur-Marne » : l'article SNCF n'est lié qu'à l'ancienne gare.
        $old = $this->closedStation('bry-sncf', 'Gare de Bry-sur-Marne (SNCF)');
        $this->station('bry-rer', 'Bry-sur-Marne', 48.8, 2.5, ['status' => 'active', 'source_status' => 'active']);
        DB::table('stations')->where('id', $old)->update(['name' => 'Bry-sur-Marne']);
        $this->cachePage('Gare de Bry-sur-Marne (SNCF)', ' | fermeture = {{date|15 mai 1939}} (voyageurs)<br>{{date|13 octobre 2009}} (marchandises)');

        // Un article lié à deux gares fermées : gardé pour celle dont c'est l'élément principal.
        $main = $this->closedStation('lencloitre', 'Gare de Lencloître', 'declassified', 'Q100');
        $other = $this->station('lencloitre-bis', 'Lencloître', 48.1, 2.1, ['status' => 'declassified', 'source_status' => 'declassified', 'wikidata_id' => 'Q101'])->id;
        $this->link($other, 'Gare de Lencloître', 'Q100b');
        $this->cachePage('Gare de Lencloître', ' | fermeture = 1946 (voyageurs)');

        // Un article lié à deux gares sans élément principal : aucune des deux.
        $a = $this->closedStation('homonyme-a', 'Gare de Saint-Martin', 'gone', 'Q200', via: 'osm');
        $b = $this->closedStation('homonyme-b', 'Gare de Saint-Martin', 'gone', 'Q201', via: 'osm');
        $this->cachePage('Gare de Saint-Martin', ' | fermeture = 1938 (voyageurs)');

        // Gare réouverte aux voyageurs aujourd'hui : rien.
        $open = $this->closedStation('monteux', 'Gare de Monteux', 'active');
        $this->cachePage('Gare de Monteux', ' | fermeture = 1938 (voyageurs)');

        $this->artisan('vff:import-station-passenger-ends', ['--from-cache' => true])->assertSuccessful();

        $this->assertSame(['closure_passenger 1939-05-15 day wikipedia gare:Gare de Bry-sur-Marne (SNCF)#infobox-voyageurs',
            'closure_freight 2009-10-13 day wikipedia gare:Gare de Bry-sur-Marne (SNCF)#infobox-fret'], $this->events($old));
        $this->assertSame(1, DB::table('station_events')->where('source', 'wikipedia')->where('event_date', '1939-05-15')->count());
        $this->assertSame(['closure_passenger 1946-01-01 year wikipedia gare:Gare de Lencloître#infobox-voyageurs'], $this->events($main));
        $this->assertSame([], $this->events($other));
        $this->assertSame([], $this->events($a));
        $this->assertSame([], $this->events($b));
        $this->assertSame([], $this->events($open));
    }

    public function test_halts_close_before_opening_is_refused_and_a_rerun_replaces_only_its_rows(): void
    {
        $halt = $this->closedStation('halte', 'Halte de Lannoy', 'gone');
        $this->cachePage('Halte de Lannoy', ' | fermeture = 1939 (voyageurs)');
        $early = $this->closedStation('morsalines', 'Gare de Morsalines', 'declassified');
        DB::table('station_events')->insert(['station_id' => $early, 'type' => 'opening', 'event_date' => '1886-01-01', 'date_precision' => 'year',
            'source' => 'wikidata', 'is_inferred' => false, 'created_at' => now(), 'updated_at' => now()]);
        $this->cachePage('Gare de Morsalines', ' | fermeture = 1848 (voyageurs)');

        $kept = $this->closedStation('baud', 'Gare de Baud');
        $this->cachePage('Gare de Baud', ' | fermeture = 2 octobre 1949 (voyageurs)');
        // Ligne d'une autre commande (vff:import-station-closures) : jamais effacée ici.
        DB::table('station_events')->insert(['station_id' => $kept, 'type' => 'closure', 'event_date' => '1990-01-01', 'date_precision' => 'year',
            'source' => 'wikipedia', 'source_ref' => 'gare:Gare de Baud#texte', 'is_inferred' => false, 'created_at' => now(), 'updated_at' => now()]);

        $this->artisan('vff:import-station-passenger-ends', ['--from-cache' => true])->assertSuccessful();
        $this->assertSame(['closure 1939-01-01 year wikipedia gare:Halte de Lannoy#infobox-voyageurs'], $this->events($halt));
        $this->assertSame(['opening 1886-01-01 year wikidata '], $this->events($early));

        // L'article a changé : la date est remplacée, la ligne de l'autre commande reste.
        $this->cachePage('Gare de Baud', ' | fermeture = 3 octobre 1949 (voyageurs)', revid: 5000);
        $this->artisan('vff:import-station-passenger-ends', ['--from-cache' => true])->assertSuccessful();
        $this->assertSame([
            'closure_passenger 1949-10-03 day wikipedia gare:Gare de Baud#infobox-voyageurs',
            'closure 1990-01-01 year wikipedia gare:Gare de Baud#texte',
        ], $this->events($kept));
    }

    public function test_dry_run_writes_nothing_and_the_csv_compares_with_known_dates_and_eras(): void
    {
        $sid = $this->closedStation('quintin', 'Gare de Quintin');
        $this->cachePage('Gare de Quintin', ' | fermeture = 2006 (voyageurs)');
        DB::table('station_events')->insert(['station_id' => $sid, 'type' => 'closure', 'event_date' => '2006-01-01', 'date_precision' => 'year',
            'source' => 'wikidata', 'source_ref' => 'Q1#P3999', 'is_inferred' => false, 'created_at' => now(), 'updated_at' => now()]);
        $conflict = $this->closedStation('chatillon', 'Gare de Châtillon', 'gone');
        $this->cachePage('Gare de Châtillon', ' | fermeture = mai 1980 (voyageurs)');
        DB::table('station_events')->insert(['station_id' => $conflict, 'type' => 'closure_passenger', 'event_date' => '1994-01-01', 'date_precision' => 'year',
            'source' => 'wikidata', 'source_ref' => 'Q2#P3999', 'is_inferred' => false, 'created_at' => now(), 'updated_at' => now()]);
        foreach ([1990 => 'active', 2000 => 'active', 2010 => 'no_commercial'] as $year => $status) {
            $era = DB::table('eras')->where('year', $year)->value('id') ?? DB::table('eras')->insertGetId(['year' => $year, 'label' => (string) $year, 'is_current' => false, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('station_eras')->insert(['station_id' => $sid, 'era_id' => $era, 'status' => $status, 'uncertain' => $year === 2000, 'created_at' => now(), 'updated_at' => now()]);
        }
        $csv = storage_path('app/data/gares-infobox/bilan.csv');

        $this->artisan('vff:import-station-passenger-ends', ['--from-cache' => true, '--dry-run' => true, '--csv' => $csv])->assertSuccessful();

        $this->assertSame(0, DB::table('station_events')->where('source', 'wikipedia')->count());
        $rows = array_map('str_getcsv', file($csv, FILE_IGNORE_NEW_LINES));
        $head = array_shift($rows);
        $byName = [];
        foreach ($rows as $r) {
            $byName[array_combine($head, $r)['name']] = array_combine($head, $r);
        }
        $this->assertSame('fermeture de la même année, retypée en fin des voyageurs (gare fret seul)', $byName['Quintin']['verdict']);
        $this->assertSame('2000', $byName['Quintin']['last_passenger_era']);
        $this->assertSame('', $byName['Quintin']['passenger_eras_after']);
        $this->assertSame('2000', $byName['Quintin']['uncertain_eras_before']);
        $this->assertSame('conflit : autre fin des voyageurs', $byName['Chatillon']['verdict']);
    }

    public function test_csv_cells_that_look_like_formulas_are_neutralised(): void
    {
        $this->closedStation('=piege', 'Gare piégée');
        $this->cachePage('Gare piégée', ' | fermeture = 2006 (voyageurs)');
        $csv = storage_path('app/data/gares-infobox/bilan-formule.csv');

        $this->artisan('vff:import-station-passenger-ends', ['--from-cache' => true, '--dry-run' => true, '--csv' => $csv])->assertSuccessful();

        $rows = array_map(fn ($l) => str_getcsv($l, escape: ''), file($csv, FILE_IGNORE_NEW_LINES));
        $head = array_shift($rows);
        $names = array_column(array_map(fn ($r) => array_combine($head, $r), $rows), 'name');
        $this->assertSame(["'=piege"], $names);
        foreach ($rows as $row) {
            foreach ($row as $cell) {
                $this->assertDoesNotMatchRegularExpression('/^[=+\-@\t\r]/', $cell);
            }
        }
    }

    public function test_csv_tells_when_a_later_freight_end_makes_the_mass_closure_a_passenger_end(): void
    {
        $sid = $this->closedStation('lion-d-or', 'Gare du Lion-d\'Or', 'gone');
        $this->cachePage('Gare du Lion-d\'Or', ' | fermeture = 1 mars 1938 (voyageurs) 3 novembre 1969 (marchandises)');
        DB::table('station_events')->insert(['station_id' => $sid, 'type' => 'demolition', 'event_date' => '1938-03-01', 'date_precision' => 'day',
            'source' => 'wikidata', 'source_ref' => 'Q3#P576', 'is_inferred' => false, 'created_at' => now(), 'updated_at' => now()]);
        $kept = $this->closedStation('marcenais', 'Gare de Marcenais', 'declassified');
        $this->cachePage('Gare de Marcenais', ' | fermeture = 1938 (voyageurs)');
        DB::table('station_events')->insert(['station_id' => $kept, 'type' => 'closure', 'event_date' => '1938-01-01', 'date_precision' => 'year',
            'source' => 'wikidata', 'source_ref' => 'Q4#P3999', 'is_inferred' => false, 'created_at' => now(), 'updated_at' => now()]);
        $csv = storage_path('app/data/gares-infobox/bilan-fret.csv');

        $this->artisan('vff:import-station-passenger-ends', ['--from-cache' => true, '--dry-run' => true, '--csv' => $csv])->assertSuccessful();

        $rows = array_map(fn ($l) => str_getcsv($l, escape: ''), file($csv, FILE_IGNORE_NEW_LINES));
        $head = array_shift($rows);
        $verdicts = [];
        foreach ($rows as $r) {
            $r = array_combine($head, $r);
            $verdicts[$r['name'].' '.$r['type']] = $r['verdict'];
        }
        $this->assertEquals([
            'Lion-d-or closure_passenger' => 'fermeture de la même année, retypée en fin des voyageurs (fret plus tardif)',
            'Lion-d-or closure_freight' => 'nouvelle fin du fret',
            'Marcenais closure_passenger' => 'fermeture totale de la même année, gardée',
        ], $verdicts);
    }
}
