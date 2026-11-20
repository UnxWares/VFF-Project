<?php

namespace Tests\Unit;

use App\Services\WikipediaLineParser;
use PHPUnit\Framework\TestCase;

/**
 * Chronologies Wikipédia : faux événements relevés sur la carte (2026-10-19).
 *   - « Le député … s'est exprimé en 2005 sur la réouverture » (section « Projets »)
 *     donnait une réouverture de Laval à Mayenne en 2005 (ligne 436000 en service
 *     sur la carte de 2010 et 2020, puis plus rien en 2030) ;
 *   - « 4 engins AMG 800 sont remis en service » donnait une réouverture de la ligne ;
 *   - « le 15 novembre 1890 de cette gare jusqu'au terminus de Calvi » n'était pas lu.
 */
class WikipediaLineParserTest extends TestCase
{
    private function events(string $body, string $title = 'Ligne de La Chapelle-Anthenaise à Flers'): array
    {
        return (new WikipediaLineParser)->parseChronology($body, $title);
    }

    private function find(array $events, string $type): array
    {
        return array_values(array_filter($events, fn ($e) => $e['type'] === $type));
    }

    public function test_project_section_debate_is_not_a_reopening(): void
    {
        $events = $this->events("== Projets ==\nLe député UMP de la troisième circonscription de la Mayenne, Yannick Favennec, "
            ."s'est exprimé en 2005 sur la réouverture au trafic ferroviaire de la section entre la gare de Laval et celle de Mayenne.");

        $this->assertSame([], $this->find($events, 'reopening'));
    }

    public function test_topic_prepositions_are_not_events_whatever_the_heading(): void
    {
        $events = $this->events("== Historique ==\n=== Fermeture, projets et voie verte ===\n"
            ."Une page Facebook pour la remise en service de la ligne est créée en 2014, suivie par un site internet en octobre 2015.\n"
            ."== Histoire ==\nLe Sidec s'est un temps engagé pour la réouverture de la ligne, en avril 2009.");

        $this->assertSame([], $this->find($events, 'reopening'));
    }

    public function test_real_reopening_dated_to_the_day_under_a_project_heading_is_kept(): void
    {
        $events = $this->events("== Historique ==\n=== Projet de réouverture entre Oloron-Sainte-Marie et Bedous ===\n"
            ."À l'initiative du conseil régional d'Aquitaine, la section d'Oloron-Sainte-Marie à Bedous, longue de 25 km, est rouverte le 26 juin 2016.",
            'Ligne de Pau à Canfranc');

        $reopen = $this->find($events, 'reopening');
        $this->assertCount(1, $reopen);
        $this->assertSame('2016-06-26', $reopen[0]['date']);
        $this->assertSame('Bedous', $reopen[0]['section_to']);
    }

    public function test_historical_project_headings_keep_their_openings(): void
    {
        $events = $this->events("== Histoire ==\n=== Genèse du projet ===\nEn 1868, une courte ligne est ouverte entre Flers et Berjou via Condé-sur-Noireau.");

        $this->assertCount(1, $this->find($events, 'opening'));
    }

    public function test_rolling_stock_back_in_service_is_not_a_reopening(): void
    {
        $events = $this->events("== Histoire ==\n=== Chronologie ===\n* été 2011 : 4 engins AMG 800 sont remis en service.\n"
            .'* été 2012 : 8 engins AMG 800 sont remis en service.', 'Ligne de Bastia à Ajaccio');

        $this->assertSame([], $this->find($events, 'reopening'));
    }

    public function test_something_put_in_service_on_the_line_is_not_an_opening(): void
    {
        $events = $this->events("== Histoire ==\nLes autorails Renault arrivent ensuite. Ils sont mis en service sur la ligne Nice-Digne en 1935 et permettent une liaison plus rapide.",
            'Ligne de Nice à Digne');

        $this->assertSame([], $this->find($events, 'opening'));
    }

    public function test_anaphoric_section_continues_from_previous_end(): void
    {
        $events = $this->events("== Histoire ==\nL'inauguration de la ligne se fait en deux temps : le 10 janvier 1889 de Ponte-Leccia à Palasca, "
            ."et le 15 novembre 1890 de cette gare jusqu'au terminus de Calvi.", 'Ligne de Ponte-Leccia à Calvi');

        $openings = $this->find($events, 'opening');
        $this->assertCount(2, $openings);
        $this->assertSame(['Ponte-Leccia', 'Palasca', '1889-01-10'], [$openings[0]['section_from'], $openings[0]['section_to'], $openings[0]['date']]);
        $this->assertSame(['Palasca', 'Calvi', '1890-11-15'], [$openings[1]['section_from'], $openings[1]['section_to'], $openings[1]['date']]);
    }

    public function test_first_trains_running_is_an_opening(): void
    {
        $events = $this->events("== Histoire ==\nLes premiers trains commencent à circuler entre Ajaccio et Bocognano le 1 décembre 1888 (41,1 km).",
            'Ligne de Bastia à Ajaccio');

        $openings = $this->find($events, 'opening');
        $this->assertCount(1, $openings);
        $this->assertSame(['Ajaccio', 'Bocognano', '1888-12-01'], [$openings[0]['section_from'], $openings[0]['section_to'], $openings[0]['date']]);
    }

    public function test_place_name_stops_at_sentence_end(): void
    {
        $events = $this->events("== Histoire ==\n=== Chronologie ===\n* 3 décembre 1894 : inauguration de la dernière section manquante de la ligne "
            .'entre Vivario et Vizzavona. La longueur totale de la ligne est de 154 km.', 'Ligne de Bastia à Ajaccio');

        $this->assertSame('Vizzavona', $this->find($events, 'opening')[0]['section_to']);
    }

    public function test_project_heading_detection(): void
    {
        $p = new WikipediaLineParser;
        foreach (['Projets', 'Histoire > Projet de réouverture entre X et Y', 'Perspectives > Calendrier prévisionnel', 'Quel avenir pour la ligne ?', 'Avenir'] as $h) {
            $this->assertTrue($p->isProjectHeading($h), $h);
        }
        foreach (['Histoire > Le projet', 'Histoire > Genèse du projet', 'Histoire de la ligne > Premiers projets', 'Historique > Fermeture, projets et voie verte'] as $h) {
            $this->assertFalse($p->isProjectHeading($h), $h);
        }
    }
}
