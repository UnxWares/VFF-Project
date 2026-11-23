<?php

namespace App\Services\Api;

use App\Models\Station;
use App\Models\StationEvent;
use App\Rules\PartialDate;
use App\Support\RedistributableDates;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

/**
 * Filtres des événements d'une gare (`GET /api/v1/stations/{station}/events`).
 */
final class StationEventQuery
{
    /** @return array<string, list<mixed>> */
    public static function rules(): array
    {
        return [
            'type' => ['nullable', 'string', 'max:24'],
            'source' => ['nullable', 'string', 'max:30'],
            'from' => ['nullable', new PartialDate],
            'to' => ['nullable', new PartialDate],
            'inferred' => ['nullable', Rule::in([true, false, 1, 0, '1', '0'])],
            'order' => ['nullable', Rule::in(['asc', 'desc'])],
        ];
    }

    /**
     * Forme canonique : `true` et `false` en chaînes deviennent des booléens.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public static function normalize(array $filters): array
    {
        if (isset($filters['inferred']) && is_string($filters['inferred'])) {
            $filters['inferred'] = match (strtolower($filters['inferred'])) {
                'true' => true,
                'false' => false,
                default => $filters['inferred'],
            };
        }

        return $filters;
    }

    /**
     * @param  Builder<StationEvent>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<StationEvent>
     */
    public function apply(Builder $query, Station $station, array $filters): Builder
    {
        $query->where('station_events.station_id', $station->id)
            // Hors du site : jamais un événement sans source redistribuable, ni une date déduite d'un tel événement de ligne.
            ->whereRaw(app(RedistributableDates::class)->stationEventKept('station_events'));

        if (filled($filters['type'] ?? null)) {
            $query->where('station_events.type', $filters['type']);
        }
        if (filled($filters['source'] ?? null)) {
            $query->where('station_events.source', $filters['source']);
        }
        if (filled($filters['from'] ?? null)) {
            $query->where('station_events.event_date', '>=', PartialDate::bound($filters['from'], false));
        }
        if (filled($filters['to'] ?? null)) {
            $query->where('station_events.event_date', '<=', PartialDate::bound($filters['to'], true));
        }
        if (isset($filters['inferred'])) {
            $query->where('station_events.is_inferred', filter_var($filters['inferred'], FILTER_VALIDATE_BOOLEAN));
        }

        $direction = ($filters['order'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        return $query->orderBy('station_events.event_date', $direction)->orderBy('station_events.id', $direction);
    }
}
