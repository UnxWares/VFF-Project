<?php

namespace App\Services\Signalling;

/**
 * Systèmes de signalisation cités dans un texte d'article (phrase, valeur d'infobox, cellule de tableau) :
 * repérage et code VFF, partagés par le texte (SignallingHistoryExtractor), l'infobox
 * (SignallingInfoboxReader) et les tableaux (SignallingTableReader).
 *
 * Codes : BAL, BAPR, BM (toutes variantes de block manuel), CT (cantonnement téléphonique), TVM-300, TVM-430
 * ou TVM (version non dite), ETCS_L1, ETCS_L2 ou ETCS (niveau non dit), KVB (contrôle de vitesse, jamais un
 * cantonnement).
 */
final class SignallingSystems
{
    /** Famille → expression (sensible à la casse pour les sigles). Le code final est calculé par code(). */
    private const MENTIONS = [
        'BAL' => '/(?i:blo(?:ck|c)\s+automatique\s+lumineux)|\bBAL(?:-VB)?\b/u',
        'BAPR' => '/(?i:blo(?:ck|c)\s+automatique\s+à\s+permissivité\s+restreinte)|\bBAPR(?:-[A-Z]{2})?\b/u',
        'BM' => '/(?i:blo(?:ck|c)\s+manuel(?:\s+unifié)?)|\bBM(?:VU|U)?(?:-VU)?\b/u',
        'CT' => '/(?i:cantonnement\s+téléphonique)(?!\s+assisté)/u',
        'TVM' => '/(?i:transmission\s+voie[- ]machine)|\bTVM(?:[\s-]*(?:300|430))?\b/u',
        'ETCS' => '/\b(?:ETCS|ERTMS)(?:\s*[\/-]\s*(?:ETCS|ERTMS))?(?:\s+(?:de\s+)?(?:niveau|level|N)\s*([12])|\s*L([12])\b|\s+([12])\b(?!\s*(?:\d|ans?\b|km\b|mois\b|[,.]\d))|\s*\(\s*niveau\s*([12])\s*\))?/u',
        'KVB' => '/\bKVB(?:\s?P)?\b|(?i:contrôle\s+de\s+vitesse\s+par\s+balises)/u',
    ];

    /** Année avant laquelle le système n'existait pas : une date plus ancienne est une erreur de lecture probable. */
    private const FIRST_YEAR = ['BAL' => 1925, 'BAPR' => 1935, 'BM' => 1850, 'CT' => 1850, 'TVM' => 1976, 'ETCS' => 1998, 'KVB' => 1980];

    /**
     * Systèmes cités dans un texte, avec leur position (en caractères) et leur code, dans l'ordre du texte.
     * « block automatique lumineux (BAL) », « block manuel à voie unique (BMVU) » : le sigle qui répète le nom ne
     * compte qu'une fois.
     *
     * @return list<array{system: string, pos: int, text: string}>
     */
    public static function mentions(string $text): array
    {
        $found = [];
        foreach (self::MENTIONS as $family => $rx) {
            if (! preg_match_all($rx, $text, $all, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
                continue;
            }
            foreach ($all as $m) {
                $found[] = [
                    'system' => self::code($family, $m),
                    'pos' => mb_strlen(substr($text, 0, $m[0][1])),
                    'text' => $m[0][0],
                ];
            }
        }
        usort($found, fn ($a, $b) => $a['pos'] <=> $b['pos']);

        $mentions = [];
        foreach ($found as $m) {
            $last = $mentions === [] ? null : $mentions[array_key_last($mentions)];
            $gap = $last === null ? '' : mb_substr($text, $last['pos'] + mb_strlen($last['text']), $m['pos'] - $last['pos'] - mb_strlen($last['text']));
            // « block manuel à voie unique (BMVU) » : le sigle qui suit le nom, après quelques mots, aussi.
            if ($last !== null && SignallingSystems::base($last['system']) === SignallingSystems::base($m['system']) && preg_match('/^[\p{L}\s’\'-]{0,30}\(\s*$/u', $gap)) {
                if ($m['system'] !== $last['system'] && strlen($m['system']) > strlen($last['system'])) {
                    // Le sigle est plus précis (« ETCS (ETCS_L2) ») : il garde la place du nom.
                    $mentions[array_key_last($mentions)]['system'] = $m['system'];
                }

                continue;
            }
            $mentions[] = $m;
        }

        return $mentions;
    }

    /** Le texte cite-t-il au moins un système ? */
    public static function cited(string $text): bool
    {
        return self::mentions($text) !== [];
    }

    /** Date antérieure à la naissance du système (« BAL en 1900 » : lecture fautive probable). */
    public static function tooEarly(string $system, int $year): bool
    {
        return $year < (self::FIRST_YEAR[self::base($system)] ?? 0);
    }

    /** Code sans version ni niveau : TVM-430 → TVM, ETCS_L2 → ETCS. */
    public static function base(string $system): string
    {
        return (string) preg_replace('/[-_].*$/', '', $system);
    }

    /**
     * Code du système d'après la famille et ce que le texte précise (TVM 300, ETCS niveau 2).
     *
     * @param  array<int|string, array{0: string, 1: int}>  $match
     */
    private static function code(string $family, array $match): string
    {
        $text = $match[0][0];
        if ($family === 'TVM' && preg_match('/(300|430)/', $text, $n)) {
            return 'TVM-'.$n[1];
        }
        if ($family === 'ETCS') {
            $level = '';
            foreach ([1, 2, 3, 4] as $g) {
                if (isset($match[$g]) && $match[$g][0] !== '' && $match[$g][1] >= 0) {
                    $level = $match[$g][0];
                    break;
                }
            }

            return $level !== '' ? 'ETCS_L'.$level : 'ETCS';
        }

        return $family;
    }
}
