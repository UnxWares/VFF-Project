<?php

namespace App\Services\Contributions;

use App\Enums\ContributionStatus;
use App\Enums\MaterialType;
use App\Models\Contribution;
use App\Models\Material;
use App\Services\Materials\WikidataItemResolver;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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
 *      (WikidataItemResolver : un élément qui n'existe pas est refusé, un élément fusionné prend son
 *      identifiant final) ; une série déjà présente (même identifiant Wikidata, même nom) est refusée
 *      avec l'invitation à corriger sa fiche, plutôt qu'un doublon. Une correction ne garde que les
 *      champs qui changent (`changes` : avant, après) ;
 *   2. relecture par deux validateurs (ContributionReview) ;
 *   3. apply() : la série est créée ou corrigée dans `materials` ; chaque champ écrit prend la
 *      provenance `{source: contrib, ref: contribution:<id>}` (colonne `provenance`), que l'import de
 *      Wikidata n'écrase jamais (MaterialImporter). Une nouvelle série a `source` = `contrib`.
 *
 * Champs proposés : nom, catégorie (traction : `locomotive_electrique`, `autorail`…), systèmes
 * d'électrification (liste fermée, mêmes libellés et éléments Wikidata que l'import, lus par
 * MaterialTractionRule), écartement (liste fermée), constructeurs, années de mise en service et de
 * retrait, identifiant Wikidata.
 */
final readonly class MaterialSeriesContributions
{
    public const TYPE = 'material_series';

    public const MODES = ['new', 'correction'];

    /** Source d'une série créée par une contribution, et de chaque champ écrit (provenance). */
    public const SOURCE = 'contrib';

    public const FINGERPRINT_PREFIX = 'contribution:';

    /** Champs d'une série qu'une proposition peut donner ou corriger. */
    public const FIELDS = ['name', 'type', 'builder', 'in_service_from', 'in_service_to', 'gauge_mm', 'electrification', 'wikidata_id'];

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
     * Écartements proposés (mm) → libellé et élément Wikidata, comme l'import (`materials.gauges`).
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
            'gauge_mm' => ['nullable', 'integer', Rule::in(array_keys(self::GAUGES))],
            'electrification' => ['nullable', 'array', 'max:'.count(self::ELECTRIFICATION)],
            'electrification.*' => ['required', 'string', 'distinct', Rule::in(array_keys(self::ELECTRIFICATION))],
            'wikidata_id' => ['nullable', 'string', 'max:120'],
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
            ...SourceCitation::messages(),
        ];
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
        $wikidata = null;
        if (isset($values['wikidata_id'])) {
            $wikidata = $this->checkWikidata($values['wikidata_id'], $material);
            $values['wikidata_id'] = $wikidata['id'];
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
        if ($changes === []) {
            throw ValidationException::withMessages(['changes' => __('proposals.series.errors.no_change')]);
        }
        if (isset($changes['name'])) {
            $this->refuseDuplicate((string) $changes['name']['after'], null, $material->id);
        }
        self::checkYears(
            $changes['in_service_from']['after'] ?? $material->in_service_from,
            $changes['in_service_to']['after'] ?? $material->in_service_to,
        );

        return ['material' => $material, 'payload' => [
            'material_id' => $material->id,
            'material_slug' => $material->slug,
            'material_name' => $material->name,
            'changes' => $changes,
            ...$base,
        ]];
    }

    /**
     * Ce que le formulaire montre après « Vérifier » : l'élément Wikidata et, s'il est déjà dans VFF,
     * la série qui le porte (à corriger plutôt qu'à créer).
     *
     * @return array{item: array<string, mixed>|null, existing: array{slug: string, name: string}|null}
     *
     * @throws ValidationException identifiant illisible, Wikidata injoignable
     */
    public function lookup(string $input): array
    {
        $qid = WikidataItemResolver::normalize($input)
            ?? throw ValidationException::withMessages(['wikidata_id' => __('proposals.series.errors.qid_format')]);
        $item = $this->resolve($qid);
        $existing = $item !== null ? Material::query()->where('wikidata_id', $item['id'])->first(['slug', 'name']) : null;

        return [
            'item' => $item,
            'existing' => $existing !== null ? ['slug' => $existing->slug, 'name' => $existing->name] : null,
        ];
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
        $after = array_map(fn (array $change): mixed => $change['after'], $p['changes'] ?? []);
        if ($this->taken(isset($after['name']) ? (string) $after['name'] : null, $after['wikidata_id'] ?? null, $material->id)) {
            throw new ContributionNotPublishable(PublicationFailure::AlreadyPresent, "Nom ou identifiant Wikidata pris par une autre série (contribution {$contribution->id})");
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
            'gauge_mm' => isset($data['gauge_mm']) ? (int) $data['gauge_mm'] : null,
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
     * Élément Wikidata vérifié : il existe, et aucune autre série de VFF ne le porte. Une série à
     * corriger déjà rattachée à un autre élément garde le sien.
     *
     * @return array{id: string, label: ?string, description: ?string, instance_of: list<string>}
     *
     * @throws ValidationException élément inconnu, déjà pris, Wikidata injoignable
     */
    private function checkWikidata(string $qid, ?Material $material): array
    {
        if ($material?->wikidata_id !== null && $material->wikidata_id !== $qid) {
            throw ValidationException::withMessages(['wikidata_id' => __('proposals.series.errors.qid_other', ['qid' => $material->wikidata_id])]);
        }
        $item = $this->resolve($qid)
            ?? throw ValidationException::withMessages(['wikidata_id' => __('proposals.series.errors.qid_missing', ['qid' => $qid])]);
        $owner = Material::query()->where('wikidata_id', $item['id'])->first(['id', 'name']);
        if ($owner !== null && $owner->id !== $material?->id) {
            throw ValidationException::withMessages(['wikidata_id' => __('proposals.series.errors.qid_taken', ['name' => $owner->name])]);
        }

        return $item;
    }

    /**
     * @return array{id: string, label: ?string, description: ?string, instance_of: list<string>}|null
     *
     * @throws ValidationException Wikidata injoignable
     */
    private function resolve(string $qid): ?array
    {
        try {
            return $this->wikidata->lookup($qid);
        } catch (RuntimeException $e) {
            report($e);

            throw ValidationException::withMessages(['wikidata_id' => __('proposals.series.errors.qid_unreachable')]);
        }
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
     * Valeur actuelle d'un champ, sous la forme de la saisie (systèmes d'électrification par clé).
     */
    private static function current(Material $material, string $field): mixed
    {
        return match ($field) {
            'type' => $material->getRawOriginal('type'),
            'builder' => $material->getRawOriginal('builder'),
            'electrification' => self::systemKeys($material->electrification ?? []) ?: null,
            default => $material->getAttribute($field),
        };
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
     * Écrit les valeurs dans la série avec leur provenance ; l'écartement remplit aussi `gauges`, les
     * constructeurs `builders`, comme l'import.
     *
     * @param  array<string, mixed>  $values
     * @param  array<string, string>  $provenance  {source, ref, url?}
     */
    private function write(Material $material, array $values, array $provenance): void
    {
        $sources = $material->provenance ?? [];
        foreach ($values as $field => $value) {
            $columns = match ($field) {
                'electrification' => ['electrification' => array_map(fn (string $key): array => self::ELECTRIFICATION[$key], (array) $value)],
                'gauge_mm' => ['gauge_mm' => (int) $value, 'gauges' => [['label' => self::GAUGES[(int) $value]['label'], 'mm' => (int) $value, 'wikidata_id' => self::GAUGES[(int) $value]['wikidata_id']]]],
                'builder' => ['builder' => (string) $value, 'builders' => array_map(
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
