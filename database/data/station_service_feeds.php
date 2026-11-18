<?php

/**
 * Flux GTFS lus par vff:import-station-service (desserte des gares aujourd'hui).
 * Recensement du 2026-11-18 : API https://transport.data.gouv.fr/api/datasets, jeux
 * « public-transit » dont une ressource GTFS déclare le mode « rail ». Les agrégats
 * régionaux (Bretagne, Pays de la Loire, Normandie, Grand Est…) republient les TER du
 * flux SNCF : non retenus. Licence : champ « licence » du jeu sur transport.data.gouv.fr
 * (lov2 = Licence Ouverte 2.0, odc-odbl = ODbL, mobility-licence = Licence Mobilités).
 *
 * Champs : key, name, provider (database/data/source_rights.php), licence, page, url
 * (ou history : version archivée par transport.data.gouv.fr la plus proche du jour MM-JJ
 * passé le plus récent), modes gardés (rail, tram, bus), tram_is_rail (tram-trains),
 * bus_stop_pattern (arrêts de car dans une ligne de train), bus_trip_pattern (courses de car
 * dans une ligne de train, d'après leur destination), route_pattern (lignes gardées,
 * d'après leur nom court), uic_fields / uic_pattern (où lire le code UIC), tourist (trains
 * touristiques), reference (summer ou spring : version archivée qui sert de référence, pas la
 * desserte du jour), window (fenêtre « MM-JJ » lue), opt_in (lu seulement avec --feed=…), departments
 * (départements que seul ce flux couvre : sans lui à jour, leurs gares sont « inconnu »).
 */
return [
    [
        'key' => 'sncf',
        'name' => 'SNCF Voyageurs : TGV INOUI, OUIGO, Intercités, TER (GTFS)',
        'provider' => 'sncf',
        'licence' => 'ODbL',
        'page' => 'https://transport.data.gouv.fr/datasets/horaires-sncf',
        'url' => 'https://eu.ftp.opendatasoft.com/sncf/plandata/Export_OpenData_SNCF_GTFS_NewTripId.zip',
        'modes' => ['rail', 'bus'],
        'tram_is_rail' => true,
        'bus_stop_pattern' => '/^StopPoint:OCECar/',
        'uic_fields' => ['parent_station', 'stop_id'],
        'uic_pattern' => '/OCE(?:[^-]*-)?([0-9]{8})$/',
    ],
    [
        // Dessertes d'été (Quiberon, côte Fleurie…) : absentes du flux de l'hiver.
        'key' => 'sncf-ete',
        'name' => 'SNCF Voyageurs, version de mi-juin (référence de l\'été)',
        'provider' => 'sncf',
        'licence' => 'ODbL',
        'page' => 'https://transport.data.gouv.fr/datasets/horaires-sncf',
        'history' => ['dataset' => 1290, 'resource' => 83582, 'day' => '06-15'],
        'modes' => ['rail'],
        'tram_is_rail' => true,
        'bus_stop_pattern' => '/^StopPoint:OCECar/',
        'uic_fields' => ['parent_station', 'stop_id'],
        'uic_pattern' => '/OCE(?:[^-]*-)?([0-9]{8})$/',
        'reference' => 'summer',
    ],
    [
        // Desservie au printemps et l'été mais plus aujourd'hui : interruption ou fermeture, pas une saison.
        'key' => 'sncf-printemps',
        'name' => 'SNCF Voyageurs, version de mi-avril, avril et mai (référence du printemps)',
        'provider' => 'sncf',
        'licence' => 'ODbL',
        'page' => 'https://transport.data.gouv.fr/datasets/horaires-sncf',
        'history' => ['dataset' => 1290, 'resource' => 83582, 'day' => '04-15'],
        'window' => ['04-15', '05-31'],
        'modes' => ['rail'],
        'tram_is_rail' => true,
        'bus_stop_pattern' => '/^StopPoint:OCECar/',
        'uic_fields' => ['parent_station', 'stop_id'],
        'uic_pattern' => '/OCE(?:[^-]*-)?([0-9]{8})$/',
        'reference' => 'spring',
    ],
    [
        'key' => 'transilien',
        'name' => 'Transilien et RER (GTFS publié par SNCF)',
        'provider' => 'sncf',
        'licence' => 'ODbL',
        'page' => 'https://transport.data.gouv.fr/datasets/horaires-des-lignes-transilien-1',
        'url' => 'https://eu.ftp.opendatasoft.com/sncf/gtfs/transilien-gtfs.zip',
        'modes' => ['rail'],
    ],
    [
        'key' => 'zou-trains',
        'name' => 'Trains régionaux ZOU ! (TER Sud)',
        'provider' => 'region-sud',
        'licence' => 'Licence Ouverte 2.0',
        'page' => 'https://transport.data.gouv.fr/datasets/trains-zou-en-provence-alpes-cote-dazur',
        'url' => 'https://proxy-data.zou.maregionsud.fr/GTFS/GTFS_SIBR_zou_ferre_datasud.zip',
        'modes' => ['rail'],
    ],
    [
        // Seul flux qui contient les Chemins de fer de Provence (lignes « 49 », exploitant EXP).
        'key' => 'zou-express',
        'name' => 'Réseau ZOU ! Express (Chemins de fer de Provence)',
        'provider' => 'region-sud',
        'licence' => 'Licence Ouverte 2.0',
        'page' => 'https://transport.data.gouv.fr/datasets/lignes-des-reseaux-transport-zou-provence-alpes-cote-d-azur-express-3-3',
        'url' => 'https://www.data.gouv.fr/api/1/datasets/r/1164d75b-4a9f-4c5a-b765-31d861b34fda',
        'modes' => ['rail'],
        // Saint-André-les-Alpes - Digne : cars publiés dans la ligne de train (« … GARE CPZou /car »).
        'bus_trip_pattern' => '#/\s*car\b#i',
    ],
    [
        'key' => 'transdev-rail-sud',
        'name' => 'Transdev Rail Sud Intermétropole (Marseille - Toulon - Nice)',
        'provider' => 'transdev-rail-sud',
        'licence' => 'Licence Ouverte 2.0',
        'page' => 'https://transport.data.gouv.fr/datasets/zou',
        'url' => 'https://www.data.gouv.fr/api/1/datasets/r/764d6d5b-d04e-4aa7-94b7-f8b2274d2964',
        'modes' => ['rail'],
    ],
    [
        'key' => 'cfc',
        'name' => 'Chemins de fer de la Corse',
        'provider' => 'collectivite-corse',
        'licence' => 'Licence Ouverte 2.0',
        'page' => 'https://transport.data.gouv.fr/datasets/gtfs-transport-horaires-chemins-de-fer-corse-1',
        'url' => 'https://www.data.gouv.fr/api/1/datasets/r/69c3db8a-a5fd-471f-b59f-46b9faded381',
        'modes' => ['rail'],
        'departments' => ['2A', '2B'],
    ],
    [
        'key' => 'panoramique-domes',
        'name' => 'Panoramique des Dômes (agrégat Oùra, Auvergne-Rhône-Alpes)',
        'provider' => 'oura',
        'licence' => 'ODbL',
        'page' => 'https://transport.data.gouv.fr/datasets/agregat-oura',
        'url' => 'https://api.oura3.cityway.fr/dataflow/offre-tc/download?provider=OURA&dataFormat=GTFS&dataProfil=OPENDATA',
        'modes' => ['rail'],
        'tourist' => true,
    ],
    [
        // Aucune gare de plus que le flux SNCF : lu seulement sur demande.
        'key' => 'eurostar',
        'name' => 'Eurostar',
        'provider' => 'eurostar',
        'licence' => 'Licence Ouverte 2.0',
        'page' => 'https://transport.data.gouv.fr/datasets/eurostar-gtfs-plan-de-transport-et-temps-reel',
        'url' => 'https://www.data.gouv.fr/api/1/datasets/r/bfd97acd-63f3-4ea4-bfe8-70e4c7fd8d13',
        'modes' => ['rail'],
        'uic_fields' => ['stop_code'],
        'uic_pattern' => '/^(87[0-9]{5})$/',
        'opt_in' => true,
    ],
    [
        // Aucune gare de plus que le flux SNCF : lu seulement sur demande.
        'key' => 'renfe',
        'name' => 'Renfe (trains à grande vitesse France - Espagne)',
        'provider' => 'renfe',
        'licence' => 'Licence Ouverte 2.0',
        'page' => 'https://transport.data.gouv.fr/datasets/horaires-ave-espagne-france',
        'url' => 'https://www.data.gouv.fr/api/1/datasets/r/eae0fa46-087a-4018-ada9-d8add124e635',
        'modes' => ['rail'],
        'opt_in' => true,
    ],
    [
        // Aucune gare de plus que le flux SNCF, et des arrêts techniques publiés comme des arrêts
        // voyageurs (Pressins, Saint-Cassin-la-Cascade, sans pickup_type) : lu seulement sur demande.
        'key' => 'trenitalia',
        'name' => 'Trenitalia France',
        'provider' => 'trenitalia',
        'licence' => 'Licence Ouverte 2.0',
        'page' => 'https://transport.data.gouv.fr/datasets/horaires-des-trains-trenitalia-france',
        'url' => 'https://www.data.gouv.fr/api/1/datasets/r/bdecea2c-ebc9-4f22-812d-927e4a2e4bad',
        'modes' => ['rail'],
        'opt_in' => true,
    ],
    [
        // Tram-trains T4, T11, T12, T13 et T14 (Esbly - Crécy) : seul flux qui les contient.
        // Les tramways urbains sont écartés (arrêts voisins d'anciennes gares de la Petite Ceinture).
        // Licence Mobilités (pas l'ODbL) : lu seulement sur demande (--feed=idfm-tram).
        'key' => 'idfm-tram',
        'name' => 'Île-de-France Mobilités, tram-trains T4, T11, T12, T13 et T14',
        'provider' => 'idfm',
        'licence' => 'Licence Mobilités',
        'page' => 'https://transport.data.gouv.fr/datasets/reseau-urbain-et-interurbain-dile-de-france-mobilites',
        'url' => 'https://eu.ftp.opendatasoft.com/stif/GTFS/IDFM-gtfs.zip',
        'modes' => ['tram'],
        'route_pattern' => '/^T(4|11|12|13|14)$/',
        'opt_in' => true,
    ],
];
