<?php

namespace App\Models;

use App\Enums\MediaType;
use App\Support\WebUrl;
use Clickbar\Magellan\Data\Geometries\Point;
use Database\Factories\MediaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Média rattaché à une entité (ligne, gare, matériel, événement).
 *
 * Deux natures :
 *   - `provider = upload` : fichier hébergé par VFF (file_path renseigné) ;
 *   - autre provider (commons, wikidata, gallica…) : média EXTERNE, on ne
 *     garde que métadonnées + URL de miniatures (hotlink), jamais le fichier.
 *
 * Droits : `license` est la licence telle que déclarée par la source, jamais
 * réécrite ; NULL = inconnue (pas de licence présumée). `attribution` est la
 * ligne de crédit à afficher avec l'image.
 *
 * Adresses (`source_url`, `license_url`, `thumb_url`, `display_url`) : venues de la source, elles sont
 * rendues en lien ou en image ; seules les adresses http(s) sont lues et écrites (WebUrl), une autre
 * devient nulle.
 */
class Media extends Model
{
    /** @use HasFactory<MediaFactory> */
    use HasFactory;

    public const PROVIDER_UPLOAD = 'upload';

    public const PROVIDER_COMMONS = 'commons';

    public const RIGHTS_PUBLIC_DOMAIN = 'public_domain';

    public const RIGHTS_OPEN_LICENSE = 'open_license';

    public const RIGHTS_PERMISSION = 'permission';

    public const RIGHTS_UNKNOWN = 'unknown';

    /**
     * Droits d'une image que l'API (REST, GraphQL, serveur MCP) ne diffuse pas : une simple autorisation de
     * l'auteur vaut pour le site, pas pour une redistribution. Le site garde ces images.
     */
    public const RIGHTS_NOT_REDISTRIBUTED = [self::RIGHTS_PERMISSION];

    protected $table = 'media';

    protected $guarded = ['id'];

    protected $casts = [
        'type' => MediaType::class,
        'location' => Point::class,
        'exif_data' => 'array',
        'provider_meta' => 'array',
        'captured_at' => 'date',
        'file_size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'depicted_year' => 'integer',
        'commercial_ok' => 'boolean',
        'share_alike' => 'boolean',
        'is_featured' => 'boolean',
    ];

    /** @return Attribute<?string, mixed> */
    protected function sourceUrl(): Attribute
    {
        return self::webUrl();
    }

    /** @return Attribute<?string, mixed> */
    protected function licenseUrl(): Attribute
    {
        return self::webUrl();
    }

    /** @return Attribute<?string, mixed> */
    protected function thumbUrl(): Attribute
    {
        return self::webUrl();
    }

    /** @return Attribute<?string, mixed> */
    protected function displayUrl(): Attribute
    {
        return self::webUrl();
    }

    /** @return Attribute<?string, mixed> */
    private static function webUrl(): Attribute
    {
        return Attribute::make(get: WebUrl::safe(...), set: WebUrl::safe(...));
    }

    /** @return MorphTo<Model, $this> */
    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isExternal(): bool
    {
        return $this->provider !== self::PROVIDER_UPLOAD;
    }

    /** @param  Builder<Media>  $query */
    public function scopeExternal(Builder $query): void
    {
        $query->where('provider', '<>', self::PROVIDER_UPLOAD);
    }

    /**
     * Médias que l'API peut diffuser (sans RIGHTS_NOT_REDISTRIBUTED).
     *
     * @param  Builder<Media>  $query
     */
    public function scopeRedistributable(Builder $query): void
    {
        $query->whereNotIn($query->qualifyColumn('rights_status'), self::RIGHTS_NOT_REDISTRIBUTED);
    }
}
