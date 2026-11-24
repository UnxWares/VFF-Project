<?php

namespace App\GraphQL\Resolvers;

use App\Services\NetworkGraph\RouteException;
use App\Services\Routebook\Routebook;

/** Livret de parcours (`routebook(from, to)`) : même service que `GET /api/v1/routebook`, null si le trajet est impossible. */
class RoutebookResolver
{
    public function __construct(private readonly Routebook $routebook) {}

    /**
     * @param  array{from: string, to: string}  $args
     * @return array<string, mixed>|null
     */
    public function __invoke(mixed $_, array $args): ?array
    {
        try {
            $book = $this->routebook->between($args['from'], $args['to']);
        } catch (RouteException) {
            return null;
        }
        $book['legs'] = array_map(fn (array $leg): array => $leg + [
            'line_code' => $leg['line']['code'],
            'line_slug' => $leg['line']['slug'],
            'line_name' => $leg['line']['name'],
        ], $book['legs']);

        return $book;
    }
}
