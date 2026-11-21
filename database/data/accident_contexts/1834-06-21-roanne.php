<?php

// Contexte sourcé de l'accident de la diligence du plan incliné de Neulise, en juin 1834 (recherche du 21/11/2026).
return [
    'slug' => '1834-06-21-roanne',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1830' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1830',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1834',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1830&oldid=235638465#1834',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Date du 21 juin 1834, deux morts et quatre blessés ; cite le Journal des débats du 30 juin 1834.',
        ],
        'vdr-neulise' => [
            'provider' => 'sites-web',
            'kind' => 'web',
            'reliability' => 'local_history',
            'title' => '1834, Une roue brisée à Neulise (42)',
            'publisher' => 'Victimes du rail en France au XIXe siècle',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://www.victimesdurail.fr/1834-une-roue-brisee-a-neulise-42/',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Site de généalogie ; transcrit le Journal des débats politiques du 30 juin 1834, qui reprend le Journal de Saint-Étienne du 26 juin.',
        ],
        'debats-1834-06-30' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Journal des débats politiques et littéraires du 30 juin 1834, page 3',
            'publisher' => 'Journal des débats (numérisé sur Gallica)',
            'author' => null,
            'published' => '1834-06-30',
            'locator' => null,
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k438450p/f3',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Lu seulement dans sa transcription.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Sur le chemin de fer de la Loire, les diligences circulaient depuis quelque temps et avaient déjà transporté plus de 40 000 voyageurs sans accident. À la descente du plan incliné de Neulise (Loire), une roue de la diligence s\'est brisée.',
                'en' => 'On the Loire railway, stagecoach-type passenger cars had been running for some time and had already carried more than 40,000 passengers without an accident. On the descent of the Neulise inclined plane (Loire), a wheel of the car broke.',
                'de' => 'Auf der Eisenbahn der Loire verkehrten seit einiger Zeit Personenwagen in Kutschenform, die bereits mehr als 40 000 Reisende ohne Unfall befördert hatten. Beim Hinabfahren der geneigten Ebene von Neulise (Loire) brach ein Rad des Wagens.',
                'sources' => ['vdr-neulise', 'wp-liste-1830'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Selon le Journal de Saint-Étienne cité par le Journal des débats, le conducteur avait laissé prendre un peu trop de vitesse à la voiture et ne l\'a pas retenue tout de suite, ce qui était toujours possible. La voiture est tombée.',
                'en' => 'According to the Journal de Saint-Étienne as cited by the Journal des débats, the guard had let the car pick up a little too much speed and did not hold it back at once, which was always possible. The car fell.',
                'de' => 'Laut dem vom Journal des débats zitierten Journal de Saint-Étienne hatte der Schaffner den Wagen etwas zu schnell werden lassen und ihn nicht sofort gebremst, was stets möglich war. Der Wagen stürzte.',
                'sources' => ['vdr-neulise'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'L\'accident a tué un voyageur de Roanne et un employé de la compagnie chargé de la conduite des convois. Quatre autres voyageurs ont été plus ou moins violemment contusionnés, ainsi que le conducteur ; les habitants voisins et les médecins de Saint-Symphorien et de Roanne ont porté secours.',
                'en' => 'The accident killed a passenger from Roanne and a company employee in charge of running the trains. Four other passengers were more or less badly bruised, as was the guard; neighbouring residents and doctors from Saint-Symphorien and Roanne gave help.',
                'de' => 'Bei dem Unfall starben ein Reisender aus Roanne und ein Angestellter der Gesellschaft, der für die Zugführung zuständig war. Vier weitere Reisende erlitten mehr oder weniger schwere Prellungen, ebenso der Schaffner; Anwohner und Ärzte aus Saint-Symphorien und Roanne leisteten Hilfe.',
                'sources' => ['vdr-neulise', 'wp-liste-1830'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'La compagnie a donné aux conducteurs l\'ordre de régler plus prudemment la vitesse à la descente des plans inclinés, de sorte que le bris d\'une roue reste sans suite grave. Le journal présente cet accident comme le premier de ce type sur la ligne.',
                'en' => 'The company ordered the guards to control speed more cautiously on the descent of inclined planes, so that a broken wheel would have no serious consequences. The newspaper presents this as the first accident of its kind on the line.',
                'de' => 'Die Gesellschaft wies die Schaffner an, die Geschwindigkeit beim Hinabfahren der geneigten Ebenen vorsichtiger zu regeln, damit ein Radbruch ohne schwere Folgen bleibe. Die Zeitung stellt diesen Unfall als den ersten seiner Art auf der Strecke dar.',
                'sources' => ['vdr-neulise'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le texte du Journal des débats, tel que l\'a transcrit le site de généalogie, parle du « samedi 12 juin », alors que le samedi de cette semaine de juin 1834 est le 21 ; l\'entrée de la liste de Wikipédia donne le 21 juin.',
                'en' => 'The text of the Journal des débats, as transcribed by the genealogy site, speaks of "Saturday 12 June", whereas the Saturday of that week of June 1834 is the 21st; the Wikipedia list entry gives 21 June.',
                'de' => 'Der Text des Journal des débats, wie ihn die Genealogie-Seite abgeschrieben hat, spricht vom „Samstag, 12. Juni“, während der Samstag jener Woche im Juni 1834 der 21. ist; der Eintrag der Wikipedia-Liste nennt den 21. Juni.',
                'sources' => ['vdr-neulise', 'wp-liste-1830'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'commune_insee',
            'value' => '42156',
            'sources' => ['vdr-neulise', 'wp-liste-1830'],
            'fr' => 'L\'accident a eu lieu à la descente du plan incliné de Neulise, selon le Journal des débats et la liste de Wikipédia.',
            'en' => 'The accident took place on the descent of the Neulise inclined plane, according to the Journal des débats and the Wikipedia list.',
            'de' => 'Der Unfall ereignete sich beim Hinabfahren der geneigten Ebene von Neulise, laut dem Journal des débats und der Wikipedia-Liste.',
        ],
    ],
];
