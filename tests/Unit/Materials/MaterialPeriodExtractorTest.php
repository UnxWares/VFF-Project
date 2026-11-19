<?php

namespace Tests\Unit\Materials;

use App\Services\Materials\MaterialPeriodCollector;
use App\Services\Materials\MaterialPeriodExtractor;
use App\Services\Materials\WikipediaMaterialSections;
use App\Services\WikipediaLineParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Périodes de circulation lues dans les phrases des articles (cas réels résumés) : dates et
 * précision, début, fin, remplacements, fiabilité, et tout ce qui ne doit rien produire.
 */
class MaterialPeriodExtractorTest extends TestCase
{
    /** Séries citées dans les fixtures : titre d'article → identifiant. */
    private const SERIES = [
        'X 2400' => 1, 'X 72500' => 2, 'B 82500' => 3, 'Z 20500' => 4, 'Z 50000' => 5, 'Z 5300' => 6, 'Z 5600' => 7,
        'X 3800' => 8, 'BB 7200' => 9, 'Renault ABJ' => 10, 'X 5500' => 11, 'Élément à turbine à gaz' => 12, 'X 2000' => 13,
        'BB 16000' => 14, 'BB 26000' => 15,
    ];

    /** Lignes citées : titre → identifiant (la ligne de l'article est la 100). */
    private const LINES = ['Ligne de Paris-Austerlitz à Bordeaux-Saint-Jean' => 101, 'Ligne de Paris-Montparnasse à Brest' => 102];

    /**
     * Faits d'un texte d'article de ligne, simplifiés en « série => [début, précision, fin, précision, fiabilité] ».
     *
     * @return array<int, list<array{0: ?string, 1: ?string, 2: ?string, 3: ?string, 4: string}>>
     */
    private function lineFacts(string $wikitext): array
    {
        $facts = (new MaterialPeriodExtractor(new WikipediaLineParser))->extract(
            $wikitext,
            fn (string $target): array => isset(self::SERIES[$target]) ? [self::SERIES[$target]] : [],
            fn (string $target): bool => isset(self::LINES[$target]),
            MaterialPeriodExtractor::MODE_LINE_ARTICLE,
            new \DateTimeImmutable('2026-11-19'),
        );

        return $this->simplify($facts);
    }

    /** @return array<int, list<array{0: ?string, 1: ?string, 2: ?string, 3: ?string, 4: string}>> */
    private function materialFacts(string $wikitext): array
    {
        $facts = (new MaterialPeriodExtractor(new WikipediaLineParser))->extract(
            $wikitext,
            fn (string $target): array => isset(self::LINES[$target]) ? [self::LINES[$target]] : [],
            fn (string $target): bool => isset(self::SERIES[$target]),
            MaterialPeriodExtractor::MODE_MATERIAL_ARTICLE,
            new \DateTimeImmutable('2026-11-19'),
        );

        return $this->simplify($facts);
    }

    /**
     * @param  list<array<string, mixed>>  $facts
     * @return array<int, list<array{0: ?string, 1: ?string, 2: ?string, 3: ?string, 4: string}>>
     */
    private function simplify(array $facts): array
    {
        $out = [];
        foreach ($facts as $f) {
            $this->assertLessThanOrEqual(200, mb_strlen($f['excerpt']));
            $this->assertStringNotContainsString('—', $f['excerpt']);
            $out[$f['id']][] = [$f['from']['date'] ?? null, $f['from']['precision'] ?? null, $f['to']['date'] ?? null, $f['to']['precision'] ?? null, $f['confidence']];
        }
        ksort($out);

        return $out;
    }

    public function test_year_range_is_a_medium_period(): void
    {
        $this->assertSame(
            [1 => [['1971-01-01', 'year', '1989-01-01', 'year', 'medium']]],
            $this->lineFacts('* Autorails [[X 2400]] du dépôt de Vénissieux (de 1971 à 1989).'),
        );
    }

    public function test_numeric_day_dates_are_read(): void
    {
        $this->assertSame(
            [12 => [['1975-09-28', 'day', '1986-01-01', 'year', 'medium']]],
            $this->lineFacts('* Turbotrains [[Élément à turbine à gaz|ETG]] du dépôt de Vénissieux (du 28/09/1975 à 1986).'),
        );
    }

    public function test_start_with_day_precision_is_high(): void
    {
        $this->assertSame(
            [3 => [['2009-09-06', 'day', null, null, 'high']]],
            $this->lineFacts('* [[B 82500]] du dépôt de Lyon-Vaise (depuis le {{date|6 septembre 2009}}, sur Genève - Grenoble).'),
        );
    }

    public function test_end_with_month_precision_is_high(): void
    {
        $this->assertSame(
            [6 => [[null, null, '2011-12-01', 'month', 'high']]],
            $this->lineFacts('Jusqu\'en décembre 2011, des navettes d\'automotrices ([[Z 5300]]) circulaient entre Orléans et Les Aubrais.'),
        );
    }

    public function test_passive_replacement_ends_the_replaced_series_and_starts_the_other(): void
    {
        $this->assertSame(
            [
                10 => [['1973-01-01', 'year', null, null, 'medium']],
                11 => [[null, null, '1973-01-01', 'year', 'medium']],
            ],
            $this->lineFacts('Les [[X 5500]] furent remplacés en 1973 par les autorails [[Renault ABJ|ABJ4]] du dépôt de Bordeaux.'),
        );
    }

    public function test_active_replacement_with_a_start_marker(): void
    {
        $this->assertSame(
            [
                2 => [[null, null, '2009-06-01', 'month', 'high']],
                3 => [['2009-06-01', 'month', null, null, 'high']],
            ],
            $this->lineFacts('En juin 2009, mise en service des [[B 82500]] en remplacement des [[X 72500]] sur la ligne.'),
        );
    }

    public function test_series_followed_by_a_leaving_verb_ends_there(): void
    {
        $this->assertSame(
            [
                6 => [[null, null, '2003-01-01', 'year', 'medium']],
                7 => [['2003-01-01', 'year', null, null, 'medium']],
            ],
            $this->lineFacts('Depuis 2003, les [[Z 5300]] disparaissent définitivement de la ligne et seules des [[Z 5600]] y circulent.'),
        );
    }

    public function test_date_only_dates_the_series_of_its_own_part_of_the_sentence(): void
    {
        $this->assertSame(
            [5 => [['2013-10-07', 'day', null, null, 'high']]],
            $this->lineFacts('Elle est parcourue par des rames {{nobr|[[Z 20500]]}} et, depuis le {{date|7 octobre 2013}}, avec des automotrices [[Z 50000]].'),
        );
    }

    public function test_verb_far_from_the_series_dates_nothing(): void
    {
        $this->assertSame([], $this->lineFacts(
            'Le trafic subsiste entre Paris et Tours, avec la mise en service d\'« Aqualys » le 28 novembre 1999, train Grandes Lignes avec voitures Corail tractées par des [[BB 7200]].',
        ));
    }

    /** @return array<string, array{0: string}> */
    public static function nothingToRead(): array
    {
        return [
            'date seule sans verbe (présence, pas un début)' => ['En 2019, le parcours est assuré par des rames [[Z 50000]].'],
            'futur' => ['Les [[B 82500]] devraient arriver en 2027 sur la ligne.'],
            'commande' => ['Le 14 juin 2019, la région a commandé des [[Z 50000]] destinées à la ligne.'],
            'circulation exceptionnelle' => ['Des [[X 2400]] venues en renfort pour les Jeux olympiques en février 1968.'],
            'nombre du nom de la série' => ['Les [[X 2000|X 2000]] arrivent sur la ligne.'],
            'date à venir' => ['Les [[Z 50000]] circulent sur la ligne à partir de 2030.'],
            'possibilité étudiée' => ['Dès octobre 1933, la compagnie considère la possibilité d\'exploiter la ligne avec des [[X 2400]].'],
            'début après la fin' => ['Des [[X 2400]] (de 1990 à 1980).'],
        ];
    }

    #[DataProvider('nothingToRead')]
    public function test_sentences_that_date_nothing(string $wikitext): void
    {
        $this->assertSame([], $this->lineFacts($wikitext));
    }

    /** @return array<string, array{0: string}> */
    public static function lowConfidence(): array
    {
        return [
            'décennie' => ['À partir des années 1970, les [[X 2400]] font leur apparition.'],
            'date approximative' => ['Vers 1937, les [[X 2400]] arrivent sur la ligne.'],
            'environ après la date' => ['Depuis octobre 2013 environ : des [[X 2400]] sur la ligne.'],
            'date d\'une autre action' => ['Malgré la mise sous tension de la ligne de 1954 à 1957, le trafic local reste assuré par des [[X 3800]].'],
            'autre ligne citée' => ['Depuis 2010, les [[X 2400]] relient la ligne à la [[Ligne de Paris-Montparnasse à Brest|ligne de Brest]].'],
            'fréquentation' => ['La fréquentation a augmenté de 89 % entre 2002 et 2008 avec l\'arrivée des [[X 2400]].'],
        ];
    }

    #[DataProvider('lowConfidence')]
    public function test_doubtful_facts_are_kept_low(string $wikitext): void
    {
        $facts = $this->lineFacts($wikitext);
        $this->assertNotSame([], $facts);
        foreach ($facts as $byId) {
            foreach ($byId as $fact) {
                $this->assertSame('low', $fact[4]);
            }
        }
    }

    public function test_material_article_dates_the_cited_line(): void
    {
        $this->assertSame(
            [101 => [['1983-01-01', 'year', null, null, 'medium']]],
            $this->materialFacts('Elles ont circulé à partir de 1983 sur [[Ligne de Paris-Austerlitz à Bordeaux-Saint-Jean|Paris-Bordeaux]].'),
        );
        $this->assertSame(
            [102 => [[null, null, '1990-01-01', 'year', 'medium']]],
            $this->materialFacts('Sur la [[Ligne de Paris-Montparnasse à Brest|ligne de Brest]], elles sont remplacées par des automotrices en 1990.'),
        );
    }

    public function test_material_article_with_another_series_is_low(): void
    {
        $facts = $this->materialFacts('Depuis 1995, elles roulent sur [[Ligne de Paris-Montparnasse à Brest|Paris-Brest]] avec les [[BB 26000]].');
        $this->assertSame('low', $facts[102][0][4]);
    }

    public function test_linked_sentences_keep_link_positions_in_plain_text(): void
    {
        $sentences = WikipediaMaterialSections::linkedSentences("Des rames {{nobr|[[Z 20500]]}} et des [[Z 50000|Franciliens]].\n| [[X 2400]] || 1960\n[[Fichier:X.jpg|vignette|Un [[X 2400]]]]");
        $this->assertCount(1, $sentences);
        $text = $sentences[0]['text'];
        $this->assertSame('Des rames Z 20500 et des Franciliens.', $text);
        $this->assertSame([['target' => 'Z 20500', 'pos' => 10, 'length' => 7, 'compared' => false], ['target' => 'Z 50000', 'pos' => 25, 'length' => 11, 'compared' => false]], $sentences[0]['links']);
        $this->assertSame('Franciliens', mb_substr($text, 25, 11));
    }

    public function test_a_series_cited_as_a_comparison_gets_no_period(): void
    {
        // Phrase du Blanc-Argent, datée pour que seule la comparaison puisse l'écarter.
        $this->assertSame(
            [11 => [['2002-01-01', 'year', null, null, 'medium']]],
            $this->lineFacts('Depuis 2002, les [[X 5500]] offrent un confort similaire aux autorails [[X 2000]].'),
            'le X 2000 n\'est qu\'un terme de comparaison',
        );
        $this->assertSame([], $this->lineFacts('Ce nouveau matériel offre depuis 2002 un confort similaire aux autorails [[X 2000]], avec notamment la climatisation.'));
    }

    public function test_wikidata_precision_gives_the_reliability(): void
    {
        $this->assertSame(
            ['from' => ['date' => '1963-05-30', 'precision' => 'day'], 'to' => ['date' => '2000-01-01', 'precision' => 'year'], 'confidence' => 'medium'],
            MaterialPeriodCollector::wikidataFact(['from' => '1963-05-30', 'from_precision' => 11, 'to' => '2000-06-24', 'to_precision' => 9]),
        );
        $this->assertSame('high', MaterialPeriodCollector::wikidataFact(['from' => '2012-09-01', 'from_precision' => 10, 'to' => null, 'to_precision' => null])['confidence'] ?? null);
        $this->assertSame('low', MaterialPeriodCollector::wikidataFact(['from' => '1900-01-01', 'from_precision' => 8, 'to' => null, 'to_precision' => null])['confidence'] ?? null);
        $this->assertNull(MaterialPeriodCollector::wikidataFact(['from' => '2000-01-01', 'from_precision' => 9, 'to' => '1990-01-01', 'to_precision' => 9]));
        $this->assertNull(MaterialPeriodCollector::wikidataFact(['from' => null, 'from_precision' => null, 'to' => null, 'to_precision' => null]));
    }

    public function test_section_heading_names_the_portion(): void
    {
        $this->assertSame(['Saint-Just-en-Chaussée', 'Cambrai'], MaterialPeriodCollector::sectionOf('Matériel roulant ayant circulé sur la ligne > Section de Saint-Just-en-Chaussée à Cambrai'));
        $this->assertSame(['Gretz', 'Tournan'], MaterialPeriodCollector::sectionOf('Tronçon entre Gretz et Tournan'));
        $this->assertSame([null, null], MaterialPeriodCollector::sectionOf('Matériel roulant > Autorails'));
    }
}
