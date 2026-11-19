<?php

namespace App\Models;

use App\Enums\MaterialType;
use Database\Factories\MaterialFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Série de matériel roulant (BB 15000, X 73500, TGV Atlantique…).
 *
 * Fiches importées de Wikidata (`source` = wikidata, `wikidata_id`) par vff:import-materials ;
 * `provenance` garde la source de chaque champ ({champ: {source, ref}}), `image` les droits du
 * fichier Commons (auteur, licence, attribution).
 *
 * @property list<string>|null $designations
 * @property list<array{name: string, wikidata_id: ?string, units: ?int}>|null $builders
 * @property list<array{name: string, wikidata_id: ?string, from_year: ?int, to_year: ?int, units: ?int}>|null $operators
 * @property list<array{label: string, wikidata_id: ?string}>|null $electrification
 * @property list<array{label: string, mm: ?int, wikidata_id: ?string}>|null $gauges
 * @property array<string, mixed>|null $image
 * @property array<string, array{source: string, ref?: string|null, original?: array{amount: float, unit: string}, via?: string, revid?: int, value?: string, units?: int, as_of?: string}>|null $provenance
 * @property string|null $service_status `in_service` ou `withdrawn` quand une source le dit (Wikidata P5817, effectif de l'infobox Wikipédia), nul sinon
 * @property string|null $service_status_checked_at date de lecture de la source (AAAA-MM-JJ)
 * @property string $scope `france` (rattachée à la France dans Wikidata) ou `attested` (circulation en France attestée par une ligne citée)
 */
class Material extends Model
{
    /** @use HasFactory<MaterialFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'type' => MaterialType::class,
        'in_service_from' => 'integer',
        'in_service_to' => 'integer',
        'designations' => 'array',
        'builders' => 'array',
        'operators' => 'array',
        'units_built' => 'integer',
        'max_speed_kmh' => 'integer',
        'power_kw' => 'integer',
        'electrification' => 'array',
        'gauge_mm' => 'integer',
        'gauges' => 'array',
        'image' => 'array',
        'provenance' => 'array',
        'fetched_at' => 'datetime',
    ];

    /** @return BelongsToMany<Line, $this> */
    public function lines(): BelongsToMany
    {
        return $this->belongsToMany(Line::class, 'line_materials')
            ->withPivot(LineMaterial::PIVOT_COLUMNS)
            ->withTimestamps();
    }

    /**
     * Liens vers les lignes avec leur preuve (source, citation, période).
     *
     * @return HasMany<LineMaterial, $this>
     */
    public function lineLinks(): HasMany
    {
        return $this->hasMany(LineMaterial::class);
    }

    /** @return MorphMany<Media, $this> */
    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    /** Adresse de l'élément Wikidata, nulle pour une fiche saisie dans VFF. */
    public function wikidataUrl(): ?string
    {
        return $this->wikidata_id !== null ? 'https://www.wikidata.org/wiki/'.$this->wikidata_id : null;
    }

    /** Adresse de l'article Wikipédia dans cette langue (`fr` ou `en`), nulle s'il n'existe pas. */
    public function wikipediaUrl(string $lang = 'fr'): ?string
    {
        $title = $lang === 'en' ? $this->wikipedia_en : $this->wikipedia_fr;

        return $title !== null ? "https://{$lang}.wikipedia.org/wiki/".rawurlencode(str_replace(' ', '_', $title)) : null;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
