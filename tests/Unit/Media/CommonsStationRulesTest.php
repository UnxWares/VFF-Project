<?php

namespace Tests\Unit\Media;

use App\Services\Media\Sources\CommonsStationRules;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Règles pures du rattachement aux gares des fichiers Commons déjà en base (CommonsStationRules). */
class CommonsStationRulesTest extends TestCase
{
    public function test_normalize_expands_saint_and_drops_accents_and_punctuation(): void
    {
        $this->assertSame('la mothe saint heray', CommonsStationRules::normalize('La Mothe-St-Héray'));
        $this->assertSame('epierre saint leger', CommonsStationRules::normalize('Épierre-St-Léger'));
        $this->assertSame('sainte foy', CommonsStationRules::normalize('Ste-Foy'));
        $this->assertSame('gare de niort 2012', CommonsStationRules::normalize('Gare de Niort-2012.jpg'));
    }

    public function test_station_keys_keep_name_without_trailing_qualifier(): void
    {
        $keys = CommonsStationRules::stationKeys(['Saint-Marcellin (Isère)', 'St-Marcellin']);
        $this->assertContains('saint marcellin isere', $keys);
        $this->assertContains('saint marcellin', $keys);
    }

    /** @return iterable<string, array{string, list<string>}> */
    public static function categories(): iterable
    {
        yield 'gare de' => ['Gare de Saint-Marcellin (Isère)', ['saint marcellin']];
        yield 'gare du' => ['Trains in Gare du Mans', ['mans', 'le mans']];
        yield 'gare d\'' => ["Exterior of Gare d'Évry-Courcouronnes", ['evry courcouronnes']];
        yield 'entre parenthèses' => ['Hall 2 (gare de Lyon-Part-Dieu)', ['lyon part dieu']];
        yield 'tirets entourés d\'espaces' => ['Gare de Rouen - Rive Droite', ['rouen rive droite']];
        yield 'bahnhof' => ['Bahnhof Saargemünd', ['saargemund']];
        yield 'anglais' => ['Luxembourg City railway station', ['luxembourg city']];
        yield 'métro écarté' => ['Gares (Rennes Metro)', []];
        yield 'sans gare' => ['2012 in Deux-Sèvres', []];
    }

    /** @param  list<string>  $expected */
    #[DataProvider('categories')]
    public function test_category_keys(string $category, array $expected): void
    {
        $keys = CommonsStationRules::categoryKeys($category);
        foreach ($expected as $k) {
            $this->assertContains($k, $keys, $category);
        }
        if ($expected === []) {
            $this->assertSame([], $keys);
        }
    }

    public function test_title_names_the_station_as_a_whole_word(): void
    {
        $this->assertTrue(CommonsStationRules::titleNames('File:Gare de Brunstatt 09112011.jpg', ['brunstatt']));
        $this->assertTrue(CommonsStationRules::titleNames('File:Gare-Lingolsheim-1900.jpg', ['lingolsheim']));
        $this->assertTrue(CommonsStationRules::titleNames('File:Bahnhof Gueret.jpg', ['gueret']));
        $this->assertTrue(CommonsStationRules::titleNames('File:Trébrivan-Le Moustoir-station.jpg', ['trebrivan le moustoir']));
        // Nom composé : « Grenoble-Gières » et « Nice - Riquier » ne nomment ni Grenoble ni Nice.
        $this->assertFalse(CommonsStationRules::titleNames('File:Fin électrification gare de Grenoble-Gières.JPG', ['grenoble']));
        $this->assertFalse(CommonsStationRules::titleNames('File:Gare de Nice - Riquier 2020 11.jpg', ['nice']));
        // Un nom sans « gare » ne suffit pas.
        $this->assertFalse(CommonsStationRules::titleNames('File:Vue de Brunstatt.jpg', ['brunstatt']));
    }

    public function test_title_key_prefers_the_longest_name(): void
    {
        $this->assertSame('valence tgv', CommonsStationRules::titleKey('File:Gare de Valence TGV côté TER.jpg', ['valence', 'valence tgv']));
    }

    public function test_title_extensions_list_longer_names(): void
    {
        $this->assertSame(['valence tgv', 'valence tgv cote', 'valence tgv cote ter'],
            CommonsStationRules::titleExtensions('File:Gare de Valence TGV côté TER (1).jpg', 'valence'));
    }

    public function test_names_a_station(): void
    {
        $this->assertTrue(CommonsStationRules::namesAStation('Gare de Laissey'));
        $this->assertFalse(CommonsStationRules::namesAStation('Maison de garde-barrières (Gondecourt)'));
        $this->assertFalse(CommonsStationRules::namesAStation('Station de métro Bastille'));
    }

    public function test_reject_reasons(): void
    {
        $this->assertNull(CommonsStationRules::rejectReason('station_p373', null, false, 2010, 1860, 'Gare de X'));
        $this->assertSame('too_far', CommonsStationRules::rejectReason('station_p373', 301.0, true, 2010, 1860, 'Gare de X'));
        $this->assertNull(CommonsStationRules::rejectReason('station_category', 299.0, true, 2010, 1860, 'Gare de X'));
        $this->assertSame('not_corroborated', CommonsStationRules::rejectReason('station_category', null, false, 2010, 1860, 'Gare de X'));
        $this->assertSame('before_opening', CommonsStationRules::rejectReason('station_title', null, true, 1850, 1860, 'Gare de X'));
        $this->assertNull(CommonsStationRules::rejectReason('station_title', null, true, 1858, 1860, 'Gare de X'));
        $this->assertSame('not_picture', CommonsStationRules::rejectReason('station_p373', null, true, null, null, 'Logo SNCF gare de X'));
    }
}
