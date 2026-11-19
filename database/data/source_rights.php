<?php

/**
 * Registre des DROITS de chaque source externe (chantier « sources-libres », 2026-10-20).
 * Lu par App\Support\SourceRights et vff:source-rights (lecture seule : rien n'est filtré).
 *
 * providers : un fournisseur = une source et ses conditions de réutilisation.
 *   - statut : « sur » (licence ouverte écrite, prouvée par un lien), « incertain » (accord oral,
 *     pas de licence, conditions floues ou contradictoires), « interdit » ;
 *   - accord : null (licence publique), « oral », « ecrit » (accord particulier des auteurs) ;
 *   - preuve : pages lues le jour indiqué (lu_le) qui établissent la licence ; citation : phrase exacte ;
 *   - domaines : domaines des liens (source_url) qui désignent ce fournisseur (sources composites).
 * sources : par table, valeur de la colonne de provenance → fournisseur.
 * composites : par table, valeur dont le fournisseur dépend du lien cité (corrections manuelles,
 *   patrimoine) → fournisseur par défaut quand le lien ne désigne aucun fournisseur déclaré.
 *
 * Changer un statut (ex. accord écrit d'un auteur reçu) : modifier ici, avec la preuve.
 * Détail et mesures : storage/app/reports/agents/sources-libres.md.
 * Pas de tiret long dans les textes (consigne éditoriale).
 */
return [
    'providers' => [
        'osm' => [
            'nom' => 'OpenStreetMap',
            'statut' => 'sur',
            'licence' => 'ODbL 1.0 (Open Database License)',
            'accord' => null,
            'preuve' => [
                'https://www.openstreetmap.org/copyright',
                'https://opendatacommons.org/licenses/odbl/1-0/',
                'https://osmfoundation.org/wiki/Licence/Community_Guidelines/Collective_Database_Guideline_Guideline',
            ],
            'citation' => 'licensed under the Open Data Commons Open Database License (ODbL).',
            'obligations' => 'Attribution « © contributeurs OpenStreetMap » avec lien vers openstreetmap.org/copyright et mention de l\'ODbL. '
                .'Base DÉRIVÉE publiquement utilisée (tracés OSM mêlés à d\'autres tracés de même nature, recalages sur OSM) : '
                .'publiée sous ODbL (art. 4.4) et offerte au public (art. 4.6). Ce qui y entre doit pouvoir être publié sous ODbL.',
            'domaines' => ['openstreetmap.org'],
            'lu_le' => '2026-10-20',
        ],
        'ohm' => [
            'nom' => 'OpenHistoricalMap',
            'statut' => 'sur',
            'licence' => 'CC0 1.0 (domaine public), sauf licence contraire indiquée par objet (tag license=*)',
            'accord' => null,
            'preuve' => ['https://www.openhistoricalmap.org/copyright'],
            'citation' => 'OpenHistoricalMap data is dedicated to the public domain under a Creative Commons CC0 dedication.',
            'obligations' => 'Aucune obligation légale (crédit apprécié). Vérifier le tag license=* des objets repris.',
            'remarques' => '1 021 km de tracés OHM sont à moins de 5 m de tracés archeoferroviaire : reprise possible d\'archeo par des contributeurs, à vérifier avant de les compter comme source indépendante.',
            'domaines' => ['openhistoricalmap.org'],
            'lu_le' => '2026-10-20',
        ],
        'ign' => [
            'nom' => 'IGN (BD TOPO, BD ORTHO historique, RGE ALTI)',
            'statut' => 'sur',
            'licence' => 'Licence Ouverte 2.0 (Etalab), données publiques de l\'IGN libres depuis le 1er janvier 2021',
            'accord' => null,
            'preuve' => [
                'https://www.data.gouv.fr/datasets/bd-topo-r',
                'https://www.data.gouv.fr/datasets/bd-ortho-r-historique',
                'https://macarte.ign.fr/cgu',
                'https://www.data.gouv.fr/datasets/rge-alti-r',
                'https://www.data.gouv.fr/dataservices/api-geoplateforme-calcul-altimetrique',
                'https://cartes.gouv.fr/aide/fr/guides-utilisateur/utiliser-les-services-de-la-geoplateforme/calcul-altimetrique/',
            ],
            'citation' => 'Les Contenus IGN sont soumis à la licence libre Etalab 2.0. [...] sous réserve de mentionner la paternité de l\'IGN : sa source (au moins le nom de l\'IGN) et la date de dernière mise à jour des Contenu IGN réutilisés.',
            'obligations' => 'Mention « IGN - BD TOPO » (ou BD ORTHO historique, RGE ALTI) et date de mise à jour des données. Compatible ODbL en entrée. '
                .'Profils en long (vff:compute-line-profiles) : RGE ALTI lu le 2026-11-19 sur data.gouv.fr (licence « lov2 », producteur IGN) ; '
                .'service de calcul altimétrique limité à 5 requêtes par seconde par adresse IP (data.gouv.fr et page d\'aide de cartes.gouv.fr), VFF en fait une par seconde au plus.',
            'remarques' => 'SCAN 25, SCAN 100 et SCAN OACI ne sont pas libres. SCAN 50 historique et carte de l\'état-major : licence non établie par une page de l\'IGN (seulement affichés en fond de carte, aucune donnée extraite en base).',
            'domaines' => ['ign.fr', 'geopf.fr', 'cartes.gouv.fr'],
            'lu_le' => '2026-10-20',
        ],
        'sncf' => [
            'nom' => 'SNCF Open Data (SNCF Réseau, Gares & Connexions, GTFS voyageurs)',
            'statut' => 'sur',
            'licence' => 'ODbL 1.0 (chaque jeu utilisé : champ metas.default.license de l\'API)',
            'accord' => null,
            'preuve' => [
                'https://ressources.data.sncf.com/api/explore/v2.1/catalog/datasets/lignes-par-statut',
                'https://ressources.data.sncf.com/api/explore/v2.1/catalog/datasets/formes-des-lignes-du-rfn',
                'https://ressources.data.sncf.com/api/explore/v2.1/catalog/datasets/liste-des-gares',
                'https://ressources.data.sncf.com/api/explore/v2.1/catalog/datasets/liste-des-passages-a-niveau',
                'https://ressources.data.sncf.com/api/explore/v2.1/catalog/datasets/mode-de-cantonnement-des-lignes',
                'https://ressources.data.sncf.com/api/explore/v2.1/catalog/datasets/lignes-equipees-de-kvb',
                'https://transport.data.gouv.fr/datasets/horaires-sncf',
                'https://transport.data.gouv.fr/datasets/horaires-des-lignes-transilien-1',
            ],
            'citation' => '"license": "Open Database License (ODbL)" (métadonnées des 12 jeux utilisés, lues le 2026-10-20) ; '
                .'« licence » : « odc-odbl » pour horaires-sncf et horaires-des-lignes-transilien-1 (API transport.data.gouv.fr, lue le 2026-11-18) ; '
                .'"license": "Open Database License (ODbL)", "publisher": "SNCF Réseau" pour liste-des-passages-a-niveau, mode-de-cantonnement-des-lignes et lignes-equipees-de-kvb (lus le 2026-11-20)',
            'obligations' => 'Attribution (SNCF Réseau / SNCF Gares & Connexions / SNCF Voyageurs) ; partage à l\'identique des bases dérivées publiquement utilisées.',
            'domaines' => ['sncf.com', 'transport.data.gouv.fr'],
            'lu_le' => '2026-10-20',
        ],
        'wikipedia' => [
            'nom' => 'Wikipédia (FR)',
            'statut' => 'sur',
            'licence' => 'CC BY-SA 4.0 pour les textes ; les faits (dates) ne sont pas protégés',
            'accord' => null,
            'preuve' => [
                'https://fr.wikipedia.org/wiki/Wikip%C3%A9dia:Citation_et_r%C3%A9utilisation_du_contenu_de_Wikip%C3%A9dia',
                'https://creativecommons.org/licenses/by-sa/4.0/legalcode.en',
            ],
            'citation' => 'Le contenu textuel de Wikipédia est sous CC BY-SA 4.0, licence libre, laquelle permet une réutilisation gratuite de celui-ci.',
            'obligations' => 'Courte citation (source_quote) : auteur et source (permalien). Introductions reprises sur les fiches : CC BY-SA 4.0, attribution, lien vers la licence, texte repris sous la même licence. '
                .'Section 4 de la CC BY-SA 4.0 : reprendre une partie SUBSTANTIELLE d\'une base Wikipédia rendrait la base VFF (pas ses contenus) « matériel adapté ».',
            'domaines' => ['wikipedia.org'],
            'lu_le' => '2026-10-20',
        ],
        'wikidata' => [
            'nom' => 'Wikidata',
            'statut' => 'sur',
            'licence' => 'CC0 1.0',
            'accord' => null,
            'preuve' => ['https://www.wikidata.org/wiki/Wikidata:Licensing'],
            'citation' => 'All structured data (i.e. the main, Property, Lexeme, and EntitySchema namespaces) is released into the public domain under Creative Commons Zero.',
            'obligations' => 'Aucune obligation légale. API : User-Agent identifiant (politique de la Wikimedia Foundation).',
            'domaines' => ['wikidata.org'],
            'lu_le' => '2026-10-20',
        ],
        'commons' => [
            'nom' => 'Wikimedia Commons',
            'statut' => 'sur',
            'licence' => 'Licence propre à chaque fichier (CC BY, CC BY-SA, GFDL, domaine public…), enregistrée dans media.license',
            'accord' => null,
            'preuve' => ['https://commons.wikimedia.org/wiki/Commons:Reusing_content_outside_Wikimedia'],
            'citation' => 'Each media file has its licensing specified on its file description page.',
            'obligations' => 'Attribution et licence de chaque fichier affichées (media.attribution, media.license) ; partage à l\'identique pour les œuvres dérivées des fichiers BY-SA.',
            'domaines' => ['commons.wikimedia.org', 'upload.wikimedia.org'],
            'lu_le' => '2026-10-20',
        ],
        'communes' => [
            'nom' => 'API Découpage administratif (geo.api.gouv.fr, jeu « Contours administratifs » d\'Etalab)',
            'statut' => 'sur',
            'licence' => 'ODbL (jeu « Contours administratifs » utilisé par l\'API, data.gouv.fr) ; communes de métropole issues d\'ADMIN EXPRESS (IGN, Licence Ouverte 2.0)',
            'accord' => null,
            'preuve' => [
                'https://www.data.gouv.fr/datasets/contours-administratifs',
                'https://www.data.gouv.fr/datasets/admin-express-admin-express-cog-admin-express-cog-carto-admin-express-cog-carto-pe-admin-express-cog-carto-plus-pe',
            ],
            'citation' => 'Ce jeu de données est utilisé par l\'API Découpage Administratif. (licence du jeu : odc-odbl)',
            'obligations' => 'Attribution (Etalab, IGN ADMIN EXPRESS) ; partage à l\'identique des bases dérivées.',
            'domaines' => ['geo.api.gouv.fr'],
            'lu_le' => '2026-10-20',
        ],
        'jorf' => [
            'nom' => 'Journal officiel (DILA, données JORF en open data)',
            'statut' => 'sur',
            'licence' => 'Licence Ouverte (Etalab) ; actes officiels',
            'accord' => null,
            'preuve' => [
                'https://www.data.gouv.fr/datasets/jorf-les-donnees-de-l-edition-lois-et-decrets-du-journal-officiel',
                'https://www.legifrance.gouv.fr/contenu/pied-de-page/open-data-et-api',
                'https://echanges.dila.gouv.fr/OPENDATA/JORF/',
            ],
            'citation' => 'Le fonds documentaire des documents publiés dans l\'édition " Lois et décrets " du Journal officiel depuis 1990 est constitué : des lois, ordonnances, décrets, arrêtés et circulaires (licence du jeu : fr-lo)',
            'obligations' => 'Mention de la source (DILA, Journal officiel) et de la date de mise à jour ; lien vers le texte sur Légifrance.',
            'domaines' => ['legifrance.gouv.fr', 'dila.gouv.fr'],
            'lu_le' => '2026-10-20',
        ],
        'vff' => [
            'nom' => 'VFF (corrections sourcées, estimations, contributions validées, formes déclarées)',
            'statut' => 'sur',
            'licence' => 'Travail propre de VFF ; contributions sous CC BY-SA 4.0 (mentions légales)',
            'accord' => null,
            'preuve' => ['https://vff-project.org/legal'],
            'citation' => 'Les médias contribués (photos, documents) restent la propriété de leurs auteurs ; ceux-ci en cèdent l\'usage à VFF sous licence Creative Commons CC BY-SA 4.0 sauf mention contraire.',
            'obligations' => 'Chaque correction cite sa source : le fournisseur réel est celui du lien cité (cf. composites).',
            'domaines' => ['vff-project.org'],
            'lu_le' => '2026-10-20',
        ],
        'archeoferroviaire' => [
            'nom' => 'archeoferroviaire.free.fr (J. Randé, F. Librini, K. Moreau ; tracés aussi de F. Hespert et Y. Zwartjes)',
            'statut' => 'incertain',
            'licence' => 'Tous droits réservés (mentions légales) ; usage par VFF non commercial, avec crédit et lien',
            'accord' => null,
            'preuve' => [
                'http://archeoferroviaire.free.fr/v31/spip.php?page=rubrique&id_rubrique=3',
                'https://macarte.ign.fr/cgu',
                'https://www.data.gouv.fr/datasets/rge-alti-r',
                'https://www.data.gouv.fr/dataservices/api-geoplateforme-calcul-altimetrique',
                'https://cartes.gouv.fr/aide/fr/guides-utilisateur/utiliser-les-services-de-la-geoplateforme/calcul-altimetrique/',
            ],
            'citation' => 'Aucune reproduction, même partielle, en dehors de celles prévues à l\'article L 122-5 du code de la propriété intellectuelle, ne peut être faite de ce site sans l\'autorisation expresse de l\'auteur. [...] Toute diffusion ou utilisation à des fins commerciales ou publicitaires est exclue.',
            'obligations' => 'Crédit nominatif et lien vers l\'article d\'origine. Usage non commercial uniquement (mentions légales du site) : '
                .'données hors ODbL, signalées par source = archeoferroviaire dans l\'API et les exports.',
            'remarques' => 'Carte « Ma carte » de l\'IGN : le contenu appartient à son auteur (CGU Ma carte, art. 8.2), aucune licence ouverte.',
            'domaines' => ['archeoferroviaire.free.fr'],
            'lu_le' => '2026-10-20',
        ],
        'lignes-oubliees' => [
            'nom' => 'lignes-oubliees.com',
            'statut' => 'incertain',
            'licence' => 'Autorisation écrite de reproduire les INFORMATIONS avec mention de l\'origine (page À propos) ; pas de licence ; PDF sans mention de droits',
            'accord' => 'ecrit',
            'preuve' => [
                'https://www.lignes-oubliees.com/index.php?page=apropos',
                'https://www.lignes-oubliees.com/fichiers/declassements.pdf',
            ],
            'citation' => 'Elles peuvent être librement reproduites à condition d\'indiquer leur origine (ce site et éventuellement les sources correspondantes).',
            'obligations' => 'Lien vers la fiche d\'origine avec chaque information. Photos, profils et documents exclus. '
                .'Incertain : l\'extraction systématique de la base et sa republication sous une licence ouverte ne sont pas prévues par la phrase ; un courriel de confirmation suffirait.',
            'domaines' => ['lignes-oubliees.com'],
            'lu_le' => '2026-10-20',
        ],
        'argentanwebferro' => [
            'nom' => 'Argentan Web Ferroviaire (argentanwebferro.fr, Thierry Danglos) : articles d\'histoire des lignes normandes et galerie de photos',
            'statut' => 'incertain',
            'licence' => 'Articles : tous droits réservés (mentions légales), seuls des FAITS en sont tirés ; galerie Piwigo : CC BY-SA 4.0 affichée en pied de page, valable pour les seules œuvres de l\'auteur',
            'accord' => null,
            'preuve' => [
                'https://argentanwebferro.fr/mentions-legales/',
                'https://argentanwebferro.fr/AWF/about.php',
            ],
            'citation' => 'Articles : « Toute reproduction, distribution, modification, adaptation, retransmission ou publication, même partielle, de ces différents éléments est strictement interdite sans l\'accord exprès par écrit de Danglos Thierry. » '
                .'Galerie : « Ce(tte) œuvre est mise à disposition selon les termes de la Licence Creative Commons Attribution - Partage dans les Mêmes Conditions 4.0 International. »',
            'obligations' => 'Articles : faits reformulés (dates, sections, arrêts, exploitants), lien vers l\'article avec chaque fait, au plus une phrase citée ; aucun texte, tableau ni photo d\'article repris. '
                .'Galerie : seulement les photos de l\'auteur, avec son nom, la licence CC BY-SA 4.0, son lien et le lien de la page de la photo ; partage à l\'identique des œuvres dérivées. '
                .'Cartes postales anciennes, archives et photos créditées à d\'autres photographes exclues.',
            'remarques' => 'Décision de l\'utilisateur du 2026-11-19 : lecture de tout le site pour en tirer les faits. Risque résiduel : extraction systématique (droit des bases de données) '
                .'et liens profonds (les mentions légales demandent d\'écrire avant d\'en poser) ; courriel de confirmation à l\'auteur proposé (storage/app/reports/agents/source-argentan.md). '
                .'Faits en tables de source (argentanwebferro_*), non appliqués au moteur.',
            'domaines' => ['argentanwebferro.fr'],
            'lu_le' => '2026-11-19',
        ],
        'region-sud' => [
            'nom' => 'Région Provence-Alpes-Côte d\'Azur (horaires ZOU ! : trains régionaux, Chemins de fer de Provence)',
            'statut' => 'sur',
            'licence' => 'Licence Ouverte 2.0 (Etalab)',
            'accord' => null,
            'preuve' => [
                'https://transport.data.gouv.fr/datasets/trains-zou-en-provence-alpes-cote-dazur',
                'https://transport.data.gouv.fr/datasets/lignes-des-reseaux-transport-zou-provence-alpes-cote-d-azur-express-3-3',
            ],
            'citation' => '« licence » : « lov2 » (API transport.data.gouv.fr des deux jeux, lue le 2026-11-18)',
            'obligations' => 'Mention de la source (Région Sud, ZOU !) et de la date de mise à jour. Compatible ODbL en entrée.',
            'domaines' => ['maregionsud.fr', 'datasud.fr'],
            'lu_le' => '2026-11-18',
        ],
        'transdev-rail-sud' => [
            'nom' => 'Transdev Rail Sud Intermétropole (trains Marseille - Toulon - Nice)',
            'statut' => 'sur',
            'licence' => 'Licence Ouverte 2.0 (Etalab)',
            'accord' => null,
            'preuve' => ['https://transport.data.gouv.fr/datasets/zou'],
            'citation' => '« licence » : « lov2 » (API transport.data.gouv.fr, lue le 2026-11-18)',
            'obligations' => 'Mention de la source (Transdev Rail Sud Intermétropole) et de la date de mise à jour.',
            'domaines' => [],
            'lu_le' => '2026-11-18',
        ],
        'collectivite-corse' => [
            'nom' => 'Collectivité de Corse (horaires des Chemins de fer de la Corse)',
            'statut' => 'sur',
            'licence' => 'Licence Ouverte 2.0 (Etalab)',
            'accord' => null,
            'preuve' => ['https://transport.data.gouv.fr/datasets/gtfs-transport-horaires-chemins-de-fer-corse-1'],
            'citation' => '« licence » : « lov2 » (API transport.data.gouv.fr, lue le 2026-11-18)',
            'obligations' => 'Mention de la source (Collectivité de Corse) et de la date de mise à jour.',
            'remarques' => 'Flux périmé le 2026-11-18 : horaires du 03/03/2026 au 09/03/2026 seulement.',
            'domaines' => [],
            'lu_le' => '2026-11-18',
        ],
        'oura' => [
            'nom' => 'Oùra (agrégat des réseaux d\'Auvergne-Rhône-Alpes : Panoramique des Dômes)',
            'statut' => 'sur',
            'licence' => 'ODbL',
            'accord' => null,
            'preuve' => ['https://transport.data.gouv.fr/datasets/agregat-oura'],
            'citation' => '« licence » : « odc-odbl » (API transport.data.gouv.fr, lue le 2026-11-18)',
            'obligations' => 'Attribution (Oùra, Région Auvergne-Rhône-Alpes) ; partage à l\'identique des bases dérivées.',
            'domaines' => ['oura3.cityway.fr'],
            'lu_le' => '2026-11-18',
        ],
        'eurostar' => [
            'nom' => 'Eurostar International',
            'statut' => 'sur',
            'licence' => 'Licence Ouverte 2.0 (Etalab)',
            'accord' => null,
            'preuve' => ['https://transport.data.gouv.fr/datasets/eurostar-gtfs-plan-de-transport-et-temps-reel'],
            'citation' => '« licence » : « lov2 » (API transport.data.gouv.fr, lue le 2026-11-18)',
            'obligations' => 'Mention de la source (Eurostar) et de la date de mise à jour.',
            'domaines' => ['eurostar.com'],
            'lu_le' => '2026-11-18',
        ],
        'renfe' => [
            'nom' => 'Renfe Viajeros (trains France - Espagne)',
            'statut' => 'sur',
            'licence' => 'Licence Ouverte 2.0 (Etalab)',
            'accord' => null,
            'preuve' => ['https://transport.data.gouv.fr/datasets/horaires-ave-espagne-france'],
            'citation' => '« licence » : « lov2 » (API transport.data.gouv.fr, lue le 2026-11-18)',
            'obligations' => 'Mention de la source (Renfe) et de la date de mise à jour.',
            'domaines' => ['renfe.com'],
            'lu_le' => '2026-11-18',
        ],
        'trenitalia' => [
            'nom' => 'Trenitalia France',
            'statut' => 'sur',
            'licence' => 'Licence Ouverte 2.0 (Etalab)',
            'accord' => null,
            'preuve' => ['https://transport.data.gouv.fr/datasets/horaires-des-trains-trenitalia-france'],
            'citation' => '« licence » : « lov2 » (API transport.data.gouv.fr, lue le 2026-11-18)',
            'obligations' => 'Mention de la source (Trenitalia France) et de la date de mise à jour.',
            'domaines' => [],
            'lu_le' => '2026-11-18',
        ],
        'idfm' => [
            'nom' => 'Île-de-France Mobilités (GTFS des réseaux urbains et interurbains)',
            'statut' => 'incertain',
            'licence' => 'Licence Mobilités (fondée sur l\'ODbL, avec des obligations propres)',
            'accord' => null,
            'preuve' => [
                'https://transport.data.gouv.fr/datasets/reseau-urbain-et-interurbain-dile-de-france-mobilites',
                'https://wiki.lafabriquedesmobilites.fr/wiki/Licence_Mobilit%C3%A9s',
            ],
            'citation' => '« licence » : « mobility-licence » (API transport.data.gouv.fr, lue le 2026-11-18) ; licence « fondée sur la licence ODbL »',
            'obligations' => 'Partage à l\'identique des bases dérivées de même nature ; identification du réutilisateur ; '
                .'compatibilité avec la stratégie de mobilité de l\'autorité organisatrice. Pas l\'ODbL : à faire valider avant toute publication.',
            'remarques' => 'Seul flux des tram-trains T4, T11, T12, T13 et T14 : lu seulement sur demande (vff:import-station-service --feed=idfm-tram).',
            'domaines' => ['iledefrance-mobilites.fr'],
            'lu_le' => '2026-11-18',
        ],
        'tiers-cites' => [
            'nom' => 'Sites tiers cités ponctuellement (associations, presse, institutions)',
            'statut' => 'incertain',
            'licence' => 'Pas de licence : faits isolés repris avec lien et courte citation',
            'accord' => null,
            'preuve' => ['https://commons.wikimedia.org/wiki/Commons:Copyright_rules_by_territory/France/fr'],
            'citation' => '(faits isolés : une date par site, lien vers la page citée)',
            'obligations' => 'Lien vers la page citée ; citation courte. Risque faible (faits), à remplacer par une source sûre quand elle existe.',
            'domaines' => [
                'parcdescombes.com', 'marc-andre-dubout.org', 'mejanes-camargue.fr', 'assemblee-nationale.fr',
                'cftr.evolutive.org', 'rail.lu', 'cftanse.fr', 'amitram.fr', 'minieresbunn.lu',
            ],
            'lu_le' => '2026-10-20',
        ],
        'bea-tt' => [
            'nom' => 'BEA-TT (Bureau d\'enquêtes sur les accidents de transport terrestre)',
            'statut' => 'sur',
            'licence' => 'Licence Ouverte 2.0 (Etalab), « sauf mention contraire » ; rapports et bilan général des enquêtes',
            'accord' => null,
            'preuve' => [
                'https://www.bea-tt.developpement-durable.gouv.fr/historique-des-enquetes-a1187.html',
                'https://github.com/etalab/licence-ouverte/blob/master/LO.md',
            ],
            'citation' => 'Sauf mention contraire, tous les contenus de ce site sont sous licence etalab-2.0 (pied de chaque page du site, lu le 2026-11-19).',
            'obligations' => 'Mention de la paternité (BEA-TT) et de la date de mise à jour du document (bilan général des enquêtes au 09/11/2026). Compatible ODbL en entrée.',
            'remarques' => 'Seuls le bilan général des enquêtes (date, intitulé, mode, nombre de tués, lien du rapport) et les liens des rapports sont repris (vff:import-accidents) ; aucun texte de rapport. La page « Mentions légales » du site renvoyait une erreur le 2026-11-19 : la licence est celle du pied de page.',
            'domaines' => ['bea-tt.developpement-durable.gouv.fr'],
            'lu_le' => '2026-11-19',
        ],
    ],

    'sources' => [
        // Sources des fiches d'accidents (vff:import-accidents) : accident_sources.provider.
        'accident_sources' => [
            'wikidata' => 'wikidata',
            'wikipedia' => 'wikipedia',
            'bea-tt' => 'bea-tt',
        ],
        'line_events' => [
            'wikipedia' => 'wikipedia',
            'wikipedia-reseaux' => 'wikipedia',
            // Dates estimées d'après les dates des compagnies lues dans Wikipédia.
            'estimation-reseau' => 'wikipedia',
            'wikidata' => 'wikidata',
            'ohm' => 'ohm',
            'osm' => 'osm',
            'sncf' => 'sncf',
            'jorf' => 'jorf',
            'contrib' => 'vff',
            'lignes-oubliees' => 'lignes-oubliees',
            // Années tirées du NOM des tracés archeo (« 14-18 », « 30-40 »…).
            'archeoferroviaire' => 'archeoferroviaire',
        ],
        'source_traces' => [
            'osm' => 'osm',
            'ohm' => 'ohm',
            'bdtopo' => 'ign',
            // Extraction par VFF des voies sur la BD ORTHO historique (Licence Ouverte), corridor archeo.
            'ign-historique' => 'ign',
            'sncf' => 'sncf',
            // Formes déclarées = unions de voies OSM / BD TOPO désignées (database/data/lignes_hors_rfn.php).
            'declared' => 'osm',
            'contrib' => 'vff',
            'archeoferroviaire' => 'archeoferroviaire',
        ],
        'line_reference_parts' => [
            'osm' => 'osm', 'ohm' => 'ohm', 'bdtopo' => 'ign', 'ign-historique' => 'ign', 'sncf' => 'sncf',
            'declared' => 'osm', 'contrib' => 'vff', 'archeoferroviaire' => 'archeoferroviaire',
        ],
        'line_segments' => [
            'osm' => 'osm', 'ohm' => 'ohm', 'bdtopo' => 'ign', 'ign-historique' => 'ign', 'sncf' => 'sncf',
            'declared' => 'osm', 'contrib' => 'vff', 'archeoferroviaire' => 'archeoferroviaire',
        ],
        'station_sources' => [
            'sncf' => 'sncf', 'sncf_voyageurs' => 'sncf', 'osm' => 'osm', 'ohm' => 'ohm', 'wikidata' => 'wikidata',
            'communes' => 'communes', 'wikipedia_schema' => 'wikipedia', 'lignes-oubliees' => 'lignes-oubliees',
        ],
        'source_stations' => [
            'lignes-oubliees' => 'lignes-oubliees', 'wikipedia' => 'wikipedia',
        ],
        'station_events' => [
            'wikidata' => 'wikidata', 'wikidata:qualifiers' => 'wikidata', 'wikipedia' => 'wikipedia', 'ohm' => 'ohm',
            'osm' => 'osm', 'osm:note' => 'osm',
            // Déduits par VFF des événements des lignes ou de l'état des tracés.
            'inferred:line_event' => 'vff', 'inferred:trace' => 'vff',
        ],
        'line_sources' => [
            'sncf-classification' => 'sncf', 'gtfs-voyageurs' => 'sncf', 'wikipedia-intro' => 'wikipedia', 'wikidata' => 'wikidata',
        ],
        // Flux GTFS de vff:import-station-service (evidence[].feed), cf. database/data/station_service_feeds.php.
        'station_services' => [
            'sncf' => 'sncf', 'sncf-ete' => 'sncf', 'sncf-printemps' => 'sncf', 'transilien' => 'sncf',
            'zou-trains' => 'region-sud', 'zou-express' => 'region-sud', 'transdev-rail-sud' => 'transdev-rail-sud',
            'cfc' => 'collectivite-corse', 'panoramique-domes' => 'oura', 'eurostar' => 'eurostar', 'renfe' => 'renfe',
            'trenitalia' => 'trenitalia', 'idfm-tram' => 'idfm',
        ],
        'media' => [
            'commons' => 'commons',
            'upload' => 'vff',
            // Photos de la galerie d'Argentan Web Ferroviaire (œuvres de l'auteur, CC BY-SA 4.0), import préparé, non lancé.
            'argentanwebferro' => 'argentanwebferro',
        ],
        // Passages à niveau (vff:import-sncf-level-crossings) et signalisation par PK (vff:import-sncf-signalling) :
        // tables sans colonne de provenance, une seule source (SNCF Réseau, ODbL), citée par l'API.
        'sncf_level_crossings' => ['sncf' => 'sncf'],
        'line_signalling' => ['sncf' => 'sncf'],
        // Profils en long (vff:compute-line-profiles) : altitude de la voie BD TOPO, à défaut RGE ALTI (line_profiles.source).
        'line_profiles' => ['bdtopo-rge-alti' => 'ign'],
        // Matériel roulant (vff:import-materials) : fiches Wikidata, champ par champ (provenance),
        // image Commons (licence propre à chaque fichier), liens matériel ↔ ligne attestés.
        'materials' => ['wikidata' => 'wikidata'],
        'materials.fields' => ['wikidata' => 'wikidata', 'commons' => 'commons', 'wikipedia' => 'wikipedia'],
        'materials.image' => ['commons' => 'commons'],
        'line_materials' => ['wikidata' => 'wikidata', 'wikipedia' => 'wikipedia'],
        'lines.name_source' => [
            'archeoferroviaire' => 'archeoferroviaire',
            'lignes-oubliees' => 'lignes-oubliees',
            'wikidata' => 'wikidata',
            // Noms historiques curés d'après Wikipédia (database/data/lignes_noms_historiques.php).
            'curated' => 'wikipedia',
            // Noms construits par VFF (communes et gares extrémités).
            'derived' => 'vff', 'derived_nearest' => 'vff', 'derived_stations' => 'vff',
        ],
    ],

    'composites' => [
        'line_events' => [
            // Corrections manuelles sourcées (database/data/line_event_corrections*.php) : le lien cité décide.
            'manual' => 'vff',
            // Exploitations associatives (BD TOPO, OSM, Wikipédia, Wikidata, lignes-oubliees) : le lien cité décide.
            'heritage' => 'vff',
        ],
        'station_events' => [
            // Corrections sourcées des dates de gares : le lien cité décide.
            'correction' => 'vff',
        ],
    ],
];
