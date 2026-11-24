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
                'https://ressources.data.sncf.com/api/explore/v2.1/catalog/datasets/caracteristique-des-voies-et-declivite',
                'https://data.sncf.com/api/explore/v2.1/catalog/datasets/regime-dexploitation-des-lignes',
                'https://data.sncf.com/api/explore/v2.1/catalog/datasets/liste-des-quais',
                'https://data.sncf.com/api/explore/v2.1/catalog/datasets/lignes-equipees-de-liaison-radio-sol-train',
            ],
            'citation' => '"license": "Open Database License (ODbL)" (métadonnées des 12 jeux utilisés, lues le 2026-10-20) ; '
                .'« licence » : « odc-odbl » pour horaires-sncf et horaires-des-lignes-transilien-1 (API transport.data.gouv.fr, lue le 2026-11-18) ; '
                .'"license": "Open Database License (ODbL)", "publisher": "SNCF Réseau" pour liste-des-passages-a-niveau, mode-de-cantonnement-des-lignes et lignes-equipees-de-kvb (lus le 2026-11-20) ; '
                .'"license": "Open Data Commons Open Database License (ODbL)", "publisher": "Gares & Connexions" pour frequentation-gares (2025) et frequentation-gares-archive (2015-2024), lus le 2026-11-21 (database/data/frequentation_gares_405000.php ; les deux jeux ne sont pas comparables entre eux, avertissement de SNCF) ; '
                .'"license": "Open Database License (ODbL)", "publisher": "SNCF Réseau" pour caracteristique-des-voies-et-declivite, lu le 2026-11-22 (database/data/sourced_grades_sncf.php, vff:import-sncf-grades, qui revérifie la licence à chaque téléchargement) ; '
                .'"license": "Open Database License (ODbL)", "publisher": "SNCF Réseau" pour liste-des-quais (modifié le 2020-05-26), lu le 2026-11-22 (vff:import-sncf-platforms, table station_platforms, qui revérifie la licence et l\'éditeur à chaque téléchargement) ; '
                .'"license": "Open Database License (ODbL)", "publisher": "SNCF Réseau" pour regime-dexploitation-des-lignes (modifié le 2022-03-24), lu le 2026-11-22 (vff:import-sncf-track-regime, tables line_track_portions et lines.nb_voies_default, trace line_sources « sncf-regime ») ; '
                .'"license": "Open Database License (ODbL)", "publisher": "SNCF Réseau" pour lignes-equipees-de-liaison-radio-sol-train (modifié le 2022-03-24), lu le 2026-11-22 (vff:import-sncf-radio, table line_radio_portions) ; les deux commandes revérifient la licence et l\'éditeur à chaque téléchargement ; '
                .'"license": "Open Data Commons Open Database License (ODbL)", "publisher": "Gares & Connexions" pour frequentation-gares (modifié le 2026-11-13) et frequentation-gares-archive (modifié le 2025-07-03), relus le 2026-11-23 (vff:import-sncf-attendance, table station_attendances, qui revérifie la licence et l\'éditeur à chaque téléchargement) ; SNCF y écrit que 2025 vient d\'autres sources et d\'une autre méthode de calcul que 2015-2024 ; '
                .'"license": "Open Data Commons Open Database License (ODbL)" ou "Open Database License (ODbL)", "publisher": "Gares & Connexions" pour accessibilite_gares (NeTEx, modifié le 2026-05-21), equipements-accessibilite-en-gare, assistance-psh-pmr, etat-fonctionnement-elevatique-gare, gares-de-voyageurs et stationnement-securise-velo-en-gare-au-30-06-24, et "publisher": "SNCF Voyageurs" pour points-vente, lus le 2026-11-23 (vff:import-station-accessibility, table station_accessibility, qui revérifie la licence et l\'éditeur à chaque téléchargement)',
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
        'pop' => [
            'nom' => 'POP, Plateforme ouverte du patrimoine (base Mérimée, ministère de la Culture)',
            'statut' => 'sur',
            'licence' => 'Licence Ouverte 2.0 (Etalab)',
            'accord' => null,
            'preuve' => [
                'https://www.data.gouv.fr/datasets/immeubles-proteges-au-titre-des-monuments-historiques-2',
                'https://www.pop.culture.gouv.fr/',
            ],
            'citation' => 'Jeu « Immeubles protégés au titre des Monuments Historiques » publié par le ministère de la Culture sur data.gouv.fr, licence « lov2 » (Licence Ouverte v2.0), dernière mise à jour le 2026-11-19.',
            'obligations' => 'Mention de la paternité (« Ministère de la Culture, base Mérimée, POP ») et de la date de mise à jour. '
                .'Seul le fichier officiel du ministère est lu (ministere-culture.s3.sbg.io.cloud.ovh.net/POP/merimee.csv), pas les copies de tiers (Toulouse métropole, départements). '
                .'La licence est revérifiée à chaque téléchargement (vff:import-station-heritage, table station_heritage).',
            'remarques' => 'Seule la base Mérimée des monuments historiques est lue (références PA) ; l\'Inventaire général et le label Architecture contemporaine remarquable viennent de Wikidata (P1435, P380).',
            'domaines' => ['pop.culture.gouv.fr'],
            'lu_le' => '2026-11-23',
        ],
        'palissy' => [
            'nom' => 'POP, Plateforme ouverte du patrimoine (base Palissy, ministère de la Culture)',
            'statut' => 'sur',
            'licence' => 'Licence Ouverte 1.0 (Etalab, « fr-lo » sur data.gouv.fr)',
            'accord' => null,
            'preuve' => [
                'https://www.data.gouv.fr/datasets/liste-des-objets-mobiliers-propriete-publique-classes-au-titre-des-monuments-historiques-1',
            ],
            'citation' => 'Jeu « Liste des objets protégés au titre des Monuments historiques » publié par le ministère de la Culture sur data.gouv.fr, licence « fr-lo » (Licence Ouverte), ressource palissy.csv, dernière mise à jour le 2026-11-13 (lu le 2026-11-23 par l\'API data.gouv.fr ; le portail data.culture.gouv.fr n\'expose plus de page lisible sans JavaScript).',
            'obligations' => 'Mention de la paternité (« Ministère de la Culture, base Palissy, POP ») et de la date de mise à jour. Seul le fichier officiel du ministère est lu (ministere-culture.s3.sbg.io.cloud.ovh.net/POP/palissy.csv).',
            'remarques' => 'Seuls les objets ferroviaires protégés sont gardés (database/data/preserved_rolling_stock.php, clé `palissy`) : numéro, lieu au moment de la protection, date et nature de la protection.',
            'domaines' => ['pop.culture.gouv.fr'],
            'lu_le' => '2026-11-23',
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
                .'Bibliographie des lignes et des gares (vff:import-bibliography, table bibliography_entries) : seules les notices de livres du catalogue général (auteurs, titre, éditeur, lieu, année, ISBN) avec le lien vers la notice sur catalogue.bnf.fr, la source et la date de récupération ; une requête toutes les 5 secondes au plus, User-Agent du projet (API SRU documentée sur api.bnf.fr). '
                .'Aucune image, vignette ni texte numérisé n\'est stocké ni affiché : sur un site édité par une société, leur affichage pourrait relever de la réutilisation commerciale (tarif « Web et applications mobiles » : 75 € HT par image).',
            'remarques' => 'Lu le 2026-11-20. gallica.bnf.fr (SRU, OAI-PMH, IIIF et page des conditions d\'utilisation) refuse les requêtes de la machine de développement (blocage Cloudflare « Sorry, you have been blocked »), et aussi celles de l\'outil de lecture de pages : '
                .'les notices sont donc lues dans l\'API SRU du catalogue général (catalogue.bnf.fr/api/SRU, critère bib.digitized = freeAccess : document numérisé en accès libre dans Gallica), qui décrit les mêmes documents. '
                .'Le catalogue général ne décrit que les collections de la BnF : les documents des bibliothèques partenaires de Gallica, soumis aux règles de chaque institution, n\'y sont pas. '
                .'La page des conditions d\'utilisation de Gallica (https://gallica.bnf.fr/edit/und/conditions-dutilisation-des-contenus-de-gallica, citée comme licence par la page de l\'API) n\'a pas pu être lue.',
            'domaines' => ['gallica.bnf.fr', 'catalogue.bnf.fr'],
            'lu_le' => '2026-11-20',
        ],
        // Notices des Archives SNCF rattachées aux lignes (vff:import-sncf-archives, table line_archive_records) : métadonnées
        // du jeu de données « archives-sncf-sardo » et lien vers archives.sncf.com, jamais l'image ni le document numérisés.
        'sncf-archives' => [
            'nom' => 'Archives SNCF (Service Archives Documentation du groupe SNCF, jeu de données « archives-sncf-sardo »)',
            'statut' => 'sur',
            'licence' => 'Jeu de données : ODbL 1.0 ; documents numérisés : conditions d\'utilisation du site archives.sncf.com (droits indiqués sur chaque notice, « SNCF-MEDIATHEQUE » pour les photographies)',
            'accord' => null,
            // Les pages de ressources.data.sncf.com (métadonnées du jeu, licence) sont rangées chez le fournisseur `sncf` :
            // elles sont citées dans `remarques`.
            'preuve' => [
                'https://www.data.gouv.fr/api/1/datasets/archives-sncf-sardo/',
            ],
            'citation' => '"license": "Open Database License (ODbL)", "license_url": "https://data.sncf.com/pages/licence", "publisher": "SNCF", 12 447 notices, modifié le 2023-12-21 (métadonnées du jeu lues le 2026-11-22) ; '
                .'« license » : « odc-odbl » sur data.gouv.fr (même jour).',
            'obligations' => 'Attribution « SNCF, Service Archives Documentation » avec le nom du jeu de données ; partage à l\'identique de la base dérivée (ODbL). '
                .'Seuls le titre, le type, le classement, le service versant, la boîte, la date, le titulaire des droits et le lien sont repris. '
                .'Aucune description (elle peut nommer des agents), aucune image ni document : le lien mène à la notice sur archives.sncf.com. '
                .'Les thèmes qui portent sur des personnes (fichiers des cheminots blessés et tués, contentieux, questions de personnel) ne sont pas rattachés.',
            'remarques' => 'Lu le 2026-11-22. Métadonnées du jeu : https://ressources.data.sncf.com/api/explore/v2.1/catalog/datasets/archives-sncf-sardo ; FAQ de la licence : https://data.sncf.com/pages/licence. '
                .'Export JSON du jeu lu en une requête (ressources.data.sncf.com, plateforme Opendatasoft), copie locale dans storage/app/data/archives-sncf. '
                .'Le site archives.sncf.com lui-même n\'est jamais interrogé par VFF.',
            'domaines' => ['archives.sncf.com'],
            'lu_le' => '2026-11-22',
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
        // Presse ancienne lue par le texte pour le contexte des accidents (lots de presse du 21/11/2026) : sources de
        // faits validées par Baptiste le 22/11/2026. Énoncés rédigés par VFF, lien vers la page du journal, aucun texte
        // recopié, aucune image, aucun nom de victime privée.
        'delpher' => [
            'nom' => 'Delpher, Koninklijke Bibliotheek',
            'statut' => 'incertain',
            'licence' => 'Selon les conditions d\'utilisation de Delpher : le matériel peut être utilisé librement pour sa propre recherche, le matériel publié pour la première fois il y a plus de 140 ans est dans le domaine public, les droits des notices et des images de presse plus récentes restent ceux de chaque objet ; aucune licence ouverte générale',
            'accord' => null,
            'preuve' => ['https://www.delpher.nl/over-delpher/gebruiksvoorwaarden', 'https://www.kb.nl/over-ons/diensten/delpher', 'https://www.kb.nl/contact/auteursrecht', 'https://www.kb.nl/legal/gebruiksvoorwaarden-en-klachtregeling'],
            'citation' => 'Al het materiaal in Delpher mag je voor eigen onderzoek vrij gebruiken, maar het is niet toegestaan om grote aantallen objecten waar (mogelijk) auteursrecht op rust te downloaden.',
            'obligations' => 'Crédit « Delpher, Koninklijke Bibliotheek » (mention de la source appréciée, usage courant) avec lien vers l\'article (resolver.kb.nl). Faits seulement, rédigés par VFF : aucun texte OCR recopié, aucune image, aucune extraction en masse d\'objets possiblement sous droit.',
            'remarques' => 'Conditions relues le 22/11/2026 sur delpher.nl/over-delpher/gebruiksvoorwaarden (page lisible par l\'outil de lecture, 403 pour curl) et sur kb.nl. Lu par l\'API SRU publique jsru.kb.nl (une requête à la fois).',
            'domaines' => ['delpher.nl', 'resolver.kb.nl', 'kb.nl'],
            'lu_le' => '2026-11-22',
        ],
        'letempsarchives' => [
            'nom' => 'Le Temps Archives (Journal de Genève, Gazette de Lausanne, Le Nouveau Quotidien)',
            'statut' => 'incertain',
            'licence' => 'Pas de licence ouverte : archives numérisées de Le Temps SA, sans page de conditions propre ; les conditions générales de letemps.ch interdisent la reproduction, la mise en lien et l\'utilisation à des fins publiques ou commerciales sans autorisation écrite de Le Temps ; les journaux du XIXe et du début du XXe siècle sont sans doute dans le domaine public (auteurs morts depuis plus de 70 ans), mais la numérisation et la base appartiennent au Temps',
            'accord' => null,
            'preuve' => ['https://www.letempsarchives.ch/robots.txt', 'https://www.letemps.ch/conditions-generales', 'https://www.letempsarchives.ch/'],
            'citation' => 'La reproduction (entière ou partielle), la transmission (électronique ou par d\'autres voies), la modification, la mise en lien ou l\'utilisation de ces marques, noms, logos, photos, éléments visuels, textes et autres matériaux à des fins publiques ou commerciales sont interdites sans autorisation écrite préalable de Le Temps.',
            'obligations' => 'Faits seulement (date, lieu, bilan), rédigés par VFF, avec le journal, la date, la page et le lien vers la page du numéro (letempsarchives.ch/page/<numéro>/<page>) ; une citation de quelques mots au plus ; aucune image, aucune reproduction de page ni d\'extrait de texte ; pas d\'extraction en masse.',
            'remarques' => 'robots.txt du 06/11/2026 : « Disallow: /* », « Allow: /$ » (seule la page d\'accueil est ouverte aux programmes) ; la recherche demande un jeton de session. Le 22/11/2026 (21 h 50) puis le 23/11/2026 vers 1 h, Baptiste, responsable du projet, a donné l\'ordre exprès d\'accéder au site malgré le robots.txt, parce qu\'il estime avoir lui-même cette autorisation, un humain étant derrière les requêtes : l\'accès est donc fait pour lui et sur son autorisation, pas sur un accord de Le Temps SA (aucun accord écrit reçu ; en demander un reste conseillé). Conditions d\'accès tenues : une requête toutes les 5 secondes au moins, User-Agent « VFF-Bot/1.0 (+https://vff-project.org) », seulement la recherche et les pages des accidents sans article déjà connu, aucune barrière technique contournée. Les pages sont des images (IIIF) sans texte lisible par programme ; la recherche est floue et renvoie surtout des faux positifs (Nyon pour Saint-Sornin, Siedlice pour Vrizy).',
            'domaines' => ['letempsarchives.ch'],
            'lu_le' => '2026-11-23',
        ],
        'ddb-zeitungsportal' => [
            'nom' => 'Deutsches Zeitungsportal (Deutsche Digitale Bibliothek)',
            'statut' => 'incertain',
            'licence' => 'Conditions de la Deutsche Digitale Bibliothek : textes du site sous CC BY 4.0 ; droits des journaux numérisés propres à chaque objet (mention de droits de l\'objet)',
            'accord' => null,
            'preuve' => [
                'https://www.deutsche-digitale-bibliothek.de/content/newspaper/nutzungsbedingungen_dzp',
                'https://www.deutsche-digitale-bibliothek.de/content/nutzungshinweise',
            ],
            'citation' => 'Es gelten die Nutzungsbedingungen der Deutschen Digitalen Bibliothek.',
            'obligations' => 'Faits seulement, avec le titre du journal, la date, la page et un lien vers la page du portail. Aucun texte OCR recopié, aucune image ; la mention de droits de chaque objet décide de toute autre reprise.',
            'remarques' => 'Lu le 22/11/2026. La FAQ du portail (content/newspaper/fragen-antworten) n\'a pas été lue. Recherche en plein texte par l\'API Solr publique du portail.',
            'domaines' => ['deutsche-digitale-bibliothek.de'],
            'lu_le' => '2026-11-22',
        ],
        'victimes-du-rail' => [
            'nom' => 'Victimes du rail (relevés de presse ancienne sur les victimes d\'accidents ferroviaires)',
            'statut' => 'incertain',
            'licence' => 'Pas de licence : site personnel, éditeur non nommé, qui demande d\'être cité comme source',
            'accord' => null,
            'preuve' => ['https://www.victimesdurail.fr/pourquoi/', 'https://www.victimesdurail.fr/confidentialite-des-donnees/'],
            'citation' => 'Merci de mentionner ce site comme source si vous utilisez les données publiées ici.',
            'obligations' => 'Faits seulement (date, lieu, bilan, nature), avec la mention « Victimes du rail » et un lien vers la page lue. Aucun nom de victime repris, aucune transcription recopiée.',
            'remarques' => 'Aucune page de mentions légales ni de conditions (lu le 22/11/2026). Les transcriptions du site citent la presse d\'époque : remonter au journal quand il est lisible.',
            'domaines' => ['victimesdurail.fr'],
            'lu_le' => '2026-11-22',
        ],
        // Presse européenne lue par le texte pour le contexte des accidents (agent accidents-europe, 22/11/2026) :
        // faits seulement, lien vers la page, aucun texte ni image repris.
        'anno' => [
            'nom' => 'ANNO, AustriaN Newspapers Online (Österreichische Nationalbibliothek)',
            'statut' => 'incertain',
            'licence' => 'Pas de licence nommée : l\'ÖNB ne revendique aucun droit d\'exploitation sur ses contenus en ligne et consent à leur réutilisation ; droits des tiers à vérifier ; numérisations de Google limitées à l\'usage non commercial',
            'accord' => null,
            'preuve' => ['https://anno.onb.ac.at/node/11'],
            'citation' => 'An allen Inhalten, die von der Österreichischen Nationalbibliothek (ÖNB) ins Netz gestellt werden, macht die ÖNB kein eigenes urheberrechtliches Verwertungsrecht geltend.',
            'obligations' => 'Titre, date et page du journal avec la mention « ANNO/Österreichische Nationalbibliothek » ou le lien ANNO. Faits seulement, aucun texte OCR recopié, aucune image (les numérisations de Google sont réservées à l\'usage non commercial).',
            'remarques' => 'Lu le 22/11/2026 (FAQ, rubrique Nachnutzung). Recherche par l\'API de la recherche plein texte (anno-suche/rest, permise par le robots.txt), une requête à la fois ; le texte intégral d\'une page passe par une vérification humaine, non contournée : seuls les extraits de la recherche sont lus.',
            'domaines' => ['anno.onb.ac.at', 'data.onb.ac.at'],
            'lu_le' => '2026-11-22',
        ],
        'eluxemburgensia' => [
            'nom' => 'eluxemburgensia (presse luxembourgeoise numérisée, Bibliothèque nationale du Luxembourg)',
            'statut' => 'incertain',
            'licence' => 'Domaine public librement réutilisable, y compris à des fins commerciales (œuvres publiées avant le 31/12/1883 selon la règle prudente de la BnL en 2026) ; contenus plus récents protégés, limités aux exceptions du droit d\'auteur',
            'accord' => null,
            'preuve' => ['https://eluxemburgensia.lu/fr/support/copyrights'],
            'citation' => 'Les contenus faisant partie du domaine public peuvent être utilisés à toutes fins, y compris à des fins commerciales.',
            'obligations' => 'Titre du journal, date, page, mention « numérisé par la Bibliothèque nationale du Luxembourg, www.eluxemburgensia.lu » et lien direct (persist.lu). Faits seulement, aucun texte ni image repris pour les contenus protégés.',
            'remarques' => 'Lu le 22/11/2026 (page Droits, servie par l\'API du site). Pas de robots.txt. Recherche par l\'API publique du visualiseur (viewer.eluxemburgensia.lu/api/viewer2/search), une requête à la fois ; seuls les extraits de la recherche sont lus.',
            'domaines' => ['eluxemburgensia.lu', 'persist.lu'],
            'lu_le' => '2026-11-22',
        ],
        // Images ouvertes (autochromes, cartes postales d'archives) rattachées aux gares et aux lignes : vff:import-open-data-media
        // (agent medias-2, 22/11/2026). Lecture par l'API tabulaire de data.gouv.fr ou le WFS du Grand Lyon, jamais par l'API des
        // portails Opendatasoft (leur robots.txt l'interdit aux robots) ; images en lien direct, jamais copiées.
        'albert-kahn' => [
            'nom' => 'Musée départemental Albert-Kahn, Archives de la Planète (Département des Hauts-de-Seine)',
            'statut' => 'sur',
            'licence' => 'Par image : CC0 1.0 (domaine public) ou CC BY 4.0, lue dans le champ « Licence » de chaque ligne ; index documentaire sous Licence Ouverte ; les images « non-diffusables » ou « non-librement réutilisables (droits réservés) » ne sont pas dans le jeu',
            'accord' => null,
            'preuve' => ['https://collections.albert-kahn.hauts-de-seine.fr/cgu_media', 'https://www.data.gouv.fr/datasets/archives-de-la-planete'],
            'citation' => '« La licence Creative Commons (CC-BY 4.0) permet également de copier, modifier, distribuer et représenter l\'œuvre, y compris à des fins commerciales, sans avoir besoin de demander l\'autorisation. La seule obligation est de créditer les créateurs des œuvres originales, d\'en indiquer les sources et d\'indiquer si des modifications ont été effectuées aux œuvres. »',
            'obligations' => 'Titre de l\'œuvre et nom de l\'auteur (l\'opérateur) à chaque affichage ; aucun traitement qui dénature l\'image ou en détourne le sens (droit moral, y compris pour le domaine public) ; revente des fichiers proscrite. '
                .'Mention invitée par le musée : légende, numéro d\'inventaire, « Musée départemental Albert-Kahn » et son appartenance au Département des Hauts-de-Seine, lien vers albert-kahn.hauts-de-seine.fr pour toute publication en ligne ; informer le musée des réutilisations (collection-musee-albert-kahn@hauts-de-seine.fr).',
            'remarques' => 'Réserve « soumis à CGU pour les images » levée le 22/11/2026 : les CGU (collections.albert-kahn.hauts-de-seine.fr/cgu_media) ne restreignent pas plus que CC0 et CC BY 4.0 (usage commercial permis). '
                .'Le téléchargement HD sur le site du musée exige un compte ; VFF ne télécharge rien et affiche l\'image du portail open data en lien direct. Les coordonnées du jeu sont celles de la commune, pas de la prise de vue.',
            'domaines' => ['albert-kahn.hauts-de-seine.fr'],
            'lu_le' => '2026-11-22',
        ],
        'ad92' => [
            'nom' => 'Archives départementales des Hauts-de-Seine (cartes postales 9 Fi, 1900-1944)',
            'statut' => 'sur',
            'licence' => 'Licence Ouverte (Etalab), déclarée pour le jeu',
            'accord' => null,
            'preuve' => ['https://www.data.gouv.fr/datasets/cartes-postales-2', 'https://opendata.hauts-de-seine.fr/explore/dataset/fr-229200506-cartes-postales/information/'],
            'citation' => '« licence » : « fr-lo » (API data.gouv.fr du jeu « Cartes postales » du Département des Hauts-de-Seine, lue le 22/11/2026)',
            'obligations' => 'Mention « Archives départementales des Hauts-de-Seine », cote (9FI/…) et licence ; lien vers la notice du portail.',
            'remarques' => 'Le crédit de l\'auteur de la carte (photographe ou éditeur) n\'est pas dans le jeu. Les jeux de Vanves et de Meudon reprennent cette sous-série : non importés à part.',
            'domaines' => ['opendata.hauts-de-seine.fr'],
            'lu_le' => '2026-11-22',
        ],
        'suresnes' => [
            'nom' => 'Ville de Suresnes (cartes postales anciennes des archives communales)',
            'statut' => 'sur',
            'licence' => 'Licence Ouverte (Etalab), déclarée pour le jeu ; seules les cartes les plus anciennes y figurent « en raison des droits d\'auteurs sur les images » (colonne « Open data »)',
            'accord' => null,
            'preuve' => ['https://www.data.gouv.fr/datasets/cartes-postales-anciennes'],
            'citation' => '« Seules les cartes les plus anciennes figurent dans l\'Open data, en raison des droits d\'auteurs sur les images. »',
            'obligations' => 'Mention « Archives communales de Suresnes », cote (6 FI …) et licence ; seules les lignes dont la colonne « Open data » est vraie sont reprises.',
            'domaines' => [],
            'lu_le' => '2026-11-22',
        ],
        'evry-courcouronnes' => [
            'nom' => 'Ville d\'Évry-Courcouronnes (cartes postales anciennes)',
            'statut' => 'incertain',
            'licence' => 'Licence Ouverte 2.0 (Etalab), déclarée pour le jeu ; mais le jeu précise que les cartes sont « soit libres de droits, certaines proviennent de dons de particuliers »',
            'accord' => null,
            'preuve' => ['https://www.data.gouv.fr/datasets/cartes-postales-anciennes-1', 'https://data.evrycourcouronnes.fr/explore/dataset/cartes-postales/information/'],
            'citation' => 'Ces cartes sont soit libres de droits, certaines proviennent de dons de particuliers.',
            'obligations' => 'Mention « Ville d\'Évry-Courcouronnes », identifiant et licence ; lien vers la notice du portail.',
            'remarques' => 'Statut incertain : la part des cartes données par des particuliers (sans cession écrite visible) n\'est pas distinguée dans le jeu. À confirmer par un courriel à la ville avant la mise en ligne.',
            'domaines' => ['data.evrycourcouronnes.fr'],
            'lu_le' => '2026-11-22',
        ],
        'sevres' => [
            'nom' => 'Ville de Sèvres (cartes postales anciennes des archives municipales)',
            'statut' => 'sur',
            'licence' => 'Licence Ouverte (Etalab), déclarée pour le jeu',
            'accord' => null,
            'preuve' => ['https://www.data.gouv.fr/datasets/cartes-postales-anciennes-des-archives-municipales'],
            'citation' => '« licence » : « fr-lo » (API data.gouv.fr du jeu « Cartes postales anciennes des Archives municipales », lue le 22/11/2026)',
            'obligations' => 'Mention « Archives municipales de Sèvres », cote (18 Fi …) et licence.',
            'domaines' => ['sevres-seineouest.opendatasoft.com', 'data.sevres.fr'],
            'lu_le' => '2026-11-22',
        ],
        'gadagne' => [
            'nom' => 'Ville de Lyon, musée Gadagne (cartes postales)',
            'statut' => 'incertain',
            'licence' => 'Licence Ouverte 2.0 (Etalab) déclarée sur data.gouv.fr ; le texte de la licence sur le portail du Grand Lyon (rendu par JavaScript) n\'a pas pu être lu : portée sur les images à confirmer',
            'accord' => null,
            'preuve' => ['https://www.data.gouv.fr/datasets/cartes-postales-du-musee-gadagne-de-la-ville-de-lyon'],
            'citation' => '« licence » : « lov2 » (API data.gouv.fr du jeu « Cartes postales du musée Gadagne de la ville de Lyon », lue le 22/11/2026)',
            'obligations' => 'Mention « Ville de Lyon, musée Gadagne », numéro d\'inventaire (004.12.…) et licence ; lien vers la fiche du jeu.',
            'remarques' => 'Les cartes sont des œuvres d\'éditeurs du début du XXe siècle : la licence de la Ville couvre sa numérisation et sa base, la portée sur les images reste à confirmer.',
            'domaines' => ['data.grandlyon.com'],
            'lu_le' => '2026-11-22',
        ],
        // Fonds ajoutés par l'agent medias-3 (22/11/2026), même importateur : vff:import-open-data-media (database/data/open_data_media.php).
        'saone-et-loire' => [
            'nom' => 'Archives départementales de Saône-et-Loire (cartes postales 6 FI)',
            'statut' => 'sur',
            'licence' => 'Licence Ouverte (Etalab), déclarée pour le jeu « Données mises à disposition par les Archives départementales de Saône-et-Loire » (ressource « Cartes postales »)',
            'accord' => null,
            'preuve' => ['https://www.data.gouv.fr/datasets/donnees-mises-a-disposition-par-les-archives-departementales-de-saone-et-loire/'],
            'citation' => '« licence » : « fr-lo » (API data.gouv.fr du jeu, mise à jour du 03/04/2019, lue le 22/11/2026) ; description du jeu : « cartes postales : paysages, personnages, sites, monuments de Saône-et-Loire, plus de 10000 images et leur description sont disponibles via ce jeu de données ».',
            'obligations' => 'Mention « Archives départementales de Saône-et-Loire », cote (6 FI n) et licence ; lien vers la notice du site des archives (archives71.fr).',
            'remarques' => 'Lecture par l\'API tabulaire de data.gouv.fr (tabular-api.data.gouv.fr, pas de robots.txt) : le robots.txt de static.data.gouv.fr interdit /resources, donc le CSV n\'est pas téléchargé. Les dates sont vagues (« Après 1903 ») ; les cartes « Après 1950 » (droits d\'éditeurs en cours) ne sont pas reprises (max_year 1944). L\'éditeur de la carte n\'est pas crédité (colonne éditeur non reprise).',
            'domaines' => ['saone-et-loire71.fr', 'archives71.fr'],
            'lu_le' => '2026-11-22',
        ],
        'getty' => [
            'nom' => 'J. Paul Getty Museum, Open Content (photographies de chemins de fer français : Baldus, Collard)',
            'statut' => 'sur',
            'licence' => 'CC0 1.0 (domaine public), lue sur chaque objet : droit « License for Collection Metadata » classé CC0 et affiché « Public Domain » (« No Copyright »)',
            'accord' => null,
            'preuve' => ['https://www.getty.edu/projects/open-content-program/', 'https://data.getty.edu/museum/collection/sparql'],
            'citation' => '« Digital images of Getty-owned artworks in the public domain marked with the CC0-public domain icon are available for download under CC0. Such images are not protected by copyright and may be used without restriction or fees for commercial and noncommercial purposes. »',
            'obligations' => 'Aucune obligation légale ; VFF mentionne « The J. Paul Getty Museum, Los Angeles (Open Content) », l\'auteur, la date et le numéro d\'inventaire, avec un lien vers la notice.',
            'remarques' => 'Lu le 22/11/2026. data.getty.edu : pas de robots.txt (404) ; www.getty.edu : robots.txt sans interdiction des pages lues ; media.getty.edu répondait 503 pour son robots.txt : aucune requête n\'y est faite (les adresses IIIF sont seulement composées, l\'image est affichée par le navigateur du visiteur). Les descriptions des notices sont sous CC BY 4.0 : non reprises. Une planche reliée dans deux albums (84.XO.401 et 84.XO.734.1) n\'est gardée qu\'une fois.',
            'domaines' => ['data.getty.edu', 'media.getty.edu', 'www.getty.edu'],
            'lu_le' => '2026-11-22',
        ],
        'enpc' => [
            'nom' => 'École nationale des ponts et chaussées, Héritage des ponts et chaussées (photographies de chantiers et de lignes, 1855-1905)',
            'statut' => 'sur',
            'licence' => 'Licence Ouverte Etalab pour les contenus « Source : École nationale des ponts et chaussées » (dépôt et cote de l\'École dans le manifeste IIIF) ; les documents « Source : Bibliothèque nationale de France » ou d\'autres institutions, hors Licence Ouverte, sont refusés',
            'accord' => null,
            'preuve' => ['https://heritage.ecoledesponts.fr/enpc/content/reutiliser-nos-collections'],
            'citation' => '« La réutilisation des contenus identifiés : « Source : École nationale des ponts et chaussées » sont téléchargeables et réutilisables avec la mention « École nationale des ponts et chaussées », selon les termes de la licence ouverte Etalab » ; « La réutilisation des métadonnées des documents disponibles dans Héritage des ponts et chaussées est soumise à la licence ouverte Etalab. »',
            'obligations' => 'Mention « École nationale des ponts et chaussées » avec la cote et l\'auteur ; la haute définition est payante pour un usage commercial (VFF n\'affiche que la moyenne définition du service IIIF, 1 600 px) ; documents d\'un tiers ou de la BnF exclus.',
            'remarques' => 'Lu le 22/11/2026. robots.txt : toute adresse à paramètres et la recherche SRU interdites ; seuls le plan du site (sitemap.txt) et les manifestes IIIF (/iiif/ark:/12148/<id>/manifest.json) sont lus, une requête toutes les 2 s. Le serveur n\'envoie pas son certificat intermédiaire (HARICA GEANT TLS R1) : il est joint par database/data/certs. Le champ license des manifestes renvoie vers une page de conditions de Gallica qui répond 404 : la page de l\'École fait foi. Vérifié le 22/11/2026 sur 8 notices HTML (pages ark) : métadonnée DC.rights « domaine public / public domain » et mention « Source heritage.ecoledesponts.fr / Ecole nationale des ponts et chaussées, <cote> », identiques au dépôt et à la cote du manifeste. Courriel de confirmation préparé (sources-images-2 E6), non envoyé.',
            'domaines' => ['heritage.ecoledesponts.fr'],
            'lu_le' => '2026-11-22',
        ],
        'numistral' => [
            'nom' => 'Numistral (Bibliothèque nationale et universitaire de Strasbourg, Université de Strasbourg)',
            'statut' => 'sur',
            'licence' => 'Licence Ouverte (Etalab) pour les reproductions « Source : Bibliothèque nationale et universitaire de Strasbourg » et « Source : Bibliothèque numérique patrimoniale de l\'Université de Strasbourg » ; métadonnées sous Licence Ouverte 2.0',
            'accord' => null,
            'preuve' => ['https://www.numistral.fr/fr/content/conditions-dutilisation'],
            'citation' => '« Les reproductions numériques provenant des collections de la Bibliothèque nationale et universitaire de Strasbourg (Bnu) sont signalées par la mention « Source : Bibliothèque nationale et universitaire de Strasbourg ». Ces documents sont diffusés sous la Licence Ouverte / Open Licence, et, à ce titre, librement réutilisables sous réserve de mention de source. »',
            'obligations' => 'Mention « Source : Bibliothèque nationale et universitaire de Strasbourg » (ou de l\'Université de Strasbourg), cote et licence. Les documents de la BnF (non commercial libre, commercial payant), de l\'Université de Haute-Alsace (non commercial) et de Mulhouse (en partie sous restriction) sont refusés.',
            'remarques' => 'Lu le 22/11/2026. Pas de robots.txt sur numistral.bnf.fr (404). Le site invite à moissonner par OAI-PMH (oai.bnf.fr, injoignable le jour de la lecture) : les identifiants viennent de la recherche (images, 50 résultats par page) et le dépôt de chaque image du manifeste IIIF.',
            'domaines' => ['numistral.bnf.fr', 'www.numistral.fr'],
            'lu_le' => '2026-11-22',
        ],
        // Rosalis et Pireneas (agent medias-4, 23/11/2026) : vff:import-open-data-media, notices Dublin Core par ark (DublinCoreReader).
        'rosalis' => [
            'nom' => 'Rosalis, bibliothèque numérique de Toulouse (images du domaine public, notices ODbL)',
            'statut' => 'sur',
            'licence' => 'Domaine public pour les images dont la notice porte « domaine public » (dc:rights), sans « Conditions spécifiques d\'utilisation » ni « Source : Bibliothèque nationale de France » ; notices sous ODbL',
            'accord' => null,
            'preuve' => ['https://rosalis.bibliotheque.toulouse.fr/rosalis/fr/content/conditions-dutilisation'],
            'citation' => '« Vous pouvez télécharger copier, distribuer et partager les documents ; Remixer, transformer et créer à partir des œuvres par tous moyens et sous tous formats, pour toute utilisation, y compris commerciale ; De façon libre et gratuite ! Mais, il faut citer le ou les auteurs (en respectant le droit moral), et mentionner la source » ; « Les œuvres entrées dans le domaine public sont librement réutilisables sous réserve de mentionner l\'auteur et la provenance ».',
            'obligations' => 'Mention de l\'auteur et de la provenance : « Auteur - Mairie de Toulouse, établissement de conservation - Rosalis, cote » (VFF écrit : auteur, date, établissement, Mairie de Toulouse, Rosalis, cote). Les documents à « Conditions spécifiques d\'utilisation » (droits de tiers) et ceux de la BnF (non commercial libre, commercial payant) sont refusés. Les notices (métadonnées) sont publiées sous ODbL : la base de VFF reprend le titre, l\'auteur, la date et les sujets, avec lien vers la notice.',
            'remarques' => 'Lu le 22/11/2026 (sources-images-3) et le 23/11/2026 (medias-4). robots.txt : adresses à paramètres, /node/* et /services/engine/search/sru interdits ; levé pour ces lectures par décision de Baptiste, User-Agent honnête, une requête toutes les 5 s. Recherche (HTML) pour les identifiants, puis une notice /services/OAIRecord?ark= par image : la licence est lue notice par notice, toute autre valeur refuse l\'image. Vues de Monaco et d\'Espagne refusées. Images en lien direct (service IIIF 1.1 du site), aucune copie.',
            'domaines' => ['rosalis.bibliotheque.toulouse.fr'],
            'lu_le' => '2026-11-23',
        ],
        'pireneas' => [
            'nom' => 'Pireneas, bibliothèque numérique Pau Béarn Pyrénées (BNRP)',
            'statut' => 'incertain',
            'licence' => 'Réutilisation non commerciale libre et gratuite, avec mention de la source ; commerciale payante (licence). Droit d\'auteur échu (« domaine public » dans la notice), mais la reproduction suit la règle de l\'éditeur',
            'accord' => null,
            'preuve' => ['https://www.pireneas.fr/pireneas/fr/content/droits-dutilisation'],
            'citation' => '« La réutilisation non commerciale de ces contenus est libre et gratuite dans le respect de la législation en vigueur et notamment du maintien de la mention de source » ; « La réutilisation commerciale de ces contenus est payante et fait l\'objet d\'une licence. Est entendue par réutilisation commerciale la revente de contenus sous forme de produits élaborés ou de fourniture de service ou toute autre réutilisation des contenus générant directement des revenus. »',
            'obligations' => 'Mention « Source : pireneas.fr / Archives et bibliothèques Pau Béarn Pyrénées » ou de l\'établissement concerné, avec la cote. Usage non commercial seulement : media.commercial_ok = false, masquable par le réglage vff.media.show_non_commercial. Images de la BnF refusées ; les autres partenaires de la BNRP affichent « domaine public » dans la notice.',
            'remarques' => 'Statut incertain : un site gratuit sans publicité ne tire pas de revenu direct, mais le texte ne tranche pas pour un éditeur société (UnxWares SAS) ; à confirmer par courriel à pireneas@agglo-pau.fr (brouillon dans medias-4.md), puis à revoir au passage à l\'association UWOSC. Même moteur et mêmes lectures que Rosalis. Les vues de tramway ne sont pas rattachées (VFF ne décrit pas les tramways urbains).',
            'domaines' => ['pireneas.fr'],
            'lu_le' => '2026-11-23',
        ],
        // Bibliothèque numérique du Limousin et L'Empreinte (agent fonds-images-seuil, 23/11/2026) : vff:import-open-data-media.
        'bnl-limousin' => [
            'nom' => 'Bibliothèque numérique du Limousin (BFM de Limoges)',
            'statut' => 'sur',
            'licence' => 'Par image, lue sur la notice : « Licence ouverte » (dcterms:license) ou « Domaine public » (dcterms:rights) ; les images « Droits réservés » (fonds Boudeau, photographe mort en 1959) sont refusées',
            'accord' => null,
            'preuve' => ['https://bnl-bfm.limoges.fr/api/items?fulltext_search=chemin+de+fer'],
            'citation' => 'Notice 94 : « dcterms:rights : Domaine public », « dcterms:license : Licence ouverte » ; notice 6773 : « dcterms:license : Droits réservés » (refusée).',
            'obligations' => 'Crédit « Auteur, Bibliothèque francophone multimédia, Ville de Limoges (cote), Bibliothèque numérique du Limousin » et lien vers la notice.',
            'remarques' => 'Lu le 23/11/2026 : API publique d\'Omeka S (/api/items), sans clé et sans robots.txt, User-Agent honnête, une requête toutes les 5 s. Les pages HTML du site renvoient un contrôle de navigateur : elles ne sont jamais lues ; le texte des conditions de réutilisation n\'a donc pas pu être lu (la licence repose sur le champ de chaque notice). Images en lien direct (/files/large et /files/medium), aucune copie.',
            'domaines' => ['bnl-bfm.limoges.fr'],
            'lu_le' => '2026-11-23',
        ],
        'lempreinte' => [
            'nom' => 'L\'Empreinte, médiathèques de Valence Romans Agglo (documents du domaine public)',
            'statut' => 'sur',
            'licence' => 'Domaine public pour les documents dont la notice porte « Domaine Public » (dc:rights) ; notices sous « Licence ouverte/Open Licence »',
            'accord' => null,
            'preuve' => ['https://www.lempreinte.valenceromansagglo.fr/mentions-legales.htm'],
            'citation' => '« des reproductions d\'originaux appartenant au domaine public [...] L\'impression, le partage et le téléchargement de ces ressources numériques est libre et gratuit. » ; « Les notices décrivant les documents sont publiées sous la licence « Licence ouverte/Open Licence » ».',
            'obligations' => 'Provenance « Médiathèques Valence Romans Agglo, cote du document » (VFF écrit : établissement, L\'Empreinte, cote, avec lien vers la notice).',
            'remarques' => 'Lu le 23/11/2026 : OAI-PMH oai2.php (ensemble theme:gares-et-chemins-de-fer, 68 notices, quatre requêtes), pas de robots.txt. Réserve : les mentions légales demandent que les pages du site ne soient pas imbriquées (i-frame) ; VFF affiche l\'image par un lien direct de la vignette, avec renvoi vers la notice, sans cadre. Les textes de l\'entrepôt sont de l\'UTF-8 encodé deux fois : réparés à la lecture.',
            'domaines' => ['www.lempreinte.valenceromansagglo.fr'],
            'lu_le' => '2026-11-23',
        ],
        'panoramax' => [
            'nom' => 'Panoramax (photos de rue, instances IGN et OpenStreetMap France)',
            'statut' => 'sur',
            'licence' => 'Par photo, lue sur l\'élément (`properties.license`) : instance IGN, Licence Ouverte 2.0 (etalab-2.0) ; instance OpenStreetMap France, CC BY-SA 4.0',
            'accord' => null,
            'preuve' => [
                'https://panoramax.openstreetmap.fr/api/pages/terms-of-service/fr',
                'https://api.panoramax.xyz/api/instances',
            ],
            'citation' => 'IGN : « La licence de publication des photos ainsi que des métadonnées et tags sémantiques est la Licence Ouverte 2.0. » ; « Ces données personnelles sont automatiquement floutées lors de l\'envoi et préalablement à leur diffusion. » OpenStreetMap France : « sous licence Creative Commons CC-BY-SA 4.0 pour toute diffusion des photos originales ou de photos dérivées ».',
            'obligations' => 'Crédit « Photo : <contributeur>, Panoramax (IGN ou OpenStreetMap France), <licence> » et lien vers la photo sur son instance ; partage à l\'identique pour les photos CC BY-SA. Les fichiers restent chez Panoramax (lien direct vers thumb.jpg et sd.jpg).',
            'remarques' => 'Conditions de l\'instance IGN : panoramax.ign.fr/api/pages/terms-of-service/fr (domaine de l\'IGN, citée ci-dessus). Catalogue fédéré api.panoramax.xyz (API STAC sans clé ; robots.txt : deux robots nommés refusés). Seules les instances IGN et OpenStreetMap France sont retenues (floutage des visages et des plaques à l\'envoi). Conditions de l\'instance OpenStreetMap France lues par l\'agent sources-images le 22/11 (son robots.txt refuse une longue liste de robots nommés : pas de relecture automatique). Tri des photos (règle des abords) : bâtiments BD TOPO (WFS de l\'IGN, Licence Ouverte 2.0), bâtiments voyageurs et quais OpenStreetMap (une requête Overpass, ODbL) et quais SNCF (ODbL), lus pour décider seulement : rien n\'en est publié ni gardé en base.',
            'domaines' => ['panoramax.ign.fr', 'panoramax.openstreetmap.fr', 'api.panoramax.xyz'],
            'lu_le' => '2026-11-22',
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
            // Fiches rédigées à la main (accident_records_manual.php) : la source est le document de presse cité.
            'delpher' => 'delpher',
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
            'sncf-classification' => 'sncf', 'gtfs-voyageurs' => 'sncf', 'sncf-regime' => 'sncf', 'wikipedia-intro' => 'wikipedia', 'wikidata' => 'wikidata',
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
            // Images ouvertes de vff:import-open-data-media (database/data/open_data_media.php), une valeur par jeu.
            'albert-kahn' => 'albert-kahn',
            'ad92-9fi' => 'ad92',
            'suresnes-cp' => 'suresnes',
            'evry-cp' => 'evry-courcouronnes',
            'sevres-cp' => 'sevres',
            'gadagne-cp' => 'gadagne',
            'sl71-cp' => 'saone-et-loire',
            'getty' => 'getty',
            'enpc-ph' => 'enpc',
            'numistral' => 'numistral',
            'rosalis' => 'rosalis',
            'pireneas' => 'pireneas',
            'bnl-limousin' => 'bnl-limousin',
            'lempreinte' => 'lempreinte',
            // Photos de rue des gares (vff:import-panoramax-media), lien direct vers l'instance d'origine.
            'panoramax' => 'panoramax',
        ],
        // Passages à niveau (vff:import-sncf-level-crossings) et signalisation par PK (vff:import-sncf-signalling) :
        // tables sans colonne de provenance, une seule source (SNCF Réseau, ODbL), citée par l'API.
        'sncf_level_crossings' => ['sncf' => 'sncf'],
        // Voies vertes OSM rattachées aux lignes (vff:link-greenways) : way, géométrie et mesure tirés d'OpenStreetMap (ODbL).
        'line_greenways' => ['osm' => 'osm'],
        'line_signalling' => ['sncf' => 'sncf'],
        // Quais des gares (vff:import-sncf-platforms) : jeu « Liste des quais » de SNCF Réseau (ODbL), colonne source = sncf.
        'station_platforms' => ['sncf' => 'sncf'],
        // Voies par portion de PK (vff:import-sncf-track-regime) et radio sol-train (vff:import-sncf-radio) : SNCF Réseau, ODbL.
        'line_track_portions' => ['sncf' => 'sncf'],
        'line_radio_portions' => ['sncf' => 'sncf'],
        // Fréquentation annuelle des gares (vff:import-sncf-attendance) : SNCF Gares & Connexions, ODbL, colonne source = sncf.
        'station_attendances' => ['sncf' => 'sncf'],
        // Accessibilité et services des gares (vff:import-station-accessibility) : SNCF Gares & Connexions et SNCF Voyageurs, ODbL, colonne source = sncf.
        'station_accessibility' => ['sncf' => 'sncf'],
        // Desserte d'un jour type des gares et des lignes (vff:import-station-timetables) : horaires GTFS SNCF et Transilien (ODbL), colonne source = sncf.
        'station_timetables' => ['sncf' => 'sncf'],
        'line_timetables' => ['sncf' => 'sncf'],
        // Gares et ouvrages protégés (vff:import-station-heritage) : base Mérimée du ministère de la Culture (Licence Ouverte 2.0) et Wikidata (CC0).
        'station_heritage' => ['merimee' => 'pop', 'wikidata' => 'wikidata'],
        // Mises en service de signalisation datées (vff:import-signalling-history) : faits relevés dans les articles de ligne de Wikipédia (CC BY-SA 4.0).
        // La portion placée (section_geom) est tracée sur les formes SNCF Réseau (ODbL), comme line_signalling_map ; la carte par ère
        // (line_signalling_history_map) reprend ces faits sur le tracé VFF de chaque ère.
        // `manual` (vff:import-signalling-sources, database/data/signalling_history_sources.php) : faits datés par d'autres
        // documents (SNCF Réseau, BEA-TT, EPSF, Journal officiel, presse), voir composites.
        'line_signalling_events' => ['wikipedia' => 'wikipedia'],
        // Profils en long (vff:compute-line-profiles) : altitude de la voie BD TOPO, à défaut RGE ALTI (line_profiles.source).
        'line_profiles' => ['bdtopo-rge-alti' => 'ign'],
        // Points du profil (line_profile_points.source) : altitude de la voie (BD TOPO), du terrain (RGE ALTI) ou de la voie
        // estimée d'après le terrain (RGE ALTI, option --estimate-track).
        'line_profile_points' => ['bdtopo' => 'ign', 'rge_alti' => 'ign', 'rge_track' => 'ign'],
        // Ouvrages d'art (vff:import-structures) : tracé et longueur de la BD TOPO (voies en tunnel ou sur pont, constructions),
        // nom, dates, protection et image de Wikidata (CC0, image Commons sous sa propre licence) ; structures.sources[].source.
        // `merimee` : la référence Mérimée vient de Wikidata (P380), la notice de la POP n'est qu'un lien.
        'structures' => ['bdtopo' => 'ign', 'bdtopo-construction' => 'ign', 'wikidata' => 'wikidata', 'wikipedia' => 'wikipedia', 'merimee' => 'wikidata'],
        // Documents d'archives de Gallica rattachés aux lignes (vff:import-gallica) : métadonnées des notices de la BnF
        // (Licence Ouverte) et lien vers Gallica, jamais l'image ni le texte numérisés.
        'line_archive_documents' => ['gallica' => 'gallica'],
        // Bibliographie des lignes et des gares (vff:import-bibliography) : notices du catalogue général de la BnF (provider `gallica` :
        // métadonnées sous Licence Ouverte, lien vers la notice), références des articles de Wikipédia (CC BY-SA 4.0, lien vers la
        // révision lue) et éléments de Wikidata (CC0). Jamais le contenu ni une page d'un ouvrage.
        'bibliography_entries' => ['bnf' => 'gallica', 'wikipedia' => 'wikipedia', 'wikidata' => 'wikidata'],
        'bibliography_links' => ['bnf' => 'gallica', 'wikipedia' => 'wikipedia', 'wikidata' => 'wikidata'],
        // Notices des Archives SNCF rattachées aux lignes (vff:import-sncf-archives) : métadonnées du jeu « archives-sncf-sardo » (ODbL) et lien.
        'line_archive_records' => ['sncf-archives' => 'sncf-archives'],
        // Contexte sourcé des accidents (vff:enrich-accidents, database/data/accident_contexts/*.php) :
        // accident_references.provider. Énoncés rédigés par VFF, faits seulement avec lien ; une courte citation
        // exacte au plus par fiche, et seulement d'un rapport du BEA-TT ou d'un texte du Journal officiel (Licence
        // Ouverte). Presse, ouvrages, sites d'histoire locale, débats parlementaires : faits seulement, lien.
        // wikipedia-it : même licence que les autres éditions (CC BY-SA 4.0), faits seulement.
        'accident_references' => [
            'bea-tt' => 'bea-tt', 'legifrance' => 'jorf', 'gallica' => 'gallica', 'commons' => 'commons',
            'wikipedia' => 'wikipedia', 'wikipedia-en' => 'wikipedia-en', 'wikipedia-de' => 'wikipedia-de', 'wikipedia-it' => 'wikipedia',
            'wikidata' => 'wikidata', 'ouvrages' => 'tiers-cites',
            'retronews' => 'retronews', 'assemblee-nationale' => 'assemblee-nationale', 'senat' => 'senat',
            'archives-nationales' => 'archives-nationales', 'francearchives' => 'francearchives',
            'archives-departementales' => 'archives-departementales', 'persee' => 'persee', 'europeana' => 'europeana', 'ina' => 'ina',
            'letempsarchives' => 'letempsarchives',
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
        // Jeux de données ouverts (vff:export-open-data) : provenance de chaque champ d'une gare (stations.provenance,
        // clés name, geom, opened_at, closed_at, et stations.position_source). `vff:eras` : fermeture déduite par VFF
        // de l'état des lignes de chaque ère ; `correction` : correction sourcée de VFF (fournisseur par défaut).
        'stations.fields' => [
            'sncf' => 'sncf', 'sncf_voyageurs' => 'sncf', 'osm' => 'osm', 'osm:note' => 'osm', 'ohm' => 'ohm',
            'wikidata' => 'wikidata', 'wikidata:qualifiers' => 'wikidata', 'communes' => 'communes',
            'wikipedia' => 'wikipedia', 'wikipedia_schema' => 'wikipedia', 'lignes-oubliees' => 'lignes-oubliees',
            'argentanwebferro' => 'argentanwebferro', 'correction' => 'vff', 'inferred:line_event' => 'vff',
            'inferred:trace' => 'vff', 'vff:eras' => 'vff',
            // Position recalée sur la voie d'une source (StationPositioning) : la source de la voie.
            'recalage:osm' => 'osm', 'recalage:ohm' => 'ohm', 'recalage:sncf' => 'sncf', 'recalage:wikidata' => 'wikidata',
        ],
        // Provenance des champs d'un accident (accidents.provenance, location_source, toll_source) : `context` et `manual`
        // sont les fiches et contextes rédigés par VFF (faits seulement), `commune` le centre de la commune (API
        // Découpage administratif), `station` la position de la gare la plus proche (sa propre provenance est relue
        // par l'export), `sncf-pn` un passage à niveau de SNCF Réseau.
        'accidents.fields' => [
            'wikipedia' => 'wikipedia', 'wikipedia-en' => 'wikipedia-en', 'wikipedia-de' => 'wikipedia-de',
            'bea-tt' => 'bea-tt', 'wikidata' => 'wikidata', 'vff' => 'vff', 'context' => 'vff', 'manual' => 'vff',
            'commune' => 'communes', 'station' => 'vff', 'sncf-pn' => 'sncf',
        ],
        // Provenance de l'écartement d'une portion (line_segments.gauge_source, vff:enrich-era-segments) : `archeo` est
        // l'écartement lu dans le calque du tracé d'origine ; event, curated, line et network sont déduits par VFF.
        'line_segments.gauge_source' => [
            'wikidata' => 'wikidata', 'osm' => 'osm', 'osm_hist' => 'osm', 'sncf' => 'sncf', 'bdtopo' => 'ign', 'ohm' => 'ohm',
            'archeo' => 'archeoferroviaire', 'event' => 'vff', 'curated' => 'vff', 'line' => 'vff', 'network' => 'vff',
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
        'line_signalling_events' => [
            // Mises en service datées par des documents, relevées à la main (database/data/signalling_history_sources.php) :
            // le lien du premier document décide (Légifrance, BEA-TT, Wikipédia…) ; VFF à défaut (communiqués de SNCF Réseau,
            // presse). Faits seulement : date, système, portion, résumé rédigé par VFF, lien ; aucun texte recopié.
            'manual' => 'vff',
        ],
        'accident_references' => [
            // Presse et sites web cités par le contexte des accidents : le lien cité décide (Delpher, Deutsches
            // Zeitungsportal, Victimes du rail, Gallica…) ; sites tiers cités ponctuellement à défaut.
            'presse' => 'tiers-cites',
            'sites-web' => 'tiers-cites',
            // Presse numérisée de Delpher (Koninklijke Bibliotheek) : crédit « Delpher, Koninklijke Bibliotheek ».
            'delpher' => 'delpher',
        ],
    ],
];
