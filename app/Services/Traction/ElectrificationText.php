<?php

namespace App\Services\Traction;

/**
 * Lecture des textes et étiquettes d'électrification : événements datés
 * (Wikipédia, Wikidata, lignes-oubliees), tags OSM, libellés SNCF Open Data.
 *
 * Un événement de type « electrification » n'est pas toujours une mise sous
 * tension : la phrase peut décrire un début de travaux, une décision, ou au
 * contraire une DÉSélectrification (« mise hors tension », « dés-électrifiée »).
 * kind() tranche, voltage() extrait la tension citée.
 */
final class ElectrificationText
{
    public const KIND_ELECTRIFICATION = 'electrification';

    public const KIND_DEELECTRIFICATION = 'deelectrification';

    /** Phrase qui ne date pas une mise sous tension (travaux, projet, décision…). */
    public const KIND_IGNORE = 'ignore';

    /** Tensions de traction plausibles en France (V). */
    private const PLAUSIBLE_V = [550, 600, 650, 700, 750, 800, 850, 1200, 1500, 2400, 3000, 6000, 6600, 11000, 12000, 15000, 20000, 25000];

    private const DEELECTRIFICATION = '/(d[ée]s[\s-]?[ée]lectrifi|hors[\s-]tension|d[ée]pos[ée]e?s?\s+(de\s+)?(la|les)\s+cat[ée]naires?|cat[ée]naires?\s+(a|ont)\s+[ée]t[ée]\s+d[ée]pos|cat[ée]naires?\s+(est|sont)\s+d[ée]pos|suppression\s+de\s+(la\s+cat[ée]naire|l\'[ée]lectrification)|retrait\s+de\s+la\s+cat[ée]naire|fin\s+de\s+la\s+traction\s+[ée]lectrique)/iu';

    private const IGNORE = '/(d[ée]but(ent|e|aient|ait)?\b[^.;]{0,50}(travaux|[ée]lectrification)|travaux[^.;]{0,80}(commenc|d[ée]but|entrepris|engag|lanc[ée]s|pr[ée]paratoires)|pr[ée]paratoires|inscrite?s?\s+au\s+contrat|contrat\s+de\s+plan|d[ée]claration\s+d\'utilit[ée]|utilit[ée]\s+publique|d[ée]cision\s+minist[ée]rielle|programme\s+d\'[ée]lectrification|(envisag|pr[ée]vu|projet|[ée]tudi)[^.;]{0,40}[ée]lectrifi|autoris[ée]?\w*\s+l\'[ée]lectrification|refus[ée]?\w*\s+son\s+aide)/iu';

    /**
     * Nature de l'événement d'après son texte.
     */
    public static function kind(string $text): string
    {
        if (preg_match(self::DEELECTRIFICATION, $text)) {
            return self::KIND_DEELECTRIFICATION;
        }
        // Une mise sous tension explicite l'emporte sur le contexte (« dans le cadre du programme
        // d'électrification du réseau Est, la section Bondy - Gargan est mise sous tension »),
        // sauf début de travaux.
        $commissioning = (bool) preg_match('/(mise?s?\s+sous\s+tension|mise\s+en\s+service\s+de\s+l\'[ée]lectrification)/iu', $text);
        if (preg_match(self::IGNORE, $text) && (! $commissioning || preg_match('/travaux/iu', $text))) {
            return self::KIND_IGNORE;
        }

        return self::KIND_ELECTRIFICATION;
    }

    /**
     * Tension de traction citée dans un texte (V), ou null. « 1 500 V », « 1,5 kV »,
     * « 25 kV - 50 Hz », « 1500 volts continu », « 12 kVolt - 25 Hertz »… Si plusieurs
     * tensions sont citées, on préfère celle qui précède « continu », « Hz »,
     * « monophasé » ou « alternatif » (la tension d'ALIMENTATION d'une sous-station,
     * ex. « 20 000 volts triphasé redressé en 850 V continu », n'est pas celle de la ligne).
     */
    public static function voltage(string $text): ?int
    {
        $re = '/(?<![\d,.])(\d{1,3}(?:[ \x{00A0}\x{202F}.]\d{3})+|\d+(?:[,.]\d+)?)\s*(k\s*v(?:olts?)?|kilovolts?|v(?:olts?)?)(?![a-zà-ÿ])([^.;]{0,25})/iu';
        if (! preg_match_all($re, $text, $m, PREG_SET_ORDER)) {
            return null;
        }
        $best = null;
        foreach ($m as $match) {
            $num = $match[1];
            if (preg_match('/^\d{1,3}(?:[ \x{00A0}\x{202F}.]\d{3})+$/u', $num)) {
                $value = (float) preg_replace('/\D/', '', $num);
            } else {
                $value = (float) str_replace(',', '.', $num);
            }
            if (stripos(preg_replace('/\s+/', '', $match[2]), 'k') === 0) {
                $value *= 1000;
            }
            $v = (int) round($value);
            if (! in_array($v, self::PLAUSIBLE_V, true)) {
                continue;
            }
            $qualified = (bool) preg_match('/^\s*[-–=]?\s*(cc|continu|\d+[,.]?\d*\s*hz|16\s*2\/3|monophas|alternatif|ca\b|c\.c\.|par\s+(troisi|cat)|-?\s*\d+\s*hertz)/iu', $match[3]);
            if ($qualified) {
                return $v;
            }
            $best ??= $v;
        }

        return $best;
    }

    /** Fréquence citée (Hz) : 50, 16.7 (16 2/3), 25 ; 0 pour « continu », null si muet. */
    public static function frequency(string $text): ?float
    {
        if (preg_match('/16\s*(2\/3|⅔|[,.]6+7?)\s*(hz|hertz)/iu', $text)) {
            return 16.7;
        }
        if (preg_match('/(\d+(?:[,.]\d+)?)\s*(hz|hertz)\b/iu', $text, $m)) {
            return (float) str_replace(',', '.', $m[1]);
        }
        if (preg_match('/\b(continu|cc\b|c\.c\.)/iu', $text)) {
            return 0.0;
        }

        return null;
    }

    /** Vrai si le texte décrit un troisième rail (rail de contact latéral). */
    public static function thirdRail(string $text): bool
    {
        return (bool) preg_match('/(troisi[èe]me\s+rail|3e\s+rail|3[èe]me\s+rail|rail\s+(de\s+contact|lat[ée]ral|[ée]lectrique))/iu', $text);
    }

    /**
     * Date de FIN d'un régime d'électrification citée par Wikidata (qualificatif
     * P582), ex. « P580 = 1925-00-00 ; P582 = 1976-00-00 » : le troisième rail de
     * Culoz - Modane est remplacé par la caténaire en 1976.
     */
    public static function wikidataEndYear(string $text): ?int
    {
        return preg_match('/P582\s*=\s*(\d{4})/u', $text, $m) ? (int) $m[1] : null;
    }

    /**
     * Événement « toute la ligne » qui date l'ACHÈVEMENT de l'électrification
     * (« entièrement électrifiée », « intégralement », « l'ensemble de la ligne »,
     * « entre 1926 et 1938 ») : après cette date toute la ligne est sous tension.
     * Renvoie l'année d'achèvement, ou null.
     */
    public static function completionYear(string $text, int $eventYear): ?int
    {
        if (preg_match('/entre\s+(\d{4})\s+et\s+(\d{4})/u', $text, $m)) {
            return max((int) $m[1], (int) $m[2]);
        }
        if (preg_match('/(enti[èe]rement|int[ée]gralement|en\s+totalit[ée]|toute\s+sa\s+longueur|l\'ensemble\s+de\s+la\s+ligne|[ée]lectrification\s+compl[èe]te|compl[èe]tement\s+[ée]lectrifi|ach[èe]vement|[ée]lectrification\s+(?:est\s+|a\s+[ée]t[ée]\s+)?achev[ée]e|en\s+int[ée]gralit[ée])/iu', $text)) {
            return $eventYear;
        }

        return null;
    }

    /** Infobox signalant une électrification PARTIELLE (« électrification3 = partiel »). */
    public static function partialInfobox(string $text): bool
    {
        return (bool) preg_match('/[ée]lectrification\d?\s*=\s*partiel/iu', $text);
    }

    /**
     * Tension d'un tag OSM `voltage` (« 25000 », « 1500 », « 25000;1500 » en gare
     * bi-tension : la première valeur).
     */
    public static function osmVoltage(?string $tag): ?int
    {
        if ($tag === null || trim($tag) === '') {
            return null;
        }
        $first = trim(explode(';', $tag)[0]);

        return ctype_digit($first) && (int) $first > 0 ? (int) $first : null;
    }

    /** Tag OSM `electrified` : true (contact_line, rail, yes, 4th_rail…), false (no), null (inconnu). */
    public static function osmElectrified(?string $tag): ?bool
    {
        return match (true) {
            $tag === null || $tag === '' => null,
            $tag === 'no' => false,
            in_array($tag, ['contact_line', 'rail', 'yes', '4th_rail', 'ground-level_power_supply', 'contact_line;rail'], true) => true,
            default => null,
        };
    }

    /**
     * Libellé SNCF « liste des lignes électrifiées » → [tension V, fréquence Hz, troisième rail].
     * « 25000 volts alternatif », « 1500 volts continu », « 15000 volts 16 2/3 alternatif »,
     * « 850 volts continu par 3ème rail »…
     *
     * @return array{0: ?int, 1: ?float, 2: bool}
     */
    public static function fromSncfLabel(string $label): array
    {
        $v = preg_match('/(\d[\d\s]*)\s*volts?/iu', $label, $m) ? (int) preg_replace('/\D/', '', $m[1]) : null;
        $f = self::frequency($label);
        if ($f === null && preg_match('/alternatif/iu', $label)) {
            $f = $v === 15000 ? 16.7 : 50.0;
        }

        return [$v ?: null, $f, self::thirdRail($label) || (bool) preg_match('/3[èe]me\s+rail/iu', $label)];
    }
}
