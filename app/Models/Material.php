<?php

namespace App\Models;

use App\Enums\MaterialType;
use Database\Factories\MaterialFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Série de matériel roulant (BB 15000, X 73500, TGV Atlantique…).
 *
 * Fiches importées de Wikidata (`source` = wikidata, `wikidata_id`) par vff:import-materials ;
 * `provenance` garde la source de chaque champ ({champ: {source, ref}} ; source `contrib` pour une
 * valeur proposée par un contributeur et validée : ref `contribution:<id>`, url de la source citée),
 * `image` les droits du fichier Commons (auteur, licence, attribution).
 *
 * @property list<string>|null $designations
 * @property list<array{name: string, wikidata_id: ?string, units: ?int}>|null $builders
 * @property list<array{name: string, wikidata_id: ?string, from_year: ?int, to_year: ?int, units: ?int}>|null $operators
 * @property list<array{label: string, wikidata_id: ?string}>|null $electrification
 * @property list<array{label: string, mm: ?int, wikidata_id: ?string}>|null $gauges
 * @property array<string, mixed>|null $image
 * @property array<string, array{source: string, ref?: string|null, url?: string, original?: array{amount: float, unit: string}, via?: string, revid?: int, value?: string, units?: int, as_of?: string}>|null $provenance
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

    /**
     * Nom lisible : ni vide, ni un identifiant Wikidata brut (« Q529531 »). L'import garde l'identifiant
     * comme nom quand l'élément n'a de libellé ni en français ni en anglais (WikidataMaterialMapper) :
     * il ne s'affiche jamais comme un nom (revue des PR #19 et #20, 16 constructeurs sur 15 séries).
     */
    public static function isReadableName(?string $name): bool
    {
        return $name !== null && trim($name) !== '' && preg_match('/^Q\d+$/', trim($name)) !== 1;
    }

    /**
     * Noms des constructeurs (Wikidata P176), sans doublon, dans l'ordre de la source, sans ceux qui ne
     * sont connus que par leur identifiant.
     *
     * @return list<string>
     */
    public function builderNames(): array
    {
        return array_values(array_unique(array_filter(array_column($this->builders ?? [], 'name'), self::isReadableName(...))));
    }

    /**
     * Constructeurs en une ligne (`builder`, noms joints à l'import), lus sans les identifiants Wikidata
     * bruts : « Henschel, Q529531, Krauss-Maffei » se lit « Henschel, Krauss-Maffei ».
     *
     * @return Attribute<?string, never>
     */
    protected function builder(): Attribute
    {
        return Attribute::get(fn (?string $value): ?string => self::readableJoinedNames($value));
    }

    /** Noms joints par des virgules, sans ceux qui ne sont qu'un identifiant Wikidata ; null s'il n'en reste aucun. */
    public static function readableJoinedNames(?string $joined): ?string
    {
        $kept = array_filter(explode(', ', $joined ?? ''), self::isReadableName(...));

        return $kept === [] ? null : implode(', ', $kept);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** Largeur des vignettes servies avec les fiches de ligne (palier de miniatures de Commons). */
    public const THUMB_WIDTH = 330;

    /**
     * Vignette légère de l'image de la série (`image`, Commons) et son crédit, pour la section « Matériel
     * roulant » de la fiche ligne : miniature ramenée à THUMB_WIDTH px quand l'original est plus large (celles
     * de l'import font 500 px), dimensions pour réserver la place, auteur, licence et page du fichier. Null
     * sans image.
     *
     * @param  array<string, mixed>|null  $image
     * @return array{url: string, width: int, height: int, file: ?string, author: ?string, license: ?string, license_url: ?string, rights_status: ?string, source_url: ?string, provider: string}|null
     */
    public static function thumbnail(?array $image): ?array
    {
        $url = $image['thumb_url'] ?? null;
        if (! is_string($url) || $url === '') {
            return null;
        }
        $width = isset($image['width']) ? (int) $image['width'] : 0;
        $height = isset($image['height']) ? (int) $image['height'] : 0;
        if ($width > self::THUMB_WIDTH && substr_count($url, '/500px-') === 1) {
            $url = str_replace('/500px-', '/'.self::THUMB_WIDTH.'px-', $url);
        }
        $shown = $width > 0 ? min($width, self::THUMB_WIDTH) : self::THUMB_WIDTH;

        return [
            'url' => $url,
            'width' => $shown,
            'height' => $width > 0 && $height > 0 ? (int) round($shown * $height / $width) : (int) round($shown * 2 / 3),
            'file' => isset($image['file']) ? (string) $image['file'] : null,
            'author' => isset($image['author']) && trim((string) $image['author']) !== '' ? (string) $image['author'] : null,
            'license' => $image['license'] ?? null,
            'license_url' => $image['license_url'] ?? null,
            'rights_status' => $image['rights_status'] ?? null,
            'source_url' => $image['source_url'] ?? null,
            'provider' => (string) ($image['provider'] ?? 'commons'),
        ];
    }
}
