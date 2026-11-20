<?php

namespace App\Services\Archives;

/**
 * Date d'un document d'archives, telle que la notice de la BnF la donne : texte imprimé (`label`),
 * année (`year`), dernière année d'un intervalle (`yearTo`), jour ou mois exacts (`exact`, AAAA-MM-JJ) et
 * précision : day, month, year, circa (date approchée), range (entre year et yearTo) ou unknown.
 */
final readonly class ArchiveDate
{
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
     * « ca », « vers », « env. » ou « ? » dans le texte imprimé : date approchée (circa).
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
        $approx = $label !== null && preg_match('/\b(ca|vers|env)\b\.?|\?/iu', $label) === 1;

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

        return new self($label ?? (string) $from['from'], $from['from'], null, null, $approx || $kind === 'f' ? 'circa' : 'year');
    }

    /** Sans zone 100 exploitable : une année à quatre chiffres lue dans le texte, sinon inconnue. */
    private static function fromLabel(?string $label): self
    {
        if ($label !== null && preg_match('/\b(1[6-9]\d\d|20[0-4]\d)\b/', $label, $m) === 1) {
            $approx = preg_match('/\b(ca|vers|env)\b\.?|\?/iu', $label) === 1;

            return new self($label, (int) $m[1], null, null, $approx ? 'circa' : 'year');
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

    /** « [ca 1850] » reste tel quel ; « [s.d.] » (sans date) et les blancs seuls : null. */
    private static function cleanLabel(string $label): ?string
    {
        $label = trim((string) preg_replace('/\s+/u', ' ', $label), " \t,;:");
        if ($label === '' || preg_match('/^\[?s\.\s?d\.?\]?$/iu', $label) === 1) {
            return null;
        }

        return mb_substr($label, 0, 120);
    }
}
