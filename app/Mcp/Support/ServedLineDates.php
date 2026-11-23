<?php

namespace App\Mcp\Support;

use Illuminate\Support\Facades\DB;

/**
 * Dates de ligne précalculées (fiche d'un département : première ouverture, dernière fermeture, électrification,
 * réouvertures) revues pour le serveur MCP. Le cache de ces fiches ne garde pas la source de chaque date : une date
 * n'est servie que si un événement d'une source servie (NonCommercialSources) de la même ligne et de la même nature la
 * donne. Même jour : la date reste ; même année seulement : elle prend la date et la précision de cet événement ;
 * sinon elle devient nulle. Une requête pour toutes les lignes de la fiche.
 *
 * Le site, l'API REST et GraphQL gardent ces dates avec la mention de leur source.
 */
final class ServedLineDates
{
    /** Nature d'une date et types d'événement qui la donnent. */
    public const KINDS = [
        'opened' => ['opening', 'opening_passenger', 'opening_freight'],
        'closed' => ['closure', 'closure_passenger', 'closure_freight'],
        'electrified' => ['electrification'],
        'reopened' => ['reopening', 'reopening_passenger', 'reopening_freight'],
    ];

    /** @var array<string, list<array{date: string, precision: string, type: string}>> dates servies par « slug|nature » */
    private array $served = [];

    public function __construct(private readonly NonCommercialSources $nonCommercial) {}

    /**
     * Lit les dates servies des lignes données (slugs). À appeler avant date().
     *
     * @param  list<?string>  $slugs  slugs des lignes (null ignorés)
     */
    public function load(array $slugs): self
    {
        $this->served = [];
        $slugs = array_values(array_unique(array_filter($slugs, fn (?string $s): bool => $s !== null && $s !== '')));
        if ($slugs === []) {
            return $this;
        }
        $kindOf = [];
        foreach (self::KINDS as $kind => $types) {
            foreach ($types as $type) {
                $kindOf[$type] = $kind;
            }
        }
        $rows = DB::table('line_events')->join('lines', 'lines.id', '=', 'line_events.line_id')
            ->whereIn('lines.slug', $slugs)->whereIn('line_events.type', array_keys($kindOf))->whereNotNull('line_events.event_date')
            ->whereRaw($this->nonCommercial->sqlKept('line_events', 'line_events.source', 'line_events.source_url'))
            ->orderBy('line_events.event_date')->orderBy('line_events.id')
            ->get(['lines.slug', 'line_events.type', 'line_events.event_date', 'line_events.date_precision']);
        foreach ($rows as $r) {
            $this->served[$r->slug.'|'.$kindOf[$r->type]][] = ['date' => substr((string) $r->event_date, 0, 10), 'precision' => (string) ($r->date_precision ?? 'day'),
                'type' => (string) $r->type];
        }

        return $this;
    }

    /**
     * La date servie à la place de celle-ci (nature de KINDS) : la même, celle d'un événement servi de la même année, ou
     * null. Une fermeture qui porte sa portée (`scope` : all, passenger, freight, DepartmentFigures::lineDates) ne
     * prend qu'un événement du même type (closure, closure_passenger, closure_freight). Les autres clés de la date sont
     * gardées.
     *
     * @param  array<string, mixed>|null  $dated  {date, precision, …}
     * @return array<string, mixed>|null
     */
    public function date(?string $slug, string $kind, ?array $dated): ?array
    {
        if ($dated === null || $slug === null || ! isset($dated['date'])) {
            return $dated;
        }
        $date = substr((string) $dated['date'], 0, 10);
        $candidates = $this->served[$slug.'|'.$kind] ?? [];
        if ($kind === 'closed' && is_string($dated['scope'] ?? null)) {
            $type = $dated['scope'] === 'all' ? 'closure' : 'closure_'.$dated['scope'];
            $candidates = array_values(array_filter($candidates, fn (array $c): bool => $c['type'] === $type));
        }
        foreach ($candidates as $c) {
            if ($c['date'] === $date) {
                return $dated;
            }
        }
        $rank = ['day' => 0, 'month' => 1, 'year' => 2];
        $best = null;
        foreach ($candidates as $c) {
            if (substr($c['date'], 0, 4) === substr($date, 0, 4) && ($best === null || ($rank[$c['precision']] ?? 3) < ($rank[$best['precision']] ?? 3))) {
                $best = $c;
            }
        }

        return $best === null ? null : array_replace($dated, ['date' => $best['date'], 'precision' => $best['precision']]);
    }
}
