<?php

/*
 * Photos de séries choisies après relecture (App\Support\MaterialPhotoChoices), pour des séries sans image sur
 * Wikidata (P18) ni image de repli utilisable : WikidataMaterialMapper::imageCandidates() les essaie en premier.
 * La licence libre et l'auteur sont revérifiés sur Commons à chaque import (isFreeImage()), et la provenance
 * garde la page où la photo a été trouvée (`found_in` : `commons:Category:…`, `frwiki:<article>` ou `enwiki:<article>`).
 *
 * Règles de la relecture (les mêmes que material_photo_exclusions.php) : l'engin de la série en entier ou presque,
 * assez gros ; pas de plan, dessin, schéma, intérieur, cabine, plaque, carte ni accident ; pas une autre série,
 * même de même type.
 *
 * Relecture du 21/11/2026 (rapports storage/app/reports/agents/matin-2111/materiel-verif.md et
 * aprem-2111/materiel-photos-2.md). Une série dont l'image Wikidata (P18) est écartée dans
 * material_photo_exclusions.php prend aussi la photo choisie ici.
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
    [
        'material' => 'Q2807694', // 140 PLM 4295 à 4499
        'file' => 'PLM 4362.jpg',
        'found_in' => 'frwiki:140 PLM 4295 à 4499',
        'shows' => 'la 4362 PLM de profil, catalogue de l\'Exposition de Turin de 1911 (domaine public)',
    ],
    [
        'material' => 'Q86665327', // 030 PLM 1513 à 2457
        'file' => 'Bourbonnais PLM.jpg',
        'found_in' => 'frwiki:030 PLM 1513 à 2457',
        'shows' => 'une Bourbonnais du PLM et son tender, de profil, carte postale ancienne (domaine public, archives Arthur Meyer)',
    ],
    [
        'material' => 'Q2806515', // 031 T Est 613 à 742
        'file' => 'Locomotive n° B.687 de la Cie de l\'Est, Service de banlieu, Serie 8 - de 613 a 742, transformee a boggies.jpg',
        'found_in' => 'enwiki:Est 613 to 742',
        'shows' => 'la B.687 de l\'Est (série 8, service de banlieue) de profil, carte postale F. Fleury d\'avant 1914 (domaine public)',
    ],
    [
        'material' => 'Q832767', // G8.1 AL et ex-Prusse
        'file' => 'Prussian G 8.1 locomotive at Bassens, Gironde, France, 3 May 1919 (111-SC-158416).jpg',
        'found_in' => 'commons:Category:Prussian G 8.1',
        'shows' => 'une G 8.1 livrée au titre de l\'armistice, de profil à Bassens (Gironde) le 3 mai 1919 (domaine public, US Army Signal Corps)',
    ],
    [
        'material' => 'Q16024628', // 220 T Nord 2.311 à 2.380
        'file' => 'Du Nord 2.311 to 2.380.jpg',
        'found_in' => 'enwiki:Nord 2.311 to 2.380',
        'shows' => 'la 2.342 du Nord de profil vers 1910 (CC0, ETH-Bibliothek Zürich) ; remplace un plan',
    ],
    [
        'material' => 'Q3570521', // X 5800
        'file' => 'X5845.jpg',
        'found_in' => 'commons:Category:SNCF Class X 5800',
        'shows' => 'le X 5845 en livrée rouge et crème, de trois quarts, sur Arlanc - La Chaise-Dieu en 2016 (CC BY-SA 4.0, Romain agrivap) ; remplace un plan',
    ],
    [
        'material' => 'Q110795698', // Autorail Pauline (XC 11000)
        'file' => 'Pauline prototype en ligne.png',
        'found_in' => 'commons:Category:Charentaise railcars',
        'shows' => 'la Pauline prototype du Midi en ligne, de trois quarts, en 1931 (domaine public)',
    ],
];
