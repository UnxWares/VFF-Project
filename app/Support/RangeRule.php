<?php

namespace App\Support;

use Illuminate\Support\Fluent;
use Illuminate\Validation\ConditionalRules;
use Illuminate\Validation\Rule;

/**
 * Borne haute d'un intervalle dont la borne basse est facultative : `gte:<champ>` seul fait échouer
 * la borne haute quand la borne basse est absente (Laravel compare alors un entier à null). La
 * comparaison n'est donc faite que si la borne basse est renseignée.
 */
final class RangeRule
{
    public static function atLeast(string $lowerBound): ConditionalRules
    {
        return Rule::when(fn (Fluent $input): bool => filled($input->get($lowerBound)), ['gte:'.$lowerBound]);
    }
}
