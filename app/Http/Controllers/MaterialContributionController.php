<?php

namespace App\Http\Controllers;

use App\Enums\ContentLicense;
use App\Enums\MaterialType;
use App\Http\Requests\SubmitLineMaterialContributionRequest;
use App\Http\Requests\SubmitMaterialSeriesContributionRequest;
use App\Models\Line;
use App\Models\Material;
use App\Services\Contributions\ContributionSubmissions;
use App\Services\Contributions\LineMaterialContributions;
use App\Services\Contributions\MaterialSeriesContributions;
use App\Services\Contributions\SourceCitation;
use App\Services\TraceContributionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Formulaires « Proposer un matériel sur une ligne » et « Proposer une série » (nouvelle série ou
 * correction) de l'espace Contribuer, et leurs contrôles avant l'envoi : compatibilité d'une série
 * avec une ligne (écartement, électrification), vérification d'un identifiant Wikidata.
 */
class MaterialContributionController extends Controller
{
    /** Formulaire du lien, avec la ligne (`?line=<slug>`) ou la série (`?material=<slug>`) présélectionnée. */
    public function createLink(Request $request): Response
    {
        $line = $request->filled('line')
            ? Line::query()->where('slug', (string) $request->query('line'))->first(['slug', 'code', 'name'])
            : null;

        return Inertia::render('Contribute/ProposeLineMaterial', [
            'preselectedLine' => $line?->only(['slug', 'code', 'name']),
            'preselectedMaterial' => self::materialSummary($request),
            'circulations' => array_keys(LineMaterialContributions::CIRCULATIONS),
            'precisions' => TraceContributionService::PRECISIONS,
            'sourceKinds' => SourceCitation::KINDS,
            'licenses' => self::licenses(),
            'quoteLimits' => [LineMaterialContributions::QUOTE_MIN, LineMaterialContributions::QUOTE_MAX],
        ]);
    }

    public function storeLink(SubmitLineMaterialContributionRequest $request, ContributionSubmissions $submissions): RedirectResponse
    {
        $contribution = $submissions->submitLineMaterial($request->user(), $request->validated());

        return redirect()->route('contribute.thanks', ['id' => $contribution->id]);
    }

    /**
     * Contrôle d'un couple ligne × série avant l'envoi : conflit avec la règle d'écartement ou
     * d'électrification (message traduit), lien déjà connu, exclusion relue.
     */
    public function checkLink(Request $request, LineMaterialContributions $links): JsonResponse
    {
        $data = $request->validate([
            'line' => ['required', 'string', 'max:191'],
            'material' => ['required', 'string', 'max:191'],
        ]);
        $line = Line::query()->where('slug', $data['line'])->firstOrFail();
        $material = Material::query()->where('slug', $data['material'])->firstOrFail();

        return response()->json($links->check($line, $material));
    }

    /** Formulaire d'une série : nouvelle, ou correction de la série `?material=<slug>` (valeurs actuelles). */
    public function createSeries(Request $request): Response
    {
        $material = $request->filled('material')
            ? Material::query()->where('slug', (string) $request->query('material'))->first()
            : null;

        return Inertia::render('Contribute/ProposeSeries', [
            'material' => $material !== null ? [
                'slug' => $material->slug,
                'name' => $material->name,
                'type' => $material->getRawOriginal('type'),
                'builder' => $material->getRawOriginal('builder'),
                'in_service_from' => $material->in_service_from,
                'in_service_to' => $material->in_service_to,
                'gauge_mm' => $material->gauge_mm,
                'electrification' => MaterialSeriesContributions::systemKeys($material->electrification ?? []),
                'wikidata_id' => $material->wikidata_id,
            ] : null,
            'types' => array_map(fn (MaterialType $t): string => $t->value, MaterialType::cases()),
            'systems' => array_keys(MaterialSeriesContributions::ELECTRIFICATION),
            'gauges' => array_keys(MaterialSeriesContributions::GAUGES),
            'sourceKinds' => SourceCitation::KINDS,
            'licenses' => self::licenses(),
            'minYear' => MaterialSeriesContributions::MIN_YEAR,
        ]);
    }

    public function storeSeries(SubmitMaterialSeriesContributionRequest $request, ContributionSubmissions $submissions): RedirectResponse
    {
        $contribution = $submissions->submitMaterialSeries($request->user(), $request->validated());

        return redirect()->route('contribute.thanks', ['id' => $contribution->id]);
    }

    /**
     * Vérification d'un identifiant Wikidata avant l'envoi : l'élément (identifiant final, libellé,
     * description) et la série de VFF qui le porte déjà, ou une erreur 422 sur `qid`.
     */
    public function wikidata(Request $request, MaterialSeriesContributions $series): JsonResponse
    {
        $qid = (string) $request->validate(['qid' => ['required', 'string', 'max:120']])['qid'];

        return response()->json($series->lookup($qid));
    }

    /** @return array{slug: string, name: string, type: string}|null série présélectionnée (`?material=<slug>`) */
    private static function materialSummary(Request $request): ?array
    {
        $material = $request->filled('material')
            ? Material::query()->where('slug', (string) $request->query('material'))->first(['slug', 'name', 'type'])
            : null;

        return $material !== null ? ['slug' => $material->slug, 'name' => $material->name, 'type' => $material->getRawOriginal('type')] : null;
    }

    /** @return list<string> licences d'un document cité */
    private static function licenses(): array
    {
        return array_map(fn (ContentLicense $l): string => $l->value, ContentLicense::cases());
    }
}
