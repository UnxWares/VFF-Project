<?php

/**
 * Lecture du matériel roulant dans Wikidata (CC0) par vff:import-materials.
 *
 * Périmètre : les séries (éléments instances d'une sous-classe de « classe de matériel roulant
 * ferroviaire », Q811704) exploitées par une compagnie française (P137 dont le pays P17 est la
 * France) ou rattachées à la France (P17). Les réseaux hors de France (Algérie, Tunisie, Grèce…),
 * dont les compagnies portent parfois le pays France, sont écartés par leur exploitant.
 *
 * Catégorie : chaque série est rangée d'après les classes dont elle descend (P31 ou P279, puis
 * P279 transitif), dans l'ordre des règles ci-dessous : la première qui s'applique gagne. Une
 * série qu'aucune règle ne range n'est pas importée (liste dans le rapport de la commande).
 *
 * Toutes les valeurs sont des identifiants Wikidata vérifiés le 2026-11-19 (libellés lus).
 * Pas de tiret long dans les textes (consigne éditoriale).
 */
return [
    // Métaclasse dont les sous-classes désignent une série de matériel (série de locomotives
    // Q19832486, rame type Q77814805, modèle de tramway Q76154857…).
    'rolling_stock_class' => 'Q811704',

    // Pays : France.
    'country' => 'Q142',

    // Exploitants dont le réseau est hors de France : une série exploitée seulement par eux est écartée.
    'excluded_operators' => [
        'Q3359730' => 'PLM réseau d\'Algérie',
        'Q20726274' => 'Office des chemins de fer algériens',
        'Q5501662' => 'Chemin de fer Franco-Hellénique',
    ],

    // Classes à écarter : matériel de métro (hors du champ de VFF, qui décrit le réseau ferré).
    'excluded_roots' => [
        'Q5503' => 'métro',
        'Q1199344' => 'métro sur pneumatiques',
        'Q108875699' => 'métro sur pneumatiques (matériel)',
        'Q113455515' => 'métro automatique',
        'Q1144784' => 'véhicule automatique léger (VAL)',
    ],

    // Bimode = électrique ET thermique. La classe Q3588091 (« rame automotrice bimode », en anglais « hybrid-powered
    // multiple unit ») couvre aussi un turbotrain à turbine et moteur diesel (ETG) : elle ne range une série en
    // « bimode » que si celle-ci descend aussi d'une classe électrique. La classe Q28455463 (« electro-diesel
    // multiple unit ») n'a pas besoin de cette garantie.
    'bimode_needs_electric' => ['Q3588091'],
    'electric_classes' => ['Q483373', 'Q16936947', 'Q625151'],

    // Règles de catégorie, dans l'ordre : [catégorie VFF, classes Wikidata qui la désignent].
    'type_rules' => [
        ['rame_tgv', ['Q13402959' => 'train à grande vitesse', 'Q129337' => 'TGV']],
        // Un engin bimode descend à la fois des rames électriques et thermiques : sa classe « bimode »
        // le distingue (catégorie à part, attribuée seulement quand Wikidata le déclare). Placé avant
        // les règles de traction, car un bimode descend aussi des classes électrique et thermique ; sans
        // classe « bimode », une série reste dans la catégorie de sa traction.
        ['bimode', [
            'Q3588091' => 'rame automotrice bimode',
            'Q28455463' => 'engin ferroviaire bi-modes',
        ]],
        ['locomotive_vapeur', ['Q171043' => 'locomotive à vapeur']],
        ['locomotive_electrique', ['Q625151' => 'locomotive électrique']],
        ['locomotive_diesel', ['Q34336' => 'locomotive diesel', 'Q1079190' => 'locotracteur']],
        // Q76154857 : la série est déclarée « modèle de tramway » (classe d'appartenance P31).
        ['tramway', ['Q3407658' => 'tramway', 'Q76154857' => 'modèle de tramway']],
        ['engin_service', [
            'Q800342' => 'engin de maintenance de l\'infrastructure',
            'Q14976' => 'chasse-neige',
            'Q209658' => 'draisine',
            'Q1967218' => 'voiture d\'auscultation de la voie',
        ]],
        ['automotrice', [
            'Q483373' => 'rame automotrice électrique',
            'Q16936947' => 'automotrice électrique',
        ]],
        ['autorail', [
            'Q1567915' => 'rame automotrice Diesel',
            'Q10915943' => 'automotrice diesel',
            'Q752392' => 'autorail',
            'Q5526370' => 'turbotrain',
            'Q788471' => 'autorail à grande capacité',
            // La série est déclarée « classe d'autorails » (classe d'appartenance P31).
            'Q63040753' => 'classe d\'autorails',
        ]],
        ['automotrice', ['Q3959904' => 'rame automotrice', 'Q7132141' => 'automotrice']],
        ['voiture', ['Q753779' => 'voiture de chemin de fer', 'Q16814621' => 'voiture voyageurs']],
        ['wagon', ['Q9166501' => 'wagon de marchandises']],
    ],

    // Repli quand Wikidata ne range pas la série (aucune classe parente) : catégories de son
    // article Wikipédia en français, dans l'ordre (la première qui s'applique gagne). `exclude` :
    // matériel de métro, hors du champ de VFF. Motifs lus sur les catégories réelles le 2026-11-19.
    'category_rules' => [
        ['exclude', '/^(?:Matériel roulant du métro|Métro)\b/u'],
        ['rame_tgv', '/^(?:Rame TGV|Train à grande vitesse)\b/u'],
        ['locomotive_vapeur', '/^Locomotive à vapeur\b/u'],
        ['locomotive_electrique', '/^Locomotive électrique\b/u'],
        ['locomotive_diesel', '/^(?:Locomotive [Dd]iesel|Locotracteur)\b/u'],
        ['tramway', '/^Tramway\b/u'],
        ['automotrice', '/^Automotrice\b/u'],
        ['autorail', '/^Autorail\b/u'],
        ['voiture', '/^(?:Voiture de chemin de fer|Voiture voyageurs|Remorque d\'autorail)\b/u'],
        ['wagon', '/^Wagon\b/u'],
    ],

    // État d'utilisation (P5817) : valeurs reconnues et état VFF correspondant. Une autre valeur
    // (inconnue, « abandonné »…) ne donne rien. Wikidata renseigne très peu cette propriété (1 série sur
    // 709 le 2026-11-19) : l'effectif de l'infobox Wikipédia complète.
    'service_status_values' => [
        'Q55654238' => 'in_service',    // en cours d'utilisation
    ],

    // Effectif de l'infobox Wikipédia : l'effectif en service n'est retenu que si l'article le date
    // de cette année ou d'une année plus récente (un effectif plus ancien n'atteste pas la situation
    // actuelle : la série a pu être retirée depuis).
    'fleet_min_year' => 2020,

    // Unités de vitesse (P2052) : facteur vers le km/h.
    'speed_units' => [
        'Q180154' => 1.0,           // kilomètre par heure
        'Q211256' => 1.609344,      // mille par heure
        'Q182429' => 3.6,           // mètre par seconde
    ],

    // Unités de puissance (P2109) : facteur vers le kW.
    'power_units' => [
        'Q3320608' => 1.0,          // kilowatt
        'Q25236' => 0.001,          // watt
        'Q6982035' => 1000.0,       // mégawatt
        'Q160857' => 0.73549875,    // cheval-vapeur (cheval métrique, 735,49875 W)
    ],

    // Unité de largeur des éléments d'écartement (P2049) : millimètre.
    'millimetre' => 'Q174789',
];
