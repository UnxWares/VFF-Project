<?php

return [
    // Matériel : séries françaises que Wikidata ne range pas (ni pays ni exploitant), ajoutées aux candidats de
    // vff:import-materials depuis database/data/material_extra_series.php ; false pour ne lire que Wikidata.
    'materials' => [
        'extra_series' => (bool) env('VFF_MATERIAL_EXTRA_SERIES', true),
    ],

    /*
    | Médias (photos, cartes postales, vues aériennes) : réglages de diffusion.
    |
    | show_non_commercial : montrer les médias dont la licence interdit l'usage commercial (CC BY-NC…,
    | `media.commercial_ok = false`). Faux : ils disparaissent partout où un média est servi (fiches, galeries,
    | accueil, API REST, GraphQL, serveur MCP, relais des vignettes), sans être effacés de la base. Décision du
    | 22/11/2026 : les fonds NC sont intégrés, avec ce réglage pour les masquer (VFF est édité par une société
    | jusqu'au passage à l'association).
    */
    'media' => [
        'show_non_commercial' => (bool) env('VFF_MEDIA_SHOW_NON_COMMERCIAL', true),

        // show_uncertain : montrer les médias d'un fonds dont la licence est « incertaine » dans database/data/source_rights.php
        // (statut `incertain` : Évry-Courcouronnes, musée Gadagne, Pireneas…). Chaque média en porte le marqueur `rights_uncertain`
        // (fiche, API, GraphQL, MCP). Faux : ils disparaissent partout où un média est servi, sans être effacés de la base.
        'show_uncertain' => (bool) env('VFF_MEDIA_SHOW_UNCERTAIN', true),

        // Vues aériennes historiques des gares (IGN, BD ORTHO historique, Licence Ouverte 2.0) : relais
        // /media/aerial/{gare}/{période}.jpg, cache disque, lectures chez l'IGN plafonnées par minute.
        'aerial' => [
            'enabled' => (bool) env('VFF_MEDIA_AERIAL', true),
            'cache_path' => env('VFF_MEDIA_AERIAL_CACHE', storage_path('app/cache/aerial')),
            'upstream_per_minute' => (int) env('VFF_MEDIA_AERIAL_UPSTREAM_PER_MINUTE', 30),
        ],
    ],
];
