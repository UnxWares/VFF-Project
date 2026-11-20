<?php

namespace App\Services\Archives;

/**
 * Date d'un document d'archives, telle que la notice de la BnF la donne : texte imprimé (`label`),
 * année (`year`), dernière année d'un intervalle (`yearTo`), jour ou mois exacts (`exact`, AAAA-MM-JJ) et
 * précision : day, month, year, circa (date approchée), range (entre year et yearTo), before (au plus tard
 * year : « Avant 1885 »), after (au plus tôt year : « Après 1900 ») ou unknown.
 */
final readonly class ArchiveDate
{
    /** Date approchée dans le texte imprimé : « [ca 1850] », « circa 1869 », « vers 1880 », « env. 1900 », « 1910? ». */
    private const APPROX = '/\b(ca|circa|vers|env)\b\.?|\?/iu';

    /** Borne imprimée en tête du texte : « Avant 1885 », « [avant 1900] », « Après 1871 », « before 1850 ». */
    private const BOUND = '/^\[?\s*(avant|before|apr[eè]s|after)\b/iu';

    /** Mois en français (avec et sans accents) et en anglais, numérotés. */
    private const MONTHS = [
        'janvier' => 1, 'fevrier' => 2, 'février' => 2, 'mars' => 3, 'avril' => 4, 'mai' => 5, 'juin' => 6, 'juillet' => 7,
        'aout' => 8, 'août' => 8, 'septembre' => 9, 'octobre' => 10, 'novembre' => 11, 'decembre' => 12, 'décembre' => 12,
        'january' => 1, 'february' => 2, 'march' => 3, 'april' => 4, 'may' => 5, 'june' => 6, 'july' => 7, 'august' => 8,
        'september' => 9, 'october' => 10, 'november' => 11, 'december' => 12,
    ];

    public function __construct(
        public ?string $label,
        public ?int $year,
        public ?int $yearTo,
        public ?string $exact,
        public string $precision,
    ) {}

    public static function unknown(?string $label = null): self
    {
        return new self($label, null, null, null, 'unknown');
    }

    /**
     * Date tirée de la zone 100 de la notice UNIMARC (type de date en position 8, première date en 9-12,
     * seconde en 13-16) et du texte de la date de publication (zone 210 ou 214, sous-zone d).
     *
     *   d, h, i, k, a, b, c : une seule date (la première) ;
     *   e : reproduction, la seconde date est celle de l'original ;
     *   f : date incertaine entre la première et la seconde ; g : publication étalée sur plusieurs années ;
     *   j : date détaillée, la seconde « date » donne le mois et le jour (MMJJ) ;
     *   u : inconnue.
     * Un chiffre inconnu (« 189. ») donne la décennie (intervalle) ; deux ou plus (« 18.. ») : date inconnue.
     * « ca », « circa », « vers », « env. » ou « ? » dans le texte imprimé : date approchée (circa).
     * « Avant 1885 » (code f, une seule année) : borne haute (before) ; « Après 1871 » : borne basse (after).
     * Ce ne sont pas des dates approchées : le document date au plus tard (ou au plus tôt) de cette année.
     * Une seule année codée : le jour ou le mois imprimés de la même année sont gardés (« 21 juin 1926 »,
     * « 10/05/1874 », « [Mai 1923] », « August 6, 1870 ») ; si le texte imprimé n'est qu'une autre année
     * (« 1854 » pour 1864 codé), cette année l'emporte, en date approchée : la notice se contredit.
     */
    public static function fromUnimarc(?string $field100, ?string $label): self
    {
        $label = $label === null ? null : self::cleanLabel($label);
        if ($field100 === null || strlen($field100) < 17) {
            return self::fromLabel($label);
        }
        $kind = $field100[8];
        $first = substr($field100, 9, 4);
        $second = substr($field100, 13, 4);
        $approx = $label !== null && preg_match(self::APPROX, $label) === 1;

        if ($kind === 'u') {
            return self::fromLabel($label);
        }
        if ($kind === 'j' && ctype_digit($first)) {
            $month = substr($second, 0, 2);
            $day = substr($second, 2, 2);
            if (ctype_digit($month) && (int) $month >= 1 && (int) $month <= 12) {
                $hasDay = ctype_digit($day) && (int) $day >= 1 && checkdate((int) $month, (int) $day, (int) $first);

                return new self($label ?? $first, (int) $first, null,
                    sprintf('%s-%s-%s', $first, $month, $hasDay ? $day : '01'), $hasDay ? 'day' : 'month');
            }
        }
        if ($kind === 'e' && self::year($second) !== null) {
            [$first, $second] = [$second, '    '];
        }
        $from = self::year($first);
        if ($from === null) {
            return self::fromLabel($label);
        }
        if ($from['to'] !== $from['from']) {
            return new self($label, $from['from'], $from['to'], null, 'range');
        }
        $to = in_array($kind, ['f', 'g'], true) ? self::year($second) : null;
        if ($to !== null && $to['to'] > $from['from']) {
            return new self($label, $from['from'], $to['to'], null, 'range');
        }

        $year = $from['from'];
        if (($bound = self::bound($label, $year)) !== null) {
            return new self($label, $year, null, null, $bound);
        }
        if ($kind === 'd' && $label !== null && preg_match('/^\[?(\d{4})\]?$/', $label, $m) === 1 && self::year($m[1]) !== null && (int) $m[1] !== $year) {
            return new self($label, (int) $m[1], null, null, 'circa');
        }
        if (! $approx && $kind !== 'f' && ($detail = self::detail($label, $year)) !== null) {
            return new self($label, $year, null, $detail[0], $detail[1]);
        }

        return new self($label ?? (string) $year, $year, null, null, $approx || $kind === 'f' ? 'circa' : 'year');
    }

    /**
     * Jour ou mois de l'année `$year` imprimés dans le texte : [AAAA-MM-JJ, day] ou [AAAA-MM-01, month], null
     * si le texte n'en donne pas ou s'il parle d'une autre année.
     *
     * @return array{0: string, 1: string}|null
     */
    private static function detail(?string $label, int $year): ?array
    {
        if ($label === null) {
            return null;
        }
        $months = implode('|', array_keys(self::MONTHS));
        $day = null;
        $month = null;
        $y = null;
        if (preg_match('#\b(\d{1,2})/(\d{1,2})/(\d{4})\b#u', $label, $m) === 1) {
            [$day, $month, $y] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match("/(?<![\\d.])(1er|\\d{1,2})\\s+({$months})\\s+(\\d{4})\\b/iu", $label, $m) === 1) {
            [$day, $month, $y] = [$m[1] === '1er' ? 1 : (int) $m[1], self::MONTHS[mb_strtolower($m[2])], (int) $m[3]];
        } elseif (preg_match("/\\b({$months})\\s+(\\d{1,2}),\\s*(\\d{4})\\b/iu", $label, $m) === 1) {
            [$day, $month, $y] = [(int) $m[2], self::MONTHS[mb_strtolower($m[1])], (int) $m[3]];
        } elseif (preg_match("/(?<![\\p{L}])({$months})\\s+(\\d{4})\\b/iu", $label, $m) === 1) {
            [$month, $y] = [self::MONTHS[mb_strtolower($m[1])], (int) $m[2]];
        }
        if ($y !== $year || $month < 1 || $month > 12) {
            return null;
        }
        if ($day === null) {
            return [sprintf('%04d-%02d-01', $year, $month), 'month'];
        }

        return checkdate($month, $day, $year) ? [sprintf('%04d-%02d-%02d', $year, $month, $day), 'day'] : null;
    }

    /** `before` ou `after` quand le texte imprimé commence par une borne de l'année `$year`, sinon null. */
    private static function bound(?string $label, int $year): ?string
    {
        if ($label === null || preg_match(self::BOUND, $label, $m) !== 1 || ! str_contains($label, (string) $year)) {
            return null;
        }

        return in_array(mb_strtolower($m[1]), ['avant', 'before'], true) ? 'before' : 'after';
    }

    /** Sans zone 100 exploitable : une année à quatre chiffres lue dans le texte (et son jour), sinon inconnue. */
    private static function fromLabel(?string $label): self
    {
        if ($label !== null && preg_match('/\b(1[6-9]\d\d|20[0-4]\d)\b/', $label, $m) === 1) {
            $year = (int) $m[1];
            if (($bound = self::bound($label, $year)) !== null) {
                return new self($label, $year, null, null, $bound);
            }
            if (preg_match(self::APPROX, $label) === 1) {
                return new self($label, $year, null, null, 'circa');
            }
            $detail = self::detail($label, $year);

            return new self($label, $year, null, $detail[0] ?? null, $detail[1] ?? 'year');
        }

        return self::unknown($label);
    }

    /**
     * Année sur quatre caractères : « 1860 » → 1860 ; « 189. » ou « 189? » → décennie 1890 à 1899 ;
     * plus d'un chiffre inconnu, ou hors de 1600 à 2049 : null.
     *
     * @return array{from: int, to: int}|null
     */
    private static function year(string $chars): ?array
    {
        if (ctype_digit($chars)) {
            $y = (int) $chars;

            return $y >= 1600 && $y <= 2049 ? ['from' => $y, 'to' => $y] : null;
        }
        if (preg_match('/^(\d{3})[ .?u-]$/', $chars, $m) === 1) {
            $decade = (int) $m[1] * 10;

            return $decade >= 1600 && $decade <= 2049 ? ['from' => $decade, 'to' => $decade + 9] : null;
        }

        return null;
    }

    /**
     * « [ca 1850] » reste tel quel ; « sans date » sous ses formes (« [s.d.] », « (s. d.) », « (s. d.,) »,
     * « S. d. ») et les blancs seuls : null, pour ne pas masquer une année codée dans la zone 100.
     */
    private static function cleanLabel(string $label): ?string
    {
        $label = trim((string) preg_replace('/\s+/u', ' ', $label), " \t,;:");
        if ($label === '' || preg_match('/^[\[(]?\s*s\.\s*d\.?\s*,?\s*[\])]?$/iu', $label) === 1) {
            return null;
        }

        return mb_substr($label, 0, 120);
    }
}
