<?php

/**
 * Complément du matériel roulant préservé (même forme que preserved_rolling_stock.php, qu'il complète sans le modifier) :
 * lu et fusionné par App\Services\Materials\PreservedRollingStock. Un exemplaire déjà connu (même série, même numéro) ne reçoit
 * que ce qui lui manquait (lieu, état, protection, sources), un nouveau est ajouté.
 *
 * Sources, faits seulement (numéro, lieu, état dit par la source, protection ; jamais d'état deviné) :
 *   - Wikipédia en français (CC BY-SA 4.0), articles « Matériel moteur préservé de la SNCF » (version 241069362) et « Cité du train »
 *     (version 241565103), lus par l'API MediaWiki le 23/11/2026 (User-Agent du projet, 5 s entre deux requêtes) ; un exemplaire que
 *     la liste dit ferraillé, démantelé ou vendu n'est pas repris ; la série est celle de la section ou du lien de l'article ;
 *   - Palissy (POP, ministère de la Culture, Licence Ouverte 2.0) : date et nature de la protection des notices que Wikipédia cite
 *     (référence PM…), lues dans le fichier palissy.csv du 13/11/2026.
 *
 * @return array{venues: array<string, array<string, mixed>>, units: array<string, list<array<string, mixed>>>, others: list<array<string, mixed>>}
 */
return [
    'venues' => [
        'auberge-du-chemin-de-fer' => ['name' => 'Auberge du Chemin de Fer', 'type' => 'tourist_railway', 'commune' => null, 'tourist_railway' => null],
        'cercle-ferroviaire-des-territoires-de-sologne-et-touraine-cftst' => ['name' => 'Cercle Ferroviaire des Territoires de Sologne et Touraine (CFTST)', 'type' => 'association', 'commune' => null, 'tourist_railway' => null],
        'chemin-de-fer-touristique-du-vermandois-cftv' => ['name' => 'Chemin de fer touristique du Vermandois (CFTV)', 'type' => 'tourist_railway', 'commune' => null, 'tourist_railway' => null],
        'chemins-de-fer-de-la-haute-auvergne-cfha' => ['name' => 'Chemins de Fer de la Haute Auvergne (CFHA)', 'type' => 'tourist_railway', 'commune' => null, 'tourist_railway' => null],
        'musee-de-la-mine-sur-le-site-du-carreau-wendel' => ['name' => 'Musée de la Mine sur le site du Carreau Wendel', 'type' => 'museum', 'commune' => null, 'tourist_railway' => null],
        'parc-naturel-regional-des-landes-de-gascogne-a-l-ecomusee-de-marqueze' => ['name' => 'Parc naturel régional des Landes de Gascogne à l\'Écomusée de Marquèze', 'type' => 'museum', 'commune' => null, 'tourist_railway' => null],
        'train-des-mouettes-tdm' => ['name' => 'Train des Mouettes (TdM)', 'type' => 'tourist_railway', 'commune' => null, 'tourist_railway' => null],
        'train-du-bas-berry-tbb' => ['name' => 'Train du Bas-Berry (TBB)', 'type' => 'tourist_railway', 'commune' => null, 'tourist_railway' => 'Train du Bas-Berry'],
        'velorail-du-perigord-vert' => ['name' => 'Vélorail du Périgord Vert', 'type' => 'tourist_railway', 'commune' => null, 'tourist_railway' => null],
    ],
    'units' => [
        '030-ouest-1904-a-2244' => [
            ['number' => '030 C 815', 'venue' => 'cite-du-train', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Cité du train', 241565103]]],
        ],
        '030-t-ouest-1011-a-1114' => [
            ['number' => '030 TA 628', 'venue' => 'cite-du-train', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Cité du train', 241565103]]],
        ],
        '030-tx' => [
            ['number' => '030 TX 1', 'venue' => null, 'where' => 'Exposée en monument devant les locaux de l\'Office national des chemins de fer (ONCF) à Rabat au Maroc', 'state' => 'exposed', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
        ],
        '131-t-est-32001-a-32050' => [
            ['number' => '131 TB 31', 'venue' => 'cite-du-train', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Cité du train', 241565103]]],
        ],
        '140-midi-4001-a-4018' => [
            ['number' => '140 Midi 4008', 'venue' => 'cite-du-train', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Cité du train', 241565103]]],
        ],
        '140-nord-4061-a-4340' => [
            ['number' => '140 A 259', 'venue' => 'cite-du-train', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Cité du train', 241565103]]],
        ],
        '141-plm-1-a-680' => [
            ['number' => '141 F 282', 'venue' => 'cite-du-train', 'where' => null, 'state' => 'stored', 'mh' => null, 'src' => [['wikipedia', 'Cité du train', 241565103]]],
        ],
        '141-t-est-4401-a-4512' => [
            ['number' => '141 TB 424', 'venue' => 'amicale-des-anciens-et-amis-de-la-traction-a-vapeur-aaatv', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Cité du train', 241565103]]],
        ],
        '141-t-po-5301-a-5490' => [
            ['number' => '141 T 5452', 'venue' => 'cite-du-train', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Cité du train', 241565103]]],
        ],
        '150-p' => [
            ['number' => '150 P 13', 'venue' => 'pacific-vapeur-club', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
        ],
        '150-x' => [
            ['number' => '150 X 82', 'venue' => null, 'where' => 'Préservée au Çamlık Railway Museum en Turquie', 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
        ],
        '1abba1-3600' => [
            ['number' => '1ABBA1 3603', 'venue' => 'cite-du-train', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362], ['wikipedia', 'Cité du train', 241565103]]],
        ],
        '221-nord-2641-a-2675' => [
            ['number' => '221 A 30', 'venue' => 'cite-du-train', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Cité du train', 241565103]]],
        ],
        '230-nord-3513-a-3662' => [
            ['number' => '230 D 9', 'venue' => 'chemin-de-fer-touristique-de-la-vallee-de-l-aa-cftva', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Cité du train', 241565103]]],
        ],
        '230-plm-2601-a-2760' => [
            ['number' => '230 B 114', 'venue' => 'cite-du-train', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Cité du train', 241565103]]],
        ],
        '231-g-entre-2-et-285' => [
            ['number' => '231 G 558', 'venue' => null, 'where' => null, 'state' => 'running', 'mh' => ['kind' => 'classified', 'date' => '1984-06-08', 'year' => 1984, 'ref' => 'PM76001891'], 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362], ['palissy', 'PM76001891']]],
        ],
        '231-h-1-a-30' => [
            ['number' => '231 H 8', 'venue' => 'cite-du-train', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362], ['wikipedia', 'Cité du train', 241565103]]],
        ],
        '231-po-4501-a-4600' => [
            ['number' => '231 PO 4546', 'venue' => 'cite-du-train', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Cité du train', 241565103]]],
        ],
        '232-nord-31101-a-31102' => [
            ['number' => '232 Nord 3.1102', 'venue' => 'cite-du-train', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Cité du train', 241565103]]],
        ],
        '241-est-41001' => [
            ['number' => '241 A1', 'venue' => 'cite-du-train', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Cité du train', 241565103]]],
        ],
        '241-p-17' => [
            ['number' => '241 P 17', 'venue' => null, 'where' => null, 'state' => 'running', 'mh' => ['kind' => 'classified', 'date' => '1990-03-27', 'year' => 1990, 'ref' => 'PM71000825'], 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362], ['palissy', 'PM71000825']]],
        ],
        '242-at-plm-1-a-120' => [
            ['number' => '242 AT 6', 'venue' => 'cite-du-train', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Cité du train', 241565103]]],
        ],
        '2cc2-3400' => [
            ['number' => '2CC2 3402', 'venue' => 'association-pour-la-preservation-du-materiel-ferroviaire-savoyard-apmfs', 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
        ],
        '2d2-5500' => [
            ['number' => '2D2 5516', 'venue' => 'cite-du-train', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362], ['wikipedia', 'Cité du train', 241565103]]],
            ['number' => '2D2 5525', 'venue' => null, 'where' => null, 'state' => 'running', 'mh' => ['kind' => 'classified', 'date' => '1990-03-27', 'year' => 1990, 'ref' => 'PM92000167'], 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362], ['palissy', 'PM92000167']]],
        ],
        'a1aa1a-62000' => [
            ['number' => 'A1A A1A 62036', 'venue' => null, 'where' => 'Conservé par la SNCF, garé hors service (génératrice)', 'state' => 'stored', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
        ],
        'a1aa1a-68500' => [
            ['number' => 'A1A A1A 68506', 'venue' => null, 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'A1A A1A 68540', 'venue' => null, 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
        ],
        'bb-1-80' => [
            ['number' => 'BB 36', 'venue' => 'cite-du-train', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362], ['wikipedia', 'Cité du train', 241565103]]],
        ],
        'bb-12000' => [
            ['number' => 'BB 12083', 'venue' => 'musee-de-la-mine-sur-le-site-du-carreau-wendel', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'BB 12114', 'venue' => 'ajecta-musee-vivant-du-chemin-de-fer', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362], ['wikipedia', 'Cité du train', 241565103]]],
        ],
        'bb-1280' => [
            ['number' => 'BB 1283', 'venue' => 'cite-du-train', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'BB E1', 'venue' => 'cite-du-train', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Cité du train', 241565103]]],
        ],
        'bb-1500' => [
            ['number' => 'BB 1501', 'venue' => 'cite-du-train', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
        ],
        'bb-1600' => [
            ['number' => 'BB 1632', 'venue' => 'cite-du-train', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Cité du train', 241565103]]],
        ],
        'bb-16500' => [
            ['number' => 'BB 16678', 'venue' => null, 'where' => 'Exposée en monument à Magenta (Marne)', 'state' => 'exposed', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'BB 16745', 'venue' => 'centre-de-la-mine-et-du-chemin-de-fer-cmcf', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362], ['wikipedia', 'Cité du train', 241565103]]],
        ],
        'bb-17000' => [
            ['number' => 'BB 17013', 'venue' => 'cite-du-train', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362], ['wikipedia', 'Cité du train', 241565103]]],
            ['number' => 'BB 17016', 'venue' => 'amicale-des-agents-de-paris-saint-lazare', 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
        ],
        'bb-25100' => [
            ['number' => 'BB 25188', 'venue' => null, 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
        ],
        'bb-25200' => [
            ['number' => 'BB 25236', 'venue' => null, 'where' => 'Préservée en attente de convention', 'state' => 'stored', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
        ],
        'bb-25500' => [
            ['number' => 'BB 25609', 'venue' => 'cite-du-train', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362], ['wikipedia', 'Cité du train', 241565103]]],
            ['number' => 'BB 25639', 'venue' => null, 'where' => 'Préservée en état de marche par l\'Association pour la Préservation de la CC 6570 (APCC 6570)', 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'BB 25660', 'venue' => null, 'where' => 'Préservée en état de marche par l\'Association pour la Préservation de la CC 6570 (APCC 6570)', 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'BB 25673', 'venue' => null, 'where' => 'Préservée en attente de convention', 'state' => 'stored', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'BB 25679', 'venue' => null, 'where' => 'Préservée en attente de convention', 'state' => 'stored', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
        ],
        'bb-300' => [
            ['number' => 'BB 327', 'venue' => 'amicale-des-anciens-et-amis-de-la-traction-a-vapeur-aaatv', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'BB 346', 'venue' => 'amicale-des-anciens-et-amis-de-la-traction-a-vapeur-aaatv', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
        ],
        'bb-4100' => [
            ['number' => 'BB 4177', 'venue' => 'amicale-des-anciens-et-amis-de-la-traction-a-vapeur-aaatv', 'where' => null, 'state' => null, 'mh' => ['kind' => 'classified', 'date' => '1990-03-27', 'year' => 1990, 'ref' => 'PM13001069'], 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362], ['palissy', 'PM13001069']]],
        ],
        'bb-4200' => [
            ['number' => 'BB 4240', 'venue' => null, 'where' => 'Exposée en monument à la biscuiterie Védère à Montgaillard (Hautes-Pyrénées)', 'state' => 'exposed', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
        ],
        'bb-60031-60033' => [
            ['number' => 'BB 60032', 'venue' => 'cite-du-train', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Cité du train', 241565103]]],
        ],
        'bb-62400' => [
            ['number' => 'BB 62412', 'venue' => null, 'where' => 'Conservée à l’étranger (Pays-Bas)', 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'BB 62413', 'venue' => null, 'where' => 'Préservé par un particulier aux Pays-Bas', 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'BB 62424', 'venue' => null, 'where' => 'Conservée à l’étranger (Pays-Bas)', 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'BB 62454', 'venue' => null, 'where' => 'Préservé en état de marche par la Stichting 2454 CREW aux Pays-Bas', 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'BB 62459', 'venue' => null, 'where' => 'Conservée à l’étranger (Pays-Bas)', 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
        ],
        'bb-63000' => [
            ['number' => 'BB 63038', 'venue' => 'train-du-pays-cathare-et-du-fenouilledes', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'BB 63069', 'venue' => null, 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'BB 63123', 'venue' => null, 'where' => 'Préservé en état de marche par le Chemin de fer à vapeur des Trois Vallées (CFV3V) en Belgique', 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'BB 63149', 'venue' => null, 'where' => 'Préservé en état de marche par le Chemin de fer à vapeur des Trois Vallées (CFV3V) en Belgique', 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'BB 63165', 'venue' => null, 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
        ],
        'bb-63500' => [
            ['number' => 'BB 63705', 'venue' => null, 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'BB 63812', 'venue' => null, 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'BB 63852', 'venue' => null, 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'BB 63866', 'venue' => null, 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'BB 63895', 'venue' => null, 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'BB 63958', 'venue' => null, 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'BB 64045', 'venue' => 'depot-musee-de-pont-erambourg-acf', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'BB 64047', 'venue' => null, 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
        ],
        'bb-66000' => [
            ['number' => 'BB 66014', 'venue' => null, 'where' => 'Stationnée sur les voies du dépôt de Nevers (propriété SNCF)', 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'BB 66105', 'venue' => null, 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'BB 66252', 'venue' => null, 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'BB 66304', 'venue' => 'amicale-des-cheminots-pour-la-preservation-de-la-141-r-1126', 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
        ],
        'bb-66400' => [
            ['number' => 'BB 66467', 'venue' => 'agrivap-les-trains-de-la-decouverte', 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'BB 66503', 'venue' => null, 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
        ],
        'bb-67300' => [
            ['number' => 'BB 67305', 'venue' => 'cite-du-train', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362], ['wikipedia', 'Cité du train', 241565103]]],
            ['number' => 'BB 67382', 'venue' => 'association-rhone-alpine-de-conservation-d-engins-thermiques-arcet', 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
        ],
        'bb-69400' => [
            ['number' => 'BB 69421', 'venue' => null, 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'BB 69432', 'venue' => null, 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'BB 69436', 'venue' => null, 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'BB 69449', 'venue' => null, 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'BB 69499', 'venue' => null, 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
        ],
        'bb-71000' => [
            ['number' => 'BB 71008', 'venue' => 'train-des-mouettes-tdm', 'where' => null, 'state' => 'running', 'mh' => ['kind' => 'listed', 'date' => '2020-06-18', 'year' => 2020, 'ref' => 'PM17002692'], 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362], ['palissy', 'PM17002692']]],
            ['number' => 'BB 71010', 'venue' => 'train-des-mouettes-tdm', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'BB 71015', 'venue' => 'chemin-de-fer-de-la-vendee-cfv', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'BB 71018', 'venue' => 'chemin-de-fer-de-la-vendee-cfv', 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'BB 71027', 'venue' => 'chemin-de-fer-de-la-vendee-cfv', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
        ],
        'bb-7200' => [
            ['number' => 'BB 7315', 'venue' => 'cite-du-train', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362], ['wikipedia', 'Cité du train', 241565103]]],
            ['number' => 'BB 7321-R', 'venue' => null, 'where' => 'Préservée par l\'Association Ferroviaire de Conservation de Locomotives (AFCL)', 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'BB 7338', 'venue' => 'amicale-des-cheminots-pour-la-preservation-de-la-141-r-1126', 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'BB 7398', 'venue' => 'amicale-des-cheminots-pour-la-preservation-de-la-141-r-1126', 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
        ],
        'bb-80000' => [
            ['number' => 'BB 80010', 'venue' => null, 'where' => 'Préservée par l\'APPF (Association pour la Préservation du Patrimoine Ferroviaire, association basée au Creusot)', 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
        ],
        'bb-8500' => [
            ['number' => 'BB 8616', 'venue' => 'cercle-ferroviaire-des-territoires-de-sologne-et-touraine-cftst', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
        ],
        'bb-88500' => [
            ['number' => 'BB 88511-RC', 'venue' => null, 'where' => 'Préservée par l\'Association Ferroviaire de Conservation de Locomotives (AFCL)', 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
        ],
        'bb-9200' => [
            ['number' => 'BB 9292', 'venue' => 'association-pour-la-preservation-du-materiel-ferroviaire-savoyard-apmfs', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Cité du train', 241565103]]],
        ],
        'bb-9300' => [
            ['number' => 'BB 9337', 'venue' => null, 'where' => null, 'state' => 'stored', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362], ['wikipedia', 'Cité du train', 241565103]]],
        ],
        'bb-9400' => [
            ['number' => 'BB 9411', 'venue' => 'amicale-des-anciens-et-amis-de-la-traction-a-vapeur-aaatv', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
        ],
        'c-20150' => [
            ['number' => 'C 20155', 'venue' => null, 'where' => 'Préservée par l\'association Team C-20155 en Suisse', 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
        ],
        'c-61000' => [
            ['number' => 'C 61041', 'venue' => 'chemin-de-fer-touristique-du-vermandois-cftv', 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'C 61042', 'venue' => 'chemin-de-fer-de-la-vendee-cfv', 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
        ],
        'cc-1100' => [
            ['number' => 'CC 1110', 'venue' => null, 'where' => 'Garée aux ateliers de Béziers', 'state' => 'stored', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
        ],
        'cc-14100' => [
            ['number' => 'CC 14161', 'venue' => null, 'where' => 'Exposée en monument à Conflans-en-Jarnisy (Meurthe-et-Moselle)', 'state' => 'exposed', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'CC 14183', 'venue' => 'musee-de-la-mine-sur-le-site-du-carreau-wendel', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
        ],
        'cc-20001' => [
            ['number' => 'CC 20001', 'venue' => 'association-pour-la-preservation-du-materiel-ferroviaire-savoyard-apmfs', 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
        ],
        'cc-40100' => [
            ['number' => 'CC 40110', 'venue' => null, 'where' => null, 'state' => 'running', 'mh' => ['kind' => 'classified', 'date' => '2010-02-24', 'year' => 2010, 'ref' => 'PM75004199'], 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362], ['palissy', 'PM75004199']]],
        ],
        'cc-6500' => [
            ['number' => 'CC 6572', 'venue' => 'cite-du-train', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Cité du train', 241565103]]],
        ],
        'cc-65000' => [
            ['number' => 'CC 65005', 'venue' => 'train-des-mouettes-tdm', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
        ],
        'cc-65500' => [
            ['number' => 'CC 65506', 'venue' => 'centre-de-la-mine-et-du-chemin-de-fer-cmcf', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'CC 65512', 'venue' => 'association-pour-la-preservation-du-patrimoine-et-des-metiers-ferroviaires-appmf', 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
        ],
        'cc-7100' => [
            ['number' => 'CC 7102', 'venue' => null, 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
        ],
        'cc-80000' => [
            ['number' => 'CC 80001', 'venue' => 'ecomusee-du-haut-pays-et-des-transports-ehpt', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
        ],
        'tc-61100' => [
            ['number' => 'TC 61112', 'venue' => 'chemin-de-fer-de-la-vendee-cfv', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
        ],
        'x-200' => [
            ['number' => 'X 211', 'venue' => 'train-du-bas-berry-tbb', 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'X 212', 'venue' => 'chemin-de-fer-de-la-baie-de-somme', 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'X 213', 'venue' => 'chemin-de-fer-de-la-baie-de-somme', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'X 224', 'venue' => 'train-du-bas-berry-tbb', 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'X 241', 'venue' => 'train-du-bas-berry-tbb', 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'X 242', 'venue' => 'train-du-bas-berry-tbb', 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
        ],
        'x-2100' => [
            ['number' => 'X 2124', 'venue' => 'cercle-ferroviaire-des-territoires-de-sologne-et-touraine-cftst', 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'X 2135', 'venue' => null, 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'X 2140', 'venue' => null, 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'X 2141', 'venue' => 'cercle-ferroviaire-des-territoires-de-sologne-et-touraine-cftst', 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'X 2144', 'venue' => null, 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
        ],
        'x-2200' => [
            ['number' => 'X 2204', 'venue' => null, 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'X 2208', 'venue' => null, 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'X 2211', 'venue' => 'cite-du-train', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'X 2232', 'venue' => null, 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'X 2247', 'venue' => 'autorail-limousin', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'X 2251', 'venue' => null, 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'X 2255', 'venue' => 'autorail-limousin', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
        ],
        'x-2400' => [
            ['number' => 'X 2403', 'venue' => null, 'where' => null, 'state' => 'running', 'mh' => ['kind' => 'classified', 'date' => '2010-02-24', 'year' => 2010, 'ref' => 'PM15000759'], 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362], ['palissy', 'PM15000759']]],
            ['number' => 'X 2425', 'venue' => null, 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'X 2426', 'venue' => 'chemin-de-fer-touristique-pontarlier-vallorbe-coni-fer', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
        ],
        'x-2700' => [
            ['number' => 'X 2709', 'venue' => 'chemin-de-fer-du-haut-forez-cfhf', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'X 2719', 'venue' => null, 'where' => 'Préservé par l\'association Autorail Lorraine Champagne Ardenne (ALCA)', 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'X 2725', 'venue' => null, 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'X 2726', 'venue' => 'chemins-de-fer-de-la-haute-auvergne-cfha', 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
        ],
        'x-2800' => [
            ['number' => 'X 2863', 'venue' => 'cite-du-train', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'X 2875', 'venue' => 'chemins-de-fer-du-centre-bretagne-cfcb', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
        ],
        'x-3600' => [
            ['number' => 'X 3601', 'venue' => null, 'where' => null, 'state' => 'running', 'mh' => ['kind' => 'classified', 'date' => '1998-01-19', 'year' => 1998, 'ref' => 'PM27001948'], 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362], ['palissy', 'PM27001948']]],
            ['number' => 'X 3623', 'venue' => 'chemin-de-fer-touristique-du-vermandois-cftv', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
        ],
        'x-3800' => [
            ['number' => 'X 3866', 'venue' => 'depot-musee-de-pont-erambourg-acf', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'X 3876', 'venue' => 'auberge-du-chemin-de-fer', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'X 3968', 'venue' => null, 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'X 4042', 'venue' => 'musee-de-la-mine-sur-le-site-du-carreau-wendel', 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
        ],
        'x-42000' => [
            ['number' => 'X 42511', 'venue' => null, 'where' => null, 'state' => 'exposed', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
        ],
        'x-4300' => [
            ['number' => 'X 4395', 'venue' => 'train-thur-doller-alsace-ttda', 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
        ],
        'x-4500' => [
            ['number' => 'X 4545', 'venue' => null, 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'X 4567', 'venue' => null, 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'X 4573', 'venue' => null, 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'X 4590', 'venue' => null, 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
        ],
        'x-4630' => [
            ['number' => 'X 4630', 'venue' => null, 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'X 4647', 'venue' => null, 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'X 4691', 'venue' => 'depot-musee-de-pont-erambourg-acf', 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'X 4719', 'venue' => null, 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
        ],
        'x-4750' => [
            ['number' => 'X 4787', 'venue' => null, 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'X 4790', 'venue' => null, 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'X 4795', 'venue' => null, 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
        ],
        'x-4900' => [
            ['number' => 'X 4903', 'venue' => 'train-touristique-du-centre-var-attcv', 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'X 4911', 'venue' => 'train-touristique-du-centre-var-attcv', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
        ],
        'x-5500' => [
            ['number' => 'X 5509', 'venue' => 'chemin-de-fer-de-la-vallee-de-l-eure-cfve', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
        ],
        'x-5600' => [
            ['number' => 'X 5621', 'venue' => 'agrivap-les-trains-de-la-decouverte', 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'X 8013', 'venue' => null, 'where' => 'Exposé en monument à Richelieu', 'state' => 'exposed', 'mh' => ['kind' => 'classified', 'date' => '1997-05-29', 'year' => 1997, 'ref' => 'PM37001046'], 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362], ['palissy', 'PM37001046']]],
        ],
        'x-5800' => [
            ['number' => 'X 5815', 'venue' => 'velorail-du-perigord-vert', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'X 5822', 'venue' => 'train-des-mouettes-tdm', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'X 5845', 'venue' => 'association-des-trains-du-sud-de-la-france-atsf', 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'X 5852', 'venue' => 'train-thur-doller-alsace-ttda', 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
        ],
        'x-94750' => [
            ['number' => 'XP 94755', 'venue' => null, 'where' => 'Préservé par l\'Association du Musée Postal des Anciens Ambulants de Toulouse', 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
        ],
        'y-11200' => [
            ['number' => 'Y 11251', 'venue' => null, 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
        ],
        'y-2200' => [
            ['number' => 'Y 2262', 'venue' => null, 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'Y 2263', 'venue' => 'tacot-des-lacs', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
        ],
        'y-6200' => [
            ['number' => 'Y 6259', 'venue' => null, 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
        ],
        'y-7400' => [
            ['number' => 'Y 7469', 'venue' => 'parc-naturel-regional-des-landes-de-gascogne-a-l-ecomusee-de-marqueze', 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
        ],
        'y-bl' => [
            ['number' => 'Y 7218', 'venue' => 'cite-du-train', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'Y 7282', 'venue' => 'parc-naturel-regional-des-landes-de-gascogne-a-l-ecomusee-de-marqueze', 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'Y 7583', 'venue' => 'parc-naturel-regional-des-landes-de-gascogne-a-l-ecomusee-de-marqueze', 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
            ['number' => 'Y 7697', 'venue' => 'amicale-des-anciens-et-amis-de-la-traction-a-vapeur-aaatv', 'where' => null, 'state' => 'running', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
        ],
        'z-100' => [
            ['number' => 'Z 103', 'venue' => null, 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
        ],
        'z-1200' => [
            ['number' => 'Z 1208', 'venue' => 'cite-du-train', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362]]],
        ],
        'z-3400' => [
            ['number' => 'Z 23461', 'venue' => 'cite-du-train', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Cité du train', 241565103]]],
        ],
        'z-3700' => [
            ['number' => 'Z 3714', 'venue' => null, 'where' => null, 'state' => 'stored', 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362], ['wikipedia', 'Cité du train', 241565103]]],
        ],
        'z-4100' => [
            ['number' => 'ZC9 Eyp 23156', 'venue' => 'cite-du-train', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Cité du train', 241565103]]],
        ],
        'z-4900' => [
            ['number' => 'Z 4909', 'venue' => null, 'where' => null, 'state' => 'stored', 'mh' => ['kind' => 'classified', 'date' => '2003-07-21', 'year' => 2003, 'ref' => 'PM87000733'], 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362], ['palissy', 'PM87000733'], ['wikipedia', 'Cité du train', 241565103]]],
        ],
        'z-7100' => [
            ['number' => 'Z 7133', 'venue' => 'association-pour-la-preservation-du-materiel-ferroviaire-savoyard-apmfs', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikipedia', 'Matériel moteur préservé de la SNCF', 241069362], ['wikipedia', 'Cité du train', 241565103]]],
        ],
    ],
    'others' => [],
];
