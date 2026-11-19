<?php

namespace App\Http\Controllers\Api;

/**
 * Exemple de fiche de gare pour la documentation OpenAPI (GET /api/v1/stations/brest).
 * Réponse réelle de la base, listes raccourcies à un ou deux éléments et textes longs coupés.
 */
final class StationSheetExample
{
    public const BODY = [
        'data' => [
            'slug' => 'brest',
            'name' => 'Brest',
            'alt_names' => [],
            'type' => 'gare',
            'status' => 'active',
            'source_status' => 'active',
            'lifecycle' => 'active',
            'city' => 'Brest',
            'department_code' => '29',
            'opened_at' => '1865-04-26',
            'opened_precision' => 'day',
            'closed_at' => null,
            'closed_precision' => null,
            'closed_after' => null,
            'uic_code' => '87474007',
            'wikidata_id' => 'Q2255490',
            'description' => null,
            'lat' => 48.3876941,
            'lng' => -4.4800927,
            'position' => ['source' => 'osm', 'spread_m' => 216, 'flagged' => true],
            'attribute_sources' => [
                'geom' => 'osm',
                'name' => 'sncf',
                'type' => 'osm',
                'status' => 'sncf',
                'uic_code' => 'sncf',
                'opened_at' => 'wikidata',
                'wikidata_id' => 'wikidata',
                'geom_recalage' => [
                    'osm' => 'n2207570062',
                    'from' => [-4.4776283],
                    'mode' => 'concordance',
                    'agree' => 'wikidata',
                    'moved_m' => 193,
                    'from_source' => 'sncf',
                ],
            ],
            'eras' => [['year' => 1830, 'is_current' => false, 'status' => null, 'note' => null, 'undated' => false]],
            'events' => [
                [
                    'type' => 'opening',
                    'date' => '1865-04-26',
                    'precision' => 'day',
                    'source' => 'wikidata',
                    'source_url' => 'https://www.wikidata.org/wiki/Q2255490',
                    'inferred' => false,
                    'note' => null,
                    'provenance' => [
                        'provider' => 'wikidata',
                        'name' => 'Wikidata',
                        'license' => 'CC0 1.0',
                        'license_status' => 'verified',
                    ],
                ],
            ],
            'lines' => [
                [
                    'slug' => 'rfn-420000',
                    'code' => '420000',
                    'name' => 'Ligne de Paris-Montparnasse à Brest',
                    'network' => 'principal',
                    'method' => 'sncf_pk',
                    'pk_m' => 622422,
                    'pk_source' => 'sncf',
                    'first_year' => 1865,
                    'last_year' => null,
                    'never_served' => null,
                    'active_current' => true,
                    'era_years' => [1870],
                ],
            ],
            'sources' => [
                [
                    'source' => 'sncf',
                    'ref' => '87474007',
                    'name' => 'Brest',
                    'lifecycle' => 'active',
                    'uic_code' => '87474007',
                    'opened_on' => null,
                    'closed_on' => null,
                    'match_method' => 'seed',
                    'distance_m' => 193,
                    'passenger' => true,
                    'freight' => true,
                    'pk_m' => null,
                    'geocoding' => null,
                    'precision_m' => null,
                    'url' => 'https://ressources.data.sncf.com/explore/dataset/liste-des-gares/table/?q=87474007',
                    'provenance' => [
                        'provider' => 'sncf',
                        'name' => 'SNCF Open Data (SNCF Réseau, Gares & Connexions, GTFS voyageurs)',
                        'license' => 'ODbL 1.0 (chaque jeu utilisé : champ metas.default.license de l\'API)',
                        'license_status' => 'verified',
                    ],
                ],
            ],
            'neighbors' => [
                [
                    'slug' => 'le-rody',
                    'name' => 'Le Rody',
                    'type' => 'gare',
                    'city' => 'Le Relecq-Kerhuon',
                    'status' => 'declassified',
                    'lng' => -4.4211292,
                    'lat' => 48.398713,
                    'distance_m' => 4535,
                ],
            ],
            'links' => [
                'web' => 'https://vff-project.org/stations/brest',
                'events' => 'https://vff-project.org/api/v1/stations/brest/events',
                'media' => 'https://vff-project.org/api/v1/media?station=brest',
            ],
        ],
    ];
}
