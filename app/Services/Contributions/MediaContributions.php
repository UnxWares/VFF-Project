<?php

namespace App\Services\Contributions;

use App\Enums\ContentLicense;
use App\Enums\ContributionStatus;
use App\Enums\MediaType;
use App\Models\Contribution;
use App\Models\Line;
use App\Models\LineEvent;
use App\Models\Material;
use App\Models\Media;
use App\Models\Station;
use App\Services\Media\CommonsFileResolver;
use App\Services\Media\ImageSanitizer;
use App\Services\Media\SanitizedImage;
use App\Services\Media\UnsupportedImage;
use App\Services\TraceContributionService;
use App\Support\WebUrl;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Contributions « média » (type `media`) : une image rattachée à une ligne, à une gare, à un
 * événement daté d'une ligne ou à une série de matériel roulant. Deux origines :
 *
 *   - `commons` : un fichier de Wikimedia Commons désigné par son adresse. Ses métadonnées (auteur,
 *     licence, miniature) sont lues sur Commons (CommonsFileResolver) ; une licence non libre ou
 *     non commerciale est refusée. Rien n'est téléchargé : comme les autres médias Commons, l'image
 *     reste chez Wikimedia ;
 *   - `upload` : un fichier déposé, avec son auteur, sa licence (liste fermée ContentLicense), sa
 *     date de prise de vue et son lieu. Type réel, taille et dimensions contrôlés ; métadonnées
 *     (dont la position GPS) retirées par ImageSanitizer AVANT l'enregistrement. Le fichier attend
 *     dans une réserve privée (disque `local`, jamais servi publiquement) : seuls son auteur et les
 *     validateurs le voient (MediaContributionController::file). À la publication, il est copié
 *     dans le disque public, une fois la transaction validée. Refus, retrait ou suppression du
 *     compte : il est effacé (discard()).
 *
 * À la publication, le média entre dans `media` avec sa provenance : `provider_meta.source` =
 * `contribution:<id>` (et, pour un fichier déposé, `external_id` = `contribution:<id>`).
 */
final readonly class MediaContributions
{
    public const TYPE = 'media';

    public const SOURCES = ['commons', 'upload'];

    /** Rattachement : une ligne, une gare, un événement daté d'une ligne, ou une série de matériel. */
    public const TARGETS = ['line', 'station', 'event', 'material'];

    /** Taille maximale d'un fichier déposé, en kilo-octets (10 Mo). */
    public const MAX_KILOBYTES = 10240;

    /** Côté le plus court accepté, côté le plus long accepté (pixels). */
    public const MIN_SIDE = 400;

    public const MAX_SIDE = 12000;

    /** Réserve privée des fichiers en attente (storage/app/contributions/media, jamais servie par le site). */
    public const PRIVATE_DISK = 'local';

    public const PRIVATE_DIR = 'contributions/media';

    /** Fichiers publiés (storage/app/public/media/contributions, servis sous /storage). */
    public const PUBLIC_DISK = 'public';

    public const PUBLIC_DIR = 'media/contributions';

    public const PROVENANCE_PREFIX = 'contribution:';

    public function __construct(
        private ImageSanitizer $images,
        private CommonsFileResolver $commons,
        private TraceContributionService $traces,
    ) {}

    /** @return list<string> */
    public static function mediaTypes(): array
    {
        return array_map(fn (MediaType $t): string => $t->value, MediaType::cases());
    }

    /**
     * Règles de saisie, communes au formulaire du site et à la mutation GraphQL. Les champs qui ne
     * concernent pas l'origine ou le rattachement choisis sont écartés (exclude_if, exclude_unless).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public static function rules(): array
    {
        return [
            'source' => ['required', 'string', Rule::in(self::SOURCES)],
            'target_type' => ['required', 'string', Rule::in(self::TARGETS)],
            'line_slug' => ['exclude_if:target_type,station,material', 'required', 'string', 'max:191', 'exists:lines,slug'],
            'station_slug' => ['exclude_unless:target_type,station', 'required', 'string', 'max:191', 'exists:stations,slug'],
            'material_slug' => ['exclude_unless:target_type,material', 'required', 'string', 'max:191', 'exists:materials,slug'],
            'event_id' => ['exclude_unless:target_type,event', 'required', 'integer', 'min:1'],
            'commons_url' => ['exclude_unless:source,commons', 'required', 'string', 'max:500'],
            'file' => ['exclude_unless:source,upload', 'required', 'file', 'mimetypes:'.implode(',', ImageSanitizer::MIMES),
                'max:'.self::MAX_KILOBYTES,
                Rule::dimensions()->minWidth(self::MIN_SIDE)->minHeight(self::MIN_SIDE)->maxWidth(self::MAX_SIDE)->maxHeight(self::MAX_SIDE)],
            'media_type' => ['exclude_unless:source,upload', 'required', 'string', Rule::in(self::mediaTypes())],
            'title' => ['exclude_unless:source,upload', 'required', 'string', 'min:3', 'max:250'],
            'caption' => ['exclude_unless:source,upload', 'nullable', 'string', 'max:800'],
            'author' => ['exclude_unless:source,upload', 'required', 'string', 'min:2', 'max:200'],
            'license' => ['exclude_unless:source,upload', 'required', 'string', Rule::in(ContentLicense::mediaValues())],
            'captured_precision' => ['exclude_unless:source,upload', 'nullable', 'required_with:captured_date', 'string', Rule::in(TraceContributionService::PRECISIONS)],
            'captured_date' => ['exclude_unless:source,upload', 'nullable', 'required_with:captured_precision', 'string', 'max:10'],
            'place' => ['exclude_unless:source,upload', 'nullable', 'string', 'max:250'],
            'lat' => ['exclude_unless:source,upload', 'nullable', 'required_with:lng', 'numeric', 'between:-90,90'],
            'lng' => ['exclude_unless:source,upload', 'nullable', 'required_with:lat', 'numeric', 'between:-180,180'],
            'rights_confirmed' => ['exclude_unless:source,upload', 'accepted'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        $sizes = ['min' => self::MIN_SIDE, 'max' => self::MAX_SIDE];

        return [
            'line_slug.required' => __('proposals.errors.line_required'),
            'line_slug.exists' => __('proposals.errors.line_required'),
            'station_slug.required' => __('proposals.errors.station_required'),
            'station_slug.exists' => __('proposals.errors.station_required'),
            'material_slug.required' => __('proposals.errors.material_required'),
            'material_slug.exists' => __('proposals.errors.material_required'),
            'event_id.required' => __('proposals.media.errors.event'),
            'event_id.integer' => __('proposals.media.errors.event'),
            'commons_url.required' => __('proposals.media.errors.commons_url'),
            'file.required' => __('proposals.media.errors.file_required'),
            'file.file' => __('proposals.media.errors.file_required'),
            'file.mimetypes' => __('proposals.media.errors.file_type'),
            'file.max' => __('proposals.media.errors.file_size', ['max' => self::MAX_KILOBYTES / 1024]),
            'file.dimensions' => __('proposals.media.errors.file_dimensions', $sizes),
            'title.required' => __('proposals.media.errors.title'),
            'title.min' => __('proposals.media.errors.title'),
            'author.required' => __('proposals.media.errors.author'),
            'author.min' => __('proposals.media.errors.author'),
            'license.required' => __('proposals.media.errors.license'),
            'license.in' => __('proposals.media.errors.license'),
            'captured_date.required_with' => __('editor.errors.bad_date'),
            'captured_precision.required_with' => __('editor.errors.bad_date'),
            'lat.required_with' => __('proposals.media.errors.coordinates'),
            'lng.required_with' => __('proposals.media.errors.coordinates'),
            'lat.between' => __('proposals.media.errors.coordinates'),
            'lng.between' => __('proposals.media.errors.coordinates'),
            'rights_confirmed.accepted' => __('proposals.media.errors.rights'),
        ];
    }

    /**
     * Prépare une proposition validée par rules() : cible, métadonnées Commons, ou image nettoyée
     * (pas encore enregistrée : voir storePrivate()).
     *
     * @param  array<string, mixed>  $data
     * @return array{payload: array<string, mixed>, target: Line|Station|Material, image: ?SanitizedImage}
     *
     * @throws ValidationException adresse Commons illisible, fichier refusé, doublon, événement d'une autre ligne
     * @throws ModelNotFoundException ligne, gare ou série inconnue
     */
    public function prepare(array $data): array
    {
        ['model' => $target, 'payload' => $targetPayload, 'mediable' => [$mediableType, $mediableId]] = $this->target($data);
        $common = [
            'source' => $data['source'],
            ...$targetPayload,
            'notes' => self::trimmed($data['notes'] ?? null),
        ];

        if ($data['source'] === 'commons') {
            $meta = $this->commonsFile((string) $data['commons_url']);
            $this->ensureNewCommons((int) $meta['page_id'], $mediableType, $mediableId);

            return ['payload' => [...$common, 'commons' => self::commonsSummary($meta)], 'target' => $target, 'image' => null];
        }

        $file = $data['file'];
        if (! $file instanceof UploadedFile) {
            throw ValidationException::withMessages(['file' => __('proposals.media.errors.file_required')]);
        }
        try {
            $image = $this->images->sanitize((string) $file->get());
        } catch (UnsupportedImage $e) {
            throw ValidationException::withMessages(['file' => __('proposals.media.errors.file_'.$e->reason)]);
        }
        $sha256 = hash('sha256', $image->bytes);
        $this->ensureNewFile($sha256);

        $license = ContentLicense::from((string) $data['license']);
        $captured = null;
        if (($data['captured_date'] ?? null) !== null) {
            $date = $this->traces->normalizeDate((string) $data['captured_date'], (string) $data['captured_precision'])
                ?? throw ValidationException::withMessages(['captured_date' => __('editor.errors.bad_date')]);
            $captured = ['date' => $date, 'precision' => $data['captured_precision']];
        }
        $location = isset($data['lat'], $data['lng']) ? ['lat' => round((float) $data['lat'], 6), 'lng' => round((float) $data['lng'], 6)] : null;

        return [
            'payload' => [
                ...$common,
                'media_type' => $data['media_type'],
                'title' => self::clean($data['title']),
                'caption' => self::trimmed($data['caption'] ?? null),
                'author' => self::clean($data['author']),
                'license' => $license->value,
                'license_name' => $license->label(),
                'license_url' => $license->url(),
                'captured' => $captured,
                'place' => self::clean($data['place'] ?? null),
                'location' => $location,
                'file' => [
                    'mime' => $image->mime,
                    'extension' => $image->extension(),
                    'size' => strlen($image->bytes),
                    'width' => $image->displayWidth(),
                    'height' => $image->displayHeight(),
                    'sha256' => $sha256,
                    'orientation' => $image->orientation,
                    'removed' => $image->removed,
                ],
                'rights_confirmed' => true,
            ],
            'target' => $target,
            'image' => $image,
        ];
    }

    /**
     * Enregistre une image nettoyée dans la réserve privée, sous un nom aléatoire.
     *
     * @return string chemin dans le disque privé
     *
     * @throws RuntimeException écriture impossible
     */
    public function storePrivate(SanitizedImage $image): string
    {
        $path = self::PRIVATE_DIR.'/'.Str::uuid().'.'.$image->extension();
        if (! Storage::disk(self::PRIVATE_DISK)->put($path, $image->bytes)) {
            throw new RuntimeException("Écriture impossible dans la réserve privée : {$path}");
        }

        return $path;
    }

    /** Efface tout de suite un fichier de la réserve privée (envoi abandonné avant l'enregistrement). */
    public function deletePrivate(string $path): void
    {
        if (self::isPrivatePath($path)) {
            Storage::disk(self::PRIVATE_DISK)->delete($path);
        }
    }

    /**
     * Publie le média (dans la transaction de l'approbation). Un fichier déposé est copié vers le
     * disque public une fois la transaction validée : annulée, rien n'a été rendu public.
     *
     * @return array{media_id: int, file_path: ?string}
     *
     * @throws ContributionNotPublishable cible disparue, fichier Commons déjà rattaché, fichier privé absent
     */
    public function apply(Contribution $contribution): array
    {
        $p = $contribution->payload;
        [$mediableType, $mediableId] = $this->publishedTarget($contribution);

        if (($p['source'] ?? null) === 'commons') {
            $meta = self::safeUrls($p['commons']);
            if ($this->commonsAttached((int) $meta['page_id'], $mediableType, $mediableId)) {
                throw new ContributionNotPublishable(PublicationFailure::AlreadyPresent, "Fichier Commons {$meta['page_id']} déjà rattaché (contribution {$contribution->id})");
            }
            $id = DB::table('media')->insertGetId($this->commonsRow($contribution, $meta, $mediableType, $mediableId));

            return ['media_id' => (int) $id, 'file_path' => null];
        }

        $file = $p['file'] ?? [];
        $private = (string) ($file['path'] ?? '');
        if (! self::isPrivatePath($private) || ! Storage::disk(self::PRIVATE_DISK)->exists($private)) {
            throw new ContributionNotPublishable(PublicationFailure::FileMissing, "Fichier privé absent pour la contribution {$contribution->id}");
        }
        $public = self::PUBLIC_DIR.'/'.$contribution->id.'-'.Str::lower(Str::random(16)).'.'.$file['extension'];
        $external = self::PROVENANCE_PREFIX.$contribution->id;
        $id = DB::table('media')->where('provider', Media::PROVIDER_UPLOAD)->where('external_id', $external)->value('id')
            ?? DB::table('media')->insertGetId($this->uploadRow($contribution, $p, $public, $mediableType, $mediableId));

        DB::afterCommit(fn () => $this->publishFile($private, $public));

        return ['media_id' => (int) $id, 'file_path' => $public];
    }

    /**
     * Efface le fichier privé d'une contribution refusée, retirée ou supprimée, une fois la
     * transaction en cours validée (rien n'est effacé si elle est annulée).
     */
    public function discard(Contribution $contribution): void
    {
        $path = $contribution->payload['file']['path'] ?? null;
        if ($contribution->type !== self::TYPE || ! is_string($path) || ! self::isPrivatePath($path)) {
            return;
        }
        DB::afterCommit(function () use ($path): void {
            try {
                Storage::disk(self::PRIVATE_DISK)->delete($path);
            } catch (Throwable $e) {
                report($e);
            }
        });
    }

    /**
     * Fichier privé d'une contribution en attente (chemin et type), null s'il n'existe pas ou plus
     * (contribution Commons, publiée, refusée).
     *
     * @return array{path: string, mime: string}|null
     */
    public function pendingFile(Contribution $contribution): ?array
    {
        $file = $contribution->payload['file'] ?? null;
        if ($contribution->type !== self::TYPE || ! $contribution->status->isOpen() || ! is_array($file)
            || ! is_string($file['path'] ?? null) || ! in_array($file['mime'] ?? null, ImageSanitizer::MIMES, true)
            || ! self::isPrivatePath($file['path']) || ! Storage::disk(self::PRIVATE_DISK)->exists($file['path'])) {
            return null;
        }

        return ['path' => $file['path'], 'mime' => $file['mime']];
    }

    /**
     * Charge utile montrée hors du serveur (API, pages du site) : sans le chemin du fichier dans la
     * réserve privée, qui ne sert qu'au serveur, et avec les adresses Commons relues (http(s) seulement,
     * pour une proposition enregistrée avant ce contrôle).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function withoutPrivatePath(array $payload): array
    {
        if (is_array($payload['file'] ?? null)) {
            unset($payload['file']['path']);
        }
        if (is_array($payload['commons'] ?? null)) {
            $payload['commons'] = self::safeUrls($payload['commons']);
        }

        return $payload;
    }

    /**
     * Métadonnées d'un fichier Commons accepté (lookup + verdict sur les droits), pour le
     * formulaire (aperçu avant l'envoi) et pour prepare().
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException adresse illisible, fichier absent, Commons injoignable, droits refusés
     */
    public function commonsFile(string $url, string $field = 'commons_url'): array
    {
        $title = CommonsFileResolver::fileTitle($url)
            ?? throw ValidationException::withMessages([$field => __('proposals.media.errors.commons_url')]);
        try {
            $meta = $this->commons->lookup($title);
        } catch (RuntimeException|HttpClientException $e) {
            report($e);
            throw ValidationException::withMessages([$field => __('proposals.media.errors.commons_unreachable')]);
        }
        if ($meta === null) {
            throw ValidationException::withMessages([$field => __('proposals.media.errors.commons_missing')]);
        }
        $refusal = CommonsFileResolver::refusal($meta);
        if ($refusal !== null) {
            throw ValidationException::withMessages([$field => __('proposals.media.errors.commons_'.$refusal)]);
        }

        return $meta;
    }

    /**
     * Résumé des métadonnées Commons gardé dans la proposition et montré aux validateurs.
     *
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    public static function commonsSummary(array $meta): array
    {
        return self::safeUrls(array_intersect_key($meta, array_flip([
            'page_id', 'file_title', 'title', 'caption', 'type', 'mime', 'width', 'height', 'source_url', 'thumb_url',
            'display_url', 'author', 'credit', 'attribution', 'attribution_required', 'license', 'license_url',
            'rights_status', 'commercial_ok', 'share_alike', 'depicted_year', 'captured_at', 'date_precision', 'date_raw',
            'lat', 'lon',
        ])));
    }

    /**
     * Adresses d'un résumé Commons rendues en lien ou en image : http(s) seulement, nulles sinon (WebUrl).
     *
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private static function safeUrls(array $meta): array
    {
        foreach (['source_url', 'thumb_url', 'display_url', 'license_url'] as $key) {
            if (array_key_exists($key, $meta)) {
                $meta[$key] = WebUrl::safe($meta[$key]);
            }
        }

        return $meta;
    }

    // ─── Rattachement ───────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $data
     * @return array{model: Line|Station|Material, payload: array<string, mixed>, mediable: array{0: class-string, 1: int}}
     */
    private function target(array $data): array
    {
        if ($data['target_type'] === 'material') {
            $material = Material::query()->where('slug', $data['material_slug'])->firstOrFail();

            return [
                'model' => $material,
                'payload' => ['target_type' => 'material', 'material_slug' => $material->slug, 'material_name' => $material->name],
                'mediable' => [Material::class, $material->id],
            ];
        }
        if ($data['target_type'] === 'station') {
            $station = Station::query()->where('slug', $data['station_slug'])->firstOrFail();

            return [
                'model' => $station,
                'payload' => ['target_type' => 'station', 'station_slug' => $station->slug, 'station_name' => $station->name],
                'mediable' => [Station::class, $station->id],
            ];
        }

        $line = Line::query()->where('slug', $data['line_slug'])->firstOrFail();
        $payload = ['target_type' => $data['target_type'], 'line_slug' => $line->slug, 'line_code' => $line->code, 'line_name' => $line->name];
        if ($data['target_type'] !== 'event') {
            return ['model' => $line, 'payload' => $payload, 'mediable' => [Line::class, $line->id]];
        }

        $event = LineEvent::query()->whereKey((int) $data['event_id'])->where('line_id', $line->id)->first()
            ?? throw ValidationException::withMessages(['event_id' => __('proposals.media.errors.event')]);

        return [
            'model' => $line,
            'payload' => [...$payload, 'event' => [
                'id' => $event->id,
                'type' => $event->getRawOriginal('type'),
                'date' => $event->event_date->toDateString(),
                'precision' => $event->date_precision,
            ]],
            'mediable' => [LineEvent::class, $event->id],
        ];
    }

    /**
     * Fiche à laquelle le média publié se rattache, relue au moment de la publication.
     *
     * @return array{0: class-string, 1: int}
     *
     * @throws ContributionNotPublishable la ligne, la gare, l'événement ou la série a disparu
     */
    private function publishedTarget(Contribution $contribution): array
    {
        $p = $contribution->payload;
        $eventId = isset($p['event']['id']) ? (int) $p['event']['id'] : null;
        [$type, $id, $exists] = match (true) {
            $eventId !== null => [LineEvent::class, $eventId, LineEvent::query()->whereKey($eventId)->exists()],
            $contribution->target_type === Station::class => [Station::class, (int) $contribution->target_id, Station::query()->whereKey($contribution->target_id)->exists()],
            $contribution->target_type === Material::class => [Material::class, (int) $contribution->target_id, Material::query()->whereKey($contribution->target_id)->exists()],
            default => [Line::class, (int) $contribution->target_id, Line::query()->whereKey($contribution->target_id)->exists()],
        };
        if (! $exists) {
            throw new ContributionNotPublishable(PublicationFailure::TargetNotFound, "Cible {$type} {$id} introuvable (contribution {$contribution->id})");
        }

        return [$type, $id];
    }

    // ─── Doublons ───────────────────────────────────────────────────────────

    /** @param  class-string  $mediableType */
    private function ensureNewCommons(int $pageId, string $mediableType, int $mediableId): void
    {
        if ($this->commonsAttached($pageId, $mediableType, $mediableId)) {
            throw ValidationException::withMessages(['commons_url' => __('proposals.media.errors.commons_attached')]);
        }
        $pending = self::openContributions()
            ->where('payload->commons->page_id', (string) $pageId)
            ->get(['payload'])
            ->contains(fn (Contribution $c): bool => $this->sameTarget($c->payload, $mediableType, $mediableId));
        if ($pending) {
            throw ValidationException::withMessages(['commons_url' => __('proposals.media.errors.pending_duplicate')]);
        }
    }

    private function ensureNewFile(string $sha256): void
    {
        if (Media::query()->where('provider', Media::PROVIDER_UPLOAD)->where('provider_meta->sha256', $sha256)->exists()) {
            throw ValidationException::withMessages(['file' => __('proposals.media.errors.file_published')]);
        }
        if (self::openContributions()->where('payload->file->sha256', $sha256)->exists()) {
            throw ValidationException::withMessages(['file' => __('proposals.media.errors.pending_duplicate')]);
        }
    }

    /** @param  class-string  $mediableType */
    private function commonsAttached(int $pageId, string $mediableType, int $mediableId): bool
    {
        return Media::query()
            ->where('provider', Media::PROVIDER_COMMONS)
            ->where('external_id', (string) $pageId)
            ->where('mediable_type', $mediableType)
            ->where('mediable_id', $mediableId)
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  class-string  $mediableType
     */
    private function sameTarget(array $payload, string $mediableType, int $mediableId): bool
    {
        $existing = match ($payload['target_type'] ?? null) {
            'station' => [Station::class, Station::query()->where('slug', $payload['station_slug'] ?? '')->value('id')],
            'material' => [Material::class, Material::query()->where('slug', $payload['material_slug'] ?? '')->value('id')],
            'event' => [LineEvent::class, $payload['event']['id'] ?? null],
            default => [Line::class, Line::query()->where('slug', $payload['line_slug'] ?? '')->value('id')],
        };

        return $existing[0] === $mediableType && (int) $existing[1] === $mediableId;
    }

    /** @return Builder<Contribution> */
    private static function openContributions(): Builder
    {
        return Contribution::query()
            ->where('type', self::TYPE)
            ->whereIn('status', [ContributionStatus::Pending->value, ContributionStatus::Approved->value]);
    }

    // ─── Lignes de `media` ──────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $meta
     * @param  class-string  $mediableType
     * @return array<string, mixed>
     */
    private function commonsRow(Contribution $contribution, array $meta, string $mediableType, int $mediableId): array
    {
        $lat = $meta['lat'] ?? null;
        $lon = $meta['lon'] ?? null;

        return [
            'mediable_type' => $mediableType,
            'mediable_id' => $mediableId,
            'provider' => Media::PROVIDER_COMMONS,
            'external_id' => (string) $meta['page_id'],
            'type' => in_array($meta['type'] ?? null, self::mediaTypes(), true) ? $meta['type'] : MediaType::Photo->value,
            'title' => mb_substr((string) ($meta['title'] ?? $meta['file_title']), 0, 255),
            'caption' => $meta['caption'] ?? null,
            'author' => isset($meta['author']) ? mb_substr((string) $meta['author'], 0, 255) : null,
            'attribution' => $meta['attribution'] ?? null,
            'license' => isset($meta['license']) ? mb_substr((string) $meta['license'], 0, 191) : null,
            'license_url' => $meta['license_url'] ?? null,
            'rights_status' => $meta['rights_status'],
            'commercial_ok' => $meta['commercial_ok'] ?? null,
            'share_alike' => $meta['share_alike'] ?? null,
            'mime_type' => $meta['mime'] ?? null,
            'width' => $meta['width'] ?? null,
            'height' => $meta['height'] ?? null,
            'depicted_year' => $meta['depicted_year'] ?? null,
            'captured_at' => $meta['captured_at'] ?? null,
            'is_featured' => false,
            'source_url' => $meta['source_url'] ?? null,
            'thumb_url' => $meta['thumb_url'] ?? null,
            'display_url' => $meta['display_url'] ?? null,
            'location' => is_numeric($lat) && is_numeric($lon) ? self::point((float) $lat, (float) $lon) : null,
            'provider_meta' => json_encode(array_filter([
                'source' => self::PROVENANCE_PREFIX.$contribution->id,
                'contribution_id' => $contribution->id,
                'file_title' => $meta['file_title'] ?? null,
                'date_raw' => $meta['date_raw'] ?? null,
                'date_precision' => $meta['date_precision'] ?? null,
                'credit' => $meta['credit'] ?? null,
                'attribution_required' => $meta['attribution_required'] ?? null,
            ], fn ($v) => $v !== null), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * @param  array<string, mixed>  $p
     * @param  class-string  $mediableType
     * @return array<string, mixed>
     */
    private function uploadRow(Contribution $contribution, array $p, string $publicPath, string $mediableType, int $mediableId): array
    {
        $license = ContentLicense::from((string) $p['license']);
        $file = $p['file'];
        $captured = $p['captured'] ?? null;

        return [
            'mediable_type' => $mediableType,
            'mediable_id' => $mediableId,
            'provider' => Media::PROVIDER_UPLOAD,
            'external_id' => self::PROVENANCE_PREFIX.$contribution->id,
            'type' => $p['media_type'],
            // Adresses calculées par l'API depuis file_path (Storage::url), comme les autres fichiers hébergés.
            'file_path' => $publicPath,
            'thumb_url' => null,
            'display_url' => null,
            'mime_type' => $file['mime'],
            'file_size' => $file['size'],
            'width' => $file['width'],
            'height' => $file['height'],
            'title' => mb_substr((string) $p['title'], 0, 255),
            'caption' => $p['caption'] ?? null,
            'author' => mb_substr((string) $p['author'], 0, 255),
            'attribution' => $p['author'].', '.$license->label(),
            'license' => $license->label(),
            'license_url' => $license->url(),
            'rights_status' => $license->rightsStatus(),
            'commercial_ok' => true,
            'share_alike' => $license->shareAlike(),
            'captured_at' => $captured['date'] ?? null,
            'depicted_year' => $captured !== null ? (int) substr((string) $captured['date'], 0, 4) : null,
            'location' => isset($p['location']['lat'], $p['location']['lng']) ? self::point((float) $p['location']['lat'], (float) $p['location']['lng']) : null,
            'is_featured' => false,
            'provider_meta' => json_encode(array_filter([
                'source' => self::PROVENANCE_PREFIX.$contribution->id,
                'contribution_id' => $contribution->id,
                'date_precision' => $captured['precision'] ?? null,
                'place' => $p['place'] ?? null,
                'sha256' => $file['sha256'],
                'metadata_removed' => $file['removed'] ?: null,
                'attribution_required' => $license->attributionRequired(),
            ], fn ($v) => $v !== null), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /** Copie le fichier vers le disque public, puis l'efface de la réserve privée (après la transaction). */
    private function publishFile(string $private, string $public): void
    {
        try {
            $bytes = Storage::disk(self::PRIVATE_DISK)->get($private);
            if ($bytes === null || ! Storage::disk(self::PUBLIC_DISK)->put($public, $bytes)) {
                throw new RuntimeException("Publication du fichier impossible : {$private} → {$public}");
            }
            Storage::disk(self::PRIVATE_DISK)->delete($private);
        } catch (Throwable $e) {
            // Le média est enregistré : le fichier reste dans la réserve privée pour une nouvelle copie.
            report($e);
        }
    }

    private static function point(float $lat, float $lng): Expression
    {
        return DB::raw(sprintf('ST_SetSRID(ST_MakePoint(%.7F, %.7F), 4326)', $lng, $lat));
    }

    private static function isPrivatePath(string $path): bool
    {
        return str_starts_with($path, self::PRIVATE_DIR.'/') && ! str_contains($path, '..');
    }

    private static function clean(mixed $value): ?string
    {
        $text = is_string($value) ? trim((string) preg_replace('/\s+/u', ' ', $value)) : '';

        return $text === '' ? null : $text;
    }

    private static function trimmed(mixed $value): ?string
    {
        $text = is_string($value) ? trim($value) : '';

        return $text === '' ? null : $text;
    }
}
