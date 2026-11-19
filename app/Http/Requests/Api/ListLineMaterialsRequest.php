<?php

namespace App\Http\Requests\Api;

use App\Enums\MaterialType;
use App\Models\LineMaterial;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filtres de `GET /api/v1/lines/{line}/materials` (API publique, anonyme).
 */
class ListLineMaterialsRequest extends FormRequest
{
    public const CONFIDENCES = ['high', 'medium', 'low'];

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'type' => ['nullable', Rule::enum(MaterialType::class)],
            'source' => ['nullable', Rule::in(LineMaterial::IMPORTED_SOURCES)],
            'min_confidence' => ['nullable', Rule::in(self::CONFIDENCES)],
        ];
    }

    /** @return list<string> niveaux de fiabilité retenus (`medium` : medium et high) */
    public function confidences(): array
    {
        $min = $this->input('min_confidence');

        return $min === null ? [] : array_slice(self::CONFIDENCES, 0, (int) array_search($min, self::CONFIDENCES, true) + 1);
    }
}
