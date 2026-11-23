<?php

namespace App\Models;

use App\Services\Materials\RelationRouter;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Période de circulation d'une série sur une ligne, datée par une source (Wikipédia ou Wikidata) :
 * début, fin, ou les deux, avec leur précision. Seules les périodes `high` et `medium` sont montrées.
 *
 * Portion parcourue, quand la source la nomme (MaterialPeriodPortion) : `section_location`, gares
 * d'extrémité `from_station_id` / `to_station_id`, `located_pk_start_m` / `located_pk_end_m`.
 *
 * @property-read LineMaterial $link
 * @property-read ?Station $fromStation
 * @property-read ?Station $toStation
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
        'relation' => 'array',
        'located_pk_start_m' => 'integer',
        'located_pk_end_m' => 'integer',
    ];

    /** Portions montrées : placées sur la ligne par des gares ou des points remarquables (pas `line`, ni `unlocated`). */
    public const SHOWN_LOCATIONS = ['stations', 'landmarks'];

    /**
     * Forme courte servie aux fiches du site (ligne, série) et à la fiche REST d'une ligne : dates et
     * précision, portion, relation assurée (`relation` : `label`, `from` / `to` {`id`, `name`}, `service`, ou
     * null), portion placée sur la ligne (`portion`, voir portion()), lien vers la source. Jamais la phrase lue.
     *
     * @return array{start_date: ?string, start_precision: ?string, end_date: ?string, end_precision: ?string, section_from: ?string, section_to: ?string, relation: ?array<string, mixed>, portion: ?array<string, mixed>, source: string, source_url: string}
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
            'relation' => $this->relation,
            'portion' => $this->portion(),
            'source' => $this->source,
            'source_url' => $this->source_url,
        ];
    }

    /**
     * Portion de la ligne parcourue, placée sur le tracé : `location` (`stations`, `landmarks` ou `line` pour la
     * ligne entière), gares d'extrémité `from` / `to` (`id` et `name` lisible ; `id` nul pour un point
     * remarquable, nommé comme dans la source), PK de début et de fin `pk_start_m` <= `pk_end_m`. Null quand la source ne
     * nomme aucune portion ou qu'elle n'a pas pu être placée.
     *
     * @return array{location: string, from: ?array{id: ?int, name: string}, to: ?array{id: ?int, name: string}, pk_start_m: ?int, pk_end_m: ?int}|null
     */
    public function portion(): ?array
    {
        if (! in_array($this->section_location, [...self::SHOWN_LOCATIONS, 'line'], true)) {
            return null;
        }
        // Point remarquable (pas de gare) : nommé comme dans le titre de section qui le cite.
        $end = fn (?Station $station, ?string $written): ?array => $station !== null
            ? ['id' => (int) $station->id, 'name' => RelationRouter::readable((string) $station->name)]
            : ($written !== null ? ['id' => null, 'name' => $written] : null);

        return [
            'location' => (string) $this->section_location,
            'from' => $end($this->fromStation, $this->section_from),
            'to' => $end($this->toStation, $this->section_to),
            'pk_start_m' => $this->located_pk_start_m,
            'pk_end_m' => $this->located_pk_end_m,
        ];
    }

    /** @return BelongsTo<Station, $this> */
    public function fromStation(): BelongsTo
    {
        return $this->belongsTo(Station::class, 'from_station_id');
    }

    /** @return BelongsTo<Station, $this> */
    public function toStation(): BelongsTo
    {
        return $this->belongsTo(Station::class, 'to_station_id');
    }

    /** @return BelongsTo<LineMaterial, $this> */
    public function link(): BelongsTo
    {
        return $this->belongsTo(LineMaterial::class, 'line_material_id');
    }
}
