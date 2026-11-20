<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Période de circulation d'une série sur une ligne, datée par une source (Wikipédia ou Wikidata) :
 * début, fin, ou les deux, avec leur précision. Seules les périodes `high` et `medium` sont montrées.
 *
 * @property-read LineMaterial $link
 */
class LineMaterialPeriod extends Model
{
    /** Fiabilités montrées sur le site et servies par défaut. */
    public const SHOWN_CONFIDENCES = ['high', 'medium'];

    /**
     * Sources des périodes que l'import des périodes (vff:import-material-periods) ne touche jamais :
     * les contributions validées. Toute autre source, même inconnue, est une période importée, que
     * l'import remplace ou efface quand ses sources ne la portent plus.
     */
    public const CONTRIBUTED_SOURCES = ['contrib'];

    /**
     * Périodes que l'import gère : toutes sauf les contributions (une source nulle ou inconnue comprise).
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function imported(Builder $query): void
    {
        $query->where(fn (Builder $q) => $q->whereNull('source')->orWhereNotIn('source', self::CONTRIBUTED_SOURCES));
    }

    protected $guarded = ['id'];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'source_revision' => 'integer',
    ];

    /**
     * Forme courte servie aux fiches du site (ligne, série) et à la fiche REST d'une ligne : dates et
     * précision, portion, lien vers la source. Jamais la phrase lue.
     *
     * @return array{start_date: ?string, start_precision: ?string, end_date: ?string, end_precision: ?string, section_from: ?string, section_to: ?string, source: string, source_url: string}
     */
    public function toSheet(): array
    {
        return [
            'start_date' => $this->start_date?->toDateString(),
            'start_precision' => $this->start_precision,
            'end_date' => $this->end_date?->toDateString(),
            'end_precision' => $this->end_precision,
            'section_from' => $this->section_from,
            'section_to' => $this->section_to,
            'source' => $this->source,
            'source_url' => $this->source_url,
        ];
    }

    /** @return BelongsTo<LineMaterial, $this> */
    public function link(): BelongsTo
    {
        return $this->belongsTo(LineMaterial::class, 'line_material_id');
    }
}
