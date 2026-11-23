<?php

namespace App\Services\Contributions;

use App\Enums\ContributionAction;
use App\Enums\ContributionStatus;
use App\Enums\LineEventType;
use App\Models\Contribution;
use App\Models\Line;
use App\Services\EraTimeline;
use App\Services\TraceContributionService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Contributions « événement daté » (type `line_event`) : une ouverture, une fermeture, une
 * électrification… d'une ligne existante, avec sa date à la précision que donne la source, la
 * portion concernée quand on la connaît et une source obligatoire.
 *
 * Cycle de vie (mêmes services que les autres contributions) :
 *   1. normalize() : saisie validée par rules() → charge utile de la contribution ;
 *   2. relecture par deux validateurs (ContributionReview) ;
 *   3. apply() : l'événement entre dans `line_events`, source `contrib` (contributions validées,
 *      registre des droits : VFF), empreinte `contribution:<id>` (provenance, une seule ligne
 *      même si la publication est rejouée), portion `pending`.
 *
 * Correction d'un événement existant (`corrects_event_id`, depuis la chronologie de la fiche) : la
 * charge utile garde l'événement visé tel qu'il était (source, type, date, portion) et la raison de
 * la correction. À la publication, l'événement corrigé entre comme un nouvel événement `contrib` et
 * l'événement visé est neutralisé (type `other`, description « … (correction sourcée : raison) »),
 * comme une neutralisation de database/data/line_event_corrections*.php ; vff:apply-event-corrections
 * rejoue ces neutralisations après chaque import (replayCorrections), puisque les imports réécrivent
 * leurs événements.
 *
 * Aucun recalcul du moteur ici : la portion est résolue ensuite par
 * `vff:resolve-event-sections --line=<code>`, et la carte par décennie suit au prochain passage du
 * pipeline de l'historique (tâche à part, docs/CONTRIBUTIONS.md).
 */
final readonly class EventContributions
{
    public const TYPE = 'line_event';

    /** Portion concernée : toute la ligne, entre deux lieux, entre deux PK. */
    public const PORTIONS = ['whole', 'places', 'pk'];

    /** Nature de la source : page web, document numérisé (avec sa licence), ouvrage ou archive papier. */
    public const SOURCE_KINDS = SourceCitation::KINDS;

    /** Valeurs admises par line_events (contraintes line_events_reuse_check et line_events_heritage_kind_check). */
    public const REUSES = ['greenway', 'road', 'path', 'urban', 'other'];

    public const HERITAGE_KINDS = ['tourist_train', 'velorail', 'museum'];

    /** Source des événements publiés (comme ceux des tracés contribués) et préfixe de leur empreinte. */
    public const SOURCE = 'contrib';

    public const FINGERPRINT_PREFIX = 'contribution:';

    /** Marque d'un événement neutralisé, celle des fichiers de corrections (EventText::splitCorrection). */
    public const NEUTRALIZED_MARK = 'correction sourcée :';

    /** Citation courte de la source et raison d'une correction : une ou deux phrases. */
    public const EXCERPT_MAX = 300;

    public const REASON_MIN = 10;

    public const REASON_MAX = 300;

    public function __construct(private TraceContributionService $traces) {}

    /**
     * Types proposés : tous ceux de line_events, sauf l'accident, qui a sa propre fiche vérifiée
     * (vff:import-accidents).
     *
     * @return list<string>
     */
    public static function eventTypes(): array
    {
        return array_values(array_map(
            fn (LineEventType $t): string => $t->value,
            array_filter(LineEventType::cases(), fn (LineEventType $t): bool => $t !== LineEventType::Accident),
        ));
    }

    /**
     * Règles de saisie, communes au formulaire du site et à la mutation GraphQL. Les champs d'une
     * portion ou d'une source qui ne s'appliquent pas sont écartés (exclude_unless).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public static function rules(): array
    {
        return [
            'line_slug' => ['required', 'string', 'max:191', 'exists:lines,slug'],
            'type' => ['required', 'string', Rule::in(self::eventTypes())],
            'precision' => ['required', 'string', Rule::in(TraceContributionService::PRECISIONS)],
            'date' => ['required', 'string', 'max:10'],
            'portion' => ['required', 'string', Rule::in(self::PORTIONS)],
            'from' => ['exclude_unless:portion,places', 'required', 'string', 'max:250'],
            'to' => ['exclude_unless:portion,places', 'required', 'string', 'max:250', 'different:from'],
            'pk_start_m' => ['exclude_unless:portion,pk', 'required', 'integer', 'min:0', 'max:9999999'],
            'pk_end_m' => ['exclude_unless:portion,pk', 'required', 'integer', 'min:0', 'max:9999999', 'gt:pk_start_m'],
            'reuse' => ['exclude_unless:type,conversion', 'nullable', 'string', Rule::in(self::REUSES)],
            'heritage_kind' => ['exclude_unless:type,heritage_start,heritage_end', 'nullable', 'string', Rule::in(self::HERITAGE_KINDS)],
            'description' => ['nullable', 'required_if:type,other', 'string', 'max:300'],
            ...SourceCitation::rules(),
            'source_excerpt' => ['nullable', 'string', 'max:'.self::EXCERPT_MAX],
            'corrects_event_id' => ['nullable', 'integer', 'min:1'],
            'correction_reason' => ['exclude_without:corrects_event_id', 'required', 'string', 'min:'.self::REASON_MIN, 'max:'.self::REASON_MAX],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'line_slug.required' => __('proposals.errors.line_required'),
            'line_slug.exists' => __('proposals.errors.line_required'),
            'date.required' => __('editor.errors.bad_date'),
            'date.max' => __('editor.errors.bad_date'),
            'from.required' => __('proposals.event.errors.places'),
            'to.required' => __('proposals.event.errors.places'),
            'to.different' => __('proposals.event.errors.places_same'),
            'pk_start_m.required' => __('proposals.event.errors.pk'),
            'pk_end_m.required' => __('proposals.event.errors.pk'),
            'pk_end_m.gt' => __('proposals.event.errors.pk_order'),
            'description.required_if' => __('proposals.event.errors.description_other'),
            'source_excerpt.max' => __('proposals.event.errors.excerpt_length', ['max' => self::EXCERPT_MAX]),
            'correction_reason.required' => __('proposals.event.errors.reason', ['min' => self::REASON_MIN, 'max' => self::REASON_MAX]),
            'correction_reason.min' => __('proposals.event.errors.reason', ['min' => self::REASON_MIN, 'max' => self::REASON_MAX]),
            'correction_reason.max' => __('proposals.event.errors.reason', ['min' => self::REASON_MIN, 'max' => self::REASON_MAX]),
            ...SourceCitation::messages(),
        ];
    }

    /**
     * Charge utile d'une proposition validée par rules().
     *
     * @param  array<string, mixed>  $data
     * @return array{payload: array<string, mixed>, line: Line}
     *
     * @throws ValidationException date illisible pour la précision choisie, événement corrigé introuvable
     * @throws ModelNotFoundException ligne inconnue
     */
    public function normalize(array $data): array
    {
        $line = Line::query()->where('slug', $data['line_slug'])->firstOrFail();
        $corrects = isset($data['corrects_event_id'])
            ? (self::correctable($line->id, (int) $data['corrects_event_id'])
                ?? throw ValidationException::withMessages(['corrects_event_id' => __('proposals.event.errors.corrected_missing')]))
            : null;
        $date = $this->traces->normalizeDate((string) $data['date'], (string) $data['precision'])
            ?? throw ValidationException::withMessages(['date' => __('editor.errors.bad_date')]);

        $portion = match ($data['portion']) {
            'places' => ['kind' => 'places', 'from' => self::clean($data['from']), 'to' => self::clean($data['to'])],
            'pk' => ['kind' => 'pk', 'pk_start_m' => (int) $data['pk_start_m'], 'pk_end_m' => (int) $data['pk_end_m']],
            default => ['kind' => 'whole'],
        };

        return [
            'line' => $line,
            'payload' => [
                'line_slug' => $line->slug,
                'line_code' => $line->code,
                'line_name' => $line->name,
                'event_type' => $data['type'],
                'date' => $date,
                'precision' => $data['precision'],
                'portion' => $portion,
                'reuse' => $data['reuse'] ?? null,
                'heritage_kind' => $data['heritage_kind'] ?? null,
                'description' => self::clean($data['description'] ?? null),
                'source' => SourceCitation::fromInput($data),
                'source_excerpt' => self::clean($data['source_excerpt'] ?? null),
                'corrects' => $corrects,
                'correction_reason' => $corrects !== null ? self::clean($data['correction_reason'] ?? null) : null,
                'notes' => self::trimmed($data['notes'] ?? null),
            ],
        ];
    }

    /**
     * Événement d'une ligne qu'une proposition peut corriger, tel qu'il est maintenant (charge utile
     * `corrects`) : jamais un accident (fiche vérifiée à part), ni un événement déjà neutralisé par une
     * correction (`other` « correction sourcée »).
     *
     * @return array{event_id: int, source: string, type: string, date: string, precision: string, from: ?string, to: ?string, pk_start_m: ?int, pk_end_m: ?int, source_url: ?string, description: ?string}|null
     */
    public static function correctable(int $lineId, int $eventId): ?array
    {
        $e = DB::table('line_events')->where('id', $eventId)->where('line_id', $lineId)
            ->first(['id', 'source', 'type', 'event_date', 'date_precision', 'section_from', 'section_to', 'section_pk_start_m', 'section_pk_end_m', 'source_url', 'description']);
        if ($e === null || $e->type === LineEventType::Accident->value
            || ($e->type === 'other' && str_contains((string) $e->description, self::NEUTRALIZED_MARK))) {
            return null;
        }

        return [
            'event_id' => (int) $e->id,
            'source' => (string) $e->source,
            'type' => (string) $e->type,
            'date' => substr((string) $e->event_date, 0, 10),
            'precision' => (string) $e->date_precision,
            'from' => $e->section_from,
            'to' => $e->section_to,
            'pk_start_m' => $e->section_pk_start_m !== null ? (int) $e->section_pk_start_m : null,
            'pk_end_m' => $e->section_pk_end_m !== null ? (int) $e->section_pk_end_m : null,
            'source_url' => $e->source_url,
            'description' => $e->description,
        ];
    }

    /**
     * Publie l'événement dans `line_events` (dans la transaction de l'approbation). Rejouer la
     * publication met à jour la même ligne (empreinte `contribution:<id>`), sans doublon.
     *
     * @return array{line_event_id: int, line_slug: string}
     *
     * @throws ContributionNotPublishable la ligne a disparu (fusion, suppression)
     */
    public function apply(Contribution $contribution): array
    {
        $p = $contribution->payload;
        $line = Line::query()->find($contribution->target_id)
            ?? throw new ContributionNotPublishable(PublicationFailure::TargetNotFound, "Ligne {$contribution->target_id} introuvable (contribution {$contribution->id})");
        $portion = $p['portion'] ?? ['kind' => 'whole'];
        $fingerprint = self::fingerprint($contribution->id);

        $row = [
            'line_id' => $line->id,
            'type' => $p['event_type'],
            'event_date' => $p['date'],
            'date_precision' => $p['precision'],
            'description' => self::description($p, $contribution->id),
            'source' => self::SOURCE,
            'source_url' => $p['source']['url'] ?? null,
            // Citation courte de la source quand le contributeur l'a donnée, sinon sa référence (titre, licence).
            'source_quote' => $p['source_excerpt'] ?? SourceCitation::reference($p['source'] ?? []),
            // La confiance suit la source citée (ouvrage, archive ou site officiel : haute ; page web secondaire : moyenne).
            'confidence' => EraTimeline::citedConfidence((string) ($p['source']['kind'] ?? 'web'), $p['source']['url'] ?? null),
            'section_from' => $portion['from'] ?? null,
            'section_to' => $portion['to'] ?? null,
            'section_pk_start_m' => $portion['pk_start_m'] ?? null,
            'section_pk_end_m' => $portion['pk_end_m'] ?? null,
            // Portion à résoudre par vff:resolve-event-sections (tâche à part) : rien n'est recalculé ici.
            'section_resolution' => 'pending',
            'section_geom' => null,
            'section_length_m' => null,
            'reuse' => $p['reuse'] ?? null,
            'heritage_kind' => $p['heritage_kind'] ?? null,
            'updated_at' => now(),
        ];

        $id = DB::table('line_events')->where('fingerprint', $fingerprint)->value('id');
        if ($id === null) {
            $id = DB::table('line_events')->insertGetId($row + ['fingerprint' => $fingerprint, 'created_at' => now()]);
        } else {
            DB::table('line_events')->where('id', $id)->update($row);
        }
        $neutralized = $this->neutralizeCorrected($contribution, (int) $id);

        return ['line_event_id' => (int) $id, 'line_slug' => $line->slug]
            + (isset($p['corrects']) ? ['corrected_event_ids' => $neutralized] : []);
    }

    /**
     * Neutralise l'événement qu'une correction remplace, comme une neutralisation des fichiers de
     * corrections sourcées : type `other`, description « <fait>, fait corrigé par la contribution n° <id>
     * (correction sourcée : <raison>) ». L'événement est retrouvé par son identifiant s'il n'a pas
     * changé, sinon (import rejoué : nouvelle ligne) par sa ligne, sa source, son type et sa date, et
     * son départ quand il en avait un. Rien à faire pour une proposition qui ne corrige rien.
     *
     * @param  int|null  $publishedId  événement publié par la contribution (jamais neutralisé)
     * @return list<int> événements neutralisés ou déjà neutralisés
     */
    public function neutralizeCorrected(Contribution $contribution, ?int $publishedId = null, bool $dryRun = false): array
    {
        $c = $contribution->payload['corrects'] ?? null;
        if (! is_array($c)) {
            return [];
        }
        $publishedId ??= DB::table('line_events')->where('fingerprint', self::fingerprint($contribution->id))->value('id');
        // Le fait visé tel que la source le donne, ou déjà neutralisé par CETTE contribution ; jamais un autre fait
        // `other` du même jour (fait neutre de la source, neutralisation d'un fichier de corrections ou d'une autre contribution).
        $mine = '%, fait corrigé par la contribution n° '.$contribution->id.' ('.self::NEUTRALIZED_MARK.'%';
        $base = DB::table('line_events')->where('line_id', $contribution->target_id)
            ->where('source', $c['source'])->where('event_date', $c['date'])
            ->where(fn ($q) => $q->where('type', $c['type'])->orWhere(fn ($o) => $o->where('type', 'other')->where('description', 'like', $mine)))
            ->when($publishedId !== null, fn ($q) => $q->where('id', '<>', $publishedId));
        $ids = (clone $base)->where('id', $c['event_id'])->pluck('id');
        if ($ids->isEmpty()) {
            $ids = (clone $base)->when($c['from'] ?? null, fn ($q, $from) => $q->where('section_from', $from))->pluck('id');
        }
        if ($ids->isEmpty()) {
            return [];
        }
        $label = __('editor.event_types.'.$c['type'], [], 'fr');
        $description = Str::limit("{$label}, fait corrigé par la contribution n° {$contribution->id}", 600, '…')
            .' ('.self::NEUTRALIZED_MARK.' '.($contribution->payload['correction_reason'] ?? '').')';
        if (! $dryRun) {
            DB::table('line_events')->whereIn('id', $ids)
                ->where(fn ($q) => $q->where('type', '<>', 'other')->orWhere('description', '<>', $description)->orWhereNull('description'))
                ->update(['type' => 'other', 'description' => $description, 'updated_at' => now()]);
        }

        return $ids->map(fn ($id): int => (int) $id)->values()->all();
    }

    /**
     * Rejoue les neutralisations des corrections publiées (vff:apply-event-corrections, après les
     * fichiers) : un import qui réécrit ses événements ne fait pas revenir un fait corrigé.
     *
     * @return array{replayed: int, neutralized: int, missing: list<int>} missing : contributions dont l'événement visé a disparu
     */
    public function replayCorrections(?int $lineId = null, bool $dryRun = false): array
    {
        $out = ['replayed' => 0, 'neutralized' => 0, 'missing' => []];
        Contribution::query()->where('type', self::TYPE)->where('status', ContributionStatus::Applied->value)
            ->where('action', ContributionAction::Update->value)
            ->when($lineId !== null, fn ($q) => $q->where('target_id', $lineId))
            ->orderBy('id')
            ->each(function (Contribution $contribution) use (&$out, $dryRun): void {
                $ids = $this->neutralizeCorrected($contribution, null, $dryRun);
                $out['replayed']++;
                $out['neutralized'] += count($ids);
                if ($ids === []) {
                    $out['missing'][] = $contribution->id;
                }
            });

        return $out;
    }

    /**
     * Événements déjà connus de la même famille sur la ligne (ouvertures, fermetures,
     * réouvertures…), du plus proche au plus lointain de la date proposée : les validateurs voient
     * tout de suite un doublon ou une contradiction.
     *
     * @return list<array{id: int, type: string, date: string, precision: string, source: ?string, source_url: ?string, from: ?string, to: ?string, pk_start_m: ?int, pk_end_m: ?int, description: string}>
     */
    public function similarEvents(Contribution $contribution, int $limit = 6): array
    {
        $p = $contribution->payload;
        $family = self::family((string) ($p['event_type'] ?? ''));
        $types = array_values(array_filter(self::eventTypes(), fn (string $t): bool => self::family($t) === $family));
        $fingerprint = self::fingerprint($contribution->id);

        return DB::table('line_events')
            ->where('line_id', $contribution->target_id)
            ->whereIn('type', $types)
            ->where(fn ($q) => $q->whereNull('fingerprint')->orWhere('fingerprint', '<>', $fingerprint))
            ->orderByRaw('ABS(event_date - CAST(? AS date))', [$p['date'] ?? '1900-01-01'])
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'type', 'event_date', 'date_precision', 'source', 'source_url', 'section_from', 'section_to', 'section_pk_start_m', 'section_pk_end_m', 'description'])
            ->map(fn (object $e): array => [
                'id' => (int) $e->id,
                'type' => (string) $e->type,
                'date' => substr((string) $e->event_date, 0, 10),
                'precision' => (string) $e->date_precision,
                'source' => $e->source,
                'source_url' => $e->source_url,
                'from' => $e->section_from,
                'to' => $e->section_to,
                'pk_start_m' => $e->section_pk_start_m !== null ? (int) $e->section_pk_start_m : null,
                'pk_end_m' => $e->section_pk_end_m !== null ? (int) $e->section_pk_end_m : null,
                'description' => (string) $e->description,
            ])
            ->values()
            ->all();
    }

    /** Empreinte (provenance) de l'événement publié par une contribution. */
    public static function fingerprint(int $contributionId): string
    {
        return self::FINGERPRINT_PREFIX.$contributionId;
    }

    /** Famille d'un type : les variantes voyageurs et fret d'une ouverture restent des ouvertures. */
    private static function family(string $type): string
    {
        return match (true) {
            str_starts_with($type, 'reopening') => 'reopening',
            str_starts_with($type, 'opening') => 'opening',
            str_starts_with($type, 'closure') => 'closure',
            str_starts_with($type, 'heritage') => 'heritage',
            str_starts_with($type, 'concession') => 'concession',
            default => $type,
        };
    }

    /**
     * Description publiée (en français, comme les autres événements de la base) : le type, la
     * portion, la précision du contributeur, et le numéro de la contribution (avec la référence de
     * la source quand une citation courte la remplace dans source_quote).
     *
     * @param  array<string, mixed>  $p
     */
    private static function description(array $p, int $contributionId): string
    {
        $label = __('editor.event_types.'.$p['event_type'], [], 'fr');
        $portion = $p['portion'] ?? [];
        $where = match ($portion['kind'] ?? 'whole') {
            'places' => (preg_match('/^[aeiouyéèêâîôh]/iu', (string) $portion['from']) === 1 ? " d'" : ' de ')
                .$portion['from'].' à '.$portion['to'],
            'pk' => ' du PK '.self::pk((int) $portion['pk_start_m']).' au PK '.self::pk((int) $portion['pk_end_m']),
            default => '',
        };
        $detail = ($p['description'] ?? null) ? ' : '.$p['description'] : '';
        // La citation courte prend la place de la référence dans source_quote : la référence reste ici.
        $reference = ($p['source_excerpt'] ?? null) !== null ? SourceCitation::reference($p['source'] ?? []) : null;

        return Str::limit($label.$where.$detail, 900, '…')." (contribution n° {$contributionId}"
            .($reference !== null ? ', source : '.Str::limit($reference, 300, '…') : '').')';
    }

    /** PK en kilomètres et mètres : 12345 → « 12+345 ». */
    private static function pk(int $meters): string
    {
        return sprintf('%d+%03d', intdiv($meters, 1000), $meters % 1000);
    }

    private static function clean(mixed $value): ?string
    {
        return SourceCitation::clean($value);
    }

    private static function trimmed(mixed $value): ?string
    {
        return SourceCitation::trimmed($value);
    }
}
