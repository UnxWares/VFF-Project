<?php

namespace App\GraphQL\Resolvers\Concerns;

use App\Models\Era;
use GraphQL\Error\Error;

trait FindsEra
{
    /**
     * Ère demandée (année exacte) ou ère courante. Erreur CLIENT lisible si
     * elle n'existe pas : un firstOrFail() devenait « Internal server error ».
     */
    protected static function findEra(?int $year): Era
    {
        $era = $year !== null
            ? Era::where('year', $year)->first()
            : Era::where('is_current', true)->first();

        return $era ?? throw new Error($year !== null ? "Aucune ère {$year}." : 'Aucune ère courante.');
    }
}
