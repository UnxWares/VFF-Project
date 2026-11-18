<?php

namespace App\GraphQL\Resolvers;

use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Résolveur générique pour les champs GraphQL `String!` adossés à un attribut
 * Eloquent casté en BackedEnum (LineType, SegmentStatus, StationStatus, etc.).
 *
 * Sans lui, Lighthouse tente de sérialiser l'instance d'enum en String et
 * lève « String cannot represent value: instance of App\Enums\… ». Ce
 * résolveur renvoie la valeur scalaire (->value) - et reste transparent pour
 * les attributs déjà scalaires.
 */
class EnumValue
{
    public function __invoke(mixed $root, array $args, GraphQLContext $context, ResolveInfo $info): mixed
    {
        try {
            $value = data_get($root, $info->fieldName);
        } catch (\ValueError) {
            // Valeur en base absente de l'enum PHP (ex. nouveau type d'événement
            // importé avant mise à jour de l'enum) : on renvoie la valeur brute
            // plutôt que de faire échouer toute la requête.
            return $root instanceof Model
                ? $root->getRawOriginal($info->fieldName)
                : null;
        }

        return $value instanceof \BackedEnum ? $value->value : $value;
    }
}
