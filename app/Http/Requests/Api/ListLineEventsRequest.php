<?php

namespace App\Http\Requests\Api;

use App\Rules\PaginationCursor;
use App\Services\Api\LineEventQuery;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Filtres et pagination de `GET /api/v1/events` (API publique, anonyme).
 */
class ListLineEventsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(LineEventQuery::normalize($this->query()));
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return LineEventQuery::rules() + [
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'cursor' => ['nullable', 'string', 'max:512', new PaginationCursor(['line_events.event_date', 'line_events.id'])],
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
