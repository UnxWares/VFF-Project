<?php

namespace Tests\Unit;

use App\Services\SecondaryNetworkParser;
use App\Services\WikipediaLineParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Articles Wikipédia des réseaux secondaires (SecondaryNetworkParser) :
 * itinéraires des lignes (titre d'article, puces, titres de section),
 * événements datés d'un fragment, plages « (1905 - 1937) », qualificatifs de
 * trafic, et fiches d'un article complet. Aucune base, aucun réseau.
 */
class SecondaryNetworkParserTest extends TestCase
{
    private SecondaryNetworkParser $p;

    protected function setUp(): void
    {
        $this->p = new SecondaryNetworkParser(new WikipediaLineParser);
    }

    /** @param  list<array{label: string}>|null  $places */
    private static function labels(?array $places): ?array
    {
        return $places === null ? null : array_column($places, 'label');
    }

    /** Événements réduits à [type, date, précision, confiance, plage]. */
    private function events(string $text, string $mode = 'texte', ?string $base = null): array
    {
        return array_map(fn ($e) => [$e['type'], $e['date'], $e['precision'], $e['confidence'], $e['range']],
            $this->p->extractEvents($text, $mode, false, $base));
    }

    // ─── Lignes isolées : lieux d'après le titre ─────────────────────────

    /** @return array<string, array{0: string, 1: ?list<string>}> */
    public static function titleProvider(): array
    {
        return [
            'tramway' => ['Tramway d\'Annecy à Thônes', ['Annecy', 'Thônes']],
            'ligne' => ['Ligne de Saint-Brieuc à Moncontour', ['Saint-Brieuc', 'Moncontour']],
            'contraction du' => ['Chemin de fer du Blanc à Argent', ['Le Blanc', 'Argent']],
            'contraction au' => ['Ligne de Clermont au Mont-Dore', ['Clermont', 'Le Mont-Dore']],
            'qualificatif retiré' => ['Ligne de Bastia à Ajaccio (ligne)', ['Bastia', 'Ajaccio']],
            'extensions retirées' => ['Ligne de Nice à Digne et extensions', ['Nice', 'Digne']],
            'tiret' => ['Tramway Lyon – Neuville', ['Lyon', 'Neuville']],
            // Un nom qui COMMENCE par « La », « Le », « L » n'est pas un article.
            'Lalevade' => ['Ligne du Puy à Lalevade', ['Le Puy', 'Lalevade']],
            'Laval' => ['Tramway de Mayenne à Laval', ['Mayenne', 'Laval']],
            'Lens' => ['Chemin de fer de Béthune à Lens', ['Béthune', 'Lens']],
            'Lagny' => ['Tramway de Villeneuve-le-Comte à Lagny', ['Villeneuve-le-Comte', 'Lagny']],
            'réseau, pas une ligne' => ['Chemins de fer départementaux de la Somme', null],
            'un seul lieu' => ['Chemin de fer de la Mure', null],
        ];
    }

    #[DataProvider('titleProvider')]
    public function test_single_line_places_from_the_article_title(string $title, ?array $expected): void
    {
        $this->assertSame($expected, self::labels($this->p->singleLinePlaces($title)));
    }

    public function test_single_line_places_have_no_link_target(): void
    {
        foreach ($this->p->singleLinePlaces('Tramway d\'Annecy à Thônes') as $place) {
            $this->assertNull($place['target']);
        }
    }

    // ─── Itinéraires en tête de puce ou de cellule ───────────────────────

    public function test_route_head_with_length_and_dates_in_the_tail(): void
    {
        $head = $this->p->routeHead('Corbigny - Alligny, (62 km), ouverture en 1901, fermeture le 5 mars 1939');

        $this->assertSame(['Corbigny', 'Alligny'], self::labels($head['places']));
        $this->assertSame(', (62 km), ouverture en 1901, fermeture le 5 mars 1939', $head['tail']);
    }

    public function test_route_head_keeps_wiki_link_targets(): void
    {
        $head = $this->p->routeHead('[[Saint-Laurent-sur-Saône|Saint-Laurent]] - Saint-Trivier, 30 km, ouverte en 1897, fermée en 1936');

        $this->assertSame(['Saint-Laurent', 'Saint-Trivier'], self::labels($head['places']));
        $this->assertSame('Saint-Laurent-sur-Saône', $head['places'][0]['target']);
        $this->assertNull($head['places'][1]['target']);
    }

    /** @return array<string, array{0: string, 1: ?list<string>}> */
    public static function routeProvider(): array
    {
        return [
            'période entre parenthèses' => ['La Mi-Forêt - Sautoger (1905 - 1937)', ['La Mi-Forêt', 'Sautoger']],
            'numéro de ligne' => ['Ligne n° 3 : Abbeville - Dompierre', ['Abbeville', 'Dompierre']],
            'de … à … par …' => ['de Doullens à Beauval par Hem et Lucheux, ouverte le 15 novembre 1888', ['Doullens', 'Hem', 'Lucheux', 'Beauval']],
            'entre … et …' => ['entre Albert et Ham', ['Albert', 'Ham']],
            'préfixe de gare retiré' => ['Gare de Doullens - Beauval', ['Doullens', 'Beauval']],
            'pas d\'itinéraire' => ['ouverte en 1897', null],
            'longueur, pas un lieu' => ['Doullens - 12 km', null],
        ];
    }

    #[DataProvider('routeProvider')]
    public function test_route_head(string $raw, ?array $expected): void
    {
        $this->assertSame($expected, self::labels($this->p->routeHead($raw)['places'] ?? null));
    }

    public function test_strict_route_head_refuses_a_cell_with_a_verb(): void
    {
        $this->assertNotNull($this->p->routeHead('Saint-Laurent - Saint-Trivier', true));
        // Les dates qui suivent l'itinéraire vont dans la queue, même en mode strict.
        $this->assertSame('ouverte en 1897', $this->p->routeHead('Saint-Laurent - Saint-Trivier ouverte en 1897', true)['tail']);
        // Un verbe DANS l'itinéraire : cellule descriptive, pas un itinéraire.
        $this->assertNull($this->p->routeHead('Fermeture Saint-Laurent - Saint-Trivier', true));
    }

    // ─── Qualificatifs et types ──────────────────────────────────────────

    public function test_traffic_qualifiers_are_case_sensitive_for_freight(): void
    {
        $this->assertSame(['P', 'F'], $this->p->qualifiers('fermée aux voyageurs et aux marchandises'));
        $this->assertSame(['A'], $this->p->qualifiers('fermée à tout trafic'));
        $this->assertSame(['F'], $this->p->qualifiers('ouverte à la petite vitesse'));
        // « Le Fret » est un lieu (Finistère), pas le fret.
        $this->assertSame([], $this->p->qualifiers('Le Fret'));
    }

    /** @return array<string, array{0: string, 1: list<string>, 2: string}> */
    public static function typeProvider(): array
    {
        return [
            'fermeture voyageurs' => ['closure', ['P'], 'closure_passenger'],
            'fermeture fret' => ['closure', ['F'], 'closure_freight'],
            'fermeture des deux = totale' => ['closure', ['P', 'F'], 'closure'],
            'tout trafic' => ['closure', ['A'], 'closure'],
            'ouverture voyageurs' => ['opening', ['P'], 'opening_passenger'],
            'réouverture fret' => ['reopening', ['F'], 'reopening_freight'],
            'déclassement non qualifié' => ['declassification', ['P'], 'declassification'],
            'dépose non qualifiée' => ['dismantling', ['F'], 'dismantling'],
        ];
    }

    #[DataProvider('typeProvider')]
    public function test_type_for_base_and_qualifiers(string $base, array $quals, string $expected): void
    {
        $this->assertSame($expected, $this->p->typeFor($base, $quals));
        $this->assertArrayHasKey($expected, SecondaryNetworkParser::TYPE_LABELS);
    }

    // ─── Dates et événements d'un fragment ───────────────────────────────

    public function test_dates_ignore_decades_far_past_and_future(): void
    {
        $dates = $this->p->dates('au début de l\'année 1939, puis le 1er mai 1905, années 1930, 1820, 2099');

        $this->assertSame([['1939-01-01', 'year'], ['1905-05-01', 'day']], array_map(fn ($d) => [$d['date'], $d['precision']], $dates));
    }

    /** @return array<string, array{0: string, 1: list<array>}> */
    public static function eventsProvider(): array
    {
        return [
            'ouverture puis fermeture voyageurs' => ['ouverte le 1er mai 1905 et fermée aux voyageurs le 1er avril 1937',
                [['opening', '1905-05-01', 'day', 'high', false], ['closure_passenger', '1937-04-01', 'day', 'high', false]]],
            'années seules : confiance moyenne' => ['ouverte en 1897, fermée en 1936',
                [['opening', '1897-01-01', 'year', 'medium', false], ['closure', '1936-01-01', 'year', 'medium', false]]],
            'verbe hérité' => ['fermée aux voyageurs en 1939, aux marchandises en 1948',
                [['closure_passenger', '1939-01-01', 'year', 'medium', false], ['closure_freight', '1948-01-01', 'year', 'medium', false]]],
            'remplacée par des autocars = voyageurs' => ['remplacée par des autocars en 1934', [['closure_passenger', '1934-01-01', 'year', 'medium', false]]],
            'date après « jusqu\'à sa fermeture »' => ['jusqu\'à sa fermeture le 6 février 1989', [['closure', '1989-02-06', 'day', 'high', false]]],
            'déclassement' => ['fermée puis déclassée en 1954', [['declassification', '1954-01-01', 'year', 'medium', false]]],
            'projet : rien' => ['projet de fermeture en 1950', []],
            'concession : rien' => ['concédée en 1890', []],
            'gare : rien' => ['la gare est fermée en 1950', []],
            'futur : rien' => ['ouverte en 2099', []],
            'sans date : rien' => ['ouverte puis fermée', []],
            'vide' => ['', []],
        ];
    }

    #[DataProvider('eventsProvider')]
    public function test_extract_events_from_prose(string $text, array $expected): void
    {
        $this->assertSame($expected, $this->events($text));
    }

    public function test_period_in_brackets_is_a_low_confidence_lifespan_in_lists_only(): void
    {
        $lifespan = [['opening', '1905-01-01', 'year', 'low', true], ['closure', '1937-01-01', 'year', 'low', true]];

        $this->assertSame($lifespan, $this->events('Saint-Laurent - Saint-Trivier (1905 - 1937)', 'liste'));
        $this->assertSame($lifespan, $this->events('(1905 - 1937)', 'tete'));
        $this->assertSame([], $this->events('(1905 - 1937)', 'texte'));
        // Période d'un exploitant : pas la vie de la ligne.
        $this->assertSame([], $this->events('exploitée par la compagnie CFTA (1905 - 1937)', 'liste'));
    }

    public function test_lone_date_under_a_list_introduction_takes_its_verb(): void
    {
        $this->assertSame([['opening', '1901-03-10', 'day', 'medium', false]], $this->events('10 mars 1901', 'tete', 'opening'));
        $this->assertSame([], $this->events('10 mars 1901', 'tete'));
    }

    public function test_render_keeps_link_targets_but_not_date_links(): void
    {
        [$plain, $links] = $this->p->render('[[Gare de Doullens|Doullens]] {{date|15|11|1888}} [[1891 dans les chemins de fer|1891]] {{tri|0012|12 km}} [[Catégorie:X]] \'\'gras\'\'&nbsp;fin');

        $this->assertSame('Doullens 15 novembre 1888 1891 12 km gras fin', $plain);
        $this->assertSame(['doullens' => 'Gare de Doullens'], $links);
    }

    // ─── Article complet ─────────────────────────────────────────────────

    private const NETWORK = <<<'WT'
        {{Infobox Réseau
        | nom = Chemins de fer départementaux de la Somme
        | mise en service = 1888
        | fermeture = 1959
        }}
        Les '''chemins de fer départementaux de la Somme''' formaient un réseau.

        == Les lignes ==
        Les lignes sont ouvertes aux dates suivantes :
        * [[Doullens]] - Beauval, 12 km : 10 mars 1889
        * Albert - Ham (1889 - 1949)

        === Ligne Abbeville - Dompierre ===
        La ligne est ouverte le 15 novembre 1892 et fermée aux voyageurs le 1er avril 1937.

        == Prose ==
        La ligne de Saint-Brieuc à Moncontour a été ouverte le 1er mai 1905 et fermée aux voyageurs le 1er avril 1937. Le projet de fermeture de la ligne de Amiens à Doullens en 1950 est abandonné.

        == Matériel roulant ==
        * Locomotive 030T livrée en 1890 et fermée en 1950.

        == Fermeture ==
        Toutes les lignes du réseau sont fermées le 1er mars 1959.
        WT;

    public function test_network_article_gives_one_record_per_route(): void
    {
        $r = $this->p->parse(self::NETWORK, 'Chemins de fer départementaux de la Somme');

        $this->assertNull($r['single']);
        $this->assertSame([
            [['Doullens', 'Beauval'], 'liste', 12.0, [['opening', '1889-03-10']]],
            [['Albert', 'Ham'], 'liste', null, [['opening', '1889-01-01'], ['closure', '1949-01-01']]],
            [['Abbeville', 'Dompierre'], 'titre', null, [['opening', '1892-11-15'], ['closure_passenger', '1937-04-01']]],
            [['Saint-Brieuc', 'Moncontour'], 'texte', null, [['opening', '1905-05-01'], ['closure_passenger', '1937-04-01']]],
        ], array_map(fn ($rec) => [self::labels($rec['places']), $rec['origin'], $rec['km'], array_map(fn ($e) => [$e['type'], $e['date']], $rec['events'])], $r['records']));
        $this->assertSame('Doullens', $r['records'][0]['places'][0]['target']);
    }

    public function test_network_dates_and_overall_statements(): void
    {
        $net = $this->p->parse(self::NETWORK, 'Chemins de fer départementaux de la Somme')['network'];

        $this->assertSame('1888-01-01', $net['start']['date']);
        $this->assertSame('1959-01-01', $net['end']['date']);
        $this->assertSame('1949-01-01', $net['last_closure']['date']);
        // « Toutes les lignes du réseau sont fermées le 1er mars 1959 » : fin d'ensemble.
        $this->assertSame(['closure', '1959-03-01'], [$net['statement']['type'], $net['statement']['date']]);
        // Réseau : l'infobox ne date aucune fiche.
        $this->assertSame([], $net['events']);
    }

    public function test_isolated_line_article_uses_its_infobox_as_a_weak_fallback(): void
    {
        $r = $this->p->parse("{{Infobox Ligne ferroviaire\n| mise en service = 1 juillet 1898\n| fermeture = 1934\n}}\nLe tramway est ouvert en 1898.", 'Tramway d\'Annecy à Thônes');

        $this->assertCount(1, $r['records']);
        $this->assertSame(['Annecy', 'Thônes'], self::labels($r['records'][0]['places']));
        $this->assertSame([['opening', '1898-01-01', 'article'], ['opening', '1898-07-01', 'infobox'], ['closure', '1934-01-01', 'infobox']],
            array_map(fn ($e) => [$e['type'], $e['date'], $e['origin']], $r['records'][0]['events']));
        foreach ($r['records'][0]['events'] as $e) {
            if ($e['origin'] === 'infobox') {
                $this->assertSame('low', $e['confidence']);
            }
        }
    }

    public function test_quotes_are_bounded_and_never_empty_for_dated_events(): void
    {
        $long = 'La ligne de Saint-Brieuc à Moncontour a été ouverte le 1er mai 1905. '.str_repeat('Texte de remplissage sans date. ', 20);
        $r = $this->p->parse("== Histoire ==\n{$long}", 'Chemins de fer des Côtes-du-Nord');

        foreach ($r['records'] as $rec) {
            $this->assertLessThanOrEqual(300, mb_strlen($rec['quote']));
            foreach ($rec['events'] as $e) {
                $this->assertNotSame('', $e['quote']);
                $this->assertLessThanOrEqual(300, mb_strlen($e['quote']));
            }
        }
    }

    /** Fiches d'un article : [lieux, [[type, date], …]] (fiches sans événement comprises). */
    private function records(string $wikitext, string $title): array
    {
        return array_map(fn ($rec) => [self::labels($rec['places']), array_map(fn ($e) => [$e['type'], $e['date']], $rec['events'])],
            $this->p->parse($wikitext, $title)['records']);
    }

    // ─── Règles ajoutées le 2026-10-19 (lignes secondaires restées sans date) ─

    public function test_list_introduced_by_suppressed_lines_takes_the_closure_and_its_date(): void
    {
        // « CFD Réseau de la Dordogne » (frwiki) : verbe « supprimées » / « cesse » de l'introduction.
        $w = <<<'WT'
            == Exploitation par la compagnie CFD ==
            Le {{date-|1 décembre 1934}}, sont supprimées les lignes suivantes :
            * [[Ligne de Thiviers à Saint-Yrieix|Saint-Yrieix-la-Perche - Thiviers]], (33&nbsp;km),
            * Villefranche-du-Périgord - Sarlat, (53,6 km)
            L'exploitation ferroviaire cesse sur les lignes restantes aux dates suivantes :
            * Périgueux - Saint-Pardoux-la-Rivière, le {{date-|1 janvier 1949}} ,(53 km),
            * Périgueux - Bergerac, le {{date-|1 avril 1949}}, (56 km),
            WT;

        $this->assertSame([
            [['Saint-Yrieix-la-Perche', 'Thiviers'], [['closure', '1934-12-01']]],
            [['Villefranche-du-Périgord', 'Sarlat'], [['closure', '1934-12-01']]],
            [['Périgueux', 'Saint-Pardoux-la-Rivière'], [['closure', '1949-01-01']]],
            [['Périgueux', 'Bergerac'], [['closure', '1949-04-01']]],
        ], $this->records($w, 'CFD Réseau de la Dordogne'));
    }

    public function test_list_of_the_network_lines_gives_low_confidence_openings(): void
    {
        // « Chemins de fer départementaux des Ardennes » (frwiki) : « Il comprend les lignes suivantes : »
        // sans verbe, chaque puce porte sa date de mise en service.
        $w = <<<'WT'
            == Lignes ==
            === Le premier réseau ===
            Le premier réseau est construit à l'écartement de 800 mm pour des raisons stratégiques. Il comprend les lignes suivantes :
            *Tremblois-lès-Rocroi - Rocroi : novembre 1895 (12km) ;
            *Wasigny - Signy-l'Abbaye (11,5km) :
            :Wasigny - Signy-Gare : novembre 1897,
            :Signy-Gare - Signy-Place : août 1898 ;
            WT;
        $r = $this->p->parse($w, 'Chemins de fer départementaux des Ardennes')['records'];

        $this->assertSame([
            [['Tremblois-lès-Rocroi', 'Rocroi'], [['opening', '1895-11-01', 'month', 'low']]],
            [['Wasigny', 'Signy-l\'Abbaye'], []],
            [['Wasigny', 'Signy-Gare'], [['opening', '1897-11-01', 'month', 'low']]],
            [['Signy-Gare', 'Signy-Place'], [['opening', '1898-08-01', 'month', 'low']]],
        ], array_map(fn ($rec) => [self::labels($rec['places']), array_map(fn ($e) => [$e['type'], $e['date'], $e['precision'], $e['confidence']], $rec['events'])], $r));
        // Liste de concessions : pas une liste d'ouvertures.
        $this->assertSame([[['Rocroi', 'Hiraumont'], []]],
            $this->records("== Lignes ==\nLe département concède les lignes suivantes :\n* Rocroi - Hiraumont : décembre 1905\n", 'Chemins de fer départementaux des Ardennes'));
    }

    public function test_extensions_and_added_sections_are_implicit_openings(): void
    {
        // « Chemins de fer départementaux de la Loire » et « Tramways de la Sarthe » (frwiki).
        $loire = "== Prolongements ==\nLa [[Société des Chemins de fer du Centre]] (CFC) , exploitante de 1911 à 1928, réalisa les prolongements suivants:\n* [[Renaison]] - [[Ambierle]], (8 km), en 1914 (réseau nord)\n";
        $sarthe = "== Histoire ==\nPar la suite, les sections suivantes ont été ajoutées :\n* Le Grand-Lucé - [[La Chartre-sur-le-Loir]], en 1884 ;\n* Ballon - Montbizot, en 1886 ;\n";

        $this->assertSame([[['Renaison', 'Ambierle'], [['opening', '1914-01-01']]]], $this->records($loire, 'Chemins de fer départementaux de la Loire'));
        $this->assertSame([
            [['Le Grand-Lucé', 'La Chartre-sur-le-Loir'], [['opening', '1884-01-01']]],
            [['Ballon', 'Montbizot'], [['opening', '1886-01-01']]],
        ], $this->records($sarthe, 'Tramways de la Sarthe'));
        // « Les lignes suivantes sont remplacées par des autocars : » : ni ouverture, ni (faute de verbe connu) fermeture.
        $this->assertSame([[['Ballon', 'Montbizot'], []]],
            $this->records("== Histoire ==\nLes lignes suivantes sont remplacées par des autocars :\n* Ballon - Montbizot, en 1934 ;\n", 'Tramways de la Sarthe'));
    }

    public function test_verb_of_a_parent_bullet_applies_to_its_sub_bullets(): void
    {
        // « Réseau de Seine-et-Marne (SE) » (frwiki).
        $w = <<<'WT'
            == Lignes ==
            L'exploitation était assurée sur les lignes de tramway de :
            * [[Tramway de Sablonnières à Bray-sur-Seine|Sablonnières à Bray-sur-Seine]] (88 km), ouverte :
            ** de Sablonnières à Saint-Siméon le {{date|23|11|1903}}
            ** de Saint-Siméon à Jouy-le-Châtel le {{date|23|3|1903}}
            ** de Jouy-le-Châtel à Nangis-Gare le …
            WT;

        // (« Sablonnières à Bray-sur-Seine », sans « de », et « … le … » sans date ne font pas de fiche.)
        $this->assertSame([
            [['Sablonnières', 'Saint-Siméon'], [['opening', '1903-11-23']]],
            [['Saint-Siméon', 'Jouy-le-Châtel'], [['opening', '1903-03-23']]],
        ], $this->records($w, 'Réseau de Seine-et-Marne (SE)'));
        // Sans la puce parente « ouverte : », les mêmes sous-puces ne sont pas datées.
        $this->assertSame([[['Sablonnières', 'Saint-Siméon'], []]],
            $this->records("== Lignes ==\n* Sablonnières à Bray-sur-Seine (88 km) :\n** de Sablonnières à Saint-Siméon le {{date|23|11|1903}}\n", 'Réseau de Seine-et-Marne (SE)'));
    }

    public function test_lifespan_in_a_bullet_ignores_the_operator_of_another_clause(): void
    {
        // « Chemins de fer départementaux de l'Oise » (frwiki) : la société citée ensuite n'est pas la période.
        $this->assertSame([[['Bussy', 'Ercheu'], [['opening', '1897-01-01'], ['closure', '1953-01-01']]]], $this->records(
            "==Lignes==\n* [[Chemin de fer de Bussy à Ercheu|Bussy - Ercheu]], {{unité|13|km}} (1897 - 1953), exploitée à l'origine par la Société du Chemin de fer d'intérêt local de Bussy à Ercheu.\n",
            'Chemins de fer départementaux de l\'Oise'));
        // « Tramways de l'Ouest du Dauphiné » : « (8 avril 1900 - fin 1937) ».
        $this->assertSame([['opening', '1900-04-08', 'day', 'low', true], ['closure', '1937-01-01', 'year', 'low', true]],
            $this->events('({{date|8 avril 1900}} - fin 1937)', 'tete'));
    }

    public function test_line_named_by_its_terminus_goes_to_the_record_ending_there(): void
    {
        // « Compagnie des Chemins de fer d'intérêt local du Territoire de Belfort » (frwiki).
        $w = <<<'WT'
            == Historique ==
            Le service sera cependant de courte durée puisque les lignes disparaissent progressivement dès 1932 (fermeture de la ligne de Réchésy). La ligne du nord (Étueffont, Rougemont, Lachapelle-sous-Rougemont) est fermée en 1936, sauf l'embranchement d'Étueffont-Haut. En 1938, la ligne de Sochaux disparaît à son tour. Seule subsiste alors la ligne Belfort - Étueffont-Haut, jusqu'au {{date|4|juillet|1948}}.

            == Infrastructure ==
            === Lignes ===
            * Belfort - [[Sochaux]], ({{unité|16|km}}), ouverture le {{date|6 août 1913}} ;
            * Belfort - [[Rechesy]], ({{unité|27|km}}), ouverture le {{date|1 octobre 1913}} ;
            * Belfort - [[Menoncourt|Les Errues]] - [[Etueffont-Haut]], ({{unité|16|km}}), ouverture le {{date|15 mai 1913}} ;
            WT;

        $this->assertSame([
            [['Belfort', 'Étueffont-Haut'], [['closure', '1948-07-04']]],
            [['Belfort', 'Sochaux'], [['opening', '1913-08-06'], ['closure', '1938-01-01']]],
            [['Belfort', 'Rechesy'], [['opening', '1913-10-01'], ['closure', '1932-01-01']]],
            [['Belfort', 'Les Errues', 'Etueffont-Haut'], [['opening', '1913-05-15']]],
        ], $this->records($w, 'Compagnie des Chemins de fer d\'intérêt local du Territoire de Belfort'));
    }

    /** @return array<string, array{0: string, 1: ?string}> */
    public static function terminusProvider(): array
    {
        return [
            'terminus seul' => ['En 1938, la ligne de Sochaux disparaît à son tour.', 'Sochaux'],
            'entre parenthèses' => ['dès 1932 (fermeture de la ligne de Réchésy)', 'Réchésy'],
            'contraction du' => ['la ligne du Blanc ferme en 1953', 'Le Blanc'],
            'itinéraire complet : traité ailleurs' => ['la ligne de Belfort à Delle ferme', null],
            'tiret : traité ailleurs' => ['la ligne de Belfort - Delle ferme', null],
            'exception' => ['toutes fermées sauf la ligne de Vergt', null],
            'deux termini : ambigu' => ['la ligne de Sochaux et la ligne de Réchésy ferment', null],
            'aucune' => ['le réseau ferme en 1950', null],
        ];
    }

    #[DataProvider('terminusProvider')]
    public function test_terminus_mention(string $clause, ?string $expected): void
    {
        $this->assertSame($expected, $this->p->terminusMention($clause));
    }

    public function test_pronoun_clause_repeats_the_previous_event(): void
    {
        // « Tramways des Deux-Sèvres » (frwiki).
        $w = "== Histoire ==\nEn 1901, est ouverte la ligne de Parthenay à Saint-Laurs via l'Absie et celle de Bressuire à Montreuil-Bellay.\n";

        $this->assertSame([
            [['Parthenay', 'Saint-Laurs'], [['opening', '1901-01-01']]],
            [['Bressuire', 'Montreuil-Bellay'], [['opening', '1901-01-01']]],
        ], $this->records($w, 'Tramways des Deux-Sèvres'));
    }

    public function test_network_period_from_the_introduction(): void
    {
        // « Tramways de Tarn-et-Garonne » (frwiki) : ni infobox ni fermeture datée, la période est dans l'introduction.
        $w = <<<'WT'
            Les '''Tramways de Tarn-et-Garonne''', sont un ancien réseau de [[Voie ferrée d'intérêt local|chemins de fer départementaux]] qui ont fonctionné dans le département de [[Tarn-et-Garonne]] entre 1913 et 1933<ref>FACS</ref>. Le réseau formait un ensemble de {{unité|182|km}} de lignes à [[voie métrique]] exploitées en traction à vapeur.

            == Les lignes ==
            * ligne 1: [[Montauban]] - [[Molières (Tarn-et-Garonne)| Molières]], ({{unité|35|km}}), ouverte en 1913
            WT;
        $net = $this->p->parse($w, 'Tramways de Tarn-et-Garonne')['network'];

        $this->assertSame(['1913-01-01', 'introduction'], [$net['start']['date'], $net['start']['field']]);
        $this->assertSame(['1933-01-01', 'introduction'], [$net['end']['date'], $net['end']['field']]);
        $this->assertStringStartsWith('Introduction de l\'article : Les Tramways de Tarn-et-Garonne', SecondaryNetworkParser::fieldQuote($net['end']));
    }

    public function test_end_of_traffic_is_not_the_end_of_a_company_taken_over(): void
    {
        // « Chemins de fer départementaux de la Côte-d'Or » : « fin » = fin du réseau.
        $cote = $this->p->parse("{{Infobox Réseau de transport en commun\n| nom = Chemins de fer départementaux de la Côte-d'Or\n| fin                  = {{Date|1948}}\n}}\nTexte.", 'Chemins de fer départementaux de la Côte-d\'Or')['network'];
        $this->assertSame('1948-01-01', $cote['closed']['date']);
        // « Société des chemins de fer des Bouches-du-Rhône » : disparue en 1881 au profit d'un successeur, les lignes continuent.
        $bdr = $this->p->parse("{{Infobox Société\n| date de création = [[1870 dans les chemins de fer|1870]]\n| date de disparition = [[1881 dans les chemins de fer|1881]]\n| successeur = [[Société nouvelle des chemins de fer des Bouches-du-Rhône]]\n}}\nTexte.", 'Société des chemins de fer des Bouches-du-Rhône')['network'];
        $this->assertNull($bdr['closed']);
        $this->assertSame('1881-01-01', $bdr['end']['date']);
    }

    /** @return array<string, array{0: string, 1: ?list<string>}> */
    public static function leadPeriodProvider(): array
    {
        return [
            'réseau qui a fonctionné' => ['Le réseau des tramways de la Sarthe a fonctionné de 1882 à 1947.', ['1882-01-01', '1947-01-01']],
            'tramway exploité' => ['Le tramway de Pithiviers à Toury est une ligne exploitée de 1892 à 1964.', ['1892-01-01', '1964-01-01']],
            // Période d'un exploitant ou d'une concession : pas celle du réseau.
            'exploitant' => ['Le réseau est exploité par la compagnie CFD de 1926 à 1949.', null],
            'concession' => ['La ligne, en service, fut concédée de 1880 à 1910.', null],
            'sans réseau ni ligne' => ['La compagnie a fonctionné de 1900 à 1930.', null],
        ];
    }

    #[DataProvider('leadPeriodProvider')]
    public function test_lead_period(string $lead, ?array $expected): void
    {
        $period = $this->p->leadPeriod($lead);

        $this->assertSame($expected, $period ? [$period['start']['date'], $period['end']['date']] : null);
    }

    public function test_line_article_without_places_in_its_title_takes_the_route_of_its_introduction(): void
    {
        // « Ligne de tramway 2 (ELRT) » (frwiki) : le titre ne nomme pas les terminus, l'introduction si.
        $w = <<<'WT'
            {{Infobox Ligne de transport en commun
             | nom                     = Ligne 2
             | date d'ouverture        = {{date|02|05|1908}}
             | date de fermeture       = {{date|15|07|1956}}
             | exploitant              = [[Électrique Lille Roubaix Tourcoing|ELRT]]
            }}

            La '''ligne 2''' est une ancienne ligne de [[tramway]] de l'[[Électrique Lille Roubaix Tourcoing]]. Exploitée entre le {{Date|2|05|1908}} et le {{Date|15|07|1956}}, la ligne reliait [[Lille]] à [[Leers]], elle faisait partie au même titre que les [[Tramway du Grand Boulevard|lignes du Grand Boulevard]] des [[Lignes ELRT|lignes départementales de l'ELRT]].
            WT;
        $r = $this->p->parse($w, 'Ligne de tramway 2 (ELRT)');

        $this->assertSame(['Lille', 'Leers'], self::labels($r['single']));
        $this->assertSame('Leers', $r['single'][1]['target']);
        $infobox = array_values(array_filter($r['records'][0]['events'], fn ($e) => $e['origin'] === 'infobox'));
        // {{date|02|05|1908}} : jour lu malgré le zéro initial.
        $this->assertSame([['opening', '1908-05-02', 'day'], ['closure', '1956-07-15', 'day']],
            array_map(fn ($e) => [$e['type'], $e['date'], $e['precision']], $infobox));
        // Article de réseau (pas d'infobox de ligne) : « reliait » ne fait pas une ligne isolée.
        $this->assertNull($this->p->parse("Le réseau reliait [[Lille]] à [[Leers]].\n", 'Électrique Lille Roubaix Tourcoing')['single']);
    }

    public function test_survival_until_a_date_is_a_closure(): void
    {
        $this->assertSame([['closure', '1948-07-04', 'day', 'high', false]],
            $this->events('Seule subsiste alors la ligne Belfort - Étueffont-Haut, jusqu\'au 4 juillet 1948.'));
        // Sans verbe de survie, « jusqu'au » seul n'est pas une fermeture.
        $this->assertSame([], $this->events('La ligne Belfort - Étueffont-Haut, jusqu\'au 4 juillet 1948.'));
    }

    public function test_empty_article(): void
    {
        $r = $this->p->parse('', 'Chemins de fer départementaux de la Somme');

        $this->assertSame([], $r['records']);
        $this->assertSame([], $r['infobox']);
        $this->assertNull($r['network']['start']);
    }
}
