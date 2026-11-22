<?php

namespace Tests\Unit;

use App\Console\Commands\SourceRightsReport;
use App\Support\SourceRights;
use PHPUnit\Framework\TestCase;

/**
 * Registre des droits des sources (database/data/source_rights.php) et règles de
 * App\Support\SourceRights, sans base : une règle par test.
 */
class SourceRightsTest extends TestCase
{
    /** @return array<string, mixed> */
    private static function registry(): array
    {
        return require __DIR__.'/../../database/data/source_rights.php';
    }

    private static function rights(): SourceRights
    {
        return new SourceRights(self::registry());
    }

    /** @return array<string, mixed> petit registre de test */
    private static function fixture(): array
    {
        return [
            'providers' => [
                'libre' => ['nom' => 'Libre', 'statut' => 'sur', 'licence' => 'CC0', 'accord' => null, 'preuve' => ['https://exemple.org/licence'],
                    'obligations' => 'aucune', 'domaines' => ['libre.org']],
                'douteux' => ['nom' => 'Douteux', 'statut' => 'incertain', 'licence' => 'accord oral', 'accord' => 'oral', 'preuve' => ['https://douteux.fr/mentions'],
                    'obligations' => 'crédit', 'domaines' => ['douteux.fr']],
                'maison' => ['nom' => 'Maison', 'statut' => 'sur', 'licence' => 'travail propre', 'accord' => null, 'preuve' => ['https://maison.test/legal'],
                    'obligations' => 'lien cité', 'domaines' => []],
            ],
            'sources' => ['line_events' => ['libre' => 'libre', 'douteux' => 'douteux']],
            'composites' => ['line_events' => ['manual' => 'maison']],
        ];
    }

    public function test_the_shipped_registry_has_no_inconsistency(): void
    {
        $this->assertSame([], SourceRightsReport::registryErrors(self::rights()));
    }

    public function test_every_provider_proves_its_licence_with_a_link(): void
    {
        foreach (self::registry()['providers'] as $key => $p) {
            $this->assertNotEmpty($p['preuve'] ?? [], "{$key} : preuve manquante");
            foreach ($p['preuve'] as $url) {
                $this->assertMatchesRegularExpression('#^https?://\S+$#', $url, "{$key} : lien de preuve invalide");
            }
            $this->assertNotSame('', trim((string) ($p['licence'] ?? '')), "{$key} : licence vide");
        }
    }

    /**
     * Une preuve lue chez un fournisseur (ses domaines) ne se range pas dans l'entrée d'un autre :
     * les liens du RGE ALTI s'étaient glissés dans l'entrée d'une source tous droits réservés.
     * Exception explicite : les CGU de « Ma carte » (IGN), qui établissent qu'une carte tracée sur ce
     * service appartient à son auteur, servent de preuve à l'entrée de cet auteur ; la page de Commons
     * sur le droit d'auteur en France fonde le droit de courte citation des sites tiers. Les portails qui
     * publient les jeux de plusieurs producteurs (transport.data.gouv.fr) ne désignent personne.
     */
    public function test_a_proof_link_belongs_to_the_provider_that_owns_its_domain(): void
    {
        $rights = self::rights();
        $allowed = [
            'archeoferroviaire' => ['https://macarte.ign.fr/cgu'],
            'tiers-cites' => ['https://commons.wikimedia.org/wiki/Commons:Copyright_rules_by_territory/France/fr'],
            // Pages de réutilisation des éditions anglaise et allemande de Wikipédia : leur licence est celle de chaque édition.
            'wikipedia-en' => ['https://en.wikipedia.org/wiki/Wikipedia:Reusing_Wikipedia_content'],
            'wikipedia-de' => ['https://de.wikipedia.org/wiki/Wikipedia:Weiternutzung'],
            // Conditions de l'Assemblée nationale (documents cités par le contexte des accidents) : le domaine
            // assemblee-nationale.fr reste à tiers-cites pour les corrections de lignes déjà sourcées.
            'assemblee-nationale' => [
                'https://data.assemblee-nationale.fr/licence-ouverte-open-licence',
                'https://www.assemblee-nationale.fr/dyn/langues/welcome-to-the-english-website-of-the-french-national-assembly/contact',
            ],
        ];
        $portals = ['transport.data.gouv.fr'];
        foreach (self::registry()['providers'] as $key => $p) {
            foreach ($p['preuve'] as $url) {
                $owner = $rights->providerForUrl($url);
                if (in_array(parse_url($url, PHP_URL_HOST), $portals, true)) {
                    continue;
                }
                if ($owner === null || $owner === $key || in_array($url, $allowed[$key] ?? [], true)) {
                    continue;
                }
                $this->fail("{$key} : preuve {$url} rangée chez le mauvais fournisseur (domaine de {$owner})");
            }
        }
        $this->addToAssertionCount(1);
    }

    public function test_an_oral_agreement_is_never_safe(): void
    {
        $registry = self::fixture();
        $registry['providers']['douteux']['statut'] = 'sur';
        $errors = SourceRightsReport::registryErrors(new SourceRights($registry));

        $this->assertContains("fournisseur douteux : SÛR sur la foi d'un accord oral", $errors);
        foreach (self::registry()['providers'] as $key => $p) {
            if (($p['accord'] ?? null) === 'oral') {
                $this->assertNotSame(SourceRights::SAFE, $p['statut'], "{$key} : accord oral classé sûr");
            }
        }
    }

    public function test_a_mapping_to_an_unknown_provider_is_reported(): void
    {
        $registry = self::fixture();
        $registry['sources']['line_events']['blog'] = 'inconnu';

        $this->assertContains('line_events.blog : fournisseur « inconnu » inconnu', SourceRightsReport::registryErrors(new SourceRights($registry)));
    }

    public function test_an_unknown_status_is_reported(): void
    {
        $registry = self::fixture();
        $registry['providers']['libre']['statut'] = 'peut-etre';

        $this->assertContains('fournisseur libre : statut « peut-etre » inconnu', SourceRightsReport::registryErrors(new SourceRights($registry)));
    }

    public function test_archeoferroviaire_and_lignes_oubliees_are_not_safe_without_a_written_licence(): void
    {
        $rights = self::rights();

        $this->assertSame(SourceRights::UNCERTAIN, $rights->status('archeoferroviaire'));
        $this->assertSame(SourceRights::UNCERTAIN, $rights->status('lignes-oubliees'));
        $this->assertSame('archeoferroviaire', $rights->providerFor('source_traces', 'archeoferroviaire'));
        $this->assertSame('lignes-oubliees', $rights->providerFor('station_sources', 'lignes-oubliees'));
    }

    public function test_a_composite_source_takes_the_provider_of_the_cited_link(): void
    {
        $rights = self::rights();

        $this->assertSame('lignes-oubliees', $rights->providerFor('line_events', 'manual', 'https://www.lignes-oubliees.com/index.php?page=ecouviez'));
        $this->assertSame('wikipedia', $rights->providerFor('line_events', 'manual', 'https://fr.wikipedia.org/w/index.php?title=X&oldid=1'));
        $this->assertSame('ign', $rights->providerFor('line_events', 'heritage', 'https://geoservices.ign.fr/bdtopo'));
        $this->assertSame('tiers-cites', $rights->providerFor('line_events', 'manual', 'http://www.amitram.fr/asso_presentation.php'));
    }

    public function test_a_composite_source_without_known_link_falls_back_to_its_default(): void
    {
        $rights = self::rights();

        $this->assertSame('vff', $rights->providerFor('line_events', 'manual', null));
        $this->assertSame('vff', $rights->providerFor('line_events', 'manual', 'https://site-inconnu.example/page'));
    }

    public function test_domain_matching_accepts_subdomains_but_not_lookalikes(): void
    {
        $rights = new SourceRights(self::fixture());

        $this->assertSame('douteux', $rights->providerForUrl('https://www.douteux.fr/a'));
        $this->assertSame('douteux', $rights->providerForUrl('http://douteux.fr'));
        $this->assertNull($rights->providerForUrl('https://pasdouteux.fr/a'));
        $this->assertNull($rights->providerForUrl('https://douteux.fr.example.com/a'));
        $this->assertNull($rights->providerForUrl('pas une url'));
    }

    public function test_an_undeclared_source_has_no_provider_nor_status(): void
    {
        $rights = self::rights();

        $this->assertNull($rights->providerFor('line_events', 'blog-inconnu'));
        $this->assertNull($rights->statusFor('line_events', 'blog-inconnu'));
        $this->assertFalse($rights->knows('line_events', 'blog-inconnu'));
        $this->assertTrue($rights->knows('line_events', 'manual'));
    }

    public function test_sql_condition_covers_direct_and_cited_uncertain_sources_only(): void
    {
        $sql = (new SourceRights(self::fixture()))->sqlCondition('line_events', SourceRights::UNCERTAIN);

        $this->assertStringContainsString("source IN ('douteux')", $sql);
        $this->assertStringContainsString("source IN ('manual')", $sql);
        $this->assertStringContainsString('douteux\.fr', $sql);
        $this->assertStringNotContainsString("'libre'", $sql);
        // Statut sans aucune source : condition toujours fausse.
        $this->assertSame('FALSE', (new SourceRights(self::fixture()))->sqlCondition('line_events', SourceRights::FORBIDDEN));
    }

    public function test_every_table_value_of_the_pipeline_is_declared(): void
    {
        $rights = self::rights();
        // Provenances écrites par les commandes d'import et de dérivation (cf. vff:rebuild-history).
        $expected = [
            'line_events' => ['wikipedia', 'lignes-oubliees', 'ohm', 'wikipedia-reseaux', 'archeoferroviaire', 'wikidata', 'manual', 'estimation-reseau', 'heritage', 'osm', 'jorf'],
            'source_traces' => ['archeoferroviaire', 'bdtopo', 'declared', 'ign-historique', 'ohm', 'osm', 'sncf'],
            'station_sources' => ['sncf', 'osm', 'wikidata', 'sncf_voyageurs', 'lignes-oubliees', 'ohm'],
            'media' => ['commons', 'upload'],
        ];
        foreach ($expected as $table => $values) {
            foreach ($values as $v) {
                $this->assertTrue($rights->knows($table, $v), "{$table}.{$v} non déclaré");
            }
        }
    }
}
