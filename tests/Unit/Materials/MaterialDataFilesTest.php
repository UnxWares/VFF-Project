<?php

namespace Tests\Unit\Materials;

use App\Http\Resources\MaterialDetailResource;
use PHPUnit\Framework\TestCase;

/**
 * Forme et contenu des fichiers de données relus à la main du matériel : liens ligne × série
 * (line_material_supplements.php), valeurs de fiche (material_date_supplements.php) et familles
 * (material_families.php). Rapport storage/app/reports/agents/aprem-2111/materiel-manques.md.
 */
class MaterialDataFilesTest extends TestCase
{
    /** @return array{links: list<array<string, mixed>>, dropped_periods: list<array<string, mixed>>} */
    private static function lineSupplements(): array
    {
        return require __DIR__.'/../../../database/data/line_material_supplements.php';
    }

    /** @return list<array<string, mixed>> */
    private static function dateSupplements(): array
    {
        return require __DIR__.'/../../../database/data/material_date_supplements.php';
    }

    /** @return array<string, list<string>> */
    private static function families(): array
    {
        return require __DIR__.'/../../../database/data/material_families.php';
    }

    /**
     * @param  list<array<string, mixed>>  $links
     * @return array<string, mixed>|null
     */
    private static function link(array $links, string $line, string $material): ?array
    {
        foreach ($links as $l) {
            if ($l['line'] === $line && $l['material'] === $material) {
                return $l;
            }
        }

        return null;
    }

    public function test_every_line_link_is_complete_cited_and_dated_consistently(): void
    {
        $seen = [];
        foreach (self::lineSupplements()['links'] as $i => $l) {
            $where = "lien {$i} ({$l['line']} × {$l['material']})";
            foreach (['line', 'material', 'kind', 'article', 'section', 'quote'] as $key) {
                $this->assertIsString($l[$key] ?? null, "{$where} : {$key}");
                $this->assertNotSame('', trim($l[$key]), "{$where} : {$key} vide");
            }
            $this->assertMatchesRegularExpression('/^Q[1-9]\d{0,9}$/', $l['material'], $where);
            $this->assertContains($l['kind'], ['wikipedia_line_article', 'wikipedia_material_article'], $where);
            $this->assertIsInt($l['revid'], $where);
            $this->assertGreaterThan(0, $l['revid'], $where);
            $this->assertLessThanOrEqual(255, mb_strlen($l['quote']), "{$where} : citation trop longue");
            $json = json_encode($l, JSON_UNESCAPED_UNICODE) ?: '';
            $this->assertStringNotContainsString('—', $json, "{$where} : tiret long");
            $this->assertStringNotContainsString(' -- ', $json, "{$where} : double tiret");
            foreach (['from', 'to'] as $bound) {
                if (($l[$bound] ?? null) === null) {
                    continue;
                }
                $this->assertContains($l[$bound]['precision'], ['day', 'month', 'year'], "{$where} : précision de {$bound}");
                $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $l[$bound]['date'], "{$where} : date de {$bound}");
            }
            if (($l['from'] ?? null) !== null && ($l['to'] ?? null) !== null) {
                $this->assertLessThanOrEqual($l['to']['date'], $l['from']['date'], "{$where} : fin avant le début");
            }
            $key = $l['line'].':'.$l['material'].':'.$l['quote'];
            $this->assertArrayNotHasKey($key, $seen, "{$where} : doublon");
            $seen[$key] = true;
        }
    }

    public function test_the_gas_turbine_sets_are_dated_on_paris_caen_cherbourg(): void
    {
        $links = self::lineSupplements()['links'];

        $rtg = self::link($links, '366000', 'Q265728');
        $this->assertNotNull($rtg, 'RTG sur Mantes - Cherbourg');
        $this->assertSame('1975-01-01', $rtg['from']['date']);
        $this->assertSame('year', $rtg['from']['precision']);
        $this->assertSame('1996-06-01', $rtg['to']['date']);

        $etg = self::link($links, '366000', 'Q3588105');
        $this->assertNotNull($etg, 'fin des ETG sur Mantes - Cherbourg');
        $this->assertNull($etg['from'] ?? null, 'le début des ETG (mars 1971) vient déjà de l\'article de la ligne');
        $this->assertSame('1975-01-01', $etg['to']['date']);
        $this->assertStringContainsString('remplacés dès 1975', $etg['quote']);
    }

    public function test_the_second_normandy_order_is_linked_to_its_two_lines_without_invented_dates(): void
    {
        $links = self::lineSupplements()['links'];
        foreach (['340000', '366000'] as $line) {
            $z = self::link($links, $line, 'Q137669875');
            $this->assertNotNull($z, "Z 56800 sur {$line}");
            $this->assertStringContainsString('Z 56800', $z['quote']);
            $this->assertNull($z['from'] ?? null);
            $this->assertNull($z['to'] ?? null);
        }
    }

    public function test_the_x_72500_link_quotes_the_sentence_that_names_caen_and_mezidon(): void
    {
        $x = self::link(self::lineSupplements()['links'], '366000', 'Q3570526');
        $this->assertNotNull($x);
        $this->assertStringContainsString('Caen - Mézidon', $x['quote']);
    }

    public function test_no_link_is_added_for_the_b_81500_on_normandy_lines(): void
    {
        // Aucune phrase des articles lus ne le place en Normandie : pas de lien sans phrase.
        foreach (self::lineSupplements()['links'] as $l) {
            $this->assertNotSame('Q847148', $l['material']);
        }
    }

    public function test_the_card_values_read_by_hand_are_cited_and_unique_per_field(): void
    {
        $seen = [];
        foreach (self::dateSupplements() as $i => $r) {
            $where = "valeur {$i} ({$r['material']} {$r['field']})";
            $this->assertContains($r['field'], ['in_service_from', 'in_service_to', 'units_built', 'builder'], $where);
            $this->assertContains($r['lang'] ?? 'fr', ['fr', 'en', 'de', 'it', 'nl'], "{$where} : langue de l'article");
            foreach (['basis', 'source', 'quote'] as $key) {
                $this->assertNotSame('', trim((string) $r[$key]), "{$where} : {$key}");
            }
            $json = json_encode($r, JSON_UNESCAPED_UNICODE) ?: '';
            $this->assertStringNotContainsString('—', $json, $where);
            $this->assertStringNotContainsString(' -- ', $json, $where);
            $this->assertArrayNotHasKey($r['material'].':'.$r['field'], $seen, "{$where} : doublon");
            $seen[$r['material'].':'.$r['field']] = true;
        }
    }

    public function test_the_missing_dates_and_counts_are_filled(): void
    {
        $by = [];
        foreach (self::dateSupplements() as $r) {
            $by[$r['material']][$r['field']] = $r['value'];
        }
        $this->assertSame(2004, $by['Q847148']['in_service_from'], 'B 81500 : première rame livrée le 6/02/2004');
        $this->assertSame(2005, $by['Q822918']['in_service_from'], 'Z 27500');
        $this->assertSame(40, $by['Q120253257']['units_built'], 'Z 56600');
        $this->assertSame(27, $by['Q137669875']['units_built'], 'Z 56800');
        $this->assertSame(2024, $by['Q137669875']['in_service_from'], 'Z 56800');
    }

    public function test_every_value_is_cited_with_a_revision_and_plausible(): void
    {
        $by = [];
        foreach (self::dateSupplements() as $i => $r) {
            $where = "valeur {$i} ({$r['material']} {$r['field']})";
            $this->assertMatchesRegularExpression('/^Q[1-9]\d{0,9}$/', $r['material'], $where);
            $this->assertIsInt($r['revid'], "{$where} : révision");
            $this->assertGreaterThan(0, $r['revid'], $where);
            $this->assertLessThanOrEqual(255, mb_strlen($r['quote']), "{$where} : citation trop longue");
            if (in_array($r['field'], ['in_service_from', 'in_service_to'], true)) {
                $this->assertIsInt($r['value'], $where);
                $this->assertGreaterThanOrEqual(1830, $r['value'], $where);
                $this->assertLessThanOrEqual(2030, $r['value'], $where);
            }
            if ($r['field'] === 'units_built') {
                $this->assertIsInt($r['value'], $where);
                $this->assertGreaterThan(0, $r['value'], $where);
            }
            $by[$r['material']][$r['field']] = $r['value'];
        }
        foreach ($by as $qid => $fields) {
            if (isset($fields['in_service_from'], $fields['in_service_to'])) {
                $this->assertGreaterThanOrEqual($fields['in_service_from'], $fields['in_service_to'], "{$qid} : retrait avant la mise en service");
            }
        }
    }

    /** Passe du 23/11 sur les articles des autres éditions de Wikipédia : la langue est dite, le permalien pointe sur la bonne édition. */
    public function test_the_values_read_in_other_language_editions_say_their_language_and_link_to_that_edition(): void
    {
        $byKey = [];
        foreach (self::dateSupplements() as $r) {
            $byKey[$r['material'].':'.$r['field']] = $r;
        }
        $this->assertSame(1973, $byKey['Q132346:in_service_from']['value'], 'Série 18 SNCB : six locomotives en service en 1973 et 1974');
        $this->assertSame('en', $byKey['Q132346:in_service_from']['lang']);
        $this->assertSame(1859, $byKey['Q105415368:in_service_from']['value'], 'Est 0.250 : mises en service en 1859');
        $this->assertSame(1937, $byKey['Q105415368:in_service_to']['value'], 'Est 0.250 : retraits de 1892 à 1937');
        $this->assertSame(1994, $byKey['Q41499495:in_service_from']['value'], 'Breda VLC de Lille : livrés en 1994');
        $this->assertSame(1934, $byKey['Q125904212:in_service_from']['value'], 'Y 7020 : première livraison (Renault, 1934)');
        $this->assertSame(1979, $byKey['Q125904212:in_service_to']['value'], 'Y 7020 : dernière radiée en 1979');
        $this->assertSame('de', $byKey['Q125904212:in_service_to']['lang']);

        // Le permalien suit la langue ; sans langue, l'édition française comme avant.
        $this->assertSame(
            'https://de.wikipedia.org/w/index.php?title=SNCF_Y_7020&oldid='.$byKey['Q125904212:in_service_to']['revid'],
            MaterialDetailResource::sourceUrl('wikipedia', 'SNCF Y 7020', $byKey['Q125904212:in_service_to']['revid'], 'de'),
        );
        $this->assertSame('https://fr.wikipedia.org/w/index.php?title=Z_21500&oldid=7', MaterialDetailResource::sourceUrl('wikipedia', 'Z 21500', 7));
        $this->assertSame('https://fr.wikipedia.org/wiki/Z_21500', MaterialDetailResource::sourceUrl('wikipedia', 'Z 21500', null, 'xx'), 'langue inconnue : français, jamais un hôte arbitraire');
    }

    public function test_the_dates_read_in_the_series_articles_on_23_11_are_present(): void
    {
        $by = [];
        foreach (self::dateSupplements() as $r) {
            $by[$r['material']][$r['field']] = $r['value'];
        }
        $this->assertSame(1960, $by['Q3574426']['in_service_from'], 'Z 9056 à 9059 : livrées en 1960');
        $this->assertSame(1867, $by['Q113833028']['in_service_from'], '030 Ouest 1904 à 2244 : première livraison (1951-1974 par Schneider en 1867)');
        $this->assertSame(1938, $by['Q2931155']['in_service_from'], 'CC 1100 : prototypes livrés en 1937, mis en service en 1938');
        $this->assertSame(1960, $by['Q585475']['in_service_from'], 'BB 20004 : sortie de transformation le 30/03/1960');
        $this->assertSame(1969, $by['Q585475']['in_service_to'], 'BB 20004 : remise au type en 1969');
        $this->assertSame(2016, $by['Q3588094']['in_service_to'], 'EAD modernisés radiés en 2016');
        $this->assertSame(32, $by['Q120253464']['units_built'], 'Z 56700');
        $this->assertSame(2025, $by['Q134528511']['in_service_from'], 'Z 59000 : Transdev depuis le 29/06/2025');
    }

    public function test_families_are_disjoint_and_a_family_is_never_its_own_version(): void
    {
        $versions = [];
        foreach (self::families() as $family => $members) {
            $this->assertNotEmpty($members, $family);
            $this->assertSame($members, array_values(array_unique($members)), "{$family} : version en double");
            $this->assertNotContains($family, $members, "{$family} est sa propre version");
            foreach ($members as $slug) {
                $this->assertArrayNotHasKey($slug, $versions, "{$slug} est la version de deux familles");
                $this->assertArrayNotHasKey($slug, self::families(), "{$slug} est à la fois famille et version");
                $versions[$slug] = $family;
            }
        }
    }

    public function test_the_families_checked_against_their_articles_are_listed(): void
    {
        $families = self::families();
        $this->assertContains('b-81500', $families['autorail-grande-capacite']);
        $this->assertEqualsCanonicalizing(['x-4300', 'x-4500', 'x-4630', 'x-4750', 'x-4900', 'x-94750'], $families['element-automoteur-double']);
        $this->assertNotContains('x-72500', $families['element-automoteur-double'], 'successeur des EAD, pas une version');
        $this->assertEqualsCanonicalizing(['sncf-b-83500', 'coradia-liner'], $families['regiolis']);
        $this->assertEqualsCanonicalizing(['z-5600', 'z-8800', 'z-20500', 'z-20900'], $families['z-2n']);
    }

    /**
     * Notes des relations ambiguës (material_relation_lines.php) : deux extrémités, des codes de ligne, une révision
     * figée et la phrase citée. Paris - Laon, Neussargues - Saint-Chély-d'Apcher, Nancy - Neufchâteau, Bordeaux -
     * Limoges et Limoges - Gannat : retraits de l'import complet du 23/11 (rapport aprem-2111/materiel-derive.md) ; puis huit relations de plus (rapport aprem-2111/materiel-relations.md).
     */
    public function test_every_ambiguous_relation_note_is_complete_and_cited(): void
    {
        $notes = require __DIR__.'/../../../database/data/material_relation_lines.php';
        $relations = [];
        foreach ($notes as $note) {
            $this->assertCount(2, $note['relation']);
            $this->assertNotSame([], $note['lines']);
            foreach ($note['lines'] as $code) {
                $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
            }
            $this->assertMatchesRegularExpression('/^https:\/\/fr\.wikipedia\.org\/w\/index\.php\?title=[^&]+&oldid=\d+$/', $note['source']);
            $this->assertMatchesRegularExpression('/« [^»]+ »/u', $note['reason'], 'la phrase de la source est citée');
            $relations[] = implode(' - ', $note['relation']);
        }
        $this->assertSame(count($relations), count(array_unique($relations)), 'une note par relation');
        $this->assertContains('Paris - Laon', $relations);
        $this->assertContains('Neussargues - Saint-Chély-d\'Apcher', $relations);
        $this->assertContains('Nancy - Neufchâteau', $relations);
        $this->assertContains('Bordeaux - Limoges', $relations);
        $this->assertContains('Limoges - Gannat', $relations);
        foreach (['Paris-Montparnasse - Mantes-la-Jolie', 'Nancy - Pont-Saint-Vincent', 'Limoges - Agen', 'Lille - Don-Sainghin', 'Lille - Béthune', 'Lille - Saint-Pol-sur-Ternoise', 'Saint-Pol-sur-Ternoise - Étaples', 'Arras - Dunkerque'] as $relation) {
            $this->assertContains($relation, $relations);
        }
        // Import du 25/11 : liens du XAT 1000 (et leurs quatre périodes de 1939) retirés par la règle des relations ambiguës.
        $this->assertContains('Bordeaux - Périgueux', $relations);
        $this->assertContains('Bordeaux - Bergerac', $relations);
    }
}
