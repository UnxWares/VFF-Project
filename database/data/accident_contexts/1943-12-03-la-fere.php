<?php

// Contexte sourcé du déraillement de La Fère du 3 décembre 1943 (recherche du 21/11/2026). Fiche peu documentée.
return [
    'slug' => '1943-12-03-la-fere',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1940' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1940',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1943, 3 décembre, La Fère',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1940&oldid=239153903',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée de trois lignes sourcée par Le Petit Parisien du 4 décembre 1943, page 1 (non lu) : heure, trajet, quatre voitures enchevêtrées, dix morts, quarante-cinq blessés. Aucune cause n\'est donnée. Une autre entrée du même jour (Demigny) attribue un déraillement à un sabotage.',
        ],
        'gallica-petit-parisien-1943-12-04' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Petit Parisien du 4 décembre 1943, page 1',
            'publisher' => 'Le Petit Parisien (Gallica, BnF)',
            'author' => null,
            'published' => '1943-12-04',
            'locator' => 'page 1',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k685160w/f1.zoom.r=sabotage.langFR',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Journal de l\'Occupation, donc à lire avec prudence ; seule référence de l\'entrée de Wikipédia, qui recense dans le même numéro deux déraillements du 3 décembre. Gallica non lu (droits).',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 3 décembre 1943, vers deux heures, un train de Tergnier à Laon a déraillé sur la ligne d\'Amiens à Laon, entre les gares de La Fère et de Versigny (Aisne). Quatre voitures de voyageurs se sont enchevêtrées.',
                'en' => 'On 3 December 1943, at about two in the morning, a train from Tergnier to Laon derailed on the Amiens to Laon line, between La Fère and Versigny stations (Aisne). Four passenger coaches piled up.',
                'de' => 'Am 3. Dezember 1943 gegen zwei Uhr nachts entgleiste ein Zug von Tergnier nach Laon auf der Strecke Amiens - Laon zwischen den Bahnhöfen La Fère und Versigny (Aisne). Vier Reisezugwagen verkeilten sich ineinander.',
                'sources' => ['wp-liste-1940'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le déraillement a fait dix morts et quarante-cinq blessés selon la liste de Wikipédia.',
                'en' => 'The derailment killed ten people and injured forty-five, according to the Wikipedia list.',
                'de' => 'Bei der Entgleisung starben zehn Menschen, fünfundvierzig wurden verletzt, so die Wikipedia-Liste.',
                'sources' => ['wp-liste-1940'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'Aucun document lu n\'indique la cause. La liste de Wikipédia ne parle pas de sabotage pour cet accident, alors qu\'elle le fait pour un autre déraillement du même jour près de Demigny (Saône-et-Loire) et que les deux entrées reposent sur le même numéro de journal. La nature « déraillement » de la fiche est donc conservée, sans qualifier l\'événement de fait de guerre.',
                'en' => 'No document read gives the cause. The Wikipedia list does not mention sabotage for this accident, whereas it does for another derailment the same day near Demigny (Saône-et-Loire), and both entries rest on the same newspaper issue. The record\'s "derailment" type is therefore kept, without describing the event as an act of war.',
                'de' => 'Kein gelesenes Dokument nennt die Ursache. Die Wikipedia-Liste erwähnt für diesen Unfall keine Sabotage, wohl aber für eine andere Entgleisung am selben Tag bei Demigny (Saône-et-Loire), und beide Einträge beruhen auf derselben Zeitungsausgabe. Die Art „Entgleisung“ des Datensatzes bleibt daher bestehen, ohne das Ereignis als Kriegshandlung zu bezeichnen.',
                'sources' => ['wp-liste-1940'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Une seule source (la liste de Wikipédia, d\'après un quotidien de 1943 non lu) documente cet accident : date, bilan et circonstances ne sont pas recoupés. Un accident du même jour à Demigny est attribué à un sabotage ; rien ne permet de dire si celui de La Fère l\'est aussi.',
                'en' => 'A single source (the Wikipedia list, based on an unread 1943 daily) documents this accident: date, toll and circumstances are not cross-checked. An accident the same day at Demigny is attributed to sabotage; nothing shows whether the one at La Fère was too.',
                'de' => 'Nur eine Quelle (die Wikipedia-Liste, nach einer nicht gelesenen Tageszeitung von 1943) dokumentiert diesen Unfall: Datum, Bilanz und Umstände sind nicht gegengeprüft. Ein Unfall am selben Tag in Demigny wird einer Sabotage zugeschrieben; nichts zeigt, ob dies auch für den von La Fère gilt.',
                'sources' => ['wp-liste-1940'],
            ],
        ],
    ],
    'corrections' => [],
];
