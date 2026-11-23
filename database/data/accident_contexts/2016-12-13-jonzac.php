<?php

// Contexte sourcé de des ruptures multiples de rails au passage d'un train de fret à Jonzac (Charente-Maritime) du 13 décembre 2016 (recherche du 23/11/2026).
return [
    'slug' => '2016-12-13-jonzac',
    'checked_on' => '2026-11-23',
    'sources' => [
        'bea-tt-2016-12-13-jonzac' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Ruptures multiples de rail sur la ligne entre et Bordeaux (17)',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'page de présentation de l\'enquête',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/jonzac-17-13-12-2016-ruptures-multiples-de-rails-r252.html',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Présentation de l\'enquête du BEA-TT : localisation, faits, ouverture ou conclusions de l\'enquête.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 13 décembre 2016, à la suite de ratés d\'ouverture de deux passages à niveau sur la ligne de Saintes à Bordeaux, le personnel d\'intervention a découvert plusieurs rails cassés. Le train de fret qui venait de passer sur la section a été arrêté et un méplat important a été détecté sur les deux roues du premier essieu d\'un wagon. Au total, 13 ruptures de rails ont été décelées.',
                'en' => 'On 13 December 2016, after two level crossings failed to open on the Saintes to Bordeaux line, the intervention staff found several broken rails. The freight train that had just run over the section was stopped and a large flat was found on both wheels of the first axle of a wagon. In all, 13 rail breaks were found.',
                'de' => 'Am 13. Dezember 2016 entdeckten die Einsatzkräfte, nachdem sich zwei Bahnübergänge auf der Strecke Saintes - Bordeaux nicht geöffnet hatten, mehrere gebrochene Schienen. Der Güterzug, der den Abschnitt eben befahren hatte, wurde angehalten, und an beiden Rädern der ersten Achse eines Wagens wurde eine große Flachstelle festgestellt. Insgesamt wurden 13 Schienenbrüche entdeckt.',
                'sources' => ['bea-tt-2016-12-13-jonzac'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT a publié son rapport final le 30 novembre 2017 (thèmes : rupture de rail, train de fret), avec des recommandations adressées à SNCF Réseau et à ERMEWA.',
                'en' => 'The BEA-TT published its final report on 30 November 2017 (themes: rail break, freight train), with recommendations addressed to SNCF Réseau and ERMEWA.',
                'de' => 'Die BEA-TT veröffentlichte ihren Abschlussbericht am 30. November 2017 (Themen: Schienenbruch, Güterzug), mit Empfehlungen an SNCF Réseau und ERMEWA.',
                'sources' => ['bea-tt-2016-12-13-jonzac'],
            ],
        ],
    ],
];
