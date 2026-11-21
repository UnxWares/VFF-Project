<?php

namespace Tests\Unit\StationAudit;

use App\Services\StationAudit\PlacementGrade;
use App\Services\StationAudit\PlacementGrader;
use App\Services\StationAudit\SourcePosition;
use PHPUnit\Framework\TestCase;

class PlacementGraderTest extends TestCase
{
    private static function p(string $source, int $distance, ?int $precision = null): SourcePosition
    {
        return new SourcePosition($source, $source.'-ref', 2.0, 48.0, $distance, null, null, $precision);
    }

    public function test_no_position_is_none(): void
    {
        $this->assertSame(PlacementGrade::None, PlacementGrader::grade([], 'sncf')->grade);
    }

    public function test_only_the_retained_family_is_isolated(): void
    {
        // SNCF et SNCF voyageurs : même famille, elles ne se confirment pas.
        $r = PlacementGrader::grade([self::p('sncf', 0), self::p('sncf_voyageurs', 3)], 'sncf');
        $this->assertSame(PlacementGrade::Isolated, $r->grade);
        $this->assertSame(1, $r->families);
        $this->assertNull($r->nearestAgreeingM);
    }

    public function test_an_independent_source_within_20_m_agrees(): void
    {
        $r = PlacementGrader::grade([self::p('sncf', 0), self::p('osm', 12), self::p('bdtopo', 0)], 'sncf');
        $this->assertSame(PlacementGrade::Agree, $r->grade);
        $this->assertSame(0, $r->nearestAgreeingM);
        $this->assertSame(12, $r->farthestPreciseM);
        $this->assertSame(3, $r->families);
    }

    public function test_the_nearest_between_20_and_100_m_is_close(): void
    {
        $this->assertSame(PlacementGrade::Close, PlacementGrader::grade([self::p('sncf', 0), self::p('osm', 45)], 'sncf')->grade);
    }

    public function test_a_lone_far_source_is_set_aside_when_another_family_is_within_50_m(): void
    {
        // Règle A5 (21/11) : une seule famille lointaine ne fait plus « disagree » à elle seule.
        $r = PlacementGrader::grade([self::p('osm', 0), self::p('wikidata', 14), self::p('sncf', 101)], 'osm');
        $this->assertSame(PlacementGrade::Agree, $r->grade);
        $this->assertSame(101, $r->farthestPreciseM);
        $this->assertSame('sncf', $r->outlier?->source);
    }

    public function test_a_precise_source_beyond_100_m_disagrees_without_a_close_family(): void
    {
        $r = PlacementGrader::grade([self::p('osm', 0), self::p('wikidata', 51), self::p('sncf', 101)], 'osm');
        $this->assertSame(PlacementGrade::Disagree, $r->grade);
        $this->assertNull($r->outlier);
    }

    public function test_a_coarse_source_contradicts_only_beyond_one_kilometre(): void
    {
        $this->assertSame(PlacementGrade::Isolated, PlacementGrader::grade([self::p('sncf', 0), self::p('wikidata', 476)], 'sncf')->grade);
        $this->assertSame(PlacementGrade::Disagree, PlacementGrader::grade([self::p('sncf', 0), self::p('wikidata', 2158)], 'sncf')->grade);
    }

    public function test_the_nearest_position_represents_its_family(): void
    {
        // Deux nœuds OSM : l'un à 5 m, l'autre (homonyme) à 900 m. La famille OSM est d'accord.
        $r = PlacementGrader::grade([self::p('sncf', 0), self::p('osm', 900), self::p('osm', 5)], 'sncf');
        $this->assertSame(PlacementGrade::Agree, $r->grade);
        $this->assertSame(5, $r->farthestPreciseM);
    }

    public function test_a_list_position_is_precise_only_within_50_m(): void
    {
        $this->assertTrue(PlacementGrader::isPrecise('wikipedia_schema', 40));
        $this->assertFalse(PlacementGrader::isPrecise('lignes-oubliees', 250));
        $this->assertFalse(PlacementGrader::isPrecise('wikipedia_schema', null));
        $this->assertTrue(PlacementGrader::isPrecise('bdtopo'));
        $this->assertFalse(PlacementGrader::isPrecise('wikidata'));
        // Une liste calée à 250 m à 300 m de la position : pas une contradiction.
        $this->assertSame(PlacementGrade::Isolated, PlacementGrader::grade([self::p('osm', 0), self::p('lignes-oubliees', 300, 250)], 'osm')->grade);
    }

    public function test_derived_copies_never_count(): void
    {
        $copy = new SourcePosition('lignes-oubliees', 'lo', 2.0, 48.0, 0, null, null, 0, derived: true);
        $r = PlacementGrader::grade([self::p('sncf', 0), $copy], 'sncf');
        $this->assertSame(PlacementGrade::Isolated, $r->grade);
        $this->assertSame(1, $r->families);
    }

    public function test_retained_position_outvoted_by_two_agreeing_families(): void
    {
        // Retenue en 2,0 ; OSM et Wikidata à 465 et 461 m, à 4 m l'un de l'autre.
        $osm = new SourcePosition('osm', 'n', 2.00623, 48.0, 465);
        $wd = new SourcePosition('wikidata', 'Q', 2.00618, 48.0, 461);
        $this->assertTrue(PlacementGrader::grade([self::p('sncf', 0), $osm, $wd], 'sncf')->outvoted);
        // Une seule autre famille loin : désaccord, pas de minorité.
        $this->assertFalse(PlacementGrader::grade([self::p('sncf', 0), $osm], 'sncf')->outvoted);
        // Deux familles d'accord avec la retenue : elle n'est pas minoritaire.
        $this->assertFalse(PlacementGrader::grade([self::p('sncf', 0), self::p('bdtopo', 0), self::p('ohm', 5), $osm, $wd], 'sncf')->outvoted);
        $this->assertEqualsWithDelta(3.7, PlacementGrader::metres($osm, $wd), 0.2);
    }
}
