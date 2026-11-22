<?php

// Contexte sourcé de la collision de Campbon du 3 octobre 1911 (fiche datée du 3 novembre) (recherche du 21/11/2026).
return [
    'slug' => '1911-10-03-campbon',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1911' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1911',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => 'section Novembre',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1911&oldid=233727889#Novembre',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée de la liste : ligne de Sablé à Montoir-de-Bretagne, erreur du chef de gare de Campbon, collision frontale de deux trains de marchandises sur la section à voie unique vers Bouvron, quatre morts et deux blessés graves.',
        ],
        'gallica-petit-parisien-1911' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Petit Parisien, 5 octobre 1911, p. 1',
            'publisher' => 'Gallica (Bibliothèque nationale de France)',
            'author' => null,
            'published' => '1911-10-05',
            'locator' => 'p. 1',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k564114q.r=tamponne.langFR',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Numéro cité par la liste de Wikipédia à l\'appui de l\'entrée.',
        ],
        'delpher-1911-10-05-zutphen' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Buitenland. De Oorlog',
            'publisher' => 'Presse néerlandaise numérisée (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1911-10-05',
            'locator' => 'Zutphensche courant',
            'url' => 'http://resolver.kb.nl/resolve?urn=MMRAZ02:000395082:mpeg21:a00011',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Brève : mardi soir, sur la ligne de Châteaubriant à Saint-Nazaire, deux trains de marchandises se sont heurtés ; quatre agents du train tués.',
        ],
        'delpher-1911-10-05-vaderland' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Frankrijk. Generaal Moinier terug',
            'publisher' => 'Presse néerlandaise numérisée (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1911-10-05',
            'locator' => 'Het Vaderland',
            'url' => 'http://resolver.kb.nl/resolve?urn=MMKB23:001491011:mpeg21:a00047',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Brève de la rubrique France : la veille, entre Bouvron et Campbon (Loire-Inférieure), deux trains de marchandises se sont heurtés ; quatre agents tués, deux grièvement blessés, cause inconnue. Même texte dans la Utrechtsche courant du 6 octobre.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le mardi 3 octobre 1911 au soir, sur la ligne de Sablé à Montoir-de-Bretagne, le chef de gare de Campbon a laissé entrer par erreur un train de marchandises à destination de Châteaubriant sur la section à voie unique restant entre Campbon et Bouvron, alors qu\'un autre train, vers Saint-Nazaire, s\'y trouvait déjà engagé.',
                'en' => 'On the evening of Tuesday 3 October 1911, on the Sablé to Montoir-de-Bretagne line, the stationmaster at Campbon wrongly let a goods train bound for Châteaubriant enter the single-track section remaining between Campbon and Bouvron, while another train, heading for Saint-Nazaire, was already on it.',
                'de' => 'Am Abend des Dienstags, 3. Oktober 1911, ließ auf der Strecke Sablé - Montoir-de-Bretagne der Bahnhofsvorsteher von Campbon irrtümlich einen Güterzug nach Châteaubriant in den verbleibenden eingleisigen Abschnitt zwischen Campbon und Bouvron einfahren, obwohl dort bereits ein anderer Zug in Richtung Saint-Nazaire fuhr.',
                'sources' => ['wp-liste-1911', 'delpher-1911-10-05-zutphen'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'La collision frontale a fait quatre morts (un mécanicien, un chauffeur, un chef de train et un serre-frein) et deux blessés graves (un mécanicien et un chauffeur). Les brèves néerlandaises du 5 octobre donnent le même bilan.',
                'en' => 'The head-on collision killed four (a driver, a fireman, a guard and a brakeman) and seriously injured two (a driver and a fireman). The Dutch items of 5 October give the same toll.',
                'de' => 'Der Frontalzusammenstoß forderte vier Tote (einen Lokführer, einen Heizer, einen Zugführer und einen Bremser) und zwei Schwerverletzte (einen Lokführer und einen Heizer). Die niederländischen Notizen vom 5. Oktober nennen dieselbe Bilanz.',
                'sources' => ['wp-liste-1911', 'delpher-1911-10-05-vaderland'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'L\'erreur du chef de gare de Campbon, qui a autorisé l\'entrée d\'un second train sur la section à voie unique déjà occupée, est la cause indiquée.',
                'en' => 'The cause given is the error of the stationmaster at Campbon, who allowed a second train onto the single-track section already occupied.',
                'de' => 'Als Ursache wird der Fehler des Bahnhofsvorstehers von Campbon genannt, der einem zweiten Zug die Einfahrt in den bereits besetzten eingleisigen Abschnitt erlaubte.',
                'sources' => ['wp-liste-1911'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'La collision a lieu entre les gares de Campbon et de Bouvron, sans que les sources nomment la commune. La liste de Wikipédia date l\'accident du 3 novembre 1911 mais s\'appuie sur Le Petit Parisien du 5 octobre ; les brèves néerlandaises du 5 octobre le placent le mardi soir, soit le 3 octobre, ou « la veille ».',
                'en' => 'The collision happened between Campbon and Bouvron stations; the sources do not name the commune. The Wikipedia list dates the accident 3 November 1911 but relies on Le Petit Parisien of 5 October; the Dutch items of 5 October place it on Tuesday evening, that is 3 October, or "yesterday".',
                'de' => 'Der Zusammenstoß ereignete sich zwischen den Bahnhöfen Campbon und Bouvron; die Quellen nennen die Gemeinde nicht. Die Wikipedia-Liste datiert das Unglück auf den 3. November 1911, stützt sich aber auf Le Petit Parisien vom 5. Oktober; die niederländischen Notizen vom 5. Oktober verorten es am Dienstagabend, also am 3. Oktober, oder „gestern“.',
                'sources' => ['wp-liste-1911', 'delpher-1911-10-05-zutphen', 'delpher-1911-10-05-vaderland'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'occurred_on',
            'value' => '1911-10-03',
            'sources' => ['delpher-1911-10-05-zutphen', 'delpher-1911-10-05-vaderland', 'wp-liste-1911'],
            'fr' => 'Deux brèves néerlandaises du 5 octobre 1911 rapportent la collision, survenue le mardi soir (3 octobre), et la liste de Wikipédia s\'appuie sur Le Petit Parisien du 5 octobre ; la date du 3 novembre est une erreur de mois.',
            'en' => 'Two Dutch items of 5 October 1911 report the collision, which happened on the Tuesday evening (3 October), and the Wikipedia list relies on Le Petit Parisien of 5 October; the date of 3 November is a wrong month.',
            'de' => 'Zwei niederländische Notizen vom 5. Oktober 1911 berichten über den Zusammenstoß am Dienstagabend (3. Oktober), und die Wikipedia-Liste stützt sich auf Le Petit Parisien vom 5. Oktober; das Datum 3. November ist ein Monatsfehler.',
        ],
    ],
];
