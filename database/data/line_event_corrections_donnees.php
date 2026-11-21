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
];
