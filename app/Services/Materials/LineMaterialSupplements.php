<?php

namespace App\Services\Materials;

/**
 * Liens et périodes lus à la main dans une phrase citée (database/data/line_material_supplements.php), quand la
 * lecture automatique ne peut pas les prendre, et périodes importées qu'une autre source contredit.
 *
 * Les preuves ajoutées portent `reviewed` : relues à la main, elles passent par les mêmes exclusions et règles
 * (écartement, électrification) que les autres. Une entrée dont la ligne ou la série n'existe pas dans VFF ne vaut
 * rien et est signalée (unresolvedEntries()).
 */
final class LineMaterialSupplements
{
    private const FILE = 'line_material_supplements.php';

    /**
     * @param  list<array{line_id: int, material_id: int, evidence: array<string, mixed>, from: ?array{date: string, precision: string}, to: ?array{date: string, precision: string}, page: array{title: string, revid: int}, heading: string, origin: string}>  $links
     * @param  array<string, list<array{start: ?string, end: ?string}>>  $dropped  « ligne:série » → périodes retirées
     * @param  list<string>  $unresolved
     */
    public function __construct(
        private readonly array $links = [],
        private readonly array $dropped = [],
        private readonly array $unresolved = [],
    ) {}

    /**
     * @param  array{links?: list<array<string, mixed>>, dropped_periods?: list<array<string, mixed>>}|null  $data  null : le fichier de données
     */
    public static function fromDatabase(?array $data = null): self
    {
        $data ??= require database_path('data/'.self::FILE);
        $links = array_values($data['links'] ?? []);
        $dropped = array_values($data['dropped_periods'] ?? []);
        $all = [...$links, ...$dropped];
        if ($all === []) {
            return new self;
        }
        [$lineIds, $materialIds] = LineMaterialExclusions::lookup($all);

        $resolved = [];
        foreach ($links as $e) {
            if (! isset($lineIds[$e['line']], $materialIds[$e['material']])) {
                continue;
            }
            $page = ['title' => (string) $e['article'], 'revid' => (int) $e['revid']];
            $evidence = array_filter([
                'kind' => (string) $e['kind'],
                'article' => $page['title'],
                'revid' => $page['revid'],
                'section' => (string) $e['section'],
                'quote' => (string) $e['quote'],
                'url' => 'https://fr.wikipedia.org/w/index.php?title='.rawurlencode(str_replace(' ', '_', $page['title'])).'&oldid='.$page['revid'],
                'doubt' => WikipediaMaterialSections::uncertainSection((string) $e['section']),
                'reviewed' => true,
            ], fn ($v): bool => $v !== null);
            $resolved[] = [
                'line_id' => $lineIds[$e['line']],
                'material_id' => $materialIds[$e['material']],
                'evidence' => $evidence,
                'from' => $e['from'] ?? null,
                'to' => $e['to'] ?? null,
                'page' => $page,
                'heading' => (string) $e['section'],
                'origin' => $e['kind'] === LineMaterialLinker::KIND_LINE_ARTICLE ? MaterialPeriodExtractor::MODE_LINE_ARTICLE : MaterialPeriodExtractor::MODE_MATERIAL_ARTICLE,
            ];
        }
        $byKey = [];
        foreach ($dropped as $e) {
            if (isset($lineIds[$e['line']], $materialIds[$e['material']]) && trim((string) $e['source']) !== '') {
                $byKey[$lineIds[$e['line']].':'.$materialIds[$e['material']]][] = ['start' => $e['start'], 'end' => $e['end']];
            }
        }
        // Une entrée de lien a pour source son article ; une période retirée, la source qui la contredit.
        $sourced = array_map(fn (array $e): array => $e + ['source' => (string) ($e['article'] ?? '')], $all);

        return new self($resolved, $byKey, LineMaterialExclusions::unresolved($sourced, self::FILE));
    }

    /**
     * Preuves ajoutées, une par entrée rattachée.
     *
     * @return list<array{line_id: int, material_id: int, evidence: array<string, mixed>}>
     */
    public function evidence(): array
    {
        return array_map(fn (array $l): array => ['line_id' => $l['line_id'], 'material_id' => $l['material_id'], 'evidence' => $l['evidence']], $this->links);
    }

    /**
     * Périodes datées par les entrées, au format des faits de MaterialPeriodCollector (relues : `reviewed`).
     *
     * @return list<array<string, mixed>>
     */
    public function periods(): array
    {
        $out = [];
        foreach ($this->links as $l) {
            if ($l['from'] === null && $l['to'] === null) {
                continue;
            }
            $out[] = [
                'line_id' => $l['line_id'], 'material_id' => $l['material_id'], 'origin' => $l['origin'], 'page' => $l['page'], 'heading' => $l['heading'],
                'from' => $l['from'], 'to' => $l['to'], 'confidence' => 'high', 'excerpt' => mb_strimwidth((string) $l['evidence']['quote'], 0, 255, '…'), 'reviewed' => true,
            ];
        }

        return $out;
    }

    /** Une période importée de ce couple est-elle retirée (bornes identiques) ? */
    public function drops(int $lineId, int $materialId, ?string $start, ?string $end): bool
    {
        foreach ($this->dropped["{$lineId}:{$materialId}"] ?? [] as $d) {
            if ($d['start'] === $start && $d['end'] === $end) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> entrées sans effet dans cette base */
    public function unresolvedEntries(): array
    {
        return $this->unresolved;
    }
}
