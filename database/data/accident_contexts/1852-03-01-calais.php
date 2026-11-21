<?php

// Contexte sourcé de l'accident de passage à niveau de Saint-Pierre-lès-Calais du 1er mars 1852 (recherche du 21/11/2026).
return [
    'slug' => '1852-03-01-calais',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1850' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1850',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1852',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1850&oldid=233367195#1852',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée de la liste, d\'après La Presse du 8 mars 1852 : sur la ligne de Lille à Calais de la Compagnie du Nord, dans les faubourgs de Calais, près de la gare de Saint-Pierre-lès-Calais, une carriole s\'engage à 9 h 30 sur un passage à niveau resté ouvert à l\'arrivée d\'une locomotive ; la conductrice est tuée, son père grièvement blessé, le cheval indemne ; le garde-barrière est inculpé d\'homicide par imprudence.',
        ],
        'nrc-1852-03-08' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Fransche Post (Nieuwe Rotterdamsche courant du 8 mars 1852)',
            'publisher' => 'Nieuwe Rotterdamsche courant',
            'author' => null,
            'published' => '1852-03-08',
            'locator' => 'p. 1',
            'url' => 'https://resolver.kb.nl/resolve?urn=ddd:000016946:mpeg21:a0003',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Presse néerlandaise numérisée par la Koninklijke Bibliotheek (Delpher). Nouvelles de France : une voiture avec un homme et une femme traversait la voie quand arrive à l\'improviste une locomotive venant de Calais ; la voiture est brisée, la femme tuée, l\'homme grièvement blessé.',
        ],
        'handelsblad-1852-03-08' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Fransche Post. Parijs, Donderdag 4 Maart (Algemeen Handelsblad du 8 mars 1852)',
            'publisher' => 'Algemeen Handelsblad',
            'author' => null,
            'published' => '1852-03-08',
            'locator' => 'p. 1',
            'url' => 'https://resolver.kb.nl/resolve?urn=ddd:010073267:mpeg21:a0004',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Presse néerlandaise numérisée par la Koninklijke Bibliotheek (Delpher). Nouvelles de Paris du 4 mars : sur le chemin de fer de Calais, un tilbury traverse la voie à l\'approche d\'un train ; le cheval, effrayé par le sifflet de la locomotive, refuse d\'avancer ; le train arrive à pleine vitesse, brise la voiture, écrase le cheval, tue la femme assise dans la voiture ; l\'homme est grièvement blessé.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 1er mars 1852, vers 9 h 30, une voiture légère traversait la ligne de Lille à Calais par un passage à niveau des faubourgs de Calais, près de la gare de Saint-Pierre-lès-Calais. La barrière était restée ouverte.',
                'en' => 'On 1 March 1852, at about 9.30 am, a light carriage was crossing the Lille to Calais line at a level crossing in the outskirts of Calais, near Saint-Pierre-lès-Calais station. The gate had been left open.',
                'de' => 'Am 1. März 1852 gegen 9.30 Uhr überquerte ein leichter Wagen in den Vororten von Calais, nahe dem Bahnhof Saint-Pierre-lès-Calais, die Strecke Lille-Calais an einem Bahnübergang. Die Schranke war offen geblieben.',
                'sources' => ['wp-liste-1850', 'handelsblad-1852-03-08'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Une locomotive venant de Calais a heurté la voiture et l\'a brisée.',
                'en' => 'A locomotive coming from Calais struck the carriage and smashed it.',
                'de' => 'Eine aus Calais kommende Lokomotive erfasste den Wagen und zertrümmerte ihn.',
                'sources' => ['nrc-1852-03-08', 'handelsblad-1852-03-08', 'wp-liste-1850'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'La femme qui se trouvait dans la voiture a été tuée et l\'homme qui l\'accompagnait grièvement blessé ; la liste de Wikipédia précise qu\'il s\'agit d\'une fille et de son père.',
                'en' => 'The woman in the carriage was killed and the man with her seriously injured; the Wikipedia list states that they were a daughter and her father.',
                'de' => 'Die Frau im Wagen wurde getötet, der Mann, der sie begleitete, schwer verletzt; nach der Wikipedia-Liste handelte es sich um eine Tochter und ihren Vater.',
                'sources' => ['nrc-1852-03-08', 'handelsblad-1852-03-08', 'wp-liste-1850'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le garde-barrière, un militaire en retraite, a été inculpé d\'homicide par imprudence.',
                'en' => 'The crossing keeper, a retired soldier, was charged with manslaughter through negligence.',
                'de' => 'Der Schrankenwärter, ein ehemaliger Soldat im Ruhestand, wurde wegen fahrlässiger Tötung angeklagt.',
                'sources' => ['wp-liste-1850'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le sort du cheval diffère : indemne selon la liste de Wikipédia, écrasé selon l\'Algemeen Handelsblad, qui ajoute qu\'il avait refusé d\'avancer, effrayé par le sifflet de la locomotive.',
                'en' => 'The fate of the horse differs: unhurt according to the Wikipedia list, crushed according to the Algemeen Handelsblad, which adds that it had refused to move, frightened by the locomotive\'s whistle.',
                'de' => 'Das Schicksal des Pferdes weicht ab: unverletzt nach der Wikipedia-Liste, überfahren nach dem Algemeen Handelsblad, das hinzufügt, es habe sich, vom Pfiff der Lokomotive erschreckt, nicht von der Stelle bewegt.',
                'sources' => ['wp-liste-1850', 'handelsblad-1852-03-08'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 1,
            'sources' => ['nrc-1852-03-08', 'wp-liste-1850'],
            'fr' => 'Une morte, la femme assise dans la voiture, selon la liste de Wikipédia et la presse néerlandaise du 8 mars 1852 ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'One dead, the woman in the carriage, according to the Wikipedia list and the Dutch press of 8 March 1852; the record gave no number of deaths.',
            'de' => 'Eine Tote, die Frau im Wagen, nach der Wikipedia-Liste und der niederländischen Presse vom 8. März 1852; der Eintrag nannte keine Zahl von Toten.',
        ],
        [
            'field' => 'injuries',
            'value' => 1,
            'sources' => ['nrc-1852-03-08', 'handelsblad-1852-03-08', 'wp-liste-1850'],
            'fr' => 'Un blessé grave, l\'homme qui accompagnait la victime, selon la liste de Wikipédia et deux journaux néerlandais du 8 mars 1852 ; la fiche ne donnait pas de nombre de blessés.',
            'en' => 'One seriously injured, the man accompanying the victim, according to the Wikipedia list and two Dutch newspapers of 8 March 1852; the record gave no number of injured.',
            'de' => 'Ein Schwerverletzter, der Begleiter des Opfers, nach der Wikipedia-Liste und zwei niederländischen Zeitungen vom 8. März 1852; der Eintrag nannte keine Zahl von Verletzten.',
        ],
    ],
];
