<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Paramètres de `GET /api/v1/what-was-here` : un point (WGS 84), l'année, le rayon et la langue.
 */
class WhatWasHereRequest extends FormRequest
{
    /** Rayon par défaut et bornes, en mètres : « quelques centaines de mètres » autour du point. */
    public const DEFAULT_RADIUS_M = 400;

    public const MIN_RADIUS_M = 100;

    public const MAX_RADIUS_M = 1000;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'year' => ['sometimes', 'integer', 'min:1800', 'max:2100'],
            'radius_m' => ['sometimes', 'integer', 'min:'.self::MIN_RADIUS_M, 'max:'.self::MAX_RADIUS_M],
            'lang' => ['sometimes', 'string', 'in:fr,en,de'],
        ];
    }

    /**
     * Centre de la case de 0,001 degré (environ 100 m) qui contient le point : deux lecteurs d'une même case
     * partagent la même réponse en cache, et la réponse ne dépend plus de la position exacte demandée.
     *
     * @return array{0: float, 1: float} latitude, longitude
     */
    public function cell(): array
    {
        return [
            round((floor((float) $this->query('lat') * 1000) + 0.5) / 1000, 4),
            round((floor((float) $this->query('lng') * 1000) + 0.5) / 1000, 4),
        ];
    }
}
