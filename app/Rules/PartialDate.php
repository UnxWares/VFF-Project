<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Date de filtre à précision variable : `AAAA` (année entière) ou `AAAA-MM-JJ` (jour exact).
 *
 * Utilisée comme borne de période (`from`, `to`) : une année vaut du 1er janvier au 31 décembre.
 */
final class PartialDate implements ValidationRule
{
    private const PATTERN = '/^(\d{4})(?:-(\d{2})-(\d{2}))?$/D';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) && ! is_int($value)) {
            $fail('The :attribute must be a year (YYYY) or a date (YYYY-MM-DD).');

            return;
        }
        $isDate = preg_match(self::PATTERN, (string) $value, $m) === 1
            && (int) $m[1] >= 1
            && (! isset($m[2]) || checkdate((int) $m[2], (int) $m[3], (int) $m[1]));
        if (! $isDate) {
            $fail('The :attribute must be a year (YYYY) or a date (YYYY-MM-DD).');
        }
    }

    /** Borne ISO `AAAA-MM-JJ` d'une valeur valide : début de période (`$end` faux) ou fin (`$end` vrai). */
    public static function bound(string|int $value, bool $end): string
    {
        $value = (string) $value;

        return strlen($value) === 4 ? $value.($end ? '-12-31' : '-01-01') : $value;
    }
}
