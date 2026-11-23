<?php

/**
 * Compagnies de chemins de fer historiques : une fiche par entrée (/companies/{slug}, `GET /api/v1/companies`).
 *
 * Fichier versionné et sourcé : les faits sur la compagnie elle-même (création, fusions, rachats, fin, siège) viennent
 * des articles Wikipédia en français et des éléments Wikidata cités dans `sources`, relus le 23/11/2026. Les lignes
 * qu'elle exploitait ne sont PAS ici : elles viennent des événements datés de concession (`line_events`, types
 * `concession` et `concession_end`, infobox de l'article de la ligne) que `App\Services\Companies\CompanyFigures`
 * rattache à la compagnie par ses `aliases`. Aucun rattachement n'est deviné.
 *
 * Clés :
 *   slug            adresse de la fiche
 *   kind            `grande-compagnie`, `etat`, `sncf`, `infrastructure` (gestionnaire d'infrastructure), `secondaire`
 *   name            nom officiel (nom propre, conservé dans les trois langues)
 *   short           appellation courante
 *   wikidata        élément Wikidata
 *   founded, ended  [date `AAAA-MM-JJ`, précision `day` ou `year`] ; ended null = toujours en activité
 *   seat            ville du siège (nom propre), absente quand aucune source ne la donne
 *   aliases         libellés d'infobox rattachés à la compagnie, normalisés (CompanyFigures::normalize)
 *   attach_from     année avant laquelle un événement de concession n'est pas rattaché (défaut : l'année de création)
 *   end_policy      fin d'un rattachement sans date de fin ni successeur : `company_end` (fin de la compagnie),
 *                   `open` (toujours en fonction), `unknown` (la fin n'est pas connue : listé, jamais compté par décennie)
 *   predecessors, successors   slugs des fiches voisines
 *   summary, events textes `fr`, `en`, `de` ; chaque événement porte sa date
 *   sources         [libellé, adresse]
 */
$wp = fn (string $title): string => 'https://fr.wikipedia.org/wiki/'.$title;
$wd = fn (string $id): string => 'https://www.wikidata.org/wiki/'.$id;

return [
    [
        'slug' => 'nord', 'kind' => 'grande-compagnie', 'name' => 'Compagnie des chemins de fer du Nord', 'short' => 'Nord', 'wikidata' => 'Q541879',
        'founded' => ['1845-09-20', 'day'], 'ended' => ['1937-12-31', 'day'], 'seat' => 'Paris',
        'aliases' => ['nord', 'du nord', 'ch de fer du nord', 'cie des chemins de fer du nord', 'compagnie des chemins de fer du nord', 'compagnie du chemin de fer du nord', 'compagnie du nord'],
        'end_policy' => 'company_end', 'predecessors' => [], 'successors' => ['sncf'],
        'summary' => [
            'fr' => 'Compagnie du nord de la France, créée par le banquier James de Rothschild et ses associés pour exploiter la concession de Paris à la frontière belge par Lille et Valenciennes. Elle garde son nom malgré l’absorption d’autres compagnies du secteur et sert de modèle aux autres grandes compagnies.',
            'en' => 'Company of northern France, created by the banker James de Rothschild and his partners to run the concession from Paris to the Belgian border through Lille and Valenciennes. It kept its name despite absorbing other companies of the region and served as a model for the other large companies.',
            'de' => 'Gesellschaft Nordfrankreichs, vom Bankier James de Rothschild und seinen Partnern gegründet, um die Konzession von Paris über Lille und Valenciennes zur belgischen Grenze zu betreiben. Sie behielt ihren Namen trotz der Übernahme anderer Gesellschaften der Region und diente den anderen großen Gesellschaften als Vorbild.',
        ],
        'events' => [
            ['1845-09-20', 'day', ['fr' => 'Création de la compagnie.', 'en' => 'The company is created.', 'de' => 'Gründung der Gesellschaft.']],
            ['1938-01-01', 'day', ['fr' => 'Son réseau est réuni dans la SNCF.', 'en' => 'Its network is merged into the SNCF.', 'de' => 'Ihr Netz geht in der SNCF auf.']],
        ],
        'sources' => [['Wikipédia', $wp('Compagnie_des_chemins_de_fer_du_Nord')], ['Wikidata', $wd('Q541879')]],
    ],
    [
        'slug' => 'est', 'kind' => 'grande-compagnie', 'name' => 'Compagnie des chemins de fer de l’Est', 'short' => 'Est', 'wikidata' => 'Q663187',
        'founded' => ['1845-01-01', 'year'], 'ended' => ['1937-12-31', 'day'], 'seat' => 'Paris',
        'aliases' => ['est', 'ch de fer de l est', 'cie de l est', 'compagnie des chemins de fer de l est', 'paris strasbourg', 'paris a strasbourg'],
        'end_policy' => 'company_end', 'predecessors' => [], 'successors' => ['sncf'],
        'summary' => [
            'fr' => 'Société anonyme créée en 1845 sous le nom de Compagnie du chemin de fer de Paris à Strasbourg. Elle prend le nom de Compagnie des chemins de fer de l’Est en 1854, à la suite du rachat d’autres compagnies et de nouvelles concessions. Les concessions antérieures à ce changement de nom sont rattachées à la compagnie sous le nom de Paris à Strasbourg.',
            'en' => 'Joint-stock company created in 1845 as the Compagnie du chemin de fer de Paris à Strasbourg. It took the name Compagnie des chemins de fer de l’Est in 1854, after buying other companies and receiving new concessions. Concessions dated before the change of name are attached to the company under the name Paris à Strasbourg.',
            'de' => 'Aktiengesellschaft, 1845 als Compagnie du chemin de fer de Paris à Strasbourg gegründet. Sie nahm 1854 den Namen Compagnie des chemins de fer de l’Est an, nach dem Kauf anderer Gesellschaften und neuen Konzessionen. Die Konzessionen vor der Umbenennung sind der Gesellschaft unter dem Namen Paris à Strasbourg zugeordnet.',
        ],
        'events' => [
            ['1845-01-01', 'year', ['fr' => 'Création sous le nom de Compagnie du chemin de fer de Paris à Strasbourg.', 'en' => 'Created as the Compagnie du chemin de fer de Paris à Strasbourg.', 'de' => 'Gründung als Compagnie du chemin de fer de Paris à Strasbourg.']],
            ['1854-01-01', 'year', ['fr' => 'Changement de nom en Compagnie des chemins de fer de l’Est, après le rachat d’autres compagnies et l’obtention de nouvelles concessions.', 'en' => 'Renamed Compagnie des chemins de fer de l’Est, after buying other companies and obtaining new concessions.', 'de' => 'Umbenennung in Compagnie des chemins de fer de l’Est nach dem Kauf anderer Gesellschaften und dem Erhalt neuer Konzessionen.']],
            ['1938-01-01', 'day', ['fr' => 'Nationalisation : son réseau est réuni dans la SNCF.', 'en' => 'Nationalisation: its network is merged into the SNCF.', 'de' => 'Verstaatlichung: Ihr Netz geht in der SNCF auf.']],
        ],
        'sources' => [['Wikipédia', $wp('Compagnie_des_chemins_de_fer_de_l\'Est')], ['Wikidata', $wd('Q663187')]],
    ],
    [
        'slug' => 'ouest', 'kind' => 'grande-compagnie', 'name' => 'Compagnie des chemins de fer de l’Ouest', 'short' => 'Ouest', 'wikidata' => 'Q1069374',
        'founded' => ['1855-06-16', 'day'], 'ended' => ['1908-12-31', 'day'], 'seat' => 'Paris',
        'aliases' => ['ouest', 'ch de fer de l ouest', 'compagnie des chemins de fer de l ouest'],
        'end_policy' => 'company_end', 'predecessors' => [], 'successors' => ['etat'],
        'summary' => [
            'fr' => 'Compagnie créée en 1855 par la fusion de plusieurs compagnies desservant la Normandie et la Bretagne. Rachetée en 1909 par l’Administration des chemins de fer de l’État, qui reprend son réseau (dit alors « Ouest-État »).',
            'en' => 'Company created in 1855 by the merger of several companies serving Normandy and Brittany. Bought in 1909 by the Administration des chemins de fer de l’État, which took over its network (then called "Ouest-État").',
            'de' => 'Gesellschaft, 1855 durch den Zusammenschluss mehrerer Gesellschaften der Normandie und der Bretagne entstanden. 1909 von der Administration des chemins de fer de l’État übernommen, die ihr Netz fortführte (damals „Ouest-État“ genannt).',
        ],
        'events' => [
            ['1855-06-16', 'day', ['fr' => 'Création par fusion de plusieurs compagnies de Normandie et de Bretagne.', 'en' => 'Created by merging several companies of Normandy and Brittany.', 'de' => 'Gründung durch Zusammenschluss mehrerer Gesellschaften der Normandie und der Bretagne.']],
            ['1909-01-01', 'day', ['fr' => 'Rachat par l’Administration des chemins de fer de l’État.', 'en' => 'Bought by the Administration des chemins de fer de l’État.', 'de' => 'Übernahme durch die Administration des chemins de fer de l’État.']],
        ],
        'sources' => [['Wikipédia', $wp('Compagnie_des_chemins_de_fer_de_l\'Ouest')], ['Wikidata', $wd('Q1069374')]],
    ],
    [
        'slug' => 'paris-orleans', 'kind' => 'grande-compagnie', 'name' => 'Compagnie du chemin de fer de Paris à Orléans', 'short' => 'Paris-Orléans (PO)', 'wikidata' => 'Q5998584',
        'founded' => ['1838-08-13', 'day'], 'ended' => ['1937-12-31', 'day'], 'seat' => 'Paris',
        'aliases' => ['po', 'cie po', 'paris a orleans'],
        'end_policy' => 'company_end', 'predecessors' => [], 'successors' => ['sncf'],
        'summary' => [
            'fr' => 'Société anonyme, l’une des grandes compagnies privées. Elle desservait l’ensemble du Sud-Ouest, avec pour tête de réseau parisienne la gare d’Austerlitz. En 1934, elle forme avec le Midi une communauté d’intérêt pour l’exploitation commune de leurs réseaux.',
            'en' => 'Joint-stock company, one of the large private companies. It served the whole of the south-west, with the Gare d’Austerlitz as its Paris terminus. In 1934 it formed a community of interest with the Midi to run their networks jointly.',
            'de' => 'Aktiengesellschaft, eine der großen Privatgesellschaften. Sie bediente den gesamten Südwesten, mit dem Gare d’Austerlitz als Pariser Kopfbahnhof. 1934 bildete sie mit dem Midi eine Interessengemeinschaft für den gemeinsamen Betrieb ihrer Netze.',
        ],
        'events' => [
            ['1838-08-13', 'day', ['fr' => 'Création de la compagnie.', 'en' => 'The company is created.', 'de' => 'Gründung der Gesellschaft.']],
            ['1934-01-01', 'year', ['fr' => 'Communauté d’intérêt avec la Compagnie du Midi : exploitation commune des deux réseaux.', 'en' => 'Community of interest with the Compagnie du Midi: joint operation of the two networks.', 'de' => 'Interessengemeinschaft mit der Compagnie du Midi: gemeinsamer Betrieb beider Netze.']],
            ['1938-01-01', 'day', ['fr' => 'Son réseau est réuni dans la SNCF.', 'en' => 'Its network is merged into the SNCF.', 'de' => 'Ihr Netz geht in der SNCF auf.']],
        ],
        'sources' => [['Wikipédia', $wp('Compagnie_du_chemin_de_fer_de_Paris_à_Orléans')], ['Wikidata', $wd('Q5998584')]],
    ],
    [
        'slug' => 'plm', 'kind' => 'grande-compagnie', 'name' => 'Compagnie des chemins de fer de Paris à Lyon et à la Méditerranée', 'short' => 'Paris-Lyon-Méditerranée (PLM)', 'wikidata' => 'Q1121154',
        'founded' => ['1857-07-03', 'day'], 'ended' => ['1937-12-31', 'day'], 'seat' => 'Paris',
        'aliases' => ['plm', 'ch de fer plm', 'plm france'],
        'end_policy' => 'company_end', 'predecessors' => [], 'successors' => ['sncf'],
        'summary' => [
            'fr' => 'L’une des plus importantes compagnies privées françaises, entre sa création en 1857 et sa nationalisation en 1938. Elle desservait le sud-est : Côte d’Azur, Provence, Cévennes, Alpes. Sa gare parisienne était la gare de Lyon.',
            'en' => 'One of the most important private French companies, from its creation in 1857 to its nationalisation in 1938. It served the south-east: the Côte d’Azur, Provence, the Cévennes and the Alps. Its Paris terminus was the Gare de Lyon.',
            'de' => 'Eine der bedeutendsten französischen Privatgesellschaften, von ihrer Gründung 1857 bis zur Verstaatlichung 1938. Sie bediente den Südosten: Côte d’Azur, Provence, Cevennen und Alpen. Ihr Pariser Bahnhof war der Gare de Lyon.',
        ],
        'events' => [
            ['1857-07-03', 'day', ['fr' => 'Création de la compagnie.', 'en' => 'The company is created.', 'de' => 'Gründung der Gesellschaft.']],
            ['1938-01-01', 'day', ['fr' => 'Nationalisation : son réseau est réuni dans la SNCF.', 'en' => 'Nationalisation: its network is merged into the SNCF.', 'de' => 'Verstaatlichung: Ihr Netz geht in der SNCF auf.']],
        ],
        'sources' => [['Wikipédia', $wp('Compagnie_des_chemins_de_fer_de_Paris_à_Lyon_et_à_la_Méditerranée')], ['Wikidata', $wd('Q1121154')]],
    ],
    [
        'slug' => 'midi', 'kind' => 'grande-compagnie', 'name' => 'Compagnie des chemins de fer du Midi et du Canal latéral à la Garonne', 'short' => 'Midi', 'wikidata' => 'Q378181',
        'founded' => ['1852-11-06', 'day'], 'ended' => ['1937-12-31', 'day'], 'seat' => 'Bordeaux',
        'aliases' => ['midi', 'du midi', 'compagnie du midi', 'ch de fer du midi'],
        'end_policy' => 'company_end', 'predecessors' => [], 'successors' => ['sncf'],
        'summary' => [
            'fr' => 'L’une des anciennes grandes compagnies privées. Son réseau desservait la partie du Sud-Ouest comprise entre la Garonne et les Pyrénées. Seule des grandes compagnies à ne pas avoir d’accès direct à Paris. En 1934, son exploitation fusionne avec celle du Paris-Orléans.',
            'en' => 'One of the former large private companies. Its network served the part of the south-west between the Garonne and the Pyrenees. It was the only large company without direct access to Paris. In 1934 its operation was merged with that of the Paris-Orléans.',
            'de' => 'Eine der ehemaligen großen Privatgesellschaften. Ihr Netz bediente den Teil des Südwestens zwischen Garonne und Pyrenäen. Als einzige der großen Gesellschaften hatte sie keinen direkten Zugang nach Paris. 1934 wurde ihr Betrieb mit dem der Paris-Orléans zusammengelegt.',
        ],
        'events' => [
            ['1852-11-06', 'day', ['fr' => 'Création de la compagnie.', 'en' => 'The company is created.', 'de' => 'Gründung der Gesellschaft.']],
            ['1934-01-01', 'year', ['fr' => 'Exploitation confiée à la structure commune avec le Paris-Orléans.', 'en' => 'Operation entrusted to the joint structure with the Paris-Orléans.', 'de' => 'Betrieb an die gemeinsame Struktur mit der Paris-Orléans übertragen.']],
            ['1938-01-01', 'day', ['fr' => 'Nationalisation du réseau : il est réuni dans la SNCF.', 'en' => 'Nationalisation of the network: it is merged into the SNCF.', 'de' => 'Verstaatlichung des Netzes: Es geht in der SNCF auf.']],
        ],
        'sources' => [['Wikipédia', $wp('Compagnie_des_chemins_de_fer_du_Midi_et_du_Canal_latéral_à_la_Garonne')], ['Wikidata', $wd('Q378181')]],
    ],
    [
        'slug' => 'etat', 'kind' => 'etat', 'name' => 'Administration des chemins de fer de l’État', 'short' => 'Réseau de l’État', 'wikidata' => 'Q1069376',
        'founded' => ['1878-05-25', 'day'], 'ended' => ['1937-12-31', 'day'], 'seat' => null,
        'aliases' => ['etat', 'etat non concedee', 'etat non concede', 'administration des chemins de fer de l etat', 'ch de fer de l etat'],
        'end_policy' => 'company_end', 'predecessors' => ['ouest'], 'successors' => ['sncf'],
        'summary' => [
            'fr' => 'Administration créée par le gouvernement à la fin du XIXe siècle (et non « compagnie » : le terme désignait une société commerciale). Son réseau résulte de l’incorporation des concessions de petites compagnies tombées en déshérence, puis, en 1909, du rachat de la Compagnie de l’Ouest.',
            'en' => 'Administration created by the government at the end of the 19th century (not a "company": that word meant a commercial firm). Its network came from taking over the concessions of small failing companies, then, in 1909, from the purchase of the Compagnie de l’Ouest.',
            'de' => 'Von der Regierung Ende des 19. Jahrhunderts geschaffene Verwaltung (keine „Gesellschaft“: dieser Begriff bezeichnete ein Handelsunternehmen). Ihr Netz entstand aus der Übernahme der Konzessionen kleiner, in Not geratener Gesellschaften und 1909 aus dem Kauf der Compagnie de l’Ouest.',
        ],
        'events' => [
            ['1878-05-25', 'day', ['fr' => 'Création de l’Administration des chemins de fer de l’État.', 'en' => 'The Administration des chemins de fer de l’État is created.', 'de' => 'Gründung der Administration des chemins de fer de l’État.']],
            ['1909-01-01', 'day', ['fr' => 'Rachat de la Compagnie de l’Ouest : son réseau s’ajoute à celui de l’État.', 'en' => 'Purchase of the Compagnie de l’Ouest: its network is added to the State network.', 'de' => 'Kauf der Compagnie de l’Ouest: Ihr Netz kommt zum Staatsnetz hinzu.']],
            ['1938-01-01', 'day', ['fr' => 'Son réseau est réuni dans la SNCF.', 'en' => 'Its network is merged into the SNCF.', 'de' => 'Ihr Netz geht in der SNCF auf.']],
        ],
        'sources' => [['Wikipédia', $wp('Administration_des_chemins_de_fer_de_l\'État')], ['Wikidata', $wd('Q1069376')]],
    ],
    [
        'slug' => 'alsace-lorraine', 'kind' => 'etat', 'name' => 'Administration des chemins de fer d’Alsace et de Lorraine', 'short' => 'Alsace-Lorraine (AL)', 'wikidata' => 'Q826750',
        'founded' => ['1919-06-19', 'day'], 'ended' => ['1937-12-31', 'day'], 'seat' => 'Paris',
        'aliases' => ['al', 'al non concedee', 'al non concede', 'al non concedees'],
        'attach_from' => 1918,
        'end_policy' => 'company_end', 'predecessors' => [], 'successors' => ['sncf'],
        'summary' => [
            'fr' => 'Administration de l’État français créée en 1919. Elle gérait et exploitait le réseau ferroviaire de l’Alsace et de la Moselle et, à titre provisoire, le réseau Guillaume-Luxembourg. Les événements de concession de 1918, année de la reprise du réseau, lui sont rattachés.',
            'en' => 'Administration of the French State created in 1919. It managed and ran the railway network of Alsace and Moselle and, provisionally, the Guillaume-Luxembourg network. Concession events of 1918, the year the network was taken over, are attached to it.',
            'de' => 'Verwaltung des französischen Staates, 1919 geschaffen. Sie verwaltete und betrieb das Eisenbahnnetz des Elsass und der Mosel und vorläufig das Netz Guillaume-Luxembourg. Konzessionsereignisse von 1918, dem Jahr der Übernahme des Netzes, sind ihr zugeordnet.',
        ],
        'events' => [
            ['1919-06-19', 'day', ['fr' => 'Création de l’Administration des chemins de fer d’Alsace et de Lorraine.', 'en' => 'The Administration des chemins de fer d’Alsace et de Lorraine is created.', 'de' => 'Gründung der Administration des chemins de fer d’Alsace et de Lorraine.']],
            ['1938-01-01', 'day', ['fr' => 'Disparition avec la création de la SNCF.', 'en' => 'Disappears with the creation of the SNCF.', 'de' => 'Auflösung mit der Gründung der SNCF.']],
        ],
        'sources' => [['Wikipédia', $wp('Administration_des_chemins_de_fer_d\'Alsace_et_de_Lorraine')], ['Wikidata', $wd('Q826750')]],
    ],
    [
        'slug' => 'sncf', 'kind' => 'sncf', 'name' => 'Société nationale des chemins de fer français', 'short' => 'SNCF', 'wikidata' => 'Q13646',
        'founded' => ['1938-01-01', 'day'], 'ended' => null, 'seat' => null,
        'aliases' => ['sncf', 'sncf france'],
        'attach_from' => 1937,
        'end_policy' => 'open', 'predecessors' => ['nord', 'est', 'paris-orleans', 'plm', 'midi', 'etat', 'alsace-lorraine'], 'successors' => [],
        'summary' => [
            'fr' => 'Entreprise ferroviaire publique, créée le 1er janvier 1938 par convention entre l’État et les compagnies préexistantes, en application du décret-loi du 31 août 1937. Les concessions qui lui sont rattachées commencent en 1937 (convention) ou en 1938. La fin d’un rattachement est celle que donne l’article de la ligne (la reprise de l’infrastructure par Réseau ferré de France en 1997, par exemple).',
            'en' => 'Public railway company, created on 1 January 1938 by an agreement between the State and the existing companies, under the decree-law of 31 August 1937. The concessions attached to it begin in 1937 (agreement) or 1938. The end of an attachment is the one given by the line’s article (the takeover of the infrastructure by Réseau ferré de France in 1997, for instance).',
            'de' => 'Öffentliches Eisenbahnunternehmen, am 1. Januar 1938 durch eine Vereinbarung zwischen dem Staat und den bestehenden Gesellschaften gegründet, aufgrund des Gesetzesdekrets vom 31. August 1937. Die ihr zugeordneten Konzessionen beginnen 1937 (Vereinbarung) oder 1938. Das Ende einer Zuordnung ist das im Artikel der Strecke genannte (zum Beispiel die Übernahme der Infrastruktur durch Réseau ferré de France 1997).',
        ],
        'events' => [
            ['1937-08-31', 'day', ['fr' => 'Décret-loi qui fonde la SNCF.', 'en' => 'Decree-law that founds the SNCF.', 'de' => 'Gesetzesdekret zur Gründung der SNCF.']],
            ['1938-01-01', 'day', ['fr' => 'Création de la SNCF : elle reprend les réseaux des grandes compagnies, de l’État et d’Alsace-Lorraine.', 'en' => 'Creation of the SNCF: it takes over the networks of the large companies, of the State and of Alsace-Lorraine.', 'de' => 'Gründung der SNCF: Sie übernimmt die Netze der großen Gesellschaften, des Staates und von Elsass-Lothringen.']],
            ['1997-02-13', 'day', ['fr' => 'Création de Réseau ferré de France, propriétaire de l’infrastructure.', 'en' => 'Creation of Réseau ferré de France, owner of the infrastructure.', 'de' => 'Gründung von Réseau ferré de France, Eigentümer der Infrastruktur.']],
            ['2015-01-01', 'day', ['fr' => 'La SNCF est divisée en trois établissements publics : SNCF, SNCF Réseau et SNCF Mobilités.', 'en' => 'The SNCF is split into three public bodies: SNCF, SNCF Réseau and SNCF Mobilités.', 'de' => 'Die SNCF wird in drei öffentliche Einrichtungen geteilt: SNCF, SNCF Réseau und SNCF Mobilités.']],
        ],
        'sources' => [['Wikipédia', $wp('Société_nationale_des_chemins_de_fer_français')], ['Wikidata', $wd('Q13646')]],
    ],
    [
        'slug' => 'rff', 'kind' => 'infrastructure', 'name' => 'Réseau ferré de France', 'short' => 'Réseau ferré de France (RFF)', 'wikidata' => 'Q1071978',
        'founded' => ['1997-02-13', 'day'], 'ended' => ['2014-12-31', 'day'], 'seat' => 'Paris',
        'aliases' => ['rff', 'reseau ferre de france'],
        'end_policy' => 'company_end', 'predecessors' => [], 'successors' => ['sncf-reseau'],
        'summary' => [
            'fr' => 'Gestionnaire et propriétaire de l’infrastructure du réseau ferré national de 1997 à 2014. Il ne circule pas de trains : les lignes qui lui sont rattachées le sont comme infrastructure.',
            'en' => 'Manager and owner of the national rail infrastructure from 1997 to 2014. It ran no trains: the lines attached to it are attached as infrastructure.',
            'de' => 'Verwalter und Eigentümer der Infrastruktur des nationalen Eisenbahnnetzes von 1997 bis 2014. Er ließ keine Züge fahren: Die ihm zugeordneten Strecken sind ihm als Infrastruktur zugeordnet.',
        ],
        'events' => [
            ['1997-02-13', 'day', ['fr' => 'Création de Réseau ferré de France.', 'en' => 'Creation of Réseau ferré de France.', 'de' => 'Gründung von Réseau ferré de France.']],
            ['2015-01-01', 'day', ['fr' => 'Remplacé par SNCF Réseau.', 'en' => 'Replaced by SNCF Réseau.', 'de' => 'Ersetzt durch SNCF Réseau.']],
        ],
        'sources' => [['Wikipédia', $wp('Réseau_ferré_de_France')], ['Wikidata', $wd('Q1071978')]],
    ],
    [
        'slug' => 'sncf-reseau', 'kind' => 'infrastructure', 'name' => 'SNCF Réseau', 'short' => 'SNCF Réseau', 'wikidata' => 'Q21605526',
        'founded' => ['2015-01-01', 'day'], 'ended' => null, 'seat' => null,
        'aliases' => ['sncf reseau'],
        'end_policy' => 'open', 'predecessors' => ['rff'], 'successors' => [],
        'summary' => [
            'fr' => 'Gestionnaire de l’infrastructure du réseau ferré national depuis le 1er janvier 2015, successeur de Réseau ferré de France. Les lignes qui lui sont rattachées le sont comme infrastructure.',
            'en' => 'Manager of the national rail infrastructure since 1 January 2015, successor of Réseau ferré de France. The lines attached to it are attached as infrastructure.',
            'de' => 'Verwalter der Infrastruktur des nationalen Eisenbahnnetzes seit dem 1. Januar 2015, Nachfolger von Réseau ferré de France. Die ihm zugeordneten Strecken sind ihm als Infrastruktur zugeordnet.',
        ],
        'events' => [
            ['2015-01-01', 'day', ['fr' => 'Création de SNCF Réseau, qui remplace Réseau ferré de France.', 'en' => 'Creation of SNCF Réseau, which replaces Réseau ferré de France.', 'de' => 'Gründung von SNCF Réseau, das Réseau ferré de France ersetzt.']],
        ],
        'sources' => [['Wikipédia', $wp('SNCF_Réseau')], ['Wikidata', $wd('Q21605526')]],
    ],
    [
        'slug' => 'grand-central', 'kind' => 'grande-compagnie', 'name' => 'Compagnie du chemin de fer Grand-Central de France', 'short' => 'Grand-Central', 'wikidata' => 'Q2990084',
        'founded' => ['1853-07-30', 'day'], 'ended' => ['1857-06-19', 'day'], 'seat' => 'Paris',
        'aliases' => ['grand central'],
        'end_policy' => 'company_end', 'predecessors' => [], 'successors' => ['paris-orleans', 'plm'],
        'summary' => [
            'fr' => 'Société anonyme en activité de 1853 à 1857, au moment où le Second Empire relance les travaux publics et regroupe les compagnies par zones. Son réseau est réparti entre le Paris-Orléans et le Paris-Lyon-Méditerranée.',
            'en' => 'Joint-stock company active from 1853 to 1857, when the Second Empire relaunched public works and grouped the companies by region. Its network was divided between the Paris-Orléans and the Paris-Lyon-Méditerranée.',
            'de' => 'Aktiengesellschaft, von 1853 bis 1857 tätig, als das Second Empire die öffentlichen Arbeiten ankurbelte und die Gesellschaften nach Regionen bündelte. Ihr Netz wurde zwischen der Paris-Orléans und der Paris-Lyon-Méditerranée aufgeteilt.',
        ],
        'events' => [
            ['1853-07-30', 'day', ['fr' => 'Création de la compagnie.', 'en' => 'The company is created.', 'de' => 'Gründung der Gesellschaft.']],
            ['1857-06-19', 'day', ['fr' => 'Fin de la compagnie : son réseau passe à d’autres compagnies.', 'en' => 'End of the company: its network passes to other companies.', 'de' => 'Ende der Gesellschaft: Ihr Netz geht an andere Gesellschaften über.']],
        ],
        'sources' => [['Wikipédia', $wp('Compagnie_du_chemin_de_fer_Grand-Central_de_France')], ['Wikidata', $wd('Q2990084')]],
    ],
    [
        'slug' => 'economiques', 'kind' => 'secondaire', 'name' => 'Société générale des chemins de fer économiques', 'short' => 'Chemins de fer économiques (SE)', 'wikidata' => 'Q610141',
        'founded' => ['1880-02-12', 'day'], 'ended' => ['1966-01-01', 'year'], 'seat' => null,
        'aliases' => ['societe generale des chemins de fer economiques', 'ch de fer economiques', 'chemins de fer economiques'],
        'end_policy' => 'unknown', 'predecessors' => [], 'successors' => [],
        'summary' => [
            'fr' => 'L’une des grandes compagnies exploitant essentiellement des réseaux de chemins de fer secondaires en France, de 1880 à 1966. La fin de chacune de ses concessions n’étant pas donnée par les articles de lignes, les lignes sont listées sans être comptées dans les chiffres par décennie.',
            'en' => 'One of the large companies mostly running secondary railway networks in France, from 1880 to 1966. As the articles on the lines do not give the end of each of its concessions, the lines are listed but not counted in the figures by decade.',
            'de' => 'Eine der großen Gesellschaften, die vor allem Nebenbahnnetze in Frankreich betrieben, von 1880 bis 1966. Da die Streckenartikel das Ende der einzelnen Konzessionen nicht nennen, sind die Strecken aufgeführt, aber nicht in den Zahlen je Jahrzehnt gezählt.',
        ],
        'events' => [
            ['1880-02-12', 'day', ['fr' => 'Création de la société.', 'en' => 'The company is created.', 'de' => 'Gründung der Gesellschaft.']],
            ['1966-01-01', 'year', ['fr' => 'Disparition de la société.', 'en' => 'The company ceases to exist.', 'de' => 'Auflösung der Gesellschaft.']],
        ],
        'sources' => [['Wikipédia', $wp('Société_générale_des_chemins_de_fer_économiques_(France)')], ['Wikidata', $wd('Q610141')]],
    ],
    [
        'slug' => 'cfd', 'kind' => 'secondaire', 'name' => 'Compagnie de chemins de fer départementaux', 'short' => 'Chemins de fer départementaux (CFD)', 'wikidata' => 'Q2989867',
        'founded' => ['1881-01-01', 'year'], 'ended' => null, 'seat' => null,
        'aliases' => ['cfd'],
        'end_policy' => 'unknown', 'predecessors' => [], 'successors' => [],
        'summary' => [
            'fr' => 'Entreprise historiquement spécialisée dans la construction de matériel ferroviaire, qui a aussi construit et exploité des réseaux de lignes secondaires. Elle existe encore comme société de portefeuille industrielle. La fin de chacune de ses exploitations n’étant pas donnée par les articles de lignes, les lignes sont listées sans être comptées dans les chiffres par décennie.',
            'en' => 'Company historically specialised in building railway equipment, which also built and ran networks of secondary lines. It still exists as an industrial holding company. As the articles on the lines do not give the end of each operation, the lines are listed but not counted in the figures by decade.',
            'de' => 'Unternehmen, das historisch auf den Bau von Eisenbahnmaterial spezialisiert war und auch Netze von Nebenstrecken gebaut und betrieben hat. Es besteht noch als industrielle Holdinggesellschaft. Da die Streckenartikel das Ende der einzelnen Betriebe nicht nennen, sind die Strecken aufgeführt, aber nicht in den Zahlen je Jahrzehnt gezählt.',
        ],
        'events' => [
            ['1881-01-01', 'year', ['fr' => 'Création de la compagnie.', 'en' => 'The company is created.', 'de' => 'Gründung der Gesellschaft.']],
        ],
        'sources' => [['Wikipédia', $wp('Compagnie_de_chemins_de_fer_départementaux')], ['Wikidata', $wd('Q2989867')]],
    ],
];
