<?php

namespace Tests\Unit\StationAudit;

use App\Services\StationAudit\DateComparison;
use App\Services\StationAudit\WikidataStationDates;
use PHPUnit\Framework\TestCase;

/**
 * Cas limites de la lecture des dates Wikidata des gares (WikidataStationDates::parse et ::time) et de
 * leur comparaison aux dates de VFF (DateComparison) : siècles, bornes d'années, déclarations
 * incomplètes, qualificatifs et références partiels.
 */
class WikidataStationDatesEdgesTest extends TestCase
{
    /** @return array<string, mixed> */
    private static function value(string $time, int $precision): array
    {
        return ['time' => $time, 'precision' => $precision];
    }

    /** @return array<string, mixed> */
    private static function statement(?string $id, string $time, int $precision, array $extra = []): array
    {
        return array_filter(['id' => $id, 'mainsnak' => ['datavalue' => ['value' => self::value($time, $precision)]]], fn ($v) => $v !== null) + $extra;
    }

    public function test_a_century_is_stored_at_its_first_year(): void
    {
        // Convention de Wikidata : « XIXe siècle » s'écrit +1850 ou +1900 (siècle = année / 100 arrondie au-dessus).
        $this->assertSame(['1801-01-01', 'century'], WikidataStationDates::time(self::value('+1850-00-00T00:00:00Z', 7)));
        $this->assertSame(['1801-01-01', 'century'], WikidataStationDates::time(self::value('+1900-00-00T00:00:00Z', 7)));
        $this->assertSame(['1801-01-01', 'century'], WikidataStationDates::time(self::value('+1801-00-00T00:00:00Z', 7)));
        $this->assertSame(['1901-01-01', 'century'], WikidataStationDates::time(self::value('+1901-00-00T00:00:00Z', 7)));
        $this->assertSame(['1701-01-01', 'century'], WikidataStationDates::time(self::value('+1800-00-00T00:00:00Z', 7)));
    }

    public function test_year_bounds_and_malformed_values(): void
    {
        $this->assertSame(['1700-01-01', 'year'], WikidataStationDates::time(self::value('+1700-06-01T00:00:00Z', 9)));
        $this->assertNull(WikidataStationDates::time(self::value('+1699-06-01T00:00:00Z', 9)));
        // Avant notre ère, sans signe, précision absente ou valeur qui n'est pas un tableau : écartées.
        $this->assertNull(WikidataStationDates::time(self::value('-1866-01-01T00:00:00Z', 9)));
        $this->assertNull(WikidataStationDates::time(self::value('1866-01-01T00:00:00Z', 9)));
        $this->assertNull(WikidataStationDates::time(['time' => '+1866-01-01T00:00:00Z']));
        $this->assertNull(WikidataStationDates::time('+1866-01-01T00:00:00Z'));
        $this->assertNull(WikidataStationDates::time(null));
        // Jour sans mois ni jour connus : complété au 1er janvier (date réelle) ; 29 février d'une année bissextile seulement.
        $this->assertSame(['1866-01-01', 'day'], WikidataStationDates::time(self::value('+1866-00-00T00:00:00Z', 11)));
        $this->assertSame(['1868-02-29', 'day'], WikidataStationDates::time(self::value('+1868-02-29T00:00:00Z', 11)));
        $this->assertNull(WikidataStationDates::time(self::value('+1867-02-29T00:00:00Z', 11)));
        $this->assertSame(['1866-01-01', 'month'], WikidataStationDates::time(self::value('+1866-00-00T00:00:00Z', 10)));
        $this->assertSame(['1860-01-01', 'decade'], WikidataStationDates::time(self::value('+1869-12-31T00:00:00Z', 8)));
        // Le fuseau de l'horodatage ne change pas le jour (Wikidata écrit toujours en UTC, sans heure).
        $this->assertSame(['1866-05-21', 'day'], WikidataStationDates::time(self::value('+1866-05-21T23:00:00Z', 11)));
    }

    public function test_incomplete_statements_are_skipped(): void
    {
        $claims = [
            'P1619' => [
                'pas un tableau',
                ['rank' => 'normal'],
                ['mainsnak' => ['snaktype' => 'novalue']],
                self::statement('Q9$ok', '+1866-05-21T00:00:00Z', 11),
            ],
            // Propriété réduite à une seule déclaration (non listée) : ignorée sans erreur.
            'P3999' => 'inattendu',
            'P31' => [self::statement('Q9$x', '+1900-01-01T00:00:00Z', 11)],
        ];
        $c = WikidataStationDates::parse('Q9', $claims);
        $this->assertCount(1, $c);
        $this->assertSame(['opening', 'normal', 'Q9#P1619#Q9$ok', [], []], [$c[0]['kind'], $c[0]['rank'], $c[0]['source_ref'], $c[0]['qualifiers'], $c[0]['references']]);
        $this->assertSame([], WikidataStationDates::parse('Q9', []));
    }

    public function test_source_ref_is_stable_without_a_statement_id(): void
    {
        $claims = ['P1619' => [self::statement(null, '+1866-05-21T00:00:00Z', 11)]];
        $a = WikidataStationDates::parse('Q9', $claims)[0]['source_ref'];
        $this->assertSame($a, WikidataStationDates::parse('Q9', $claims)[0]['source_ref']);
        $this->assertMatchesRegularExpression('/^Q9#P1619#[0-9a-f]{32}$/', $a);
        // Identifiant Wikidata long (Q…$GUID) : les 36 derniers caractères, le GUID.
        $long = WikidataStationDates::parse('Q9', ['P1619' => [self::statement('Q9$0D8C2F6E-1234-4ABC-9DEF-0123456789AB', '+1866-05-21T00:00:00Z', 11)]]);
        $this->assertSame('Q9#P1619#0D8C2F6E-1234-4ABC-9DEF-0123456789AB', $long[0]['source_ref']);
    }

    public function test_kind_from_qualifiers(): void
    {
        $q = fn (string $p, string ...$ids): array => [$p => array_map(fn (string $id): array => ['datavalue' => ['value' => ['id' => $id]]], $ids)];
        $kind = fn (string $property, array $qualifiers): string => WikidataStationDates::parse('Q9', [$property => [
            self::statement('Q9$k', '+1950-01-01T00:00:00Z', 11, ['qualifiers' => $qualifiers]),
        ]])[0]['kind'];
        $this->assertSame('reopening', $kind('P1619', $q('P1480', 'Q16571590')));
        $this->assertSame('reopening', $kind('P1619', $q('P518', 'Q16571590')));
        // « Réouverture » n'a de sens que pour une ouverture ; « voyageurs » que pour une fermeture.
        $this->assertSame('closure', $kind('P3999', $q('P1480', 'Q16571590')));
        $this->assertSame('opening', $kind('P1619', $q('P518', 'Q1433891')));
        // Voyageurs et fret à la fois : la fin des voyageurs l'emporte.
        $this->assertSame('closure_passenger', $kind('P3999', $q('P518', 'Q2234623', 'Q334166')));
        $this->assertSame('closure_freight', $kind('P3999', $q('P518', 'Q1662079')));
        $this->assertSame('closure', $kind('P3999', $q('P518', 'Q42')));
    }

    public function test_partial_qualifiers_and_references(): void
    {
        $statement = self::statement('Q9$q', '+1950-01-01T00:00:00Z', 11, [
            'qualifiers' => [
                'P1319' => [['datavalue' => ['value' => self::value('+1948-00-00T00:00:00Z', 9)]]],
                // Borne illisible : absente plutôt que fausse.
                'P1326' => [['datavalue' => ['value' => self::value('+1500-00-00T00:00:00Z', 9)]]],
                'P805' => [['snaktype' => 'somevalue'], ['datavalue' => ['value' => ['id' => 'Q7']]]],
                'P518' => [],
            ],
            'references' => [
                ['snaks' => ['P854' => [['datavalue' => ['value' => 'https://example.org/a']]], 'P248' => [['datavalue' => ['value' => ['id' => 'Q1']]]]]],
                ['snaks' => ['P854' => [['datavalue' => ['value' => '']]]]],
                ['snaks' => []],
                [],
            ],
        ]);
        $c = WikidataStationDates::parse('Q9', ['P1619' => [$statement]])[0];
        $this->assertSame(['earliest' => '1948-01-01', 'subject' => ['Q7']], $c['qualifiers']);
        $this->assertSame([['stated_in' => 'Q1', 'url' => 'https://example.org/a']], $c['references']);
    }

    public function test_century_comparison_uses_the_century_number(): void
    {
        // Candidat « XIXe siècle » (1801-01-01) : toute date de 1801 à 1900 en fait partie.
        $this->assertSame('less_precise', DateComparison::compare('1801-01-01', 'century', '1866-05-21', 'day')['comparison']);
        $this->assertSame('less_precise', DateComparison::compare('1801-01-01', 'century', '1900-12-31', 'day')['comparison']);
        $this->assertSame('differs', DateComparison::compare('1801-01-01', 'century', '1901-01-01', 'day')['comparison']);
        $this->assertSame('differs', DateComparison::compare('1801-01-01', 'century', '1800-06-01', 'year')['comparison']);
        $this->assertSame('same', DateComparison::compare('1801-01-01', 'century', '1801-01-01', 'century')['comparison']);
    }

    public function test_comparison_edges(): void
    {
        $this->assertSame(['comparison' => 'new', 'delta_days' => null], DateComparison::compare('1866-01-01', 'year', '', 'day'));
        // Précision VFF absente : le jour ; précision inconnue : l'année.
        $this->assertSame('less_precise', DateComparison::compare('1866-05-01', 'month', '1866-05-21', null)['comparison']);
        $this->assertSame('same', DateComparison::compare('1866-05-01', 'bizarre', '1866-09-01', 'year')['comparison']);
        // Même mois, jours différents, précision au mois d'un côté : d'accord.
        $this->assertSame(['comparison' => 'more_precise', 'delta_days' => 20], DateComparison::compare('1866-05-21', 'day', '1866-05-01', 'month'));
        $this->assertSame(['comparison' => 'differs', 'delta_days' => -1], DateComparison::compare('1866-04-30', 'day', '1866-05-01', 'month'));
        // Un jour d'écart à la précision du jour : désaccord ; l'écart est signé (candidat moins VFF).
        $this->assertSame(['comparison' => 'differs', 'delta_days' => 1], DateComparison::compare('1866-05-02', 'day', '1866-05-01', 'day'));
        // Passage à l'heure d'été au milieu, fuseau de Paris : l'écart reste un nombre de jours entier.
        $zone = date_default_timezone_get();
        try {
            date_default_timezone_set('Europe/Paris');
            $this->assertSame(184, DateComparison::compare('1990-09-30', 'day', '1990-03-30', 'day')['delta_days']);
            $this->assertSame(-184, DateComparison::compare('1990-03-30', 'day', '1990-09-30', 'day')['delta_days']);
        } finally {
            date_default_timezone_set($zone);
        }
    }
}
