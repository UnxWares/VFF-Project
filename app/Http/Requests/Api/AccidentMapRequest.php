<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Filtre de `GET /api/v1/accidents.geojson` : année de fin facultative (mêmes bornes que le
 * paramètre `to` de la liste des accidents).
 */
class AccidentMapRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['to' => ['nullable', 'integer', 'min:1800', 'max:2100']];
    }

    public function toYear(): ?int
    {
        return $this->filled('to') ? $this->integer('to') : null;
    }
}
