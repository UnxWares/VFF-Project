<?php

namespace Tests\Unit;

use App\Services\WikipediaLineParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Lecture des gabarits de date et des énumérations d'étapes de WikipediaLineParser (commits d08f3318, cda02020 et b7e9ef70),
 * jusque dans la citation et la portion de chaque événement : cibles des tests de mutation. Les noms de gares portent des
 * accents pour que les décalages en octets et en caractères ne se confondent pas.
 */
class WikipediaLineParserMutationTest extends TestCase
{
    private const TITLE = 'Ligne de Saint-Julien (Troyes) à Gray';

    private function template(string $inner): string
    {
        $method = new \ReflectionMethod(WikipediaLineParser::class, 'renderTemplate');

        return $method->invoke(new WikipediaLineParser, $inner);
    }

    /** @return array<string, array{string, string}> */
    public static function templates(): array
    {
        return [
            'date barre oblique' => ['Date-|20/7/1862||', '20 juillet 1862'],
            'date avec jour, mois, année et suite ignorée' => ['date|1er|juin|1980|dans les chemins de fer', '1er juin 1980'],
            'date par numéro d argument' => ['Date|3=2014', '2014'],
            'date sans jour' => ['date||mai|2006', 'mai 2006'],
            'date année mois jour' => ['Date|1909 07 24', '24 juillet 1909'],
            'date jour mois numérique' => ['Date|28|08|2022', '28 août 2022'],
            'date avec zéro' => ['Date|01/12/2023', '01 décembre 2023'],
            'date mois numérique année' => ['Date||11|2024', 'novembre 2024'],
            'mois et année en un argument' => ['date|mai 2006', 'mai 2006'],
            'année mois' => ['Date|1914 05', 'mai 1914'],
            'année tiret mois' => ['Date|2018-09', 'septembre 2018'],
            'année tiret mois tiret jour' => ['Date|1934-09-21', '21 septembre 1934'],
            'saison écrite telle quelle' => ['Date|automne 2017', 'automne 2017'],
            'début de mois' => ['Date|début mars 1950', 'début mars 1950'],
            'trimestre' => ['Date|1er trimestre 2014', '1er trimestre 2014'],
            'reste après l année' => ['Date|1864 dans les chemins de fer', '1864 dans les chemins de fer'],
            'le devant la date' => ['date|le 3 mars 1900', '3 mars 1900'],
            'dernier jour du mois' => ['date|31|12|1999', '31 décembre 1999'],
            'jour impossible' => ['date|32|12|1999', '32 12 1999'],
            'jour nul' => ['date|0|12|1999', '0 12 1999'],
            'mois impossible' => ['date|5|13|1999', '5 13 1999'],
            'mois nul' => ['date|5|0|1999', '5 0 1999'],
            'année sur deux chiffres' => ['date|5|mars|99', '5 mars 99'],
            'chiffres seuls' => ['date|1|2|3', '1 2 3'],
            'année seule' => ['Date|2014', '2014'],
            'mois en lettres' => ['date|15|août|1900', '15 août 1900'],
            'majuscule du mois' => ['date|15|Août|1900', '15 août 1900'],
            'quatre arguments utiles et suite' => ['date|1|2|2003|x|y', '1 février 2003'],
            'arguments numérotés' => ['Date|1=5|2=mars|3=1900', '5 mars 1900'],
            'argument nommé ignoré' => ['Date|a=1|5|mars|1900', '5 mars 1900'],
            'jour inconnu' => ['Date|1900-12-xx', 'décembre 1900'],
            'arguments vides' => ['date||||2012', '2012'],
            'mois tiret année' => ['date|mai-2006', 'mai 2006'],
            'jour et mois' => ['date|1er|mai', '1er mai'],
            'mois numérique et année' => ['date|12|1999', 'décembre 1999'],
            'année et mois numérique' => ['date|1999|12', 'décembre 1999'],
            'année et mois en lettres' => ['date|1999|mai', 'mai 1999'],
            'année puis mois en un argument' => ['date|1990 mars', 'mars 1990'],
            'deux dates' => ['date|décembre 1999 - janvier 2000', 'décembre 1999 janvier 2000'],
            'date avec plus' => ['date-|1er|janvier|2000', '1er janvier 2000'],
            'cinquième argument' => ['date|5|mars|1900|7', '5 mars 1900'],
            'argument numéroté après les autres' => ['date|5|mars|1900|3=2014', '5 mars 2014'],
            'argument nommé collé à un chiffre' => ['date|5|mars|1900|b2=x', '5 mars 1900'],
            'arguments numérotés dans le désordre' => ['date|3=1900|2=mars|1=5', '5 mars 1900'],
            'espace insécable dans une saison' => ["date|automne\u{A0}2017", "automne\u{A0}2017"],
            'saison avec tiret final' => ['date|été 2017 -', 'été 2017'],
            'reste après l année au troisième argument' => ['date|1er|juin|1980 dans les chemins de fer|2005', '1er juin 1980'],
            'mois en majuscules accentuées' => ['date|15|FÉVRIER|1900', '15 février 1900'],
            'mot qui finit par un chiffre' => ['date|a12|mars|1900', ''],
            'mot qui commence par un chiffre' => ['date|12a|mars|1900', ''],
            'le en argument seul' => ['date|le|3|mars|1900', ''],
            'le au milieu' => ['date|5 le mars 1900', '5 le mars 1900'],
            'mois numérique un' => ['date|1|2000', 'janvier 2000'],
            'jour un' => ['date|1|mars|1900', '1 mars 1900'],
            'deux nombres dont le premier n est pas un mois' => ['date|13|2006', '13 2006'],
            'année puis nombre qui n est pas un mois' => ['date|2006|13', '2006 13'],
            'texte avant la date' => ['date|x|5|mars|1900', ''],
            'date républicaine' => ['Date républicaine|3|mars|1800', '3 mars 1800'],
            'date de sport' => ['date sport|12|mai|2001', '12 mai 2001'],
            'premier avec arguments' => ['1er|juin 1969', '1er juin 1969'],
            'premier, mois et année' => ['1er|mai|1900', '1er mai 1900'],
            'premier trimestre' => ['1er|trimestre', '1er trimestre'],
            'premier sans argument' => ['1er|', '1er'],
            'premier, arguments vides' => ['1er||', '1er'],
            'premier, lien avec barre' => ['1er|juin [[1969 dans les chemins de fer|1969]]', '1er juin [[1969 dans les chemins de fer|1969]]'],
            'suffixe er' => ['er', 'er'],
            'suffixe e' => ['e', 'e'],
            'suffixe es' => ['es', 'es'],
            'suffixe ex' => ['ex', 'ex'],
            'nombre et unité' => ['nombre|12|km|x', '12 km'],
            'unité' => ['unité|5|km|x', '5 km'],
            'nb' => ['nb|3|ans', '3'],
            'm' => ['m|ici', 'ici'],
            'abréviation' => ['abr|SNCF|Société', 'SNCF'],
            'langue avec texte' => ['lang|en|Hello', 'Hello'],
            'langue sans texte' => ['langue|fr', 'fr'],
            'lien' => ['lien|a|b', 'a'],
            'lien avec texte nommé' => ['lien|texte=T|a', 'T'],
            'lien avec fr nommé' => ['lien|fr=F|a', 'F'],
            'numéro' => ['numéro|3', 'n° 3'],
            'n°' => ['n°|4', 'n° 4'],
            'nos' => ['nos|5', 'n° 5'],
            'numéro sans argument' => ['no', 'n° '],
            'siècle court' => ['s|XIX', 'XIXe siècle'],
            'siècle' => ['siècle|XX', 'XXe siècle'],
            'siècle sans argument' => ['s', 'e siècle'],
            'heure' => ['heure|14|30', '14 h 30'],
            'heure sans minute' => ['heure|5', '5 h '],
            'saint avec point' => ['St.', 'St'],
            'sainte' => ['Ste', 'Ste'],
            'saint minuscule' => ['st', 'St'],
            'sainte avec point' => ['ste.', 'Ste'],
            'lien insécable' => ['lnobr|LGV 1', '[[LGV 1]]'],
            'lien insécable avec texte' => ['lien insécable|A|B', '[[A|B]]'],
            'lien insécable vide' => ['lnobr|', ''],
            'lien insécable sans texte' => ['lnobr|A|', '[[A]]'],
            'format de nombre' => ['formatnum:12 345', '12 345'],
            'minuscules' => ['lc:ABC', 'ABC'],
            'fonction inconnue' => ['xyz:1', ''],
            'gabarit inconnu' => ['inconnu|x', ''],
        ];
    }

    #[DataProvider('templates')]
    public function test_templates_are_rendered_as_text_the_date_reader_understands(string $inner, string $expected): void
    {
        $this->assertSame($expected, $this->template($inner));
    }

    /**
     * Événements du texte, sous la forme « type date départ>arrivée | citation » (« (infobox) » pour ceux de l'infobox).
     *
     * @return list<string>
     */
    private function events(string $text, string $infobox = '', string $title = self::TITLE): array
    {
        $wikitext = ($infobox !== '' ? "{{Infobox Ligne ferroviaire\n{$infobox}\n}}\n" : '')."== Histoire ==\n{$text}\n";

        return array_map(
            fn (array $e): string => "{$e['type']} {$e['date']} {$e['section_from']}>{$e['section_to']}".($e['origin'] === 'infobox' ? ' (infobox)' : ' | '.$e['source_quote']),
            (new WikipediaLineParser)->parseChronology($wikitext, $title),
        );
    }

    /** @return array<string, array{string, list<string>}> */
    public static function sentences(): array
    {
        return [
            'quatre étapes, la dernière en tête de phrase' => [
                'La ligne est mise en service en plusieurs étapes : Troyes - Bar-sur-Seine le 20 juillet 1862, Bar-sur-Seine - Châtillon-sur-Seine le 19 octobre 1868, Châtillon-sur-Seine - Is-sur-Tille le 9 décembre 1882 et enfin la section située entre Is-sur-Tille et Gray est mise en service le 27 octobre 1888.',
                [
                    'opening 1862-07-20 Troyes>Bar-sur-Seine | Troyes - Bar-sur-Seine le 20 juillet 1862',
                    'opening 1868-10-19 Bar-sur-Seine>Châtillon-sur-Seine | Bar-sur-Seine - Châtillon-sur-Seine le 19 octobre 1868',
                    'opening 1882-12-09 Châtillon-sur-Seine>Is-sur-Tille | Châtillon-sur-Seine - Is-sur-Tille le 9 décembre 1882',
                    'opening 1888-10-27 Is-sur-Tille>Gray | la section située entre Is-sur-Tille et Gray est mise en service le 27 octobre 1888.',
                ],
            ],
            'étapes séparées par des points-virgules' => [
                'La ligne est mise en service par étapes : Troyes - Bar-sur-Seine le 20 juillet 1862 ; Bar-sur-Seine - Châtillon-sur-Seine le 19 octobre 1868 ; Châtillon-sur-Seine - Is-sur-Tille en 1882.',
                [
                    'opening 1862-07-20 Troyes>Bar-sur-Seine | Troyes - Bar-sur-Seine le 20 juillet 1862',
                    'opening 1868-10-19 Bar-sur-Seine>Châtillon-sur-Seine | Bar-sur-Seine - Châtillon-sur-Seine le 19 octobre 1868',
                    'opening 1882-01-01 Châtillon-sur-Seine>Is-sur-Tille | Châtillon-sur-Seine - Is-sur-Tille en 1882.',
                ],
            ],
            'étapes avec « entre » et une parenthèse après la date' => [
                'La ligne est mise en service entre Troyes et Bar-sur-Seine le 20 juillet 1862, entre Bar-sur-Seine et Châtillon-sur-Seine le 19 octobre 1868 et entre Châtillon-sur-Seine et Is-sur-Tille en 1882.',
                [
                    'opening 1862-07-20 Troyes>Bar-sur-Seine | La ligne est mise en service entre Troyes et Bar-sur-Seine le 20 juillet 1862',
                    'opening 1868-10-19 Bar-sur-Seine>Châtillon-sur-Seine | entre Bar-sur-Seine et Châtillon-sur-Seine le 19 octobre 1868',
                    'opening 1882-01-01 Châtillon-sur-Seine>Is-sur-Tille | entre Châtillon-sur-Seine et Is-sur-Tille en 1882.',
                ],
            ],
            'date puis portion avec « le second »' => [
                'La ligne est mise en service le 20 juillet 1862 de Troyes à Bar-sur-Seine, le second le 19 octobre 1868 de Bar-sur-Seine à Châtillon-sur-Seine, puis le 9 décembre 1882 de Châtillon-sur-Seine à Is-sur-Tille.',
                [
                    'opening 1862-07-20 Troyes>Bar-sur-Seine | La ligne est mise en service le 20 juillet 1862 de Troyes à Bar-sur-Seine',
                    'opening 1868-10-19 Bar-sur-Seine>Châtillon-sur-Seine | le second le 19 octobre 1868 de Bar-sur-Seine à Châtillon-sur-Seine',
                    'opening 1882-12-09 Châtillon-sur-Seine>Is-sur-Tille | le 9 décembre 1882 de Châtillon-sur-Seine à Is-sur-Tille.',
                ],
            ],
            'date puis portion avec « à partir du » et « dès le »' => [
                'La ligne est mise en service à partir du 20 juillet 1862 de Troyes à Bar-sur-Seine, dès le 19 octobre 1868 de Bar-sur-Seine à Châtillon-sur-Seine.',
                [
                    'opening 1862-07-20 Troyes>Bar-sur-Seine | La ligne est mise en service à partir du 20 juillet 1862 de Troyes à Bar-sur-Seine',
                    'opening 1868-10-19 Bar-sur-Seine>Châtillon-sur-Seine | dès le 19 octobre 1868 de Bar-sur-Seine à Châtillon-sur-Seine.',
                ],
            ],
            'date puis portion avec un jour à zéro et « la seconde »' => [
                'La ligne est mise en service le 20 juillet 1862 de Troyes à Bar-sur-Seine, la seconde le 05 octobre 1868 de Bar-sur-Seine à Châtillon-sur-Seine.',
                [
                    'opening 1862-07-20 Troyes>Bar-sur-Seine | La ligne est mise en service le 20 juillet 1862 de Troyes à Bar-sur-Seine',
                    'opening 1868-10-05 Bar-sur-Seine>Châtillon-sur-Seine | la seconde le 05 octobre 1868 de Bar-sur-Seine à Châtillon-sur-Seine.',
                ],
            ],
            'une date sans portion garde la proposition entière' => [
                'La ligne est mise en service le 20 juillet 1862 de Troyes à Bar-sur-Seine, le 19 octobre 1868 et de Bar-sur-Seine à Châtillon-sur-Seine.',
                ['opening 1862-07-20 Troyes>Bar-sur-Seine | La ligne est mise en service le 20 juillet 1862 de Troyes à Bar-sur-Seine, le 19 octobre 1868'],
            ],
            'une portion et deux dates' => [
                'Le tronçon Troyes - Bar-sur-Seine est ouvert le 20 juillet 1862 et la ligne ferme en 1950.',
                [
                    'opening 1862-07-20 Troyes>Bar-sur-Seine | Le tronçon Troyes - Bar-sur-Seine est ouvert le 20 juillet 1862',
                    'closure 1950-01-01 > | Le tronçon Troyes - Bar-sur-Seine est ouvert le 20 juillet 1862 et la ligne ferme en 1950.',
                ],
            ],
            'portion entre parenthèses après la deuxième date' => [
                'La ligne est mise en service le 20 juillet 1862 entre Troyes et Bar-sur-Seine et le 19 octobre 1868 entre Bar-sur-Seine et Châtillon-sur-Seine (ouverture de la section Is-sur-Tille - Gray).',
                [
                    'opening 1862-07-20 Troyes>Bar-sur-Seine | La ligne est mise en service le 20 juillet 1862 entre Troyes et Bar-sur-Seine',
                    'opening 1868-10-19 Bar-sur-Seine>Châtillon-sur-Seine | le 19 octobre 1868 entre Bar-sur-Seine et Châtillon-sur-Seine (ouverture de la section Is-sur-Tille - Gray).',
                ],
            ],
            'un autre verbe n est pas une étape' => [
                'La section Troyes - Bar-sur-Seine ayant été fermée en 1990, seule la portion Bar-sur-Seine - Châtillon-sur-Seine est exploitée en 2005.',
                ['closure 1990-01-01 Troyes>Bar-sur-Seine | La section Troyes - Bar-sur-Seine ayant été fermée en 1990, seule la portion Bar-sur-Seine - Châtillon-sur-Seine est exploitée en 2005.'],
            ],
            'fermetures voyageurs en années' => [
                'La ligne est fermée au service des voyageurs en 1950 entre Troyes et Bar-sur-Seine et en 1960 entre Bar-sur-Seine et Châtillon-sur-Seine.',
                [
                    'closure_passenger 1950-01-01 Troyes>Bar-sur-Seine | La ligne est fermée au service des voyageurs en 1950 entre Troyes et Bar-sur-Seine',
                    'closure_passenger 1960-01-01 Bar-sur-Seine>Châtillon-sur-Seine | en 1960 entre Bar-sur-Seine et Châtillon-sur-Seine.',
                ],
            ],
            'date commune de deux portions « entre »' => [
                'Le trafic marchandises cesse entre Villars et Poinson et entre Mirebeau-sur-Bèze et Champagne-sur-Vingeanne le 30 mai 1975.',
                [
                    'closure_freight 1975-05-30 Villars>Poinson | Le trafic marchandises cesse entre Villars et Poinson',
                    'closure_freight 1975-05-30 Mirebeau-sur-Bèze>Champagne-sur-Vingeanne | entre Mirebeau-sur-Bèze et Champagne-sur-Vingeanne le 30 mai 1975.',
                ],
            ],
            'date commune avec une portion qui est toute la ligne' => [
                'Le trafic marchandises cesse entre Troyes et Gray et entre Mirebeau et Champagne le 30 mai 1975.',
                [
                    'closure_freight 1975-05-30 Troyes>Gray | Le trafic marchandises cesse entre Troyes et Gray',
                    'closure_freight 1975-05-30 Mirebeau>Champagne | entre Mirebeau et Champagne le 30 mai 1975.',
                ],
            ],
            'date commune : exemple entre parenthèses non coupé' => [
                'Le trafic marchandises cesse (notamment entre Villars et Poinson et entre Mirebeau et Champagne) le 30 mai 1975.',
                ['closure_freight 1975-05-30 Villars>Poinson | Le trafic marchandises cesse (notamment entre Villars et Poinson et entre Mirebeau et Champagne) le 30 mai 1975.'],
            ],
            'date commune : une portion d une autre ligne n est pas coupée' => [
                'Le trafic marchandises cesse entre Villars et Poinson et entre la ligne de Gray et Bar le 30 mai 1975.',
                ['closure_freight 1975-05-30 Villars>Poinson | Le trafic marchandises cesse entre Villars et Poinson et entre la ligne de Gray et Bar le 30 mai 1975.'],
            ],
            'date commune suivie d une parenthèse' => [
                'Le trafic voyageurs cesse entre Villars et Poinson et entre Mirebeau et Champagne le 30 mai 1975 (et 1976).',
                ['closure_passenger 1975-05-30 Mirebeau>Champagne | entre Mirebeau et Champagne le 30 mai 1975 (et 1976).'],
            ],
            'respectivement : deux portions, deux dates' => [
                'Les sections de Châtillon à Pleure et de Pleure à Bletterans sont fermées respectivement le 1 mars 1969 et le 2 juin 1971.',
                [
                    'closure 1969-03-01 Châtillon>Pleure | Les sections de Châtillon à Pleure et de Pleure à Bletterans sont fermées respectivement le 1 mars 1969 et le 2 juin 1971.',
                    'closure 1971-06-02 Pleure>Bletterans | Les sections de Châtillon à Pleure et de Pleure à Bletterans sont fermées respectivement le 1 mars 1969 et le 2 juin 1971.',
                ],
            ],
            'respectivement : séparateur « , puis »' => [
                'Les sections de Châtillon à Pleure et de Pleure à Bletterans sont fermées respectivement le 1 mars 1969, puis le 2 juin 1971.',
                [
                    'closure 1969-03-01 Châtillon>Pleure | Les sections de Châtillon à Pleure et de Pleure à Bletterans sont fermées respectivement le 1 mars 1969, puis le 2 juin 1971.',
                    'closure 1971-06-02 Pleure>Bletterans | Les sections de Châtillon à Pleure et de Pleure à Bletterans sont fermées respectivement le 1 mars 1969, puis le 2 juin 1971.',
                ],
            ],
            'respectivement : trois portions' => [
                'Les sections de Châtillon à Pleure, de Pleure à Bletterans et de Bletterans à Gray sont fermées respectivement en 1969, 1971 et le 3 mai 1980.',
                [
                    'closure 1969-01-01 Châtillon>Pleure | Les sections de Châtillon à Pleure, de Pleure à Bletterans et de Bletterans à Gray sont fermées respectivement en 1969, 1971 et le 3 mai 1980.',
                    'closure 1971-01-01 Pleure>Bletterans | Les sections de Châtillon à Pleure, de Pleure à Bletterans et de Bletterans à Gray sont fermées respectivement en 1969, 1971 et le 3 mai 1980.',
                    'closure 1980-05-03 Bletterans>Gray | Les sections de Châtillon à Pleure, de Pleure à Bletterans et de Bletterans à Gray sont fermées respectivement en 1969, 1971 et le 3 mai 1980.',
                ],
            ],
            'respectivement : une seule portion et une seule date' => [
                'Les sections de Châtillon à Pleure sont fermées respectivement le 1 mars 1969.',
                ['closure 1969-03-01 Châtillon>Pleure | Les sections de Châtillon à Pleure sont fermées respectivement le 1 mars 1969.'],
            ],
            'respectivement : décennies refusées' => [
                'Les sections de Châtillon à Pleure et de Pleure à Bletterans sont fermées respectivement dans les années 1960 et 1970.',
                [],
            ],
            'respectivement : électrification en mois' => [
                'Les sections de Châtillon à Pleure et de Pleure à Bletterans sont électrifiées respectivement en mars 1969 et juin 1971.',
                [
                    'electrification 1969-03-01 Châtillon>Pleure | Les sections de Châtillon à Pleure et de Pleure à Bletterans sont électrifiées respectivement en mars 1969 et juin 1971.',
                    'electrification 1971-06-01 Pleure>Bletterans | Les sections de Châtillon à Pleure et de Pleure à Bletterans sont électrifiées respectivement en mars 1969 et juin 1971.',
                ],
            ],
            'respectivement : portion de trop après les dates' => [
                'Les sections de Châtillon à Pleure et de Pleure à Bletterans sont fermées respectivement le 1 mars 1969 et le 2 juin 1971 entre Gray et Bar.',
                [
                    'closure 1969-03-01 Pleure>Bletterans | de Pleure à Bletterans sont fermées respectivement le 1 mars 1969',
                    'closure 1971-06-02 Gray>Bar | Les sections de Châtillon à Pleure et de Pleure à Bletterans sont fermées respectivement le 1 mars 1969 et le 2 juin 1971 entre Gray et Bar.',
                ],
            ],
            'respectivement : mois accentués' => [
                'Les sections de Châtillon à Pleure et de Pleure à Bletterans sont fermées respectivement le 1 décembre 1969 et le 2 février 1971.',
                [
                    'closure 1969-12-01 Châtillon>Pleure | Les sections de Châtillon à Pleure et de Pleure à Bletterans sont fermées respectivement le 1 décembre 1969 et le 2 février 1971.',
                    'closure 1971-02-02 Pleure>Bletterans | Les sections de Châtillon à Pleure et de Pleure à Bletterans sont fermées respectivement le 1 décembre 1969 et le 2 février 1971.',
                ],
            ],
            'respectivement : incise accentuée entre les portions' => [
                'Les sections de Pleure à Bletterans, créées par décret, et de Bletterans à Gray sont fermées respectivement le 1 mars 1969 et le 2 juin 1971.',
                [
                    'closure 1969-03-01 Pleure>Bletterans | Les sections de Pleure à Bletterans, créées par décret, et de Bletterans à Gray sont fermées respectivement le 1 mars 1969 et le 2 juin 1971.',
                    'closure 1971-06-02 Bletterans>Gray | Les sections de Pleure à Bletterans, créées par décret, et de Bletterans à Gray sont fermées respectivement le 1 mars 1969 et le 2 juin 1971.',
                ],
            ],
            'respectivement : fin de phrase après les dates' => [
                'Les sections de Châtillon à Pleure et de Pleure à Bletterans sont fermées respectivement le 1 mars 1969 et le 2 juin 1971, soit 2 sections.',
                [
                    'closure 1969-03-01 Châtillon>Pleure | Les sections de Châtillon à Pleure et de Pleure à Bletterans sont fermées respectivement le 1 mars 1969 et le 2 juin 1971, soit 2 sections.',
                    'closure 1971-06-02 Pleure>Bletterans | Les sections de Châtillon à Pleure et de Pleure à Bletterans sont fermées respectivement le 1 mars 1969 et le 2 juin 1971, soit 2 sections.',
                ],
            ],
            'respectivement : une autre ligne citée après les dates' => [
                'Les sections de Châtillon à Pleure et de Pleure à Bletterans sont fermées respectivement le 1 mars 1969 et le 2 juin 1971 sur la ligne de Paris à Lyon.',
                [
                    'closure 1969-03-01 Châtillon>Pleure | Les sections de Châtillon à Pleure et de Pleure à Bletterans sont fermées respectivement le 1 mars 1969 et le 2 juin 1971 sur la ligne de Paris à Lyon.',
                    'closure 1971-06-02 Pleure>Bletterans | Les sections de Châtillon à Pleure et de Pleure à Bletterans sont fermées respectivement le 1 mars 1969 et le 2 juin 1971 sur la ligne de Paris à Lyon.',
                ],
            ],
            'respectivement : sans verbe de fermeture' => [
                'Les sections de Châtillon à Pleure et de Pleure à Bletterans sont, respectivement le 1 mars 1969 et le 2 juin 1971.',
                [],
            ],
            'mois accentués, portion après la date' => [
                'La ligne est fermée en février 1969 entre Châtillon et Pleure et en août 1971 entre Pleure et Bletterans.',
                [
                    'closure 1969-02-01 Châtillon>Pleure | La ligne est fermée en février 1969 entre Châtillon et Pleure',
                    'closure 1971-08-01 Pleure>Bletterans | en août 1971 entre Pleure et Bletterans.',
                ],
            ],
            'mises en service accentuées, portion avant la date' => [
                'La ligne est mise en service entre Châtillon et Pleure en 1969, puis entre Pleure et Bletterans en 1971.',
                [
                    'opening 1969-01-01 Châtillon>Pleure | La ligne est mise en service entre Châtillon et Pleure en 1969',
                    'opening 1971-01-01 Pleure>Bletterans | La ligne est mise en service entre Châtillon et Pleure en 1969, puis entre Pleure et Bletterans en 1971.',
                ],
            ],
            'date commune : parenthèse après la deuxième portion' => [
                'Le trafic marchandises cesse entre Châtillon et Pleure et entre Pleure et Bletterans (hors gare) le 30 mai 1975.',
                [
                    'closure_freight 1975-05-30 Châtillon>Pleure | Le trafic marchandises cesse entre Châtillon et Pleure',
                    'closure_freight 1975-05-30 Pleure>Bletterans | entre Pleure et Bletterans (hors gare) le 30 mai 1975.',
                ],
            ],
            'date commune : parenthèse après la première portion' => [
                'Le trafic marchandises cesse entre Châtillon et Pleure (notamment) et entre Pleure et Bletterans le 30 mai 1975.',
                [
                    'closure_freight 1975-05-30 Châtillon>Pleure | Le trafic marchandises cesse entre Châtillon et Pleure (notamment)',
                    'closure_freight 1975-05-30 Pleure>Bletterans | entre Pleure et Bletterans le 30 mai 1975.',
                ],
            ],
            'étapes avec parenthèses accentuées après les dates' => [
                'La ligne est mise en service par étapes : Troyes - Bar-sur-Seine le 20 juillet 1862 (à voie étroite), Bar-sur-Seine - Châtillon-sur-Seine le 19 octobre 1868 (à écartement normal), puis Châtillon-sur-Seine - Is-sur-Tille le 9 décembre 1882.',
                [
                    'opening 1862-07-20 Troyes>Bar-sur-Seine | Troyes - Bar-sur-Seine le 20 juillet 1862 (à voie étroite)',
                    'opening 1868-10-19 Bar-sur-Seine>Châtillon-sur-Seine | Bar-sur-Seine - Châtillon-sur-Seine le 19 octobre 1868 (à écartement normal)',
                    'opening 1882-12-09 Châtillon-sur-Seine>Is-sur-Tille | Châtillon-sur-Seine - Is-sur-Tille le 9 décembre 1882.',
                ],
            ],
            'date puis portion avec noms accentués' => [
                'La ligne est mise en service le 20 juillet 1862 de Troyes à Châtillon-sur-Seine, le 19 octobre 1868 de Châtillon-sur-Seine à Is-sur-Tille.',
                [
                    'opening 1862-07-20 Troyes>Châtillon-sur-Seine | La ligne est mise en service le 20 juillet 1862 de Troyes à Châtillon-sur-Seine',
                    'opening 1868-10-19 Châtillon-sur-Seine>Is-sur-Tille | le 19 octobre 1868 de Châtillon-sur-Seine à Is-sur-Tille.',
                ],
            ],
            'date puis portion avec « puis » et « et enfin »' => [
                'La ligne est mise en service le 20 juillet 1862 de Troyes à Châtillon-sur-Seine, puis le 19 octobre 1868 de Châtillon-sur-Seine à Is-sur-Tille et enfin le 9 décembre 1882 d\'Is-sur-Tille à Gray.',
                [
                    'opening 1862-07-20 Troyes>Châtillon-sur-Seine | La ligne est mise en service le 20 juillet 1862 de Troyes à Châtillon-sur-Seine',
                    'opening 1868-10-19 Châtillon-sur-Seine>Is-sur-Tille | le 19 octobre 1868 de Châtillon-sur-Seine à Is-sur-Tille',
                    'opening 1882-12-09 Is-sur-Tille>Gray | le 9 décembre 1882 d\'Is-sur-Tille à Gray.',
                ],
            ],
            'date puis portion séparées par un point-virgule' => [
                'La ligne est mise en service le 20 juillet 1862 de Troyes à Châtillon-sur-Seine ; le 19 octobre 1868 de Châtillon-sur-Seine à Is-sur-Tille.',
                [
                    'opening 1862-07-20 Troyes>Châtillon-sur-Seine | La ligne est mise en service le 20 juillet 1862 de Troyes à Châtillon-sur-Seine',
                    'opening 1868-10-19 Châtillon-sur-Seine>Is-sur-Tille | le 19 octobre 1868 de Châtillon-sur-Seine à Is-sur-Tille.',
                ],
            ],
            'portion « jusqu\'à » en tête puis « entre »' => [
                'La ligne est mise en service jusqu\'à Châtillon-sur-Seine le 20 juillet 1862, puis entre Châtillon-sur-Seine et Is-sur-Tille le 19 octobre 1868.',
                [
                    'opening 1862-07-20 >Châtillon-sur-Seine | La ligne est mise en service jusqu\'à Châtillon-sur-Seine le 20 juillet 1862',
                    'opening 1868-10-19 Châtillon-sur-Seine>Is-sur-Tille | entre Châtillon-sur-Seine et Is-sur-Tille le 19 octobre 1868.',
                ],
            ],
            'portion « jusqu\'à » après une portion « entre »' => [
                'La ligne est mise en service entre Troyes et Châtillon-sur-Seine le 20 juillet 1862, jusqu\'à Is-sur-Tille le 19 octobre 1868.',
                [
                    'opening 1862-07-20 Troyes>Châtillon-sur-Seine | La ligne est mise en service entre Troyes et Châtillon-sur-Seine le 20 juillet 1862, jusqu\'à Is-sur-Tille le 19 octobre 1868.',
                ],
            ],
            'deux « jusqu\'à » après leur date' => [
                'La ligne est mise en service le 20 juillet 1862 jusqu\'à Châtillon-sur-Seine, le 19 octobre 1868 jusqu\'à Is-sur-Tille.',
                [
                    'opening 1862-07-20 >Châtillon-sur-Seine | La ligne est mise en service le 20 juillet 1862 jusqu\'à Châtillon-sur-Seine, le 19 octobre 1868 jusqu\'à Is-sur-Tille.',
                ],
            ],
            'deux verbes pour deux tronçons' => [
                'Le tronçon entre Troyes et Bar-sur-Seine est fermé le 20 juillet 1962 et celui entre Bar-sur-Seine et Châtillon-sur-Seine est ouvert le 19 octobre 1868.',
                [
                    'opening 1868-10-19 Bar-sur-Seine>Châtillon-sur-Seine | celui entre Bar-sur-Seine et Châtillon-sur-Seine est ouvert le 19 octobre 1868.',
                    'closure 1962-07-20 Troyes>Bar-sur-Seine | Le tronçon entre Troyes et Bar-sur-Seine est fermé le 20 juillet 1962',
                ],
            ],
            'ouvre puis ferme au présent' => [
                'La ligne ouvre le 4 septembre 1882 entre Achères et Versailles ; elle ferme le 5 mars 1983.',
                [
                    'opening 1882-09-04 Achères>Versailles | La ligne ouvre le 4 septembre 1882 entre Achères et Versailles',
                    'closure 1983-03-05 Achères>Versailles | La ligne ouvre le 4 septembre 1882 entre Achères et Versailles ; elle ferme le 5 mars 1983.',
                ],
            ],
            'verbe au présent dans une parenthèse' => [
                'La ligne ferme au trafic voyageurs le 5 mars 1983 entre Troyes et Gray (ferme aussi le 6 avril 1990).',
                [
                    'closure_passenger 1983-03-05 Troyes>Gray | La ligne ferme au trafic voyageurs le 5 mars 1983 entre Troyes et Gray (ferme aussi le 6 avril 1990).',
                ],
            ],
            'verbe au présent avec date complète' => [
                'La ligne ferme au service des voyageurs en 1950.',
                ['closure_passenger 1950-01-01 > | La ligne ferme au service des voyageurs en 1950.'],
            ],
            'verbe au pluriel' => [
                'La ligne ferment le 2 mars 1969.',
                ['closure 1969-03-02 > | La ligne ferment le 2 mars 1969.'],
            ],
            'ouvrent aux marchandises' => [
                'La ligne ouvrent aux marchandises le 2 mars 1969.',
                ['opening_freight 1969-03-02 > | La ligne ouvrent aux marchandises le 2 mars 1969.'],
            ],
            'ouvre à partir du' => [
                'La ligne ouvre à partir du 3 mars 1869.',
                ['opening 1869-03-03 > | La ligne ouvre à partir du 3 mars 1869.'],
            ],
            'ouvre sans date' => ['La ligne ouvre.', []],
            'plusieurs phrases au présent' => [
                'La ligne ouvre en 1877 de Troyes à Gray. Elle ferme au trafic des marchandises le 3 mars 1980. La ligne ferme au service des voyageurs en 1950.',
                [
                    'opening 1877-01-01 Troyes>Gray | La ligne ouvre en 1877 de Troyes à Gray.',
                    'closure_passenger 1950-01-01 > | La ligne ferme au service des voyageurs en 1950.',
                    'closure_freight 1980-03-03 Troyes>Gray | Elle ferme au trafic des marchandises le 3 mars 1980.',
                ],
            ],
            'ligne liée citée au présent' => [
                'La [[Ligne de Paris à Lyon|ligne de Paris]] ferme en 1950 entre Achères et Versailles.',
                [],
            ],
            'verbe nié' => ['Elle n\'ouvre pas le 4 septembre 1882. Elle ne ferme pas le 5 mars 1983.', []],
            'passé puis présent' => [
                'La ligne fut ouverte le 4 septembre 1882 et ferme au trafic le 5 mars 1983 puis continue pour les marchandises jusqu\'en 1990.',
                ['opening 1882-09-04 > | La ligne fut ouverte le 4 septembre 1882 et ferme au trafic le 5 mars 1983'],
            ],
        ];
    }

    /** @param  list<string>  $expected */
    #[DataProvider('sentences')]
    public function test_stage_enumerations_keep_portion_date_and_quote(string $text, array $expected): void
    {
        $this->assertSame($expected, $this->events($text));
    }

    public function test_a_date_in_the_lead_in_refuses_the_respective_pairing(): void
    {
        $events = $this->events('En 1950, les sections de Châtillon à Pleure et de Pleure à Bletterans sont fermées respectivement le 1 mars 1969 et le 2 juin 1971.');

        $this->assertNotContains('closure 1969-03-01 Châtillon>Pleure | Les sections de Châtillon à Pleure et de Pleure à Bletterans sont fermées respectivement le 1 mars 1969 et le 2 juin 1971.', $events);
        $this->assertCount(1, $events);
        $this->assertStringStartsWith('closure 1969-03-01 Pleure>Bletterans', $events[0]);
    }

    public function test_only_the_first_clause_of_a_respective_reading_hides_the_infobox(): void
    {
        $events = $this->events('Les sections de Châtillon à Pleure et de Pleure à Bletterans sont fermées respectivement le 1 mars 1969 et le 2 juin 1971.', "| fermeture = 1969\n| fermeture2 = 1980");

        $this->assertSame(2, count($events));
        $this->assertSame([], array_filter($events, fn (string $e): bool => str_contains($e, '(infobox)')));
    }

    public function test_a_present_tense_reading_keeps_the_other_infobox_dates_and_a_past_one_hides_them(): void
    {
        $infobox = "| mise en service = 1869\n| mise en service2 = 1890";

        $this->assertSame([
            'opening 1869-03-03 > | La ligne ouvre le 3 mars 1869.',
            'opening 1890-01-01 > (infobox)',
        ], $this->events('La ligne ouvre le 3 mars 1869.', $infobox));
        $this->assertSame(['opening 1869-03-03 > | La ligne fut ouverte le 3 mars 1869.'], $this->events('La ligne fut ouverte le 3 mars 1869.', $infobox));
        $this->assertSame([
            'closure 1969-01-01 > (infobox)',
            'closure_passenger 1969-03-02 > | La ligne ferme au service voyageurs le 2 mars 1969.',
            'closure 1980-01-01 > (infobox)',
        ], $this->events('La ligne ferme au service voyageurs le 2 mars 1969.', "| fermeture = 1969\n| fermeture2 = 1980"));
    }

    public function test_the_internal_marker_never_reaches_the_events(): void
    {
        $wikitext = "{{Infobox Ligne ferroviaire\n| mise en service = 1869\n| mise en service2 = 1890\n}}\n== Histoire ==\nLa ligne ouvre le 3 mars 1869.\n";

        foreach ((new WikipediaLineParser)->parseChronology($wikitext, self::TITLE) as $e) {
            $this->assertArrayNotHasKey("\0added", $e);
            $this->assertCount(14, $e);
        }
    }
}
