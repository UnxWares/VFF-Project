<?php

/**
 * Liens ligne × matériel à ne pas créer (App\Services\Materials\LineMaterialExclusions), lus par
 * vff:import-materials : faux positifs relus à la main qu'aucune règle générale sûre ne couvre.
 *
 * Avec `quote`, l'exclusion ne retire que les preuves dont la citation contient ce texte : le lien revient si
 * une autre source le prouve. Sans `quote`, elle vaut pour le couple ligne × série entier : à réserver aux
 * séries qui ne peuvent pas rouler sur la ligne, faits établis avec la source.
 *
 * Format d'une entrée :
 *   [
 *       'line' => '690000',                  // lines.code (ou lines.slug pour une ligne hors RFN)
 *       'material' => 'Q1234567',            // materials.wikidata_id
 *       'quote' => 'texte de la phrase',     // facultatif
 *       'source' => 'https://fr.wikipedia.org/w/index.php?title=…&oldid=…',
 *       'reason' => 'ce que dit la source, en une phrase',
 *   ],
 *
 * Relecture du 19/11/2026 (rapport storage/app/reports/agents/soir/materiel-electrification.md).
 *
 * @return list<array{line: string, material: string, quote?: string, source: string, reason: string}>
 */
return [
    [
        'line' => '908000',
        'material' => 'Q751355', // BB 9300
        'source' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Valence_%C3%A0_Moirans&oldid=239909508',
        'reason' => 'série à 1 500 V continu seulement ; Valence - Moirans n\'est électrifiée qu\'en 25 kV 50 Hz (de 1994 à 2014 par étapes), sauf 2,2 km de 1 500 V côté Valence-Ville ; la phrase citée décrit un changement de machine à Toulouse-Matabiau',
    ],
    [
        'line' => '908000',
        'material' => 'Q607033', // BB 7200
        'source' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Valence_%C3%A0_Moirans&oldid=239909508',
        'reason' => 'série à 1 500 V continu seulement ; Valence - Moirans n\'est électrifiée qu\'en 25 kV 50 Hz (de 1994 à 2014 par étapes), sauf 2,2 km de 1 500 V côté Valence-Ville ; la phrase citée décrit un changement de machine à Toulouse-Matabiau',
    ],
    [
        'line' => '690000',
        'material' => 'Q432769', // Régiolis
        'quote' => 'restreintes à la portion de Bourges à Montluçon',
        'source' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Vierzon_%C3%A0_Saincaize&oldid=240196094',
        'reason' => 'les trois Régiolis des Paris - Montluçon sont « restreintes à la portion de Bourges à Montluçon », qui n\'emprunte pas Vierzon - Saincaize',
    ],
    [
        'line' => '650000',
        'material' => 'Q110795698', // Autorail Pauline
        'quote' => 'permet de calculer la consommation',
        'source' => 'https://fr.wikipedia.org/w/index.php?title=Autorail_Pauline&oldid=241246803',
        'reason' => 'voyage d\'essai des 4 et 5 septembre 1931 (Bordeaux - Montréjeau, rampe de Capvern) pour mesurer la consommation, avant la mise en service ; la série a ensuite desservi l\'étoile de Mont-de-Marsan',
    ],
    [
        'line' => 'hors-rfn-chemin-de-fer-de-rille',
        'material' => 'Q2120514', // Système Péchot
        'quote' => 'en cours de remise en état historique',
        'source' => 'https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_de_Rill%C3%A9&oldid=241314245',
        'reason' => 'une plateforme Péchot venue des Chanteraines, « en cours de remise en état historique » : pièce de collection en restauration, aucune circulation décrite',
    ],
];
