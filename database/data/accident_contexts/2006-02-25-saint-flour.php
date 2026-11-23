<?php

// Contexte sourcé de du déraillement d'un train Corail à Saint-Flour (Cantal) du 25 février 2006 (recherche du 23/11/2026).
return [
    'slug' => '2006-02-25-saint-flour',
    'checked_on' => '2026-11-23',
    'sources' => [
        'bea-tt-2006-02-25-saint-flour' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Déraillement train corail à Saint-Flour',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'page de présentation de l\'enquête',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/saint-flour-15-25-02-2006-deraillement-d-un-train-r55.html',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Présentation de l\'enquête du BEA-TT : localisation, faits, ouverture ou conclusions de l\'enquête.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 25 février 2006, un train Corail de Paris à Béziers (train n° 5941) a déraillé dans une courbe. La locomotive et la première voiture ont été projetées contre une paroi rocheuse. Le bilan est de deux blessés légers et de dégâts importants sur le matériel roulant et l\'infrastructure.',
                'en' => 'On 25 February 2006 a Corail train from Paris to Béziers (train no. 5941) derailed on a curve. The locomotive and the first coach were thrown against a rock wall. The toll is two slightly injured and heavy damage to the rolling stock and the infrastructure.',
                'de' => 'Am 25. Februar 2006 entgleiste ein Corail-Zug von Paris nach Béziers (Zug Nr. 5941) in einer Kurve. Die Lokomotive und der erste Wagen wurden gegen eine Felswand geschleudert. Die Bilanz lautet zwei Leichtverletzte und erhebliche Schäden am rollenden Material und an der Infrastruktur.',
                'sources' => ['bea-tt-2006-02-25-saint-flour'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT a publié son rapport final le 6 décembre 2006 (thèmes : déraillement, maintenance, rupture de rail), avec des recommandations adressées à la SNCF et à RFF.',
                'en' => 'The BEA-TT published its final report on 6 December 2006 (themes: derailment, maintenance, rail break), with recommendations addressed to the SNCF and RFF.',
                'de' => 'Die BEA-TT veröffentlichte ihren Abschlussbericht am 6. Dezember 2006 (Themen: Entgleisung, Instandhaltung, Schienenbruch), mit Empfehlungen an die SNCF und RFF.',
                'sources' => ['bea-tt-2006-02-25-saint-flour'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'injuries',
            'value' => 2,
            'sources' => ['bea-tt-2006-02-25-saint-flour'],
            'fr' => 'Le BEA-TT compte deux blessés légers.',
            'en' => 'The BEA-TT counts two slightly injured.',
            'de' => 'Die BEA-TT zählt zwei Leichtverletzte.',
        ],
    ],
];
