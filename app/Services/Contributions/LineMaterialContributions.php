<?php

namespace App\Services\Contributions;

use App\Enums\ContributionStatus;
use App\Models\Contribution;
use App\Models\Line;
use App\Models\LineMaterial;
use App\Models\LineMaterialPeriod;
use App\Models\Material;
use App\Services\Materials\LineMaterialExclusions;
use App\Services\Materials\MaterialGaugeRule;
use App\Services\Materials\MaterialTractionRule;
use App\Services\TraceContributionService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Contributions « matériel sur une ligne » (type `line_material`) : une série de matériel a circulé
 * sur une ligne, d'après une source citée, avec sa période de service quand la source la date.
 *
 * Cycle de vie (mêmes services que les autres contributions) :
 *   1. normalize() : saisie validée par rules(), puis règles d'écartement (MaterialGaugeRule) et
 *      d'électrification (MaterialTractionRule), les mêmes que l'import : un lien incompatible est
 *      refusé, sauf si le contributeur cite la phrase de sa source qui nomme l'écartement ou la tension
 *      de la série (comme la preuve d'un lien importé). L'exception est alors gardée dans la charge
 *      utile (`exception`) et signalée en évidence aux validateurs ;
 *   2. relecture par deux validateurs (ContributionReview) ;
 *   3. apply() : le lien entre dans `line_materials` avec la source `contrib` (un lien par couple
 *      ligne × série pour toutes les contributions, à côté des liens importés que l'import gère seul) et
 *      une preuve `kind` = `contribution` (provenance `contribution:<id>`) ; la période, s'il y en a
 *      une, entre dans `line_material_periods` (empreinte `contribution:<id>`). Aucun import n'est
 *      relancé.
 *
 * Fiabilité, comme pour les liens importés (LineMaterialLinker::attributes) : `high` pour une preuve
 * sans doute (service régulier, source relue par deux validateurs), `low` quand la circulation déclarée
 * est exceptionnelle, un essai ou un train de musée (famille de doute `one_off`, `trial`,
 * `preserved`). La période suit : `high` au jour ou au mois, `medium` à l'année, `low` (jamais montrée)
 * pour une circulation douteuse, comme MaterialPeriodCollector.
 */
final readonly class LineMaterialContributions
{
    public const TYPE = 'line_material';

    /** Source des liens et des périodes publiés (registre des droits : VFF). */
    public const SOURCE = 'contrib';

    /** Nature de la preuve d'un lien publié (`line_materials.evidence[].kind`). */
    public const EVIDENCE_KIND = 'contribution';

    /** Origine d'une période publiée (`line_material_periods.origin`). */
    public const PERIOD_ORIGIN = 'contribution';

    public const FINGERPRINT_PREFIX = 'contribution:';

    /**
     * Nature de la circulation déclarée → famille de doute de la preuve (WikipediaMaterialSections) :
     * service régulier (aucun doute), circulation exceptionnelle, essais, train de musée ou touristique.
     */
    public const CIRCULATIONS = [
        'regular' => null,
        'occasional' => 'one_off',
        'trial' => 'trial',
        'heritage' => 'preserved',
    ];

    /** Longueur de la phrase citée, en caractères. */
    public const QUOTE_MIN = 10;

    public const QUOTE_MAX = 600;

    public function __construct(private TraceContributionService $traces) {}

    /**
     * Règles de saisie, communes au formulaire du site et à la mutation GraphQL. La période est
     * facultative ; une date sans sa précision (ou l'inverse) est refusée.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public static function rules(): array
    {
        $precisions = Rule::in(TraceContributionService::PRECISIONS);

        return [
            'line_slug' => ['required', 'string', 'max:191', 'exists:lines,slug'],
            'material_slug' => ['required', 'string', 'max:191', 'exists:materials,slug'],
            'circulation' => ['required', 'string', Rule::in(array_keys(self::CIRCULATIONS))],
            'start_precision' => ['nullable', 'required_with:start_date', 'string', $precisions],
            'start_date' => ['nullable', 'required_with:start_precision', 'string', 'max:10'],
            'end_precision' => ['nullable', 'required_with:end_date', 'string', $precisions],
            'end_date' => ['nullable', 'required_with:end_precision', 'string', 'max:10'],
            'section_from' => ['nullable', 'required_with:section_to', 'string', 'max:250'],
            'section_to' => ['nullable', 'required_with:section_from', 'string', 'max:250', 'different:section_from'],
            ...SourceCitation::rules(),
            'quote' => ['nullable', 'string', 'min:'.self::QUOTE_MIN, 'max:'.self::QUOTE_MAX],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'line_slug.required' => __('proposals.errors.line_required'),
            'line_slug.exists' => __('proposals.errors.line_required'),
            'material_slug.required' => __('proposals.errors.material_required'),
            'material_slug.exists' => __('proposals.errors.material_required'),
            'start_date.required_with' => __('editor.errors.bad_date'),
            'end_date.required_with' => __('editor.errors.bad_date'),
            'section_from.required_with' => __('proposals.event.errors.places'),
            'section_to.required_with' => __('proposals.event.errors.places'),
            'section_to.different' => __('proposals.event.errors.places_same'),
            'quote.min' => __('proposals.line_material.errors.quote_length', ['min' => self::QUOTE_MIN, 'max' => self::QUOTE_MAX]),
            'quote.max' => __('proposals.line_material.errors.quote_length', ['min' => self::QUOTE_MIN, 'max' => self::QUOTE_MAX]),
            ...SourceCitation::messages(),
        ];
    }

    /**
     * Charge utile d'une proposition validée par rules(), après les règles d'écartement et
     * d'électrification.
     *
     * @param  array<string, mixed>  $data
     * @return array{payload: array<string, mixed>, line: Line}
     *
     * @throws ValidationException date illisible, fin avant le début, doublon, lien incompatible sans preuve
     * @throws ModelNotFoundException ligne ou série inconnue
     */
    public function normalize(array $data): array
    {
        $line = Line::query()->where('slug', $data['line_slug'])->firstOrFail();
        $material = Material::query()->where('slug', $data['material_slug'])->firstOrFail();
        $period = $this->period($data);
        $circulation = (string) $data['circulation'];
        $quote = SourceCitation::clean($data['quote'] ?? null);

        $existing = self::existingLink($line->id, $material->id);
        $this->refuseDuplicates($line, $material, $period, $existing);

        $exception = null;
        $raw = $this->compatibility($line, $material);
        if ($raw !== null) {
            $proof = [['quote' => $quote, 'doubt' => self::CIRCULATIONS[$circulation]]];
            if ($quote === null || $this->compatibility($line, $material, $proof) !== null) {
                throw ValidationException::withMessages(['quote' => self::conflictMessage($raw, $material, $quote !== null, $circulation)]);
            }
            $exception = ['rule' => $raw['rule'], 'reason' => $raw['reason']];
        }

        return [
            'line' => $line,
            'payload' => [
                'line_slug' => $line->slug,
                'line_code' => $line->code,
                'line_name' => $line->name,
                'material_id' => $material->id,
                'material_slug' => $material->slug,
                'material_name' => $material->name,
                'material_type' => $material->getRawOriginal('type'),
                'circulation' => $circulation,
                'period' => $period,
                'source' => SourceCitation::fromInput($data),
                'quote' => $quote,
                'exception' => $exception,
                'excluded' => $this->exclusion($line->id, $material->id),
                'existing_link' => $existing,
                'notes' => SourceCitation::trimmed($data['notes'] ?? null),
            ],
        ];
    }

    /**
     * Conflit avec la règle d'écartement, puis avec celle d'électrification, ou null si le lien est
     * compatible (ou non jugé) : `rule` (`gauge` ou `traction`), `reason` (motif en français, comme dans
     * le bilan de l'import et vff:check-invariants) et les chiffres de la règle.
     *
     * @param  list<array<string, mixed>>  $evidence  preuves qui lèvent le conflit (phrase citée, doute)
     * @return array{rule: string, reason: string, details: array<string, mixed>}|null
     */
    public function compatibility(Line $line, Material $material, array $evidence = []): ?array
    {
        $gauges = MaterialGaugeRule::fromDatabase(lineId: $line->id);
        $details = $gauges->details($line->id, $material->id, $evidence);
        if ($details !== null) {
            return ['rule' => 'gauge', 'reason' => (string) $gauges->conflict($line->id, $material->id, $evidence), 'details' => $details];
        }
        $traction = MaterialTractionRule::fromDatabase(lineId: $line->id);
        $details = $traction->details($line->id, $material->id, $evidence);
        if ($details !== null) {
            return ['rule' => 'traction', 'reason' => (string) $traction->conflict($line->id, $material->id, $evidence), 'details' => $details];
        }

        return null;
    }

    /**
     * Ce que le formulaire montre dès que la ligne et la série sont choisies : le conflit avec une
     * règle (message traduit, qui demande la phrase de la source), le lien déjà connu et l'exclusion
     * relue du couple.
     *
     * @return array{conflict: array{rule: string, message: string}|null, existing: array<string, mixed>|null, excluded: ?string}
     */
    public function check(Line $line, Material $material): array
    {
        $raw = $this->compatibility($line, $material);

        return [
            'conflict' => $raw !== null ? ['rule' => $raw['rule'], 'message' => self::conflictMessage($raw, $material, false, 'regular')] : null,
            'existing' => self::existingLink($line->id, $material->id),
            'excluded' => $this->exclusion($line->id, $material->id),
        ];
    }

    /**
     * Publie le lien et sa période (dans la transaction de l'approbation). Rejouer la publication ne
     * duplique rien : la preuve et la période portent la provenance `contribution:<id>`.
     *
     * @return array{line_material_id: int, period_id: ?int, line_slug: string, material_slug: string}
     *
     * @throws ContributionNotPublishable ligne ou série disparue, lien devenu incompatible
     */
    public function apply(Contribution $contribution): array
    {
        $p = $contribution->payload;
        $line = Line::query()->find($contribution->target_id)
            ?? throw new ContributionNotPublishable(PublicationFailure::TargetNotFound, "Ligne {$contribution->target_id} introuvable (contribution {$contribution->id})");
        $material = Material::query()->find($p['material_id'] ?? null)
            ?? throw new ContributionNotPublishable(PublicationFailure::TargetNotFound, "Série introuvable (contribution {$contribution->id})");

        $evidence = self::evidence($contribution);
        // Les tronçons ont pu changer depuis l'envoi : la règle est relue avec la preuve citée.
        $conflict = $this->compatibility($line, $material, [$evidence]);
        if ($conflict !== null) {
            throw new ContributionNotPublishable(PublicationFailure::Incompatible, "Lien {$line->code} × {$material->name} incompatible : {$conflict['reason']} (contribution {$contribution->id})");
        }

        $link = LineMaterial::query()->firstOrNew(['line_id' => $line->id, 'material_id' => $material->id, 'source' => self::SOURCE]);
        $kept = array_values(array_filter($link->evidence ?? [], fn (array $e): bool => ($e['contribution'] ?? null) !== $evidence['contribution']));
        $all = [...$kept, $evidence];
        $sure = array_values(array_filter($all, fn (array $e): bool => ! is_string($e['doubt'] ?? null)));
        $link->fill([
            'evidence' => $all,
            'confidence' => $sure !== [] ? 'high' : 'low',
            // Permalien de la preuve la plus forte : une preuve sans doute d'abord, la plus récente.
            'source_url' => (($sure !== [] ? end($sure) : end($all))['url'] ?? null) ?? $link->source_url,
            'fetched_at' => now(),
        ])->save();

        $periodId = null;
        if (is_array($p['period'] ?? null)) {
            $periodId = $this->writePeriod($contribution, $link, $p);
        }

        return ['line_material_id' => (int) $link->id, 'period_id' => $periodId, 'line_slug' => $line->slug, 'material_slug' => $material->slug];
    }

    /**
     * Contexte de la relecture : liens déjà connus du couple (toutes sources, avec leurs périodes
     * montrées), conflit relu maintenant avec la preuve citée.
     *
     * @return array{links: list<array<string, mixed>>, conflict_now: ?string}
     */
    public function reviewContext(Contribution $contribution): array
    {
        $p = $contribution->payload;
        $links = LineMaterial::query()
            ->with('shownPeriods')
            ->where('line_id', $contribution->target_id)
            ->where('material_id', $p['material_id'] ?? 0)
            ->orderBy('id')
            ->get()
            ->map(fn (LineMaterial $l): array => [
                'id' => (int) $l->id,
                'source' => $l->source,
                'confidence' => $l->confidence,
                'source_url' => $l->source_url,
                'quote' => $l->evidence[0]['quote'] ?? null,
                'periods' => $l->shownPeriods->map(fn (LineMaterialPeriod $period): array => $period->toSheet())->values()->all(),
            ])
            ->values()
            ->all();
        $line = Line::query()->find($contribution->target_id);
        $material = Material::query()->find($p['material_id'] ?? null);
        $conflict = $line !== null && $material !== null ? $this->compatibility($line, $material, [self::evidence($contribution)]) : null;

        return ['links' => $links, 'conflict_now' => $conflict['reason'] ?? null];
    }

    /** Empreinte (provenance) de la preuve et de la période publiées par une contribution. */
    public static function fingerprint(int $contributionId): string
    {
        return self::FINGERPRINT_PREFIX.$contributionId;
    }

    /**
     * Message traduit d'un conflit, pour le formulaire : ce que dit la règle, puis ce qu'il faut pour
     * proposer le lien quand même (ou pourquoi la phrase citée ne suffit pas).
     *
     * @param  array{rule: string, reason: string, details: array<string, mixed>}  $conflict
     */
    public static function conflictMessage(array $conflict, Material $material, bool $quoted, string $circulation): string
    {
        $d = $conflict['details'];
        if ($conflict['rule'] === 'gauge') {
            $mm = fn (int $width): string => self::number($width).' mm';
            $what = __('proposals.line_material.conflict.gauge', [
                'series' => $material->name,
                'series_mm' => $mm((int) $d['series_mm']),
                'line_mm' => $mm((int) $d['line_mm']),
                'share' => (int) $d['share'],
            ]);
        } else {
            $volts = implode(', ', array_map(fn (int $v): string => self::volts($v), $d['volts']));
            $what = __($d['partial'] ? 'proposals.line_material.conflict.traction_partial' : 'proposals.line_material.conflict.traction_never', [
                'series' => $material->name,
                'volts' => $volts,
                'share' => max(1, (int) $d['share']),
            ]);
        }
        $next = match (true) {
            ! $quoted => 'proposals.line_material.conflict.ask_quote_'.$conflict['rule'],
            $conflict['rule'] === 'traction' && self::CIRCULATIONS[$circulation] !== null => 'proposals.line_material.conflict.doubtful',
            default => 'proposals.line_material.conflict.quote_not_enough_'.$conflict['rule'],
        };

        return $what.' '.__($next);
    }

    /**
     * Lien déjà connu pour ce couple (le plus sûr d'abord), ou null.
     *
     * @return array{id: int, source: ?string, confidence: ?string, source_url: ?string}|null
     */
    private static function existingLink(int $lineId, int $materialId): ?array
    {
        $link = LineMaterial::query()
            ->where('line_id', $lineId)
            ->where('material_id', $materialId)
            ->orderByRaw("CASE confidence WHEN 'high' THEN 0 WHEN 'medium' THEN 1 ELSE 2 END")
            ->orderBy('id')
            ->first(['id', 'source', 'confidence', 'source_url']);

        return $link !== null ? [
            'id' => (int) $link->id,
            'source' => $link->source,
            'confidence' => $link->confidence,
            'source_url' => $link->source_url,
        ] : null;
    }

    /**
     * Refuse une proposition sans apport : le lien est déjà sûr dans VFF et la proposition ne date
     * rien, ou la même proposition (couple et dates) attend déjà sa relecture.
     *
     * @param  array<string, mixed>|null  $period
     * @param  array<string, mixed>|null  $existing
     */
    private function refuseDuplicates(Line $line, Material $material, ?array $period, ?array $existing): void
    {
        if ($period === null && $existing !== null && in_array($existing['confidence'] ?? null, ['high', 'medium'], true)) {
            throw ValidationException::withMessages(['material_slug' => __('proposals.line_material.errors.already_linked', ['series' => $material->name])]);
        }
        $pending = Contribution::query()
            ->where('type', self::TYPE)
            ->where('target_id', $line->id)
            ->whereIn('status', [ContributionStatus::Pending->value, ContributionStatus::Approved->value])
            ->where('payload->material_id', (string) $material->id)
            ->get(['payload']);
        foreach ($pending as $other) {
            if (($other->payload['period'] ?? null) == $period) {
                throw ValidationException::withMessages(['material_slug' => __('proposals.line_material.errors.pending_duplicate')]);
            }
        }
    }

    /**
     * Période de service saisie, ou null sans aucune date : début et fin à leur précision, portion
     * parcourue facultative.
     *
     * @param  array<string, mixed>  $data
     * @return array{start: array{date: string, precision: string}|null, end: array{date: string, precision: string}|null, from: ?string, to: ?string}|null
     *
     * @throws ValidationException date illisible pour sa précision, fin avant le début
     */
    private function period(array $data): ?array
    {
        $bound = function (string $key) use ($data): ?array {
            $raw = SourceCitation::clean($data["{$key}_date"] ?? null);
            if ($raw === null) {
                return null;
            }
            $precision = (string) $data["{$key}_precision"];
            $date = $this->traces->normalizeDate($raw, $precision)
                ?? throw ValidationException::withMessages(["{$key}_date" => __('editor.errors.bad_date')]);

            return ['date' => $date, 'precision' => $precision];
        };
        [$start, $end] = [$bound('start'), $bound('end')];
        $from = SourceCitation::clean($data['section_from'] ?? null);
        $to = SourceCitation::clean($data['section_to'] ?? null);
        if ($start === null && $end === null) {
            if ($from !== null) {
                throw ValidationException::withMessages(['start_date' => __('proposals.line_material.errors.section_without_date')]);
            }

            return null;
        }
        if ($start !== null && $end !== null && $end['date'] < $start['date']) {
            throw ValidationException::withMessages(['end_date' => __('proposals.line_material.errors.end_before_start')]);
        }

        return ['start' => $start, 'end' => $end, 'from' => $from, 'to' => $to];
    }

    /** Motif de l'exclusion relue du couple entier (database/data/line_material_exclusions.php), ou null. */
    private function exclusion(int $lineId, int $materialId): ?string
    {
        $none = [];
        $reason = LineMaterialExclusions::fromDatabase()->apply($lineId, $materialId, $none);

        // Le motif seul, sans le préfixe « exclusion relue : » du bilan de l'import.
        return $reason !== null ? (string) preg_replace('/^exclusion relue : /u', '', $reason) : null;
    }

    /**
     * Preuve publiée d'une contribution : source citée (titre, nature, licence, adresse), phrase
     * citée, famille de doute de la circulation déclarée, provenance `contribution:<id>`.
     *
     * @return array{kind: string, contribution: string, title: ?string, source_kind: ?string, license: ?string, quote: ?string, doubt: ?string, url: ?string}
     */
    private static function evidence(Contribution $contribution): array
    {
        $p = $contribution->payload;
        $source = $p['source'] ?? [];

        return [
            'kind' => self::EVIDENCE_KIND,
            'contribution' => self::fingerprint($contribution->id),
            'title' => $source['title'] ?? null,
            'source_kind' => $source['kind'] ?? null,
            'license' => $source['license'] ?? null,
            'quote' => $p['quote'] ?? null,
            'doubt' => self::CIRCULATIONS[$p['circulation'] ?? 'regular'] ?? null,
            'url' => $source['url'] ?? null,
        ];
    }

    /** @param array<string, mixed> $p */
    private function writePeriod(Contribution $contribution, LineMaterial $link, array $p): int
    {
        $period = $p['period'];
        $precisions = array_filter([$period['start']['precision'] ?? null, $period['end']['precision'] ?? null]);
        $confidence = match (true) {
            self::CIRCULATIONS[$p['circulation'] ?? 'regular'] !== null => 'low',
            in_array('year', $precisions, true) => 'medium',
            default => 'high',
        };
        $values = [
            'line_material_id' => $link->id,
            'start_date' => $period['start']['date'] ?? null,
            'start_precision' => $period['start']['precision'] ?? null,
            'end_date' => $period['end']['date'] ?? null,
            'end_precision' => $period['end']['precision'] ?? null,
            'section_from' => $period['from'] ?? null,
            'section_to' => $period['to'] ?? null,
            'confidence' => $confidence,
            'origin' => self::PERIOD_ORIGIN,
            'source' => self::SOURCE,
            'source_title' => SourceCitation::reference($p['source'] ?? []) !== null ? mb_strimwidth((string) SourceCitation::reference($p['source']), 0, 255, '…') : null,
            'source_revision' => null,
            'source_url' => $p['source']['url'] ?? null,
            'source_excerpt' => isset($p['quote']) ? mb_strimwidth((string) $p['quote'], 0, 255, '…') : null,
        ];
        $row = LineMaterialPeriod::query()->updateOrCreate(['fingerprint' => self::fingerprint($contribution->id)], $values);

        return (int) $row->id;
    }

    /** Nombre entier avec le séparateur de milliers de la langue (« 1 435 », « 1,435 », « 1.435 »). */
    private static function number(int $value): string
    {
        return match (app()->getLocale()) {
            'en' => number_format($value, 0, '.', ','),
            'de' => number_format($value, 0, ',', '.'),
            default => number_format($value, 0, ',', "\u{202F}"),
        };
    }

    /** Tension lisible dans la langue : « 1 500 V », « 25 kV ». */
    private static function volts(int $volt): string
    {
        if ($volt >= 10_000) {
            $kv = $volt / 1000;
            $text = rtrim(rtrim(number_format($kv, 1, app()->getLocale() === 'en' ? '.' : ',', ''), '0'), ',.');

            return $text."\u{00A0}kV";
        }

        return self::number($volt)."\u{00A0}V";
    }
}
