<?php

// Contexte sourcé de la collision d'un train du Petit Anjou avec une voiture hippomobile, près de Noyant ou de Mazé, d'août 1901 (recherche du 22/11/2026).
return [
    'slug' => '1901-08-12-noyant',
    'checked_on' => '2026-11-22',
    'sources' => [
        'wp-liste-1901' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1901',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'Août',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1901&oldid=241345719#Août',
            'access' => 'open',
            'consulted_on' => '2026-11-22',
            'note' => 'Entrée du 12 août 1901, d\'après Le Figaro du 13 août et Le Temps du 15 août : sur la ligne à voie métrique d\'Angers à Noyant-Méon du Petit Anjou, près de Mazé, un train de Baugé à Angers percute une carriole d\'excursionnistes dont le cheval s\'est emballé ; sur les six passagers, une jeune fille et un enfant de quatre ans sont tués, les quatre autres grièvement blessés.',
        ],
        'noordbrabanter-1901-08-15' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Telegrammen',
            'publisher' => 'De Noord Brabanter',
            'author' => null,
            'published' => '1901-08-15',
            'locator' => null,
            'url' => 'https://resolver.kb.nl/resolve?urn=MMUBTB04:210829040:mpeg21:a00035',
            'access' => 'open',
            'consulted_on' => '2026-11-22',
            'note' => 'Presse néerlandaise numérisée par la Koninklijke Bibliotheek (Delpher) ; dépêche de Paris du 15 août : La Presse signale sur la ligne d\'Anjou une collision entre le train venant de Noyant et une voiture où se trouvaient cinq jeunes filles et un enfant ; deux d\'entre eux ont été tués, quatre blessés.',
        ],
        'volksblad-1901-08-17' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Een vreeselijk ongeluk',
            'publisher' => 'Het Volksblad',
            'author' => null,
            'published' => '1901-08-17',
            'locator' => null,
            'url' => 'https://resolver.kb.nl/resolve?urn=MMKB19:000078016:mpeg21:a00022',
            'access' => 'open',
            'consulted_on' => '2026-11-22',
            'note' => 'Presse néerlandaise numérisée par la Koninklijke Bibliotheek (Delpher) ; brève : accident près de Noyant ; à un passage à niveau, un train a heurté une voiture avec cinq jeunes filles et un enfant, deux des occupants ont été tués et les autres grièvement blessés. Le même texte paraît dans Het Volksdagblad du même jour.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 12 août 1901, sur la ligne à voie métrique d\'Angers à Noyant-Méon du réseau du Petit Anjou, un train venant de Baugé et allant à Angers a heurté une voiture attelée qui contenait des excursionnistes : cinq jeunes filles et un enfant selon la presse néerlandaise, six passagers selon la liste de Wikipédia. Selon cette liste, le cheval s\'était emballé ; la presse néerlandaise place la rencontre à un passage à niveau.',
                'en' => 'On 12 August 1901, on the metre-gauge line from Angers to Noyant-Méon of the Petit Anjou network, a train from Baugé to Angers struck a horse-drawn carriage carrying excursionists: five young girls and a child according to the Dutch press, six passengers according to the Wikipedia list. According to that list the horse had bolted; the Dutch press places the collision at a level crossing.',
                'de' => 'Am 12. August 1901 stieß auf der Meterspurstrecke von Angers nach Noyant-Méon des Netzes Petit Anjou ein Zug von Baugé nach Angers mit einem Pferdewagen zusammen, in dem Ausflügler saßen: fünf junge Mädchen und ein Kind nach der niederländischen Presse, sechs Fahrgäste nach der Wikipedia-Liste. Nach dieser Liste war das Pferd durchgegangen; die niederländische Presse verlegt den Zusammenstoß auf einen Bahnübergang.',
                'sources' => ['noordbrabanter-1901-08-15', 'volksblad-1901-08-17', 'wp-liste-1901'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Deux des occupants de la voiture ont été tués, un enfant et une jeune fille selon la liste de Wikipédia et la presse néerlandaise, et les quatre autres ont été grièvement blessés.',
                'en' => 'Two of the people in the carriage were killed, a child and a young girl according to the Wikipedia list and the Dutch press, and the other four were seriously injured.',
                'de' => 'Zwei der Insassen des Wagens wurden getötet, ein Kind und ein junges Mädchen nach der Wikipedia-Liste und der niederländischen Presse, die vier anderen wurden schwer verletzt.',
                'sources' => ['wp-liste-1901', 'noordbrabanter-1901-08-15', 'volksblad-1901-08-17'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le lieu diffère : la liste de Wikipédia dit près de Mazé, la presse néerlandaise près de Noyant ou avec un train venant de Noyant. Le nom de la fiche suit la seconde version.',
                'en' => 'The place differs: the Wikipedia list says near Mazé, the Dutch press near Noyant or with a train coming from Noyant. The record\'s name follows the second version.',
                'de' => 'Der Ort weicht ab: Die Wikipedia-Liste sagt bei Mazé, die niederländische Presse bei Noyant oder mit einem aus Noyant kommenden Zug. Der Name des Eintrags folgt der zweiten Fassung.',
                'sources' => ['wp-liste-1901', 'noordbrabanter-1901-08-15', 'volksblad-1901-08-17'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 2,
            'sources' => ['wp-liste-1901', 'noordbrabanter-1901-08-15'],
            'fr' => 'Deux morts selon la liste de Wikipédia (Le Figaro du 13 août 1901) et la dépêche néerlandaise d\'après La Presse ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'Two dead according to the Wikipedia list (Le Figaro of 13 August 1901) and the Dutch dispatch after La Presse; the record gave no number of deaths.',
            'de' => 'Zwei Tote nach der Wikipedia-Liste (Le Figaro vom 13. August 1901) und der niederländischen Depesche nach La Presse; der Eintrag nannte keine Zahl von Toten.',
        ],
        [
            'field' => 'injuries',
            'value' => 4,
            'sources' => ['wp-liste-1901', 'noordbrabanter-1901-08-15'],
            'fr' => 'Quatre blessés selon la liste de Wikipédia et la dépêche néerlandaise ; la fiche ne donnait pas de nombre de blessés.',
            'en' => 'Four injured according to the Wikipedia list and the Dutch dispatch; the record gave no number of injured.',
            'de' => 'Vier Verletzte nach der Wikipedia-Liste und der niederländischen Depesche; der Eintrag nannte keine Zahl von Verletzten.',
        ],
    ],
];
