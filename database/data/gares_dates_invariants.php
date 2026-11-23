<?php

/**
 * Ouvertures de gares au jour corrigées d'après l'article de la gare ou de la ligne, relevées par le contrôle
 * des invariants stations.opened_before_lines et stations.ohm_opened_before_lines (chantier « invariants-gares-nuit »,
 * 23/11/2026, rapport storage/app/reports/agents/aprem-2111/invariants-gares-nuit.md). Mêmes entrées que
 * gares_dates_articles.php (action « add », type « opening », source « correction »), incluses par
 * gares_corrections.php. Gares dont la date vient d'OpenStreetMap, d'OpenHistoricalMap ou de Wikidata (pas d'une
 * entrée de gares_dates_articles.php) : les autres corrections du chantier modifient directement ces entrées.
 *
 * Pas de tiret long dans les textes (consigne éditoriale).
 *
 * @return list<array<string, mixed>>
 */
$wp = fn (string $title, int $oldid) => 'https://fr.wikipedia.org/w/index.php?title='.rawurlencode(str_replace(' ', '_', $title)).'&oldid='.$oldid;

return array_map(fn (array $g) => [
    'station' => ['name' => $g[0], 'wikidata' => $g[1]],
    'action' => 'add',
    'type' => 'opening',
    'date' => $g[4],
    'precision' => 'day',
    'source_url' => $wp($g[2], $g[3]),
    'quote' => $g[5],
    'reason' => $g[6],
], [
    ['Andrézieux', 'Q3095632', 'Gare d\'Andrézieux', 230262788, '1864-06-15', 'Mise en service : 15 juin 1864',
        'gare actuelle (PLM) ; l\'objet OpenHistoricalMap « Gare d\'Andrézieux » (2 mai 1827, essais de la première ligne de Saint-Étienne à Andrézieux) est l\'ancienne gare de cette ligne, remplacée en 1864 : la date de 1827 précédait de trente-sept ans la section où tombe la gare'],
    ['Cergy-St-Christophe', 'Q1617169', 'Gare de Cergy-Saint-Christophe', 236159249, '1985-09-29', 'La gare de Cergy-Saint-Christophe est mise en service le 29 septembre 1985 par la Société nationale des chemins de fer français (SNCF), lorsqu\'elle ouvre à l\'exploitation le premier prolongement de la ligne de la bifurcation de Neuville à Cergy-Préfecture.',
        'le 22 septembre 1985 d\'OpenStreetMap n\'a pas de source ; l\'article de la gare (infobox et texte) donne le 29'],
    ['Noyon', 'Q1826892', 'Gare de Noyon', 241488734, '1849-02-25', 'Celle-ci fut inaugurée le 25 février 1849, par le prince-président Louis-Napoléon Bonaparte.',
        'la date de Wikidata (25 février 1848) porte une année fausse : la gare provisoire est établie en 1849 (première locomotive à Noyon le 30 janvier 1849), la section Compiègne - Noyon ouvre en février 1849'],
    ['Amélie-les-Bains', 'Q82925388', 'Gare d\'Amélie-les-Bains', 231707538, '1898-06-26', 'La gare d\'Amélie-les-Bains est mise en service le 26 juin 1898 par la Compagnie du Midi, lorsqu\'elle ouvre à l\'exploitation la dernière section de la ligne d\'Elne à Arles-sur-Tech, entre Céret et Arles-sur-Tech.',
        'la date de Wikidata (18 août 1889) est l\'ouverture d\'Elne à Céret : la gare est sur la dernière section, Céret - Arles-sur-Tech, ouverte le 26 juin 1898 (chronologie de la ligne)'],
]);
