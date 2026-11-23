<?php

// « Ce jour-là » (/on-this-day) : textes de la page. Les faits viennent de App\Services\OnThisDay.
return [
    'meta_title' => 'Ce jour-là',
    'meta_description' => 'Ce qui s’est passé un jour du calendrier les années précédentes : ouvertures et fermetures de lignes et de gares, électrifications, mises en service, accidents, chaque fait avec son année, sa source et sa fiche.',
    'hero_title' => 'Ce jour-là',
    'hero_subtitle' => 'Pour un jour du calendrier, les faits du rail français que l’encyclopédie connaît à ce jour précis, année après année : lignes et gares ouvertes ou fermées, électrifications, mises en service. Chaque fait renvoie à sa fiche et à sa source.',
    'today_badge' => 'Aujourd’hui',

    'nav_label' => 'Choisir un jour',
    'prev_day' => 'Jour précédent : :date',
    'next_day' => 'Jour suivant : :date',
    'go_today' => 'Revenir à aujourd’hui',
    'pick_legend' => 'Une autre date',
    'month_label' => 'Mois',
    'day_label' => 'Jour',
    'go' => 'Afficher ce jour',

    'facts_title' => 'Un :date, les années précédentes',
    'count_one' => ':value fait connu au jour près',
    'count_other' => ':value faits connus au jour près',
    'empty' => 'Aucun fait n’est connu au jour près pour le :date.',
    'empty_hint' => 'Une date connue seulement à l’année ou au mois n’est jamais présentée comme un jour : elle reste dans la chronologie.',
    'nearest_prev' => 'Jour précédent avec des faits : :date',
    'nearest_next' => 'Jour suivant avec des faits : :date',
    'years_ago_one' => 'il y a :value an',
    'years_ago_other' => 'il y a :value ans',
    'this_year' => 'cette année',

    'kinds' => [
        'line' => [
            'opening' => 'Ouverture de ligne',
            'reopening' => 'Réouverture de ligne',
            'closure' => 'Fermeture de ligne',
            'electrification' => 'Électrification',
            'signalling' => 'Signalisation mise en service',
        ],
        'station' => [
            'opening' => 'Ouverture de gare',
            'reopening' => 'Réouverture de gare',
            'closure' => 'Fermeture de gare',
            'demolition' => 'Démolition de gare',
        ],
        'accident' => ['accident' => 'Accident'],
        'milestone' => ['milestone' => 'Jalon national'],
    ],
    'traffic' => [
        'passenger' => 'aux voyageurs',
        'freight' => 'au fret',
        'total' => 'à tout trafic',
    ],
    'deaths_one' => 'Bilan connu : :value mort',
    'deaths_other' => 'Bilan connu : :value morts',
    'source_one' => 'Source :',
    'source_other' => 'Sources :',
    'external' => '(site externe)',

    'accidents_hide' => 'Masquer les accidents',
    'accidents_show' => 'Afficher les accidents',
    'accidents_note' => 'Les accidents sont présentés sobrement, par respect pour les victimes et leurs proches : nom, lieu et bilan connu, avec leurs sources.',
    'accidents_hidden_one' => ':value accident est masqué pour ce jour.',
    'accidents_hidden_other' => ':value accidents sont masqués pour ce jour.',

    'method_title' => 'Ce que cette page affiche',
    'coverage' => ':with jours de l’année sur :days ont au moins un fait connu au jour près (:facts faits au total).',
    'method' => [
        'day' => 'Seules les dates connues au jour sont présentées ici. Quand deux sources datent un même fait de jours différents, ou qu’une seule donne le mois ou l’année, le fait reste dans la chronologie et n’apparaît pas à un jour.',
        'sources' => 'Chaque fait a au moins une source dont les faits peuvent être publiés, citée avec son lien. Les faits connus seulement par d’autres sources ne sont pas affichés.',
        'stations' => 'Les ouvertures et fermetures de gares sont celles que retient la fiche de la gare ; une date déduite de l’ouverture d’une ligne n’est pas un fait de la gare et n’est pas affichée.',
        'accidents' => 'Les accidents affichés ont une fiche relue ; une fiche encore à vérifier n’apparaît pas.',
        'cache' => 'La liste d’un jour est calculée une fois par jour et gardée en cache.',
    ],
    'api_text' => 'Les mêmes faits, en JSON :',
    'link_chronology' => 'Les mêmes faits année par année : la chronologie du rail en France',
    'link_network' => 'Le réseau en chiffres',
];
