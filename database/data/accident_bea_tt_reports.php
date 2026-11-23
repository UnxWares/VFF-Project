<?php

/**
 * Lecture des pages et des rapports d'enquête du BEA-TT (www.bea-tt.developpement-durable.gouv.fr), rangée par rangée du
 * « Bilan général des enquêtes » (accident_sources, fournisseur `bea-tt`). Le bilan est un tableau PDF dont l'intitulé ne
 * donne pas toujours la commune (« Rattrapage gare de Tencin-Theys », « Électrocution sur caténaire à Saint Nazaire ») et
 * dont la date est parfois décalée d'un jour (Hochfelden, Bizanos, Laroche-Migennes, Saint-Florentin). Chaque ligne ci-dessous
 * est une rangée du bilan dont la page de l'enquête (et, pour quatre d'entre elles, le rapport) a été relue le 23/11/2026,
 * une requête toutes les 5 secondes avec le User-Agent du projet, pages publiques (Licence Ouverte 2.0).
 *
 *   - clé          : lien de la rangée dans le bilan (accident_sources.url), jamais un lien recomposé ;
 *   - slug         : fiche concernée, pour mémoire (la clé fait foi) ;
 *   - commune_insee: commune que la page de l'enquête nomme, code du référentiel des communes de VFF (absent quand la page
 *                    ne nomme pas de commune que la fiche porte, voir `place`) ;
 *   - date         : jour que la page ET le rapport donnent, seulement quand il diffère de celui du bilan ;
 *   - place        : lieu tel que la page l'écrit, en quelques mots (gare, passage à niveau) ;
 *   - report       : le document officiel (title, number si le rapport le porte, url, published).
 *
 * La vérification (AccidentClaimReader) lit cette table : la rangée du bilan donne alors une commune et le jour de la page ;
 * un jour différent de celui de la fiche est un désaccord signalé, jamais corrigé en silence. Aucun texte de rapport n'est
 * recopié. Test : BeaTtReportTableTest.
 */
return [
    'https://www.bea-tt.developpement-durable.gouv.fr/saint-nazaire-44-17-04-2004-electrocution-d-une-r20.html' => [
        'slug' => '2004-04-17-saint-nazaire',
        'commune_insee' => '44184',
        'place' => 'Saint-Nazaire (44)',
        'report' => [
            'title' => 'Rapport d’enquête sur l’électrocution d’une personne le 17/04/2004 en gare de triage de Saint-Nazaire',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/rapport-d-enquete-sur-l-electrocution-d-une-a7.html',
            'published' => '2005-08-29',
        ],
    ],
    'https://www.bea-tt.developpement-durable.gouv.fr/longueville-77-16-02-2005-collision-de-deux-trains-r38.html' => [
        'slug' => '2005-02-16-longueville',
        'commune_insee' => '77260',
        'place' => 'Longueville (77)',
        'report' => [
            'title' => 'Rapport d’enquête sur la collision entre deux trains de voyageurs le 16/02/2005 à Longueville',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/rapport-d-enquete-sur-la-collision-entre-deux-a51.html',
            'published' => '2006-01-23',
        ],
    ],
    'https://www.bea-tt.developpement-durable.gouv.fr/tencins-theys-38-28-06-2006-quasi-rattrapage-de-r67.html' => [
        'slug' => '2006-06-28-tencin',
        'commune_insee' => '38501',
        'place' => 'Tencins-Theys (38), gare de Tencin-Theys',
        'report' => [
            'title' => 'Rapport d’enquête sur le quasi-rattrapage de deux trains le 28/06/2006 à Tencins-Theys',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/rapport-d-enquete-sur-le-quasi-rattrapage-de-deux-a137.html',
            'published' => '2008-01-16',
        ],
    ],
    'https://www.bea-tt.developpement-durable.gouv.fr/villeneuve-triage-94-1-03-2007-accident-mortel-d-r73.html' => [
        'slug' => '2007-03-01-villeneuve-saint-georges',
        'commune_insee' => '94078',
        'place' => 'Villeneuve-Triage (94), gare de Villeneuve-Triage à Villeneuve-Saint-Georges',
        'report' => [
            'title' => 'Rapport d’enquête sur l’accident mortel d’une voyageuse le 1/03/2007 à Villeneuve-Triage',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/rapport-d-enquete-sur-l-accident-mortel-d-une-a158.html',
            'published' => '2008-04-01',
        ],
    ],
    'https://www.bea-tt.developpement-durable.gouv.fr/paris-est-75-5-04-2007-accostage-brutal-d-un-train-r69.html' => [
        'slug' => '2007-04-05-paris',
        'commune_insee' => '75056',
        'place' => 'Paris-Est (75)',
        'report' => [
            'title' => 'Rapport d’enquête sur le choc d’un heurtoir par un Transilien le 5/04/2007 à Paris gare de l’Est',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/rapport-d-enquete-sur-le-choc-d-un-heurtoir-par-un-a143.html',
            'published' => '2008-02-13',
        ],
    ],
    'https://www.bea-tt.developpement-durable.gouv.fr/versailles-rive-gauche-78-13-08-2007-accostage-r77.html' => [
        'slug' => '2007-08-13-versailles',
        'commune_insee' => '78646',
        'place' => 'Versailles rive gauche (78)',
        'report' => [
            'title' => 'Rapport d’enquête sur le choc d’un heurtoir par un Transilien le 13/08/2007 à Versailles Rive gauche',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/rapport-d-enquete-sur-le-choc-d-un-heurtoir-par-un-a170.html',
            'published' => '2008-04-28',
        ],
    ],
    'https://www.bea-tt.developpement-durable.gouv.fr/bayard-52-26-02-2008-heurt-mortel-d-un-agent-r101.html' => [
        'slug' => '2008-02-26-bayard-sur-marne',
        'commune_insee' => '52265',
        'place' => 'Bayard (52), passage à niveau n° 37',
        'report' => [
            'title' => 'Rapport d’enquête technique sur le heurt de personnes le 26/02/2008 à Bayard sur le PN37',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/rapport-d-enquete-technique-sur-le-heurt-de-a211.html',
            'published' => '2009-03-18',
        ],
    ],
    'https://www.bea-tt.developpement-durable.gouv.fr/tunnel-de-livernant-16-20-05-2009-collision-entre-r119.html' => [
        'slug' => '2009-05-20-livernant',
        'commune_insee' => '16082',
        'place' => 'Tunnel de Livernant (16), à Charmant (commune déléguée de Boisné-La Tude)',
        'report' => [
            'title' => 'Rapport d’enquête sur la collision entre un train et le chargement d’un train croiseur le 20/05/2009',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/rapport-d-enquete-sur-la-collision-entre-un-train-a318.html',
            'published' => '2011-01-13',
        ],
    ],
    'https://www.bea-tt.developpement-durable.gouv.fr/bully-grenay-62-29-07-2010-deraillement-d-un-train-r137.html' => [
        'slug' => '2010-07-29-bully-les-mines',
        'commune_insee' => '62186',
        'place' => 'Bully-Grenay (62), gare de Bully-Grenay à Bully-les-Mines',
        'report' => [
            'title' => 'Rapport d’enquête sur le déraillement d’un train de charbon le 29/07/2010 à Bully-Grenay',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/rapport-d-enquete-sur-le-deraillement-d-un-train-a382.html',
            'published' => '2011-10-18',
        ],
    ],
    'https://www.bea-tt.developpement-durable.gouv.fr/valence-d-agen-82-20-10-2011-deraillement-de-trois-r164.html' => [
        'slug' => '2011-10-20-valence',
        'commune_insee' => '82186',
        'place' => 'Valence-d\'Agen (82)',
        'report' => [
            'title' => 'Rapport d’enquête sur le déraillement de trois wagons le 20/10/2011 à Valence-d’Agen',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/rapport-d-enquete-sur-le-deraillement-de-trois-a476.html',
            'published' => '2012-12-17',
        ],
    ],
    'https://www.bea-tt.developpement-durable.gouv.fr/laroche-migennes-89-1-12-2015-deraillement-d-un-r242.html' => [
        'slug' => '2015-12-02-migennes',
        'commune_insee' => '89257',
        'date' => '2015-12-01',
        'place' => 'Laroche-Migennes (89)',
        'report' => [
            'title' => 'Rapport d’enquête sur le déraillement d’un TER le 1/12/2015 à Laroche-Migennes',
            'number' => 'BEATT-2015-017',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/rapport-d-enquete-sur-le-deraillement-d-un-ter-le-a785.html',
            'published' => '2016-12-20',
        ],
    ],
    'https://www.bea-tt.developpement-durable.gouv.fr/saint-aunes-34-17-08-2016-collision-d-un-ter-avec-r248.html' => [
        'slug' => '2016-08-17-saint-aunes',
        'commune_insee' => '34240',
        'place' => 'Saint-Aunès (34)',
        'report' => [
            'title' => 'Clôture de l\'enquête sur la collision d\'un TER avec un arbre sur la voie le 17/08/2016 à Saint-Aunès',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/saint-aunes-34-17-08-2016-collision-d-un-ter-avec-r248.html',
            'published' => '2016-11-09',
        ],
    ],
    'https://www.bea-tt.developpement-durable.gouv.fr/jonzac-17-13-12-2016-ruptures-multiples-de-rails-r252.html' => [
        'slug' => '2016-12-13-jonzac',
        'commune_insee' => '17197',
        'place' => 'Jonzac (17)',
        'report' => [
            'title' => 'Rapport d’enquête sur les ruptures multiples de rails le 13/12/2016 à Jonzac',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/rapport-d-enquete-sur-les-ruptures-multiples-de-a835.html',
            'published' => '2017-11-30',
        ],
    ],
    'https://www.bea-tt.developpement-durable.gouv.fr/solaize-69-13-03-2017-deraillement-d-un-train-de-r259.html' => [
        'slug' => '2017-03-13-solaize',
        'commune_insee' => '69296',
        'place' => 'Solaize (69), gare de triage de Sibelin à Solaize',
        'report' => [
            'title' => 'Rapport sur le déraillement d’un train de marchandises dangereuses le 13/03/2017 à Solaize',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/rapport-sur-le-deraillement-d-un-train-de-a908.html',
            'published' => '2019-03-18',
        ],
    ],
    'https://www.bea-tt.developpement-durable.gouv.fr/bonneville-sur-touques-14-2-11-2017-collision-r263.html' => [
        'slug' => '2017-11-02-bonneville-sur-touques',
        'commune_insee' => '14086',
        'place' => 'Bonneville-sur-Touques (14), passage à niveau n° 8',
        'report' => [
            'title' => 'Rapport d’enquête sur la collision entre un TER et une automobile à Bonneville-sur-Touques le 2/11/2017',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/rapport-d-enquete-sur-la-collision-entre-un-ter-et-a937.html',
            'published' => '2019-08-06',
        ],
    ],
    'https://www.bea-tt.developpement-durable.gouv.fr/marseille-13-24-08-2018-deraillement-d-un-tgv-en-r271.html' => [
        'slug' => '2018-08-24-marseille',
        'commune_insee' => '13055',
        'place' => 'Marseille (13), gare de Marseille-Saint-Charles',
        'report' => [
            'title' => 'Rapport d’enquête sur le déraillement d’un TGV le 24/08/2018 en gare de Marseille',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/rapport-d-enquete-sur-le-deraillement-d-un-tgv-le-a948.html',
            'published' => '2019-12-11',
        ],
    ],
    'https://www.bea-tt.developpement-durable.gouv.fr/denfert-rochereau-75-24-06-2020-deraillement-d-une-r316.html' => [
        'slug' => '2020-06-24-paris',
        'commune_insee' => '75056',
        'place' => 'Denfert-Rochereau (75)',
        'report' => [
            'title' => 'Rapport d’enquête sur le déraillement d’une rame du RER B à la station Denfert-Rochereau le 24/06/2020',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/rapport-d-enquete-sur-le-deraillement-d-une-rame-a1146.html',
            'published' => '2022-11-03',
        ],
    ],
    'https://www.bea-tt.developpement-durable.gouv.fr/hochfelden-67-24-02-2022-heurt-d-un-poids-lourd-r324.html' => [
        'slug' => '2022-02-24-hochfelden',
        'commune_insee' => '67202',
        'date' => '2022-02-24',
        'place' => 'Hochfelden (67)',
        'report' => [
            'title' => 'Rapport d’enquête technique sur le heurt d’un poids lourd par un TER le 24 février 2022 sur le PN 22 à Hochfelden (67)',
            'number' => 'BEATT-2022-01',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/rapport-d-enquete-technique-sur-le-heurt-d-un-a1314.html',
            'published' => '2024-08-07',
        ],
    ],
    'https://www.bea-tt.developpement-durable.gouv.fr/bizanos-64-29-05-2022-incident-entre-un-train-de-r334.html' => [
        'slug' => '2022-05-29-bizanos',
        'commune_insee' => '64132',
        'date' => '2022-05-29',
        'place' => 'Bizanos (64)',
        'report' => [
            'title' => 'Rapport d’enquête sur l’incident entre un train de voyageurs et un autocar, le 29/05/2022 à Bizanos',
            'number' => 'BEATT-2022-05',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/rapport-d-enquete-sur-l-incident-entre-un-train-de-a1259.html',
            'published' => '2023-12-22',
        ],
    ],
    'https://www.bea-tt.developpement-durable.gouv.fr/saint-florentin-89-17-01-2024-deraillement-d-un-r477.html' => [
        'slug' => '2024-01-18-saint-florentin',
        'date' => '2024-01-17',
        'place' => 'Saint-Florentin (89), base arrière travaux de Saint-Florentin-Vergigny',
        'report' => [
            'title' => 'Rapport d’enquête sur le déraillement d’un train de travaux le 17 janvier 2024 à Saint-Florentin-Vergigny',
            'number' => 'BEATT-2024-01',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/rapport-d-enquete-sur-le-deraillement-d-un-train-a1334.html',
            'published' => '2024-12-20',
        ],
    ],
    'https://www.bea-tt.developpement-durable.gouv.fr/metz-sablon-57-11-02-2024-collision-puis-r493.html' => [
        'slug' => '2024-02-11-metz',
        'commune_insee' => '57463',
        'place' => 'Metz-Sablon (57)',
        'report' => [
            'title' => 'Rapport d’enquête technique sur la collision et le déraillement d’un train de fret survenus le 11/02/2024 à Metz-Sablon (57)',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/rapport-d-enquete-technique-sur-la-collision-et-le-a1388.html',
            'published' => '2025-04-11',
        ],
    ],
    'https://www.bea-tt.developpement-durable.gouv.fr/oulchy-breny-02-30-10-2024-deraillement-d-un-train-r504.html' => [
        'slug' => '2024-10-30-breny',
        'commune_insee' => '02121',
        'place' => 'Oulchy-Breny (02), gare d\'Oulchy-Breny à Breny',
        'report' => [
            'title' => 'Rapport d’enquête technique sur le déraillement d’un train de fret survenu le 30/10/2024 à Oulchy-Breny',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/rapport-d-enquete-technique-sur-le-deraillement-d-a1495.html',
            'published' => '2026-08-07',
        ],
    ],
    'https://www.bea-tt.developpement-durable.gouv.fr/IMG/pdf/beatt_2025_08-paris-est_note-etape.pdf' => [
        'slug' => '2025-10-22-paris',
        'commune_insee' => '75056',
        'place' => 'Paris-Est (75)',
        'report' => [
            'title' => 'Note d\'étape sur l\'avancement de l\'enquête technique relative à la situation de collision transversale de deux trains de SNCF Voyageurs à Paris-Est (75)',
            'number' => 'BEATT-2025-08',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/IMG/pdf/beatt_2025_08-paris-est_note-etape.pdf',
            'published' => '2026-11',
        ],
    ],
    'https://www.bea-tt.developpement-durable.gouv.fr/IMG/pdf/beatt_2026_01_carentan-fiche_de_presentation.pdf' => [
        'slug' => '2026-01-11-carentan-les-marais',
        'commune_insee' => '50099',
        'place' => 'Carentan (50)',
        'report' => [
            'title' => 'Déraillement d\'un train de transport combiné survenu le 11 janvier 2026 à Carentan (Manche), fiche de présentation',
            'number' => 'BEATT-2026-01',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/IMG/pdf/beatt_2026_01_carentan-fiche_de_presentation.pdf',
            'published' => '2026-01',
        ],
    ],
];
