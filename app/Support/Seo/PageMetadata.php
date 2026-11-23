<?php

namespace App\Support\Seo;

use App\Http\Resources\MediaResource;
use App\Models\Media;
use App\Services\Api\MediaQuery;
use App\Support\EventText;
use App\Support\SourceRights;
use Illuminate\Http\Request;

/**
 * Métadonnées de partage d'une page publique, calculées côté serveur d'après la page Inertia que le
 * contrôleur vient de construire (composant et propriétés) : accueil, fiches ligne, gare, matériel
 * et accident, histoires d'événements. Les autres pages gardent le `<head>` générique du gabarit.
 *
 * Les adresses absolues (canonique, Open Graph, JSON-LD) viennent de SiteOrigin, pas de l'en-tête `Host`.
 * Le navigateur n'en lit que le chemin (serverSeo.js), donc le serveur de dev et la navigation n'en dépendent pas.
 *
 * Titres, descriptions et images suivent exactement ceux que les composants Svelte posent dans le
 * navigateur (Components/SeoHead.svelte) : le serveur écrit les balises une fois pour les robots
 * qui n'exécutent pas le JavaScript, et le navigateur les reprend telles quelles au démarrage.
 */
final class PageMetadata
{
    /** Nombre d'images de la fiche ligne citées dans les données structurées (comme le navigateur). */
    private const LINE_IMAGES = 4;

    /** Image de partage de l'accueil : 1 200 x 630 px, réseau en service en orange, générée par scripts/share/build-share-image.mjs. */
    public const HOME_SHARE_IMAGE = '/share/accueil-reseau-1200x630.jpg';

    public function __construct(
        private readonly Request $request,
        private readonly MediaQuery $media,
        private readonly SourceRights $rights,
    ) {}

    /**
     * @param  array<string, mixed>  $page  page Inertia (`component`, `props`, `url`)
     */
    public function forPage(array $page): ?ShareMetadata
    {
        $props = (array) ($page['props'] ?? []);
        // Origine fixe (app.url), jamais l'en-tête Host : un cache partagé ne doit pas servir une adresse étrangère.
        $origin = SiteOrigin::get();

        return match ($page['component'] ?? null) {
            'Home' => $this->home($origin),
            'Lines/Show' => isset($props['line']['slug']) ? $this->line($origin, $props['line'], (array) ($props['stations'] ?? [])) : null,
            'Stations/Show' => isset($props['station']['slug']) ? $this->station($origin, $props['station']) : null,
            'Communes/Show' => isset($props['commune']['slug']) ? $this->commune($origin, $props['commune'], (array) ($props['stations'] ?? [])) : null,
            'OpenData' => isset($props['datasets']) ? $this->openData($origin, (array) $props['datasets'], (array) ($props['licence'] ?? [])) : null,
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

    /**
     * @param  array<string, mixed>  $line
     * @param  list<array<string, mixed>>  $stations
     */
    private function line(string $origin, array $line, array $stations): ShareMetadata
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
                StructuredData::line($origin, $line, $this->lineDescription($line), $images, $stations),
                StructuredData::breadcrumb($origin, [
                    ['name' => __('common.app_full'), 'path' => '/'],
                    ['name' => __('nav.lines'), 'path' => '/lines'],
                    ['name' => $line['name'], 'path' => '/lines/'.$line['slug']],
                ]),
            ],
        );
    }

    /**
     * Extrait de la ligne servi dans les données structurées : seulement celui de Wikipédia (CC BY-SA, source
     * déclarée par la fiche). Un texte rédigé d'après d'autres sources n'y entre pas : leurs droits ne sont
     * pas ceux d'une donnée ouverte.
     *
     * @param  array<string, mixed>  $line
     */
    private function lineDescription(array $line): ?string
    {
        return ($line['description_source']['type'] ?? null) === 'wikipedia' ? ($line['description'] ?? null) : null;
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
            // Pas d'extrait : la fiche gare ne dit pas de quelle source il vient, donc pas de droit prouvé.
            StructuredData::station($origin, $station, null, $this->stationDates($station)),
            StructuredData::breadcrumb($origin, [
                ['name' => __('common.app_full'), 'path' => '/'],
                ['name' => __('nav.stations'), 'path' => '/stations'],
                ['name' => $station['name'], 'path' => $path],
            ]),
        ]);
    }

    /**
     * Dates d'ouverture et de fermeture publiables d'une gare : relevées dans une source (ni déduites par VFF,
     * ni en intervalle) dont la licence permet la redistribution (registre des droits, `stations.fields`).
     *
     * @param  array<string, mixed>  $station
     * @return array{opened: ?string, closed: ?string}
     */
    private function stationDates(array $station): array
    {
        $provenance = (array) ($station['provenance'] ?? []);
        $date = function (string $key) use ($station, $provenance): ?string {
            $value = $station[$key.'_at'] ?? null;
            $precision = $station[$key.'_precision'] ?? null;
            $source = $provenance[$key.'_at'] ?? null;
            if (! is_string($value) || $value === '' || $precision === 'range' || ! is_string($source) || $source === ''
                || $source === 'vff:eras' || str_starts_with($source, 'inferred:')
                || ! $this->rights->allowsRedistribution($this->rights->providerFor('stations.fields', $source))) {
                return null;
            }

            return match ($precision) {
                'year' => substr($value, 0, 4),
                'month' => substr($value, 0, 7),
                default => substr($value, 0, 10),
            };
        };

        return ['opened' => $date('opened'), 'closed' => $date('closed')];
    }

    /**
     * @param  array<string, mixed>  $commune
     * @param  list<array<string, mixed>>  $stations
     */
    private function commune(string $origin, array $commune, array $stations): ShareMetadata
    {
        $title = __('communes.title', ['name' => $commune['name']]);
        $department = ($commune['department_name'] ?? null) ? "{$commune['department_name']} ({$commune['department_code']})" : (string) ($commune['department_code'] ?? '');
        $path = '/communes/'.$commune['slug'];

        return $this->build($origin, $path, $title.' - VFF', $title, __('communes.meta', ['name' => $commune['name'], 'department' => $department]), 'article', null, [
            StructuredData::commune($origin, $commune, $stations),
            StructuredData::breadcrumb($origin, [
                ['name' => __('common.app_full'), 'path' => '/'],
                ['name' => $commune['name'], 'path' => $path],
            ]),
        ]);
    }

    /**
     * Jeux de données ouverts (Pages/OpenData.svelte) : titre et description de la page, jeu de données ODbL.
     *
     * @param  list<array<string, mixed>>  $datasets
     * @param  array<string, mixed>  $licence
     */
    private function openData(string $origin, array $datasets, array $licence): ShareMetadata
    {
        $title = __('open_data.meta_title');
        $description = __('open_data.meta_description');

        return $this->build($origin, '/open-data', $title.' - VFF', $title, $description, 'website', null, [
            StructuredData::dataset($origin, $datasets, $licence, $title, $description, $this->lang()),
            StructuredData::breadcrumb($origin, [
                ['name' => __('common.app_full'), 'path' => '/'],
                ['name' => $title, 'path' => '/open-data'],
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
