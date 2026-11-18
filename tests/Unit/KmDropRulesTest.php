<?php

namespace Tests\Unit;

use App\Console\Commands\ImportLignesOubliees;
use App\Console\Commands\ImportSecondaryNetworkDates;
use App\Console\Commands\OhmEvents;
use PHPUnit\Framework\TestCase;

/**
 * Règles qui évitent qu'une ligne reste « en service » jusqu'en 2020 puis disparaisse en
 * 2030 sans fermeture datée (invariant lines.km_drop_without_event), appelées par
 * réflexion, sans base :
 *   - vff:ohm-events : un way OHM rattaché à une ligne d'une autre catégorie de réseau,
 *     ou un tramway moderne (1985 et après) sur une ligne non exploitée, ne date pas la
 *     ligne ; extrémités des noms « Voie-mère de A à B », « Ligne de A a B » ; l'année de
 *     fin de la carte source (« … for the period 1820 to 1866 ») n'est pas une ouverture ;
 *   - vff:import-secondary-network-dates : ligne déjà datée ailleurs mais sans aucune fin
 *     d'exploitation → seules les fins des fiches sont écrites ;
 *   - vff:import-lignes-oubliees : section « A à B » sans tiret.
 */
class KmDropRulesTest extends TestCase
{
    private function call(object|string $target, string $method, mixed ...$args): mixed
    {
        $object = is_string($target) ? (new \ReflectionClass($target))->newInstanceWithoutConstructor() : $target;

        return (new \ReflectionMethod($object, $method))->invoke($object, ...$args);
    }

    // ─── OhmEvents ───────────────────────────────────────────────────────

    private function way(array $over = []): array
    {
        return $over + ['id' => 1, 'osm' => 'w1', 'layer' => 'rail', 'kind' => 'active', 'tags' => [], 'len' => 2000.0,
            'start' => ['date' => '1868-10-19', 'year' => 1868, 'precision' => 'day', 'approx' => false], 'end' => null,
            'mode' => null, 'elec' => null, 'code' => null, 'gauges' => [], 'narrow' => false, 'service' => false, 'usage' => '',
            'linked_line' => null, 'linked_network' => null, 'chunks' => 40];
    }

    private function ohm(array $exploitedCodes = []): OhmEvents
    {
        $cmd = (new \ReflectionClass(OhmEvents::class))->newInstanceWithoutConstructor();
        foreach (['step' => 50.0, 'buffer' => 30.0, 'exploitedCodes' => array_fill_keys($exploitedCodes, true),
            'stats' => ['name_excluded' => 0, 'network_excluded' => 0, 'modern_tram_excluded' => 0]] as $prop => $value) {
            (new \ReflectionProperty($cmd, $prop))->setValue($cmd, $value);
        }

        return $cmd;
    }

    public function test_way_of_a_main_line_does_not_date_the_tramway_alongside(): void
    {
        // Ligne de Nice à Menton (tramway TNL) : le way de la grande ligne Marseille - Vintimille (1868) la longe.
        $tram = (object) ['id' => 3870, 'code' => null, 'name' => 'Ligne de Nice à Menton', 'network' => 'secondaire', 'gauge_mm_default' => null];
        $ways = [
            1 => $this->way(['linked_line' => 596, 'linked_network' => 'principal']),
            2 => $this->way(['id' => 2, 'linked_line' => 3870, 'linked_network' => 'secondaire']),   // son propre way
            3 => $this->way(['id' => 3, 'linked_line' => 3871, 'linked_network' => 'secondaire']),   // même catégorie : tronc commun possible
            4 => $this->way(['id' => 4]),                                                           // way non rattaché
        ];

        $kept = $this->call($this->ohm(), 'filterWays', $tram, $ways);

        $this->assertSame([2, 3, 4], array_keys($kept));
        $this->assertTrue(OhmEvents::otherNetworkWay((object) ['id' => 4287, 'network' => 'particulier'], $this->way(['linked_line' => 68, 'linked_network' => 'principal'])));
        // Catégorie non renseignée = réseau principal.
        $this->assertFalse(OhmEvents::otherNetworkWay((object) ['id' => 10, 'network' => null], $this->way(['linked_line' => 11, 'linked_network' => 'principal'])));
    }

    public function test_modern_tram_does_not_reopen_a_line_unless_it_is_still_exploited(): void
    {
        $t3 = $this->way(['layer' => 'tram', 'start' => ['date' => '2006-12-05', 'year' => 2006, 'precision' => 'day', 'approx' => false]]);
        $oldTram = $this->way(['layer' => 'tram', 'start' => ['date' => '1909-02-01', 'year' => 1909, 'precision' => 'day', 'approx' => false]]);
        $this->assertTrue(OhmEvents::modernTramWay($t3));
        $this->assertFalse(OhmEvents::modernTramWay($oldTram));
        $this->assertFalse(OhmEvents::modernTramWay($this->way(['start' => $t3['start']])));    // railway=rail
        $this->assertFalse(OhmEvents::modernTramWay($this->way(['layer' => 'tram', 'start' => null])));

        // Ligne de Lyon-Est à Montalieu (901000, déclassée) : le T3 de 2006 n'est pas une réouverture.
        $cfel = (object) ['id' => 680, 'code' => '901000', 'name' => 'Ligne de Lyon-Part-Dieu à Montalieu-Vercieu', 'network' => 'principal', 'gauge_mm_default' => null];
        $this->assertSame([], $this->call($this->ohm(), 'filterWays', $cfel, [1 => $t3]));
        // Tram-train sur une ligne exploitée aujourd'hui (SNCF Réseau) : gardé.
        $this->assertSame([1], array_keys($this->call($this->ohm(['901000']), 'filterWays', $cfel, [1 => $t3])));
        // Ancien tramway (avant 1985) : gardé.
        $this->assertSame([1], array_keys($this->call($this->ohm(), 'filterWays', $cfel, [1 => $oldTram])));
    }

    public function test_name_ends_of_spur_lines_and_unaccented_ohm_names(): void
    {
        $ends = fn (string $name) => $this->call(OhmEvents::class, 'nameEnds', $name);
        $this->assertSame(['pont-a-vendin', 'violaines'], $ends('Voie-mère de Pont-à-Vendin à Violaines'));
        $this->assertSame(['fives', 'abeville'], $ends('Ligne de Fives a Abeville'));
        $this->assertSame(['ban-gavres', 'pointe-st-germain'], $ends('Ligne militaire de Ban-Gâvres à Pointe-Saint-Germain'));
        $this->assertNull($ends('voies-mères de Pont-à-Vendin'));

        // Le way « Ligne de Fives a Abeville » n'a aucune extrémité commune avec la voie-mère : écarté.
        $line = (object) ['id' => 595, 'code' => '286610', 'name' => 'Voie-mère de Pont-à-Vendin à Violaines', 'network' => 'principal', 'gauge_mm_default' => null];
        $kept = $this->call($this->ohm(), 'filterWays', $line, [1 => $this->way(['tags' => ['name' => 'Ligne de Fives a Abeville']]),
            2 => $this->way(['id' => 2, 'tags' => ['name' => 'voies-mères de Pont-à-Vendin']])]);
        $this->assertSame([2], array_keys($kept));
    }

    public function test_end_year_of_the_source_map_period_is_not_an_opening(): void
    {
        $etatMajor = 'French military maps (Etat-Major) for the period 1820 to 1866';
        // Pont-de-la-Deûle - Bachy-Mouchin, inaugurée le 20 décembre 1883 : « start_date=1866 ».
        $this->assertTrue(OhmEvents::placeholderStart(['start_date' => '1866', 'source' => $etatMajor]));
        // Date précise, autre année, autre source : lues normalement.
        $this->assertFalse(OhmEvents::placeholderStart(['start_date' => '1848-09-01', 'source' => $etatMajor]));
        $this->assertFalse(OhmEvents::placeholderStart(['start_date' => '1846', 'source' => $etatMajor]));
        $this->assertFalse(OhmEvents::placeholderStart(['start_date' => '1866']));

        // Le way n'a plus de date : il ne date plus la ligne.
        $line = (object) ['id' => 3280, 'code' => null, 'name' => 'Ligne de Pont-de-la-Deûle à Orchies', 'network' => 'principal', 'gauge_mm_default' => null];
        $p = (object) ['way' => 7, 'source_ref' => 'w7', 'layer' => 'rail', 'len' => 15795.0, 'linked_line' => 3280, 'linked_network' => 'principal',
            'properties' => json_encode(['railway' => 'rail', 'start_date' => '1866', 'source' => $etatMajor])];
        $way = $this->call(OhmEvents::class, 'describeWay', $p) + ['chunks' => 300];
        $this->assertNull($way['start']);
        $this->assertSame([], $this->call($this->ohm(), 'filterWays', $line, [7 => $way]));
    }

    // ─── ImportSecondaryNetworkDates ─────────────────────────────────────

    private function fiche(string $type, string $date, bool $whole = true, ?string $from = null, ?string $to = null): array
    {
        return ['type' => $type, 'date' => $date, 'precision' => 'year', 'confidence' => 'medium', 'range' => false, 'origin' => 'article',
            'quote' => "{$type} {$date}", 'record' => 0, 'article' => 'Chemin de fer de Luxey à Mont-de-Marsan', 'url' => 'https://fr.wikipedia.org/w/index.php?oldid=1',
            'route' => 'Luxey - Mont-de-Marsan', 'kind' => $whole ? 'full' : 'section', 'detail' => '', 'whole' => $whole, 'from' => $from, 'to' => $to, 'rank' => 30];
    }

    private function datedLine(?string $otherClosures, string $firstOpening = '1906-07-12', ?string $lastClosure = null, ?string $lastOpening = null): object
    {
        return (object) ['id' => 3781, 'network' => 'secondaire', 'dated' => true, 'first_opening' => $firstOpening,
            'other_closures' => $otherClosures, 'last_closure' => $lastClosure, 'last_opening' => $lastOpening ?? $firstOpening];
    }

    public function test_dated_line_without_any_end_receives_the_ends_of_the_network_article(): void
    {
        // Luxey - Mont-de-Marsan : ouverte en 1906 selon Wikipédia, aucune fin ailleurs.
        $events = [$this->fiche('opening', '1906-06-12'), $this->fiche('closure', '1959-01-01'), $this->fiche('declassification', '1960-01-01'),
            $this->fiche('closure', '1890-01-01')];   // fin « toute la ligne » avant l'ouverture : incohérente

        [$kept, $status, $reason] = $this->call(ImportSecondaryNetworkDates::class, 'endsOfDatedLine', $events, $this->datedLine(null), []);

        $this->assertSame('write', $status);
        $this->assertSame(['closure 1959-01-01', 'declassification 1960-01-01'], array_map(fn ($e) => $e['type'].' '.$e['date'], $kept));
        $this->assertStringContainsString('seules les fins', $reason);
    }

    public function test_dated_line_with_a_known_end_keeps_its_own_dates(): void
    {
        $events = [$this->fiche('opening', '1906-06-12'), $this->fiche('closure', '1959-01-01')];
        foreach (['closure', 'declassification', 'closure_passenger,closure_freight'] as $others) {
            [$kept, $status] = $this->call(ImportSecondaryNetworkDates::class, 'endsOfDatedLine', $events, $this->datedLine($others), []);
            $this->assertSame('dated', $status, $others);
            $this->assertSame($events, $kept);
        }
        // Fermeture connue ailleurs mais suivie d'une ouverture (Lagny - Mortcerf : « fermée en 1900 »,
        // prolongement ouvert en 1902) : la ligne vit encore, les fermetures de la fiche (1934) sont écrites.
        [, $status] = $this->call(ImportSecondaryNetworkDates::class, 'endsOfDatedLine', [$this->fiche('closure', '1934-01-01')],
            $this->datedLine('closure', '1872-01-01', '1900-01-01', '1902-01-01'), []);
        $this->assertSame('write', $status);
        // Fermeture aux voyageurs seule ailleurs : ce n'est pas une fin, les fermetures de la fiche sont écrites.
        [$kept, $status] = $this->call(ImportSecondaryNetworkDates::class, 'endsOfDatedLine',
            [$this->fiche('closure', '1937-01-01', false, 'Saint-Girons', 'Castillon-en-Couserans')], $this->datedLine('closure_passenger', '1911-01-01'), []);
        $this->assertSame('write', $status);
        $this->assertSame('Saint-Girons', $kept[0]['from']);
        // Aucune fin dans les fiches ni fin de réseau : la ligne reste « déjà datée ».
        [, $status] = $this->call(ImportSecondaryNetworkDates::class, 'endsOfDatedLine', [$this->fiche('opening', '1906-06-12')], $this->datedLine(null), []);
        $this->assertSame('dated', $status);
    }

    public function test_network_end_closes_a_dated_line_without_end(): void
    {
        $article = ['single' => false, 'url' => 'https://fr.wikipedia.org/w/index.php?oldid=2', 'network' => [
            'statement' => ['date' => '1950-12-31', 'year' => 1950, 'precision' => 'day', 'field' => 'fermeture du réseau', 'value' => 'ces lignes ferment le 31 décembre 1950'],
        ]];
        $events = [$this->fiche('opening', '1906-06-12')];

        [$kept, $status] = $this->call(ImportSecondaryNetworkDates::class, 'endsOfDatedLine', $events, $this->datedLine(null),
            ['Chemin de fer de Luxey à Mont-de-Marsan' => $article]);

        $this->assertSame('write', $status);
        $this->assertSame([['closure', '1950-12-31', true]], array_map(fn ($e) => [$e['type'], $e['date'], $e['whole']], $kept));
    }

    // ─── ImportLignesOubliees ────────────────────────────────────────────

    public function test_lignes_oubliees_section_written_a_to_b_without_dash(): void
    {
        $parse = fn (string $s) => array_intersect_key($this->call(ImportLignesOubliees::class, 'parseSection', $s), ['from' => 0, 'to' => 0]);

        // Fiche 709000 : « Fermeture aux marchandises : Lapeyrouse à Volvic : 08/12/2007 ».
        $this->assertSame(['from' => 'Lapeyrouse', 'to' => 'Volvic'], $parse('Lapeyrouse à Volvic'));
        $this->assertSame(['from' => 'Laveline-devant-Bruyères', 'to' => 'Gérardmer'], $parse('Laveline-devant-Bruyères à Gérardmer'));
        $this->assertSame(['from' => 'Rosporden', 'to' => 'La Boissière'], $parse('Rosporden - La Boissière (671,700)'));
        // Inchangés : section localisée, raccordement nommé, lieu entre parenthèses.
        $this->assertSame(['from' => 'Annecy', 'to' => 'Annecy'], $parse('Section à Annecy (1,699 - 2,360)'));
        $this->assertSame(['from' => 'Raccordement La Hisse à Saint-Samson', 'to' => 'Raccordement La Hisse à Saint-Samson'], $parse('Raccordement La Hisse à Saint-Samson'));
        $this->assertSame(['from' => 'Villers-les-Pots (de la gare au PN 23)', 'to' => 'Villers-les-Pots (de la gare au PN 23)'], $parse('Villers-les-Pots (de la gare au PN 23)'));
    }
}
