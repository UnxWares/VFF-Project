<?php

namespace App\Http\Requests\Api;

use App\Services\Api\AccidentQuery;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Filtres, tri et pagination de `GET /api/v1/accidents` (API publique, anonyme).
 */
class ListAccidentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return AccidentQuery::rules() + [
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** @return array<string, mixed> filtres validés, sans pagination */
    public function filters(): array
    {
        return $this->safe()->except(['per_page', 'page']);
    }

    public function perPage(): int
    {
        return $this->integer('per_page', 25);
    }
}
