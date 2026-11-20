<?php

namespace Tests\Unit;

use App\Services\WikipediaLineParser;
use PHPUnit\Framework\TestCase;

/**
 * WikipediaLineParser, phrases réelles (chantier « données à sourcer », 2026-10-20) : fausses
 * ouvertures / réouvertures après 2020 qui effaçaient la vitesse 2020 de lignes entières
 * (vff:import-sncf-vmax --previous) et faussaient la chronologie.
 *   - 586000 Ceinture de Bordeaux (oldid 237714178) : section « Gares », « * [[Gare du Bouscat-Sainte-Germaine]]
 *     (ouverte en 2023) » est l'ouverture d'une halte, pas de la ligne ;
 *   - 891000 Collonges - Divonne (oldid 239794629) : « Avantages de la ligne … » d'un rapport du
 *     1 juin 2021 sur une éventuelle réouverture, pas une réouverture.
 */
class WikipediaDonneesSourceesTest extends TestCase
{
    private function events(string $wikitext, string $title): array
    {
        return array_map(fn ($e) => "{$e['type']} {$e['date']}", (new WikipediaLineParser)->parseChronology($wikitext, $title));
    }

    public function test_station_list_section_dates_stations_not_the_line(): void
    {
        $wikitext = "== Histoire ==\nLa ligne est mise en service le 1er juin 1917.\n\n=== Gares ===\nDans le sens inverse des aiguilles d’une montre :\n\n"
            ."* [[Gare du Bouscat-Sainte-Germaine]] (ouverte en 2023)\n* [[Gare de Mérignac-Arlac]] (ouverte en 2010)\n"
            ."* [[Gare de Talence-Médoquine]] (rouverte en {{date|septembre 2025}})\n";

        $events = $this->events($wikitext, 'Ceinture de Bordeaux');

        $this->assertContains('opening 1917-06-01', $events);
        $this->assertSame([], array_values(array_filter($events, fn ($e) => preg_match('/20(10|23|25)/', $e))));
    }

    public function test_advantages_of_a_reopening_are_not_a_reopening(): void
    {
        $wikitext = "== Histoire ==\nLa ligne est mise en service le 1er juin 1899.\n\n=== Avantages de la ligne Bellegarde – Gex – Nyon ===\n"
            ."Le dernier rapport de commission du Forum d'Agglomération du [[Grand Genève]], daté du {{date|1 juin 2021}}, "
            ."liste les avantages suivants à la réouverture de la ligne et à son raccordement à [[Nyon]] :\n";

        $events = $this->events($wikitext, 'Ligne de Collonges - Fort-l\'Écluse à Divonne-les-Bains');

        $this->assertContains('opening 1899-06-01', $events);
        $this->assertNotContains('reopening 2021-06-01', $events);
    }
}
