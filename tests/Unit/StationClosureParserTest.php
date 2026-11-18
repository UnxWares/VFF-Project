<?php

namespace Tests\Unit;

use App\Services\StationClosureParser;
use App\Services\WikipediaLineParser;
use PHPUnit\Framework\TestCase;

/**
 * Fermetures des gares lues dans Wikipédia, Wikidata et OSM
 * (vff:import-station-closures). Cas réels relevés le 2026-10-19.
 */
class StationClosureParserTest extends TestCase
{
    private function parser(): StationClosureParser
    {
        return new StationClosureParser(new WikipediaLineParser);
    }

    /** @return list<string> « type date précision » */
    private function brief(array $events): array
    {
        return array_map(fn ($e) => "{$e['type']} {$e['date']} {$e['precision']}", $events);
    }

    // ── Article de ligne ───────────────────────────────────────────────────

    public function test_line_article_sentence_names_the_closed_halts(): void
    {
        $events = $this->parser()->fromLineArticle(
            "== Histoire ==\nEn 1960, les haltes de [[Tourgéville]] et de [[Gonneville-sur-Mer|Gonneville]] - [[Saint-Vaast-en-Auge|Saint-Vaast]], "
            .'à caractère rural, présentant un trafic faible, sont fermées<ref name="RP63"/>.',
            'Ligne de Mézidon à Trouville - Deauville');

        $this->assertCount(1, $events);
        $this->assertSame(['Tourgéville', 'Gonneville - Saint-Vaast'], $events[0]['names']);
        $this->assertSame('closure 1960-01-01 year', $this->brief($events)[0]);
    }

    public function test_line_article_section_between_two_stations_is_not_a_station_closure(): void
    {
        $events = $this->parser()->fromLineArticle(
            "== Histoire ==\nLa section entre les gares de [[Gare de Rolleville|Rolleville]] et [[Gare des Ifs|Les Ifs]] "
            .'est définitivement fermée au trafic voyageurs le {{date|6 avril 1970}}.',
            'Ligne du Havre-Graville à Tourville-les-Ifs');

        $this->assertSame([], $events);
    }

    public function test_line_article_station_table_with_closure_cell(): void
    {
        $events = $this->parser()->fromLineArticle(
            "== Gares ==\n{| class=\"wikitable\"\n! Gare !! État !! Fréquentation\n|-\n"
            ."| [[Gare de Feuquerolles|Feuquerolles]] || (Fermée <small>en 2018</small>) || {{nts|238}}\n|-\n"
            ."| [[Gare de Blécourt|Blécourt]] || ''Fermée (le quai subsiste)'' || \n|}",
            'Ligne d\'Abbeville à Eu');

        $this->assertCount(1, $events);
        $this->assertSame(['Feuquerolles'], $events[0]['names']);
        $this->assertSame('closure 2018-01-01 year', $this->brief($events)[0]);
        $this->assertSame('tableau', $events[0]['origin']);
    }

    public function test_line_article_list_item_starting_with_a_station(): void
    {
        $events = $this->parser()->fromLineArticle(
            "== Gares ==\n** [[Gare de Paris-Bestiaux|Paris-Bestiaux]], ouverte le {{date|18 octobre 1867}} et fermée le {{date|31 décembre 1977}} ;",
            'Ligne de Petite Ceinture');

        $this->assertSame(['closure 1977-12-31 day'], $this->brief($events));
        $this->assertSame(['Paris-Bestiaux'], $events[0]['names']);
    }

    public function test_station_kept_open_is_not_closed(): void
    {
        $events = $this->parser()->fromLineArticle(
            "== Histoire ==\nEn 1938, la ligne est fermée aux voyageurs ; la gare de Saint-Machin reste ouverte au fret.",
            'Ligne de Alpha à Bravo');

        $this->assertSame([], $events);
    }

    // ── Article de gare ────────────────────────────────────────────────────

    public function test_station_infobox_with_passenger_and_freight_dates(): void
    {
        $events = $this->parser()->fromStationArticle(
            "{{Infobox Gare\n | nom = Quittebeuf\n | fermeture = {{date|27 septembre 1969|dans les chemins de fer}} <small>(voyageurs)</small>"
            ."<br>{{date|30 novembre 1990|dans les chemins de fer}} <small>(marchandises)</small>\n}}\nLa '''gare de Quittebeuf''' est une gare fermée.",
            'Gare de Quittebeuf');

        $this->assertSame(['closure_passenger 1969-09-27 day', 'closure_freight 1990-11-30 day'], $this->brief($events));
        $this->assertSame('infobox', $events[0]['origin']);
    }

    public function test_infobox_qualifier_before_the_date(): void
    {
        $this->assertSame(['closure_passenger 1972-03-06 day', 'closure_freight 1988-02-01 day'],
            $this->brief($this->parser()->closureValue('Voyageurs le 6 mars 1972 Marchandises le 1er février 1988')));
    }

    public function test_vague_closures_keep_their_precision(): void
    {
        $p = $this->parser();
        $this->assertSame(['closure 1950-01-01 after'], $this->brief($p->closureValue(
            $this->plain($p, '{{2e|moitié}} du {{XXe siècle}}'))));
        $this->assertSame(['closure 1970-01-01 after'], $this->brief($p->closureValue($this->plain($p, 'fin du {{s-|XX}}'))));
        $this->assertSame(['closure 1980-01-01 decade'], $this->brief($p->closureValue('années 1980')));
        $this->assertSame(['closure 1984-01-01 before'], $this->brief($p->closureValue('Avant 1984')));
        $this->assertSame([], $p->closureValue('date inconnue'));
    }

    private function plain(StationClosureParser $p, string $wikitext): string
    {
        return (new WikipediaLineParser)->toPlainText(StationClosureParser::expandCenturies($wikitext));
    }

    public function test_station_sentence_with_the_station_as_subject(): void
    {
        $events = $this->parser()->fromStationArticle(
            "La '''gare d'Aouste-sur-Sye''' est une gare ferroviaire française.\n== Histoire ==\n"
            .'Mise en service en [[1885 dans les chemins de fer|1885]], elle est fermée en [[1972 dans les chemins de fer|1972]].',
            'Gare d\'Aouste-sur-Sye');

        $this->assertSame(['closure 1972-01-01 year'], $this->brief($events));
        $this->assertSame('texte', $events[0]['origin']);
    }

    public function test_sentence_about_the_line_is_not_a_station_closure(): void
    {
        $events = $this->parser()->fromStationArticle(
            "La '''gare d'Airvault-Gare''' est une gare ferroviaire française.\n== Histoire ==\n"
            ."Gare de bifurcation, elle est également l'origine de la [[ligne d'Airvault-Gare à Moncontour]] (fermée et déclassée depuis 1954).\n"
            .'La ligne est fermée aux voyageurs le {{date|26 septembre 1969}}.',
            'Gare d\'Airvault-Gare');

        $this->assertSame([], $events);
    }

    public function test_date_of_another_fact_or_a_line_section_is_not_taken(): void
    {
        $p = $this->parser();
        // Chéry-Lury : 1980 est la destruction du bâtiment, pas la fermeture.
        $this->assertSame([], $p->fromStationArticle("La '''gare''' est fermée.\nLa gare est fermée puis le bâtiment voyageurs est détruit en 1980.", 'Gare de Chéry-Lury'));
        // Villecresnes : la portion « entre la gare de X et celle de Y » ferme, pas la gare.
        $this->assertSame([], $p->fromStationArticle("La '''gare''' est fermée.\nLa section de la ligne entre la gare de Villecresnes, au PK 28,500, "
            .'et celle de Santeny - Servon, au PK 31,500, fermée au trafic depuis le 1 novembre 1990, est déclassée.', 'Gare de Villecresnes'));
        // « à la fin de l'année 2011 » : une année, pas les années 2010.
        $this->assertSame(['closure_passenger 2011-01-01 year'], $this->brief($p->fromStationArticle(
            "La '''gare''' est une gare.\nElle est fermée au trafic voyageurs à la fin de l'année 2011.", 'Gare de Saint-Nicolas')));
    }

    public function test_halt_closed_to_passengers_is_closed(): void
    {
        $events = $this->parser()->fromStationArticle(
            "La '''halte de Clérey''' est une gare.\nC'est une halte voyageurs fermée aux voyageurs en 1996.",
            'Halte de Clérey');

        $this->assertSame(['closure 1996-01-01 year'], $this->brief($events));
    }

    // ── Wikidata ───────────────────────────────────────────────────────────

    /** @return array<string, mixed> déclaration Wikidata minimale */
    private function claim(?array $value, array $qualifiers = [], string $rank = 'normal'): array
    {
        return ['rank' => $rank, 'mainsnak' => ['snaktype' => 'value', 'datavalue' => ['value' => $value]], 'qualifiers' => $qualifiers];
    }

    private function time(string $t, int $precision): array
    {
        return ['time' => $t, 'precision' => $precision];
    }

    public function test_wikidata_closed_state_with_start_date(): void
    {
        $ent = ['claims' => ['P5817' => [
            $this->claim(['id' => 'Q11639308'], ['P580' => [['datavalue' => ['value' => $this->time('+1954-11-12T00:00:00Z', 11)]]]]),
            $this->claim(['id' => 'Q55654238'], ['P580' => [['datavalue' => ['value' => $this->time('+1896-05-31T00:00:00Z', 11)]]]]),
        ]]];

        $this->assertSame(['closure 1954-11-12 day'], $this->brief(StationClosureParser::fromWikidata($ent)));
    }

    public function test_wikidata_station_class_ended_and_decade_closure(): void
    {
        $ent = ['claims' => [
            'P31' => [
                $this->claim(['id' => 'Q55488'], ['P582' => [['datavalue' => ['value' => $this->time('+1993-00-00T00:00:00Z', 9)]]]]),
                $this->claim(['id' => 'Q2175765']),
            ],
            'P3999' => [$this->claim($this->time('+1950-00-00T00:00:00Z', 8))],
        ]];

        $this->assertSame(['closure 1993-01-01 year', 'closure 1950-01-01 decade'], $this->brief(StationClosureParser::fromWikidata($ent)));

        // Une nature « gare » toujours valable : la fin d'une autre nature n'est pas une fermeture.
        $ent['claims']['P31'][] = $this->claim(['id' => 'Q55678']);
        $this->assertSame(['closure 1950-01-01 decade'], $this->brief(StationClosureParser::fromWikidata($ent)));
    }

    // ── Texte libre (OSM) ──────────────────────────────────────────────────

    public function test_osm_free_text(): void
    {
        $this->assertSame(['closure_passenger 2016-01-01 year'],
            $this->brief(StationClosureParser::fromFreeText('Gare non desservie depuis 2016 mais toujours répertoriée par la SNCF.')));
        $this->assertSame([], StationClosureParser::fromFreeText('date de fermeture introuvable'));
    }

    public function test_scope_after_the_verb_when_the_date_comes_first(): void
    {
        // Fontenay-le-Comte (gares-relecture, 2026-10-20) : la fin du FRET, pas une fermeture aux voyageurs.
        $events = $this->parser()->fromStationArticle(
            'La gare est fermée au service des voyageurs le 2 mars 1969. En 2007, la gare est désaffectée du trafic marchandises.',
            'Gare de Fontenay-le-Comte');

        $this->assertSame(['closure_passenger 1969-03-02 day', 'closure_freight 2007-01-01 year'], $this->brief($events));
    }
}
