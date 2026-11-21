<?php

/**
 * Corrections sourcées d'événements de lignes relevées par la relecture des données des fiches
 * (question 18 de QUESTIONS.md, rapport storage/app/reports/agents/ui-donnees-fiches.md ; chantier
 * « donnees-corrections », 21/11/2026), ou par l'audit des tronçons (vff:audit-segments). Format : voir
 * line_event_corrections.php.
 *
 * Pas de tiret long dans les textes.
 *
 * @return list<array<string, mixed>>
 */
$wp = fn (string $title, int $oldid) => 'https://fr.wikipedia.org/w/index.php?title='.rawurlencode(str_replace(' ', '_', $title)).'&oldid='.$oldid;

return [
    // Constat 6 : 440000 Vire - Romagny. La « date d'ouverture » Wikidata (1883) est l'année de la
    // concession (convention du 17/07/1883, loi du 20/11/1883) ; la ligne ouvre de Vire aux Maures et
    // de Sourdeval à Mortain le 18/12/1887 (déjà en base, Wikipédia et lignes-oubliees). L'année 1883
    // faisait commencer la desserte de Vire sur cette ligne quatre ans trop tôt.
    [
        'line' => '440000',
        'action' => 'neutralize',
        'match' => ['source' => 'wikidata', 'type' => 'opening', 'date' => '1883-01-01'],
        'label' => 'Année de la concession (date d\'ouverture Wikidata)',
        'source_url' => $wp('Ligne de Vire à Romagny', 226857304),
        'quote' => 'Section de Vire aux Maures : le 18 décembre 1887',
        'reason' => 'concédée le 17 juillet 1883 à la Compagnie de l\'Ouest, la ligne ouvre le 18 décembre 1887',
    ],

    // Audit des tronçons (storage/app/reports/agents/nuit-2111/audit-troncons.md) : 900000 Culoz - Modane.
    // La « fermeture de toute la ligne » du 19/09/1871, lue dans la section sur le PLM de l'article, est
    // celle du chemin de fer du Mont-Cenis : ligne provisoire de Saint-Michel-de-Maurienne à Suse par le
    // col, proposée en 1865 par M. Brassey et l'ingénieur Fell, fermée quand le tunnel du Fréjus ouvre. La
    // ligne de Culoz à Modane n'a pas fermé : la même page date du 20/10/1871 la section de Saint-Michel
    // à Modane. Cette date contredisait tous les tronçons en service de 1880 à 2030 dans l'audit.
    [
        'line' => '900000',
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'closure', 'date' => '1871-09-19'],
        'label' => 'Fermeture du chemin de fer du Mont-Cenis (Saint-Michel-de-Maurienne - Suse)',
        'source_url' => $wp('Ligne de Culoz à Modane (frontière)', 238204405),
        'quote' => 'Cette ligne est mise en service le 15 juin 1868 et fermée le 19 septembre 1871 à l\'ouverture du tunnel ferroviaire du Fréjus.',
        'reason' => 'fermeture de la ligne provisoire du Mont-Cenis par le col, remplacée par le tunnel du Fréjus ; Culoz - Modane reste ouverte (section Saint-Michel - Modane ouverte le 20 octobre 1871)',
    ],

    // Contrôle à la main du 21/11/2026 (storage/app/reports/agents/aprem-2111/controle-main-3.md, erreurs de
    // données ; vérifications dans aprem-2111/donnees-erreurs.md).

    // 354000 Motteville - Montérolier-Buchy : la fin des voyageurs de 1938 manquait, la ligne était notée
    // « mixte » en 2030 alors qu'elle ne voit que des trains de fret (et des détournements exceptionnels).
    [
        'line' => '354000',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1938-10-02',
        'precision' => 'day',
        'from' => 'Motteville',
        'to' => 'Montérolier-Buchy',
        'source_url' => $wp('Ligne de Montérolier - Buchy à Motteville', 241046044),
        'quote' => 'l\'ensemble des circulations voyageurs sont supprimées le 2 octobre 1938 avec un report sur route',
        'reason' => 'suppression des voyageurs par la coordination des transports de 1938 (JORF du 19 octobre 1938) ; la ligne reste au fret',
    ],

    // 945000 Nice - Breil-sur-Roya : l'« ouverture » de 2023 est une phrase sur l'horaire 2023 et faisait
    // attendre une ligne fermée avant 2023 ; l'année Wikidata 1921 est celle de la reprise des travaux.
    // La ligne ouvre le 30/10/1928 (déjà en base par OHM).
    [
        'line' => '945000',
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'opening', 'date' => '2023-01-01'],
        'label' => 'Desserte à l\'horaire 2023',
        'source_url' => $wp('Ligne de Nice à Breil-sur-Roya', 235342459),
        'quote' => 'À l\'horaire 2023, la ligne comprend 10,5 allers-retours quotidiens entre Nice-Ville et Breil-sur-Roya',
        'reason' => 'phrase sur la desserte de 2023, pas une ouverture : la ligne est en service depuis 1928',
    ],
    [
        'line' => '945000',
        'action' => 'neutralize',
        'match' => ['source' => 'wikidata', 'type' => 'opening', 'date' => '1921-01-01'],
        'label' => 'Reprise des travaux (date d\'ouverture Wikidata)',
        'source_url' => $wp('Ligne de Nice à Breil-sur-Roya', 235342459),
        'quote' => 'La guerre de 1914-1918 et ses séquelles interrompirent les travaux qui ne purent reprendre qu\'en 1921.',
        'reason' => '1921 est la reprise des travaux ; l\'inauguration de Nice - Breil-sur-Roya - Coni a lieu le 30 octobre 1928',
    ],

    // 980000 Petite Ceinture : la « fermeture » de 1934 (infobox et Wikidata) est la fin des voyageurs ; le fret
    // a roulé jusqu'en 1993 (deuxième fermeture de l'infobox). VFF notait la ligne fermée en 1970.
    [
        'line' => '980000',
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'closure', 'date' => '1934-01-01'],
        'label' => 'Fin des voyageurs (infobox)',
        'source_url' => $wp('Ligne de Petite Ceinture', 241167819),
        'quote' => 'La suppression des trains de voyageurs est alors décidée : elle est effective le lundi 23 juillet 1934',
        'reason' => 'fin du service voyageurs, pas de la ligne : le fret roule jusqu\'en 1993',
    ],
    [
        'line' => '980000',
        'action' => 'neutralize',
        'match' => ['source' => 'wikidata', 'type' => 'closure', 'date' => '1934-04-01'],
        'label' => 'Fin des voyageurs (Wikidata)',
        'source_url' => $wp('Ligne de Petite Ceinture', 241167819),
        'quote' => 'La suppression des trains de voyageurs est alors décidée : elle est effective le lundi 23 juillet 1934',
        'reason' => 'fin du service voyageurs, pas de la ligne : le fret roule jusqu\'en 1993',
    ],
    [
        'line' => '980000',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1934-07-23',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Petite Ceinture', 241167819),
        'quote' => 'La suppression des trains de voyageurs est alors décidée : elle est effective le lundi 23 juillet 1934',
        'reason' => 'fin des voyageurs de la Petite Ceinture, remplacés par l\'autobus PC',
    ],

    // 265000 Templeuve - Don-Sainghin : les fins des voyageurs de 1952 et 1953 manquaient (« mixte » en 1970).
    [
        'line' => '265000',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1952-01-01',
        'precision' => 'year',
        'from' => 'Seclin',
        'to' => 'Pont-à-Marcq',
        'source_url' => $wp('Ligne de Templeuve à Don-Sainghin', 235337773),
        'quote' => 'La SNCF supprime le trafic voyageurs en 1952 sur le tronçon de Seclin à Pont-à-Marcq et l\'année suivante (1953) entre Templeuve et Pont-à-Marcq',
        'reason' => 'fin des voyageurs de Seclin à Pont-à-Marcq',
    ],
    [
        'line' => '265000',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1953-01-01',
        'precision' => 'year',
        'from' => 'Templeuve',
        'to' => 'Pont-à-Marcq',
        'source_url' => $wp('Ligne de Templeuve à Don-Sainghin', 235337773),
        'quote' => 'La SNCF supprime le trafic voyageurs en 1952 sur le tronçon de Seclin à Pont-à-Marcq et l\'année suivante (1953) entre Templeuve et Pont-à-Marcq',
        'reason' => 'fin des voyageurs de Templeuve à Pont-à-Marcq',
    ],

    // Perpignan - Thuir (ligne 3894, hors RFN) : fin des voyageurs en 1953, fret jusqu'en juin 1994.
    [
        'line' => '3894',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1953-01-01',
        'precision' => 'year',
        'source_url' => $wp('Ligne de Perpignan à Thuir', 236212395),
        'quote' => 'Le trafic voyageurs y a cessé en 1953',
        'reason' => 'fin des voyageurs, la ligne reste au fret',
    ],
    [
        'line' => '3894',
        'action' => 'add',
        'type' => 'closure',
        'date' => '1994-06-01',
        'precision' => 'month',
        'source_url' => $wp('Ligne de Perpignan à Thuir', 236212395),
        'quote' => 'Reprise ensuite par la SNCF, elle fut empruntée par des trains de fret jusqu\'en juin 1994',
        'reason' => 'fin du fret, dernier trafic de la ligne',
    ],

    // 696000 Châteauroux - La Ville-Gozet : l'ouverture de La Châtre - Montluçon manquait.
    [
        'line' => '696000',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1884-09-22',
        'precision' => 'day',
        'from' => 'La Châtre',
        'to' => 'Montluçon',
        'source_url' => $wp('Ligne de Châteauroux à La Ville-Gozet', 240558838),
        'quote' => 'Le 22 septembre 1884, la Compagnie du PO est autorisée à ouvrir à l\'exploitation les 66,101 kilomètres de la section située entre La Châtre et Montluçon.',
        'reason' => 'ouverture de la seconde section de la ligne (infobox : mise en service 1882 et 1884)',
    ],
];
