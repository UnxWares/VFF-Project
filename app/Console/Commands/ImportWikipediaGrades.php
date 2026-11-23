<?php

namespace App\Console\Commands;

use App\Services\Profiles\MeasuredGrade;
use App\Services\Profiles\RackRailways;
use App\Services\Profiles\SourcedGrades;
use App\Services\Profiles\WikipediaGrades;
use App\Services\Signalling\WikipediaArticleCache;
use App\Services\WikimediaClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Déclivités maximales lues dans les articles de Wikipédia des lignes (infobox, puis texte), articles
 * DÉJÀ en cache (storage/app/data/signalisation-historique/wikipedia, rempli par
 * vff:import-signalling-history ou vff:import-line-speeds) : aucun appel réseau. Résultat fusionné
 * dans database/data/sourced_grades.php (lu par App\Services\Profiles\SourcedGrades), sans rien
 * effacer. La base n'est jamais modifiée : lectures des lignes et des profils seulement.
 * Règles : App\Services\Profiles\WikipediaGrades.
 *
 *   php artisan vff:import-wikipedia-grades --dry-run          # bilan seulement, aucun fichier écrit
 *   php artisan vff:import-wikipedia-grades --csv=rapport.csv  # écrit le fichier et le rapport par ligne
 */
class ImportWikipediaGrades extends Command
{
    protected $signature = 'vff:import-wikipedia-grades
        {--dry-run : bilan seulement, aucun fichier écrit}
        {--articles-dir= : dossier du cache des articles (défaut : celui de vff:import-signalling-history)}
        {--output= : fichier de données écrit (défaut : database/data/sourced_grades.php)}
        {--csv= : rapport détaillé par ligne, au format CSV}';

    protected $description = 'Déclivités maximales lues dans les articles de Wikipédia en cache (infobox, puis texte contrôlé par le profil), fusionnées dans le fichier des déclivités sourcées';

    public function handle(WikimediaClient $wiki): int
    {
        // Tests : jamais le vrai fichier de données.
        $output = $this->option('output') ?: (app()->runningUnitTests() ? null : database_path('data/sourced_grades.php'));
        if ($output === null && ! $this->option('dry-run')) {
            $this->error('--output obligatoire pendant les tests.');

            return self::FAILURE;
        }
        $cache = new WikipediaArticleCache($wiki, (string) ($this->option('articles-dir') ?: WikipediaArticleCache::defaultDirectory()));

        $lines = DB::table('lines as l')
            ->leftJoin('line_profiles as p', 'p.line_id', '=', 'l.id')
            ->whereNotNull('l.wikipedia_title')->where('l.wikipedia_title', '!=', '')
            ->orderBy('l.slug')
            ->get(['l.slug', 'l.code', 'l.wikipedia_title', 'l.length_official_m', 'p.id as profile_id', 'p.length_m', 'p.max_grade_permil', 'p.max_grade_withheld', 'p.gaps'])
            ->all();
        // Article rattaché à plusieurs lignes : compté sur le titre lu (redirections suivies).
        $articles = [];
        $perTitle = [];
        foreach ($lines as $line) {
            $article = $cache->get((string) $line->wikipedia_title);
            $articles[$line->slug] = $article;
            $key = $article['title'] ?? (string) $line->wikipedia_title;
            $perTitle[$key] = ($perTitle[$key] ?? 0) + 1;
        }

        $read = [];
        $rows = [];
        foreach ($lines as $line) {
            $article = $articles[$line->slug];
            $row = ['slug' => $line->slug, 'title' => $line->wikipedia_title, 'value' => null, 'measure' => null, 'withheld' => $line->max_grade_withheld];
            if ($article === null) {
                $rows[] = $row + ['status' => 'article_missing'];

                continue;
            }
            $length = $line->length_official_m ?? $line->length_m;
            $measured = null;
            $measure = function () use ($line, &$measured): ?array {
                return $measured = MeasuredGrade::of(
                    $line->profile_id === null ? null : (int) $line->profile_id,
                    $line->max_grade_permil === null ? null : (float) $line->max_grade_permil,
                    $line->gaps === null ? null : (string) $line->gaps,
                );
            };
            $decision = WikipediaGrades::decide(
                ['title' => $article['title'], 'revid' => $article['revid'], 'content' => $article['content']],
                $line->code === null ? null : (string) $line->code,
                $length === null ? null : (int) $length,
                RackRailways::isRack($line->slug),
                ($perTitle[$article['title']] ?? 1) > 1,
                $measure,
            );
            if ($decision['entry'] !== null) {
                $read[$line->slug] = $decision['entry'];
            }
            $rows[] = array_replace($row, [
                'value' => $decision['entry']['max_grade_permil'] ?? null,
                'measure' => $measured === null ? ($line->max_grade_permil === null ? null : (float) $line->max_grade_permil.' (track)') : $measured['value'].' ('.$measured['kind'].')',
            ]) + ['status' => $decision['status']];
        }

        $existing = is_file((string) $output) ? (array) require (string) $output : [];
        ['entries' => $entries, 'outcome' => $outcome] = WikipediaGrades::merge($existing, $read);
        $rows = array_map(fn (array $r): array => $r + ['outcome' => $outcome[$r['slug']] ?? '-'], $rows);

        $this->report($rows, $outcome, count($entries), $lines);
        if ($this->option('csv')) {
            $this->csv((string) $this->option('csv'), $rows);
        }

        if ($this->option('dry-run')) {
            $this->line('Passe à blanc : aucun fichier écrit.');

            return self::SUCCESS;
        }
        $content = WikipediaGrades::render($entries);
        $before = is_file((string) $output) ? (string) file_get_contents((string) $output) : null;
        if ($before === $content) {
            $this->line("{$output} : inchangé.");
        } else {
            file_put_contents((string) $output, $content);
            $this->info("{$output} écrit (".count($entries).' lignes).');
        }
        SourcedGrades::flush();

        return self::SUCCESS;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, string>  $outcome
     * @param  list<object>  $lines
     */
    private function report(array $rows, array $outcome, int $total, array $lines): void
    {
        $counts = array_count_values(array_column($rows, 'status'));
        ksort($counts);
        $this->table(['Lecture', 'Lignes'], array_map(fn (string $k, int $n): array => [$k, $n], array_keys($counts), $counts));
        $merged = array_count_values($outcome);
        ksort($merged);
        $this->table(['Fichier', 'Lignes'], array_map(fn (string $k, int $n): array => [$k, $n], array_keys($merged), $merged));
        $changed = array_filter($rows, fn (array $r): bool => in_array($r['outcome'], ['added', 'updated'], true));
        if ($changed !== []) {
            $this->table(['Ligne', 'Wikipédia ‰', 'Mesure ‰', 'Lecture', 'Fichier'], array_map(fn (array $r): array => [
                $r['slug'], $r['value'] ?? '-', $r['measure'] ?? '-', $r['status'], $r['outcome'],
            ], array_values($changed)));
        }
        $known = array_flip(array_map(fn (object $l): string => (string) $l->slug, $lines));
        $orphans = array_filter(array_keys($outcome), fn (string $slug): bool => ! isset($known[$slug]) && $outcome[$slug] === 'kept_not_read');
        if ($orphans !== []) {
            $this->warn(count($orphans).' entrées gardées pour une ligne sans titre de Wikipédia en base (ou disparue) : '.implode(', ', array_slice($orphans, 0, 20)).(count($orphans) > 20 ? '…' : ''));
        }
        $this->info("{$total} déclivités de Wikipédia dans le fichier après fusion.");
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function csv(string $path, array $rows): void
    {
        $handle = fopen($path, 'w');
        if ($handle === false) {
            $this->warn("{$path} : écriture impossible.");

            return;
        }
        fputcsv($handle, ['slug', 'title', 'status', 'outcome', 'wikipedia_permil', 'measure', 'withheld'], escape: '');
        foreach ($rows as $r) {
            fputcsv($handle, [$r['slug'], $r['title'], $r['status'], $r['outcome'], $r['value'], $r['measure'], $r['withheld']], escape: '');
        }
        fclose($handle);
    }
}
