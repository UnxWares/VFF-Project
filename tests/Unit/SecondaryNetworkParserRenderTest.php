<?php

namespace Tests\Unit;

use App\Services\SecondaryNetworkParser;
use App\Services\WikipediaLineParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Rendu d'un fragment de wikitexte (SecondaryNetworkParser::render) : modèles courants lus comme du
 * texte (dates, unités, tri, langue, numéros), liens internes ramenés à leur libellé avec leur cible,
 * entités HTML, espaces insécables, et retrait des fichiers insérés (stripFileLinks).
 */
class SecondaryNetworkParserRenderTest extends TestCase
{
    private SecondaryNetworkParser $parser;

    protected function setUp(): void
    {
        $this->parser = new SecondaryNetworkParser(new WikipediaLineParser);
    }

    /** @return array<string, array{string, string}> */
    public static function templates(): array
    {
        return [
            'date en trois champs' => ['{{date|12|7|1891}}', '12 juillet 1891'],
            'jour à zéro initial' => ['{{date|02|05|1908}}', '2 mai 1908'],
            'date écrite' => ['{{Date|12 juillet 1891}}', '12 juillet 1891'],
            'premier du mois' => ['{{date|1er|mars|1900}}', '1er mars 1900'],
            'mois et année' => ['{{date|mars|1900}}', 'mars 1900'],
            'année seule' => ['{{date|1900}}', '1900'],
            'date vide' => ['{{date|}}', ''],
            'unité' => ['{{unité|1 200|m}}', '1 200 m'],
            'tri : la dernière valeur' => ['{{tri|a|b|c}}', 'c'],
            'langue : le texte, pas le code' => ['{{lang|de|Bahnhof}}', 'Bahnhof'],
            'numéro' => ['{{n°|7}}', 'n° 7'],
            'saint' => ['{{St}} {{Ste}}', 'St Ste'],
            'ordinal' => ['{{1er}}', '1er'],
            'mise en forme : le contenu' => ['{{nobr|Saint-Brieuc}}', 'Saint-Brieuc'],
            'lien : texte de préférence' => ['{{lien|texte=Foo|fr=Bar}}', 'Foo'],
            'modèle inconnu : rien' => ['avant{{inconnu|x}}après', 'avantaprès'],
            'modèles imbriqués' => ['{{nobr|le {{date|1900}}}}', 'le 1900'],
        ];
    }

    #[DataProvider('templates')]
    public function test_templates_become_plain_text(string $wikitext, string $expected): void
    {
        $this->assertSame($expected, $this->parser->render($wikitext)[0]);
    }

    public function test_internal_links_give_their_label_and_a_normalised_target_index(): void
    {
        [$text, $links] = $this->parser->render("[[Saint-Brieuc|St-Brieuc]] à [[Loudéac]], via [[Gare d'Uzel#Histoire]]");

        $this->assertSame("St-Brieuc à Loudéac, via Gare d'Uzel", $text);
        $this->assertSame('Saint-Brieuc', $links['saint brieuc']);
        $this->assertSame('Loudéac', $links['loudeac']);
    }

    public function test_date_links_and_namespaced_links_are_not_places(): void
    {
        [$text, $links] = $this->parser->render('en [[1891 dans les chemins de fer|1891]], [[Catégorie:Ligne]]fin');

        $this->assertSame('en 1891, fin', $text);
        $this->assertSame([], $links);
    }

    public function test_external_links_keep_their_label_only(): void
    {
        $this->assertSame('le site officiel', $this->parser->render('[https://exemple.org le site officiel]')[0]);
        $this->assertSame('', $this->parser->render('[https://exemple.org]')[0]);
    }

    public function test_html_and_typography_are_cleaned(): void
    {
        $this->assertSame('a b & c d', $this->parser->render("a\u{00A0}b &amp; c<br/>d")[0]);
        $this->assertSame('texte en gras', $this->parser->render("texte en '''gras'''")[0]);
    }

    private function strip(string $wikitext): string
    {
        return (new ReflectionMethod($this->parser, 'stripFileLinks'))->invoke($this->parser, $wikitext);
    }

    public function test_inserted_files_are_removed_even_with_links_in_their_caption(): void
    {
        $this->assertSame('avant  après', $this->strip('avant [[Fichier:A.jpg|vignette|La [[gare]] de X]] après'));
        $this->assertSame('  fin', $this->strip('[[File:A.jpg|x]] [[Image:B.png|y [[z]] w]] fin'));
        $this->assertSame('suite', $this->strip('[[fichier:minuscule.jpg|x]]suite'));
    }

    public function test_ordinary_links_and_text_are_left_alone(): void
    {
        $this->assertSame('sans fichier [[lien]]', $this->strip('sans fichier [[lien]]'));
    }
}
