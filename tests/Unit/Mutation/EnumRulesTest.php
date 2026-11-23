<?php

namespace Tests\Unit\Mutation;

use App\Enums\AccidentDatePrecision;
use App\Enums\ApiAccessProfile;
use App\Enums\ContentLicense;
use App\Enums\FactDatePrecision;
use App\Enums\LevelCrossingGroup;
use App\Enums\SegmentStatus;
use App\Enums\ServicePattern;
use App\Enums\SignallingFamily;
use App\Enums\SignallingRole;
use App\Enums\StationServiceClass;
use App\Enums\StationStatus;
use App\Enums\TransitMode;
use App\Enums\WebhookEvent;
use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Règles portées par les énumérations, cas par cas : précisions de date, modes GTFS, statuts SNCF,
 * familles de signalisation, desserte des gares. Écrit pour les tests de mutation
 * (tests/Mutation/Critical) : chaque branche d'un `match` a son cas, et une étiquette qui porte deux
 * mots-clés suit la première règle qui s'applique.
 */
class EnumRulesTest extends TestCase
{
    public function test_wikidata_precisions_of_an_accident_date(): void
    {
        $this->assertSame(AccidentDatePrecision::Day, AccidentDatePrecision::fromWikidata(14));
        $this->assertSame(AccidentDatePrecision::Day, AccidentDatePrecision::fromWikidata(11));
        $this->assertSame(AccidentDatePrecision::Month, AccidentDatePrecision::fromWikidata(10));
        $this->assertSame(AccidentDatePrecision::Year, AccidentDatePrecision::fromWikidata(9));
        $this->assertNull(AccidentDatePrecision::fromWikidata(8), 'décennie');
        $this->assertNull(AccidentDatePrecision::fromWikidata(7), 'siècle');
    }

    public function test_accident_date_precisions_are_ranked_from_year_to_day(): void
    {
        $this->assertSame([1, 2, 3], [
            AccidentDatePrecision::Year->rank(),
            AccidentDatePrecision::Month->rank(),
            AccidentDatePrecision::Day->rank(),
        ]);
    }

    public function test_only_a_period_has_no_line_event_precision(): void
    {
        $this->assertSame('day', FactDatePrecision::Day->lineEventPrecision());
        $this->assertSame('month', FactDatePrecision::Month->lineEventPrecision());
        $this->assertSame('year', FactDatePrecision::Year->lineEventPrecision());
        $this->assertNull(FactDatePrecision::Range->lineEventPrecision());
    }

    /** @return iterable<string, array{int, ?TransitMode}> */
    public static function routeTypes(): iterable
    {
        foreach ([2 => 'rail', 100 => 'rail', 117 => 'rail', 0 => 'tram', 900 => 'tram', 906 => 'tram',
            3 => 'bus', 200 => 'bus', 209 => 'bus', 700 => 'bus', 717 => 'bus'] as $type => $mode) {
            yield "type {$type}" => [$type, TransitMode::from($mode)];
        }
        foreach ([1, 4, 99, 118, 199, 210, 699, 718, 899, 907] as $type) {
            yield "type {$type} ignoré" => [$type, null];
        }
    }

    #[DataProvider('routeTypes')]
    public function test_gtfs_route_types(int $type, ?TransitMode $mode): void
    {
        $this->assertSame($mode, TransitMode::fromRouteType($type));
    }

    /** @return iterable<string, array{?string, SegmentStatus}> */
    public static function sncfLabels(): iterable
    {
        yield 'en travaux' => ['En travaux', SegmentStatus::InService];
        yield 'code S9A3' => ['S9A3', SegmentStatus::InService];
        yield 'exploitée' => ['Exploitée', SegmentStatus::InService];
        yield 'majuscules accentuées' => ['FERMÉE ET DÉPOSÉE', SegmentStatus::Dismantled];
        yield 'transférée' => ['Transférée en voie de port', SegmentStatus::ServiceTrack];
        yield 'voie de service' => ['Voie de service', SegmentStatus::ServiceTrack];
        yield 'fermée pour travaux' => ['Fermée pour travaux', SegmentStatus::InService];
        yield 'S9A3 fermée' => ['S9A3 fermée', SegmentStatus::InService];
        yield 'exploitée, voie fermée' => ['Exploitée (voie 2 fermée)', SegmentStatus::InService];
        yield 'nul' => [null, SegmentStatus::InService];
        yield 'espaces' => ['   ', SegmentStatus::InService];
    }

    #[DataProvider('sncfLabels')]
    public function test_sncf_line_status_labels(?string $label, SegmentStatus $status): void
    {
        $this->assertSame($status, SegmentStatus::fromSncfLibelle($label));
    }

    /** @return iterable<string, array{string, ?int}> */
    public static function levelCrossingLabels(): iterable
    {
        yield 'classe' => ['CLASSE 17', 17];
        yield 'espaces autour' => ['  CLASSE 4 ', 4];
        yield 'préfixe' => ['SOUS-CLASSE 17', null];
        yield 'suffixe' => ['CLASSE 17B', null];
        yield 'trois chiffres' => ['CLASSE 170', null];
    }

    #[DataProvider('levelCrossingLabels')]
    public function test_level_crossing_class_labels(string $label, ?int $class): void
    {
        $this->assertSame($class, LevelCrossingGroup::parseClass($label));
    }

    /** @return iterable<string, array{string, ?SignallingRole}> */
    public static function signallingSystems(): iterable
    {
        foreach (['BAL', 'BAPR', 'BM', 'CT'] as $system) {
            yield $system => [$system, SignallingRole::Block];
        }
        foreach (['TVM', 'TVM-430', 'ETCS', 'ETCS_L2'] as $system) {
            yield $system => [$system, SignallingRole::Cab];
        }
        yield 'KVB' => ['KVB', SignallingRole::SpeedControl];
        yield 'CC' => ['CC', SignallingRole::RemoteControl];
        yield 'CCVU n’est pas un code' => ['CCVU', null];
        yield 'BAL sans préfixe exact' => ['BALX', null];
    }

    #[DataProvider('signallingSystems')]
    public function test_role_of_each_signalling_system(string $system, ?SignallingRole $role): void
    {
        $this->assertSame($role, SignallingRole::forSystem($system));
    }

    public function test_licences_accepted_for_an_uploaded_image(): void
    {
        $this->assertSame(['public_domain', 'cc0-1.0', 'cc-by-4.0', 'cc-by-3.0', 'cc-by-2.5', 'cc-by-2.0', 'cc-by-sa-4.0',
            'cc-by-sa-3.0', 'cc-by-sa-2.5', 'cc-by-sa-2.0', 'etalab-2.0'], ContentLicense::mediaValues());
        $this->assertSame(array_values(array_filter(ContentLicense::cases(), fn (ContentLicense $l): bool => ! in_array($l, [ContentLicense::AllRightsReserved, ContentLicense::Unknown], true))),
            ContentLicense::forMedia());
        $this->assertFalse(ContentLicense::AllRightsReserved->allowsMedia());
        $this->assertTrue(ContentLicense::Etalab2->allowsMedia());
    }

    /** @return iterable<string, array{ContentLicense, string, ?string, string, bool}> */
    public static function licences(): iterable
    {
        yield 'domaine public' => [ContentLicense::PublicDomain, 'Public domain', null, 'public_domain', false];
        yield 'CC0' => [ContentLicense::Cc0, 'CC0', 'https://creativecommons.org/publicdomain/zero/1.0/', 'public_domain', false];
        yield 'CC BY' => [ContentLicense::CcBy4, 'CC BY 4.0', 'https://creativecommons.org/licenses/by/4.0/', 'open_license', true];
        yield 'CC BY-SA' => [ContentLicense::CcBySa4, 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0/', 'open_license', true];
        yield 'CC BY 2.5' => [ContentLicense::CcBy25, 'CC BY 2.5', 'https://creativecommons.org/licenses/by/2.5/', 'open_license', true];
        yield 'CC BY-SA 3.0' => [ContentLicense::CcBySa3, 'CC BY-SA 3.0', 'https://creativecommons.org/licenses/by-sa/3.0/', 'open_license', true];
        yield 'CC BY-SA 2.0' => [ContentLicense::CcBySa2, 'CC BY-SA 2.0', 'https://creativecommons.org/licenses/by-sa/2.0/', 'open_license', true];
        yield 'Licence Ouverte' => [ContentLicense::Etalab2, 'Licence Ouverte 2.0', 'https://www.etalab.gouv.fr/licence-ouverte-open-licence/', 'open_license', true];
        yield 'tous droits réservés' => [ContentLicense::AllRightsReserved, 'Tous droits réservés', null, 'unknown', false];
        yield 'inconnue' => [ContentLicense::Unknown, 'Licence inconnue', null, 'unknown', false];
    }

    #[DataProvider('licences')]
    public function test_licence_names_links_rights_and_attribution(ContentLicense $licence, string $label, ?string $url, string $rights, bool $attribution): void
    {
        $this->assertSame([$label, $url, $rights, $attribution], [$licence->label(), $licence->url(), $licence->rightsStatus(), $licence->attributionRequired()]);
    }

    public function test_licence_families_and_versions(): void
    {
        $this->assertSame(['public_domain', null], [ContentLicense::PublicDomain->family(), ContentLicense::PublicDomain->version()]);
        $this->assertSame(['cc0', '1.0'], [ContentLicense::Cc0->family(), ContentLicense::Cc0->version()]);
        $this->assertSame(['cc-by', '2.5'], [ContentLicense::CcBy25->family(), ContentLicense::CcBy25->version()]);
        $this->assertSame(['cc-by-sa', '3.0', true], [ContentLicense::CcBySa3->family(), ContentLicense::CcBySa3->version(), ContentLicense::CcBySa3->shareAlike()]);
        $this->assertFalse(ContentLicense::CcBy2->shareAlike());
        $this->assertSame(['etalab', '2.0'], [ContentLicense::Etalab2->family(), ContentLicense::Etalab2->version()]);
        $this->assertSame([null, null], [ContentLicense::AllRightsReserved->version(), ContentLicense::Unknown->version()]);
    }

    public function test_cab_signalling_without_version_or_level(): void
    {
        $this->assertSame(SignallingFamily::Cab, SignallingFamily::fromCode('TVM'));
        $this->assertSame(SignallingFamily::Cab, SignallingFamily::fromCode('ETCS'));
        $this->assertSame(SignallingRole::Cab, SignallingRole::forSystem('TVM'));
        $this->assertSame(SignallingRole::Cab, SignallingRole::forSystem('ETCS'));
        $this->assertNull(SignallingRole::forSystem('VUT'));
    }

    public function test_regular_service_patterns(): void
    {
        $regular = array_values(array_filter(ServicePattern::cases(), fn (ServicePattern $p): bool => $p->isRegular()));

        $this->assertSame([ServicePattern::Daily, ServicePattern::Weekdays, ServicePattern::Weekend, ServicePattern::SomeDays], $regular);
    }

    public function test_status_proposed_by_the_station_service(): void
    {
        $this->assertSame(StationStatus::Active, StationServiceClass::Regular->proposedStatus(StationStatus::Declassified));
        $this->assertSame(StationStatus::Active, StationServiceClass::Periodic->proposedStatus(StationStatus::Declassified));
        $this->assertSame(StationStatus::Tourist, StationServiceClass::Tourist->proposedStatus(StationStatus::Active));
        foreach ([StationServiceClass::Velorail, StationServiceClass::BusOnly, StationServiceClass::None] as $class) {
            $this->assertSame(StationStatus::Declassified, $class->proposedStatus(StationStatus::Active), $class->value);
            $this->assertSame(StationStatus::NoCommercialService, $class->proposedStatus(StationStatus::NoCommercialService), $class->value);
        }
        $this->assertNull(StationServiceClass::Unknown->proposedStatus(StationStatus::Active));
    }

    public function test_api_access_profiles_and_their_rate_limits(): void
    {
        $this->assertSame(ApiAccessProfile::Anonymous, ApiAccessProfile::for(null));
        $this->assertSame(ApiAccessProfile::Member, ApiAccessProfile::for(new User));
        $this->assertSame(ApiAccessProfile::Partner, ApiAccessProfile::for((new User)->forceFill(['api_partner_at' => now()])));

        // Une limite nulle laisse passer une requête par minute, jamais zéro ; une valeur lue d'une
        // variable d'environnement (une chaîne) est rendue en entier.
        $perMinute = [];
        foreach ([1, 0, '120'] as $limit) {
            config(['api.rate_limits.member' => $limit]);
            $perMinute[] = ApiAccessProfile::Member->perMinute();
        }
        $this->assertSame([1, 1, 120], $perMinute);
    }

    public function test_webhook_event_values_are_strings(): void
    {
        $this->assertSame(['contribution.applied', 'current_era.refreshed', 'accident.published', 'material.published'], WebhookEvent::values());
    }
}
