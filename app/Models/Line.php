<?php

namespace App\Models;

use App\Enums\LineType;
use Database\Factories\LineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Line extends Model
{
    /** @use HasFactory<LineFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'alt_names' => 'array',
        'advocacy' => 'array',
        'is_threatened' => 'boolean',
        'type' => LineType::class,
    ];

    /** @return HasMany<LineSegment, $this> */
    public function segments(): HasMany
    {
        return $this->hasMany(LineSegment::class);
    }

    /** @return BelongsToMany<Material, $this> */
    public function materials(): BelongsToMany
    {
        return $this->belongsToMany(Material::class, 'line_materials')
            ->withPivot(['era_id', 'from_date', 'to_date', 'notes'])
            ->withTimestamps();
    }

    /** @return HasMany<LineEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(LineEvent::class);
    }

    /** @return MorphMany<Media, $this> */
    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
