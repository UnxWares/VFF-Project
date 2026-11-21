<?php

// Contexte sourcé de l'accident de Thourotte de février 1879 : une équipe de cantonniers renversée par le train n° 38 après le déraillement d'une machine de ballast (recherche du 21/11/2026).
return [
    'slug' => '1879-02-09-thourotte',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1870' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1870',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1879, 9 février',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1870&oldid=235357691#1879',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Source de la fiche (4 morts et 2 blessés graves), d\'après Le Temps du 13 février 1879.',
        ],
        'vdr-thourotte' => [
            'provider' => 'sites-web',
            'kind' => 'web',
            'reliability' => 'local_history',
            'title' => '1879, Accident à Thourotte (60)',
            'publisher' => 'Victimes du rail en France au XIXe siècle',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://www.victimesdurail.fr/1879-accident-a-thourotte/',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Site de généalogie ; transcrit La Charente du 16 février 1879, qui reprend le rapport de la gendarmerie de Compiègne et le Journal de l\'Oise.',
        ],
        'la-charente-1879-02-16' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'La Charente du 16 février 1879',
            'publisher' => 'La Charente',
            'author' => null,
            'published' => '1879-02-16',
            'locator' => 'p. 3',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k4644490c/f3',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Lu seulement dans sa transcription.',
        ],
        'le-temps-1879-02-13' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Temps du 13 février 1879',
            'publisher' => 'Le Temps',
            'author' => null,
            'published' => '1879-02-13',
            'locator' => 'p. 2',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k227555z/f2.image',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Cité par Wikipédia ; non lu.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Vers 16 heures, la machine d\'un train de ballast a déraillé entre Ribécourt et Thourotte, au point kilométrique 111,600, au lieu-dit la Carrière de Gamaches, sur la ligne de Creil à Jeumont. Elle obstruait les deux voies, et les trains ont circulé en contre-voie avec plusieurs heures de retard.',
                'en' => 'At about 4 pm the engine of a ballast train derailed between Ribécourt and Thourotte, at kilometre post 111.600, at the place called la Carrière de Gamaches, on the Creil to Jeumont line. It blocked both tracks, and trains ran on the wrong line with delays of several hours.',
                'de' => 'Gegen 16 Uhr entgleiste die Lokomotive eines Schotterzugs zwischen Ribécourt und Thourotte bei Kilometer 111,600, an der Stelle Carrière de Gamaches, auf der Strecke Creil-Jeumont. Sie versperrte beide Gleise, und die Züge fuhren mit mehreren Stunden Verspätung auf dem Gegengleis.',
                'sources' => ['vdr-thourotte', 'wp-liste-1870'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Leur travail terminé, neuf cantonniers rentraient à Thourotte en marchant sur la voie. À 22 h 10, au point kilométrique 109,700, à environ 200 mètres de la gare, le train n° 38, qui roulait en contre-voie de Ribécourt vers Compiègne, les a pris par derrière. Le Journal de l\'Oise notait qu\'ils n\'avaient pas pensé à la circulation en contre-voie.',
                'en' => 'Their work done, nine track workers were walking back to Thourotte along the line. At 10.10 pm, at kilometre post 109.700, about 200 metres from the station, train no. 38, running on the wrong line from Ribécourt towards Compiègne, struck them from behind. The Journal de l\'Oise noted that they had not thought of the wrong-line working.',
                'de' => 'Nach getaner Arbeit gingen neun Streckenarbeiter auf dem Gleis nach Thourotte zurück. Um 22.10 Uhr erfasste sie bei Kilometer 109,700, etwa 200 Meter vor dem Bahnhof, von hinten der Zug Nr. 38, der auf dem Gegengleis von Ribécourt nach Compiègne fuhr. Das Journal de l\'Oise bemerkte, dass sie nicht an den Gegengleisbetrieb gedacht hatten.',
                'sources' => ['vdr-thourotte', 'wp-liste-1870'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Quatre ouvriers, dont le chef d\'équipe et son fils, ont été tués sur le coup ; un cinquième a été grièvement blessé et un sixième légèrement contusionné. Trois des morts ont été inhumés ensemble à Longueil-Annel, le quatrième à Thourotte, et une souscription a été ouverte dans les deux journaux de Compiègne.',
                'en' => 'Four workers, including the gang foreman and his son, were killed outright; a fifth was seriously injured and a sixth slightly bruised. Three of the dead were buried together at Longueil-Annel and the fourth at Thourotte, and a subscription was opened at the two Compiègne newspapers.',
                'de' => 'Vier Arbeiter, darunter der Vorarbeiter und sein Sohn, waren sofort tot; ein fünfter wurde schwer verletzt, ein sechster leicht geprellt. Drei der Toten wurden gemeinsam in Longueil-Annel beigesetzt, der vierte in Thourotte, und die beiden Zeitungen von Compiègne eröffneten eine Spendensammlung.',
                'sources' => ['vdr-thourotte'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le procureur de la République, le juge d\'instruction de Compiègne et l\'inspecteur en chef de la Compagnie du Nord se sont rendus sur les lieux le lendemain matin.',
                'en' => 'The public prosecutor, the Compiègne examining magistrate and the chief inspector of the Compagnie du Nord went to the scene the next morning.',
                'de' => 'Der Staatsanwalt, der Untersuchungsrichter von Compiègne und der Oberinspektor der Compagnie du Nord begaben sich am nächsten Morgen an den Unfallort.',
                'sources' => ['vdr-thourotte'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Wikipédia situe l\'accident vers 23 heures et décrit une équipe qui venait de relever la locomotive déraillée, surprise par un train en retard de deux heures. Le rapport de la gendarmerie donne 22 h 10. La fiche classe l\'accident en déraillement d\'après la première circonstance ; les quatre morts ont été renversés par un train.',
                'en' => 'Wikipedia puts the accident at about 11 pm and describes a gang that had just re-railed the derailed engine, caught by a train running two hours late. The gendarmerie report gives 10.10 pm. The record files the accident as a derailment after the first event; the four dead were struck by a train.',
                'de' => 'Wikipedia setzt den Unfall gegen 23 Uhr an und beschreibt eine Kolonne, die gerade die entgleiste Lokomotive wieder aufgegleist hatte und von einem zwei Stunden verspäteten Zug überrascht wurde. Der Bericht der Gendarmerie nennt 22.10 Uhr. Der Eintrag ordnet den Unfall nach dem ersten Ereignis als Entgleisung ein; die vier Toten wurden von einem Zug erfasst.',
                'sources' => ['wp-liste-1870', 'vdr-thourotte'],
            ],
        ],
    ],
    'corrections' => [],
];
