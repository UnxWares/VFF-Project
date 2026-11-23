<?php

namespace Tests\Unit;

use App\Services\RailProjects;
use PHPUnit\Framework\TestCase;

/**
 * Invariants du catalogue versionné `database/data/projets_ferroviaires.php` : chaque fait daté porte sa source, aucune
 * date prévue n'est présentée comme franchie, aucun projet déjà en service, aucun tiret long.
 */
class RailProjectsCatalogueTest extends TestCase
{
    private const REGIONS = ['Auvergne-Rhône-Alpes', 'Bourgogne-Franche-Comté', 'Bretagne', 'Centre-Val de Loire', 'Corse', 'Grand Est', 'Hauts-de-France',
        'Île-de-France', 'Normandie', 'Nouvelle-Aquitaine', 'Occitanie', 'Pays de la Loire', "Provence-Alpes-Côte d'Azur"];

    /** @return list<array<string, mixed>> */
    private function entries(): array
    {
        return require dirname(__DIR__, 2).'/'.RailProjects::CATALOGUE;
    }

    public function test_catalogue_is_not_empty_and_ids_are_unique(): void
    {
        $ids = array_column($this->entries(), 'id');
        $this->assertGreaterThanOrEqual(25, count($ids));
        $this->assertSame($ids, array_values(array_unique($ids)));
        foreach ($ids as $id) {
            $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $id);
        }
    }

    public function test_every_project_has_known_kind_regions_and_sponsors(): void
    {
        foreach ($this->entries() as $e) {
            $this->assertContains($e['kind'], RailProjects::KINDS, $e['id']);
            $this->assertNotEmpty($e['regions'], $e['id']);
            $this->assertSame([], array_diff($e['regions'], self::REGIONS), $e['id']);
            $this->assertNotEmpty($e['porteurs'], $e['id']);
            foreach ($e['porteurs'] as $p) {
                $this->assertNotSame('', trim($p['name']), $e['id']);
                $this->assertNotSame('', trim($p['role']), $e['id']);
            }
            $this->assertNotSame('', trim($e['summary']), $e['id']);
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $e['checked'], $e['id']);
        }
    }

    public function test_every_dated_stage_has_a_source_and_a_valid_date(): void
    {
        $today = '2026-11-23';
        foreach ($this->entries() as $e) {
            $this->assertNotEmpty($e['stages'], $e['id']);
            foreach ($e['stages'] as $s) {
                $where = $e['id'].' '.$s['kind'].' '.$s['date'];
                $this->assertContains($s['kind'], RailProjects::STAGES, $where);
                $this->assertMatchesRegularExpression('/^\d{4}(-\d{2}(-\d{2})?)?$/', $s['date'], $where);
                $this->assertIsBool($s['done'], $where);
                $this->assertNotSame('', trim($s['source']['label']), $where);
                $this->assertMatchesRegularExpression('#^https://#', $s['source']['url'], $where);
                if (isset($s['source']['published'])) {
                    $this->assertMatchesRegularExpression('/^\d{4}(-\d{2}(-\d{2})?)?$/', $s['source']['published'], $where);
                    $this->assertLessThanOrEqual($today, $s['source']['published'], $where);
                }
                // Une étape franchie est passée ; une étape prévue ne l'est pas encore (à la précision près).
                $start = strlen($s['date']) === 4 ? $s['date'].'-01-01' : (strlen($s['date']) === 7 ? $s['date'].'-01' : $s['date']);
                if ($s['done']) {
                    $this->assertLessThanOrEqual($today, $start, $where.' franchie mais datée dans le futur');
                } else {
                    $end = strlen($s['date']) === 4 ? $s['date'].'-12-31' : (strlen($s['date']) === 7 ? $s['date'].'-28' : $s['date']);
                    $this->assertGreaterThanOrEqual($today, $end, $where.' prévue mais déjà passée : à mettre à jour');
                }
            }
        }
    }

    public function test_no_project_is_already_in_service(): void
    {
        foreach ($this->entries() as $e) {
            foreach ($e['stages'] as $s) {
                $this->assertFalse($s['kind'] === 'commissioning' && $s['done'], $e['id'].' est déjà en service : le retirer du catalogue, ou en faire une étape `service`');
            }
        }
    }

    public function test_state_follows_the_last_stage_passed(): void
    {
        $this->assertSame('study', RailProjects::state(['stages' => [['kind' => 'study', 'done' => true]]]));
        $this->assertSame('dup', RailProjects::state(['stages' => [['kind' => 'dup', 'done' => true], ['kind' => 'works', 'done' => false]]]));
        $this->assertSame('works', RailProjects::state(['stages' => [['kind' => 'funding', 'done' => true], ['kind' => 'works', 'done' => true]]]));
        $this->assertSame('suspended', RailProjects::state(['suspended' => true, 'stages' => [['kind' => 'dup', 'done' => true]]]));
    }

    public function test_dates_precision(): void
    {
        $this->assertSame('year', RailProjects::precision('2028'));
        $this->assertSame('month', RailProjects::precision('2028-03'));
        $this->assertSame('day', RailProjects::precision('2028-03-12'));
    }

    public function test_texts_have_no_long_dash_and_no_planned_date_wording_as_fact(): void
    {
        foreach ($this->entries() as $e) {
            $text = json_encode($e, JSON_UNESCAPED_UNICODE);
            $this->assertStringNotContainsString("\u{2014}", $text, $e['id']);
            $this->assertStringNotContainsString('--', str_replace('://', '', $text), $e['id']);
            $this->assertDoesNotMatchRegularExpression('/lien d.int[ée]r[êe]t/iu', $text, $e['id']);
        }
    }
}
