<?php

// Serveur MCP (app/Mcp, docs/MCP.md) : textes des réponses aux assistants IA. Fichier côté serveur
// seulement (App\Support\Translations::SERVER_ONLY), jamais envoyé aux pages.
return [
    'attribution' => [
        'notice' => 'Données de Voies Ferrées de France (vff-project.org), base sous licence ODbL 1.0 : citez « Voies Ferrées de France (vff-project.org) », le lien de la fiche et les sources ci-dessous.',
        'terms' => 'Les données de VFF sont réutilisables, y compris à des fins commerciales, sous Open Database License (ODbL) 1.0 : citez « Voies Ferrées de France (vff-project.org) » et ses sources, dont « © contributeurs OpenStreetMap », et publiez sous la même licence toute base de données dérivée. Exception : les données d’une source qui n’autorise qu’un usage non commercial (champ source) ne sont réutilisables qu’à des fins non commerciales, en citant la source avec un lien.',
        'restricted' => '(pas de licence ouverte : reprise avec cette mention et le lien de la source seulement)',
        'providers' => [
            'vff' => 'Voies Ferrées de France (vff-project.org), UnxWares, ODbL 1.0',
            'osm' => '© contributeurs OpenStreetMap, ODbL 1.0 (https://www.openstreetmap.org/copyright)',
            'sncf' => 'SNCF Open Data (SNCF Réseau, SNCF Voyageurs, SNCF Gares & Connexions), ODbL 1.0',
            'ohm' => 'OpenHistoricalMap, CC0 1.0',
            'ign' => 'IGN (BD TOPO, BD ORTHO historique), Licence Ouverte 2.0',
            'wikipedia' => 'Wikipédia, CC BY-SA 4.0 (textes) ; faits repris avec lien vers l’article',
            'wikidata' => 'Wikidata, CC0 1.0',
            'commons' => 'Wikimedia Commons, licence propre à chaque fichier',
            'communes' => 'Etalab et IGN ADMIN EXPRESS (contours administratifs), ODbL',
            'jorf' => 'DILA, Journal officiel (Légifrance), Licence Ouverte',
            'archeoferroviaire' => 'archeoferroviaire.free.fr (J. Randé, F. Librini, K. Moreau), usage non commercial, avec lien vers l’article',
            'lignes-oubliees' => 'lignes-oubliees.com, informations reproduites avec mention de l’origine et lien vers la fiche',
            'argentanwebferro' => 'Argentan Web Ferroviaire (Thierry Danglos), faits repris avec lien vers l’article',
            'bea-tt' => 'BEA-TT (Bureau d’enquêtes sur les accidents de transport terrestre), Licence Ouverte 2.0 (Etalab) ; bilan général des enquêtes et liens des rapports, avec la date de mise à jour du bilan',
        ],
    ],

    'errors' => [
        'unknown_arguments' => 'Arguments inconnus : :names.',
        'line_not_found' => 'Ligne introuvable : « :query ». Cherchez son identifiant avec l’outil search (suggestions jointes).',
        'profile_not_computed' => 'Le profil en long de « :line » n’est pas encore calculé.',
        'station_not_found' => 'Gare introuvable : « :query ». Cherchez son identifiant avec l’outil search (suggestions jointes).',
        'material_not_found' => 'Série de matériel introuvable : « :query ». Cherchez son identifiant avec l’outil search ou materials (suggestions jointes).',
        'accident_not_found' => 'Accident introuvable : « :query ». Cherchez son identifiant avec l’outil accidents (filtre q).',
        'era_not_found' => 'Pas de carte du réseau pour :year (voir les ères disponibles).',
        'same_era' => 'Les deux années tombent dans la même ère de la carte (:year) : choisissez deux décennies différentes (voir les ères disponibles).',
        'graph_not_built' => 'Le graphe ferroviaire de cette époque n’est pas encore calculé : itinéraire impossible pour le moment.',
        'ambiguous_station' => 'Plusieurs gares correspondent : précisez avec un slug parmi les candidates.',
        'same_station' => 'Départ et arrivée désignent la même gare.',
        'station_not_in_network' => 'Une des gares n’est pas desservie par le réseau en service à cette époque.',
        'no_path' => 'Aucun itinéraire entre :from et :to sur le réseau en service à cette époque.',
        'invalid_uri' => 'Adresse de ressource invalide : attendu vff://{lang}/lines/{slug}, lang = fr, en ou de.',
    ],

    'truncated' => 'Réponse raccourcie pour rester sous la taille maximale : affinez la demande (période, types, limite).',
    'truncated_page' => 'Page raccourcie pour rester sous la taille maximale : next_cursor reprend au premier élément non renvoyé, rien n\'est perdu.',
    'between' => 'entre :from et :to (aucune source ne date ce changement)',
    'today' => 'aujourd’hui',

    'accidents' => [
        'review_note' => 'Fiche « à vérifier » : au moins un point reste incertain (bilans ou dates qui diffèrent selon les sources, lieu connu à la commune près, ligne incertaine). Ne la présentez pas comme sûre : le détail est dans review.notes de get_accident.',
    ],

    'compare' => [
        'opened' => 'Ouvert ou rouvert entre les deux ères (en service à la seconde, pas à la première)',
        'closed' => 'Fermé voie en place à la seconde ère, alors qu’il ne l’était pas à la première',
        'removed' => 'Déposé ou disparu à la seconde ère (en service ou fermé à la première)',
        'kept' => 'En service aux deux ères',
    ],

    'traffic' => [
        'passenger' => 'Voyageurs seuls',
    ],

    'electrification' => [
        'none' => 'Non électrifiée',
        'marginal' => 'Électrifiée à la marge (voies d’une gare, raccordement)',
        'partial' => 'Partiellement électrifiée',
        'full' => 'Électrifiée',
    ],

    'route' => [
        'duration_note' => 'Durée indicative à 100 km/h de moyenne, pas un horaire historique.',
    ],

    'markdown' => [
        'code' => 'Code de ligne',
        'length' => 'Longueur du tracé',
        'in_service_today' => 'en service aujourd’hui',
        'max_speed' => 'Vitesse maximale',
        'electrification' => 'Électrification',
        'signalling' => 'Signalisation',
        'phases' => 'Phases',
        'chronology' => 'Chronologie',
        'source' => 'source',
        'more_events' => ':n autres événements : outil line_events.',
        'sources' => 'Sources et licences',
        'sheet' => 'Fiche VFF',
        'licences_title' => 'Licences et attributions des données de VFF',
        'licence' => 'Licence',
        'mention' => 'Mention',
    ],
];
