<?php

namespace App\Console\Commands;

use App\Services\CatalogSearch;
use Illuminate\Console\Command;

/**
 * Mesure de la recherche sur le jeu d'essai tests/Fixtures/Search/queries.php : part des requêtes dont la fiche
 * attendue sort en premier, et dans les cinq premiers résultats de son type (CatalogSearch, comme l'en-tête,
 * l'accueil, la carte, l'API REST, GraphQL et MCP), avec le temps de chaque requête. Lecture seule.
 *
 *   vff:search-eval            # taux et temps
 *   vff:search-eval --failures # détaille les requêtes manquées
 */
class SearchEval extends Command
{
    protected $signature = 'vff:search-eval
        {--failures : Affiche les requêtes dont la fiche attendue n\'est pas première}';

    protected $description = 'Mesure la recherche sur le jeu d\'essai (premier résultat juste, dans les 5 premiers, temps)';

    private const BUCKETS = ['station' => 'stations', 'line' => 'lines', 'material' => 'materials', 'accident' => 'accidents'];

    public function handle(CatalogSearch $search): int
    {
        /** @var list<array{q: string, type: string, slug: string}> $cases */
        $cases = require base_path('tests/Fixtures/Search/queries.php');
        $first = 0;
        $top5 = 0;
        $times = [];
        foreach ($cases as $case) {
            $start = hrtime(true);
            $result = $search->search($case['q'], 5, 5, 5, 5);
            $times[] = (hrtime(true) - $start) / 1e6;
            $slugs = array_column($result[self::BUCKETS[$case['type']]], 'slug');
            $rank = array_search($case['slug'], $slugs, true);
            $first += $rank === 0 ? 1 : 0;
            $top5 += $rank !== false ? 1 : 0;
            if ($rank !== 0 && $this->option('failures')) {
                $this->line(sprintf('%-45s %-9s attendu %s, rang %s, reçu %s', $case['q'], $case['type'], $case['slug'],
                    $rank === false ? '-' : (string) ($rank + 1), implode(', ', array_slice($slugs, 0, 3)) ?: 'rien'));
            }
        }
        sort($times);
        $n = count($cases);
        $this->info(sprintf('%d requêtes : premier juste %d (%.1f %%), dans les 5 premiers %d (%.1f %%)', $n, $first, 100 * $first / $n, $top5, 100 * $top5 / $n));
        $this->info(sprintf('Temps par requête (4 types) : médiane %.1f ms, 95e centile %.1f ms, max %.1f ms',
            $times[intdiv($n, 2)], $times[(int) floor(0.95 * ($n - 1))], end($times)));

        return self::SUCCESS;
    }
}
