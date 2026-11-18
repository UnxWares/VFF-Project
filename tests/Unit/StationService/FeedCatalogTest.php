<?php

namespace Tests\Unit\StationService;

use App\Enums\StationServiceClass;
use App\Enums\StationStatus;
use App\Enums\TransitMode;
use App\Services\StationService\FeedCatalog;
use App\Services\StationService\FeedDefinition;
use App\Services\StationService\FeedRole;
use App\Services\StationService\GtfsFeedStore;
use App\Support\SourceRights;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Flux déclarés (database/data/station_service_feeds.php), versions archivées et
 * vocabulaire de la desserte des gares.
 */
class FeedCatalogTest extends TestCase
{
    private static function catalog(): FeedCatalog
    {
        return FeedCatalog::fromFile(__DIR__.'/../../../database/data/station_service_feeds.php');
    }

    public function test_the_default_selection_leaves_out_feeds_read_on_demand(): void
    {
        $keys = array_map(fn (FeedDefinition $f): string => $f->key, self::catalog()->select([]));

        $this->assertContains('sncf', $keys);
        $this->assertContains('sncf-ete', $keys);
        $this->assertNotContains('idfm-tram', $keys, 'Licence Mobilités : sur demande');
        $this->assertNotContains('trenitalia', $keys);
    }

    public function test_an_unknown_feed_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        self::catalog()->select(['inconnu']);
    }

    public function test_corsica_is_covered_by_its_own_feed_only(): void
    {
        $this->assertSame(['2A', '2B'], self::catalog()->restrictedDepartments());
    }

    public function test_every_feed_cites_a_declared_provider(): void
    {
        $rights = new SourceRights(require __DIR__.'/../../../database/data/source_rights.php');
        foreach (self::catalog()->select(['sncf', 'sncf-ete', 'sncf-printemps', 'transilien', 'zou-trains', 'zou-express',
            'transdev-rail-sud', 'cfc', 'panoramique-domes', 'eurostar', 'renfe', 'trenitalia', 'idfm-tram']) as $feed) {
            $this->assertNotNull($rights->status($feed->provider), "{$feed->key} : fournisseur {$feed->provider} non déclaré");
            $this->assertTrue($rights->knows('station_services', $feed->key), "{$feed->key} : absent de source_rights (station_services)");
        }
    }

    public function test_an_archived_version_targets_the_last_past_day(): void
    {
        $feed = self::catalog()->select(['sncf-ete'])[0];

        $this->assertSame(20260615, $feed->targetDay(20261118));
        $this->assertSame(20250615, $feed->targetDay(20260301));
        $this->assertNull(self::catalog()->select(['sncf'])[0]->targetDay(20261118));
    }

    public function test_the_read_window_depends_on_the_role_of_the_feed(): void
    {
        [$current, $summer, $spring] = self::catalog()->select(['sncf', 'sncf-ete', 'sncf-printemps']);

        $this->assertSame([20261118, null], $current->readWindow(20261118));
        $this->assertSame([null, null], $summer->readWindow(20261118));
        $this->assertSame([20260415, 20260531], $spring->readWindow(20261118));
        $this->assertSame(FeedRole::Spring, $spring->reference);
    }

    public function test_the_closest_archived_version_is_chosen(): void
    {
        $csv = sys_get_temp_dir().'/vff-history-'.bin2hex(random_bytes(4)).'.csv';
        file_put_contents($csv, "resource_history_id,resource_id,permanent_url,payload,inserted_at\n"
            ."1,83582,https://h/a.zip,\"{\"\"x\"\":1}\",2026-06-01 00:10:00Z\n"
            ."2,83582,https://h/b.zip,{},2026-06-14 23:50:00Z\n"
            ."3,99999,https://h/c.zip,{},2026-06-15 00:00:00Z\n"
            ."4,83582,https://h/d.zip,{},2026-07-01 00:10:00Z\n");
        try {
            $this->assertSame('https://h/b.zip', GtfsFeedStore::closestVersion($csv, 83582, 20260615));
            $this->assertNull(GtfsFeedStore::closestVersion($csv, 1, 20260615));
        } finally {
            unlink($csv);
        }
    }

    public function test_route_types_map_to_train_tram_and_coach(): void
    {
        $this->assertSame(TransitMode::Rail, TransitMode::fromRouteType(2));
        $this->assertSame(TransitMode::Rail, TransitMode::fromRouteType(106));
        $this->assertSame(TransitMode::Tram, TransitMode::fromRouteType(0));
        $this->assertSame(TransitMode::Bus, TransitMode::fromRouteType(3));
        $this->assertSame(TransitMode::Bus, TransitMode::fromRouteType(712));
        $this->assertNull(TransitMode::fromRouteType(1), 'métro');
        $this->assertNull(TransitMode::fromRouteType(7), 'funiculaire');
    }

    public function test_feed_roles_feed_the_matching_evidence(): void
    {
        $this->assertSame('rail', FeedRole::Current->slot(TransitMode::Rail));
        $this->assertSame('bus', FeedRole::Current->slot(TransitMode::Bus));
        $this->assertSame('summer', FeedRole::Summer->slot(TransitMode::Rail));
        $this->assertNull(FeedRole::Summer->slot(TransitMode::Bus));
        $this->assertSame('touristTrains', FeedRole::Tourist->slot(TransitMode::Rail));
    }

    public function test_each_class_proposes_a_status_of_the_current_era(): void
    {
        $this->assertSame(StationStatus::Active, StationServiceClass::Periodic->proposedStatus(StationStatus::Declassified));
        $this->assertSame(StationStatus::Tourist, StationServiceClass::Tourist->proposedStatus(StationStatus::Active));
        $this->assertSame(StationStatus::Declassified, StationServiceClass::BusOnly->proposedStatus(StationStatus::Active));
        $this->assertSame(StationStatus::NoCommercialService, StationServiceClass::None->proposedStatus(StationStatus::NoCommercialService));
        $this->assertNull(StationServiceClass::Unknown->proposedStatus(StationStatus::Active));
    }
}
