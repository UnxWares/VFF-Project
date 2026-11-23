<?php

namespace App\Http\Requests\Api;

use App\Services\LevelCrossings\LevelCrossingQuery;
use App\Services\Map\ViewListing;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Emprise de `GET /api/v1/eras/{year}/in-view` : `bbox=ouest,sud,est,nord` en degrés WGS 84 (obligatoire), et
 * coloration des tracés facultative (`color`).
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
            /**
             * Coloration des tracés de la carte (facultatif) : `speed` (classe de vitesse maximale), `electrification`,
             * `gauge` (écartement) ou `signalling` (famille de signalisation). Chaque ligne porte alors `classes`.
             *
             * @example speed
             */
            'color' => ['nullable', 'string', Rule::in(ViewListing::COLORINGS)],
        ];
    }

    /** Coloration demandée (ViewListing::COLORINGS), ou null. */
    public function color(): ?string
    {
        $color = $this->query('color');

        return is_string($color) && in_array($color, ViewListing::COLORINGS, true) ? $color : null;
    }

    /** @return array{0: float, 1: float, 2: float, 3: float} ouest, sud, est, nord, arrondis à 3 décimales (environ 100 m : clé de cache) */
    public function bbox(): array
    {
        /** @var array{0: float, 1: float, 2: float, 3: float} $box */
        $box = array_map(fn (string $v): float => round((float) $v, 3), explode(',', (string) $this->query('bbox')));

        return $box;
    }
}
