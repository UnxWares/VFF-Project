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
            'remarques' => 'Voies vertes rattachées aux lignes (line_greenways, vff:link-greenways) : identifiant du way, longueur et tracé de la partie qui suit l\'ancienne emprise, tirés d\'OSM. Base dérivée publiée sous ODbL ; la mention « © contributeurs OpenStreetMap » est reprise sur la carte, dans la fiche de la ligne et dans l\'API.',
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
                'https://ressources.data.sncf.com/api/explore/v2.1/catalog/datasets/frequentation-gares',
                'https://ressources.data.sncf.com/api/explore/v2.1/catalog/datasets/frequentation-gares-archive',
            ],
            'citation' => '"license": "Open Database License (ODbL)" (métadonnées des 12 jeux utilisés, lues le 2026-10-20) ; '
                .'« licence » : « odc-odbl » pour horaires-sncf et horaires-des-lignes-transilien-1 (API transport.data.gouv.fr, lue le 2026-11-18) ; '
                .'"license": "Open Database License (ODbL)", "publisher": "SNCF Réseau" pour liste-des-passages-a-niveau, mode-de-cantonnement-des-lignes et lignes-equipees-de-kvb (lus le 2026-11-20) ; '
                .'"license": "Open Data Commons Open Database License (ODbL)", "publisher": "Gares & Connexions" pour frequentation-gares (2025) et frequentation-gares-archive (2015-2024), lus le 2026-11-21 (database/data/frequentation_gares_405000.php ; les deux jeux ne sont pas comparables entre eux, avertissement de SNCF)',
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
            'remarques' => 'Fiches d\'accidents (vff:import-accidents) : seuls des FAITS sont repris des articles et des pages « Liste d\'accidents ferroviaires en France » (date, lieu cité, lignes citées, nombre de morts et de blessés, nature) ; aucun texte n\'est copié ni conservé, le résumé est rédigé par VFF. Chaque fiche cite le permalien de la révision lue (accident_sources) ; toute citation d\'un texte de Wikipédia reste sous CC BY-SA 4.0 avec attribution. Page des conditions de réutilisation relue le 2026-11-19. Matériel roulant (vff:import-materials) : seuls des FAITS sont repris de l\'infobox « Infobox Matériel ferroviaire » (catégorie annoncée, effectif en service et sa date), avec l\'article et la révision lue en provenance ; les phrases qui relient une série à une ligne sont citées avec leur permalien (line_materials.evidence). Historique de la signalisation (vff:import-signalling-history) : seuls des FAITS sont repris des articles de lignes (système, date et sa précision, portion si l\'article la donne), avec le titre de l\'article et le permalien de la révision lue ; une courte phrase (200 caractères au plus) est gardée pour relire le fait, montrée dans l\'API avec l\'attribution CC BY-SA 4.0, jamais sur la fiche. Périodes de circulation du matériel (vff:import-material-periods) : seuls des FAITS sont repris des phrases qui relient une série à une ligne (début, fin et leur précision, portion nommée par le titre de section), avec le titre de l\'article et le permalien de la révision lue ; la phrase est gardée en base pour relecture, jamais montrée ni servie par l\'API ou le serveur MCP. Fins du service voyageurs des gares (vff:import-station-passenger-ends) : seule la DATE est reprise du champ « fermeture » de l\'infobox de l\'article de la gare, avec le permalien de la révision lue (station_events.source_url) et le texte du champ en courte citation (300 caractères au plus).',
            'domaines' => ['wikipedia.org'],
            'lu_le' => '2026-11-19',
        ],
        'wikipedia-en' => [
            'nom' => 'Wikipédia (EN)',
            'statut' => 'sur',
            'licence' => 'CC BY-SA 4.0 pour les textes ; les faits (dates) ne sont pas protégés',
            'accord' => null,
            'preuve' => [
                'https://en.wikipedia.org/wiki/Wikipedia:Reusing_Wikipedia_content',
                'https://creativecommons.org/licenses/by-sa/4.0/legalcode.en',
            ],
            'citation' => 'While in principle Wikipedia\'s text is subject to the Creative Commons Attribution-ShareAlike License and, generally, the GNU Free Documentation License and can be used free of charge for any purpose so long as licensing terms are met',
            'obligations' => 'Attribution par le lien vers la révision lue (permalien gardé dans accident_sources) ; tout texte repris resterait sous CC BY-SA 4.0.',
            'remarques' => 'Fiches d\'accidents (vff:import-accidents --only=wikipedia-editions) : seuls des FAITS sont lus dans « List of rail accidents (années) » et « List of rail accidents in France » (date, lieu nommé, présence d\'une référence propre à l\'entrée) ; aucun texte n\'est copié ni conservé. Ces entrées ne créent aucune fiche : elles servent de seconde source aux fiches existantes. Page des conditions de réutilisation relue le 2026-11-19.',
            'lu_le' => '2026-11-19',
        ],
        'wikipedia-de' => [
            'nom' => 'Wikipedia (DE)',
            'statut' => 'sur',
            'licence' => 'CC BY-SA 4.0 pour les textes ; les faits (dates) ne sont pas protégés',
            'accord' => null,
            'preuve' => [
                'https://de.wikipedia.org/wiki/Wikipedia:Weiternutzung',
                'https://creativecommons.org/licenses/by-sa/4.0/legalcode.de',
            ],
            'citation' => 'Grundsätzlich dürfen alle Texte der Wikipedia gemäß der Creative-Commons-Lizenz CC-BY-SA 4.0 (Namensnennung - Weitergabe unter gleichen Bedingungen 4.0) genutzt werden.',
            'obligations' => 'Attribution par le lien vers la révision lue (permalien gardé dans accident_sources) ; tout texte repris resterait sous CC BY-SA 4.0.',
            'remarques' => 'Fiches d\'accidents (vff:import-accidents --only=wikipedia-editions) : seuls des FAITS sont lus dans « Liste schwerer Unfälle im Schienenverkehr » (date, lieu en tête de l\'entrée, présence d\'une référence) ; aucun texte n\'est copié ni conservé. Ces entrées ne créent aucune fiche. Page des conditions de réutilisation relue le 2026-11-19.',
            'lu_le' => '2026-11-19',
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
                .'Faits relus appliqués au moteur depuis le 2026-11-21 (décision de l\'utilisateur sur la question 19) : line_events et source_stations, source « argentanwebferro », '
                .'rangée après Wikipédia et avant OpenHistoricalMap, avec le lien de l\'article et une phrase citée au plus (vff:apply-argentanwebferro).',
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
                'parcdescombes.com', 'marc-andre-dubout.org', 'mejanes-camargue.fr',
                'cftr.evolutive.org', 'rail.lu', 'cftanse.fr', 'amitram.fr', 'minieresbunn.lu',
                // Rames remarquables du matériel (database/data/material_notable_units.php) : LyonMag (27/02/2020),
                // forum JNS rapportant France Bleu (06/02/2020) ; faits datés, courte citation, lien.
                'lyonmag.com', 'jnsforum.com',
                // Faits sur les relations du matériel (database/data/material_relation_notes.php) : communiqué
                // Région Normandie, SNCF Voyageurs et Saipol du 17/01/2023 (hébergé par Oleo100), article de la
                // Banque des Territoires avec l'AFP du 14/04/2021 ; Régiolis au B100 sur Paris - Granville.
                'oleo100.com', 'banquedesterritoires.fr',
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
        'insee' => [
            'nom' => 'Code officiel géographique (INSEE)',
            'statut' => 'sur',
            'licence' => 'Licence Ouverte 2.0 (Etalab), jeu « Code officiel géographique » publié par l\'INSEE sur data.gouv.fr',
            'accord' => null,
            'preuve' => [
                'https://www.data.gouv.fr/datasets/code-officiel-geographique-1',
                'https://www.insee.fr/fr/information/2560452',
            ],
            'citation' => 'Chaque année, l\'Insee met à disposition sur son site (insee.fr) le code officiel géographique qui rassemble les codes et libellés des communes, cantons, arrondissements, départements, collectivités territoriales ayant les compétences départementales, régions [...] (licence du jeu : fr-lo)',
            'obligations' => 'Mention de la source (INSEE, Code officiel géographique) et de la date de mise à jour. Compatible ODbL en entrée.',
            'remarques' => 'Codes et noms des départements et des régions (App\\Support\\FrenchDepartments) : valeurs des filtres department et region de l\'API, listes /api/v1/departments et /api/v1/regions. Aucune autre donnée de l\'INSEE n\'est reprise.',
            'domaines' => ['insee.fr'],
            'lu_le' => '2026-11-19',
        ],
        'gallica' => [
            'nom' => 'Gallica, bibliothèque numérique de la BnF (notices du catalogue général de la Bibliothèque nationale de France)',
            'statut' => 'sur',
            'licence' => 'Métadonnées descriptives de la BnF : Licence Ouverte (Etalab) depuis le 1er janvier 2014. Documents numérisés : conditions d\'utilisation des contenus de Gallica (réutilisation non commerciale libre et gratuite avec la mention de source, réutilisation commerciale soumise à autorisation et à redevance)',
            'accord' => null,
            'preuve' => [
                'https://www.bnf.fr/fr/conditions-de-reutilisations-des-donnees-de-la-bnf',
                'https://www.bnf.fr/sites/default/files/2018-11/metadonnees_bnf_cond_utilisation.pdf',
                'https://api.bnf.fr/fr/conditions-generales-dutilisation-du-site-bnf-api-et-jeux-de-donnees',
                'https://www.bnf.fr/fr/faire-une-utilisation-commerciale-dune-reproduction',
                'https://api.bnf.fr/fr/api-gallica-de-recherche',
                'https://api.bnf.fr/fr/api-sru-catalogue-general',
            ],
            'citation' => 'Métadonnées : « La BnF a, depuis le 1er janvier 2014, placé ses métadonnées descriptives (données bibliographiques et d\'autorité) sous la « Licence Ouverte / Open Licence » de l\'État élaborée par la mission Etalab. L\'utilisation de ces métadonnées est libre et gratuite sous réserve du maintien de la mention de leur source et de l\'indication de leur date de récupération. » '
                .'Documents : « Ces utilisations commerciales sont soumises à autorisation et au paiement d\'une redevance [...] L\'utilisation non commerciale des documents ou dans une publication académique ou scientifique est libre et gratuite sous réserve de la mention de source : BnF ou Bibliothèque nationale de France. »',
            'obligations' => 'Mention « Source : gallica.bnf.fr / BnF » (Bibliothèque nationale de France) et date de récupération des notices. '
                .'Seules les métadonnées des notices produites par la BnF sont reprises (titre, date, type, auteurs, cote, identifiants ark) avec un lien vers le document dans Gallica (vff:import-gallica, table line_archive_documents). '
                .'Aucune image, vignette ni texte numérisé n\'est stocké ni affiché : sur un site édité par une société, leur affichage pourrait relever de la réutilisation commerciale (tarif « Web et applications mobiles » : 75 € HT par image).',
            'remarques' => 'Lu le 2026-11-20. gallica.bnf.fr (SRU, OAI-PMH, IIIF et page des conditions d\'utilisation) refuse les requêtes de la machine de développement (blocage Cloudflare « Sorry, you have been blocked »), et aussi celles de l\'outil de lecture de pages : '
                .'les notices sont donc lues dans l\'API SRU du catalogue général (catalogue.bnf.fr/api/SRU, critère bib.digitized = freeAccess : document numérisé en accès libre dans Gallica), qui décrit les mêmes documents. '
                .'Le catalogue général ne décrit que les collections de la BnF : les documents des bibliothèques partenaires de Gallica, soumis aux règles de chaque institution, n\'y sont pas. '
                .'La page des conditions d\'utilisation de Gallica (https://gallica.bnf.fr/edit/und/conditions-dutilisation-des-contenus-de-gallica, citée comme licence par la page de l\'API) n\'a pas pu être lue.',
            'domaines' => ['gallica.bnf.fr', 'catalogue.bnf.fr'],
            'lu_le' => '2026-11-20',
        ],
        // Fournisseurs des documents cités par le contexte des accidents (vff:enrich-accidents), conditions lues le
        // 2026-11-21 (rapport storage/app/reports/agents/nuit-2111/accidents-archives.md). Faits seulement, avec lien.
        'retronews' => [
            'nom' => 'RetroNews (presse ancienne, BnF-Partenariats)',
            'statut' => 'incertain',
            'licence' => 'Aucune réutilisation autorisée : contenus protégés, accès pour un usage personnel et non commercial (CGU du 09/07/2026)',
            'accord' => null,
            'preuve' => ['https://www.retronews.fr/pages/cgu'],
            'citation' => 'Les Contenus sont protégés par des droits de propriété intellectuelle. Leur consultation ne vaut pas autorisation de réutilisation. [...] Aucun téléchargement n\'est autorisé.',
            'obligations' => 'Lien vers l\'article seulement (titre du journal, date, page) ; aucun texte, aucune image, aucune extraction automatique. Accès payant : un article RetroNews n\'appuie aucun énoncé (accès paywall).',
            'remarques' => 'Les CGU ne disent rien des liens hypertextes. Interdits notamment : téléchargement, reproduction, extraction, réutilisation à des fins commerciales.',
            'domaines' => ['retronews.fr'],
            'lu_le' => '2026-11-21',
        ],
        'assemblee-nationale' => [
            'nom' => 'Assemblée nationale (débats, questions écrites et réponses des ministres, données ouvertes)',
            'statut' => 'sur',
            'licence' => 'Documents parlementaires non protégés par le droit d\'auteur (art. L. 122-5 du CPI) ; données de data.assemblee-nationale.fr sous Licence Ouverte (exploitation commerciale permise)',
            'accord' => null,
            'preuve' => [
                'https://data.assemblee-nationale.fr/licence-ouverte-open-licence',
                'https://www.assemblee-nationale.fr/dyn/langues/welcome-to-the-english-website-of-the-french-national-assembly/contact',
            ],
            'citation' => 'Depuis novembre 2011, la "Licence Ouverte / Open Licence" s\'applique à l\'ensemble des réutilisations libres gratuites de données publiques [...] autorisant la reproduction, la redistribution, l\'adaptation et l\'exploitation commerciale des données.',
            'obligations' => 'Faits seulement, avec la date de la séance ou le numéro de la question et un lien vers le document. Aucune citation : les pages du site interdisent la reproduction à des fins commerciales (« Any use or reproduction for commercial purposes is forbidden »), alors que les données ouvertes l\'autorisent ; VFF étant édité par une société, la prudence l\'emporte.',
            'remarques' => 'Mentions légales en français non trouvées (404). Le domaine assemblee-nationale.fr, rangé avant chez tiers-cites pour les corrections de lignes, est rattaché ici depuis le 21/11/2026 : mêmes documents, conditions lues.',
            'domaines' => ['assemblee-nationale.fr'],
            'lu_le' => '2026-11-21',
        ],
        'senat' => [
            'nom' => 'Sénat (comptes rendus des débats, questions, rapports ; données de data.senat.fr)',
            'statut' => 'sur',
            'licence' => 'Travaux parlementaires sans droit d\'auteur (art. L. 122-5 du CPI), reproduction sous réserve de gratuité de la diffusion ; données de data.senat.fr sous licence de réutilisation gratuite, exploitation commerciale permise',
            'accord' => null,
            'preuve' => ['https://www.senat.fr/mentions-legales.html', 'https://data.senat.fr/licence/'],
            'citation' => 'Les travaux parlementaires ne sont couverts par aucun droit d\'auteur (article L.122-5 du code de la propriété intellectuelle).',
            'obligations' => 'Faits seulement, avec la date de la séance et un lien ; mention « sur le site du Sénat ». Aucune citation ni reproduction (réserve de gratuité de la diffusion, VFF étant édité par une société). Aucun fichier audio ou vidéo.',
            'remarques' => 'La page des mentions légales demande d\'informer le webmestre des liens vers le site et les réserve aux usages non commerciaux : question posée à Baptiste (rapport accidents-archives).',
            'domaines' => ['senat.fr'],
            'lu_le' => '2026-11-21',
        ],
        'archives-nationales' => [
            'nom' => 'Archives nationales (France)',
            'statut' => 'sur',
            'licence' => 'Réutilisation gratuite des informations publiques, commerciale ou non, avec mention de l\'origine (CGU de la salle des inventaires virtuelle)',
            'accord' => null,
            'preuve' => [
                'https://www.archives-nationales.culture.gouv.fr/quelles-sont-les-modalites-de-reproduction-et-de-reutilisation',
                'https://www.siv.archives-nationales.culture.gouv.fr/siv/conditions-generales-d-utilisation',
            ],
            'citation' => 'La réutilisation des « informations publiques » contenues dans les documents produits ou reçus par les Archives nationales est gratuite. [...] La seule obligation est de mentionner l\'origine des documents.',
            'obligations' => 'Mention « Archives nationales (France) », cote ou intitulé, lien vers l\'instrument de recherche. Exclus : documents non librement communicables, archives privées, œuvres protégées.',
            'domaines' => ['archives-nationales.culture.gouv.fr'],
            'lu_le' => '2026-11-21',
        ],
        'francearchives' => [
            'nom' => 'FranceArchives (portail national des archives)',
            'statut' => 'incertain',
            'licence' => 'Jeux de données annoncés sous Licence Ouverte ; conditions propres à chaque service d\'archives pour les documents',
            'accord' => null,
            'preuve' => ['https://francearchives.gouv.fr/article/28204488'],
            'citation' => '(page non lue directement : défi JavaScript ; conditions relevées par recherche)',
            'obligations' => 'Lien vers la notice et faits seulement ; règles du service d\'archives qui conserve le document.',
            'domaines' => ['francearchives.gouv.fr'],
            'lu_le' => '2026-11-21',
        ],
        'archives-departementales' => [
            'nom' => 'Archives départementales (services en ligne de chaque département)',
            'statut' => 'incertain',
            'licence' => 'Variable selon le département : Licence Ouverte (Paris, Haute-Savoie), redevance au-delà d\'un seuil (Lot-et-Garonne), tous droits réservés (Savoie)',
            'accord' => null,
            'preuve' => [
                'https://patrimoines.savoie.fr/web/dsp_6171/mentions-legales',
                'https://archivesdepartementales.lotetgaronne.fr/fileadmin/cru-1769607006/mediatheque/mediatheque_archives/01-Documents/tarifs-reutilisation.pdf',
            ],
            'citation' => 'Savoie : « Tous les droits de reproduction sont réservés ». Lot-et-Garonne : « 0,005 € par image par an et signature d\'une licence de réutilisation commerciale avec redevance » (de 5 001 à 300 000 images).',
            'obligations' => 'Faits et cote avec lien seulement ; aucune image. Lire la page « réutilisation » du département avant toute reprise d\'image.',
            'lu_le' => '2026-11-21',
        ],
        'persee' => [
            'nom' => 'Persée (revues savantes numérisées)',
            'statut' => 'incertain',
            'licence' => 'Usage privé, scientifique ou pédagogique, à l\'exclusion de toute finalité commerciale ; liens permis vers toute page',
            'accord' => null,
            'preuve' => ['https://www.persee.fr/cgu'],
            'citation' => 'Persée autorise la création de liens vers toute page du Site.',
            'obligations' => 'Référence bibliographique (faits) et lien vers l\'article seulement ; aucun texte ni métadonnée reprise en masse sans accord (information@persee.fr).',
            'domaines' => ['persee.fr'],
            'lu_le' => '2026-11-21',
        ],
        'europeana' => [
            'nom' => 'Europeana',
            'statut' => 'sur',
            'licence' => 'Métadonnées sous CC0 1.0 ; objets selon la déclaration de droits de chacun',
            'accord' => null,
            'preuve' => ['https://www.europeana.eu/en/rights/usage-guidelines-for-metadata', 'https://www.europeana.eu/en/rights/terms-of-use'],
            'citation' => 'available free of restriction under the Creative Commons CC0 1.0 Universal Public Domain Dedication',
            'obligations' => 'Attribution des sources des métadonnées ; objet repris seulement si sa déclaration de droits le permet, sinon lien.',
            'domaines' => ['europeana.eu'],
            'lu_le' => '2026-11-21',
        ],
        'ina' => [
            'nom' => 'INA (archives audiovisuelles)',
            'statut' => 'incertain',
            'licence' => 'Usage privé et non commercial ; reproduction et diffusion interdites sans accord écrit ; liens libres sous conditions, sans transclusion',
            'accord' => null,
            'preuve' => ['https://www.ina.fr/conditions-generales-d-utilisation'],
            'citation' => 'Toute reproduction, diffusion, communication publique [...] (section 1.2 : interdites sauf accord écrit et préalable de l\'Editeur)',
            'obligations' => 'Lien simple vers la page de la vidéo, sans lecteur incrusté ni image ; une vidéo n\'appuie aucun énoncé (accès link_only).',
            'domaines' => ['ina.fr'],
            'lu_le' => '2026-11-21',
        ],
    ],

    'sources' => [
        // Sources des fiches d'accidents (vff:import-accidents) : accident_sources.provider.
        'accident_sources' => [
            'wikidata' => 'wikidata',
            'wikipedia' => 'wikipedia',
            'wikipedia-en' => 'wikipedia-en',
            'wikipedia-de' => 'wikipedia-de',
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
            'lignes-oubliees' => 'lignes-oubliees',
            // Années tirées du NOM des tracés archeo (« 14-18 », « 30-40 »…).
            'archeoferroviaire' => 'archeoferroviaire',
            // Faits relus des articles d'Argentan Web Ferroviaire (vff:apply-argentanwebferro) : fait, lien, une phrase citée.
            'argentanwebferro' => 'argentanwebferro',
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
            'argentanwebferro' => 'argentanwebferro',
        ],
        // Audit des gares (vff:audit-stations) : source de chaque position comparée (station_audits.positions),
        // dont les zones de gare de la BD TOPO (WFS de la Géoplateforme, Licence Ouverte 2.0).
        'station_audits' => [
            'sncf' => 'sncf', 'sncf_voyageurs' => 'sncf', 'osm' => 'osm', 'ohm' => 'ohm', 'wikidata' => 'wikidata',
            'bdtopo' => 'ign', 'wikipedia_schema' => 'wikipedia', 'lignes-oubliees' => 'lignes-oubliees',
            'argentanwebferro' => 'argentanwebferro', 'correction' => 'vff',
        ],
        // Dates candidates des gares (vff:import-station-wikidata-dates), comparées aux dates de VFF sans les remplacer.
        'station_date_candidates' => [
            'wikidata' => 'wikidata',
        ],
        'source_stations' => [
            'lignes-oubliees' => 'lignes-oubliees', 'wikipedia' => 'wikipedia',
            // Arrêts des articles d'Argentan Web Ferroviaire (nom et PK), connus de VFF ou confirmés par une autre source.
            'argentanwebferro' => 'argentanwebferro',
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
        // Voies vertes OSM rattachées aux lignes (vff:link-greenways) : way, géométrie et mesure tirés d'OpenStreetMap (ODbL).
        'line_greenways' => ['osm' => 'osm'],
        'line_signalling' => ['sncf' => 'sncf'],
        // Mises en service de signalisation datées (vff:import-signalling-history) : faits relevés dans les articles de ligne de Wikipédia (CC BY-SA 4.0).
        // La portion placée (section_geom) est tracée sur les formes SNCF Réseau (ODbL), comme line_signalling_map ; la carte par ère
        // (line_signalling_history_map) reprend ces faits sur le tracé VFF de chaque ère.
        'line_signalling_events' => ['wikipedia' => 'wikipedia'],
        // Profils en long (vff:compute-line-profiles) : altitude de la voie BD TOPO, à défaut RGE ALTI (line_profiles.source).
        'line_profiles' => ['bdtopo-rge-alti' => 'ign'],
        // Points du profil (line_profile_points.source) : altitude de la voie (BD TOPO) ou du terrain (RGE ALTI).
        'line_profile_points' => ['bdtopo' => 'ign', 'rge_alti' => 'ign'],
        // Documents d'archives de Gallica rattachés aux lignes (vff:import-gallica) : métadonnées des notices de la BnF
        // (Licence Ouverte) et lien vers Gallica, jamais l'image ni le texte numérisés.
        'line_archive_documents' => ['gallica' => 'gallica'],
        // Contexte sourcé des accidents (vff:enrich-accidents, database/data/accident_contexts/*.php) :
        // accident_references.provider. Énoncés rédigés par VFF, faits seulement avec lien ; une courte citation
        // exacte au plus par fiche, et seulement d'un rapport du BEA-TT ou d'un texte du Journal officiel (Licence
        // Ouverte). Presse, ouvrages, sites d'histoire locale, débats parlementaires : faits seulement, lien.
        // wikipedia-it : même licence que les autres éditions (CC BY-SA 4.0), faits seulement.
        'accident_references' => [
            'bea-tt' => 'bea-tt', 'legifrance' => 'jorf', 'gallica' => 'gallica', 'commons' => 'commons',
            'wikipedia' => 'wikipedia', 'wikipedia-en' => 'wikipedia-en', 'wikipedia-de' => 'wikipedia-de', 'wikipedia-it' => 'wikipedia',
            'wikidata' => 'wikidata', 'presse' => 'tiers-cites', 'ouvrages' => 'tiers-cites', 'sites-web' => 'tiers-cites',
            'retronews' => 'retronews', 'assemblee-nationale' => 'assemblee-nationale', 'senat' => 'senat',
            'archives-nationales' => 'archives-nationales', 'francearchives' => 'francearchives',
            'archives-departementales' => 'archives-departementales', 'persee' => 'persee', 'europeana' => 'europeana', 'ina' => 'ina',
        ],
        // Images d'archives des accidents : fichiers de Wikimedia Commons, licence relue sur chaque fichier.
        'accident_images' => ['commons' => 'commons'],
        // Matériel roulant (vff:import-materials) : fiches Wikidata, champ par champ (provenance),
        // image Commons (licence propre à chaque fichier), liens matériel ↔ ligne attestés.
        // `contrib` : séries, champs, liens et périodes proposés par les contributeurs et validés (LineMaterialContributions,
        // MaterialSeriesContributions), sous CC BY-SA 4.0 comme les autres contributions.
        'materials' => ['wikidata' => 'wikidata', 'contrib' => 'vff'],
        'materials.fields' => ['wikidata' => 'wikidata', 'commons' => 'commons', 'wikipedia' => 'wikipedia', 'contrib' => 'vff'],
        'materials.image' => ['commons' => 'commons'],
        'line_materials' => ['wikidata' => 'wikidata', 'wikipedia' => 'wikipedia', 'contrib' => 'vff'],
        // Périodes de circulation datées (vff:import-material-periods) : faits relevés dans Wikipédia (CC BY-SA 4.0), dates P580/P582 de Wikidata (CC0).
        'line_material_periods' => ['wikidata' => 'wikidata', 'wikipedia' => 'wikipedia', 'contrib' => 'vff'],
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
            // Événements contribués et validés (EventContributions, dates des tracés contribués) : le lien
            // cité décide, comme pour les corrections manuelles ; VFF pour un ouvrage ou une archive papier.
            'contrib' => 'vff',
        ],
        'station_events' => [
            // Corrections sourcées des dates de gares : le lien cité décide.
            'correction' => 'vff',
        ],
    ],
];
