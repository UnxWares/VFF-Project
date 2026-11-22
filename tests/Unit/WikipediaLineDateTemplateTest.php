<?php

namespace Tests\Unit;

use App\Services\WikipediaLineParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Gabarits Wikipédia de date des articles de lignes : {{Date-|20/7/1862||}} (ligne 838000, Troyes - Gray, n'avait
 * aucun événement d'ouverture), {{date||04|2019}}, {{Date|3=1978}}, {{1er|mai 1863}}...
 * Les exemples sont tirés d'articles réels du cache.
 */
class WikipediaLineDateTemplateTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function templates(): array
    {
        return [
            'jour/mois/année sans zéro (Troyes - Gray)' => ['{{Date-|20/7/1862||}}', '20 juillet 1862'],
            'jour/mois/année avec arguments vides' => ['{{date-|9/12/1882||}}', '9 décembre 1882'],
            'jour/mois/année seul' => ['{{Date-|30/5/1975}}', '30 mai 1975'],
            'barres obliques et suffixe (Joigny - Toucy)' => ['{{Date|19/06/1869|dans les chemins de fer}}', '19 juin 1869'],
            'ISO' => ['{{date-|1934-09-21}}', '21 septembre 1934'],
            'jour mois année séparés par des espaces' => ['{{Date|1909 07 24}}', '24 juillet 1909'],
            'jour, mois numérique, année' => ['{{Date|6|3|1972}}', '6 mars 1972'],
            'jour, mois, année et suffixe' => ['{{date|1er|juin|1980|dans les chemins de fer}}', '1er juin 1980'],
            'jour vide, mois numérique, année (Clisson - Cholet)' => ['{{date||04|2019}}', 'avril 2019'],
            'jour vide, mois, année' => ['{{Date-||12|1925}}', 'décembre 1925'],
            'mois et année avec espace (Tramway de Saint-Amand)' => ['{{Date|04 1935}}', 'avril 1935'],
            'année seule en paramètre 3 (Givors - Grezan)' => ['{{Date|3=1978}}', '1978'],
            'mois et année nommés' => ['{{Date|2=février|3=1991}}', 'février 1991'],
            'jour en deux arguments, année séparée' => ['{{Date-|1 juillet|1881}}', '1 juillet 1881'],
            'suffixe collé à l\'année' => ['{{date|25 septembre|1864 dans les chemins de fer}}', '25 septembre 1864'],
            'tiret final' => ['{{date|14 décembre 2008-}}', '14 décembre 2008'],
            'écriture libre gardée' => ['{{date-|automne 2017}}', 'automne 2017'],
            '1er avec mois' => ['{{1er|juin}}', '1er juin'],
            '1er avec date entière' => ['{{1er|mai 1863}}', '1er mai 1863'],
            '1er avec lien dont la barre ne sépare pas' => ['{{1er|juin [[1969 dans les chemins de fer|1969]]}}', '1er juin 1969'],
            '1er seul' => ['{{1er}}', '1er'],
            'forme habituelle inchangée' => ['{{date|1 mars 1951}}', '1 mars 1951'],
        ];
    }

    #[DataProvider('templates')]
    public function test_template_is_rendered_as_a_readable_date(string $template, string $expected): void
    {
        $this->assertSame($expected, trim((new WikipediaLineParser)->toPlainText($template)));
    }

    public function test_troyes_gray_stage_openings_are_read_from_the_slash_template(): void
    {
        $text = "== Histoire ==\nLa ligne est mise en service en plusieurs étapes : Troyes - Bar-sur-Seine le {{Date-|20/7/1862||}}, "
            .'Bar-sur-Seine - Châtillon-sur-Seine le {{Date-|19/10/1868||}}, Châtillon-sur-Seine - Is-sur-Tille le {{date-|9/12/1882||}} '
            ."et enfin la section située entre Is-sur-Tille et Gray est mise en service le {{Date-|27/10/1888}}.\n";
        $dates = array_column($this->opening($text), 'date');

        $this->assertContains('1862-07-20', $dates);
        $this->assertContains('1888-10-27', $dates);
    }

    public function test_freight_end_is_read_from_the_slash_template(): void
    {
        $text = "== Histoire ==\nLe trafic marchandises cesse entre Villars et Poinson et entre [[Mirebeau-sur-Bèze]] et [[Champagne-sur-Vingeanne]] le {{Date-|30/5/1975}}.\n";
        $events = (new WikipediaLineParser)->parseChronology($text, 'Ligne de Saint-Julien (Troyes) à Gray');

        $this->assertContains('1975-05-30', array_column($events, 'date'));
    }

    public function test_template_and_plain_date_give_the_same_events(): void
    {
        $plain = "== Histoire ==\nLa ligne ferme au service voyageurs le 2 mars 1969 pour la section Châtillon-sur-Seine - Gray, puis le 31 mai 1980 pour Troyes - Châtillon-sur-Seine.\n";
        $template = str_replace(['2 mars 1969', '31 mai 1980'], ['{{Date-|2/3/1969}}', '{{date-|31 mai 1980}}'], $plain);
        $parser = new WikipediaLineParser;

        $this->assertSame(
            $parser->parseChronology($plain, 'Ligne de Saint-Julien (Troyes) à Gray'),
            $parser->parseChronology($template, 'Ligne de Saint-Julien (Troyes) à Gray'),
        );
    }

    public function test_list_item_and_infobox_use_the_same_reading(): void
    {
        $list = "== Chronologie ==\n* {{Date-|14/5/1986}} : électrification de la ligne\n";
        $events = (new WikipediaLineParser)->parseChronology($list, 'Ligne de Saint-Nazaire au Croisic');
        $this->assertContains('1986-05-14', array_column($events, 'date'));

        $infobox = "{{Infobox Ligne ferroviaire\n | nomligne = Test\n | mise en service = {{Date-|20/7/1862}}\n | fermeture = {{Date|3=1969}}\n}}\n";
        $events = (new WikipediaLineParser)->parseChronology($infobox, 'Ligne de Test à Exemple');
        $dates = array_column($events, 'date');
        $this->assertContains('1862-07-20', $dates);
        $this->assertContains('1969-01-01', $dates);
    }

    public function test_reopening_with_split_first_template_gets_its_month(): void
    {
        // Ligne de Sainte-Pazanne à Pornic : « fermée le {{1er|septembre}} 2014 » était lue 2014 sans mois.
        $text = "== Histoire ==\nEn conséquence, la ligne est fermée le {{1er|septembre}} 2014 afin de permettre la réalisation de travaux de rénovation.\n";
        $events = (new WikipediaLineParser)->parseChronology($text, 'Ligne de Sainte-Pazanne à Pornic');

        $this->assertContains('2014-09-01', array_column($events, 'date'));
    }

    /** @return list<array<string, mixed>> */
    private function opening(string $text): array
    {
        return array_values(array_filter(
            (new WikipediaLineParser)->parseChronology($text, 'Ligne de Saint-Julien (Troyes) à Gray'),
            fn (array $e) => $e['type'] === 'opening',
        ));
    }
}
