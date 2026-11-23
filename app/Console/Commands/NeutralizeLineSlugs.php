<?php

namespace App\Console\Commands;

use App\Services\Lines\LineSlugFileRewriter;
use App\Services\Lines\LineSlugRedirects;
use App\Services\Lines\NeutralLineSlugs;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Slugs neutres des lignes créées depuis archeoferroviaire (préfixe « archeo- » retiré, voir
 * App\Services\Lines\NeutralLineSlugs) et redirections des anciens slugs.
 *
 *   php artisan vff:neutralize-line-slugs --dry-run        liste, collisions, rien d'écrit
 *   php artisan vff:neutralize-line-slugs --map-only       écrit seulement database/data/line_slug_redirects.php
 *   php artisan vff:neutralize-line-slugs                  écrit les correspondances et renomme les lignes (une transaction)
 *   php artisan vff:neutralize-line-slugs --rewrite-files  remplace aussi les anciens slugs cités par les fichiers du projet
 *
 * Idempotente : un second passage ne trouve plus de ligne à renommer et garde les correspondances écrites.
 * Les anciens slugs de lignes fusionnées dans une autre (alt_names.merged) mènent aussi à la ligne qui les a reçues.
 * Après l'écriture : vider le cache (php artisan cache:clear), qui garde la carte, les tuiles et le plan du site.
 */
class NeutralizeLineSlugs extends Command
{
    protected $signature = 'vff:neutralize-line-slugs
        {--dry-run : Liste les renommages et les collisions, sans rien écrire}
        {--map-only : Écrit seulement le fichier des correspondances, sans toucher la base}
        {--rewrite-files : Remplace aussi les anciens slugs dans les fichiers du projet (données, tests, exemples)}
        {--map= : Fichier des correspondances (défaut database/data/line_slug_redirects.php ; les tests en donnent un autre)}';

    protected $description = 'Retire le préfixe de source des slugs de ligne et garde une redirection 301 de chaque ancien slug.';

    /** Renommages affichés sans -v. */
    private const SHOWN = 25;

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $mapOnly = (bool) $this->option('map-only');
        $path = is_string($this->option('map')) && $this->option('map') !== '' ? $this->option('map') : LineSlugRedirects::path();
        $known = LineSlugRedirects::read($path);

        $plan = NeutralLineSlugs::plan($this->prefixedLines(), $this->mergedSlugs(), DB::table('lines')->pluck('slug')->all(), $known);
        $this->report($plan, $known);

        if ($dry) {
            if ($this->option('rewrite-files')) {
                $this->rewriteFiles($known + self::pairs($plan), true);
            }
            $this->info('[dry-run] rien d\'écrit.');

            return self::SUCCESS;
        }

        $map = $known + self::pairs($plan);
        ksort($map);
        if (! $mapOnly && $plan['renames'] !== []) {
            DB::transaction(function () use ($plan): void {
                foreach ($plan['renames'] as $r) {
                    DB::table('lines')->where('id', $r['line_id'])->where('slug', $r['from'])->update(['slug' => $r['to'], 'updated_at' => now()]);
                }
            });
            $this->info(count($plan['renames']).' lignes renommées (base '.DB::connection()->getDatabaseName().').');
        }
        if ($map !== $known) {
            file_put_contents($path, self::export($map));
            $this->info(count($map).' correspondances dans '.$path.' ('.(count($map) - count($known)).' nouvelles).');
        } else {
            $this->line('Correspondances inchangées ('.count($map).').');
        }
        if ($this->option('rewrite-files')) {
            $this->rewriteFiles($map, false);
        }
        if (! $mapOnly) {
            $this->line('À faire ensuite : php artisan cache:clear (carte, tuiles, plan du site).');
        }

        return self::SUCCESS;
    }

    /** @return list<array{id: int, slug: string}> */
    private function prefixedLines(): array
    {
        return DB::table('lines')->where('slug', 'like', NeutralLineSlugs::PREFIX.'%')->orderBy('id')->get(['id', 'slug'])
            ->map(fn (object $r): array => ['id' => (int) $r->id, 'slug' => (string) $r->slug])->all();
    }

    /** @return list<array{from: string, line_id: int, line_slug: string}> */
    private function mergedSlugs(): array
    {
        $rows = DB::select(<<<'SQL'
            SELECT DISTINCT m.value->>'slug' AS from_slug, l.id, l.slug
            FROM lines l
            CROSS JOIN LATERAL jsonb_array_elements(CASE WHEN jsonb_typeof(l.alt_names::jsonb->'merged') = 'array'
                THEN l.alt_names::jsonb->'merged' ELSE '[]'::jsonb END) AS m(value)
            WHERE jsonb_typeof(m.value) = 'object' AND m.value->>'slug' LIKE ?
              AND NOT EXISTS (SELECT 1 FROM lines x WHERE x.slug = m.value->>'slug')
            ORDER BY 1, 2
            SQL, [NeutralLineSlugs::PREFIX.'%']);

        return array_map(fn (object $r): array => ['from' => (string) $r->from_slug, 'line_id' => (int) $r->id, 'line_slug' => (string) $r->slug], $rows);
    }

    /**
     * @param  array{renames: list<array{line_id: int, from: string, to: string}>, merged: list<array{line_id: int, from: string, to: string}>, collisions: list<array{from: string, wanted: string, to: string}>}  $plan
     * @param  array<string, string>  $known
     */
    private function report(array $plan, array $known): void
    {
        $this->line('Base : '.DB::connection()->getDatabaseName().' ; correspondances déjà écrites : '.count($known).'.');
        $this->line(count($plan['renames']).' lignes à renommer, '.count($plan['merged']).' anciens slugs de lignes fusionnées à rediriger, '
            .count($plan['collisions']).' collision(s).');
        $all = $this->output->isVerbose();
        foreach (array_slice($plan['renames'], 0, $all ? null : self::SHOWN) as $r) {
            $this->line("  {$r['from']}  →  {$r['to']}");
        }
        if (! $all && count($plan['renames']) > self::SHOWN) {
            $this->line('  … (-v pour toute la liste)');
        }
        foreach (array_slice($plan['merged'], 0, $all ? null : self::SHOWN) as $m) {
            $this->line("  fusionnée : {$m['from']}  →  {$m['to']}");
        }
        foreach ($plan['collisions'] as $c) {
            $this->warn("  collision : {$c['from']} voulait {$c['wanted']}, reçoit {$c['to']}");
        }
    }

    /** @param  array<string, string>  $map */
    private function rewriteFiles(array $map, bool $dryRun): void
    {
        $changed = (new LineSlugFileRewriter(new LineSlugRedirects($map)))->rewriteTree(base_path(), $dryRun);
        $this->line(($dryRun ? '[dry-run] ' : '').count($changed).' fichier(s) '.($dryRun ? 'à réécrire' : 'réécrit(s)').', '.array_sum($changed).' slug(s) remplacé(s).');
        foreach ($changed as $file => $count) {
            $this->line("  {$file} : {$count}");
        }
    }

    /**
     * @param  array{renames: list<array{from: string, to: string}>, merged: list<array{from: string, to: string}>}  $plan
     * @return array<string, string>
     */
    private static function pairs(array $plan): array
    {
        $pairs = [];
        foreach ([...$plan['renames'], ...$plan['merged']] as $r) {
            $pairs[$r['from']] = $r['to'];
        }

        return $pairs;
    }

    /** @param  array<string, string>  $map */
    public static function export(array $map): string
    {
        $rows = '';
        foreach ($map as $from => $to) {
            $rows .= '    '.var_export($from, true).' => '.var_export($to, true).",\n";
        }

        return <<<PHP
            <?php

            /**
             * Anciens slugs de ligne et slug qui les remplace, écrit par `php artisan vff:neutralize-line-slugs` (ne pas
             * modifier à la main sans relire App\\Services\\Lines\\NeutralLineSlugs).
             *
             * Lu par App\\Services\\Lines\\LineSlugRedirects : l'ancienne adresse d'une fiche répond 301 vers la nouvelle
             * (site et API), GraphQL et le serveur MCP acceptent l'ancien slug. Un ancien slug n'est jamais redonné à
             * une autre ligne. Une ligne fusionnée dans une autre mène à la ligne qui l'a reçue.
             */
            return [
            {$rows}];

            PHP;
    }
}
