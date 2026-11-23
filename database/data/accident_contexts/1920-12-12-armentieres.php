<?php

// Contexte sourcé de l'accident d'Armentières du 12 décembre 1920 (recherche du 21/11/2026).
return [
    'slug' => '1920-12-12-armentieres',
    'checked_on' => '2026-11-23',
    'sources' => [
        'wp-liste-1920' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1920',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'Décembre, 12 décembre 1920',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1920&oldid=236476563',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée détaillée, appuyée sur Le Petit Parisien des 13 et 14 décembre 1920 (Gallica, non lus).',
        ],
        'gallica-petit-parisien-1920-12-13' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Petit Parisien du 13 décembre 1920, page 1',
            'publisher' => 'Le Petit Parisien',
            'author' => null,
            'published' => '1920-12-13',
            'locator' => 'page 1',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k6045017.image.langFR.r=tamponne',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Compte rendu contemporain cité par la liste de Wikipédia ; non lu (Gallica).',
        ],
        'kb-1920-12-13-leeuwarder-courant' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Spoorwegongeluk.',
            'publisher' => 'Leeuwarder Courant (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1920-12-13',
            'locator' => 'article « Spoorwegongeluk. », texte OCR',
            'url' => 'http://resolver.kb.nl/resolve?urn=ddd:010602059:mpeg21:a0053:ocr',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Dépêches Havas-Reuter d\'Armentières des 12 et 13 décembre : un train de voyageurs heurte un train de marchandises venant de Lille ; une dizaine de morts, puis onze morts et une trentaine de blessés.',
        ],
        'kb-1920-12-13-nieuwsblad-van-het-noorden' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Telegrafische berichten. Frankrijk.',
            'publisher' => 'Nieuwsblad van het Noorden (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1920-12-13',
            'locator' => 'article « Telegrafische berichten. Frankrijk. », texte OCR',
            'url' => 'http://resolver.kb.nl/resolve?urn=ddd:010668219:mpeg21:a0111:ocr',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Dépêche du 12 décembre : l\'accident de ce dimanche matin près d\'Armentières, le train de voyageurs 2102 venant de Berguette vers Lille heurte un autre train à la Chapelle-d\'Armentières.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 12 décembre 1920, peu après 9 h, à la sortie de la gare d\'Armentières (Nord), sur la ligne de Calais à Lille, dans une tourmente de neige, un train de voyageurs venant de Saint-Omer et allant vers Lille circulait vers une bifurcation.',
                'en' => 'On 12 December 1920, shortly after 9 am, just outside Armentières station (Nord), on the Calais to Lille line, in a snowstorm, a passenger train from Saint-Omer bound for Lille was approaching a junction.',
                'de' => 'Am 12. Dezember 1920, kurz nach 9 Uhr, fuhr am Ausgang des Bahnhofs Armentières (Nord) an der Strecke Calais - Lille bei Schneetreiben ein Personenzug aus Saint-Omer in Richtung Lille auf eine Abzweigung zu.',
                'sources' => ['wp-liste-1920'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Un train de charbon venant de Lille a pris le train de voyageurs en écharpe à la bifurcation. Son mécanicien n\'avait pas vu le signal d\'arrêt. Le tender et les deux voitures de tête du train de voyageurs, bondées, ont été écrasés.',
                'en' => 'A coal train from Lille struck the passenger train a glancing blow at the junction. Its driver had not seen the stop signal. The tender and the two crowded leading coaches of the passenger train were crushed.',
                'de' => 'Ein Kohlenzug aus Lille streifte den Personenzug an der Abzweigung seitlich. Sein Lokführer hatte das Haltsignal nicht gesehen. Der Tender und die beiden überfüllten vorderen Wagen des Personenzugs wurden zerquetscht.',
                'sources' => ['wp-liste-1920'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Deux journaux néerlandais du 13 décembre 1920 confirment l\'accident du dimanche matin (12 décembre) près d\'Armentières, à la Chapelle-d\'Armentières, entre un train de voyageurs et un train de marchandises venant de Lille, avec une dizaine de morts puis onze morts et une trentaine de blessés selon les nouvelles dépêches, ce qui confirme la date, le lieu et le bilan de 11 morts de la fiche.',
                'en' => 'Two Dutch newspapers of 13 December 1920 confirm the accident of Sunday morning (12 December) near Armentières, at La Chapelle-d\'Armentières, between a passenger train and a goods train coming from Lille, with about ten dead then eleven dead and about thirty injured according to later dispatches, which confirms the date, the place and the toll of 11 dead in the record.',
                'de' => 'Zwei niederländische Zeitungen vom 13. Dezember 1920 bestätigen das Unglück vom Sonntagmorgen (12. Dezember) bei Armentières, in La Chapelle-d\'Armentières, zwischen einem Reisezug und einem aus Lille kommenden Güterzug, mit etwa zehn Toten, dann elf Toten und etwa dreißig Verletzten nach späteren Meldungen, was Datum, Ort und die Bilanz von 11 Toten der Datenbank bestätigt.',
                'sources' => ['kb-1920-12-13-leeuwarder-courant', 'kb-1920-12-13-nieuwsblad-van-het-noorden'],
            ],
            [
                'fr' => 'La liste de Wikipédia compte onze morts et une trentaine de blessés, d\'après Le Petit Parisien des 13 et 14 décembre 1920.',
                'en' => 'The Wikipedia list counts eleven dead and about thirty injured, based on Le Petit Parisien of 13 and 14 December 1920.',
                'de' => 'Die Wikipedia-Liste zählt elf Tote und etwa dreißig Verletzte nach Le Petit Parisien vom 13. und 14. Dezember 1920.',
                'sources' => ['wp-liste-1920'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'La cause retenue par la liste est le franchissement d\'un signal d\'arrêt par le mécanicien du train de charbon, dans la neige. Aucun rapport d\'enquête n\'a été trouvé.',
                'en' => 'The cause given by the list is the coal train driver passing a stop signal in the snow. No inquiry report was found.',
                'de' => 'Als Ursache nennt die Liste das Überfahren eines Haltsignals durch den Lokführer des Kohlenzugs im Schnee. Ein Untersuchungsbericht wurde nicht gefunden.',
                'sources' => ['wp-liste-1920'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'La fiche ne donne pas de nombre de blessés ; la liste de Wikipédia en indique une trentaine. Cette valeur, approximative et issue d\'une seule source lue, n\'est pas portée en correction. Aucune seconde source n\'a été trouvée pour le bilan.',
                'en' => 'The record gives no injury count; the Wikipedia list indicates about thirty. This approximate figure, from a single source read, is not entered as a correction. No second source was found for the toll.',
                'de' => 'Der Datensatz nennt keine Verletztenzahl; die Wikipedia-Liste gibt etwa dreißig an. Dieser ungefähre Wert aus einer einzigen gelesenen Quelle wird nicht als Korrektur übernommen. Für die Opferzahl wurde keine zweite Quelle gefunden.',
                'sources' => ['wp-liste-1920'],
            ],
        ],
    ],
    'corrections' => [],
];
