<?php

namespace App\GraphQL\Resolvers;

use App\Models\Accident;
use App\Models\AccidentLine;
use App\Models\AccidentSource;

/**
 * Champs calculés des types GraphQL `Accident`, `AccidentLineLink` et `AccidentSource` : mêmes
 * valeurs que l'API REST (AccidentResource, AccidentDetailResource).
 */
final class AccidentField
{
    private const LOCALES = ['fr', 'en', 'de'];

    /** @param array{lang?: string} $args */
    public function name(Accident $accident, array $args): string
    {
        return $accident->nameIn($this->locale($args));
    }

    /** @param array{lang?: string} $args */
    public function summary(Accident $accident, array $args): string
    {
        return $accident->summary[$this->locale($args)] ?? $accident->summary['fr'] ?? '';
    }

    /** @param array{lang?: string} $args */
    public function wikipediaUrl(Accident $accident, array $args): ?string
    {
        return $accident->wikipedia[$this->locale($args)]['url'] ?? null;
    }

    public function time(Accident $accident): ?string
    {
        return $accident->occurred_at_time !== null ? substr((string) $accident->occurred_at_time, 0, 5) : null;
    }

    public function lat(Accident $accident): ?float
    {
        return $accident->geom?->getLatitude();
    }

    public function lng(Accident $accident): ?float
    {
        return $accident->geom?->getLongitude();
    }

    /** @return list<array<string, string|null>> */
    public function trains(Accident $accident): array
    {
        return $accident->trains ?? [];
    }

    /** @return list<array{code: string, label: string, detail: string}> */
    public function reviewNotes(Accident $accident): array
    {
        return array_map(fn (array $n) => [
            'code' => $n['code'],
            'label' => __("accidents.review.{$n['code']}", [], 'fr'),
            'detail' => $n['detail'],
        ], $accident->review_notes ?? []);
    }

    /** @return list<string> */
    public function method(AccidentLine $link): array
    {
        return explode('+', $link->method);
    }

    public function pkLabel(AccidentLine $link): ?string
    {
        return $link->pk_m !== null ? sprintf('%d+%03d', intdiv($link->pk_m, 1000), $link->pk_m % 1000) : null;
    }

    /** Révision lue, en texte : les révisions de Wikidata dépassent l'entier 32 bits de GraphQL. */
    public function revision(AccidentSource $source): ?string
    {
        return $source->revision !== null ? (string) $source->revision : null;
    }

    public function facts(AccidentSource $source): ?string
    {
        return $source->facts !== null ? (string) json_encode($source->facts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
    }

    /** @param array{lang?: string} $args */
    private function locale(array $args): string
    {
        $lang = strtolower((string) ($args['lang'] ?? 'fr'));

        return in_array($lang, self::LOCALES, true) ? $lang : 'fr';
    }
}
