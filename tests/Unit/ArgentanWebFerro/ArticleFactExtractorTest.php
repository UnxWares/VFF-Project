<?php

namespace Tests\Unit\ArgentanWebFerro;

use App\Enums\ArgentanFactKind;
use App\Enums\FactDatePrecision;
use App\Enums\LineEventType;
use App\Enums\StationType;
use App\Services\ArgentanWebFerro\ArticleFactExtractor;
use App\Services\ArgentanWebFerro\ExtractedFact;
use PHPUnit\Framework\TestCase;

/**
 * Faits d'un article d'Argentan Web Ferroviaire : petites pages HTML écrites comme celles du
 * site (paragraphes WordPress, tirets typographiques, apostrophes courbes, légendes de photos).
 */
class ArticleFactExtractorTest extends TestCase
{
    /** Tiret demi-cadratin des pages du site, écrit « ~ » ici (règle éditoriale : aucun tiret long dans le code). */
    private static function dashes(string $html): string
    {
        return str_replace(' ~ ', " \u{2013} ", $html);
    }

    /** @return list<ExtractedFact> */
    private function extract(string $html, ?\Closure $isPlace = null): array
    {
        return (new ArticleFactExtractor($isPlace))->extract(self::dashes($html));
    }

    /** @return list<ExtractedFact> */
    private function events(string $html): array
    {
        return array_values(array_filter($this->extract($html), fn (ExtractedFact $f) => $f->kind === ArgentanFactKind::Event));
    }

    public function test_year_prefix_paragraph_gives_one_event_per_trigger_with_its_section(): void
    {
        $events = $this->events('<p>1918: Fermeture du service marchandises, réquisition et dépose de la voie par l’armée entre Tinchebray et Les Maures</p>');

        $this->assertSame([LineEventType::ClosureFreight, LineEventType::Dismantling], array_map(fn ($e) => $e->type, $events));
        foreach ($events as $e) {
            $this->assertSame(1918, $e->year);
            $this->assertSame(FactDatePrecision::Year, $e->precision);
            $this->assertSame('1918-01-01', $e->date());
            $this->assertSame(['Tinchebray', 'Les Maures'], [$e->from, $e->to]);
        }
    }

    public function test_day_dates_and_chained_extensions(): void
    {
        $events = $this->events('<p>La ligne est ouverte de Ste Gauburge à Gacé le 22/11/1880. Elle est prolongée jusqu’à Ticheville le 10/09/1881 et jusqu’au Mesnil-Mauger le 30/12/1881.</p>');

        $this->assertCount(3, $events);
        $this->assertSame(['1880-11-22', '1881-09-10', '1881-12-30'], array_map(fn ($e) => $e->date(), $events));
        $this->assertSame([['Ste Gauburge', 'Gacé'], ['Gacé', 'Ticheville'], ['Ticheville', 'Mesnil-Mauger']], array_map(fn ($e) => [$e->from, $e->to], $events));
        $this->assertSame([LineEventType::Opening], array_values(array_unique(array_map(fn ($e) => $e->type, $events), SORT_REGULAR)));
        $this->assertSame(FactDatePrecision::Day, $events[0]->precision);
    }

    public function test_each_trigger_goes_to_the_nearest_date(): void
    {
        $events = $this->events('<p>1938 marque la fermeture du service marchandises de Couterne à Bagnoles de l’Orne avant une suspension du service voyageurs en 1941.</p>');

        $this->assertSame([[LineEventType::ClosureFreight, 1938], [LineEventType::ClosurePassenger, 1941]], array_map(fn ($e) => [$e->type, $e->year], $events));
        $this->assertSame("Bagnoles de l'Orne", $events[1]->to, 'apostrophe courbe ramenée à l\'apostrophe droite');
    }

    public function test_a_date_without_its_own_trigger_is_not_given_the_next_one(): void
    {
        $events = $this->events('<p>Le service se limite à la Trinité de Réville en 1969 avant un arrêt définitif en 1987</p>');

        $this->assertSame([[LineEventType::Closure, 1987]], array_map(fn ($e) => [$e->type, $e->year], $events));
    }

    public function test_enumerated_dates_inherit_the_previous_type(): void
    {
        $events = $this->events('<p>Ouverte en 1856 entre Le Mans et Alençon, elle est prolongée jusqu’à Argentan en 1858 et Mézidon en 1859.</p>');

        $this->assertSame([1856, 1858, 1859], array_map(fn ($e) => $e->year, $events));
        $this->assertSame([['Le Mans', 'Alençon'], ['Alençon', 'Argentan'], ['Argentan', 'Mézidon']], array_map(fn ($e) => [$e->from, $e->to], $events));
    }

    public function test_list_items_inherit_the_opening_lead(): void
    {
        $events = $this->events('<p>La section de ligne Le Mans-Mézidon est ouverte en 3 étapes:</p><ul><li>le 15/03/1856 pour Le Mans-Alençon</li><li>le 01/02/1858 pour Alençon-Argentan</li></ul><p>Le ligne fait 140 km.</p><ul><li>Paris-Dreux en 1864</li></ul>');

        $this->assertCount(2, $events, 'la liste après une phrase sans déclencheur n\'hérite de rien');
        $this->assertSame([['1856-03-15', 'Le Mans', 'Alençon'], ['1858-02-01', 'Alençon', 'Argentan']], array_map(fn ($e) => [$e->date(), $e->from, $e->to], $events));
    }

    public function test_period_and_slash_sections(): void
    {
        [$event] = $this->events('<p>Entre 1966 et 1971, la section Mortagne au Perche/Condé sur Huisne ferme.</p>');

        $this->assertSame(FactDatePrecision::Range, $event->precision);
        $this->assertSame([1966, 1971], [$event->year, $event->yearTo]);
        $this->assertSame(['Mortagne au Perche', 'Condé sur Huisne'], [$event->from, $event->to]);
    }

    public function test_month_precision(): void
    {
        [$event] = $this->events('<p>La section Surdon-Argentan était en service depuis février 1858, au titre de la liaison Le Mans-Mézidon.</p>');

        $this->assertSame([LineEventType::Opening, '1858-02-01', FactDatePrecision::Month], [$event->type, $event->date(), $event->precision]);
        $this->assertSame(['Surdon', 'Argentan'], [$event->from, $event->to]);
    }

    public function test_compound_section_is_cut_at_a_known_station(): void
    {
        $known = fn (string $name): bool => in_array($name, ['Ste Gauburge', 'Montreuil-Cernières'], true);
        $html = '<p>En 1954, la section Ste Gauburge-Montreuil-Cernières ferme.</p>';

        [$withIndex] = array_values(array_filter($this->extract($html, $known), fn ($f) => $f->kind === ArgentanFactKind::Event));
        $this->assertSame(['Ste Gauburge', 'Montreuil-Cernières'], [$withIndex->from, $withIndex->to]);
        $this->assertNull((new ArticleFactExtractor)->split('Condé/Huisne'), '« / » pour « sur » : pas une section');
    }

    public function test_undated_facts_are_kept_without_date_only_for_strong_types(): void
    {
        $events = $this->events('<p>La voie est déposée.</p><p>La ligne ouvre à la circulation.</p>');

        $this->assertCount(1, $events);
        $this->assertSame(LineEventType::Dismantling, $events[0]->type);
        $this->assertNull($events[0]->year);
        $this->assertNull($events[0]->date());
        $this->assertNull($events[0]->precision);
    }

    public function test_mines_depots_and_bankruptcy_are_not_line_events(): void
    {
        $events = $this->events('<p>La mine ferme ses portes le 14 décembre 1978.</p><p>En 1947, le dépôt de Ste Gauburge ferme.</p><p>1888: La Compagnie dépose le bilan.</p>');

        $this->assertSame([], $events);
    }

    public function test_captions_tables_and_shortcodes_are_ignored(): void
    {
        $html = '<figure><img src="x.jpg"/><figcaption>Fermeture de la gare ~ 1970</figcaption></figure>'
            .'<table><tr><td>Ouverture 1901</td></tr></table>'
            .'<p>A noter un [su_tooltip title="" content="Marchandises-Voyageurs"]MV[/su_tooltip] en 1931.</p>';

        $this->assertSame([], $this->extract($html));
        $this->assertSame(['A noter un MV en 1931.'], ArticleFactExtractor::blocks(self::dashes($html)));
    }

    public function test_stops_with_pk_nature_and_bifurcation(): void
    {
        $html = '<p>La ligne dessert les gares et haltes suivantes:</p>'
            .'<p>Alençon ~ PK 55.9</p><p>Messei (Halte) ~ PK 363.6 (3)</p><p>Echauffour ~ PK 0.0 (Bifurcation)</p>'
            .'<p>Briouze (pk 0) sur la ligne Paris-Granville</p><p>St Pierre d’Entremont (arrêt)</p><p>Villepreux-Les Clayes ~ PK</p>';
        $stops = array_values(array_filter($this->extract($html), fn ($f) => $f->kind === ArgentanFactKind::Stop));

        $this->assertSame([
            ['Alençon', null, 55900], ['Messei', StationType::Halte, 363600], ['Echauffour', StationType::Embranchement, 0],
            ['Briouze', null, 0], ["St Pierre d'Entremont", StationType::PointArret, null], ['Villepreux-Les Clayes', null, null],
        ], array_map(fn ($s) => [$s->stopName, $s->stopNature, $s->pkM], $stops));
    }

    public function test_bare_stop_list_after_a_serving_lead(): void
    {
        $html = '<p>La ligne est longue de 44.329 km et desert les gares ou arrêts suivents:</p>'
            .'<p>Trun</p><figure><figcaption>Trun ~ VFEO</figcaption></figure><p>Villedieu les Bailleul</p><p>Gare de Boucé</p>'
            .'<p>Le matériel roulant se compose de locomotives à vapeur.</p><p>Fleuré</p>';
        $stops = array_map(fn ($s) => $s->stopName, array_values(array_filter($this->extract($html), fn ($f) => $f->kind === ArgentanFactKind::Stop)));

        $this->assertSame(['Trun', 'Villedieu les Bailleul'], $stops, 'légende, « Gare de … » et texte après la liste exclus');
    }

    public function test_duplicate_stop_keeps_its_pk(): void
    {
        $stops = array_values(array_filter($this->extract('<p>Flers de l’Orne ~ PK 45.7</p><p>Flers de l’Orne (halte)</p>'), fn ($f) => $f->kind === ArgentanFactKind::Stop));

        $this->assertCount(1, $stops);
        $this->assertSame([45700, StationType::Halte], [$stops[0]->pkM, $stops[0]->stopNature]);
    }

    public function test_operators_from_periods_and_takeovers(): void
    {
        $facts = array_values(array_filter($this->extract('<p>1888->1891: Exploitation par la CGFCS (Compagnie Générale)</p><p>En 1886, l’exploitation est reprise par la Compagnie de l’Ouest</p>'),
            fn ($f) => $f->kind === ArgentanFactKind::Operator));

        $this->assertSame([[1888, 1891, 'CGFCS (Compagnie Générale)'], [1886, null, "Compagnie de l'Ouest"]], array_map(fn ($f) => [$f->year, $f->yearTo, $f->operator], $facts));
    }

    public function test_quote_is_one_sentence_of_at_most_240_characters_without_long_dash(): void
    {
        $long = 'En 1938, la ligne ferme au trafic voyageurs ~ '.str_repeat('et le reste du texte de l’article continue longuement ', 8).'jusqu’au bout.';
        [$event] = $this->events('<p>'.$long.' Une autre phrase en 1950.</p>');
        $this->assertStringContainsString("\u{2013}", self::dashes($long), 'le texte source porte bien un tiret typographique');

        $this->assertLessThanOrEqual(240, mb_strlen($event->quote));
        $this->assertStringNotContainsString('Une autre phrase', $event->quote);
        $this->assertDoesNotMatchRegularExpression('/[\x{2013}\x{2014}]/u', $event->quote);
        $this->assertStringEndsWith('…', $event->quote);
    }

    public function test_dates_ignore_series_numbers_and_measures(): void
    {
        $this->assertSame([], ArticleFactExtractor::dates('Série 1904/2244 Ouest, train 1590, 1200 m de quai'));
        $this->assertSame([[1887, 1888, FactDatePrecision::Range]], array_map(fn ($d) => [$d['year'], $d['year_to'], $d['precision']], ArticleFactExtractor::dates('en 1887/1888')));
        $this->assertSame([[1991, 5, 26]], array_map(fn ($d) => [$d['year'], $d['month'], $d['day']], ArticleFactExtractor::dates('le 26 mai 1991')));
    }

    public function test_fingerprint_is_stable_and_specific_to_the_article(): void
    {
        [$a] = $this->events('<p>En 1964, fermeture de la ligne.</p>');
        [$b] = $this->events('<p>En 1964, fermeture de la ligne.</p>');

        $this->assertSame($a->fingerprint('page', 151), $b->fingerprint('page', 151));
        $this->assertNotSame($a->fingerprint('page', 151), $a->fingerprint('post', 151));
        $this->assertSame(40, strlen($a->fingerprint('page', 151)));
    }
}
