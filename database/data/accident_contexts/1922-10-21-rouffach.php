<?php

// Contexte sourcé de l'accident de Rouffach du 21 octobre 1922 (recherche du 21/11/2026).
return [
    'slug' => '1922-10-21-rouffach',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1922' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1922',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'Octobre, Rouffach',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1922&oldid=236708402#Octobre',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée sourcée par Le Matin du 22/10/1922, p. 1 (Gallica, non lu) ; donne 0 h 20, 13 morts, 12 blessés, nombreux militaires en permission.',
        ],
        'lm-1922-10-22' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Matin, 22 octobre 1922, p. 1',
            'publisher' => 'Le Matin',
            'author' => null,
            'published' => '1922-10-22',
            'locator' => 'p. 1',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k574128t.langFR',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Référence citée par la liste de Wikipédia ; non lue (droits).',
        ],
        'rg-rouffach-1922' => [
            'provider' => 'sites-web',
            'kind' => 'scholarly',
            'reliability' => 'local_history',
            'title' => 'La catastrophe ferroviaire de Rouffach en 1922',
            'publisher' => 'ResearchGate',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://www.researchgate.net/publication/356919246_la_catastrophe_ferroviaire_de_Rouffach_en_1922',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Article détaillé repéré (témoignages, enquête, poste d\'aiguillage) ; accès refusé (403), non lu. Un résumé de moteur de recherche évoque minuit cinq, à vérifier.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Dans la nuit du 20 au 21 octobre 1922, vers 0 h 20 selon la liste de Wikipédia, en gare de Rouffach (Haut-Rhin), sur la ligne de Strasbourg à Bâle, un rapide Strasbourg - Lyon ou Bordeaux a été aiguillé par erreur sur la voie où stationnait un train de marchandises, et l\'a heurté.',
                'en' => 'In the night of 20 to 21 October 1922, at about 0.20 am according to the Wikipedia list, at Rouffach station (Haut-Rhin) on the Strasbourg to Basel line, an express from Strasbourg to Lyon or Bordeaux was wrongly routed onto the track where a goods train was standing, and collided with it.',
                'de' => 'In der Nacht vom 20. auf den 21. Oktober 1922, gegen 0.20 Uhr laut der Wikipedia-Liste, wurde im Bahnhof Rouffach (Haut-Rhin) an der Strecke Straßburg-Basel ein Schnellzug von Straßburg nach Lyon oder Bordeaux irrtümlich auf das Gleis geleitet, auf dem ein Güterzug stand, und stieß mit ihm zusammen.',
                'sources' => ['wp-liste-1922'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'La liste de Wikipédia compte treize morts et douze blessés, parmi lesquels de nombreux militaires en permission.',
                'en' => 'The Wikipedia list gives thirteen dead and twelve injured, among them many soldiers on leave.',
                'de' => 'Die Wikipedia-Liste nennt dreizehn Tote und zwölf Verletzte, darunter viele Soldaten auf Urlaub.',
                'sources' => ['wp-liste-1922'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Une seule source lue (la liste de Wikipédia, elle-même fondée sur un seul journal) documente cet accident : l\'enquête, les responsabilités et les suites restent à établir. La nuit du 20 au 21 octobre explique la date retenue (21 octobre) ; l\'heure exacte n\'est pas confirmée.',
                'en' => 'Only one source was read (the Wikipedia list, itself based on a single newspaper): the inquiry, responsibilities and aftermath remain to be established. The night of 20 to 21 October explains the date used (21 October); the exact time is not confirmed.',
                'de' => 'Nur eine Quelle wurde gelesen (die Wikipedia-Liste, die selbst auf einer einzigen Zeitung beruht): Untersuchung, Verantwortlichkeiten und Folgen sind noch zu klären. Die Nacht vom 20. auf den 21. Oktober erklärt das verwendete Datum (21. Oktober); die genaue Uhrzeit ist nicht bestätigt.',
                'sources' => ['wp-liste-1922'],
            ],
        ],
    ],
    'corrections' => [],
];
