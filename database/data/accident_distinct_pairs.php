<?php

// Couples de fiches d'accident publiées du même jour, de même nature et à moins de AccidentImporter::ROUTE_MAX_M,
// relus un par un : ce sont deux accidents distincts, pas un doublon (invariant accidents.same_day_nearby).
// Une ligne par couple : [slug, slug, raison].
return [
    ['1910-12-23-cadaujac', '1910-12-23-arbanats', 'Deux collisions du même soir sur la ligne Bordeaux - Montauban : la seconde, en gare d\'Arbanats, est la suite de la première (trains retenus après l\'accident de Cadaujac) ; Le Matin du 24 et du 25 décembre 1910 (liste de Wikipédia, relue le 22/11/2026).'],
];
