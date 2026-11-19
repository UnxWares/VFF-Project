<?php

// Export of a line's route (GPX, KML: app/Services/TraceExport). Text written into the file,
// language chosen by the `lang` query parameter (fr by default).
return [
    'era_past' => 'state of the network on 31 December :year',
    'era_current' => 'current state of the network',
    'title' => ':line (:era)',
    'summary' => 'Route of “:line”, :era. Line page: :url',
    'sections' => '{1} :count section|[2,*] :count sections',
    'sources_label' => 'Sources of the route',
    'database' => 'French Railway Lines (Voies Ferrées de France, vff-project.org), database under the ODbL 1.0',
    'segment' => 'Section :id',
    'pk_range' => 'PK :from to :to',
    'pk_unknown' => 'PK unknown',
    'status' => 'Status',
    'source' => 'Source',
    'withheld_not_open' => '{1} :count section (:km km) is not included: its source, :source, does not allow redistribution.|[2,*] :count sections (:km km) are not included: their source, :source, does not allow redistribution.',
    'withheld_unknown' => '{1} :count section (:km km) is not included: its source is not declared.|[2,*] :count sections (:km km) are not included: their source is not declared.',
];
