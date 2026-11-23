<?php

// Abschnitt „Zum Weiterlesen“ der Strecken- und Bahnhofsseiten: Bücher und Artikel zum Thema. Übernommen werden nur die
// Katalogdaten (Autoren, Titel, Verlag, Jahr, ISBN) mit dem Link zum Katalogeintrag oder zur ursprünglichen Quelle.
return [
    'title' => 'Zum Weiterlesen',
    'intro_line' => 'Bücher und Artikel zu dieser Strecke: Monografien, Zeitschriften, lokale Studien.',
    'intro_station' => 'Bücher und Artikel zu diesem Bahnhof.',
    'show_one' => 'Den Literaturhinweis anzeigen',
    'show_other' => 'Die :count Literaturhinweise anzeigen',
    'list_label' => 'Literaturhinweise',
    'undated' => 'Ohne Datum',
    'kind' => [
        'book' => 'Buch',
        'article' => 'Artikel',
    ],
    'in_journal' => 'in :journal',
    'isbn' => 'ISBN :isbn',
    'link' => [
        'bnf' => 'Eintrag im Katalog der BnF',
        'wikipedia' => 'Literaturhinweis im Wikipedia-Artikel',
        'wikidata' => 'Wikidata-Eintrag',
    ],
    'truncated' => 'Die :shown neuesten Literaturhinweise von :total; die vollständige Liste finden Sie in der API.',
    'source' => 'Titel, Autoren, Verlage und Jahre stammen aus den Einträgen des Gesamtkatalogs der Bibliothèque nationale de France (Licence Ouverte), abgerufen am :date, aus den Literaturhinweisen der Wikipedia-Artikel (CC BY-SA 4.0) und aus Wikidata (CC0). Der Inhalt der Bücher wird nicht übernommen.',
    'source_nodate' => 'Titel, Autoren, Verlage und Jahre stammen aus den Literaturhinweisen der Wikipedia-Artikel (CC BY-SA 4.0) und aus Wikidata (CC0) oder aus den Einträgen des Gesamtkatalogs der Bibliothèque nationale de France (Licence Ouverte). Der Inhalt der Bücher wird nicht übernommen.',
];
