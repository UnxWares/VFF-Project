<?php

namespace App\Http\Requests\Api;

use App\Rules\PaginationCursor;
use App\Services\Api\LineDirectoryQuery;
use App\Services\Api\SortedCursor;
use App\Support\QueryBoolean;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Filtres, tri et pagination de `GET /api/v1/lines` (API publique, anonyme).
 */
class ListLinesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(QueryBoolean::castKeys(LineDirectoryQuery::normalize($this->query()), ['facets']));
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return LineDirectoryQuery::rules() + [
            'facets' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'cursor' => ['nullable', 'string', 'max:512', new PaginationCursor(SortedCursor::COLUMNS)],
        ];
    }

    /** @return array<string, mixed> filtres validés, sans pagination ni compteurs */
    public function filters(): array
    {
        return $this->safe()->except(['per_page', 'cursor', 'facets']);
    }

    public function perPage(): int
    {
        return $this->integer('per_page', 25);
    }

    public function wantsFacets(): bool
    {
        return $this->boolean('facets');
    }
}
