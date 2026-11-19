<?php

namespace Tests\Unit\Api;

use App\Rules\PaginationCursor;
use App\Rules\PartialDate;
use App\Services\Api\LineEventQuery;
use App\Services\Api\MediaQuery;
use App\Services\Api\Provenance;
use App\Services\Api\StationEventQuery;
use App\Support\SourceRights;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Briques de l'API publique sans base de données : dates partielles, curseurs, provenance, filtres canoniques.
 */
class ApiSupportTest extends TestCase
{
    /** @return array<string, array{string|int, bool}> */
    public static function partialDates(): array
    {
        return [
            'année' => ['1930', true],
            'année entière en entier' => [1930, true],
            'date' => ['1930-06-15', true],
            'bissextile' => ['1932-02-29', true],
            'jour inexistant' => ['1930-02-29', false],
            'mois inexistant' => ['1930-13-01', false],
            'année sur deux chiffres' => ['30', false],
            'mois seul' => ['1930-06', false],
            'texte' => ['hier', false],
            'année zéro' => ['0000', false],
            'espace' => [' 1930', false],
            'heure en trop' => ['1930-06-15 10:00', false],
        ];
    }

    #[DataProvider('partialDates')]
    public function test_partial_date_validation(string|int $value, bool $valid): void
    {
        $this->assertSame($valid, Validator::make(['from' => $value], ['from' => [new PartialDate]])->passes(), (string) $value);
    }

    public function test_partial_date_bounds(): void
    {
        $this->assertSame('1930-01-01', PartialDate::bound('1930', false));
        $this->assertSame('1930-12-31', PartialDate::bound('1930', true));
        $this->assertSame('1930-06-15', PartialDate::bound('1930-06-15', false));
        $this->assertSame('1930-06-15', PartialDate::bound('1930-06-15', true));
        $this->assertSame('1930-12-31', PartialDate::bound(1930, true));
    }

    public function test_pagination_cursor_requires_the_columns_of_its_list(): void
    {
        $encode = fn (array $parameters): string => rtrim(strtr(base64_encode(json_encode($parameters + ['_pointsToNextItems' => true])), '+/', '-_'), '=');
        $check = fn (mixed $value, array $columns): bool => Validator::make(['cursor' => $value], ['cursor' => [new PaginationCursor($columns)]])->passes();

        $this->assertTrue($check($encode(['media.id' => 5]), ['media.id']));
        $this->assertFalse($check($encode(['media.id' => 5]), ['line_events.event_date', 'line_events.id']));
        $this->assertFalse($check('???', ['media.id']));
        $this->assertFalse($check(['media.id' => 5], ['media.id']));
        $this->assertTrue($check($encode(['media.id' => null]), ['media.id']), 'une valeur nulle reste une colonne présente');
    }

    public function test_provenance_gives_the_provider_and_its_licence(): void
    {
        $provenance = new Provenance(new SourceRights(self::registry()));

        $this->assertSame(
            ['provider' => 'ohm', 'name' => 'OpenHistoricalMap', 'license' => 'CC0 1.0', 'license_status' => 'verified'],
            $provenance->for('line_events', 'ohm'),
        );
        $this->assertSame('uncertain', $provenance->for('line_events', 'archeo')['license_status']);
        $this->assertSame('forbidden', $provenance->for('line_events', 'interdite')['license_status']);
        // Source composite : le domaine du lien cité désigne le fournisseur.
        $this->assertSame('ohm', $provenance->for('line_events', 'manual', 'https://www.openhistoricalmap.org/way/1')['provider']);
        $this->assertSame('vff', $provenance->for('line_events', 'manual', 'https://exemple.test/page')['provider']);
        $this->assertSame(
            ['provider' => null, 'name' => null, 'license' => null, 'license_status' => null],
            $provenance->for('line_events', 'inconnue'),
        );
        $this->assertSame(['provider' => null, 'name' => null, 'license' => null, 'license_status' => null], $provenance->for('line_events', null));
    }

    public function test_filters_are_normalised(): void
    {
        $this->assertSame('2A', LineEventQuery::normalize(['department' => ' 2a '])['department']);
        $this->assertSame(true, MediaQuery::normalize(['commercial_ok' => 'true'])['commercial_ok']);
        $this->assertSame(false, MediaQuery::normalize(['commercial_ok' => 'FALSE'])['commercial_ok']);
        $this->assertSame('maybe', MediaQuery::normalize(['commercial_ok' => 'maybe'])['commercial_ok']);
        $this->assertSame(true, StationEventQuery::normalize(['inferred' => 'true'])['inferred']);
        $this->assertSame('1', StationEventQuery::normalize(['inferred' => '1'])['inferred']);
    }

    /** @return array<string, mixed> */
    private static function registry(): array
    {
        $provider = fn (string $nom, string $licence, string $statut, array $domaines = []) => compact('nom', 'licence', 'statut', 'domaines');

        return [
            'providers' => [
                'ohm' => $provider('OpenHistoricalMap', 'CC0 1.0', 'sur', ['openhistoricalmap.org']),
                'archeo' => $provider('Source tierce', 'Conditions particulières', 'incertain'),
                'interdite' => $provider('Source fermée', 'Tous droits réservés', 'interdit'),
                'vff' => $provider('VFF', 'CC BY-SA 4.0', 'sur'),
            ],
            'sources' => ['line_events' => ['ohm' => 'ohm', 'archeo' => 'archeo', 'interdite' => 'interdite']],
            'composites' => ['line_events' => ['manual' => 'vff']],
        ];
    }
}
