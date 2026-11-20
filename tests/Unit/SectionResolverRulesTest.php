<?php

namespace Tests\Unit;

use App\Services\SectionResolver;
use App\Services\WikipediaLineParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Résolution des portions d'événements (SectionResolver), règles pures :
 *   - variantes d'un nom de lieu cherchées sur le tracé ;
 *   - objections à appliquer un événement sans portion à TOUTE la ligne ;
 *   - textes qui visent « la ligne » (reste de la ligne après des fermetures partielles).
 */
class SectionResolverRulesTest extends TestCase
{
    private function resolver(): SectionResolver
    {
        return new SectionResolver(new WikipediaLineParser);
    }

    private function call(object $object, string $method, mixed ...$args): mixed
    {
        return (new \ReflectionMethod($object, $method))->invoke($object, ...$args);
    }

    // ─── variants ────────────────────────────────────────────────────────

    /** @return array<string, array{0: string, 1: list<string>, 2?: list<string>}> */
    public static function variantProvider(): array
    {
        return [
            'deux lieux séparés par un tiret' => ['La Varenne - Chennevières', ['La Varenne - Chennevières', 'La Varenne', 'Chennevières']],
            'embranchement de' => ['L\'embranchement de Auray', ['Auray']],
            'gare de' => ['Gare de Brest', ['Brest']],
            'ancienne halte de la' => ['Ancienne halte de la Brohinière', ['Brohinière']],
            'section de' => ['Section de Lunéville', ['Lunéville']],
            'département entre parenthèses' => ['Brest (Finistère)', ['Brest']],
            'suffixe Ville accolé' => ['Calais-Ville', ['Calais-Ville', 'Calais']],
            'suffixe Triage séparé' => ['Lyon Triage', ['Lyon']],
            'gare d\'une grande ville' => ['Paris-Bastille', ['Paris-Bastille', 'Paris']],
            'nom ancien' => ['Napoléonville', ['Napoléonville', 'Pontivy']],
            'nom ancien Bourbon-Vendée' => ['Bourbon-Vendée', ['La Roche-sur-Yon']],
            'nom composé gardé entier' => ['Saint-Brieuc', ['Saint-Brieuc'], ['Saint']],
            'trois mots gardés entiers' => ['Boissy-Saint-Léger', ['Boissy-Saint-Léger'], ['Boissy']],
            'barre oblique' => ['Bully/Grenay', ['Bully', 'Grenay']],
        ];
    }

    /**
     * @param  list<string>  $contains
     * @param  list<string>  $excludes
     */
    #[DataProvider('variantProvider')]
    public function test_place_name_variants(string $name, array $contains, array $excludes = []): void
    {
        $variants = $this->call($this->resolver(), 'variants', $name);

        foreach ($contains as $v) {
            $this->assertContains($v, $variants, implode(' | ', $variants));
        }
        foreach ($excludes as $v) {
            $this->assertNotContains($v, $variants, implode(' | ', $variants));
        }
        $this->assertSame(array_values(array_unique($variants)), $variants, 'sans doublon');
    }

    public function test_strict_variants_do_not_cut_a_station_name(): void
    {
        $this->assertNotContains('Paris', $this->call($this->resolver(), 'variants', 'Paris-Bastille', false));
    }

    // ─── wholeLineObjection ──────────────────────────────────────────────

    private function event(string $type, string $quote, string $date = '1950-01-01', string $description = ''): object
    {
        return (object) ['type' => $type, 'source_quote' => $quote, 'description' => $description, 'event_date' => $date];
    }

    public function test_quote_naming_an_unidentified_portion_is_never_applied_to_the_whole_line(): void
    {
        $resolver = $this->resolver();

        foreach (['La section est fermée en 1950.', 'Fermeture du tronçon terminal.', 'Le trafic cesse entre Carhaix et Loudéac.'] as $quote) {
            $this->assertStringStartsWith('Portion citée', $this->call($resolver, 'wholeLineObjection', $this->event('closure', $quote)), $quote);
        }
        // Infobox et Wikidata décrivent toute la ligne, même avec le mot « section ».
        $this->assertNull($this->call($resolver, 'wholeLineObjection', $this->event('closure', 'Infobox : fermeture = 1950 (sections)')));
        $this->assertNull($this->call($resolver, 'wholeLineObjection', $this->event('closure', 'Wikidata P3999 (parties)')));
    }

    public function test_reopening_without_explicit_whole_line_is_suspect(): void
    {
        $resolver = $this->resolver();

        $this->assertStringStartsWith('Réouverture', $this->call($resolver, 'wholeLineObjection', $this->event('reopening_passenger', 'La ligne rouvre aux voyageurs.')));
        $this->assertNull($this->call($resolver, 'wholeLineObjection', $this->event('reopening', 'La ligne rouvre sur toute la ligne.')));
        $this->assertNull($this->call($resolver, 'wholeLineObjection', $this->event('reopening', 'Réouverture de la ligne entière.')));
    }

    public function test_closure_after_a_partial_closure_needs_its_portion(): void
    {
        $resolver = $this->resolver();
        (new \ReflectionProperty($resolver, 'firstPartialClosure'))->setValue($resolver, '1938-05-15');

        $this->assertStringStartsWith('Fermeture sans portion', $this->call($resolver, 'wholeLineObjection', $this->event('closure', 'La ligne ferme au fret.', '1969-01-01')));
        $this->assertNull($this->call($resolver, 'wholeLineObjection', $this->event('closure', 'La ligne ferme aux voyageurs.', '1930-01-01')), 'avant la fermeture partielle');
        $this->assertNull($this->call($resolver, 'wholeLineObjection', $this->event('closure', 'La ligne est fermée en totalité.', '1969-01-01')));
        $this->assertNull($this->call($resolver, 'wholeLineObjection', $this->event('opening', 'Mise en service.', '1969-01-01')));
    }

    public function test_closure_without_earlier_partial_closure_applies_to_the_whole_line(): void
    {
        $this->assertNull($this->call($this->resolver(), 'wholeLineObjection', $this->event('closure', 'Fermeture au trafic voyageurs.')));
    }

    // ─── LINE_CLOSURE_TEXT ───────────────────────────────────────────────

    /** @return array<string, array{0: string, 1: bool}> */
    public static function lineClosureTextProvider(): array
    {
        return [
            'la ligne est fermée' => ['en 1969, la ligne est fermée au trafic.', true],
            'cette ligne a été déclassée' => ['cette ligne a été officiellement déclassée en 1972.', true],
            'fermeture de la ligne' => ['fermeture de la ligne le 1er mars.', true],
            'dépose de cette ligne' => ['dépose complète de cette ligne en 1975.', true],
            'reste de la ligne' => ['le reste de la ligne ferme en 1952.', true],
            'elle est fermée' => ['ouverte en 1905. elle est définitivement fermée en 1950.', true],
            'définitivement close' => ['la voie est définitivement close.', true],
            'la ligne cesse d\'être exploitée' => ['la ligne cesse d\'être exploitée en 1938.', true],
            'section fermée' => ['la section de carhaix à loudéac est fermée.', false],
            'gare fermée' => ['la gare est fermée en 1970.', false],
            'ouverture' => ['la ligne est ouverte en 1880.', false],
        ];
    }

    #[DataProvider('lineClosureTextProvider')]
    public function test_texts_that_target_the_whole_line(string $text, bool $expected): void
    {
        $pattern = (new \ReflectionClass(SectionResolver::class))->getConstant('LINE_CLOSURE_TEXT');

        $this->assertSame($expected, (bool) preg_match($pattern, $text));
    }

    public function test_pk_calibration_stretched_far_beyond_the_chainage_is_rejected(): void
    {
        // 632000 : PK 608,230 et 614,675 (6,4 km) calés à 47,2 km l'un de l'autre.
        $this->assertTrue(SectionResolver::stretchedCalibration([[0.0, 608230], [47199.9, 614675]]));
        // Puis PK 610,826 à 614,675 (3,8 km) calés à 1,1 km l'un de l'autre (forme SNCF généralisée).
        $this->assertTrue(SectionResolver::stretchedCalibration([[48318.0, 610826], [49436.2, 614675]]));
        // Écarts ordinaires (tracé un peu plus long ou plus court que le chaînage) : calage gardé.
        $this->assertFalse(SectionResolver::stretchedCalibration([[0.0, 608230], [52000.0, 659154]]));
        $this->assertFalse(SectionResolver::stretchedCalibration([[0.0, 10000], [9000.0, 15000], [21000.0, 30000]]));
        // Un seul point, ou deux points au même PK : rien à juger.
        $this->assertFalse(SectionResolver::stretchedCalibration([[0.0, 10000]]));
        $this->assertFalse(SectionResolver::stretchedCalibration([[0.0, 10000], [500.0, 10000]]));
    }
}
