<?php

namespace Tests\Unit;

use App\Services\WikipediaLineParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Articles Wikipédia « Ligne de X à Y » (WikipediaLineParser, sans réseau) :
 * dates françaises, portions « de X à Y » / « entre X et Y » / PK, infobox,
 * schéma de ligne, et extraction de la chronologie par portion (prose,
 * listes, tableaux), avec ses garde-fous (projets, fermetures temporaires,
 * autres lignes, voies vertes, gares).
 */
class WikipediaChronologyParserTest extends TestCase
{
    private WikipediaLineParser $p;

    protected function setUp(): void
    {
        $this->p = new WikipediaLineParser;
    }

    /** Événements réduits à [type, date, précision, de, à, origine]. */
    private function events(string $wikitext, string $title = 'Ligne de Carhaix à Morlaix'): array
    {
        return array_map(fn ($e) => [$e['type'], $e['date'], $e['precision'], $e['section_from'], $e['section_to'], $e['origin']],
            $this->p->parseChronology($wikitext, $title));
    }

    // ─── Dates ───────────────────────────────────────────────────────────

    public function test_find_dates_keeps_each_precision_in_text_order(): void
    {
        $dates = $this->p->findDates('La ligne est ouverte le 1er juillet 1882, puis fermée en mars 1939 et déclassée en 1954.');

        $this->assertSame([
            ['pos' => 24, 'date' => '1882-07-01', 'year' => 1882, 'precision' => 'day'],
            ['pos' => 57, 'date' => '1939-03-01', 'year' => 1939, 'precision' => 'month'],
            ['pos' => 83, 'date' => '1954-01-01', 'year' => 1954, 'precision' => 'year'],
        ], $dates);
    }

    public function test_find_dates_positions_are_in_characters_not_bytes(): void
    {
        $dates = $this->p->findDates('Électrifiée, élargie, fermée en 1938');

        $this->assertSame(mb_strpos('Électrifiée, élargie, fermée en 1938', '1938'), $dates[0]['pos']);
    }

    /** @return array<string, array{0: string, 1: list<array{0: string, 1: string}>}> */
    public static function datesProvider(): array
    {
        return [
            'décennie' => ['au cours des années 1970', [['1970-01-01', 'decade']]],
            'plage : première année' => ['travaux 1938-1939', [['1938-01-01', 'year']]],
            'jour impossible → mois' => ['le 31 février 1900', [['1900-02-01', 'month']]],
            'mois sans accent' => ['en aout 1914', [['1914-08-01', 'month']]],
            'date dans un PK' => ['au PK 1920', []],
            'longueur' => ['une ligne de 1850 m', []],
            'tension' => ['électrifiée en 1500 V', []],
            'voyageurs' => ['1935 voyageurs par an', []],
            'tonnage' => ['1900 tonnes', []],
            'numéro' => ['le décret n° 1936', []],
            'avant 1800' => ['en 1789', []],
            'après 2039' => ['en 2045', []],
        ];
    }

    #[DataProvider('datesProvider')]
    public function test_find_dates_ignores_measures_and_numbers(string $text, array $expected): void
    {
        $this->assertSame($expected, array_map(fn ($d) => [$d['date'], $d['precision']], $this->p->findDates($text)));
    }

    public function test_norm_folds_accents_and_saints(): void
    {
        $this->assertSame('ligne de st etienne a ste foy l argentiere', $this->p->norm('Ligne de Saint-Étienne à Sainte-Foy-l’Argentière'));
        $this->assertSame('', $this->p->norm(' - '));
    }

    // ─── Extrémités de la ligne ──────────────────────────────────────────

    /** @return array<string, array{0: ?string, 1: array, 2: list<string>}> */
    public static function endsProvider(): array
    {
        return [
            'contraction du' => ['Ligne du Mans à Angers', [], ['Le Mans', 'Angers']],
            'noms composés' => ['Ligne de Paris-Est à Strasbourg-Ville', [], ['Paris-Est', 'Strasbourg-Ville']],
            'des … aux' => ['Ligne des Aubrais-Orléans aux Sables-d\'Olonne', [], ['Les Aubrais-Orléans', 'Les Sables-d\'Olonne']],
            'élision' => ['Ligne de l\'Arbresle à Lozanne', [], ['L\'Arbresle', 'Lozanne']],
            'de la' => ['Ligne de la Bastille à Marles-en-Brie', [], ['La Bastille', 'Marles-en-Brie']],
            'qualificatif retiré' => ['Ligne de Carhaix à Morlaix (voie métrique)', [], ['Carhaix', 'Morlaix']],
            'repli sur l\'infobox' => [null, ['origine' => 'Lyon', 'destination' => 'Genève'], ['Lyon', 'Genève']],
            'titre hors ligne' => ['Gare de Lyon', [], []],
        ];
    }

    #[DataProvider('endsProvider')]
    public function test_line_ends(?string $title, array $infobox, array $expected): void
    {
        $this->assertSame($expected, $this->p->lineEnds($title, $infobox));
    }

    // ─── Portions ────────────────────────────────────────────────────────

    private function section(string $clause): ?array
    {
        return $this->p->findSection($clause, ['Paris-Bastille', 'Marles-en-Brie']);
    }

    public function test_section_from_to(): void
    {
        $s = $this->section('fermeture du tronçon de Verneuil-l\'Étang à Marles-en-Brie au trafic des voyageurs');

        $this->assertSame('Verneuil-l\'Étang', $s['from']);
        $this->assertSame('Marles-en-Brie', $s['to']);
        $this->assertFalse($s['whole_line']);
        $this->assertFalse($s['other_line']);
    }

    public function test_section_between(): void
    {
        $s = $this->section('la section entre Boissy-Saint-Léger et Verneuil-l\'Étang');

        $this->assertSame(['Boissy-Saint-Léger', 'Verneuil-l\'Étang'], [$s['from'], $s['to']]);
    }

    public function test_section_between_the_line_ends_is_the_whole_line(): void
    {
        $s = $this->section('de Paris-Bastille à Marles-en-Brie');

        $this->assertTrue($s['whole_line']);
        $this->assertNull($s['from']);
    }

    public function test_section_by_kilometre_points(): void
    {
        $s = $this->section('du PK 12,5 au PK 20,3');
        $single = $this->section('Section à Yèbles (PK 46,677 à 46,735)');

        $this->assertSame([12500, 20300], [$s['pk_start'], $s['pk_end']]);
        $this->assertSame([46677, 46735, 'Yèbles', null], [$single['pk_start'], $single['pk_end'], $single['from'], $single['to']]);
    }

    public function test_section_dash_at_the_head_of_a_cell(): void
    {
        $s = $this->section('Vincennes – Boissy-Saint-Léger : fermée');

        $this->assertSame(['Vincennes', 'Boissy-Saint-Léger'], [$s['from'], $s['to']]);
    }

    public function test_extension_names_only_the_destination(): void
    {
        $s = $this->section('puis prolongée jusqu\'à Gretz-Armainvilliers');

        $this->assertTrue($s['only_to']);
        $this->assertSame('Gretz-Armainvilliers', $s['to']);
        $this->assertNull($s['from']);
    }

    public function test_company_names_are_not_sections(): void
    {
        $this->assertNull($this->section('la Compagnie du chemin de fer de Paris à Strasbourg'));
        $this->assertNull($this->section('rien du tout ici'));
    }

    public function test_link_to_another_line_is_flagged(): void
    {
        $s = $this->section('la fermeture de la ⟦ligne de Gretz à Coulommiers⟧');

        $this->assertTrue($s['other_line']);
    }

    // ─── Infobox, texte brut, schéma ─────────────────────────────────────

    private const ARTICLE = <<<'WT'
        {{Infobox Ligne ferroviaire
        | nom = Ligne de Carhaix à Morlaix
        | origine = [[Gare de Carhaix|Carhaix]]
        | destination = Morlaix
        | mise en service = {{date|1|juillet|1891}}
        | fermeture = 1967<!-- commentaire -->
        | longueur = 48,5 km
        | concessionnaire1 = [[Compagnie des chemins de fer de l'Ouest|Ouest]]
        | dateconc1 = 1883
        | finconc1 = 1908
        | régions = [[Bretagne]]
        | départements = [[Finistère]], [[Côtes-d'Armor]]
        | schéma = {{BS-header}}
        }}
        La '''ligne de Carhaix à Morlaix''' est une ancienne ligne<ref>Source.</ref> du [[Réseau breton]].

        == Histoire ==
        La ligne est ouverte le {{date|1|juillet|1891}}.
        La section de Plounéour-Ménez à Morlaix est fermée aux voyageurs le 15 mai 1938. Elle est fermée au trafic des marchandises en 1967.

        == Chronologie ==
        * 12 mai 1938 : fermeture aux voyageurs de Carhaix à Plounéour-Ménez ;
        * 1970 : dépose des voies de la section de Carhaix à Plounéour-Ménez.

        == Notes et références ==
        * 1999 : fermeture de la librairie.
        WT;

    public function test_infobox_concession_descriptions_have_no_long_dash(): void
    {
        $wikitext = <<<'WT'
        {{Infobox Ligne ferroviaire
        | nom = Ligne d'Orléans à Rouen
        | concessionnaire1 = Orléans — Rouen
        | dateconc1 = 1852
        | finconc1 = 1855
        }}
        La '''ligne d'Orléans à Rouen''' est une ligne.
        WT;

        $descriptions = array_column(array_filter(
            $this->p->parseChronology($wikitext, "Ligne d'Orléans à Rouen"),
            fn ($e) => in_array($e['type'], ['concession', 'concession_end'], true),
        ), 'description');

        $this->assertCount(2, $descriptions);
        foreach ($descriptions as $d) {
            $this->assertStringNotContainsString('—', $d);
            $this->assertStringContainsString('Orléans - Rouen', $d);
        }
    }

    public function test_infobox_values_are_cleaned_and_the_route_diagram_dropped(): void
    {
        $infobox = $this->p->parseInfobox(self::ARTICLE);

        $this->assertSame('Carhaix', $infobox['origine']);
        $this->assertSame('1 juillet 1891', $infobox['mise en service']);
        $this->assertSame('1967', $infobox['fermeture']);
        $this->assertSame('Ouest', $infobox['concessionnaire1']);
        $this->assertSame('', $infobox['schéma']);
        $this->assertSame([], $this->p->parseInfobox('Pas d\'infobox ici.'));
    }

    public function test_companies_regions_and_departements_from_the_infobox(): void
    {
        $infobox = $this->p->parseInfobox(self::ARTICLE);

        $this->assertSame([['operator' => 'Ouest', 'from_year' => 1883, 'to_year' => 1908]], $this->p->parseCompanies(self::ARTICLE, $infobox));
        $this->assertSame(['Bretagne'], $this->p->parseRegions($infobox));
        $this->assertSame(['Finistère', 'Côtes-d\'Armor'], $this->p->parseDepartements($infobox));
    }

    public function test_plain_text_marks_links_to_other_lines(): void
    {
        $text = $this->p->toPlainText(
            "La [[ligne de Paris à Brest|ligne]] et la [[Ligne de Rennes à Redon]] {{date|12|mai|1938}}, ''gras'' &amp; <br/>fin"
            .' [[Catégorie:Ligne]] [https://x.org lien]<ref>note</ref> [[Fichier:X.jpg|thumb|[[légende]] ici]].',
            'Ligne de Paris à Brest',
        );

        $this->assertSame('La ligne et la ⟦Ligne de Rennes à Redon⟧ 12 mai 1938, gras & fin lien .', $text);
    }

    public function test_plain_text_renders_common_templates(): void
    {
        $this->assertSame('1er janvier 48.5 km X 1234 XIXe siècle St-Brieuc',
            $this->p->toPlainText('{{1er}} janvier {{unité|48.5|km}} {{Lien|fr=Gare de X|texte=X}} {{formatnum:1234}} {{Inconnu|a|b}} {{s-|XIX}} {{St}}-Brieuc'));
        $this->assertSame('1 octobre 1953', $this->p->toPlainText('{{date|1|10|1953}}'));
    }

    public function test_route_diagram_stations_and_kilometre_points(): void
    {
        $diagram = <<<'WT'
            {{BS-début}}
            {{BS2bis|KBHFa||0,000|[[Gare de Carhaix|Carhaix]]|}}
            {{BS2bis|HST||12,5|Poullaouen (halte)|}}
            {{BS2bis|BHF||{{BSkm|102,468|25,100}}|[[Gare de Scrignac|Scrignac]]|}}
            {{BS2bis|TUNNEL1||30,0|Tunnel de X|}}
            {{BS2bis|exBHF||48,500|Morlaix|}}
            {{BS2bis|BHF||abc|Nulle|}}
            {{BS-fin}}
            WT;

        $this->assertSame([
            ['name' => 'Carhaix', 'pk_m' => 0, 'icon' => 'KBHFa'],
            ['name' => 'Poullaouen', 'pk_m' => 12500, 'icon' => 'HST'],
            // {{BSkm|PK d'origine|PK sur cette ligne}} : le dernier.
            ['name' => 'Scrignac', 'pk_m' => 25100, 'icon' => 'BHF'],
            ['name' => 'Morlaix', 'pk_m' => 48500, 'icon' => 'exBHF'],
        ], $this->p->parseRouteDiagram($diagram));
        $this->assertSame([], $this->p->parseRouteDiagram('Pas de schéma.'));
    }

    // ─── Chronologie ─────────────────────────────────────────────────────

    public function test_chronology_of_a_full_article(): void
    {
        $this->assertSame([
            ['concession', '1883-01-01', 'year', null, null, 'infobox'],
            ['opening', '1891-07-01', 'day', null, null, 'texte'],
            ['concession_end', '1908-01-01', 'year', null, null, 'infobox'],
            ['closure_passenger', '1938-05-12', 'day', 'Carhaix', 'Plounéour-Ménez', 'liste'],
            ['closure_passenger', '1938-05-15', 'day', 'Plounéour-Ménez', 'Morlaix', 'texte'],
            // « Elle est fermée … » : la portion de la phrase précédente.
            ['closure_freight', '1967-01-01', 'year', 'Plounéour-Ménez', 'Morlaix', 'texte'],
            ['dismantling', '1970-01-01', 'year', 'Carhaix', 'Plounéour-Ménez', 'liste'],
        ], $this->events(self::ARTICLE));
    }

    public function test_events_carry_their_description_quote_and_confidence(): void
    {
        $events = $this->p->parseChronology(self::ARTICLE, 'Ligne de Carhaix à Morlaix');
        $list = collect($events)->firstWhere('origin', 'liste');

        $this->assertSame('Fermeture aux voyageurs de Carhaix à Plounéour-Ménez (Wikipédia, liste, « Chronologie »)', $list['description']);
        $this->assertSame('high', $list['confidence']);
        // Citation : la proposition de l'événement (assez longue), sinon la phrase entière.
        $this->assertSame('fermeture aux voyageurs de Carhaix à Plounéour-Ménez', $list['source_quote']);
        foreach ($events as $e) {
            $this->assertArrayHasKey('portion_unknown', $e);
            $this->assertContains($e['confidence'], ['high', 'medium', 'low']);
            $this->assertArrayHasKey($e['type'], WikipediaLineParser::TYPE_LABELS);
        }
    }

    public function test_infobox_closure_is_a_fallback_only(): void
    {
        $events = $this->events("{{Infobox Ligne ferroviaire\n| fermeture = 1967\n| mise en service = 1891\n}}\nTexte.");

        $this->assertSame([['opening', '1891-01-01', 'year', null, null, 'infobox'], ['closure', '1967-01-01', 'year', null, null, 'infobox']], $events);
        $closure = collect($this->p->parseChronology("{{Infobox Ligne ferroviaire\n| fermeture = 1967\n}}", 'Ligne de Carhaix à Morlaix'))->firstWhere('type', 'closure');
        $this->assertSame('low', $closure['confidence']);
        $this->assertStringContainsString('nature du trafic non précisée', $closure['description']);
    }

    public function test_staged_opening_in_the_infobox_marks_the_first_dates_as_partial(): void
    {
        $events = $this->p->parseChronology("{{Infobox Ligne ferroviaire\n| mise en service = 1856\n| mise en service2 = 1871\n}}", 'Ligne de Carhaix à Morlaix');

        $this->assertSame([true, false], array_column($events, 'portion_unknown'));
        $this->assertSame(['1856-01-01', '1871-01-01'], array_column($events, 'date'));
    }

    /** @return array<string, array{0: string}> */
    public static function nonEventProvider(): array
    {
        return [
            'fermeture temporaire pour travaux' => ['La ligne est fermée temporairement pour travaux du 3 mars 2015 au 12 avril 2015.'],
            'projet de réouverture' => ['La réouverture de la ligne est envisagée en 2030.'],
            'voie verte' => ['L\'emprise est transformée en voie verte ouverte aux cyclistes en 2005.'],
            'fermeture d\'une gare' => ['La gare de Scrignac est fermée en 1950.'],
            'autre ligne' => ['La [[ligne de Guingamp à Carhaix]] est fermée en 1950.'],
            'avant 1820' => ['La ligne est ouverte en 1791.'],
            'date future' => ['La ligne est ouverte en 2035.'],
            'texte sans sujet' => ['En 1950, la fermeture des mines entraîne un déclin.'],
            'matériel remis en service' => ['La ligne voit 4 autorails remis en service en 1990.'],
            'état, pas événement' => ['La section est fermée depuis plusieurs années, en 2010.'],
        ];
    }

    #[DataProvider('nonEventProvider')]
    public function test_sentences_that_are_not_events_of_the_line(string $sentence): void
    {
        $this->assertSame([], $this->events("== Histoire ==\n{$sentence}"));
    }

    public function test_reference_sections_are_ignored(): void
    {
        $this->assertSame([], $this->events("== Notes et références ==\n* 1999 : fermeture de la ligne.\n== Bibliographie ==\nLa ligne est fermée en 1950."));
    }

    /** @return array<string, array{0: string, 1: string, 2: string}> */
    public static function typeProvider(): array
    {
        return [
            'tout trafic' => ['La ligne est fermée à tout trafic le 1er janvier 1967.', 'closure', '1967-01-01'],
            'voyageurs' => ['La ligne est fermée aux voyageurs le 1er octobre 1938.', 'closure_passenger', '1938-10-01'],
            'marchandises' => ['La ligne est fermée au trafic des marchandises en 1967.', 'closure_freight', '1967-01-01'],
            'report sur route' => ['Le service de la ligne est transféré sur route le 1er octobre 1938.', 'closure_passenger', '1938-10-01'],
            'déclassement' => ['La ligne est déclassée le 25 novembre 1975.', 'declassification', '1975-11-25'],
            'électrification' => ['La ligne est électrifiée en 1925.', 'electrification', '1925-01-01'],
            'réouverture aux voyageurs' => ['La ligne est rouverte aux voyageurs le 9 décembre 2007.', 'reopening_passenger', '2007-12-09'],
        ];
    }

    #[DataProvider('typeProvider')]
    public function test_event_type_and_traffic_qualifier(string $sentence, string $type, string $date): void
    {
        $this->assertSame([[$type, $date]], array_map(fn ($e) => [$e[0], $e[1]], $this->events("== Histoire ==\n{$sentence}")));
    }

    public function test_freight_kept_means_closure_to_passengers_only(): void
    {
        $events = $this->events("== Histoire ==\nLa ligne est fermée aux voyageurs le 1er octobre 1938, le trafic des marchandises étant maintenu.");

        $this->assertSame('closure_passenger', $events[0][0]);
    }

    public function test_parallel_structure_inherits_the_verb(): void
    {
        $events = $this->events("== Histoire ==\nLa ligne est fermée au service des voyageurs le 1er octobre 1938 et des marchandises le 1er mars 1969.");

        $this->assertSame([['closure_passenger', '1938-10-01'], ['closure_freight', '1969-03-01']], array_map(fn ($e) => [$e[0], $e[1]], $events));
    }

    public function test_long_dash_of_the_source_never_reaches_displayed_texts(): void
    {
        $events = $this->p->parseChronology("== Histoire — débuts ==\nLa ligne — longue de 48 km — est fermée aux voyageurs le 1er octobre 1938.", 'Ligne de Carhaix à Morlaix');

        $this->assertCount(1, $events);
        $this->assertStringNotContainsString('—', $events[0]['description']);
        $this->assertStringNotContainsString('—', $events[0]['source_quote']);
    }

    public function test_list_bullets_inherit_the_date_and_section_of_their_parent(): void
    {
        $events = $this->events("== Chronologie ==\n* 1er juillet 1891 : ouverture de Carhaix à Morlaix\n* 1938 : fermeture aux voyageurs\n");

        // Ouverture entre les extrémités de la ligne : toute la ligne (pas de portion).
        $this->assertSame([['opening', '1891-07-01', 'day', null, null, 'liste'], ['closure_passenger', '1938-01-01', 'year', null, null, 'liste']], $events);
        $whole = $this->p->parseChronology("== Chronologie ==\n* 1er juillet 1891 : ouverture de Carhaix à Morlaix\n", 'Ligne de Carhaix à Morlaix');
        $this->assertStringContainsString('de toute la ligne', $whole[0]['description']);
    }

    public function test_heading_names_the_default_event_type_of_its_list(): void
    {
        $events = $this->events("== Dates de déclassement ==\n* de Scrignac à Morlaix : 12 juin 1972");

        $this->assertSame([['declassification', '1972-06-12', 'day', 'Scrignac', 'Morlaix', 'liste']], $events);
    }

    public function test_chronology_table_gives_one_event_per_section_and_column(): void
    {
        $events = $this->events(<<<'WT'
            == Chronologie ==
            {| class="wikitable"
            ! Section !! Ouverture !! Fermeture aux voyageurs !! Déclassement
            |-
            | Carhaix - Scrignac || 1er juillet 1891 || 12 mai 1938 || 1972
            |-
            | Scrignac - Morlaix || 1er juillet 1891 || 12 mai 1938 ||
            |}
            WT);

        $this->assertSame([
            ['opening', '1891-07-01', 'day', 'Carhaix', 'Scrignac', 'tableau'],
            ['opening', '1891-07-01', 'day', 'Scrignac', 'Morlaix', 'tableau'],
            ['closure_passenger', '1938-05-12', 'day', 'Carhaix', 'Scrignac', 'tableau'],
            ['closure_passenger', '1938-05-12', 'day', 'Scrignac', 'Morlaix', 'tableau'],
            ['declassification', '1972-01-01', 'year', 'Carhaix', 'Scrignac', 'tableau'],
        ], $events);
    }

    public function test_same_fact_in_a_list_and_in_the_text_is_kept_once(): void
    {
        $events = $this->events("== Histoire ==\nLa section de Carhaix à Scrignac est fermée aux voyageurs le 12 mai 1938.\n== Chronologie ==\n* 12 mai 1938 : fermeture aux voyageurs de Carhaix à Scrignac");

        $this->assertSame([['closure_passenger', '1938-05-12', 'day', 'Carhaix', 'Scrignac', 'liste']], $events);
    }

    public function test_section_cited_but_not_understood_is_never_the_whole_line(): void
    {
        $events = $this->p->parseChronology("== Histoire ==\nLa ligne est fermée partiellement en 1950.", 'Ligne de Carhaix à Morlaix');

        $this->assertCount(1, $events);
        $this->assertTrue($events[0]['portion_unknown']);
    }

    public function test_project_headings(): void
    {
        $this->assertTrue($this->p->isProjectHeading('Histoire > Projets'));
        $this->assertTrue($this->p->isProjectHeading('Avenir'));
        $this->assertTrue($this->p->isProjectHeading('L\'avenir de la ligne'));
        $this->assertFalse($this->p->isProjectHeading('Histoire > Ouverture'));
        $this->assertFalse($this->p->isProjectHeading('Projets > Histoire'));
    }

    public function test_empty_article_has_no_event(): void
    {
        $this->assertSame([], $this->p->parseChronology('', null));
        $this->assertSame([], $this->p->parseEvents('Texte sans date.', 'Ligne de Carhaix à Morlaix'));
    }

    // ─── Régressions ─────────────────────────────────────────────────────

    /**
     * « Section de X à Y » en tête de puce ou de titre de section donnait
     * section_from = « Section de X » (le motif « X à Y » en tête de cellule
     * avalait le mot « Section ») : 26 événements wikipedia de la base réelle
     * (2026-10-19), ex. « Fermeture aux voyageurs de Section de Rochy-Condé à Compiègne ».
     * Corrigé depuis dans WikipediaLineParser.
     */
    public function test_section_word_at_the_head_of_a_bullet_is_not_part_of_the_place(): void
    {
        $bullet = $this->events("== Dates de déclassement ==\n* Section de Scrignac à Morlaix (PK 25,100 à 48,500) : 12 juin 1972");
        $heading = $this->events("== Section de Rochy-Condé à Compiègne ==\nLa ligne est fermée aux voyageurs le 1er octobre 1938.", 'Ligne de Rochy-Condé à Soissons');

        $this->assertSame('Scrignac', $bullet[0][3]);
        $this->assertSame('Rochy-Condé', $heading[0][3]);
    }
}
