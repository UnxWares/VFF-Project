<?php

/**
 * Corrections sourcées d'événements de lignes relevées par l'audit des fermetures de gares (gares-anomalies, 2026-11-23,
 * rapport storage/app/reports/agents/aprem-2111/gares-anomalies.md). Format : voir line_event_corrections.php.
 * Appliquées par vff:apply-event-corrections (glob line_event_corrections*.php).
 *
 * Trois événements de lignes encore exploitées, lus comme des fermetures ou des déposes de la ligne entière ; chacun
 * fermait ou « déposait » à tort les gares de la ligne.
 *
 * @return list<array<string, mixed>>
 */
return [
    // 903000 Saint-André-le-Gaz - Chambéry : « l'antenne voyageurs vers Belley se voit fermée le 15 mai 1939 » est la fermeture de la ligne
    // 904000 (Pressins - Virieu-le-Grand, déjà datée du 15/05/1939 de Saint-André-le-Gaz à Belley). Portée par la ligne de Chambéry, elle
    // fermait aux voyageurs les haltes de La Bridoire, Pressins, Domessin, Saint-Cassin et Cognin, dont l'article ne date pas la fermeture.
    [
        'line' => '903000',
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'closure_passenger', 'date' => '1939-05-15'],
        'label' => 'Fermeture de l\'antenne voyageurs de Belley (ligne 904000)',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Saint-Andr%C3%A9-le-Gaz_%C3%A0_Chamb%C3%A9ry&oldid=235342681',
        'quote' => 'l\'antenne voyageurs vers Belley se voit fermée le 15 mai 1939.',
        'reason' => 'la date est celle de l\'antenne de Belley, pas de la ligne de Chambéry, qui reste ouverte aux voyageurs (article de la ligne de Pressins à Virieu-le-Grand, oldid 240450400 : « Le service des voyageurs sur la ligne vers Belley est supprimé par la SNCF le 15 mai 1939 »)',
    ],

    // 903000 : « La traction vapeur est par ailleurs définitivement abandonnée sur cette ligne à l'été 1962 » était lue comme la fermeture
    // de toute la ligne (tout trafic, 1962), alors qu'elle est électrifiée en 1985 et toujours exploitée.
    [
        'line' => '903000',
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'closure', 'date' => '1962-01-01'],
        'label' => 'Fin de la traction vapeur',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Saint-Andr%C3%A9-le-Gaz_%C3%A0_Chamb%C3%A9ry&oldid=235342681',
        'quote' => 'La traction vapeur est par ailleurs définitivement abandonnée sur cette ligne à l\'été 1962.',
        'reason' => 'fin de la traction vapeur, pas de l\'exploitation : la ligne est électrifiée le 18/02/1985 et reste ouverte',
    ],

    // 753000 Laroche-Migennes - Cosne : « 1943 - 1944, dépose de la deuxième voie sur l'ensemble de la ligne » était lue comme la dépose
    // de la ligne ; le moteur fermait en 1944 les gares de Beaumont, Merry-sur-Yonne et Étais (section Clamecy - Cosne, voyageurs
    // fermés le 15/05/1938). La ligne reste exploitée de Laroche-Migennes à Clamecy.
    [
        'line' => '753000',
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'dismantling', 'date' => '1944-01-01'],
        'label' => 'Dépose de la deuxième voie',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Laroche-Migennes_%C3%A0_Cosne&oldid=235261261',
        'quote' => '1943 - 1944, dépose de la deuxième voie sur l\'ensemble de la ligne ;',
        'reason' => 'la dépose de la seconde voie (récupération des rails) laisse la ligne à voie unique et en service ; ce n\'est pas la dépose de la ligne',
    ],
];
