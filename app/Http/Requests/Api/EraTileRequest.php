<?php

namespace App\Http\Requests\Api;

use App\Enums\TileFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * Paramètres de `GET /api/v1/eras/{year}/tiles/{z}/{x}/{y}.mvt` : jeu d'attributs facultatif (`fields`, TileFields).
 */
class EraTileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string|Enum>> */
    public function rules(): array
    {
        return ['fields' => ['nullable', 'string', Rule::enum(TileFields::class)]];
    }

    public function fields(): TileFields
    {
        return $this->enum('fields', TileFields::class) ?? TileFields::All;
    }
}
