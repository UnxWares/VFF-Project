<?php

namespace App\Models;

use App\Enums\MediaType;
use App\Support\SourceRights;
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
 * `commercial_ok = false` (licence NC) : montré seulement si le réglage `vff.media.show_non_commercial` est vrai
 * (portée displayable(), à poser partout où un média est servi). `derivatives_ok = false` (licence ND) : image
 * réduite, jamais recadrée (isCroppable()).
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

    /** Langues des légendes traduites (`provider_meta.captions`, image proposée sur le site). */
    public const CAPTION_LOCALES = ['fr', 'en', 'de'];

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
        'derivatives_ok' => 'boolean',
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

    /**
     * Légendes par langue (`fr`, `en`, `de`) rangées dans `provider_meta.captions`, null s'il n'y en a pas :
     * `caption` reste la légende principale (le français, sinon la première langue donnée).
     *
     * @return array<string, string>|null
     */
    public function captions(): ?array
    {
        $captions = $this->provider_meta['captions'] ?? null;
        if (! is_array($captions)) {
            return null;
        }
        $found = [];
        foreach (self::CAPTION_LOCALES as $locale) {
            if (is_string($captions[$locale] ?? null) && trim($captions[$locale]) !== '') {
                $found[$locale] = $captions[$locale];
            }
        }

        return $found === [] ? null : $found;
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

    /** Vrai si les médias sous licence non commerciale sont montrés (réglage `vff.media.show_non_commercial`). */
    public static function showsNonCommercial(): bool
    {
        return (bool) config('vff.media.show_non_commercial', true);
    }

    /** Vrai si les médias d'un fonds à licence incertaine sont montrés (réglage `vff.media.show_uncertain`). */
    public static function showsUncertain(): bool
    {
        return (bool) config('vff.media.show_uncertain', true);
    }

    /**
     * Valeurs de `media.provider` dont le fonds est déclaré « incertain » dans le registre des droits
     * (database/data/source_rights.php, table `media`).
     *
     * @return list<string>
     */
    public static function uncertainProviders(): array
    {
        /** @var list<string>|null $cache */
        static $cache = null;

        return $cache ??= (new SourceRights)->sourcesWithStatus('media', SourceRights::UNCERTAIN);
    }

    /** Vrai si la licence de ce média est « incertaine » : son fonds est déclaré `incertain` au registre des droits. */
    public function isRightsUncertain(): bool
    {
        return in_array($this->provider, self::uncertainProviders(), true);
    }

    /**
     * Condition SQL des médias montrables, à poser dans une requête brute : `TRUE` quand les réglages montrent
     * les médias non commerciaux et ceux à licence incertaine, sinon `<alias>.commercial_ok IS DISTINCT FROM FALSE`
     * (inconnu reste montré) et `<alias>.provider NOT IN (fonds incertains)`.
     */
    public static function displayableSql(string $alias = 'media'): string
    {
        $parts = [];
        if (! self::showsNonCommercial()) {
            $parts[] = "{$alias}.commercial_ok IS DISTINCT FROM FALSE";
        }
        $uncertain = self::uncertainProviders();
        if (! self::showsUncertain() && $uncertain !== []) {
            $parts[] = "{$alias}.provider NOT IN (".implode(', ', array_map(fn (string $p): string => "'".str_replace("'", "''", $p)."'", $uncertain)).')';
        }

        return $parts === [] ? 'TRUE' : implode(' AND ', $parts);
    }

    /**
     * Médias montrables selon les réglages `vff.media.show_non_commercial` et `vff.media.show_uncertain` : sans effet quand ils
     * sont vrais, sinon écarte les médias `commercial_ok = false` (NC) et ceux d'un fonds à licence incertaine. Un droit inconnu
     * (null) reste montré.
     *
     * @param  Builder<Media>  $query
     */
    public function scopeDisplayable(Builder $query): void
    {
        if (! self::showsNonCommercial()) {
            $query->where(fn (Builder $q) => $q->whereNull($query->qualifyColumn('commercial_ok'))
                ->orWhere($query->qualifyColumn('commercial_ok'), true));
        }
        $uncertain = self::uncertainProviders();
        if (! self::showsUncertain() && $uncertain !== []) {
            $query->whereNotIn($query->qualifyColumn('provider'), $uncertain);
        }
    }

    /** Faux pour une image sous clause ND : réduite seulement, jamais recadrée (vignette entière). */
    public function isCroppable(): bool
    {
        return $this->derivatives_ok !== false;
    }

    /**
     * Médias que l'API peut diffuser : sans RIGHTS_NOT_REDISTRIBUTED, et montrables (scopeDisplayable).
     *
     * @param  Builder<Media>  $query
     */
    public function scopeRedistributable(Builder $query): void
    {
        $query->whereNotIn($query->qualifyColumn('rights_status'), self::RIGHTS_NOT_REDISTRIBUTED);
        $this->scopeDisplayable($query);
    }
}
