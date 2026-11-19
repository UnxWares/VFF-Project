<?php

namespace App\Console\Commands;

use App\Services\Materials\MaterialPeriodCollector;
use App\Services\Materials\MaterialPeriodExtractor;
use App\Services\Materials\SourceCache;
use App\Services\Materials\WikidataMaterialRules;
use App\Services\Materials\WikidataMaterialSource;
use App\Services\Media\CommonsClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Périodes de circulation du matériel roulant sur les lignes (phase 13) : quelle série a roulé sur quelle
 * ligne, de quand à quand, d'après les articles Wikipédia des lignes et des séries (faits datés seulement,
 * CC BY-SA 4.0) et les qualificatifs de date de Wikidata (P3438, CC0). Rangées dans `line_material_periods`,
 * rattachées aux liens ligne × matériel importés.
 *
 * À lancer après `vff:import-materials` : les articles sont lus dans son cache (storage/app/data/materiel/,
 * mêmes révisions que les preuves des liens), sans requête vers Wikipédia. Wikidata : une seule requête,
 * gardée en cache ; `--offline` ne lit que le cache.
 *
 * Rejouable : chaque période a une empreinte stable (lien, début, fin, portion) ; celles que les sources ne
 * portent plus sont retirées (cette table seulement).
 *
 *   php artisan vff:import-material-periods
 *   php artisan vff:import-material-periods --dry-run -v
 *   php artisan vff:import-material-periods --offline
 */
class ImportMaterialPeriods extends Command
{
    protected $signature = 'vff:import-material-periods
        {--offline : n\'appelle jamais Wikidata, lit le cache seulement}
        {--dry-run : n\'écrit rien en base, affiche le bilan (et les périodes avec -v)}';

    protected $description = 'Range les périodes de circulation des séries de matériel sur les lignes, datées par Wikipédia et Wikidata.';

    /** Une seconde au moins entre deux appels aux serveurs Wikimedia. */
    private const MIN_INTERVAL_MS = 1000;

    public function handle(MaterialPeriodExtractor $extractor): int
    {
        $cache = $this->laravel->bound(SourceCache::class) ? $this->laravel->make(SourceCache::class) : SourceCache::default();
        $wikidata = new WikidataMaterialSource(new CommonsClient(minIntervalMs: self::MIN_INTERVAL_MS), $cache, WikidataMaterialRules::default());
        $collector = new MaterialPeriodCollector($cache, $extractor, $wikidata);

        ['periods' => $periods, 'stats' => $stats] = $collector->collect((bool) $this->option('offline'));
        $this->info("Articles lus : {$stats['line_articles']} de lignes, {$stats['material_articles']} de séries ; déclarations Wikidata datées : {$stats['wikidata']}.");
        $this->line("  Faits sans lien ligne × série attesté (écartés) : {$stats['unlinked']}");

        $byConfidence = array_count_values(array_column($periods, 'confidence'));
        $shown = array_filter($periods, fn (array $p): bool => $p['confidence'] !== 'low');
        $this->table(['fiabilité', 'périodes'], collect(['high', 'medium', 'low'])->map(fn (string $c): array => [$c, $byConfidence[$c] ?? 0])->all());
        $linkIds = array_unique(array_column($shown, 'line_material_id'));
        $pairs = DB::table('line_materials')->whereIn('id', $linkIds)->get(['line_id', 'material_id']);
        $this->info(count($shown).' période(s) sûre(s) sur '.$pairs->pluck('line_id')->unique()->count().' ligne(s) et '.$pairs->pluck('material_id')->unique()->count().' série(s).');

        if ($this->output->isVerbose()) {
            $names = DB::table('line_materials')->join('lines', 'lines.id', '=', 'line_materials.line_id')->join('materials', 'materials.id', '=', 'line_materials.material_id')
                ->whereIn('line_materials.id', array_unique(array_column($periods, 'line_material_id')))
                ->get(['line_materials.id', 'lines.code', 'lines.name as line', 'materials.name as material'])->keyBy('id');
            foreach ($periods as $p) {
                $n = $names[$p['line_material_id']] ?? null;
                $this->line(sprintf('  [%s] %s %s | %s → %s | %s | %s', $p['confidence'], $n->code ?? '', $n->material ?? '', $p['start_date'] ?? '…', $p['end_date'] ?? '…', $p['origin'], $p['source_excerpt'] ?? $p['source_url']));
            }
        }

        if ($this->option('dry-run')) {
            $this->warn('--dry-run : rien n\'est écrit.');

            return self::SUCCESS;
        }
        $written = $collector->write($periods);
        $this->info("Périodes : {$written['created']} créée(s), {$written['updated']} mise(s) à jour, {$written['removed']} retirée(s).");

        return self::SUCCESS;
    }
}
