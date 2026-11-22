<?php

/**
 * Fiches d'accident rédigées à la main : un accident qu'aucune source de l'import (Wikidata, BEA-TT, listes de
 * Wikipédia) ne décrit, appuyé par un document lu. Lu par App\Services\Accidents\ManualAccidentCollector, comme
 * une source de plus de vff:import-accidents (`--only=manual`). Format des enregistrements : voir ce collecteur.
 *
 * Seuls des faits sont notés (date, lieu, nature, bilan, lignes) avec le lien du document : jamais son texte.
 * Le récit et ses sources vont dans le contexte (database/data/accident_contexts/<slug>.php). Pas de tiret long.
 */
return [
    [
        // Rapport : storage/app/reports/agents/aprem-2111/accidents-decisions.md (texte lu le 22/11/2026).
        'id' => '1872-06-20-juvisy-sur-orge',
        'date' => '1872-06-20',
        'commune' => 'Juvisy-sur-Orge',
        'department' => '91',
        'kind' => 'collision',
        'deaths' => 5,
        'injuries' => null,
        'line_articles' => ['Ligne de Paris-Austerlitz à Bordeaux-Saint-Jean'],
        'source' => [
            'provider' => 'delpher',
            'title' => 'Gemengde berigten, Arnhemsche courant, 24 juin 1872, p. 2',
            'url' => 'https://resolver.kb.nl/resolve?urn=MMKB08:000090706:mpeg21:a0006',
            'licence' => 'Faits seulement, lien vers l\'article (conditions de la KB)',
            'licence_url' => 'https://www.kb.nl/legal/gebruiksvoorwaarden-en-klachtregeling',
            'published' => '1872-06-24',
        ],
    ],
];
