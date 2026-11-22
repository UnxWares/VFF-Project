<?php

namespace App\Http\Requests\Api;

use App\Services\Api\AccidentQuery;
use App\Support\QueryBoolean;
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

    /** `true` et `false` en toutes lettres acceptés pour les filtres booléens, comme sur les autres listes. */
    protected function prepareForValidation(): void
    {
        $this->merge(QueryBoolean::castKeys($this->query(), ['has_line']));
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return AccidentQuery::rules() + [
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            // Borne haute : au-delà, (page - 1) × per_page ne tient plus dans un entier (erreur 500).
            'page' => ['nullable', 'integer', 'min:1', 'max:1000000'],
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
