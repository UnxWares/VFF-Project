<?php

/**
 * Liens gare - ligne ajoutés à la main, lus par vff:attach-stations (App\Services\StationLineInclusions) : une gare
 * desservie par une ligne que ni la liste SNCF, ni la proximité du tracé, ni les terminus nommés ne lui rattachent.
 * Même forme que station_line_exclusions.php ; méthode du lien créé : reviewed_link.
 *
 * Pas de tiret long dans les textes (consigne éditoriale).
 *
 * @return list<array{station: string, line: string, reason: string, source_url: string, reviewed_at: string}>
 */
return [
    // Terminus du RER C : la gare est sur l'ancienne ligne d'Étampes à Beaune-la-Rolande (684000, fret seul), mais les trains
    // du RER C y arrivent par la ligne de Paris-Austerlitz à Bordeaux-Saint-Jean (570000), qui les amène jusqu'à Étampes.
    // Décision du 23/11/2026 (le plafond par les lignes ne voyait que la ligne fret).
    ['station' => 'st-martin-detampes', 'line' => 'rfn-570000',
        'reason' => 'gare desservie par le RER C (les trains y retournent aux heures de pointe, dans le sas avant la fin de la ligne d\'Étampes à Beaune-la-Rolande), qui y arrive par la ligne de Paris-Austerlitz à Bordeaux-Saint-Jean ; la gare est aussi sur la ligne 684000 (fret seul)',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Gare_de_Saint-Martin-d%27%C3%89tampes&oldid=240282342', 'reviewed_at' => '2026-11-23'],
];
