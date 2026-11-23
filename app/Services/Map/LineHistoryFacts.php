<?php

namespace App\Services\Map;

use App\Mcp\Support\Attribution;
use App\Mcp\Support\NonCommercialSources;
use Illuminate\Support\Facades\DB;

/**
 * L'histoire d'une ligne en quelques années, pour la phrase « ouverte en 1869, fermée aux voyageurs en 1938 » de la
 * page « Autour de moi » : l'ouverture, la fermeture aux voyageurs, au fret, la fermeture complète et le déclassement
 * ou la dépose, tirés des événements datés de la ligne (line_events). Une fermeture suivie d'une réouverture du même
 * trafic n'est pas comptée. Les faits d'une source non commerciale ne sont jamais lus.
 */
final class LineHistoryFacts
{
    /** Types d'événements lus, rangés par fait. */
    private const KINDS = [
        'opening' => 'opened', 'opening_passenger' => 'opened_passenger',
        'closure_passenger' => 'closed_passenger', 'closure_freight' => 'closed_freight', 'closure' => 'closed',
        'dismantling' => 'dismantled',
        'reopening' => 'reopened', 'reopening_passenger' => 'reopened_passenger', 'reopening_freight' => 'reopened_freight',
    ];

    /** Portions décrites au plus par ligne. */
    public const MAX_SECTIONS = 3;

    public function __construct(private readonly Attribution $attribution, private readonly NonCommercialSources $nonCommercial) {}

    /**
     * @param  list<int>  $lineIds
     * @return array{facts: array<int, array<string, mixed>>, providers: list<?string>} années par ligne (`opened`, `closed_passenger`,
     *                                                                                  `closed_freight`, `closed`, `dismantled`) et fournisseurs des sources lues
     */
    public function forLines(array $lineIds): array
    {
        if ($lineIds === []) {
            return ['facts' => [], 'providers' => []];
        }
        $rows = DB::table('line_events')
            ->join('lines', 'lines.id', '=', 'line_events.line_id')
            ->whereIn('line_events.line_id', $lineIds)
            ->whereIn('line_events.type', array_keys(self::KINDS))
            ->whereRaw($this->nonCommercial->sqlKept('line_events', 'line_events.source', 'line_events.source_url'))
            ->orderBy('line_events.event_date')->orderBy('line_events.id')
            ->get(['line_events.line_id', 'line_events.type', 'line_events.event_date', 'line_events.source', 'line_events.source_url', 'line_events.section_from', 'line_events.section_to', 'line_events.section_pk_start_m', 'line_events.section_length_m', 'lines.length_official_m']);

        $dates = [];
        $sections = [];
        $providers = [];
        foreach ($rows as $r) {
            $kind = self::KINDS[$r->type];
            $year = (int) substr((string) $r->event_date, 0, 4);
            // Un événement qui ne porte que sur une portion n'est jamais un fait de toute la ligne (ni une fermeture ni
            // une dépose : l'état d'aujourd'hui vient du moteur) ; seule l'ouverture reste celle de la ligne.
            if (! in_array($kind, ['opened', 'opened_passenger'], true) && self::isPartial($r)) {
                if (in_array($kind, ['closed_passenger', 'closed_freight', 'closed', 'dismantled'], true)) {
                    $named = $r->section_from !== null && $r->section_to !== null && $r->section_from !== $r->section_to;
                    $sections[(int) $r->line_id][$kind.$year.($named ? $r->section_from.$r->section_to : '')] = [
                        'key' => $kind, 'year' => $year, 'from' => $named ? $r->section_from : null, 'to' => $named ? $r->section_to : null,
                    ];
                }
            } else {
                $dates[(int) $r->line_id][$kind][] = $year;
            }
            $providers[] = $this->attribution->providerFor('line_events', $r->source, $r->source_url);
        }

        $facts = [];
        foreach ($dates as $id => $by) {
            $facts[$id] = $this->one($by);
        }
        foreach ($sections as $id => $list) {
            $facts[$id]['sections'] = array_slice(array_values($list), 0, self::MAX_SECTIONS);
        }

        return ['facts' => $facts, 'providers' => array_values(array_unique($providers, SORT_REGULAR))];
    }

    /**
     * Accorde l'histoire à l'état d'aujourd'hui, qui vient du moteur et non des événements : une ligne en service
     * n'est pas « fermée » ni « déposée » en entier ; son événement de fermeture ou de dépose passe en portion sans nom
     * (« une section fermée en 1942 »).
     *
     * @param  array<string, mixed>  $facts
     * @return array<string, mixed>
     */
    public static function reconcile(array $facts, string $status): array
    {
        if ($status !== 'in_service') {
            return $facts;
        }
        $sections = $facts['sections'] ?? [];
        foreach (['closed', 'dismantled'] as $kind) {
            if (isset($facts[$kind])) {
                array_unshift($sections, ['key' => $kind, 'year' => $facts[$kind], 'from' => null, 'to' => null]);
                unset($facts[$kind]);
            }
        }
        if ($sections !== []) {
            $facts['sections'] = array_slice($sections, 0, self::MAX_SECTIONS);
        }

        return $facts;
    }

    /** L'événement ne concerne-t-il qu'une portion : portion nommée ou repérée par PK, ou plus courte que la ligne. */
    private static function isPartial(object $r): bool
    {
        return $r->section_from !== null || $r->section_to !== null || $r->section_pk_start_m !== null
            || ($r->section_length_m !== null && $r->length_official_m !== null && $r->section_length_m < 0.9 * $r->length_official_m);
    }

    /**
     * @param  array<string, list<int>>  $by  années triées par fait
     * @return array<string, int>
     */
    private function one(array $by): array
    {
        $first = fn (string ...$kinds): ?int => ($years = array_merge(...array_map(fn (string $k): array => $by[$k] ?? [], $kinds))) === [] ? null : min($years);
        $last = fn (string $k): ?int => isset($by[$k]) ? max($by[$k]) : null;
        // Fermeture suivie d'une réouverture du même trafic : écartée.
        $closing = fn (string $closed, string ...$reopened): ?int => ($year = $last($closed)) !== null
            && ($again = max(array_merge([0], ...array_map(fn (string $k): array => $by[$k] ?? [], $reopened)))) <= $year ? $year : null;
        $facts = [
            'opened' => $first('opened', 'opened_passenger'),
            'closed_passenger' => $closing('closed_passenger', 'reopened_passenger', 'reopened'),
            'closed_freight' => $closing('closed_freight', 'reopened_freight', 'reopened'),
            'closed' => $closing('closed', 'reopened'),
            'dismantled' => $last('dismantled'),
        ];

        return array_filter($facts, fn (?int $y): bool => $y !== null);
    }
}
