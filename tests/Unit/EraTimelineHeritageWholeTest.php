<?php

namespace Tests\Unit;

use App\Services\EraTimeline;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * EraTimeline::datedWholeHeritage : pour l'exploitation associative, une date « toute la ligne »
 * d'une source plus fiable passe avant la portion observée (et « attestée » à la date de la
 * source) d'une source moins fiable.
 */
class EraTimelineHeritageWholeTest extends TestCase
{
    private static function ev(string $type, string $date, array $extra = []): array
    {
        return ['type' => $type, 'date' => $date] + $extra;
    }

    /** Ligne hors RFN (CFBS Noyelles - Le Crotoy, 4481) : événements manual « toute la ligne ». */
    private static function crotoy(array $more): EraTimeline
    {
        return (new EraTimeline(array_merge([
            self::ev('opening', '1887-07-01', ['source' => 'manual', 'whole' => true]),
            self::ev('closure', '1969-12-31', ['source' => 'manual', 'whole' => true]),
            self::ev('heritage_start', '1971-07-04', ['source' => 'manual', 'whole' => true, 'heritage_kind' => 'tourist_train']),
        ], $more)))->heritageEndsCommercialTraffic();
    }

    public function test_manual_whole_line_date_beats_an_attested_localized_portion(): void
    {
        $t = self::crotoy([self::ev('heritage_start', '2026-01-01', ['source' => 'heritage', 'whole' => false, 'heritage_kind' => 'tourist_train'])]);

        foreach ([1980, 1990, 2020] as $year) {
            $this->assertSame('tourist_train', $this->state($t, $year)['heritage'] ?? null, "ère {$year}");
        }
        $this->assertSame('abandoned', $t->stateAt(1970)['status']);
    }

    public function test_without_localized_event_the_whole_line_date_applies(): void
    {
        $this->assertSame('tourist_train', $this->state(self::crotoy([]), 1980)['heritage'] ?? null);
    }

    public function test_localized_event_of_the_same_source_still_wins(): void
    {
        // 4489 : manual « toute la ligne » 1981, manual Le Passage - Les Tilliers 1991.
        $t = (new EraTimeline([
            self::ev('heritage_start', '1981-01-01', ['source' => 'manual', 'whole' => true, 'heritage_kind' => 'tourist_train']),
            self::ev('heritage_start', '1991-07-01', ['source' => 'manual', 'whole' => false, 'heritage_kind' => 'tourist_train']),
        ]))->heritageEndsCommercialTraffic();

        $this->assertNull($t->stateAt(1990));
        $this->assertSame('tourist_train', $this->state($t, 2000)['heritage'] ?? null);
    }

    /** @return array<string, array{0: string, 1: string, 2: bool}> */
    public static function sourceProvider(): array
    {
        return [
            'manual entière / heritage localisé' => ['manual', 'heritage', true],
            'wikipedia entière / heritage localisé' => ['wikipedia', 'heritage', true],
            'wikidata entière / lignes-oubliees localisé' => ['wikidata', 'lignes-oubliees', false],
            'heritage entière / heritage localisé' => ['heritage', 'heritage', false],
            'heritage entière / manual localisé' => ['heritage', 'manual', false],
        ];
    }

    /** Propriété : la date « toute la ligne » n'est retenue que si sa source est strictement plus fiable. */
    #[DataProvider('sourceProvider')]
    public function test_whole_line_wins_only_from_a_strictly_more_reliable_source(string $wholeSource, string $localSource, bool $wholeWins): void
    {
        $t = (new EraTimeline([
            self::ev('opening', '1880-01-01', ['source' => 'lignes-oubliees']),
            self::ev('closure', '1960-01-01', ['source' => 'lignes-oubliees']),
            self::ev('heritage_start', '1975-01-01', ['source' => $wholeSource, 'whole' => true, 'heritage_kind' => 'tourist_train']),
            self::ev('heritage_start', '2005-01-01', ['source' => $localSource, 'whole' => false, 'heritage_kind' => 'tourist_train']),
        ]))->heritageEndsCommercialTraffic();

        $this->assertSame($wholeWins, ($this->state($t, 1990)['heritage'] ?? null) === 'tourist_train');
        $this->assertSame('tourist_train', $this->state($t, 2010)['heritage'] ?? null);
    }

    public function test_heritage_end_follows_the_same_rule(): void
    {
        $t = self::crotoy([
            self::ev('heritage_end', '2008-06-01', ['source' => 'manual', 'whole' => true]),
            self::ev('heritage_end', '2026-01-01', ['source' => 'heritage', 'whole' => false]),
        ]);

        $this->assertSame('tourist_train', $this->state($t, 2000)['heritage'] ?? null);
        $this->assertArrayNotHasKey('heritage', $t->stateAt(2010) ?? []);
    }

    public function test_dedupe_keeps_the_whole_line_event(): void
    {
        $kept = EraTimeline::dedupe([
            self::ev('heritage_start', '1971-07-04', ['source' => 'manual', 'whole' => true, 'heritage_kind' => 'tourist_train']),
            self::ev('heritage_start', '2026-01-01', ['source' => 'heritage', 'whole' => false, 'heritage_kind' => 'tourist_train']),
            self::ev('closure', '1969-12-31', ['source' => 'manual', 'whole' => true]),
            self::ev('closure', '1990-01-01', ['source' => 'wikidata', 'whole' => false]),
        ]);

        $this->assertSame([['heritage_start', '1971-07-04'], ['closure', '1990-01-01']],
            array_map(fn ($e) => [$e['type'], $e['date']], $kept), 'la portion datée garde la priorité hors exploitation associative');
    }

    public function test_same_year_whole_openings_are_one_opening_not_a_completion(): void
    {
        // 752000 : Wikidata « 1981 » (année seule) et Wikipédia 27/09/1981 : pas d'achèvement en 1981.
        $this->assertSame('1981-01-01', EraTimeline::completionOpening([
            ['type' => 'opening', 'date' => '1981-01-01'], ['type' => 'opening', 'date' => '1981-09-27'],
        ])['date']);
        // Achèvement réel, une autre année : retenu.
        $this->assertSame('1983-09-25', EraTimeline::completionOpening([
            ['type' => 'opening', 'date' => '1981-09-27'], ['type' => 'opening', 'date' => '1981-01-01'], ['type' => 'opening', 'date' => '1983-09-25'],
        ])['date']);
    }

    /**
     * @return array<string, string>
     */
    private function state(EraTimeline $timeline, int $year, bool $isCurrent = false, ?int $previousYear = null): array
    {
        return $timeline->stateAt($year, $isCurrent, $previousYear) ?? [];
    }
}
