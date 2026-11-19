<?php

namespace App\GraphQL\Resolvers;

use App\Enums\EraChange;
use App\GraphQL\Resolvers\Concerns\FindsEra;
use App\Services\EraComparison;
use GraphQL\Error\Error;

/**
 * Requête GraphQL `eraComparison(from, to)` : même résumé que l'API REST
 * `GET /api/v1/eras/{year}/compare/{other}` (EraComparison::summary), mêmes noms de champs.
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
        $summary = $this->comparison->summary($from->id, $to->id);

        return [
            'from' => $from->year,
            'to' => $to->year,
            'changes' => array_combine(
                array_map(fn (EraChange $c): string => $c->value, EraChange::cases()),
                array_map(fn (EraChange $c): array => $summary['changes'][$c->value], EraChange::cases()),
            ),
            'open_km' => $summary['open_km'],
            'links' => [
                'self' => route('api.eras.compare', [$from->year, $to->year]),
                'map' => url("/maps?era={$from->year}&compare={$to->year}"),
                'tiles' => url("/api/v1/eras/{$from->year}/compare/{$to->year}/tiles").'/{z}/{x}/{y}.mvt',
                'sources' => route('api.sources.index'),
            ],
        ];
    }
}
