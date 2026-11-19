<?php

namespace App\GraphQL\Resolvers;

use App\GraphQL\Resolvers\Concerns\FindsEra;
use App\Services\EraComparison;
use GraphQL\Error\Error;

/**
 * Requête GraphQL `eraComparison(from, to)` : même résumé que l'API REST
 * `GET /api/v1/eras/{year}/compare/{other}` (EraComparison::compare), mêmes noms de champs.
 * Les deux années peuvent venir dans n'importe quel ordre ; la comparaison va de la plus ancienne
 * (`from`) à la plus récente (`to`).
 */
final class EraComparisonResolver
{
    use FindsEra;

    public function __construct(private readonly EraComparison $comparison) {}

    /**
     * @param  array{from: int, to: int}  $args
     * @return array<string, mixed>
     */
    public function __invoke(mixed $_, array $args): array
    {
        if ($args['from'] === $args['to']) {
            throw new Error('Choisissez deux ères différentes à comparer.');
        }
        [$from, $to] = collect([self::findEra($args['from']), self::findEra($args['to'])])->sortBy('year')->values()->all();

        return $this->comparison->compare($from->id, $from->year, $to->id, $to->year, url('/'));
    }
}
