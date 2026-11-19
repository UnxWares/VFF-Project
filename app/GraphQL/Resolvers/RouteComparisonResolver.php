<?php

namespace App\GraphQL\Resolvers;

use App\Services\NetworkGraph\RouteException;
use App\Services\RouteComparison;

/**
 * Champ `routeComparison` : un même trajet à deux époques (RouteComparison). Comme `route`, un
 * refus qui vaut pour toute la comparaison (gare inconnue, même ère…) donne `null` ; l'API REST
 * en donne le motif.
 */
class RouteComparisonResolver
{
    public function __construct(private readonly RouteComparison $comparison) {}

    /**
     * @param  array{from: string, to: string, era?: ?int, compare: int}  $args
     * @return array<string, mixed>|null
     */
    public function __invoke(mixed $root, array $args): ?array
    {
        try {
            return $this->comparison->compare($args['from'], $args['to'], $args['era'] ?? null, $args['compare']);
        } catch (RouteException) {
            return null;
        }
    }

    /** @param  array{unavailable?: ?array{error: string}}  $root  `RouteAtEra` */
    public function reason(array $root): ?string
    {
        return $root['unavailable']['error'] ?? null;
    }

    /** @param  array{unavailable?: ?array{message: string}}  $root  `RouteAtEra` */
    public function message(array $root): ?string
    {
        return $root['unavailable']['message'] ?? null;
    }
}
