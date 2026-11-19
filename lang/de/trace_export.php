<?php

// Export der Trasse einer Strecke (GPX, KML: app/Services/TraceExport). Text in der Datei,
// Sprache über den Abfrageparameter `lang` (Standard fr).
return [
    'era_past' => 'Stand des Netzes am 31. Dezember :year',
    'era_current' => 'aktueller Stand des Netzes',
    'title' => ':line (:era)',
    'summary' => 'Trasse von „:line“, :era. Seite der Strecke: :url',
    'sections' => '{1} :count Abschnitt|[2,*] :count Abschnitte',
    'sources_label' => 'Quellen der Trasse',
    'database' => 'Französische Bahnstrecken (Voies Ferrées de France, vff-project.org), Datenbank unter ODbL 1.0',
    'segment' => 'Abschnitt :id',
    'pk_range' => 'PK :from bis :to',
    'pk_unknown' => 'PK unbekannt',
    'status' => 'Zustand',
    'source' => 'Quelle',
    'withheld_not_open' => '{1} :count Abschnitt (:km km) ist nicht enthalten: Seine Quelle, :source, erlaubt keine Weitergabe.|[2,*] :count Abschnitte (:km km) sind nicht enthalten: Ihre Quelle, :source, erlaubt keine Weitergabe.',
    'withheld_unknown' => '{1} :count Abschnitt (:km km) ist nicht enthalten: Seine Quelle ist nicht angegeben.|[2,*] :count Abschnitte (:km km) sind nicht enthalten: Ihre Quelle ist nicht angegeben.',
];
