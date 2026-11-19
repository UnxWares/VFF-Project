<?php

namespace App\Services;

use App\Enums\EraChange;
use App\Enums\RouteComparisonOutcome;
use App\Services\NetworkGraph\RouteException;
use Illuminate\Support\Facades\DB;

/**
 * Comparaison d'un même trajet (gare A → gare B) à deux époques : « Angoulême - Limoges en
 * 1930 et aujourd'hui ».
 *
 * L'itinéraire de chaque époque est celui de PathfindingService, sans autre calcul. Un trajet
 * impossible à une époque (aucun chemin, gare hors du réseau exploité) n'est pas une erreur :
 * l'époque le dit (`route` nul, `unavailable` avec le code et le message). Les autres refus
 * (gare inconnue ou ambiguë, ère introuvable, graphe pas calculé, même gare, même ère) valent
 * pour toute la comparaison et sont relancés.
 *
 * Les deux époques sont rangées dans l'ordre chronologique (`earlier`, `later`), quel que soit
 * l'ordre de la demande.
 */
class RouteComparison
{
    /** Refus propres à une époque : le trajet y est impossible, la comparaison continue. */
    public const UNAVAILABLE_CODES = ['no_path', 'station_not_in_network'];

    /** Part du tracé en service au-delà de laquelle une ligne compte comme « en service ». */
    public const OPEN_SHARE = 0.9;

    public function __construct(private readonly PathfindingService $pathfinding) {}

    /**
     * @param  array{heritage?: bool, traffic?: string}  $options
     * @return array<string, mixed>
     *
     * @throws RouteException
     */
    public function compare(string $from, string $to, ?int $year, int $otherYear, array $options = []): array
    {
        $sides = [
            ['requested' => $year, 'era' => $this->pathfinding->resolveEra($year)],
            ['requested' => $otherYear, 'era' => $this->pathfinding->resolveEra($otherYear)],
        ];
        if ((int) $sides[0]['era']->year === (int) $sides[1]['era']->year) {
            throw new RouteException('same_era', 422,
                "Les deux années désignent la même carte du réseau ({$sides[0]['era']->year}) : choisissez deux époques différentes.",
                ['era_year' => (int) $sides[0]['era']->year]);
        }
        usort($sides, fn (array $a, array $b): int => (int) $a['era']->year <=> (int) $b['era']->year);

        [$earlier, $later] = array_map(fn (array $side): array => $this->atEra($from, $to, $side['requested'], $side['era'], $options), $sides);

        $presence = $this->linePresence(
            array_values(array_filter(array_merge(
                array_column($earlier['route']['lines'] ?? [], 'line_slug'),
                array_column($later['route']['lines'] ?? [], 'line_slug'),
            ))),
            [(int) $sides[0]['era']->id => 'earlier', (int) $sides[1]['era']->id => 'later'],
        );

        return [
            'from' => $earlier['route']['from'] ?? $later['route']['from'] ?? $this->stationBySlug($from),
            'to' => $earlier['route']['to'] ?? $later['route']['to'] ?? $this->stationBySlug($to),
            'earlier' => $earlier,
            'later' => $later,
            'differences' => self::differences($earlier['route'], $later['route'], $presence),
        ];
    }

    /**
     * Différences entre deux itinéraires (fonction pure).
     *
     * Lignes regroupées par slug (une ligne quittée puis reprise compte une fois, longueurs
     * additionnées) ; pour une ligne empruntée à une seule époque, `other_era` dit ce qu'elle
     * était à l'autre (linePresence) : `open` (en service, mais pas sur le plus court chemin),
     * `partly_open` (en service sur une partie seulement), `closed` (fermée, voie en place) ou
     * `none` (déposée, pas encore construite ou absente de la carte).
     *
     * @param  array<string, mixed>|null  $earlier  itinéraire de la première époque (ou null)
     * @param  array<string, mixed>|null  $later  itinéraire de la seconde époque (ou null)
     * @param  array<string, array{earlier?: string, later?: string}>  $presence  slug de ligne => présence par époque
     * @return array<string, mixed>
     */
    public static function differences(?array $earlier, ?array $later, array $presence): array
    {
        $outcome = RouteComparisonOutcome::fromAvailability($earlier !== null, $later !== null);
        $a = self::groupLines($earlier['lines'] ?? []);
        $b = self::groupLines($later['lines'] ?? []);

        $change = null;
        $percent = null;
        if ($earlier !== null && $later !== null) {
            $change = (int) $later['distance_m'] - (int) $earlier['distance_m'];
            $percent = (int) $earlier['distance_m'] > 0 ? round($change / (int) $earlier['distance_m'] * 100, 1) : null;
        }

        $withPresence = fn (array $lines, string $era): array => array_values(array_map(
            fn (array $l): array => $l + ['other_era' => $l['line_slug'] !== null ? ($presence[$l['line_slug']][$era] ?? 'none') : null],
            $lines,
        ));
        $stationsA = array_column($earlier['stations'] ?? [], 'slug');
        $stationsB = array_column($later['stations'] ?? [], 'slug');

        return [
            'outcome' => $outcome->value,
            'distance_change_m' => $change,
            'distance_change_km' => $change === null ? null : round($change / 1000, 1),
            'distance_change_percent' => $percent,
            'same_path' => $earlier !== null && $later !== null && $stationsA === $stationsB,
            'shared_lines' => array_values(array_intersect_key($b, $a)),
            'lines_only_earlier' => $withPresence(array_diff_key($a, $b), 'later'),
            'lines_only_later' => $withPresence(array_diff_key($b, $a), 'earlier'),
            'shared_stations' => count(array_intersect($stationsA, $stationsB)),
        ];
    }

    /**
     * Lignes d'un itinéraire regroupées par slug (sans slug : par code, sinon par nom), dans
     * l'ordre de première apparition.
     *
     * @param  list<array<string, mixed>>  $legs
     * @return array<string, array{line_slug: ?string, line_name: ?string, line_code: ?string, length_m: int, length_km: float}>
     */
    private static function groupLines(array $legs): array
    {
        $out = [];
        foreach ($legs as $leg) {
            $key = $leg['line_slug'] ?? ('code:'.($leg['line_code'] ?? $leg['line_name'] ?? '?'));
            $out[$key] ??= ['line_slug' => $leg['line_slug'], 'line_name' => $leg['line_name'], 'line_code' => $leg['line_code'], 'length_m' => 0, 'length_km' => 0.0];
            $out[$key]['length_m'] += (int) $leg['length_m'];
            $out[$key]['length_km'] = round($out[$key]['length_m'] / 1000, 1);
        }

        return $out;
    }

    /**
     * Itinéraire à une époque, ou la raison qui le rend impossible.
     *
     * @param  object{id: int, year: int, is_current: bool}  $era
     * @param  array{heritage?: bool, traffic?: string}  $options
     * @return array{requested_year: ?int, era_year: int, era_is_current: bool, route: array<string, mixed>|null, unavailable: array<string, mixed>|null}
     */
    private function atEra(string $from, string $to, ?int $requested, object $era, array $options): array
    {
        $base = ['requested_year' => $requested, 'era_year' => (int) $era->year, 'era_is_current' => (bool) $era->is_current];
        try {
            return $base + ['route' => $this->pathfinding->route($from, $to, $requested, $options), 'unavailable' => null];
        } catch (RouteException $e) {
            if (! in_array($e->errorCode, self::UNAVAILABLE_CODES, true)) {
                throw $e;
            }

            return $base + ['route' => null, 'unavailable' => ['error' => $e->errorCode, 'message' => $e->getMessage(), ...$e->details]];
        }
    }

    /**
     * Présence de chaque ligne aux deux ères, d'après les longueurs de ses tronçons classés par
     * EraChange : `open` si au moins OPEN_SHARE de ses tronçons connus à l'ère sont en service,
     * `partly_open` si une partie seulement l'est, sinon `closed` si une partie est fermée voie en
     * place, sinon `none`.
     *
     * @param  list<string>  $slugs
     * @param  array<int, string>  $eras  identifiant d'ère => 'earlier' | 'later'
     * @return array<string, array<string, string>>
     */
    private function linePresence(array $slugs, array $eras): array
    {
        if ($slugs === []) {
            return [];
        }
        $presence = EraChange::presenceSql('ls.status');
        $rows = DB::table('line_segments as ls')
            ->join('lines as l', 'l.id', '=', 'ls.line_id')
            ->whereIn('l.slug', array_unique($slugs))
            ->whereIn('ls.era_id', array_keys($eras))
            ->groupBy('l.slug', 'ls.era_id')
            ->selectRaw(<<<SQL
                l.slug, ls.era_id,
                COALESCE(SUM(ST_Length(ls.geom::geography)) FILTER (WHERE ({$presence}) = 'open'), 0) AS open_m,
                COALESCE(SUM(ST_Length(ls.geom::geography)) FILTER (WHERE ({$presence}) = 'closed'), 0) AS closed_m,
                COALESCE(SUM(ST_Length(ls.geom::geography)), 0) AS total_m
                SQL)
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[$r->slug][$eras[(int) $r->era_id]] = self::presenceOf((float) $r->open_m, (float) $r->closed_m, (float) $r->total_m);
        }

        return $out;
    }

    /** Présence d'une ligne d'après les longueurs (m) de ses tronçons à une ère (fonction pure). */
    public static function presenceOf(float $openM, float $closedM, float $totalM): string
    {
        return match (true) {
            $totalM > 0 && $openM >= self::OPEN_SHARE * $totalM => 'open',
            $openM > 0 => 'partly_open',
            $closedM > 0 => 'closed',
            default => 'none',
        };
    }

    /** @return array{slug: string, name: string}|null */
    private function stationBySlug(string $slug): ?array
    {
        $s = DB::table('stations')->where('slug', trim($slug))->first(['slug', 'name']);

        return $s ? ['slug' => (string) $s->slug, 'name' => (string) $s->name] : null;
    }
}
