<?php

namespace App\Services\ArgentanWebFerro;

use App\Services\PlaceNames;

/**
 * Comparaison de noms de gares entre l'article et VFF, sans géographie : « St Pierre
 * d'Entremont » = « Saint-Pierre-d'Entremont », « Flers » ⊂ « Flers de l'Orne »,
 * « Condé/Huisne » = « Condé-sur-Huisne », « Fresnes » ≈ « Frênes » (une lettre d'écart).
 */
final class PlaceMatcher
{
    public static function same(string $a, string $b): bool
    {
        [$a, $b] = [self::expand($a), self::expand($b)];
        $ta = PlaceNames::tokens($a);
        $tb = PlaceNames::tokens($b);
        if ($ta === [] || $tb === []) {
            return false;
        }
        if (implode(' ', $ta) === implode(' ', $tb)) {
            return true;
        }
        [$short, $long] = count($ta) <= count($tb) ? [$a, $tb] : [$b, $ta];
        $significant = PlaceNames::significant($short);
        if ($significant !== [] && array_diff($significant, $long) === []) {
            return true;
        }
        $ja = implode('', $ta);
        $jb = implode('', $tb);

        return min(strlen($ja), strlen($jb)) >= 6 && levenshtein($ja, $jb) <= 1;
    }

    /** Gare au nom voisin (un mot significatif commun) : « Ambrières les Vallées » / « Ambrières-Centre ». */
    public static function near(string $a, string $b): bool
    {
        $sa = PlaceNames::significant(self::expand($a));
        $sb = PlaceNames::significant(self::expand($b));

        return array_intersect($sa, $sb) !== [];
    }

    /** Abréviations de l'article : « ND du Hamel » = « Notre-Dame-du-Hamel ». */
    private static function expand(string $name): string
    {
        return (string) preg_replace('/\bN\.?\s?D\.?(?=\s|-|$)/u', 'Notre-Dame', $name);
    }

    /**
     * Premier nom de la liste équivalent à $name.
     *
     * @param  iterable<string>  $names
     */
    public static function find(string $name, iterable $names): ?string
    {
        foreach ($names as $candidate) {
            if (self::same($name, $candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
