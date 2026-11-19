<?php

namespace App\Http\Controllers\Api;

/**
 * Exemples réels (base du 20/11/2026) des réponses des passages à niveau, pour la documentation
 * OpenAPI (attributs `ApiResponse` de LevelCrossingController).
 */
final class LevelCrossingExamples
{
    private const PROVENANCE = [
        'provider' => 'sncf',
        'name' => 'SNCF Open Data (SNCF Réseau, Gares & Connexions, GTFS voyageurs)',
        'license' => 'ODbL 1.0 (chaque jeu utilisé : champ metas.default.license de l\'API)',
        'license_status' => 'verified',
    ];

    private const LINE_REF = ['slug' => 'rfn-70000', 'code' => '070000', 'name' => 'Ligne de Noisy-le-Sec à Strasbourg-Ville'];

    public const ITEM = [
        'id' => '30000010436',
        'label' => 'PN 32',
        'class' => 17,
        'class_label' => 'Public pour voitures, barrières ou demi-barrières automatiques, signalisation lumineuse SAL 2 ou SAL 2B',
        'group' => 'automatic',
        'pk_m' => 112866,
        'pk_label' => '112+866',
        'commune' => 'Courthiézy',
        'crossed_way' => 'VC',
        'line' => self::LINE_REF,
        'line_code' => '070000',
        'lat' => 49.062227,
        'lng' => 3.602119,
        'provenance' => self::PROVENANCE,
    ];

    public const LIST = [
        'data' => [self::ITEM],
        'links' => [
            'first' => 'https://vff-project.org/api/v1/level-crossings?commune=courthiezy&per_page=1&page=1',
            'last' => 'https://vff-project.org/api/v1/level-crossings?commune=courthiezy&per_page=1&page=2',
            'prev' => 'https://vff-project.org/api/v1/level-crossings?commune=courthiezy&per_page=1&page=1',
            'next' => null,
        ],
        'meta' => [
            'current_page' => 2, 'from' => 2, 'last_page' => 2, 'per_page' => 1, 'to' => 2, 'total' => 2,
            'path' => 'https://vff-project.org/api/v1/level-crossings',
        ],
    ];

    public const LINE = [
        'line' => self::LINE_REF,
        'total' => 96,
        'summary' => [
            ['group' => 'automatic', 'count' => 85],
            ['group' => 'guarded', 'count' => 5],
            ['group' => 'no_barrier', 'count' => 1],
            ['group' => 'pedestrian', 'count' => 4],
            ['group' => 'private', 'count' => 1],
        ],
        'level_crossings' => [self::ITEM],
        'provenance' => self::PROVENANCE,
    ];

    public const INVALID = [
        'message' => 'The selected group is invalid. (and 1 more error)',
        'errors' => [
            'group' => ['The selected group is invalid.'],
            'per_page' => ['The per page field must not be greater than 200.'],
        ],
    ];
}
