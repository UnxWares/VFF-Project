<?php

// Historique de la signalisation (vff:import-signalling-history) : frise de la fiche ligne.
// Faits relevés dans les articles de ligne de Wikipédia (CC BY-SA 4.0).
return [
    'title' => 'Signalling and train protection over time',
    'intro' => 'Commissioning dates taken from the line’s Wikipedia article and from other documents (EPSF decisions and reports, SNCF Réseau and government reviews, newspapers). The list is incomplete: a system that is missing may have been commissioned without any of these documents giving a date.',
    'system' => [
        'BM' => 'BM, manual block',
        'CT' => 'Telephone block',
        'CC' => 'Centralised control (CCVU, CCR, PCD)',
        'TVM' => 'TVM',
        'ETCS' => 'ETCS',
    ],
    'section' => [
        'whole_line' => 'Whole line',
        'unspecified' => 'Section not stated',
        'between' => 'From :from to :to',
        'from' => 'From :from onwards',
        'to' => 'As far as :to',
        'pk' => ':pk :from to :to',
    ],
    // Origin of a fact that does not come from a sentence of the article.
    'origin' => [
        'opening' => 'Line opening date: a high-speed line runs on cab signalling from the day it opens.',
        'infobox' => 'Date given in the article’s infobox.',
        'table' => 'Date given in a table of the article.',
    ],
    'source' => [
        'link' => 'Wikipedia',
        'hint' => 'article “:title”, new tab',
        'note_one' => 'Facts taken from the Wikipedia article “:title” (CC BY-SA 4.0 licence). The day or month is shown only when the article gives it.',
        'note_other' => 'Facts taken from the Wikipedia articles “:title” (CC BY-SA 4.0 licence). The day or month is shown only when the article gives it.',
        'document_hint' => ':title, new tab',
        'note_documents' => 'When the link shows a publisher’s name, the date comes from that document and is confirmed by an official source or by two independent sources. The day or month is shown only when these documents give it.',
    ],
    'at' => [
        'intro' => 'Date chosen in “:section”: :date. Each commissioning is placed against that date.',
        'change' => 'Choose another date',
    ],
    'status' => [
        'in_force' => 'In force at that date',
        'replaced' => 'Replaced by :system (:when)',
        'later' => 'Not yet in service at that date',
        'uncertain_year' => 'Date known to the year only: in service before or after that date',
        'uncertain_month' => 'Date known to the month only: in service before or after that date',
    ],
    // Dates contradicted by documents (sourced corrections), folded under the timeline.
    'corrections' => [
        'summary' => 'Dates contradicted by documents (:count)',
        'intro' => 'These dates, read in an article or a document, are contradicted by more reliable documents. They appear neither in the timeline nor on the map.',
        'read_in' => 'Date read in',
        'original_document' => 'a document',
        'action' => [
            'neutralize' => 'Set aside',
            'replace' => 'Replaced in the timeline by the date of the documents',
        ],
        'reason' => 'Why (in French):',
        'documents' => 'Contradicted by:',
    ],
    // Ligne classique en service sans mise en service de cantonnement datée (SignallingDating, calculé) ; `checked`: date du dernier passage.
    'dating' => [
        'undated' => 'No free document consulted dates the commissioning of this line’s signalling.',
        'checked' => 'Last checked: :date.',
    ],
];
