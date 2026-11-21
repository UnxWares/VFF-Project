<?php

namespace Tests\Feature\SegmentAudit;

use App\Models\Era;
use App\Models\Line;
use App\Models\Station;
use App\Services\SegmentAudit\SegmentAuditor;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * Chemins de SegmentAuditor mesurés en base sur un réseau d'essai (BuildsRailNetwork, section
 * « Audit des tronçons ») : avis des sources d'état actuel (BD TOPO, OSM, lignes-oubliées), dépose
 * probable (track_unconfirmed), événements non localisés, chronologies muettes, train touristique,
 * jonctions, gares et longueur officielle tirée des PK.
 *
 * Tronçons est-ouest sur le parallèle 48° N, entre 2,00° et 2,01° E (environ 745 m) ; chaque test
 * pose ses propres voies. Date de calcul fixée au 21/11/2026.
 */
class SegmentAuditorPathsTest extends TestCase
{
    use BuildsRailNetwork;
    use RefreshDatabase;

    private const AS_OF = '2026-11-21';

    private const TRACK = 'LINESTRING(2.0 48.0, 2.01 48.0)';

    private Era $past;

    private Era $current;

    private Line $audited;

    protected function setUp(): void
    {
        parent::setUp();
        $this->past = Era::factory()->create(['year' => 1960, 'label' => 'Années 1960']);
        $this->current = Era::factory()->current()->create(['year' => 2030, 'label' => 'Aujourd’hui']);
        $this->audited = Line::factory()->create(['slug' => 'rfn-654330', 'code' => '654330', 'name' => 'Ligne d’Oscar', 'is_lgv' => false, 'network' => 'principal']);
    }

    /** @return array<string, mixed> */
    private function audit(int $segment, ?Era $era = null, ?Line $line = null): array
    {
        $era ??= $this->current;
        $audit = app(SegmentAuditor::class)->auditLine(($line ?? $this->audited)->id, [$era->year], self::AS_OF);

        return self::auditedSegment($audit, $era->id, $segment);
    }

    /**
     * Avis d'une source dans les preuves du classement.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private static function verdict(array $row, string $source): array
    {
        foreach ($row['evidence']['classification']['verdicts'] as $v) {
            if ($v['source'] === $source) {
                return $v;
            }
        }
        self::fail("aucun avis de {$source}");
    }

    /** @param  array<string, mixed>  $row */
    private static function classification(array $row): array
    {
        return [$row['classification_grade'], $row['evidence']['classification']['reasons']];
    }

    public function test_an_unknown_line_has_no_audit(): void
    {
        $this->assertSame([], app(SegmentAuditor::class)->auditLine(999_999, null, self::AS_OF));
    }

    // ─── Sources d'état actuel ───────────────────────────────────────────

    public function test_an_unattached_bdtopo_track_only_weakly_contradicts(): void
    {
        $segment = $this->auditSegment($this->audited, $this->current, self::TRACK, 'abandoned', 'unknown');
        $this->auditTrack('bdtopo', self::TRACK, properties: ['usage' => 'Voyageur et fret']);

        $row = $this->audit($segment);
        $bdtopo = self::verdict($row, 'bdtopo');

        $this->assertSame(['class' => 'open', 'traffic' => 'mixed'], $bdtopo['expected']);
        $this->assertFalse($bdtopo['agrees']);
        $this->assertSame('expected_open', $bdtopo['why']);
        $this->assertTrue($bdtopo['weak'], 'voie peut-être d’une ligne voisine');
        $this->assertSame(['nature' => 'Voie ferrée principale', 'etat' => 'En service', 'usage' => 'Voyageur et fret', 'attached' => false], $bdtopo['observed']);
        $this->assertSame('current', $bdtopo['kind']);
        $this->assertSame(3, $bdtopo['priority']);
        $this->assertSame(['fair', ['weak_contradiction']], self::classification($row));
    }

    public function test_a_bdtopo_track_listed_for_the_line_contradicts_strongly(): void
    {
        $segment = $this->auditSegment($this->audited, $this->current, self::TRACK, 'abandoned', 'unknown');
        // Rattachée à la ligne par source_trace_lines seulement (line_id vide).
        $this->auditTrack('bdtopo', self::TRACK, properties: ['usage' => 'Fret'], listedFor: $this->audited->id);

        $row = $this->audit($segment);
        $bdtopo = self::verdict($row, 'bdtopo');

        $this->assertSame(['class' => 'open', 'traffic' => 'freight'], $bdtopo['expected']);
        $this->assertTrue($bdtopo['observed']['attached']);
        $this->assertFalse($bdtopo['weak']);
        $this->assertSame(['poor', ['source_contradicts']], self::classification($row));
    }

    public function test_an_unattached_bdtopo_track_that_agrees_counts(): void
    {
        $segment = $this->auditSegment($this->audited, $this->current, self::TRACK, 'in_service', 'freight');
        $this->auditTrack('bdtopo', self::TRACK, properties: ['usage' => 'Fret']);

        $row = $this->audit($segment);
        $bdtopo = self::verdict($row, 'bdtopo');

        $this->assertTrue($bdtopo['agrees']);
        $this->assertFalse($bdtopo['weak'], 'un accord d’une voie non rattachée compte');
        $this->assertSame(['good', ['sources_agree']], self::classification($row));
    }

    public function test_bdtopo_traffic_contradiction(): void
    {
        $segment = $this->auditSegment($this->audited, $this->current, self::TRACK, 'in_service', 'mixed');
        $this->auditTrack('bdtopo', self::TRACK, $this->audited->id, properties: ['usage' => 'Fret']);

        $bdtopo = self::verdict($this->audit($segment), 'bdtopo');

        $this->assertSame([false, 'expected_freight_only'], [$bdtopo['agrees'], $bdtopo['why']]);
    }

    /** @return iterable<string, array{0: array<string, string>, 1: ?string, 2: string, 3: string}> */
    public static function bdtopoStates(): iterable
    {
        yield 'en construction' => [['etat_de_l_objet' => 'En construction'], null, 'construction', 'not_open'];
        yield 'en projet' => [['etat_de_l_objet' => 'En projet'], null, 'construction', 'not_open'];
        yield 'non exploitée' => [['etat_de_l_objet' => 'Non exploité'], null, 'abandoned', 'closed'];
        yield 'vélo-rail' => [['usage' => 'Vélo-rail'], null, 'abandoned', 'closed'];
        yield 'sans objet' => [[], 'Sans objet', 'abandoned', 'closed'];
    }

    /**
     * @param  array<string, string>  $properties
     */
    #[DataProvider('bdtopoStates')]
    public function test_bdtopo_state_of_the_track(array $properties, ?string $layer, string $status, string $class): void
    {
        $segment = $this->auditSegment($this->audited, $this->current, self::TRACK, $status, 'unknown');
        $this->auditTrack('bdtopo', self::TRACK, $this->audited->id, $layer, $properties);

        $bdtopo = self::verdict($this->audit($segment), 'bdtopo');

        $this->assertSame(['class' => $class, 'traffic' => null], $bdtopo['expected']);
        $this->assertTrue($bdtopo['agrees']);
    }

    public function test_bdtopo_prefers_the_line_track_and_ignores_far_or_rack_tracks(): void
    {
        $segment = $this->auditSegment($this->audited, $this->current, self::TRACK, 'abandoned', 'unknown');
        $neighbour = Line::factory()->create(['code' => '654334', 'is_lgv' => false]);
        // Voie d'une ligne voisine en service à 0 m, voie de la ligne fermée à 3 m : celle de la ligne l'emporte.
        $this->auditTrack('bdtopo', self::TRACK, $neighbour->id, properties: ['usage' => 'Fret']);
        $this->auditTrack('bdtopo', 'LINESTRING(2.0 48.000027, 2.01 48.000027)', $this->audited->id, properties: ['etat_de_l_objet' => 'Non exploité']);
        // Crémaillère à 0 m et voie de la ligne à 6 m : jamais retenues.
        $this->auditTrack('bdtopo', self::TRACK, $this->audited->id, 'Funiculaire ou crémaillère');
        $this->auditTrack('bdtopo', 'LINESTRING(2.0 47.999946, 2.01 47.999946)', $this->audited->id);

        $bdtopo = self::verdict($this->audit($segment), 'bdtopo');

        $this->assertSame(['class' => 'closed', 'traffic' => null], $bdtopo['expected']);
        $this->assertTrue($bdtopo['observed']['attached']);
    }

    /**
     * Corrigé le 21/11 après-midi (règles 2026-11-21.5) : `attached` valait NULL pour une voie sans line_id
     * ni rattachement à la ligne, et `ORDER BY attached DESC` rangeait ces voies libres en tête. Ère 2030
     * de `vff` avant la correction : 508 tronçons lisaient l'état BD TOPO d'une voie libre alors qu'une
     * voie de leur ligne était à moins de 5 m.
     *
     * @return iterable<string, array{0: string, 1: string, 2: array<string, string>, 3: string}>
     */
    public static function freeTrackBeforeOwnTrack(): iterable
    {
        yield 'BD TOPO' => ['bdtopo', 'Voie ferrée principale', ['etat_de_l_objet' => 'Non exploité'], 'closed'];
        yield 'OSM' => ['osm', 'disused', [], 'closed'];
    }

    /** @param  array<string, string>  $properties */
    #[DataProvider('freeTrackBeforeOwnTrack')]
    public function test_a_free_track_does_not_pass_before_the_line_track(string $source, string $layer, array $properties, string $class): void
    {
        $segment = $this->auditSegment($this->audited, $this->current, self::TRACK, 'abandoned', 'unknown');
        // Voie libre en service collée au tronçon, voie de la ligne désaffectée à 3 m.
        $this->auditTrack($source, self::TRACK, properties: ['usage' => 'Fret']);
        $this->auditTrack($source, 'LINESTRING(2.0 48.000027, 2.01 48.000027)', $this->audited->id, $layer, $properties);

        $verdict = self::verdict($this->audit($segment), $source);

        $this->assertTrue($verdict['observed']['attached']);
        $this->assertSame($class, $verdict['expected']['class']);
    }

    /** @return iterable<string, array{0: string, 1: string, 2: string, 3: bool}> */
    public static function osmLayers(): iterable
    {
        yield 'rail' => ['rail', 'abandoned', 'in_place', true];
        yield 'voie étroite' => ['narrow_gauge', 'in_service', 'in_place', true];
        yield 'désaffectée' => ['disused', 'abandoned', 'closed', true];
        yield 'désaffectée (préfixe)' => ['disused:railway', 'dismantled', 'closed', true];
        yield 'abandonnée' => ['abandoned', 'dismantled', 'gone', true];
        yield 'rasée' => ['razed', 'abandoned', 'gone', false];
        yield 'démontée' => ['dismantled', 'razed', 'gone', true];
    }

    #[DataProvider('osmLayers')]
    public function test_osm_layer_of_the_track(string $layer, string $status, string $class, bool $agrees): void
    {
        $segment = $this->auditSegment($this->audited, $this->current, self::TRACK, $status, 'unknown');
        $this->auditTrack('osm', self::TRACK, $this->audited->id, $layer);

        $osm = self::verdict($this->audit($segment), 'osm');

        $this->assertSame(['class' => $class, 'traffic' => null], $osm['expected']);
        $this->assertSame($agrees, $osm['agrees']);
        $this->assertSame(['layer' => $layer, 'attached' => true], $osm['observed']);
        $this->assertFalse($osm['weak']);
        $this->assertSame(5, $osm['priority']);
    }

    public function test_osm_track_of_another_line_is_a_weak_opinion_and_the_line_track_wins(): void
    {
        $segment = $this->auditSegment($this->audited, $this->current, self::TRACK, 'abandoned', 'unknown');
        $neighbour = Line::factory()->create(['code' => '654331', 'is_lgv' => false]);
        $this->auditTrack('osm', self::TRACK, $neighbour->id, 'razed');

        $alone = self::verdict($this->audit($segment), 'osm');
        $this->assertSame([false, true, false], [$alone['agrees'], $alone['weak'], $alone['observed']['attached']]);

        // Une voie de la ligne à 8 m, désaffectée, passe avant la voie voisine collée au tronçon.
        $this->auditTrack('osm', 'LINESTRING(2.0 48.000072, 2.01 48.000072)', $this->audited->id, 'disused');
        $own = self::verdict($this->audit($segment), 'osm');
        $this->assertSame(['disused', true, true], [$own['observed']['layer'], $own['observed']['attached'], $own['agrees']]);

        // Hors des 10 m : plus aucun avis OSM.
        $far = $this->auditSegment($this->audited, $this->current, 'LINESTRING(2.0 48.0002, 2.01 48.0002)', 'abandoned', 'unknown');
        $sources = array_column($this->audit($far)['evidence']['classification']['verdicts'], 'source');
        $this->assertNotContains('osm', $sources);
    }

    public function test_osm_layers_outside_the_state_list_say_nothing(): void
    {
        $segment = $this->auditSegment($this->audited, $this->current, self::TRACK, 'abandoned', 'unknown');
        $this->auditTrack('osm', self::TRACK, $this->audited->id, 'construction');
        $this->auditTrack('osm', self::TRACK, $this->audited->id, 'historic');

        $row = $this->audit($segment);

        $this->assertSame([], $row['evidence']['classification']['verdicts']);
        $this->assertSame(['unknown', ['no_source']], self::classification($row));
    }

    public function test_present_state_of_lignes_oubliees(): void
    {
        $inPlace = $this->auditSegment($this->audited, $this->current, self::TRACK, 'abandoned', 'unknown');
        $this->auditPresentState('track_in_place', self::TRACK);
        $this->auditPresentState('greenway', self::TRACK);

        $lo = self::verdict($this->audit($inPlace), 'present-lignes-oubliees');
        $this->assertSame(['class' => 'in_place', 'traffic' => null], $lo['expected'], 'la voie en place passe avant');
        $this->assertSame([true, true, 7], [$lo['agrees'], $lo['weak'], $lo['priority']]);
        $this->assertSame(['state' => 'track_in_place'], $lo['observed']);

        $gone = $this->auditSegment($this->audited, $this->current, 'LINESTRING(2.02 48.0, 2.03 48.0)', 'abandoned', 'unknown');
        $this->auditPresentState('greenway', 'LINESTRING(2.02 48.0001, 2.03 48.0001)');
        $row = $this->audit($gone);
        $lo = self::verdict($row, 'present-lignes-oubliees');
        $this->assertSame(['class' => 'gone', 'traffic' => null], $lo['expected']);
        $this->assertSame([false, true], [$lo['agrees'], $lo['weak']]);
        $this->assertSame(['fair', ['weak_contradiction']], self::classification($row));

        // Relevé à plus de 15 m : ignoré.
        $far = $this->auditSegment($this->audited, $this->current, 'LINESTRING(2.04 48.0, 2.05 48.0)', 'abandoned', 'unknown');
        $this->auditPresentState('dismantled', 'LINESTRING(2.04 48.00015, 2.05 48.00015)');
        $this->assertSame([], $this->audit($far)['evidence']['classification']['verdicts']);
    }

    public function test_current_sources_are_not_read_for_a_past_era(): void
    {
        $segment = $this->auditSegment($this->audited, $this->past, self::TRACK, 'abandoned', 'unknown');
        $this->auditTrack('bdtopo', self::TRACK, $this->audited->id);
        $this->auditTrack('osm', self::TRACK, $this->audited->id, 'razed');
        $this->auditPresentState('road', self::TRACK);

        $row = $this->audit($segment, $this->past);

        $this->assertSame([], $row['evidence']['classification']['verdicts']);
        $this->assertSame([], $row['evidence']['classification']['rules']);
    }

    // ─── Dépose probable (track_unconfirmed) ─────────────────────────────

    /** @return iterable<string, array{0: string, 1: bool}> */
    public static function closureAges(): iterable
    {
        yield 'fermée en 1950' => ['1950-06-01', true];
        yield 'fermée il y a juste 50 ans' => ['1976-12-31', true];
        yield 'fermée en 1977' => ['1977-01-01', false];
    }

    #[DataProvider('closureAges')]
    public function test_an_old_closure_without_any_track_observation_is_doubtful(string $closure, bool $doubtful): void
    {
        $segment = $this->auditSegment($this->audited, $this->current, self::TRACK, 'abandoned', 'unknown');
        $this->auditEvent($this->audited, 'opening', '1900-05-01');
        $this->auditEvent($this->audited, 'closure', $closure);

        $row = $this->audit($segment);

        $this->assertSame($doubtful ? ['track_unconfirmed'] : [], $row['evidence']['classification']['rules']);
        $this->assertSame($doubtful ? ['fair', ['rule_track_unconfirmed', 'sources_agree']] : ['good', ['sources_agree']], self::classification($row));
    }

    public function test_a_declassification_also_dates_the_end(): void
    {
        $segment = $this->auditSegment($this->audited, $this->current, self::TRACK, 'abandoned', 'unknown');
        $this->auditEvent($this->audited, 'opening', '1900-05-01');
        $this->auditEvent($this->audited, 'declassification', '1940-01-01');

        $this->assertSame(['track_unconfirmed'], $this->audit($segment)['evidence']['classification']['rules']);
    }

    /** @return iterable<string, array{0: string, 1: bool}> */
    public static function trackObservations(): iterable
    {
        yield 'voie OSM désaffectée' => ['disused', false];
        yield 'voie OSM en place' => ['rail', false];
        yield 'voie OSM abandonnée (déposée)' => ['abandoned', true];
    }

    #[DataProvider('trackObservations')]
    public function test_an_observed_track_lifts_the_doubt(string $layer, bool $doubtful): void
    {
        $segment = $this->auditSegment($this->audited, $this->current, self::TRACK, 'abandoned', 'unknown');
        $this->auditEvent($this->audited, 'opening', '1900-05-01');
        $this->auditEvent($this->audited, 'closure', '1950-06-01');
        $this->auditTrack('osm', self::TRACK, $this->audited->id, $layer);

        $this->assertSame($doubtful ? ['track_unconfirmed'] : [], $this->audit($segment)['evidence']['classification']['rules']);
    }

    public function test_the_doubt_needs_a_closed_current_non_heritage_segment(): void
    {
        $this->auditEvent($this->audited, 'opening', '1900-05-01');
        $this->auditEvent($this->audited, 'closure', '1950-06-01');
        $gone = $this->auditSegment($this->audited, $this->current, self::TRACK, 'dismantled', 'unknown');
        $heritage = $this->auditSegment($this->audited, $this->current, 'LINESTRING(2.02 48.0, 2.03 48.0)', 'abandoned', 'unknown', heritage: true);
        $past = $this->auditSegment($this->audited, $this->past, self::TRACK, 'abandoned', 'unknown');

        $this->assertSame([], $this->audit($gone)['evidence']['classification']['rules']);
        $this->assertSame([], $this->audit($heritage)['evidence']['classification']['rules']);
        $this->assertSame([], $this->audit($past, $this->past)['evidence']['classification']['rules']);
    }

    public function test_a_dismantling_alone_does_not_date_the_closure(): void
    {
        $segment = $this->auditSegment($this->audited, $this->current, self::TRACK, 'abandoned', 'unknown');
        $this->auditEvent($this->audited, 'opening', '1900-05-01');
        $this->auditEvent($this->audited, 'closure', '1990-01-01');
        // Dépose ancienne mal datée : seule la dernière fermeture compte (1990, pas assez ancienne).
        $this->auditEvent($this->audited, 'dismantling', '1930-01-01', 'lignes-oubliees');

        $this->assertSame([], $this->audit($segment)['evidence']['classification']['rules']);
    }

    // ─── Train touristique ───────────────────────────────────────────────

    public function test_a_heritage_segment_is_not_contradicted_by_a_closure(): void
    {
        $segment = $this->auditSegment($this->audited, $this->current, self::TRACK, 'in_service', 'mixed', heritage: true);
        $this->auditEvent($this->audited, 'opening', '1900-05-01');
        $this->auditEvent($this->audited, 'closure', '1950-06-01');

        $row = $this->audit($segment);
        $wikipedia = self::verdict($row, 'wikipedia');

        $this->assertSame([true, null, true], [$wikipedia['agrees'], $wikipedia['why'], $wikipedia['heritage']]);
        $this->assertSame(['class' => 'closed', 'traffic' => null], $wikipedia['expected']);
        $this->assertTrue($row['evidence']['classification']['actual']['heritage']);
        $this->assertSame('good', $row['classification_grade']);
    }

    public function test_a_heritage_segment_is_still_contradicted_by_a_later_opening(): void
    {
        $segment = $this->auditSegment($this->audited, $this->past, self::TRACK, 'in_service', 'passenger', heritage: true);
        $this->auditEvent($this->audited, 'opening', '1990-05-01');

        $wikipedia = self::verdict($this->audit($segment, $this->past), 'wikipedia');

        $this->assertSame([false, 'expected_not_open'], [$wikipedia['agrees'], $wikipedia['why']]);
        $this->assertArrayNotHasKey('heritage', $wikipedia);
    }

    // ─── Événements : portée et chronologies muettes ─────────────────────

    public function test_an_unlocated_opening_is_ignored(): void
    {
        $segment = $this->auditSegment($this->audited, $this->current, self::TRACK, 'in_service', 'mixed');
        $this->auditEvent($this->audited, 'opening', '1900-05-01', resolution: 'unresolved');

        $this->assertSame(['unknown', ['no_source']], self::classification($this->audit($segment)));
    }

    public function test_a_localized_event_counts_only_near_the_segment(): void
    {
        $near = $this->auditSegment($this->audited, $this->current, self::TRACK, 'in_service', 'mixed');
        $far = $this->auditSegment($this->audited, $this->current, 'LINESTRING(2.03 48.0, 2.04 48.0)', 'in_service', 'mixed');
        $this->auditEvent($this->audited, 'opening', '1900-05-01', resolution: 'pk', wkt: 'LINESTRING(2.0 48.0002, 2.01 48.0002)');

        $this->assertSame('wikipedia', self::verdict($this->audit($near), 'wikipedia')['source']);
        $this->assertSame(['unknown', ['no_source']], self::classification($this->audit($far)));
    }

    public function test_an_lgv_ignores_events_before_high_speed(): void
    {
        $lgv = Line::factory()->create(['code' => '654332', 'is_lgv' => true, 'network' => 'principal']);
        $segment = $this->auditSegment($lgv, $this->current, self::TRACK, 'in_service', 'passenger');
        $this->auditEvent($lgv, 'opening', '1900-05-01');
        $this->auditEvent($lgv, 'closure', '1975-01-01');

        $row = $this->audit($segment, line: $lgv);
        $this->assertSame(['unknown', ['no_source']], self::classification($row));
        $this->assertSame([], $row['evidence']['classification']['rules'], 'voyageurs seuls permis sur une LGV');

        $this->auditEvent($lgv, 'opening', '1981-09-27');
        $wikipedia = self::verdict($this->audit($segment, line: $lgv), 'wikipedia');
        $this->assertSame([true, 1981], [$wikipedia['agrees'], $wikipedia['first_opening_year']]);
    }

    public function test_an_unlocated_closure_saves_a_closed_segment(): void
    {
        $segment = $this->auditSegment($this->audited, $this->past, self::TRACK, 'abandoned', 'unknown');
        $this->auditEvent($this->audited, 'opening', '1900-05-01');
        $closure = $this->auditEvent($this->audited, 'closure', '1950-06-01', resolution: 'unresolved');

        $row = $this->audit($segment, $this->past);
        $wikipedia = self::verdict($row, 'wikipedia');

        $this->assertTrue($wikipedia['agrees']);
        $this->assertTrue($wikipedia['unlocated_end']);
        $this->assertTrue($wikipedia['weak']);
        $this->assertSame(['closure', '1950-06-01'], [end($wikipedia['events'])['type'], end($wikipedia['events'])['date']]);
        $this->assertSame($closure, end($wikipedia['events'])['id']);
        $this->assertSame(['fair', ['weak_sources_only']], self::classification($row));
    }

    public function test_an_unlocated_closure_after_the_era_does_not_count(): void
    {
        $segment = $this->auditSegment($this->audited, $this->past, self::TRACK, 'abandoned', 'unknown');
        $this->auditEvent($this->audited, 'opening', '1900-05-01');
        $this->auditEvent($this->audited, 'closure', '1970-06-01', resolution: 'unresolved');

        // Sans la fin non localisée, Wikipédia ne date que l'ouverture : son silence ne contredit pas.
        $this->assertSame(['unknown', ['no_source']], self::classification($this->audit($segment, $this->past)));
    }

    public function test_an_unlocated_closure_does_not_help_an_open_segment(): void
    {
        $segment = $this->auditSegment($this->audited, $this->past, self::TRACK, 'in_service', 'mixed');
        $this->auditEvent($this->audited, 'opening', '1900-05-01');
        $this->auditEvent($this->audited, 'closure', '1950-06-01', 'wikidata', 'unresolved');

        $row = $this->audit($segment, $this->past);

        $this->assertSame(['wikipedia'], array_column($row['evidence']['classification']['verdicts'], 'source'), 'Wikidata ne parle que de la fin non localisée');
        $this->assertFalse(self::verdict($row, 'wikipedia')['unlocated_end']);
    }

    public function test_a_source_that_only_dates_closures(): void
    {
        $closed = $this->auditSegment($this->audited, $this->past, self::TRACK, 'abandoned', 'unknown');
        $this->auditEvent($this->audited, 'closure', '1950-06-01', 'ohm');

        $ohm = self::verdict($this->audit($closed, $this->past), 'ohm');
        $this->assertSame(['class' => 'closed', 'traffic' => null], $ohm['expected']);
        $this->assertSame([true, null, 6], [$ohm['agrees'], $ohm['first_opening_year'], $ohm['priority']]);
    }

    /** @return iterable<string, array{0: string, 1: string}> */
    public static function silentChronologies(): iterable
    {
        yield 'fermeture après l’ère' => ['closure', '2010-06-01'];
        yield 'fin des voyageurs seule' => ['closure_passenger', '1950-06-01'];
    }

    #[DataProvider('silentChronologies')]
    public function test_a_closure_only_source_that_says_nothing_of_the_era(string $type, string $date): void
    {
        $segment = $this->auditSegment($this->audited, $this->past, self::TRACK, 'abandoned', 'unknown');
        $this->auditEvent($this->audited, $type, $date, 'ohm');

        $this->assertSame(['unknown', ['no_source']], self::classification($this->audit($segment, $this->past)));
    }

    public function test_a_source_that_only_dates_openings_does_not_contradict_a_closure(): void
    {
        $segment = $this->auditSegment($this->audited, $this->past, self::TRACK, 'abandoned', 'unknown');
        $this->auditEvent($this->audited, 'opening', '1900-05-01', 'ohm');
        $this->auditEvent($this->audited, 'opening', '1900-05-01', 'wikipedia');
        $this->auditEvent($this->audited, 'closure', '1950-06-01', 'wikipedia');

        $row = $this->audit($segment, $this->past);

        $this->assertSame(['wikipedia'], array_column($row['evidence']['classification']['verdicts'], 'source'));
    }

    public function test_a_contradiction_near_the_date_is_soft(): void
    {
        $segment = $this->auditSegment($this->audited, $this->past, self::TRACK, 'abandoned', 'unknown');
        $this->auditEvent($this->audited, 'opening', '1900-05-01');
        $this->auditEvent($this->audited, 'closure', '1962-06-01');

        $row = $this->audit($segment, $this->past);
        $wikipedia = self::verdict($row, 'wikipedia');

        $this->assertSame([false, true], [$wikipedia['agrees'], $wikipedia['near_date']]);
        $this->assertSame(['fair', ['weak_contradiction']], self::classification($row));
    }

    // ─── Exactitude : jonctions, gares, longueur ─────────────────────────

    public function test_a_free_end_close_to_another_line_is_a_junction_gap(): void
    {
        $segment = $this->auditSegment($this->audited, $this->current, self::TRACK, 'in_service', 'mixed', 'osm');
        $neighbour = Line::factory()->create(['slug' => 'rfn-654333', 'code' => '654333', 'is_lgv' => false]);
        // Environ 3 m à l'est du bout du tronçon (0,00004° de longitude à 48° N).
        $this->auditSegment($neighbour, $this->current, 'LINESTRING(2.01004 48.0, 2.02 48.0)', 'in_service', 'mixed', 'osm');

        $row = $this->audit($segment);
        $gaps = $row['evidence']['accuracy']['junction_gaps'];

        $this->assertCount(1, $gaps);
        $this->assertSame('rfn-654333', $gaps[0]['other_line']);
        $this->assertEqualsWithDelta(2.98, $gaps[0]['m'], 0.05);
        $this->assertEqualsWithDelta(2.01, $gaps[0]['lon'], 0.00001);
        $this->assertEqualsWithDelta(48.0, $gaps[0]['lat'], 0.00001);
        $this->assertSame(['poor', ['junction_gap']], [$row['accuracy_grade'], $row['evidence']['accuracy']['reasons']]);
    }

    public function test_stations_of_the_segment_and_their_order(): void
    {
        $segment = $this->auditSegment($this->audited, $this->current, 'LINESTRING(2.0 48.0, 2.03 48.0)', 'in_service', 'mixed', 'osm');
        $this->auditSegment($this->audited, $this->current, 'LINESTRING(2.03 48.0, 2.04 48.0)', 'service_track', 'freight', 'osm');
        // PK croissants, mais la troisième gare revient vers l'ouest : une inversion. La deuxième est à 300 m du tracé.
        foreach ([[2.0, 48.0, 0], [2.02, 48.0027, 1_500], [2.01, 48.0, 3_000], [2.03, 48.0, 4_500]] as $i => [$lon, $lat, $pk]) {
            $station = Station::factory()->create(['slug' => 'gare-oscar-'.$i, 'name' => 'Gare Oscar '.$i, 'geom' => Point::makeGeodetic($lat, $lon)]);
            DB::table('line_stops')->insert(['line_id' => $this->audited->id, 'era_id' => $this->current->id, 'station_id' => $station->id,
                'position' => $i + 1, 'pk_m' => $pk, 'effective_status' => 'active', 'is_terminal' => false]);
        }

        $audit = app(SegmentAuditor::class)->auditLine($this->audited->id, [2030], self::AS_OF);
        $row = self::auditedSegment($audit, $this->current->id, $segment);

        $this->assertSame(['gare-oscar-0', 'gare-oscar-1', 'gare-oscar-2', 'gare-oscar-3'], array_column($row['evidence']['accuracy']['stations'], 'slug'));
        $this->assertSame([0, 1_500, 3_000, 4_500], array_column($row['evidence']['accuracy']['stations'], 'pk_m'));
        $this->assertEqualsWithDelta(300.2, $row['evidence']['accuracy']['stations'][1]['distance_m'], 0.5);
        $this->assertSame('Gare Oscar 1', $row['evidence']['accuracy']['stations'][1]['name']);
        $this->assertSame(['fair', ['station_off_track']], [$row['accuracy_grade'], $row['evidence']['accuracy']['reasons']]);
        $this->assertSame(1, $audit[0]['line']['evidence']['accuracy']['station_order_inversions']);
        $this->assertContains('station_order', $audit[0]['line']['evidence']['accuracy']['reasons']);
    }

    /** @return iterable<string, array{0: int, 1: ?int, 2: ?string}> */
    public static function pkExtents(): iterable
    {
        yield 'étendue de 5 km' => [5_000, 5_000, 'lignes-oubliees-pk'];
        yield 'étendue d’un kilomètre au plus' => [1_000, null, null];
    }

    #[DataProvider('pkExtents')]
    public function test_official_length_from_lignes_oubliees_pk(int $end, ?int $official, ?string $source): void
    {
        $this->auditSegment($this->audited, $this->current, self::TRACK, 'in_service', 'mixed', 'osm');
        $event = $this->auditEvent($this->audited, 'closure', '2010-01-01', 'lignes-oubliees', 'pk');
        DB::table('line_events')->where('id', $event)->update(['section_pk_start_m' => $end, 'section_pk_end_m' => 0]);
        // Événement d'une autre source ou sans fin de PK : jamais compté.
        $other = $this->auditEvent($this->audited, 'closure', '2010-01-01', 'wikipedia', 'pk');
        DB::table('line_events')->where('id', $other)->update(['section_pk_start_m' => 0, 'section_pk_end_m' => 90_000]);
        $open = $this->auditEvent($this->audited, 'closure', '2011-01-01', 'lignes-oubliees', 'pk');
        DB::table('line_events')->where('id', $open)->update(['section_pk_start_m' => 80_000]);

        $line = app(SegmentAuditor::class)->auditLine($this->audited->id, [2030], self::AS_OF)[0]['line'];

        $this->assertSame([$official, $source], [$line['evidence']['accuracy']['official_length_m'], $line['evidence']['accuracy']['official_source']]);
    }

    public function test_the_line_length_wins_over_the_pk_extent(): void
    {
        $this->audited->update(['length_official_m' => 800]);
        $this->auditSegment($this->audited, $this->current, self::TRACK, 'in_service', 'mixed', 'osm');
        $event = $this->auditEvent($this->audited, 'closure', '2010-01-01', 'lignes-oubliees', 'pk');
        DB::table('line_events')->where('id', $event)->update(['section_pk_start_m' => 0, 'section_pk_end_m' => 5_000]);

        $line = app(SegmentAuditor::class)->auditLine($this->audited->id, [2030], self::AS_OF)[0]['line'];

        $this->assertSame([800, 'line'], [$line['evidence']['accuracy']['official_length_m'], $line['evidence']['accuracy']['official_source']]);
    }
}
