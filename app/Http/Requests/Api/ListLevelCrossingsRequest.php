<?php

namespace App\Http\Requests\Api;

use App\Services\LevelCrossings\LevelCrossingQuery;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Filtres et pagination de `GET /api/v1/level-crossings` (API publique, anonyme).
 */
class ListLevelCrossingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return LevelCrossingQuery::rules() + [
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
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
        return $this->integer('per_page', 50);
    }
}
