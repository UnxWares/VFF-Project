<?php

namespace App\Support\Seo;

use App\Http\Resources\MediaResource;
use App\Models\Line;
use App\Models\Media;
use Illuminate\Http\Request;

/**
 * Métadonnées de partage d'une page publique, calculées côté serveur d'après la page Inertia que le
 * contrôleur vient de construire (composant et propriétés) : accueil, fiches ligne, gare, matériel
 * et accident. Les autres pages gardent le `<head>` générique du gabarit.
 *
 * Titres, descriptions et images suivent exactement ceux que les composants Svelte posent dans le
 * navigateur (Components/SeoHead.svelte) : le serveur écrit les balises une fois pour les robots
 * qui n'exécutent pas le JavaScript, et le navigateur les reprend telles quelles au démarrage.
 */
final class PageMetadata
{
    /** Nombre d'images de la fiche ligne citées dans les données structurées (comme le navigateur). */
    private const LINE_IMAGES = 4;

    public function __construct(private readonly Request $request) {}

    /**
     * @param  array<string, mixed>  $page  page Inertia (`component`, `props`, `url`)
     */
    public function forPage(array $page): ?ShareMetadata
    {
        $props = (array) ($page['props'] ?? []);
        $origin = $this->request->getSchemeAndHttpHost();

        return match ($page['component'] ?? null) {
            'Home' => $this->home($origin),
            'Lines/Show' => isset($props['line']['slug']) ? $this->line($origin, $props['line']) : null,
            'Stations/Show' => isset($props['station']['slug']) ? $this->station($origin, $props['station']) : null,
            'Materials/Show' => isset($props['material']['slug']) ? $this->material($origin, $props['material']) : null,
            'Accidents/Show' => isset($props['accident']['slug']) ? $this->accident($origin, $props['accident']) : null,
            default => null,
        };
    }

    private function lang(): string
    {
        return app()->getLocale();
    }

    private function home(string $origin): ShareMetadata
    {
        $title = __('home.meta_title');
        $description = __('home.meta_description');

        return $this->build($origin, '/', $title, $title, $description, 'website', [
            'url' => $origin.'/icons/icon-512.png',
            'alt' => __('nav.logo_alt'),
        ], [StructuredData::website($origin, __('common.app_full'), 'VFF', $description, $this->lang())]);
    }

    /** @param  array<string, mixed>  $line */
    private function line(string $origin, array $line): ShareMetadata
    {
        $description = ($line['description'] ?? null)
            ? mb_substr((string) $line['description'], 0, 300)
            : __('lines.meta_description', ['name' => $line['name']]);
        $images = $this->lineImages($line['slug']);
        $first = $images[0] ?? null;

        return $this->build($origin, '/lines/'.$line['slug'], $line['name'].' - VFF', $line['name'], $description, 'article',
            $first === null ? null : ['url' => $first['display_url'] ?? $first['thumb_url'], 'alt' => $first['caption'] ?? null],
            [
                StructuredData::line($origin, $line, $line['description'] ?? null, $images),
                StructuredData::breadcrumb($origin, [
                    ['name' => __('common.app_full'), 'path' => '/'],
                    ['name' => __('nav.lines'), 'path' => '/lines'],
                    ['name' => $line['name'], 'path' => '/lines/'.$line['slug']],
                ]),
            ],
        );
    }

    /** @param  array<string, mixed>  $station */
    private function station(string $origin, array $station): ShareMetadata
    {
        $description = __('stations.meta_show', ['name' => $station['name']]);
        $path = '/stations/'.$station['slug'];

        return $this->build($origin, $path, $station['name'].' - VFF', $station['name'], $description, 'article', null, [
            StructuredData::station($origin, $station, $station['description'] ?? null),
            StructuredData::breadcrumb($origin, [
                ['name' => __('common.app_full'), 'path' => '/'],
                ['name' => __('nav.stations'), 'path' => '/stations'],
                ['name' => $station['name'], 'path' => $path],
            ]),
        ]);
    }

    /** @param  array<string, mixed>  $material */
    private function material(string $origin, array $material): ShareMetadata
    {
        $description = __('materials.meta_show', ['name' => $material['name']]);
        $path = '/materials/'.$material['slug'];
        $image = $material['image'] ?? null;
        $imageUrl = is_array($image) ? ($image['display_url'] ?? $image['thumb_url'] ?? null) : null;

        return $this->build($origin, $path, $material['name'].' - VFF', $material['name'], $description, 'article',
            $imageUrl === null ? null : ['url' => $imageUrl, 'alt' => $material['name']],
            [
                StructuredData::material($origin, $material, $material['description'] ?? null),
                StructuredData::breadcrumb($origin, [
                    ['name' => __('common.app_full'), 'path' => '/'],
                    ['name' => __('nav.materials'), 'path' => '/materials'],
                    ['name' => $material['name'], 'path' => $path],
                ]),
            ],
        );
    }

    /** @param  array<string, mixed>  $accident */
    private function accident(string $origin, array $accident): ShareMetadata
    {
        $lang = $this->lang();
        $name = (string) ($accident['name'][$lang] ?? $accident['name']['fr'] ?? '');
        $path = '/accidents/'.$accident['slug'];
        $description = __('accidents.meta_show', ['name' => $name]);

        return $this->build($origin, $path, $name.' - VFF', $name, $description, 'article', null, [
            StructuredData::accident($origin, $accident, $lang, $this->placeText($accident['place'] ?? [])),
            StructuredData::breadcrumb($origin, [
                ['name' => __('common.app_full'), 'path' => '/'],
                ['name' => __('nav.accidents'), 'path' => '/accidents'],
                ['name' => $name, 'path' => $path],
            ]),
        ]);
    }

    /**
     * « Brétigny-sur-Orge (Essonne) », la commune seule ou le département seul (comme la fiche).
     *
     * @param  array<string, mixed>  $place
     */
    private function placeText(array $place): ?string
    {
        $commune = $place['commune'] ?? null;
        $department = $place['department'] ?? null;
        if ($commune) {
            return $department && $department !== $commune ? "{$commune} ({$department})" : $commune;
        }

        return $department ?: null;
    }

    /**
     * Premières images Commons de la ligne, dans l'ordre de la galerie (époque représentée, la plus
     * ancienne d'abord), mises en forme comme l'API des médias.
     *
     * @return list<array<string, mixed>>
     */
    private function lineImages(string $slug): array
    {
        $lineId = Line::query()->where('slug', $slug)->value('id');
        if ($lineId === null) {
            return [];
        }

        return Media::query()
            ->where('mediable_type', Line::class)
            ->where('mediable_id', $lineId)
            ->orderByRaw('depicted_year ASC NULLS LAST')
            ->orderBy('id')
            ->limit(self::LINE_IMAGES)
            ->get()
            ->map(fn (Media $m): array => MediaResource::make($m)->resolve($this->request))
            ->all();
    }

    /**
     * @param  'website'|'article'  $type
     * @param  array{url: string, alt: ?string}|null  $image
     * @param  list<array<string, mixed>>  $jsonLd
     */
    private function build(string $origin, string $path, string $documentTitle, string $title, string $description, string $type, ?array $image, array $jsonLd): ShareMetadata
    {
        return new ShareMetadata(
            documentTitle: $documentTitle,
            title: $title,
            metaDescription: $description,
            description: (string) StructuredData::shorten($description, 200),
            url: StructuredData::absoluteUrl($origin, $path),
            type: $type,
            locale: StructuredData::ogLocale($this->lang()),
            siteName: __('common.app_full'),
            image: $image === null ? null : ['url' => StructuredData::absoluteUrl($origin, $image['url']), 'alt' => $image['alt']],
            jsonLd: array_map(StructuredData::serialize(...), $jsonLd),
        );
    }
}
