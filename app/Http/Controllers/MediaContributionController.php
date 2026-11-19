<?php

namespace App\Http\Controllers;

use App\Enums\ContentLicense;
use App\Http\Requests\SubmitMediaContributionRequest;
use App\Models\Contribution;
use App\Models\Line;
use App\Models\LineEvent;
use App\Models\Station;
use App\Services\Contributions\ContributionSubmissions;
use App\Services\Contributions\MediaContributions;
use App\Services\TraceContributionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Formulaire « Proposer une image » de l'espace Contribuer (fichier Wikimedia Commons ou fichier
 * déposé), aperçu d'un fichier Commons avant l'envoi, et lecture privée d'un fichier déposé en
 * attente de relecture (MediaContributions).
 */
class MediaContributionController extends Controller
{
    /**
     * Formulaire, avec la fiche présélectionnée : `?line=<slug>`, `?station=<slug>` ou
     * `?event=<id>` (l'événement et sa ligne).
     */
    public function create(Request $request): Response
    {
        $event = $request->filled('event') && ctype_digit((string) $request->query('event'))
            ? LineEvent::query()->with('line:id,slug,code,name')->find((int) $request->query('event'))
            : null;
        $line = $event->line ?? ($request->filled('line')
            ? Line::query()->where('slug', (string) $request->query('line'))->first(['slug', 'code', 'name'])
            : null);
        $station = $request->filled('station')
            ? Station::query()->where('slug', (string) $request->query('station'))->first(['slug', 'name', 'city'])
            : null;

        return Inertia::render('Contribute/ProposeMedia', [
            'preselected' => [
                'line' => $line?->only(['slug', 'code', 'name']),
                'station' => $station?->only(['slug', 'name', 'city']),
                'eventId' => $event?->id,
            ],
            'mediaTypes' => MediaContributions::mediaTypes(),
            'licenses' => array_map(fn (ContentLicense $l): array => [
                'value' => $l->value,
                'name' => $l->label(),
                'url' => $l->url(),
            ], ContentLicense::forMedia()),
            'precisions' => TraceContributionService::PRECISIONS,
            'limits' => [
                'max_bytes' => MediaContributions::MAX_KILOBYTES * 1024,
                'min_side' => MediaContributions::MIN_SIDE,
                'max_side' => MediaContributions::MAX_SIDE,
            ],
        ]);
    }

    public function store(SubmitMediaContributionRequest $request, ContributionSubmissions $submissions): RedirectResponse
    {
        $contribution = $submissions->submitMedia($request->user(), $request->validated());

        return redirect()->route('contribute.thanks', ['id' => $contribution->id]);
    }

    /**
     * Aperçu d'un fichier Commons avant l'envoi : titre, auteur, licence, miniature, ou le motif
     * du refus (erreur de validation 422 sur `url`).
     */
    public function commons(Request $request, MediaContributions $media): JsonResponse
    {
        $url = (string) $request->validate(['url' => ['required', 'string', 'max:500']])['url'];

        return response()->json(['file' => MediaContributions::commonsSummary($media->commonsFile($url, 'url'))]);
    }

    /**
     * Fichier déposé, en attente de relecture : jamais public, montré seulement à son auteur et
     * aux validateurs. Servi tel qu'il a été nettoyé, sans cache partagé, sans interprétation
     * possible par le navigateur (type imposé, bac à sable).
     */
    public function file(Request $request, Contribution $contribution, MediaContributions $media): StreamedResponse
    {
        Gate::authorize('viewPendingFile', $contribution);
        $file = $media->pendingFile($contribution);
        abort_if($file === null, 404);

        return Storage::disk(MediaContributions::PRIVATE_DISK)->response($file['path'], null, [
            'Content-Type' => $file['mime'],
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}
