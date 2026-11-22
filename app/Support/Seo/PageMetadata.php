<?php

namespace App\Support\Seo;

use App\Http\Resources\MediaResource;
use App\Models\Media;
use App\Services\Api\MediaQuery;
use App\Support\EventText;
use Illuminate\Http\Request;

/**
 * Métadonnées de partage d'une page publique, calculées côté serveur d'après la page Inertia que le
 * contrôleur vient de construire (composant et propriétés) : accueil, fiches ligne, gare, matériel
 * et accident, histoires d'événements. Les autres pages gardent le `<head>` générique du gabarit.
 *
 * Titres, descriptions et images suivent exactement ceux que les composants Svelte posent dans le
 * navigateur (Components/SeoHead.svelte) : le serveur écrit les balises une fois pour les robots
 * qui n'exécutent pas le JavaScript, et le navigateur les reprend telles quelles au démarrage.
 */
final class PageMetadata
{
    /** Nombre d'images de la fiche ligne citées dans les données structurées (comme le navigateur). */
    private const LINE_IMAGES = 4;

    /** Image de partage de l'accueil : 1 200 x 630 px, logo sur la carte de 1865 (domaine public) de l'accueil. */
    public const HOME_SHARE_IMAGE = '/share/accueil-1200x630.jpg';

    public function __construct(
        private readonly Request $request,
        private readonly MediaQuery $media,
    ) {}

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
            'Lines/Story' => isset($props['story']['line']['slug'], $props['story']['event']['id']) ? $this->story($origin, $props['story']) : null,
            'Journal/Index' => $this->journalIndex($origin, (int) ($props['pagination']['current'] ?? 1)),
            'Journal/Show' => isset($props['article']['slug'], $props['article']['json_ld']) ? $this->journalArticle($origin, $props['article']) : null,
            default => null,
        };
    }

    /** Liste du journal de bord (Pages/Journal/Index.svelte) : page 2 et suivantes ont leur propre adresse. */
    private function journalIndex(string $origin, int $page): ShareMetadata
    {
        $title = __('journal.meta_title');

        return $this->build($origin, $page > 1 ? '/journal?page='.$page : '/journal', $title.' - VFF', $title,
            __('journal.meta_description'), 'website', null, [
                StructuredData::breadcrumb($origin, [
                    ['name' => __('common.app_full'), 'path' => '/'],
                    ['name' => __('journal.index.title'), 'path' => '/journal'],
                ]),
            ]);
    }

    /**
     * Article du journal (Pages/Journal/Show.svelte) : données structurées Article déjà calculées par
     * le contrôleur (JournalPresenter::jsonLd), photo de l'article.
     *
     * @param  array<string, mixed>  $article
     */
    private function journalArticle(string $origin, array $article): ShareMetadata
    {
        $path = '/journal/'.$article['slug'];
        $image = is_array($article['image'] ?? null) ? ['url' => (string) $article['image']['src'], 'alt' => $article['image']['alt'] ?? null] : null;

        return $this->build($origin, $path, $article['title'].' - '.__('journal.meta_title').' - VFF', (string) $article['title'],
            (string) $article['summary'], 'article', $image, [
                (array) $article['json_ld'],
                StructuredData::breadcrumb($origin, [
                    ['name' => __('common.app_full'), 'path' => '/'],
                    ['name' => __('journal.index.title'), 'path' => '/journal'],
                    ['name' => (string) $article['title'], 'path' => $path],
                ]),
            ]);
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
            'url' => $origin.self::HOME_SHARE_IMAGE,
            'alt' => __('home.share_image_alt'),
        ], [StructuredData::website($origin, __('common.app_full'), 'VFF', $description, $this->lang())]);
    }

    /** @param  array<string, mixed>  $line */
    private function line(string $origin, array $line): ShareMetadata
    {
        $description = ($line['description'] ?? null)
            ? mb_substr((string) $line['description'], 0, 300)
            : __('lines.meta_description', ['name' => $line['name']]);
        $images = isset($line['id']) ? $this->lineImages((int) $line['id']) : [];
        // Première image affichable : un média sans adresse d'image (document sans aperçu) est sauté.
        $first = null;
        foreach ($images as $media) {
            $url = StructuredData::imageUrl($media);
            if ($url !== null) {
                $first = ['url' => $url, 'alt' => $media['caption'] ?? null];
                break;
            }
        }

        return $this->build($origin, '/lines/'.$line['slug'], $line['name'].' - VFF', $line['name'], $description, 'article',
            $first,
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

    /**
     * Histoire d'un événement de ligne (Pages/Lines/Story.svelte) : même titre que la page (« Ouverture de
     * Vernon-Giverny à Port-Villez, 3 mai 1843 »), première image du carrousel.
     *
     * @param  array<string, mixed>  $story
     */
    private function story(string $origin, array $story): ShareMetadata
    {
        $line = (array) $story['line'];
        $event = (array) $story['event'];
        $type = (string) ($event['type'] ?? '');
        $label = __('stories.titles.'.$type);
        $section = $story['text']['section_text'] ?? null;
        $title = $section ? __('stories.title_section', ['event' => $label, 'section' => $section]) : $label;
        $date = EventText::isoDate((string) ($event['date'] ?? ''), $event['date_precision'] ?? 'day', $this->lang());
        $path = '/lines/'.$line['slug'].'/stories/'.$event['id'];
        $first = $story['images'][0] ?? null;
        $url = is_array($first) ? StructuredData::imageUrl($first) : null;

        return $this->build($origin, $path, $title.', '.$date.' - '.$line['name'].' - VFF', $title.', '.$date,
            __('stories.meta_description', ['event' => $title, 'date' => $date, 'line' => $line['name']]), 'article',
            $url === null ? null : ['url' => $url, 'alt' => $first['caption'] ?? null],
            [StructuredData::breadcrumb($origin, [
                ['name' => __('common.app_full'), 'path' => '/'],
                ['name' => __('nav.lines'), 'path' => '/lines'],
                ['name' => $line['name'], 'path' => '/lines/'.$line['slug']],
                ['name' => $title, 'path' => $path],
            ])],
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
        $imageUrl = is_array($image) ? StructuredData::imageUrl($image) : null;

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
     * Premiers médias de la ligne, dans l'ordre de la galerie (MediaQuery::galleryOrder, époque
     * représentée la plus ancienne d'abord), mis en forme comme l'API des médias.
     *
     * @return list<array<string, mixed>>
     */
    private function lineImages(int $lineId): array
    {
        return $this->media->galleryOrder($this->media->forLine($lineId))
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
