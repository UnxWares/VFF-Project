<?php

namespace Tests\Unit;

use App\Enums\SegmentStatus;
use App\Services\OsmImporter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Verrouille le cœur de la classification (audit 2026-05) : mapping statut SNCF
 * et détection heritage. Logique PURE (pas de DB → compatible sqlite :memory:).
 */
class ClassificationTest extends TestCase
{
    #[DataProvider('libelleProvider')]
    public function test_segment_status_from_sncf_libelle(string $libelle, SegmentStatus $expected): void
    {
        $this->assertSame($expected, SegmentStatus::fromSncfLibelle($libelle));
    }

    public static function libelleProvider(): array
    {
        return [
            'Exploitée → in_service' => ['Exploitée', SegmentStatus::InService],
            'En travaux → in_service' => ['S9A3 - Ligne en travaux', SegmentStatus::InService],
            'Neutralisée → abandoned' => ['Neutralisée', SegmentStatus::Abandoned],
            'Fermée → abandoned' => ['Fermée', SegmentStatus::Abandoned],
            // « Fermée NON déposée » = rails en place → abandoned (pas dismantled)
            'Fermée non déposée → abandoned' => ['Fermée non déposée (Plus utilisable)', SegmentStatus::Abandoned],
            // « Fermée ET déposée » = dépose avérée → dismantled
            'Fermée et déposée → dismantled' => ['Fermée et déposée (Plus utilisable)', SegmentStatus::Dismantled],
            // Déclassement juridique ≠ dépose physique → abandoned
            'Déclassée vendue → abandoned' => ['Déclassée vendue', SegmentStatus::Abandoned],
            'Retranchée → abandoned' => ['Retranchée (Plus utilisable)', SegmentStatus::Abandoned],
            'Transférée en voie de service → service_track' => ['Transférée en voie de service', SegmentStatus::ServiceTrack],
        ];
    }

    #[DataProvider('heritageProvider')]
    public function test_is_heritage_operator(?string $operator, bool $expected): void
    {
        $this->assertSame($expected, OsmImporter::isHeritageOperator($operator));
    }

    public static function heritageProvider(): array
    {
        return [
            // Faux positifs corrigés : concession LGV / étranger / AOT / SNCF = PAS heritage
            'LISEA (concession LGV SEA)' => ['LISEA', false],
            'SNCF Réseau' => ['SNCF Réseau', false],
            'SBB (étranger)' => ['SBB', false],
            'Région Occitanie (AOT)' => ['Région Occitanie', false],
            'null' => [null, false],
            // Vrais chemins de fer touristiques / vélorails
            'Train du Bas-Berry' => ['Train du Bas-Berry', true],
            'Vélorail des Fades' => ['Vélorail électrique des Fades', true],
            'Train à vapeur' => ['Train à vapeur des Cévennes', true],
            'Chemin de fer touristique' => ['Chemin de fer touristique du Vivarais', true],
        ];
    }

    public function test_active_statuses(): void
    {
        $this->assertTrue(SegmentStatus::InService->isActive());
        $this->assertTrue(SegmentStatus::SingleTrack->isActive());
        $this->assertFalse(SegmentStatus::Abandoned->isActive());
        $this->assertFalse(SegmentStatus::ServiceTrack->isActive());
        $this->assertFalse(SegmentStatus::Dismantled->isActive());
    }
}
