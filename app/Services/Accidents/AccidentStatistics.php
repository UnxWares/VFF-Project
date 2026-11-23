<?php

namespace App\Services\Accidents;

use App\Enums\AccidentKind;
use App\Models\Accident;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * « Les accidents en chiffres » : agrégats des accidents publiés (mêmes règles que la liste : sans doublons ni
 * heurts non confirmés), par décennie, par nature, part des passages à niveau, bilans les plus lourds.
 * Communs à la page `/accidents/statistics` et à `GET /api/v1/accidents/statistics`.
 *
 * Filtres : `from` et `to` (années, bornes incluses), `kind` (nature : décennies et bilans les plus lourds seulement,
 * la ventilation par nature et la part des passages à niveau restent sur toutes les natures), `review` (`ok` :
 * seulement les fiches dont la date et le lieu sont confirmés ; absent : toutes, les fiches « à vérifier » comprises
 * et comptées à part dans `to_review`). Un bilan inconnu n'est jamais estimé : les sommes ne portent que sur les
 * bilans connus, dont le nombre est donné (`deaths_known`, `injuries_known`).
 */
final class AccidentStatistics
{
    /** Durée de garde des agrégats (secondes) : un import récent finit par y paraître. */
    public const TTL = 3600;

    /** Nombre de bilans les plus lourds listés. */
    public const DEADLIEST = 15;

    /**
     * @param  array{from?: int|string|null, to?: int|string|null, kind?: string|null, review?: string|null}  $filters  filtres validés
     * @return array<string, mixed>
     */
    public function get(array $filters = []): array
    {
        $f = $this->normalise($filters);

        return Cache::remember('accident-statistics:v1:'.md5((string) json_encode($f)), self::TTL, fn (): array => $this->compute($f));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{from: ?int, to: ?int, kind: ?string, review: ?string}
     */
    private function normalise(array $filters): array
    {
        $kind = $filters['kind'] ?? null;

        return [
            'from' => isset($filters['from']) && $filters['from'] !== '' ? (int) $filters['from'] : null,
            'to' => isset($filters['to']) && $filters['to'] !== '' ? (int) $filters['to'] : null,
            'kind' => is_string($kind) && AccidentKind::tryFrom($kind) !== null ? $kind : null,
            'review' => ($filters['review'] ?? null) === 'ok' ? 'ok' : null,
        ];
    }

    /**
     * @param  array{from: ?int, to: ?int, kind: ?string, review: ?string}  $f
     * @return array<string, mixed>
     */
    private function compute(array $f): array
    {
        // Lecture bornée : trente secondes au plus, sur la seule transaction de ce calcul.
        return DB::transaction(function () use ($f): array {
            DB::statement("SET LOCAL statement_timeout = '30s'");

            $base = fn () => Accident::query()->betweenYears($f['from'], $f['to'])
                ->when($f['review'] === 'ok', fn ($q) => $q->where('accidents.review_status', 'ok'));

            // Une seule lecture groupée par décennie et nature : tout le reste s'en déduit.
            $cells = $base()->toBase()->selectRaw(
                '(EXTRACT(YEAR FROM accidents.occurred_on)::int / 10) * 10 AS decade, accidents.kind AS kind, COUNT(*) AS n,'
                ."COUNT(*) FILTER (WHERE accidents.review_status = 'to_review') AS to_review,"
                .'COALESCE(SUM(accidents.deaths), 0) AS deaths, COUNT(accidents.deaths) AS deaths_known,'
                .'COALESCE(SUM(accidents.injuries), 0) AS injuries, COUNT(accidents.injuries) AS injuries_known'
            )->groupBy('decade', 'accidents.kind')->get();

            $deadliest = $base()
                ->when($f['kind'] !== null, fn ($q) => $q->where('accidents.kind', $f['kind']))
                ->where('accidents.deaths', '>', 0)
                ->orderByDesc('accidents.deaths')->orderBy('accidents.occurred_on')->orderBy('accidents.id')
                ->limit(self::DEADLIEST)
                ->get(['accidents.slug', 'accidents.name', 'accidents.occurred_on', 'accidents.kind', 'accidents.commune_name', 'accidents.department_code', 'accidents.deaths', 'accidents.injuries', 'accidents.review_status'])
                ->map(fn (Accident $a): array => [
                    'slug' => $a->slug,
                    'name' => $a->nameIn('fr'),
                    'names' => $a->name,
                    'date' => $a->occurred_on->toDateString(),
                    'kind' => $a->kind->value,
                    'commune' => $a->commune_name,
                    'department_code' => $a->department_code,
                    'deaths' => $a->deaths,
                    'injuries' => $a->injuries,
                    'review_status' => $a->review_status->value,
                ])->all();

            return $this->assemble($f, $cells->all(), $deadliest);
        });
    }

    /**
     * @param  array{from: ?int, to: ?int, kind: ?string, review: ?string}  $f
     * @param  list<object>  $cells
     * @param  list<array<string, mixed>>  $deadliest
     * @return array<string, mixed>
     */
    private function assemble(array $f, array $cells, array $deadliest): array
    {
        $empty = ['accidents' => 0, 'to_review' => 0, 'deaths' => 0, 'deaths_known' => 0, 'injuries' => 0, 'injuries_known' => 0];
        $add = function (array $into, object $c): array {
            $into['accidents'] += (int) $c->n;
            $into['to_review'] += (int) $c->to_review;
            $into['deaths'] += (int) $c->deaths;
            $into['deaths_known'] += (int) $c->deaths_known;
            $into['injuries'] += (int) $c->injuries;
            $into['injuries_known'] += (int) $c->injuries_known;

            return $into;
        };

        $byDecade = [];
        $levelCrossing = [];
        $allKinds = [];
        $decadeAll = [];
        foreach ($cells as $c) {
            $d = (int) $c->decade;
            $decadeAll[$d] = $add($decadeAll[$d] ?? $empty, $c);
            if ($c->kind === AccidentKind::LevelCrossing->value) {
                $levelCrossing[$d] = (int) $c->n;
            }
            $allKinds[$c->kind] = $add($allKinds[$c->kind] ?? $empty, $c);
            if ($f['kind'] === null || $f['kind'] === $c->kind) {
                $byDecade[$d] = $add($byDecade[$d] ?? $empty, $c);
            }
        }

        $decades = [];
        $totals = $empty;
        if ($decadeAll !== []) {
            for ($d = min(array_keys($decadeAll)); $d <= max(array_keys($decadeAll)); $d += 10) {
                $row = $byDecade[$d] ?? $empty;
                $all = $decadeAll[$d]['accidents'] ?? 0;
                $lc = $levelCrossing[$d] ?? 0;
                $decades[] = ['decade' => $d, 'from_year' => $d, 'to_year' => $d + 9] + $row + [
                    'all_kinds' => $all,
                    'level_crossing' => $lc,
                    'level_crossing_share' => $all > 0 ? round($lc / $all, 3) : null,
                ];
                foreach (array_keys($totals) as $k) {
                    $totals[$k] += $row[$k];
                }
            }
        }

        $kinds = [];
        foreach (AccidentKind::cases() as $case) {
            if (isset($allKinds[$case->value])) {
                $kinds[] = ['kind' => $case->value] + $allKinds[$case->value];
            }
        }
        usort($kinds, fn (array $a, array $b): int => $b['accidents'] <=> $a['accidents']);

        $range = Accident::query()->betweenYears($f['from'], $f['to'])
            ->when($f['review'] === 'ok', fn ($q) => $q->where('accidents.review_status', 'ok'))
            ->when($f['kind'] !== null, fn ($q) => $q->where('accidents.kind', $f['kind']))
            ->toBase()->selectRaw('MIN(accidents.occurred_on) AS first, MAX(accidents.occurred_on) AS last')->first();

        return [
            'computed_at' => Carbon::now()->toIso8601String(),
            'filters' => $f,
            'totals' => $totals + ['first_date' => $range->first ?? null, 'last_date' => $range->last ?? null],
            'decades' => $decades,
            'kinds' => $kinds,
            'deadliest' => $deadliest,
        ];
    }
}
