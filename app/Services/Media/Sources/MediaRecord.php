<?php

namespace App\Services\Media\Sources;

use App\Enums\MediaType;
use App\Models\Media;
use App\Support\WebUrl;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Un média prêt à écrire dans `media`, avec tous ses droits, produit par une source (MediaSource) et écrit
 * par MediaWriter. Même forme pour Commons rattaché aux gares, Panoramax, Albert-Kahn, les cartes postales
 * d'archives : une source ne fabrique jamais de ligne SQL elle-même.
 *
 * Droits obligatoires (assertValid) : licence exacte telle que la source la déclare (`license`), statut,
 * `commercial_ok` et `derivatives_ok` connus (vrai ou faux, jamais présumés : nul seulement si la source ne
 * dit rien, ce qui est refusé pour une nouvelle source), crédit prêt à afficher, lien vers la page d'origine.
 * Un média du domaine public peut ne pas avoir de licence nommée.
 */
final readonly class MediaRecord
{
    public const PRECISIONS = ['day', 'month', 'year', 'decade', 'circa', 'range', 'before', 'after'];

    /**
     * @param  class-string  $mediableType
     * @param  ?string  $capturedAt  date de prise de vue AAAA-MM-JJ (premier jour du mois ou de l'année si la précision est moindre)
     * @param  array<string, mixed>  $meta  métadonnées d'audit (méthode de rattachement, distance, azimut…), rangées dans `provider_meta`
     */
    public function __construct(
        public string $provider,
        public string $externalId,
        public string $mediableType,
        public int $mediableId,
        public string $rightsStatus,
        public ?string $license,
        public ?string $licenseUrl,
        public ?bool $commercialOk,
        public ?bool $derivativesOk,
        public ?bool $shareAlike,
        public string $attribution,
        public ?string $author,
        public string $sourceUrl,
        public ?string $thumbUrl,
        public ?string $displayUrl,
        public MediaType $type = MediaType::Photo,
        public ?string $title = null,
        public ?string $caption = null,
        public ?int $width = null,
        public ?int $height = null,
        public ?string $capturedAt = null,
        public ?string $datePrecision = null,
        public ?int $depictedYear = null,
        public ?float $lat = null,
        public ?float $lng = null,
        public bool $featured = false,
        public array $meta = [],
    ) {}

    /** Motif de refus (droits incomplets, adresse non web…), null si le média peut être écrit. */
    public function invalidReason(): ?string
    {
        return match (true) {
            $this->provider === '' || mb_strlen($this->provider) > 20 => 'provider',
            $this->externalId === '' || mb_strlen($this->externalId) > 191 => 'external_id',
            ! in_array($this->rightsStatus, [Media::RIGHTS_PUBLIC_DOMAIN, Media::RIGHTS_OPEN_LICENSE, Media::RIGHTS_PERMISSION], true) => 'rights_status',
            $this->rightsStatus !== Media::RIGHTS_PUBLIC_DOMAIN && trim((string) $this->license) === '' => 'license',
            $this->commercialOk === null => 'commercial_ok',
            $this->derivativesOk === null => 'derivatives_ok',
            trim($this->attribution) === '' => 'attribution',
            WebUrl::safe($this->sourceUrl) === null => 'source_url',
            $this->thumbUrl === null || WebUrl::safe($this->thumbUrl) === null => 'thumb_url',
            $this->displayUrl !== null && WebUrl::safe($this->displayUrl) === null => 'display_url',
            $this->datePrecision !== null && ! in_array($this->datePrecision, self::PRECISIONS, true) => 'date_precision',
            $this->capturedAt !== null && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->capturedAt) => 'captured_at',
            ($this->lat === null) !== ($this->lng === null) => 'location',
            $this->lat !== null && (abs($this->lat) > 90 || abs((float) $this->lng) > 180) => 'location',
            default => null,
        };
    }

    /** @throws InvalidArgumentException si un droit manque (voir invalidReason()) */
    public function assertValid(): void
    {
        $reason = $this->invalidReason();
        if ($reason !== null) {
            throw new InvalidArgumentException("Média {$this->provider}:{$this->externalId} refusé : {$reason}");
        }
    }

    /**
     * Ligne de `media` (sans horodatage).
     *
     * @return array<string, mixed>
     */
    public function toRow(): array
    {
        return [
            'mediable_type' => $this->mediableType,
            'mediable_id' => $this->mediableId,
            'provider' => $this->provider,
            'external_id' => $this->externalId,
            'type' => $this->type->value,
            'title' => $this->title !== null ? mb_substr($this->title, 0, 255) : null,
            'caption' => $this->caption,
            'author' => $this->author !== null ? mb_substr($this->author, 0, 255) : null,
            'attribution' => $this->attribution,
            'license' => $this->license !== null ? mb_substr($this->license, 0, 191) : null,
            'license_url' => WebUrl::safe($this->licenseUrl),
            'rights_status' => $this->rightsStatus,
            'commercial_ok' => $this->commercialOk,
            'derivatives_ok' => $this->derivativesOk,
            'share_alike' => $this->shareAlike,
            'source_url' => WebUrl::safe($this->sourceUrl),
            'thumb_url' => WebUrl::safe($this->thumbUrl),
            'display_url' => WebUrl::safe($this->displayUrl),
            'width' => $this->width,
            'height' => $this->height,
            'captured_at' => $this->capturedAt,
            'date_precision' => $this->datePrecision,
            'depicted_year' => $this->depictedYear,
            'is_featured' => $this->featured,
            'location' => $this->lat !== null && $this->lng !== null
                ? DB::raw(sprintf('ST_SetSRID(ST_MakePoint(%.7F, %.7F), 4326)', $this->lng, $this->lat))
                : null,
            'provider_meta' => json_encode(
                array_filter([...$this->meta, 'date_precision' => $this->datePrecision], fn (mixed $v): bool => $v !== null),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ),
        ];
    }
}
