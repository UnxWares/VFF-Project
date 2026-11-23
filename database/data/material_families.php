<?php

/**
 * Familles de séries qui ont chacune leur fiche et dont les versions en ont une aussi
 * (App\Services\Materials\MaterialFamilyMembers) : la fiche d'une ligne ne montre pas la famille à côté
 * de ses versions. Clés et valeurs : `materials.slug`. Une version est celle que Wikidata ou l'article de la
 * série dit être de la famille.
 *
 * @return array<string, list<string>>
 */
return [
    'autorail-grande-capacite' => ['x-76500', 'z-27500', 'b-81500', 'b-82500'],
    'regio-2n' => ['z-55500', 'z-56300', 'z-56500', 'z-56600', 'z-56700', 'z-56800', 'z-57000'],
    // Article « Élément automoteur double » : « Quatre grandes séries constituent la famille des EAD : X 4300, X 4500,
    // X 4630, X 4750 / X 4790 », auxquelles « on peut ajouter » les X 4900 (éléments triples) et les X 94750 (postales).
    // Les X 72500 en sont les successeurs, pas des versions.
    'element-automoteur-double' => ['x-4300', 'x-4500', 'x-4630', 'x-4750', 'x-4900', 'x-94750'],
    // Article « Régiolis » (« Liste des versions du Régiolis » : B 83500, B 84500, B 85000, B 85900) : B 85000 est le
    // Coradia Liner (même article), B 83500 est rattachée au Régiolis par Wikidata (Q141973967, sous-classe de Q432769).
    'regiolis' => ['sncf-b-83500', 'coradia-liner'],
    // Article « Z 2N » : dénomination générale des automotrices à deux niveaux Z 5600, Z 8800, Z 20500, Z 20900, Z 92050.
    'z-2n' => ['z-5600', 'z-8800', 'z-20500', 'z-20900'],
];
