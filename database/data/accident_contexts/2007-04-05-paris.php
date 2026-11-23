<?php

// Contexte sourcé de de l'accostage brutal d'un train Transilien en gare de Paris-Est du 5 avril 2007 (recherche du 23/11/2026).
return [
    'slug' => '2007-04-05-paris',
    'checked_on' => '2026-11-23',
    'sources' => [
        'bea-tt-2007-04-05-paris' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Choc d\'un train contre un heurtoir en gare de Paris-Est',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'page de présentation de l\'enquête',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/paris-est-75-5-04-2007-accostage-brutal-d-un-train-r69.html',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Présentation de l\'enquête du BEA-TT : localisation, faits, ouverture ou conclusions de l\'enquête.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 5 avril 2007, un train Transilien de Château-Thierry à Paris a percuté à faible vitesse le heurtoir de la voie 21 de la gare de Paris-Est. Il circulait sur son étape finale de Meaux à Paris, exceptionnellement chargé, très au-delà de 2 200 voyageurs, à cause de perturbations sur des trains précédents. Le bilan est de 58 blessés légers et les dégâts matériels sont limités.',
                'en' => 'On 5 April 2007 a Transilien train from Château-Thierry to Paris hit at low speed the buffer stop of track 21 at Paris-Est station. It was on its final leg from Meaux to Paris, exceptionally loaded, far beyond 2,200 passengers, because of disruption to earlier trains. The toll is 58 slightly injured and the material damage is limited.',
                'de' => 'Am 5. April 2007 prallte ein Transilien von Château-Thierry nach Paris mit niedriger Geschwindigkeit gegen den Prellbock von Gleis 21 im Bahnhof Paris-Est. Er befand sich auf seiner letzten Etappe von Meaux nach Paris, außergewöhnlich stark besetzt, weit über 2 200 Fahrgäste, wegen Störungen vorausgefahrener Züge. Die Bilanz lautet 58 Leichtverletzte, und die Sachschäden sind begrenzt.',
                'sources' => ['bea-tt-2007-04-05-paris'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT a publié son rapport final le 13 février 2008, puis un amendement de son chapitre 3 (éléments de contexte) par un expert en freinage le 2 mars 2009, avec les recommandations et les réponses de leurs destinataires.',
                'en' => 'The BEA-TT published its final report on 13 February 2008, then an amendment of its chapter 3 (context) by a braking expert on 2 March 2009, with the recommendations and the replies of their recipients.',
                'de' => 'Die BEA-TT veröffentlichte ihren Abschlussbericht am 13. Februar 2008, dann am 2. März 2009 eine Änderung ihres Kapitels 3 (Kontext) durch einen Bremsexperten, mit den Empfehlungen und den Antworten ihrer Adressaten.',
                'sources' => ['bea-tt-2007-04-05-paris'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'injuries',
            'value' => 58,
            'sources' => ['bea-tt-2007-04-05-paris'],
            'fr' => 'Le BEA-TT compte 58 blessés légers.',
            'en' => 'The BEA-TT counts 58 slightly injured.',
            'de' => 'Die BEA-TT zählt 58 Leichtverletzte.',
        ],
    ],
];
