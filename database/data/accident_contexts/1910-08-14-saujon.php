<?php

// Contexte sourcé de l'accident de Saujon du 14 août 1910 (recherche du 21/11/2026).
return [
    'slug' => '1910-08-14-saujon',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-fr' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Accident ferroviaire de Saujon',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Accident_ferroviaire_de_Saujon&oldid=234272346',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Article détaillé : composition, enquête, procès de 1911, débats sur la responsabilité de l\'État.',
        ],
        'wp-de' => [
            'provider' => 'wikipedia-de',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Eisenbahnunfall von Saujon',
            'publisher' => 'Wikipedia',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://de.wikipedia.org/wiki/Eisenbahnunfall_von_Saujon',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Bilan de 43 morts et 50 blessés, d\'après Semmens, Katastrophen auf Schienen (1996), p. 38.',
        ],
        'shasm' => [
            'provider' => 'sites-web',
            'kind' => 'web',
            'reliability' => 'local_history',
            'title' => 'La catastrophe ferroviaire de Saujon 1910',
            'publisher' => 'Société d\'Histoire et d\'Archéologie en Saintonge Maritime',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://www.shasm.fr/histoire-locale/ev%C3%A8nements/saujon-catastrophe-ferroviaire/',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Extraits du Gaulois du 16 août 1910 ; faits seulement, rien recopié.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le dimanche 14 août 1910, à l\'entrée de la gare de Saujon (Charente-Inférieure, aujourd\'hui Charente-Maritime), sur le réseau de l\'État, un train de plaisir venant de Bordeaux et de Langon pour Royan heurte un train de marchandises. Le train de plaisir, formé de façon improvisée, transportait plus d\'un millier de voyageurs. L\'accident est situé vers midi.',
                'en' => 'On Sunday 14 August 1910, at the entrance to Saujon station (Charente-Inférieure, now Charente-Maritime), on the State network, an excursion train from Bordeaux and Langon bound for Royan struck a freight train. The excursion train, made up in an improvised way, carried more than a thousand passengers. The accident is placed at about midday.',
                'de' => 'Am Sonntag, 14. August 1910, prallte am Einfahrtsbereich des Bahnhofs Saujon (Charente-Inférieure, heute Charente-Maritime) im Netz der Staatsbahn ein Ausflugszug aus Bordeaux und Langon nach Royan auf einen Güterzug. Der behelfsmäßig zusammengestellte Ausflugszug beförderte mehr als tausend Reisende. Der Unfall wird auf etwa Mittag datiert.',
                'sources' => ['wp-fr', 'shasm'],
            ],
            [
                'fr' => 'Le train de marchandises (n° 1512) stationnait sur une voie d\'évitement, sa locomotive empiétant sur la voie principale.',
                'en' => 'The freight train (no. 1512) stood on a siding, its locomotive encroaching on the main line.',
                'de' => 'Der Güterzug (Nr. 1512) stand auf einem Ausweichgleis, seine Lokomotive ragte in das Hauptgleis.',
                'sources' => ['wp-fr'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Le train de plaisir roulait à environ 50 km/h au lieu des 15 km/h exigés. Le freinage d\'urgence n\'a ramené sa vitesse qu\'à 10 à 15 km/h environ au moment du choc.',
                'en' => 'The excursion train was running at about 50 km/h instead of the required 15 km/h. Emergency braking reduced its speed only to about 10 to 15 km/h at the moment of impact.',
                'de' => 'Der Ausflugszug fuhr mit etwa 50 km/h statt der vorgeschriebenen 15 km/h. Die Schnellbremsung verringerte die Geschwindigkeit beim Aufprall nur auf etwa 10 bis 15 km/h.',
                'sources' => ['wp-fr'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le bilan retenu est de 38 morts et 103 blessés. Le Gaulois du 16 août 1910 parlait d\'une quarantaine de victimes. L\'accident survient moins de deux mois après celui de Villepreux, sur le même réseau.',
                'en' => 'The toll retained is 38 dead and 103 injured. Le Gaulois of 16 August 1910 spoke of about forty victims. The accident came less than two months after that of Villepreux, on the same network.',
                'de' => 'Als Bilanz gelten 38 Tote und 103 Verletzte. Le Gaulois vom 16. August 1910 sprach von etwa vierzig Opfern. Der Unfall ereignete sich weniger als zwei Monate nach dem von Villepreux im selben Netz.',
                'sources' => ['wp-fr', 'shasm'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'L\'enquête a établi que l\'aiguilleur avait ouvert trop tôt le signal d\'entrée, croyant le train de marchandises entièrement garé. La vitesse excessive du train de plaisir a aggravé les conséquences. Des critiques ont aussi visé la composition improvisée du train, l\'absence d\'enclenchement entre signaux et aiguilles et l\'absence d\'attelages automatiques.',
                'en' => 'The inquiry established that the signalman had cleared the entry signal too early, believing the freight train to be fully clear. The excursion train\'s excessive speed made the consequences worse. Critics also pointed to the improvised train formation, the lack of interlocking between signals and points, and the absence of automatic couplers.',
                'de' => 'Die Untersuchung ergab, dass der Weichenwärter das Einfahrsignal zu früh geöffnet hatte, weil er den Güterzug für vollständig abgestellt hielt. Die überhöhte Geschwindigkeit des Ausflugszugs verschärfte die Folgen. Kritik richtete sich auch gegen die behelfsmäßige Zugbildung, das Fehlen einer Abhängigkeit zwischen Signalen und Weichen und das Fehlen automatischer Kupplungen.',
                'sources' => ['wp-fr'],
            ],
            [
                'fr' => 'Le Gaulois, cité par une société d\'histoire locale, attribue l\'accident à une erreur d\'aiguillage.',
                'en' => 'Le Gaulois, quoted by a local history society, attributes the accident to a points error.',
                'de' => 'Le Gaulois, zitiert von einem Heimatgeschichtsverein, führt den Unfall auf einen Weichenfehler zurück.',
                'sources' => ['shasm'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Seul l\'aiguilleur a été poursuivi : le tribunal de Saintes l\'a condamné en 1911 à un an de prison avec sursis et à une amende. L\'État a été reconnu civilement responsable et a dû indemniser les victimes.',
                'en' => 'Only the signalman was prosecuted: the Saintes court sentenced him in 1911 to one year in prison, suspended, and a fine. The State was held civilly liable and had to compensate the victims.',
                'de' => 'Nur der Weichenwärter wurde verfolgt: Das Gericht in Saintes verurteilte ihn 1911 zu einem Jahr Haft auf Bewährung und einer Geldstrafe. Der Staat wurde zivilrechtlich haftbar gemacht und musste die Opfer entschädigen.',
                'sources' => ['wp-fr'],
            ],
            [
                'fr' => 'L\'accident a relancé le débat sur l\'exploitation des chemins de fer par l\'État, et ravivé les tensions entre l\'État et l\'Église catholique après la loi de séparation de 1905, notamment lors des cérémonies religieuses.',
                'en' => 'The accident reopened the debate on State operation of the railways, and revived tensions between the State and the Catholic Church after the 1905 separation law, notably during the memorial services.',
                'de' => 'Der Unfall belebte die Debatte über den Staatsbetrieb der Eisenbahnen neu und verschärfte die Spannungen zwischen Staat und katholischer Kirche nach dem Trennungsgesetz von 1905, besonders bei den Trauerfeiern.',
                'sources' => ['wp-fr'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le bilan diffère : 38 morts et 103 blessés (Wikipédia français), 43 morts et 50 blessés (Wikipedia allemand, d\'après Semmens), une quarantaine de victimes (Le Gaulois, via la SHASM). La liste Wikipédia des accidents de 1910 donne 36 morts et 59 blessés selon les données de la fiche (page non lue).',
                'en' => 'The toll differs: 38 dead and 103 injured (French Wikipedia), 43 dead and 50 injured (German Wikipedia, after Semmens), about forty casualties (Le Gaulois, via the SHASM). The Wikipedia list of 1910 accidents gives 36 dead and 59 injured according to the record data (page not read).',
                'de' => 'Die Bilanz weicht ab: 38 Tote und 103 Verletzte (französische Wikipedia), 43 Tote und 50 Verletzte (deutsche Wikipedia nach Semmens), etwa vierzig Opfer (Le Gaulois über die SHASM). Die Wikipedia-Liste der Unfälle von 1910 nennt laut Datensatz 36 Tote und 59 Verletzte (Seite nicht gelesen).',
                'sources' => ['wp-fr', 'wp-de', 'shasm'],
            ],
        ],
    ],
    'images' => [
        [
            'file' => 'File:Photo de la catastrophe de Saujon dans le journal Le Matin.jpg',
            'caption' => [
                'fr' => 'Photographie de la catastrophe de Saujon publiée dans Le Matin en 1910.',
                'en' => 'Photograph of the Saujon disaster published in Le Matin in 1910.',
                'de' => 'Fotografie der Katastrophe von Saujon, 1910 in Le Matin veröffentlicht.',
            ],
        ],
        [
            'file' => 'File:Saujon - catastrophe ferroviaire 14 août 1910.jpg',
            'caption' => [
                'fr' => 'Carte postale ancienne consacrée à la catastrophe ferroviaire de Saujon du 14 août 1910.',
                'en' => 'Period postcard devoted to the Saujon railway disaster of 14 August 1910.',
                'de' => 'Historische Postkarte zur Eisenbahnkatastrophe von Saujon am 14. August 1910.',
            ],
        ],
    ],
];
