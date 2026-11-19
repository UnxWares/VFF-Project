<?php

namespace App\Http\Requests\Api;

use App\Rules\PaginationCursor;
use App\Services\Api\StationEventQuery;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Filtres et pagination de `GET /api/v1/stations/{station}/events` (API publique, anonyme).
 */
class ListStationEventsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(StationEventQuery::normalize($this->query()));
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return StationEventQuery::rules() + [
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'cursor' => ['nullable', 'string', 'max:512', new PaginationCursor(['station_events.event_date', 'station_events.id'])],
        ];
    }

    /** @return array<string, mixed> filtres validés, sans pagination */
    public function filters(): array
    {
        return $this->safe()->except(['per_page', 'cursor']);
    }

    public function perPage(): int
    {
        return $this->integer('per_page', 25);
    }
}
