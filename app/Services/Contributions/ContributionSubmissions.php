<?php

namespace App\Services\Contributions;

use App\Enums\ContributionAction;
use App\Enums\ContributionStatus;
use App\Enums\SegmentStatus;
use App\Enums\TrafficType;
use App\Models\Contribution;
use App\Models\Era;
use App\Models\Line;
use App\Models\Material;
use App\Models\User;
use App\Services\Notifications\ContributionNotifier;
use App\Services\TraceContributionService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Enregistrement d'une contribution (statut « pending ») et annonce aux validateurs, commun au site
 * (formulaire de modification par PK, éditeur de tracé) et à l'API GraphQL (`submitContribution`).
 *
 * Les types :
 *   - `line_segment` : changement d'attributs d'une portion de ligne entre deux PK, à une ère ;
 *   - `line_trace` (TraceContributionService::TYPE) : tracé dessiné, ligne disparue ou correction ;
 *   - `line_event` (EventContributions::TYPE) : événement daté d'une ligne existante ;
 *   - `media` (MediaContributions::TYPE) : image de Commons ou fichier déposé, rattaché à une ligne,
 *     une gare ou un événement ;
 *   - `line_material` (LineMaterialContributions::TYPE) : une série de matériel a circulé sur une ligne ;
 *   - `material_series` (MaterialSeriesContributions::TYPE) : nouvelle série ou correction d'une série.
 *
 * Les données arrivent déjà validées par les règles de ce service (segmentRules) ou de
 * TraceContributionService, EventContributions, MediaContributions, LineMaterialContributions et
 * MaterialSeriesContributions (rules()). Les contrôles
 * d'accès (compte connecté, non suspendu) restent à l'appelant : middleware `auth` et
 * `not.suspended` sur le site, ContributionApiAccess dans l'API.
 */
final readonly class ContributionSubmissions
{
    /** Attributs d'une portion qu'une modification par PK peut changer (les autres clés sont ignorées). */
    public const SEGMENT_FIELDS = ['status', 'traffic_type', 'electrified', 'voltage_v', 'max_speed_kmh', 'is_heritage'];

    public function __construct(
        private TraceContributionService $traces,
        private EventContributions $events,
        private MediaContributions $media,
        private LineMaterialContributions $lineMaterials,
        private MaterialSeriesContributions $series,
        private ContributionNotifier $notifier,
    ) {}

    /**
     * Règles d'une modification par PK. Comme toute contribution sourcée, elle cite au moins une
     * source (adresse ou référence) : les validateurs ne relisent pas une affirmation sans pièce.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public static function segmentRules(): array
    {
        return [
            'line_code' => 'required|string',
            'era_year' => 'required|integer',
            'pk_start_m' => 'required|integer|min:0|max:9999999',
            'pk_end_m' => 'required|integer|min:0|max:9999999|gt:pk_start_m',
            'changes' => 'required|array',
            // Valeurs des énumérations : avant, une valeur libre passait la validation
            // puis faisait échouer l'application (CHECK SQL) au moment de l'approbation,
            // avec l'erreur SQL brute affichée au validateur.
            'changes.status' => ['nullable', 'string', Rule::enum(SegmentStatus::class)],
            'changes.traffic_type' => ['nullable', 'string', Rule::enum(TrafficType::class)],
            'changes.electrified' => 'nullable|boolean',
            'changes.voltage_v' => 'nullable|integer|min:0|max:50000',
            'changes.max_speed_kmh' => 'nullable|integer|min:0|max:600',
            'changes.is_heritage' => 'nullable|boolean',
            // Au moins une source non vide (les chaînes vides arrivent à null : « required » les refuse).
            'sources' => ['required', 'array', 'min:1'],
            'sources.*' => ['required', 'string', 'max:500'],
            'notes' => 'nullable|string|max:2000',
        ];
    }

    /** @return array<string, string> */
    public static function segmentMessages(): array
    {
        return [
            'sources.required' => __('editor.segment.errors.source_required'),
            'sources.min' => __('editor.segment.errors.source_required'),
            'sources.*.required' => __('editor.segment.errors.source_required'),
        ];
    }

    /**
     * Enregistre une modification par PK validée par segmentRules().
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException aucun changement réel (champs vides ou clés inconnues seules)
     * @throws ModelNotFoundException ligne ou ère inconnue
     */
    public function submitSegment(User $author, array $data): Contribution
    {
        // Le navigateur bloque déjà un envoi vide ; ici le serveur le refuse aussi (champs laissés
        // sur « Inchangé » ou vides, ou clés inconnues seules) : rien à relire pour les validateurs.
        $changes = array_filter(
            array_intersect_key($data['changes'] ?? [], array_flip(self::SEGMENT_FIELDS)),
            fn ($v) => $v !== null,
        );
        if ($changes === []) {
            throw ValidationException::withMessages(['changes' => __('editor.segment.errors.no_change')]);
        }

        $line = Line::query()
            ->whereRaw("REGEXP_REPLACE(code, '\\s+', '', 'g') = ?", [preg_replace('/\s+/', '', (string) $data['line_code'])])
            ->firstOrFail();
        $era = Era::where('year', $data['era_year'])->firstOrFail();

        $contribution = Contribution::create([
            'user_id' => $author->id,
            'type' => 'line_segment',
            'action' => ContributionAction::Update->value,
            'target_type' => Line::class,
            'target_id' => $line->id,
            'status' => ContributionStatus::Pending->value,
            'payload' => [
                'line_code' => $line->code,
                'line_name' => $line->name,
                'era_year' => $era->year,
                'pk_start_m' => (int) $data['pk_start_m'],
                'pk_end_m' => (int) $data['pk_end_m'],
                'changes' => $changes,
                'sources' => $data['sources'] ?? [],
                'notes' => $data['notes'] ?? null,
            ],
        ]);
        $this->notifier->submitted($contribution);

        return $contribution;
    }

    /**
     * Enregistre un tracé validé par TraceContributionService::rules().
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException portion trop courte ou date illisible (TraceContributionService::normalize)
     */
    public function submitTrace(User $author, array $data): Contribution
    {
        ['payload' => $payload, 'line' => $line] = $this->traces->normalize($data);

        $contribution = Contribution::create([
            'user_id' => $author->id,
            'type' => TraceContributionService::TYPE,
            'action' => $line ? ContributionAction::Update->value : ContributionAction::Create->value,
            'target_type' => $line ? Line::class : null,
            'target_id' => $line?->id,
            'status' => ContributionStatus::Pending->value,
            'payload' => $payload,
        ]);
        $this->notifier->submitted($contribution);

        return $contribution;
    }

    /**
     * Enregistre un événement daté validé par EventContributions::rules().
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException date illisible pour la précision choisie
     * @throws ModelNotFoundException ligne inconnue
     */
    public function submitEvent(User $author, array $data): Contribution
    {
        ['payload' => $payload, 'line' => $line] = $this->events->normalize($data);

        $contribution = Contribution::create([
            'user_id' => $author->id,
            'type' => EventContributions::TYPE,
            'action' => ContributionAction::Create->value,
            'target_type' => Line::class,
            'target_id' => $line->id,
            'status' => ContributionStatus::Pending->value,
            'payload' => $payload,
        ]);
        $this->notifier->submitted($contribution);

        return $contribution;
    }

    /**
     * Enregistre un média validé par MediaContributions::rules(). Un fichier déposé, déjà nettoyé
     * de ses métadonnées, va dans la réserve privée avant l'enregistrement de la contribution ; il
     * est effacé si celui-ci échoue.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException fichier refusé, adresse Commons refusée, doublon
     * @throws ModelNotFoundException ligne ou gare inconnue
     */
    public function submitMedia(User $author, array $data): Contribution
    {
        ['payload' => $payload, 'target' => $target, 'image' => $image] = $this->media->prepare($data);
        $path = $image !== null ? $this->media->storePrivate($image) : null;
        if ($path !== null) {
            $payload['file']['path'] = $path;
        }

        try {
            $contribution = Contribution::create([
                'user_id' => $author->id,
                'type' => MediaContributions::TYPE,
                'action' => ContributionAction::Create->value,
                'target_type' => $target::class,
                'target_id' => $target->id,
                'status' => ContributionStatus::Pending->value,
                'payload' => $payload,
            ]);
        } catch (Throwable $e) {
            if ($path !== null) {
                $this->media->deletePrivate($path);
            }
            throw $e;
        }
        $this->notifier->submitted($contribution);

        return $contribution;
    }

    /**
     * Enregistre un lien « matériel sur une ligne » validé par LineMaterialContributions::rules().
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException date illisible, doublon, lien incompatible sans phrase qui le justifie
     * @throws ModelNotFoundException ligne ou série inconnue
     */
    public function submitLineMaterial(User $author, array $data): Contribution
    {
        ['payload' => $payload, 'line' => $line] = $this->lineMaterials->normalize($data);

        $contribution = Contribution::create([
            'user_id' => $author->id,
            'type' => LineMaterialContributions::TYPE,
            'action' => ContributionAction::Create->value,
            'target_type' => Line::class,
            'target_id' => $line->id,
            'status' => ContributionStatus::Pending->value,
            'payload' => $payload,
        ]);
        $this->notifier->submitted($contribution);

        return $contribution;
    }

    /**
     * Enregistre une nouvelle série ou la correction d'une série, validée par
     * MaterialSeriesContributions::rules(). Une nouvelle série n'a pas de cible avant sa publication.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException identifiant Wikidata inconnu ou déjà pris, doublon, rien à corriger
     * @throws ModelNotFoundException série à corriger inconnue
     */
    public function submitMaterialSeries(User $author, array $data): Contribution
    {
        ['payload' => $payload, 'material' => $material] = $this->series->normalize($data);

        $contribution = Contribution::create([
            'user_id' => $author->id,
            'type' => MaterialSeriesContributions::TYPE,
            'action' => $material !== null ? ContributionAction::Update->value : ContributionAction::Create->value,
            'target_type' => $material !== null ? Material::class : null,
            'target_id' => $material?->id,
            'status' => ContributionStatus::Pending->value,
            'payload' => $payload,
        ]);
        $this->notifier->submitted($contribution);

        return $contribution;
    }
}
