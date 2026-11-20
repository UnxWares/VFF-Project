<?php

namespace App\Services\Traction;

/**
 * Écartement des voies : lecture des indices et écartement d'une portion à une ère.
 *
 * Indices lus :
 *   - tag OSM / OHM `gauge` (« 1435 », « 1000 », « 600 », « 1000;1435 » voie mixte) ;
 *   - BD TOPO `largeur` (Normale → 1435, Etroite → voie étroite d'écartement inconnu,
 *     Large → voie large) ;
 *   - SNCF « lignes LGV et par écartement » (catlig) ;
 *   - noms des tracés archeoferroviaire (« V60 », « VN », « voie de 0m60 »,
 *     « voie métrique », « VM ») et textes des événements (« mise à voie normale en 1967 »).
 *
 * Classes : standard (≈ 1435), metre (1000), narrow (< 1000 ou étroite sans valeur),
 * broad (> 1450). NULL = inconnu : rien n'est inventé.
 */
final class Gauge
{
    public const STANDARD = 'standard';

    public const METRE = 'metre';

    public const NARROW = 'narrow';

    public const BROAD = 'broad';

    /** Classe d'un écartement en mm. */
    public static function classOf(?int $mm): ?string
    {
        return match (true) {
            $mm === null || $mm <= 0 => null,
            $mm >= 1400 && $mm <= 1450 => self::STANDARD,
            $mm >= 990 && $mm <= 1010 => self::METRE,
            $mm > 1450 => self::BROAD,
            default => self::NARROW,
        };
    }

    /**
     * Tag `gauge` OSM/OHM → mm. Voie mixte « 1000;1435 » : l'écartement le plus large
     * (celui du matériel normal qui l'emprunte). « narrow » → null (classe seule).
     */
    public static function fromTag(?string $tag): ?int
    {
        if ($tag === null || trim($tag) === '') {
            return null;
        }
        $values = array_filter(array_map(fn ($v) => is_numeric(trim($v)) ? (int) round((float) trim($v)) : null, explode(';', $tag)));
        $values = array_filter($values, fn ($v) => $v >= 300 && $v <= 2000);

        return $values ? max($values) : null;
    }

    /** BD TOPO `largeur` → [mm, classe]. */
    public static function fromBdtopo(?string $largeur): array
    {
        return match ($largeur) {
            'Normale' => [1435, self::STANDARD],
            'Etroite', 'Étroite' => [null, self::NARROW],
            'Large' => [null, self::BROAD],
            default => [null, null],
        };
    }

    /** SNCF catlig → [mm, classe]. */
    public static function fromSncfCatlig(?string $catlig): array
    {
        return match (true) {
            $catlig === null => [null, null],
            (bool) preg_match('/voie\s+[ée]troite/iu', $catlig) => [null, self::NARROW],
            (bool) preg_match('/[ée]cartement\s+normal|grande\s+vitesse/iu', $catlig) => [1435, self::STANDARD],
            default => [null, null],
        };
    }

    /**
     * Écartement cité dans un NOM de tracé ou un libellé court : « V60 », « voie de 0m60 »,
     * « (voie 0,60 m) », « 60 cm », « Decauville », « VM », « voie métrique », « VN »,
     * « voie normale », « 0m70 », « 0m75 ». Le PREMIER écartement cité l'emporte
     * (« ligne à voie métrique, puis mise à voie normale » → 1000, la conversion est
     * lue à part). Renvoie [mm|null, classe] ou [null, null].
     *
     * @return array{0: ?int, 1: ?string}
     */
    public static function fromName(?string $name): array
    {
        if ($name === null || trim($name) === '') {
            return [null, null];
        }
        $patterns = [
            '/\bV\s?60\b|\b0\s?[m,.]\s?60\b|\b60\s?cm\b|voie\s+de\s+60\b|decauville/iu' => 600,
            '/\b0\s?[m,.]\s?70\b|\b70\s?cm\b/iu' => 700,
            '/\bV\s?75\b|\b0\s?[m,.]\s?75\b|\b75\s?cm\b/iu' => 750,
            // « VM » / « VN » en capitales seulement (abréviations des tracés archeo).
            '/\bVM\b|(?i:voie\s+m[ée]trique|[ée]cartement\s+(de\s+)?(1\s?000\s?mm|m[ée]trique)|\b1\s?m[eè]tre\s+d\'[ée]cartement)/u' => 1000,
            '/\bVN\b|(?i:voie\s+normale|[ée]cartement\s+(normal|standard)|\b1\s?435\s?mm)/u' => 1435,
            '/voie\s+[ée]troite/iu' => null,
        ];
        $best = null;
        foreach ($patterns as $re => $mm) {
            if (preg_match($re, $name, $m, PREG_OFFSET_CAPTURE)) {
                $pos = $m[0][1];
                if ($best === null || $pos < $best[0]) {
                    $best = [$pos, $mm];
                }
            }
        }
        if ($best === null) {
            return [null, null];
        }

        return [$best[1], $best[1] === null ? self::NARROW : self::classOf($best[1])];
    }

    /**
     * Phrase d'un événement qui dit l'écartement de LA ligne elle-même (sujet de la
     * phrase), ex. « La ligne est construite à l'écartement métrique », « Cette ligne
     * de chemin de fer secondaire à voie métrique… ». Une phrase qui cite une AUTRE
     * ligne (« croisait la ligne à voie métrique de… ») est ignorée.
     *
     * @return array{0: ?int, 1: ?string}
     */
    public static function fromLineSentence(?string $text, ?string $lineName = null): array
    {
        if ($text === null) {
            return [null, null];
        }
        if (! preg_match('/^\s*(la\s+ligne|cette\s+ligne|elle|le\s+chemin\s+de\s+fer|la\s+section|ce\s+tron[çc]on|d\'un\s+[ée]cartement)\b([^.;]{0,140})/iu', $text, $m)) {
            return [null, null];
        }
        // Une autre ligne citée dans la proposition : on ne sait pas de laquelle il s'agit.
        if (preg_match('/\b(croise|longe|rejoint|c[ôo]toie|correspond)/iu', $m[2])) {
            return [null, null];
        }
        // « La ligne … de Saint-Jean-d'Angély à Saint-Saviol » : une ligne nommée par ses
        // extrémités qui ne sont pas celles de la ligne décrite.
        if ($lineName !== null && preg_match('/\bde\s+(\p{Lu}[\p{L}\'’-]+)[^,;]{0,40}?\s+(?:à|au|aux)\s+\p{Lu}/u', $m[2], $t)
            && mb_stripos($lineName, $t[1]) === false) {
            return [null, null];
        }

        return self::fromName($m[0]);
    }

    /**
     * Conversion d'écartement datée citée dans un texte : « mise à voie normale en
     * 1967 », « transformée à l'écartement normal en 1967 », « La ligne, mise à voie
     * normale, est rouverte le 21 août 1916 », « à voie métrique jusqu'en 1915 ».
     *
     * @return ?array{year: int, to_mm: int, from_mm: ?int}
     */
    public static function conversion(?string $text, ?int $eventYear = null): ?array
    {
        if ($text === null) {
            return null;
        }
        $toNormal = '(mise?|transform[ée]e?|convertie?|reconstruite?|pass[ée]e?|port[ée]e?)\s+(à|en)\s+(la\s+)?(voie\s+normale|l\'[ée]cartement\s+(normal|standard))';
        if (preg_match('/'.$toNormal.'[^.;]{0,60}?\b(1[89]\d{2}|20\d{2})\b/iu', $text, $m)) {
            return ['year' => (int) $m[6], 'to_mm' => 1435, 'from_mm' => null];
        }
        if (preg_match('/'.$toNormal.'/iu', $text) && $eventYear !== null) {
            return ['year' => $eventYear, 'to_mm' => 1435, 'from_mm' => null];
        }
        if (preg_match('/voie\s+m[ée]trique\s+jusqu[\'’]?\s*(en|à|au)?\s*(1[89]\d{2})/iu', $text, $m)) {
            return ['year' => (int) $m[2], 'to_mm' => 1435, 'from_mm' => 1000];
        }

        return null;
    }

    /**
     * Écartement d'une portion à l'année $year.
     *
     * @param  ?array{mm: ?int, class: ?string, source: string}  $current  voie en place aujourd'hui (OSM, BD TOPO, SNCF)
     * @param  array<int, array{mm: ?int, class: ?string, source: string, from?: ?int, until?: ?int}>  $historical  tracés anciens (OHM daté, OSM désaffecté, noms archeo)
     * @param  array<int, array{mm: ?int, class: ?string, source: string}>  $line  indices au niveau de la ligne (texte, Wikidata, fiche), par priorité
     * @param  array<int, array{year: int, to_mm: int, from_mm: ?int, source: string}>  $conversions
     * @return array{mm: ?int, class: ?string, source: ?string}
     */
    public static function at(int $year, ?array $current, array $historical, array $line, array $conversions, ?int $networkDefault = null): array
    {
        $pack = fn (?int $mm, ?string $class, ?string $source) => ['mm' => $mm, 'class' => $class ?? self::classOf($mm), 'source' => $source];

        // Écartement d'origine connu indépendamment du tracé actuel.
        $old = null;
        foreach ($historical as $h) {
            if (($h['mm'] ?? null) !== null || ($h['class'] ?? null) !== null) {
                $old = $h;
                break;
            }
        }
        if ($old === null) {
            foreach ($line as $l) {
                if (($l['mm'] ?? null) !== null || ($l['class'] ?? null) !== null) {
                    $old = $l;
                    break;
                }
            }
        }

        // 0. Écartement établi à la main pour toute la ligne (ex. Réseau breton) : il prime sur la
        //    voie la plus proche relevée aujourd'hui (gare commune avec une ligne à voie normale).
        if (! $conversions) {
            foreach ($line as $l) {
                if (($l['source'] ?? null) === 'curated') {
                    return $pack($l['mm'] ?? null, $l['class'] ?? null, 'curated');
                }
            }
        }

        // 1. Conversions datées (curation ou texte) : avant → ancien écartement, après → nouveau.
        if ($conversions) {
            usort($conversions, fn ($a, $b) => $a['year'] <=> $b['year']);
            $after = null;
            foreach ($conversions as $c) {
                if ($c['year'] <= $year) {
                    $after = $c;
                }
            }
            if ($after !== null) {
                return $pack($after['to_mm'], null, $after['source']);
            }
            $first = $conversions[0];
            $from = $first['from_mm'] ?? (($old && ($old['mm'] ?? null) !== $first['to_mm']) ? $old['mm'] : null);
            if ($from !== null) {
                return $pack($from, null, $first['source']);
            }
            if ($old && ($old['class'] ?? null) !== null && $old['class'] !== self::classOf($first['to_mm'])) {
                return $pack(null, $old['class'], $old['source']);
            }
        }

        // 2. Voie actuelle : son écartement vaut pour toutes les ères, sauf si un tracé ancien
        //    DATÉ (OHM) dit autre chose avant sa date de fin (conversion implicite).
        if ($current && (($current['mm'] ?? null) !== null || ($current['class'] ?? null) !== null)) {
            foreach ($historical as $h) {
                $until = $h['until'] ?? null;
                if ($until !== null && $year < $until && (($h['mm'] ?? null) !== null)
                    && self::classOf($h['mm']) !== ($current['class'] ?? self::classOf($current['mm']))
                    && (($h['from'] ?? null) === null || $year >= $h['from'])) {
                    return $pack($h['mm'], null, $h['source']);
                }
            }

            return $pack($current['mm'] ?? null, $current['class'] ?? null, $current['source']);
        }

        // 3. Tracé disparu : indices historiques (période couvrante d'abord), puis ligne, puis réseau.
        foreach ($historical as $h) {
            if ((($h['mm'] ?? null) !== null || ($h['class'] ?? null) !== null)
                && (($h['from'] ?? null) === null || $year >= $h['from'])
                && (($h['until'] ?? null) === null || $year < $h['until'] + 10)) {
                return $pack($h['mm'] ?? null, $h['class'] ?? null, $h['source']);
            }
        }
        if ($old !== null) {
            return $pack($old['mm'] ?? null, $old['class'] ?? null, $old['source']);
        }
        if ($networkDefault !== null) {
            return $pack($networkDefault, null, 'network');
        }

        return ['mm' => null, 'class' => null, 'source' => null];
    }
}
