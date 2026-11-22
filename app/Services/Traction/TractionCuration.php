<?php

namespace App\Services\Traction;

/**
 * Chronologies d'électrification et d'écartement établies à la main, pour les
 * lignes pionnières ou singulières que les sources automatiques lisent mal.
 * Chaque entrée cite sa source (articles Wikipédia consultés le 19/10/2026).
 *
 * ELECTRIFICATION : par code RFN.
 *   - replace = true : la chronologie REMPLACE tous les événements de la ligne ;
 *   - replace = false : les événements s'AJOUTENT (portée « toute la ligne »).
 * GAUGE_CONVERSIONS : changements d'écartement datés, par code RFN ou motif de nom.
 * GAUGE_LINES : écartement d'origine de lignes disparues, par motif de nom.
 */
final class TractionCuration
{
    /** @var array<int, array{replace: bool, events: list<array{0: string, 1: string, 2: ?int}>, note: string}> code RFN (clé entière en PHP) => chronologie */
    public const ELECTRIFICATION = [
        // Ligne de Cerdagne (« Train jaune ») : construite électrique, troisième rail 850 V
        // continu, ouverte de Villefranche à Mont-Louis en 1910 (Wikipédia « Ligne de Cerdagne »).
        '669000' => ['replace' => true, 'events' => [['1910-01-01', 'electrification', 850]],
            'note' => 'Wikipédia, Ligne de Cerdagne : troisième rail 850 V dès l\'ouverture (1910)'],
        // Perpignan - Villefranche : laboratoire du Midi en 12 kV 16 2/3 Hz (1912), resté en
        // 12 kV après 1920, mis hors tension en 1971, réélectrifié en 1 500 V le 24 mai 1984.
        '679000' => ['replace' => true, 'events' => [
            ['1912-01-01', 'electrification', 12000],
            ['1971-01-01', 'deelectrification', null],
            ['1984-05-24', 'electrification', 1500],
        ], 'note' => 'Wikipédia, Ligne de Perpignan à Villefranche-Vernet-les-Bains, § Électrification'],
        // Cannes-la-Bocca - Grasse : essai PLM 12 kV 25 Hz (1910-1914), caténaire démontée
        // « probablement dès 1914 » ; 25 kV 50 Hz en 2004-2005 (événements existants).
        '944000' => ['replace' => false, 'events' => [['1914-12-31', 'deelectrification', null]],
            'note' => 'Wikipédia, Ligne de Cannes-la-Bocca à Grasse'],
        // Pau - Canfranc : électrifiée intégralement en 1 500 V par le Midi (ouverture
        // internationale 1928) ; Oloron - Canfranc désélectrifiée en 1992 (événement localisé),
        // caténaire Pau - Oloron plus alimentée depuis septembre 2008.
        '664000' => ['replace' => false, 'events' => [
            ['1928-07-18', 'electrification', 1500],
            ['2008-09-01', 'deelectrification', null],
        ], 'note' => 'Wikipédia, Ligne de Pau à Canfranc (frontière)'],
        // Puteaux - Issy-Plaine (ligne des Moulineaux) : troisième rail latéral en 650 V continu le
        // 22 juillet 1928, « porté à 750 volts CC après 1933 » (année exacte non donnée : 1934 est la
        // première année possible), exploitation ferroviaire arrêtée le 21 mai 1993, troisième rail
        // déposé pour le tramway T2. La SNCF ne connaît plus ce troisième rail (« non électrifiée »).
        // Wikipédia, Ligne de Puteaux à Issy-Plaine, rév. 241186256, § Électrification et Desserte.
        '972000' => ['replace' => true, 'events' => [
            ['1928-07-22', 'electrification', 650],
            ['1934-01-01', 'electrification', 750],
            ['1993-05-21', 'deelectrification', null],
        ], 'note' => 'Wikipédia, Ligne de Puteaux à Issy-Plaine (rév. 241186256) : troisième rail 650 V (1928), 750 V après 1933, fin en 1993'],
    ];

    /** @var array<int, array{year: int, from_mm: int, to_mm: int, note: string}> code RFN => conversion */
    public const GAUGE_CONVERSIONS = [
        // Réseau breton : Carhaix - Guingamp mise à l'écartement standard du 14 février au 3 juillet 1967.
        '485000' => ['year' => 1967, 'from_mm' => 1000, 'to_mm' => 1435, 'note' => 'Wikipédia, Réseau breton § Fin de la voie métrique'],
        // Guingamp - Paimpol : voie métrique (1894), troisième rail à voie normale ajouté en 1924
        // (voie mixte), rail métrique déposé en 1953.
        '486000' => ['year' => 1924, 'from_mm' => 1000, 'to_mm' => 1435, 'note' => 'Wikipédia, Ligne de Guingamp à Paimpol'],
    ];

    /** @var array<string, array{mm: int, note: string}> motif de nom de ligne => écartement */
    public const GAUGE_LINES = [
        // Réseau breton (426 km à voie métrique centrés sur Carhaix, fermés en 1967).
        '/^Ligne de Carhaix à (Morlaix|Rosporden|Châteaulin|Loudéac|Camaret)/u' => ['mm' => 1000, 'note' => 'Réseau breton'],
        '/^Ligne de (Loudéac à La Brohinière|Châteaulin à Camaret|Plémy à Loudéac)/u' => ['mm' => 1000, 'note' => 'Réseau breton'],
    ];

    /**
     * Événements d'électrification curés d'une ligne (code RFN), ou null.
     *
     * @return ?array{replace: bool, events: array<int, array{date: string, kind: string, voltage_v: ?int}>}
     */
    public static function electrification(?string $code): ?array
    {
        $entry = $code !== null ? (self::ELECTRIFICATION[$code] ?? null) : null;
        if ($entry === null) {
            return null;
        }

        return ['replace' => $entry['replace'], 'events' => array_map(
            fn ($e) => ['date' => $e[0], 'kind' => $e[1], 'voltage_v' => $e[2]],
            $entry['events'],
        )];
    }

    /** Conversion d'écartement curée d'une ligne (code RFN), ou null. */
    public static function gaugeConversion(?string $code): ?array
    {
        return $code !== null ? (self::GAUGE_CONVERSIONS[$code] ?? null) : null;
    }

    /** Écartement curé d'une ligne d'après son nom, ou null. */
    public static function gaugeForName(string $name): ?int
    {
        foreach (self::GAUGE_LINES as $re => $g) {
            if (preg_match($re, $name)) {
                return $g['mm'];
            }
        }

        return null;
    }
}
