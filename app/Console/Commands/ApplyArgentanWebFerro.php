<?php

namespace App\Console\Commands;

use App\Services\ArgentanWebFerro\EngineFacts;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Faits relus d'Argentan Web Ferroviaire (argentanwebferro_facts, rempli par
 * vff:import-argentanwebferro) → moteur : événements de lignes (line_events, source
 * « argentanwebferro ») et arrêts des listes par ligne (source_stations). Règles et décisions :
 * App\Services\ArgentanWebFerro\EngineFacts, database/data/argentanwebferro_moteur.php.
 *
 * Rejouable : empreinte « argentanwebferro:<empreinte du fait> » pour les événements, empreinte du
 * fait pour les arrêts ; un fait retiré (rejeté, écarté, disparu de l'article) disparaît des tables
 * du moteur au passage suivant (sauf avec --line). Une portion changée repasse « à résoudre ».
 *
 * Ensuite (vff:rebuild-history le fait dans cet ordre) : vff:apply-event-corrections,
 * vff:resolve-event-sections --source=argentanwebferro, vff:build-era-segments --line=…, et pour les
 * arrêts vff:import-station-sources --source=pk, vff:consolidate-stations, vff:stations-pipeline.
 *
 *   php artisan vff:apply-argentanwebferro --dry-run
 *   php artisan vff:apply-argentanwebferro --line=425000
 */
class ApplyArgentanWebFerro extends Command
{
    protected $signature = 'vff:apply-argentanwebferro
        {--line= : code SNCF ou slug d\'une ligne}
        {--dry-run : affiche ce qui serait écrit, sans rien écrire}';

    protected $description = 'Faits relus d\'Argentan Web Ferroviaire → line_events et source_stations (source argentanwebferro).';

    public function handle(): int
    {
        $rules = EngineFacts::load();
        if ($errors = $rules->validate()) {
            foreach ($errors as $e) {
                $this->error($e);
            }

            return self::FAILURE;
        }
        $dry = (bool) $this->option('dry-run');
        $only = $this->onlyLine();
        if ($this->option('line') && $only === null) {
            $this->error('Ligne inconnue : '.$this->option('line'));

            return self::FAILURE;
        }

        $facts = DB::table('argentanwebferro_facts')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
        $known = array_flip(array_column($facts, 'fingerprint'));
        foreach (array_keys($rules->skip + $rules->stops) as $fp) {
            if (! isset($known[$fp])) {
                $this->warn("Décision sans fait correspondant (article modifié ?) : {$fp}");
            }
        }
        $refs = array_values(array_filter(array_map(fn ($c) => (string) ($c['line'] ?? ''), $rules->stops)));
        $lineIds = $this->lineIds($refs);

        $events = [];
        $stops = [];
        $reasons = [];
        foreach ($facts as $f) {
            if ($f['kind'] === 'event') {
                ['row' => $row, 'reason' => $reason] = $rules->event($f);
                if ($row !== null) {
                    $events[$row['fingerprint']] = $row;
                } else {
                    // Faits écartés un par un : comptés ensemble (raisons dans le fichier de décisions).
                    $key = str_starts_with((string) $reason, 'écarté') ? 'écarté (database/data/argentanwebferro_moteur.php)' : (string) $reason;
                    $reasons[$key] = ($reasons[$key] ?? 0) + 1;
                }
            } elseif (($row = $rules->stop($f, $lineIds)) !== null) {
                $stops[$row['source_ref']] = $row;
            }
        }
        $inScope = fn (array $row) => $only === null || $row['line_id'] === $only;

        $e = $this->syncEvents(array_filter($events, $inScope), array_keys($events), $only, $dry);
        $s = $this->syncStops(array_filter($stops, $inScope), array_keys($stops), $only, $dry);

        $this->table(['table', 'ajoutés', 'mis à jour', 'inchangés', 'retirés'], [
            ['line_events', ...$e], ['source_stations', ...$s],
        ]);
        if ($reasons) {
            ksort($reasons);
            $this->table(['fait gardé en source', 'nombre'], array_map(fn ($k, $v) => [mb_strimwidth($k, 0, 110, '…'), $v], array_keys($reasons), $reasons));
        }
        $this->info(($dry ? '[dry-run] ' : '').count($events).' événement(s) et '.count($stops).' arrêt(s) retenus.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, array<string, mixed>>  $rows
     * @param  list<string>  $all  empreintes retenues sur toutes les lignes
     * @return array{int, int, int, int}
     */
    private function syncEvents(array $rows, array $all, ?int $only, bool $dry): array
    {
        $counts = [0, 0, 0, 0];
        $current = DB::table('line_events')->where('source', EngineFacts::SOURCE)
            ->when($only !== null, fn ($q) => $q->where('line_id', $only))
            ->get()->keyBy('fingerprint');
        foreach ($rows as $fp => $row) {
            $cur = $current[$fp] ?? null;
            if ($cur === null) {
                $counts[0]++;
                if (! $dry) {
                    DB::table('line_events')->insert($row + ['created_at' => now(), 'updated_at' => now()]);
                }

                continue;
            }
            $changed = array_filter($row, fn ($v, $k) => $k !== 'section_resolution' && (string) $cur->{$k} !== (string) $v, ARRAY_FILTER_USE_BOTH);
            if ($changed === []) {
                $counts[2]++;

                continue;
            }
            $counts[1]++;
            $samePortion = ! array_intersect_key($changed, array_flip(['line_id', 'section_from', 'section_to']));
            // Portion inchangée : la résolution déjà faite reste (section_geom, longueur, note).
            $update = $samePortion ? array_diff_key($row, ['section_resolution' => true])
                : $row + ['section_geom' => null, 'section_length_m' => null, 'section_resolution_note' => null];
            if (! $dry) {
                DB::table('line_events')->where('id', $cur->id)->update($update + ['updated_at' => now()]);
            }
        }
        $stale = DB::table('line_events')->where('source', EngineFacts::SOURCE)->whereNotIn('fingerprint', $all ?: [''])
            ->when($only !== null, fn ($q) => $q->where('line_id', $only));
        $counts[3] = $stale->count();
        if (! $dry && $counts[3] > 0) {
            $stale->delete();
        }

        return $counts;
    }

    /**
     * @param  array<string, array<string, mixed>>  $rows
     * @param  list<string>  $all
     * @return array{int, int, int, int}
     */
    private function syncStops(array $rows, array $all, ?int $only, bool $dry): array
    {
        $counts = [0, 0, 0, 0];
        $current = DB::table('source_stations')->where('source', EngineFacts::SOURCE)
            ->when($only !== null, fn ($q) => $q->where('line_id', $only))
            ->get()->keyBy('source_ref');
        foreach ($rows as $ref => $row) {
            $data = ['line_id' => $row['line_id'], 'name' => $row['name'], 'pk_m' => $row['pk_m'],
                'properties' => json_encode($row['properties'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)];
            $cur = $current[$ref] ?? null;
            if ($cur === null) {
                $counts[0]++;
                if (! $dry) {
                    DB::table('source_stations')->insert($data + ['source' => EngineFacts::SOURCE, 'source_ref' => $ref, 'created_at' => now(), 'updated_at' => now()]);
                }

                continue;
            }
            $same = (int) $cur->line_id === $data['line_id'] && $cur->name === $data['name'] && (string) $cur->pk_m === (string) $data['pk_m']
                && json_decode((string) $cur->properties, true) == $row['properties'];
            if ($same) {
                $counts[2]++;

                continue;
            }
            $counts[1]++;
            if (! $dry) {
                DB::table('source_stations')->where('id', $cur->id)->update($data + ['updated_at' => now()]);
            }
        }
        $stale = DB::table('source_stations')->where('source', EngineFacts::SOURCE)->whereNotIn('source_ref', $all ?: [''])
            ->when($only !== null, fn ($q) => $q->where('line_id', $only));
        $counts[3] = $stale->count();
        if (! $dry && $counts[3] > 0) {
            $stale->delete();
        }

        return $counts;
    }

    private function onlyLine(): ?int
    {
        $ref = (string) $this->option('line');

        return $ref === '' ? null : ($this->lineIds([$ref])[$ref] ?? null);
    }

    /**
     * @param  list<string>  $refs  codes SNCF ou slugs
     * @return array<string, int>
     */
    private function lineIds(array $refs): array
    {
        if ($refs === []) {
            return [];
        }
        $out = [];
        foreach (DB::table('lines')->whereIn('code', $refs)->orWhereIn('slug', $refs)->orderBy('id')->get(['id', 'code', 'slug']) as $l) {
            foreach ([$l->code, $l->slug] as $r) {
                if ($r !== null && ! isset($out[$r])) {
                    $out[$r] = (int) $l->id;
                }
            }
        }

        return $out;
    }
}
