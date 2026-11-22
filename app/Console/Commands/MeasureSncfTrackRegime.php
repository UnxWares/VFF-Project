<?php

namespace App\Console\Commands;

use App\Services\Sncf\TrackRegime;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Passe à blanc de l'import du jeu SNCF Open Data « Régime d'exploitation des lignes » (SNCF Réseau,
 * ODbL) : mesure ce qu'il apporterait au nombre de voies des lignes (`lines.nb_voies_default`, 91 lignes
 * renseignées sur 2 705 au 22/11/2026) sans rien écrire. Lectures seules en base ; seul le cache local
 * (storage/app/sources/sncf-regime/, dossier `.nobackup`) est écrit.
 *
 * Le jeu est téléchargé une fois (métadonnées, dont la licence vérifiée, puis export JSON sans les
 * tracés) ; --refresh le télécharge de nouveau. La commande refuse de tourner sans --dry-run : il
 * n'existe pas encore de mode d'écriture.
 *
 *   php artisan vff:measure-sncf-track-regime --dry-run
 *   php artisan vff:measure-sncf-track-regime --dry-run --csv=/chemin/rapport.csv
 */
class MeasureSncfTrackRegime extends Command
{
    protected $signature = 'vff:measure-sncf-track-regime
        {--dry-run : obligatoire, rapport seulement (aucune écriture en base)}
        {--refresh : télécharge de nouveau le jeu au lieu du cache local}
        {--cache-dir= : dossier du cache (défaut : storage/app/sources/sncf-regime)}
        {--csv= : rapport détaillé par ligne, au format CSV}';

    protected $description = 'Mesure, sans rien écrire, ce que le régime d\'exploitation SNCF (voies) apporterait aux lignes et aux gares';

    private const API = 'https://data.sncf.com/api/explore/v2.1/catalog/datasets/';

    public function handle(): int
    {
        if (! $this->option('dry-run')) {
            $this->error('Passe à blanc seulement : ajouter --dry-run (aucun mode d\'écriture n\'existe).');

            return self::INVALID;
        }

        try {
            $data = $this->load();
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $summary = TrackRegime::summarize($data['records']);
        $this->info(sprintf('%d enregistrements, %d codes de ligne exploitables (jeu modifié le %s, licence %s).',
            count($data['records']), count($summary), $data['modified'] ?? '?', $data['license']));

        $banalisedOnly = array_diff(
            array_unique(array_map(fn ($r) => (string) $r['code_ligne'], array_filter($data['records'], fn ($r) => TrackRegime::isBanalised($r['exploitati'] ?? null)))),
            array_keys($summary));

        $lines = DB::table('lines')->whereIn('code', array_keys($summary))
            ->get(['id', 'slug', 'name', 'code', 'nb_voies_default'])->groupBy('code');
        $wikidata = DB::table('wikidata_lines')->whereNotNull('line_id')->whereRaw("jsonb_typeof(facts->'tracks') = 'number'")
            ->selectRaw("line_id, (facts->>'tracks')::numeric::int as tracks")->pluck('tracks', 'line_id');

        $rows = [];
        $count = ['new' => 0, 'agree' => 0, 'contradiction' => 0, 'mixed' => 0];
        $wdCount = ['agree' => 0, 'contradiction' => 0, 'mixed' => 0];
        $unmatched = [];
        $touched = [];
        foreach ($summary as $code => $s) {
            if (! isset($lines[$code])) {
                $unmatched[] = $code;

                continue;
            }
            foreach ($lines[$code] as $line) {
                $known = $line->nb_voies_default !== null ? (int) $line->nb_voies_default : null;
                $verdict = TrackRegime::verdict($s, $known);
                $count[$verdict]++;
                $touched[] = (int) $line->id;
                if (isset($wikidata[$line->id])) {
                    $w = TrackRegime::verdict($s, (int) $wikidata[$line->id]);
                    if ($w !== 'new') {
                        $wdCount[$w]++;
                    }
                }
                $rows[] = [$line->slug, $code, $s['tracks'], $s['uniform'] ? 'uniforme' : 'partagé', $s['share'], $known ?? '',
                    $wikidata[$line->id] ?? '', $verdict, implode(' | ', $s['regimes']), $s['km']];
            }
        }

        $stations = $touched === [] ? 0 : (int) DB::table('station_lines')->whereIn('line_id', $touched)->distinct()->count('station_id');

        $this->line('');
        $this->line(sprintf('Lignes touchées : %d (codes du jeu sans ligne dans VFF : %d)', count($touched), count($unmatched)));
        $this->line(sprintf('  faits nouveaux (nombre de voies absent de la fiche) : %d', $count['new']));
        $this->line(sprintf('  confirmés par la fiche : %d', $count['agree']));
        $this->line(sprintf('  contradictions (régime uniforme, nombre différent) : %d', $count['contradiction']));
        $this->line(sprintf('  régime partagé, nombre différent de celui de la fiche : %d', $count['mixed']));
        $this->line(sprintf('Contrôle croisé avec Wikidata : %d accords, %d contradictions, %d régimes partagés', $wdCount['agree'], $wdCount['contradiction'], $wdCount['mixed']));
        $this->line(sprintf('Gares rattachées aux lignes touchées : %d', $stations));
        $this->line(sprintf('Lignes à régime partagé (voie unique et double voie) : %d', count(array_filter($summary, fn ($s) => ! $s['uniform']))));
        $this->line(sprintf('Lignes au seul régime « voie banalisée » (nombre de voies non établi, écartées) : %d', count($banalisedOnly)));
        $this->line('Aucune écriture en base.');

        if ($csv = $this->option('csv')) {
            $h = fopen((string) $csv, 'w');
            fputcsv($h, ['slug', 'code', 'voies_sncf', 'regime', 'part_dominante', 'voies_fiche', 'voies_wikidata', 'verdict', 'libelles', 'km'], ',', '"', '');
            foreach ($rows as $r) {
                fputcsv($h, $r, ',', '"', '');
            }
            fclose($h);
            $this->line("Rapport : {$csv}");
        }

        return self::SUCCESS;
    }

    /** @return array{records: list<array<string, mixed>>, license: string, modified: ?string} */
    private function load(): array
    {
        $dir = rtrim((string) ($this->option('cache-dir') ?: storage_path('app/sources/sncf-regime')), '/');
        $cache = $dir.'/'.TrackRegime::DATASET.'.json';
        if (! $this->option('refresh') && is_file($cache)) {
            $cached = json_decode((string) file_get_contents($cache), true);
            if (is_array($cached['records'] ?? null) && $cached['records'] !== []) {
                return ['records' => $cached['records'], 'license' => (string) ($cached['license'] ?? '?'), 'modified' => $cached['modified'] ?? null];
            }
        }

        $http = Http::withUserAgent('VFF-Bot/1.0 (+https://vff-project.org)')->timeout(120);
        try {
            $meta = $http->get(self::API.TrackRegime::DATASET);
            $export = $meta->ok() ? $http->get(self::API.TrackRegime::DATASET.'/exports/json', [
                'select' => 'code_ligne,lib_ligne,exploitati,rg_troncon,pkd,pkf', 'limit' => -1]) : null;
        } catch (ConnectionException) {
            throw new \RuntimeException('SNCF Open Data injoignable : rien mesuré.');
        }
        if (! $meta->ok() || ! $export?->ok()) {
            throw new \RuntimeException('SNCF Open Data a répondu une erreur : rien mesuré.');
        }
        $default = $meta->json('metas.default') ?? [];
        $license = (string) ($default['license'] ?? '');
        if (! str_contains($license, 'ODbL') || ($default['publisher'] ?? '') !== TrackRegime::ATTRIBUTION) {
            throw new \RuntimeException("Licence ou éditeur inattendus (« {$license} », « ".($default['publisher'] ?? '').' ») : rien mesuré.');
        }
        $records = $export->json();
        if (! is_array($records) || $records === []) {
            throw new \RuntimeException('Jeu vide : rien mesuré.');
        }

        @mkdir($dir, 0775, true);
        // Cache régénérable : hors sauvegarde (convention .nobackup du poste de développement).
        if (! is_file($dir.'/.nobackup')) {
            touch($dir.'/.nobackup');
        }
        $modified = isset($default['modified']) ? substr((string) $default['modified'], 0, 10) : null;
        file_put_contents($cache, json_encode(['dataset' => TrackRegime::DATASET, 'fetched_at' => now()->toDateTimeString(),
            'license' => $license, 'modified' => $modified, 'records' => $records], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return ['records' => $records, 'license' => $license, 'modified' => $modified];
    }
}
