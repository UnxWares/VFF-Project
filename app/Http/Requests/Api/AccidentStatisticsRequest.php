<?php

namespace App\Http\Requests\Api;

use App\Enums\AccidentKind;
use App\Support\RangeRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filtres de `GET /api/v1/accidents/statistics` (API publique, anonyme) : période, nature, état de relecture.
 */
class AccidentStatisticsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'integer', 'between:1800,2100'],
            'to' => ['nullable', 'integer', 'between:1800,2100', RangeRule::atLeast('from')],
            'kind' => ['nullable', Rule::enum(AccidentKind::class)],
            'review' => ['nullable', Rule::in(['ok'])],
        ];
    }

    /** @return array<string, mixed> */
    public function filters(): array
    {
        return $this->safe()->all();
    }
}
