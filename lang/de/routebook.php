<?php

// Streckenbuch (/routebook): Texte der Seite. Die Daten stammen aus GET /api/v1/routebook.
return [
    'meta_title' => 'Streckenbuch',
    'meta_description' => 'Die heutige Strecke zwischen zwei Bahnhöfen, Linie für Linie: Kilometerpunkte, Gleisplan, Geschwindigkeiten und Neigungen in Fahrtrichtung, mit der Quelle jeder Angabe.',
    'hero_title' => 'Streckenbuch',
    'hero_subtitle' => 'Wählen Sie zwei Bahnhöfe: Die befahrenen Strecken folgen in Fahrtrichtung aufeinander, mit ihren Kilometerpunkten, ihren Gleisen und der Quelle jeder Angabe.',

    'submit' => 'Streckenbuch anzeigen',
    'clear' => 'Löschen',
    'print' => 'Drucken',
    'loading' => 'Strecke wird berechnet…',

    'summary_one' => ':km km, :count Objekt',
    'summary_other' => ':km km, :count Objekte',
    'lines_one' => ':count Strecke',
    'lines_other' => ':count Strecken',
    'changes_one' => ':count Streckenwechsel',
    'changes_other' => ':count Streckenwechsel',
    'today' => 'Heutiges Netz, kürzester Weg für Reisezüge.',

    'legs_title' => 'Befahrene Strecken',
    'legs_intro' => 'In der Reihenfolge der Fahrt. Abfahrt und Ankunft liegen in der Mitte des Bahnhofs, an seinem Kilometerpunkt auf der Strecke.',
    'leg_km' => 'km :from bis :to der Fahrt',
    'leg_pk' => 'km :from bis :to der Strecke',
    'leg_pk_unknown' => 'Kilometerpunkte auf dieser Strecke unbekannt',
    'junction' => 'Streckenwechsel auf freier Strecke',
    'direction' => [
        'up' => 'steigende Kilometrierung',
        'down' => 'fallende Kilometrierung',
    ],
    'precision' => [
        'exact' => 'Kilometerpunkte der Bahnhöfe aus einer Quelle',
        'derived' => 'von VFF auf dem Verlauf von SNCF Réseau berechnete Kilometerpunkte',
        'approximate' => 'ungefähre Kilometerpunkte, zwischen zwei Bahnhöfen interpoliert',
    ],
    'no_detail' => 'Noch kein detaillierter Gleisplan',

    'layout_title' => 'Gleisplan',
    'coverage_none' => 'Für keinen Abschnitt dieser Fahrt gibt es bisher einen detaillierten Gleisplan (RINF, OpenStreetMap): Die Strecken und ihre Kilometerpunkte stehen oben. Ein Abschnitt ohne Daten ist kein Abschnitt ohne Gleise: Die Quelle beschreibt ihn noch nicht.',
    'coverage_partial' => ':km km der Fahrt haben noch keinen detaillierten Gleisplan (Strecken :lines).',

    'sources_title' => 'Quellen',

    'errors' => [
        'station_not_in_network' => 'Der Bahnhof :station wird vom heutigen Netz nicht bedient.',
        'no_path' => 'Diese beiden Bahnhöfe sind im heutigen Netz nicht verbunden.',
        'graph_not_built' => 'Das heutige Netz ist für Fahrten noch nicht verfügbar.',
    ],

    'from_station' => 'Von hier abfahren: Streckenbuch',
    'from_line' => 'Streckenbuch von :from nach :to',
];
