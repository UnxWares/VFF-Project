<?php

namespace App\Enums;

use App\Models\Accident;
use App\Models\Line;
use App\Models\Material;
use App\Models\Station;
use Illuminate\Database\Eloquent\Model;

/** Sorte de fiche visée par un signalement d'erreur, et l'adresse de cette fiche sur le site. */
enum ErrorReportSubject: string
{
    case Line = 'line';
    case Station = 'station';
    case Material = 'material';
    case Accident = 'accident';

    /** @return class-string<Model> */
    public function modelClass(): string
    {
        return match ($this) {
            self::Line => Line::class,
            self::Station => Station::class,
            self::Material => Material::class,
            self::Accident => Accident::class,
        };
    }

    /** Chemin relatif de la fiche : « /lines/paris-le-havre ». */
    public function path(string $slug): string
    {
        $prefix = match ($this) {
            self::Line => 'lines',
            self::Station => 'stations',
            self::Material => 'materials',
            self::Accident => 'accidents',
        };

        return "/{$prefix}/".rawurlencode($slug);
    }

    /** Fiche désignée par son slug, ou null si elle n'existe pas. */
    public function find(string $slug): ?Model
    {
        return $this->modelClass()::query()->where('slug', $slug)->first();
    }

    /**
     * Nom de la fiche, gardé avec le signalement (la fiche peut être renommée ensuite) :
     * « Paris - Le Havre (340000) » pour une ligne, le nom français d'un accident.
     */
    public function labelOf(Model $model): string
    {
        return match (true) {
            $model instanceof Line => trim($model->name.($model->code ? " ({$model->code})" : '')),
            $model instanceof Accident => $model->nameIn('fr'),
            default => (string) $model->getAttribute('name'),
        };
    }
}
