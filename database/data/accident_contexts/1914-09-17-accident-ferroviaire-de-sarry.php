<?php

// Contexte sourcé de l'accident ferroviaire dit de Sarry du 17 septembre 1914 (recherche du 21/11/2026).
return [
    'slug' => '1914-09-17-accident-ferroviaire-de-sarry',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-de' => [
            'provider' => 'wikipedia-de',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Eisenbahnunfall von Sarry',
            'publisher' => 'Wikipedia (Deutsch)',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://de.wikipedia.org/wiki/Eisenbahnunfall_von_Sarry',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Seule source trouvée qui nomme Sarry ; s\'appuie sur Stockert (1920, n° 242) et situe l\'accident à Sarry (Marne).',
        ],
        'lette-2014' => [
            'provider' => 'sites-web',
            'kind' => 'web',
            'reliability' => 'association',
            'title' => 'Schweres Eisenbahnunglück nach der Ersten Marneschlacht',
            'publisher' => 'Eisenbahnmuseum Alter Bahnhof Lette',
            'author' => 'P. Dr. D. Hörnemann',
            'published' => '2014-04',
            'locator' => 'p. 1-6',
            'url' => 'https://www.bahnhof-lette.de/fileadmin/files/Monatsgeschichte_April_2014/2014-4_Eisenbahnunglueck_Marneschlacht.pdf',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Décrit un accident très semblable à Mary-sur-Marne (Seine-et-Marne), daté du 14 septembre 1914 ; ne mentionne pas Sarry.',
        ],
        'wp-en-liste-1910' => [
            'provider' => 'wikipedia-en',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'List of rail accidents (1910-1919)',
            'publisher' => 'Wikipedia (English)',
            'author' => null,
            'published' => null,
            'locator' => 'année 1914',
            'url' => 'https://en.wikipedia.org/wiki/List_of_rail_accidents_(1910%E2%80%931919)',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Mentionne un train de la Croix-Rouge tombé dans la Marne en octobre 1914, avec une légende sur le pont de Mary ; pas d\'entrée Sarry.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'D\'après l\'article allemand, un train sanitaire transportant des blessés du front vers l\'arrière circule de nuit sur la ligne de Châlons-en-Champagne à Reims, au sud de Châlons, et doit franchir un pont sur la Marne.',
                'en' => 'According to the German article, a hospital train carrying wounded from the front to the rear was running at night on the Châlons-en-Champagne to Reims line, south of Châlons, and had to cross a bridge over the Marne.',
                'de' => 'Nach dem deutschen Artikel fuhr nachts ein Lazarettzug mit Verwundeten von der Front nach hinten auf der Strecke Châlons-en-Champagne-Reims südlich von Châlons und musste eine Brücke über die Marne überqueren.',
                'sources' => ['wp-de'],
            ],
        ],
        'course' => [
            [
                'fr' => 'L\'armée française avait fait sauter le pont à titre préventif, pour empêcher son emploi par les troupes allemandes, sans prévenir le personnel ferroviaire ni protéger la voie. Dans l\'obscurité, le train s\'est engagé dans la brèche : six voitures sont restées accrochées aux débris du pont, les autres sont tombées dans la rivière.',
                'en' => 'The French army had blown up the bridge as a precaution to stop German troops using it, without warning the railway staff or protecting the line. In the dark the train ran into the gap: six carriages stayed caught on the wreckage of the bridge and the rest fell into the river.',
                'de' => 'Die französische Armee hatte die Brücke vorsorglich gesprengt, damit deutsche Truppen sie nicht nutzen konnten, ohne das Eisenbahnpersonal zu warnen oder die Strecke zu sichern. In der Dunkelheit fuhr der Zug in die Lücke: Sechs Wagen blieben an den Brückentrümmern hängen, die übrigen stürzten in den Fluss.',
                'sources' => ['wp-de'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Douze corps ont été retrouvés et quarante-cinq personnes sont données pour disparues, soit environ 57 morts. L\'article ne mentionne pas de blessés.',
                'en' => 'Twelve bodies were recovered and forty-five people were reported missing, making about 57 dead. The article does not mention any injured.',
                'de' => 'Zwölf Leichen wurden geborgen, fünfundvierzig Personen gelten als vermisst, was etwa 57 Tote ergibt. Verletzte erwähnt der Artikel nicht.',
                'sources' => ['wp-de'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'La cause décrite est l\'absence d\'information du mécanicien sur la destruction du pont : de nuit, il n\'a pas vu la voie coupée, ou trop tard.',
                'en' => 'The cause described is that the driver had not been told the bridge was destroyed: at night he did not see the broken line, or saw it too late.',
                'de' => 'Als Ursache wird beschrieben, dass der Lokführer über die Zerstörung der Brücke nicht informiert war: Nachts sah er die unterbrochene Strecke nicht oder zu spät.',
                'sources' => ['wp-de'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Les sources ne s\'accordent pas sur le lieu ni sur la date. Un document d\'un musée ferroviaire allemand décrit un accident très proche (train sanitaire tombé dans la Marne sur un pont détruit par les Français, environ 70 morts sur 700 personnes) à Mary-sur-Marne en Seine-et-Marne, le 14 septembre 1914, vers 23 h 45. Les deux récits se ressemblent (train sanitaire de nuit, pont détruit par l\'armée française sans avertir le personnel, chute dans la Marne), mais rien ne permet de les identifier : l\'article allemand de Sarry ne cite que Stockert (1920), nomme une ligne et un département différents (Châlons-en-Champagne à Reims, Marne), donne une autre date (17 septembre) et un autre bilan (12 corps et 45 disparus, soit environ 57 morts), et aucune source française ni le musée allemand ne mentionne Sarry. La fiche est donc gardée séparée de celle de Lizy-sur-Ourcq tant qu\'aucune source ne tranche.',
                'en' => 'Sources disagree on both place and date. A document from a German railway museum describes a very similar accident (a hospital train that fell into the Marne at a bridge destroyed by the French, about 70 dead out of 700 people) at Mary-sur-Marne in Seine-et-Marne on 14 September 1914 at about 11.45 pm. The two accounts resemble each other (night hospital train, bridge destroyed by the French army without warning the railway staff, fall into the Marne), but nothing allows them to be identified: the German article on Sarry cites only Stockert (1920), names a different line and département (Châlons-en-Champagne to Reims, Marne), gives another date (17 September) and another toll (12 bodies and 45 missing, about 57 dead), and neither any French source nor the German museum mentions Sarry. The record is therefore kept separate from Lizy-sur-Ourcq until a source settles it.',
                'de' => 'Die Quellen widersprechen sich bei Ort und Datum. Ein Dokument eines deutschen Eisenbahnmuseums beschreibt einen sehr ähnlichen Unfall (ein Lazarettzug, der an einer von den Franzosen zerstörten Brücke in die Marne stürzte, etwa 70 Tote unter 700 Personen) in Mary-sur-Marne in Seine-et-Marne am 14. September 1914 gegen 23.45 Uhr. Die beiden Darstellungen ähneln sich (nächtlicher Lazarettzug, von der französischen Armee ohne Warnung des Bahnpersonals zerstörte Brücke, Sturz in die Marne), lassen sich aber nicht gleichsetzen: Der deutsche Artikel über Sarry stützt sich nur auf Stockert (1920), nennt eine andere Strecke und ein anderes Département (Châlons-en-Champagne-Reims, Marne), ein anderes Datum (17. September) und eine andere Opferzahl (12 Leichen und 45 Vermisste, etwa 57 Tote), und weder eine französische Quelle noch das deutsche Museum erwähnt Sarry. Der Datensatz bleibt daher von Lizy-sur-Ourcq getrennt, bis eine Quelle die Frage klärt.',
                'sources' => ['lette-2014', 'wp-de'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'kind',
            'value' => 'war_action',
            'sources' => ['wp-de'],
            'fr' => "L'armée française avait fait sauter le pont à titre préventif, sans prévenir le personnel ferroviaire : c'est un fait de guerre, pas une rupture d'ouvrage.",
            'en' => 'The French army had blown up the bridge as a precaution, without warning the railway staff: this is an act of war, not a structural failure.',
            'de' => 'Die französische Armee hatte die Brücke vorsorglich gesprengt, ohne das Eisenbahnpersonal zu warnen: Es handelt sich um eine Kriegseinwirkung, nicht um ein Bauwerksversagen.',
        ],
    ],
    'images' => [],
];
