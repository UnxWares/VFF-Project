<?php

// Kunstbauten der Strecken (vff:import-structures): Abschnitt des Streckenblatts (LineStructures.svelte).
return [
    'line' => [
        'title' => 'Kunstbauten',
        'intro' => 'Tunnel, Viadukte und große Brücken der Strecke, nach Streckenkilometer geordnet.',
        'summary' => 'Die Strecke hat :list.',
        'tunnels_length' => 'insgesamt :length Tunnel',
        'count' => [
            'tunnel_one' => ':count Tunnel',
            'tunnel_other' => ':count Tunnel',
            'viaduct_one' => ':count Viadukt',
            'viaduct_other' => ':count Viadukte',
            'bridge_one' => ':count große Brücke',
            'bridge_other' => ':count große Brücken',
        ],
        'kind' => [
            'tunnel' => 'Tunnel',
            'viaduct' => 'Viadukt',
            'bridge' => 'Brücke',
        ],
        'unnamed' => [
            'tunnel' => 'Tunnel ohne bekannten Namen',
            'viaduct' => 'Viadukt ohne bekannten Namen',
            'bridge' => 'Brücke ohne bekannten Namen',
        ],
        'pk' => 'km :pk',
        'pk_range' => 'km :from bis :to',
        'built' => 'erbaut :date',
        'built_day' => 'erbaut am :date',
        'opened' => 'in Betrieb genommen :date',
        'opened_day' => 'in Betrieb genommen am :date',
        'closed' => 'stillgelegt :date',
        'closed_day' => 'stillgelegt am :date',
        'heritage' => [
            'classified' => 'denkmalgeschützt (classé monument historique)',
            'registered' => 'denkmalgeschützt (inscrit monument historique)',
            'protected' => 'denkmalgeschützt (monument historique)',
        ],
        'not_current' => 'auf der Strecke nicht mehr in Betrieb',
        'wikipedia' => 'Wikipedia',
        'merimee' => 'Mérimée',
        'wikidata' => 'Wikidata',
        'photo_credit' => 'Foto :author, :license',
        'photo_license' => 'Foto, :license',
        'all_closed' => 'Die Strecke ist nicht mehr in Betrieb: Die Bauwerke stehen noch, manche umgenutzt.',
        'photo_alt' => 'Foto von :name',
        'notable' => 'Bauwerke mit Namen oder ab 300 m Länge. Die vollständige Liste ist unten eingeklappt.',
        'show_all' => 'Alle :count Bauwerke anzeigen',
        'list_label' => 'Alle Kunstbauten der Strecke',
        'source' => 'Verlauf und Längen: IGN, BD TOPO (Licence Ouverte 2.0). Namen, Daten und Denkmalschutz: Wikidata (CC0). Streckenkilometer auf dem Höhenprofil geschätzt, an den Bahnhöfen geeicht. Tunnel unter 50 m und Brücken unter 100 m ohne Namen sind nicht aufgeführt.',
    ],
];
