<?php

namespace Tests\Unit\Factories;

use App\Models\Accident;
use App\Models\Station;
use Database\Factories\StationFactory;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Département des gares et des accidents fabriqués pour les tests : toujours un code réservé
 * (StationFactory::RESERVED_DEPARTMENTS), jamais celui qu'un test filtre. Un tirage parmi tous les
 * codes faisait parfois tomber un objet de remplissage dans le département filtré (échec en CI).
 */
class FactoryDepartmentsTest extends TestCase
{
    public function test_factories_only_draw_reserved_real_departments(): void
    {
        foreach (range(1, 200) as $_) {
            $this->assertContains(Station::factory()->raw()['department_code'], StationFactory::RESERVED_DEPARTMENTS);
            $this->assertContains(Accident::factory()->raw()['department_code'], StationFactory::RESERVED_DEPARTMENTS);
        }

        foreach (StationFactory::RESERVED_DEPARTMENTS as $code) {
            $this->assertMatchesRegularExpression('/\A(0[1-9]|[1-8]\d|9[0-5])\z/', $code, 'département métropolitain réel');
        }
    }

    public function test_no_php_test_uses_a_reserved_department(): void
    {
        $quoted = implode('|', StationFactory::RESERVED_DEPARTMENTS);
        // Exemptions étroites, tests purs (sans base) où aucun objet de remplissage n'entre en jeu :
        // - un code postal à 4 chiffres commençant par 97 donne '09' (zéro de tête) ;
        // - la règle de lieu de Commons lit le vrai département de gares homonymes : Romont (Vosges, 88),
        //   Floirac (Lot, 46) face à Floirac (Gironde), Saint-Rémy-sur-Marne dont le nom contient la Haute-Marne (52).
        $exempt = [
            'Unit/Sncf/StationAttendanceMatcherBoundaryTest.php' => ['09'],
            'Unit/Media/CommonsCoverageTest.php' => ['88', '46', '52'],
        ];
        $found = [];
        foreach (Finder::create()->files()->in(base_path('tests'))->name('*.php')->notName('FactoryDepartmentsTest.php') as $file) {
            if (preg_match_all('/[\'"=]('.$quoted.')[\'"&]/', $file->getContents(), $matches) > 0) {
                $codes = array_diff(array_unique($matches[1]), $exempt[$file->getRelativePathname()] ?? []);
                if ($codes !== []) {
                    $found[$file->getRelativePathname()] = array_values($codes);
                }
            }
        }

        $this->assertSame([], $found, 'ces départements sont réservés aux fabriques : choisissez-en un autre dans le test');
    }
}
