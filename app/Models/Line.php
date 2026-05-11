<?php

namespace App\Models;

use App\Enums\LineType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Line extends Model
{
    /** @use HasFactory<\Database\Factories\LineFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'alt_names' => 'array',
        'type' => LineType::class,
    ];

    public function segments(): HasMany
    {
        return $this->hasMany(LineSegment::class);
    }

    public function materials(): BelongsToMany
    {
        return $this->belongsToMany(Material::class, 'line_materials')
            ->withPivot(['era_id', 'from_date', 'to_date', 'notes'])
            ->withTimestamps();
    }

    public function events(): HasMany
    {
        return $this->hasMany(LineEvent::class);
    }

    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
