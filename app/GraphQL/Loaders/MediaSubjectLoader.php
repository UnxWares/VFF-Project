<?php

namespace App\GraphQL\Loaders;

use App\Models\Media;
use GraphQL\Deferred;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Chargement groupé de l'entité d'un média (`mediable`) en GraphQL : les médias d'une même liste
 * (`Material.media`, `LineEvent.media`, `media(...)`) lisent leurs entités en une requête par type
 * (ligne, gare, série, événement), au lieu d'une requête par média. Une instance par position dans
 * la requête (BatchLoaderRegistry de Lighthouse), oubliée à la fin de la requête.
 */
final class MediaSubjectLoader
{
    /** @var array<int, Media> médias dont l'entité n'est pas encore lue, par identifiant */
    private array $pending = [];

    /** Entité du média, lue avec celles des autres médias demandés. */
    public function load(Media $media): Deferred
    {
        if (! $media->relationLoaded('mediable')) {
            $this->pending[(int) $media->id] = $media;
        }

        return new Deferred(function () use ($media): ?Model {
            if ($this->pending !== []) {
                (new Collection(array_values($this->pending)))->loadMissing('mediable');
                $this->pending = [];
            }

            $subject = $media->getRelationValue('mediable');

            return $subject instanceof Model ? $subject : null;
        });
    }
}
