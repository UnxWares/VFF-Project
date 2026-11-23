<?php

namespace App\Http\Requests\Api;

use App\Services\LevelCrossings\LevelCrossingQuery;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Emprise de `GET /api/v1/eras/{year}/in-view` : `bbox=ouest,sud,est,nord` en degrés WGS 84 (obligatoire).
 */
class MapViewListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            /**
             * Emprise `ouest,sud,est,nord` en degrés WGS 84 (point décimal), ouest < est et sud < nord. Obligatoire.
             *
             * @example 2.2,48.8,2.5,48.95
             */
            'bbox' => ['bail', 'required', 'string', 'regex:'.LevelCrossingQuery::BBOX_PATTERN, function (string $attribute, mixed $value, \Closure $fail): void {
                [$west, $south, $east, $north] = array_map('floatval', explode(',', (string) $value));
                if ($west >= $east || $south >= $north || $west < -180 || $east > 180 || $south < -90 || $north > 90) {
                    $fail(__('validation.custom.bbox.invalid'));
                }
            }],
        ];
    }

    /** @return array{0: float, 1: float, 2: float, 3: float} ouest, sud, est, nord, arrondis à 3 décimales (environ 100 m : clé de cache) */
    public function bbox(): array
    {
        /** @var array{0: float, 1: float, 2: float, 3: float} $box */
        $box = array_map(fn (string $v): float => round((float) $v, 3), explode(',', (string) $this->query('bbox')));

        return $box;
    }
}
