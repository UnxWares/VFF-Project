<?php

namespace Tests\Unit\OpenData;

use App\Services\OpenData\OpenDataExporter;
use App\Services\OpenData\OpenDataLedger;
use App\Services\OpenData\OpenDataLicenceViolation;
use App\Services\OpenData\OpenDataRights;
use App\Support\SourceRights;
use PHPUnit\Framework\TestCase;

/**
 * Règle des droits des jeux ouverts (OpenDataRights) : licence ouverte écrite, usage commercial permis,
 * source déclarée ; contrôle final qui refuse un jeu contenant un fournisseur non autorisé ; registre réel.
 */
class OpenDataRightsTest extends TestCase
{
    private function rights(bool $commercial = true): OpenDataRights
    {
        return new OpenDataRights(new SourceRights([
            'providers' => [
                'ouvert' => ['nom' => 'Ouvert', 'statut' => 'sur', 'licence' => 'ODbL 1.0 (Open Database License)', 'preuve' => ['https://ouvert.example/licence']],
                'nc' => ['nom' => 'NC', 'statut' => 'sur', 'licence' => 'Réutilisation non commerciale libre et gratuite'],
                'oral' => ['nom' => 'Oral', 'statut' => 'incertain', 'licence' => 'Accord oral'],
                'ferme' => ['nom' => 'Fermé', 'statut' => 'interdit', 'licence' => 'Tous droits réservés'],
            ],
            'sources' => ['t' => ['a' => 'ouvert', 'b' => 'nc', 'c' => 'oral', 'd' => 'ferme']],
            'composites' => [],
        ]), $commercial);
    }

    public function test_only_written_open_licences_that_allow_commercial_use_enter(): void
    {
        $rights = $this->rights();
        $this->assertNull($rights->refusal('ouvert'));
        $this->assertSame(OpenDataRights::NON_COMMERCIAL, $rights->refusal('nc'));
        $this->assertSame(OpenDataRights::NOT_OPEN, $rights->refusal('oral'));
        $this->assertSame(OpenDataRights::NOT_OPEN, $rights->refusal('ferme'));
        $this->assertSame(OpenDataRights::UNKNOWN_SOURCE, $rights->refusal('absent'));
        $this->assertSame(OpenDataRights::UNKNOWN_SOURCE, $rights->refusal(null));
        $this->assertSame(['a'], $rights->allowedSources('t'));
        $this->assertSame("COALESCE(x.source IN ('a'), FALSE)", $rights->sqlAllowed('t', 'x.source'));
        $this->assertSame('FALSE', $rights->sqlAllowed('absente', 'x.source'));
    }

    public function test_a_non_commercial_source_enters_a_dataset_declared_non_commercial(): void
    {
        $this->assertTrue($this->rights(false)->allows('nc'));
        $this->assertFalse($this->rights(false)->allows('oral'));
    }

    public function test_the_final_check_refuses_a_dataset_that_cites_an_unauthorised_provider(): void
    {
        $rights = $this->rights();
        $rights->assertAllowed('jeu', ['ouvert']);
        $this->expectException(OpenDataLicenceViolation::class);
        $this->expectExceptionMessage('oral');
        $rights->assertAllowed('jeu', ['ouvert', 'oral']);
    }

    public function test_attribution_gives_name_short_licence_and_proof(): void
    {
        $this->assertSame([['key' => 'ouvert', 'name' => 'Ouvert', 'licence' => 'ODbL 1.0', 'url' => 'https://ouvert.example/licence']],
            $this->rights()->attribution(['ouvert']));
        $this->assertSame('Licence Ouverte 2.0', OpenDataRights::shortLicence('ign', 'Licence Ouverte 2.0 (Etalab), données publiques'));
        $this->assertSame('CC0 1.0', OpenDataRights::shortLicence('wikidata', 'CC0 1.0'));
        $this->assertSame('ODbL 1.0', OpenDataRights::shortLicence('vff', 'Travail propre de VFF ; contributions sous CC BY-SA 4.0'));
    }

    public function test_the_ledger_counts_by_item_action_and_reason(): void
    {
        $ledger = new OpenDataLedger;
        $ledger->withhold('sections.geometry', OpenDataRights::NOT_OPEN);
        $ledger->withhold('sections.geometry', OpenDataRights::NOT_OPEN, OpenDataLedger::REPLACED, 3);
        $ledger->withhold('events', OpenDataRights::UNKNOWN_SOURCE);
        $ledger->cite('osm');
        $ledger->cite(null);
        $ledger->cite('ign');
        $this->assertSame(['ign', 'osm'], $ledger->providers());
        $this->assertSame(2, $ledger->total(OpenDataLedger::REMOVED));
        $this->assertSame(3, $ledger->total(OpenDataLedger::REPLACED));
        $this->assertSame(['events', 'sections.geometry'], array_keys($ledger->withheld()));
    }

    /** Registre réel : les sources de remplacement sont libres, OpenHistoricalMap n'en fait pas partie, les sources non libres connues sont refusées. */
    public function test_the_real_register_backs_the_replacement_sources(): void
    {
        $rights = new OpenDataRights(new SourceRights(require dirname(__DIR__, 3).'/database/data/source_rights.php'));
        foreach (OpenDataExporter::REPLACEMENT_TRACE_SOURCES as $source) {
            $this->assertTrue($rights->allowsSource('source_traces', $source), $source);
        }
        foreach (OpenDataExporter::REPLACEMENT_STATION_SOURCES as $source) {
            $this->assertTrue($rights->allowsSource('station_sources', $source), $source);
        }
        $this->assertNotContains('ohm', OpenDataExporter::REPLACEMENT_TRACE_SOURCES);
        $this->assertNotContains('contrib', OpenDataExporter::REPLACEMENT_TRACE_SOURCES);
        foreach (['archeoferroviaire', 'lignes-oubliees', 'argentanwebferro', 'retronews', 'persee', 'pireneas', 'idfm'] as $provider) {
            $this->assertFalse($rights->allows($provider), $provider);
        }
        $this->assertSame(OpenDataRights::NON_COMMERCIAL, $rights->refusal('gallica'));
        $this->assertFalse($rights->allowsSource('line_segments', 'archeoferroviaire'));
        $this->assertFalse($rights->allowsSource('line_segments.gauge_source', 'archeo'));
        $this->assertTrue($rights->allowsSource('stations.fields', 'vff:eras'));
        $this->assertTrue($rights->allowsSource('line_events', 'manual', 'https://www.legifrance.gouv.fr/jorf/id/1'));
        $this->assertFalse($rights->allowsSource('line_events', 'manual', 'https://www.lignes-oubliees.com/x'));
    }
}
