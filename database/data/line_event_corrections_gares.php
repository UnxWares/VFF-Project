<?php

/**
 * Corrections sourcées d'événements de lignes trouvées par la relecture des gares douteuses
 * (chantier « gares-relecture », 2026-10-20). Format : voir line_event_corrections.php.
 * Appliquées par vff:apply-event-corrections (glob line_event_corrections*.php).
 *
 * @return list<array<string, mixed>>
 */
return [
    // 002000 Gretz-Armainvilliers - Sézanne : aucune date pour la fin du trafic entre Coulommiers et
    // La Ferté-Gaucher ; le moteur fermait la section dès 2000 (gares de Chailly-Boissy-le-Châtel,
    // Saint-Siméon, Jouy-sur-Morin - Le Marais, Chauffry, Saint-Rémy-la-Vanne « fermées entre 1991 et
    // 2000 », alors que Wikidata donnait le 31/12/2011, date tirée du référentiel SNCF des gares).
    [
        'line' => '002000',
        'action' => 'add',
        'type' => 'closure',
        'date' => '2002-11-11',
        'precision' => 'day',
        'from' => 'Coulommiers',
        'to' => 'La Ferté-Gaucher',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_P_du_Transilien&oldid=240492233',
        'quote' => '11 novembre 2002 : suppression du trafic ferroviaire sur la section Coulommiers - La Ferté-Gaucher, et remplacement de la desserte par des bus.',
        'reason' => 'fin de tout trafic ferroviaire entre Coulommiers et La Ferté-Gaucher (section suspendue depuis)',
    ],

    // 376000 Saint-Pierre-du-Vauvray - Louviers : ni ouverture ni fermeture aux voyageurs ; la ligne était
    // absente de toutes les ères historiques et la gare de Louviers fermée dès 1951-1960 par les seuls
    // événements de la ligne 370000, alors que les voyageurs vers Saint-Pierre-du-Vauvray ont circulé
    // jusqu'au 27/11/1969 (fret ensuite ; ligne suspendue en 2023 selon SNCF Réseau).
    [
        'line' => '376000',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1867-04-23',
        'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Gare_de_Louviers&oldid=235032785',
        'quote' => 'La ligne de Saint-Pierre-du-Vauvray à Louviers a été déclarée d\'intérêt public le 14 juin 1861, et a été mise en service le 23 avril 1867, incluant la gare de Louviers.',
        'reason' => 'ouverture de toute la ligne (absente des ères historiques faute de date)',
    ],
    [
        'line' => '376000',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1969-11-27',
        'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Gare_de_Louviers&oldid=235032785',
        'quote' => 'Le 27 novembre 1969, il est supprimé sur la ligne de Saint-Pierre-du-Vauvray à Louviers. La gare fut utilisée quelque temps pour le service marchandises.',
        'reason' => 'fermeture aux voyageurs de toute la ligne (fret maintenu ensuite)',
    ],

    // 944000 Cannes-la-Bocca - Grasse : la « dépose » de 1914 est celle de la caténaire de l'essai de traction
    // électrique (1909-1914), pas de la voie ; la ligne restait exploitée (fret après le 02/10/1938). Effet
    // avant correction : ligne « déposée » en 1920 puis absente jusqu'en 1980, gares de Grasse et de
    // Mouans-Sartoux « ouvertes sans voie » (invariant stations.active_without_track).
    [
        'line' => '944000',
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'dismantling', 'date' => '1914-01-01'],
        'label' => 'Dépose de la caténaire expérimentale',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Cannes-la-Bocca_%C3%A0_Grasse&oldid=237782258',
        'quote' => 'Afin d\'expérimenter la traction électrique, à l\'automne 1909, une section de la ligne est équipée d\'une ligne aérienne légère',
        'reason' => 'dépose de la ligne aérienne d\'essai, la voie reste en service',
    ],
];
