<?php

namespace App\Http\Controllers\Api;

/**
 * Exemples de réponses de l'API des accidents pour la documentation OpenAPI (attributs `ApiResponse`),
 * tirés de la base de développement après vff:import-accidents du 19/11/2026 (fiche de l'accident de
 * Millas, 14/12/2017 : passage à niveau, trois sources, bilans des blessés qui diffèrent).
 */
final class AccidentExamples
{
    /** Une page de la liste (`department=66&sort=-deaths&per_page=1`). */
    public const LIST = [
        'data' => [
            [
                'slug' => '1909-10-31-sauto',
                'wikidata_id' => 'Q18003224',
                'name' => [
                    'de' => 'Eisenbahnunfall an der Pont de Cassagne',
                    'en' => 'Accident du Paillat',
                    'fr' => 'Accident du Paillat',
                ],
                'kind' => 'other',
                'kind_label' => 'Autre accident',
                'date' => '1909-10-31',
                'date_precision' => 'day',
                'time' => null,
                'place' => [
                    'commune' => 'Sauto',
                    'commune_insee' => '66192',
                    'department_code' => '66',
                    'department' => 'Pyrénées-Orientales',
                    'lat' => 42.506572,
                    'lng' => 2.155631,
                    'precision' => 'exact',
                ],
                'line' => [
                    'slug' => 'rfn-669000',
                    'code' => '669000',
                    'name' => 'Ligne de Villefranche-Vernet-les-Bains à Latour-de-Carol',
                    'confidence' => 'high',
                ],
                'deaths' => 6,
                'injuries' => 8,
                'toll_source' => 'wikidata',
                'summary' => [
                    'de' => 'Am 31. Oktober 1909 ereignete sich in Sauto (Pyrénées-Orientales) ein Eisenbahnunfall. Bilanz: 6 Tote und 8 Verletzte.',
                    'en' => 'On 31 October 1909, a railway accident occurred at Sauto (Pyrénées-Orientales). Toll: 6 deaths and 8 injured.',
                    'fr' => 'Le 31 octobre 1909, un accident ferroviaire survient à Sauto (Pyrénées-Orientales). Bilan : 6 morts et 8 blessés.',
                ],
                'review_status' => 'ok',
                'url' => 'https://vff-project.org/accidents/1909-10-31-sauto',
            ],
        ],
        'links' => [
            'first' => 'https://vff-project.org/api/v1/accidents?department=66&per_page=1&sort=-deaths&page=1',
            'last' => 'https://vff-project.org/api/v1/accidents?department=66&per_page=1&sort=-deaths&page=4',
            'prev' => null,
            'next' => 'https://vff-project.org/api/v1/accidents?department=66&per_page=1&sort=-deaths&page=2',
        ],
        'meta' => [
            'current_page' => 1,
            'from' => 1,
            'last_page' => 4,
            'path' => 'https://vff-project.org/api/v1/accidents',
            'per_page' => 1,
            'to' => 1,
            'total' => 4,
        ],
    ];

    /** Fiche complète. */
    public const SHOW = [
        'data' => [
            'slug' => '2017-12-14-millas',
            'wikidata_id' => 'Q45757847',
            'name' => [
                'de' => 'Busunfall von Millas',
                'en' => 'Millas crash',
                'fr' => 'Accident de Millas',
            ],
            'kind' => 'level_crossing',
            'kind_label' => 'Passage à niveau',
            'date' => '2017-12-14',
            'date_precision' => 'day',
            'time' => null,
            'place' => [
                'commune' => 'Millas',
                'commune_insee' => '66108',
                'department_code' => '66',
                'department' => 'Pyrénées-Orientales',
                'lat' => 42.685278,
                'lng' => 2.703889,
                'precision' => 'exact',
            ],
            'line' => [
                'slug' => 'rfn-679000',
                'code' => '679000',
                'name' => 'Ligne de Perpignan à Villefranche-Vernet-les-Bains',
                'confidence' => 'high',
            ],
            'deaths' => 6,
            'injuries' => 14,
            'toll_source' => 'bea-tt',
            'summary' => [
                'de' => 'Am 14. Dezember 2017 ereignete sich in Millas (Pyrénées-Orientales) ein Zusammenstoß an einem Bahnübergang. Beteiligte Züge: SNCF Z 7300, TER. Bilanz: 6 Tote und 14 Verletzte. Die Quellen sind sich über diese Bilanz nicht einig.',
                'en' => 'On 14 December 2017, a collision occurred at a level crossing at Millas (Pyrénées-Orientales). Trains involved: SNCF Z 7300, TER. Toll: 6 deaths and 14 injured. The sources disagree on this toll.',
                'fr' => 'Le 14 décembre 2017, une collision survient à un passage à niveau à Millas (Pyrénées-Orientales). Trains concernés : SNCF Z 7300, TER. Bilan : 6 morts et 14 blessés. Les sources ne s’accordent pas sur ce bilan.',
            ],
            'review_status' => 'to_review',
            'url' => 'https://vff-project.org/accidents/2017-12-14-millas',
            'lines' => [
                [
                    'line' => [
                        'slug' => 'rfn-679000',
                        'code' => '679000',
                        'name' => 'Ligne de Perpignan à Villefranche-Vernet-les-Bains',
                    ],
                    'is_primary' => true,
                    'era_year' => 2010,
                    'distance_m' => 20,
                    'pk_m' => 482290,
                    'pk_label' => '482+290',
                    'section' => [
                        'from' => [
                            'slug' => 'millas',
                            'name' => 'Millas',
                        ],
                        'to' => [
                            'slug' => 'st-feliu-davall',
                            'name' => 'St-Féliu-d\'Avall',
                        ],
                    ],
                    'method' => [
                        'proximity',
                    ],
                    'confidence' => 'high',
                    'score' => 0.585,
                ],
            ],
            'nearest_station' => [
                'slug' => 'millas',
                'name' => 'Millas',
                'distance_m' => 924,
            ],
            'trains' => [
                [
                    'label' => null,
                    'number' => null,
                    'category' => null,
                    'material' => 'SNCF Z 7300',
                    'operator' => null,
                ],
                [
                    'label' => 'TER',
                    'number' => null,
                    'category' => 'passenger',
                    'material' => null,
                    'operator' => null,
                ],
                [
                    'label' => null,
                    'number' => null,
                    'category' => null,
                    'material' => null,
                    'operator' => 'Autocars Faur, SNCF',
                ],
            ],
            'toll_source_url' => 'https://www.bea-tt.developpement-durable.gouv.fr/millas-66-14-12-2017-collision-entre-un-train-de-r264.html',
            'wikipedia' => [
                'de' => [
                    'url' => 'https://de.wikipedia.org/wiki/Busunfall_von_Millas',
                    'revid' => null,
                    'title' => 'Busunfall von Millas',
                ],
                'en' => [
                    'url' => 'https://en.wikipedia.org/wiki/Millas_train-bus_collision',
                    'revid' => null,
                    'title' => 'Millas train-bus collision',
                ],
                'fr' => [
                    'url' => 'https://fr.wikipedia.org/wiki/Accident_de_Millas',
                    'revid' => 241196701,
                    'title' => 'Accident de Millas',
                ],
            ],
            'sources' => [
                [
                    'provider' => 'bea-tt',
                    'role' => 'report',
                    'title' => 'Collision entre un TER et un autocar scolaire sur le PN 25 à Millas (66)',
                    'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/millas-66-14-12-2017-collision-entre-un-train-de-r264.html',
                    'license' => 'Licence Ouverte 2.0 (Etalab)',
                    'license_url' => 'https://www.etalab.gouv.fr/licence-ouverte-open-licence/',
                    'license_status' => 'verified',
                    'revision' => null,
                    'retrieved_at' => '2026-11-19T01:47:15+01:00',
                    'facts' => [
                        'date' => '2017-12-14',
                        'mode' => 'PN',
                        'deaths' => 6,
                        'inventory' => 'https://www.bea-tt.developpement-durable.gouv.fr/IMG/pdf/20261109_bilan-general-enquetes-beatt.pdf',
                    ],
                ],
                [
                    'provider' => 'wikidata',
                    'role' => 'data',
                    'title' => 'Accident de Millas',
                    'url' => 'https://www.wikidata.org/wiki/Q45757847',
                    'license' => 'CC0 1.0',
                    'license_url' => 'https://creativecommons.org/publicdomain/zero/1.0/',
                    'license_status' => 'verified',
                    'revision' => 2520152318,
                    'retrieved_at' => '2026-11-19T01:47:15+01:00',
                    'facts' => [
                        'lat' => 42.685277777778,
                        'lng' => 2.7038888888889,
                        'date' => '2017-12-14',
                        'deaths' => 6,
                        'classes' => [
                            'Q19710423',
                            'Q24871403',
                            'Q1078765',
                        ],
                        'injuries' => 14,
                        'commune_insee' => '66108',
                        'date_precision' => 'day',
                    ],
                ],
                [
                    'provider' => 'wikipedia',
                    'role' => 'article',
                    'title' => 'Accident de Millas',
                    'url' => 'https://fr.wikipedia.org/w/index.php?title=Accident_de_Millas&oldid=241196701',
                    'license' => 'CC BY-SA 4.0',
                    'license_url' => 'https://creativecommons.org/licenses/by-sa/4.0/deed.fr',
                    'license_status' => 'verified',
                    'revision' => 241196701,
                    'retrieved_at' => '2026-11-19T01:47:15+01:00',
                    'facts' => [
                        'lat' => 42.68562,
                        'lng' => 2.70443,
                        'date' => '2017-12-14',
                        'type' => 'Collision à un passage à niveau (PN)',
                        'deaths' => 6,
                        'trains' => [
                            'Autocar Irisbus Récréo',
                            'Rame automotrice',
                        ],
                        'injuries' => 18,
                        'operators' => [
                            'Autocars Faur',
                            'SNCF',
                        ],
                        'department' => '66',
                        'date_precision' => 'day',
                    ],
                ],
            ],
            'review' => [
                'status' => 'to_review',
                'notes' => [
                    [
                        'code' => 'toll_mismatch',
                        'label' => 'Les sources ne donnent pas le même bilan.',
                        'detail' => 'blessés : Wikidata 14, Wikipédia 18',
                    ],
                ],
            ],
            'provenance' => [
                'date' => 'wikidata',
                'kind' => 'wikidata',
                'name' => 'wikidata',
                'deaths' => 'bea-tt',
                'injuries' => 'wikidata',
                'location' => 'wikidata',
            ],
            'imported_at' => '2026-11-19T01:47:15+01:00',
        ],
    ];

    /** 422 : nature inconnue et bornes d'années inversées. */
    public const INVALID = [
        'message' => 'The to field must be greater than or equal to 1990. (and 1 more error)',
        'errors' => [
            'to' => [
                'The to field must be greater than or equal to 1990.',
            ],
            'kind' => [
                'The selected kind is invalid.',
            ],
        ],
    ];

    /** 404 : slug inconnu. */
    public const NOT_FOUND = ['message' => 'No query results for model [App\\Models\\Accident].'];

    /** GeoJSON de la carte : un accident précis et un accident connu à la commune près (vraies fiches). */
    public const MAP = [
        'type' => 'FeatureCollection',
        'features' => [
            [
                'type' => 'Feature',
                'geometry' => ['type' => 'Point', 'coordinates' => [2.30222, 48.60722]],
                'properties' => [
                    'slug' => '2013-07-12-bretigny-sur-orge', 'date' => '2013-07-12', 'date_precision' => 'day', 'year' => 2013,
                    'kind' => 'derailment', 'place' => 'Brétigny-sur-Orge', 'department' => '91', 'precision' => 'exact',
                    'deaths' => 7, 'injuries' => 192, 'to_review' => true,
                ],
            ],
        ],
    ];

    /** 422 de la carte : année hors bornes. */
    public const MAP_INVALID = [
        'message' => 'The to field must not be greater than 2100.',
        'errors' => ['to' => ['The to field must not be greater than 2100.']],
    ];
}
