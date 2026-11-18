<?php

namespace Tests\Unit;

use App\Support\StationNames;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Noms de gares (StationNames) : cas limites de la normalisation (clé de
 * dédoublonnage multi-sources) et de la mise en casse des libellés SNCF.
 */
class StationNamesEdgeCasesTest extends TestCase
{
    /** @return array<string, array{0: ?string, 1: ?string}> */
    public static function normalizeProvider(): array
    {
        return [
            'null' => [null, null],
            'blancs' => ["  \t ", null],
            'guillemets autour du libellé' => ['« Gare de Brest »', 'brest'],
            'ponctuation en tête' => ['- Halte de Kerhuon', 'kerhuon'],
            'point d\'arrêt sans nom' => ['Point d\'arrêt', null],
            'bâtiment voyageurs sans nom' => ['Ancien BV', null],
            'apostrophe typographique' => ['Gare de l’Isle-Adam', 'isle adam'],
            'suffixe SNCF' => ['Morlaix SNCF', 'morlaix'],
            'Ste et St' => ['Ste-Foy-lès-Lyon St', 'sainte foy les lyon saint'],
            's/ abrégé' => ['Vic s/ Cère', 'vic sur cere'],
        ];
    }

    #[DataProvider('normalizeProvider')]
    public function test_normalize(?string $name, ?string $expected): void
    {
        $this->assertSame($expected, StationNames::normalize($name));
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function prettifyProvider(): array
    {
        return [
            'élision suivie d\'une majuscule accentuée' => ['L\'ÉTANG-LA-VILLE', 'L\'Étang-la-Ville'],
            'apostrophe typographique' => ['L’ÎLE-BOUCHARD', 'L’Île-Bouchard'],
            'article en tête gardé en majuscule' => ['LES AUBRAIS', 'Les Aubrais'],
            'déjà en casse mixte : inchangé' => ['Saint-Étienne-Châteaucreux', 'Saint-Étienne-Châteaucreux'],
            'accents en majuscules' => ['ÉCOUEN-ÉZANVILLE', 'Écouen-Ézanville'],
            'lès' => ['ROMAGNY-LÈS-MONTBÉLIARD', 'Romagny-lès-Montbéliard'],
        ];
    }

    #[DataProvider('prettifyProvider')]
    public function test_prettify(string $label, string $expected): void
    {
        $this->assertSame($expected, StationNames::prettify($label));
    }
}
