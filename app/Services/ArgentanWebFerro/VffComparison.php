<?php

namespace App\Services\ArgentanWebFerro;

use App\Enums\ArgentanFactKind;
use App\Enums\FactDatePrecision;
use App\Enums\StationType;

/**
 * Comparaison d'un fait d'Argentan Web Ferroviaire avec ce que VFF sait déjà (lecture seule) :
 *   - événement : confirmé (même année ; même jour quand les deux sont au jour),
 *     contradictoire (autre date pour le même type et une section compatible), nouveau
 *     (aucun événement de ce type sur une section compatible), non comparé ;
 *   - arrêt : présent (même nom parmi les gares de la ligne), absent, nature différente.
 * Sections : même section (deux bornes communes) d'abord ; à défaut, un événement VFF de toute
 * la ligne ; une section de l'article absente de VFF, à une autre date, est « nouveau ».
 */
final class VffComparison
{
    public const CONFIRMED = 'confirmé';

    public const CONTRADICTORY = 'contradictoire';

    public const NEW = 'nouveau';

    public const NOT_COMPARED = 'non comparé';

    public const PRESENT = 'présent';

    public const ABSENT = 'absent de VFF';

    public const OTHER_NATURE = 'nature différente';

    /** Types VFF comparables à chaque type d'événement de l'article. */
    private const FAMILIES = [
        'opening' => ['opening', 'opening_passenger', 'opening_freight'],
        'opening_passenger' => ['opening', 'opening_passenger'],
        'opening_freight' => ['opening', 'opening_freight'],
        'closure_passenger' => ['closure_passenger'],
        'closure_freight' => ['closure_freight'],
        'closure' => ['closure'],
        'dismantling' => ['dismantling', 'conversion'],
        'declassification' => ['declassification'],
        'reopening' => ['reopening', 'reopening_passenger', 'reopening_freight'],
        'reopening_passenger' => ['reopening', 'reopening_passenger'],
        'concession' => ['concession'],
        'electrification' => ['electrification'],
    ];

    /**
     * @param  array<int, list<array{type: string, date: string, precision: string, source: ?string, from: ?string, to: ?string, url: ?string}>>  $eventsByLine
     * @param  array<int, list<array{name: string, type: string}>>  $stationsByLine
     */
    public function __construct(private readonly array $eventsByLine, private readonly array $stationsByLine) {}

    /**
     * @param  list<int>  $lines  ligne du fait, ou toutes les lignes de l'article si le fait n'est pas rattaché
     * @return array{status: string, detail: string, vff: ?array{type: string, date: string, precision: string, source: ?string, from: ?string, to: ?string, url: ?string}}
     */
    public function compare(ExtractedFact $fact, array $lines): array
    {
        if ($lines === []) {
            return ['status' => self::NOT_COMPARED, 'detail' => 'aucune ligne VFF', 'vff' => null];
        }

        return match ($fact->kind) {
            ArgentanFactKind::Stop => $this->stop($fact, $lines),
            ArgentanFactKind::Event => $this->event($fact, $lines),
            ArgentanFactKind::Operator => ['status' => self::NOT_COMPARED, 'detail' => 'exploitants : pas de table équivalente', 'vff' => null],
        };
    }

    /** @param  list<int>  $lines */
    private function stop(ExtractedFact $fact, array $lines): array
    {
        foreach ($lines as $lineId) {
            foreach ($this->stationsByLine[$lineId] ?? [] as $station) {
                if (! PlaceMatcher::same((string) $fact->stopName, $station['name'])) {
                    continue;
                }
                if ($fact->stopNature !== null && $fact->stopNature !== StationType::Embranchement && $fact->stopNature->value !== $station['type']) {
                    return ['status' => self::OTHER_NATURE, 'detail' => "VFF : {$station['name']} ({$station['type']}), article : {$fact->stopNature->value}", 'vff' => null];
                }

                return ['status' => self::PRESENT, 'detail' => "VFF : {$station['name']}", 'vff' => null];
            }
        }

        foreach ($lines as $lineId) {
            foreach ($this->stationsByLine[$lineId] ?? [] as $station) {
                if (PlaceMatcher::near((string) $fact->stopName, $station['name'])) {
                    return ['status' => self::ABSENT, 'detail' => "nom voisin dans VFF : {$station['name']} (même gare renommée ?)", 'vff' => null];
                }
            }
        }

        return ['status' => self::ABSENT, 'detail' => 'aucune gare de ce nom sur la ligne dans VFF', 'vff' => null];
    }

    /** @param  list<int>  $lines */
    private function event(ExtractedFact $fact, array $lines): array
    {
        $family = self::FAMILIES[$fact->type->value ?? ''] ?? null;
        if ($family === null) {
            return ['status' => self::NOT_COMPARED, 'detail' => 'type sans équivalent comparable ('.($fact->type->value ?? '?').')', 'vff' => null];
        }
        $sameType = [];
        foreach ($lines as $lineId) {
            foreach ($this->eventsByLine[$lineId] ?? [] as $e) {
                if (in_array($e['type'], $family, true)) {
                    $sameType[] = $e;
                }
            }
        }
        if ($sameType === []) {
            return ['status' => self::NEW, 'detail' => 'aucun événement de ce type dans VFF', 'vff' => null];
        }
        $hasSection = $fact->from !== null && $fact->to !== null;
        $sameSection = array_values(array_filter($sameType, fn (array $e) => $this->sameSection($fact, $e)));
        $wholeLine = array_values(array_filter($sameType, fn (array $e) => $e['from'] === null && $e['to'] === null));
        $compatible = match (true) {
            $sameSection !== [] => $sameSection,
            $hasSection => $wholeLine,
            default => $sameType,
        };
        if ($compatible === []) {
            return ['status' => self::NEW, 'detail' => 'VFF a ce type sur une autre section', 'vff' => null];
        }
        if ($fact->year === null) {
            return ['status' => self::CONFIRMED, 'detail' => 'fait non daté, attesté dans VFF', 'vff' => $compatible[0]];
        }
        usort($compatible, fn (array $a, array $b) => $this->distance($fact, $a) <=> $this->distance($fact, $b));
        $best = $compatible[0];
        $year = (int) substr($best['date'], 0, 4);
        $inRange = $fact->precision === FactDatePrecision::Range
            ? $year >= $fact->year && $year <= ($fact->yearTo ?? $fact->year)
            : $year === $fact->year;
        if (! $inRange && $hasSection && $sameSection === []) {
            // La section de l'article n'existe pas dans VFF, qui ne date que la ligne entière.
            return ['status' => self::NEW, 'detail' => sprintf('section absente de VFF, qui ne date que la ligne entière : %s (%s)', $best['date'], $best['source'] ?? '?'), 'vff' => $best];
        }
        if (! $inRange) {
            return ['status' => self::CONTRADICTORY, 'detail' => sprintf('VFF %s (%s), écart %+d an(s)', $best['date'], $best['source'] ?? '?', $year - $fact->year), 'vff' => $best];
        }
        $factDate = $fact->date();
        if ($fact->precision === FactDatePrecision::Day && $best['precision'] === 'day' && $best['date'] !== $factDate) {
            return ['status' => self::CONTRADICTORY, 'detail' => "même année, jour différent : VFF {$best['date']}", 'vff' => $best];
        }
        if (in_array($fact->precision, [FactDatePrecision::Day, FactDatePrecision::Month], true) && in_array($best['precision'], ['day', 'month'], true)
            && substr($best['date'], 0, 7) !== substr((string) $factDate, 0, 7)) {
            return ['status' => self::CONTRADICTORY, 'detail' => "même année, mois différent : VFF {$best['date']}", 'vff' => $best];
        }
        $more = $fact->precision === FactDatePrecision::Day && $best['precision'] !== 'day' ? ' (l\'article donne le jour)' : '';

        return ['status' => self::CONFIRMED, 'detail' => "VFF {$best['date']} ({$best['source']}){$more}", 'vff' => $best];
    }

    /**
     * Même section : les deux bornes de l'article se retrouvent (dans un ordre ou l'autre) dans
     * l'événement VFF. Une seule borne commune désigne une section voisine, pas la même.
     *
     * @param  array{from: ?string, to: ?string}  $e
     */
    private function sameSection(ExtractedFact $fact, array $e): bool
    {
        if ($fact->from === null || $fact->to === null || $e['from'] === null || $e['to'] === null) {
            return false;
        }

        return PlaceMatcher::same($fact->from, $e['from']) && PlaceMatcher::same($fact->to, $e['to'])
            || PlaceMatcher::same($fact->from, $e['to']) && PlaceMatcher::same($fact->to, $e['from']);
    }

    /** @param  array{date: string}  $e */
    private function distance(ExtractedFact $fact, array $e): int
    {
        return abs((int) strtotime($e['date']) - (int) strtotime((string) $fact->date()));
    }
}
