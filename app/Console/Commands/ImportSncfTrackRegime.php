<?php

namespace App\Console\Commands;

use App\Services\Sncf\SncfOpenDataCache;
use App\Services\Sncf\TrackRegime;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Import du jeu SNCF Open Data « Régime d'exploitation des lignes » (SNCF Réseau, ODbL) : nombre de voies
 * principales par portion de PK.
 *
 * Écrit :
 * - `line_track_portions` : chaque tronçon du jeu rattaché à une ligne de VFF par son code, libellé SNCF tel quel
 *   et nombre de voies qu'il établit (null pour « voie banalisée » et « _Autre », qui ne le disent pas) ;
 *   toutes les portions `sncf` sont remplacées d'un coup, dans une transaction ;
 * - `lines.nb_voies_default`, là où VFF garde déjà le nombre de voies d'une ligne, seulement pour une ligne au
 *   régime uniforme (90 % de sa longueur ou plus) et seulement si le champ est vide ou a été écrit par cette
 *   commande (`line_sources` « sncf-regime », `fields_provided` contient `nb_voies_default`). Une valeur venue
 *   d'une autre source (Wikidata, lignes-oubliees, Wikipédia) n'est jamais remplacée : un nombre différent est
 *   compté et listé comme contradiction. Une ligne au régime partagé (voie unique et double voie) garde un champ
 *   vide ; ses portions disent le détail ;
 * - `line_sources` « sncf-regime » : une trace par ligne touchée (lien du jeu, date de lecture, champs fournis).
 *
 * Le jeu décrit le réseau actuel : aucune ère. Il est téléchargé une fois (licence et éditeur vérifiés, cache
 * storage/app/sources/sncf-regime/, dossier `.nobackup`) ; --refresh le télécharge de nouveau.
 *
 *   php artisan vff:import-sncf-track-regime --dry-run             # mesure, aucune écriture
 *   php artisan vff:import-sncf-track-regime --dry-run --csv=/chemin/rapport.csv
 *   php artisan vff:import-sncf-track-regime                       # écrit
 */
class ImportSncfTrackRegime extends Command
{
    protected $signature = 'vff:import-sncf-track-regime
        {--dry-run : rapport seulement, aucune écriture en base}
        {--refresh : télécharge de nouveau le jeu au lieu du cache local}
        {--cache-dir= : dossier du cache (défaut : storage/app/sources/sncf-regime)}
        {--csv= : rapport détaillé par ligne, au format CSV}';

    protected $description = 'Importe le régime d\'exploitation SNCF Réseau (nombre de voies par portion de PK) et complète le nombre de voies des lignes';

    public const SOURCE_TYPE = 'sncf-regime';

    public const SOURCE_URL = 'https://data.sncf.com/explore/dataset/regime-dexploitation-des-lignes/';

    private const FIELDS = ['code_ligne', 'lib_ligne', 'exploitati', 'rg_troncon', 'pkd', 'pkf'];

    public function handle(SncfOpenDataCache $cache): int
    {
        $dry = (bool) $this->option('dry-run');
        try {
            $data = $cache->load(TrackRegime::DATASET, self::FIELDS,
                (string) ($this->option('cache-dir') ?: storage_path('app/sources/sncf-regime')), (bool) $this->option('refresh'));
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $records = $data['records'];
        $summary = TrackRegime::summarize($records);
        $this->info(sprintf('%d enregistrements, %d codes de ligne au nombre de voies établi (jeu modifié le %s, licence %s).',
            count($records), count($summary), $data['modified'] ?? '?', $data['license']));

        $codes = array_values(array_unique(array_filter(array_map(fn ($r) => trim((string) ($r['code_ligne'] ?? '')), $records))));
        $banalisedOnly = array_diff(
            array_unique(array_map(fn ($r) => (string) $r['code_ligne'], array_filter($records, fn ($r) => TrackRegime::isBanalised($r['exploitati'] ?? null)))),
            array_keys($summary));

        $lines = DB::table('lines')->whereIn('code', $codes)->get(['id', 'slug', 'code', 'nb_voies_default'])->groupBy('code');
        $owned = DB::table('line_sources')->where('source_type', self::SOURCE_TYPE)->get(['line_id', 'fields_provided'])
            ->filter(fn ($s) => in_array('nb_voies_default', (array) json_decode((string) $s->fields_provided, true), true))
            ->pluck('line_id')->flip();
        $wikidata = DB::table('wikidata_lines')->whereNotNull('line_id')->whereRaw("jsonb_typeof(facts->'tracks') = 'number'")
            ->selectRaw("line_id, (facts->>'tracks')::numeric::int as tracks")->pluck('tracks', 'line_id');

        // Ce que ferait l'écriture, ligne par ligne.
        $plan = [];          // line_id => nouvelle valeur de nb_voies_default (null : effacer une valeur de cette commande)
        $count = ['write' => 0, 'refresh' => 0, 'agree' => 0, 'contradiction' => 0, 'mixed' => 0, 'clear' => 0];
        $wdCount = ['agree' => 0, 'contradiction' => 0, 'mixed' => 0];
        $rows = [];
        $contradictions = [];
        foreach ($lines as $code => $group) {
            $s = $summary[$code] ?? null;
            foreach ($group as $line) {
                $known = $line->nb_voies_default !== null ? (int) $line->nb_voies_default : null;
                $ours = isset($owned[$line->id]);
                $action = match (true) {
                    $s === null => $ours && $known !== null ? 'clear' : 'banalised',
                    ! $s['uniform'] => $ours && $known !== null ? 'clear' : 'mixed',
                    $known === null => 'write',
                    $known === $s['tracks'] => 'agree',
                    $ours => 'refresh',
                    default => 'contradiction',
                };
                if (in_array($action, ['write', 'refresh'], true)) {
                    $plan[(int) $line->id] = $s['tracks'];
                } elseif ($action === 'clear') {
                    $plan[(int) $line->id] = null;
                }
                if (isset($count[$action])) {
                    $count[$action]++;
                }
                if ($action === 'contradiction') {
                    $contradictions[] = "{$line->slug} : fiche {$known}, SNCF Réseau {$s['tracks']}";
                }
                if ($s !== null && isset($wikidata[$line->id])) {
                    $w = TrackRegime::verdict($s, (int) $wikidata[$line->id]);
                    if ($w !== 'new') {
                        $wdCount[$w]++;
                    }
                }
                $rows[] = [$line->slug, $code, $s['tracks'] ?? '', $s === null ? 'banalisée' : ($s['uniform'] ? 'uniforme' : 'partagé'),
                    $s['share'] ?? '', $known ?? '', $wikidata[$line->id] ?? '', $action,
                    $s === null ? '' : implode(' | ', $s['regimes']), $s['km'] ?? ''];
            }
        }
        // Lignes dont cette commande avait écrit le nombre de voies et que le jeu ne cite plus : valeur effacée.
        $touched = $lines->flatten()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $gone = array_values(array_diff(array_map('intval', $owned->keys()->all()), $touched));
        foreach ($gone as $id) {
            $plan[$id] = null;
            $count['clear']++;
        }

        $portions = $this->portions($records, $lines, $data);
        $unmatched = array_values(array_diff($codes, $lines->keys()->all()));
        $stations = $touched === [] ? 0 : (int) DB::table('station_lines')->whereIn('line_id', $touched)->distinct()->count('station_id');

        $this->line('');
        $this->line(sprintf('Lignes touchées : %d (codes du jeu sans ligne dans VFF : %d)', count($touched), count($unmatched)));
        $this->line(sprintf('Portions écrites dans line_track_portions : %d', count($portions)));
        $this->line(sprintf('Nombre de voies de la fiche (lines.nb_voies_default) :'));
        $this->line(sprintf('  écrit (champ vide, régime uniforme) : %d', $count['write']));
        $this->line(sprintf('  rafraîchi (valeur écrite par cette commande) : %d', $count['refresh']));
        $this->line(sprintf('  confirmé (même nombre) : %d', $count['agree']));
        $this->line(sprintf('  contradictions (autre source, nombre différent, jamais remplacé) : %d', $count['contradiction']));
        $this->line(sprintf('  régime partagé, champ laissé tel quel (détail par portion) : %d', $count['mixed']));
        $this->line(sprintf('  effacé (valeur de cette commande que le jeu ne confirme plus) : %d', $count['clear']));
        foreach ($contradictions as $c) {
            $this->line('    '.$c);
        }
        $this->line(sprintf('Contrôle croisé avec Wikidata : %d accords, %d contradictions, %d régimes partagés', $wdCount['agree'], $wdCount['contradiction'], $wdCount['mixed']));
        $this->line(sprintf('Gares rattachées aux lignes touchées : %d', $stations));
        $this->line(sprintf('Lignes à régime partagé (voie unique et double voie) : %d', count(array_filter($summary, fn ($s) => ! $s['uniform']))));
        $this->line(sprintf('Lignes au seul régime « voie banalisée » (nombre de voies non établi, écartées) : %d', count($banalisedOnly)));

        if ($csv = $this->option('csv')) {
            $h = fopen((string) $csv, 'w');
            fputcsv($h, ['slug', 'code', 'voies_sncf', 'regime', 'part_dominante', 'voies_fiche', 'voies_wikidata', 'action', 'libelles', 'km'], ',', '"', '');
            foreach ($rows as $r) {
                fputcsv($h, $r, ',', '"', '');
            }
            fclose($h);
            $this->line("Rapport : {$csv}");
        }

        if ($dry) {
            $this->line('Passe à blanc : aucune écriture en base.');

            return self::SUCCESS;
        }

        $this->write($portions, $plan, $touched, $data);
        $this->info('Écrit : '.count($portions).' portions, '.count($plan).' nombres de voies modifiés.');

        return self::SUCCESS;
    }

    /**
     * Lignes de line_track_portions : un tronçon du jeu par ligne de VFF de même code (libellé vide écarté).
     *
     * @param  list<array<string, mixed>>  $records
     * @param  Collection<array-key, Collection<int, \stdClass>>  $lines
     * @param  array{modified: ?string, fetched_at: ?string}  $data
     * @return list<array<string, mixed>>
     */
    private function portions(array $records, $lines, array $data): array
    {
        $now = now();
        $out = [];
        foreach ($records as $r) {
            $code = trim((string) ($r['code_ligne'] ?? ''));
            $regime = trim((string) ($r['exploitati'] ?? ''));
            if ($regime === '' || ! isset($lines[$code])) {
                continue;
            }
            foreach ($lines[$code] as $line) {
                $out[] = [
                    'line_id' => $line->id, 'code_ligne' => $code,
                    'rg_troncon' => isset($r['rg_troncon']) ? (int) $r['rg_troncon'] : null,
                    'pk_start_m' => TrackRegime::pkMeters($r['pkd'] ?? null), 'pk_end_m' => TrackRegime::pkMeters($r['pkf'] ?? null),
                    'regime' => mb_substr($regime, 0, 120), 'tracks' => TrackRegime::tracks($regime), 'source' => 'sncf',
                    'dataset_modified' => $data['modified'], 'fetched_at' => $data['fetched_at'] ?? $now,
                    'created_at' => $now, 'updated_at' => $now,
                ];
            }
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $portions
     * @param  array<int, ?int>  $plan
     * @param  list<int>  $touched
     * @param  array{modified: ?string, fetched_at: ?string}  $data
     */
    private function write(array $portions, array $plan, array $touched, array $data): void
    {
        $now = now();
        DB::transaction(function () use ($portions, $plan, $touched, $data, $now) {
            DB::table('line_track_portions')->where('source', 'sncf')->delete();
            foreach (array_chunk($portions, 500) as $chunk) {
                DB::table('line_track_portions')->insert($chunk);
            }
            foreach ($plan as $lineId => $tracks) {
                DB::table('lines')->where('id', $lineId)->update(['nb_voies_default' => $tracks, 'encyclopedia_updated_at' => $now, 'updated_at' => $now]);
            }
            // Trace de provenance : nb_voies_default seulement là où la valeur vient de cette commande.
            $ownedAfter = array_keys(array_filter($plan, fn ($t) => $t !== null));
            $kept = DB::table('line_sources')->where('source_type', self::SOURCE_TYPE)->whereIn('line_id', $touched)->get(['line_id', 'fields_provided'])
                ->filter(fn ($s) => in_array('nb_voies_default', (array) json_decode((string) $s->fields_provided, true), true) && ! array_key_exists((int) $s->line_id, $plan))
                ->pluck('line_id')->map(fn ($id) => (int) $id)->all();
            DB::table('line_sources')->where('source_type', self::SOURCE_TYPE)->whereNotIn('line_id', $touched ?: [0])->delete();
            foreach ($touched as $lineId) {
                $fields = in_array($lineId, $ownedAfter, true) || in_array($lineId, $kept, true)
                    ? ['line_track_portions', 'nb_voies_default'] : ['line_track_portions'];
                DB::table('line_sources')->updateOrInsert(
                    ['line_id' => $lineId, 'source_type' => self::SOURCE_TYPE],
                    ['source_url' => self::SOURCE_URL, 'fetched_at' => $data['fetched_at'] ?? $now, 'fields_provided' => json_encode($fields),
                        'confidence' => 'high', 'created_at' => $now, 'updated_at' => $now],
                );
            }
        });
    }
}
