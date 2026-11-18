<?php

namespace Tests\Unit;

use App\Services\WikipediaLineParser;
use PHPUnit\Framework\TestCase;

/**
 * Événements d'AUTRES lignes cités dans un article (contrôle qualité du 2026-10-20) : le parseur ne
 * garde que les événements de la ligne elle-même. Phrases réelles des articles concernés.
 */
class WikipediaForeignEventsTest extends TestCase
{
    private function events(string $body, string $title): array
    {
        return (new WikipediaLineParser)->parseChronology($body, $title);
    }

    /** @return list<string> « type date from>to » */
    private function summary(array $events): array
    {
        return array_map(fn ($e) => $e['type'].' '.$e['date'].' '.($e['section_from'] ?? '-').'>'.($e['section_to'] ?? '-'), $events);
    }

    public function test_comparison_with_a_foreign_line_is_not_an_event(): void
    {
        $e = $this->events("== Historique ==\n=== Une ligne nouvelle vers le sud-est ===\nCette ligne à grande vitesse est inspirée par le précédent du [[Shinkansen Tōkaidō]], ouvert au [[Japon]] en [[1964]] entre [[Tokyo]] et [[Osaka]].\n", 'LGV Sud-Est');
        $this->assertSame([], $this->summary($e), 'pas d’« ouverture de Tokyo à Osaka » sur la LGV Sud-Est');
    }

    public function test_lnobr_link_keeps_the_subject_of_another_line(): void
    {
        $e = $this->events("== Histoire ==\nLa {{lnobr|LGV 1}} n'est ouverte en totalité jusqu'à Bruxelles que le {{Date|14|décembre|1997}}, à la suite de nombreux désaccords.\n", 'LGV Nord');
        $this->assertSame([], $this->summary($e), 'ouverture de la LGV 1 belge, pas de la LGV Nord');
        $this->assertStringContainsString('⟦LGV 1⟧', (new WikipediaLineParser)->toPlainText('La {{lnobr|LGV 1}} est', 'LGV Nord'));
    }

    public function test_reopening_of_another_line_in_parentheses_is_not_an_event(): void
    {
        $e = $this->events("== La ligne aujourd'hui ==\nLa ligne voit circuler des trains de la [[Ligne du Haut-Bugey|ligne du Haut-Bugey]] (rouverte après modernisation le {{Date|12 décembre 2010}}), des TER Lyon - Genève et des TER de Genève à Culoz via Bellegarde.\n",
            'Ligne de Lyon-Perrache à Genève (frontière)');
        $this->assertSame([], $this->summary($e));
    }

    public function test_itinerary_is_not_a_section_and_staged_opening_is_not_a_whole_line_date(): void
    {
        $e = $this->events("Ouverte à partir de 1847 par la Compagnie des chemins de fer du Nord, elle constitue une partie de la grande artère historique entre [[Paris]] et [[Bruxelles]].\n", 'Ligne de Creil à Jeumont');
        $this->assertSame([], $this->summary($e), 'ni « ouverture de Paris à Bruxelles », ni ouverture de toute la ligne en 1847');
    }

    public function test_line_named_by_its_ends_as_subject_is_another_line(): void
    {
        $e = $this->events("== Histoire ==\nEn février 1859, la ligne Le Mans - Argentan est prolongée jusqu'à Mézidon.\n", 'Ligne de Falaise à Berjou');
        $this->assertSame([], $this->summary($e));
    }

    public function test_aside_about_another_electrification_is_not_an_event(): void
    {
        $e = $this->events("== Électrification ==\nLes caténaires arrivent en gare d'Amagne - Lucquy le 8 décembre 1964 (électrification entre Reims et Mohon).\n", "Ligne d'Hirson à Amagne-Lucquy");
        $this->assertSame([], array_filter($this->summary($e), fn ($s) => str_contains($s, 'Reims')));
    }

    public function test_aside_commenting_the_main_section_keeps_the_event(): void
    {
        $e = $this->events("== Histoire ==\nLa ligne est mise en service par étapes :\n* de Courty à Pont-de-Dore le 15 mai 1872 (mise en service de la ligne de Clermont-Ferrand à Thiers) ;\n",
            'Ligne de Saint-Germain-des-Fossés à Darsac');
        $this->assertContains('opening 1872-05-15 Courty>Pont-de-Dore', $this->summary($e));
    }

    public function test_filing_a_concession_is_not_a_dismantling(): void
    {
        $e = $this->events("== Histoire ==\nLe 9 mai 1867 est créée la Compagnie du chemin de fer de la Seudre qui dépose une concession pour la création d'une ligne entre Royan, Saujon et Pons.\n", 'Ligne de Saintes à Royan');
        $this->assertSame([], array_filter($this->summary($e), fn ($s) => str_starts_with($s, 'dismantling')));
    }

    public function test_road_section_is_not_the_line_section(): void
    {
        $e = $this->events("== Histoire ==\nLors de la construction de l'A85 entre Corzé et Bourgueil de 1995 à 1997, la plate-forme de la ligne a été utilisée, ce qui a entraîné la dépose de la voie entre Baugé et Longué en 1997.\n", 'Ligne de La Flèche à Vivy');
        $this->assertSame([], array_filter($this->summary($e), fn ($s) => str_contains($s, 'Corzé')));
    }

    public function test_new_itinerary_put_in_service_is_still_a_section(): void
    {
        $e = $this->events("== Histoire ==\nLe nouvel itinéraire de Saint-Denis à Creil, long de quarante-trois kilomètres, est mis en service le 10 mai 1859.\n", 'Ligne de Paris-Nord à Lille');
        $this->assertContains('opening 1859-05-10 Saint-Denis>Creil', $this->summary($e));
    }

    public function test_previous_sections_are_not_a_comparison(): void
    {
        $e = $this->events("== Histoire ==\nLa ligne est ouverte en trois fois : de Paris à Viroflay en 1840, et le tronçon de Viroflay-Rive-Gauche à Issy-Plaine, destiné à relier les précédents, ouvert en 1902.\n", 'Ligne des Moulineaux');
        $this->assertContains('opening 1902-01-01 Viroflay-Rive-Gauche>Issy-Plaine', $this->summary($e));
    }

    public function test_like_many_lines_the_line_itself_closes(): void
    {
        $e = $this->events("Cette transversale relie la [[Ligne de La Plaine à Hirson et Anor (frontière)|ligne de La Plaine à Hirson]], constituant l'axe de Paris-Nord à Laon, à la [[Ligne de Trilport à Bazoches|ligne de Trilport à Bazoches]]. Comme de nombreuses lignes secondaires, elle est fermée au trafic de voyageurs en 1939.\n",
            "Ligne d'Ormoy-Villers à Mareuil-sur-Ourcq");
        $this->assertSame(['closure_passenger 1939-01-01 ->-'], $this->summary($e), 'la ligne elle-même, pas « de Paris-Nord à Laon »');
    }

    public function test_date_of_a_clause_about_another_subject_does_not_date_the_previous_verb(): void
    {
        // 794000 : déclassement du 07/07/1905 (date de la DUP de Brioude - Saint-Flour), avant l'ouverture de 1910 :
        // la gare de Brioude était « desservie de 1908 à 1905 ».
        $e = $this->events("== Histoire ==\nFinalement, en l'absence de début de réalisation de la ligne, elle est déclassée et une ligne de « Brioude à Saint-Flour » est déclarée d'utilité publique par une loi le {{date|7 juillet 1905}}.\n",
            'Ligne de Beaumont-Loriat à Saint-Flour');
        $this->assertSame([], array_values(array_filter($this->summary($e), fn ($s) => str_starts_with($s, 'declassification'))));
        // Même sujet : la date est partagée (« fermée et déposée en 1954 »).
        $e = $this->events("== Histoire ==\nLa ligne est fermée et déposée en 1954.\n", 'Ligne de Charlie à Delta');
        $this->assertContains('1954', array_map(fn ($x) => substr($x['date'], 0, 4), array_filter($e, fn ($x) => $x['type'] === 'dismantling')));
        $this->assertContains('1954', array_map(fn ($x) => substr($x['date'], 0, 4), array_filter($e, fn ($x) => str_starts_with($x['type'], 'closure'))));
    }
}
