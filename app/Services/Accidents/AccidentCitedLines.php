<?php

namespace App\Services\Accidents;

use App\Enums\AccidentLinkConfidence;
use App\Models\Accident;
use App\Models\AccidentLine;
use Illuminate\Support\Facades\DB;

/**
 * Rattache une fiche à la ligne que sa source cite, avec le titre de l'article de ligne cité (« Ligne de
 * Paris-Lyon à Marseille-Saint-Charles ») et le permalien de la source qui le cite. Un titre d'article est
 * un fait ; aucune phrase de la source n'est recopiée.
 *
 * Le titre est cherché dans les lignes de VFF (titre de leur article, éléments Wikidata de lignes), puis
 * après suivi des redirections de Wikipédia (« Ligne Lille - Thionville »). Un titre qu'aucune ligne ne
 * porte reste signalé, sans être deviné.
 *
 * Un rattachement fait par le tracé (AccidentLineMatcher) garde sa confiance et reçoit seulement la
 * citation. Une ligne citée mais que le tracé de l'époque n'a pas retrouvée près du lieu est ajoutée avec
 * la confiance la plus basse : la source dit la ligne, rien ne confirme sa position.
 */
final class AccidentCitedLines
{
    /** @var array<string, list<int>> titre d'article → lignes de VFF qui le portent */
    private array $resolved = [];

    /** @var array<string, string> titre cité → titre final après redirections (Wikipédia) */
    private array $redirects = [];

    /** @param list<Accident> $accidents  fiches avec leurs sources */
    public function prepare(AccidentSourceClient $client, array $accidents, AccidentVerificationResult $result): void
    {
        $titles = [];
        foreach ($accidents as $accident) {
            foreach ($this->citations($accident) as [$title]) {
                $titles[$title] = true;
            }
        }
        $titles = array_keys($titles);
        $unresolved = array_values(array_filter($titles, fn (string $t) => $this->lookup($t) === []));
        if ($unresolved !== []) {
            $this->redirects = $client->redirectTargets('fr', $unresolved);
        }
        foreach ($titles as $title) {
            if ($this->linesFor($title) === []) {
                $result->unresolvedLineTitles[] = $title;
            }
        }
        sort($result->unresolvedLineTitles);
    }

    /** Rattachements de la fiche d'après ses sources : crée ceux qui manquent, complète les autres. */
    public function apply(Accident $accident, AccidentVerificationResult $result): void
    {
        $links = AccidentLine::query()->where('accident_id', $accident->id)->get()->keyBy('line_id');
        $hasPrimary = $links->contains(fn (AccidentLine $l) => $l->is_primary);
        $cited = [];
        foreach ($this->citations($accident) as [$title, $url]) {
            foreach ($this->linesFor($title) as $lineId) {
                // Une ligne citée par plusieurs sources garde la première citation.
                if (isset($cited[$lineId])) {
                    continue;
                }
                $cited[$lineId] = true;
                $link = $links->get($lineId);
                if ($link === null) {
                    $link = new AccidentLine;
                    $link->forceFill([
                        'accident_id' => $accident->id, 'line_id' => $lineId, 'is_primary' => ! $hasPrimary,
                        'method' => 'wikipedia', 'confidence' => AccidentLinkConfidence::Low, 'score' => 0.4,
                        'cited_as' => $title, 'cited_url' => $url,
                    ])->save();
                    $links->put($lineId, $link);
                    $hasPrimary = true;
                    $result->count('citedLines', 'created');

                    continue;
                }
                $methods = explode('+', $link->method);
                $wanted = ['cited_as' => $title, 'cited_url' => $url, 'method' => implode('+', array_values(array_unique([...$methods, 'wikipedia'])))];
                if ($link->cited_as !== $wanted['cited_as'] || $link->cited_url !== $wanted['cited_url'] || $link->method !== $wanted['method']) {
                    $link->forceFill($wanted)->save();
                    $result->count('citedLines', 'updated');
                }
            }
        }
    }

    /**
     * Articles de ligne cités par les sources de la fiche, avec le permalien de la source.
     *
     * @return list<array{0: string, 1: string}>
     */
    private function citations(Accident $accident): array
    {
        $out = [];
        foreach ($accident->sources as $source) {
            foreach ($source->facts['line_articles'] ?? [] as $title) {
                $out[] = [str_replace('_', ' ', (string) $title), $source->url];
            }
        }

        return $out;
    }

    /** @return list<int> */
    private function linesFor(string $title): array
    {
        $ids = $this->lookup($title);
        $final = $this->redirects[$title] ?? null;

        return $ids !== [] || $final === null || $final === $title ? $ids : $this->lookup($final);
    }

    /** @return list<int> */
    private function lookup(string $title): array
    {
        return $this->resolved[$title] ??= DB::table('lines')->where('wikipedia_title', $title)->pluck('id')
            ->merge(DB::table('wikidata_lines')->where('frwiki_title', $title)->whereNotNull('line_id')->pluck('line_id'))
            ->map(fn ($id) => (int) $id)->unique()->values()->all();
    }
}
