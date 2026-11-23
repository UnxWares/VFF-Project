<?php

/**
 * Corrections sourcées d'événements de lignes trouvées en comparant la desserte du jour (horaires GTFS
 * ouverts, vff:import-station-service, rapport storage/app/reports/agents/gares-desserte.md) aux états
 * de VFF (chantier « donnees-gares », 19/11/2026). Format : voir line_event_corrections.php.
 * Appliquées par vff:apply-event-corrections (glob line_event_corrections*.php).
 *
 * Règles (docs/METHODE-CLASSIFICATION.md, « Desserte du jour ») : une suspension sans date de reprise
 * qui dure (Montréjeau - Luchon, onze ans) est une fermeture suivie d'une réouverture ; des travaux avec
 * reprise annoncée ne sont pas une fermeture ; un train touristique qui roule en plus d'un service
 * public ne rend pas la ligne touristique.
 *
 * @return list<array<string, mixed>>
 */
$wp = fn (string $title, int $oldid) => 'https://fr.wikipedia.org/w/index.php?title='.rawurlencode(str_replace(' ', '_', $title)).'&oldid='.$oldid;

return [
    // 668000 Montréjeau - Luchon : aucune date de suspension ni de réouverture dans VFF (ligne « mixte »
    // de 2014 à 2025, gares fermées en 2030). Circulations suspendues le 18/11/2014, réouverture le
    // 22/06/2025 après transfert de la gestion à la Région Occitanie.
    [
        'line' => '668000',
        'action' => 'add',
        'type' => 'closure',
        'date' => '2014-11-18',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Montréjeau - Gourdan-Polignan à Luchon', 239813913),
        'quote' => 'Invoquant des raisons de sécurité, Réseau ferré de France et la SNCF suspendent les circulations ferroviaires à partir du 18 novembre 2014',
        'reason' => 'suspension de toutes les circulations (remplacées par des cars), sans date de reprise',
    ],
    [
        'line' => '668000',
        'action' => 'add',
        'type' => 'reopening',
        'date' => '2025-06-22',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Montréjeau - Gourdan-Polignan à Luchon', 239813913),
        'quote' => 'Les travaux et la pose des rails sont effectués en 2024. La réouverture a lieu le 22 juin 2025',
        'reason' => 'réouverture de toute la ligne (TER Occitanie, trains tous les jours)',
    ],

    // Train des Pignes (Chemins de fer de Provence, Nice - Digne) : l'« exploitation associative » du
    // train à vapeur (G.E.C.P.) entre Puget-Théniers et Saint-André-les-Alpes rendait la portion
    // touristique, et ses 14 gares « touristiques », alors que le service public ZOU ! y roule tous les
    // jours (horaires GTFS ZOU ! Express, Licence Ouverte 2.0). Le train à vapeur reste dans la chronologie.
    [
        'line' => '4459',
        'action' => 'neutralize',
        'match' => ['source' => 'heritage', 'type' => 'heritage_start', 'date' => '2026-01-01'],
        'label' => 'Train à vapeur touristique (G.E.C.P.), en plus du service public',
        'source_url' => $wp('Ligne de Nice à Digne', 240211690),
        'quote' => 'quatre allers-retours quotidiens entre Nice et Digne-les-Bains',
        'reason' => 'train à vapeur en saison sur une ligne à service public quotidien : la portion reste une ligne ouverte aux voyageurs',
    ],

    // 712000 Busseau-sur-Creuse - Ussel : la liaison Guéret - Felletin (section Busseau - Felletin) est
    // fermée le soir du 31/08/2025 ; VFF la montrait « mixte » en 2030.
    [
        'line' => '712000',
        'action' => 'add',
        'type' => 'closure',
        'date' => '2025-08-31',
        'precision' => 'day',
        'from' => 'Busseau-sur-Creuse',
        'to' => 'Felletin',
        'source_url' => $wp('Gare de Lavaveix-les-Mines', 229097905),
        'quote' => 'La ligne ferroviaire reliant Guéret à Felletin ferme officiellement le soir du 31 août 2025',
        'reason' => 'fermeture de la section Busseau-sur-Creuse - Felletin (cars ensuite ; article de la ligne : « fermeture au trafic voyageurs du tronçon entre Busseau et Felletin en 2025 »)',
    ],

    // 330000 Saint-Denis - Dieppe : trains de voyageurs Gisors - Serqueux remplacés par des cars le
    // 19/07/2024 (la section reste un itinéraire fret) ; Sérifontaine et Gournay - Ferrières fermées.
    [
        'line' => '330000',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '2024-07-19',
        'precision' => 'day',
        'from' => 'Gisors',
        'to' => 'Serqueux',
        'source_url' => $wp('Ligne de Saint-Denis à Dieppe', 239854982),
        'quote' => 'et ferme le 19 juillet 2024 pour un manque de fréquentation et un fort déficit',
        'reason' => 'fin des trains de voyageurs entre Gisors et Serqueux (cars TER), fret maintenu',
    ],
    // Même section : la fermeture du 19/01/2009 (Wikipédia) est reprise ici au rang « manual », sinon la
    // fermeture aux voyageurs de 2024 ci-dessus, plus fiable, l'écarte à l'arbitrage des fermetures de la
    // portion (EraTimeline::dedupe) et la section paraît ouverte en 2010.
    [
        'line' => '330000',
        'action' => 'add',
        'type' => 'closure',
        'date' => '2009-01-19',
        'precision' => 'day',
        'from' => 'Gisors',
        'to' => 'Serqueux',
        'source_url' => $wp('Ligne de Saint-Denis à Dieppe', 239854982),
        'quote' => 'Fermé le 19 janvier 2009 pour cause de vétusté, le tronçon entre Gisors et Serqueux est rouvert le 15 décembre 2013 au service des voyageurs',
        'reason' => 'section fermée pour vétusté du 19/01/2009 au 15/12/2013',
    ],
    // La réouverture du 15/12/2013, au même rang « manual » : sans elle, la chronologie de cette source finit sur
    // la fermeture de 2009 et contredit la section en service (audit des tronçons de 2030, 50 km « à revoir »).
    [
        'line' => '330000',
        'action' => 'add',
        'type' => 'reopening',
        'date' => '2013-12-15',
        'precision' => 'day',
        'from' => 'Gisors',
        'to' => 'Serqueux',
        'source_url' => $wp('Ligne de Saint-Denis à Dieppe', 239854982),
        'quote' => 'Fermé le 19 janvier 2009 pour cause de vétusté, le tronçon entre Gisors et Serqueux est rouvert le 15 décembre 2013 au service des voyageurs',
        'reason' => 'réouverture de la section Gisors - Serqueux (voyageurs, puis fret à nouveau en 2021)',
    ],
    // Même ligne : « bien qu'officiellement ouverte, n'est plus exploitée depuis le 19 janvier 2009 » était lu
    // comme une ouverture le jour de la fermeture ; la section, fermée de 2009 à 2013, paraissait ouverte en 2010.
    [
        'line' => '330000',
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'opening', 'date' => '2009-01-19'],
        'label' => 'Section restée officiellement ouverte, sans exploitation',
        'source_url' => $wp('Ligne de Saint-Denis à Dieppe', 239854982),
        'quote' => 'celle entre Gisors et Serqueux, bien qu\'officiellement ouverte, n\'est plus exploitée depuis le 19 janvier 2009 par la SNCF, faute d\'entretien',
        'reason' => 'pas une ouverture : la section n\'est plus exploitée du 19/01/2009 à sa réouverture du 15/12/2013',
    ],
];
