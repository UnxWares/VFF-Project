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
            /**
             * Gare de départ : slug, identifiant, nom exact ou nom de ville, comme pour `GET /api/v1/route`.
             *
             * @example angouleme
             */
            'from' => ['required', 'string', 'max:200'],
            /**
             * Gare d'arrivée, mêmes formes que `from`.
             *
             * @example limoges-benedictins
             */
            'to' => ['required', 'string', 'max:200'],
            /**
             * Première année, de 1800 à 2100 : on retient l'ère de cette année, sinon la décennie précédente. Par défaut : réseau actuel.
             *
             * @example 1930
             */
            'era' => ['nullable', 'integer', 'min:1800', 'max:2100'],
            /**
             * Seconde année, mêmes règles que `era` ; elle doit désigner une autre ère.
             *
             * @example 2026
             */
            'compare' => ['required', 'integer', 'min:1800', 'max:2100'],
            /**
             * Autoriser les voies exploitées par une association (train touristique), aux deux époques.
             *
             * @example false
             */
            'heritage' => ['nullable', 'boolean'],
            /**
             * Voies sans service voyageurs : `passenger` les pénalise, `all` les traite comme les autres.
             *
             * @example passenger
             */
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
