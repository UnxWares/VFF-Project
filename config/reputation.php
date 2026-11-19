<?php

/*
| Réputation des membres : niveaux et seuils.
|
| Pas de score : trois chiffres que chacun peut vérifier (contributions acceptées et appliquées,
| contributions refusées, relectures faites) et un niveau déduit de seuils écrits ici. Les mêmes
| seuils alimentent la page d'aide /contribute/reputation (Contribute/Reputation.svelte).
|
| Les niveaux sont listés du plus bas au plus haut. Un membre a le niveau le plus haut dont
| TOUS les seuils sont atteints :
|   - applied    : contributions acceptées et appliquées, au moins ;
|   - reviews    : relectures faites (approbations données, refus décidés), au moins ;
|   - acceptance : part de contributions acceptées parmi celles qui ont reçu une décision
|                  (acceptées / (acceptées + refusées)), en pourcentage entier arrondi vers le bas,
|                  au moins. Ignorée tant qu'aucune contribution n'a reçu de décision.
| Changer un seuil s'applique tout de suite : le niveau est calculé à l'affichage, sans recalcul.
| Le premier niveau est celui de tout membre (tous les seuils à zéro).
|
| La clé de chaque niveau est aussi celle de son libellé (lang/<langue>/reputation.php, `levels`).
*/
return [
    'levels' => [
        'newcomer' => ['applied' => 0, 'reviews' => 0, 'acceptance' => 0],
        'contributor' => ['applied' => 1, 'reviews' => 0, 'acceptance' => 0],
        'regular' => ['applied' => 5, 'reviews' => 0, 'acceptance' => 60],
        'experienced' => ['applied' => 20, 'reviews' => 10, 'acceptance' => 80],
    ],
];
