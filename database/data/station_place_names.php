<?php

/**
 * Gares fermées dont le nom actuel vient d'OpenStreetMap et désigne le lieu qui occupe aujourd'hui le bâtiment
 * (commerce, association, salle, office de tourisme), pas la gare. Le nom ferroviaire est celui qu'une source
 * donne déjà pour la gare (Wikidata, schéma Wikipédia, Lignes oubliées) ; le nom du lieu reste en note (alt_names,
 * source osm_place). Lu par vff:import-station-names.
 *
 * Seules les gares dont une source donne un nom ferroviaire figurent ici ; les autres cas sont listés dans le rapport
 * storage/app/reports/agents/aprem-2111/noms-gares.md.
 *
 * @return list<array{slug: string, name: string, place: string, source: string, source_url: string}>
 */
return [
    ['slug' => 'boulevard-ornano', 'name' => 'Boulevard Ornano', 'place' => 'La Recyclerie', 'source' => 'wikidata', 'source_url' => 'https://www.wikidata.org/wiki/Q16508091'],
    ['slug' => 'lavenue-de-saint-ouen', 'name' => 'Avenue de Saint-Ouen', 'place' => 'Le Hasard Ludique', 'source' => 'wikidata', 'source_url' => 'https://www.wikidata.org/wiki/Q16507976'],
    ['slug' => 'etretat', 'name' => 'Étretat', 'place' => 'Centre de Vacances CCE SNCF', 'source' => 'wikidata', 'source_url' => 'https://www.wikidata.org/wiki/Q113835961'],
    ['slug' => 'le-thillot', 'name' => 'Le Thillot', 'place' => 'Maison des Hautes-Mynes', 'source' => 'wikidata', 'source_url' => 'https://www.wikidata.org/wiki/Q3098238'],
    ['slug' => 'magnac-laval', 'name' => 'Magnac-Laval', 'place' => 'Maison des Associations', 'source' => 'wikidata', 'source_url' => 'https://www.wikidata.org/wiki/Q106763464'],
    ['slug' => 'nantes-etat', 'name' => 'Nantes-État', 'place' => 'Maison des Syndicats', 'source' => 'wikidata', 'source_url' => 'https://www.wikidata.org/wiki/Q957240'],
    ['slug' => 'aubenas', 'name' => 'Aubenas', 'place' => "Maison des Associations d'Aubenas", 'source' => 'wikidata', 'source_url' => 'https://www.wikidata.org/wiki/Q2529671'],
    ['slug' => 'bussang', 'name' => 'Bussang', 'place' => 'Office de tourisme de Bussang', 'source' => 'wikidata', 'source_url' => 'https://www.wikidata.org/wiki/Q3096093'],
    ['slug' => 'office-de-tourisme', 'name' => 'Crozon-Morgat', 'place' => 'Office de Tourisme', 'source' => 'wikipedia_schema', 'source_url' => ''],
    ['slug' => 'mairie-de-buis-les-baronnies', 'name' => 'Buis-les-Baronnies', 'place' => 'Mairie de Buis-les-Baronnies', 'source' => 'lignes-oubliees', 'source_url' => ''],
];
