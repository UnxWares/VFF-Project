<?php

/**
 * Fréquentation des gares de la ligne 405000 (Argentan - Granville), relevée dans les jeux SNCF
 * Open Data « Fréquentation en gares » (SNCF Gares & Connexions, licence ODbL, voir
 * database/data/source_rights.php, clé « sncf »). Les chiffres de l'encart de soutien de la ligne
 * (database/seeders/AdvocacySeeder.php) en sont tirés et vérifiés par
 * tests/Feature/Advocacy/ArgentanGranvilleAdvocacyTest.php.
 *
 * Valeur : total_voyageurs_AAAA = montées et descentes de voyageurs dans l'année (pas des personnes
 * distinctes), sans les non-voyageurs.
 *
 * Deux jeux, à ne pas mélanger (avertissement de SNCF dans leurs descriptions, lues le 2026-11-21) :
 *   - 2015 à 2024 : jeu « frequentation-gares-archive », plus mis à jour ;
 *   - 2025 : jeu « frequentation-gares », nouvelle méthode (correspondances comptées deux fois, billets
 *     vendus par des tiers mieux pris en compte) : « les chiffres publiés pour 2025 ne sont pas
 *     comparables à ceux du jeu de données historique 2015-2024 ». Gardé à part, jamais comparé.
 *
 * Rejouer le relevé : télécharger les deux exports CSV ci-dessous (séparateur « ; ») et lire, pour
 * chaque code UIC, les colonnes total_voyageurs_2015 à total_voyageurs_2024 (totalvoyageurs2017 pour
 * 2017) et total_voyageurs_2025. Cache local : storage/app/data/frequentation-gares/.
 *
 * `only_this_line` : gare desservie par les seuls trains de cette ligne (Paris - Granville). Argentan
 * (Caen - Alençon - Le Mans) et Folligny, Granville (Caen - Coutances - Rennes) voient passer d'autres
 * relations : leurs chiffres ne mesurent pas la seule ligne. Saint-Sever n'est plus desservie.
 *
 * @return array{source: array<string, string>, stations: list<array{name: string, uic: string, only_this_line: bool, travellers: array<int, int>, travellers_2025: int}>}
 */
return [
    'source' => [
        'archive' => 'https://ressources.data.sncf.com/api/explore/v2.1/catalog/datasets/frequentation-gares-archive/exports/csv?delimiter=%3B',
        'current' => 'https://ressources.data.sncf.com/api/explore/v2.1/catalog/datasets/frequentation-gares/exports/csv?delimiter=%3B',
        'page' => 'https://ressources.data.sncf.com/explore/dataset/frequentation-gares-archive/',
        'publisher' => 'SNCF Gares & Connexions',
        'licence' => 'ODbL 1.0',
        'read_on' => '2026-11-21',
    ],
    'stations' => [
        ['name' => 'Argentan', 'uic' => '87444539', 'only_this_line' => false, 'travellers' => [2015 => 310239, 2016 => 291798, 2017 => 298107, 2018 => 274448, 2019 => 294691, 2020 => 175602, 2021 => 256502, 2022 => 389139, 2023 => 444175, 2024 => 398579], 'travellers_2025' => 445001],
        ['name' => 'Écouché', 'uic' => '87444547', 'only_this_line' => true, 'travellers' => [2015 => 919, 2016 => 825, 2017 => 641, 2018 => 202, 2019 => 528, 2020 => 285, 2021 => 178, 2022 => 734, 2023 => 326, 2024 => 183], 'travellers_2025' => 0],
        ['name' => 'Briouze', 'uic' => '87448084', 'only_this_line' => true, 'travellers' => [2015 => 32692, 2016 => 34775, 2017 => 35494, 2018 => 34356, 2019 => 37420, 2020 => 24431, 2021 => 31297, 2022 => 54629, 2023 => 57305, 2024 => 45439], 'travellers_2025' => 47396],
        ['name' => 'Flers', 'uic' => '87448001', 'only_this_line' => true, 'travellers' => [2015 => 121625, 2016 => 125648, 2017 => 126503, 2018 => 118812, 2019 => 126812, 2020 => 77154, 2021 => 99533, 2022 => 144527, 2023 => 155659, 2024 => 155284], 'travellers_2025' => 161401],
        ['name' => 'Vire', 'uic' => '87448159', 'only_this_line' => true, 'travellers' => [2015 => 74891, 2016 => 75950, 2017 => 77151, 2018 => 71071, 2019 => 80187, 2020 => 50430, 2021 => 57098, 2022 => 92269, 2023 => 103822, 2024 => 100571], 'travellers_2025' => 103647],
        ['name' => 'Saint-Sever', 'uic' => '87447714', 'only_this_line' => true, 'travellers' => [2015 => 341, 2016 => 78, 2017 => 75, 2018 => 76, 2019 => 48, 2020 => 7, 2021 => 2, 2022 => 0, 2023 => 0, 2024 => 0], 'travellers_2025' => 0],
        ['name' => 'Villedieu-les-Poêles', 'uic' => '87447698', 'only_this_line' => true, 'travellers' => [2015 => 69944, 2016 => 79093, 2017 => 86219, 2018 => 87124, 2019 => 101134, 2020 => 52622, 2021 => 66386, 2022 => 94601, 2023 => 112813, 2024 => 112032], 'travellers_2025' => 116170],
        ['name' => 'Folligny', 'uic' => '87447649', 'only_this_line' => false, 'travellers' => [2015 => 15101, 2016 => 12051, 2017 => 12712, 2018 => 8596, 2019 => 4046, 2020 => 2163, 2021 => 4068, 2022 => 6633, 2023 => 9017, 2024 => 7886], 'travellers_2025' => 10381],
        ['name' => 'Granville', 'uic' => '87447680', 'only_this_line' => false, 'travellers' => [2015 => 197782, 2016 => 212199, 2017 => 216105, 2018 => 209162, 2019 => 263943, 2020 => 183469, 2021 => 238620, 2022 => 346517, 2023 => 383021, 2024 => 375919], 'travellers_2025' => 412906],
    ],
];
