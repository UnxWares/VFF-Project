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
            ->withPivot(LineMaterial::PIVOT_COLUMNS)
            ->withTimestamps();
    }

    /**
     * Liens vers le matériel roulant avec leur preuve (source, citation, période).
     *
     * @return HasMany<LineMaterial, $this>
     */
    public function materialLinks(): HasMany
    {
        return $this->hasMany(LineMaterial::class);
    }

    /** @return HasMany<LineEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(LineEvent::class);
    }

    /**
     * Documents d'archives de Gallica rattachés à la ligne (vff:import-gallica), du plus ancien au plus
     * récent, les documents sans date en dernier.
     *
     * @return HasMany<LineArchiveDocument, $this>
     */
    public function archiveDocuments(): HasMany
    {
        return $this->hasMany(LineArchiveDocument::class)
            ->orderByRaw('year IS NULL, year, exact_date NULLS LAST, title, id');
    }

    /** @return MorphMany<Media, $this> */
    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    /**
     * Accidents rattachés à la ligne (vff:import-accidents), principaux ou secondaires.
     *
     * @return BelongsToMany<Accident, $this>
     */
    public function accidents(): BelongsToMany
    {
        return $this->belongsToMany(Accident::class, 'accident_lines')
            ->withPivot(['is_primary', 'era_year', 'distance_m', 'pk_m', 'method', 'confidence', 'score'])
            ->withTimestamps();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
