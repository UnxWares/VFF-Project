<?php

namespace App\Models;

use App\Enums\MaterialType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Material extends Model
{
    /** @use HasFactory<\Database\Factories\MaterialFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'type' => MaterialType::class,
        'in_service_from' => 'integer',
        'in_service_to' => 'integer',
    ];

    public function lines(): BelongsToMany
    {
        return $this->belongsToMany(Line::class, 'line_materials')
            ->withPivot(['era_id', 'from_date', 'to_date', 'notes'])
            ->withTimestamps();
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
