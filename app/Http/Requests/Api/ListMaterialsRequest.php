<?php

namespace App\Http\Requests\Api;

use App\Rules\PaginationCursor;
use App\Services\Api\MaterialQuery;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Filtres, tri et pagination de `GET /api/v1/materials` (API publique, anonyme).
 */
class ListMaterialsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return MaterialQuery::rules() + [
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'cursor' => ['nullable', 'string', 'max:512', new PaginationCursor(MaterialQuery::CURSOR_COLUMNS)],
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
