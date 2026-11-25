<?php

namespace App\GraphQL\Resolvers;

use App\Models\Line;
use App\Services\LineSheet;

class LineAltNamesField
{
    /**
     * `Line.alt_names` : autres noms de la ligne, comme `alt_names` de la fiche hors du site (LineSheet::otherNames).
     * La colonne `lines.alt_names` est un objet de provenance (noms datés, libellés de tracés, fusions), pas une liste
     * de noms : la lire telle quelle échouait.
     *
     * @return list<string>
     */
    public function __invoke(Line $line): array
    {
        return app(LineSheet::class)->otherNames($line);
    }
}
