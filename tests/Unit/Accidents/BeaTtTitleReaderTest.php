<?php

namespace Tests\Unit\Accidents;

use App\Enums\AccidentKind;
use App\Services\Accidents\AccidentKindClassifier;
use App\Services\Accidents\BeaTtTitleReader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Intitulés réels du bilan des enquêtes du BEA-TT : commune, département, passage à niveau, gare,
 * trains cités, nature (mode PN = passage à niveau).
 */
class BeaTtTitleReaderTest extends TestCase
{
    private BeaTtTitleReader $reader;

    protected function setUp(): void
    {
        $this->reader = new BeaTtTitleReader(new AccidentKindClassifier);
    }

    /** @return iterable<string, array{0: string, 1: ?string}> */
    public static function places(): iterable
    {
        yield 'après « à »' => ['Collision entre un TER et un autocar scolaire sur le PN 25 à Millas (66)', 'Millas'];
        yield 'dernier « à »' => ['Déraillement d’une rame de TGV en Gare de Lyon à Paris (75)', 'Paris'];
        yield 'article contracté' => ['Collision entre un TER et un VL au Breuil (69)', 'Le Breuil'];
        yield 'article gardé' => ['Collision entre un poids lourd et un train à la Roche-en-Brenil', 'la Roche-en-Brenil'];
        yield 'après « de »' => ['Passage à niveau de Neufchâteau', 'Neufchâteau'];
        yield 'gare de' => ['Déraillement d’un TER à l’entrée de la gare de Sainte-Pazanne (44)', 'Sainte-Pazanne'];
        yield 'exposant collé' => ['Déraillement d’une rame de tramway suite à la collision avec un VL à Lyon 8e (69)', 'Lyon 8e'];
        yield 'train collé au lieu' => ['Collision de 2 TER Longueville', 'Longueville'];
        yield 'fret collé au lieu' => ['Déraillement train Fret La Ferté-sur-Chiers', 'La Ferté-sur-Chiers'];
        yield 'dernier nom propre' => ['Enrayage d’un essieu d’un train de fret entre Romilly-sur-Seine et Troyes (10)', 'Troyes'];
        yield 'aucun lieu' => ['Incendie dans une navette fret Eurotunnel dans le tunnel nord de la liaison fixe transmanche', null];
    }

    #[DataProvider('places')]
    public function test_place_is_the_proper_noun_after_the_last_preposition(string $title, ?string $expected): void
    {
        $this->assertSame($expected, $this->reader->place($title));
    }

    public function test_reads_department_level_crossing_station_and_kind(): void
    {
        $pn = $this->reader->read('Collision entre un TER et un camion semi-remorque sur le PN n° 169 à Montauban (82)', 'PN');
        $this->assertSame('82', $pn['department']);
        $this->assertSame(169, $pn['level_crossing']);
        $this->assertSame(AccidentKind::LevelCrossing, $pn['kind']);

        $station = $this->reader->read('Déraillement d’une rame TGV en entrée de gare de Marseille St-Charles (13)', 'F');
        $this->assertSame('Marseille St-Charles', $station['station']);
        $this->assertSame(AccidentKind::Derailment, $station['kind']);

        $corsica = $this->reader->read('Choc d’un train de Voyageurs contre un heurtoir en gare de Calvi (2B)', 'TG');
        $this->assertSame('2B', $corsica['department']);
        $this->assertSame('Calvi', $corsica['station']);
    }

    public function test_trains_named_with_number_and_categories_without_duplicates(): void
    {
        $this->assertSame(
            [['label' => 'TER', 'category' => 'passenger', 'number' => '849 902', 'material' => null, 'operator' => null]],
            $this->reader->trains('Déraillement et début d’incendie du TER 849 902, le 30/05/2025 à Laon (02)'),
        );
        $this->assertSame(['RER C'], array_column($this->reader->trains('Déraillement RER C à Choisy-le-Roi'), 'label'));
        $mixed = $this->reader->trains('Déraillement de trois wagons trémies et heurt d’un TGV croiseur à Valence d’Agen (82)');
        $this->assertSame(['TGV', null], array_column($mixed, 'label'));
        $this->assertSame(['passenger', 'freight'], array_column($mixed, 'category'));
        // « train de voyageurs » n'ajoute rien quand le TER est nommé.
        $this->assertCount(1, $this->reader->trains('Collision d’un train de voyageurs TER avec un tracteur'));
        $this->assertSame([], $this->reader->trains('Effondrement du tunnel de Vierzy'));
    }
}
