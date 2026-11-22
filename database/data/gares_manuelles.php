<?php

/**
 * Gares absentes de toutes les sources importées (SNCF, Wikidata, OSM, OHM), saisies à la main avec
 * une source vérifiable : lues par vff:import-station-sources (source « manual », App\Services\ManualStations),
 * puis dédoublonnées par vff:consolidate-stations comme une gare à part entière. Une gare manuelle ne se
 * fond jamais dans une autre par son nom ou sa proximité : elle n'est ajoutée que si aucune fiche ne la
 * porte déjà (contrôle à faire avant, par le nom ET la position).
 *
 * Clés : ref (identifiant stable, slug minuscule), name, lat, lon (WGS 84, d'une source citée),
 * opened_on / opened_precision, closed_on / closed_precision (facultatifs, day | month | year),
 * source_url (permalien https), quote (fait lu, court), reason (pourquoi la gare manque), network
 * (facultatif). Faits seulement : aucun texte d'article repris. Pas de tiret long.
 *
 * Gares que les sources structurées n'ont pas et que rien de citable ne situe (Toulouse-Bonnefoy, gare
 * locale de Strasbourg, gare des Tramways de Tarn-et-Garonne à Villebourbon) : non créées, voir
 * storage/app/reports/agents/aprem-2111/gares-de-tete.md.
 *
 * @return list<array<string, mixed>>
 */
$wp = fn (string $title, int $oldid) => 'https://fr.wikipedia.org/w/index.php?title='.rawurlencode(str_replace(' ', '_', $title)).'&oldid='.$oldid;

return [
    // Première gare de Boulogne-Ville (rive gauche de la Liane, quai Chanzy : le tramway du Portel et la ligne de
    // Boulogne à Bonningues y avaient leur gare de correspondance avec le Nord). Wikipédia donne ses coordonnées
    // (emplacement actuel du casino) ; la fiche « Boulogne-Ville » de la base est la gare de 1962, sur l'autre rive.
    [
        'ref' => 'boulogne-ville-ancienne-gare',
        'name' => 'Boulogne-Ville (ancienne gare)',
        'lat' => 50.721798,
        'lon' => 1.599333,
        'opened_on' => '1855-01-01',
        'opened_precision' => 'year',
        'closed_on' => '1962-01-01',
        'closed_precision' => 'year',
        'network' => 'Nord',
        'source_url' => $wp('Gare de Boulogne-Ville', 228948464),
        'quote' => 'première gare construite en 1855 sur la rive gauche de la Liane ; remplacée par la gare actuelle inaugurée le 28 juin 1962',
        'reason' => 'gare de tête des chemins de fer économiques du Nord (quai Chanzy) et du tramway du Portel, absente de la base (la fiche Boulogne-Ville date de 1962)',
    ],
];
