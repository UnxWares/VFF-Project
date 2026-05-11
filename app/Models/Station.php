<?php

namespace App\Models;

use App\Enums\StationType;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Station extends Model
{
    /** @use HasFactory<\Database\Factories\StationFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'geom' => Point::class,
        'type' => StationType::class,
        'alt_names' => 'array',
        'opened_at' => 'date',
        'closed_at' => 'date',
    ];

    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
