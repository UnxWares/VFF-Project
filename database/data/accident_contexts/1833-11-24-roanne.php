<?php

// Contexte sourcé du déraillement de wagons du plan incliné de Neulise, ligne d'Andrézieux à Roanne, du 24 novembre 1833 (recherche du 22/11/2026).
return [
    'slug' => '1833-11-24-roanne',
    'checked_on' => '2026-11-22',
    'sources' => [
        'wp-liste-1830' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1830',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1833',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1830&oldid=235638465#1833',
            'access' => 'open',
            'consulted_on' => '2026-11-22',
            'note' => 'Entrée du 24 novembre 1833, d\'après le Journal des débats du 1er décembre 1833 : lors d\'essais après une avarie de la machine à vapeur du plan incliné de Neulise, seize wagons dévalent la pente et déraillent dans une courbe ; sur les quatre agents chargés de les freiner, un est tué et trois sont blessés.',
        ],
        'haye-1833-12-04' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Paris, 30 novembre',
            'publisher' => 'Journal de La Haye (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1833-12-04',
            'locator' => null,
            'url' => 'https://resolver.kb.nl/resolve?urn=ddd:010095118:mpeg21:a0008',
            'access' => 'open',
            'consulted_on' => '2026-11-22',
            'note' => 'Presse néerlandaise numérisée par la Koninklijke Bibliotheek (Delpher) ; article en français. Correspondance de Paris du 30 novembre : récit de l\'accident du 24 sur le chemin de fer de Roanne, au plan incliné entre Saint-Symphorien-de-Lay et Neulise.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 24 novembre 1833, sur le chemin de fer d\'Andrézieux à Roanne, la machine à vapeur fixe du plan incliné de Neulise, entre Saint-Symphorien-de-Lay et Neulise, venait d\'être réparée après une avarie. Pour l\'essayer, quatre hommes avaient été chargés de conduire 16 wagons vides au bas de la descente afin de les faire remonter. Ils ont lâché les freins, les wagons ont pris une vitesse très forte sur plus d\'une demi-lieue et ont quitté les rails dans une courbe.',
                'en' => 'On 24 November 1833, on the Andrézieux to Roanne railway, the fixed steam engine of the Neulise inclined plane, between Saint-Symphorien-de-Lay and Neulise, had just been repaired after a breakdown. To test it, four men had been told to take 16 empty wagons to the bottom of the descent so that they could be hauled back up. They released the brakes, the wagons gathered great speed over more than half a league and left the rails on a curve.',
                'de' => 'Am 24. November 1833 war auf der Eisenbahn von Andrézieux nach Roanne die feststehende Dampfmaschine der geneigten Ebene von Neulise zwischen Saint-Symphorien-de-Lay und Neulise nach einem Schaden gerade repariert worden. Zur Probe sollten vier Männer 16 leere Wagen ans untere Ende des Gefälles bringen, damit sie wieder hinaufgezogen werden konnten. Sie lösten die Bremsen, die Wagen nahmen auf mehr als einer halben Wegstunde sehr schnell Fahrt auf und sprangen in einer Kurve aus den Schienen.',
                'sources' => ['haye-1833-12-04', 'wp-liste-1830'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Les quatre hommes qui conduisaient les wagons ont voulu sauter à terre. Selon le Journal de La Haye, tous ont été plus ou moins blessés et l\'un d\'eux est resté mort sur place. La liste de Wikipédia donne le même bilan : un mort et trois blessés.',
                'en' => 'The four men in charge of the wagons tried to jump to the ground. According to the Journal de La Haye, all were injured to some degree and one was left dead on the spot. The Wikipedia list gives the same toll: one dead and three injured.',
                'de' => 'Die vier Männer bei den Wagen versuchten abzuspringen. Nach dem Journal de La Haye wurden alle mehr oder weniger verletzt, und einer blieb tot am Ort. Die Wikipedia-Liste nennt dieselbe Bilanz: ein Toter und drei Verletzte.',
                'sources' => ['haye-1833-12-04', 'wp-liste-1830'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'Le Journal de La Haye attribue l\'accident à l\'imprudence des quatre hommes, qui voulaient aller trop vite et ont lâché les freins, et ajoute que la machine, mal construite, venait de subir une détérioration. La liste de Wikipédia rattache l\'accident à l\'avarie de la machine et aux essais qui ont suivi.',
                'en' => 'The Journal de La Haye blames the four men\'s carelessness, since they wanted to go too fast and released the brakes, and adds that the engine, badly built, had just broken down. The Wikipedia list links the accident to the engine breakdown and the tests that followed.',
                'de' => 'Das Journal de La Haye führt den Unfall auf die Unvorsichtigkeit der vier Männer zurück, die zu schnell fahren wollten und die Bremsen lösten, und fügt hinzu, die schlecht gebaute Maschine habe gerade einen Schaden erlitten. Die Wikipedia-Liste bringt den Unfall mit dem Maschinenschaden und den anschließenden Versuchen in Verbindung.',
                'sources' => ['haye-1833-12-04', 'wp-liste-1830'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 1,
            'sources' => ['haye-1833-12-04', 'wp-liste-1830'],
            'fr' => 'Un agent tué selon la liste de Wikipédia et le Journal de La Haye ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'One crew member killed according to the Wikipedia list and the Journal de La Haye; the record gave no number of deaths.',
            'de' => 'Ein Mann getötet nach der Wikipedia-Liste und dem Journal de La Haye; der Eintrag nannte keine Zahl von Toten.',
        ],
        [
            'field' => 'injuries',
            'value' => 3,
            'sources' => ['haye-1833-12-04', 'wp-liste-1830'],
            'fr' => 'Trois agents blessés selon la liste de Wikipédia ; le Journal de La Haye dit que tous les quatre ont été plus ou moins blessés, dont un mort.',
            'en' => 'Three crew members injured according to the Wikipedia list; the Journal de La Haye says all four were injured to some degree, one of them dead.',
            'de' => 'Drei Männer verletzt nach der Wikipedia-Liste; das Journal de La Haye sagt, alle vier seien mehr oder weniger verletzt worden, einer davon tot.',
        ],
        [
            'field' => 'commune_insee',
            'value' => '42156',
            'sources' => ['haye-1833-12-04', 'wp-liste-1830'],
            'fr' => 'L\'accident a eu lieu au plan incliné de Neulise, entre Saint-Symphorien-de-Lay et Neulise, selon le Journal de La Haye et la liste de Wikipédia, et non à Roanne.',
            'en' => 'The accident took place on the Neulise inclined plane, between Saint-Symphorien-de-Lay and Neulise, according to the Journal de La Haye and the Wikipedia list, not at Roanne.',
            'de' => 'Der Unfall ereignete sich an der geneigten Ebene von Neulise zwischen Saint-Symphorien-de-Lay und Neulise, laut dem Journal de La Haye und der Wikipedia-Liste, nicht in Roanne.',
        ],
    ],
];
