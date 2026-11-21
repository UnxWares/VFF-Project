<?php

/**
 * Lignes à crémaillère, déclarées à la main d'après l'article de Wikipédia en français de chaque
 * ligne (lu le 21/11/2026, citation exacte gardée). Lu par App\Services\Profiles\RackRailways :
 * sur ces lignes, la déclivité mesurée sur le profil en long n'est pas publiée (le calcul écarte
 * toute pente au-delà de 120 ‰, plus que toute ligne à adhérence, alors qu'une crémaillère monte
 * à 250 ‰) ; la fiche, l'API REST, GraphQL et l'outil MCP `line_profile` donnent à la place la
 * déclivité publiée par la source.
 *
 * Clé : slug de la ligne (`lines.slug`). Champs :
 *   - system : strub | abt | riggenbach (type de crémaillère) ;
 *   - extent : full (crémaillère sur tout le parcours) | partial (sur une partie) | null (la
 *     source ne le dit pas) ;
 *   - max_grade_permil : déclivité la plus forte selon la source, en ‰ ;
 *   - braking_only : vrai si la crémaillère ne servait qu'au freinage (Laon) ;
 *   - source_url (permalien de la révision lue), source_title, quote (extraits exacts de l'article ou de son
 *     infobox, séparés par « ; », 300 car. max).
 *
 * Wikipédia : CC BY-SA 4.0 ; seuls des faits (type, étendue, déclivité) sont repris, avec le lien.
 * Pas de tiret long dans les textes (consigne éditoriale).
 *
 * @return array<string, array{system: string, extent: string|null, max_grade_permil: int, braking_only: bool,
 *     source_url: string, source_title: string, quote: string}>
 */
return [
    'archeo-ligne-de-saint-gervais-a-bionnassay' => [
        'system' => 'strub',
        'extent' => 'partial',
        'max_grade_permil' => 240,
        'braking_only' => false,
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Tramway_du_Mont-Blanc&oldid=239002403',
        'source_title' => 'Tramway du Mont-Blanc',
        'quote' => 'La crémaillère est de type Strub ; elle est disposée sur 85 % de la longueur du parcours.',
    ],
    'archeo-ligne-de-chamonix-a-montenvers' => [
        'system' => 'strub',
        'extent' => 'full',
        'max_grade_permil' => 220,
        'braking_only' => false,
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_du_Montenvers&oldid=239685415',
        'source_title' => 'Chemin de fer du Montenvers',
        'quote' => 'La crémaillère est de type Strub ; elle est disposée sur la totalité du parcours.',
    ],
    'archeo-ligne-de-saint-ignace-a-la-rhune' => [
        'system' => 'strub',
        'extent' => 'full',
        'max_grade_permil' => 250,
        'braking_only' => false,
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_de_la_Rhune&oldid=240155679',
        'source_title' => 'Chemin de fer de la Rhune',
        'quote' => 'la technique du chemin de fer à crémaillère de type Strub ; pente de 250 mm/m',
    ],
    'hors-rfn-panoramique-des-domes' => [
        'system' => 'strub',
        'extent' => 'full',
        'max_grade_permil' => 155,
        'braking_only' => false,
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Panoramique_des_D%C3%B4mes&oldid=238874624',
        'source_title' => 'Panoramique des Dômes',
        'quote' => 'crémaillère est de type Strub ; Pente maximale 155 ‰',
    ],
    'archeo-ligne-de-luchon-a-superbagneres' => [
        'system' => 'strub',
        'extent' => 'full',
        'max_grade_permil' => 250,
        'braking_only' => false,
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_de_Luchon_%C3%A0_Superbagn%C3%A8res&oldid=237206881',
        'source_title' => 'Chemin de fer de Luchon à Superbagnères',
        'quote' => 'un chemin de fer à voie métrique et à crémaillère ; la crémaillère de type Strub équipait tout le parcours',
    ],
    'archeo-ligne-daix-les-bains-au-revard' => [
        'system' => 'abt',
        'extent' => 'full',
        'max_grade_permil' => 210,
        'braking_only' => false,
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_du_Mont-Revard&oldid=240812259',
        'source_title' => 'Chemin de fer du Mont-Revard',
        'quote' => 'La crémaillère est installée sur l\'ensemble du parcours. La rampe la plus élevée a une pente de 21 %.',
    ],
    'archeo-ligne-de-monte-carlo-a-la-turbie' => [
        'system' => 'riggenbach',
        'extent' => null,
        'max_grade_permil' => 250,
        'braking_only' => false,
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_%C3%A0_cr%C3%A9maill%C3%A8re_de_La_Turbie_%C3%A0_Monte-Carlo&oldid=232525037',
        'source_title' => 'Chemin de fer à crémaillère de La Turbie à Monte-Carlo',
        'quote' => 'De type Riggenbach ; Pente maximale 250 ‰',
    ],
    'archeo-ligne-de-veyrier-a-monnetier' => [
        'system' => 'abt',
        'extent' => null,
        'max_grade_permil' => 250,
        'braking_only' => false,
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_du_Sal%C3%A8ve&oldid=235284228',
        'source_title' => 'Chemin de fer du Salève',
        'quote' => 'Les lignes à voie métrique électrifiées étaient équipées de crémaillères. Les rampes atteignaient 250 ‰.',
    ],
    'archeo-ligne-detrembieres-a-treize-arbres' => [
        'system' => 'abt',
        'extent' => null,
        'max_grade_permil' => 250,
        'braking_only' => false,
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_du_Sal%C3%A8ve&oldid=235284228',
        'source_title' => 'Chemin de fer du Salève',
        'quote' => 'Les lignes à voie métrique électrifiées étaient équipées de crémaillères. Les rampes atteignaient 250 ‰.',
    ],
    'archeo-ligne-de-laon-gare-a-hotel-de-ville' => [
        'system' => 'abt',
        'extent' => 'partial',
        'max_grade_permil' => 130,
        'braking_only' => true,
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Tramway_de_Laon&oldid=240920232',
        'source_title' => 'Tramway de Laon',
        'quote' => 'la crémaillère ne fut utilisée que pour assurer le freinage des rames ; une rampe variant de 9 % à 13 %',
    ],
    'archeo-ligne-de-munster-a-col-de-la-schlucht' => [
        'system' => 'strub',
        'extent' => 'partial',
        'max_grade_permil' => 220,
        'braking_only' => false,
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Tramway_de_Munster_%C3%A0_la_Schlucht&oldid=235536602',
        'source_title' => 'Tramway de Munster à la Schlucht',
        'quote' => 'La section à crémaillère, de type Strub, se situait entre Saegmatt et le centre de cure de l\'Altenberg ; elle atteignait une rampe maximale de 220 mm m−1.',
    ],
];
