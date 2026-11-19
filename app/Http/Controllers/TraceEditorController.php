<?php

namespace App\Http\Controllers;

use App\Enums\ContributionAction;
use App\Enums\ContributionStatus;
use App\Models\Contribution;
use App\Models\Era;
use App\Models\Line;
use App\Services\Notifications\ContributionNotifier;
use App\Services\TraceContributionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Éditeur de tracé collaboratif : dessiner une ligne disparue ou corriger une
 * portion existante sur fond de carte historique IGN, avec événements datés
 * par portion. Les propositions passent par la modération (Validate/Index)
 * avant d'être écrites dans source_traces / line_events.
 */
class TraceEditorController extends Controller
{
    public function __construct(private readonly TraceContributionService $traces) {}

    /** GET /contribute/trace[?line=slug] - page de l'éditeur. */
    public function create(Request $request): Response
    {
        $slug = $request->query('line');
        $line = $slug ? Line::where('slug', $slug)->first(['id', 'slug', 'name', 'code', 'network']) : null;

        return Inertia::render('Contribute/Trace', [
            'line' => $line ? [
                'id' => $line->id,
                'slug' => $line->slug,
                'name' => $line->name,
                'code' => $line->code,
                'network' => $line->network,
            ] : null,
            'currentEra' => (int) (Era::where('is_current', true)->value('year') ?? Era::max('year') ?? 2030),
            'networks' => TraceContributionService::NETWORKS,
            'eventTypes' => TraceContributionService::eventTypes(),
            'precisions' => TraceContributionService::PRECISIONS,
            'limits' => [
                'portions' => TraceContributionService::MAX_PORTIONS,
                'points' => TraceContributionService::MAX_POINTS,
            ],
        ]);
    }

    /** GET /contribute/trace/reference?bbox=minLng,minLat,maxLng,maxLat&zoom=z - données d'accrochage. */
    public function reference(Request $request): JsonResponse
    {
        $data = $request->validate([
            'bbox' => ['required', 'string', 'regex:/^-?\d+(\.\d+)?(,-?\d+(\.\d+)?){3}$/'],
            'zoom' => 'required|numeric|min:0|max:24',
            // Filtre facultatif des sources de tracés (ex. « archeoferroviaire,ohm ») ; vide = aucune.
            'sources' => ['nullable', 'string', 'max:200', 'regex:/^[a-z0-9_,-]*$/'],
            'segments' => 'nullable|boolean',
        ]);
        $sources = $request->has('sources')
            ? array_values(array_filter(explode(',', (string) ($data['sources'] ?? ''))))
            : null;
        $bbox = array_map('floatval', explode(',', $data['bbox']));
        [$minx, $miny, $maxx, $maxy] = $bbox;
        $zoom = (float) $data['zoom'];

        // Garde-fou : pas de requête « toute la France » (l'éditeur charge à zoom ≥ 10).
        if ($zoom < 9.5 || $maxx <= $minx || $maxy <= $miny || ($maxx - $minx) > 3 || ($maxy - $miny) > 2) {
            return response()->json(['type' => 'FeatureCollection', 'features' => [], 'too_large' => true]);
        }

        return response()->json($this->traces->referenceFeatures($bbox, $zoom, $sources, $request->boolean('segments', true)));
    }

    /** GET /contribute/trace/lines/{line:slug} - géométrie actuelle d'une ligne (affichage « avant »). */
    public function line(Line $line): JsonResponse
    {
        return response()->json([
            'line' => [
                'id' => $line->id,
                'slug' => $line->slug,
                'name' => $line->name,
                'code' => $line->code,
                'network' => $line->network,
            ],
            ...$this->traces->lineGeometry($line->id),
        ]);
    }

    /** GET /contribute/trace/feature/{kind}/{id} - géométrie complète à reprendre comme portion. */
    public function feature(string $kind, int $id): JsonResponse
    {
        $parts = $this->traces->featureParts($kind, $id);
        abort_if($parts === null, 404);

        return response()->json($parts);
    }

    /** POST /contribute/trace - enregistre la proposition (statut pending). */
    public function store(Request $request, ContributionNotifier $notifier): RedirectResponse
    {
        $data = $request->validate(TraceContributionService::rules(), TraceContributionService::messages());
        ['payload' => $payload, 'line' => $line] = $this->traces->normalize($data);

        $contribution = Contribution::create([
            'user_id' => Auth::id(),
            'type' => TraceContributionService::TYPE,
            'action' => $line ? ContributionAction::Update->value : ContributionAction::Create->value,
            'target_type' => $line ? Line::class : null,
            'target_id' => $line?->id,
            'status' => ContributionStatus::Pending->value,
            'payload' => $payload,
        ]);
        $notifier->submitted($contribution);

        return redirect()->route('contribute.thanks', ['id' => $contribution->id]);
    }
}
