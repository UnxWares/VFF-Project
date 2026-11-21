<?php

/**
 * Corrections sourcées d'événements de lignes relevées par la relecture des données des fiches
 * (question 18 de QUESTIONS.md, rapport storage/app/reports/agents/ui-donnees-fiches.md ; chantier
 * « donnees-corrections », 21/11/2026). Format : voir line_event_corrections.php.
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
];
