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
            'insee' => 'INSEE, Code officiel géographique (codes et noms des départements et des régions), Licence Ouverte 2.0',
            'gallica' => 'Source : gallica.bnf.fr / BnF ; métadonnées des notices de la Bibliothèque nationale de France, Licence Ouverte, avec la date de récupération (documents numérisés : conditions d’utilisation de Gallica)',
            'sncf-archives' => 'Source : SNCF, Service Archives Documentation du groupe SNCF (jeu de données « Archives SNCF »), Open Database License (ODbL) ; documents numérisés : conditions d’utilisation d’archives.sncf.com',
        ],
    ],

    'errors' => [
        'invalid_day' => 'Jour invalide : donnez un mois (1 à 12) et un jour du mois qui existent, tous les deux ou aucun (par défaut : aujourd’hui).',
        'invalid_period' => 'Période invalide : donnez soit `year`, soit `decade` (multiple de 10), dans l’étendue de la chronologie (1827 à aujourd’hui).',
        'unknown_arguments' => 'Arguments inconnus : :names.',
        'line_not_found' => 'Ligne introuvable : « :query ». Cherchez son identifiant avec l’outil search (suggestions jointes).',
        'no_geometry' => 'Le tracé de « :line » n’existe pas pour l’ère :year : pas de point kilométrique.',
        'profile_not_computed' => 'Le profil en long de « :line » n’est pas encore calculé.',
        'audit_not_computed' => 'L’audit des tronçons de « :line » n’est pas encore calculé pour :year.',
        'station_not_found' => 'Gare introuvable : « :query ». Cherchez son identifiant avec l’outil search (suggestions jointes).',
        'material_not_found' => 'Série de matériel introuvable : « :query ». Cherchez son identifiant avec l’outil search ou materials (suggestions jointes).',
        'accident_not_found' => 'Accident introuvable : « :query ». Cherchez son identifiant avec l’outil accidents (filtre q).',
        'journal_not_found' => 'Article du journal introuvable : « :query ». La liste des articles s’obtient avec l’outil journal sans argument.',
        'era_not_found' => 'Pas de carte du réseau pour :year (voir les ères disponibles).',
        'same_era' => 'Les deux années tombent dans la même ère de la carte (:year) : choisissez deux décennies différentes (voir les ères disponibles).',
        'invalid_area' => 'Zone invalide : donnez soit un rectangle complet (south, west, north, east, avec north > south et east > west), soit un cercle complet (lat, lng, radius_km), pas les deux.',
        'area_too_large' => 'Zone trop grande : un rectangle fait au plus :height° de haut et :width° de large, un cercle au plus :radius km de rayon. Découpez la zone ou utilisez network_at_era pour la France entière.',
        'area_too_small' => 'Zone trop petite : un rectangle fait au moins :side° de côté, un cercle au moins :radius km de rayon (les tracés de certaines sources ne sont pas redistribuables).',
        'graph_not_built' => 'Le graphe ferroviaire de cette époque n’est pas encore calculé : itinéraire impossible pour le moment.',
        'ambiguous_station' => 'Plusieurs gares correspondent : précisez avec un slug parmi les candidates.',
        'same_station' => 'Départ et arrivée désignent la même gare.',
        'station_not_in_network' => 'Une des gares n’est pas desservie par le réseau en service à cette époque.',
        'no_path' => 'Aucun itinéraire entre :from et :to sur le réseau en service à cette époque.',
        'invalid_uri' => 'Adresse de ressource invalide : attendu vff://{lang}/lines/{slug}, lang = fr, en ou de.',
        'event_not_on_line' => 'L’événement :event n’appartient pas à la ligne :line. Prenez un identifiant donné par line_events ou par line_stories sans `event`.',
        'no_story' => 'L’événement :event n’a pas d’histoire : moins de 3 images et documents datés à 5 ans près (dont 2 images), ou type d’événement sans histoire.',
    ],

    'truncated' => 'Réponse raccourcie pour rester sous la taille maximale : affinez la demande (période, types, limite).',
    'truncated_page' => 'Page raccourcie pour rester sous la taille maximale : next_cursor reprend au premier élément non renvoyé, rien n\'est perdu.',
    'redirected' => [
        'line' => 'L’identifiant « :requested » est l’ancienne adresse d’une ligne renommée : la réponse est celle de sa fiche actuelle, « :current ». Utilisez désormais « :current » et dites-le à l’utilisateur s’il a donné l’ancien.',
        'station' => 'L’identifiant « :requested » est l’ancienne adresse d’une gare fondue dans une autre fiche (même gare) : la réponse est celle de la fiche actuelle, « :current ». Utilisez désormais « :current ».',
        'accident' => 'L’identifiant « :requested » est l’ancienne adresse d’une fiche d’accident renommée ou fusionnée (même accident) : la réponse est celle de la fiche actuelle, « :current ». Utilisez désormais « :current ».',
    ],
    'between' => 'entre :from et :to (aucune source ne date ce changement)',
    'today' => 'aujourd’hui',
    'dates' => [
        'unknown_after' => 'date inconnue, après :year (aucune source ne la date)',
        'unknown' => 'date inconnue (aucune source ne la date)',
    ],

    'projects' => [
        'planned_note' => 'Une étape dont `done` vaut false est une date PRÉVUE par sa source, jamais certaine : écrivez « prévue en <date> selon <source.label> ». Un projet mis en service sort de cette liste.',
    ],

    'preserved' => [
        'coverage_note' => 'Liste des exemplaires que des sources disent conservés, pas un inventaire : un exemplaire absent n’est pas démoli pour autant, et l’état est celui que rapporte la source à sa date de lecture.',
    ],

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
        'signalling_history' => 'Signalisation et protection au fil du temps',
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

    // Invites (prompts) MCP : message envoyé à l'assistant, dans la langue demandée (app/Mcp/Prompts).
    'prompts' => [
        'language' => 'français',
        'present_line' => [
            'message' => 'Présentez la ligne « :line » avec les données de VFF (Voies Ferrées de France).
1. :identify
2. Lisez sa fiche avec get_line, événements compris. Si la chronologie est raccourcie (truncated), complétez-la avec line_events.
3. Rédigez quelques paragraphes : d\'où part la ligne et où elle va, sa longueur, son électrification et sa signalisation, les grandes dates (ouverture, fermetures, électrification, dépose) et son état aujourd\'hui.
4. Pour chaque date ou chiffre, citez sa source (champ source et lien source_url). Écrivez une date avec sa seule précision connue : jour, mois ou année. Une phase de précision « range » n\'est datée par aucune source : dites seulement qu\'elle a eu lieu entre deux décennies.
5. Écrivez avec vos propres mots, sans recopier de texte de Wikipédia ni d\'une autre source.
6. Terminez par le lien vff_url de la fiche et les mentions du bloc attribution.
Répondez en :language.',
            'found' => 'La ligne est « :name » (identifiant :slug) : passez cet identifiant aux outils. Sa fiche en Markdown est aussi dans la ressource vff://:lang/lines/:slug.',
            'search' => 'Cherchez son identifiant avec search {"query": ":line", "kinds": ["lines"]}. Si plusieurs lignes correspondent, demandez laquelle avant de continuer.',
        ],
        'compare_region_eras' => [
            'message' => 'Comparez le réseau ferré de la région :region (code INSEE :code) en :from et en :to, avec les données de VFF. Départements de la région : :departments.
1. Pour chaque époque, comptez les lignes ouvertes et fermées avec list_lines {"region": ":code", "era": <année>, "facets": true, "limit": 1}, et les gares par statut avec list_stations {"region": ":code", "era": <année>, "facets": true, "limit": 1}.
2. Relevez ce qui a changé entre les deux dates avec network_events {"region": ":code", "from": ":events_from", "to": ":events_to"} (fermetures, ouvertures, électrifications, déposes), en suivant next_cursor tant que has_more vaut true.
3. Pour situer la région dans le pays, compare_eras {"from": :from, "to": :to} donne les mêmes chiffres pour toute la France.
4. Présentez un tableau des deux époques, puis les principaux changements, ligne par ligne, chacun avec sa date, sa précision et sa source.
5. Précisez qu\'une ligne compte dans la région si elle y dessert une gare ou si sa fiche cite l\'un de ses départements. La carte de VFF est un instantané au 31 décembre de chaque décennie ; :era_note
6. Terminez par les mentions du bloc attribution.
Répondez en :language.',
            'current' => 'l\'époque :to décrit le réseau d\'aujourd\'hui.',
            'past' => 'les deux époques sont des instantanés au 31 décembre de leur année.',
        ],
        'station_story' => [
            'message' => 'Racontez l\'histoire de la gare « :station » avec les données de VFF.
1. :identify
2. Lisez sa fiche avec get_station : dates d\'ouverture et de fermeture, statut à chaque décennie, lignes desservies avec leurs années de desserte, sources.
3. Pour les lignes qui la desservent, get_line (avec include_events à false) donne leur ouverture et leur fermeture. Ajoutez les accidents survenus près de la gare avec accidents {"station": "<identifiant>"} et des images d\'archives avec media {"station": "<identifiant>"}.
4. Racontez dans l\'ordre : l\'ouverture, les lignes qui arrivent ou ferment, les changements de statut, la situation d\'aujourd\'hui. Une décennie au statut « uncertain » n\'est pas confirmée par les voies de l\'époque : signalez-le.
5. Donnez chaque date avec sa précision et sa source, écrivez avec vos propres mots sans recopier de texte d\'une source, et montrez chaque image avec son attribution.
6. Terminez par le lien vff_url de la fiche et les mentions du bloc attribution.
Répondez en :language.',
            'found' => 'La gare est « :name » (:city, identifiant :slug) : passez cet identifiant aux outils.',
            'search' => 'Cherchez son identifiant avec search {"query": ":station", "kinds": ["stations"]}. Plusieurs gares portent parfois le même nom : en cas de doute, demandez laquelle avant de continuer.',
        ],
    ],
];
