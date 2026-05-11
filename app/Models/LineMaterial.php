<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modèle pivot ligne × matériel × époque (avec colonnes additionnelles).
 * Utilisé directement quand on a besoin de l'enrichissement (date_from/to, notes).
 * Sinon, la relation belongsToMany sur Line ou Material suffit.
 */
class LineMaterial extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'from_date' => 'date',
        'to_date' => 'date',
    ];

    public function line(): BelongsTo
    {
        return $this->belongsTo(Line::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function era(): BelongsTo
    {
        return $this->belongsTo(Era::class);
    }
}
