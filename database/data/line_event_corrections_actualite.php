<?php

/**
 * Faits d'ACTUALITÉ datés et sourcés (menace, décision publique) gardés dans la chronologie des
 * lignes. Type « other » : contexte de la chronologie, jamais un changement d'état de la carte
 * (LineChronicle::STATE_FAMILIES, EraTimeline) ni une portion à calculer. Même format que
 * line_event_corrections.php ; « label » est le libellé affiché (traduit par lang/{fr,en,de}/generated.php).
 * Ton neutre : la position de VFF reste dans l'encart de soutien (AdvocacySeeder). Pas de tiret long.
 *
 * @return list<array<string, mixed>>
 */
return [

    // 405000 Argentan - Granville : courrier du président de la Région Normandie au Premier ministre,
    // daté du 9 juillet 2026, transmis à la presse le 21 juillet (France 3 Normandie, 23 juillet 2026).
    // Il vise toute la relation Paris - Granville, donc toute la ligne 405000.
    [
        'line' => '405000',
        'action' => 'add',
        'type' => 'other',
        'date' => '2026-07-09',
        'precision' => 'day',
        'label' => 'Alerte sur l\'avenir de la ligne',
        'source_url' => 'https://france3-regions.franceinfo.fr/normandie/1-milliard-d-euros-de-travaux-trop-vetuste-la-ligne-sncf-paris-granville-pourrait-elle-vraiment-disparaitre-3390607.html',
        'quote' => 'le risque de fermeture de la ligne est imminent',
        'reason' => 'le président de la Région Normandie écrit au Premier ministre que, sans financement de l\'État pour la remise en état de la ligne Paris - Granville (près d\'un milliard d\'euros d\'ici 2040), sa fermeture pourrait être envisagée d\'ici trois ans ; la Région demande à l\'État de reprendre ces coûts',
    ],
];
