<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lien ligne × matériel roulant : le matériel a circulé (ou circule) sur la ligne.
 *
 * Un lien importé n'existe que si une source le dit : `source` (wikidata, wikipedia), `source_url`
 * (permalien de la révision lue) et `evidence`, liste des preuves (article, section, citation).
 * `from_date` / `to_date` / `era_id` ne sont remplis que si la source date la période ; un lien
 * saisi dans VFF a une `source` nulle.
 */
class LineMaterial extends Model
{
    /** Colonnes du pivot exposées par les relations belongsToMany de Line et Material. */
    public const PIVOT_COLUMNS = ['era_id', 'from_date', 'to_date', 'notes', 'source', 'source_url', 'confidence', 'evidence'];

    /** Sources des liens importés automatiquement (vff:import-materials). */
    public const IMPORTED_SOURCES = ['wikidata', 'wikipedia'];

    protected $guarded = ['id'];

    protected $casts = [
        'from_date' => 'date',
        'to_date' => 'date',
        'evidence' => 'array',
        'fetched_at' => 'datetime',
    ];

    /** @return BelongsTo<Line, $this> */
    public function line(): BelongsTo
    {
        return $this->belongsTo(Line::class);
    }

    /** @return BelongsTo<Material, $this> */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    /** @return BelongsTo<Era, $this> */
    public function era(): BelongsTo
    {
        return $this->belongsTo(Era::class);
    }
}
