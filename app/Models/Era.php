<?php

namespace App\Models;

use Database\Factories\EraFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Era extends Model
{
    /** @use HasFactory<EraFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'year' => 'integer',
        'is_current' => 'boolean',
        'editable_until' => 'date',
    ];

    /** @return HasMany<LineSegment, $this> */
    public function segments(): HasMany
    {
        return $this->hasMany(LineSegment::class);
    }

    /** @return HasMany<LineMaterial, $this> */
    public function lineMaterials(): HasMany
    {
        return $this->hasMany(LineMaterial::class);
    }
}
