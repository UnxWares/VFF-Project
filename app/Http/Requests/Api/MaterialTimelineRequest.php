<?php

namespace App\Http\Requests\Api;

use App\Services\Materials\MaterialTimeline;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Filtres de `GET /api/v1/materials/timeline` (API publique, anonyme). */
class MaterialTimelineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'group' => ['nullable', Rule::in(array_keys(MaterialTimeline::GROUPS))],
            'builder' => ['nullable', 'string', 'max:100'],
            'in_service_at' => ['nullable', 'integer', 'between:1800,2100'],
        ];
    }

    /** @return array<string, mixed> */
    public function filters(): array
    {
        return $this->safe()->all();
    }
}
