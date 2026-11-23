<?php

namespace Tests\Unit\Accessibility;

use App\Services\Accessibility\AccessibilityNetex;
use App\Services\Accessibility\StationAccessibilityBuilder;
use PHPUnit\Framework\TestCase;

/** Assemblage des jeux de SNCF Gares & Connexions par gare : rattachements, équipements, états, absences. */
class StationAccessibilityBuilderTest extends TestCase
{
    private const G1 = 'aaaaaaaa-0000-4000-8000-000000000001';

    private const G2 = 'aaaaaaaa-0000-4000-8000-000000000002';

    /** @return array<string, mixed> */
    private function fixture(string $dataset): array
    {
        return json_decode((string) file_get_contents(dirname(__DIR__, 2)."/Fixtures/SncfAccessibility/{$dataset}.json"), true);
    }

    /**
     * @param-out StationAccessibilityBuilder $builder
     *
     * @return array<string, array<string, mixed>>
     */
    private function built(?StationAccessibilityBuilder &$builder = null): array
    {
        $builder = new StationAccessibilityBuilder($this->fixture('gares-de-voyageurs')['records']);
        $stops = (new AccessibilityNetex)->stations(dirname(__DIR__, 2).'/Fixtures/SncfAccessibility/netex-accessibilite-gares.xml');

        return $builder->build($stops, $this->fixture('equipements-accessibilite-en-gare')['records'], $this->fixture('assistance-psh-pmr')['records'],
            $this->fixture('etat-fonctionnement-elevatique-gare')['records'], $this->fixture('points-vente')['records'],
            $this->fixture('stationnement-securise-velo-en-gare-au-30-06-24')['records'], '2026-11-23T10:00:00+01:00');
    }

    public function test_a_station_gets_every_dataset_that_knows_it(): void
    {
        $row = $this->built()[self::G1];

        $this->assertSame(['partial', true, true, true], [$row['mobility'], $row['accessible'], $row['step_free'], $row['tactile_guidance']]);
        $this->assertSame([2, 1], [$row['quays_total'], $row['quays_step_free']]);
        $this->assertSame([2, 1, 0], [$row['lifts'], $row['escalators'], $row['travelators']]);
        $this->assertSame('présence ponctuelle de dispositifs de guidage sonore', $row['netex_comment']);
        $this->assertSame(['induction_loop', 'tactile_strips', 'toilets'], $row['amenities'], 'équipements dans l\'ordre de l\'API, inconnu écarté');
        $this->assertSame(['mode' => 'reserved_or_walk_in', 'free' => true, 'meeting_point' => 'Guichet', 'first_to_last_train' => false,
            'hours' => ['monday' => '08:45 - 12:00 / 13:30 - 17:00', 'tuesday' => '08:45 - 12:00 / 13:30 - 17:00', 'wednesday' => '08:45 - 12:00 / 13:30 - 17:00',
                'thursday' => '08:45 - 12:00 / 13:30 - 17:00', 'friday' => '08:45 - 12:00 / 13:30 - 17:00', 'saturday' => null, 'sunday' => null],
            'since' => '2025-06-04'], $row['assistance']);
        $this->assertSame(['counter' => true, 'machines' => ['ter'], 'assisted' => false], $row['sales']);
        $this->assertSame(['places' => 6, 'as_of' => '2024-12-30', 'lom' => true, 'lom_target' => 10], $row['bike_parking']);
    }

    public function test_elevator_states_are_counted_sorted_and_dated(): void
    {
        $e = $this->built()[self::G1]['elevators'];

        $this->assertSame(['state_at' => '2026-11-23T10:00:00+01:00', 'ok' => 1, 'ko' => 1, 'unknown' => 0], array_diff_key($e, ['items' => 1]));
        $this->assertSame(['Ascenseur - Voie 1', 'Ascenseur - Voie 2', 'Esc mec - Hall'], array_column($e['items'], 'label'));
        $this->assertSame(['ko', 'ok', 'ko'], array_column($e['items'], 'state'));
        $this->assertSame(['lift', 'lift', 'escalator'], array_column($e['items'], 'kind'), 'les nombres ne comptent que les ascenseurs');
    }

    public function test_an_inaccessible_station_is_known_as_such_and_a_missing_fact_stays_missing(): void
    {
        $row = $this->built()[self::G2];

        $this->assertSame(['none', false, false], [$row['mobility'], $row['accessible'], $row['step_free']]);
        $this->assertSame(['staff'], $row['amenities']);
        $this->assertNull($row['assistance']);
        $this->assertNull($row['elevators']);
        $this->assertNull($row['sales']);
        $this->assertSame(['places' => 2, 'as_of' => '2024-12-30', 'lom' => false, 'lom_target' => null], $row['bike_parking'], 'N/C : pas d\'objectif');
    }

    public function test_unknown_equipment_labels_are_reported_not_stored(): void
    {
        $this->built($builder);

        $this->assertSame(['Équipement inventé' => 1], $builder->unknownAmenities);
    }

    public function test_a_gare_with_several_uic_codes_is_reachable_by_each(): void
    {
        $this->built($builder);

        $this->assertSame(['87000004', '87000005'], $builder->uicsOf('aaaaaaaa-0000-4000-8000-000000000004'));
    }

    public function test_uic_codes_lose_their_leading_zeros(): void
    {
        $this->assertSame('87763425', StationAccessibilityBuilder::uic('0087763425'));
        $this->assertSame('87763425', StationAccessibilityBuilder::uic('87763425'));
    }
}
