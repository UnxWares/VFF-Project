<?php

namespace App\Services\Contributions;

use App\Enums\ContributionStatus;
use App\Enums\MaterialType;
use App\Models\Contribution;
use App\Models\LineMaterial;
use App\Models\Material;
use App\Services\Materials\MaterialGaugeRule;
use App\Services\Materials\MaterialTractionRule;
use App\Services\Materials\WikidataItemResolver;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Contributions « série de matériel » (type `material_series`) : une nouvelle série (`new`) ou la
 * correction d'une série de VFF (`correction`), avec une source obligatoire.
 *
 * Cycle de vie (mêmes services que les autres contributions) :
 *   1. normalize() : saisie validée par rules() ; identifiant Wikidata vérifié auprès de Wikidata
 *      seulement s'il change (WikidataItemResolver : un élément qui n'existe pas ou qui n'est pas une
 *      série de matériel roulant est refusé). Une nouvelle série prend l'identifiant final d'un
 *      élément fusionné ; une correction ne change jamais d'identifiant sans que l'auteur saisisse
 *      l'identifiant final lui-même. Une série déjà présente (même identifiant Wikidata, même nom) est
 *      refusée avec l'invitation à corriger sa fiche, plutôt qu'un doublon. Une correction ne garde que
 *      les champs qui changent (`changes` : avant, après ; après nul pour une valeur effacée, champ
 *      `clear`) et mesure son effet sur les liens de la série (`impact` : liens qui deviendraient
 *      contraires aux règles d'écartement ou d'électrification) ;
 *   2. relecture par deux validateurs (ContributionReview), qui voient l'effet mesuré de nouveau
 *      (reviewContext) ;
 *   3. apply() : la série est créée ou corrigée dans `materials` ; chaque champ écrit prend la
 *      provenance `{source: contrib, ref: contribution:<id>}` (colonne `provenance`), que l'import de
 *      Wikidata n'écrase jamais (MaterialImporter). Une nouvelle série a `source` = `contrib`. Une
 *      correction échoue si un champ a changé depuis l'envoi (`stale`), si les années ne se suivent
 *      plus (`inconsistent`) ou si elle rendrait un lien de la série contraire aux règles
 *      (`incompatible`, avec la liste des liens) : elle ne laisse jamais un lien contredire les règles,
 *      et l'import ne retire jamais en silence un lien que la correction aurait condamné.
 *
 * Champs proposés : nom, catégorie (traction : `locomotive_electrique`, `autorail`…), systèmes
 * d'électrification (liste fermée, mêmes libellés et éléments Wikidata que l'import, lus par
 * MaterialTractionRule), écartements (ceux que VFF connaît, une série peut en avoir plusieurs),
 * constructeurs, années de mise en service et de retrait, identifiant Wikidata.
 */
final readonly class MaterialSeriesContributions
{
    public const TYPE = 'material_series';

    public const MODES = ['new', 'correction'];

    /** Source d'une série créée par une contribution, et de chaque champ écrit (provenance). */
    public const SOURCE = 'contrib';

    public const FINGERPRINT_PREFIX = 'contribution:';

    /** Champs d'une série qu'une proposition peut donner ou corriger. */
    public const FIELDS = ['name', 'type', 'builder', 'in_service_from', 'in_service_to', 'gauges', 'electrification', 'wikidata_id'];

    /** Champs qu'une correction peut effacer (valeur fausse, sans valeur juste connue). */
    public const CLEARABLE = ['builder', 'in_service_from', 'in_service_to', 'gauges', 'electrification'];

    /** Champs lus par les règles des liens (MaterialGaugeRule, MaterialTractionRule). */
    private const RULE_FIELDS = ['type', 'in_service_from', 'in_service_to', 'gauges', 'electrification'];

    /** Écartements d'une série au plus. */
    public const MAX_GAUGES = 4;

    /**
     * Systèmes d'électrification proposés : clé de saisie → libellé et élément Wikidata, exactement
     * comme l'import les écrit dans `materials.electrification` (MaterialTractionRule lit la tension
     * dans le libellé).
     *
     * @var array<string, array{label: string, wikidata_id: string}>
     */
    public const ELECTRIFICATION = [
        'dc_600' => ['label' => 'électrification ferroviaire 600 V continu', 'wikidata_id' => 'Q21855034'],
        'dc_750' => ['label' => 'électrification ferroviaire 750 V continu', 'wikidata_id' => 'Q25857994'],
        'dc_850' => ['label' => 'électrification ferroviaire 850 V continu', 'wikidata_id' => 'Q32901842'],
        'dc_1500' => ['label' => 'électrification ferroviaire 1500 V continu', 'wikidata_id' => 'Q21253457'],
        'dc_3000' => ['label' => 'électrification ferroviaire 3000 V continu', 'wikidata_id' => 'Q21251675'],
        'ac_15k' => ['label' => 'électrification ferroviaire 15 kV 16,7 Hz', 'wikidata_id' => 'Q1415234'],
        'ac_20k' => ['label' => 'électrification ferroviaire 20 kV 50 Hz', 'wikidata_id' => 'Q106594569'],
        'ac_25k' => ['label' => 'électrification ferroviaire 25 kV 50 Hz', 'wikidata_id' => 'Q106530439'],
    ];

    /**
     * Écartements courants (mm) → libellé et élément Wikidata, comme l'import (`materials.gauges`).
     * Le formulaire propose aussi ceux que la base connaît déjà (knownGauges()).
     *
     * @var array<int, array{label: string, wikidata_id: string}>
     */
    public const GAUGES = [
        1435 => ['label' => 'écartement standard', 'wikidata_id' => 'Q1999572'],
        1000 => ['label' => 'écartement métrique', 'wikidata_id' => 'Q24609'],
        600 => ['label' => 'écartement des rails de 600 mm', 'wikidata_id' => 'Q10676739'],
        1668 => ['label' => 'voie ibérique', 'wikidata_id' => 'Q3319112'],
        1520 => ['label' => 'voie russe', 'wikidata_id' => 'Q1197451'],
    ];

    /** Première année de service acceptée. */
    public const MIN_YEAR = 1800;

    public function __construct(private WikidataItemResolver $wikidata) {}

    /**
     * Règles de saisie, communes au formulaire du site et à la mutation GraphQL. Pour une correction,
     * un champ absent ou nul ne change pas.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public static function rules(): array
    {
        $maxYear = (int) date('Y') + 30;

        return [
            'mode' => ['required', 'string', Rule::in(self::MODES)],
            'material_slug' => ['exclude_unless:mode,correction', 'required', 'string', 'max:191', 'exists:materials,slug'],
            'name' => ['nullable', 'required_if:mode,new', 'string', 'min:2', 'max:120'],
            'type' => ['nullable', 'required_if:mode,new', 'string', Rule::enum(MaterialType::class)],
            'builder' => ['nullable', 'string', 'min:2', 'max:255'],
            'in_service_from' => ['nullable', 'integer', 'min:'.self::MIN_YEAR, 'max:'.$maxYear],
            'in_service_to' => ['nullable', 'integer', 'min:'.self::MIN_YEAR, 'max:'.$maxYear],
            'gauges' => ['nullable', 'array', 'max:'.self::MAX_GAUGES],
            'gauges.*' => ['required', 'integer', 'distinct', Rule::in($gauges = self::knownGauges())],
            // Forme courte d'un seul écartement (`gauges` = [gauge_mm]).
            'gauge_mm' => ['nullable', 'integer', Rule::in($gauges), 'prohibits:gauges'],
            'electrification' => ['nullable', 'array', 'max:'.count(self::ELECTRIFICATION)],
            'electrification.*' => ['required', 'string', 'distinct', Rule::in(array_keys(self::ELECTRIFICATION))],
            'wikidata_id' => ['nullable', 'string', 'max:120'],
            'clear' => ['exclude_unless:mode,correction', 'nullable', 'array', 'max:'.count(self::CLEARABLE)],
            'clear.*' => ['required', 'string', 'distinct', Rule::in(self::CLEARABLE)],
            ...SourceCitation::rules(),
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'material_slug.required' => __('proposals.errors.material_required'),
            'material_slug.exists' => __('proposals.errors.material_required'),
            'name.required_if' => __('proposals.series.errors.name'),
            'name.min' => __('proposals.series.errors.name'),
            'type.required_if' => __('proposals.series.errors.type'),
            'in_service_from.*' => __('proposals.series.errors.year', ['min' => self::MIN_YEAR]),
            'in_service_to.*' => __('proposals.series.errors.year', ['min' => self::MIN_YEAR]),
            'gauges.*' => __('proposals.series.errors.gauge'),
            'gauge_mm.prohibits' => __('proposals.series.errors.gauge_both'),
            'gauge_mm.*' => __('proposals.series.errors.gauge'),
            'electrification.*' => __('proposals.series.errors.system'),
            'clear.*' => __('proposals.series.errors.clear'),
            ...SourceCitation::messages(),
        ];
    }

    /**
     * Écartements (mm) qu'une proposition peut donner : les courants (GAUGES) et tous ceux que les
     * séries de VFF portent déjà (891 mm du Stadler X15p, 1 588 mm…), dans l'ordre croissant.
     *
     * @return list<int>
     */
    public static function knownGauges(): array
    {
        $stored = DB::select(<<<'SQL'
            SELECT gauge_mm AS mm FROM materials WHERE gauge_mm IS NOT NULL
            UNION
            SELECT (g->>'mm')::int FROM materials, jsonb_array_elements(CASE WHEN jsonb_typeof(gauges) = 'array' THEN gauges ELSE '[]'::jsonb END) g
            WHERE g->>'mm' ~ '^[0-9]{2,4}$'
            SQL);
        $gauges = array_unique([...array_keys(self::GAUGES), ...array_map(fn (object $row): int => (int) $row->mm, $stored)]);
        sort($gauges);

        return $gauges;
    }

    /**
     * Charge utile d'une proposition validée par rules().
     *
     * @param  array<string, mixed>  $data
     * @return array{payload: array<string, mixed>, material: ?Material}
     *
     * @throws ValidationException identifiant Wikidata inconnu ou déjà pris, série déjà présente, rien à corriger
     * @throws ModelNotFoundException série à corriger inconnue
     */
    public function normalize(array $data): array
    {
        $material = $data['mode'] === 'correction'
            ? Material::query()->where('slug', $data['material_slug'])->firstOrFail()
            : null;
        $values = $this->values($data);
        $clear = $material !== null ? array_values(array_intersect(self::CLEARABLE, (array) ($data['clear'] ?? []))) : [];
        foreach ($clear as $field) {
            if (array_key_exists($field, $values)) {
                throw ValidationException::withMessages(['clear' => __('proposals.series.errors.clear_and_value', ['field' => __("proposals.review.fields.{$field}")])]);
            }
        }
        $wikidata = null;
        if (isset($values['wikidata_id'])) {
            // Identifiant inchangé (le formulaire renvoie celui de la fiche) : aucun appel à Wikidata.
            $wikidata = $material?->wikidata_id === $values['wikidata_id'] ? null : $this->checkWikidata($values['wikidata_id'], $material);
            if ($wikidata === null) {
                unset($values['wikidata_id']);
            } else {
                $values['wikidata_id'] = $wikidata['id'];
            }
        }

        $base = [
            'mode' => $data['mode'],
            'wikidata' => $wikidata,
            'source' => SourceCitation::fromInput($data),
            'notes' => SourceCitation::trimmed($data['notes'] ?? null),
        ];

        if ($material === null) {
            $this->refuseDuplicate((string) $values['name'], $values['wikidata_id'] ?? null);
            self::checkYears($values['in_service_from'] ?? null, $values['in_service_to'] ?? null);

            return ['material' => null, 'payload' => [
                'material_name' => $values['name'],
                'values' => $values,
                ...$base,
            ]];
        }

        $changes = [];
        foreach ($values as $field => $after) {
            $before = self::current($material, $field);
            if ($before !== $after) {
                $changes[$field] = ['before' => $before, 'after' => $after];
            }
        }
        foreach ($clear as $field) {
            $before = self::current($material, $field);
            if ($before !== null) {
                $changes[$field] = ['before' => $before, 'after' => null];
            }
        }
        if ($changes === []) {
            throw ValidationException::withMessages(['changes' => __('proposals.series.errors.no_change')]);
        }
        if (isset($changes['name'])) {
            $this->refuseDuplicate((string) $changes['name']['after'], null, $material->id);
        }
        $after = self::after($changes);
        self::checkYears(...self::years($material, $after));

        return ['material' => $material, 'payload' => [
            'material_id' => $material->id,
            'material_slug' => $material->slug,
            'material_name' => $material->name,
            'changes' => $changes,
            'impact' => $this->impact($material, $after),
            ...$base,
        ]];
    }

    /**
     * Ce que le formulaire montre après « Vérifier » : l'identifiant lu dans la saisie (`requested`),
     * l'élément Wikidata (son `id` diffère de `requested` quand l'élément a été fusionné : le formulaire
     * le montre et laisse l'auteur choisir) et, s'il est déjà dans VFF, la série qui le porte (à
     * corriger plutôt qu'à créer). Erreurs sous la clé `qid`, le nom du paramètre.
     *
     * @return array{requested: string, item: array<string, mixed>|null, existing: array{slug: string, name: string}|null}
     *
     * @throws ValidationException identifiant illisible, élément qui n'est pas une série, Wikidata injoignable
     */
    public function lookup(string $input): array
    {
        $qid = WikidataItemResolver::normalize($input)
            ?? throw ValidationException::withMessages(['qid' => __('proposals.series.errors.qid_format')]);
        $item = $this->resolve($qid, 'qid');
        $existing = $item !== null ? Material::query()->where('wikidata_id', $item['id'])->first(['slug', 'name']) : null;
        if ($item !== null && $existing === null && ! $this->rollingStock($item, 'qid')) {
            throw ValidationException::withMessages(['qid' => self::notSeriesMessage($item)]);
        }

        return [
            'requested' => $qid,
            'item' => $item,
            'existing' => $existing !== null ? ['slug' => $existing->slug, 'name' => $existing->name] : null,
        ];
    }

    /**
     * Ce que les validateurs voient en plus pour une correction, mesuré au moment de la relecture :
     * les champs dont la valeur a changé depuis l'envoi (`stale` : champ, valeur actuelle ; la
     * publication échouera) et les liens de la série qui deviendraient contraires aux règles
     * (`impact`, comme impact()).
     *
     * @return array{stale: list<array{field: string, now: mixed}>, impact: list<array<string, mixed>>}
     */
    public function reviewContext(Contribution $contribution): array
    {
        $p = $contribution->payload;
        $material = ($p['mode'] ?? null) === 'correction' ? Material::query()->find($contribution->target_id) : null;
        if ($material === null) {
            return ['stale' => [], 'impact' => []];
        }
        $changes = $p['changes'] ?? [];

        return [
            'stale' => array_map(fn (string $field): array => ['field' => $field, 'now' => self::current($material, $field)], self::staleFields($material, $changes)),
            'impact' => $this->impact($material, self::after($changes)),
        ];
    }

    /**
     * Effet d'une correction en cours de saisie sur les liens de la série (formulaire, avant l'envoi) :
     * les valeurs saisies qui changent un champ lu par les règles, et les champs à effacer.
     *
     * @param  array<string, mixed>  $data  valeurs validées (rules(), champs des règles et `clear`)
     * @return list<array{line_slug: string, line_code: ?string, line_name: string, source: ?string, rule: string, reason: string}>
     */
    public function previewImpact(Material $material, array $data): array
    {
        $values = array_intersect_key($this->values($data), array_flip(self::RULE_FIELDS));
        foreach (array_intersect(self::CLEARABLE, (array) ($data['clear'] ?? [])) as $field) {
            $values[$field] ??= null;
        }
        $after = array_filter($values, fn (mixed $value, string $field): bool => self::current($material, $field) !== $value, ARRAY_FILTER_USE_BOTH);

        return $this->impact($material, $after);
    }

    /**
     * Liens de la série qui deviendraient contraires à la règle d'écartement ou d'électrification si
     * ces valeurs étaient écrites (et qui ne le sont pas aujourd'hui) : ligne, source du lien, règle,
     * motif (en français, comme vff:check-invariants). Les preuves et les exceptions relues de chaque
     * lien comptent, comme pour l'import.
     *
     * @param  array<string, mixed>  $after  champ → nouvelle valeur (nulle : effacée)
     * @return list<array{line_slug: string, line_code: ?string, line_name: string, source: ?string, rule: string, reason: string}>
     */
    public function impact(Material $material, array $after): array
    {
        if (array_intersect_key($after, array_flip(self::RULE_FIELDS)) === []) {
            return [];
        }
        $links = LineMaterial::query()->with('line:id,slug,code,name')->where('material_id', $material->id)->orderBy('id')->get();
        if ($links->isEmpty()) {
            return [];
        }

        $state = fn (string $field): mixed => array_key_exists($field, $after) ? $after[$field] : self::current($material, $field);
        $gauges = $state('gauges');
        $systems = array_key_exists('electrification', $after)
            ? array_map(fn (string $key): array => self::ELECTRIFICATION[$key], (array) $after['electrification'])
            : $material->electrification;
        $lineIds = $links->pluck('line_id')->unique()->values()->all();
        $gaugeNow = MaterialGaugeRule::fromDatabase(lineIds: $lineIds);
        $tractionNow = MaterialTractionRule::fromDatabase(lineIds: $lineIds);
        $gaugeAfter = $gaugeNow->withMaterialGauge($material->id, is_array($gauges) && count($gauges) === 1 ? (int) $gauges[0] : null);
        $tractionAfter = $tractionNow->withMaterial($material->id, $systems, $state('type'), $state('in_service_from'), $state('in_service_to'));

        $out = [];
        foreach ($links as $link) {
            $evidence = array_values(array_filter($link->evidence ?? [], 'is_array'));
            [$lineId, $materialId] = [(int) $link->line_id, (int) $link->material_id];
            if (($gaugeNow->conflict($lineId, $materialId, $evidence) ?? $tractionNow->conflict($lineId, $materialId, $evidence)) !== null) {
                continue;
            }
            $gauge = $gaugeAfter->conflict($lineId, $materialId, $evidence);
            $reason = $gauge ?? $tractionAfter->conflict($lineId, $materialId, $evidence);
            if ($reason !== null) {
                $out[] = [
                    'line_slug' => $link->line->slug,
                    'line_code' => $link->line->code,
                    'line_name' => $link->line->name,
                    'source' => $link->source,
                    'rule' => $gauge !== null ? 'gauge' : 'traction',
                    'reason' => $reason,
                ];
            }
        }

        return $out;
    }

    /**
     * Crée ou corrige la série (dans la transaction de l'approbation).
     *
     * @return array{material_id: int, material_slug: string}
     *
     * @throws ContributionNotPublishable série à corriger disparue, nom ou identifiant pris entre-temps
     */
    public function apply(Contribution $contribution): array
    {
        $p = $contribution->payload;
        // Provenance de chaque champ écrit : la contribution et l'adresse de la source qu'elle cite.
        $provenance = array_filter([
            'source' => self::SOURCE,
            'ref' => self::FINGERPRINT_PREFIX.$contribution->id,
            'url' => $p['source']['url'] ?? null,
        ], fn (?string $v): bool => $v !== null);

        if (($p['mode'] ?? null) === 'new') {
            $values = $p['values'] ?? [];
            // Une série publiée par la même contribution (publication rejouée) est mise à jour, sans doublon.
            $material = Material::query()->where('provenance->name->ref', $provenance['ref'])->first();
            if ($material === null && $this->taken((string) $values['name'], $values['wikidata_id'] ?? null)) {
                throw new ContributionNotPublishable(PublicationFailure::AlreadyPresent, "Série « {$values['name']} » déjà présente (contribution {$contribution->id})");
            }
            $material ??= new Material(['slug' => self::uniqueSlug((string) $values['name']), 'source' => self::SOURCE]);
            $this->write($material, $values, $provenance);

            return ['material_id' => (int) $material->id, 'material_slug' => $material->slug];
        }

        $material = Material::query()->find($contribution->target_id)
            ?? throw new ContributionNotPublishable(PublicationFailure::TargetNotFound, "Série {$contribution->target_id} introuvable (contribution {$contribution->id})");
        $changes = $p['changes'] ?? [];
        // La fiche a pu changer depuis l'envoi (autre correction, import) : « avant » doit être l'état actuel.
        $stale = self::staleFields($material, $changes);
        if ($stale !== []) {
            throw new ContributionNotPublishable(PublicationFailure::Stale, 'Champs changés depuis l\'envoi : '.implode(', ', $stale)." (contribution {$contribution->id})",
                array_map(fn (string $field): string => __("proposals.review.fields.{$field}"), $stale));
        }
        $after = self::after($changes);
        [$from, $to] = self::years($material, $after);
        if ($from !== null && $to !== null && $to < $from) {
            throw new ContributionNotPublishable(PublicationFailure::Inconsistent, "Retrait ({$to}) avant la mise en service ({$from}) (contribution {$contribution->id})");
        }
        if ($this->taken(isset($after['name']) ? (string) $after['name'] : null, $after['wikidata_id'] ?? null, $material->id)) {
            throw new ContributionNotPublishable(PublicationFailure::AlreadyPresent, "Nom ou identifiant Wikidata pris par une autre série (contribution {$contribution->id})");
        }
        $impact = $this->impact($material, $after);
        if ($impact !== []) {
            $lines = array_map(fn (array $l): string => trim(($l['line_code'] ?? '').' '.$l['line_name']).' ('.($l['source'] ?? '-').') : '.$l['reason'], $impact);
            throw new ContributionNotPublishable(PublicationFailure::Incompatible, 'Liens rendus contraires aux règles : '.implode(' ; ', $lines)." (contribution {$contribution->id})", $lines);
        }
        $this->write($material, $after, $provenance);

        return ['material_id' => (int) $material->id, 'material_slug' => $material->slug];
    }

    /**
     * Valeurs saisies, normalisées (types, ordre fixe des systèmes, identifiant Wikidata), sans les
     * champs vides : pour une correction, un champ vide ne change rien.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function values(array $data): array
    {
        $values = [
            'name' => SourceCitation::clean($data['name'] ?? null),
            'type' => isset($data['type']) ? (string) $data['type'] : null,
            'builder' => SourceCitation::clean($data['builder'] ?? null),
            'in_service_from' => isset($data['in_service_from']) ? (int) $data['in_service_from'] : null,
            'in_service_to' => isset($data['in_service_to']) ? (int) $data['in_service_to'] : null,
            'gauges' => self::gaugeList($data),
            'electrification' => is_array($data['electrification'] ?? null) && $data['electrification'] !== []
                ? array_values(array_intersect(array_keys(self::ELECTRIFICATION), $data['electrification']))
                : null,
            'wikidata_id' => null,
        ];
        $qid = SourceCitation::clean($data['wikidata_id'] ?? null);
        if ($qid !== null) {
            $values['wikidata_id'] = WikidataItemResolver::normalize($qid)
                ?? throw ValidationException::withMessages(['wikidata_id' => __('proposals.series.errors.qid_format')]);
        }

        return array_filter($values, fn (mixed $v): bool => $v !== null);
    }

    /**
     * Écartements saisis (`gauges`, ou `gauge_mm` seul), sans doublon, dans l'ordre croissant ; null
     * sans écartement.
     *
     * @param  array<string, mixed>  $data
     * @return list<int>|null
     */
    private static function gaugeList(array $data): ?array
    {
        $gauges = is_array($data['gauges'] ?? null) ? $data['gauges'] : (isset($data['gauge_mm']) ? [$data['gauge_mm']] : []);
        $gauges = array_values(array_unique(array_map('intval', $gauges)));
        sort($gauges);

        return $gauges !== [] ? $gauges : null;
    }

    /**
     * Élément Wikidata vérifié, ou null s'il ne change rien (redirection vers l'élément de la série
     * corrigée). Il existe, il désigne une série de matériel roulant et aucune autre série de VFF ne le
     * porte. Pour une correction, l'identifiant n'est jamais remplacé à l'insu de l'auteur : un élément
     * fusionné est refusé avec son identifiant final, que l'auteur peut saisir ; une série déjà
     * rattachée à un autre élément le garde, sauf si cet élément a été fusionné dans celui saisi.
     *
     * @return array{id: string, label: ?string, description: ?string, instance_of: list<string>, subclass_of: list<string>}|null
     *
     * @throws ValidationException élément inconnu, fusionné, déjà pris, d'une autre nature, Wikidata injoignable
     */
    private function checkWikidata(string $qid, ?Material $material): ?array
    {
        $item = $this->resolve($qid)
            ?? throw ValidationException::withMessages(['wikidata_id' => __('proposals.series.errors.qid_missing', ['qid' => $qid])]);
        if ($material !== null) {
            if ($item['id'] === $material->wikidata_id) {
                return null;
            }
            if ($item['id'] !== $qid) {
                throw ValidationException::withMessages(['wikidata_id' => __('proposals.series.errors.qid_merged', ['qid' => $qid, 'final' => $item['id']])]);
            }
            if ($material->wikidata_id !== null && ($this->resolve($material->wikidata_id)['id'] ?? null) !== $item['id']) {
                throw ValidationException::withMessages(['wikidata_id' => __('proposals.series.errors.qid_other', ['qid' => $material->wikidata_id])]);
            }
        }
        $owner = Material::query()->where('wikidata_id', $item['id'])->first(['id', 'name']);
        if ($owner !== null && $owner->id !== $material?->id) {
            throw ValidationException::withMessages(['wikidata_id' => __('proposals.series.errors.qid_taken', ['name' => $owner->name])]);
        }
        if (! $this->rollingStock($item)) {
            throw ValidationException::withMessages(['wikidata_id' => self::notSeriesMessage($item)]);
        }

        return $item;
    }

    /**
     * @return array{id: string, label: ?string, description: ?string, instance_of: list<string>, subclass_of: list<string>}|null
     *
     * @throws ValidationException Wikidata injoignable (erreur sous la clé `$key`)
     */
    private function resolve(string $qid, string $key = 'wikidata_id'): ?array
    {
        try {
            return $this->wikidata->lookup($qid);
        } catch (RuntimeException $e) {
            report($e);

            throw ValidationException::withMessages([$key => __('proposals.series.errors.qid_unreachable')]);
        }
    }

    /**
     * @param  array{id: string, instance_of: list<string>, subclass_of?: list<string>}  $item
     *
     * @throws ValidationException Wikidata injoignable (erreur sous la clé `$key`)
     */
    private function rollingStock(array $item, string $key = 'wikidata_id'): bool
    {
        try {
            return $this->wikidata->isRollingStock($item);
        } catch (RuntimeException $e) {
            report($e);

            throw ValidationException::withMessages([$key => __('proposals.series.errors.qid_unreachable')]);
        }
    }

    /** @param array{id: string, label?: ?string} $item */
    private static function notSeriesMessage(array $item): string
    {
        return __('proposals.series.errors.qid_not_series', ['qid' => $item['id'], 'label' => $item['label'] ?? $item['id']]);
    }

    /**
     * Refuse une nouvelle série (ou un nouveau nom) déjà présente dans VFF ou déjà proposée : même nom
     * (casse et accents ignorés), même adresse, même identifiant Wikidata.
     *
     * @throws ValidationException doublon
     */
    private function refuseDuplicate(string $name, ?string $qid, ?int $except = null): void
    {
        $existing = $this->sameName($name, $except);
        if ($existing !== null) {
            throw ValidationException::withMessages(['name' => __('proposals.series.errors.name_taken', ['name' => $existing->name])]);
        }
        $slug = Str::slug($name);
        $pending = Contribution::query()
            ->where('type', self::TYPE)
            ->whereIn('status', [ContributionStatus::Pending->value, ContributionStatus::Approved->value])
            ->where('payload->mode', 'new')
            ->get(['payload']);
        foreach ($pending as $other) {
            $values = $other->payload['values'] ?? [];
            if (Str::slug((string) ($values['name'] ?? '')) === $slug || ($qid !== null && ($values['wikidata_id'] ?? null) === $qid)) {
                throw ValidationException::withMessages(['name' => __('proposals.series.errors.pending_duplicate')]);
            }
        }
    }

    /** Une autre série porte-t-elle ce nom ou cet identifiant Wikidata ? */
    private function taken(?string $name, ?string $qid, ?int $except = null): bool
    {
        return ($name !== null && $this->sameName($name, $except) !== null)
            || ($qid !== null && Material::query()->where('wikidata_id', $qid)->when($except !== null, fn ($q) => $q->whereKeyNot($except))->exists());
    }

    /** Série de même nom (casse et accents ignorés) ou de même adresse, sauf `$except`. */
    private function sameName(string $name, ?int $except): ?Material
    {
        return Material::query()
            ->where(fn ($q) => $q->whereRaw('lower(unaccent(name)) = lower(unaccent(?))', [$name])->orWhere('slug', Str::slug($name)))
            ->when($except !== null, fn ($q) => $q->whereKeyNot($except))
            ->first(['id', 'name']);
    }

    /** @throws ValidationException retrait avant la mise en service */
    private static function checkYears(?int $from, ?int $to): void
    {
        if ($from !== null && $to !== null && $to < $from) {
            throw ValidationException::withMessages(['in_service_to' => __('proposals.series.errors.years_order')]);
        }
    }

    /**
     * Nouvelles valeurs d'une correction, par champ (nulle : valeur effacée).
     *
     * @param  array<string, array{before: mixed, after: mixed}>  $changes
     * @return array<string, mixed>
     */
    private static function after(array $changes): array
    {
        return array_map(fn (array $change): mixed => $change['after'], $changes);
    }

    /**
     * Années de service une fois la correction écrite : la valeur corrigée, sinon l'actuelle.
     *
     * @param  array<string, mixed>  $after
     * @return array{0: ?int, 1: ?int}
     */
    private static function years(Material $material, array $after): array
    {
        $year = fn (string $field): ?int => array_key_exists($field, $after)
            ? ($after[$field] !== null ? (int) $after[$field] : null)
            : $material->getAttribute($field);

        return [$year('in_service_from'), $year('in_service_to')];
    }

    /**
     * Champs dont la valeur actuelle n'est plus l'« avant » enregistré à l'envoi.
     *
     * @param  array<string, array{before: mixed, after: mixed}>  $changes
     * @return list<string>
     */
    private static function staleFields(Material $material, array $changes): array
    {
        return array_values(array_filter(array_keys($changes), fn (string $field): bool => self::current($material, $field) !== $changes[$field]['before']));
    }

    /**
     * Valeur actuelle d'un champ, sous la forme de la saisie (systèmes d'électrification par clé).
     */
    private static function current(Material $material, string $field): mixed
    {
        return match ($field) {
            'type' => $material->getRawOriginal('type'),
            'builder' => $material->getRawOriginal('builder'),
            'electrification' => self::systemKeys($material->electrification ?? []) ?: null,
            'gauges' => self::gaugesOf($material),
            default => $material->getAttribute($field),
        };
    }

    /**
     * Écartements d'une série (mm), sans doublon, dans l'ordre croissant : ceux de `gauges`, sinon
     * `gauge_mm` ; null si aucun.
     *
     * @return list<int>|null
     */
    public static function gaugesOf(Material $material): ?array
    {
        $gauges = array_values(array_filter(array_map(
            fn (array $g): ?int => is_numeric($g['mm'] ?? null) ? (int) $g['mm'] : null,
            $material->gauges ?? [],
        )));
        if ($gauges === [] && $material->gauge_mm !== null) {
            $gauges = [(int) $material->gauge_mm];
        }
        $gauges = array_values(array_unique($gauges));
        sort($gauges);

        return $gauges !== [] ? $gauges : null;
    }

    /**
     * Clés de saisie des systèmes connus d'une série (par élément Wikidata), dans l'ordre de la liste.
     *
     * @param  array<int, mixed>  $systems
     * @return list<string>
     */
    public static function systemKeys(array $systems): array
    {
        $ids = array_column(array_filter($systems, 'is_array'), 'wikidata_id');

        return array_keys(array_filter(self::ELECTRIFICATION, fn (array $s): bool => in_array($s['wikidata_id'], $ids, true)));
    }

    /**
     * Écrit les valeurs dans la série avec leur provenance ; les écartements remplissent `gauges` et
     * `gauge_mm` (l'écartement d'une série qui n'en a qu'un, nul sinon, comme l'import), les
     * constructeurs `builder` et `builders`. Une valeur nulle efface le champ ; sa provenance
     * `contrib` empêche l'import de le remplir de nouveau.
     *
     * @param  array<string, mixed>  $values
     * @param  array<string, string>  $provenance  {source, ref, url?}
     */
    private function write(Material $material, array $values, array $provenance): void
    {
        $sources = $material->provenance ?? [];
        foreach ($values as $field => $value) {
            $columns = match ($field) {
                'electrification' => ['electrification' => $value === null ? null : array_map(fn (string $key): array => self::ELECTRIFICATION[$key], (array) $value)],
                'gauges' => $value === null ? ['gauges' => null, 'gauge_mm' => null] : [
                    'gauges' => array_map(fn (int $mm): array => self::gaugeEntry($mm), array_map('intval', (array) $value)),
                    'gauge_mm' => count((array) $value) === 1 ? (int) array_values((array) $value)[0] : null,
                ],
                'builder' => $value === null ? ['builder' => null, 'builders' => null] : ['builder' => (string) $value, 'builders' => array_map(
                    fn (string $name): array => ['name' => $name, 'wikidata_id' => null, 'units' => null],
                    array_values(array_filter(array_map('trim', explode(',', (string) $value)))),
                )],
                default => [$field => $value],
            };
            foreach ($columns as $column => $v) {
                $material->setAttribute($column, $v);
                if ($column !== 'wikidata_id') {
                    $sources[$column] = $provenance;
                }
            }
        }
        $material->provenance = $sources;
        $material->save();
    }

    /**
     * Entrée de `materials.gauges` pour un écartement : libellé et élément Wikidata de la liste courante,
     * sinon ceux qu'une série de VFF porte déjà pour cette largeur, sinon la largeur seule.
     *
     * @return array{label: string, mm: int, wikidata_id: ?string}
     */
    private static function gaugeEntry(int $mm): array
    {
        if (isset(self::GAUGES[$mm])) {
            return ['label' => self::GAUGES[$mm]['label'], 'mm' => $mm, 'wikidata_id' => self::GAUGES[$mm]['wikidata_id']];
        }
        $known = DB::selectOne(<<<'SQL'
            SELECT g->>'label' AS label, g->>'wikidata_id' AS wikidata_id
            FROM materials, jsonb_array_elements(CASE WHEN jsonb_typeof(gauges) = 'array' THEN gauges ELSE '[]'::jsonb END) g
            WHERE g->>'mm' = ? AND g->>'label' IS NOT NULL
            ORDER BY materials.id LIMIT 1
            SQL, [(string) $mm]);

        return ['label' => $known->label ?? "{$mm} mm", 'mm' => $mm, 'wikidata_id' => $known->wikidata_id ?? null];
    }

    /** Adresse d'une nouvelle série : le nom en minuscules, suffixé d'un numéro en cas d'homonymie. */
    private static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'serie';
        $slug = $base;
        for ($i = 2; Material::query()->where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}
