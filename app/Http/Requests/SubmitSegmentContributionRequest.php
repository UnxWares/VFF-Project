<?php

namespace App\Http\Requests;

use App\Enums\SegmentStatus;
use App\Enums\TrafficType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Proposition de modification d'une portion de ligne par PK. Comme toute contribution sourcée,
 * elle cite au moins une source (adresse ou référence) : les validateurs ne relisent pas une
 * affirmation sans pièce.
 */
class SubmitSegmentContributionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'line_code' => 'required|string',
            'era_year' => 'required|integer',
            'pk_start_m' => 'required|integer|min:0|max:9999999',
            'pk_end_m' => 'required|integer|min:0|max:9999999|gt:pk_start_m',
            'changes' => 'required|array',
            // Valeurs des énumérations : avant, une valeur libre passait la validation
            // puis faisait échouer l'application (CHECK SQL) au moment de l'approbation,
            // avec l'erreur SQL brute affichée au validateur.
            'changes.status' => ['nullable', 'string', Rule::enum(SegmentStatus::class)],
            'changes.traffic_type' => ['nullable', 'string', Rule::enum(TrafficType::class)],
            'changes.electrified' => 'nullable|boolean',
            'changes.voltage_v' => 'nullable|integer|min:0|max:50000',
            'changes.max_speed_kmh' => 'nullable|integer|min:0|max:600',
            'changes.is_heritage' => 'nullable|boolean',
            // Au moins une source non vide (les chaînes vides arrivent à null : « required » les refuse).
            'sources' => ['required', 'array', 'min:1'],
            'sources.*' => ['required', 'string', 'max:500'],
            'notes' => 'nullable|string|max:2000',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'sources.required' => __('editor.segment.errors.source_required'),
            'sources.min' => __('editor.segment.errors.source_required'),
            'sources.*.required' => __('editor.segment.errors.source_required'),
        ];
    }
}
