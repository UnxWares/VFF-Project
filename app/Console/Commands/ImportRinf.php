<?php

namespace App\Console\Commands;

use App\Services\Rinf\RinfClient;
use App\Services\Rinf\RinfDataset;
use App\Services\Rinf\RinfParser;
use App\Services\Rinf\RinfQueries;
use App\Services\Rinf\RinfStationMatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Import du RINF français (registre de l'infrastructure de l'ERA) depuis le point SPARQL public du graphe de
 * connaissances de l'ERA (RinfDataset, RinfQueries) : points d'exploitation, sections de ligne et leurs PK, voies,
 * voies de service, liaisons et quais. Source du schéma des voies (`GET /api/v1/lines/{line}/track-layout`).
 *
 * Lecture : six requêtes paginées (une trentaine de pages pour la France), une toutes les 5 s, User-Agent du
 * projet, réponses gardées dans storage/app/data/rinf/<portée>/ (dossier `.nobackup`) ; --offline n'utilise que ce
 * cache, --refresh le relit. Rattachement : sections aux lignes de VFF par le code de ligne (= `lines.code`),
 * points d'exploitation aux gares par le nom et la distance (RinfStationMatcher). Lectures seules sur les tables
 * de VFF ; écriture des seules tables rinf_*, dans une transaction : tout remplacé (France) ou seulement la ligne
 * demandée (--line), jamais avec --dry-run.
 *
 *   php artisan vff:import-rinf --dry-run                 # lit (ou relit le cache), mesure, n'écrit rien
 *   php artisan vff:import-rinf --dry-run --line=579000   # une ligne
 *   php artisan vff:import-rinf --offline                 # écrit, depuis le cache seulement
 */
class ImportRinf extends Command
{
    protected $signature = 'vff:import-rinf
        {--dry-run : mesure seulement, aucune écriture en base}
        {--offline : n\'utilise que le cache disque, aucune requête réseau}
        {--refresh : relit le point SPARQL au lieu du cache}
        {--line= : une seule ligne nationale (code SNCF Réseau à 6 chiffres)}
        {--cache-dir= : dossier du cache (défaut : storage/app/data/rinf)}
        {--csv= : rapport par ligne de VFF, au format CSV}';

    protected $description = 'Importe le RINF français (ERA) : points d\'exploitation, sections, voies, voies de service et quais';

    /** Taille des pages de résultats SPARQL. */
    public const PAGE = 5000;

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $line = $this->option('line') !== null ? trim((string) $this->option('line')) : null;
        try {
            $queries = new RinfQueries($line);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::INVALID;
        }
        $base = (string) ($this->option('cache-dir') ?: storage_path('app/data/rinf'));
        $client = new RinfClient($base.'/'.$queries->scope(), (bool) $this->option('offline'), (bool) $this->option('refresh'));

        try {
            $sections = RinfParser::sections($client->selectAll('sections', $queries->sections(), self::PAGE));
            $tracks = RinfParser::sectionTracks($client->selectAll('section-tracks', $queries->sectionTracks(), self::PAGE));
            $profiles = RinfParser::gradientProfiles($client->selectAll('gradients', $queries->gradients(), self::PAGE));
            $ops = RinfParser::operationalPoints($client->selectAll('operational-points', $queries->operationalPoints(), self::PAGE));
            $opTracks = RinfParser::opTracks($client->selectAll('op-tracks', $queries->opTracks(), self::PAGE));
            $platforms = RinfParser::platforms($client->selectAll('platforms', $queries->platforms(), self::PAGE));
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $fetchedAt = $client->fetchedAt() ?? now()->toIso8601String();
        $this->info(sprintf('RINF lu (%d requêtes réseau, cache %s, lecture du %s).', $client->requests(), $base.'/'.$queries->scope(), substr($fetchedAt, 0, 10)));

        // Déclivités : portions par voie de section, dans le sens des PK.
        foreach ($profiles as $uri => $profile) {
            $t = $tracks[$uri] ?? null;
            $s = $t !== null ? ($sections[$t['section_uri']] ?? null) : null;
            if ($s === null || $s['pk_start_m'] === null || $s['pk_end_m'] === null) {
                continue;
            }
            $increasing = $s['pk_end_m'] >= $s['pk_start_m'];
            $runsUp = match ($t['direction']) {
                'O' => ! $increasing,
                default => $increasing,
            };
            $segments = RinfParser::gradientSegments($profile, (int) $s['pk_start_m'], (int) $s['pk_end_m'], $runsUp);
            $tracks[$uri]['gradient_profile'] = $segments === [] ? null : json_encode($segments);
        }

        // Rattachements : lignes par code, gares par nom et distance (lectures seules).
        $codes = array_values(array_unique(array_column($sections, 'line_code')));
        $lineIds = DB::table('lines')->whereIn('code', $codes)->orderBy('id')->get(['id', 'code'])->groupBy('code')
            ->map(fn ($g): int => (int) $g->first()->id)->all();
        $matcher = new RinfStationMatcher(DB::table('stations')->selectRaw('id, slug, name, ST_Y(geom) as lat, ST_X(geom) as lon')->get());
        $methods = [];
        foreach ($ops as $uopid => $op) {
            $m = $matcher->match($op);
            $ops[$uopid] += $m ?? ['station_id' => null, 'station_match' => null, 'station_distance_m' => null];
            $methods[$m['station_match'] ?? 'none'] = ($methods[$m['station_match'] ?? 'none'] ?? 0) + 1;
        }
        foreach ($sections as $uri => $s) {
            $sections[$uri]['line_id'] = $lineIds[$s['line_code']] ?? null;
        }

        $this->report($sections, $tracks, $ops, $opTracks, $platforms, $lineIds, $methods);
        if ($csv = $this->option('csv')) {
            $this->csv((string) $csv, $sections, $tracks, $ops);
        }
        if ($dry) {
            $this->line('Passe à blanc : aucune écriture en base.');

            return self::SUCCESS;
        }

        $dataset = fn (string $graph): string => 'http://data.europa.eu/949/graph/'.$graph.' ('.RinfDataset::ENDPOINT.')';
        $meta = fn (array $row, string $graph): array => $row + ['dataset' => $dataset($graph), 'fetched_at' => $fetchedAt, 'created_at' => now(), 'updated_at' => now()];
        $graphOf = fn (?string $uopid): string => $uopid !== null && isset($ops[$uopid]) ? (string) $ops[$uopid]['dataset'] : '0087';

        DB::transaction(function () use ($line, $sections, $tracks, $ops, $opTracks, $platforms, $meta, $graphOf): void {
            if ($line === null) {
                DB::table('rinf_platforms')->delete();
                DB::table('rinf_tracks')->delete();
                DB::table('rinf_sections')->delete();
                DB::table('rinf_operational_points')->delete();
            } else {
                $old = DB::table('rinf_sections')->where('line_code', $line)->pluck('uri')->all();
                DB::table('rinf_tracks')->whereIn('section_uri', $old)->delete();
                DB::table('rinf_sections')->where('line_code', $line)->delete();
                DB::table('rinf_platforms')->whereIn('op_uopid', array_keys($ops))->delete();
                DB::table('rinf_tracks')->whereIn('op_uopid', array_keys($ops))->delete();
                DB::table('rinf_operational_points')->whereIn('uopid', array_keys($ops))->delete();
            }
            foreach (array_chunk(array_values($ops), 500) as $chunk) {
                DB::table('rinf_operational_points')->insert(array_map(fn (array $o): array => $meta(array_diff_key($o, ['dataset' => 1]), (string) $o['dataset']), $chunk));
            }
            foreach (array_chunk(array_values($sections), 500) as $chunk) {
                DB::table('rinf_sections')->insert(array_map(fn (array $s): array => $meta(array_diff_key($s, ['dataset' => 1]), (string) $s['dataset']), $chunk));
            }
            $sectionGraph = array_column($sections, 'dataset', 'uri');
            $trackRows = [];
            foreach ($tracks as $t) {
                if (isset($sectionGraph[$t['section_uri']])) {
                    $trackRows[] = $meta($t, (string) $sectionGraph[$t['section_uri']]);
                }
            }
            foreach ($opTracks as $uri => $t) {
                if (isset($ops[$t['op_uopid']]) && ! isset($tracks[$uri])) {
                    $trackRows[] = $meta($t, $graphOf($t['op_uopid']));
                }
            }
            foreach (array_chunk($trackRows, 500) as $chunk) {
                DB::table('rinf_tracks')->insert($chunk);
            }
            foreach (array_chunk(array_values($platforms), 500) as $chunk) {
                DB::table('rinf_platforms')->insert(array_map(fn (array $p): array => $meta($p, $graphOf($p['op_uopid'])), $chunk));
            }
        });
        $this->info(sprintf('Écrit : %d points d\'exploitation, %d sections, %d voies, %d quais.', count($ops), count($sections), count($tracks) + count($opTracks), count($platforms)));

        return self::SUCCESS;
    }

    /**
     * Mesure de couverture : ce que le RINF français décrit, ce qui est rattaché à VFF, ce qui manque.
     *
     * @param  array<string, array<string, mixed>>  $sections
     * @param  array<string, array<string, mixed>>  $tracks
     * @param  array<string, array<string, mixed>>  $ops
     * @param  array<string, array<string, mixed>>  $opTracks
     * @param  array<string, array<string, mixed>>  $platforms
     * @param  array<string, int>  $lineIds
     * @param  array<string, int>  $methods
     */
    private function report(array $sections, array $tracks, array $ops, array $opTracks, array $platforms, array $lineIds, array $methods): void
    {
        $regular = array_filter($sections, fn (array $s): bool => $s['nature'] === 'regular');
        $withPk = array_filter($regular, fn (array $s): bool => $s['pk_start_m'] !== null && $s['pk_end_m'] !== null);
        $matched = array_filter($withPk, fn (array $s): bool => isset($lineIds[$s['line_code']]));
        $km = fn (array $list): float => array_sum(array_map(fn (array $s): int => abs((int) $s['pk_end_m'] - (int) $s['pk_start_m']), $list)) / 1000;
        $mainTracks = array_filter($tracks, fn (array $t): bool => strtoupper((string) $t['ref']) !== 'VLINK');
        $count = fn (array $list, callable $f): int => count(array_filter($list, $f));

        $this->line('');
        $this->line(sprintf('Points d\'exploitation : %d (avec position : %d)', count($ops), $count($ops, fn ($o) => $o['lat'] !== null)));
        foreach (array_count_values(array_map(fn ($o) => (string) $o['kind'], $ops)) as $kind => $n) {
            $this->line(sprintf('  %s : %d', $kind, $n));
        }
        $this->line(sprintf('  rattachés à une gare de VFF : %d (nom et distance %d, nom seul %d, distance seule %d)',
            count($ops) - ($methods['none'] ?? 0), $methods['name_distance'] ?? 0, $methods['name'] ?? 0, $methods['distance'] ?? 0));
        $this->line(sprintf('Sections de ligne : %d (liaisons : %d ; avec les deux PK : %d ; %.0f km)', count($sections),
            count($sections) - count($regular), count($withPk), $km($withPk)));
        $this->line(sprintf('Lignes nationales : %d codes, dont %d rattachés à une ligne de VFF (%.0f km de sections)',
            count(array_unique(array_column($sections, 'line_code'))), count($lineIds), $km($matched)));
        $this->line(sprintf('Voies des sections : %d (hors liaisons VLINK : %d ; vitesse déclarée : %d ; profil de déclivités : %d ; ligne de contact dite : %d)',
            count($tracks), count($mainTracks), $count($mainTracks, fn ($t) => $t['max_speed_kmh'] !== null),
            $count($mainTracks, fn ($t) => $t['gradient_profile'] !== null), $count($mainTracks, fn ($t) => $t['electrified'] !== null)));
        $this->line(sprintf('Voies des points d\'exploitation : %d voies de gare, %d voies de service dont %d liaisons',
            $count($opTracks, fn ($t) => $t['rinf_class'] === 'running'), $count($opTracks, fn ($t) => $t['rinf_class'] === 'siding'),
            $count($opTracks, fn ($t) => preg_match('/^liaison\b/iu', (string) $t['ref']) === 1)));
        $this->line(sprintf('Quais (bords de quai) : %d (longueur : %d, hauteur : %d)', count($platforms),
            $count($platforms, fn ($p) => $p['length_m'] !== null), $count($platforms, fn ($p) => $p['height_mm'] !== null)));
        $this->line('Aiguillages et signaux : 0 (SNCF Réseau n\'en déclare pas au RINF : ni era:Switch ni era:Signal dans son graphe).');
    }

    /**
     * Rapport par ligne de VFF.
     *
     * @param  array<string, array<string, mixed>>  $sections
     * @param  array<string, array<string, mixed>>  $tracks
     * @param  array<string, array<string, mixed>>  $ops
     */
    private function csv(string $path, array $sections, array $tracks, array $ops): void
    {
        $by = [];
        $trackCount = array_count_values(array_map(fn (array $t): string => (string) $t['section_uri'], $tracks));
        foreach ($sections as $s) {
            $code = (string) $s['line_code'];
            $by[$code] ??= ['line_id' => $s['line_id'], 'sections' => 0, 'm' => 0, 'tracks' => 0, 'ops' => [], 'stations' => []];
            $by[$code]['sections']++;
            $by[$code]['m'] += $s['pk_start_m'] !== null && $s['pk_end_m'] !== null && $s['nature'] === 'regular' ? abs((int) $s['pk_end_m'] - (int) $s['pk_start_m']) : 0;
            $by[$code]['tracks'] += $trackCount[$s['uri']] ?? 0;
            foreach ([$s['op_start_uopid'], $s['op_end_uopid']] as $u) {
                $by[$code]['ops'][$u] = true;
                if (($ops[$u]['station_id'] ?? null) !== null) {
                    $by[$code]['stations'][$ops[$u]['station_id']] = true;
                }
            }
        }
        $h = fopen($path, 'w');
        if ($h === false) {
            $this->error("Rapport illisible : {$path}");

            return;
        }
        fputcsv($h, ['code', 'line_id', 'sections', 'km', 'tracks', 'operational_points', 'stations'], ',', '"', '');
        ksort($by);
        foreach ($by as $code => $b) {
            fputcsv($h, [$code, $b['line_id'] ?? '', $b['sections'], round($b['m'] / 1000, 3), $b['tracks'], count($b['ops']), count($b['stations'])], ',', '"', '');
        }
        fclose($h);
        $this->line("Rapport : {$path}");
    }
}
