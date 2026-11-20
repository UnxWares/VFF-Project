<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Tire des événements datés des NOMS des tracés archeoferroviaire, qui portent
 * souvent leur période : « Tracé initial (jusqu'en 1876) », « Marcq / Saint-Juvin
 * (ligne militaire 14/18) », « Ligne Hagondange Vigy Mil 30-40 VN »…
 *
 * Le nom fait partie du tracé (conditions de la source : database/data/source_rights.php).
 * Chaque événement porte sur la géométrie du
 * tracé (section_resolution='trace'), précision à l'année, confiance moyenne
 * (basse pour « 30-40 », période approximative de la ligne Maginot).
 *
 * Sans ces dates, un ancien tracé remplacé (déviation) restait « en service »
 * dans toutes les décennies, en double du tracé actuel.
 *
 *   php artisan vff:dates-from-trace-names
 */
class DatesFromTraceNames extends Command
{
    protected $signature = 'vff:dates-from-trace-names {--dry-run}';

    protected $description = 'Crée des événements datés à partir des périodes écrites dans les noms des tracés archeoferroviaire.';

    private const YEAR = '(1[89]\d{2}|20[0-2]\d)';

    public function handle(): int
    {
        $traces = DB::select("SELECT id, line_id, name FROM source_traces
            WHERE source = 'archeoferroviaire' AND layer <> 'exploitee' AND line_id IS NOT NULL AND name ~ '[0-9]{2}'");

        $created = ['opening' => 0, 'closure' => 0];
        $examples = [];
        foreach ($traces as $t) {
            foreach ($this->periods($t->name) as $type => [$year, $confidence]) {
                if (! $this->option('dry-run')) {
                    $this->upsert($t, $type, $year, $confidence);
                }
                $created[$type]++;
                if (count($examples) < 12) {
                    $examples[] = [$t->name, $type, $year];
                }
            }
        }

        $this->table(['Nom du tracé', 'Événement', 'Année'], array_map(fn ($e) => [mb_strimwidth($e[0], 0, 70, '…'), $e[1], $e[2]], $examples));
        $this->info("✓ {$created['opening']} ouvertures et {$created['closure']} fermetures tirées des noms de ".count($traces).' tracés.');

        return self::SUCCESS;
    }

    /**
     * @return array<string, array{0: int, 1: string}> type => [année, confiance]
     */
    public function periods(string $name): array
    {
        $n = str_replace(['’', '—', '–'], ["'", '-', '-'], $name);
        $y = self::YEAR;

        return match (true) {
            (bool) preg_match('#\b14\s*[-/]\s*18\b#u', $n) => ['opening' => [1914, 'medium'], 'closure' => [1918, 'medium']],
            (bool) preg_match('#\b39\s*[-/]\s*45\b#u', $n) => ['opening' => [1939, 'medium'], 'closure' => [1945, 'medium']],
            (bool) preg_match('#\b(?:mil\w*)\b.*\b30\s*[-/]\s*40\b#iu', $n) => ['opening' => [1930, 'low'], 'closure' => [1940, 'low']],
            (bool) preg_match("#\\(\\s*{$y}\\s*-\\s*{$y}\\s*\\)#u", $n, $m) => ['opening' => [(int) $m[1], 'medium'], 'closure' => [(int) $m[2], 'medium']],
            (bool) preg_match("#jusqu'(?:en|à|a)\\s+{$y}#iu", $n, $m) => ['closure' => [(int) $m[1], 'medium']],
            (bool) preg_match("#(?:depuis|à partir de|ouvert[e]? en)\\s+{$y}#iu", $n, $m) => ['opening' => [(int) $m[1], 'medium']],
            default => [],
        };
    }

    private function upsert(object $trace, string $type, int $year, string $confidence): void
    {
        $label = $type === 'opening' ? 'Ouverture' : 'Fermeture';
        DB::statement(<<<'SQL'
            INSERT INTO line_events (line_id, type, event_date, date_precision, description, source, source_quote,
                                     source_trace_id, confidence, section_resolution, section_geom, fingerprint, created_at, updated_at)
            SELECT :line, :type, make_date(:year, 1, 1), 'year', :descr, 'archeoferroviaire', :quote,
                   id, :conf, 'trace', geom, :fp, NOW(), NOW()
            FROM source_traces WHERE id = :trace
            ON CONFLICT (fingerprint) DO UPDATE SET event_date = EXCLUDED.event_date, confidence = EXCLUDED.confidence,
                description = EXCLUDED.description, section_geom = EXCLUDED.section_geom, updated_at = NOW()
            SQL, [
            'line' => $trace->line_id, 'type' => $type, 'year' => $year,
            'descr' => "{$label} ({$year}) d'après le nom du tracé archeoferroviaire",
            'quote' => mb_strimwidth($trace->name, 0, 300),
            'conf' => $confidence, 'trace' => $trace->id,
            'fp' => "archeo-name:{$trace->id}:{$type}",
        ]);
    }
}
