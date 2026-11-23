<?php

/**
 * Projets ferroviaires français pas encore en service : catalogue de la page `/projects` et de `GET /api/v1/projects`
 * (lu par App\Services\RailProjects). Faits seulement, chaque étape datée avec sa source (officielle d'abord, presse
 * régionale ensuite, Wikipédia pour recouper), vérifiés le 23/11/2026.
 *
 * Champs : id ; name ; kind = reopening | new_line | lgv | rer | electrification ; regions ; porteurs (name, role) ;
 * line_slugs (fiches de ligne VFF, ignorées si elles n'existent pas) ; suspended (true : suspendu, reporté sans date ou
 * abandonné) ; summary (français, neutre) ; stages : kind = study | debate | dup | funding | works | service |
 * commissioning, date (YYYY, YYYY-MM ou YYYY-MM-DD, à la précision de la source), done (true = franchie ; false = date
 * PRÉVUE, jamais certaine), note, source (label, url, published) ; checked (date de vérification).
 * `service` : service voyageurs ouvert sur une partie du projet ou déjà ouvert puis arrêté ; `commissioning` n'a que des
 * dates prévues (un projet mis en service sort du catalogue).
 * Les doutes relevés à la vérification (sources en désaccord, page inaccessible) sont en commentaire de chaque projet.
 *
 * @return list<array<string, mixed>>
 */
return [
    [
        // Doutes : Divergence sur les dates : l'objectif officiel des financeurs est 2032 (Bordeaux-Toulouse) et 2034
        // (Bordeaux-Dax), alors que le COI (avril 2026) juge réaliste 2035 pour Bordeaux-Toulouse et la presse cite 2042
        // pour Bordeaux-Dax, dates contestées par les collectivités. Le ministre des Transports a déclaré le 15/04/2026
        // ne prendre aucun engagement de date ; le Premier ministre a qualifié le projet d'irréversible le 07/05/2026
        // pour Bordeaux-Toulouse seulement (Bordeaux-Dax non confirmé dans la même communication, selon une question
        // sénatoriale). Phase 2 Dax-Espagne : pas de DUP. La DUP des lignes nouvelles arrive à échéance en 2031 (COI).
        // Une demande d'abrogation du décret de 2016 a fait l'objet d'une décision du Conseil d'État du 02/03/2026 dont
        // l'issue n'a pas été vérifiée. Le début du génie civil des lignes nouvelles n'est pas daté de façon sourcée.
        // L'avis d'appel d'offres effectif (BOAMP/JOUE) n'a pas été vérifié. Aucune étape de débat public n'est datée
        // ici faute de source lue.
        'id' => 'gpso-bordeaux-toulouse-dax',
        'name' => 'Lignes nouvelles du Sud-Ouest : Bordeaux - Toulouse et Bordeaux - Dax (GPSO / LNSO)',
        'kind' => 'lgv',
        'regions' => ['Nouvelle-Aquitaine', 'Occitanie'],
        'porteurs' => [
            ['name' => 'SNCF Réseau', 'role' => 'maître d\'ouvrage'],
            ['name' => 'Société du Grand Projet du Sud-Ouest (SGPSO)', 'role' => 'financeur'],
            ['name' => 'État', 'role' => 'financeur'],
            ['name' => 'Région Nouvelle-Aquitaine', 'role' => 'financeur'],
            ['name' => 'Région Occitanie', 'role' => 'financeur'],
        ],
        'line_slugs' => [],
        'summary' => 'Projet de 327 km de lignes nouvelles à grande vitesse vers Toulouse et Dax, avec deux gares nouvelles (Agen, Montauban) et une gare à Mont-de-Marsan, précédé d\'aménagements capacitaires sur les lignes existantes autour de Bordeaux et de Toulouse. Seuls les aménagements AFSB et AFNT sont en travaux ; les lignes nouvelles sont au stade de la préparation des appels d\'offres de conception-réalisation.',
        'stages' => [
            ['kind' => 'dup', 'date' => '2015-11-25', 'done' => true, 'note' => 'arrêté préfectoral de DUP des aménagements ferroviaires au sud de Bordeaux (AFSB)', 'source' => ['label' => 'Ministère de la Transition écologique, page projets de lignes à grande vitesse (mise à jour du 25/10/2023)', 'url' => 'https://www.ecologie.gouv.fr/projets-lignes-grande-vitesse-en-france', 'published' => '2023-10-25']],
            ['kind' => 'dup', 'date' => '2016-01-04', 'done' => true, 'note' => 'arrêté préfectoral de DUP des aménagements ferroviaires au nord de Toulouse (AFNT)', 'source' => ['label' => 'Ministère de la Transition écologique, page projets de lignes à grande vitesse (mise à jour du 25/10/2023)', 'url' => 'https://www.ecologie.gouv.fr/projets-lignes-grande-vitesse-en-france', 'published' => '2023-10-25']],
            ['kind' => 'dup', 'date' => '2016-06-02', 'done' => true, 'note' => 'décret n° 2016-738 déclarant d\'utilité publique et urgents les travaux des lignes Bordeaux-Toulouse et Bordeaux-Dax (JORF du 5 juin 2016)', 'source' => ['label' => 'Légifrance, décret n° 2016-738 du 2 juin 2016', 'url' => 'https://www.legifrance.gouv.fr/jorf/id/JORFTEXT000032630048', 'published' => '2016-06-05']],
            ['kind' => 'funding', 'date' => '2022', 'done' => true, 'note' => 'plan de financement signé (40 % État, 40 % collectivités, 20 % Union européenne) et création de la Société du GPSO', 'source' => ['label' => 'Conseil d\'orientation des infrastructures, rapport Grands projets : le temps des choix (3 avril 2026)', 'url' => 'https://www.ecologie.gouv.fr/sites/default/files/documents/260403_Rapport_COI_2026-Grands_projets-le_temps_des_choix-2_0.pdf', 'published' => '2026-04-03']],
            ['kind' => 'works', 'date' => '2024-05-07', 'done' => true, 'note' => 'lancement des travaux des aménagements ferroviaires au nord de Toulouse (autorisation environnementale du 9 février 2024)', 'source' => ['label' => 'Préfecture d\'Occitanie, lancement du chantier AFNT', 'url' => 'https://www.prefectures-regions.gouv.fr/occitanie/Actualites/GPSO-lancement-du-chantier-des-Amenagements-Ferroviaires-au-Nord-Toulouse-AFNT']],
            ['kind' => 'funding', 'date' => '2026-05-07', 'done' => true, 'note' => 'protocole d\'accord signé à Toulouse : 820 M€ engagés en 2026 et 2027 à parité État et collectivités ; engagement de lancer les procédures de marché avant fin 2026', 'source' => ['label' => 'Le Journal des Entreprises, LNSO : l\'État et les collectivités scellent un accord', 'url' => 'https://www.lejournaldesentreprises.com/article/ligne-nouvelle-du-sud-ouest-letat-et-les-collectivites-scellent-un-accord-cle-2142511', 'published' => '2026-05-07']],
            ['kind' => 'funding', 'date' => '2026-06', 'done' => true, 'note' => 'courrier du Premier ministre aux présidents des deux régions : abandon du partenariat public-privé au profit d\'un marché de conception-réalisation à financement public', 'source' => ['label' => 'Info.fr, LGV Bordeaux-Toulouse : l\'État renonce au PPP', 'url' => 'https://info.fr/lgv-bordeaux-toulouse-etat-renonce-ppp-confirme-financement-public/', 'published' => '2026-06-24']],
            ['kind' => 'works', 'date' => '2026-11-05', 'done' => true, 'note' => 'le conseil d\'administration de SNCF Réseau valide le lancement de deux appels d\'offres de conception-réalisation Bordeaux-Toulouse (Corea Ouest et Corea Est) ; avis de candidature annoncés d\'ici fin 2026', 'source' => ['label' => 'Batiweb, LGV Bordeaux-Toulouse : SNCF Réseau valide les appels d\'offres', 'url' => 'https://www.batiweb.com/actualites/construction/lgv-bordeaux-toulouse-sncf-reseau-appels-offres-49577', 'published' => '2026-11-06']],
            ['kind' => 'commissioning', 'date' => '2032', 'done' => false, 'note' => 'mise en service prévue de Bordeaux-Toulouse « au plus tôt » en 2032 selon SNCF Réseau', 'source' => ['label' => 'Batiweb, LGV Bordeaux-Toulouse : SNCF Réseau valide les appels d\'offres', 'url' => 'https://www.batiweb.com/actualites/construction/lgv-bordeaux-toulouse-sncf-reseau-appels-offres-49577', 'published' => '2026-11-06']],
            ['kind' => 'commissioning', 'date' => '2034', 'done' => false, 'note' => 'mise en service prévue de Bordeaux-Dax (2032 et 2034 : dates initiales rappelées par la presse)', 'source' => ['label' => 'Banque des Territoires (Localtis), LGV Bordeaux-Toulouse : Lecornu affirme le caractère irréversible', 'url' => 'https://www.banquedesterritoires.fr/ferroviaire-sebastien-lecornu-affirme-le-caractere-irreversible-de-la-lgv-bordeaux-toulouse', 'published' => '2026-05-07']],
        ],
        'checked' => '2026-11-23',
    ],
    [
        // Doutes : Divergence de dates : l'échéance officielle est fin 2033 (États, TELT, 26/06/2026) ; le COI (avril
        // 2026) estime qu'une mise en service en 2035 paraît beaucoup plus probable ; la Cour des comptes européenne
        // (19/01/2026) retient 2033 et chiffre le retard à 18 ans par rapport au calendrier initial. La page du
        // ministère de 2017 annonçait 2029 (périmée). Projet binational : la fiche couvre la section transfrontalière
        // et, côté français, la section d'accès ; le COI indique que les accès neufs (Grenay - Chambéry, Avressieux -
        // Saint-Jean-de-Maurienne) n'ont pas de décision de réalisation, que ses membres sont divisés à leur sujet et
        // qu'une prorogation de la DUP de 2013 devait être soumise au Conseil d'État à l'été 2026 (issue non vérifiée).
        // Les dates précises de début des travaux définitifs et de la loi de ratification de l'accord de 2015 n'ont pas
        // été sourcées.
        'id' => 'lyon-turin',
        'name' => 'Liaison ferroviaire Lyon - Turin (tunnel de base du Mont-Cenis et accès)',
        'kind' => 'new_line',
        'regions' => ['Auvergne-Rhône-Alpes'],
        'porteurs' => [
            ['name' => 'TELT (Tunnel Euralpin Lyon Turin), détenue à parité par la France et l\'Italie', 'role' => 'maître d\'ouvrage'],
            ['name' => 'État français', 'role' => 'financeur'],
            ['name' => 'État italien', 'role' => 'financeur'],
            ['name' => 'Union européenne', 'role' => 'financeur'],
            ['name' => 'SNCF Réseau', 'role' => 'maître d\'ouvrage'],
        ],
        'line_slugs' => ['rfn-900000'],
        'summary' => 'Projet binational franco-italien dont la section transfrontalière comprend un tunnel de base bitube de 57,5 km (164 km de galeries au total) et deux gares internationales (Saint-Jean-de-Maurienne et Suse). Le chantier est en cours ; les lignes nouvelles d\'accès côté français ne sont pas engagées.',
        'stages' => [
            ['kind' => 'dup', 'date' => '2007-12-18', 'done' => true, 'note' => 'déclaration d\'utilité publique de la section transfrontalière côté français', 'source' => ['label' => 'Ministère de la Transition écologique, Grand projet liaison ferroviaire Lyon-Turin (mis à jour le 07/02/2017)', 'url' => 'https://www.ecologie.gouv.fr/grand-projet-liaison-ferroviaire-lyon-turin', 'published' => '2017-01-23']],
            ['kind' => 'funding', 'date' => '2012-01-30', 'done' => true, 'note' => 'accord franco-italien de Rome (gouvernance, clé de financement, droit applicable)', 'source' => ['label' => 'Ministère de la Transition écologique, Grand projet liaison ferroviaire Lyon-Turin', 'url' => 'https://www.ecologie.gouv.fr/grand-projet-liaison-ferroviaire-lyon-turin', 'published' => '2017-01-23']],
            ['kind' => 'dup', 'date' => '2013-08-25', 'done' => true, 'note' => 'décret publié : DUP des deux premières phases des accès français (Grenay - Chambéry, Avressieux - Saint-Jean-de-Maurienne) ; échéance en août 2028, prorogation à demander', 'source' => ['label' => 'Ministère de la Transition écologique, Grand projet liaison ferroviaire Lyon-Turin', 'url' => 'https://www.ecologie.gouv.fr/grand-projet-liaison-ferroviaire-lyon-turin', 'published' => '2017-01-23']],
            ['kind' => 'funding', 'date' => '2015-02-24', 'done' => true, 'note' => 'accord de Paris pour l\'engagement des travaux définitifs de la section transfrontalière', 'source' => ['label' => 'Conseil d\'orientation des infrastructures, rapport d\'avril 2026', 'url' => 'https://www.ecologie.gouv.fr/sites/default/files/documents/260403_Rapport_COI_2026-Grands_projets-le_temps_des_choix-2_0.pdf', 'published' => '2026-04-03']],
            ['kind' => 'works', 'date' => '2026-11', 'done' => true, 'note' => 'chantier en cours : 50,2 km de galeries creusées sur 164 km (30,7 %)', 'source' => ['label' => 'TELT, site officiel', 'url' => 'https://www.telt.eu/fr/', 'published' => '2026-11-10']],
            ['kind' => 'commissioning', 'date' => '2033', 'done' => false, 'note' => 'mise en service de la section transfrontalière annoncée « fin 2033 » par les États (avec TELT)', 'source' => ['label' => 'Ministère de la Transition écologique, communiqué sur le 36e sommet franco-italien', 'url' => 'https://www.ecologie.gouv.fr/presse/36eme-sommet-franco-italien-philippe-tabarot-annonce-plusieurs-avancees-transports', 'published' => '2026-06-26']],
        ],
        'checked' => '2026-11-23',
    ],
    [
        // Doutes : Le plan de financement complet de la phase 2 n'est pas arrêté : le COI (avril 2026) demande de
        // l'arrêter et signale que 20 % (920 M EUR de 2025) sont attendus de l'Union européenne au titre du MIE
        // 2028-2034 ; deux conventions de financement complémentaires de la phase 1 restent à mettre en place. Des
        // sources divergent sur le début de la phase 2 (travaux visés dès 2026 par le maître d'ouvrage selon le COI,
        // 2027-2028 selon la presse du 16/12/2025). Les phases 3 et 4 (lignes nouvelles) sont sans calendrier. Le tunnel
        // et la gare souterraine de Marseille Saint-Charles sont intégrés à la phase 2 de la LNPCA et non à un projet
        // séparé. La page de la préfecture sur le financement n'a pas pu être lue (accès refusé).
        'id' => 'lnpca',
        'name' => 'Ligne nouvelle Provence - Côte d\'Azur (LNPCA), phases 1 et 2',
        'kind' => 'new_line',
        'regions' => ['Provence-Alpes-Côte d\'Azur'],
        'porteurs' => [
            ['name' => 'SNCF Réseau', 'role' => 'maître d\'ouvrage'],
            ['name' => 'SNCF Gares & Connexions', 'role' => 'maître d\'ouvrage'],
            ['name' => 'Région Sud Provence-Alpes-Côte d\'Azur', 'role' => 'financeur'],
            ['name' => 'État', 'role' => 'financeur'],
            ['name' => 'Société LNPCA', 'role' => 'financeur'],
        ],
        'line_slugs' => [],
        'summary' => 'Les phases 1 et 2 (28 opérations, estimées à 3,644 Md EUR HT aux conditions de juillet 2020) forment un ensemble indissociable qui doit désaturer les nœuds de Marseille, Toulon et Nice. Les travaux de la phase 1 ont été lancés en décembre 2025 ; la phase 2 inclut la gare souterraine et le tunnel de Marseille.',
        'stages' => [
            ['kind' => 'debate', 'date' => '2005', 'done' => true, 'note' => 'débat public CNDP (projet de LGV PACA)', 'source' => ['label' => 'CNDP, rapport 2025 du garant de la concertation continue LNPCA (12/03/2026)', 'url' => 'https://www.debatpublic.fr/sites/default/files/2026-03/LNPCA%20RAPPORT%202025%20%20DU%20GARANT%20Vdef%20120326.pdf', 'published' => '2026-03-12']],
            ['kind' => 'dup', 'date' => '2022-01-17', 'done' => true, 'note' => 'enquête publique unique, du 17 janvier au 28 février 2022 ; avis favorable de la commission le 22 avril 2022', 'source' => ['label' => 'CNDP, rapport 2025 du garant de la concertation continue LNPCA (12/03/2026)', 'url' => 'https://www.debatpublic.fr/sites/default/files/2026-03/LNPCA%20RAPPORT%202025%20%20DU%20GARANT%20Vdef%20120326.pdf', 'published' => '2026-03-12']],
            ['kind' => 'dup', 'date' => '2022-10-13', 'done' => true, 'note' => 'arrêté inter-préfectoral déclarant d\'utilité publique les phases 1 et 2 ; recours rejetés par le tribunal administratif de Marseille le 18 juillet 2025', 'source' => ['label' => 'Le Monde du Droit, LNPCA : confirmation de l\'utilité publique', 'url' => 'https://www.lemondedudroit.fr/public/284-environnement/100222-lnpca-confirmation-de-l-utilite-publique-du-projet.html', 'published' => '2025-07-29']],
            ['kind' => 'funding', 'date' => '2024-12-20', 'done' => true, 'note' => 'comité de pilotage : engagements de l\'État et des collectivités pour financer les opérations de la phase 1', 'source' => ['label' => 'Préfecture de région PACA, financement des phases 1 et 2 (cité par résultat de recherche)', 'url' => 'https://www.prefectures-regions.gouv.fr/provence-alpes-cote-dazur/Grands-dossiers/Ligne-Nouvelle-Provence-Cote-d-Azur-financement-de-la-phase-1-et-2']],
            ['kind' => 'works', 'date' => '2025-12-16', 'done' => true, 'note' => 'lancement officiel des travaux de la phase 1 en gare de Marseille Saint-Charles', 'source' => ['label' => 'TourMaG, travaux de la LNPCA officiellement lancés', 'url' => 'https://www.tourmag.com/Marseille-les-travaux-de-la-Ligne-Nouvelle-Provence-Cote-d-Azur-LNPCA-officiellement-lances_a129824.html', 'published' => '2025-12-16']],
            ['kind' => 'commissioning', 'date' => '2030', 'done' => false, 'note' => 'phase 1 : travaux jusqu\'en 2030, mise en service progressive', 'source' => ['label' => 'TourMaG, travaux de la LNPCA officiellement lancés', 'url' => 'https://www.tourmag.com/Marseille-les-travaux-de-la-Ligne-Nouvelle-Provence-Cote-d-Azur-LNPCA-officiellement-lances_a129824.html', 'published' => '2025-12-16']],
            ['kind' => 'commissioning', 'date' => '2035', 'done' => false, 'note' => 'phases 1 et 2 achevées à partir de 2035 (gare souterraine de Marseille), selon le garant et le COI', 'source' => ['label' => 'Conseil d\'orientation des infrastructures, rapport d\'avril 2026', 'url' => 'https://www.ecologie.gouv.fr/sites/default/files/documents/260403_Rapport_COI_2026-Grands_projets-le_temps_des_choix-2_0.pdf', 'published' => '2026-04-03']],
        ],
        'checked' => '2026-11-23',
    ],
    [
        // Doutes : Dates de mise en service de la phase 1 divergentes : la presse de 2022 citait l'horizon 2030, un
        // communiqué préfectoral de juillet 2025 (cité par une recherche, non lu) évoquait 2034 ; le COI ne donne que
        // 2045 pour le projet global. La convention de financement de la phase 1 devait être signée début 2026 selon un
        // communiqué de décembre 2025 (non lu) ; sa signature n'a pas été confirmée, et le COI indique qu'il reste 2 037
        // M EUR (de 2025) à contractualiser. La phase 2 (fonctionnalités, mixité fret, gares nouvelles, tracé) n'est pas
        // stabilisée ; elle n'a pas de DUP. Le calendrier de l'appel d'offres a évolué (septembre 2026 annoncé en
        // décembre 2025, octobre 2026 après le comité de pilotage de juillet 2026). Les pages de la préfecture n'ont pas
        // pu être lues (accès refusé) ; l'étape de juillet 2026 repose sur un résultat de recherche citant le
        // communiqué.
        'id' => 'lnmp',
        'name' => 'Ligne nouvelle Montpellier - Perpignan (LNMP)',
        'kind' => 'new_line',
        'regions' => ['Occitanie'],
        'porteurs' => [
            ['name' => 'SNCF Réseau', 'role' => 'maître d\'ouvrage'],
            ['name' => 'Société de la ligne nouvelle Montpellier Perpignan (SLNMP)', 'role' => 'financeur'],
            ['name' => 'Région Occitanie', 'role' => 'financeur'],
            ['name' => 'État', 'role' => 'financeur'],
        ],
        'line_slugs' => [],
        'summary' => 'Ligne nouvelle en deux phases. La phase 1 Montpellier - Béziers est déclarée d\'utilité publique et financée en partie par protocole ; l\'appel à candidatures de conception-réalisation a été lancé en juillet 2026. La phase 2 Béziers - Perpignan a fait l\'objet d\'une concertation préalable du 9 avril au 19 juin 2026.',
        'stages' => [
            ['kind' => 'dup', 'date' => '2021-12-14', 'done' => true, 'note' => 'enquête publique de la phase 1 (14 décembre 2021 au 27 janvier 2022)', 'source' => ['label' => 'Banque des Territoires (Localtis), accords de financement LNMP (24/01/2022)', 'url' => 'https://www.banquedesterritoires.fr/occitanie-accords-de-financement-sur-le-premier-troncon-de-la-lgv-montpellier-perpignan-et-sur-la', 'published' => '2022-01-24']],
            ['kind' => 'funding', 'date' => '2022-01-22', 'done' => true, 'note' => 'protocole d\'intention de financement signé à Narbonne : 40 % État, 40 % collectivités (816 M EUR), 20 % Union européenne attendus', 'source' => ['label' => 'Banque des Territoires (Localtis), accords de financement LNMP (24/01/2022)', 'url' => 'https://www.banquedesterritoires.fr/occitanie-accords-de-financement-sur-le-premier-troncon-de-la-lgv-montpellier-perpignan-et-sur-la', 'published' => '2022-01-24']],
            ['kind' => 'dup', 'date' => '2023-02-16', 'done' => true, 'note' => 'décret n° 2023-111 déclarant d\'utilité publique et urgents les travaux de la phase 1 Montpellier - Béziers', 'source' => ['label' => 'Ministère de la Transition écologique, LNMP : travaux déclarés d\'utilité publique (21/02/2023)', 'url' => 'https://www.ecologie.gouv.fr/ligne-ferroviaire-nouvelle-montpellier-perpignan-des-travaux-declares-dutilite-publique-avancee', 'published' => '2023-02-21']],
            ['kind' => 'debate', 'date' => '2026-04-09', 'done' => true, 'note' => 'concertation préalable de la phase 2 Béziers - Perpignan du 9 avril au 19 juin 2026 (décision CNDP du 4 février 2026)', 'source' => ['label' => 'CNDP, décision n° 2026/17/LNMP Phase 2/12 du 4 février 2026', 'url' => 'https://www.debatpublic.fr/sites/default/files/2026-02/DECISION_2026_17_LNMP_PHASE_2_17%20Sign%C3%A9%20MP.pdf', 'published' => '2026-02-05']],
            ['kind' => 'funding', 'date' => '2026-07-06', 'done' => true, 'note' => 'comité de pilotage : lancement de l\'appel à candidatures du marché de conception-réalisation de la phase 1 (appel d\'offres visé en octobre 2026)', 'source' => ['label' => 'Préfecture d\'Occitanie, appel à candidatures LNMP Montpellier-Béziers (cité par résultat de recherche)', 'url' => 'https://www.prefectures-regions.gouv.fr/occitanie/Actualites/L-appel-a-candidatures-des-entreprises-est-lance-pour-le-troncon-Montpellier-Beziers']],
            ['kind' => 'works', 'date' => '2029', 'done' => false, 'note' => 'début des travaux de la phase 1 visé en 2029 (raccordements au réseau existant), 2030 pour les travaux principaux', 'source' => ['label' => 'Conseil d\'orientation des infrastructures, rapport d\'avril 2026', 'url' => 'https://www.ecologie.gouv.fr/sites/default/files/documents/260403_Rapport_COI_2026-Grands_projets-le_temps_des_choix-2_0.pdf', 'published' => '2026-04-03']],
            ['kind' => 'commissioning', 'date' => '2045', 'done' => false, 'note' => 'mise en service du projet global (phases 1 et 2) évaluée en 2045 dans les études socio-économiques', 'source' => ['label' => 'Conseil d\'orientation des infrastructures, rapport d\'avril 2026', 'url' => 'https://www.ecologie.gouv.fr/sites/default/files/documents/260403_Rapport_COI_2026-Grands_projets-le_temps_des_choix-2_0.pdf', 'published' => '2026-04-03']],
        ],
        'checked' => '2026-11-23',
    ],
    [
        // Doutes : Aucune information trouvée sur la tenue ou le résultat du comité de pilotage de l'automne 2026 : le
        // périmètre du projet refondé n'est pas confirmé au 23/11/2026. La concertation a été suspendue en 2024 (COI) ;
        // un accord entre les deux régions sur le maintien de l'arrêt des trains normands à Mantes a été trouvé le 6
        // novembre 2025 (COI). Un doute subsiste sur la place des sections vers Caen et l'Eure dans le périmètre refondé
        // (la feuille de route recentre sur l'axe Paris - Mantes - Rouen - Le Havre). Les dates d'enquête publique, de
        // DUP et de mise en service sont des hypothèses du COI, non des décisions. Aucune société de financement dédiée
        // n'a été trouvée.
        'id' => 'lnpn',
        'name' => 'Ligne nouvelle Paris - Normandie (LNPN)',
        'kind' => 'new_line',
        'regions' => ['Île-de-France', 'Normandie'],
        'porteurs' => [
            ['name' => 'SNCF Réseau', 'role' => 'maître d\'ouvrage'],
            ['name' => 'Délégué interministériel au développement de la vallée de la Seine', 'role' => 'porteur du projet'],
            ['name' => 'Région Normandie', 'role' => 'financeur'],
            ['name' => 'Région Île-de-France', 'role' => 'financeur'],
        ],
        'line_slugs' => [],
        'summary' => 'Projet de ligne nouvelle sur l\'axe Paris - Mantes - Rouen - Le Havre / Caen, phasé par une décision ministérielle de 2020. Il est en cours de refonte : une feuille de route a été validée le 9 janvier 2026 et le périmètre du projet refondé devait être décidé à l\'automne 2026.',
        'stages' => [
            ['kind' => 'debate', 'date' => '2011', 'done' => true, 'note' => 'débat public CNDP', 'source' => ['label' => 'Conseil d\'orientation des infrastructures, rapport d\'avril 2026', 'url' => 'https://www.ecologie.gouv.fr/sites/default/files/documents/260403_Rapport_COI_2026-Grands_projets-le_temps_des_choix-2_0.pdf', 'published' => '2026-04-03']],
            ['kind' => 'study', 'date' => '2020', 'done' => true, 'note' => 'décision ministérielle phasant le projet et demandant la préparation de l\'enquête publique des sections prioritaires', 'source' => ['label' => 'Conseil d\'orientation des infrastructures, rapport d\'avril 2026', 'url' => 'https://www.ecologie.gouv.fr/sites/default/files/documents/260403_Rapport_COI_2026-Grands_projets-le_temps_des_choix-2_0.pdf', 'published' => '2026-04-03']],
            ['kind' => 'study', 'date' => '2026-01-09', 'done' => true, 'note' => 'comité de pilotage : validation d\'une feuille de route de refondation du projet', 'source' => ['label' => 'Ministère de la Transition écologique, communiqué LNPN : feuille de route', 'url' => 'https://www.ecologie.gouv.fr/presse/projet-ligne-nouvelle-paris-normandie-lnpn-validation-feuille-route-aboutir-projet-refonde', 'published' => '2026-01-09']],
            ['kind' => 'study', 'date' => '2026', 'done' => false, 'note' => 'nouveau comité de pilotage prévu à l\'automne 2026 pour décider des composantes et du périmètre du projet refondé', 'source' => ['label' => 'Ministère de la Transition écologique, communiqué LNPN : feuille de route', 'url' => 'https://www.ecologie.gouv.fr/presse/projet-ligne-nouvelle-paris-normandie-lnpn-validation-feuille-route-aboutir-projet-refonde', 'published' => '2026-01-09']],
            ['kind' => 'dup', 'date' => '2029', 'done' => false, 'note' => 'enquête publique puis DUP envisagées vers 2029-2030 (hypothèse du COI)', 'source' => ['label' => 'Conseil d\'orientation des infrastructures, rapport d\'avril 2026', 'url' => 'https://www.ecologie.gouv.fr/sites/default/files/documents/260403_Rapport_COI_2026-Grands_projets-le_temps_des_choix-2_0.pdf', 'published' => '2026-04-03']],
            ['kind' => 'commissioning', 'date' => '2040', 'done' => false, 'note' => 'mise en service vers 2040 des sections de priorité 1 (Paris - Mantes et Rouen - Barentin), après EOLE (2030) et l\'optimisation de Saint-Lazare (2034 au plus tôt)', 'source' => ['label' => 'Conseil d\'orientation des infrastructures, rapport d\'avril 2026', 'url' => 'https://www.ecologie.gouv.fr/sites/default/files/documents/260403_Rapport_COI_2026-Grands_projets-le_temps_des_choix-2_0.pdf', 'published' => '2026-04-03']],
        ],
        'checked' => '2026-11-23',
    ],
    [
        // Doutes : Aucune DUP ni enquête publique n'est engagée. Le COI (avril 2026) recommande de prioriser la
        // modernisation des axes existants et d'examiner, au rendu des études, l'opportunité de poursuivre ou non la
        // partie lignes nouvelles, avec un point d'étape vers 2029-2030 ; aucun scénario n'a de valeur actuelle nette
        // positive selon le COI. Pas de société de projet : sa création est jugée prématurée. Les dates de débat
        // (septembre 2014 à janvier 2015) proviennent d'un bilan de la CNDP cité par une recherche, non lu. Les dates
        // d'enquête et de mise en service sont des hypothèses, non des décisions.
        'id' => 'lnobpl',
        'name' => 'Liaisons nouvelles Ouest Bretagne - Pays de la Loire (LNOBPL)',
        'kind' => 'new_line',
        'regions' => ['Bretagne', 'Pays de la Loire'],
        'porteurs' => [
            ['name' => 'SNCF Réseau', 'role' => 'maître d\'ouvrage'],
            ['name' => 'Région Bretagne', 'role' => 'financeur'],
            ['name' => 'Région Pays de la Loire', 'role' => 'financeur'],
        ],
        'line_slugs' => [],
        'summary' => 'Projet à deux composantes (modernisation des lignes existantes et lignes nouvelles), relancé après l\'abandon du projet d\'aéroport de Notre-Dame-des-Landes. Un programme d\'études de 38 M EUR est engagé pour 2025-2029, financé par les deux CPER 2023-2027.',
        'stages' => [
            ['kind' => 'debate', 'date' => '2014-01-08', 'done' => true, 'note' => 'décision de la CNDP d\'organiser un débat public ; débat tenu de 2014 à 2015', 'source' => ['label' => 'CNDP, décision n° 2024/160/LNOB-PL/12 du 6 novembre 2024 (visas)', 'url' => 'https://debatpublic.fr/sites/default/files/2024-11/DECISION_2024_160_LNO-BPL_12%20Sign%C3%A9%20MP.pdf', 'published' => '2024-11-07']],
            ['kind' => 'study', 'date' => '2024-12', 'done' => true, 'note' => 'décision ministérielle confirmant les priorités et cadrant la suite des études ; programme d\'études de 38 M EUR contractualisé pour 2025-2029 avec 12 partenaires', 'source' => ['label' => 'Conseil d\'orientation des infrastructures, rapport d\'avril 2026', 'url' => 'https://www.ecologie.gouv.fr/sites/default/files/documents/260403_Rapport_COI_2026-Grands_projets-le_temps_des_choix-2_0.pdf', 'published' => '2026-04-03']],
            ['kind' => 'dup', 'date' => '2030', 'done' => false, 'note' => 'enquêtes publiques vers 2030 pour la modernisation des lignes existantes ; vers 2035 pour les lignes nouvelles (hypothèses du programme d\'études)', 'source' => ['label' => 'Conseil d\'orientation des infrastructures, rapport d\'avril 2026', 'url' => 'https://www.ecologie.gouv.fr/sites/default/files/documents/260403_Rapport_COI_2026-Grands_projets-le_temps_des_choix-2_0.pdf', 'published' => '2026-04-03']],
            ['kind' => 'commissioning', 'date' => '2040', 'done' => false, 'note' => 'mise en service vers 2040 de la modernisation, vers 2045 des lignes nouvelles (hypothèses)', 'source' => ['label' => 'Conseil d\'orientation des infrastructures, rapport d\'avril 2026', 'url' => 'https://www.ecologie.gouv.fr/sites/default/files/documents/260403_Rapport_COI_2026-Grands_projets-le_temps_des_choix-2_0.pdf', 'published' => '2026-04-03']],
        ],
        'checked' => '2026-11-23',
    ],
    [
        // Doutes : Le projet n'est pas abandonné officiellement : la question écrite n° 8753 (16e législature, 2023 ou
        // 2024, date exacte non lue) indique que la LGV POCL ne sera étudiée qu'après 2038 en raison de l'éloignement de
        // la saturation de la LGV Paris - Lyon (position du COI de 2023 rappelée par la députée). Le rapport du COI
        // d'avril 2026 ne traite pas le POCL. Aucune étape d'étude, de DUP ou de financement n'existe. La date de la
        // décision de classement de Mobilité 21 (rapport de 2013) et la décision de RFF de 2012 n'ont pas été sourcées.
        // Le COI a par ailleurs recommandé la modernisation de la ligne Paris - Clermont-Ferrand existante (760 M EUR de
        // régénération jusqu'en 2026, 130 M EUR de modernisation, selon la réponse ministérielle).
        'id' => 'pocl',
        'name' => 'LGV Paris - Orléans - Clermont-Ferrand - Lyon (POCL)',
        'kind' => 'lgv',
        'regions' => ['Centre-Val de Loire', 'Bourgogne-Franche-Comté', 'Auvergne-Rhône-Alpes', 'Île-de-France'],
        'porteurs' => [
            ['name' => 'Réseau ferré de France (aujourd\'hui SNCF Réseau)', 'role' => 'maître d\'ouvrage'],
        ],
        'line_slugs' => [],
        'suspended' => true,
        'summary' => 'Projet de ligne à grande vitesse ayant fait l\'objet d\'un débat public d\'octobre 2011 à janvier 2012, classé seconde priorité par la commission Mobilité 21 en 2013. Le gouvernement a indiqué qu\'il ne serait étudié qu\'après 2038 ; l\'État investit à la place dans la modernisation de la ligne existante Paris - Clermont-Ferrand.',
        'stages' => [
            ['kind' => 'debate', 'date' => '2011-10-03', 'done' => true, 'note' => 'débat public CNDP du 3 octobre 2011 au 31 janvier 2012 (saisine du 17 janvier 2011 ; bilan du 20 mars 2012)', 'source' => ['label' => 'CNDP, débat public LGV POCL, bilan du président', 'url' => 'https://cpdp.debatpublic.fr/cpdp-lgv-pocl/DOCS/compte-rendu-bilan/bilan.pdf', 'published' => '2012-03-20']],
        ],
        'checked' => '2026-11-23',
    ],
    [
        // Doutes : Les dates de débat public (2010) et de DUP (janvier 2022) proviennent d'un article de presse
        // (Info.fr) non recoupé avec le décret ou l'arrêté, ni avec la CNDP ; la page de SNCF Réseau n'a pas pu être lue
        // (accès refusé). Un article de presse du 22/12/2025 attribue le report aux enjeux de signalisation (mise en
        // service de la signalisation début 2027, puis essais) et évoque un report de deux ans ; une autre source parle
        // d'« un an de retard » dans son titre. La date de signature de la convention de financement (2024 selon la
        // presse) n'a pas été sourcée. Les parts de financement varient légèrement selon les sources (État 65 % ou 66,5
        // %).
        'id' => 'roissy-picardie',
        'name' => 'Liaison ferroviaire Roissy - Picardie',
        'kind' => 'new_line',
        'regions' => ['Hauts-de-France', 'Île-de-France'],
        'porteurs' => [
            ['name' => 'SNCF Réseau', 'role' => 'maître d\'ouvrage'],
            ['name' => 'Région Hauts-de-France', 'role' => 'financeur'],
            ['name' => 'État', 'role' => 'financeur'],
        ],
        'line_slugs' => [],
        'summary' => 'Raccordement ferroviaire destiné à relier la Picardie à la plateforme aéroportuaire de Roissy ; budget d\'environ 541 M EUR (État 359 M EUR, Région Hauts-de-France environ 130 M EUR). Le génie civil était achevé à 90 % en janvier 2026 ; la mise en service commerciale est reportée à décembre 2028.',
        'stages' => [
            ['kind' => 'debate', 'date' => '2010', 'done' => true, 'note' => 'débat public (CNDP)', 'source' => ['label' => 'Info.fr, Roissy-Picardie : 359 M EUR de l\'État', 'url' => 'https://info.fr/roissy-picardie-359-millions-etat-liaison-lille-roissy/', 'published' => '2026-05-04']],
            ['kind' => 'dup', 'date' => '2022-01', 'done' => true, 'note' => 'arrêté de déclaration d\'utilité publique en janvier 2022 (enquête publique en 2021)', 'source' => ['label' => 'Info.fr, Roissy-Picardie : 359 M EUR de l\'État', 'url' => 'https://info.fr/roissy-picardie-359-millions-etat-liaison-lille-roissy/', 'published' => '2026-05-04']],
            ['kind' => 'works', 'date' => '2024-01', 'done' => true, 'note' => 'début des travaux ; génie civil à 90 % en janvier 2026', 'source' => ['label' => 'Info.fr, Roissy-Picardie : 359 M EUR de l\'État', 'url' => 'https://info.fr/roissy-picardie-359-millions-etat-liaison-lille-roissy/', 'published' => '2026-05-04']],
            ['kind' => 'commissioning', 'date' => '2028-12', 'done' => false, 'note' => 'mise en service commerciale reportée de fin 2026 à décembre 2028, annonce du 19 décembre 2025 par SNCF Réseau et la Région (enjeux de signalisation)', 'source' => ['label' => 'La Gazette France, le barreau ferroviaire Creil-Roissy accuse un an de retard', 'url' => 'https://www.lagazettefrance.fr/article/le-barreau-ferroviaire-creil-roissy-accuse-un-an-de-retard', 'published' => '2025-12-22']],
        ],
        'checked' => '2026-11-23',
    ],
    [
        // Doutes : L'ouverture a été reportée plusieurs fois (initialement visée pour les Jeux olympiques de 2024, puis
        // fin 2023 selon la page du ministère) ; la date du 28 mars 2027 est annoncée par le consortium et SNCF Réseau
        // et reste conditionnée aux essais et à la certification. Le tarif n'est pas officiellement fixé (la presse cite
        // 25 EUR). Le décret approuvant le contrat de concession (février 2019) et sa date exacte de signature n'ont pas
        // été sourcés par une page lue. Le coût de 2,6 Md EUR provient de la presse.
        'id' => 'cdg-express',
        'name' => 'CDG Express (liaison Paris-Est - Roissy Charles-de-Gaulle)',
        'kind' => 'new_line',
        'regions' => ['Île-de-France'],
        'porteurs' => [
            ['name' => 'CDG Express Infra (SNCF Réseau, Groupe ADP, Caisse des Dépôts)', 'role' => 'maître d\'ouvrage'],
            ['name' => 'Hello Paris (Keolis et RATP Dev)', 'role' => 'porteur du projet'],
            ['name' => 'État', 'role' => 'financeur'],
        ],
        'line_slugs' => ['rfn-25000'],
        'summary' => 'Liaison ferroviaire directe sans arrêt entre la gare de l\'Est et l\'aéroport Paris-Charles-de-Gaulle, réalisée en concession de travaux. SNCF Réseau a posé les derniers raccordements en mai 2026 ; l\'ouverture est annoncée au 28 mars 2027.',
        'stages' => [
            ['kind' => 'dup', 'date' => '2008-12-19', 'done' => true, 'note' => 'arrêté inter-préfectoral de DUP initial ; DUP modifiée par arrêté inter-préfectoral du 31 mars 2017', 'source' => ['label' => 'Ministère de la Transition écologique, CDG Express', 'url' => 'https://www.ecologie.gouv.fr/politiques-publiques/charles-gaulle-express']],
            ['kind' => 'funding', 'date' => '2016-12-28', 'done' => true, 'note' => 'loi n° 2016-1887 ratifiant l\'ordonnance sur le contrat de concession ; taxe sur les billets d\'avion (LFR du 29 décembre 2016) et prêt de l\'État jusqu\'à 1,7 Md EUR (loi de finances pour 2018)', 'source' => ['label' => 'Ministère de la Transition écologique, CDG Express', 'url' => 'https://www.ecologie.gouv.fr/politiques-publiques/charles-gaulle-express']],
            ['kind' => 'works', 'date' => '2019', 'done' => true, 'note' => 'début des travaux en 2019 (concession de travaux SNCF Réseau - Groupe ADP - Caisse des Dépôts)', 'source' => ['label' => 'CDG Express, le parcours du projet', 'url' => 'https://cdgexpress.com/en/discover-cdg-express/journey-so-far']],
            ['kind' => 'works', 'date' => '2026-05-25', 'done' => true, 'note' => 'raccordement des 4,5 derniers kilomètres de voies au poste de commande de Saint-Denis (23 au 25 mai 2026) ; restent essais et certification', 'source' => ['label' => 'Ulysse, CDG Express : SNCF Réseau pose les derniers rails', 'url' => 'https://ulysse.com/news/cdg-express-derniers-rails-lancement-2027', 'published' => '2026-05-30']],
            ['kind' => 'commissioning', 'date' => '2027-03-28', 'done' => false, 'note' => 'ouverture au public annoncée le 28 mars 2027', 'source' => ['label' => 'CDG Express, le parcours du projet', 'url' => 'https://cdgexpress.com/en/discover-cdg-express/journey-so-far']],
        ],
        'checked' => '2026-11-23',
    ],
    [
        // Doutes : Les travaux ne sont pas engagés et aucun financement des travaux n'est acté : la recherche du
        // financement des études d'avant-projet détaillé (50 M EUR) puis des travaux est signalée comme prioritaire par
        // le COI. Le COI regrette le faible avancement de la mise à quatre voies Saint-Fons - Grenay. Le CFAL Nord,
        // déclaré d'utilité publique en 2012, est en phase d'études (CNDP) ; il n'est pas détaillé ici. Le tunnel de
        // Marseille Saint-Charles est traité dans la fiche LNPCA (phase 2). Aucune date de mise en service des
        // aménagements n'est sourcée au-delà de l'horizon 2039 du COI (hypothèse).
        'id' => 'etoile-ferroviaire-lyonnaise',
        'name' => 'Étoile ferroviaire lyonnaise (ancien nœud ferroviaire lyonnais), aménagements à long terme',
        'kind' => 'new_line',
        'regions' => ['Auvergne-Rhône-Alpes'],
        'porteurs' => [
            ['name' => 'SNCF Réseau', 'role' => 'maître d\'ouvrage'],
            ['name' => 'Région Auvergne-Rhône-Alpes', 'role' => 'financeur'],
            ['name' => 'Métropole de Lyon', 'role' => 'financeur'],
            ['name' => 'SYTRAL Mobilités', 'role' => 'autorité organisatrice'],
        ],
        'line_slugs' => [],
        'summary' => 'Projet de désaturation de l\'étoile ferroviaire de Lyon, qui a fait l\'objet d\'un débat public en 2019 puis d\'une concertation continue. SNCF Réseau a reprogrammé la préparation de l\'enquête publique au quatrième trimestre 2027.',
        'stages' => [
            ['kind' => 'debate', 'date' => '2019-04-11', 'done' => true, 'note' => 'débat public CNDP du 11 avril au 11 juillet 2019 ; décision de SNCF Réseau publiée au Journal officiel le 11 décembre 2019', 'source' => ['label' => 'CNDP, rapport intermédiaire n° 2 de la concertation continue (13/11/2025)', 'url' => 'https://www.debatpublic.fr/sites/default/files/2025-11/Rapport_interm%C3%A9diaire_2_EFL_CNDP.pdf', 'published' => '2025-11-13']],
            ['kind' => 'study', 'date' => '2025', 'done' => true, 'note' => 'avant-projet sommaire finalisé début 2025', 'source' => ['label' => 'CNDP, rapport intermédiaire n° 2 de la concertation continue (13/11/2025)', 'url' => 'https://www.debatpublic.fr/sites/default/files/2025-11/Rapport_interm%C3%A9diaire_2_EFL_CNDP.pdf', 'published' => '2025-11-13']],
            ['kind' => 'dup', 'date' => '2027', 'done' => false, 'note' => 'enquête publique prévue fin 2027 (préparation reprogrammée au 4e trimestre 2027), DUP visée en 2028', 'source' => ['label' => 'CNDP, rapport intermédiaire n° 2 de la concertation continue (13/11/2025)', 'url' => 'https://www.debatpublic.fr/sites/default/files/2025-11/Rapport_interm%C3%A9diaire_2_EFL_CNDP.pdf', 'published' => '2025-11-13']],
            ['kind' => 'works', 'date' => '2032', 'done' => false, 'note' => 'démarrage des travaux visé en 2032 pour une mise en service complète à l\'horizon 2039 (calendrier d\'études du COI)', 'source' => ['label' => 'Conseil d\'orientation des infrastructures, rapport d\'avril 2026', 'url' => 'https://www.ecologie.gouv.fr/sites/default/files/documents/260403_Rapport_COI_2026-Grands_projets-le_temps_des_choix-2_0.pdf', 'published' => '2026-04-03']],
        ],
        'checked' => '2026-11-23',
    ],
    [
        // Doutes : Aucune source officielle de 2026 trouvée sur une décision de financement ou un calendrier de travaux.
        // Un comité de lignes de l'étoile de Limoges était annoncé pour le 17/09/2026 (non lu). Le CPER 2015-2022 aurait
        // compté 6,4 M€ (réponse ministérielle). Grand Angoulême et Limoges Métropole auraient commandé une étude de
        // scénarios alternatifs (document régional, non daté précisément). Une source de presse évoque un horizon 2030
        // sans origine officielle : non retenu.
        'id' => 'angouleme-limoges',
        'name' => 'Réouverture d\'Angoulême à Limoges',
        'kind' => 'reopening',
        'regions' => ['Nouvelle-Aquitaine'],
        'porteurs' => [
            ['name' => 'Région Nouvelle-Aquitaine', 'role' => 'autorité organisatrice'],
            ['name' => 'SNCF Réseau', 'role' => 'maître d\'ouvrage'],
            ['name' => 'État', 'role' => 'financeur'],
        ],
        'line_slugs' => ['rfn-610000'],
        'summary' => 'La section Angoulême - Saillat-Chassenon est fermée depuis mars 2018 (service de cars) ; les trains circulent entre Limoges et Saillat-Chassenon. Le coût de la réouverture complète est évalué à environ 360 M€ (260 M€ pour le tronçon fermé, 100 M€ pour Limoges - Saillat), chiffre attribué à SNCF Réseau et cité par un député à l\'Assemblée nationale le 26 novembre 2025 ; aucun financement de travaux n\'est acté à ce jour.',
        'stages' => [
            ['kind' => 'study', 'date' => '2021', 'done' => true, 'note' => 'première étude pilotée par SNCF Réseau, cofinancée État et Région (2 M€)', 'source' => ['label' => 'Préfecture de Nouvelle-Aquitaine, réunion de concertation sur la liaison Angoulême - Limoges', 'url' => 'https://www.prefectures-regions.gouv.fr/nouvelle-aquitaine/Documents-publications/Salle-de-presse/Communiques-et-dossiers-de-presse/Reunion-de-concertation-sur-la-liaison-ferroviaire-entre-Angouleme-et-Limoges']],
            ['kind' => 'study', 'date' => '2025-06', 'done' => true, 'note' => 'étude préliminaire de réouverture publiée ; le 24 novembre 2025, le président de la Région déclare que le projet ne peut être financé à court terme', 'source' => ['label' => 'France 3 Nouvelle-Aquitaine, 27/11/2025', 'url' => 'https://france3-regions.franceinfo.fr/nouvelle-aquitaine/on-n-est-pas-capable-de-la-financer-cette-ligne-de-train-tres-attendue-ne-verra-toujours-pas-le-jour-3256927.html', 'published' => '2025-11-27']],
            ['kind' => 'study', 'date' => '2023', 'done' => true, 'note' => 'CPER 2023-2027 : enveloppe de 34 M€ pour études et régénération (État 13,6 M€) ; environ 10 M€ de travaux de maintenance Limoges - Saillat en 2023', 'source' => ['label' => 'Assemblée nationale, question orale n° 433 (JO du 26/11/2025, réponse du ministre des Transports)', 'url' => 'https://questions.assemblee-nationale.fr/q17/17-433QOSD.htm', 'published' => '2025-11-26']],
        ],
        'checked' => '2026-11-23',
    ],
    [
        // Doutes : Aucune annonce officielle de 2026 trouvée. Un compte rendu de 2023 indiquerait que le CPER ne
        // financerait pas de réouvertures et que 300 à 400 M€ seraient nécessaires en Auvergne (non lu directement). Les
        // travaux nécessaires (Laqueuille - Eygurande) viennent d'une association d'usagers.
        'id' => 'laqueuille-le-mont-dore',
        'name' => 'Réouverture de Laqueuille au Mont-Dore (liaison Clermont-Ferrand - Le Mont-Dore)',
        'kind' => 'reopening',
        'regions' => ['Auvergne-Rhône-Alpes'],
        'porteurs' => [
            ['name' => 'Région Auvergne-Rhône-Alpes', 'role' => 'autorité organisatrice'],
            ['name' => 'Fédération Agir pour la ligne', 'role' => 'association'],
        ],
        'line_slugs' => ['rfn-710000'],
        'summary' => 'Ligne fermée aux voyageurs depuis 2015. Le vice-président aux transports de la Région s\'est déclaré favorable au principe d\'une réouverture ; une étude du CEREMA financée par la Région a été lancée en 2021. Aucune décision de réouverture ni financement trouvé.',
        'stages' => [
            ['kind' => 'study', 'date' => '2021', 'done' => true, 'note' => 'étude CEREMA financée par la Région sur les besoins et une réhabilitation à coût limité', 'source' => ['label' => 'Résultat de recherche citant l\'étude CEREMA (patrimoine.auvergnerhonealpes.fr / presse)', 'url' => 'https://www.cerema.fr/fr/print/pdf/node/13306']],
        ],
        'checked' => '2026-11-23',
    ],
    [
        // Doutes : La date exacte du 22/07/2024 vient d'un texte de pétition cité par un résultat de recherche ; la
        // question parlementaire dit seulement juillet 2024. Le type d'étape de l'arrêt n'a pas d'équivalent dans le
        // format (classé study). Le SERM rouennais est évoqué par le député sans lien établi avec cette ligne.
        'id' => 'serqueux-gisors',
        'name' => 'Liaison voyageurs Serqueux - Gisors',
        'kind' => 'reopening',
        'regions' => ['Normandie'],
        'porteurs' => [
            ['name' => 'Région Normandie', 'role' => 'autorité organisatrice'],
            ['name' => 'SNCF Réseau', 'role' => 'maître d\'ouvrage'],
        ],
        'line_slugs' => [],
        'suspended' => true,
        'summary' => 'La ligne, modernisée pour le fret et rouverte aux voyageurs le 29 mars 2021, a vu son service voyageurs arrêté par la Région Normandie en juillet 2024 (service jugé non viable, remplacé par la ligne de car 527). Aucune remise en service voyageurs n\'est annoncée.',
        'stages' => [
            ['kind' => 'service', 'date' => '2021-03-29', 'done' => true, 'note' => 'remise en service aux voyageurs après les travaux de modernisation engagés en 2017', 'source' => ['label' => 'Gares & Connexions, Serqueux - Gisors reprend du service', 'url' => 'https://www.garesetconnexions.sncf/fr/actualites/ligne-serqueux-gisors-reprend-du-service', 'published' => '2021-05-03']],
            ['kind' => 'service', 'date' => '2024-07', 'done' => true, 'note' => 'arrêt du service voyageurs par la Région Normandie (cars de substitution)', 'source' => ['label' => 'Assemblée nationale, question écrite n° 2190 (JO 19/11/2024, réponse JO 03/06/2025)', 'url' => 'https://questions.assemblee-nationale.fr/q17/17-2190QE.htm', 'published' => '2025-06-03']],
        ],
        'checked' => '2026-11-23',
    ],
    [
        // Doutes : Enquête publique et DUP annoncées comme à venir (dépôt du dossier prévu automne 2025 selon un
        // document de 2024) mais aucune date confirmée trouvée en 2026. Aucun plan de financement trouvé. Oloron -
        // Bedous a rouvert en 2016 (dates de juin ou juillet selon les sources) ; le bilan CNDP n'a pu être lu (PDF non
        // décodable), sa date vient du nom du fichier. Un service TER Oloron - Bedous existe ; Bedous - Canfranc est
        // desservi par car.
        'id' => 'pau-canfranc',
        'name' => 'Réouverture de Pau à Canfranc (Oloron - Bedous - Somport)',
        'kind' => 'reopening',
        'regions' => ['Nouvelle-Aquitaine'],
        'porteurs' => [
            ['name' => 'SNCF Réseau', 'role' => 'maître d\'ouvrage'],
            ['name' => 'Région Nouvelle-Aquitaine', 'role' => 'autorité organisatrice'],
        ],
        'line_slugs' => ['rfn-664000'],
        'summary' => 'Projet de réouverture de la section Bedous - Forges d\'Abel (environ 25 km, fermée depuis 1970) et d\'adaptation de Pau - Bedous (60 km), avec un ou deux nouveaux arrêts. Une concertation préalable s\'est tenue en 2024 ; horizon de mise en service souhaité 2032.',
        'stages' => [
            ['kind' => 'debate', 'date' => '2024-09-23', 'done' => true, 'note' => 'concertation préalable du 23 septembre au 20 décembre 2024', 'source' => ['label' => 'Région Nouvelle-Aquitaine, concertation préalable Pau - Canfranc', 'url' => 'https://transports.nouvelle-aquitaine.fr/toute-lactualite-des-transports/concertation-prealable-sur-le-projet-de-reouverture-de-la-ligne-pau-canfranc-2']],
            ['kind' => 'debate', 'date' => '2025-01-17', 'done' => true, 'note' => 'bilan de la concertation préalable', 'source' => ['label' => 'CNDP, bilan de la concertation préalable', 'url' => 'https://debatpublic.fr/sites/default/files/2025-01/SNCF%20R%C3%A9ouverture%20Pau-Canfranc%20Bilan%20Concertation%20Pr%C3%A9alable17janvier2025.pdf', 'published' => '2025-01-17']],
            ['kind' => 'commissioning', 'date' => '2032', 'done' => false, 'note' => 'horizon de mise en service souhaité', 'source' => ['label' => 'SNCF Réseau, dépliant Pau - Canfranc', 'url' => 'https://transports.nouvelle-aquitaine.fr/sites/default/files/2024-09/SNCF-RESEAU-Pau%20Canfranc_Depliant.pdf', 'published' => '2024-09']],
        ],
        'checked' => '2026-11-23',
    ],
    [
        // Doutes : Sources de presse régionale uniquement (aucun communiqué de la Région lu). Le coût de 40 M€ concerne
        // la phase 1 selon l'article ; la Région prévoyait 66 M€ en 2021 selon un résultat de recherche. L'autorisation
        // environnementale préfectorale (décision attendue au mieux début novembre 2026) n'est pas confirmée. Report
        // depuis un objectif initial 2026 puis travaux 2026 et mise en service 2028 annoncés en 2023.
        'id' => 'ales-besseges',
        'name' => 'Réouverture d\'Alès à Bessèges',
        'kind' => 'reopening',
        'regions' => ['Occitanie'],
        'porteurs' => [
            ['name' => 'Région Occitanie', 'role' => 'maître d\'ouvrage'],
            ['name' => 'État', 'role' => 'financeur'],
        ],
        'line_slugs' => [],
        'summary' => 'Réouverture en deux phases portée par la Région : Alès - Saint-Ambroix (environ 40 M€ estimés) puis Saint-Ambroix - Bessèges. Une consultation publique en ligne s\'est tenue du 4 mai au 4 août 2026 pour la première phase, dans l\'attente de l\'autorisation environnementale.',
        'stages' => [
            ['kind' => 'dup', 'date' => '2026-05-04', 'done' => true, 'note' => 'consultation en ligne du 4 mai au 4 août 2026 (557 observations dont 459 favorables), phase 1 Alès - Saint-Ambroix', 'source' => ['label' => 'Objectif Gard, consultation publique largement favorable', 'url' => 'https://www.objectifsud.fr/economie/fait-du-soir-ligne-ales-besseges-la-consultation-publique-largement-favorable-mais-parfois-hors-sujet-167783.php', 'published' => '2026-08-05']],
            ['kind' => 'dup', 'date' => '2026-06-11', 'done' => true, 'note' => 'avis délibéré de l\'Autorité environnementale sur le projet de modernisation de la ligne ; deux avis défavorables (Conseil national de la protection de la nature et Inspection générale de l\'environnement) cités en juillet 2026, la Région maintient une réouverture en 2028', 'source' => ['label' => 'IGEDD, avis délibéré sur la modernisation de la ligne Alès - Bessèges (11/06/2026)', 'url' => 'https://www.igedd.developpement-durable.gouv.fr/IMG/pdf/8__110626_modernisation_ligne_sncf_ales_besseges_delibere_cle2d8e9a.pdf', 'published' => '2026-06-11']],
            ['kind' => 'commissioning', 'date' => '2028', 'done' => false, 'note' => 'mise en service prévue Alès - Saint-Ambroix', 'source' => ['label' => 'Objectif Gard, entretien avec le vice-président de la Région', 'url' => 'https://www.objectifgard.com/gard/linterview-jean-luc-gibelin-on-ouvrira-les-lignes-ales-saint-ambroix-en-2028-et-saint-ambroix-besseges-en-2030-164070.php', 'published' => '2026-05-22']],
            ['kind' => 'commissioning', 'date' => '2030', 'done' => false, 'note' => 'mise en service prévue Saint-Ambroix - Bessèges', 'source' => ['label' => 'Objectif Gard, entretien avec le vice-président de la Région', 'url' => 'https://www.objectifgard.com/gard/linterview-jean-luc-gibelin-on-ouvrira-les-lignes-ales-saint-ambroix-en-2028-et-saint-ambroix-besseges-en-2030-164070.php', 'published' => '2026-05-22']],
        ],
        'checked' => '2026-11-23',
    ],
    [
        // Doutes : Positions divergentes : suspension pour raisons budgétaires par la Région (réponse ministérielle)
        // contre absence de financement de l'État (affirmation de la présidente de la Région, juillet 2026, d'après un
        // résultat de recherche). Une revue des lignes de desserte fine par une mission préfectorale était attendue.
        'id' => 'limoux-quillan',
        'name' => 'Réouverture de Limoux à Quillan',
        'kind' => 'reopening',
        'regions' => ['Occitanie'],
        'porteurs' => [
            ['name' => 'Région Occitanie', 'role' => 'autorité organisatrice'],
            ['name' => 'État', 'role' => 'financeur'],
            ['name' => 'SNCF Réseau', 'role' => 'maître d\'ouvrage'],
        ],
        'line_slugs' => ['rfn-676000'],
        'suspended' => true,
        'summary' => 'Le projet de régénération et de réouverture est suspendu par la Région Occitanie selon la réponse du ministère (29/09/2026). Les crédits de l\'État du CPER 2023-2027 sont disponibles mais non programmés ; la Région dit de son côté que l\'État ne finance pas.',
        'stages' => [
            ['kind' => 'funding', 'date' => '2022', 'done' => true, 'note' => 'protocole de financement LDFT : État 25 %, SNCF Réseau 8,5 %, Région 66,5 %', 'source' => ['label' => 'Assemblée nationale, question écrite n° 17722, réponse JO 29/09/2026', 'url' => 'https://questions.assemblee-nationale.fr/q17/17-17722QE.htm', 'published' => '2026-09-29']],
            ['kind' => 'funding', 'date' => '2023', 'done' => true, 'note' => 'CPER 2023-2027 : près de 5 M€ pour Carcassonne - Limoux et 2 M€ d\'études Limoux - Quillan', 'source' => ['label' => 'Assemblée nationale, question écrite n° 17722', 'url' => 'https://questions.assemblee-nationale.fr/q17/17-17722QE.htm', 'published' => '2026-09-29']],
        ],
        'checked' => '2026-11-23',
    ],
    [
        // Doutes : Documents lus uniquement via résultats de recherche (pages refusées ou PDF). Aucun calendrier de
        // travaux ni plan de financement trouvé. La décision de desservir Le Teil relèverait des régions (réponse
        // ministérielle non datée).
        'id' => 'rive-droite-rhone-le-teil',
        'name' => 'Prolongement de Pont-Saint-Esprit au Teil (Rive Droite du Rhône, Nîmes - Le Teil)',
        'kind' => 'reopening',
        'regions' => ['Occitanie', 'Auvergne-Rhône-Alpes'],
        'porteurs' => [
            ['name' => 'Région Occitanie', 'role' => 'porteur du projet'],
            ['name' => 'Région Auvergne-Rhône-Alpes', 'role' => 'autorité organisatrice'],
        ],
        'line_slugs' => [],
        'summary' => 'Après la réouverture de Nîmes - Pont-Saint-Esprit en 2022, le projet de réouverture jusqu\'au Teil (Ardèche) est à l\'étude, avec un avis de l\'Autorité environnementale du 30 janvier 2026.',
        'stages' => [
            ['kind' => 'study', 'date' => '2026-01-30', 'done' => true, 'note' => 'avis délibéré n° 2025-142 de l\'Autorité environnementale sur le projet de réouverture Le Teil - Nîmes', 'source' => ['label' => 'Résultat de recherche citant l\'avis de l\'Autorité environnementale (IGEDD)', 'url' => 'https://www.igedd.developpement-durable.gouv.fr/IMG/pdf/3_-_reouverture_au_trafic_voyageurs_de_la_voie_ferree_de_la_rive_droite_du_rhone_07_30__cle273181.pdf', 'published' => '2026-01-30']],
        ],
        'checked' => '2026-11-23',
    ],
    [
        // Doutes : La convention porte la mention de la commission permanente du 27 novembre 2025 dans ses visas alors
        // que le dossier est classé au 20 novembre et télétransmis le 24 novembre 2025 : seul le mois est retenu. Aucune
        // date de mise en service ni statut SERM par arrêté n'a été trouvé pour 2026.
        'id' => 'serm-lille',
        'name' => 'Service express régional métropolitain (SERM) de Lille et des Hauts-de-France',
        'kind' => 'rer',
        'regions' => ['Hauts-de-France'],
        'porteurs' => [
            ['name' => 'Région Hauts-de-France', 'role' => 'autorité organisatrice'],
            ['name' => 'Métropole Européenne de Lille', 'role' => 'financeur'],
            ['name' => 'État (ministère chargé des transports)', 'role' => 'financeur'],
            ['name' => 'SNCF Réseau et SNCF Gares & Connexions', 'role' => 'maître d\'ouvrage'],
        ],
        'line_slugs' => [],
        'summary' => 'Projet de service express régional métropolitain sur l\'étoile ferroviaire lilloise, labellisé par le ministère en juin 2024. Il en est à la phase d\'études de préfiguration, financée par l\'État, la Région, la Métropole et SNCF.',
        'stages' => [
            ['kind' => 'study', 'date' => '2020-12-24', 'done' => true, 'note' => 'convention de financement des études d\'opportunité sur l\'étoile ferroviaire de Lille, signée le 24 décembre 2020', 'source' => ['label' => 'Région Hauts-de-France, convention annexée à la délibération n° 2025.01294 (visas)', 'url' => 'https://delibinternet.hautsdefrance.fr/Docs/CommissionPermanente/2025/11/20/DELIBERATION/2025.01294_annexe.PDF', 'published' => '2025-11-24']],
            ['kind' => 'study', 'date' => '2021-12-24', 'done' => true, 'note' => 'convention de financement de la première phase des études de faisabilité (État, Région, MEL, SNCF Réseau)', 'source' => ['label' => 'Région Hauts-de-France, convention annexée à la délibération n° 2025.01294 (visas)', 'url' => 'https://delibinternet.hautsdefrance.fr/Docs/CommissionPermanente/2025/11/20/DELIBERATION/2025.01294_annexe.PDF', 'published' => '2025-11-24']],
            ['kind' => 'study', 'date' => '2023-07-06', 'done' => true, 'note' => 'délibération de la Région sur son engagement en faveur du SERM de l\'étoile ferroviaire de Lille', 'source' => ['label' => 'Région Hauts-de-France, convention annexée à la délibération n° 2025.01294 (visas)', 'url' => 'https://delibinternet.hautsdefrance.fr/Docs/CommissionPermanente/2025/11/20/DELIBERATION/2025.01294_annexe.PDF', 'published' => '2025-11-24']],
            ['kind' => 'study', 'date' => '2024-04-17', 'done' => true, 'note' => 'entrée en vigueur de la convention n° 230006737 (gouvernance, participation du public, études de consolidation)', 'source' => ['label' => 'Région Hauts-de-France, convention annexée à la délibération n° 2025.01294 (visas)', 'url' => 'https://delibinternet.hautsdefrance.fr/Docs/CommissionPermanente/2025/11/20/DELIBERATION/2025.01294_annexe.PDF', 'published' => '2025-11-24']],
            ['kind' => 'study', 'date' => '2024-06-27', 'done' => true, 'note' => 'Lille figure parmi les 15 projets de la première vague de labellisation', 'source' => ['label' => 'Ministère de la Transition écologique, communiqué de la première vague de labellisation', 'url' => 'https://www.ecologie.gouv.fr/presse/premiere-vague-labellisation-15-projets-services-express-regionaux-metropolitains-serm', 'published' => '2024-06-27']],
            ['kind' => 'study', 'date' => '2025-11', 'done' => true, 'note' => 'convention de financement de la phase d\'approfondissement des études de préfiguration (volet SNCF Gares & Connexions et SNCF Réseau), délibération régionale n° 2025.01294', 'source' => ['label' => 'Région Hauts-de-France, délibération n° 2025.01294 et convention annexée', 'url' => 'https://delibinternet.hautsdefrance.fr/Docs/CommissionPermanente/2025/11/20/DELIBERATION/2025.01294_annexe.PDF', 'published' => '2025-11-24']],
        ],
        'checked' => '2026-11-23',
    ],
    [
        // Doutes : La date exacte de labellisation (27 juin 2024 selon la liste ministérielle) est donnée en 'juin 2024'
        // par la Métropole. Le statut de SERM par arrêté n'est pas encore obtenu d'après les sources de 2026. L'horizon
        // 2030-2035 est une fourchette, la première date est reportée comme prévue.
        'id' => 'serm-nantes-saint-nazaire',
        'name' => 'SERM Nantes - Saint-Nazaire (RER métropolitain nantais)',
        'kind' => 'rer',
        'regions' => ['Pays de la Loire'],
        'porteurs' => [
            ['name' => 'Région Pays de la Loire', 'role' => 'autorité organisatrice'],
            ['name' => 'Nantes Métropole', 'role' => 'financeur'],
            ['name' => 'Société des grands projets et SNCF', 'role' => 'maître d\'ouvrage'],
        ],
        'line_slugs' => [],
        'summary' => 'Service de trains cadencés toutes les 30 minutes en heure de pointe, complétés par des cars express, visé à l\'horizon 2030-2035. La ligne Nantes - Saint-Gilles-Croix-de-Vie - Pornic en est un maillon dont les études de modernisation durent quatre ans.',
        'stages' => [
            ['kind' => 'study', 'date' => '2024-06', 'done' => true, 'note' => 'labellisation par l\'État (première vague)', 'source' => ['label' => 'Nantes Métropole, Le futur RER métropolitain franchit deux étapes', 'url' => 'https://metropole.nantes.fr/actualites/le-futur-rer-metropolitain-nantais-franchit-deux-etapes', 'published' => '2026-10-02']],
            ['kind' => 'study', 'date' => '2026-09', 'done' => true, 'note' => 'première étude de l\'agence d\'urbanisme (Auran) achevée, à consolider par la Société des grands projets et SNCF en vue du statut de SERM', 'source' => ['label' => 'Nantes Métropole, Le futur RER métropolitain franchit deux étapes', 'url' => 'https://metropole.nantes.fr/actualites/le-futur-rer-metropolitain-nantais-franchit-deux-etapes', 'published' => '2026-10-02']],
            ['kind' => 'study', 'date' => '2026-10-02', 'done' => true, 'note' => 'le conseil métropolitain approuve deux études du CPER : postes d\'aiguillage de l\'étoile nantaise (45,56 M EUR, dont 2,9 M EUR Métropole) et service cadencé Nantes - Pornic (7,5 M EUR sur quatre ans, dont 0,75 M EUR Métropole)', 'source' => ['label' => 'Nantes Métropole, Le futur RER métropolitain franchit deux étapes', 'url' => 'https://metropole.nantes.fr/actualites/le-futur-rer-metropolitain-franchit-deux-etapes', 'published' => '2026-10-02']],
            ['kind' => 'works', 'date' => '2031-06', 'done' => false, 'note' => 'travaux prévus de mi-2031 à fin 2033 sur Nantes - Saint-Gilles-Croix-de-Vie - Pornic', 'source' => ['label' => 'Nantes Métropole, Le futur RER métropolitain franchit deux étapes', 'url' => 'https://metropole.nantes.fr/actualites/le-futur-rer-metropolitain-franchit-deux-etapes', 'published' => '2026-10-02']],
            ['kind' => 'commissioning', 'date' => '2030', 'done' => false, 'note' => 'horizon 2030-2035 pour le service complet (prévu)', 'source' => ['label' => 'Nantes Métropole, L\'essentiel du conseil métropolitain du 2 octobre 2026', 'url' => 'https://metropole.nantes.fr/actualites/l-essentiel-du-conseil-metropolitain-du-2-octobre-2026', 'published' => '2026-10-02']],
        ],
        'checked' => '2026-11-23',
    ],
    [
        // Doutes : Le dossier de synthèse devait être finalisé à l'été 2025 : sa remise et l'obtention du statut par
        // arrêté n'ont pas été confirmées. Une délibération de Tisséo du 11 février 2026 évoquerait un déploiement par
        // horizons 2028, 2032 et 2040, mais ce document n'a pas été lu, donc ces dates ne sont pas retenues comme
        // étapes.
        'id' => 'serm-toulouse',
        'name' => 'SERM de l\'agglomération toulousaine',
        'kind' => 'rer',
        'regions' => ['Occitanie'],
        'porteurs' => [
            ['name' => 'Région Occitanie', 'role' => 'autorité organisatrice'],
            ['name' => 'Toulouse Métropole et Tisséo Collectivités', 'role' => 'financeur'],
            ['name' => 'Département de la Haute-Garonne', 'role' => 'financeur'],
            ['name' => 'État', 'role' => 'financeur'],
        ],
        'line_slugs' => [],
        'summary' => 'Service express multimodal sur 19 EPCI, labellisé en juin 2024. La phase de préfiguration, lancée en mars 2025 pour 535 000 EUR HT, doit conduire au dossier de synthèse en vue du statut de SERM par arrêté.',
        'stages' => [
            ['kind' => 'study', 'date' => '2024-06-27', 'done' => true, 'note' => 'labellisation par les ministres chargés de la transition écologique et des transports', 'source' => ['label' => 'Préfecture de région Occitanie, communiqué du 4 mars 2025', 'url' => 'https://www.prefectures-regions.gouv.fr/irecontenu/telechargement/125768/929833/file/20250304_CP_lancement_prefiguration_SERM.pdf', 'published' => '2025-03-04']],
            ['kind' => 'study', 'date' => '2024-06-25', 'done' => true, 'note' => 'Toulouse Métropole et Tisséo complètent leur dossier de candidature (volet ferroviaire chiffré à 4,1 Md EUR, besoins non ferroviaires estimés à 553 M EUR)', 'source' => ['label' => 'Toulouse Métropole, Pour un SERM ambitieux et cohérent', 'url' => 'https://metropole.toulouse.fr/espace-presse/pour-un-serm-ambitieux-et-coherent-au-service-des-territoires', 'published' => '2024-06-25']],
            ['kind' => 'study', 'date' => '2025-03-03', 'done' => true, 'note' => 'lancement de la phase de préfiguration, coût 535 000 EUR HT financé à 50 % par l\'État', 'source' => ['label' => 'Préfecture de région Occitanie, communiqué du 4 mars 2025', 'url' => 'https://www.prefectures-regions.gouv.fr/irecontenu/telechargement/125768/929833/file/20250304_CP_lancement_prefiguration_SERM.pdf', 'published' => '2025-03-04']],
        ],
        'checked' => '2026-11-23',
    ],
    [
        // Doutes : L'objectif 2030 date de 2024 et n'a pas été confirmé en 2026. Le statut de SERM par arrêté n'a pas
        // été trouvé. Le communiqué métropolitain parle d'une première labellisation fin juin 2024, la liste
        // ministérielle est datée du 27 juin 2024.
        'id' => 'serm-bordeaux',
        'name' => 'RER métropolitain de Bordeaux (SERM)',
        'kind' => 'rer',
        'regions' => ['Nouvelle-Aquitaine'],
        'porteurs' => [
            ['name' => 'Bordeaux Métropole', 'role' => 'porteur du projet'],
            ['name' => 'Région Nouvelle-Aquitaine', 'role' => 'autorité organisatrice'],
            ['name' => 'SNCF Réseau et SNCF Gares & Connexions', 'role' => 'maître d\'ouvrage'],
        ],
        'line_slugs' => [],
        'summary' => 'Projet initié en 2018 par la Métropole et la Région : transformer cinq lignes existantes en trois lignes traversantes avec un train toutes les demi-heures. Des éléments sont déjà en service (cars express, halte de Talence-Médoquine) ; les études d\'exploitation de trois scénarios se terminent en octobre 2026.',
        'stages' => [
            ['kind' => 'study', 'date' => '2018', 'done' => true, 'note' => 'projet adopté par la Métropole et la Région', 'source' => ['label' => 'Bordeaux Métropole, délibération n° 2026-188', 'url' => 'https://www.bordeaux-metropole.fr/sites/MET-BXMETRO-DRUPAL/files/bm_actes/organismes/bordeaux-metropole/conseils/2026-06-26/Parapheur2026-188-3_0.pdf', 'published' => '2026-07-01']],
            ['kind' => 'study', 'date' => '2024-06', 'done' => true, 'note' => 'premier projet à recevoir la labellisation SERM du ministère des Transports selon Bordeaux Métropole (Bordeaux figure dans la liste ministérielle du 27 juin 2024)', 'source' => ['label' => 'Bordeaux Métropole, communiqué du 28 juin 2024', 'url' => 'https://www.bordeaux-metropole.fr/sites/MET-BXMETRO-DRUPAL/files/2024-07/CP_SERM_BordeauxM%C3%A9tropole_0.pdf', 'published' => '2024-06-28']],
            ['kind' => 'service', 'date' => '2025-09-21', 'done' => true, 'note' => 'réouverture de la halte de Talence-Médoquine sur Arcachon - Bordeaux - Libourne', 'source' => ['label' => 'Ville de Talence, dossier de presse de l\'inauguration', 'url' => 'https://www.talence.fr/wp-content/uploads/2025/10/DP_INAUG_TALENCE-MEDOQUINE-RERM-SEPT25-V07-photos-19x26-HDSSTRAITS.pdf', 'published' => '2025-09-22']],
            ['kind' => 'study', 'date' => '2025-12', 'done' => true, 'note' => 'lancement de la phase PRO-REA pour l\'adaptation des quais entre Saint-Mariens et Bordeaux', 'source' => ['label' => 'Bordeaux Métropole, délibération n° 2026-188', 'url' => 'https://www.bordeaux-metropole.fr/sites/MET-BXMETRO-DRUPAL/files/bm_actes/organismes/bordeaux-metropole/conseils/2026-06-26/Parapheur2026-188-3_0.pdf', 'published' => '2026-07-01']],
            ['kind' => 'study', 'date' => '2026-06-26', 'done' => true, 'note' => 'convention de financement des études d\'exploitation et de robustesse de trois scénarios (11 mois, fin prévue en octobre 2026)', 'source' => ['label' => 'Bordeaux Métropole, délibération n° 2026-188', 'url' => 'https://www.bordeaux-metropole.fr/sites/MET-BXMETRO-DRUPAL/files/bm_actes/organismes/bordeaux-metropole/conseils/2026-06-26/Parapheur2026-188-3_0.pdf', 'published' => '2026-07-01']],
            ['kind' => 'commissioning', 'date' => '2030', 'done' => false, 'note' => 'mise en place progressive de nouveaux services entre 2020 et 2030 selon le communiqué de 2024', 'source' => ['label' => 'Bordeaux Métropole, communiqué du 28 juin 2024', 'url' => 'https://www.bordeaux-metropole.fr/sites/MET-BXMETRO-DRUPAL/files/2024-07/CP_SERM_BordeauxM%C3%A9tropole_0.pdf', 'published' => '2024-06-28']],
        ],
        'checked' => '2026-11-23',
    ],
    [
        // Doutes : Aucune source officielle de 2026 lue sur le calendrier ou le financement. Le communiqué de 2024
        // indique qu'il n'est pas possible d'insérer des trains supplémentaires en pointe sur Villefranche - Perrache -
        // Vienne sans investissements conséquents. La date 2027 est à confirmer pour 2026.
        'id' => 'serm-lyon',
        'name' => 'RER métropolitain lyonnais (SERM de Lyon)',
        'kind' => 'rer',
        'regions' => ['Auvergne-Rhône-Alpes'],
        'porteurs' => [
            ['name' => 'Région Auvergne-Rhône-Alpes', 'role' => 'autorité organisatrice'],
            ['name' => 'Métropole de Lyon', 'role' => 'financeur'],
            ['name' => 'SYTRAL Mobilités', 'role' => 'financeur'],
            ['name' => 'État', 'role' => 'financeur'],
        ],
        'line_slugs' => [],
        'summary' => 'Projet de service de type RER sur l\'étoile ferroviaire lyonnaise, labellisé en juin 2024. Un programme d\'études pré-opérationnelles prépare un renfort de 108 trains par jour à partir de 2027.',
        'stages' => [
            ['kind' => 'study', 'date' => '2024-06-24', 'done' => true, 'note' => 'le conseil métropolitain approuve le cofinancement d\'études pré-opérationnelles (1 M EUR HT, dont 143 500 EUR pour la Métropole)', 'source' => ['label' => 'Métropole de Lyon, communiqué du 24 juin 2024', 'url' => 'https://www.grandlyon.com/fileadmin/user_upload/media/pdf/espace-presse/cp/2024/20240624_cp_rer-metropolitain.pdf', 'published' => '2024-06-24']],
            ['kind' => 'study', 'date' => '2024-06-27', 'done' => true, 'note' => 'Lyon figure parmi les 15 projets de la première vague de labellisation', 'source' => ['label' => 'Ministère de la Transition écologique, communiqué de la première vague de labellisation', 'url' => 'https://www.ecologie.gouv.fr/presse/premiere-vague-labellisation-15-projets-services-express-regionaux-metropolitains-serm', 'published' => '2024-06-27']],
            ['kind' => 'commissioning', 'date' => '2027', 'done' => false, 'note' => 'premiers renforts d\'offre TER prévus à partir de 2027, sous réserve des conclusions des études', 'source' => ['label' => 'Métropole de Lyon, communiqué du 24 juin 2024', 'url' => 'https://www.grandlyon.com/fileadmin/user_upload/media/pdf/espace-presse/cp/2024/20240624_cp_rer-metropolitain.pdf', 'published' => '2024-06-24']],
        ],
        'checked' => '2026-11-23',
    ],
    [
        // Doutes : Le communiqué de lancement de l'étude n'est pas daté dans le document lu (il annonce des comités de
        // pilotage dès le premier semestre 2025), donc aucune date n'est donnée pour ce lancement. L'automne 2028 est
        // une date annoncée dans ce document, sans confirmation en 2026.
        'id' => 'serm-grenoble',
        'name' => 'SERM de l\'aire grenobloise',
        'kind' => 'rer',
        'regions' => ['Auvergne-Rhône-Alpes'],
        'porteurs' => [
            ['name' => 'Région Auvergne-Rhône-Alpes', 'role' => 'autorité organisatrice'],
            ['name' => 'SMMAG (Syndicat mixte des mobilités de l\'aire grenobloise)', 'role' => 'financeur'],
            ['name' => 'Grenoble Alpes Métropole et Département de l\'Isère', 'role' => 'financeur'],
            ['name' => 'État', 'role' => 'financeur'],
        ],
        'line_slugs' => [],
        'summary' => 'Offre périurbaine de train renforcée à terme jusqu\'à un train tous les quarts d\'heure en pointe. Les travaux d\'infrastructure sont estimés à plus d\'un milliard d\'euros. Une étude de 1 407 800 EUR HT doit permettre d\'obtenir le statut de SERM.',
        'stages' => [
            ['kind' => 'study', 'date' => '2024-06-27', 'done' => true, 'note' => 'Grenoble figure parmi les 15 projets de la première vague de labellisation', 'source' => ['label' => 'Ministère de la Transition écologique, communiqué de la première vague de labellisation', 'url' => 'https://www.ecologie.gouv.fr/presse/premiere-vague-labellisation-15-projets-services-express-regionaux-metropolitains-serm', 'published' => '2024-06-27']],
            ['kind' => 'commissioning', 'date' => '2028-10', 'done' => false, 'note' => 'premières améliorations de dessertes visibles à partir de l\'automne 2028 (prévu)', 'source' => ['label' => 'DREAL Auvergne-Rhône-Alpes, communiqué de lancement de la démarche SERM de l\'aire grenobloise', 'url' => 'https://www.auvergne-rhone-alpes.developpement-durable.gouv.fr/IMG/pdf/cp_serm_grenoble.pdf']],
        ],
        'checked' => '2026-11-23',
    ],
    [
        // Doutes : Le projet est régional (Bretagne) et non limité à la métropole de Rennes. Le montant et la durée de
        // l'étude n'ont pas pu être lus dans le document. Aucune date de mise en service d'une première étape n'a été
        // trouvée.
        'id' => 'serm-bretagne-rennes',
        'name' => 'SERM breton, nœud ferroviaire de Rennes',
        'kind' => 'rer',
        'regions' => ['Bretagne'],
        'porteurs' => [
            ['name' => 'Région Bretagne', 'role' => 'autorité organisatrice'],
            ['name' => 'Rennes Métropole, Brest Métropole, départements d\'Ille-et-Vilaine et du Finistère', 'role' => 'financeur'],
            ['name' => 'État', 'role' => 'financeur'],
        ],
        'line_slugs' => [],
        'summary' => 'Projet de service express régional métropolitain adapté à la Bretagne, avec pour objectif une hausse de l\'offre de transport de 20 % en 2030, 50 % en 2035 et 100 % en 2040. Des études d\'opportunité sur les aménagements ferroviaires, dont le nœud de Rennes, sont financées au titre du CPER.',
        'stages' => [
            ['kind' => 'study', 'date' => '2024-07-04', 'done' => true, 'note' => 'le projet Bretagne figure parmi les 9 projets de la seconde vague de labellisation', 'source' => ['label' => 'Ministère de la Transition écologique, communiqué de la seconde vague de labellisation', 'url' => 'https://www.ecologie.gouv.fr/presse/deuxieme-vague-labellisation-9-projets-services-express-regionaux-metropolitains-serm', 'published' => '2024-07-04']],
            ['kind' => 'study', 'date' => '2026-03-09', 'done' => true, 'note' => 'la commission permanente du Département d\'Ille-et-Vilaine approuve la convention de financement de l\'étude d\'opportunité des aménagements ferroviaires du plan de transport de la Région (volet nœud de Rennes), au titre du CPER 2021-2027', 'source' => ['label' => 'Département d\'Ille-et-Vilaine, délibération CP_2026_0145', 'url' => 'https://www.ille-et-vilaine.fr/sites/iev/files/rapports/Deliberation_2026_51713_signe.pdf', 'published' => '2026-03-10']],
        ],
        'checked' => '2026-11-23',
    ],
    [
        // Doutes : La page de l'Eurométropole sur le passage au SERM n'est pas datée. Le jour exact du lancement du REME
        // (11 décembre 2022 selon des résultats de recherche) n'a pas été lu sur une page officielle, seul le mois est
        // retenu. Aucune étape datée d'infrastructure à venir n'a été trouvée.
        'id' => 'serm-strasbourg',
        'name' => 'RER métropolitain de Strasbourg (REME devenu SERM)',
        'kind' => 'rer',
        'regions' => ['Grand Est'],
        'porteurs' => [
            ['name' => 'Région Grand Est', 'role' => 'autorité organisatrice'],
            ['name' => 'Eurométropole de Strasbourg', 'role' => 'financeur'],
        ],
        'line_slugs' => [],
        'summary' => 'Le Réseau express métropolitain européen (REME), en service depuis décembre 2022, a ajouté environ 700 trains par semaine. Il est repris dans le cadre national des SERM à partir de 2026, avec un partage à parts égales du surcoût d\'exploitation entre la Région et l\'Eurométropole pour 2026-2028.',
        'stages' => [
            ['kind' => 'service', 'date' => '2022-12', 'done' => true, 'note' => 'première hausse de l\'offre TER du REME en décembre 2022', 'source' => ['label' => 'Ministère de la Transition écologique, page SERM', 'url' => 'https://www.ecologie.gouv.fr/politiques-publiques/services-express-regionaux-metropolitains-serm']],
            ['kind' => 'study', 'date' => '2024-06-27', 'done' => true, 'note' => 'Strasbourg figure parmi les 15 projets de la première vague de labellisation', 'source' => ['label' => 'Ministère de la Transition écologique, communiqué de la première vague de labellisation', 'url' => 'https://www.ecologie.gouv.fr/presse/premiere-vague-labellisation-15-projets-services-express-regionaux-metropolitains-serm', 'published' => '2024-06-27']],
            ['kind' => 'funding', 'date' => '2026', 'done' => true, 'note' => 'la Région et l\'Eurométropole prennent chacune la moitié du surcoût d\'exploitation ferroviaire 2026-2028 (environ 9,5 M EUR par an pour l\'Eurométropole) ; le SERM prend le relais du REME en 2026', 'source' => ['label' => 'Eurométropole de Strasbourg, Le REME devient le SERM', 'url' => 'https://www.strasbourg.eu/-/bloc-notes-le-reme-devient-le-serm-une-nouvelle-etape-pour-les-mobilites-du-bassin-de-vie-strasbourgeois']],
        ],
        'checked' => '2026-11-23',
    ],
    [
        // Doutes : Divergence sur le début des travaux : un résultat de presse locale cite avril 2025, l'article du 13
        // juillet 2026 parle de juillet 2026 ; la date retenue est celle de la pose du premier poteau. La mi-2028 et
        // août 2028 diffèrent dans le même article. La phase 2 n'est pas terminée, donc le projet n'est pas écarté. Le
        // montant du financement n'a pas été trouvé.
        'id' => 'electrification-paris-troyes-phase-2',
        'name' => 'Électrification de Paris - Troyes, phase 2 (Nogent-sur-Seine - Troyes)',
        'kind' => 'electrification',
        'regions' => ['Grand Est', 'Île-de-France'],
        'porteurs' => [
            ['name' => 'SNCF Réseau', 'role' => 'maître d\'ouvrage'],
            ['name' => 'Région Grand Est', 'role' => 'financeur'],
            ['name' => 'Région Île-de-France', 'role' => 'financeur'],
        ],
        'line_slugs' => ['rfn-1000'],
        'summary' => 'Seconde phase de l\'électrification de la ligne Paris - Troyes entre Nogent-sur-Seine et Troyes, après la phase Gretz-Armainvilliers - Nogent-sur-Seine et l\'antenne de Provins mises en service en septembre 2022.',
        'stages' => [
            ['kind' => 'service', 'date' => '2022-09', 'done' => true, 'note' => 'mise en service de la phase 1 (Gretz-Armainvilliers - Nogent-sur-Seine et Longueville - Provins)', 'source' => ['label' => 'Cour des comptes via vie-publique.fr (rapport)', 'url' => 'https://www.vie-publique.fr/files/rapport/pdf/296504.pdf']],
            ['kind' => 'works', 'date' => '2026-07', 'done' => true, 'note' => 'pose du premier poteau caténaire de la phase 2 dans l\'Aube ; entreprises TSO Catenaires et Colas Rail', 'source' => ['label' => 'info.fr, Ligne Paris-Troyes : premier poteau caténaire posé dans l\'Aube', 'url' => 'https://info.fr/ligne-paris-troyes-premier-poteau-catenaire-pose-aube/', 'published' => '2026-07-13']],
            ['kind' => 'funding', 'date' => '2026', 'done' => true, 'note' => 'action régionale votée en 2026 (délibération CP2026-062) pour la tranche 3, phase 2', 'source' => ['label' => 'Région Île-de-France, fiche Ligne Paris-Troyes : électrification entre Gretz-Armainvilliers et Troyes', 'url' => 'https://www.iledefrance.fr/tous-les-services/ma-region-pres-de-chez-moi/transport-et-mobilite/transports-en-commun/253321-ligne-paris-troyes-electrification-entre-gretz-armainvilliers-et-troyes']],
            ['kind' => 'commissioning', 'date' => '2028', 'done' => false, 'note' => 'mise en service visée mi-2028 (août 2028 dans le corps de l\'article)', 'source' => ['label' => 'info.fr, Ligne Paris-Troyes : premier poteau caténaire posé dans l\'Aube', 'url' => 'https://info.fr/ligne-paris-troyes-premier-poteau-catenaire-pose-aube/', 'published' => '2026-07-13']],
        ],
        'checked' => '2026-11-23',
    ],
    [
        // Doutes : Aucune date officielle de mise en service de la traction électrique n'a été lue (une ancienne fiche
        // régionale évoquerait 2030, non vérifiée). Le document de janvier 2026 parle de préparation de
        // l'électrification ; les travaux de pose de caténaires proprement dits ne sont pas datés. Le jour de la
        // décision régionale CP2024-306 n'est pas indiqué.
        'id' => 'electrification-trilport-la-ferte-milon',
        'name' => 'Électrification de la ligne P entre Trilport et La Ferté-Milon',
        'kind' => 'electrification',
        'regions' => ['Île-de-France'],
        'porteurs' => [
            ['name' => 'SNCF Réseau', 'role' => 'maître d\'ouvrage'],
            ['name' => 'Région Île-de-France', 'role' => 'financeur'],
            ['name' => 'Île-de-France Mobilités', 'role' => 'autorité organisatrice'],
            ['name' => 'État', 'role' => 'financeur'],
        ],
        'line_slugs' => [],
        'summary' => 'Électrification de 60 km de voie entre Trilport et La Ferté-Milon, dernière ligne non électrifiée du réseau francilien, pour un coût annoncé de 160 millions d\'euros, avec 1 500 supports de caténaire.',
        'stages' => [
            ['kind' => 'funding', 'date' => '2024-06-11', 'done' => true, 'note' => 'réponse ministérielle : accord sur le financement de la régénération de l\'axe, préalable à l\'électrification, et études d\'avant-projet à lancer par SNCF Réseau', 'source' => ['label' => 'Assemblée nationale, question écrite n° 11301 et réponse publiée au JO le 11 juin 2024', 'url' => 'https://www.assemblee-nationale.fr/dyn/16/questions/QANR5L16QE11301.pdf', 'published' => '2024-06-11']],
            ['kind' => 'funding', 'date' => '2024', 'done' => true, 'note' => 'action régionale votée en 2024 (délibération CP2024-306) pour l\'électrification Trilport - La Ferté-Milon', 'source' => ['label' => 'Région Île-de-France, fiche Ligne Transilien P : électrification entre Paris et La Ferté-Milon', 'url' => 'https://www.iledefrance.fr/tous-les-services/ma-region-pres-de-chez-moi/transport-et-mobilite/transports-en-commun/210587-ligne-transilien-p-electrification-entre-paris-et-la-ferte-milon']],
            ['kind' => 'works', 'date' => '2026-01', 'done' => true, 'note' => 'SNCF Réseau publie sa première infolettre de chantier : travaux de nuit de mi-janvier à fin avril 2026, puis travaux en continu du 13 juillet au 9 août 2026 (remplacement des rails)', 'source' => ['label' => 'SNCF Réseau, infolettre n° 1 (janvier 2026)', 'url' => 'https://www.paysdelourcq.fr/app/uploads/2026/01/Infolettre-chantier-SNCF-ligne-P-Trilport-LFM.pdf', 'published' => '2026-01']],
        ],
        'checked' => '2026-11-23',
    ],
    [
        // Doutes : L'état d'avancement en 2025-2026 n'a pas été confirmé : un avenant n° 3 à la convention de
        // financement a été déposé en octobre 2024 mais son contenu n'a pas été lu. L'étape de 2020 est issue d'un
        // résultat de recherche citant explicitement la délibération. Une échéance de fin 2029 pour la modernisation de
        // la ligne vient d'un résultat de recherche non vérifié.
        'id' => 'electrification-marseille-aix',
        'name' => 'Électrification partielle (dite frugale) de Marseille - Aix-en-Provence',
        'kind' => 'electrification',
        'regions' => ['Provence-Alpes-Côte d\'Azur'],
        'porteurs' => [
            ['name' => 'Région Provence-Alpes-Côte d\'Azur', 'role' => 'autorité organisatrice'],
            ['name' => 'Métropole Aix-Marseille-Provence, Département des Bouches-du-Rhône', 'role' => 'financeur'],
            ['name' => 'SNCF Réseau', 'role' => 'maître d\'ouvrage'],
        ],
        'line_slugs' => [],
        'summary' => 'Après une étude de 2018, électrification partielle de la ligne pour faire circuler des trains à batteries, en remplacement d\'une électrification complète. Des conventions de financement ont été signées dès décembre 2020.',
        'stages' => [
            ['kind' => 'funding', 'date' => '2020-12', 'done' => true, 'note' => 'convention de financement d\'une première phase de travaux d\'électrification frugale (avenant n° 1 à la convention de modernisation Marseille - Gardanne - Aix)', 'source' => ['label' => 'Département des Bouches-du-Rhône, délibération de la commission permanente du 11 décembre 2020', 'url' => 'https://www.departement13.fr/uploads/delibs/P0ZKU.pdf']],
        ],
        'checked' => '2026-11-23',
    ],
    [
        // Doutes : Les dates 2013, 2024 et 2027 proviennent de la presse et non d'une page officielle d'Île-de-France
        // Mobilités (inaccessible) ni de Légifrance : à recouper avec le décret de DUP. L'ancien objectif de fin 2026 a
        // été repoussé au 28 février 2027 d'après les résultats de recherche. La ville de Mantes-la-Jolie date l'annonce
        // du 2 juin 2026 (Salon des maires), page non lue.
        'id' => 'rer-e-eole-mantes-la-jolie',
        'name' => 'Prolongement du RER E (Eole) de Nanterre à Mantes-la-Jolie',
        'kind' => 'rer',
        'regions' => ['Île-de-France'],
        'porteurs' => [
            ['name' => 'Île-de-France Mobilités', 'role' => 'autorité organisatrice'],
            ['name' => 'SNCF Réseau', 'role' => 'maître d\'ouvrage'],
            ['name' => 'État, Région Île-de-France et collectivités partenaires', 'role' => 'financeur'],
        ],
        'line_slugs' => ['rfn-340000'],
        'summary' => 'Prolongement du RER E vers l\'ouest : la première phase jusqu\'à Nanterre-La Folie est ouverte, la seconde phase jusqu\'à Mantes-la-Jolie doit ouvrir commercialement le 28 février 2027 avec quatre trains par heure.',
        'stages' => [
            ['kind' => 'dup', 'date' => '2013-01-31', 'done' => true, 'note' => 'déclaration d\'utilité publique citée par la presse spécialisée', 'source' => ['label' => 'Sortiraparis, RER E : quand le prolongement vers Mantes-la-Jolie ?', 'url' => 'https://www.sortiraparis.com/en/news/in-paris/articles/332366-rer-e-when-will-the-extension-to-mantes-la-jolie-enter-service', 'published' => '2026-11-09']],
            ['kind' => 'service', 'date' => '2024-05', 'done' => true, 'note' => 'ouverture de la première phase (Neuilly-Porte Maillot, La Défense, Nanterre-La Folie) ; service sans correspondance toute la journée en décembre 2024', 'source' => ['label' => 'Sortiraparis, RER E : quand le prolongement vers Mantes-la-Jolie ?', 'url' => 'https://www.sortiraparis.com/en/news/in-paris/articles/332366-rer-e-when-will-the-extension-to-mantes-la-jolie-enter-service', 'published' => '2026-11-09']],
            ['kind' => 'works', 'date' => '2026-11-06', 'done' => true, 'note' => 'inauguration de l\'atelier de maintenance de Mantes-la-Jolie (209 M EUR, financé par les huit partenaires du projet)', 'source' => ['label' => 'Batiweb, atelier de maintenance du RER E à Mantes-la-Jolie', 'url' => 'https://www.batiweb.com/actualites/urbanisme/atelier-maintenance-rer-e-mantes-la-jolie-209-millions-49578', 'published' => '2026-11-06']],
            ['kind' => 'commissioning', 'date' => '2027-02-28', 'done' => false, 'note' => 'arrivée commerciale du RER E à Mantes-la-Jolie annoncée par les porteurs du projet (prévue)', 'source' => ['label' => 'Batiweb, atelier de maintenance du RER E à Mantes-la-Jolie', 'url' => 'https://www.batiweb.com/actualites/urbanisme/atelier-maintenance-rer-e-mantes-la-jolie-209-millions-49578', 'published' => '2026-11-06']],
            ['kind' => 'commissioning', 'date' => '2029', 'done' => false, 'note' => 'mise en service définitive complète à fin 2029 (prévue)', 'source' => ['label' => 'Batiweb, atelier de maintenance du RER E à Mantes-la-Jolie', 'url' => 'https://www.batiweb.com/actualites/urbanisme/atelier-maintenance-rer-e-mantes-la-jolie-209-millions-49578', 'published' => '2026-11-06']],
        ],
        'checked' => '2026-11-23',
    ],
    [
        // Doutes : Le coût de 43 M EUR et le calendrier 2030-2032 viennent d'un article de février 2024 ; la répartition
        // État - Région à parts égales vient d'une synthèse de recherche sur la concertation de 2025 (page de la Région
        // non lue). Aucune source de novembre 2026 n'a confirmé l'ouverture de l'enquête publique ni la date de la DUP
        // (annoncée sur 2026-2027). La mise en service dépend de ce calendrier : elle n'est donc donnée que comme
        // fourchette prévue par la source.
        'id' => 'louviers-rouen',
        'name' => 'Réouverture de Louviers à Rouen (raccordement de Louviers)',
        'kind' => 'reopening',
        'regions' => ['Normandie'],
        'porteurs' => [
            ['name' => 'SNCF Réseau', 'role' => 'maître d\'ouvrage'],
            ['name' => 'Région Normandie', 'role' => 'autorité organisatrice'],
            ['name' => 'État', 'role' => 'financeur'],
        ],
        'line_slugs' => [],
        'summary' => 'Projet de desserte voyageurs de Louviers par réhabilitation de 5,5 km d\'ancienne voie, un raccordement d\'environ 500 m à l\'axe Paris - Rouen, l\'électrification et une nouvelle halte à Louviers (environ 43 M EUR selon la Région). Après la concertation de 2025, une procédure de déclaration d\'utilité publique est annoncée sur 2026-2027.',
        'stages' => [
            ['kind' => 'study', 'date' => '2021-06', 'done' => true, 'note' => 'annonce du projet par la Région Normandie après une étude d\'opportunité et de faisabilité jugée concluante', 'source' => ['label' => 'Le Journal des Entreprises, Réouverture de la liaison ferroviaire Louviers - Rouen', 'url' => 'https://www.lejournaldesentreprises.com/breve/reouverture-de-la-liaison-ferroviaire-louviers-rouen-2080810', 'published' => '2024-02-09']],
            ['kind' => 'debate', 'date' => '2025-06-02', 'done' => true, 'note' => 'concertation publique du 2 juin au 11 juillet 2025 (réunions publiques et ateliers de terrain)', 'source' => ['label' => 'RCF, Louviers - Rouen en train : la concertation publique est lancée', 'url' => 'https://www.rcf.fr/articles/actualite/louviersrouen-en-train-la-concertation-publique-est-lancee']],
            ['kind' => 'dup', 'date' => '2026', 'done' => false, 'note' => 'procédure de déclaration d\'utilité publique annoncée sur la période 2026-2027 (prévue)', 'source' => ['label' => 'Le Journal des Entreprises, Réouverture de la liaison ferroviaire Louviers - Rouen', 'url' => 'https://www.lejournaldesentreprises.com/breve/reouverture-de-la-liaison-ferroviaire-louviers-rouen-2080810', 'published' => '2024-02-09']],
            ['kind' => 'commissioning', 'date' => '2030', 'done' => false, 'note' => 'mise en service prévue entre 2030 et 2032 selon la Région (un train par heure, une demi-heure avec le futur RER métropolitain)', 'source' => ['label' => 'Le Journal des Entreprises, Réouverture de la liaison ferroviaire Louviers - Rouen', 'url' => 'https://www.lejournaldesentreprises.com/breve/reouverture-de-la-liaison-ferroviaire-louviers-rouen-2080810', 'published' => '2024-02-09']],
        ],
        'checked' => '2026-11-23',
    ],
    [
        // Vérifié le 23/11/2026 dans le communiqué du 22/01/2026 (texte extrait du PDF) : « Démarrés le 17 décembre
        // 2025, après l'obtention de l'ensemble des autorisations administratives » et « jusqu'à la réouverture au
        // service annuel 2028 », qui commence le dimanche 12/12/2027. Les sources de financement du chantier ne sont
        // pas détaillées dans les pages lues.
        'id' => 'nancy-contrexeville',
        'name' => 'Réouverture de Nancy à Contrexéville (ligne 14)',
        'kind' => 'reopening',
        'regions' => ['Grand Est'],
        'porteurs' => [
            ['name' => 'Région Grand Est', 'role' => 'autorité concédante'],
            ['name' => 'Nova 14 (NGE Concessions, Transdev, Banque des Territoires)', 'role' => 'concessionnaire'],
        ],
        'line_slugs' => [],
        'summary' => 'Régénération complète de 75 km de voie pour rouvrir la ligne aux voyageurs, dans le cadre d\'une concession de 22 ans signée le 28 juin 2024 par la Région Grand Est avec Nova 14 (environ 150 M EUR de chantier, centre de maintenance à Mirecourt). Les travaux ont commencé en décembre 2025 ; la reprise du trafic est programmée pour le 12 décembre 2027.',
        'stages' => [
            ['kind' => 'funding', 'date' => '2024-06-28', 'done' => true, 'note' => 'signature du contrat de concession (22 ans) entre la Région Grand Est et Nova 14 ; capital réparti entre NGE Concessions (50 %), Transdev (20 %) et la Banque des Territoires (30 %)', 'source' => ['label' => 'Transdev, communiqué sur le nouveau groupement pour la ligne Nancy - Contrexéville', 'url' => 'https://www.transdev.com/wp-content/uploads/2024/06/06-04-24_cpresse_nouveau_groupement_ligne_nancy-contrexeville.pdf', 'published' => '2024-06-04']],
            ['kind' => 'funding', 'date' => '2024', 'done' => true, 'note' => 'contrat de financement de 90 M EUR de la Banque européenne d\'investissement pour la réouverture', 'source' => ['label' => 'Banque européenne d\'investissement, communiqué 2024-271', 'url' => 'https://www.eib.org/fr/press/all/2024-271-france-la-bei-signe-un-contrat-de-financement-de-90-millions-d-euros-pour-la-reouverture-de-la-ligne-ferroviaire-regionale-nancy-contrexeville']],
            ['kind' => 'works', 'date' => '2025-12-17', 'done' => true, 'note' => 'démarrage des travaux de régénération après l\'obtention des autorisations administratives', 'source' => ['label' => 'Nova 14 et Banque des Territoires, communiqué de lancement du chantier (22/01/2026)', 'url' => 'https://www.banquedesterritoires.fr/sites/default/files/2026-01/01-22-26_CPresse_Lancement_chantier_ligne_14_Nancy_Contrex%C3%A9ville.pdf', 'published' => '2026-01-22']],
            ['kind' => 'commissioning', 'date' => '2027-12-12', 'done' => false, 'note' => 'reprise du trafic voyageurs programmée (14 allers-retours quotidiens annoncés, Nancy - Contrexéville en 1 h 10) (prévue)', 'source' => ['label' => 'Le Journal des Entreprises, Nova 14 engage un chantier à 150 millions d\'euros', 'url' => 'https://www.lejournaldesentreprises.com/article/le-concessionnaire-de-la-ligne-nancy-contrexeville-nova-14-engage-un-chantier-150-millions-deuros-2125277', 'published' => '2025-08-29']],
        ],
        'checked' => '2026-11-23',
    ],
];
