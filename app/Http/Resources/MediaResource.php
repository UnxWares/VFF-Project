<?php

namespace App\Http\Resources;

use App\Models\Line;
use App\Models\LineEvent;
use App\Models\Material;
use App\Models\Media;
use App\Models\Station;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Média (photo, carte postale, plan, carte, document) avec ses droits : à afficher avec l'image.
 *
 * Les médias externes (Wikimedia Commons…) ne sont pas hébergés par VFF : `thumb_url` et `display_url`
 * pointent chez le fournisseur, `source_url` vers la page d'origine (Commons pour les fichiers Commons).
 * `lat` / `lng` ne sont lus que si la requête les a sélectionnés (voir MediaQuery).
 *
 * @mixin Media
 */
class MediaResource extends JsonResource
{
    public const PROVIDER_LABELS = [
        'commons' => 'Wikimedia Commons',
        'wikidata' => 'Wikidata',
        'gallica' => 'Gallica (BnF)',
        'argentanwebferro' => 'Argentan Web Ferroviaire',
        'upload' => 'VFF',
    ];

    /** Type de rattachement : classe du modèle → clé publique. */
    private const SUBJECTS = [
        Line::class => 'line',
        Station::class => 'station',
        Material::class => 'material',
        LineEvent::class => 'event',
    ];

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $meta = $this->provider_meta ?? [];
        $thumb = $this->thumb_url ?? ($this->file_path ? Storage::url($this->file_path) : null);
        $lat = $this->getAttribute('lat');

        return [
            'id' => $this->id,
            'provider' => $this->provider,
            'provider_label' => self::PROVIDER_LABELS[$this->provider] ?? $this->provider,
            // Valeur brute : un type ajouté en base avant l'énumération PHP ne doit pas faire échouer la réponse.
            'type' => $this->resource->getRawOriginal('type'),
            'title' => $this->title,
            'caption' => $this->caption,
            'thumb_url' => $thumb,
            'display_url' => $this->display_url ?? $thumb,
            'source_url' => $this->source_url,
            'width' => $this->width,
            'height' => $this->height,
            'depicted_year' => $this->depicted_year,
            'depicted_year_to' => $meta['year_to'] ?? null,
            'date_precision' => $meta['date_precision'] ?? ($this->depicted_year ? 'year' : null),
            'captured_at' => $this->captured_at?->toDateString(),
            'author' => $this->author,
            'attribution' => $this->attribution,
            'license' => $this->license,
            'license_url' => $this->license_url,
            'rights_status' => $this->rights_status,
            'commercial_ok' => $this->commercial_ok,
            'share_alike' => $this->share_alike,
            'attribution_required' => $meta['attribution_required'] ?? null,
            'featured' => $this->is_featured,
            // Contribution validée qui a publié le média (Commons ou fichier déposé), nulle pour un import.
            'contribution_id' => is_numeric($meta['contribution_id'] ?? null) ? (int) $meta['contribution_id'] : null,
            'location' => $lat !== null ? ['lat' => (float) $lat, 'lng' => (float) $this->getAttribute('lng')] : null,
            'subject' => $this->whenLoaded('mediable', fn (): ?array => $this->subject()),
        ];
    }

    /**
     * Entité à laquelle le média est rattaché.
     *
     * @return array{type: string, slug?: string, id?: int, name?: string}|null
     */
    private function subject(): ?array
    {
        $subject = $this->mediable;
        $type = $subject !== null ? (self::SUBJECTS[$subject::class] ?? null) : null;

        return match (true) {
            $type === null => null,
            $subject instanceof LineEvent => ['type' => $type, 'id' => $subject->id],
            default => ['type' => $type, 'slug' => $subject->getAttribute('slug'), 'name' => $subject->getAttribute('name')],
        };
    }
}
