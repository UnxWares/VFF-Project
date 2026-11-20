<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Lien ligne × matériel roulant : le matériel a circulé (ou circule) sur la ligne.
 *
 * Un lien importé n'existe que si une source le dit : `source` (wikidata, wikipedia), `source_url`
 * (permalien de la révision lue) et `evidence`, liste des preuves (article, section, citation).
 * `from_date` / `to_date` / `era_id` ne sont remplis que si la source date la période ; un lien
 * saisi dans VFF a une `source` nulle. Les périodes lues dans les articles (début, fin, précision,
 * fiabilité) sont dans `periods` (table `line_material_periods`).
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

    /**
     * Périodes de circulation datées par une source, de la plus ancienne à la plus récente.
     *
     * @return HasMany<LineMaterialPeriod, $this>
     */
    public function periods(): HasMany
    {
        return $this->hasMany(LineMaterialPeriod::class)
            ->orderByRaw('COALESCE(start_date, end_date)')
            ->orderBy('end_date');
    }

    /**
     * Périodes montrées (fiabilité `high` ou `medium`).
     *
     * @return HasMany<LineMaterialPeriod, $this>
     */
    public function shownPeriods(): HasMany
    {
        return $this->periods()->whereIn('confidence', LineMaterialPeriod::SHOWN_CONFIDENCES);
    }

    /**
     * Relations assurées par la série sur la ligne, d'après les preuves qui citent une relation ou un
     * itinéraire (`route`, MaterialRelations) : une entrée par libellé, avec les périodes montrées qui la
     * datent. Les preuves qui portent un doute ne comptent pas.
     *
     * @param  iterable<LineMaterialPeriod>|null  $periods  périodes montrées déjà chargées (sinon lues)
     * @return list<array{kind: string, label: string, from: ?array{id: ?int, name: string}, to: ?array{id: ?int, name: string}, service: ?string, title: ?string, periods: list<array{start_date: ?string, start_precision: ?string, end_date: ?string, end_precision: ?string}>}>
     */
    public function relations(?iterable $periods = null): array
    {
        $out = [];
        foreach ($this->evidence ?? [] as $evidence) {
            $route = $evidence['route'] ?? null;
            if (! is_array($route) || ! isset($route['label']) || isset($evidence['doubt'])) {
                continue;
            }
            $out[$route['label']] ??= [
                'kind' => (string) $route['kind'],
                'label' => (string) $route['label'],
                'from' => $route['from'] ?? null,
                'to' => $route['to'] ?? null,
                'service' => $route['service'] ?? null,
                'title' => $route['title'] ?? null,
                'periods' => [],
            ];
        }
        if ($out === []) {
            return [];
        }
        foreach ($periods ?? ($this->relationLoaded('shownPeriods') ? $this->shownPeriods : $this->shownPeriods()->get()) as $period) {
            $label = $period->relation['label'] ?? null;
            if (is_string($label) && isset($out[$label])) {
                $out[$label]['periods'][] = [
                    'start_date' => $period->start_date?->toDateString(),
                    'start_precision' => $period->start_precision,
                    'end_date' => $period->end_date?->toDateString(),
                    'end_precision' => $period->end_precision,
                ];
            }
        }

        return array_values($out);
    }
}
