<?php

/**
 * Jeux ouverts d'images (autochromes, cartes postales d'archives) lus par `vff:import-open-data-media`
 * (App\Services\Media\OpenData\OpenDataMediaSource), un bloc par jeu. Correspondance des champs de chaque jeu
 * avec les notions communes : c'est le seul endroit où les jeux diffèrent.
 *
 * Accès : les portails Opendatasoft (opendata.hauts-de-seine.fr, data.evrycourcouronnes.fr,
 * sevres-seineouest.opendatasoft.com) interdisent `/api/` à tout robot par leur robots.txt (lu le 22/11/2026) ;
 * les mêmes lignes sont donc lues par l'API tabulaire de data.gouv.fr (`driver = tabular`, `resource` = identifiant
 * de la ressource CSV sur data.gouv.fr, pas de robots.txt), qui sert les copies analysées par data.gouv.fr. Le
 * jeu du musée Gadagne est lu par le WFS de data.grandlyon.com (`driver = wfs`, pas de robots.txt). Les images
 * ne sont jamais téléchargées : adresses en lien direct, affichées par le navigateur du visiteur.
 *
 * Clés d'un bloc :
 *   provider    valeur de media.provider (20 caractères au plus), déclarée dans source_rights.php (table media) ;
 *   name        nom du jeu, pour les rapports ;
 *   driver      tabular | wfs ;
 *   resource    (tabular) identifiant de la ressource data.gouv.fr ; (wfs) adresse du WFS ;
 *   prefilter   (tabular) requêtes de pré-sélection côté serveur, réunies (ET dans une requête, OU entre requêtes) ;
 *               vide = lecture de tout le jeu (jeux de quelques centaines de lignes) ;
 *   fields      correspondance notion commune => nom de la colonne du jeu ;
 *   require_true (option) colonne booléenne qui doit être vraie (ex. « Open data » de Suresnes) ;
 *   rail        mots qui font d'une ligne une vue ferroviaire (expression régulière sur les champs listés) ;
 *   license     fixe (`fixed`) ou lue ligne par ligne (`per_record`) ;
 *   credit      producteur, premier élément du crédit ;
 *   source_url  adresse de la notice d'origine ({id} = identifiant de la ligne) ;
 *   point_precise le point est celui de la vue (adresse), pas le centre de la commune : permet le rattachement par proximité ;
 *   commune_aliases (option) nom de commune du jeu => ville des gares de VFF ;
 *   station_aliases (option) nom de gare de VFF => noms que le jeu lui donne (anciens noms) ;
 *   thumb_suffix chemin de vignette ajouté à l'adresse du fichier (portails Opendatasoft : `/300/`), vide = l'image elle-même ;
 *   split_dash  (option) l'identifiant et le titre sont dans la même colonne, séparés par « - » (Sèvres).
 *
 * Ajouts de l'agent medias-3 (22/11/2026) :
 *   driver `getty`  SPARQL puis une notice Linked Art par objet (GettyReader) : `albums` (numéros d'inventaire des albums ferroviaires)
 *                   et `keywords` (mots français du titre) choisissent les objets ;
 *   driver `iiif`   plan du site (`sitemap`) ou recherches (`searches`, `search_url`) pour les identifiants ark, puis un manifeste IIIF
 *                   par ark (IiifManifestReader) : `ark_prefix`, `manifest`, `image_sizes`, `iiif_quality`, `ca_chain` (certificat
 *                   intermédiaire que le serveur n'envoie pas) ;
 *   repair          `cp1250-as-cp1252` : accents remis dans un CSV lu avec le mauvais codage par data.gouv.fr ;
 *   require_pattern notion => expression régulière que la ligne doit satisfaire (dépôt, droits, pays), sinon refus `hors_fonds` ;
 *   commune_split / commune_from_list  commune tirée d'une colonne (premier élément) ou d'une liste de lieux (celle que le titre nomme) ;
 *   upgrade_https   adresses d'image publiées en http, servies en https ;
 *   clean_title     titre sans « S. T. », crochets ni série après « : » (bibliothèques numériques) ;
 *   title_match     rattachement de repli par le titre seul (gare nommée, extrémités d'une ligne : OpenDataMatcher::matchByTitle) ;
 *   dedupe          une seule image par titre, auteur et date (planches reliées dans deux albums) ;
 *   fields.source   adresse de la notice par ligne (remplace `source_url`), fields.thumb adresse de la vignette.
 *
 * Droits lus le 22/11/2026, voir storage/app/reports/agents/aprem-2111/medias-2.md. Pas de tiret long.
 */
$licenceOuverte = [
    'kind' => 'fixed',
    'license' => 'Licence Ouverte (Etalab)',
    'license_url' => 'https://www.etalab.gouv.fr/licence-ouverte-open-licence/',
    'rights_status' => 'open_license',
    'commercial_ok' => true,
    'derivatives_ok' => true,
    'share_alike' => false,
];

return [
    'albert-kahn' => [
        'provider' => 'albert-kahn',
        'name' => 'Archives de la Planète (musée départemental Albert-Kahn), vues ferroviaires de France',
        'driver' => 'tabular',
        'resource' => 'd495f087-d7e0-45e6-afd4-d87d59a0433c',
        'portal' => 'https://opendata.hauts-de-seine.fr/explore/dataset/archives-de-la-planete/',
        'prefilter' => [
            ['Pays__exact' => 'France', 'Sujets__contains' => 'gare'],
            ['Pays__exact' => 'France', 'Sujets__contains' => 'train'],
            ['Pays__exact' => 'France', 'Sujets__contains' => 'tramway'],
            ['Pays__exact' => 'France', 'Sujets__contains' => 'locomotive'],
            ['Pays__exact' => 'France', 'Sujets__contains' => 'voie ferr'],
            ['Pays__exact' => 'France', 'Sujets__contains' => 'viaduc'],
            ['Pays__exact' => 'France', 'Sujets__contains' => 'wagon'],
            ['Pays__exact' => 'France', 'Sujets__contains' => 'chemin de fer'],
        ],
        'fields' => [
            'id' => 'Identifiant Fakir',
            'inventory' => "Numéro d'inventaire",
            'title' => 'Légende',
            'title_alt' => "Légende d'origine",
            'date' => 'Date de prise de vue',
            'author' => 'Opérateur',
            'commune' => 'Ville',
            'place' => 'Lieu ancien',
            'point' => 'Geo Point',
            'image' => 'Photo',
            'license' => 'Licence',
            'subjects' => 'Sujets',
            'themes' => 'Thèmes',
            'country' => 'Pays',
        ],
        'country' => 'France',
        'rail' => ['fields' => ['subjects', 'title', 'title_alt'], 'pattern' => '/\b(gares?|chemins? de fer|tramways?|trains?|locomotives?|voies? ferr[ée]es?|viaducs?|wagons?|ferroviaires?|funiculaires?)\b/iu'],
        'license' => [
            'kind' => 'per_record',
            'map' => [
                '/\bCC-BY-4\.0\b/i' => [
                    'license' => 'CC BY 4.0',
                    'license_url' => 'https://creativecommons.org/licenses/by/4.0/deed.fr',
                    'rights_status' => 'open_license',
                    'commercial_ok' => true,
                    'derivatives_ok' => true,
                    'share_alike' => false,
                ],
                '/\bCC-0+\b|\bCC0\b/i' => [
                    'license' => 'CC0 1.0',
                    'license_url' => 'https://creativecommons.org/publicdomain/zero/1.0/deed.fr',
                    'rights_status' => 'public_domain',
                    'commercial_ok' => true,
                    'derivatives_ok' => true,
                    'share_alike' => false,
                ],
            ],
        ],
        'credit' => 'musée départemental Albert-Kahn, Archives de la Planète (Département des Hauts-de-Seine)',
        'credit_with_inventory' => true,
        'source_url' => 'https://opendata.hauts-de-seine.fr/explore/dataset/archives-de-la-planete/table/?q={id}',
        'thumb_suffix' => '/300/',
        'type' => 'photo',
        'process' => 'autochrome',
        'min_year' => 1909,
        'max_year' => 1931,
    ],

    'ad92-9fi' => [
        'provider' => 'ad92-9fi',
        'name' => 'Archives départementales des Hauts-de-Seine, cartes postales (9 Fi, 1900-1944)',
        'driver' => 'tabular',
        'resource' => '87ec301a-d251-4780-9148-fb7116af459d',
        'portal' => 'https://opendata.hauts-de-seine.fr/explore/dataset/fr-229200506-cartes-postales/',
        'prefilter' => [
            ['Titre__contains' => 'gare'],
            ['Titre__contains' => 'chemin de fer'],
            ['Titre__contains' => 'tramway'],
            ['Titre__contains' => 'viaduc'],
            ['Titre__contains' => 'tunnel'],
            ['Titre__contains' => 'train'],
            ['Titre__contains' => 'funiculaire'],
            ['Titre__contains' => 'locomotive'],
            ['Titre__contains' => 'passage à niveau'],
            ['Thèmes__contains' => 'gare'],
            ['Thèmes__contains' => 'ferr'],
            ['Thèmes__contains' => 'tramway'],
            ['Thèmes__contains' => 'funiculaire'],
            ['Thèmes__contains' => 'viaduc'],
        ],
        'fields' => [
            'id' => 'Identifiant',
            'title' => 'Titre',
            'date' => 'Date',
            'commune' => 'Commune',
            'point' => 'geo_point_2d',
            'image' => 'Image',
            'themes' => 'Thèmes',
            'station_hint' => 'Thème 3',
            'address' => 'Localisation',
        ],
        'rail' => ['fields' => ['title', 'themes'], 'pattern' => '/\b(gares?|chemins? de fer|tramways?|trains?|viaducs?|tunnels?|funiculaires?|locomotives?|passages? [àa] niveau|ferroviaires?)\b/iu'],
        'license' => $licenceOuverte,
        'credit' => 'Archives départementales des Hauts-de-Seine',
        'source_url' => 'https://opendata.hauts-de-seine.fr/explore/dataset/fr-229200506-cartes-postales/table/?q={id}',
        'thumb_suffix' => '/300/',
        'type' => 'postcard',
        'min_year' => 1900,
        'round_years_circa' => true,
        'point_precise' => true,
        'max_year' => 1944,
    ],

    'suresnes-cp' => [
        'provider' => 'suresnes-cp',
        'name' => 'Ville de Suresnes, cartes postales anciennes des archives communales',
        'driver' => 'tabular',
        'resource' => '037906a2-3b34-4dfa-94c2-54df81e97592',
        'portal' => 'https://opendata.hauts-de-seine.fr/explore/dataset/fr-219200730-cartes-postales-anciennes/',
        'prefilter' => [],
        'fields' => [
            'id' => 'Référence',
            'title' => 'Intitulé',
            'date' => 'Dates',
            'place' => 'Quartier',
            'image' => 'Nommage images',
        ],
        'fixed_commune' => 'Suresnes',
        'require_true' => 'Open data',
        'rail' => ['fields' => ['title', 'place'], 'pattern' => '/\b(gares?|chemins? de fer|tramways?|trains?|viaducs?|tunnels?|locomotives?|ferroviaires?|passages? [àa] niveau)\b/iu'],
        'license' => $licenceOuverte,
        'credit' => 'Archives communales de Suresnes',
        'source_url' => 'https://opendata.hauts-de-seine.fr/explore/dataset/fr-219200730-cartes-postales-anciennes/table/?q={id}',
        'thumb_suffix' => '/300/',
        'type' => 'postcard',
    ],

    'evry-cp' => [
        'provider' => 'evry-cp',
        'name' => 'Ville d\'Évry-Courcouronnes, cartes postales anciennes',
        'driver' => 'tabular',
        'resource' => '9360ea2b-3b51-48cd-8b93-dc5f0f4a1b60',
        'portal' => 'https://data.evrycourcouronnes.fr/explore/dataset/cartes-postales/',
        'prefilter' => [],
        // Évry-Petit-Bourg, Évry et Courcouronnes ont fusionné en 2019 : les gares de VFF sont à Évry-Courcouronnes.
        'commune_aliases' => ['evry petit bourg' => 'Évry-Courcouronnes', 'evry' => 'Évry-Courcouronnes', 'courcouronnes' => 'Évry-Courcouronnes'],
        // La gare d'Évry-Petit-Bourg (ouverte en 1840 sur la ligne de Paris à Corbeil) est « Evry-Val de Seine » dans VFF (station 17347, ouverte le 1840-09-17).
        'station_aliases' => ['Evry-Val de Seine' => ['Evry Petit Bourg']],
        'fields' => [
            'id' => 'Identifiant',
            'title' => 'Titre',
            'caption' => 'Descriptif',
            'date' => 'Date',
            'commune' => 'Commune',
            'image' => 'Image',
            'themes' => 'Thèmes',
            'insee' => 'Code Insee',
        ],
        'rail' => ['fields' => ['title', 'caption', 'themes'], 'pattern' => '/\b(gares?|chemins? de fer|tramways?|trains?|viaducs?|tunnels?|locomotives?|decauville|ferroviaires?|passages? [àa] niveau|wagons?)\b/iu'],
        'license' => [
            'kind' => 'fixed',
            'license' => 'Licence Ouverte 2.0 (Etalab)',
            'license_url' => 'https://www.etalab.gouv.fr/wp-content/uploads/2017/04/ETALAB-Licence-Ouverte-v2.0.pdf',
            'rights_status' => 'open_license',
            'commercial_ok' => true,
            'derivatives_ok' => true,
            'share_alike' => false,
        ],
        'credit' => 'Ville d\'Évry-Courcouronnes',
        'source_url' => 'https://data.evrycourcouronnes.fr/explore/dataset/cartes-postales/table/?q={id}',
        'thumb_suffix' => '/300/',
        'type' => 'postcard',
    ],

    'sevres-cp' => [
        'provider' => 'sevres-cp',
        'name' => 'Ville de Sèvres, cartes postales anciennes des archives municipales',
        'driver' => 'tabular',
        'resource' => 'e43319fe-21a3-42f1-86a5-356ffec6fff8',
        'portal' => 'https://sevres-seineouest.opendatasoft.com/explore/dataset/cartes-postales-anciennes-des-archives-municipales/',
        'prefilter' => [],
        'fields' => [
            'id' => 'Name',
            'title' => 'Name',
            'image' => 'URL',
            'themes' => 'Arborescence',
        ],
        'fixed_commune' => 'Sèvres',
        'split_dash' => true,
        'rail' => ['fields' => ['title', 'themes'], 'pattern' => '/\b(gares?|chemins? de fer|tramways?|trains?|viaducs?|tunnels?|locomotives?|funiculaires?|ferroviaires?|passages? [àa] niveau)\b/iu'],
        'license' => $licenceOuverte,
        'credit' => 'Archives municipales de Sèvres',
        'source_url' => 'https://sevres-seineouest.opendatasoft.com/explore/dataset/cartes-postales-anciennes-des-archives-municipales/table/?q={id}',
        'thumb_suffix' => '/300/',
        'type' => 'postcard',
    ],

    'gadagne-cp' => [
        'provider' => 'gadagne-cp',
        'name' => 'Musée Gadagne (Lyon), cartes postales',
        'driver' => 'wfs',
        'resource' => 'https://data.grandlyon.com/geoserver/ville-de-lyon/ows?SERVICE=WFS&VERSION=2.0.0&request=GetFeature&typename=ville-de-lyon:lyon.gadagne_carte_postale&outputFormat=application/json&SRSNAME=EPSG:4326',
        'portal' => 'https://data.grandlyon.com/jeux-de-donnees/cartes-postales-musee-gadagne-ville-lyon/info',
        'prefilter' => [],
        'fields' => [
            'id' => 'numero_inventaire',
            'inventory' => 'numero_inventaire',
            'title' => 'titre_cp',
            'commune' => 'commune_pdv',
            'place' => 'lieu_represente',
            'image' => 'lien_image',
            'themes' => 'infra_tran',
            'publisher' => 'editeur',
        ],
        'rail' => ['fields' => ['title', 'themes', 'place'], 'pattern' => '/\b(gares?|chemins? de fer|tramways?|trains?|viaducs?|tunnels?|funiculaires?|ficelle|locomotives?|ferroviaires?|passages? [àa] niveau|rail|voie ferr[ée]e)\b/iu'],
        'license' => [
            'kind' => 'fixed',
            'license' => 'Licence Ouverte 2.0 (Etalab)',
            'license_url' => 'https://www.etalab.gouv.fr/wp-content/uploads/2017/04/ETALAB-Licence-Ouverte-v2.0.pdf',
            'rights_status' => 'open_license',
            'commercial_ok' => true,
            'derivatives_ok' => true,
            'share_alike' => false,
        ],
        'credit' => 'Ville de Lyon, musée Gadagne',
        'credit_with_inventory' => true,
        'source_url' => 'https://data.grandlyon.com/jeux-de-donnees/cartes-postales-musee-gadagne-ville-lyon/info',
        'thumb_suffix' => '',
        'point_precise' => true,
        'type' => 'postcard',
    ],

    // ---- Fonds ajoutés par l'agent medias-3 (22/11/2026), voir storage/app/reports/agents/aprem-2111/medias-3.md ----

    'sl71-cp' => [
        'provider' => 'sl71-cp',
        'name' => 'Archives départementales de Saône-et-Loire, cartes postales (6 FI)',
        'driver' => 'tabular',
        // Ressource « Cartes postales » du jeu « Données mises à disposition par les Archives départementales de Saône-et-Loire »
        // (data.gouv.fr, Licence Ouverte « fr-lo », mise à jour du 03/04/2019). Le CSV est en Windows-1252 lu comme du 1250 par
        // data.gouv.fr : `repair` remet les accents. static.data.gouv.fr/resources est interdit par son robots.txt : API tabulaire seulement.
        'resource' => 'c07e6169-d26c-4466-b95c-dc982aac59b3',
        'portal' => 'https://www.data.gouv.fr/datasets/donnees-mises-a-disposition-par-les-archives-departementales-de-saone-et-loire/',
        'repair' => 'cp1250-as-cp1252',
        'prefilter' => [
            ['Titre__contains' => 'gare'],
            ['Titre__contains' => 'halte'],
            ['Titre__contains' => 'viaduc'],
            ['Titre__contains' => 'chemin de fer'],
            ['Titre__contains' => 'locomotive'],
            ['Titre__contains' => 'tacot'],
            ['Titre__contains' => 'train'],
            ['Titre__contains' => 'tramway'],
            ['Titre__contains' => 'tunnel'],
        ],
        'fields' => [
            'id' => 'Reference',
            'title' => 'Titre',
            'caption' => 'Analyse',
            'date' => 'Date',
            'commune' => 'indexation lieu',
            'image' => 'Lien vers image Open Data',
            'source' => 'Lien ark Image',
        ],
        // « indexation lieu » est une liste (arrondissement, canton, commune) : la commune est celle que le titre nomme.
        'commune_from_list' => ' / ',
        'upgrade_https' => true,
        'rail' => ['fields' => ['title', 'caption'], 'pattern' => '/\b(gares?|halte|chemins? de fer|tramways?|trains?|viaducs?|tunnels?|locomotives?|tacot|passages? [àa] niveau|ferroviaires?|voie ferr[ée]e)\b/iu'],
        'license' => $licenceOuverte,
        'credit' => 'Archives départementales de Saône-et-Loire',
        'source_url' => 'https://www.archives71.fr/',
        'type' => 'postcard',
        // Dates vagues (« Après 1903 ») : les cartes « Après 1950 » (droits d'éditeurs en cours) ne sont pas reprises.
        'max_year' => 1944,
    ],

    'getty' => [
        'provider' => 'getty',
        'name' => 'J. Paul Getty Museum, Open Content : photographies de chemins de fer français',
        'driver' => 'getty',
        'resource' => 'https://data.getty.edu/museum/collection/sparql',
        'portal' => 'https://www.getty.edu/projects/open-content-program/',
        // Albums de plaques : Baldus, Paris à Lyon et à la Méditerranée (84.XO.401, doublé en 84.XO.734.1), Baldus, chemin de fer du
        // Bourbonnais (84.XO.393), Collard, lignes de Corbeil, de Saint-Étienne et de Tarare (84.XM.407).
        'albums' => ['84.XO.401', '84.XO.734.1', '84.XO.393', '84.XM.407'],
        'keywords' => 'viaduc|gare|chemins? de fer|embarcad[eè]re|souterrain',
        'fields' => [
            'id' => 'accession',
            'title' => 'title',
            'date' => 'date',
            'author' => 'author',
            'commune' => 'place',
            'place' => 'place',
            'image' => 'image',
            'thumb' => 'thumb',
            'source' => 'homepage',
            'rights_uri' => 'rights_uri',
            'rights_display' => 'rights_display',
            'country' => 'country',
        ],
        'commune_split' => ',',
        // Seules les images dont l'objet porte « CC0 1.0 » et « Public Domain » (programme Open Content) et dont le lieu représenté
        // est en France (ou, faute de lieu, qui sont des planches des albums français de la liste).
        'require_pattern' => [
            'rights_uri' => '#^http://creativecommons\.org/publicdomain/zero/1\.0/#',
            'rights_display' => '/^Public Domain$/',
            'country' => '/^France$/',
        ],
        'rail' => ['fields' => ['title'], 'pattern' => '/\b(gares?|chemins? de fer|viaducs?|souterrains?|embarcad[eè]res?|tunnels?|voie)\b/iu'],
        'license' => [
            'kind' => 'fixed',
            'license' => 'CC0 1.0',
            'license_url' => 'https://creativecommons.org/publicdomain/zero/1.0/deed.fr',
            'rights_status' => 'public_domain',
            'commercial_ok' => true,
            'derivatives_ok' => true,
            'share_alike' => false,
        ],
        'credit' => 'The J. Paul Getty Museum, Los Angeles (Open Content)',
        'credit_with_inventory' => true,
        'source_url' => 'https://www.getty.edu/art/collection/',
        'title_match' => true,
        'dedupe' => true,
        'type' => 'photo',
        'process' => 'épreuve photographique',
    ],

    'enpc-ph' => [
        'provider' => 'enpc-ph',
        'name' => 'École nationale des ponts et chaussées, Héritage des ponts et chaussées (photographies, chantiers de lignes)',
        'driver' => 'iiif',
        'resource' => 'https://heritage.ecoledesponts.fr/sitemap.txt',
        'portal' => 'https://heritage.ecoledesponts.fr/enpc/content/reutiliser-nos-collections',
        'sitemap' => 'https://heritage.ecoledesponts.fr/sitemap.txt',
        'ark_prefix' => 'btv',
        'manifest' => 'https://heritage.ecoledesponts.fr/iiif/ark:/12148/{ark}/manifest.json',
        // Le serveur de l'École n'envoie pas son certificat intermédiaire.
        'ca_chain' => 'database/data/certs/harica-geant-tls-r1.pem',
        'image_sizes' => [1600, 400],
        'iiif_quality' => 'native.jpg',
        'fields' => [
            'id' => 'ark',
            'title' => 'title',
            'date' => 'date',
            'author' => 'author',
            'inventory' => 'inventory',
            'image' => 'image',
            'thumb' => 'thumb',
            'source' => 'source',
            'repository' => 'repository',
            'shelfmark' => 'shelfmark',
            'type' => 'type',
        ],
        // Seuls les documents de l'École (dépôt et cote de l'École : « Source : École nationale des ponts et chaussées ») sont sous
        // Licence Ouverte ; les reproductions « Source : Bibliothèque nationale de France » et celles d'autres institutions suivent
        // d'autres règles (non commercial libre, commercial payant) : refusées.
        'require_pattern' => [
            'repository' => '/^Ecole nationale des ponts et chauss[ée]es$/iu',
            'shelfmark' => '/^Ecole nationale des ponts et chauss[ée]es,/iu',
            'type' => '/^image$/i',
        ],
        'rail' => ['fields' => ['title'], 'pattern' => '/\b(gares?|halte|chemins? de fer|viaducs?|tunnels?|souterrains?|lignes?|embranchements?|m[ée]tropolitain|locomotives?|voie ferr[ée]e)\b/iu'],
        'license' => [
            'kind' => 'fixed',
            'license' => 'Licence Ouverte (Etalab)',
            'license_url' => 'https://www.etalab.gouv.fr/licence-ouverte-open-licence/',
            'rights_status' => 'open_license',
            'commercial_ok' => true,
            'derivatives_ok' => true,
            'share_alike' => false,
        ],
        // Le fonds garde des vues d'Italie, d'Autriche, du Portugal, de Russie et d'Angleterre : leurs titres d'origine sont étrangers.
        'exclude_title' => '/\b(?:Viadotto|Stazione|Bahnhof|Br[üu]cke|Ansicht|Caminhos de ferro|Linha|Garnskirk|locomotive engine|first locomotive|Volga|Orenbourg)\b|[\p{L}\d]\s+\[(?:Viaduc|Gare|Vue|Pont|Bâtiment|Station|Tunnel|Souterrain|Plan)\b/iu',
        'credit' => 'École nationale des ponts et chaussées',
        'source_url' => 'https://heritage.ecoledesponts.fr/ark:/12148/{id}',
        'title_match' => true,
        'clean_title' => true,
        'type' => 'photo',
        // Photographies de 1855 à 1905 ; au-delà de 1930, le droit d'auteur peut courir : non repris.
        'max_year' => 1930,
    ],

    'numistral' => [
        'provider' => 'numistral',
        'name' => 'Numistral (BNU et Université de Strasbourg) : images de chemins de fer, de gares et de tramways',
        'driver' => 'iiif',
        'resource' => 'https://numistral.bnf.fr/services/engine/search/sru',
        'portal' => 'https://www.numistral.fr/fr/content/conditions-dutilisation',
        // Pas de robots.txt sur numistral.bnf.fr (404 le 22/11/2026) ; l'entrepôt OAI-PMH que le site invite à moissonner
        // (oai.bnf.fr) ne répondait pas : les identifiants viennent de la recherche (images seulement), les droits du manifeste.
        'search_url' => 'https://numistral.bnf.fr/services/engine/search/sru',
        'searches' => [
            '(gallica all "chemin de fer") and (dc.type all "image")',
            '(gallica all "gare") and (dc.type all "image")',
            '(gallica all "locomotive") and (dc.type all "image")',
            '(gallica all "tramway") and (dc.type all "image")',
            '(gallica all "viaduc") and (dc.type all "image")',
        ],
        'ark_prefix' => 'btv',
        'manifest' => 'https://numistral.bnf.fr/iiif/ark:/12148/{ark}/manifest.json',
        'image_sizes' => [1600, 400],
        'iiif_quality' => 'native.jpg',
        'fields' => [
            'id' => 'ark',
            'title' => 'title',
            'date' => 'date',
            'author' => 'author',
            'inventory' => 'inventory',
            'image' => 'image',
            'thumb' => 'thumb',
            'source' => 'source',
            'repository' => 'repository',
            'type' => 'type',
        ],
        // « Source : Bibliothèque nationale et universitaire de Strasbourg » et « Source : Bibliothèque numérique patrimoniale de
        // l'Université de Strasbourg » : Licence Ouverte, mention de la source. Les documents de la BnF (non commercial seulement), de
        // l'Université de Haute-Alsace (non commercial) et de Mulhouse (en partie) sont refusés.
        'require_pattern' => [
            'repository' => '/^(Biblioth[èe]que nationale et universitaire de Strasbourg|Universit[ée] de Strasbourg|Biblioth[èe]que num[ée]rique patrimoniale de l.Universit[ée] de Strasbourg)$/iu',
            'type' => '/^image$/i',
        ],
        'rail' => ['fields' => ['title'], 'pattern' => '/\b(gares?|halte|stations?|chemins? de fer|viaducs?|tunnels?|tramways?|locomotives?|trains?|voie ferr[ée]e|ferroviaires?)\b/iu'],
        'license' => [
            'kind' => 'fixed',
            'license' => 'Licence Ouverte (Etalab)',
            'license_url' => 'https://www.etalab.gouv.fr/licence-ouverte-open-licence/',
            'rights_status' => 'open_license',
            'commercial_ok' => true,
            'derivatives_ok' => true,
            'share_alike' => false,
        ],
        // Noms saisis à la main (identité lue sur les titres : « Strasbourg - La Gare Centrale », gare de 1883, est la gare actuelle).
        'title_aliases' => ['/\bStrasbourg\b.*\bgare centrale\b/iu' => 'Strasbourg-Ville'],
        'credit' => 'Bibliothèque nationale et universitaire de Strasbourg',
        'source_url' => 'https://numistral.bnf.fr/ark:/12148/{id}',
        'title_match' => true,
        'clean_title' => true,
        'type' => 'photo',
        'max_year' => 1944,
    ],
];
