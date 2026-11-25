<?php

namespace App\Services\Rinf;

/**
 * Paramètres des voies déclarés au RINF (RinfQueries::trackParameters) : vocabulaire de l'ERA lu le 25/11/2026
 * (libellés anglais des concepts http://data.europa.eu/949/concepts/…), colonnes de rinf_tracks et formes
 * normalisées qui servent à comparer le RINF aux autres sources de VFF (RinfParameterComparison).
 */
final class RinfParameters
{
    /** Colonnes de rinf_tracks remplies par les paramètres (null pour une voie de point d'exploitation). */
    public const COLUMNS = [
        'contact_line', 'energy_supply', 'voltage_v', 'frequency_hz', 'train_protection', 'etcs_level', 'gsm_r', 'gsm_r_version',
        'gauging_profiles', 'load_category', 'national_load_class', 'track_gauge_mm',
    ];

    /** Colonnes JSON (listes). */
    public const JSON_COLUMNS = ['train_protection', 'gauging_profiles'];

    /** Type de ligne de contact (concept contact-line-systems). */
    public const CONTACT_LINES = [10 => 'overhead', 20 => 'third_rail', 30 => 'fourth_rail', 40 => 'none'];

    /** Système d'alimentation (concept energy-supply-systems) : tension (V) et fréquence (Hz, 0 en continu). */
    public const ENERGY_SUPPLY = [
        'AC10' => [25000, 50.0], 'AC20' => [15000, 16.7], 'DC30' => [3000, 0.0], 'DC40' => [1500, 0.0],
        'DC60' => [750, 0.0], 'DC70' => [650, 0.0], 'DC80' => [600, 0.0], 'DC90' => [850, 0.0],
    ];

    /**
     * Systèmes de contrôle de vitesse et de protection hérités (concepts train-protection-legacy-systems et
     * other-protection-control-warning, mêmes numéros pour les mêmes systèmes) ; 0 et 100 = aucun.
     */
    public const PROTECTION = [
        1 => 'ALSN', 2 => 'ASFA', 3 => 'ATB', 4 => 'ATP-VR/RHK', 5 => 'BACC', 6 => 'CAWS and ATP', 7 => 'Crocodile', 8 => 'Ebicab',
        9 => 'EVM', 10 => 'GW ATP', 11 => 'Indusi/PZB', 12 => 'KVB', 13 => 'LS', 14 => 'LZB', 15 => 'MEMOR II+', 16 => 'RETB',
        17 => 'RSDD/SCMT', 18 => 'SELCAB', 19 => 'SHP', 20 => 'TBL', 21 => 'TPWS/AWS', 22 => 'TVM', 23 => 'ZUB 123', 24 => 'ZUB 121',
        25 => 'ATB First generation', 26 => 'ATB new generation', 27 => 'ATP', 28 => 'CAWS', 29 => 'Chiltern-ATP', 30 => 'DAAT',
        31 => 'EBICAB 700', 32 => 'EBICAB 900 ES', 33 => 'EuroSIGNUM', 34 => 'EuroZUB', 35 => 'Indusi', 36 => 'KCVB', 37 => 'KCVP',
        38 => 'KVBP', 39 => 'Mechanical Trainstops', 40 => 'NEXTEO', 41 => 'PKP radio system with Radiostop function', 42 => 'SSC',
        43 => 'TBL 1', 44 => 'TBL 2', 45 => 'TBL1+', 46 => 'TVM 300', 47 => 'TVM 430', 48 => 'ATC v2', 49 => 'ATC vR',
        50 => 'EBICAB 700 BU', 51 => 'EBICAB 700 PT', 52 => 'GNT', 53 => 'INDUSI I60', 54 => 'LZB', 55 => 'LZB ES', 56 => 'PZB 90',
        57 => 'SCMT + RSC', 58 => 'SCMT', 59 => 'LZB L72', 60 => 'LZB CE I', 61 => 'LZB CE II',
    ];

    /**
     * Numéros du tableau PROTECTION absents du concept other-protection-control-warning (vocabulaire lu le 25/11/2026) :
     * un tel code est servi tel quel, jamais pris pour le système de même numéro de l'autre concept.
     */
    private const OTHER_PROTECTION_UNDEFINED = [3, 5, 6, 8, 11, 14, 15, 17, 18, 20, 22, 24, 31, 54];

    /** Ordre d'affichage : ETCS et signalisation en cabine, contrôle de vitesse, puis répétition des signaux. */
    private const PROTECTION_ORDER = ['ETCS L2', 'ETCS L1', 'TVM 430', 'TVM 300', 'TVM', 'KVB', 'KVBP', 'KCVB', 'KCVP', 'DAAT', 'Crocodile'];

    /** Niveau ETCS (concept etcs-levels) ; 10 = « N », aucun ETCS. */
    public const ETCS_LEVELS = [10 => 'none', 20 => '1', 30 => '2', 50 => '0', 60 => 'ntc', 70 => 'under_construction', 80 => '3'];

    /**
     * Versions GSM-R dont le vocabulaire de l'ERA donne le libellé (concept gsmr-versions). Les codes 10, 20 et 40,
     * déclarés par d'autres gestionnaires que SNCF Réseau (graphes 3436, CY76, 60Z7, 0069), n'y sont pas définis : servis
     * tels quels (« gsmr-versions/40 »), le GSM-R restant déclaré.
     */
    public const GSMR_VERSIONS = ['00' => 'Baseline 0', '50' => 'Baseline 1'];

    /** Gabarits (concept gaugings). */
    public const GAUGINGS = [
        10 => 'GA', 20 => 'GB', 30 => 'GC', 40 => 'G1', 50 => 'DE3', 60 => 'G2', 70 => 'GB1', 80 => 'GB2', 90 => 'BE1', 100 => 'BE2',
        110 => 'BE3', 111 => 'BE4', 120 => 'FR-3.3', 130 => 'PTb', 140 => 'PTb+', 150 => 'PTc', 160 => 'FIN1', 170 => 'SEa', 180 => 'SEc',
        190 => 'DE1', 200 => 'DE2', 210 => 'Z-GCD', 220 => 'UK1', 230 => 'UK1[D]', 240 => 'W6', 250 => 'FS', 260 => 'S', 270 => 'GHE16',
        280 => 'GEA16', 290 => 'GEB16', 300 => 'GEC16', 340 => 'GI1', 341 => 'FR-3.4.1', 342 => 'FR-3.4.2', 350 => 'GI2', 360 => 'GI3',
        370 => 'GEE10', 380 => 'GED10', 411 => 'M30', 412 => 'M80', 413 => 'Tram-train 2.40', 414 => 'Tram-train 2.65',
        415 => 'Métrique BA', 416 => 'Métrique SGV', 417 => 'Métrique Cerd.', 530 => 'GE14', 540 => 'GEC14',
    ];

    /** Catégories de ligne de la norme EN 15528 (concept load-capability-line-categories). */
    public const LOAD_CATEGORIES = [
        10 => 'A', 20 => 'B1', 30 => 'B2', 40 => 'C2', 50 => 'C3', 60 => 'C4', 70 => 'D2', 80 => 'D3', 90 => 'D4', 100 => 'D4xL',
        110 => 'E4', 120 => 'E5', 130 => 'RA1', 140 => 'RA2', 150 => 'RA3', 160 => 'RA4', 170 => 'RA5', 180 => 'RA6', 190 => 'RA7',
        200 => 'RA8', 210 => 'RA9', 220 => 'RA10', 230 => 'HS17',
    ];

    /** Écartement nominal en mm (concept nominal-track-gauges). */
    public const TRACK_GAUGES = [10 => 750, 20 => 1000, 30 => 1435, 40 => 1520, 50 => 1524, 60 => 1600, 70 => 1668, 80 => 760];

    /**
     * Systèmes que le RINF et VFF (line_signalling, SNCF Réseau) savent tous deux nommer, et que la comparaison
     * confronte : KVB (KVBP compris, une variante du KVB), TVM 300, TVM 430, ETCS niveau 1 et 2.
     */
    public const COMPARED_PROTECTION = ['ETCS L1', 'ETCS L2', 'KVB', 'TVM 300', 'TVM 430'];

    /** Systèmes comparés de signalisation en cabine (TVM, ETCS). */
    public const CAB_PROTECTION = ['ETCS L1', 'ETCS L2', 'TVM 300', 'TVM 430'];

    /**
     * Les deux clés comparées du contrôle de vitesse (`KVB+TVM 430`, `TVM 430`, `none`) ne diffèrent que par le KVB, sur
     * une portion où l'une des deux a la TVM ou l'ETCS. Le RINF déclare le KVB sur les voies de LGV, le jeu des lignes
     * équipées de KVB de SNCF Réseau ne l'y met pas (2 337 km le 25/11/2026) : décision du 25/11/2026, l'écart est montré
     * tel que déclaré mais n'est pas compté comme un désaccord (accord du contrôle de vitesse sur la France : 92,0 % de la
     * longueur comparable au lieu de 85,1 %).
     */
    public static function differsOnlyByKvb(string $a, string $b): bool
    {
        $split = fn (string $key): array => array_values(array_filter(explode('+', $key), fn (string $s): bool => $s !== '' && $s !== 'none'));
        [$x, $y] = [$split($a), $split($b)];
        if (self::compared($x) === self::compared($y) || array_intersect([...$x, ...$y], self::CAB_PROTECTION) === []) {
            return false;
        }

        return self::compared(array_values(array_diff($x, ['KVB']))) === self::compared(array_values(array_diff($y, ['KVB'])));
    }

    /**
     * Voie principale d'une section : voie 1, voie 2 ou voie unique (« Voie V1 de Paris-12 à Marseille-01 »,
     * « Voie 1 LGV SEA », « Voie UNIQUE de … », « 1 »), à l'exclusion des voies bis, ter ou quater, des voies numérotées
     * autrement (« Voie 1Bis », « Voie V1H », « Voie 12 », « Voie 7V de Plibou ») et des voies qu'un code court en
     * capitales distingue avant le lieu (« Voie 1 SP de Bréauté-Beuzeville », « Voie 1 RV de Villognon », « Voie unique
     * VMA de … » ; « Voie 1 LGV SEA » reste la voie 1).
     */
    public static function isMainTrack(string $ref): bool
    {
        $ref = trim($ref);

        return preg_match('/^(?:voie\s+)?(?:v?[12]|unique|vu)(?![\p{L}\p{N}])/iu', $ref) === 1
            && preg_match('/^(?i:voie\s+)?(?i:v?[12]|unique|vu)\s+(?:(?i:bis|ter|quater)(?![\p{L}\p{N}])|[A-Z]{1,3}\s+(?:de|d[\'’])(?![\p{L}\p{N}]))/u', $ref) !== 1;
    }

    /**
     * Libellé d'un système de protection d'après son concept, null pour « aucun » (0, 100) ou hors du vocabulaire ;
     * un numéro que le concept ne définit pas est servi tel quel (« other-protection-control-warning/31 »).
     */
    public static function protectionLabel(string $concept): ?string
    {
        if (preg_match('~/(train-protection-legacy-systems|other-protection-control-warning)/(\d+)$~', $concept, $m) !== 1) {
            return null;
        }
        $code = (int) $m[2];
        if ($code === 0 || $code === 100) {
            return null;
        }
        if ($m[1] === 'other-protection-control-warning' && in_array($code, self::OTHER_PROTECTION_UNDEFINED, true)) {
            return $m[1].'/'.$m[2];
        }

        return self::PROTECTION[$code] ?? $m[1].'/'.$m[2];
    }

    /**
     * @param  list<string>  $labels
     * @return list<string>
     */
    public static function sortProtection(array $labels): array
    {
        usort($labels, function (string $a, string $b): int {
            $ia = array_search($a, self::PROTECTION_ORDER, true);
            $ib = array_search($b, self::PROTECTION_ORDER, true);

            return [$ia === false ? 99 : $ia, $a] <=> [$ib === false ? 99 : $ib, $b];
        });

        return $labels;
    }

    /**
     * Gabarits dans l'ordre du vocabulaire de l'ERA (gabarits européens, puis nationaux, puis parties basses).
     *
     * @param  list<string>  $labels
     * @return list<string>
     */
    public static function sortGaugings(array $labels): array
    {
        $rank = array_flip(array_values(self::GAUGINGS));
        usort($labels, fn (string $a, string $b): int => ($rank[$a] ?? 999) <=> ($rank[$b] ?? 999));

        return $labels;
    }

    /**
     * Systèmes comparés d'après le RINF : protection héritée (KVBP compté comme KVB) et niveau ETCS.
     *
     * @param  list<string>  $protection
     * @return list<string>
     */
    public static function comparedFromRinf(array $protection, ?string $etcsLevel): array
    {
        $out = [];
        foreach ($protection as $p) {
            $out[] = match ($p) {
                'KVB', 'KVBP' => 'KVB',
                'TVM 300', 'TVM 430' => $p,
                default => null,
            };
        }
        $out[] = match ($etcsLevel) {
            '1' => 'ETCS L1', '2' => 'ETCS L2', default => null,
        };

        return self::compared($out);
    }

    /**
     * Systèmes comparés d'après line_signalling (SNCF Réseau) : contrôle de vitesse et cantonnement en cabine.
     *
     * @param  list<string>  $trainProtection  KVB, KVBP, TVM-300, TVM-430, ERTMS_L1, ERTMS_L2
     * @param  list<string>  $signalling  codes de cantonnement (BAL, TVM-430, ETCS_L2…)
     * @return list<string>
     */
    public static function comparedFromVff(array $trainProtection, array $signalling): array
    {
        $out = [];
        foreach ([...$trainProtection, ...$signalling] as $p) {
            $out[] = match (true) {
                in_array($p, ['KVB', 'KVBP'], true) => 'KVB',
                $p === 'TVM-300' => 'TVM 300',
                $p === 'TVM-430' => 'TVM 430',
                in_array($p, ['ERTMS_L1', 'ETCS_L1'], true) => 'ETCS L1',
                in_array($p, ['ERTMS_L2', 'ETCS_L2'], true) => 'ETCS L2',
                default => null,
            };
        }

        return self::compared($out);
    }

    /**
     * @param  list<?string>  $values
     * @return list<string>
     */
    private static function compared(array $values): array
    {
        $values = array_values(array_unique(array_filter($values, fn (?string $v): bool => $v !== null)));
        sort($values);

        return $values;
    }

    /** Clé normalisée d'une électrification : `none`, `25000V-50Hz`, `1500V-DC`, `electrified` (tension non dite). */
    public static function electrificationKey(bool $electrified, ?int $voltage, ?float $frequency): string
    {
        if (! $electrified) {
            return 'none';
        }
        if ($voltage === null) {
            return 'electrified';
        }

        return $voltage.'V-'.($frequency === null || $frequency == 0.0 ? 'DC' : rtrim(rtrim(number_format($frequency, 2, '.', ''), '0'), '.').'Hz');
    }
}
