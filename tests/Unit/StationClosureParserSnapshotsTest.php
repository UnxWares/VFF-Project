<?php

namespace Tests\Unit;

use App\Services\StationClosureParser;
use App\Services\WikipediaLineParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * StationClosureParser : événements complets (type, date, précision, citation, origine, noms) lus dans des articles de
 * gare, des champs « fermeture » d'infobox et des articles de ligne. Textes accentués : les positions de caractères
 * (et non d'octets) doivent rester justes avant le verbe de fermeture.
 */
class StationClosureParserSnapshotsTest extends TestCase
{
    /** @return array<string, array{string, list<string>, list<array<string, mixed>>}> */
    public static function readings(): array
    {
        return [
            'article de gare S0' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare de Béziers\'\'\' est une gare.
La gare est fermée aux voyageurs en 1938, puis au fret en 1952.',
                1 => 'Gare de Béziers',
            ],  [
                0 => [
                    'type' => 'closure_passenger',
                    'date' => '1938-01-01',
                    'precision' => 'year',
                    'quote' => 'La gare est fermée aux voyageurs en 1938, puis au fret en 1952.',
                    'origin' => 'texte',
                ],
                1 => [
                    'type' => 'closure_freight',
                    'date' => '1952-01-01',
                    'precision' => 'year',
                    'quote' => 'La gare est fermée aux voyageurs en 1938, puis au fret en 1952.',
                    'origin' => 'texte',
                ],
            ]],
            'article de gare S1' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
Élue en 2001, la gare a été fermée le 3 mars 1972.',
                1 => 'Gare de Béziers',
            ],  [
                0 => [
                    'type' => 'closure',
                    'date' => '1972-03-03',
                    'precision' => 'day',
                    'quote' => 'Élue en 2001, la gare a été fermée le 3 mars 1972.',
                    'origin' => 'texte',
                ],
            ]],
            'article de gare S2' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
Été 2000 : la gare est fermée en 1960 (sans doute).',
                1 => 'Gare de Béziers',
            ],  [
                0 => [
                    'type' => 'closure',
                    'date' => '1960-01-01',
                    'precision' => 'year',
                    'quote' => 'Été 2000 : la gare est fermée en 1960 (sans doute). (date incertaine selon la source)',
                    'origin' => 'texte',
                ],
            ]],
            'article de gare S3' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
Élue ville en 1950, la gare est fermée vers 1960 à tout trafic.',
                1 => 'Gare de Béziers',
            ],  [
                0 => [
                    'type' => 'closure',
                    'date' => '1960-01-01',
                    'precision' => 'year',
                    'quote' => 'Élue ville en 1950, la gare est fermée vers 1960 à tout trafic. (date incertaine selon la source)',
                    'origin' => 'texte',
                ],
            ]],
            'article de gare S4' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
Elle est désaffectée depuis 1985.',
                1 => 'Gare de Béziers',
            ],  [
                0 => [
                    'type' => 'closure',
                    'date' => '1985-01-01',
                    'precision' => 'year',
                    'quote' => 'Elle est désaffectée depuis 1985.',
                    'origin' => 'texte',
                ],
            ]],
            'article de gare S5' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
La gare est supprimée en 1950.',
                1 => 'Gare de Béziers',
            ],  [
                0 => [
                    'type' => 'closure',
                    'date' => '1950-01-01',
                    'precision' => 'year',
                    'quote' => 'La gare est supprimée en 1950.',
                    'origin' => 'texte',
                ],
            ]],
            'article de gare S6' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
La gare de la ligne est supprimée en 1950.',
                1 => 'Gare de Béziers',
            ],  [
            ]],
            'article de gare S7' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
La gare est fermée au trafic au cours du XXe siècle (1946 pour les voyageurs, 1973 pour les marchandises).',
                1 => 'Gare de Béziers',
            ],  [
                0 => [
                    'type' => 'closure_passenger',
                    'date' => '1946-01-01',
                    'precision' => 'year',
                    'quote' => 'La gare est fermée au trafic au cours du XXe siècle (1946 pour les voyageurs, 1973 pour les marchandises).',
                    'origin' => 'texte',
                ],
                1 => [
                    'type' => 'closure_freight',
                    'date' => '1973-01-01',
                    'precision' => 'year',
                    'quote' => 'La gare est fermée au trafic au cours du XXe siècle (1946 pour les voyageurs, 1973 pour les marchandises).',
                    'origin' => 'texte',
                ],
            ]],
            'article de gare S8' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
En 2007, la gare est désaffectée du trafic marchandises.',
                1 => 'Gare de Béziers',
            ],  [
                0 => [
                    'type' => 'closure_freight',
                    'date' => '2007-01-01',
                    'precision' => 'year',
                    'quote' => 'En 2007, la gare est désaffectée du trafic marchandises.',
                    'origin' => 'texte',
                ],
            ]],
            'article de gare S9' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
La gare n\'est plus desservie depuis 1969.',
                1 => 'Gare de Béziers',
            ],  [
                0 => [
                    'type' => 'closure_passenger',
                    'date' => '1969-01-01',
                    'precision' => 'year',
                    'quote' => 'La gare n\'est plus desservie depuis 1969.',
                    'origin' => 'texte',
                ],
            ]],
            'article de gare S10' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
La ligne est fermée en 1938 ; elle est fermée en 1950.',
                1 => 'Gare de Béziers',
            ],  [
            ]],
            'article de gare S11' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
La ligne est fermée.
Elle est fermée en 1950.',
                1 => 'Gare de Béziers',
            ],  [
            ]],
            'article de gare S12' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
La gare est fermée dans la deuxième moitié du XXe siècle.',
                1 => 'Gare de Béziers',
            ],  [
                0 => [
                    'type' => 'closure',
                    'date' => '1950-01-01',
                    'precision' => 'after',
                    'quote' => 'La gare est fermée dans la deuxième moitié du XXe siècle.',
                    'origin' => 'texte',
                ],
            ]],
            'article de gare S13' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
La gare est fermée aux voyageurs le 6 mars 1972 mais la desserte reste.',
                1 => 'Gare de Béziers',
            ],  [
                0 => [
                    'type' => 'closure_passenger',
                    'date' => '1972-03-06',
                    'precision' => 'day',
                    'quote' => 'La gare est fermée aux voyageurs le 6 mars 1972 mais la desserte reste.',
                    'origin' => 'texte',
                ],
            ]],
            'article de gare S14' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
La gare, fermée aux marchandises en 1980, reste ouverte.',
                1 => 'Gare de Béziers',
            ],  [
                0 => [
                    'type' => 'closure_freight',
                    'date' => '1980-01-01',
                    'precision' => 'year',
                    'quote' => 'La gare, fermée aux marchandises en 1980, reste ouverte.',
                    'origin' => 'texte',
                ],
            ]],
            'article de gare S15' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
Été : la gare fermée en 1938 puis rouverte en 1945.',
                1 => 'Gare de Béziers',
            ],  [
                0 => [
                    'type' => 'closure',
                    'date' => '1938-01-01',
                    'precision' => 'year',
                    'quote' => 'Été : la gare fermée en 1938 puis rouverte en 1945.',
                    'origin' => 'texte',
                ],
            ]],
            'article de gare S16' => ['fromStationArticle',  [
                0 => '== Voir aussi ==
La gare est fermée en 1950.',
                1 => 'Gare de Béziers',
            ],  [
            ]],
            'article de gare S17' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
La gare fut supprimée définitivement en 1955.',
                1 => 'Gare de Béziers',
            ],  [
                0 => [
                    'type' => 'closure',
                    'date' => '1955-01-01',
                    'precision' => 'year',
                    'quote' => 'La gare fut supprimée définitivement en 1955.',
                    'origin' => 'texte',
                ],
            ]],
            'article de gare S18' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
La gare est fermée à la fin de l\'année 2011 pour tous trafics. La gare est fermée en 1900.',
                1 => 'Gare de Béziers',
            ],  [
                0 => [
                    'type' => 'closure',
                    'date' => '2011-01-01',
                    'precision' => 'year',
                    'quote' => 'La gare est fermée à la fin de l\'année 2011 pour tous trafics.',
                    'origin' => 'texte',
                ],
                1 => [
                    'type' => 'closure',
                    'date' => '1900-01-01',
                    'precision' => 'year',
                    'quote' => 'La gare est fermée en 1900.',
                    'origin' => 'texte',
                ],
            ]],
            'article de gare S19' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
La gare est fermée en 1938, puis aux voyageurs en 1950.',
                1 => 'Gare de Béziers',
            ],  [
                0 => [
                    'type' => 'closure',
                    'date' => '1938-01-01',
                    'precision' => 'year',
                    'quote' => 'La gare est fermée en 1938, puis aux voyageurs en 1950.',
                    'origin' => 'texte',
                ],
                1 => [
                    'type' => 'closure_passenger',
                    'date' => '1950-01-01',
                    'precision' => 'year',
                    'quote' => 'La gare est fermée en 1938, puis aux voyageurs en 1950.',
                    'origin' => 'texte',
                ],
            ]],
            'article de gare S20' => ['fromStationArticle',  [
                0 => 'La \'\'\'halte\'\'\' est une halte.
La halte est fermée aux voyageurs en 1938.',
                1 => 'Halte de Réalmont',
            ],  [
                0 => [
                    'type' => 'closure',
                    'date' => '1938-01-01',
                    'precision' => 'year',
                    'quote' => 'La halte est fermée aux voyageurs en 1938.',
                    'origin' => 'texte',
                ],
            ]],
            'article de gare S21' => ['fromStationArticle',  [
                0 => '{{Infobox Gare
 | nom = Été
 | fermeture = 1960
}}
La gare est fermée aux marchandises en 1975.',
                1 => 'Halte de Réalmont',
            ],  [
                0 => [
                    'type' => 'closure',
                    'date' => '1960-01-01',
                    'precision' => 'year',
                    'quote' => 'Fermeture : 1960',
                    'origin' => 'infobox',
                ],
                1 => [
                    'type' => 'closure_freight',
                    'date' => '1975-01-01',
                    'precision' => 'year',
                    'quote' => 'La gare est fermée aux marchandises en 1975.',
                    'origin' => 'texte',
                ],
            ]],
            'champ fermeture V0' => ['closureValue',  [
                0 => '27 septembre 1969 (voyageurs) 30 novembre 1990 (marchandises)',
                1 => 'T',
            ],  [
                0 => [
                    'type' => 'closure_passenger',
                    'date' => '1969-09-27',
                    'precision' => 'day',
                    'quote' => 'Fermeture : 27 septembre 1969 (voyageurs) 30 novembre 1990 (marchandises)',
                ],
                1 => [
                    'type' => 'closure_freight',
                    'date' => '1990-11-30',
                    'precision' => 'day',
                    'quote' => 'Fermeture : 27 septembre 1969 (voyageurs) 30 novembre 1990 (marchandises)',
                ],
            ]],
            'champ fermeture V1' => ['closureValue',  [
                0 => 'Voyageurs le 6 mars 1972 Marchandises le 1er janvier 1980',
                1 => 'T',
            ],  [
                0 => [
                    'type' => 'closure_passenger',
                    'date' => '1972-03-06',
                    'precision' => 'day',
                    'quote' => 'Fermeture : Voyageurs le 6 mars 1972 Marchandises le 1er janvier 1980',
                ],
                1 => [
                    'type' => 'closure_freight',
                    'date' => '1980-01-01',
                    'precision' => 'day',
                    'quote' => 'Fermeture : Voyageurs le 6 mars 1972 Marchandises le 1er janvier 1980',
                ],
            ]],
            'champ fermeture V2' => ['closureValue',  [
                0 => '1939 voyageurs 1952 marchandises',
                1 => 'T',
            ],  [
                0 => [
                    'type' => 'closure_passenger',
                    'date' => '1939-01-01',
                    'precision' => 'year',
                    'quote' => 'Fermeture : 1939 voyageurs 1952 marchandises',
                ],
                1 => [
                    'type' => 'closure_freight',
                    'date' => '1952-01-01',
                    'precision' => 'year',
                    'quote' => 'Fermeture : 1939 voyageurs 1952 marchandises',
                ],
            ]],
            'champ fermeture V3' => ['closureValue',  [
                0 => 'avant 1960',
                1 => 'T',
            ],  [
                0 => [
                    'type' => 'closure',
                    'date' => '1960-01-01',
                    'precision' => 'before',
                    'quote' => 'Fermeture : avant 1960',
                ],
            ]],
            'champ fermeture V4' => ['closureValue',  [
                0 => 'Avant 1960',
                1 => 'T',
            ],  [
                0 => [
                    'type' => 'closure',
                    'date' => '1960-01-01',
                    'precision' => 'before',
                    'quote' => 'Fermeture : Avant 1960',
                ],
            ]],
            'champ fermeture V5' => ['closureValue',  [
                0 => 'années 1980',
                1 => 'T',
            ],  [
                0 => [
                    'type' => 'closure',
                    'date' => '1980-01-01',
                    'precision' => 'decade',
                    'quote' => 'Fermeture : années 1980',
                ],
            ]],
            'champ fermeture V6' => ['closureValue',  [
                0 => 'deuxième moitié du XXe siècle',
                1 => 'T',
            ],  [
                0 => [
                    'type' => 'closure',
                    'date' => '1950-01-01',
                    'precision' => 'after',
                    'quote' => 'Fermeture : deuxième moitié du XXe siècle',
                ],
            ]],
            'champ fermeture V7' => ['closureValue',  [
                0 => '1947 (exploitation du CFEL) 1950',
                1 => 'T',
            ],  [
                0 => [
                    'type' => 'closure',
                    'date' => '1950-01-01',
                    'precision' => 'year',
                    'quote' => 'Fermeture : 1947 (exploitation du CFEL) 1950',
                ],
            ]],
            'champ fermeture V8' => ['closureValue',  [
                0 => '1947 (exploitation du CFEL)',
                1 => 'T',
            ],  [
                0 => [
                    'type' => 'closure',
                    'date' => '1947-01-01',
                    'precision' => 'year',
                    'quote' => 'Fermeture : 1947 (exploitation du CFEL)',
                ],
            ]],
            'champ fermeture V9' => ['closureValue',  [
                0 => 'vers 1960',
                1 => 'T',
            ],  [
                0 => [
                    'type' => 'closure',
                    'date' => '1960-01-01',
                    'precision' => 'year',
                    'quote' => 'Fermeture : vers 1960 (date incertaine selon la source)',
                ],
            ]],
            'champ fermeture V10' => ['closureValue',  [
                0 => '1960 ?',
                1 => 'T',
            ],  [
                0 => [
                    'type' => 'closure',
                    'date' => '1960-01-01',
                    'precision' => 'year',
                    'quote' => 'Fermeture : 1960 ? (date incertaine selon la source)',
                ],
            ]],
            'champ fermeture V11' => ['closureValue',  [
                0 => 'date inconnue 1960',
                1 => 'T',
            ],  [
                0 => [
                    'type' => 'closure',
                    'date' => '1960-01-01',
                    'precision' => 'year',
                    'quote' => 'Fermeture : date inconnue 1960 (date incertaine selon la source)',
                ],
            ]],
            'champ fermeture V12' => ['closureValue',  [
                0 => '1960 (voyageurs) 1991 (ligne)',
                1 => 'T',
            ],  [
                0 => [
                    'type' => 'closure_passenger',
                    'date' => '1960-01-01',
                    'precision' => 'year',
                    'quote' => 'Fermeture : 1960 (voyageurs) 1991 (ligne)',
                ],
                1 => [
                    'type' => 'closure',
                    'date' => '1991-01-01',
                    'precision' => 'year',
                    'quote' => 'Fermeture : 1960 (voyageurs) 1991 (ligne)',
                ],
            ]],
            'champ fermeture V13' => ['closureValue',  [
                0 => 'Été 1960',
                1 => 'T',
            ],  [
                0 => [
                    'type' => 'closure',
                    'date' => '1960-01-01',
                    'precision' => 'year',
                    'quote' => 'Fermeture : Été 1960',
                ],
            ]],
            'champ fermeture V14' => ['closureValue',  [
                0 => '1830',
                1 => 'T',
            ],  [
                0 => [
                    'type' => 'closure',
                    'date' => '1830-01-01',
                    'precision' => 'year',
                    'quote' => 'Fermeture : 1830',
                ],
            ]],
            'champ fermeture V15' => ['closureValue',  [
                0 => '1826',
                1 => 'T',
            ],  [
            ]],
            'champ fermeture V16' => ['closureValue',  [
                0 => '2 mai 1960 et 3 juin 1961',
                1 => 'T',
            ],  [
                0 => [
                    'type' => 'closure',
                    'date' => '1960-05-02',
                    'precision' => 'day',
                    'quote' => 'Fermeture : 2 mai 1960 et 3 juin 1961',
                ],
                1 => [
                    'type' => 'closure',
                    'date' => '1961-06-03',
                    'precision' => 'day',
                    'quote' => 'Fermeture : 2 mai 1960 et 3 juin 1961',
                ],
            ]],
            'champ fermeture V17' => ['closureValue',  [
                0 => 'fin du XXe siècle',
                1 => 'T',
            ],  [
                0 => [
                    'type' => 'closure',
                    'date' => '1970-01-01',
                    'precision' => 'after',
                    'quote' => 'Fermeture : fin du XXe siècle',
                ],
            ]],
            'champ fermeture V18' => ['closureValue',  [
                0 => '1950 pour les voyageurs',
                1 => 'T',
            ],  [
                0 => [
                    'type' => 'closure_passenger',
                    'date' => '1950-01-01',
                    'precision' => 'year',
                    'quote' => 'Fermeture : 1950 pour les voyageurs',
                ],
            ]],
            'champ fermeture V19' => ['closureValue',  [
                0 => '   ',
                1 => 'T',
            ],  [
            ]],
            'champ fermeture V20' => ['closureValue',  [
                0 => '',
                1 => 'T',
            ],  [
            ]],
            'champ fermeture V21' => ['closureValue',  [
                0 => '1er janvier 1960 fermeture tout trafic',
                1 => 'T',
            ],  [
                0 => [
                    'type' => 'closure',
                    'date' => '1960-01-01',
                    'precision' => 'day',
                    'quote' => 'Fermeture : 1er janvier 1960 fermeture tout trafic',
                ],
            ]],
            'champ fermeture V22' => ['closureValue',  [
                0 => '1960, 1970, 1980, 1990',
                1 => 'T',
            ],  [
                0 => [
                    'type' => 'closure',
                    'date' => '1960-01-01',
                    'precision' => 'year',
                    'quote' => 'Fermeture : 1960, 1970, 1980, 1990',
                ],
                1 => [
                    'type' => 'closure',
                    'date' => '1970-01-01',
                    'precision' => 'year',
                    'quote' => 'Fermeture : 1960, 1970, 1980, 1990',
                ],
                2 => [
                    'type' => 'closure',
                    'date' => '1980-01-01',
                    'precision' => 'year',
                    'quote' => 'Fermeture : 1960, 1970, 1980, 1990',
                ],
                3 => [
                    'type' => 'closure',
                    'date' => '1990-01-01',
                    'precision' => 'year',
                    'quote' => 'Fermeture : 1960, 1970, 1980, 1990',
                ],
            ]],
            'infobox IB' => ['fromStationInfobox',  [
                0 => '{{Infobox Gare
 | nom = Été
 | fermeture = 1938 (voyageurs)
}}
Texte fermée en 1960.',
                1 => 'Gare X',
            ],  [
                0 => [
                    'type' => 'closure_passenger',
                    'date' => '1938-01-01',
                    'precision' => 'year',
                    'quote' => 'Fermeture : 1938 (voyageurs)',
                    'origin' => 'infobox',
                ],
            ]],
            'infobox IB2' => ['fromStationInfobox',  [
                0 => '{{Infobox Commune | fermeture = 1938}}',
                1 => 'Gare X',
            ],  [
            ]],
            'infobox IB3' => ['fromStationInfobox',  [
                0 => '{{Infobox Station | fermeture2 = 1938 | fermeture3 = 1950 | date de fermeture = 1960 | fermeture = 1930}}',
                1 => 'Gare X',
            ],  [
                0 => [
                    'type' => 'closure',
                    'date' => '1930-01-01',
                    'precision' => 'year',
                    'quote' => 'Fermeture : 1930',
                    'origin' => 'infobox',
                ],
                1 => [
                    'type' => 'closure',
                    'date' => '1938-01-01',
                    'precision' => 'year',
                    'quote' => 'Fermeture : 1938',
                    'origin' => 'infobox',
                ],
                2 => [
                    'type' => 'closure',
                    'date' => '1950-01-01',
                    'precision' => 'year',
                    'quote' => 'Fermeture : 1950',
                    'origin' => 'infobox',
                ],
                3 => [
                    'type' => 'closure',
                    'date' => '1960-01-01',
                    'precision' => 'year',
                    'quote' => 'Fermeture : 1960',
                    'origin' => 'infobox',
                ],
            ]],
            'article de ligne L0' => ['fromLineArticle',  [
                0 => '== H ==
En 1960, les haltes de [[Tourgéville]] et de [[Gonneville-sur-Mer|Gonneville]] sont fermées<ref name="a"/>.',
                1 => 'Ligne de Test',
            ],  [
                0 => [
                    'names' => [
                        0 => 'Tourgéville',
                        1 => 'Gonneville',
                    ],
                    'type' => 'closure',
                    'date' => '1960-01-01',
                    'precision' => 'year',
                    'quote' => 'En 1960, les haltes de Tourgéville et de Gonneville sont fermées.',
                    'origin' => 'texte',
                ],
            ]],
            'article de ligne L1' => ['fromLineArticle',  [
                0 => '== H ==
Les gares de Caen et de Bayeux sont fermées aux marchandises en 1970.',
                1 => 'Ligne de Test',
            ],  [
                0 => [
                    'names' => [
                        0 => 'Caen',
                        1 => 'Bayeux',
                    ],
                    'type' => 'closure_freight',
                    'date' => '1970-01-01',
                    'precision' => 'year',
                    'quote' => 'Les gares de Caen et de Bayeux sont fermées aux marchandises en 1970.',
                    'origin' => 'texte',
                ],
            ]],
            'article de ligne L3' => ['fromLineArticle',  [
                0 => '== H ==
La gare de Bréauté est fermée aux voyageurs en 1938, la gare de Éu aussi.',
                1 => 'Ligne de Test',
            ],  [
                0 => [
                    'names' => [
                        0 => 'Bréauté',
                    ],
                    'type' => 'closure_passenger',
                    'date' => '1938-01-01',
                    'precision' => 'year',
                    'quote' => 'La gare de Bréauté est fermée aux voyageurs en 1938, la gare de Éu aussi.',
                    'origin' => 'texte',
                ],
            ]],
            'article de ligne L4' => ['fromLineArticle',  [
                0 => '== H ==
La halte de Bréauté est fermée aux voyageurs en 1938.',
                1 => 'Ligne de Test',
            ],  [
                0 => [
                    'names' => [
                        0 => 'Bréauté',
                    ],
                    'type' => 'closure',
                    'date' => '1938-01-01',
                    'precision' => 'year',
                    'quote' => 'La halte de Bréauté est fermée aux voyageurs en 1938.',
                    'origin' => 'texte',
                ],
            ]],
            'article de ligne L5' => ['fromLineArticle',  [
                0 => '== H ==
Les gares de Lisieux, de Orbec et de Vimoutiers restent ouvertes, fermée en 1960.',
                1 => 'Ligne de Test',
            ],  [
            ]],
            'article de ligne L6' => ['fromLineArticle',  [
                0 => '== H ==
La gare de Bréauté à Étretat est fermée en 1938.',
                1 => 'Ligne de Test',
            ],  [
            ]],
            'article de ligne L7' => ['fromLineArticle',  [
                0 => '== H ==
La section entre la gare de Bréauté et Étretat est fermée en 1938.',
                1 => 'Ligne de Test',
            ],  [
            ]],
            'article de ligne L8' => ['fromLineArticle',  [
                0 => '== H ==
* [[Gare de Léguevin|Léguevin]], ouverte en 1890, fermée aux voyageurs le 4 mai 1969
* [[Gare de Bréauté]] (Seine), fermée en 1938 et la ligne fermée en 1950
* [[Gare de Rouen]] et la ligne fermée en 1950.',
                1 => 'Ligne de Test',
            ],  [
                0 => [
                    'names' => [
                        0 => 'Léguevin',
                    ],
                    'type' => 'closure_passenger',
                    'date' => '1969-05-04',
                    'precision' => 'day',
                    'quote' => 'Léguevin , ouverte en 1890, fermée aux voyageurs le 4 mai 1969',
                    'origin' => 'liste',
                ],
                1 => [
                    'names' => [
                        0 => 'Bréauté',
                    ],
                    'type' => 'closure',
                    'date' => '1938-01-01',
                    'precision' => 'year',
                    'quote' => 'Bréauté (Seine), fermée en 1938 et la ligne fermée en 1950',
                    'origin' => 'liste',
                ],
            ]],
            'article de ligne L9' => ['fromLineArticle',  [
                0 => '== H ==
* [[Gare du Mans]] : fermée en 1960
* [[Gare des Aubrais|]] fermée au XXe siècle',
                1 => 'Ligne de Test',
            ],  [
                0 => [
                    'names' => [
                        0 => 'Le Mans',
                    ],
                    'type' => 'closure',
                    'date' => '1960-01-01',
                    'precision' => 'year',
                    'quote' => 'Le Mans : fermée en 1960',
                    'origin' => 'liste',
                ],
            ]],
            'article de ligne L10' => ['fromLineArticle',  [
                0 => '== G ==
{| class="wikitable"
! Gare !! Fermeture voyageurs !! Fermeture marchandises
|-
| [[Gare de Léguevin|Léguevin]] || 1969 || 1980
|-
| [[Gare de Rouen]] || ouverte || 
|-
| Orbec (halte) || (Fermée en 1938) || 
|}',
                1 => 'Ligne de Test',
            ],  [
                0 => [
                    'names' => [
                        0 => 'Léguevin',
                    ],
                    'quote' => 'Léguevin : fermeture voyageurs 1969',
                    'type' => 'closure_passenger',
                    'date' => '1969-01-01',
                    'precision' => 'year',
                    'origin' => 'tableau',
                ],
                1 => [
                    'names' => [
                        0 => 'Léguevin',
                    ],
                    'quote' => 'Léguevin : fermeture marchandises 1980',
                    'type' => 'closure_freight',
                    'date' => '1980-01-01',
                    'precision' => 'year',
                    'origin' => 'tableau',
                ],
                2 => [
                    'names' => [
                        0 => 'Orbec',
                    ],
                    'quote' => 'Orbec : fermeture voyageurs (Fermée en 1938)',
                    'type' => 'closure_passenger',
                    'date' => '1938-01-01',
                    'precision' => 'year',
                    'origin' => 'tableau',
                ],
            ]],
            'article de ligne L11' => ['fromLineArticle',  [
                0 => '== G ==
{| class="wikitable"
! Gare !! État
|-
| style="color:red" | [[Gare de Léguevin|Léguevin]] || Fermée en 1969
|-
| [[Gare de Lyon]] || 1960, 1970, 1980, 1990
|}',
                1 => 'Ligne de Test',
            ],  [
                0 => [
                    'names' => [
                        0 => 'Léguevin',
                    ],
                    'quote' => 'Léguevin : Fermée en 1969',
                    'type' => 'closure',
                    'date' => '1969-01-01',
                    'precision' => 'year',
                    'origin' => 'tableau',
                ],
            ]],
            'article de ligne L12' => ['fromLineArticle',  [
                0 => '<!-- fermée en 1950 -->Les gares de Caen et de Bayeux sont fermées en 1950<ref>fermée</ref>.',
                1 => 'Ligne de Test',
            ],  [
                0 => [
                    'names' => [
                        0 => 'Caen',
                        1 => 'Bayeux',
                    ],
                    'type' => 'closure',
                    'date' => '1950-01-01',
                    'precision' => 'year',
                    'quote' => 'Les gares de Caen et de Bayeux sont fermées en 1950.',
                    'origin' => 'texte',
                ],
            ]],
        ];
    }

    /**
     * @param  list<string>  $args
     * @param  list<array<string, mixed>>  $expected
     */
    #[DataProvider('readings')]
    public function test_the_events_read_are_complete(string $method, array $args, array $expected): void
    {
        $parser = new StationClosureParser(new WikipediaLineParser);

        $this->assertSame($expected, $parser->$method(...$args));
    }

    public function test_a_nominal_closure_does_not_put_the_date_in_the_station_name(): void
    {
        $parser = new StationClosureParser(new WikipediaLineParser);

        $events = $parser->fromLineArticle("== H ==\nLa fermeture de la gare de Bréauté en 1938 est actée.", 'Ligne de Test');

        $this->assertCount(1, $events);
        $this->assertSame(['Bréauté'], $events[0]['names']);
        $this->assertSame('1938-01-01', $events[0]['date']);
    }
}
