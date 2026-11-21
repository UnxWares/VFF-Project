<?php

/*
 * Photos de séries relues à l'œil et écartées (App\Support\MaterialPhotoExclusions), sur la fiche de la série
 * comme dans les vignettes des fiches de ligne : elles ne
 * montrent pas bien l'engin, ce qu'aucune règle sur la taille ou le nom du fichier ne voit
 * (Material::isShowablePhoto). Une image Wikidata (P18) écartée ici est remplacée à l'import par l'image de
 * repli (infobox de l'article, puis catégorie Commons : WikidataMaterialMapper::imageCandidates) quand il y en
 * a une ; sinon la tuile n'a pas de photo.
 *
 * Relecture du 21/11/2026 : séries des lignes 405000, 890000, 830000, 900000, 420000, 070000, 570000, 905000,
 * 366000, 909000 et 232000 (123 photos ; planches dans storage/app/reports/agents/nuit-2111/materiel-suite/), puis
 * séries reliées à une ligne (256 photos, rapport storage/app/reports/agents/aprem-2111/materiel-photos-2.md) : une
 * photo choisie dans material_photo_choices.php remplace l'image écartée quand il y en a une.
 *
 * @return list<array{file: string, material: string, reason: string}>
 */
return [
    [
        'file' => 'X 76500 Obernai.jpg',
        'material' => 'Q1326211', // X 76500
        'reason' => 'la rame X 76500 est petite, au fond du quai ; le premier plan montre une autre rame',
    ],
    [
        'file' => 'Armadabis BB75000.jpg',
        'material' => 'Q432779', // BB 75000
        'reason' => 'alignement de cinq locomotives de séries et de livrées différentes, vues de loin',
    ],
    [
        'file' => 'Nord 220-plan.jpg',
        'material' => 'Q16024628', // 220 T Nord 2.311 à 2.380
        'reason' => 'plan coté de la machine, pas une photo',
    ],
    [
        'file' => 'X 5800-plan.jpg',
        'material' => 'Q3570521', // X 5800
        'reason' => 'plan coté de l\'autorail, pas une photo',
    ],
    [
        'file' => 'Jean-Raoul Paul à côté de la Pauline prototype le 27 octobre 1931.jpg',
        'material' => 'Q110795698', // Autorail Pauline (XC 11000)
        'reason' => 'un groupe de personnes au premier plan, l\'autorail ne se voit pas',
    ],
    [
        'file' => 'Machine PIC Du MIDI du chemin de fer du Midi.png',
        'material' => 'Q111206967', // 030 Midi 801 à 1202
        'reason' => 'dessin coté de la machine, pas une photo',
    ],
    [
        'file' => 'Ouest 030 3531-3572.jpg',
        'material' => 'Q2806503', // 030 T Ouest 3531 à 3602
        'reason' => 'dessin coté de la machine, pas une photo',
    ],
    [
        'file' => 'Ceinture 030 Boer 27.jpg',
        'material' => 'Q2806503', // 030 T Ouest 3531 à 3602 (image de l'infobox)
        'reason' => 'la n° 27 du chemin de fer de Ceinture (1899), une autre compagnie que l\'Ouest',
    ],
    [
        'file' => 'BB 900.png',
        'material' => 'Q2876886', // BB 900
        'reason' => 'dessin en couleurs de la locomotive, pas une photo',
    ],
    [
        'file' => 'BBB 6003.png',
        'material' => 'Q2876710', // BBB 6003
        'reason' => 'dessin en couleurs de la locomotive, pas une photo',
    ],
];
