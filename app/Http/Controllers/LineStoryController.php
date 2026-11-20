<?php

namespace App\Http\Controllers;

use App\Models\Line;
use App\Models\LineEvent;
use App\Services\Archives\LineArchives;
use App\Services\Stories\EventStories;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Histoire d'un événement d'une ligne (page `/lines/{slug}/stories/{event}`, adresse partageable) :
 * l'événement avec ses sources, un carrousel des images de la ligne datées à quelques années près et les
 * documents de Gallica de la période (App\Services\Stories\EventStories). API : `GET
 * /api/v1/lines/{line}/stories/{event}`. 404 pour un événement d'une autre ligne ou sans histoire (contenu
 * sous le seuil) : jamais de page vide. Un fait rapporté par plusieurs sources n'a qu'une histoire : l'adresse
 * d'une autre source que la plus sûre redirige (301) vers celle de l'histoire.
 */
class LineStoryController extends Controller
{
    public function show(Line $line, int $event, EventStories $stories): Response|RedirectResponse
    {
        $model = LineEvent::query()->whereKey($event)->where('line_id', $line->id)->first();
        $story = $model === null ? null : $stories->story($line, $model);
        abort_if($story === null, 404);
        if ($story['event']['id'] !== $model->id) {
            return redirect()->route('lines.stories.show', ['line' => $line->slug, 'event' => $story['event']['id']], 301);
        }

        return Inertia::render('Lines/Story', [
            'story' => $story + [
                'text' => $stories->text($model, $story['stations'], app()->getLocale()),
                'attribution' => $story['documents'] === [] ? null : LineArchives::ATTRIBUTION,
            ],
        ]);
    }
}
