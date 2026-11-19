<?php

namespace App\Services\ArgentanWebFerro;

use App\Enums\ArgentanFactKind;

/**
 * Ligne VFF visée par un fait, parmi les lignes rattachées à l'article (curation) :
 * une seule ligne candidate → elle ; sinon celle dont les gares contiennent les bornes de la
 * section (ou l'arrêt) ; à égalité ou sans indice → douteux (aucune ligne, note explicative).
 * Lecture seule : les noms de gares viennent de station_lines (cf. ArgentanRepository).
 */
final class LineResolver
{
    /**
     * @param  array<int, list<string>>  $stationsByLine  ligne → noms de ses gares dans VFF
     */
    public function __construct(private readonly array $stationsByLine) {}

    /**
     * @param  list<int>  $candidates  lignes de l'article, dans l'ordre de la curation
     * @return array{line_id: ?int, match: string, note: ?string}
     */
    public function resolve(ExtractedFact $fact, array $candidates): array
    {
        if ($candidates === []) {
            return ['line_id' => null, 'match' => 'none', 'note' => 'article sans ligne VFF rattachée'];
        }
        if (count($candidates) === 1) {
            return ['line_id' => $candidates[0], 'match' => 'curated', 'note' => null];
        }
        $places = array_values(array_filter($fact->kind === ArgentanFactKind::Stop ? [$fact->stopName] : [$fact->from, $fact->to]));
        if ($places === []) {
            return ['line_id' => null, 'match' => 'doubtful', 'note' => 'article sur plusieurs lignes, fait sans section'];
        }
        $scores = [];
        foreach ($candidates as $lineId) {
            $names = $this->stationsByLine[$lineId] ?? [];
            $scores[$lineId] = count(array_filter($places, fn (string $p) => PlaceMatcher::find($p, $names) !== null));
        }
        arsort($scores);
        $best = (int) array_key_first($scores);
        $bestScore = $scores[$best];
        $tied = array_keys($scores, $bestScore, true);
        if ($bestScore === 0) {
            return ['line_id' => null, 'match' => 'doubtful', 'note' => 'aucune gare de la section sur les lignes de l\'article : '.implode(' / ', $places)];
        }
        if (count($tied) > 1) {
            // Section à cheval (ex. bifurcation commune) : la ligne qui porte les DEUX bornes l'emporte déjà ; sinon douteux.
            return ['line_id' => null, 'match' => 'doubtful', 'note' => 'plusieurs lignes possibles ('.count($tied).') : '.implode(' / ', $places)];
        }

        return ['line_id' => $best, 'match' => 'auto', 'note' => null];
    }
}
