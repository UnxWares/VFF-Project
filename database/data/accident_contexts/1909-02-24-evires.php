<?php

// Contexte sourcé de la rupture d'un tube de chaudière près du tunnel d'Évires, ligne d'Aix-les-Bains à Annemasse, de février 1909 (recherche du 21/11/2026).
return [
    'slug' => '1909-02-24-evires',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1909' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1909',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'Février',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1909&oldid=241222447#F%C3%A9vrier',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 24 février 1909, d\'après Le Petit Journal du 26 février : sur la ligne d\'Aix-les-Bains à Annemasse, peu avant le tunnel d\'Évires, vers 19 h, la rupture d\'un tube de la chaudière de la locomotive d\'un train de Genève à Annecy tue le chauffeur au moment où il allait recharger le foyer.',
        ],
        'ddb-1909-03-02-witten' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Paris, 26. Febr.',
            'publisher' => 'Wittener Volks-Zeitung (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1909-03-02',
            'locator' => 'p. 3',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/DOTHIYFWJT7EY55DMCDMMDSEMMTARKPN?issuepage=3',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêche de Paris du 26 février : sur un train de Genève à Annecy, à l\'entrée du tunnel d\'Évires, le chauffeur, âgé de 22 ans, ouvre la porte du foyer pour charger du charbon à la demande du mécanicien ; une explosion projette sur son visage un jet de vapeur mêlée de cendres et le jette sur la voie ; le mécanicien, indemne, arrête le train ; le chauffeur meurt quelques heures plus tard à l\'hôpital.',
        ],
        'ddb-1909-03-03-witten' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Paris, 1. März',
            'publisher' => 'Wittener Tageblatt (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1909-03-03',
            'locator' => 'p. 5',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/RZ5HGJN7UIKPAXNQM2T6KLJVN24OYYEB?issuepage=5',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Même récit, daté de Paris du 1er mars : explosion au foyer de la locomotive à l\'entrée du tunnel d\'Évires, chauffeur projeté sur la voie, mort quelques heures plus tard à l\'hôpital, mécanicien indemne.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 24 février 1909, vers 19 h, un train de Genève à Annecy roulait sur la ligne d\'Aix-les-Bains à Annemasse et approchait du tunnel d\'Évires. Le mécanicien a demandé au chauffeur de faire monter la pression.',
                'en' => 'On 24 February 1909, at about 7 pm, a Geneva to Annecy train was running on the Aix-les-Bains to Annemasse line and approaching the Évires tunnel. The driver asked the stoker to raise the steam pressure.',
                'de' => 'Am 24. Februar 1909 gegen 19 Uhr fuhr ein Zug von Genf nach Annecy auf der Strecke Aix-les-Bains-Annemasse auf den Tunnel von Évires zu. Der Lokführer wies den Heizer an, den Dampfdruck zu erhöhen.',
                'sources' => ['wp-liste-1909', 'ddb-1909-03-02-witten'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Quand le chauffeur a ouvert la porte du foyer pour charger du charbon, un tube de la chaudière s\'est rompu. Un jet de vapeur mêlée de cendres l\'a frappé au visage et l\'a projeté sur la voie. Le mécanicien, indemne, a arrêté le train.',
                'en' => 'When the stoker opened the firebox door to put on coal, a boiler tube burst. A jet of steam mixed with ashes struck him in the face and threw him onto the track. The driver, unhurt, stopped the train.',
                'de' => 'Als der Heizer die Feuertür öffnete, um Kohle nachzulegen, platzte ein Kesselrohr. Ein mit Asche vermischter Dampfstrahl traf ihn ins Gesicht und schleuderte ihn auf das Gleis. Der unverletzte Lokführer hielt den Zug an.',
                'sources' => ['wp-liste-1909', 'ddb-1909-03-02-witten', 'ddb-1909-03-03-witten'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le chauffeur, âgé de 22 ans, est mort quelques heures plus tard à l\'hôpital.',
                'en' => 'The stoker, aged 22, died a few hours later in hospital.',
                'de' => 'Der 22-jährige Heizer starb einige Stunden später im Krankenhaus.',
                'sources' => ['ddb-1909-03-02-witten', 'wp-liste-1909'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'kind',
            'value' => 'explosion',
            'sources' => ['wp-liste-1909', 'ddb-1909-03-02-witten'],
            'fr' => 'Rupture d\'un tube de chaudière selon la liste de Wikipédia, explosion au foyer selon la dépêche de Paris du 26 février 1909 ; la fiche parlait d\'un ouvrage qui cède.',
            'en' => 'Boiler tube burst according to the Wikipedia list, explosion in the firebox according to the Paris dispatch of 26 February 1909; the record described a structure failure.',
            'de' => 'Geplatztes Kesselrohr nach der Wikipedia-Liste, Explosion in der Feuerbüchse nach der Pariser Depesche vom 26. Februar 1909; der Eintrag sprach vom Versagen eines Bauwerks.',
        ],
        [
            'field' => 'deaths',
            'value' => 1,
            'sources' => ['wp-liste-1909', 'ddb-1909-03-02-witten', 'ddb-1909-03-03-witten'],
            'fr' => 'Chauffeur tué selon la liste de Wikipédia et la presse allemande ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'Stoker killed according to the Wikipedia list and the German press; the record gave no number of deaths.',
            'de' => 'Heizer getötet nach der Wikipedia-Liste und der deutschen Presse; der Eintrag nannte keine Zahl von Toten.',
        ],
    ],
];
