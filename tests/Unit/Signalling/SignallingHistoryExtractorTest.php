<?php

namespace Tests\Unit\Signalling;

use App\Services\Signalling\SignallingHistoryExtractor;
use App\Services\WikipediaLineParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Extraction des mises en service de signalisation d'un article de ligne : cas d'articles réels de
 * Wikipédia résumés en une phrase, sans réseau. Le but est autant de ne rien produire à tort (futur,
 * système remplacé, date d'une autre action) que de lire les cas sûrs.
 */
class SignallingHistoryExtractorTest extends TestCase
{
    private const TITLE = 'Ligne de Saint-Cloud à Saint-Nom-la-Bretèche - Forêt-de-Marly';

    private SignallingHistoryExtractor $extractor;

    protected function setUp(): void
    {
        $this->extractor = new SignallingHistoryExtractor(new WikipediaLineParser);
    }

    /** @return list<array<string, mixed>> */
    private function extract(string $body, string $title = self::TITLE): array
    {
        return $this->extractor->extract("== Histoire ==\n".$body, $title, new \DateTimeImmutable('2026-11-19'));
    }

    public function test_a_month_dated_extension_gives_the_system_the_portion_and_a_high_reliability(): void
    {
        $facts = $this->extract('En juin 1962, le block automatique lumineux est étendu de Garches à Saint-Nom-la-Bretèche.');

        $this->assertCount(1, $facts);
        $this->assertSame('BAL', $facts[0]['system']);
        $this->assertSame('block', $facts[0]['role']);
        $this->assertSame('1962-06-01', $facts[0]['date']);
        $this->assertSame('month', $facts[0]['precision']);
        $this->assertSame('high', $facts[0]['confidence']);
        $this->assertSame('section', $facts[0]['scope']);
        $this->assertSame(['Garches', 'Saint-Nom-la-Bretèche'], [$facts[0]['section_from'], $facts[0]['section_to']]);
        $this->assertSame('text', $facts[0]['origin']);
    }

    public function test_a_year_only_date_is_medium_and_a_full_date_in_a_list_is_high(): void
    {
        $year = $this->extract('Le BAPR est mis en service en 1996 de Molsheim à Obernai.');
        $day = $this->extract('* 21 novembre 2010 : mise en service du BAPR de Sainte-Pazanne à Saint-Gilles-Croix-de-Vie.');

        $this->assertSame(['BAPR', 'year', 'medium'], [$year[0]['system'], $year[0]['precision'], $year[0]['confidence']]);
        $this->assertSame(['BAPR', '2010-11-21', 'day', 'high', 'list'], [$day[0]['system'], $day[0]['date'], $day[0]['precision'], $day[0]['confidence'], $day[0]['origin']]);
    }

    public function test_the_system_that_is_replaced_is_not_commissioned(): void
    {
        $facts = $this->extract('En 1954, le block manuel unifié (BMU) remplace le cantonnement téléphonique entre Grenoble et Vif.');
        $this->assertSame(['BM'], array_column($facts, 'system'));

        $replaced = $this->extract('En 1976, le BAL est mis en service en remplacement d\'un block manuel unifié.');
        $this->assertSame(['BAL'], array_column($replaced, 'system'));

        $passive = $this->extract('Le block manuel de double voie, qui équipait la ligne entre Arnage et Le Mans, est remplacé par le BAPR le 13 juin 2010.');
        $this->assertSame(['BAPR'], array_column($passive, 'system'));
    }

    public function test_the_kvb_is_a_speed_control_and_never_a_block_system(): void
    {
        $facts = $this->extract('Le KVB est installé en 1989, puis le BAL en 1991.');

        $byRole = array_column($facts, 'role', 'system');
        $this->assertSame('speed_control', $byRole['KVB']);
        $this->assertSame('block', $byRole['BAL']);
        $this->assertSame('1989-01-01', array_values(array_filter($facts, fn ($f) => $f['system'] === 'KVB'))[0]['date']);
        $this->assertSame('1991-01-01', array_values(array_filter($facts, fn ($f) => $f['system'] === 'BAL'))[0]['date']);
    }

    public function test_cab_signalling_systems_carry_their_level(): void
    {
        $facts = $this->extract('La TVM 430 est mise en service le 10 juin 2007, et l\'ETCS niveau 2 le 1er juillet 2012.');

        $bySystem = array_column($facts, 'role', 'system');
        $this->assertSame(['TVM-430' => 'cab', 'ETCS_L2' => 'cab'], $bySystem);
    }

    public function test_a_date_that_belongs_to_another_action_is_not_given_to_the_system(): void
    {
        $this->assertSame([], $this->extract('La ligne est électrifiée depuis le 27 août 1950, équipée du block automatique lumineux (BAL).'));
        $this->assertSame([], $this->extract('Il s\'agit d\'une ligne à voie unique (depuis 1995), non électrifiée, équipée du block manuel.'));
        $this->assertSame([], $this->extract('La ligne est équipée du BAL et électrifiée en 1500 V en 1956.'));
    }

    public function test_each_system_of_a_list_gets_its_own_date(): void
    {
        $facts = $this->extract('Le block automatique lumineux (BAL) est mis en service en 1978, du BAPR depuis 1980.');

        $dates = array_column($facts, 'date', 'system');
        $this->assertSame(['BAL' => '1978-01-01', 'BAPR' => '1980-01-01'], $dates);
    }

    /** @return iterable<string, array{string}> */
    public static function nothingToGive(): iterable
    {
        yield 'futur' => ['Le BAPR sera mis en service en 2028 entre A et B.'];
        yield 'projet' => ['Un projet de mise en service de l\'ETCS en 2031 est étudié.'];
        yield 'devait' => ['La ligne devait être équipée de l\'ETCS niveau 1 en 2018.'];
        yield 'fermeture' => ['La ligne est fermée en 1950, le BAL est déposé.'];
        yield 'sans date' => ['La ligne est équipée du block automatique lumineux (BAL).'];
        yield 'sans verbe' => ['Le BAL de 1962 reste en place.'];
        yield 'ancien système' => ['En 1962, l\'ancien BAL est mis en service ailleurs.'];
        yield 'date dans le futur' => ['Le BAL est mis en service le 3 mars 2030.'];
        yield 'autre ligne' => ['Le 1er août 1987, le BAL est mis en service sur la [[Ligne de Paris-Lyon à Marseille-Saint-Charles|ligne de Paris à Marseille]].'];
        yield 'imparfait' => ['La ligne bénéficiait du BAPR en 1975.'];
        yield 'imparfait avec sigle' => ['La ligne bénéficiait du block automatique à permissivité restreinte (BAPR) en 1975.'];
        yield 'décennie' => ['Le BM-VU est installé dans les années 1980.'];
    }

    #[DataProvider('nothingToGive')]
    public function test_nothing_is_produced_when_the_fact_is_not_a_past_commissioning(string $sentence): void
    {
        $this->assertSame([], $this->extract($sentence));
    }

    public function test_uncertain_dates_are_kept_but_low(): void
    {
        $approximate = $this->extract('Vers 1937, le block Banjo est remplacé par du block automatique lumineux entre Bordeaux et Lamothe.');
        $period = $this->extract('Le block automatique lumineux est installé de 1976 à 1982 entre A et B.');
        $two = $this->extract('Le BAL est mis en service le 3 mars 1960 ou en 1961 entre A et B.');
        $exception = $this->extract('Hormis les tronçons de Lyon à Vénissieux déjà équipés, le BAL est mis en service en 1984.');
        $tooEarly = $this->extract('Le KVB est mis en service en 1962 sur la ligne.');

        foreach ([$approximate, $period, $two, $exception, $tooEarly] as $facts) {
            $this->assertNotEmpty($facts);
            $this->assertSame('low', $facts[0]['confidence']);
        }
    }

    public function test_the_same_year_and_this_occasion_refer_to_the_previous_date(): void
    {
        $sameYear = $this->extract('La ligne est mise sous tension le 22 juillet 1958. La même année, le BAL est installé de A à B.');
        $occasion = $this->extract('Elle est électrifiée le 11 décembre 2005. À cette occasion, le cantonnement téléphonique est remplacé par un BAPR.');

        $this->assertSame(['BAL', '1958-01-01', 'year', 'medium'], [$sameYear[0]['system'], $sameYear[0]['date'], $sameYear[0]['precision'], $sameYear[0]['confidence']]);
        $this->assertSame(['BAPR', '2005-12-11', 'day'], [$occasion[0]['system'], $occasion[0]['date'], $occasion[0]['precision']]);
    }

    public function test_the_date_of_a_parent_bullet_applies_to_its_children(): void
    {
        $facts = $this->extract("* 1969\n** mise en service du BAL de Lyon à Ambérieu\n** extension du BAPR");

        $this->assertSame(['BAL', 'BAPR'], array_column($facts, 'system'));
        $this->assertSame(['1969-01-01', '1969-01-01'], array_column($facts, 'date'));
    }

    public function test_sections_about_the_future_and_the_notes_are_skipped(): void
    {
        $text = "== Projets ==\nLe BAL est mis en service en 2020.\n== Notes ==\nLe BAL est mis en service en 2020.";

        $this->assertSame([], $this->extractor->extract($text, self::TITLE, new \DateTimeImmutable('2026-11-19')));
    }

    public function test_the_excerpt_is_short_and_free_of_long_dashes(): void
    {
        $facts = $this->extract('En 1962, le BAL est mis en service entre Paris – Gare de Lyon et Melun '.str_repeat('et des mots en plus ', 30).'—.');

        $this->assertLessThanOrEqual(200, mb_strlen($facts[0]['excerpt']));
        $this->assertStringNotContainsString('—', $facts[0]['excerpt']);
        $this->assertStringNotContainsString('–', $facts[0]['excerpt']);
    }

    public function test_one_fact_per_system_date_and_portion(): void
    {
        $facts = $this->extract('En 1962, le BAL est mis en service de A à B. En 1962, le BAL est mis en service de A à B.');

        $this->assertCount(1, $facts);
    }

    public function test_a_date_that_only_introduces_the_clause_dates_the_system(): void
    {
        $facts = $this->extract('* Le 13 septembre 1977, mise en service de l\'électrification et de la signalisation en block automatique lumineux (BAL).');

        $this->assertSame(['BAL', '1977-09-13', 'high'], [$facts[0]['system'], $facts[0]['date'], $facts[0]['confidence']]);
    }

    public function test_the_date_of_an_electrification_is_shared_by_a_coordinated_commissioning_of_the_block(): void
    {
        $facts = $this->extract('* 1er juin 1996 : mise en service de l\'électrification de la ligne en 25 kV et mise en service des signalisations de type block automatique lumineux (BAL).');

        $this->assertCount(1, $facts);
        $this->assertSame(['BAL', '1996-06-01', 'day'], [$facts[0]['system'], $facts[0]['date'], $facts[0]['precision']]);
    }

    public function test_an_electrification_date_is_not_given_to_a_block_installed_later(): void
    {
        $this->assertSame([], $this->extract('Elle est électrifiée depuis le 15 septembre 1966, équipée du block automatique lumineux (BAL).'));
        $this->assertSame([], $this->extract('* En 1995, mise en service de l\'électrification, installation du block manuel.'));
    }
}
