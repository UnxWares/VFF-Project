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
    'autorail-grande-capacite' => ['x-76500', 'z-27500', 'b-82500'],
    'regio-2n' => ['z-55500', 'z-56300', 'z-56500', 'z-56600', 'z-56700', 'z-56800', 'z-57000'],
];
