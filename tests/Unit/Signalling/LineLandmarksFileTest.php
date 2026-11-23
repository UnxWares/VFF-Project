<?php

namespace Tests\Unit\Signalling;

use App\Services\Signalling\LineLandmarksFile;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Fichier des points remarquables des lignes (LineLandmarksFile) : contrôle de chaque point, noms uniques par
 * ligne. Le vrai fichier du dépôt est contrôlé aussi.
 */
class LineLandmarksFileTest extends TestCase
{
    /** @param  array<string, mixed>  $over */
    private static function entry(array $over = []): array
    {
        return array_merge([
            'line' => '752000', 'name' => 'Bifurcation de Saint-Florentin', 'aliases' => ['Saint-Florentin'], 'kind' => 'junction',
            'pk_m' => 117_186, 'lat' => 47.97414, 'lon' => 3.69320, 'method' => 'osm_milestones',
            'sources' => [['publisher' => 'OpenStreetMap', 'title' => 'Borne', 'url' => 'https://www.openstreetmap.org/node/12036225248']],
        ], $over);
    }

    public function test_a_valid_point_is_kept_with_its_comparable_names(): void
    {
        ['points' => $points, 'errors' => $errors] = LineLandmarksFile::parse([self::entry()]);

        $this->assertSame([], $errors);
        $this->assertSame([
            'name' => 'Bifurcation de Saint-Florentin', 'keys' => ['bifurcation de st florentin', 'st florentin'], 'kind' => 'junction',
            'pk_m' => 117_186, 'lat' => 47.97414, 'lon' => 3.69320, 'method' => 'osm_milestones',
        ], $points['752000'][0]);
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function refused(): array
    {
        return [
            'code' => [['line' => '75200'], 'code de ligne'],
            'nom' => [['name' => ' '], 'nom manquant'],
            'aliases' => [['aliases' => 'Saint-Florentin'], 'aliases'],
            'genre' => [['kind' => 'station'], 'kind inconnu'],
            'pk décimal' => [['pk_m' => 117.2], 'pk_m'],
            'position' => [['lat' => 4.8, 'lon' => 47.9], 'hors de France'],
            'méthode' => [['method' => 'guess'], 'method inconnue'],
            'sans source' => [['sources' => []], 'source'],
            'source sans adresse' => [['sources' => [['title' => 'Borne', 'url' => 'http://example.org']]], 'source'],
            'alias redit' => [['aliases' => ['St Florentin', 'Saint-Florentin']], 'déjà pris'],
        ];
    }

    /** @param  array<string, mixed>  $over */
    #[DataProvider('refused')]
    public function test_a_faulty_point_is_refused(array $over, string $message): void
    {
        ['points' => $points, 'errors' => $errors] = LineLandmarksFile::parse([self::entry($over)]);

        $this->assertSame([], $points);
        $this->assertCount(1, $errors);
        $this->assertStringContainsString($message, $errors[0]);
    }

    public function test_two_points_of_a_line_never_share_a_name_but_two_lines_may(): void
    {
        ['points' => $points, 'errors' => $errors] = LineLandmarksFile::parse([
            self::entry(),
            self::entry(['name' => 'Raccordement de Saint-Florentin', 'aliases' => ['St-Florentin']]),
            self::entry(['line' => '830000']),
        ]);

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('point n° 2', $errors[0]);
        $this->assertSame(['752000', '830000'], array_map('strval', array_keys($points)));
    }

    public function test_a_faulty_file_cannot_be_loaded(): void
    {
        $path = sys_get_temp_dir().'/vff-landmarks-'.bin2hex(random_bytes(4)).'.php';
        file_put_contents($path, '<?php return '.var_export([self::entry(['kind' => 'gare'])], true).';');
        try {
            $this->expectException(\UnexpectedValueException::class);
            LineLandmarksFile::load($path);
        } finally {
            unlink($path);
        }
    }

    public function test_the_repository_file_is_valid(): void
    {
        $points = LineLandmarksFile::load(dirname(__DIR__, 3).'/database/data/line_landmarks.php');

        $this->assertSame(['005000', '014000', '226000', '431000', '752000'], array_map('strval', array_keys($points)));
        $this->assertSame(16, array_sum(array_map('count', $points)));
        foreach ($points as $line => $list) {
            $pks = array_column($list, 'pk_m');
            $sorted = $pks;
            sort($sorted);
            $this->assertSame($sorted, $pks, "points de la ligne {$line} rangés par PK");
        }
    }
}
