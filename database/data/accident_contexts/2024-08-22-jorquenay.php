<?php

// Contexte sourcé de du déraillement d'un train de fret à la sortie de la base logistique de Jorquenay (Haute-Marne) du 22 août 2024 (recherche du 23/11/2026).
return [
    'slug' => '2024-08-22-jorquenay',
    'checked_on' => '2026-11-23',
    'sources' => [
        'bea-tt-2024-08-22-jorquenay' => [
            'provider' => 'bea-tt',
            'kind' => 'report',
            'reliability' => 'official',
            'title' => 'Déraillement d\'un train (Supply Chain) sur voie principale en sortie du faisceau de Jorquenay (52)',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'fiche de présentation',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/IMG/pdf/beatt_2024_05_langres_note_d_etape_2.pdf',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Note d\'information sur l\'avancement de l\'enquête (octobre 2025) : faits et premiers constats.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 22 août 2024, le train n° 417 754 de Langres à Tergnier, composé de deux locomotives en unité multiple, d\'une machine en véhicule et de 32 wagons, s\'est mis en mouvement vers 4 h 53 depuis la voie 3 du faisceau de Jorquenay en direction de Chaumont. L\'opérateur au sol, qui surveillait le défilé, a perçu un bruit sourd, un dégagement de fumée et le soulèvement d\'un wagon, et a fait arrêter le train. Deux wagons avaient déraillé et causé des dégâts aux voies principales et aux voies de service, dont la circulation a été totalement interrompue.',
                'en' => 'On 22 August 2024 train no. 417 754 from Langres to Tergnier, made up of two locomotives in multiple, a machine carried as a vehicle and 32 wagons, set off at about 4.53 am from track 3 of the Jorquenay sidings towards Chaumont. The ground operator, who was watching the train go by, heard a dull noise, saw smoke and a wagon lifting, and had the train stopped. Two wagons had derailed and damaged the main and service tracks, on which traffic was fully interrupted.',
                'de' => 'Am 22. August 2024 setzte sich der Zug Nr. 417 754 von Langres nach Tergnier, bestehend aus zwei Lokomotiven in Vielfachsteuerung, einer als Fahrzeug beförderten Maschine und 32 Wagen, gegen 4.53 Uhr von Gleis 3 der Gleisgruppe Jorquenay in Richtung Chaumont in Bewegung. Der Bodenposten, der die Vorbeifahrt überwachte, nahm ein dumpfes Geräusch, Rauch und das Anheben eines Wagens wahr und ließ den Zug anhalten. Zwei Wagen waren entgleist und hatten die Haupt- und Nebengleise beschädigt, auf denen der Verkehr völlig unterbrochen wurde.',
                'sources' => ['bea-tt-2024-08-22-jorquenay'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Les premiers constats montrent qu\'une cale anti-dérive est restée coincée dans le coeur d\'une aiguille : le train a déraillé faute d\'avoir retiré cette cale d\'immobilisation sous la roue d\'un wagon. Le relevage a duré de 8 h 30 à 21 h 40 et la circulation sur les voies principales n\'a repris qu\'à 22 h 25. Les dommages et pertes d\'exploitation avoisinent deux millions d\'euros. Le BEA-TT, dans une note d\'avancement d\'octobre 2025, oriente ses investigations vers des facteurs techniques et organisationnels (capacité de retenue et usage des cales, consignes d\'expédition, sous-traitance) et a reporté la suite de l\'enquête de quelques mois, l\'exploitation du site passant à Captrain en janvier 2026.',
                'en' => 'The first findings show that an anti-runaway chock stayed jammed in the frog of a set of points: the train derailed because such an immobilisation chock had not been removed from under a wagon wheel. The recovery lasted from 8.30 am to 9.40 pm and traffic on the main tracks only resumed at 10.25 pm. Damage and operating losses come to about two million euros. The BEA-TT, in a progress note of October 2025, is directing its investigation towards technical and organisational factors (holding capacity and use of chocks, dispatch instructions, subcontracting) and has put off the rest of the inquiry by a few months, as operation of the site passes to Captrain in January 2026.',
                'de' => 'Die ersten Feststellungen zeigen, dass ein Hemmschuh im Herzstück einer Weiche eingeklemmt blieb: Der Zug entgleiste, weil ein solcher Festhaltekeil nicht unter dem Rad eines Wagens entfernt worden war. Die Bergung dauerte von 8.30 Uhr bis 21.40 Uhr, und der Verkehr auf den Hauptgleisen lief erst um 22.25 Uhr wieder. Schäden und Betriebsausfälle belaufen sich auf etwa zwei Millionen Euro. Die BEA-TT lenkt ihre Untersuchung in einer Zwischennotiz vom Oktober 2025 auf technische und organisatorische Faktoren (Haltekraft und Einsatz der Keile, Abfertigungsvorschriften, Unterauftragsvergabe) und hat die Fortsetzung um einige Monate verschoben, da der Betrieb des Standorts im Januar 2026 an Captrain übergeht.',
                'sources' => ['bea-tt-2024-08-22-jorquenay'],
            ],
        ],
    ],
];
