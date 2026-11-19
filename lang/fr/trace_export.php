<?php

// Export du tracé d'une ligne (GPX, KML : app/Services/TraceExport). Texte écrit dans le fichier,
// langue choisie par le paramètre `lang` de la requête (fr par défaut).
return [
    'era_past' => 'état du réseau au 31 décembre :year',
    'era_current' => 'état actuel du réseau',
    'title' => ':line (:era)',
    'summary' => 'Tracé de « :line », :era. Fiche de la ligne : :url',
    'sections' => '{1} :count tronçon|[2,*] :count tronçons',
    'sources_label' => 'Sources du tracé',
    'database' => 'Voies Ferrées de France (vff-project.org), base sous licence ODbL 1.0',
    'segment' => 'Tronçon :id',
    'pk_range' => 'PK :from à :to',
    'pk_unknown' => 'PK inconnus',
    'status' => 'État',
    'source' => 'Source',
    'withheld_not_open' => '{1} :count tronçon (:km km) n’est pas inclus : sa source, :source, n’autorise pas la redistribution.|[2,*] :count tronçons (:km km) ne sont pas inclus : leur source, :source, n’autorise pas la redistribution.',
    'withheld_unknown' => '{1} :count tronçon (:km km) n’est pas inclus : sa source n’est pas déclarée.|[2,*] :count tronçons (:km km) ne sont pas inclus : leur source n’est pas déclarée.',
];
