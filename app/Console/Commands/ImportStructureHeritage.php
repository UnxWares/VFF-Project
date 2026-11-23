<?php

namespace App\Console\Commands;

use App\Services\Heritage\MerimeeSource;
use App\Services\Heritage\StructureHeritageMatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Import des protections des ouvrages d'art (viaducs, ponts, tunnels) : monuments historiques classés ou inscrits, label
 * Architecture contemporaine remarquable, lus dans la base Mérimée du ministère de la Culture (Licence Ouverte 2.0, fichier
 * déjà téléchargé par vff:import-station-heritage, cache storage/app/sources/patrimoine/) et rapprochés des ouvrages de
 * `structures` (StructureHeritageMatcher : points expliqués, seuil prudent). Les lignes de `structure_heritage` sont
 * remplacées d'un coup, dans une transaction ; aucune autre table n'est écrite. Aucune requête réseau, sauf --refresh.
 *
 *   php artisan vff:import-structure-heritage --dry-run --csv=/chemin/
 *   php artisan vff:import-structure-heritage
 */
class ImportStructureHeritage extends Command
{
    protected $signature = 'vff:import-structure-heritage
        {--dry-run : rapport seulement, aucune écriture en base}
        {--refresh : télécharge de nouveau le fichier Mérimée}
        {--cache-dir= : dossier du cache (défaut : storage/app/sources/patrimoine)}
        {--csv= : dossier où écrire les rapports détaillés au format CSV}';

    protected $description = 'Importe les protections Mérimée (monuments historiques, label ACR) des viaducs, ponts et tunnels';

    public function handle(MerimeeSource $merimee): int
    {
        $dir = (string) ($this->option('cache-dir') ?: storage_path('app/sources/patrimoine'));
        try {
            $source = $merimee->load($dir, (bool) $this->option('refresh'));
            $records = $merimee->structureRecords($source['path']);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->info(sprintf('Mérimée : %d notices de viaduc, pont ou tunnel protégées ou labellisées (fichier du %s, licence %s).',
            count($records), $source['modified'] ?? '?', $source['license']));

        $structures = DB::table('structures')->selectRaw('id, kind, name, lat, lon, length_m, merimee_ref, wikidata_id, heritage,
            EXISTS (SELECT 1 FROM line_structures ls WHERE ls.structure_id = structures.id) AS on_line')->get();
        $result = (new StructureHeritageMatcher($structures))->build($records, $source['modified']);
        $rows = $result['rows'];

        $byStructure = [];
        foreach ($rows as $r) {
            $byStructure[$r['structure_id']][] = $r;
        }
        $known = $structures->whereIn('heritage', ['classified', 'registered'])->pluck('id')->flip();
        $new = array_filter(array_keys($byStructure), fn (int $id): bool => ! isset($known[$id]));
        $this->line(sprintf('Rapprochées : %d notices, pour %d ouvrages de VFF ; %d de ces ouvrages ne sont pas encore protégés dans `structures` (aujourd\'hui : %d ouvrages protégés).',
            count($rows), count($byStructure), count($new), $known->count()));
        $perKind = [];
        foreach ($rows as $r) {
            $kind = (string) $structures->firstWhere('id', $r['structure_id'])->kind;
            $perKind[$kind.'/'.$r['protection']] = ($perKind[$kind.'/'.$r['protection']] ?? 0) + 1;
        }
        ksort($perKind);
        $this->line('Par genre et protection : '.implode(', ', array_map(fn ($k, $n) => "{$k} {$n}", array_keys($perKind), $perKind)));
        $withArchitect = count(array_filter($rows, fn (array $r): bool => $r['architects'] !== []));
        $this->line(sprintf('Avec architecte ou auteur : %d.', $withArchitect));
        foreach ($result['scored'] as $sc) {
            if ($sc['score'] < 100 && ! isset($known[$sc['structure']->id])) {
                $this->line(sprintf('  nouveau : %s (%s, %s) -> structure %d « %s », %d points : %s', $sc['record']['title'], $sc['record']['commune'], $sc['record']['ref'],
                    $sc['structure']->id, $sc['structure']->name ?? '-', $sc['score'], implode(', ', $sc['reasons'])));
            }
        }
        // Les ponts anciens dont le texte parle seulement d'une gare ou d'un train restent dans le CSV, pas dans l'écran.
        $likely = array_values(array_filter($result['unmatched'], fn (array $u): bool => $u['kind'] !== 'bridge' || preg_match('/ferroviaire|chemin de fer|SNCF|viaduc/iu', $u['title'].' '.$u['denomination']) === 1));
        $this->line(sprintf('Notices ferroviaires sans ouvrage sûr : %d (dont %d viaducs, tunnels ou ponts ferroviaires nommés).', count($result['unmatched']), count($likely)));
        foreach ($likely as $u) {
            $this->warn(sprintf('  %s : %s (%s, %s)', $u['why'], $u['title'], $u['commune'], $u['ref']));
        }

        if ($csv = rtrim((string) $this->option('csv'), '/')) {
            if (! is_dir($csv)) {
                mkdir($csv, 0775, true);
            }
            $this->csv($csv.'/ouvrages-rattaches.csv', ['reference', 'titre', 'commune', 'genre', 'protection', 'date', 'architectes', 'structure', 'nom_structure', 'points', 'ecart_m', 'explication'],
                array_map(fn (array $sc) => [$sc['record']['ref'], $sc['record']['title'], $sc['record']['commune'], $sc['record']['kind'], $sc['record']['protection'],
                    $sc['record']['protection_date'], implode('; ', array_column($sc['record']['architects'], 'name')), $sc['structure']->id, $sc['structure']->name,
                    $sc['score'], $sc['distance'], implode(', ', $sc['reasons'])], $result['scored']));
            $this->csv($csv.'/ouvrages-non-rattaches.csv', ['reference', 'titre', 'commune', 'genre', 'raison'],
                array_map(fn (array $u) => [$u['ref'], $u['title'], $u['commune'], $u['kind'], $u['why']], $result['unmatched']));
            $this->line("Rapports : {$csv}");
        }

        if ($this->option('dry-run')) {
            $this->line('Passe à blanc : aucune écriture en base.');

            return self::SUCCESS;
        }
        if (! Schema::hasTable('structure_heritage')) {
            $this->error('La table structure_heritage n\'existe pas : appliquer la migration 2026_12_16_000000_create_structure_heritage_table avant l\'import.');

            return self::FAILURE;
        }
        $now = now();
        $insert = array_map(function (array $r) use ($now): array {
            foreach (['designations', 'architects'] as $json) {
                $r[$json] = $r[$json] === [] || $r[$json] === null ? null : json_encode($r[$json], JSON_UNESCAPED_UNICODE);
            }

            return $r + ['created_at' => $now, 'updated_at' => $now];
        }, $rows);
        DB::transaction(function () use ($insert): void {
            DB::table('structure_heritage')->where('source', 'merimee')->delete();
            foreach (array_chunk($insert, 200) as $chunk) {
                DB::table('structure_heritage')->insert($chunk);
            }
        });
        $this->info('Écrit : '.count($insert).' lignes.');

        return self::SUCCESS;
    }

    /** @param  list<list<mixed>>  $rows */
    private function csv(string $path, array $header, array $rows): void
    {
        $f = fopen($path, 'wb');
        fputcsv($f, $header, ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($f, $row, ',', '"', '');
        }
        fclose($f);
    }
}
