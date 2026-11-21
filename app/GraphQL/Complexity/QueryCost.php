<?php

declare(strict_types=1);

namespace App\GraphQL\Complexity;

/**
 * Coût des champs GraphQL, compté avant l'exécution par la règle QueryComplexity
 * (`lighthouse.security.max_query_complexity`). Les méthodes sont appelées par la directive
 * `@complexity(resolver: "App\\GraphQL\\Complexity\\QueryCost@…")` de graphql/schema.graphql.
 *
 * Sans directive, un champ vaut 1 plus le coût de ses sous-champs, et une liste `@paginate`
 * multiplie le coût de ses sous-champs par `first` (Lighthouse). Ce barème ajoute :
 *   - les listes sans pagination (relations `@hasMany`, `@belongsToMany`, listes calculées) :
 *     sous-champs comptés pour LIST_SIZE éléments, la taille courante d'une telle liste ;
 *   - les relations paginées `@morphMany(type: CONNECTION)` et les annuaires : sous-champs
 *     multipliés par `first` (Lighthouse ne le fait pas pour elles) ;
 *   - les champs calculés (géométrie, arrêts, recherche, histoire d'un événement…) : COMPUTED ;
 *   - les champs lourds (fiche complète, profil en long, audit, itinéraires, comparaison d'ères),
 *     qui demandent chacun de quelques dizaines de millisecondes à quelques secondes : HEAVY.
 *
 * Les valeurs sont décrites dans graphql/schema.graphql (type Query) et sur /docs/api.
 */
final class QueryCost
{
    /** Nombre d'éléments compté pour une liste sans pagination. */
    public const int LIST_SIZE = 10;

    /** Coût propre d'un champ calculé à la demande. */
    public const int COMPUTED = 10;

    /** Coût propre d'un champ lourd. */
    public const int HEAVY = 250;

    /** @param  array<string, mixed>  $args */
    public function list(int $childrenComplexity, array $args): int
    {
        return 1 + $childrenComplexity * self::LIST_SIZE;
    }

    /**
     * Relation paginée : `first` vaut sa valeur par défaut quand le client ne le donne pas
     * (les arguments arrivent ici avec leurs valeurs par défaut).
     *
     * @param  array<string, mixed>  $args
     */
    public function paginated(int $childrenComplexity, array $args): int
    {
        $first = $args['first'] ?? null;

        return 1 + $childrenComplexity * (is_int($first) && $first > 0 ? $first : self::LIST_SIZE);
    }

    /** @param  array<string, mixed>  $args */
    public function computed(int $childrenComplexity, array $args): int
    {
        return self::COMPUTED + $childrenComplexity;
    }

    /** @param  array<string, mixed>  $args */
    public function heavy(int $childrenComplexity, array $args): int
    {
        return self::HEAVY + $childrenComplexity;
    }
}
