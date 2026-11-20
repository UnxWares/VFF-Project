<?php

namespace Tests\Unit\Accidents;

use App\Enums\AccidentDatePrecision;
use App\Enums\AccidentLinkConfidence;
use App\Enums\AccidentLocationPrecision;
use App\Services\Accidents\AccidentCandidate;
use App\Services\Accidents\AccidentImporter;
use App\Services\Accidents\AccidentLineMatcher;
use Tests\TestCase;

/**
 * Règles de l'import qui ne lisent pas la base : bilan retenu et désaccord des sources, même
 * accident vu par deux sources, ères encadrant une date, confiance d'un rattachement.
 */
class AccidentMatchingRulesTest extends TestCase
{
    private function candidate(string $key, string $date, ?string $insee = null, ?float $lat = null, ?float $lng = null): AccidentCandidate
    {
        $c = new AccidentCandidate($key);
        $c->date = $date;
        $c->precision = AccidentDatePrecision::Day;
        $c->communeInsee = $insee;
        $c->lat = $lat;
        $c->lng = $lng;

        return $c;
    }

    public function test_official_investigation_toll_wins_and_disagreement_is_reported(): void
    {
        $importer = app(AccidentImporter::class);

        $this->assertSame(
            ['value' => 7, 'source' => 'bea-tt', 'disputed' => true, 'detail' => 'Wikidata 6, BEA-TT 7'],
            $importer->decide(['wikidata' => 6, 'bea-tt' => 7], ['bea-tt', 'wikidata', 'wikipedia']),
        );
        $this->assertFalse($importer->decide(['wikidata' => 43, 'wikipedia' => 43], ['bea-tt', 'wikidata', 'wikipedia'])['disputed']);
        $this->assertSame('wikipedia', $importer->decide(['wikipedia' => 108], ['wikidata', 'wikipedia'])['source']);
        $this->assertNull($importer->decide([], ['wikidata'])['value']);
    }

    public function test_same_accident_seen_by_two_sources(): void
    {
        $importer = app(AccidentImporter::class);
        $wd = $this->candidate('wikidata:Q1', '2013-07-12', '91103', 48.6072, 2.3022);

        $this->assertTrue($importer->sameEvent($wd, $this->candidate('bea-tt:a', '2013-07-12', '91103')));
        // Un jour d'écart et lieux à moins de 20 km.
        $this->assertTrue($importer->sameEvent($wd, $this->candidate('bea-tt:b', '2013-07-13', null, 48.70, 2.35)));
        // Même commune, dates à une semaine près : même accident (écart signalé à la fusion).
        $this->assertTrue($importer->sameEvent($wd, $this->candidate('bea-tt:c', '2013-07-17', '91103')));
        $this->assertFalse($importer->sameEvent($wd, $this->candidate('bea-tt:d', '2013-07-25', '91103')));
        // Même jour, mais à l'autre bout de la France.
        $this->assertFalse($importer->sameEvent($wd, $this->candidate('bea-tt:e', '2013-07-12', '13055', 43.30, 5.37)));
    }

    public function test_great_circle_distance(): void
    {
        $this->assertEqualsWithDelta(392000, AccidentImporter::distanceM(48.8566, 2.3522, 45.7640, 4.8357), 2000);
        $this->assertSame(0.0, AccidentImporter::distanceM(47.0, 3.0, 47.0, 3.0));
    }

    public function test_eras_surrounding_a_date(): void
    {
        $matcher = app(AccidentLineMatcher::class);

        $this->assertSame([1930, 1940], $matcher->eraYears(1933));
        $this->assertSame([1840, 1850], $matcher->eraYears(1842));
        $this->assertSame([2020, 2030], $matcher->eraYears(2025));
        $this->assertSame([2030], $matcher->eraYears(2030));
        $this->assertSame([1830, 1840], $matcher->eraYears(1828));
    }

    public function test_link_confidence_rules(): void
    {
        $matcher = app(AccidentLineMatcher::class);
        $exact = AccidentLocationPrecision::Exact;

        $this->assertSame(AccidentLinkConfidence::High, $matcher->confidence($exact, 300, true, false, 800));
        $this->assertSame(AccidentLinkConfidence::High, $matcher->confidence($exact, 40, false, true, 800));
        $this->assertSame(AccidentLinkConfidence::Medium, $matcher->confidence($exact, 40, false, false, 800));
        $this->assertSame(AccidentLinkConfidence::Low, $matcher->confidence($exact, 600, false, true, 800));
        $this->assertSame(AccidentLinkConfidence::Low, $matcher->confidence($exact, 3000, true, true, 800));
        $this->assertSame(AccidentLinkConfidence::Medium, $matcher->confidence(AccidentLocationPrecision::Commune, 200, true, true, 5000));
        $this->assertSame(AccidentLinkConfidence::Low, $matcher->confidence(AccidentLocationPrecision::Commune, 200, false, true, 5000));
    }
}
