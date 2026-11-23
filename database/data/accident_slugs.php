<?php

/**
 * Noms de fiche (slugs) retenus pour les accidents dont la date ou la commune de la source a été corrigée
 * d'après les documents du contexte (database/data/accident_contexts/ : `corrections`).
 *
 * L'import forme le slug d'une fiche à sa création, d'après la date et la commune LUES DANS LA SOURCE (celles
 * que la correction écarte). Sur une base vide, il redonnerait donc l'ancien nom, et le fichier de contexte
 * (nommé d'après le slug retenu) ne trouverait plus sa fiche. Cette table fait le lien : à la création d'une
 * fiche, si une de ses sources est ici, elle reçoit le slug retenu au lieu du slug calculé. Sur une base qui
 * contient déjà la fiche, rien ne change (le slug n'est jamais recalculé). Décision de Baptiste du 21/11/2026 :
 * le nouveau nom remplace l'ancien, sans alias (l'ancienne adresse n'existe plus).
 *
 *   - key    : source de la fiche, « fournisseur:identifiant » (accident_sources.provider et external_id) ;
 *   - from   : slug que l'import aurait formé (nom d'avant la correction, gardé pour mémoire) ;
 *   - slug   : slug retenu, égal au nom du fichier de contexte et à la clé `slug` de ce fichier ;
 *   - reason : ce qui a été corrigé.
 *
 * Un ancien slug de cette table n'est plus donné à une autre fiche (suffixe -2). Test : AccidentSlugTableTest.
 */
return [
    ['key' => 'wikipedia:fr:1834-06-21-roanne-420cada6', 'from' => '1834-06-21-roanne', 'slug' => '1834-06-21-neulise', 'reason' => 'commune corrigée d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1855-09-09-versailles-rive-gauche-18eea339', 'from' => '1855-09-09-versailles', 'slug' => '1855-09-09-paris', 'reason' => 'commune corrigée d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1864-01-05-pierrefitte-stains-9935b865', 'from' => '1864-01-05-calais', 'slug' => '1864-01-05-pierrefitte-sur-seine', 'reason' => 'commune corrigée d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1871-02-26-besle-33afe7f5', 'from' => '1871-02-26-guemene-penfao', 'slug' => '1871-02-26-langon', 'reason' => 'commune corrigée d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1872-07-20-alsace-lorraine-11df6cfb', 'from' => '1872-07-20-maubeuge', 'slug' => '1872-07-20-dannemarie', 'reason' => 'commune corrigée d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1873-07-22-bressuire-9d3bab97', 'from' => '1873-07-22-bressuire', 'slug' => '1873-07-22-chinon', 'reason' => 'commune corrigée d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1874-04-04-dijon-7c92b1c5', 'from' => '1874-04-04-dijon', 'slug' => '1874-04-04-perrigny-les-dijon', 'reason' => 'commune corrigée d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1878-07-12-saint-malo-c981df86', 'from' => '1878-07-12-saint-malo', 'slug' => '1878-07-12-saint-aubin-des-landes', 'reason' => 'commune corrigée d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1879-12-05-avricourt-93e8eb31', 'from' => '1879-12-05-avricourt', 'slug' => '1879-12-05-bondy', 'reason' => 'commune corrigée d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1881-01-09-mezy-e6ecf929', 'from' => '1881-01-09-epernay', 'slug' => '1881-01-09-mezy-moulins', 'reason' => 'commune corrigée d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1881-08-27-trayas-9afb5ad1', 'from' => '1881-08-27-saint-raphael', 'slug' => '1881-08-28-saint-raphael', 'reason' => 'date corrigée d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1881-09-19-coutras-cbd37331', 'from' => '1881-09-19-coutras', 'slug' => '1881-09-19-guitres', 'reason' => 'commune corrigée d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1883-07-05-valenciennes-3dfb2ca6', 'from' => '1883-07-05-valenciennes', 'slug' => '1883-07-05-orchies', 'reason' => 'commune corrigée d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1883-08-15-gray-d31840f2', 'from' => '1883-08-15-gray', 'slug' => '1883-08-15-chalindrey', 'reason' => 'commune corrigée d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1884-05-14-saint-denis-003579ea', 'from' => '1884-05-14-saint-denis', 'slug' => '1884-05-13-saint-denis', 'reason' => 'date corrigée d\'après les documents du contexte'],
    ['key' => 'wikidata:Q19606254', 'from' => '1888-09-05-baulme-la-roche', 'slug' => '1888-09-05-velars-sur-ouche', 'reason' => 'commune corrigée d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1890-08-08-morlaix-e8962d61', 'from' => '1890-08-08-morlaix', 'slug' => '1890-08-08-guingamp', 'reason' => 'commune corrigée d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1892-09-11-marcheprime-eea812f1', 'from' => '1892-09-11-marcheprime', 'slug' => '1892-09-11-biganos', 'reason' => 'commune corrigée d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1894-11-12-cere-08535a03', 'from' => '1894-11-12-cere', 'slug' => '1894-11-10-laval-de-cere', 'reason' => 'date et commune corrigées d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1896-07-13-bournoncle-saint-pierre-51726f6a', 'from' => '1896-07-13-bournoncle-saint-pierre', 'slug' => '1896-07-13-saint-georges-daurac', 'reason' => 'commune corrigée d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1897-07-08-nancy-c9d7e434', 'from' => '1897-07-08-nancy', 'slug' => '1897-07-08-mars-la-tour', 'reason' => 'commune corrigée d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1900-08-14-brest-31d4953c', 'from' => '1900-08-14-brest', 'slug' => '1900-08-14-plabennec', 'reason' => 'commune corrigée d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1902-11-04-soissons-abf7dac9', 'from' => '1902-11-04-soissons', 'slug' => '1902-11-04-bazoches-et-saint-thibaut', 'reason' => 'commune corrigée d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1902-12-16-noyant-5de5d9e2', 'from' => '1902-12-16-noyant', 'slug' => '1902-12-15-noyant', 'reason' => 'date corrigée d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1904-05-01-troyes-97ad6f15', 'from' => '1904-05-01-troyes', 'slug' => '1904-05-01-roissy-en-brie', 'reason' => 'commune corrigée d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1906-11-13-saint-amand-montrond-orval-e20d1939', 'from' => '1906-11-13-chateaumeillant', 'slug' => '1906-11-12-saint-amand-montrond', 'reason' => 'date et commune corrigées d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1907-08-14-bagnols-chadenet-bb2f4012', 'from' => '1907-08-14-chadenet', 'slug' => '1907-08-14-allenc', 'reason' => 'commune corrigée d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1907-12-04-creil-95ddaa81', 'from' => '1907-12-04-creil', 'slug' => '1907-12-04-bertry', 'reason' => 'commune corrigée d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1908-11-08-sete-5f198cd5', 'from' => '1908-11-08-sete', 'slug' => '1908-11-08-grisolles', 'reason' => 'commune corrigée d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1908-11-30-foret-de-saint-germain-en-laye-8ec10a03', 'from' => '1908-11-30-maisons-laffitte', 'slug' => '1908-11-30-saint-germain-en-laye', 'reason' => 'commune corrigée d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1909-08-08-dieppe-66bb1f1d', 'from' => '1909-08-08-dieppe', 'slug' => '1909-08-08-louviers', 'reason' => 'commune corrigée d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1909-12-23-leforest-a2f67535', 'from' => '1909-12-23-leforest', 'slug' => '1909-12-22-leforest', 'reason' => 'date corrigée d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1910-05-23-suresnes-796c16bb', 'from' => '1910-05-23-suresnes', 'slug' => '1910-05-23-issy-les-moulineaux', 'reason' => 'commune corrigée d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1910-09-28-montereau-73ae5029', 'from' => '1910-09-28-montereau', 'slug' => '1910-09-28-montereau-fault-yonne', 'reason' => 'commune corrigée d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1911-07-11-saint-saviol-4230e41d', 'from' => '1911-07-11-saint-saviol', 'slug' => '1911-07-09-saint-saviol', 'reason' => 'date corrigée d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1911-11-03-campbon-f23b200f', 'from' => '1911-11-03-campbon', 'slug' => '1911-10-03-campbon', 'reason' => 'date corrigée d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1911-12-10-paris-nord-73c2fefa', 'from' => '1911-12-10-hirson', 'slug' => '1911-12-10-paris', 'reason' => 'commune corrigée d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1912-05-18-paris-nord-1f695d90', 'from' => '1912-05-18-creil', 'slug' => '1912-05-18-paris', 'reason' => 'commune corrigée d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1912-07-28-tregastel-2138e067', 'from' => '1912-07-28-tregastel', 'slug' => '1912-07-28-morlaix', 'reason' => 'commune corrigée d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1912-09-22-caen-3e392923', 'from' => '1912-09-22-caen', 'slug' => '1912-09-22-merville-franceville-plage', 'reason' => 'commune corrigée d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1913-03-06-bourg-en-bresse-a1de50a4', 'from' => '1913-03-06-bourg-en-bresse', 'slug' => '1913-03-06-coligny', 'reason' => 'commune corrigée d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1913-07-13-dijon-ville-582db198', 'from' => '1913-07-13-pontarlier', 'slug' => '1913-07-13-dijon', 'reason' => 'commune corrigée d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1913-10-13-banassac-la-canourgue-b76f3926', 'from' => '1913-10-13-marvejols', 'slug' => '1913-10-13-banassac-canilhac', 'reason' => 'commune corrigée d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1917-08-09-musee-dorsay-97f016a3', 'from' => '1917-08-09-juvisy-sur-orge', 'slug' => '1917-08-09-paris', 'reason' => 'commune corrigée d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1919-12-11-marseille-saint-charles-e56ccf89', 'from' => '1919-12-11-aix-en-provence', 'slug' => '1919-12-11-marseille', 'reason' => 'commune corrigée d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1921-02-08-saint-cyr-de-favieres-768f8033', 'from' => '1921-02-08-saint-cyr-de-favieres', 'slug' => '1921-02-08-feurs', 'reason' => 'commune corrigée d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1922-07-14-paris-nord-631850cc', 'from' => '1922-07-14-ecouen', 'slug' => '1922-07-14-paris', 'reason' => 'commune corrigée d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1922-10-23-saint-denis-854eb374', 'from' => '1922-10-23-le-treport', 'slug' => '1922-10-23-saint-denis', 'reason' => 'commune corrigée d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1922-12-23-paris-nord-3f5c5377', 'from' => '1922-12-23-crepy-en-valois', 'slug' => '1922-12-23-saint-denis', 'reason' => 'commune corrigée d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1924-03-02-rueil-malmaison-0134e48a', 'from' => '1924-03-02-rueil-malmaison', 'slug' => '1924-03-02-paris', 'reason' => 'commune corrigée d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1924-11-06-barentin-c6bb4656', 'from' => '1924-11-06-barentin', 'slug' => '1924-11-06-pissy-poville', 'reason' => 'commune corrigée d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1925-03-25-clain-04ca7b2b', 'from' => '1925-03-25-poitiers', 'slug' => '1925-03-25-saint-benoit', 'reason' => 'commune corrigée d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1926-08-15-paris-gare-de-lyon-1bae92eb', 'from' => '1926-08-15-villeneuve-saint-georges', 'slug' => '1926-08-15-paris', 'reason' => 'commune corrigée d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1926-08-18-saint-pierre-du-vauvray-22c48377', 'from' => '1926-08-18-saint-pierre-du-vauvray', 'slug' => '1926-08-18-les-andelys', 'reason' => 'commune corrigée d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1926-12-15-dijon-e6add45e', 'from' => '1926-12-15-dijon', 'slug' => '1926-12-15-saint-seine-labbaye', 'reason' => 'commune corrigée d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1927-06-03-moulins-2b69678b', 'from' => '1927-06-03-moulins', 'slug' => '1927-06-03-bessay-sur-allier', 'reason' => 'commune corrigée d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1930-10-03-paris-saint-lazare-6a17ef45', 'from' => '1930-10-03-mantes-la-jolie', 'slug' => '1930-10-03-paris', 'reason' => 'commune corrigée d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1932-12-16-rivesaltes-5f501765', 'from' => '1932-12-16-rivesaltes', 'slug' => '1932-12-16-maury', 'reason' => 'commune corrigée d\'après les documents du contexte'],
    ['key' => 'wikidata:Q22247907', 'from' => '1933-10-24-le-val-dore', 'slug' => '1933-10-24-la-croisille', 'reason' => 'commune corrigée d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1937-07-10-mans-7cf8def9', 'from' => '1937-07-10-rennes', 'slug' => '1937-07-10-le-mans', 'reason' => 'commune corrigée d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1938-02-06-laigle-8cf09b21', 'from' => '1938-02-06-laigle', 'slug' => '1938-02-06-randonnai', 'reason' => 'commune corrigée d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1938-09-08-locmine-6b990a71', 'from' => '1938-09-08-locmine', 'slug' => '1938-09-08-radenac', 'reason' => 'commune corrigée d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1938-12-04-amiens-e14e313a', 'from' => '1938-12-04-amiens', 'slug' => '1938-12-04-clery-sur-somme', 'reason' => 'commune corrigée d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1939-04-20-amiens-9fc4d40f', 'from' => '1939-04-20-amiens', 'slug' => '1939-04-20-daours', 'reason' => 'commune corrigée d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1940-01-31-saverne-48c94bfb', 'from' => '1940-01-31-saverne', 'slug' => '1940-01-31-dettwiller', 'reason' => 'commune corrigée d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1940-05-18-exode-de-1940-en-france-6600420f', 'from' => '1940-05-18-morgny', 'slug' => '1940-05-18-morgny-la-pommeraye', 'reason' => 'commune corrigée d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1943-10-06-chalon-sur-saone-d6864d19', 'from' => '1943-10-06-chalon-sur-saone', 'slug' => '1943-10-06-varennes-le-grand', 'reason' => 'commune corrigée d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1953-10-16-serezin-d7482a67', 'from' => '1953-10-16-saint-rambert-dalbon', 'slug' => '1953-10-16-serezin-du-rhone', 'reason' => 'commune corrigée d\'après les documents du contexte'],
    ['key' => 'wikidata:Q31836460', 'from' => '1954-07-03-tournon-sur-rhone', 'slug' => '1954-07-03-chateaubourg', 'reason' => 'commune corrigée d\'après les documents du contexte'],
    ['key' => 'wikidata:Q15715888', 'from' => '2014-02-08-annot', 'slug' => '2014-02-08-saint-benoit', 'reason' => 'commune corrigée d\'après les documents du contexte'],
    ['key' => 'wikidata:Q86673995', 'from' => '2019-10-16-saint-pierre-sur-vence', 'slug' => '2019-10-16-boulzicourt', 'reason' => 'commune corrigée d\'après les documents du contexte'],
    ['key' => 'bea-tt:2022-02-25-hochfelden-pn', 'from' => '2022-02-25-hochfelden', 'slug' => '2022-02-24-hochfelden', 'reason' => 'date corrigée d\'après les documents du contexte'],
    ['key' => 'bea-tt:2022-05-30-bizanos-pn', 'from' => '2022-05-30-bizanos', 'slug' => '2022-05-29-bizanos', 'reason' => 'date corrigée d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1900-05-30-saint-petersbourg-a78cd435', 'from' => '1900-05-30-tergnier', 'slug' => '1900-05-30-jussy', 'reason' => 'commune corrigée d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1920-09-19-arles-sur-tech-1d17bab1', 'from' => '1920-09-19-arles-sur-tech', 'slug' => '1920-09-19-amelie-les-bains-palalda', 'reason' => 'commune corrigée d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1935-07-01-loudun-94ace121', 'from' => '1935-07-01-loudun', 'slug' => '1935-07-01-cernay', 'reason' => 'commune corrigée d\'après les documents du contexte'],
    ['key' => 'wikipedia:fr:1910-09-20-la-faloise-296562e4', 'from' => '1910-09-20-breteuil', 'slug' => '1910-09-20-la-faloise', 'reason' => 'commune corrigée d\'après les documents du contexte'],
];
