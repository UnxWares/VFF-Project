<?php

namespace Tests\Unit\StationAudit;

use App\Services\StationAudit\DateAuditor;
use App\Services\StationAudit\DateGrade;
use App\Services\StationAudit\PlacementGrade;
use App\Services\StationAudit\PlacementGrader;
use App\Services\StationAudit\SourcePosition;
use App\Services\StationAudit\StationAuditor;
use PHPUnit\Framework\TestCase;

/**
 * Réglages des gares du 21/11/2026 (règles 2026-11-21.2) : une source lointaine seule ne fait plus
 * « disagree » (G3), fermetures Wikidata tirées du référentiel SNCF déclassées (G4).
 */
class StationAuditReglagesTest extends TestCase
{
    private static function p(string $source, int $distance, ?int $precision = null, bool $derived = false): SourcePosition
    {
        return new SourcePosition($source, $source.'-'.$distance, 2.0, 48.0, $distance, null, null, $precision, $derived);
    }

    public function test_a_lone_far_point_is_an_outlier_when_another_family_is_close(): void
    {
        // Berre : OSM, BD TOPO et Wikidata d'accord à 9-27 m, point SNCF isolé à 264 m.
        $r = PlacementGrader::grade([self::p('osm', 9), self::p('bdtopo', 27), self::p('wikidata', 20), self::p('sncf', 264)], 'correction');
        $this->assertSame(PlacementGrade::Agree, $r->grade);
        $this->assertSame(['sncf', 264], [$r->outlier?->source, $r->outlier?->distanceM]);
        // Accord seulement à 50 m : « close ».
        $this->assertSame(PlacementGrade::Close, PlacementGrader::grade([self::p('osm', PlacementGrader::OUTLIER_CONFIRM_M), self::p('sncf', 300)], 'bdtopo')->grade);
        // Wikidata à plus de 1 km, seule : écartée aussi.
        $coarse = PlacementGrader::grade([self::p('osm', 5), self::p('wikidata', 1500)], 'sncf');
        $this->assertSame([PlacementGrade::Agree, 'wikidata'], [$coarse->grade, $coarse->outlier?->source]);
    }

    public function test_disagree_stays_without_a_close_family_or_with_two_far_ones(): void
    {
        $alone = PlacementGrader::grade([self::p('osm', PlacementGrader::OUTLIER_CONFIRM_M + 1), self::p('sncf', 300)], 'bdtopo');
        $this->assertSame([PlacementGrade::Disagree, null], [$alone->grade, $alone->outlier]);
        $two = PlacementGrader::grade([self::p('osm', 3), self::p('sncf', 300), self::p('ohm', 250)], 'bdtopo');
        $this->assertSame([PlacementGrade::Disagree, null], [$two->grade, $two->outlier]);
        // Une position recopiée ne confirme rien.
        $copy = PlacementGrader::grade([self::p('lignes-oubliees', 0, 10, derived: true), self::p('sncf', 300)], 'osm');
        $this->assertSame(PlacementGrade::Disagree, $copy->grade);
        // Aucune source lointaine : pas de contrôle.
        $this->assertNull(PlacementGrader::grade([self::p('osm', 3)], 'sncf')->outlier);
    }

    /** @return array<string, mixed> */
    private static function facts(array $over = []): array
    {
        return $over + [
            'status' => 'declassified',
            'opened_at' => '1855-01-01', 'opened_precision' => 'year', 'opened_source' => 'wikipedia',
            'closed_at' => '2011-12-31', 'closed_precision' => 'day', 'closed_source' => 'wikidata',
            'events' => [], 'lines' => ['count' => 1, 'first_year' => 1855, 'last_year' => 2011, 'open' => 0],
            'freight_flag' => null, 'candidates' => [], 'track_offset_m' => 3, 'today_year' => 2026,
        ];
    }

    public function test_a_wikidata_closure_from_the_sncf_register_is_only_estimated(): void
    {
        // Les Longevilles-Rochejean : Wikidata 2011 (référentiel SNCF), Wikipédia 2023.
        $r = DateAuditor::audit(self::facts(['closure_sncf_referential' => true, 'closure_alternatives' => ['wikipedia 2023-12-10']]));
        $this->assertSame(DateGrade::Estimated, $r->closure);
        $this->assertSame(DateGrade::Estimated->value, $r->services['passenger']['grade']);
        $check = array_values(array_filter($r->checks, fn (array $c): bool => $c['code'] === 'closure_sncf_referential'));
        $this->assertSame('2011-12-31 ; wikipedia 2023-12-10', $check[0]['detail'] ?? null);

        $plain = DateAuditor::audit(self::facts());
        $this->assertSame(DateGrade::Sourced, $plain->closure);
        $this->assertNotContains('closure_sncf_referential', array_column($plain->checks, 'code'));
        // Fermeture d'une autre source : le drapeau ne joue pas.
        $ohm = DateAuditor::audit(self::facts(['closed_source' => 'ohm', 'closure_sncf_referential' => true]));
        $this->assertSame(DateGrade::Sourced, $ohm->closure);
        // Gare encore ouverte : sans objet.
        $open = DateAuditor::audit(self::facts(['status' => 'active', 'closure_sncf_referential' => true]));
        $this->assertSame(DateGrade::NotApplicable, $open->closure);
        $this->assertNotContains('closure_sncf_referential', array_column($open->checks, 'code'));
    }

    public function test_sncf_register_references_and_alternative_dates(): void
    {
        $url = '{"url": "https://ressources.data.sncf.com/explore/dataset/referentiel-gares-voyageurs/table/"}';
        $this->assertTrue(StationAuditor::onlySncfReferential("[{$url}]"));
        $this->assertFalse(StationAuditor::onlySncfReferential("[{$url}, {\"imported_from\": \"Q8447\"}]"));
        $this->assertFalse(StationAuditor::onlySncfReferential('[]'));
        $this->assertFalse(StationAuditor::onlySncfReferential('pas du json'));

        $alternatives = StationAuditor::closureAlternatives('2015-12-30', [
            ['kind' => 'closure', 'date' => '2015-12-30'], ['kind' => 'closure', 'date' => '2014-01-01'], ['kind' => 'opening', 'date' => '1876-01-01'],
        ], [
            ['type' => 'closure', 'date' => '2014-01-01', 'source' => 'ohm', 'inferred' => false],
            ['type' => 'closure_passenger', 'date' => '1990-01-01', 'source' => 'inferred:line_event', 'inferred' => true],
            ['type' => 'closure', 'date' => '2015-12-30', 'source' => 'wikidata', 'inferred' => false],
        ]);
        $this->assertSame(['wikidata 2014-01-01', 'ohm 2014-01-01'], $alternatives);
    }
}
