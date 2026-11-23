<?php

namespace App\Http\Controllers;

use App\Enums\ContentLicense;
use App\Http\Requests\SubmitEventContributionRequest;
use App\Models\Line;
use App\Services\Contributions\ContributionSubmissions;
use App\Services\Contributions\EventContributions;
use App\Services\TraceContributionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Formulaire « Proposer un événement daté » de l'espace Contribuer : un événement d'une ligne
 * existante, sourcé, publié dans `line_events` après deux approbations (EventContributions).
 */
class EventContributionController extends Controller
{
    /**
     * Formulaire, avec la ligne présélectionnée depuis sa fiche (`?line=<slug>`) et, depuis la
     * chronologie de la fiche, l'événement à corriger (`&event=<id>`, un événement de cette ligne) :
     * ses valeurs actuelles préremplissent le formulaire. Un événement d'une autre ligne, un accident
     * ou un fait déjà neutralisé ouvrent le formulaire d'un nouvel événement.
     */
    public function create(Request $request): Response
    {
        $line = $request->filled('line')
            ? Line::query()->where('slug', (string) $request->query('line'))->first(['id', 'slug', 'code', 'name'])
            : null;
        $eventId = filter_var($request->query('event'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $corrected = $line !== null && $eventId !== false ? EventContributions::correctable($line->id, $eventId) : null;

        return Inertia::render('Contribute/ProposeEvent', [
            'preselectedLine' => $line?->only(['slug', 'code', 'name']),
            'correctedEvent' => $corrected,
            'excerptMax' => EventContributions::EXCERPT_MAX,
            'reasonLength' => [EventContributions::REASON_MIN, EventContributions::REASON_MAX],
            'eventTypes' => EventContributions::eventTypes(),
            'precisions' => TraceContributionService::PRECISIONS,
            'sourceKinds' => EventContributions::SOURCE_KINDS,
            'licenses' => array_map(fn (ContentLicense $l): string => $l->value, ContentLicense::cases()),
            'reuses' => EventContributions::REUSES,
            'heritageKinds' => EventContributions::HERITAGE_KINDS,
        ]);
    }

    public function store(SubmitEventContributionRequest $request, ContributionSubmissions $submissions): RedirectResponse
    {
        $contribution = $submissions->submitEvent($request->user(), $request->validated());

        return redirect()->route('contribute.thanks', ['id' => $contribution->id]);
    }
}
