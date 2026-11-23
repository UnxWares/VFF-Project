<?php

/*
 * Catégorie de matériel posée à la main pour les séries que Wikidata ne range pas (App\Support\MaterialTypeOverrides),
 * lue par WikidataMaterialMapper en dernier recours : seulement quand ni les classes Wikidata ni les catégories de
 * l'article Wikipédia ne donnent de catégorie. Dès que Wikidata (ou l'article) range la série, la ligne ne sert plus.
 *
 * Champs : `material` (élément Wikidata), `type` (valeur de App\Enums\MaterialType), `source` (permalien), `quote`
 * (courte citation exacte ou description Wikidata), `name` (facultatif : nom de la série quand Wikidata n'a ni libellé français ni anglais), `wikidata` (déclaration à ajouter dans Wikidata, pour mémoire).
 *
 * Relecture du 23/11/2026 (rapport storage/app/reports/agents/aprem-2111/materiel-nuit.md).
 *
 * @return list<array{material: string, type: string, source: string, quote: string, wikidata: string, name?: string}>
 */
return [
    [
        'material' => 'Q2876837', // BB 61000
        'type' => 'locomotive_diesel',
        'source' => 'https://en.wikipedia.org/w/index.php?title=SNCF_Class_BB_61000&oldid=1274723261',
        'quote' => 'diesel locomotives were built by Vossloh to their G1206 design',
        'wikidata' => 'P279 Q34336 (locomotive diesel)',
    ],
    [
        'material' => 'Q2876838', // BB 63413 Plathée
        'type' => 'locomotive_diesel',
        'source' => 'https://fr.wikipedia.org/w/index.php?title=BB_63413_:_Plath%C3%A9e&oldid=225162329',
        'quote' => 'réalisée sur la base de la locomotive diesel BB 63413',
        'wikidata' => 'P279 Q34336 (locomotive diesel)',
    ],
    [
        'material' => 'Q3570434', // XR 6100
        'type' => 'voiture',
        'source' => 'https://www.wikidata.org/wiki/Q3570434',
        'quote' => 'class of French railcar trailer (article Remorque ANF)',
        'wikidata' => 'P279 Q753779 (voiture de chemin de fer)',
    ],
    [
        'material' => 'Q3570436', // XR 6200
        'type' => 'voiture',
        'source' => 'https://www.wikidata.org/wiki/Q3570436',
        'quote' => 'class of French railcar trailer',
        'wikidata' => 'P279 Q753779 (voiture de chemin de fer)',
    ],
    [
        'material' => 'Q30598950', // British Rail Class 21 (MaK)
        'type' => 'locomotive_diesel',
        'source' => 'https://en.wikipedia.org/w/index.php?title=British_Rail_Class_21&oldid=1352606588',
        'quote' => 'diesel-hydraulic and diesel-electric locomotives',
        'wikidata' => 'P279 Q34336 (locomotive diesel)',
    ],
    [
        'material' => 'Q130554251', // 120 PO 125
        'type' => 'locomotive_vapeur',
        'source' => 'https://de.wikipedia.org/w/index.php?title=PO_125&oldid=250061549',
        'quote' => 'Schlepptender-Lokomotive',
        'wikidata' => 'P279 Q171043 (locomotive à vapeur)',
    ],
    [
        'material' => 'Q139678896', // 2D2 5546 à 5550
        'type' => 'locomotive_electrique',
        'name' => '2D2 5546 à 5550', // aucun libellé français ni anglais dans Wikidata : nom de l'article allemand, à la façon des autres séries
        'source' => 'https://de.wikipedia.org/w/index.php?title=SNCF_2D2_5546%E2%80%935550&oldid=266953909',
        'quote' => 'Die Elektrolokomotiven 2D2 5546–5550',
        'wikidata' => 'P279 Q625151 (locomotive électrique)',
    ],
    [
        'material' => 'Q142081720', // XD 2000-2600
        'type' => 'autorail',
        'source' => 'https://de.wikipedia.org/w/index.php?title=SNCF_XD_2000-2600&oldid=272024470',
        'quote' => 'Dieseltriebwagen',
        'wikidata' => 'P279 Q752392 (autorail)',
    ],
    [
        'material' => 'Q17448615', // Bourbonnais (locomotive)
        'type' => 'locomotive_vapeur',
        'source' => 'https://www.wikidata.org/wiki/Q17448615',
        'quote' => '0-6-0 steam locomotive (P279 : locomotive de disposition 030, Q2922269)',
        'wikidata' => 'P279 Q171043 (locomotive à vapeur)',
    ],
];
