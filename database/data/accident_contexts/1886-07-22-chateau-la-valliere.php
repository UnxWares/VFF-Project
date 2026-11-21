<?php

// Contexte sourcé du déraillement d'un train sur la ligne de Saumur à Château-du-Loir, ouverte une semaine plus tôt, de juillet 1886 (recherche du 21/11/2026).
return [
    'slug' => '1886-07-22-chateau-la-valliere',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1880' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1880',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1886',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1880&oldid=236846064#1886',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 22 juillet 1886, d\'après Le Temps du 23 juillet : vers 5 h, sur la ligne de Saumur à Château-du-Loir, ouverte une semaine auparavant, un train mixte venant de Paris déraille entre Chenu et Château-la-Vallière ; le chauffeur et le chef de train sont tués, le mécanicien et un voyageur blessés.',
        ],
        'delpher-1886-07-24-zuidholland' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Residentienieuws (Vervolg)',
            'publisher' => 'Dagblad van Zuidholland en \'s Gravenhage (presse néerlandaise numérisée, Delpher)',
            'author' => null,
            'published' => '1886-07-24',
            'locator' => null,
            'url' => 'https://resolver.kb.nl/resolve?urn=MMKB19:003602150:mpeg21:a00044',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Brève : sur la ligne de Saumur à Château-du-Loir, ouverte au trafic le 11 juillet, un train de marchandises roulant à 45 km/h a déraillé le matin, à un endroit où deux express étaient passés sans incident quelques heures plus tôt à 60 km/h ; le chauffeur et le chef de train tués, un autre agent blessé, un voyageur contusionné.',
        ],
        'delpher-1886-07-25-handelsblad' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Allerlei',
            'publisher' => 'Algemeen Handelsblad (presse néerlandaise numérisée, Delpher)',
            'author' => null,
            'published' => '1886-07-25',
            'locator' => null,
            'url' => 'https://resolver.kb.nl/resolve?urn=KBDDD02:000207365:mpeg21:a0012',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Brève : jeudi dernier, sur la ligne nouvellement ouverte de Saumur à Château-du-Loir, le train a déraillé près d\'un lieu que l\'OCR donne « Chenebutte » ; le chauffeur tué sur place, le conducteur mort peu après l\'amputation d\'une jambe, un voyageur grièvement blessé.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'La ligne de Saumur à Château-du-Loir venait d\'être ouverte au trafic, le 11 juillet 1886 selon la presse néerlandaise.',
                'en' => 'The Saumur to Château-du-Loir line had just opened to traffic, on 11 July 1886 according to the Dutch press.',
                'de' => 'Die Strecke Saumur-Château-du-Loir war gerade eröffnet worden, nach der niederländischen Presse am 11. Juli 1886.',
                'sources' => ['wp-liste-1880', 'delpher-1886-07-24-zuidholland'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Le jeudi 22 juillet 1886, vers 5 h, un train mixte de marchandises et de voyageurs venant de Paris a déraillé entre Chenu et Château-la-Vallière. Il roulait à 45 km/h ; deux express étaient passés au même endroit quelques heures plus tôt à 60 km/h sans incident.',
                'en' => 'On Thursday 22 July 1886, at about 5 am, a mixed goods and passenger train from Paris derailed between Chenu and Château-la-Vallière. It was running at 45 km/h; two expresses had passed the same spot a few hours earlier at 60 km/h without trouble.',
                'de' => 'Am Donnerstag, dem 22. Juli 1886, gegen 5 Uhr entgleiste ein gemischter Güter- und Personenzug aus Paris zwischen Chenu und Château-la-Vallière. Er fuhr 45 km/h; zwei Expresszüge hatten die Stelle einige Stunden vorher mit 60 km/h ohne Zwischenfall befahren.',
                'sources' => ['wp-liste-1880', 'delpher-1886-07-24-zuidholland'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le chauffeur a été tué sur le coup et le chef de train est mort peu après, une jambe amputée selon l\'Algemeen Handelsblad. Un autre agent, le mécanicien selon la liste de Wikipédia, et un voyageur ont été blessés.',
                'en' => 'The stoker was killed outright and the guard died soon afterwards, after a leg was amputated according to the Algemeen Handelsblad. Another railwayman, the driver according to the Wikipedia list, and one passenger were injured.',
                'de' => 'Der Heizer war sofort tot, der Zugführer starb kurz darauf, nach dem Algemeen Handelsblad nach der Amputation eines Beins. Ein weiterer Bediensteter, nach der Wikipedia-Liste der Lokführer, und ein Reisender wurden verletzt.',
                'sources' => ['wp-liste-1880', 'delpher-1886-07-24-zuidholland', 'delpher-1886-07-25-handelsblad'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'La liste de Wikipédia parle d\'un train mixte et place le déraillement entre Chenu et Château-la-Vallière ; le Dagblad van Zuidholland parle d\'un train de marchandises, l\'Algemeen Handelsblad d\'un lieu proche de Saumur que l\'OCR donne « Chenebutte ».',
                'en' => 'The Wikipedia list speaks of a mixed train and places the derailment between Chenu and Château-la-Vallière; the Dagblad van Zuidholland speaks of a goods train, the Algemeen Handelsblad of a place near Saumur that the OCR gives as "Chenebutte".',
                'de' => 'Die Wikipedia-Liste spricht von einem gemischten Zug und verortet die Entgleisung zwischen Chenu und Château-la-Vallière; das Dagblad van Zuidholland spricht von einem Güterzug, das Algemeen Handelsblad von einem Ort bei Saumur, den die Texterkennung als „Chenebutte“ wiedergibt.',
                'sources' => ['wp-liste-1880', 'delpher-1886-07-24-zuidholland', 'delpher-1886-07-25-handelsblad'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 2,
            'sources' => ['wp-liste-1880', 'delpher-1886-07-24-zuidholland', 'delpher-1886-07-25-handelsblad'],
            'fr' => 'Chauffeur et chef de train tués selon la liste de Wikipédia (Le Temps du 23 juillet 1886) et la presse néerlandaise ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'Stoker and guard killed according to the Wikipedia list (Le Temps of 23 July 1886) and the Dutch press; the record gave no number of deaths.',
            'de' => 'Heizer und Zugführer getötet nach der Wikipedia-Liste (Le Temps vom 23. Juli 1886) und der niederländischen Presse; der Eintrag nannte keine Zahl von Toten.',
        ],
        [
            'field' => 'injuries',
            'value' => 2,
            'sources' => ['wp-liste-1880', 'delpher-1886-07-24-zuidholland'],
            'fr' => 'Deux blessés, un agent et un voyageur, selon la liste de Wikipédia et le Dagblad van Zuidholland ; la fiche ne donnait pas de nombre de blessés.',
            'en' => 'Two injured, a railwayman and a passenger, according to the Wikipedia list and the Dagblad van Zuidholland; the record gave no number of injured.',
            'de' => 'Zwei Verletzte, ein Bediensteter und ein Reisender, nach der Wikipedia-Liste und dem Dagblad van Zuidholland; der Eintrag nannte keine Zahl von Verletzten.',
        ],
    ],
];
