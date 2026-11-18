<?php

namespace Tests\Unit;

use App\Console\Commands\DatesFromTraceNames;
use App\Console\Commands\ImportWikipediaChronology;
use App\Services\WikipediaLineParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Fonctions pures des commandes d'import (sans base ni réseau) : dates tirées
 * des noms de tracés archeoferroviaire et portions citées dans une référence
 * temporelle (Wikipédia). Les sélections OSM sont couvertes par ImporterMappingTest.
 */
class ImportCommandHelpersTest extends TestCase
{
    // ─── vff:dates-from-trace-names ──────────────────────────────────────

    /** @return array<string, array{0: string, 1: array<string, array{0: int, 1: string}>}> */
    public static function traceNameProvider(): array
    {
        return [
            'guerre 14-18' => ['Tinson Oulches 14-18 (voie de 0m60)', ['opening' => [1914, 'medium'], 'closure' => [1918, 'medium']]],
            'guerre 14/18' => ['Bussang Saint-Amarin 14/18', ['opening' => [1914, 'medium'], 'closure' => [1918, 'medium']]],
            'guerre 39-45' => ['Ligne X 39/45', ['opening' => [1939, 'medium'], 'closure' => [1945, 'medium']]],
            'militaire 30-40 : confiance basse' => ['Mil 30-40 Y', ['opening' => [1930, 'low'], 'closure' => [1940, 'low']]],
            'période entre parenthèses' => ['Carhaix Morlaix (1891-1967)', ['opening' => [1891, 'medium'], 'closure' => [1967, 'medium']]],
            'tirets longs normalisés' => ['Brest — Quimper (1890 – 1950)', ['opening' => [1890, 'medium'], 'closure' => [1950, 'medium']]],
            'jusqu\'en' => ['Tracé jusqu\'en 1935', ['closure' => [1935, 'medium']]],
            'jusqu’à (apostrophe typographique)' => ['Tracé jusqu’à 1935', ['closure' => [1935, 'medium']]],
            'depuis' => ['Tracé depuis 1902', ['opening' => [1902, 'medium']]],
            'ouverte en' => ['Ouverte en 1890', ['opening' => [1890, 'medium']]],
            'aucune date' => ['Carhaix Morlaix', []],
            'année hors plage' => ['Tracé (1750-1790)', []],
            'année seule non qualifiée' => ['Condé Guignicourt 1905', []],
        ];
    }

    #[DataProvider('traceNameProvider')]
    public function test_dates_from_trace_names(string $name, array $expected): void
    {
        $this->assertSame($expected, (new DatesFromTraceNames)->periods($name));
    }

    // ─── vff:import-wikipedia-chronology : référence temporelle ──────────

    private function chronology(): ImportWikipediaChronology
    {
        // Sans le conteneur Laravel : seule la dépendance au parseur sert ici.
        $rc = new ReflectionClass(ImportWikipediaChronology::class);
        $cmd = $rc->newInstanceWithoutConstructor();
        $rc->getProperty('parser')->setValue($cmd, new WikipediaLineParser);

        return $cmd;
    }

    private static function event(string $type, string $quote, ?string $from = 'Hazebrouck', ?string $to = 'Calais-Ville', string $origin = 'texte', string $date = '1993-05-16'): array
    {
        return ['type' => $type, 'date' => $date, 'year' => (int) substr($date, 0, 4), 'precision' => 'day', 'section_from' => $from, 'section_to' => $to,
            'pk_start_m' => null, 'pk_end_m' => null, 'portion_unknown' => false, 'origin' => $origin, 'heading' => 'Histoire',
            'description' => 'Ouverture d\'Hazebrouck à Calais-Ville (Wikipédia, texte, « Histoire »)', 'confidence' => 'medium', 'source_quote' => $quote];
    }

    public function test_section_of_a_reference_event_is_not_the_section_of_the_event(): void
    {
        $stats = [];
        $out = $this->chronology()->withoutReferenceSections([
            // Sujet inconnu (un poste d'aiguillage) : écarté.
            self::event('opening', 'Il est mis en service le 16 mai 1993, un mois avant la mise sous tension du tronçon d\'Hazebrouck à Calais-Ville.'),
            // Sujet « la ligne » : gardé, portion inconnue, confiance basse.
            self::event('electrification', 'La ligne est électrifiée le 20 juin 1993, un mois après la mise en service du tronçon d\'Hazebrouck à Calais-Ville.', date: '1993-06-20'),
            // Portion citée sans référence temporelle : inchangé.
            self::event('closure_passenger', 'La section de Rang-du-Fliers à Calais est fermée aux voyageurs le 1er octobre 1938.', 'Rang-du-Fliers', 'Calais', date: '1938-10-01'),
        ], $stats);

        $this->assertSame(['reference_section' => 2], $stats);
        $this->assertCount(2, $out);
        $this->assertSame(['electrification', null, null, true, 'low'],
            [$out[0]['type'], $out[0]['section_from'], $out[0]['section_to'], $out[0]['portion_unknown'], $out[0]['confidence']]);
        $this->assertSame('Électrification (Wikipédia, texte, « Histoire »)', $out[0]['description']);
        $this->assertSame(['closure_passenger', 'Rang-du-Fliers', 'Calais'], [$out[1]['type'], $out[1]['section_from'], $out[1]['section_to']]);
    }

    public function test_reference_rule_ignores_tables_infobox_and_kilometre_points(): void
    {
        $quote = 'Il est mis en service le 16 mai 1993, un mois avant la mise sous tension du tronçon d\'Hazebrouck à Calais-Ville.';
        $table = self::event('opening', $quote, origin: 'tableau');
        $pk = ['pk_start_m' => 1000, 'pk_end_m' => 2000] + self::event('opening', $quote);
        $noSection = self::event('opening', $quote, null, null);

        $this->assertSame([$table, $pk, $noSection], $this->chronology()->withoutReferenceSections([$table, $pk, $noSection]));
    }

    public function test_reference_rule_output_has_no_long_dash(): void
    {
        $e = self::event('electrification', 'La ligne est électrifiée le 20 juin 1993, un mois après la mise en service du tronçon d\'Hazebrouck à Calais-Ville.', date: '1993-06-20');
        $e['heading'] = 'Histoire — électrification';

        $out = $this->chronology()->withoutReferenceSections([$e]);

        $this->assertStringNotContainsString('—', $out[0]['description']);
    }
}
