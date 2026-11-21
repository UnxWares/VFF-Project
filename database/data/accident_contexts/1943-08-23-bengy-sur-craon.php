<?php

// Contexte sourcé de l'accident de Bengy-sur-Craon du 23 août 1943 (recherche du 21/11/2026).
return [
    'slug' => '1943-08-23-bengy-sur-craon',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1940' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1940',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1943, 23 août, Bengy',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1940&oldid=239153903#1943',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée d\'une phrase, sourcée par un dossier des archives historiques de la SNCF (cote 0084LM0002-011, document non ouvert) ; heure précise, cause (sabotage) et bilan.',
        ],
        'sncf-archives-0084lm0002' => [
            'provider' => 'sites-web',
            'kind' => 'archive',
            'reliability' => 'official',
            'title' => 'Archives historiques de la SNCF, dossier 0084LM0002-011',
            'publisher' => 'SNCF, archives historiques',
            'author' => null,
            'published' => '1943',
            'locator' => 'cote 0084LM0002-011',
            'url' => 'http://www.archives-historiques.sncf.fr/pdf/0084LM0002-011.pdf',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Dossier cité par la liste de Wikipédia pour cet accident ; le lien est mort (réponse vide), le contenu n\'a pas pu être lu.',
        ],
        'ahicf-carriere-1943' => [
            'provider' => 'sites-web',
            'kind' => 'scholarly',
            'reliability' => 'scholarly',
            'title' => '1943 : mécaniciens et chauffeurs sous la mitraille',
            'publisher' => 'Rails & histoire (AHICF)',
            'author' => 'Bruno Carrière',
            'published' => '2023-05-10',
            'locator' => null,
            'url' => 'https://www.ahicf.com/post/1943-m%C3%A9caniciens-et-chauffeurs-sous-la-mitraille',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Article sur les cheminots morts dans les sabotages de 1943 et 1944 ; il décrit la méthode (rail déboulonné) mais ne mentionne pas Bengy.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 23 août 1943, à 4 h 37, sur la ligne de Bourges à Saincaize (partie de la ligne de Vierzon à Saincaize), près de la gare de Bengy (Cher), un train de marchandises a déraillé quelques secondes avant de croiser un train de voyageurs.',
                'en' => 'On 23 August 1943, at 4.37 am, on the Bourges to Saincaize line (part of the Vierzon to Saincaize line), near Bengy station (Cher), a goods train derailed a few seconds before passing a passenger train.',
                'de' => 'Am 23. August 1943 entgleiste um 4.37 Uhr auf der Strecke Bourges-Saincaize (Teil der Strecke Vierzon-Saincaize) nahe dem Bahnhof Bengy (Cher) ein Güterzug wenige Sekunden, bevor er einem Personenzug begegnete.',
                'sources' => ['wp-liste-1940'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Les premières voitures du train de voyageurs ont été éventrées par la locomotive du train de marchandises, dont les wagons se sont renversés.',
                'en' => 'The first coaches of the passenger train were torn open by the locomotive of the goods train, whose wagons overturned.',
                'de' => 'Die ersten Wagen des Personenzugs wurden von der Lokomotive des Güterzugs aufgerissen, dessen Wagen umstürzten.',
                'sources' => ['wp-liste-1940'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'L\'accident a fait onze morts et vingt-deux blessés, selon la liste de Wikipédia.',
                'en' => 'The accident caused eleven deaths and twenty-two injuries, according to the Wikipedia list.',
                'de' => 'Der Unfall forderte laut der Wikipedia-Liste elf Tote und zweiundzwanzig Verletzte.',
                'sources' => ['wp-liste-1940'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'Le déraillement est attribué à un sabotage : un rail avait été déboulonné. La liste de Wikipédia ne dit pas qui en est l\'auteur et aucun autre document lu ne le précise.',
                'en' => 'The derailment is attributed to sabotage: a rail had been unbolted. The Wikipedia list does not say who did it and no other document read names the perpetrators.',
                'de' => 'Die Entgleisung wird einer Sabotage zugeschrieben: Eine Schiene war gelöst worden. Die Wikipedia-Liste nennt keinen Täter, und kein anderes gelesenes Dokument benennt die Urheber.',
                'sources' => ['wp-liste-1940'],
            ],
            [
                'fr' => 'Le déboulonnage de rails était alors une méthode courante : un historien des chemins de fer cite l\'accident de Tournus du 31 octobre 1943, un express Marseille - Paris déraillé sur un rail déboulonné (11 morts), et rappelle que ces sabotages provoquaient aussi des prises en écharpe par des trains suiveurs ou croiseurs. Cet article ne parle pas de Bengy.',
                'en' => 'Unbolting rails was then a common method: a railway historian cites the Tournus accident of 31 October 1943, a Marseille - Paris express derailed on an unbolted rail (11 dead), and recalls that such sabotage also caused side-swipe collisions with following or crossing trains. That article does not mention Bengy.',
                'de' => 'Das Lösen von Schienen war damals ein gängiges Verfahren: Ein Eisenbahnhistoriker nennt den Unfall von Tournus am 31. Oktober 1943, einen auf einer gelösten Schiene entgleisten Schnellzug Marseille - Paris (11 Tote), und erinnert daran, dass solche Sabotageakte auch Streifkollisionen mit nachfolgenden oder kreuzenden Zügen auslösten. Dieser Artikel erwähnt Bengy nicht.',
                'sources' => ['ahicf-carriere-1943'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Nature : la fiche classe l\'accident comme un déraillement ; il s\'agit d\'un déraillement provoqué par sabotage, suivi d\'un croisement avec un train de voyageurs, ce qui pourrait relever d\'un autre genre (acte de malveillance). Une seule source lue (la liste de Wikipédia) décrit l\'accident ; le dossier SNCF qu\'elle cite n\'a pas pu être ouvert, la fiche reste donc inchangée.',
                'en' => 'Nature: the record classes the accident as a derailment; it was a derailment caused by sabotage, followed by a passing with a passenger train, which might belong to another category (malicious act). Only one source read (the Wikipedia list) describes the accident; the SNCF file it cites could not be opened, so the record is left unchanged.',
                'de' => 'Art: Der Datensatz stuft den Unfall als Entgleisung ein; es handelte sich um eine durch Sabotage ausgelöste Entgleisung, auf die die Begegnung mit einem Personenzug folgte, was einer anderen Kategorie (böswillige Tat) zugeordnet werden könnte. Nur eine gelesene Quelle (die Wikipedia-Liste) beschreibt den Unfall; die dort zitierte SNCF-Akte konnte nicht geöffnet werden, der Datensatz bleibt daher unverändert.',
                'sources' => ['wp-liste-1940'],
            ],
        ],
    ],
    'corrections' => [],
];
