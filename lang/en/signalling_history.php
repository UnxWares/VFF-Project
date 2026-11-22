<?php

// Historique de la signalisation (vff:import-signalling-history) : frise de la fiche ligne.
// Faits relevés dans les articles de ligne de Wikipédia (CC BY-SA 4.0).
return [
    'title' => 'Signalling over time',
    'intro' => 'Commissioning dates taken from the line’s Wikipedia article and from other documents (EPSF decisions and reports, SNCF Réseau and government reviews, newspapers). The list is incomplete: a system that is missing may have been commissioned without any of these documents giving a date.',
    'system' => [
        'BM' => 'BM, manual block',
        'CT' => 'Telephone block',
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
];
