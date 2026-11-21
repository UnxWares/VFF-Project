<?php

// Contexte sourcé de la collision d'un train de banlieue contre le heurtoir de la gare de l'Est du 6 août 1988 (recherche du 21/11/2026).
return [
    'slug' => '1988-08-06-paris',
    'checked_on' => '2026-11-21',
    'sources' => [
        'presse-est-a' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Weer treinongeluk in Parijs: één dode',
            'publisher' => 'Presse néerlandaise (Delpher)',
            'author' => null,
            'published' => '1988-08-08',
            'locator' => null,
            'url' => 'https://resolver.kb.nl/resolve?urn=ABCDDD:010826719:mpeg21:a0052',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Presse néerlandaise numérisée par la Koninklijke Bibliotheek (Delpher).',
        ],
        'presse-est-b' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Opnieuw treinongeluk op station in Parijs Directeur van Franse spoorwegen stapt op',
            'publisher' => 'Presse néerlandaise (Delpher)',
            'author' => null,
            'published' => '1988-08-08',
            'locator' => null,
            'url' => 'https://resolver.kb.nl/resolve?urn=ddd:010611773:mpeg21:a0017',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Presse néerlandaise numérisée par la Koninklijke Bibliotheek (Delpher).',
        ],
        'presse-est-c' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Baas Franse spoorwegen stapt op na 2e treinramp',
            'publisher' => 'Presse néerlandaise (Delpher)',
            'author' => null,
            'published' => '1988-08-08',
            'locator' => null,
            'url' => 'https://resolver.kb.nl/resolve?urn=ABCDDD:010832703:mpeg21:a0106',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Presse néerlandaise numérisée par la Koninklijke Bibliotheek (Delpher).',
        ],
        'wp-liste-1980' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1980',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1988',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1980&oldid=240619561#1988',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Source de la fiche : train de banlieue arrivant en freinage sans coupure de la traction sur le heurtoir de la gare de l\'Est, 1 mort ; condamnation du mécanicien ; asservissement traction-freinage généralisé.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le samedi 6 août 1988 dans l\'après-midi, un train de banlieue en provenance de Château-Thierry est entré en gare de l\'Est, à Paris, sans pouvoir ralentir. Le conducteur n\'avait rien remarqué d\'anormal pendant le trajet jusqu\'à l\'arrivée en gare, où il a constaté qu\'il ne pouvait pas freiner. L\'enregistreur du train a montré qu\'il avait bien freiné et que la vitesse avait diminué, mais que la locomotive avait continué à entraîner le train.',
                'en' => 'On the afternoon of Saturday 6 August 1988, a suburban train from Château-Thierry entered the Gare de l\'Est in Paris unable to slow down. The driver had noticed nothing unusual on the journey until arriving at the station, where he found he could not brake. The train\'s recorder showed that he had indeed braked and that speed had dropped, but that the locomotive had gone on driving the train.',
                'de' => 'Am Nachmittag des Samstags, dem 6. August 1988, fuhr ein Vorortzug aus Château-Thierry in den Gare de l\'Est in Paris ein, ohne langsamer werden zu können. Der Fahrer hatte auf der Fahrt bis zur Einfahrt in den Bahnhof nichts Besonderes bemerkt und stellte dort fest, dass er nicht bremsen konnte. Der Fahrtschreiber zeigte, dass er tatsächlich gebremst hatte und die Geschwindigkeit gesunken war, die Lokomotive den Zug aber weiter angetrieben hatte.',
                'sources' => ['presse-est-a', 'presse-est-b', 'wp-liste-1980'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Le train a heurté le heurtoir à une vitesse d\'environ 20 km/h. Les voyageurs de la première voiture ont été comprimés par la force du choc ; certains sont restés coincés plusieurs heures avant d\'être dégagés. Le conducteur est sorti indemne, selon la presse néerlandaise.',
                'en' => 'The train hit the buffer stop at about 20 km/h. Passengers in the first carriage were crushed together by the force of the impact; some remained trapped for several hours before being freed. The driver was unhurt, according to the Dutch press.',
                'de' => 'Der Zug prallte mit etwa 20 km/h auf den Prellbock. Die Fahrgäste im ersten Wagen wurden durch die Wucht des Aufpralls zusammengedrückt; einige blieben mehrere Stunden eingeklemmt, bevor sie befreit werden konnten. Der Fahrer blieb nach Angaben der niederländischen Presse unverletzt.',
                'sources' => ['presse-est-a', 'presse-est-c'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Un voyageur a été tué et 56 ou 57 personnes ont été blessées, dont huit gravement selon l\'agence AFP citée par la presse néerlandaise. Quatre blessés étaient encore hospitalisés le lendemain hors de danger, d\'après le correspondant d\'un quotidien néerlandais.',
                'en' => 'One passenger was killed and 56 or 57 people were injured, eight of them seriously according to the AFP agency cited by the Dutch press. Four of the injured were still in hospital the next day, out of danger, according to a Dutch daily\'s correspondent.',
                'de' => 'Ein Fahrgast wurde getötet und 56 oder 57 Personen verletzt, davon acht schwer nach der von der niederländischen Presse zitierten Agentur AFP. Vier Verletzte lagen am Folgetag noch außer Lebensgefahr im Krankenhaus, nach dem Korrespondenten einer niederländischen Tageszeitung.',
                'sources' => ['presse-est-a', 'presse-est-b', 'presse-est-c'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'L\'accident est survenu pour la seconde fois en six semaines dans une gare parisienne, après l\'accident de la gare de Lyon de fin juin. Le président de la SNCF, Philippe Rouvillois, a donné sa démission, acceptée par le ministre des Transports, Michel Delebarre, tandis que les syndicats reprochaient à la SNCF et au gouvernement de compromettre la sécurité par recherche du profit.',
                'en' => 'The accident was the second in six weeks at a Paris station, after the Gare de Lyon accident at the end of June. The SNCF president, Philippe Rouvillois, resigned, his resignation being accepted by the transport minister, Michel Delebarre, while the unions accused SNCF and the government of endangering safety in pursuit of profit.',
                'de' => 'Der Unfall war der zweite innerhalb von sechs Wochen in einem Pariser Bahnhof, nach dem Unfall im Gare de Lyon Ende Juni. Der SNCF-Präsident Philippe Rouvillois trat zurück, sein Rücktritt wurde vom Verkehrsminister Michel Delebarre angenommen, während die Gewerkschaften der SNCF und der Regierung vorwarfen, die Sicherheit aus Gewinnstreben zu gefährden.',
                'sources' => ['presse-est-a', 'presse-est-b', 'presse-est-c'],
            ],
            [
                'fr' => 'Selon la liste de Wikipédia, le mécanicien a été condamné à quinze mois de prison avec sursis, et l\'asservissement traction-freinage a été généralisé sur les engins qui n\'en étaient pas équipés.',
                'en' => 'According to the Wikipedia list, the driver was sentenced to fifteen months\' suspended prison, and the traction-braking interlock was made universal on locomotives not yet fitted with it.',
                'de' => 'Nach der Wikipedia-Liste wurde der Lokführer zu fünfzehn Monaten Haft auf Bewährung verurteilt, und die Verriegelung von Antrieb und Bremse wurde bei allen noch nicht damit ausgerüsteten Triebfahrzeugen eingeführt.',
                'sources' => ['wp-liste-1980'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le nombre de blessés varie dans la presse du 8 août 1988 : 56 (agences DPA et AFP) ou 57 (Reuter et UPI, correspondant d\'un quotidien). La liste de Wikipédia ne donne que le mort.',
                'en' => 'The number of injured varies in the press of 8 August 1988: 56 (DPA and AFP agencies) or 57 (Reuter and UPI, a daily\'s correspondent). The Wikipedia list gives only the death.',
                'de' => 'Die Zahl der Verletzten schwankt in der Presse vom 8. August 1988: 56 (Agenturen DPA und AFP) oder 57 (Reuter und UPI, Korrespondent einer Tageszeitung). Die Wikipedia-Liste nennt nur den Toten.',
                'sources' => ['presse-est-a', 'presse-est-b', 'presse-est-c', 'wp-liste-1980'],
            ],
        ],
    ],
    'corrections' => [
    ],
];
