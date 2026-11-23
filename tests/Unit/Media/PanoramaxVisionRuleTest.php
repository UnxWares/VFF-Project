<?php

namespace Tests\Unit\Media;

use App\Services\Media\Panoramax\PanoramaxSelection;
use App\Services\Media\Panoramax\PanoramaxVision;
use App\Services\Media\Panoramax\StationScene;
use PHPUnit\Framework\TestCase;

/**
 * Tri des photos de rue par vision : premier filtre géométrique large (vignettes à noter), puis seuil sur la note
 * « la gare est visible », abaissé quand la règle des abords voit aussi le bâtiment de la gare.
 */
class PanoramaxVisionRuleTest extends TestCase
{
    private const LAT = 48.0;

    private const LNG = 2.0;

    /**
     * Photo à `$northM` mètres au nord de la gare (négatif : au sud).
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private static function photo(string $id, float $northM, ?float $azimuth, string $seq = '', int $fov = 80, string $instance = 'ign', array $extra = []): array
    {
        return [
            'id' => $id,
            'collection' => $seq !== '' ? $seq : 'seq-'.$id,
            'geometry' => ['type' => 'Point', 'coordinates' => [self::LNG, self::LAT + rad2deg($northM / 6371008.8)]],
            'links' => [['rel' => 'via', 'instance_name' => $instance]],
            'providers' => [['name' => 'contributeur', 'roles' => ['producer']]],
            'assets' => ['thumb' => ['href' => "https://panoramax.ign.fr/api/pictures/{$id}/thumb.jpg"]],
            'properties' => array_filter([
                'license' => 'etalab-2.0', 'datetime' => '2025-05-01T10:00:00+00:00', 'view:azimuth' => $azimuth,
                'pers:interior_orientation' => ['field_of_view' => $fov], 'quality:horizontal_accuracy' => 2.0,
                ...$extra,
            ], fn ($v) => $v !== null),
        ];
    }

    /** @param  list<array<string, mixed>>  $features */
    private static function ids(array $features): array
    {
        return array_map(fn (array $f): string => (string) $f['id'], $features);
    }

    public function test_candidates_use_a_wide_cone_and_skip_other_instances(): void
    {
        $rejected = [];
        $c = PanoramaxSelection::visionCandidates([
            self::photo('face', -50, 0),
            self::photo('biais-30', -50, 30),
            self::photo('biais-50', -50, 50),
            self::photo('pano-biais-50', -50, 50, fov: 360),
            self::photo('loin', -105, 0),
            self::photo('sans-cap', -20, null),
            self::photo('penchee', -20, 0, extra: ['pers:roll' => 25]),
            self::photo('osm', -20, 0, instance: 'osm-fr', extra: ['license' => 'CC-BY-SA-4.0']),
        ], self::LAT, self::LNG, $rejected);

        $this->assertSame(['face', 'biais-30', 'pano-biais-50'], self::ids($c));
        ksort($rejected);
        $this->assertSame(['facing_away' => 1, 'no_azimuth' => 1, 'tilted' => 1, 'too_far' => 1, 'vision_instance' => 1], $rejected);
    }

    public function test_candidates_are_bounded_per_sequence_and_per_station(): void
    {
        $features = [];
        foreach (range(1, 8) as $s) {
            foreach (range(1, 4) as $i) {
                // Séquence s : photos de plus en plus loin ; les séquences de rang élevé sont plus loin aussi.
                $features[] = self::photo("s{$s}-p{$i}", -(5 * $s + $i), 0, "seq-{$s}");
            }
        }
        $c = PanoramaxSelection::visionCandidates($features, self::LAT, self::LNG);

        $this->assertCount(PanoramaxSelection::VISION_MAX_SEQUENCES * PanoramaxSelection::VISION_PER_SEQUENCE, $c);
        $this->assertSame(['s1-p1', 's1-p2'], array_slice(self::ids($c), 0, 2));
        $this->assertNotContains('s7-p1', self::ids($c), 'séquences les plus lointaines laissées de côté');
    }

    public function test_pick_keeps_scored_photos_above_the_threshold_one_per_sequence(): void
    {
        $scores = ['haute' => 0.95, 'meme-seq' => 0.99, 'moyenne' => PanoramaxVision::MIN_SCORE, 'basse' => PanoramaxVision::MIN_SCORE - 0.01];
        $rejected = [];
        $picked = PanoramaxSelection::pickVision([
            self::photo('haute', -30, 0, 'a'),
            self::photo('meme-seq', -90, 0, 'a'),
            self::photo('moyenne', -40, 0, 'b'),
            self::photo('basse', -20, 0, 'c'),
            self::photo('sans-note', -20, 0, 'd'),
        ], self::LAT, self::LNG, fn (string $id): ?float => $scores[$id] ?? null, $rejected);

        $this->assertSame(['haute', 'moyenne'], self::ids($picked), 'une par séquence : note et proximité');
        $this->assertSame(['vision_low' => 1, 'vision_unscored' => 1], $rejected);
        $this->assertSame(0.95, $picked[0]['vff:vision']['score']);
        $this->assertFalse($picked[0]['vff:vision']['scene']);
    }

    public function test_scene_rule_is_recorded_and_ranks_first_at_equal_score(): void
    {
        // Bâtiment voyageurs de 20 m centré sur la gare : la photo à 40 m au sud, tournée au nord, le voit ;
        // l'autre, tournée vers l'est, ne le voit pas.
        $scene = new StationScene(self::LAT, self::LNG, targets: [[[-10.0, -10.0], [10.0, -10.0], [10.0, 10.0], [-10.0, 10.0]]]);
        $f = [self::photo('route', -40, 90, 'b'), self::photo('vue', -40, 0, 'a')];

        $picked = PanoramaxSelection::pickVision($f, self::LAT, self::LNG, fn (): float => 0.9, scene: $scene);

        $this->assertSame(['vue', 'route'], self::ids($picked));
        $this->assertTrue($picked[0]['vff:vision']['scene']);
        $this->assertArrayHasKey('vff:view', $picked[0]);
        $this->assertFalse($picked[1]['vff:vision']['scene']);
        $this->assertArrayNotHasKey('vff:view', $picked[1]);

        $below = min(PanoramaxVision::MIN_SCORE, PanoramaxVision::MIN_SCORE_SCENE) - 0.01;
        $this->assertSame([], PanoramaxSelection::pickVision($f, self::LAT, self::LNG, fn (): float => $below, scene: $scene));
    }
}
