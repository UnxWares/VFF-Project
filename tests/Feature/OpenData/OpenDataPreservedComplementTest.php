<?php

namespace Tests\Feature\OpenData;

use App\Services\Materials\PreservedRollingStock;
use App\Services\OpenData\OpenDataLedger;
use App\Services\OpenData\OpenDataRights;
use App\Services\OpenData\OpenDataSuiteExporter;
use App\Support\SourceRights;
use Tests\TestCase;

/**
 * Le jeu ouvert du matériel préservé lit les mêmes exemplaires que le site (PreservedRollingStock) : le fichier de base
 * ET son complément (database/data/preserved_rolling_stock_complement.php), fusionnés de la même façon.
 */
class OpenDataPreservedComplementTest extends TestCase
{
    public function test_the_preserved_stock_dataset_includes_the_units_of_the_complement_file(): void
    {
        $dir = sys_get_temp_dir().'/vff-open-data-preserved-'.getmypid().'-'.bin2hex(random_bytes(4));
        mkdir($dir, 0775, true);
        try {
            (new OpenDataSuiteExporter(new OpenDataRights(new SourceRights), '2026-11-23'))->preserved($dir, new OpenDataLedger);
            $units = $this->rows($dir.'/materiel-preserve-exemplaires.csv');
            $venues = array_column($this->rows($dir.'/materiel-preserve-lieux.csv'), 'venue_slug');
        } finally {
            foreach (['materiel-preserve-exemplaires.csv', 'materiel-preserve-lieux.csv'] as $file) {
                if (is_file($dir.'/'.$file)) {
                    unlink($dir.'/'.$file);
                }
            }
            rmdir($dir);
        }

        // Exemplaires et lieux que seul le complément apporte (Cité du train, Train du Bas-Berry).
        $complement = require database_path('data/preserved_rolling_stock_complement.php');
        $site = app(PreservedRollingStock::class);
        $unit = $complement['units']['030-t-ouest-1011-a-1114'][0];
        $this->assertNotSame([], $site->forMaterial('030-t-ouest-1011-a-1114'));
        $this->assertContains(['030-t-ouest-1011-a-1114', $unit['number']], array_map(fn (array $r): array => [$r['material_slug'], $r['number']], $units));
        $added = array_diff_key($complement['venues'], (require database_path('data/preserved_rolling_stock.php'))['venues']);
        $used = array_values(array_intersect(array_keys($added), $venues));
        $this->assertNotSame([], $used, 'aucun lieu du complément dans le jeu ouvert');
    }

    /** @return list<array<string, string>> */
    private function rows(string $path): array
    {
        $handle = fopen($path, 'rb');
        $this->assertNotFalse($handle);
        $header = fgetcsv($handle, 0, ',', '"', '');
        $rows = [];
        while (($cells = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $rows[] = array_combine($header, $cells);
        }
        fclose($handle);

        return $rows;
    }
}
