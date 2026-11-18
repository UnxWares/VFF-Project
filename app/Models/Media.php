<?php

namespace App\Models;

use App\Enums\MediaType;
use Clickbar\Magellan\Data\Geometries\Point;
use Database\Factories\MediaFactory;
use Illuminate\Database\Eloquent\Builder;
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
}
