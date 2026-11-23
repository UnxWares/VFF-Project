<?php

// Contexte sourcé de du déraillement de trois wagons à Valence-d'Agen (Tarn-et-Garonne) du 20 octobre 2011 (recherche du 23/11/2026).
return [
    'slug' => '2011-10-20-valence',
    'checked_on' => '2026-11-23',
    'sources' => [
        'bea-tt-2011-10-20-valence' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Déraillement de trois wagons trémies et heurt d’un TGV croiseur à Valence d’Agen (82)',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'page de présentation de l\'enquête',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/valence-d-agen-82-20-10-2011-deraillement-de-trois-r164.html',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Présentation de l\'enquête du BEA-TT : localisation, faits, ouverture ou conclusions de l\'enquête.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 20 octobre 2011, trois wagons chargés de ballast d\'un train de la SNCF de Bordeaux à Toulouse ont déraillé. Un TGV qui passait au même moment sur la voie voisine a reçu des projections de ballast qui ont abîmé ses vitres frontales. Puis deux des wagons déraillés se sont couchés, engageant le gabarit de l\'autre voie et endommageant la motrice de queue du TGV. Il n\'y a pas eu de victime, mais la voie est gravement détériorée sur plus d\'un kilomètre.',
                'en' => 'On 20 October 2011 three ballast-laden wagons of an SNCF train from Bordeaux to Toulouse derailed. A TGV passing at the same moment on the adjacent track was hit by flying ballast that damaged its front windows. Then two of the derailed wagons fell over, fouling the clearance of the other track and damaging the rear power car of the TGV. There were no casualties, but the track is badly damaged over more than a kilometre.',
                'de' => 'Am 20. Oktober 2011 entgleisten drei mit Schotter beladene Wagen eines SNCF-Zugs von Bordeaux nach Toulouse. Ein zur gleichen Zeit auf dem Nachbargleis fahrender TGV wurde von Schotter getroffen, der seine Frontscheiben beschädigte. Dann kippten zwei der entgleisten Wagen um, ragten in das Lichtraumprofil des anderen Gleises und beschädigten den hinteren Triebkopf des TGV. Es gab keine Opfer, aber das Gleis ist auf mehr als einen Kilometer schwer beschädigt.',
                'sources' => ['bea-tt-2011-10-20-valence'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT a publié son rapport final le 17 décembre 2012, avec une recommandation adressée à la SNCF et à RFF.',
                'en' => 'The BEA-TT published its final report on 17 December 2012, with a recommendation addressed to the SNCF and RFF.',
                'de' => 'Die BEA-TT veröffentlichte ihren Abschlussbericht am 17. Dezember 2012, mit einer Empfehlung an die SNCF und RFF.',
                'sources' => ['bea-tt-2011-10-20-valence'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'injuries',
            'value' => 0,
            'sources' => ['bea-tt-2011-10-20-valence'],
            'fr' => 'Le BEA-TT indique que l\'accident ne fait pas de victimes.',
            'en' => 'The BEA-TT states that the accident caused no casualties.',
            'de' => 'Die BEA-TT gibt an, dass der Unfall keine Opfer forderte.',
        ],
    ],
];
