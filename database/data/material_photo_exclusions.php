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
 * 366000, 909000 et 232000 (123 photos ; planches dans storage/app/reports/agents/nuit-2111/materiel-suite/).
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
];
