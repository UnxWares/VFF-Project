<?php

namespace Tests\Feature\Greenways;

use App\Console\Commands\LinkGreenways;
use App\Services\Greenways\GreenwayLinker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\SeedsGreenways;
use Tests\TestCase;

/**
 * Rattachement des voies vertes OSM aux lignes disparues ou fermées (GreenwayLinker) : recouvrement
 * mesuré sur l'axe de la ligne, seuils (longueur commune, part du way, total de la ligne), voies
 * encore en service ou en place exclues, relance idempotente.
 *
 * Repères : 0,00009° de latitude valent environ 10 m ; `metresOf` donne la distance en longitude.
 */
class GreenwayLinkerTest extends TestCase
{
    use RefreshDatabase;
    use SeedsGreenways;

    private function link(?array $lineIds = null): array
    {
        return app(GreenwayLinker::class)->link($lineIds);
    }

    /** @return array<int, object> lignes de line_greenways pour une ligne, par identifiant de way */
    private function linksOf(int $lineId): array
    {
        return DB::table('line_greenways')->where('line_id', $lineId)->get()->keyBy('osm_way_id')->all();
    }

    public function test_thresholds_are_the_documented_ones(): void
    {
        // Les valeurs citées par l'API (LineGreenwayController), le schéma GraphQL et la fiche ligne.
        $this->assertSame(8, GreenwayLinker::AXIS_TOLERANCE_M);
        $this->assertSame(30, GreenwayLinker::CLEARANCE_M);
        $this->assertSame(25, GreenwayLinker::PIECE_M);
        $this->assertSame(50, GreenwayLinker::MIN_SHARED_M);
        $this->assertSame(50, GreenwayLinker::MIN_SHARE_PERCENT);
        $this->assertSame(300, GreenwayLinker::MIN_LINE_TOTAL_M);
    }

    public function test_a_greenway_on_the_axis_of_a_closed_line_is_linked_with_its_common_length_and_osm_id(): void
    {
        $line = $this->seedLine('436000', 'Ligne de La Chapelle-Anthenaise à Flers', 46.0, 1.0, 1.1);
        $this->seedGreenway(100, 46.0, 1.0, 1.03, ['name' => 'Voie verte de la Mayenne']);

        $result = $this->link();

        $link = $this->linksOf($line->id)[100];
        $this->assertSame(['links' => 1, 'lines' => 1, 'removed' => 0], array_intersect_key($result, ['links' => 1, 'lines' => 1, 'removed' => 1]));
        $this->assertSame('Voie verte de la Mayenne', $link->name);
        $this->assertSame('cycleway', $link->highway);
        $this->assertSame('osm', $link->source);
        $this->assertEqualsWithDelta($this->metresOf(0.03, 46.0), $link->shared_length_m, 40);
        $this->assertSame(100, $link->shared_percent);
        // Lambert-93 (mesure) et géodésique (longueur OSM) diffèrent de quelques mètres.
        $this->assertEqualsWithDelta($link->way_length_m, $link->shared_length_m, 5);
        $this->assertSame('2026-11-18', substr($link->osm_seen_at, 0, 10));
        $geometry = DB::selectOne('SELECT ST_GeometryType(geom) AS type, ST_Length(geom::geography) AS metres FROM line_greenways WHERE osm_way_id = 100');
        $this->assertSame('ST_MultiLineString', $geometry->type);
        $this->assertEqualsWithDelta($this->metresOf(0.03, 46.0), $geometry->metres, 40);
    }

    public function test_a_greenway_beside_a_line_in_service_is_never_linked(): void
    {
        $line = $this->seedLine('437000', 'Ligne en service', 47.0, 1.0, 1.1);
        $this->seedInServiceSegment($line, 47.0, 1.0, 1.1);
        // Sur l'axe même, puis à 10 m, puis à 25 m : tous à moins de 30 m d'une voie en service.
        $this->seedGreenway(200, 47.0, 1.0, 1.03);
        $this->seedGreenway(201, 47.00009, 1.0, 1.03);
        $this->seedGreenway(202, 47.00022, 1.0, 1.03);

        $result = $this->link();

        $this->assertSame(0, $result['links']);
        $this->assertSame(0, DB::table('line_greenways')->count());
    }

    public function test_a_greenway_on_a_closed_part_of_a_partly_open_line_counts_only_there(): void
    {
        $line = $this->seedLine('438000', 'Ligne ouverte à l\'ouest, fermée à l\'est', 48.0, 1.0, 1.1);
        $this->seedInServiceSegment($line, 48.0, 1.0, 1.02);
        // La voie verte suit toute la ligne sur 0,06° ; la partie à moins de 30 m du tronçon en service n'est pas comptée.
        $this->seedGreenway(300, 48.0, 1.0, 1.06);

        $this->link();

        $link = $this->linksOf($line->id)[300];
        $closed = $this->metresOf(0.04, 48.0) - GreenwayLinker::CLEARANCE_M;
        $this->assertEqualsWithDelta($closed, $link->shared_length_m, 60);
        $this->assertEqualsWithDelta(100 * $closed / $this->metresOf(0.06, 48.0), $link->shared_percent, 3);
        $west = DB::selectOne('SELECT ST_XMin(geom) AS x FROM line_greenways WHERE osm_way_id = 300');
        $this->assertGreaterThan(1.02, $west->x, 'la partie sur la voie en service n\'est pas dessinée');
    }

    public function test_pieces_over_rails_left_in_place_are_excluded(): void
    {
        $line = $this->seedLine('439000', 'Ligne fermée, voie laissée en place à l\'ouest', 49.0, 1.0, 1.1);
        $this->seedTrackInPlace(49.0, 1.0, 1.02);
        $this->seedGreenway(400, 49.0, 1.0, 1.06);

        $this->link();

        $link = $this->linksOf($line->id)[400];
        $this->assertEqualsWithDelta($this->metresOf(0.04, 49.0) - GreenwayLinker::CLEARANCE_M, $link->shared_length_m, 60);
        $west = DB::selectOne('SELECT ST_XMin(geom) AS x FROM line_greenways WHERE osm_way_id = 400');
        $this->assertGreaterThan(1.02, $west->x, 'la partie sur les rails n\'est pas dessinée');
    }

    public function test_tolerance_share_and_minimum_length_thresholds(): void
    {
        $line = $this->seedLine('440000', 'Ligne fermée', 50.0, 1.0, 1.2);
        // À 5 m de l'axe : dans la tolérance de 8 m. À 15 m : hors tolérance.
        $this->seedGreenway(500, 50.000045, 1.0, 1.03);
        $this->seedGreenway(501, 50.000135, 1.1, 1.13);
        // Perpendiculaire : un croisement de l'emprise ne mesure que quelques mètres.
        $this->seedOsmWay(502, 'greenway', 'LINESTRING(1.15 49.998, 1.15 50.002)');
        // 214 m sur l'axe, puis le way s'en écarte de 200 m : environ un quart seulement suit l'emprise.
        $this->seedOsmWay(503, 'greenway', 'LINESTRING(1.17 50.0, 1.173 50.0, 1.173 50.0018, 1.18 50.0018)');
        // Courte voie verte mais entièrement sur l'axe : 93 m, plus que le seuil de 50 m.
        $this->seedGreenway(504, 50.0, 1.19, 1.1913);

        $this->link();

        $links = $this->linksOf($line->id);
        $this->assertEqualsCanonicalizing([500, 504], array_keys($links), 'tolérance 8 m, croisement et way surtout ailleurs écartés');
        $this->assertEqualsWithDelta(93, $links[504]->shared_length_m, 5);
    }

    public function test_the_share_threshold_is_the_share_of_the_way_not_of_the_line(): void
    {
        $line = $this->seedLine('441000', 'Ligne fermée', 51.0, 1.0, 1.3);
        // Environ 54 % du way sur l'axe : rattaché. Environ 36 % : écarté.
        $this->seedOsmWay(600, 'greenway', 'LINESTRING(1.0 51.0, 1.0155 51.0, 1.0155 51.0018, 1.0259 51.0018)');
        $this->seedOsmWay(601, 'greenway', 'LINESTRING(1.1 51.0, 1.1103 51.0, 1.1103 51.0018, 1.1259 51.0018)');

        $this->link();

        $links = $this->linksOf($line->id);
        $this->assertArrayHasKey(600, $links);
        $this->assertArrayNotHasKey(601, $links);
        $this->assertGreaterThanOrEqual(GreenwayLinker::MIN_SHARE_PERCENT, $links[600]->shared_percent);
        $this->assertLessThan(100, $links[600]->shared_percent);
    }

    public function test_a_line_needs_the_minimum_total_length(): void
    {
        $short = $this->seedLine('442000', 'Ligne à voie verte de 230 m', 52.0, 1.0, 1.1);
        $this->seedGreenway(700, 52.0, 1.0, 1.003);
        $long = $this->seedLine('443000', 'Ligne à deux voies vertes de 230 m', 53.0, 1.0, 1.1);
        $this->seedGreenway(701, 53.0, 1.0, 1.003);
        $this->seedGreenway(702, 53.0, 1.01, 1.013);

        $this->link();

        $this->assertSame([], $this->linksOf($short->id));
        $this->assertEqualsCanonicalizing([701, 702], array_keys($this->linksOf($long->id)));
    }

    public function test_railway_ways_reused_as_cycle_paths_are_candidates_but_plain_rail_and_tracks_are_not(): void
    {
        $line = $this->seedLine('444000', 'Ligne déposée', 54.0, 1.0, 1.2);
        $this->seedOsmWay(800, 'railway', 'LINESTRING(1.0 54.0, 1.03 54.0)', ['railway' => 'abandoned', 'highway' => 'cycleway', 'name' => 'Voie verte']);
        $this->seedOsmWay(801, 'railway', 'LINESTRING(1.04 54.0, 1.07 54.0)', ['railway' => 'abandoned', 'highway' => 'path', 'bicycle' => 'designated']);
        $this->seedOsmWay(802, 'railway', 'LINESTRING(1.08 54.0, 1.11 54.0)', ['railway' => 'abandoned', 'highway' => 'track']);
        $this->seedOsmWay(803, 'railway', 'LINESTRING(1.12 54.0, 1.15 54.0)', ['railway' => 'rail', 'highway' => 'cycleway']);
        $this->seedOsmWay(804, 'railway', 'LINESTRING(1.16 54.0, 1.19 54.0)', ['railway' => 'abandoned']);

        $this->link();

        $this->assertEqualsCanonicalizing([800, 801], array_keys($this->linksOf($line->id)));
    }

    public function test_the_osm_cycle_route_names_the_greenway_and_dates_it(): void
    {
        $line = $this->seedLine('445000', 'Ligne déposée', 55.0, 1.0, 1.2);
        $routes = [['id' => 12, 'name' => 'Voie verte du Val de Sarthe', 'network' => 'rcn', 'start_date' => '2008-06']];
        $this->seedGreenway(900, 55.0, 1.0, 1.03, ['name' => 'Chemin du halage'], $routes);
        $this->seedGreenway(901, 55.0, 1.04, 1.07, [], null, 2015);
        $this->seedGreenway(902, 55.0, 1.08, 1.11, [], null, 1950);

        $this->link();

        $links = $this->linksOf($line->id);
        $this->assertSame(12, (int) $links[900]->osm_relation_id);
        $this->assertSame('Voie verte du Val de Sarthe', $links[900]->relation_name);
        $this->assertSame(2008, (int) $links[900]->opened_year);
        $this->assertSame(2015, (int) $links[901]->opened_year, 'start_date du way');
        $this->assertNull($links[902]->opened_year, 'avant 1970 : date de la ligne ferroviaire, pas de la voie verte');
        $this->assertNull($links[902]->osm_relation_id);
    }

    public function test_the_displayed_name_is_computed_once_with_the_greenway_naming_rule(): void
    {
        $line = $this->seedLine('446000', 'Ligne déposée', 55.3, 1.0, 1.2);
        // Relation « Le Lac » (lieu) sur un way au nom d'itinéraire ; relation « Camping » sur une avenue ; way « Voie Verte ».
        $this->seedGreenway(910, 55.3, 1.0, 1.03, ['name' => 'Voie Verte des Hautes-Vosges'], [['id' => 30, 'name' => 'Le Lac']]);
        $this->seedGreenway(911, 55.3, 1.04, 1.07, ['name' => 'Avenue Maurice Maunoury'], [['id' => 31, 'name' => 'Camping']]);
        $this->seedGreenway(912, 55.3, 1.08, 1.11, ['name' => 'Voie Verte']);
        $this->seedGreenway(913, 55.3, 1.12, 1.15, ['name' => 'Chemin du halage'], [['id' => 32, 'name' => 'Voie verte du Val de Sarthe']]);

        $this->link();

        $links = $this->linksOf($line->id);
        $this->assertSame('Voie Verte des Hautes-Vosges', $links[910]->display_name);
        $this->assertNull($links[911]->display_name);
        $this->assertNull($links[912]->display_name);
        $this->assertSame('Voie verte du Val de Sarthe', $links[913]->display_name);
        // Les noms OSM restent tels quels à côté.
        $this->assertSame('Avenue Maurice Maunoury', $links[911]->name);
        $this->assertSame('Camping', $links[911]->relation_name);

        // Un nom OSM qui change est renommé à la relance.
        DB::table('present_osm_ways')->where('id', 912)->update(['name' => 'Voie verte de la Vallée']);
        $this->link();
        $this->assertSame('Voie verte de la Vallée', $this->linksOf($line->id)[912]->display_name);
    }

    public function test_a_greenway_on_two_overlapping_lines_is_linked_to_each(): void
    {
        $first = $this->seedLine('446000', 'Ligne A', 56.0, 1.0, 1.1);
        $second = $this->seedLine('446001', 'Ligne A bis (doublon)', 56.0, 1.0, 1.1);
        $this->seedGreenway(1000, 56.0, 1.0, 1.03);

        $this->link();

        $this->assertArrayHasKey(1000, $this->linksOf($first->id));
        $this->assertArrayHasKey(1000, $this->linksOf($second->id));
    }

    public function test_a_second_run_is_idempotent_and_drops_links_that_no_longer_pass(): void
    {
        $line = $this->seedLine('447000', 'Ligne fermée', 57.0, 1.0, 1.2);
        $this->seedGreenway(1100, 57.0, 1.0, 1.03);
        $this->seedGreenway(1101, 57.0, 1.05, 1.08);
        $this->link();
        $firstIds = DB::table('line_greenways')->orderBy('osm_way_id')->pluck('id', 'osm_way_id')->all();

        $again = $this->link();

        $this->assertSame(0, $again['removed']);
        $this->assertSame($firstIds, DB::table('line_greenways')->orderBy('osm_way_id')->pluck('id', 'osm_way_id')->all());

        // La voie 1101 disparaît d'OSM, la ligne 1100 est maintenant doublée d'une voie en service.
        DB::table('present_osm_ways')->where('id', 1101)->delete();
        $this->seedInServiceSegment($line, 57.0, 1.0, 1.03);
        $third = $this->link();

        $this->assertSame(2, $third['removed']);
        $this->assertSame([], $this->linksOf($line->id));
    }

    public function test_limiting_to_some_lines_leaves_the_others_untouched_and_dry_run_writes_nothing(): void
    {
        $a = $this->seedLine('448000', 'Ligne A', 58.0, 1.0, 1.1);
        $b = $this->seedLine('449000', 'Ligne B', 59.0, 1.0, 1.1);
        $this->seedGreenway(1200, 58.0, 1.0, 1.03);
        $this->seedGreenway(1201, 59.0, 1.0, 1.03);

        $dry = app(GreenwayLinker::class)->link([$a->id], dryRun: true);
        $this->assertSame(1, $dry['links']);
        $this->assertSame(0, DB::table('line_greenways')->count());

        $this->link([$a->id]);
        $this->assertSame([1200], array_keys($this->linksOf($a->id)));
        $this->assertSame([], $this->linksOf($b->id));

        $this->link([$b->id]);
        $this->assertSame([1200], array_keys($this->linksOf($a->id)), 'la ligne A, hors du périmètre, n\'est pas retirée');
        $this->assertSame([1201], array_keys($this->linksOf($b->id)));
    }

    public function test_tramways_metros_and_funiculars_never_receive_a_greenway(): void
    {
        // Une piste cyclable le long d'une rue tombe à moins de 8 m de l'axe d'un ancien tramway.
        $railway = $this->seedLine('452000', 'Ligne ferroviaire fermée', 62.0, 1.0, 1.1);
        $names = ['Tramway de Lille (TELB E)', 'Tramways de l\'Ain', 'Ligne de tramway 2 (ELRT)', 'Ancien métro de Lyon',
            'Funiculaire de Saint-Paul à Fourvière', 'Téléphérique de Batère', 'Plan incliné d\'Ivry'];
        $lat = 62.0;
        $excluded = [];
        foreach ($names as $i => $name) {
            $lat += 0.5;
            // Même un code à 6 chiffres ne fait pas d'un tramway une ligne ferroviaire.
            $line = $this->seedLine((string) (453000 + $i), $name, $lat, 1.0, 1.1);
            $this->seedGreenway(1400 + $i, $lat, 1.0, 1.03);
            $excluded[] = $line->id;
        }
        $this->seedGreenway(1399, 62.0, 1.0, 1.03);
        // Un nom qui contient seulement le mot ne suffit pas : « Ligne du Tramway-Vert » reste ferroviaire.
        $other = $this->seedLine('470000', 'Ligne de Poitiers au Tramway-Vert', 70.0, 1.0, 1.1);
        $this->seedGreenway(1500, 70.0, 1.0, 1.03);

        $result = $this->link();

        $this->assertSame([1399], array_keys($this->linksOf($railway->id)));
        $this->assertSame([1500], array_keys($this->linksOf($other->id)));
        $this->assertSame(0, DB::table('line_greenways')->whereIn('line_id', $excluded)->count());
        $this->assertSame(2, $result['lines']);
    }

    public function test_a_tram_train_on_the_national_network_is_a_railway_line(): void
    {
        $rfn = $this->seedLine('454000', 'Tram-train de l\'Ouest lyonnais', 63.0, 1.0, 1.1);
        $local = $this->seedLine('454001', 'Tram-train local', 64.0, 1.0, 1.1);
        $local->update(['code' => null]);
        $this->seedGreenway(1600, 63.0, 1.0, 1.03);
        $this->seedGreenway(1601, 64.0, 1.0, 1.03);

        $this->link();

        $this->assertSame([1600], array_keys($this->linksOf($rfn->id)));
        $this->assertSame([], $this->linksOf($local->id), 'sans code SNCF Réseau, un tram-train est un tramway');
    }

    public function test_a_line_that_became_a_tramway_loses_its_old_links_on_the_next_run(): void
    {
        $line = $this->seedLine('455000', 'Ligne devenue tramway', 65.0, 1.0, 1.1);
        $this->seedGreenway(1700, 65.0, 1.0, 1.03);
        $this->link();
        $this->assertCount(1, $this->linksOf($line->id));

        $line->update(['name' => 'Tramway de Test']);
        $result = $this->link();

        $this->assertSame(1, $result['removed']);
        $this->assertSame([], $this->linksOf($line->id));
    }

    public function test_the_command_prints_the_figures_and_resolves_lines_by_code(): void
    {
        $this->seedLine('450000', 'Ligne A', 60.0, 1.0, 1.1);
        $this->seedLine('451000', 'Ligne B', 61.0, 1.0, 1.1);
        $this->seedGreenway(1300, 60.0, 1.0, 1.03);
        $this->seedGreenway(1301, 61.0, 1.0, 1.03);

        $this->artisan('vff:link-greenways', ['--line' => ['450000']])->assertSuccessful();
        $this->assertSame([1300], DB::table('line_greenways')->pluck('osm_way_id')->map(fn ($id): int => (int) $id)->all());

        $this->artisan('vff:link-greenways', ['--line' => ['inconnue']])->assertFailed();
        $this->artisan('vff:link-greenways', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame(1, DB::table('line_greenways')->count());
        $this->assertInstanceOf(LinkGreenways::class, $this->app->make(LinkGreenways::class));
    }
}
