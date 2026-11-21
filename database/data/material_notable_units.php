<?php

/**
 * Rames et engins remarquables d'une série de matériel roulant, lus par App\Services\Materials\NotableUnits
 * (fiche de la série, API REST et GraphQL, outil MCP get_material, recherche).
 *
 * sources : identifiant → publication citée. `provider` est la clé du fournisseur dans source_rights.php
 *   (licence et statut des droits) ; `publisher` le titre du média ; `reported_by` le média que la page
 *   rapporte, quand elle ne fait que le citer ; `date` la date de publication (AAAA-MM-JJ) ou null.
 * units : slug de la série (materials.slug) → rames remarquables, chacune avec :
 *   - number : numéro de la rame ou de l'engin ; nickname : surnom, ou null ;
 *   - facts : faits datés. `date` (AAAA-MM-JJ, le 1er du mois ou de l'année quand la précision est moindre)
 *     et `date_to` (fin d'une période) avec `precision` (day, month, year ou null sans date) ; `text` en fr,
 *     en et de, reformulé ; `source` (clé de sources) ; `quote` : courte citation exacte de la source, ou null
 *     quand le fait est reformulé sans phrase à citer ;
 *   - unknown : ce que les sources ne disent pas, écrit tel quel (fr, en, de), ou null.
 *
 * Droits : faits isolés, une citation courte au plus par fait, lien vers la page ; aucun texte recopié.
 * Pas de tiret long ici.
 */
return [
    'sources' => [
        'lyonmag-2020-02-27' => [
            'provider' => 'tiers-cites',
            'publisher' => 'LyonMag',
            'reported_by' => null,
            'date' => '2020-02-27',
            'url' => 'https://www.lyonmag.com/article/105908/patrick-le-premier-tgv-va-faire-ses-adieux-a-lyon',
        ],
        'jnsforum-2020-02-06' => [
            'provider' => 'tiers-cites',
            'publisher' => 'JNS Forum',
            'reported_by' => 'France Bleu',
            'date' => '2020-02-06',
            'url' => 'https://jnsforum.com/community/topic/16467-patrick-the-first-commercial-tgv-retires-after-41-years',
        ],
        'wikipedia-en-tgv-pse' => [
            'provider' => 'wikipedia-en',
            'publisher' => 'Wikipedia (EN), SNCF TGV Sud-Est',
            'reported_by' => null,
            'date' => null,
            'url' => 'https://en.wikipedia.org/wiki/TGV_PSE',
        ],
    ],

    'units' => [
        'tgv-sud-est' => [
            [
                'number' => '01',
                'nickname' => 'Patrick',
                'facts' => [
                    [
                        'kind' => 'nickname',
                        'date' => null, 'date_to' => null, 'precision' => null,
                        'text' => [
                            'fr' => 'Surnommée « Patrick », du prénom du fils de son premier conducteur.',
                            'en' => 'Nicknamed “Patrick”, after the first name of its first driver’s son.',
                            'de' => 'Spitzname „Patrick“, nach dem Vornamen des Sohnes ihres ersten Triebfahrzeugführers.',
                        ],
                        'source' => 'lyonmag-2020-02-27',
                        'quote' => 'du prénom du fils de son premier conducteur',
                    ],
                    [
                        'kind' => 'first_of_series',
                        'date' => null, 'date_to' => null, 'precision' => null,
                        'text' => [
                            'fr' => 'Première rame de série du TGV.',
                            'en' => 'The first production TGV set.',
                            'de' => 'Der erste Serientriebzug des TGV.',
                        ],
                        'source' => 'wikipedia-en-tgv-pse',
                        'quote' => 'first production TGV set built',
                    ],
                    [
                        'kind' => 'built',
                        'date' => '1978-06-01', 'date_to' => null, 'precision' => 'month',
                        'text' => [
                            'fr' => 'Sortie des ateliers d’Alstom à Belfort.',
                            'en' => 'Rolled out of Alstom’s works in Belfort.',
                            'de' => 'Verließ das Alstom-Werk in Belfort.',
                        ],
                        'source' => 'lyonmag-2020-02-27',
                        'quote' => null,
                    ],
                    [
                        'kind' => 'service',
                        'date' => '1981-01-01', 'date_to' => null, 'precision' => 'year',
                        'text' => [
                            'fr' => 'En service entre Paris et Lyon dès 1981.',
                            'en' => 'In service between Paris and Lyon from 1981.',
                            'de' => 'Ab 1981 zwischen Paris und Lyon im Einsatz.',
                        ],
                        'source' => 'lyonmag-2020-02-27',
                        'quote' => null,
                    ],
                    [
                        'kind' => 'livery',
                        'date' => null, 'date_to' => null, 'precision' => null,
                        'text' => [
                            'fr' => 'Reconnaissable à sa couleur orange.',
                            'en' => 'Recognisable by its orange livery.',
                            'de' => 'An ihrer orangen Farbe zu erkennen.',
                        ],
                        'source' => 'lyonmag-2020-02-27',
                        'quote' => 'reconnaissable à sa couleur orange',
                    ],
                    [
                        'kind' => 'series_withdrawn',
                        'date' => '2019-12-01', 'date_to' => null, 'precision' => 'month',
                        'text' => [
                            'fr' => 'Toute la série TGV Sud-Est est retirée du service.',
                            'en' => 'The whole TGV Sud-Est fleet is withdrawn from service.',
                            'de' => 'Die gesamte Baureihe TGV Sud-Est wird ausgemustert.',
                        ],
                        'source' => 'wikipedia-en-tgv-pse',
                        'quote' => null,
                    ],
                    [
                        'kind' => 'farewell_livery',
                        'date' => '2020-01-01', 'date_to' => null, 'precision' => 'year',
                        'text' => [
                            'fr' => 'Pour sa tournée d’adieux, motrices dans l’orange d’origine et voitures en bleu et en carmillon, d’après France Bleu.',
                            'en' => 'For its farewell tour, power cars in the original orange and coaches in blue and carmillon red, as reported by France Bleu.',
                            'de' => 'Für die Abschiedsfahrt Triebköpfe im ursprünglichen Orange, Wagen in Blau und in Carmillon-Rot, wie France Bleu berichtete.',
                        ],
                        'source' => 'jnsforum-2020-02-06',
                        'quote' => null,
                    ],
                    [
                        'kind' => 'farewell_paris',
                        'date' => '2020-02-29', 'date_to' => '2020-03-01', 'precision' => 'day',
                        'text' => [
                            'fr' => 'Tournée d’adieux dans les gares et technicentres : escale à Paris-Gare-de-Lyon, avec une exposition à bord.',
                            'en' => 'Farewell tour of stations and maintenance centres: stop at Paris-Gare-de-Lyon, with an exhibition on board.',
                            'de' => 'Abschiedsfahrt durch Bahnhöfe und Instandhaltungswerke: Halt in Paris-Gare-de-Lyon, mit einer Ausstellung an Bord.',
                        ],
                        'source' => 'lyonmag-2020-02-27',
                        'quote' => null,
                    ],
                    [
                        'kind' => 'farewell_lyon',
                        'date' => '2020-03-07', 'date_to' => '2020-03-08', 'precision' => 'day',
                        'text' => [
                            'fr' => 'Escale à Lyon-Perrache, avec une exposition à bord.',
                            'en' => 'Stop at Lyon-Perrache, with an exhibition on board.',
                            'de' => 'Halt in Lyon-Perrache, mit einer Ausstellung an Bord.',
                        ],
                        'source' => 'lyonmag-2020-02-27',
                        'quote' => null,
                    ],
                ],
                'unknown' => [
                    'fr' => 'Ces sources ne disent pas ce qu’est devenue la rame après 2020.',
                    'en' => 'These sources do not say what became of the set after 2020.',
                    'de' => 'Was nach 2020 aus dem Triebzug wurde, geht aus diesen Quellen nicht hervor.',
                ],
            ],
        ],
    ],
];
