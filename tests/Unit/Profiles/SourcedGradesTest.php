<?php

namespace Tests\Unit\Profiles;

use App\Services\Profiles\ProfileStats;
use App\Services\Profiles\RackRailways;
use App\Services\Profiles\SncfGrades;
use App\Services\Profiles\SourcedGrades;
use Tests\TestCase;

/**
 * Liste versionnée des déclivités sourcées (database/data/sourced_grades.php) : entrées complètes,
 * sourcées par le permalien d'une révision de Wikipédia, plausibles pour une ligne à adhérence, et
 * données seulement à la place d'une mesure non publiée.
 */
class SourcedGradesTest extends TestCase
{
    public function test_every_entry_is_complete_sourced_and_plausible(): void
    {
        $all = SourcedGrades::all();

        $this->assertGreaterThan(400, count($all));
        foreach ($all as $slug => $entry) {
            $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $slug);
            $this->assertGreaterThanOrEqual(1, $entry['max_grade_permil'], $slug);
            $this->assertLessThanOrEqual(ProfileStats::MAX_PLAUSIBLE_GRADE * 1000, $entry['max_grade_permil'], $slug);
            // Chaque entrée porte son fournisseur et la licence de ce fournisseur.
            $this->assertArrayHasKey($entry['provider'], SourcedGrades::PROVIDERS, $slug);
            $this->assertSame(SourcedGrades::PROVIDERS[$entry['provider']]['license'], $entry['license'], $slug);
            if ($entry['provider'] === 'wikipedia') {
                $this->assertMatchesRegularExpression('#^https://fr\.wikipedia\.org/w/index\.php\?title=[^&]+&oldid=\d+$#', $entry['source_url'], $slug);
                // Champs de l'infobox lus, ou phrase du texte de l'article (vff:import-wikipedia-grades).
                $this->assertMatchesRegularExpression('/^(pente = |texte : « )/u', $entry['quote'], $slug);
            } else {
                $this->assertSame('SNCF Réseau', $entry['attribution'] ?? null, $slug);
                $this->assertMatchesRegularExpression('#^https://data\.sncf\.com/explore/dataset/caracteristique-des-voies-et-declivite/table/\?refine\.code_ligne=\d{6}$#', $entry['source_url'], $slug);
                $this->assertMatchesRegularExpression('/^(RAMPE|PENTE) /', $entry['quote'], $slug);
                $this->assertGreaterThanOrEqual(SncfGrades::MIN_COVERAGE, $entry['covered_share'] ?? 0, $slug);
                $this->assertLessThanOrEqual(SncfGrades::MAX_PLAIN_PERMIL, $entry['max_grade_permil'], $slug);
            }
            $this->assertNotSame('', $entry['source_title'], $slug);
            $this->assertStringNotContainsString('<ref', $entry['quote'], $slug);
            $this->assertStringNotContainsString('—', $entry['quote'].$entry['source_title']);
            // Une ligne à crémaillère a sa propre fiche (rack_railways.php).
            $this->assertFalse(RackRailways::isRack($slug), $slug);
        }
    }

    public function test_the_sourced_grade_is_given_only_when_the_measured_one_is_withheld(): void
    {
        foreach (['terrain_only', 'tunnel', 'interpolated'] as $reason) {
            $grade = SourcedGrades::present('rfn-405000', $reason);
            $this->assertNotNull($grade, $reason);
            $this->assertSame(10.0, $grade['max_grade_permil']);
            $this->assertSame('wikipedia', $grade['source']['provider']);
            $this->assertSame('CC BY-SA 4.0', $grade['source']['license']);
            $this->assertSame('Wikipédia', $grade['source']['attribution']);
            $this->assertNull($grade['covered_share']);
            $this->assertStringContainsString('oldid=240174253', $grade['source']['url']);
        }
        // Mesure publiée, parcours trop court (motif nul) ou crémaillère : rien.
        $this->assertNull(SourcedGrades::present('rfn-405000', null));
        $this->assertNull(SourcedGrades::present('rfn-405000', 'rack'));
        $this->assertNull(SourcedGrades::present('ligne-inconnue', 'terrain_only'));
    }

    public function test_the_source_is_compared_to_a_published_measure_but_never_replaces_it(): void
    {
        // Argentan - Granville : 10 ‰ selon l'infobox ; mesure publiée de 12,34 ‰.
        $compared = SourcedGrades::compare('rfn-405000', 12.34, null);
        $this->assertNotNull($compared);
        $this->assertSame(10.0, $compared['max_grade_permil']);
        $this->assertSame(2.3, $compared['difference_permil']);
        $this->assertSame('wikipedia', $compared['source']['provider']);
        $this->assertSame('Wikipédia', $compared['source']['attribution']);
        // Source plus forte que la mesure : écart négatif.
        $this->assertSame(-1.5, SourcedGrades::compare('rfn-405000', 8.5, null)['difference_permil'] ?? null);

        // Mesure non publiée (la source passe dans `sourced_grade`), absente, crémaillère, ligne inconnue : rien.
        $this->assertNull(SourcedGrades::compare('rfn-405000', null, 'terrain_only'));
        $this->assertNull(SourcedGrades::compare('rfn-405000', null, null));
        $this->assertNull(SourcedGrades::compare('rfn-405000', 12.0, 'rack'));
        $this->assertNull(SourcedGrades::compare('ligne-inconnue', 12.0, null));
    }

    public function test_both_providers_are_present_and_sncf_serves_its_licence_and_share(): void
    {
        $providers = array_count_values(array_column(SourcedGrades::all(), 'provider'));
        $this->assertGreaterThanOrEqual(450, $providers['wikipedia'] ?? 0);
        $this->assertGreaterThan(0, $providers['sncf'] ?? 0);

        // Ligne de Mézidon à Trouville-Deauville : la SNCF (36,2 ‰) dépasse Wikipédia (25 ‰) et reste cohérente avec le terrain.
        $grade = SourcedGrades::present('rfn-379000', 'terrain_only');
        $this->assertNotNull($grade);
        $this->assertSame('sncf', $grade['source']['provider']);
        $this->assertSame('ODbL 1.0', $grade['source']['license']);
        $this->assertSame('SNCF Réseau', $grade['source']['attribution']);
        $this->assertStringEndsWith('refine.code_ligne=379000', $grade['source']['url']);
        $this->assertIsFloat($grade['covered_share']);
        $this->assertGreaterThan(0.1, $grade['covered_share']);
        $this->assertNull(SourcedGrades::present('rfn-379000', null));
    }

    public function test_the_aberrant_sncf_values_are_not_in_the_files(): void
    {
        $all = SourcedGrades::all();
        // 90 ‰ sur Hirson - Amagne-Lucquy, 96 ‰ sur Lutterbach - Rixheim : écartés, Wikipédia reste.
        $this->assertSame('wikipedia', $all['rfn-212000']['provider'] ?? 'wikipedia');
        $this->assertArrayNotHasKey('rfn-125000', array_filter($all, fn (array $e): bool => $e['provider'] === 'sncf'));
    }

    public function test_decimal_values_are_kept(): void
    {
        $decimals = array_filter(SourcedGrades::all(), fn (array $e): bool => is_float($e['max_grade_permil']));

        $this->assertNotEmpty($decimals);
        $this->assertSame(7.8, SourcedGrades::present('archeo-ligne-de-bondy-a-aulnay-sous-bois', 'terrain_only')['max_grade_permil'] ?? null);
    }
}
