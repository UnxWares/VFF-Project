<?php

/**
 * Terminus nommés relus à la main, lus par vff:attach-stations (App\Services\StationTerminusOverrides).
 *
 * La règle des terminus nommés (AttachStations::attachNamedTermini, méthode terminus_name) écarte par
 * prudence les villes à plusieurs gares, les noms prolongés non uniques et les gares loin des bouts du
 * tracé : 42 cas le 19/11/2026 (rapport storage/app/reports/agents/soir/gares-recalcul.md). Chacun a été
 * relu (storage/app/reports/agents/soir/gares-candidats.md) ; le soir même, Lyon - La Balme est passée de
 * « attach » à « none » et Sainte-Foy-l'Argentière (Pontcharra, ligne inachevée) a reçu un « none »
 * (storage/app/reports/agents/soir/lignes-incoherences.md) :
 *   - decision « attach » : la source nomme la gare d'extrémité de la ligne ; la gare (slug de
 *     stations) est rattachée avec la méthode terminus_reviewed, la distance au tracé et la provenance
 *     « relu:<source_url> » ;
 *   - decision « none » : aucune source ne désigne une gare de la base (terminus sur une place, gare
 *     propre au réseau secondaire absente de la base, ligne inachevée…) ; la règle des terminus nommés
 *     ne rattache rien pour cette extrémité.
 * Une décision l'emporte aussi sur ce qui est déjà en base : au passage suivant de vff:attach-stations
 * (même --years-only), le terminus nommé de l'extrémité décidée et le terminus relu dont la gare n'est
 * plus celle d'une décision « attach » de la ligne sont retirés.
 *
 * Clés : line (slug de lines), terminus (extrémité telle qu'écrite dans le nom de la ligne),
 * decision, station (slug, pour « attach »), source_url (permalien oldid, obligatoire pour « attach »),
 * reason. Faits seulement (dates, noms de gares) : aucun texte des articles n'est repris.
 * Pas de tiret long dans les textes (consigne éditoriale).
 *
 * @return list<array{line: string, terminus: string, decision: 'attach'|'none', station?: string, source_url: ?string, reason: string}>
 */
$wp = fn (string $title, int $oldid) => 'https://fr.wikipedia.org/w/index.php?title='.rawurlencode(str_replace(' ', '_', $title)).'&oldid='.$oldid;

$roguet = $wp('Gare de Toulouse-Roguet', 240777604);
$noSource = 'aucune source consultée ne nomme la gare d\'extrémité ; ';

return [
    // ── Rattachés : la source nomme la gare d'extrémité ─────────────────────────────────

    // Gare CFSO de Toulouse-Roguet (1900-1949), point de départ des lignes de Boulogne-sur-Gesse, Cadours,
    // Sabarat, Sainte-Foy-de-Peyrolières et Lévignac (articles de la gare, de la ligne de Sabarat et des CFSO).
    ['line' => 'archeo-ligne-de-toulouse-a-cadours', 'terminus' => 'Toulouse', 'decision' => 'attach', 'station' => 'toulouse-roguet',
        'source_url' => $roguet, 'reason' => 'la ligne de Cadours partait de la gare CFSO de Toulouse-Roguet'],
    ['line' => 'archeo-ligne-de-toulouse-a-levignac', 'terminus' => 'Toulouse', 'decision' => 'attach', 'station' => 'toulouse-roguet',
        'source_url' => $roguet, 'reason' => 'la ligne de Lévignac partait de la gare CFSO de Toulouse-Roguet'],
    ['line' => 'archeo-ligne-de-toulouse-a-sabarat', 'terminus' => 'Toulouse', 'decision' => 'attach', 'station' => 'toulouse-roguet',
        'source_url' => $wp('Ligne de Toulouse-Roguet à Sabarat', 241100595), 'reason' => 'origine de la ligne en gare de Toulouse-Roguet'],
    ['line' => 'archeo-ligne-de-toulouse-a-sainte-foy-de-peyrolieres', 'terminus' => 'Toulouse', 'decision' => 'attach', 'station' => 'toulouse-roguet',
        'source_url' => $roguet, 'reason' => 'la ligne de Sainte-Foy-de-Peyrolières partait de la gare CFSO de Toulouse-Roguet'],

    // Gare en impasse de Saint-Vaast-la-Hougue, ouverte avec la ligne le 20/04/1886, fermée avec elle en 1950, PK 25.
    ['line' => 'archeo-ligne-de-valognes-a-saint-vaast', 'terminus' => 'Saint-Vaast', 'decision' => 'attach', 'station' => 'saint-vaast-la-hougue',
        'source_url' => $wp('Gare de Saint-Vaast-la-Hougue', 229007670), 'reason' => 'gare en impasse de la ligne au PK 25, ouverte le 20/04/1886 et fermée en 1950 avec elle'],

    // Réseau à voie de 60 cm « en étoile autour de la gare de Chambéry » ; électrobus de 1930 « entre les gares de
    // Chambéry et de Chignin » à la place du tramway.
    ['line' => 'archeo-ligne-de-chambery-a-chignin', 'terminus' => 'Chambéry', 'decision' => 'attach', 'station' => 'chambery-challes-les-eaux',
        'source_url' => $wp('Tramway de la Savoie', 236094838), 'reason' => 'réseau organisé autour de la gare de Chambéry, seule gare de la ville'],

    // Embranchement de 4,9 km « partant de la gare de Moulis » vers la Gironde (vœu de 1878, compagnie du Médoc).
    ['line' => 'archeo-ligne-de-moulis-a-port-de-lamarque', 'terminus' => 'Moulis', 'decision' => 'attach', 'station' => 'moulis-listrac',
        'source_url' => $wp('Lamarque (Gironde)', 240436342), 'reason' => 'embranchement partant de la gare de Moulis ; dates d\'exploitation inconnues'],

    // ── Rien : aucune gare de la base n'est la gare d'extrémité ─────────────────────────────

    ['line' => 'archeo-ligne-daix-a-marseille', 'terminus' => 'Aix', 'decision' => 'none', 'source_url' => $wp('Ancien tramway de Marseille', 239738009),
        'reason' => 'tramway Marseille - Aix de 1903, distinct de la ligne PLM ; terminus aixois non nommé, gare d\'Aix-en-Provence à 730 m du bout du tracé'],
    ['line' => 'archeo-ligne-detaples-au-touquet', 'terminus' => 'Le Touquet', 'decision' => 'none', 'source_url' => $wp("Tramway d'Étaples à Paris-Plage", 236094248),
        'reason' => 'le tramway finissait à Paris-Plage ; Le Touquet-Aéroport est au milieu du tracé'],
    ['line' => 'archeo-ligne-dexcideuil-a-saint-yrieix', 'terminus' => 'Excideuil', 'decision' => 'none', 'source_url' => $wp('Ligne de Périgueux à Saint-Yrieix', 238652133),
        'reason' => 'voie métrique à Excideuil dès 1887, avant la gare PO (1898) : station propre au réseau départemental, absente de la base'],
    ['line' => 'archeo-ligne-de-belfort-a-rechesy', 'terminus' => 'Belfort', 'decision' => 'none', 'source_url' => $wp('Chemins de fer du Territoire de Belfort', 233324944),
        'reason' => $noSource.'la gare de Belfort est à 957 m du bout du tracé'],
    ['line' => 'archeo-ligne-de-belfort-a-sochaux', 'terminus' => 'Belfort', 'decision' => 'none', 'source_url' => $wp('Chemins de fer du Territoire de Belfort', 233324944),
        'reason' => $noSource.'la gare de Belfort est à 954 m du bout du tracé'],
    ['line' => 'archeo-ligne-de-blois-a-clery', 'terminus' => 'Cléry', 'decision' => 'none', 'source_url' => $wp('Tramways électriques de Loir-et-Cher', 239704573),
        'reason' => 'terminus à Cléry en correspondance avec Orléans - Neung, sans dire si la gare était commune ; la fiche « Cléry » est placée au centre de la commune (500 m près)'],
    ['line' => 'archeo-ligne-de-boulogne-au-portel', 'terminus' => 'Boulogne', 'decision' => 'none', 'source_url' => $wp('Ligne de tramway de Boulogne-sur-Mer au Portel', 238874156),
        'reason' => 'terminus boulonnais place Dalton ; la ligne passait devant la gare du quai Chanzy, pas à Boulogne-Maritime'],
    // Gares de tête du quai Chanzy : la première gare de Boulogne-Ville (1855-1962), créée le 22/11/2026 dans
    // database/data/gares_manuelles.php (gare de la base de 1962 sur l'autre rive de la Liane, Boulogne-Tintelleries
    // plus au nord). Bonningues : bout du tracé à 64 m de la gare ; Saint-Martin : bout à 551 m (tracé source court).
    ['line' => 'archeo-ligne-de-boulogne-a-saint-martin', 'terminus' => 'Boulogne', 'decision' => 'attach', 'station' => 'boulogne-ville-ancienne-gare',
        'source_url' => $wp('Chemin de fer Boulogne - Bonningues', 223515850),
        'reason' => 'section partant de la gare du quai Chanzy, première gare de Boulogne-Ville (gare de 1962 sur l\'autre rive)'],
    ['line' => 'archeo-ligne-de-boulogne-ville-a-bonningues', 'terminus' => 'Boulogne-Ville', 'decision' => 'attach', 'station' => 'boulogne-ville-ancienne-gare',
        'source_url' => $wp('Chemin de fer Boulogne - Bonningues', 223515850),
        'reason' => 'gare de jonction du quai Chanzy, première gare de Boulogne-Ville ; la fiche de 1962 est sur l\'autre rive de la Liane'],
    ['line' => 'archeo-ligne-de-bourg-a-villefranche', 'terminus' => 'Bourg', 'decision' => 'none', 'source_url' => $wp("Tramways de l'Ain", 241068902),
        'reason' => 'terminus « Bourg-en-Bresse ESE », gare propre au tramway, distincte de la gare PLM'],
    ['line' => 'archeo-ligne-de-decazeville-a-marcillac', 'terminus' => 'Marcillac', 'decision' => 'none', 'source_url' => null,
        'reason' => $noSource.'la gare « Marcillac » de la base est à 1,2 km du bout du tracé et le nom est porté par plusieurs communes'],
    ['line' => 'archeo-ligne-de-givet-a-flohimont', 'terminus' => 'Givet', 'decision' => 'none', 'source_url' => null,
        'reason' => $noSource.'gare de Givet à 523 m du bout du tracé'],
    ['line' => 'archeo-ligne-de-grenoble-a-veurey', 'terminus' => 'Grenoble', 'decision' => 'none', 'source_url' => $wp('Ligne de tramway de Grenoble à Veurey-Voroize', 240410495),
        'reason' => 'tramway ; l\'article ne nomme pas le terminus grenoblois, la gare de Grenoble est au milieu du tracé'],
    ['line' => 'archeo-ligne-de-grenoble-a-voreppe', 'terminus' => 'Grenoble', 'decision' => 'none', 'source_url' => null,
        'reason' => $noSource.'tramway, gare de Grenoble à 1 km du bout du tracé'],
    ['line' => 'lo-137000', 'terminus' => 'Logelbach', 'decision' => 'none', 'source_url' => $wp('Chemin de fer de la vallée de Kaysersberg', 241121876),
        'reason' => 'origine officielle de la ligne 137000 (bifurcation à Logelbach) mais les trains partaient de Colmar, déjà rattachée ; arrêt à Logelbach non attesté'],
    // Relu de nouveau le 19/11/2026 au soir (rapport soir/lignes-incoherences.md) : d'abord rattachée à Lyon-Est comme
    // ligne du Chemin de fer de l'Est de Lyon, c'est la ligne 16 des tramways OTL (Lyon - Pont de Sault-Brénaz par
    // Jons, Villette-d'Anthon et La Balme), partie du quai Jules-Courmont ; son tracé ne suit le CFEL que sur 0,3 km.
    ['line' => 'archeo-ligne-de-lyon-a-la-balme', 'terminus' => 'Lyon', 'decision' => 'none', 'source_url' => $wp('Ancien tramway de Lyon', 241111716),
        'reason' => 'ligne 16 des tramways OTL, au départ du terminus du quai Jules-Courmont (lignes 16 et 17), pas de la gare de Lyon-Est du CFEL'],
    ['line' => 'archeo-ligne-de-lyon-a-neuville-sur-saone', 'terminus' => 'Lyon', 'decision' => 'none', 'source_url' => null,
        'reason' => $noSource.'tramway ; Lyon-St-Paul n\'est qu\'une gare proche du bout du tracé'],
    ['line' => 'archeo-ligne-de-marseille-a-allauch', 'terminus' => 'Marseille', 'decision' => 'none', 'source_url' => $wp('Ancien tramway de Marseille', 239738009),
        'reason' => 'tramway d\'Allauch (ligne de la Croix-Rouge à Allauch, 1908), pas à la gare Saint-Charles'],
    ['line' => 'archeo-ligne-de-montauban-a-monclar', 'terminus' => 'Montauban', 'decision' => 'none', 'source_url' => $wp('Tramways de Tarn-et-Garonne', 237184582),
        'reason' => 'gare tête de réseau des tramways à Villebourbon, à proximité de la gare du Midi mais distincte, absente de la base'],
    ['line' => 'archeo-ligne-de-mulhouse-a-pfastatt-centre', 'terminus' => 'Mulhouse', 'decision' => 'none', 'source_url' => null,
        'reason' => $noSource.'tramway urbain, Mulhouse-Nord n\'est qu\'une gare proche du tracé'],
    ['line' => 'archeo-ligne-de-mulhouse-a-wittenheim', 'terminus' => 'Mulhouse', 'decision' => 'none', 'source_url' => null,
        'reason' => $noSource.'tramway urbain, Mulhouse-Nord n\'est qu\'une gare proche du tracé'],
    ['line' => 'archeo-ligne-de-nancy-a-dombasle-sur-meurthe', 'terminus' => 'Dombasle-sur-Meurthe', 'decision' => 'none', 'source_url' => $wp('Ancien tramway de Nancy', 225785852),
        'reason' => 'tramway suburbain (1910) parti du Marché central ; le terminus de Dombasle n\'est pas dit à la gare'],
    ['line' => 'archeo-ligne-de-pontcharra-a-sainte-foy-largentiere', 'terminus' => 'Pontcharra', 'decision' => 'none', 'source_url' => $wp('Chemins de fer départementaux de Rhône et Loire', 226987797),
        'reason' => 'ligne inachevée selon la source ; la fiche Pontcharra-St-Forgeux de la base date de 2004'],
    // Autre extrémité de la même ligne inachevée (soir/lignes-incoherences.md) : la règle des terminus nommés y avait
    // rattaché la gare PLM de Sainte-Foy-l'Argentière, alors qu'aucun train de la compagnie n'y est arrivé.
    ['line' => 'archeo-ligne-de-pontcharra-a-sainte-foy-largentiere', 'terminus' => 'Sainte-Foy-l’Argentière', 'decision' => 'none',
        'source_url' => $wp('Chemins de fer départementaux de Rhône et Loire', 226987797),
        'reason' => 'ligne inachevée, jamais ouverte : la gare PLM de Sainte-Foy-l\'Argentière n\'en a jamais été le terminus'],
    ['line' => 'archeo-ligne-de-saint-etienne-a-saint-genest-lerpt', 'terminus' => 'Saint-Etienne', 'decision' => 'none', 'source_url' => null,
        'reason' => $noSource.'tramway ; Saint-Étienne a plusieurs gares, aucune au bout du tracé'],
    ['line' => 'archeo-ligne-de-saint-malo-a-cancale-la-houle', 'terminus' => 'Saint-Malo', 'decision' => 'none', 'source_url' => $wp('Tramways bretons', 227452674),
        'reason' => 'ligne des Tramways bretons partant de Paramé, pas de la gare de Saint-Malo'],
    ['line' => 'archeo-ligne-de-strasbourg-a-marckolsheim', 'terminus' => 'Strasbourg', 'decision' => 'none', 'source_url' => $wp('Ancien tramway de Strasbourg', 239874624),
        'reason' => 'départ de la « gare locale » près de la porte de l\'Hôpital, absente de la base, pas de Strasbourg-Neudorf'],
    ['line' => 'archeo-ligne-de-taurinya-a-prades', 'terminus' => 'Prades', 'decision' => 'none', 'source_url' => null,
        'reason' => $noSource.'la gare de Prades est à 236 m du bout du tracé, sans source sur la ligne'],
    ['line' => 'archeo-ligne-de-thionville-a-fontoy', 'terminus' => 'Thionville', 'decision' => 'none', 'source_url' => $wp('Tramway de Thionville', 224566933),
        'reason' => 'tramway suburbain ; terminus thionvillois non nommé, gare de Thionville à 791 m du bout du tracé'],
    ['line' => 'archeo-ligne-de-toulon-a-ollioules', 'terminus' => 'Toulon', 'decision' => 'none', 'source_url' => $wp('Tramway de Toulon', 240962569),
        'reason' => 'ligne 1 du tramway urbain ; terminus toulonnais non nommé, gare de Toulon au milieu du tracé'],
    ['line' => 'archeo-ligne-de-toulouse-a-villemur', 'terminus' => 'Toulouse', 'decision' => 'none', 'source_url' => $wp('Chemins de fer du Sud-Ouest', 241040330),
        'reason' => 'ligne partant de Toulouse-Bonnefoy (embranchement vers Toulouse-Pont-Matabiau), gares absentes de la base ; pas de Toulouse-Matabiau'],
    ['line' => 'archeo-tramway-de-grand-place-a-wambrechies-telb-o', 'terminus' => 'Wambrechies', 'decision' => 'none', 'source_url' => null,
        'reason' => $noSource.'tramway urbain, gare de Wambrechies à 1,1 km du bout du tracé'],
    ['line' => 'archeo-tramway-de-place-rihour-a-wattignies-telb-l', 'terminus' => 'Wattignies', 'decision' => 'none', 'source_url' => null,
        'reason' => $noSource.'tramway urbain, gare de Wattignies-Templemars à 526 m du bout du tracé'],
    ['line' => 'archeo-tramway-de-place-de-la-gare-a-lomme-telb-i', 'terminus' => 'Lomme', 'decision' => 'none', 'source_url' => null,
        'reason' => $noSource.'tramway urbain, terminus de Lomme non situé'],
    ['line' => 'archeo-ligne-de-rennes-a-saint-malo', 'terminus' => 'Saint-Malo', 'decision' => 'none', 'source_url' => $wp("Tramways d'Ille-et-Vilaine", 240622737),
        'reason' => 'terminus malouin non nommé (Rennes : Croix-Mission) ; la gare de Saint-Malo est à 768 m du bout du tracé'],
    ['line' => 'archeo-tramway-de-wattrelos-a-lannoy-elrt-t', 'terminus' => 'Lannoy', 'decision' => 'none', 'source_url' => null,
        'reason' => $noSource.'tramway urbain, gare de Lannoy à 524 m du bout du tracé'],
];
