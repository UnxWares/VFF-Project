<?php

// „An diesem Tag“ (/on-this-day): Texte der Seite. Die Fakten stammen aus App\Services\OnThisDay.
return [
    'meta_title' => 'An diesem Tag',
    'meta_description' => 'Was an einem Kalendertag in früheren Jahren geschah: Eröffnungen und Stilllegungen von Strecken und Bahnhöfen, Elektrifizierungen, Inbetriebnahmen, Unfälle, jeweils mit Jahr, Quelle und Seite.',
    'hero_title' => 'An diesem Tag',
    'hero_subtitle' => 'Für einen Tag des Kalenders die Fakten der französischen Eisenbahn, die die Enzyklopädie tagesgenau kennt, Jahr für Jahr: eröffnete oder stillgelegte Strecken und Bahnhöfe, Elektrifizierungen, Inbetriebnahmen. Jedes Faktum verweist auf seine Seite und seine Quelle.',
    'today_badge' => 'Heute',

    'nav_label' => 'Einen Tag wählen',
    'prev_day' => 'Vorheriger Tag: :date',
    'next_day' => 'Nächster Tag: :date',
    'go_today' => 'Zurück zu heute',
    'pick_legend' => 'Ein anderes Datum',
    'month_label' => 'Monat',
    'day_label' => 'Tag',
    'go' => 'Diesen Tag anzeigen',

    'facts_title' => 'Am :date, in früheren Jahren',
    'count_one' => ':value tagesgenau bekanntes Faktum',
    'count_other' => ':value tagesgenau bekannte Fakten',
    'empty' => 'Für den :date ist kein Faktum tagesgenau bekannt.',
    'empty_hint' => 'Ein nur auf das Jahr oder den Monat genau bekanntes Datum wird nie als Tag dargestellt: Es bleibt in der Chronik.',
    'nearest_prev' => 'Vorheriger Tag mit Fakten: :date',
    'nearest_next' => 'Nächster Tag mit Fakten: :date',
    'years_ago_one' => 'vor :value Jahr',
    'years_ago_other' => 'vor :value Jahren',
    'this_year' => 'dieses Jahr',

    'kinds' => [
        'line' => [
            'opening' => 'Streckeneröffnung',
            'reopening' => 'Wiedereröffnung einer Strecke',
            'closure' => 'Streckenstilllegung',
            'electrification' => 'Elektrifizierung',
            'signalling' => 'Signaltechnik in Betrieb genommen',
        ],
        'station' => [
            'opening' => 'Bahnhofseröffnung',
            'reopening' => 'Wiedereröffnung eines Bahnhofs',
            'closure' => 'Bahnhofsschließung',
            'demolition' => 'Abriss eines Bahnhofs',
        ],
        'accident' => ['accident' => 'Unfall'],
        'milestone' => ['milestone' => 'Nationaler Meilenstein'],
    ],
    'traffic' => [
        'passenger' => 'für den Personenverkehr',
        'freight' => 'für den Güterverkehr',
        'total' => 'für jeden Verkehr',
    ],
    'deaths_one' => 'Bekannte Bilanz: :value Todesopfer',
    'deaths_other' => 'Bekannte Bilanz: :value Todesopfer',
    'source_one' => 'Quelle:',
    'source_other' => 'Quellen:',
    'external' => '(externe Seite)',

    'accidents_hide' => 'Unfälle ausblenden',
    'accidents_show' => 'Unfälle anzeigen',
    'accidents_note' => 'Unfälle werden aus Respekt vor den Opfern und ihren Angehörigen nüchtern dargestellt: Name, Ort und bekannte Bilanz, mit ihren Quellen.',
    'accidents_hidden_one' => ':value Unfall ist für diesen Tag ausgeblendet.',
    'accidents_hidden_other' => ':value Unfälle sind für diesen Tag ausgeblendet.',

    'method_title' => 'Was diese Seite zeigt',
    'coverage' => 'An :with von :days Tagen des Jahres ist mindestens ein Faktum tagesgenau bekannt (insgesamt :facts Fakten).',
    'method' => [
        'day' => 'Hier erscheinen nur tagesgenaue Daten. Datieren zwei Quellen dasselbe Faktum auf verschiedene Tage, oder nennt nur eine Monat oder Jahr, bleibt das Faktum in der Chronik und erscheint an keinem Tag.',
        'sources' => 'Jedes Faktum hat mindestens eine Quelle, deren Fakten veröffentlicht werden dürfen, mit ihrem Link. Nur aus anderen Quellen bekannte Fakten werden nicht angezeigt.',
        'stations' => 'Eröffnungen und Schließungen von Bahnhöfen sind die, die die Bahnhofsseite führt; ein aus der Eröffnung einer Strecke abgeleitetes Datum ist kein Faktum des Bahnhofs und wird nicht angezeigt.',
        'accidents' => 'Die angezeigten Unfälle haben eine geprüfte Seite; eine noch zu prüfende Seite erscheint nicht.',
        'cache' => 'Die Liste eines Tages wird einmal täglich berechnet und im Cache gehalten.',
    ],
    'api_text' => 'Dieselben Fakten als JSON:',
    'link_chronology' => 'Dieselben Fakten Jahr für Jahr: die Chronik der Eisenbahn in Frankreich',
    'link_network' => 'Das Netz in Zahlen',
];
