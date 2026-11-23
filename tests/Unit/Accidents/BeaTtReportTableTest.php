<?php

namespace Tests\Unit\Accidents;

use App\Services\Accidents\BeaTtReportTable;
use PHPUnit\Framework\TestCase;

/**
 * Pages d'enquête du BEA-TT relues à la main (database/data/accident_bea_tt_reports.php) : la rangée se retrouve par le lien
 * du bilan, et chaque ligne livrée est complète et bien formée.
 */
class BeaTtReportTableTest extends TestCase
{
    public function test_a_row_is_found_by_its_bilan_link_only(): void
    {
        $row = ['slug' => 'a', 'commune_insee' => '38501', 'place' => 'Tencins-Theys (38)', 'report' => ['title' => 'R', 'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/r.html']];
        $table = new BeaTtReportTable(['https://www.bea-tt.developpement-durable.gouv.fr/x-r1.html' => $row]);

        $this->assertSame($row, $table->find('https://www.bea-tt.developpement-durable.gouv.fr/x-r1.html'));
        $this->assertNull($table->find('https://www.bea-tt.developpement-durable.gouv.fr/x-r2.html'));
    }

    public function test_the_shipped_table_is_well_formed(): void
    {
        $rows = (new BeaTtReportTable)->rows();

        $this->assertNotEmpty($rows);
        $slugs = [];
        foreach ($rows as $url => $row) {
            $this->assertStringStartsWith('https://www.bea-tt.developpement-durable.gouv.fr/', $url);
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}-[a-z0-9-]+$/', $row['slug']);
            $this->assertArrayNotHasKey($row['slug'], $slugs, "fiche {$row['slug']} deux fois");
            $slugs[$row['slug']] = true;
            $this->assertNotSame('', trim($row['place']));
            $this->assertTrue(isset($row['commune_insee']) || isset($row['date']), "{$row['slug']} : ni commune ni date, la ligne ne sert à rien");
            if (isset($row['commune_insee'])) {
                $this->assertMatchesRegularExpression('/^(\d{5}|2[AB]\d{3})$/', $row['commune_insee']);
            }
            if (isset($row['date'])) {
                $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $row['date']);
                $this->assertTrue(checkdate((int) substr($row['date'], 5, 2), (int) substr($row['date'], 8, 2), (int) substr($row['date'], 0, 4)));
            }
            $this->assertNotSame('', trim($row['report']['title']));
            $this->assertStringStartsWith('https://www.bea-tt.developpement-durable.gouv.fr/', $row['report']['url']);
            if (isset($row['report']['number'])) {
                $this->assertMatchesRegularExpression('/^BEATT-\d{4}-\d{2,3}$/', $row['report']['number']);
            }
            if (isset($row['report']['published'])) {
                $this->assertMatchesRegularExpression('/^\d{4}-\d{2}(-\d{2})?$/', $row['report']['published']);
            }
        }
    }

    public function test_a_corrected_day_stays_within_two_days_of_the_slug_of_the_fiche_it_is_read_for(): void
    {
        foreach ((new BeaTtReportTable)->rows() as $row) {
            if (isset($row['date'])) {
                $gap = abs((strtotime($row['date']) - strtotime(substr($row['slug'], 0, 10))) / 86400);
                $this->assertLessThanOrEqual(1, $gap, "{$row['slug']} : le jour de la page est à plus d'un jour de la fiche");
            }
        }
    }
}
