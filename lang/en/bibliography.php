<?php

// « Further reading » section of the line and station sheets: books and articles about them. Only the catalogue data
// (authors, title, publisher, year, ISBN) is used, with a link to the record or to the original reference.
return [
    'title' => 'Further reading',
    'intro_line' => 'Books and articles about this line: monographs, magazines, local studies.',
    'intro_station' => 'Books and articles about this station.',
    'show_one' => 'Show the reference',
    'show_other' => 'Show the :count references',
    'list_label' => 'Bibliographic references',
    'undated' => 'Undated',
    'kind' => [
        'book' => 'Book',
        'article' => 'Article',
    ],
    'in_journal' => 'in :journal',
    'isbn' => 'ISBN :isbn',
    'link' => [
        'bnf' => 'Record in the BnF catalogue',
        'wikipedia' => 'Reference in the Wikipedia article',
        'wikidata' => 'Wikidata item',
    ],
    'truncated' => 'The :shown most recent references out of :total; the full list is in the API.',
    'source' => 'Titles, authors, publishers and years come from the records of the general catalogue of the Bibliothèque nationale de France (Licence Ouverte), read on :date, from the references of Wikipedia articles (CC BY-SA 4.0) and from Wikidata (CC0). No content of any book is used.',
    'source_nodate' => 'Titles, authors, publishers and years come from the references of Wikipedia articles (CC BY-SA 4.0) and from Wikidata (CC0), or from the records of the general catalogue of the Bibliothèque nationale de France (Licence Ouverte). No content of any book is used.',
];
