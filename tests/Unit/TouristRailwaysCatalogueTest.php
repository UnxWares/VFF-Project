<?php

namespace Tests\Unit;

use App\Services\TouristRailways;
use PHPUnit\Framework\TestCase;

/**
 * Invariants du catalogue versionné `database/data/chemins_de_fer_touristiques.php` (Wikipédia, Wikidata).
 */
class TouristRailwaysCatalogueTest extends TestCase
{
    /** @return list<array<string, mixed>> */
    private function entries(): array
    {
        return require dirname(__DIR__, 2).'/'.TouristRailways::CATALOGUE;
    }

    public function test_every_entry_has_a_known_type_and_kinds(): void
    {
        foreach ($this->entries() as $e) {
            $this->assertContains($e['type'], TouristRailways::TYPES, $e['name']);
            $this->assertNotEmpty($e['kinds'], $e['name']);
            $this->assertSame([], array_diff($e['kinds'], ['train', 'velorail']), $e['name']);
            if ($e['kinds'] === ['velorail']) {
                $this->assertSame('velorail', $e['type'], $e['name']);
            }
        }
    }

    public function test_gauges_are_plausible_and_regions_are_current_regions(): void
    {
        $regions = ['Auvergne-Rhône-Alpes', 'Bourgogne-Franche-Comté', 'Bretagne', 'Centre-Val de Loire', 'Corse', 'Grand Est', 'Hauts-de-France',
            'Île-de-France', 'Normandie', 'Nouvelle-Aquitaine', 'Occitanie', 'Pays de la Loire', "Provence-Alpes-Côte d'Azur", 'Outre-mer'];
        foreach ($this->entries() as $e) {
            $this->assertContains($e['region'], $regions, $e['name']);
            foreach ($e['gauges'] as $g) {
                $this->assertGreaterThanOrEqual(300, $g, $e['name']);
                $this->assertLessThanOrEqual(1700, $g, $e['name']);
            }
        }
    }

    public function test_names_are_unique_and_every_fact_that_is_given_names_its_source(): void
    {
        $names = array_map(fn (array $e): string => mb_strtolower($e['name']), $this->entries());
        $this->assertSame($names, array_values(array_unique($names)));
        foreach ($this->entries() as $e) {
            foreach (['start_year' => 'start_year_source', 'operator' => 'operator_source', 'website' => 'website_source'] as $fact => $source) {
                if ($e[$fact] !== null) {
                    $this->assertContains($e[$source], ['wp-list', 'wp-article', 'wikidata', 'official-site'], $e['name'].' '.$fact);
                }
            }
            $this->assertContains($e['region_source'] ?? 'wp-list', ['wp-list', 'vff'], $e['name']);
            if ($e['website'] !== null) {
                $this->assertMatchesRegularExpression('#^https?://#', $e['website'], $e['name']);
            }
            if ($e['start_year'] !== null) {
                $this->assertGreaterThanOrEqual(1950, $e['start_year'], $e['name']);
                $this->assertLessThanOrEqual(2026, $e['start_year'], $e['name']);
            }
        }
    }

    public function test_section_season_and_opening_facts_cite_the_article_version(): void
    {
        $withDetails = 0;
        foreach ($this->entries() as $e) {
            $facts = array_filter([$e['section_from'] ?? null, $e['section_to'] ?? null, $e['season'] ?? null, $e['opened_on'] ?? null]);
            if ($facts === []) {
                continue;
            }
            $withDetails++;
            $this->assertMatchesRegularExpression('#^https://fr\.wikipedia\.org/w/index\.php\?title=[^&]+&oldid=\d+$#', (string) ($e['detail_source'] ?? ''), $e['name']);
            if (isset($e['section_to'])) {
                $this->assertNotEmpty($e['section_from'] ?? null, $e['name']);
                $this->assertNotSame($e['section_from'], $e['section_to'], $e['name']);
            }
            if (isset($e['opened_on'])) {
                $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $e['opened_on'], $e['name']);
                $this->assertTrue(checkdate((int) substr($e['opened_on'], 5, 2), (int) substr($e['opened_on'], 8, 2), (int) substr($e['opened_on'], 0, 4)), $e['name']);
            }
        }
        $this->assertGreaterThanOrEqual(50, $withDetails);
    }

    public function test_the_thur_doller_section_starts_at_the_saint_andre_halt_not_at_the_sncf_station(): void
    {
        $byName = array_column($this->entries(), null, 'name');
        // Cernay : le train part de la halte de Cernay-Saint-André, sans liaison depuis la gare SNCF.
        $this->assertSame('Cernay-Saint-André', $byName['Train Thur Doller Alsace']['section_from']);
        $this->assertSame('Sentheim', $byName['Train Thur Doller Alsace']['section_to']);
    }

    public function test_the_settled_incoherences_are_reflected_in_the_catalogue(): void
    {
        $byName = array_column($this->entries(), null, 'name');
        // L'Albret a cessé en 2024 (exploitant et article) : plus « exploité aujourd'hui ».
        $this->assertArrayNotHasKey("Train touristique de l'Albret", $byName);
        // Sud des Ardennes : jusqu'à Attigny (plus de Challerange), exploitation depuis 1988, autorails.
        $ardennes = $byName['Chemin de fer touristique du sud des Ardennes'];
        $this->assertSame(['Amagne - Lucquy', 'Attigny', 1988, 'railcar'], [$ardennes['section_from'], $ardennes['section_to'], $ardennes['start_year'], $ardennes['type']]);
        // Centre-Bretagne : de Pontivy à Auray depuis 2021 (site de l'association).
        $cfcb = $byName['Chemins de fer du Centre-Bretagne'];
        $this->assertSame(['Pontivy', 'Auray', 2021, 'official-site'], [$cfcb['section_from'], $cfcb['section_to'], $cfcb['start_year'], $cfcb['start_year_source']]);
        // Bon-Repos : voie de 60 cm seulement (la voie métrique est un projet).
        $this->assertSame([600], $byName['Chemin de fer de Bon-Repos']['gauges']);
        $this->assertSame('Gouarec', $byName['Chemin de fer de Bon-Repos']['section_from']);
        // AGRIVAP : d'Ambert à La Chaise-Dieu.
        $this->assertSame(['Ambert', 'La Chaise-Dieu'], [$byName['AGRIVAP Les trains de la découverte']['section_from'], $byName['AGRIVAP Les trains de la découverte']['section_to']]);
    }

    public function test_no_long_dash_in_the_catalogue(): void
    {
        $text = (string) file_get_contents(dirname(__DIR__, 2).'/'.TouristRailways::CATALOGUE);
        $this->assertStringNotContainsString("\u{2014}", $text);
    }
}
