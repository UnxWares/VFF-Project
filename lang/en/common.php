<?php

return [
    'app_full' => 'French Railway Lines',
    'app_description' => 'Collaborative encyclopaedia of French railways, from 1830 to the present day: lines, stations, maps and sources.',

    'save' => 'Save',
    'close' => 'Close',
    'toc' => 'Contents',
    'osm_attribution' => '© :link contributors',

    // Shared component texts (pagination, external links).
    'new_tab' => 'opens in a new tab',
    'external_same_tab' => 'external link, opens in this tab',
    'note_ref' => 'Note',
    'required' => 'required',
    'pagination' => 'Pagination',
    'previous' => 'Previous',
    'next' => 'Next',
    'page_of' => 'Page :current of :last',
    // Directories on phones: button that unfolds the filters (number of active settings).
    'filters' => 'Filters',
    'filters_active_one' => ':count active',
    'filters_active_other' => ':count active',
    // Clin d'œil : taper « patrick » fait passer la rame TGV Sud-Est n° 01 (resources/js/lib/patrickTrain.js).
    'patrick' => [
        'label' => 'The “Patrick” TGV going by',
        'bubble' => 'TGV 01 “Patrick”, the first TGV Sud-Est set (1978)',
        'close' => 'Close',
        'announce' => 'A small nod: TGV 01 “Patrick”, the first TGV Sud-Est set (1978). A link at the bottom of the screen leads to its page.',
    ],
    // Printing a sheet: page footer (resources/css/print.css, resources/js/lib/print.js).
    'print' => [
        'printed_on' => 'Printed on :date',
        'page' => 'Page',
        'of' => 'of',
    ],

    // Sheet: sections loaded right after the first render (deferred props), loading state.
    'sheet_loading' => 'Loading the rest of the page…',
    'sheet_loading_label' => 'Rest of the page, loading',
];
