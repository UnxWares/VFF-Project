<?php

namespace App\Http\Requests\Api;

use App\Rules\PaginationCursor;
use App\Services\Api\MediaQuery;
use App\Support\QueryBoolean;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Filtres et pagination de `GET /api/v1/media` (API publique, anonyme).
 */
class ListMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(QueryBoolean::castKeys(MediaQuery::normalize($this->query()), ['facets']));
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return MediaQuery::rules() + [
            'facets' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:60'],
            'cursor' => ['nullable', 'string', 'max:512', new PaginationCursor(['media.id'])],
        ];
    }

    /** @return array<string, mixed> filtres validés, sans pagination ni compteurs */
    public function filters(): array
    {
        return $this->safe()->except(['per_page', 'cursor', 'facets']);
    }

    public function wantsFacets(): bool
    {
        return $this->boolean('facets');
    }

    public function perPage(): int
    {
        return $this->integer('per_page', 24);
    }
}
