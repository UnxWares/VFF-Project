<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Paramètres de `GET /api/v1/route/compare` : les deux gares, les deux années comparées et les
 * options du calcul d'itinéraire (mêmes bornes que `GET /api/v1/route`).
 */
class CompareRouteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'from' => ['required', 'string', 'max:200'],
            'to' => ['required', 'string', 'max:200'],
            'era' => ['nullable', 'integer', 'min:1800', 'max:2100'],
            'compare' => ['required', 'integer', 'min:1800', 'max:2100'],
            'heritage' => ['nullable', 'boolean'],
            'traffic' => ['nullable', 'in:passenger,all'],
        ];
    }

    /** Première année (null : réseau actuel). */
    public function eraYear(): ?int
    {
        return $this->filled('era') ? $this->integer('era') : null;
    }

    public function compareYear(): int
    {
        return $this->integer('compare');
    }

    /** @return array{heritage: bool, traffic: string} */
    public function routeOptions(): array
    {
        return ['heritage' => $this->boolean('heritage'), 'traffic' => (string) $this->input('traffic', 'passenger')];
    }
}
