<?php

namespace App\GraphQL\Resolvers;

use App\Models\Line;
use App\Models\LineArchiveDocument;
use App\Models\LineEvent;
use App\Models\Media;
use App\Services\Stories\EventStories;
use GraphQL\Error\Error;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use WeakMap;

/**
 * Champ `LineEvent.story` : histoire de l'événement (App\Services\Stories\EventStories), mêmes règles et
 * même contenu que `GET /api/v1/lines/{line}/stories/{event}`. Les images et les documents sont rendus en
 * objets `Media` et `LineArchiveDocument` complets (droits, liens), chacun avec son écart en années.
 *
 * Une histoire coûte une dizaine de requêtes SQL. Le champ n'est donc calculé que pour un événement demandé
 * seul (`lineEvent(id)`, ou `event` d'un média demandé seul), jamais dans une liste (`line.events`,
 * `lineEvents`, `media.data`…), et pour MAX_PER_REQUEST événements au plus par requête GraphQL (alias
 * compris). Au-delà, le champ vaut null avec une erreur explicite : sans cela, une seule requête sur
 * toutes les pages de `lines` lancerait des milliers de requêtes SQL. Une liste d'histoires s'obtient par
 * `GET /api/v1/lines/{line}/stories`, calculée en une requête.
 */
final class LineEventStoryField
{
    /** Histoires calculées au plus par requête GraphQL. */
    public const MAX_PER_REQUEST = 3;

    /** @var WeakMap<GraphQLContext, int>|null histoires déjà demandées, par requête (contexte Lighthouse) */
    private static ?WeakMap $served = null;

    public function __construct(private readonly EventStories $stories) {}

    /**
     * @return array{window_years: int, window_from: int, window_to: int, images_total: int,
     *     images: list<array{gap_years: ?int, gap_years_min: ?int, gap_years_max: ?int, gap_exact: bool, attached: bool, media: Media}>,
     *     documents: list<array{gap_years: int, document: LineArchiveDocument}>,
     *     stations: list<array{name: string, slug: string}>}|null
     */
    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke(LineEvent $root, array $args, GraphQLContext $context, ResolveInfo $info): ?array
    {
        // Un indice numérique dans le chemin (`events.3.story`) : l'événement est un élément de liste.
        if (array_filter($info->path, 'is_int') !== []) {
            throw new Error('LineEvent.story is only resolved for an event requested alone (lineEvent(id: …)), not inside a list. Use GET /api/v1/lines/{line}/stories to list the stories of a line.');
        }
        self::$served ??= new WeakMap;
        $count = (self::$served[$context] ?? 0) + 1;
        self::$served[$context] = $count;
        if ($count > self::MAX_PER_REQUEST) {
            throw new Error(sprintf('LineEvent.story is resolved for %d events at most per request.', self::MAX_PER_REQUEST));
        }

        $line = $root->relationLoaded('line') ? $root->line : Line::query()->find($root->line_id);
        // API : sans les images montrées avec une simple autorisation (EventStories::API_RIGHTS).
        $story = $line === null ? null : $this->stories->story($line, $root, true);
        if ($story === null) {
            return null;
        }
        $media = Media::query()->whereIn('id', array_column($story['images'], 'id'))->get()->keyBy('id');
        $documents = LineArchiveDocument::query()->where('line_id', $root->line_id)
            ->whereIn('gallica_ark', array_column($story['documents'], 'gallica_ark'))->get()->keyBy('gallica_ark');

        $images = [];
        foreach ($story['images'] as $i) {
            if (($m = $media->get($i['id'])) instanceof Media) {
                $images[] = ['gap_years' => $i['gap_years'], 'gap_years_min' => $i['gap_years_min'], 'gap_years_max' => $i['gap_years_max'],
                    'gap_exact' => $i['gap_exact'], 'attached' => $i['attached'], 'media' => $m];
            }
        }
        $docs = [];
        foreach ($story['documents'] as $d) {
            if (($doc = $documents->get($d['gallica_ark'])) instanceof LineArchiveDocument) {
                $docs[] = ['gap_years' => $d['gap_years'], 'document' => $doc];
            }
        }

        return [
            'window_years' => $story['window']['years'],
            'window_from' => $story['window']['from'],
            'window_to' => $story['window']['to'],
            'images_total' => $story['images_total'],
            'images' => $images,
            'documents' => $docs,
            'stations' => $story['stations'],
        ];
    }
}
