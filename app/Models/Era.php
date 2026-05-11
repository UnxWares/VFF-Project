<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Era extends Model
{
    /** @use HasFactory<\Database\Factories\EraFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'year' => 'integer',
        'is_current' => 'boolean',
        'editable_until' => 'date',
    ];

    public function segments(): HasMany
    {
        return $this->hasMany(LineSegment::class);
    }

    public function lineMaterials(): HasMany
    {
        return $this->hasMany(LineMaterial::class);
    }
}
