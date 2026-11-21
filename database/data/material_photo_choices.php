<?php

/*
 * Photos de séries choisies après relecture (App\Support\MaterialPhotoChoices), pour des séries sans image sur
 * Wikidata (P18) ni image de repli utilisable : WikidataMaterialMapper::imageCandidates() les essaie en premier.
 * La licence libre et l'auteur sont revérifiés sur Commons à chaque import (isFreeImage()), et la provenance
 * garde la page où la photo a été trouvée (`found_in` : `commons:Category:…` ou `frwiki:<article>`).
 *
 * Règles de la relecture (les mêmes que material_photo_exclusions.php) : l'engin de la série en entier ou presque,
 * assez gros ; pas de plan, dessin, schéma, intérieur, cabine, plaque, carte ni accident ; pas une autre série,
 * même de même type.
 *
 * Relecture du 21/11/2026 (rapport storage/app/reports/agents/matin-2111/materiel-verif.md).
 *
 * @return list<array{material: string, file: string, found_in: string, shows: string}>
 */
return [
    [
        'material' => 'Q432779', // BB 75000
        'file' => 'BB 75056 à Amiens - 17 avril 2010.jpg',
        'found_in' => 'commons:Category:SNCF Class BB 75000',
        'shows' => 'la BB 75056 en livrée Fret, de trois quarts, à Amiens (CC BY-SA 4.0, BB 22385)',
    ],
    [
        'material' => 'Q2815200', // 242 DT PLM 1 à 50
        'file' => '242 DT 4.jpg',
        'found_in' => 'frwiki:242 DT PLM 1 à 50',
        'shows' => 'la 242 DT 4 de profil, photo du début des années 1930 (domaine public)',
    ],
    [
        'material' => 'Q2807976', // 150 PO 6001 à 6070
        'file' => 'Coulounieix-Chamiers Mériller locomotive.JPG',
        'found_in' => 'frwiki:150 PO 6001 à 6070',
        'shows' => 'la 6065 PO (Franco-Belge, 1913) exposée au rond-point de Mériller à Coulounieix-Chamiers (CC BY-SA 3.0, Père Igor)',
    ],
    [
        'material' => 'Q3588094', // Élément automoteur double
        'file' => 'JHM-Caravelle - 1981 - Grenoble - SNCF, autorail X4500.jpg',
        'found_in' => 'frwiki:Élément automoteur double',
        'shows' => 'l\'élément X 4681 (série X 4630) en gare de Grenoble en 1981 (CC BY-SA 2.0, jhm0284)',
    ],
];
