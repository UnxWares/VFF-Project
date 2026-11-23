<?php

/**
 * Corrections SOURCÉES de l'électrification par portion (20/11/2026). Même format que
 * line_event_corrections.php. La tension d'un événement est lue dans sa citation
 * (ElectrificationText::voltage) : la citation retenue doit donc nommer la bonne tension.
 *
 * Les chronologies d'électrification de toute une ligne (troisième rail, tensions successives,
 * mise hors tension) se corrigent dans App\Services\Traction\TractionCuration.
 *
 * @return list<array<string, mixed>>
 */
$valenceMoirans = 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Valence_%C3%A0_Moirans&oldid=241124486';

return [

    // 908000 Valence - Moirans. En 1994 (LGV Rhône-Alpes), seule la section de Valence-Ville à la LGV
    // est électrifiée : 1 500 V continu jusqu'à la section de séparation du PK 2,2, puis 25 kV 50 Hz
    // jusqu'à Saint-Marcel-lès-Valence. Romans - Saint-Marcel ne l'est qu'en 2011-2014 (événements
    // 9297, 9300, 9301). Le PK de Valence-Ville (1 179) et celui de la séparation (2 250) sont ceux du
    // jeu SNCF « électrification des lignes » ; Saint-Marcel-lès-Valence est au PK 9 760 (gares SNCF).
    //
    // L'événement Wikipédia « Romans à Valence-Ville » (9292) vient d'une phrase sur la période des
    // travaux de 2009-2013 qui parle du tronçon de Valence-Ville à la LGV ; il était lu en 1 500 V sur
    // les 9 km de Romans à Valence TGV. Il est neutralisé et remplacé par l'électrification de 1994 sur
    // sa vraie portion.
    [
        'line' => '908000',
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'electrification', 'date' => '1994-01-01', 'from' => 'Romans'],
        'label' => 'Électrification de 1994 entre Valence-Ville et la LGV Rhône-Alpes (phrase sur les travaux de 2009-2013)',
        'source_url' => $valenceMoirans,
        'quote' => 'Seuls circulent normalement les TGV transitant entre la LGV Rhône-Alpes et la gare de Valence-Ville, ce tronçon ne faisant pas l\'objet de travaux (modernisation totale, BAL, IPCS, électrification 1500 V continu et 25 kV) réalisés en 1994)',
        'reason' => 'la phrase vise le tronçon de Valence-Ville à la LGV, pas Romans - Valence TGV, électrifié seulement de 2011 à 2014',
    ],
    [
        'line' => '908000',
        'action' => 'add',
        'type' => 'electrification',
        'date' => '1994-01-01',
        'precision' => 'year',
        'from' => 'Valence-Ville',
        'to' => 'la séparation du PK 2,2',
        'pk_start' => 1179,
        'pk_end' => 2250,
        'source_url' => $valenceMoirans,
        'quote' => 'la jonction avec le 1,5 kV CC à Valence-Ville est existante depuis 1994',
        'reason' => '1 500 V continu de Valence-Ville à la section de séparation du PK 2,2, posée avec la LGV Rhône-Alpes',
    ],
    [
        'line' => '908000',
        'action' => 'relocate',
        'match' => ['source' => 'wikipedia', 'type' => 'electrification', 'date' => '1994-01-01', 'from' => 'Valence'],
        'from' => 'Valence',
        'to' => 'Saint-Marcel-lès-Valence',
        'pk_start' => 2250,
        'pk_end' => 9760,
        'source_url' => $valenceMoirans,
        'quote' => 'la ligne est électrifiée en 25 kV 50 Hz sur une dizaine de kilomètres, entre Valence et Saint-Marcel-lès-Valence (peu avant la gare de Valence TGV), avec une section de séparation entre 25000 volts alternatif et 1500 V continu au PK 2.2',
        'reason' => '25 kV 50 Hz de la séparation du PK 2,2 à Saint-Marcel-lès-Valence ; « Valence » était lu comme la gare de Valence TGV (731 m seulement)',
    ],

    // 366000 Mantes - Cherbourg. L'article ne dit pas que Bernay - Lisieux est électrifié en 1989 : la ligne de la
    // chronologie annonce « début des travaux préparatoires à l'électrification » (suppression de passages à
    // niveau, rectification de tracés), et le corps de l'article précise que les travaux d'électrification
    // « seront engagés » à partir de 1989. La mise sous tension est de 1996 (27 mars de Bueil à Cherbourg,
    // 21 mai de Mantes-la-Jolie à Bueil, mise en service le 1er juin 1996 : « entièrement électrifiée depuis le
    // 1 juin 1996 »). L'événement de 1996 de l'import est donc juste (jour) ; seul celui de 1989 est faux.
    // Il bornait à tort le matériel électrique de la fiche de ligne à 1989 (ElectrificationBound).
    [
        'line' => '366000',
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'electrification', 'date' => '1989-01-01', 'from' => 'Bernay'],
        'label' => 'Début des travaux préparatoires à l\'électrification de Bernay à Lisieux (1989)',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Mantes-la-Jolie_%C3%A0_Cherbourg&oldid=241344631',
        'quote' => 'début des travaux préparatoires à l\'électrification de la ligne (suppression des passages à niveau, rectification de certains tracés de Bernay à Lisieux)',
        'reason' => 'travaux préparatoires de 1989, pas une mise sous tension ; l\'électrification de la ligne est mise en service le 1er juin 1996',
    ],

    // Événements « électrification » importés de Wikipédia qui sont en réalité le début ou le lancement de
    // travaux, un décret d'utilité publique ou la fermeture d'une gare (relus contre leur article le 23/11/2026).
    // Ils bornaient à tort le matériel électrique de la fiche de ligne (ElectrificationBound, fiabilité
    // medium) ou s'affichaient comme des électrifications dans la chronologie. Neutralisés ; les vraies mises
    // sous tension de ces lignes sont d'autres événements, à leur date. Gardés, car la source parle de
    // l'achèvement des travaux : 900000 « Chambéry - Modane, 1930 » (id 6392, aussi dans Wikidata) et
    // 946000 « travaux achevés en 1935 » (id 6238).
    [
        'line' => '001000',
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'electrification', 'date' => '2019-01-03', 'from' => 'Gretz-Armainvilliers'],
        'label' => 'Début des travaux d\'électrification de Gretz-Armainvilliers à Troyes (2019)',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Paris-Est_%C3%A0_Mulhouse-Ville&oldid=235342496',
        'quote' => 'Dès le 3 janvier 2019, les travaux d\'électrification de la ligne ont commencé sur les 128 km à double voie entre Gretz-Armainvilliers et Troyes, ainsi que sur l\'antenne de 7 km à voie unique de Longueville à Provins.',
        'reason' => 'début des travaux le 3 janvier 2019, pas une mise sous tension ; la mise en service vers Nogent-sur-Seine est du 3 juillet 2022 (autre événement)',
    ],
    [
        'line' => '884000',
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'electrification', 'date' => '2005-01-31'],
        'label' => 'Décret d\'utilité publique des travaux de modernisation et d\'électrification de Bourg-en-Bresse à Bellegarde (2005)',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Bourg-en-Bresse_%C3%A0_Bellegarde&oldid=240169436',
        'quote' => 'Après la parution d\'un décret, le 31 janvier 2005, déclarant « d\'utilité publique et urgents les travaux nécessaires à la modernisation et à l\'électrification de la ligne ferroviaire de Bourg-en-Bresse à Bellegarde-sur-Valserine »',
        'reason' => 'décret de déclaration d\'utilité publique, pas une électrification ; la mise sous tension est de 2010 (Wikidata)',
    ],
    [
        'line' => '311000',
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'electrification', 'date' => '2009-01-01', 'from' => 'Rang-du-Fliers - Verton - Berck'],
        'label' => 'Début des travaux d\'électrification de Rang-du-Fliers à Boulogne-Ville (printemps 2009)',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Longueau_%C3%A0_Boulogne-Ville&oldid=235223334',
        'quote' => 'Durant le printemps 2009, des travaux d\'électrification, sur la section entre la gare de Rang-du-Fliers - Verton - Berck et la gare de Boulogne-Ville, ont été entrepris en vue d\'augmenter le trafic.',
        'reason' => 'travaux entrepris au printemps 2009, pas une mise sous tension ; la mise en service de Boulogne à Rang-du-Fliers est du 10 décembre 2010 (autre événement)',
    ],
    [
        'line' => '810000',
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'electrification', 'date' => '1943-01-01', 'from' => 'Sète'],
        'label' => 'Début des travaux d\'électrification de Sète à Nîmes-Courbessac (1943)',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Tarascon_%C3%A0_S%C3%A8te-Ville&oldid=236799571',
        'quote' => 'Les travaux pour l\'électrification en 1500 volts continu débutent en 1943 sur la section de Sète à Nîmes-Courbessac, durant la Seconde Guerre mondiale.',
        'reason' => 'les travaux débutent en 1943 : début de chantier, pas une mise sous tension',
    ],
    [
        'line' => '834100',
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'electrification', 'date' => '2000-01-01'],
        'label' => 'Lancement des travaux d\'électrification de la LGV Méditerranée (2000)',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=LGV_M%C3%A9diterran%C3%A9e&oldid=240289807',
        'quote' => 'Les travaux d\'électrification sont lancés dans la foulée, avec pour objectif de mettre sous tension les sections nord et sud de la ligne durant l\'automne 2000, la jonction centrale en décembre',
        'reason' => 'l\'article annonce le lancement des travaux et des objectifs de mise sous tension, sans dater la mise sous tension effective',
    ],
    [
        'line' => '886000',
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'electrification', 'date' => '1953-01-01', 'from' => 'Lyon-Saint-Clair'],
        'label' => 'Fin des travaux des sauts-de-mouton de Lyon-Saint-Clair à Lyon-Brotteaux et fermeture aux voyageurs de Lyon-Croix-Rousse (1953)',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Lyon-Saint-Clair_%C3%A0_Bourg-en-Bresse&oldid=236080081',
        'quote' => 'L\'utilisation de la gare de Lyon-Croix-Rousse pour le service des voyageurs cesse en 1953 à l\'issue des travaux d\'électrification du secteur Lyonnais (achèvement des sauts-de-mouton situés entre Lyon-Saint-Clair et Lyon-Brotteaux).',
        'reason' => 'la phrase date la fermeture d\'une gare aux voyageurs et l\'achèvement de sauts-de-mouton, pas une mise sous tension ; l\'électrification de la ligne est de 1981 (autre événement)',
    ],
    [
        'line' => '905000',
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'electrification', 'date' => '1979-01-01'],
        'label' => 'Début des travaux d\'électrification de Lyon à Grenoble (1979)',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Lyon-Perrache_%C3%A0_Marseille-Saint-Charles_%28via_Grenoble%29&oldid=241143131',
        'quote' => 'En 1979, la section Lyon-Grenoble de la ligne a connu un important bouleversement avec le début de travaux d\'électrification',
        'reason' => 'début de travaux en 1979, pas une mise sous tension',
    ],
    [
        'line' => '908000',
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'electrification', 'date' => '2012-01-01'],
        'label' => 'Début des travaux du raccordement de Valence - Moirans à la LGV Méditerranée (printemps 2012)',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Valence_%C3%A0_Moirans&oldid=241124486',
        'quote' => 'Les travaux du raccordement entre la ligne Valence-Moirans et la LGV méditerranée ont débuté durant le printemps 2012, avec l\'électrification 25 kV.',
        'reason' => 'début des travaux au printemps 2012, pas une mise sous tension ; l\'électrification est mise sous tension du 30 octobre au 20 novembre 2013 (autre événement)',
    ],
    [
        'line' => '974000',
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'electrification', 'date' => '1974-01-01'],
        'label' => 'Début des travaux de ré-électrification du groupe II de Saint-Cloud à Saint-Nom-la-Bretèche (1974)',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Saint-Cloud_%C3%A0_Saint-Nom-la-Bret%C3%A8che_-_For%C3%AAt-de-Marly&oldid=240844633',
        'quote' => 'Les travaux de ré-électrification sont engagés en 1974 et imposent de nombreuses modifications de la ligne',
        'reason' => 'travaux engagés en 1974, pas une mise sous tension ; les mises en service en 25 kV sont de 1977 et 1978 (autres événements)',
    ],
];
