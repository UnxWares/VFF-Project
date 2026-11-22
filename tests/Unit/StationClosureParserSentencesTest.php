<?php

namespace Tests\Unit;

use App\Services\StationClosureParser;
use App\Services\WikipediaLineParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * StationClosureParser : bornes des phrases (écart entre le sujet, le verbe et la date, réserve de la source, siècle,
 * date en tête, portée du trafic), puces, tableaux et listes de gares, avec des textes accentués.
 */
class StationClosureParserSentencesTest extends TestCase
{
    /** @return array<string, array{string, list<string>, list<array<string, mixed>>}> */
    public static function readings(): array
    {
        return [
            'article de gare : année après la décennie' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
La gare est fermée à la fin de l\'année 1985.',
                1 => 'Gare de Test',
            ],  [
                0 => [
                    'type' => 'closure',
                    'date' => '1985-01-01',
                    'precision' => 'year',
                    'quote' => 'La gare est fermée à la fin de l\'année 1985.',
                    'origin' => 'texte',
                ],
            ]],
            'article de gare : date en tête' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
En 1960, la gare est fermée.',
                1 => 'Gare de Test',
            ],  [
                0 => [
                    'type' => 'closure',
                    'date' => '1960-01-01',
                    'precision' => 'year',
                    'quote' => 'En 1960, la gare est fermée.',
                    'origin' => 'texte',
                ],
            ]],
            'article de gare : jour en tête' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
Le 6 mars 1972, la gare ferme.',
                1 => 'Gare de Test',
            ],  [
                0 => [
                    'type' => 'closure',
                    'date' => '1972-03-06',
                    'precision' => 'day',
                    'quote' => 'Le 6 mars 1972, la gare ferme.',
                    'origin' => 'texte',
                ],
            ]],
            'article de gare : deux dates avant' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
En 1950, puis en 1960, la gare est fermée.',
                1 => 'Gare de Test',
            ],  [
            ]],
            'article de gare : date en tête et deux-points' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
En 1960 : la gare est fermée.',
                1 => 'Gare de Test',
            ],  [
            ]],
            'article de gare : puce avec le' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
* Le 6 mars 1972 : la gare est fermée.',
                1 => 'Gare de Test',
            ],  [
                0 => [
                    'type' => 'closure',
                    'date' => '1972-03-06',
                    'precision' => 'day',
                    'quote' => '* Le 6 mars 1972 : la gare est fermée.',
                    'origin' => 'texte',
                ],
            ]],
            'article de gare : puce sans deux-points' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
* 6 mars 1972 la gare est fermée.',
                1 => 'Gare de Test',
            ],  [
                0 => [
                    'type' => 'closure',
                    'date' => '1972-03-06',
                    'precision' => 'day',
                    'quote' => '* 6 mars 1972 la gare est fermée.',
                    'origin' => 'texte',
                ],
            ]],
            'article de gare : date avant un autre mot' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
Depuis 1960, la gare est fermée.',
                1 => 'Gare de Test',
            ],  [
                0 => [
                    'type' => 'closure',
                    'date' => '1960-01-01',
                    'precision' => 'year',
                    'quote' => 'Depuis 1960, la gare est fermée.',
                    'origin' => 'texte',
                ],
            ]],
            'article de gare : date après un autre mot' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
Elle a connu en 1960 une affluence record, la gare est fermée.',
                1 => 'Gare de Test',
            ],  [
            ]],
            'article de gare : écart de 30' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
La gare est fermée éééééééééééééééééééééééééééééé 1960.',
                1 => 'Gare de Test',
            ],  [
                0 => [
                    'type' => 'closure',
                    'date' => '1960-01-01',
                    'precision' => 'year',
                    'quote' => 'La gare est fermée éééééééééééééééééééééééééééééé 1960.',
                    'origin' => 'texte',
                ],
            ]],
            'article de gare : écart de 60' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
La gare est fermée éééééééééééééééééééééééééééééééééééééééééééééééééééééééééééé 1960.',
                1 => 'Gare de Test',
            ],  [
                0 => [
                    'type' => 'closure',
                    'date' => '1960-01-01',
                    'precision' => 'year',
                    'quote' => 'La gare est fermée éééééééééééééééééééééééééééééééééééééééééééééééééééééééééééé 1960.',
                    'origin' => 'texte',
                ],
            ]],
            'article de gare : écart de 64' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
La gare est fermée éééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééé 1960.',
                1 => 'Gare de Test',
            ],  [
                0 => [
                    'type' => 'closure',
                    'date' => '1960-01-01',
                    'precision' => 'year',
                    'quote' => 'La gare est fermée éééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééé 1960.',
                    'origin' => 'texte',
                ],
            ]],
            'article de gare : écart de 65' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
La gare est fermée ééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééé 1960.',
                1 => 'Gare de Test',
            ],  [
                0 => [
                    'type' => 'closure',
                    'date' => '1960-01-01',
                    'precision' => 'year',
                    'quote' => 'La gare est fermée ééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééé 1960.',
                    'origin' => 'texte',
                ],
            ]],
            'article de gare : écart de 66' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
La gare est fermée éééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééé 1960.',
                1 => 'Gare de Test',
            ],  [
                0 => [
                    'type' => 'closure',
                    'date' => '1960-01-01',
                    'precision' => 'year',
                    'quote' => 'La gare est fermée éééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééé 1960.',
                    'origin' => 'texte',
                ],
            ]],
            'article de gare : écart de 70' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
La gare est fermée éééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééé 1960.',
                1 => 'Gare de Test',
            ],  [
            ]],
            'article de gare : écart de 71' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
La gare est fermée ééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééé 1960.',
                1 => 'Gare de Test',
            ],  [
            ]],
            'article de gare : écart de 80' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
La gare est fermée éééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééé 1960.',
                1 => 'Gare de Test',
            ],  [
            ]],
            'article de gare : réserve à 20' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
Probablementéééééééé la gare est fermée en 1960.',
                1 => 'Gare de Test',
            ],  [
                0 => [
                    'type' => 'closure',
                    'date' => '1960-01-01',
                    'precision' => 'year',
                    'quote' => 'Probablementéééééééé la gare est fermée en 1960. (date incertaine selon la source)',
                    'origin' => 'texte',
                ],
            ]],
            'article de gare : réserve à 38' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
Probablementéééééééééééééééééééééééééé la gare est fermée en 1960.',
                1 => 'Gare de Test',
            ],  [
                0 => [
                    'type' => 'closure',
                    'date' => '1960-01-01',
                    'precision' => 'year',
                    'quote' => 'Probablementéééééééééééééééééééééééééé la gare est fermée en 1960.',
                    'origin' => 'texte',
                ],
            ]],
            'article de gare : réserve à 39' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
Probablementééééééééééééééééééééééééééé la gare est fermée en 1960.',
                1 => 'Gare de Test',
            ],  [
                0 => [
                    'type' => 'closure',
                    'date' => '1960-01-01',
                    'precision' => 'year',
                    'quote' => 'Probablementééééééééééééééééééééééééééé la gare est fermée en 1960.',
                    'origin' => 'texte',
                ],
            ]],
            'article de gare : réserve à 40' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
Probablementéééééééééééééééééééééééééééé la gare est fermée en 1960.',
                1 => 'Gare de Test',
            ],  [
                0 => [
                    'type' => 'closure',
                    'date' => '1960-01-01',
                    'precision' => 'year',
                    'quote' => 'Probablementéééééééééééééééééééééééééééé la gare est fermée en 1960.',
                    'origin' => 'texte',
                ],
            ]],
            'article de gare : réserve à 41' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
Probablementééééééééééééééééééééééééééééé la gare est fermée en 1960.',
                1 => 'Gare de Test',
            ],  [
                0 => [
                    'type' => 'closure',
                    'date' => '1960-01-01',
                    'precision' => 'year',
                    'quote' => 'Probablementééééééééééééééééééééééééééééé la gare est fermée en 1960.',
                    'origin' => 'texte',
                ],
            ]],
            'article de gare : réserve à 45' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
Probablementééééééééééééééééééééééééééééééééé la gare est fermée en 1960.',
                1 => 'Gare de Test',
            ],  [
                0 => [
                    'type' => 'closure',
                    'date' => '1960-01-01',
                    'precision' => 'year',
                    'quote' => 'Probablementééééééééééééééééééééééééééééééééé la gare est fermée en 1960.',
                    'origin' => 'texte',
                ],
            ]],
            'article de gare : réserve après à 100' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
La gare est fermée en 1960 é é é é é é é é é é é é é é é é é é é é environ é é é é é é é é é é é é é é é é é é é é.',
                1 => 'Gare de Test',
            ],  [
                0 => [
                    'type' => 'closure',
                    'date' => '1960-01-01',
                    'precision' => 'year',
                    'quote' => 'La gare est fermée en 1960 é é é é é é é é é é é é é é é é é é é é environ é é é é é é é é é é é é é é é é é é é é. (date incertaine selon la source)',
                    'origin' => 'texte',
                ],
            ]],
            'article de gare : réserve après à 118' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
La gare est fermée en 1960 é é é é é é é é é é é é é é é é é é é é environ é é é é é é é é é é é é é é é é é é é é é é é é é é é é é.',
                1 => 'Gare de Test',
            ],  [
                0 => [
                    'type' => 'closure',
                    'date' => '1960-01-01',
                    'precision' => 'year',
                    'quote' => 'La gare est fermée en 1960 é é é é é é é é é é é é é é é é é é é é environ é é é é é é é é é é é é é é é é é é é é é é é é é é é é é. (date incertaine selon la source)',
                    'origin' => 'texte',
                ],
            ]],
            'article de gare : réserve après à 119' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
La gare est fermée en 1960 é é é é é é é é é é é é é é é é é é é é environ é é é é é é é é é é é é é é é é é é é é é é é é é é é é é.',
                1 => 'Gare de Test',
            ],  [
                0 => [
                    'type' => 'closure',
                    'date' => '1960-01-01',
                    'precision' => 'year',
                    'quote' => 'La gare est fermée en 1960 é é é é é é é é é é é é é é é é é é é é environ é é é é é é é é é é é é é é é é é é é é é é é é é é é é é. (date incertaine selon la source)',
                    'origin' => 'texte',
                ],
            ]],
            'article de gare : réserve après à 120' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
La gare est fermée en 1960 é é é é é é é é é é é é é é é é é é é é environ é é é é é é é é é é é é é é é é é é é é é é é é é é é é é é.',
                1 => 'Gare de Test',
            ],  [
                0 => [
                    'type' => 'closure',
                    'date' => '1960-01-01',
                    'precision' => 'year',
                    'quote' => 'La gare est fermée en 1960 é é é é é é é é é é é é é é é é é é é é environ é é é é é é é é é é é é é é é é é é é é é é é é é é é é é é. (date incertaine selon la source)',
                    'origin' => 'texte',
                ],
            ]],
            'article de gare : réserve après à 121' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
La gare est fermée en 1960 é é é é é é é é é é é é é é é é é é é é environ é é é é é é é é é é é é é é é é é é é é é é é é é é é é é é.',
                1 => 'Gare de Test',
            ],  [
                0 => [
                    'type' => 'closure',
                    'date' => '1960-01-01',
                    'precision' => 'year',
                    'quote' => 'La gare est fermée en 1960 é é é é é é é é é é é é é é é é é é é é environ é é é é é é é é é é é é é é é é é é é é é é é é é é é é é é. (date incertaine selon la source)',
                    'origin' => 'texte',
                ],
            ]],
            'article de gare : réserve après à 125' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
La gare est fermée en 1960 é é é é é é é é é é é é é é é é é é é é environ é é é é é é é é é é é é é é é é é é é é é é é é é é é é é é é é.',
                1 => 'Gare de Test',
            ],  [
                0 => [
                    'type' => 'closure',
                    'date' => '1960-01-01',
                    'precision' => 'year',
                    'quote' => 'La gare est fermée en 1960 é é é é é é é é é é é é é é é é é é é é environ é é é é é é é é é é é é é é é é é é é é é é é é é é é é é é é é. (date incertaine selon la source)',
                    'origin' => 'texte',
                ],
            ]],
            'article de gare : siècle près du verbe' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
La gare est fermée au XXe siècle.',
                1 => 'Gare de Test',
            ],  [
                0 => [
                    'type' => 'closure',
                    'date' => '1900-01-01',
                    'precision' => 'after',
                    'quote' => 'La gare est fermée au XXe siècle.',
                    'origin' => 'texte',
                ],
            ]],
            'article de gare : siècle à 40 caractères' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
La gare est fermée éééééééééééééééééééééééééééééééééééééé XXe siècle.',
                1 => 'Gare de Test',
            ],  [
                0 => [
                    'type' => 'closure',
                    'date' => '1900-01-01',
                    'precision' => 'after',
                    'quote' => 'La gare est fermée éééééééééééééééééééééééééééééééééééééé XXe siècle.',
                    'origin' => 'texte',
                ],
            ]],
            'article de gare : siècle à 41 caractères' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
La gare est fermée éééééééééééééééééééééééééééééééééééééééééé XXe siècle.',
                1 => 'Gare de Test',
            ],  [
            ]],
            'article de gare : ouverte entre le verbe et la date' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
La gare est fermée puis ouverte en 1960.',
                1 => 'Gare de Test',
            ],  [
            ]],
            'article de gare : mise en service' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
La gare est fermée, mise en service en 1960.',
                1 => 'Gare de Test',
            ],  [
            ]],
            'article de gare : rupture lors de' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
La gare est fermée lors de la guerre de 1960.',
                1 => 'Gare de Test',
            ],  [
            ]],
            'article de gare : gare de X fermée' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
La gare de Saint-Étienne est fermée en 1960.',
                1 => 'Gare de Test',
            ],  [
                0 => [
                    'type' => 'closure',
                    'date' => '1960-01-01',
                    'precision' => 'year',
                    'quote' => 'La gare de Saint-Étienne est fermée en 1960.',
                    'origin' => 'texte',
                ],
            ]],
            'article de gare : halte de X' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
La halte de l\'Étoile est fermée en 1960.',
                1 => 'Gare de Test',
            ],  [
                0 => [
                    'type' => 'closure',
                    'date' => '1960-01-01',
                    'precision' => 'year',
                    'quote' => 'La halte de l\'Étoile est fermée en 1960.',
                    'origin' => 'texte',
                ],
            ]],
            'article de gare : gare de X trop longue' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
La gare de éééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééé est fermée en 1960.',
                1 => 'Gare de Test',
            ],  [
                0 => [
                    'type' => 'closure',
                    'date' => '1960-01-01',
                    'precision' => 'year',
                    'quote' => 'La gare de éééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééé est fermée en 1960.',
                    'origin' => 'texte',
                ],
            ]],
            'article de gare : sujet avant la section' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
La portion entre la gare de Tours et celle de Blois fermée en 1960.',
                1 => 'Gare de Test',
            ],  [
            ]],
            'article de gare : écart de sujet 90' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
La gare, ééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééé fermée en 1960.',
                1 => 'Gare de Test',
            ],  [
                0 => [
                    'type' => 'closure',
                    'date' => '1960-01-01',
                    'precision' => 'year',
                    'quote' => 'La gare, ééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééé fermée en 1960.',
                    'origin' => 'texte',
                ],
            ]],
            'article de gare : écart de sujet 91' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
La gare ééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééé fermée en 1960.',
                1 => 'Gare de Test',
            ],  [
                0 => [
                    'type' => 'closure',
                    'date' => '1960-01-01',
                    'precision' => 'year',
                    'quote' => 'La gare ééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééé fermée en 1960.',
                    'origin' => 'texte',
                ],
            ]],
            'article de gare : autre sujet' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
La gare et le dépôt fermée en 1960.',
                1 => 'Gare de Test',
            ],  [
            ]],
            'article de gare : elle après la ligne' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
La ligne est ouverte. Elle est fermée en 1960.',
                1 => 'Gare de Test',
            ],  [
            ]],
            'article de gare : elle après une autre phrase' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
Le service est assuré. Elle est fermée en 1960.',
                1 => 'Gare de Test',
            ],  [
                0 => [
                    'type' => 'closure',
                    'date' => '1960-01-01',
                    'precision' => 'year',
                    'quote' => 'Elle est fermée en 1960.',
                    'origin' => 'texte',
                ],
            ]],
            'article de gare : En après la ligne' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
En 1900, la ligne est ouverte.
Elle est fermée en 1960.',
                1 => 'Gare de Test',
            ],  [
            ]],
            'article de gare : supprimée avec est' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
La gare a été supprimée en 1960.',
                1 => 'Gare de Test',
            ],  [
                0 => [
                    'type' => 'closure',
                    'date' => '1960-01-01',
                    'precision' => 'year',
                    'quote' => 'La gare a été supprimée en 1960.',
                    'origin' => 'texte',
                ],
            ]],
            'article de gare : supprimée sans sujet direct' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
La gare du village voisine supprimée en 1960.',
                1 => 'Gare de Test',
            ],  [
            ]],
            'article de gare : plus desservie' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
La gare ne sont plus desservies depuis 1960.',
                1 => 'Gare de Test',
            ],  [
                0 => [
                    'type' => 'closure_passenger',
                    'date' => '1960-01-01',
                    'precision' => 'year',
                    'quote' => 'La gare ne sont plus desservies depuis 1960.',
                    'origin' => 'texte',
                ],
            ]],
            'article de gare : cesse d\'être desservie' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
La gare cesse d\'être desservie en 1960.',
                1 => 'Gare de Test',
            ],  [
                0 => [
                    'type' => 'closure_passenger',
                    'date' => '1960-01-01',
                    'precision' => 'year',
                    'quote' => 'La gare cesse d\'être desservie en 1960.',
                    'origin' => 'texte',
                ],
            ]],
            'article de gare : marchandises après la date' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
En 2007, la gare est désaffectée du trafic marchandises.',
                1 => 'Gare de Test',
            ],  [
                0 => [
                    'type' => 'closure_freight',
                    'date' => '2007-01-01',
                    'precision' => 'year',
                    'quote' => 'En 2007, la gare est désaffectée du trafic marchandises.',
                    'origin' => 'texte',
                ],
            ]],
            'article de gare : puis au fret éloigné' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
La gare est fermée aux voyageurs en 1938ééééééééééééééééééééééééééééé, puis au fret en 1952.',
                1 => 'Gare de Test',
            ],  [
                0 => [
                    'type' => 'closure_passenger',
                    'date' => '1938-01-01',
                    'precision' => 'year',
                    'quote' => 'La gare est fermée aux voyageurs en 1938ééééééééééééééééééééééééééééé, puis au fret en 1952.',
                    'origin' => 'texte',
                ],
            ]],
            'article de gare : puis au fret trop éloigné' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
La gare est fermée aux voyageurs en 1938ééééééééééééééééééééééééééééééé, puis au fret en 1952.',
                1 => 'Gare de Test',
            ],  [
                0 => [
                    'type' => 'closure_passenger',
                    'date' => '1938-01-01',
                    'precision' => 'year',
                    'quote' => 'La gare est fermée aux voyageurs en 1938ééééééééééééééééééééééééééééééé, puis au fret en 1952.',
                    'origin' => 'texte',
                ],
            ]],
            'article de gare : puis tout trafic' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
La gare est fermée aux voyageurs en 1938, puis à tout trafic en 1960.',
                1 => 'Gare de Test',
            ],  [
                0 => [
                    'type' => 'closure_passenger',
                    'date' => '1938-01-01',
                    'precision' => 'year',
                    'quote' => 'La gare est fermée aux voyageurs en 1938, puis à tout trafic en 1960.',
                    'origin' => 'texte',
                ],
                1 => [
                    'type' => 'closure',
                    'date' => '1960-01-01',
                    'precision' => 'year',
                    'quote' => 'La gare est fermée aux voyageurs en 1938, puis à tout trafic en 1960.',
                    'origin' => 'texte',
                ],
            ]],
            'article de gare : puis décennie ignorée' => ['fromStationArticle',  [
                0 => 'La \'\'\'gare\'\'\' est une gare.
La gare est fermée aux voyageurs en 1938, puis au fret dans les années 1950.',
                1 => 'Gare de Test',
            ],  [
                0 => [
                    'type' => 'closure_passenger',
                    'date' => '1938-01-01',
                    'precision' => 'year',
                    'quote' => 'La gare est fermée aux voyageurs en 1938, puis au fret dans les années 1950.',
                    'origin' => 'texte',
                ],
            ]],
            'article de ligne : puce date' => ['fromLineArticle',  [
                0 => '== T ==
* [[Gare de Léguevin|Léguevin]], fermée le 4 mai 1969, ouverte en 1890',
                1 => 'Ligne de Test',
            ],  [
                0 => [
                    'names' => [
                        0 => 'Léguevin',
                    ],
                    'type' => 'closure',
                    'date' => '1969-05-04',
                    'precision' => 'day',
                    'quote' => 'Léguevin , fermée le 4 mai 1969, ouverte en 1890',
                    'origin' => 'liste',
                ],
            ]],
            'article de ligne : puce nominale' => ['fromLineArticle',  [
                0 => '== T ==
* [[Gare de Léguevin]] fermée aux marchandises en 1969',
                1 => 'Ligne de Test',
            ],  [
                0 => [
                    'names' => [
                        0 => 'Léguevin',
                    ],
                    'type' => 'closure_freight',
                    'date' => '1969-01-01',
                    'precision' => 'year',
                    'quote' => 'Léguevin fermée aux marchandises en 1969',
                    'origin' => 'liste',
                ],
            ]],
            'article de ligne : puce autre sujet' => ['fromLineArticle',  [
                0 => '== T ==
* [[Gare de Léguevin]] et le dépôt fermée en 1960',
                1 => 'Ligne de Test',
            ],  [
            ]],
            'article de ligne : puce sans nom' => ['fromLineArticle',  [
                0 => '== T ==
* [[Gare de Léguevin|]], fermée en 1969',
                1 => 'Ligne de Test',
            ],  [
            ]],
            'article de ligne : puce du' => ['fromLineArticle',  [
                0 => '== T ==
* [[Gare du Pont]] : fermée en 1969',
                1 => 'Ligne de Test',
            ],  [
                0 => [
                    'names' => [
                        0 => 'Le Pont',
                    ],
                    'type' => 'closure',
                    'date' => '1969-01-01',
                    'precision' => 'year',
                    'quote' => 'Le Pont : fermée en 1969',
                    'origin' => 'liste',
                ],
            ]],
            'article de ligne : puce d\'' => ['fromLineArticle',  [
                0 => '== T ==
* [[Gare d\'Orléans]] : fermée en 1969',
                1 => 'Ligne de Test',
            ],  [
                0 => [
                    'names' => [
                        0 => 'Orléans',
                    ],
                    'type' => 'closure',
                    'date' => '1969-01-01',
                    'precision' => 'year',
                    'quote' => 'Orléans : fermée en 1969',
                    'origin' => 'liste',
                ],
            ]],
            'article de ligne : puce de l\'' => ['fromLineArticle',  [
                0 => '== T ==
* [[Gare de l\'Est]] : fermée en 1969',
                1 => 'Ligne de Test',
            ],  [
                0 => [
                    'names' => [
                        0 => 'L\'Est',
                    ],
                    'type' => 'closure',
                    'date' => '1969-01-01',
                    'precision' => 'year',
                    'quote' => 'L\'Est : fermée en 1969',
                    'origin' => 'liste',
                ],
            ]],
            'article de ligne : puce de la' => ['fromLineArticle',  [
                0 => '== T ==
* [[Gare de la Chapelle]] : fermée en 1969',
                1 => 'Ligne de Test',
            ],  [
                0 => [
                    'names' => [
                        0 => 'La Chapelle',
                    ],
                    'type' => 'closure',
                    'date' => '1969-01-01',
                    'precision' => 'year',
                    'quote' => 'La Chapelle : fermée en 1969',
                    'origin' => 'liste',
                ],
            ]],
            'article de ligne : puce des' => ['fromLineArticle',  [
                0 => '== T ==
* [[Gare des Ifs]] : fermée en 1969',
                1 => 'Ligne de Test',
            ],  [
                0 => [
                    'names' => [
                        0 => 'Les Ifs',
                    ],
                    'type' => 'closure',
                    'date' => '1969-01-01',
                    'precision' => 'year',
                    'quote' => 'Les Ifs : fermée en 1969',
                    'origin' => 'liste',
                ],
            ]],
            'article de ligne : puce Halte' => ['fromLineArticle',  [
                0 => '== T ==
* [[Halte de Lannoy]] : fermée en 1969 aux voyageurs',
                1 => 'Ligne de Test',
            ],  [
                0 => [
                    'names' => [
                        0 => 'Lannoy',
                    ],
                    'type' => 'closure',
                    'date' => '1969-01-01',
                    'precision' => 'year',
                    'quote' => 'Lannoy : fermée en 1969 aux voyageurs',
                    'origin' => 'liste',
                ],
            ]],
            'article de ligne : puce Arrêt' => ['fromLineArticle',  [
                0 => '== T ==
* [[Arrêt de Lannoy]] : fermée en 1969',
                1 => 'Ligne de Test',
            ],  [
                0 => [
                    'names' => [
                        0 => 'Lannoy',
                    ],
                    'type' => 'closure',
                    'date' => '1969-01-01',
                    'precision' => 'year',
                    'quote' => 'Lannoy : fermée en 1969',
                    'origin' => 'liste',
                ],
            ]],
            'article de ligne : puce avec parenthèse' => ['fromLineArticle',  [
                0 => '== T ==
* [[Gare de Lannoy|Lannoy (Oise)]] : fermée en 1969',
                1 => 'Ligne de Test',
            ],  [
                0 => [
                    'names' => [
                        0 => 'Lannoy',
                    ],
                    'type' => 'closure',
                    'date' => '1969-01-01',
                    'precision' => 'year',
                    'quote' => 'Lannoy : fermée en 1969',
                    'origin' => 'liste',
                ],
            ]],
            'article de ligne : puce deux niveaux' => ['fromLineArticle',  [
                0 => '== T ==
** [[Gare de Lannoy]] : fermée en 1969',
                1 => 'Ligne de Test',
            ],  [
                0 => [
                    'names' => [
                        0 => 'Lannoy',
                    ],
                    'type' => 'closure',
                    'date' => '1969-01-01',
                    'precision' => 'year',
                    'quote' => 'Lannoy : fermée en 1969',
                    'origin' => 'liste',
                ],
            ]],
            'article de ligne : puce numérotée' => ['fromLineArticle',  [
                0 => '== T ==
# [[Gare de Lannoy]] : fermée en 1969',
                1 => 'Ligne de Test',
            ],  [
                0 => [
                    'names' => [
                        0 => 'Lannoy',
                    ],
                    'type' => 'closure',
                    'date' => '1969-01-01',
                    'precision' => 'year',
                    'quote' => 'Lannoy : fermée en 1969',
                    'origin' => 'liste',
                ],
            ]],
            'article de ligne : puce en gras' => ['fromLineArticle',  [
                0 => '== T ==
* \'\'\'[[Gare de Lannoy]]\'\'\' : fermée en 1969',
                1 => 'Ligne de Test',
            ],  [
                0 => [
                    'names' => [
                        0 => 'Lannoy',
                    ],
                    'type' => 'closure',
                    'date' => '1969-01-01',
                    'precision' => 'year',
                    'quote' => 'Lannoy : fermée en 1969',
                    'origin' => 'liste',
                ],
            ]],
            'article de ligne : puce 2 verbes' => ['fromLineArticle',  [
                0 => '== T ==
* [[Gare de Lannoy]] : ouverte en 1890, supprimée en 1960, fermée en 1969',
                1 => 'Ligne de Test',
            ],  [
                0 => [
                    'names' => [
                        0 => 'Lannoy',
                    ],
                    'type' => 'closure',
                    'date' => '1960-01-01',
                    'precision' => 'year',
                    'quote' => 'Lannoy : ouverte en 1890, supprimée en 1960, fermée en 1969',
                    'origin' => 'liste',
                ],
            ]],
            'article de ligne : puce sans date' => ['fromLineArticle',  [
                0 => '== T ==
* [[Gare de Lannoy]] : fermée',
                1 => 'Ligne de Test',
            ],  [
            ]],
            'article de ligne : phrase 3 noms' => ['fromLineArticle',  [
                0 => '== T ==
Les gares de Lisieux, de Orbec et de Vimoutiers sont fermées en 1960.',
                1 => 'Ligne de Test',
            ],  [
                0 => [
                    'names' => [
                        0 => 'Lisieux',
                        1 => 'Orbec',
                        2 => 'Vimoutiers',
                    ],
                    'type' => 'closure',
                    'date' => '1960-01-01',
                    'precision' => 'year',
                    'quote' => 'Les gares de Lisieux, de Orbec et de Vimoutiers sont fermées en 1960.',
                    'origin' => 'texte',
                ],
            ]],
            'article de ligne : phrase gares voyageurs' => ['fromLineArticle',  [
                0 => '== T ==
Les gares voyageurs de Caen et Bayeux sont fermées en 1960.',
                1 => 'Ligne de Test',
            ],  [
                0 => [
                    'names' => [
                        0 => 'Caen',
                        1 => 'Bayeux',
                    ],
                    'type' => 'closure',
                    'date' => '1960-01-01',
                    'precision' => 'year',
                    'quote' => 'Les gares voyageurs de Caen et Bayeux sont fermées en 1960.',
                    'origin' => 'texte',
                ],
            ]],
            'article de ligne : phrase rurales' => ['fromLineArticle',  [
                0 => '== T ==
Les haltes rurales de Caen sont fermées aux voyageurs en 1960.',
                1 => 'Ligne de Test',
            ],  [
                0 => [
                    'names' => [
                        0 => 'Caen',
                    ],
                    'type' => 'closure',
                    'date' => '1960-01-01',
                    'precision' => 'year',
                    'quote' => 'Les haltes rurales de Caen sont fermées aux voyageurs en 1960.',
                    'origin' => 'texte',
                ],
            ]],
            'article de ligne : phrase point d\'arrêt' => ['fromLineArticle',  [
                0 => '== T ==
Le point d\'arrêt de Caen est fermé en 1960.',
                1 => 'Ligne de Test',
            ],  [
                0 => [
                    'names' => [
                        0 => 'Caen',
                    ],
                    'type' => 'closure',
                    'date' => '1960-01-01',
                    'precision' => 'year',
                    'quote' => 'Le point d\'arrêt de Caen est fermé en 1960.',
                    'origin' => 'texte',
                ],
            ]],
            'article de ligne : phrase station' => ['fromLineArticle',  [
                0 => '== T ==
Les stations de Caen sont fermées en 1960.',
                1 => 'Ligne de Test',
            ],  [
                0 => [
                    'names' => [
                        0 => 'Caen',
                    ],
                    'type' => 'closure',
                    'date' => '1960-01-01',
                    'precision' => 'year',
                    'quote' => 'Les stations de Caen sont fermées en 1960.',
                    'origin' => 'texte',
                ],
            ]],
            'article de ligne : phrase suppression' => ['fromLineArticle',  [
                0 => '== T ==
La suppression des gares de Caen et de Bayeux date de 1960.',
                1 => 'Ligne de Test',
            ],  [
                0 => [
                    'names' => [
                        0 => 'Caen',
                        1 => 'Bayeux',
                    ],
                    'type' => 'closure',
                    'date' => '1960-01-01',
                    'precision' => 'year',
                    'quote' => 'La suppression des gares de Caen et de Bayeux date de 1960.',
                    'origin' => 'texte',
                ],
            ]],
            'article de ligne : phrase désaffectation' => ['fromLineArticle',  [
                0 => '== T ==
La désaffectation de la gare de Caen en 1960 est décidée.',
                1 => 'Ligne de Test',
            ],  [
                0 => [
                    'names' => [
                        0 => 'Caen',
                    ],
                    'type' => 'closure',
                    'date' => '1960-01-01',
                    'precision' => 'year',
                    'quote' => 'La désaffectation de la gare de Caen en 1960 est décidée.',
                    'origin' => 'texte',
                ],
            ]],
            'article de ligne : phrase reste ouverte' => ['fromLineArticle',  [
                0 => '== T ==
Les gares de Caen sont ouvertes mais fermées en 1960.',
                1 => 'Ligne de Test',
            ],  [
            ]],
            'article de ligne : phrase maintenue' => ['fromLineArticle',  [
                0 => '== T ==
La gare de Caen reste fermée en 1960.',
                1 => 'Ligne de Test',
            ],  [
            ]],
            'article de ligne : phrase à' => ['fromLineArticle',  [
                0 => '== T ==
La gare de Caen à celle de Bayeux est fermée en 1960.',
                1 => 'Ligne de Test',
            ],  [
            ]],
            'article de ligne : phrase jusqu\'à' => ['fromLineArticle',  [
                0 => '== T ==
La gare de Caen jusqu\'à Bayeux est fermée en 1960.',
                1 => 'Ligne de Test',
            ],  [
            ]],
            'article de ligne : phrase entre' => ['fromLineArticle',  [
                0 => '== T ==
Entre la gare de Caen et Bayeux fermée en 1960.',
                1 => 'Ligne de Test',
            ],  [
            ]],
            'article de ligne : phrase long écart' => ['fromLineArticle',  [
                0 => '== T ==
La gare de Caen ééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééé est fermée en 1960.',
                1 => 'Ligne de Test',
            ],  [
            ]],
            'article de ligne : phrase long écart 121' => ['fromLineArticle',  [
                0 => '== T ==
La gare de Caen ééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééé est fermée en 1960.',
                1 => 'Ligne de Test',
            ],  [
            ]],
            'article de ligne : phrase qui' => ['fromLineArticle',  [
                0 => '== T ==
La gare de Caen qui est fermée en 1960.',
                1 => 'Ligne de Test',
            ],  [
            ]],
            'article de ligne : phrase deux gares halte' => ['fromLineArticle',  [
                0 => '== T ==
Les haltes de Caen et la gare de Bayeux sont fermées aux voyageurs en 1960.',
                1 => 'Ligne de Test',
            ],  [
                0 => [
                    'names' => [
                        0 => 'Caen',
                    ],
                    'type' => 'closure',
                    'date' => '1960-01-01',
                    'precision' => 'year',
                    'quote' => 'Les haltes de Caen et la gare de Bayeux sont fermées aux voyageurs en 1960.',
                    'origin' => 'texte',
                ],
                1 => [
                    'names' => [
                        0 => 'Bayeux',
                    ],
                    'type' => 'closure_passenger',
                    'date' => '1960-01-01',
                    'precision' => 'year',
                    'quote' => 'Les haltes de Caen et la gare de Bayeux sont fermées aux voyageurs en 1960.',
                    'origin' => 'texte',
                ],
            ]],
            'article de ligne : phrase date avant' => ['fromLineArticle',  [
                0 => '== T ==
En 1960, la gare de Caen est fermée au trafic voyageurs.',
                1 => 'Ligne de Test',
            ],  [
                0 => [
                    'names' => [
                        0 => 'Caen',
                    ],
                    'type' => 'closure_passenger',
                    'date' => '1960-01-01',
                    'precision' => 'year',
                    'quote' => 'En 1960, la gare de Caen est fermée au trafic voyageurs.',
                    'origin' => 'texte',
                ],
            ]],
            'article de ligne : phrase fret' => ['fromLineArticle',  [
                0 => '== T ==
La gare de Caen est fermée en 1960 au fret.',
                1 => 'Ligne de Test',
            ],  [
                0 => [
                    'names' => [
                        0 => 'Caen',
                    ],
                    'type' => 'closure_freight',
                    'date' => '1960-01-01',
                    'precision' => 'year',
                    'quote' => 'La gare de Caen est fermée en 1960 au fret.',
                    'origin' => 'texte',
                ],
            ]],
            'article de ligne : phrase siècle' => ['fromLineArticle',  [
                0 => '== T ==
La gare de Caen est fermée au XXe siècle.',
                1 => 'Ligne de Test',
            ],  [
                0 => [
                    'names' => [
                        0 => 'Caen',
                    ],
                    'type' => 'closure',
                    'date' => '1900-01-01',
                    'precision' => 'after',
                    'quote' => 'La gare de Caen est fermée au XXe siècle.',
                    'origin' => 'texte',
                ],
            ]],
            'article de ligne : tableau colonne gare sans lien' => ['fromLineArticle',  [
                0 => '== T ==
{| class="wikitable"
! Gare !! Fermeture
|-
| Orbec || 1938
|-
| ééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééé || 1938
|}',
                1 => 'Ligne de Test',
            ],  [
                0 => [
                    'names' => [
                        0 => 'Orbec',
                    ],
                    'quote' => 'Orbec : fermeture 1938',
                    'type' => 'closure',
                    'date' => '1938-01-01',
                    'precision' => 'year',
                    'origin' => 'tableau',
                ],
            ]],
            'article de ligne : tableau gares' => ['fromLineArticle',  [
                0 => '== T ==
{| class="wikitable"
! Stations !! État
|-
| Lisieux || Fermée en 1938
|-
| Orbec || Ouverte
|-
| Caen || (fermée) 1950
|}',
                1 => 'Ligne de Test',
            ],  [
                0 => [
                    'names' => [
                        0 => 'Lisieux',
                    ],
                    'quote' => 'Lisieux : Fermée en 1938',
                    'type' => 'closure',
                    'date' => '1938-01-01',
                    'precision' => 'year',
                    'origin' => 'tableau',
                ],
                1 => [
                    'names' => [
                        0 => 'Caen',
                    ],
                    'quote' => 'Caen : (fermée) 1950 |}',
                    'type' => 'closure',
                    'date' => '1950-01-01',
                    'precision' => 'year',
                    'origin' => 'tableau',
                ],
            ]],
            'article de ligne : tableau supprimée' => ['fromLineArticle',  [
                0 => '== T ==
{| class="wikitable"
! Gare !! État
|-
| [[Gare de Lisieux]] || Supprimée en 1938
|-
| [[Gare de Caen]] || désaffectée en 1950
|}',
                1 => 'Ligne de Test',
            ],  [
                0 => [
                    'names' => [
                        0 => 'Lisieux',
                    ],
                    'quote' => 'Lisieux : Supprimée en 1938',
                    'type' => 'closure',
                    'date' => '1938-01-01',
                    'precision' => 'year',
                    'origin' => 'tableau',
                ],
                1 => [
                    'names' => [
                        0 => 'Caen',
                    ],
                    'quote' => 'Caen : désaffectée en 1950 |}',
                    'type' => 'closure',
                    'date' => '1950-01-01',
                    'precision' => 'year',
                    'origin' => 'tableau',
                ],
            ]],
            'article de ligne : tableau attributs' => ['fromLineArticle',  [
                0 => '== T ==
{| class="wikitable"
! Gare !! Date
|-
| [[Gare de Lisieux]] || style="x" | Fermée en 1938
|-
| [[Gare de Caen]] || align=center | Fermée en 1950
|}',
                1 => 'Ligne de Test',
            ],  [
                0 => [
                    'names' => [
                        0 => 'Lisieux',
                    ],
                    'quote' => 'Lisieux : Fermée en 1938',
                    'type' => 'closure',
                    'date' => '1938-01-01',
                    'precision' => 'year',
                    'origin' => 'tableau',
                ],
                1 => [
                    'names' => [
                        0 => 'Caen',
                    ],
                    'quote' => 'Caen : Fermée en 1950 |}',
                    'type' => 'closure',
                    'date' => '1950-01-01',
                    'precision' => 'year',
                    'origin' => 'tableau',
                ],
            ]],
            'article de ligne : tableau deux colonnes' => ['fromLineArticle',  [
                0 => '== T ==
{| class="wikitable"
! Gare !! Fermeture voyageurs !! Fermeture marchandises
|-
| [[Gare de Lisieux]] || 1938 || 1950
|}',
                1 => 'Ligne de Test',
            ],  [
                0 => [
                    'names' => [
                        0 => 'Lisieux',
                    ],
                    'quote' => 'Lisieux : fermeture voyageurs 1938',
                    'type' => 'closure_passenger',
                    'date' => '1938-01-01',
                    'precision' => 'year',
                    'origin' => 'tableau',
                ],
                1 => [
                    'names' => [
                        0 => 'Lisieux',
                    ],
                    'quote' => 'Lisieux : fermeture marchandises 1950 |}',
                    'type' => 'closure_freight',
                    'date' => '1950-01-01',
                    'precision' => 'year',
                    'origin' => 'tableau',
                ],
            ]],
            'article de ligne : tableau rowspan' => ['fromLineArticle',  [
                0 => '== T ==
{| class="wikitable"
|-
! Gare
! Fermeture
|-
| [[Gare de Lisieux]]
| 1938
|}',
                1 => 'Ligne de Test',
            ],  [
                0 => [
                    'names' => [
                        0 => 'Lisieux',
                    ],
                    'quote' => 'Lisieux : fermeture 1938 |}',
                    'type' => 'closure',
                    'date' => '1938-01-01',
                    'precision' => 'year',
                    'origin' => 'tableau',
                ],
            ]],
            'article de ligne : deux tableaux' => ['fromLineArticle',  [
                0 => '== T ==
{|
! Gare !! Fermeture
|-
| [[Gare de A]] || 1938
|}
texte
{|
! Gare !! Fermeture
|-
| [[Gare de B]] || 1939
|}',
                1 => 'Ligne de Test',
            ],  [
                0 => [
                    'names' => [
                        0 => 'A',
                    ],
                    'quote' => 'A : fermeture 1938 |}',
                    'type' => 'closure',
                    'date' => '1938-01-01',
                    'precision' => 'year',
                    'origin' => 'tableau',
                ],
                1 => [
                    'names' => [
                        0 => 'B',
                    ],
                    'quote' => 'B : fermeture 1939 |}',
                    'type' => 'closure',
                    'date' => '1939-01-01',
                    'precision' => 'year',
                    'origin' => 'tableau',
                ],
            ]],
            'article de ligne : chronologie sans puce' => ['fromLineArticle',  [
                0 => '== T ==
6 mars 1972 : fermeture des gares de Caen et Bayeux.',
                1 => 'Ligne de Test',
            ],  [
                0 => [
                    'names' => [
                        0 => 'Caen',
                        1 => 'Bayeux',
                    ],
                    'type' => 'closure',
                    'date' => '1972-03-06',
                    'precision' => 'day',
                    'quote' => '6 mars 1972 : fermeture des gares de Caen et Bayeux.',
                    'origin' => 'texte',
                ],
            ]],
            'article de ligne : chronologie avec le' => ['fromLineArticle',  [
                0 => '== T ==
Le 6 mars 1972 : fermeture des gares de Caen.',
                1 => 'Ligne de Test',
            ],  [
                0 => [
                    'names' => [
                        0 => 'Caen',
                    ],
                    'type' => 'closure',
                    'date' => '1972-03-06',
                    'precision' => 'day',
                    'quote' => 'Le 6 mars 1972 : fermeture des gares de Caen.',
                    'origin' => 'texte',
                ],
            ]],
            'article de ligne : chronologie deux-points à 25' => ['fromLineArticle',  [
                0 => '== T ==
6 mars 1972éééééééééééé : fermeture des gares de Caen.',
                1 => 'Ligne de Test',
            ],  [
            ]],
            'article de ligne : chronologie deux-points à 27' => ['fromLineArticle',  [
                0 => '== T ==
6 mars 1972ééééééééééééééé : fermeture des gares de Caen.',
                1 => 'Ligne de Test',
            ],  [
            ]],
            'article de ligne : chronologie deux-points loin' => ['fromLineArticle',  [
                0 => '== T ==
6 mars 1972 : éééééééééééééééééééééééééééééé : fermeture des gares de Caen.',
                1 => 'Ligne de Test',
            ],  [
                0 => [
                    'names' => [
                        0 => 'Caen',
                    ],
                    'type' => 'closure',
                    'date' => '1972-03-06',
                    'precision' => 'day',
                    'quote' => '6 mars 1972 : éééééééééééééééééééééééééééééé : fermeture des gares de Caen.',
                    'origin' => 'texte',
                ],
            ]],
            'article de ligne : date en tête nominale' => ['fromLineArticle',  [
                0 => '== T ==
En 1960, fermeture des gares de Caen.',
                1 => 'Ligne de Test',
            ],  [
                0 => [
                    'names' => [
                        0 => 'Caen',
                    ],
                    'type' => 'closure',
                    'date' => '1960-01-01',
                    'precision' => 'year',
                    'quote' => 'En 1960, fermeture des gares de Caen.',
                    'origin' => 'texte',
                ],
            ]],
            'article de ligne : date en tête avec dès' => ['fromLineArticle',  [
                0 => '== T ==
Dès 1960, la fermeture des gares de Caen.',
                1 => 'Ligne de Test',
            ],  [
                0 => [
                    'names' => [
                        0 => 'Caen',
                    ],
                    'type' => 'closure',
                    'date' => '1960-01-01',
                    'precision' => 'year',
                    'quote' => 'Dès 1960, la fermeture des gares de Caen.',
                    'origin' => 'texte',
                ],
            ]],
            'article de ligne : deux dates en tête' => ['fromLineArticle',  [
                0 => '== T ==
En 1950 puis en 1960, fermeture des gares de Caen.',
                1 => 'Ligne de Test',
            ],  [
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

    public function test_a_decade_in_a_sentence_keeps_its_decade_precision(): void
    {
        $parser = new StationClosureParser(new WikipediaLineParser);

        $events = $parser->fromStationArticle("La '''gare''' est une gare.\nLa gare est fermée dans les années 1980.", 'Gare de Test');

        $this->assertCount(1, $events);
        $this->assertSame(['date' => '1980-01-01', 'precision' => 'decade'], ['date' => $events[0]['date'], 'precision' => $events[0]['precision']]);
    }

    public function test_a_single_year_word_is_not_a_decade(): void
    {
        $parser = new StationClosureParser(new WikipediaLineParser);

        $events = $parser->fromStationArticle("La '''gare''' est une gare.\nLa gare est fermée à la fin de l'année 2011.", 'Gare de Test');

        $this->assertCount(1, $events);
        $this->assertSame(['date' => '2011-01-01', 'precision' => 'year'], ['date' => $events[0]['date'], 'precision' => $events[0]['precision']]);
    }

    public function test_a_bulleted_chronology_line_gives_its_closure(): void
    {
        $parser = new StationClosureParser(new WikipediaLineParser);

        $events = $parser->fromLineArticle("== T ==\n* 6 mars 1972 : fermeture des gares de Caen et Bayeux.", 'Ligne de Test');

        $this->assertCount(1, $events);
        $this->assertSame(['Caen', 'Bayeux'], $events[0]['names']);
        $this->assertSame(['date' => '1972-03-06', 'precision' => 'day'], ['date' => $events[0]['date'], 'precision' => $events[0]['precision']]);
    }

    public function test_a_nominal_closure_with_a_date_after_the_station_keeps_the_name_alone(): void
    {
        $parser = new StationClosureParser(new WikipediaLineParser);

        $events = $parser->fromLineArticle("== T ==\nLa désaffectation de la gare de Caen en 1960 est décidée.", 'Ligne de Test');

        $this->assertCount(1, $events);
        $this->assertSame(['Caen'], $events[0]['names']);
        $this->assertSame('1960-01-01', $events[0]['date']);
    }
}
