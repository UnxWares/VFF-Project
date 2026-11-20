<?php

namespace App\GraphQL\Scalars;

use GraphQL\Error\Error;
use GraphQL\Language\AST\Node;
use GraphQL\Type\Definition\ScalarType;

/**
 * Scalaire `JSON` en sortie seulement : une structure imbriquée (objet ou liste) renvoyée telle
 * quelle, pour les parties de fiche dont la forme est décrite champ par champ dans la
 * documentation REST (phases, chronique, décennies…). Refusé en argument.
 */
final class Json extends ScalarType
{
    public string $name = 'JSON';

    public ?string $description = 'Structure JSON (objet ou liste) renvoyée telle quelle ; sa forme est décrite par le champ qui la porte. Type de sortie seulement.';

    public function serialize(mixed $value): mixed
    {
        return $value;
    }

    public function parseValue(mixed $value): never
    {
        throw new Error('Le type JSON ne sert qu\'en sortie.');
    }

    public function parseLiteral(Node $valueNode, ?array $variables = null): never
    {
        throw new Error('Le type JSON ne sert qu\'en sortie.');
    }
}
