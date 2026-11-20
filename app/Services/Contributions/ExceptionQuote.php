<?php

namespace App\Services\Contributions;

use App\Models\Material;
use App\Services\Materials\MaterialGaugeRule;
use App\Services\Materials\MaterialTractionRule;
use Illuminate\Support\Str;

/**
 * Phrase citée par un contributeur pour lever une règle d'écartement ou d'électrification
 * (LineMaterialContributions). La règle accepte déjà une phrase qui nomme l'écartement ou la tension
 * de la série, parce que celle d'un import vient du texte réel d'un article, à côté du nom de la série.
 * Une phrase tapée par un contributeur doit en plus :
 *   - nommer la série, par son nom ou une de ses désignations (« Z 1200 », « Z1200 ») ;
 *   - nommer la valeur qui justifie l'exception sans négation autour : « jamais en 750 V » ou
 *     « n'a pas été à voie normale » ne prouvent rien. Une négation compte dans la même phrase, à
 *     moins de NEGATION_WINDOW mots de la valeur, en français, en anglais ou en allemand.
 * Les validateurs voient toujours l'exception en évidence : ce contrôle écarte seulement les phrases
 * qui, à la lecture, ne peuvent pas la justifier.
 */
final class ExceptionQuote
{
    /** Mots qui nient (français, anglais, allemand), comparés sans accents ni capitales. */
    private const NEGATIONS = [
        'jamais', 'pas', 'sans', 'aucun', 'aucune', 'aucuns', 'aucunes', 'ni', 'nullement',
        'never', 'not', 'no', 'without', 'none', 'nor',
        'nie', 'niemals', 'nicht', 'ohne', 'kein', 'keine', 'keinen', 'keiner', 'keinem', 'keines',
    ];

    /** Nombre de mots, de part et d'autre de la valeur, où une négation la contredit. */
    public const NEGATION_WINDOW = 6;

    /**
     * Ce qui manque à la phrase pour lever la règle : `series` (la série n'est pas nommée),
     * `negated` (la valeur n'est nommée que dans une tournure négative), ou null si elle convient.
     *
     * @param  array{rule: string, details: array<string, mixed>}  $conflict  conflit levé par la phrase (LineMaterialContributions::compatibility)
     */
    public static function problem(string $quote, Material $material, array $conflict): ?string
    {
        if (! self::namesSeries($quote, $material)) {
            return 'series';
        }
        $details = $conflict['details'];
        $mentions = $conflict['rule'] === 'gauge'
            ? MaterialGaugeRule::gaugeMentions($quote, (int) $details['series_mm'])
            : MaterialTractionRule::voltageMentions($quote, array_map('intval', $details['volts']));
        foreach ($mentions as [$offset, $length]) {
            if (! self::negatedAt($quote, $offset, $length)) {
                return null;
            }
        }

        return 'negated';
    }

    /** La phrase nomme-t-elle la série, par son nom ou une de ses désignations ? */
    public static function namesSeries(string $quote, Material $material): bool
    {
        $text = ' '.implode(' ', self::tokens($quote)).' ';
        foreach (array_filter([$material->name, ...($material->designations ?? [])], 'is_string') as $name) {
            $tokens = self::tokens($name);
            // « Z 1200 », « Z1200 », « Z-1200 » : mêmes mots, quel que soit le séparateur.
            if ($tokens !== [] && str_contains($text, ' '.implode(' ', $tokens).' ')) {
                return true;
            }
        }

        return false;
    }

    /** Une négation entoure-t-elle la mention (même phrase, NEGATION_WINDOW mots de part et d'autre) ? */
    private static function negatedAt(string $quote, int $offset, int $length): bool
    {
        $before = substr($quote, 0, $offset);
        $after = substr($quote, $offset + $length);
        // La phrase de la mention seulement : ce qui suit le dernier point, ce qui précède le suivant.
        $before = (string) preg_replace('/^.*[.;!?]/su', '', $before);
        $after = (string) preg_replace('/[.;!?].*$/su', '', $after);
        $words = [
            ...array_slice(self::words($before), -self::NEGATION_WINDOW),
            ...array_slice(self::words($after), 0, self::NEGATION_WINDOW),
        ];

        return array_intersect($words, self::NEGATIONS) !== [];
    }

    /**
     * Mots d'un texte, sans accents ni capitales ; une élision (« n'a », « l'écartement ») et une
     * contraction anglaise (« wasn't ») donnent leur mot de négation.
     *
     * @return list<string>
     */
    private static function words(string $text): array
    {
        $text = Str::lower(Str::ascii(str_replace(['’', '‘'], "'", $text)));
        $text = (string) preg_replace("/n't\\b/", ' not', $text);
        preg_match_all('/[a-z0-9]+/', $text, $m);

        return $m[0];
    }

    /**
     * Mots d'un nom de série : lettres et chiffres séparés (« Z1200 » donne « z », « 1200 »).
     *
     * @return list<string>
     */
    private static function tokens(string $text): array
    {
        preg_match_all('/[a-z]+|\d+/', Str::lower(Str::ascii($text)), $m);

        return $m[0];
    }
}
