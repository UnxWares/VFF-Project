<?php

namespace App\GraphQL\Resolvers;

use App\Models\Line;
use App\Models\LineArchiveDocument;
use App\Models\LineEvent;
use App\Models\Media;
use App\Services\Stories\EventStories;

/**
 * Champ `LineEvent.story` : histoire de l'événement (App\Services\Stories\EventStories), mêmes règles et
 * même contenu que `GET /api/v1/lines/{line}/stories/{event}`. Les images et les documents sont rendus en
 * objets `Media` et `LineArchiveDocument` complets (droits, liens), chacun avec son écart en années.
 * Calculé événement par événement (trois requêtes) : à demander pour un événement, pas pour une longue liste.
 */
final class LineEventStoryField
{
    public function __construct(private readonly EventStories $stories) {}

    /**
     * @return array{window_years: int, window_from: int, window_to: int, images_total: int,
     *     images: list<array{gap_years: ?int, attached: bool, media: Media}>,
     *     documents: list<array{gap_years: int, document: LineArchiveDocument}>,
     *     stations: list<array{name: string, slug: string}>}|null
     */
    public function __invoke(LineEvent $root): ?array
    {
        $line = $root->relationLoaded('line') ? $root->line : Line::query()->find($root->line_id);
        $story = $line === null ? null : $this->stories->story($line, $root);
        if ($story === null) {
            return null;
        }
        $media = Media::query()->whereIn('id', array_column($story['images'], 'id'))->get()->keyBy('id');
        $documents = LineArchiveDocument::query()->where('line_id', $root->line_id)
            ->whereIn('gallica_ark', array_column($story['documents'], 'gallica_ark'))->get()->keyBy('gallica_ark');

        $images = [];
        foreach ($story['images'] as $i) {
            if (($m = $media->get($i['id'])) instanceof Media) {
                $images[] = ['gap_years' => $i['gap_years'], 'attached' => $i['attached'], 'media' => $m];
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
