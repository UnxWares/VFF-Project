<?php

// "On this day" (/on-this-day): page texts. The facts come from App\Services\OnThisDay.
return [
    'meta_title' => 'On this day',
    'meta_description' => 'What happened on a calendar day in earlier years: openings and closures of lines and stations, electrifications, commissionings, accidents, each fact with its year, source and sheet.',
    'hero_title' => 'On this day',
    'hero_subtitle' => 'For a day of the calendar, the French railway facts the encyclopedia knows to that exact day, year after year: lines and stations opened or closed, electrifications, commissionings. Each fact links to its sheet and its source.',
    'today_badge' => 'Today',

    'nav_label' => 'Choose a day',
    'prev_day' => 'Previous day: :date',
    'next_day' => 'Next day: :date',
    'go_today' => 'Back to today',
    'pick_legend' => 'Another date',
    'month_label' => 'Month',
    'day_label' => 'Day',
    'go' => 'Show this day',

    'facts_title' => 'On :date, in earlier years',
    'count_one' => ':value fact known to the day',
    'count_other' => ':value facts known to the day',
    'empty' => 'No fact is known to the exact day for :date.',
    'empty_hint' => 'A date known only to the year or the month is never presented as a day: it stays in the chronology.',
    'nearest_prev' => 'Previous day with facts: :date',
    'nearest_next' => 'Next day with facts: :date',
    'years_ago_one' => ':value year ago',
    'years_ago_other' => ':value years ago',
    'this_year' => 'this year',

    'kinds' => [
        'line' => [
            'opening' => 'Line opening',
            'reopening' => 'Line reopening',
            'closure' => 'Line closure',
            'electrification' => 'Electrification',
            'signalling' => 'Signalling commissioned',
        ],
        'station' => [
            'opening' => 'Station opening',
            'reopening' => 'Station reopening',
            'closure' => 'Station closure',
            'demolition' => 'Station demolition',
        ],
        'accident' => ['accident' => 'Accident'],
        'milestone' => ['milestone' => 'National milestone'],
    ],
    'traffic' => [
        'passenger' => 'to passengers',
        'freight' => 'to freight',
        'total' => 'to all traffic',
    ],
    'deaths_one' => 'Known toll: :value death',
    'deaths_other' => 'Known toll: :value deaths',
    'source_one' => 'Source:',
    'source_other' => 'Sources:',
    'external' => '(external site)',

    'accidents_hide' => 'Hide accidents',
    'accidents_show' => 'Show accidents',
    'accidents_note' => 'Accidents are presented soberly, out of respect for the victims and their families: name, place and known toll, with their sources.',
    'accidents_hidden_one' => ':value accident is hidden for this day.',
    'accidents_hidden_other' => ':value accidents are hidden for this day.',

    'method_title' => 'What this page shows',
    'coverage' => ':with days of the year out of :days have at least one fact known to the day (:facts facts in all).',
    'method' => [
        'day' => 'Only dates known to the exact day are shown here. When two sources date the same fact to different days, or when only the month or the year is given, the fact stays in the chronology and does not appear on a day.',
        'sources' => 'Each fact has at least one source whose facts may be published, cited with its link. Facts known only from other sources are not shown.',
        'stations' => 'Station openings and closures are those retained by the station sheet; a date deduced from the opening of a line is not a fact about the station and is not shown.',
        'accidents' => 'The accidents shown have a reviewed sheet; a sheet still to be checked does not appear.',
        'cache' => 'The list for a day is computed once a day and kept in cache.',
    ],
    'api_text' => 'The same facts, as JSON:',
    'link_chronology' => 'The same facts year by year: the chronology of French railways',
    'link_network' => 'The network in figures',
];
