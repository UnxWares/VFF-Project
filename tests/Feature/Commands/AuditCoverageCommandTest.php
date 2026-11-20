<?php

namespace Tests\Feature\Commands;

use App\Models\Line;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * vff:audit-coverage : listes de travail de couverture du réseau.
 * Chaque étape est vérifiée par le CSV qu'elle produit (bac à sable) :
 * OHM/OSM non couverts (regroupement, catégorie présumée, nom proposé,
 * rattachement, hors France, voies de service écartées), noms génériques,
 * incohérences de catégorie, couverture Wikidata, doublons.
 */
class AuditCoverageCommandTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
    }

    // ─── utilitaires ─────────────────────────────────────────────────────

    /** @param  array<string, mixed>  $attributes */
    private function line(string $name, ?string $network, ?string $code = null, array $attributes = []): Line
    {
        return Line::factory()->create($attributes + [
            'name' => $name,
            'slug' => Str::slug($name).'-'.random_int(1000, 999999),
            'network' => $network,
            'code' => $code,
            'type' => null,
        ]);
    }

    /** @param  array<string, mixed>|null  $properties */
    private function trace(string $source, string $ref, string $wkt, array $values = [], ?array $properties = null): int
    {
        return (int) DB::selectOne('INSERT INTO source_traces (source, source_ref, layer, line_id, name, length_m, match_method, properties, seen_at, geom)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?::jsonb, now(), ST_Multi(ST_GeomFromText(?, 4326))) RETURNING id', [
            $source, $ref, $values['layer'] ?? 'rail', $values['line_id'] ?? null, $values['name'] ?? null,
            $values['length_m'] ?? 5000, $values['match_method'] ?? null,
            $properties === null ? null : json_encode($properties), $wkt,
        ])->id;
    }

    private function commune(string $insee, string $name, float $lng, float $lat): void
    {
        DB::insert("INSERT INTO communes (code_insee, type, nom, nom_norm, geom, created_at, updated_at)
            VALUES (?, 'commune-actuelle', ?, ?, ST_SetSRID(ST_MakePoint(?, ?), 4326), now(), now())",
            [$insee, $name, mb_strtolower($name), $lng, $lat]);
    }

    /** Contour de commune (rectangle) : sert à la part « en France ». */
    private function contour(string $insee, float $lng1, float $lat1, float $lng2, float $lat2): void
    {
        DB::insert('INSERT INTO commune_contours (code_insee, nom, geom, created_at, updated_at)
            VALUES (?, ?, ST_Multi(ST_MakeEnvelope(?, ?, ?, ?, 4326)), now(), now())', [$insee, 'C'.$insee, $lng1, $lat1, $lng2, $lat2]);
    }

    /** @return list<array<string, string>> */
    private function csv(string $name): array
    {
        $fh = fopen(storage_path("app/reports/audit-coverage/{$name}.csv"), 'r');
        $head = fgetcsv($fh);
        $rows = [];
        while (($r = fgetcsv($fh)) !== false) {
            $rows[] = array_combine($head, $r);
        }
        fclose($fh);

        return $rows;
    }

    /** @return array<string, array<string, string>> lignes du CSV indexées par une colonne */
    private function csvBy(string $name, string $column): array
    {
        $out = [];
        foreach ($this->csv($name) as $r) {
            $out[$r[$column]] = $r;
        }

        return $out;
    }

    /** @param  list<array<string, string>>  $rows */
    private function rowContaining(array $rows, string $column, string $needle): ?array
    {
        foreach ($rows as $r) {
            if (str_contains($r[$column], $needle)) {
                return $r;
            }
        }

        return null;
    }

    // ─── 3. OHM / OSM non couverts ───────────────────────────────────────

    public function test_uncovered_open_data_is_clustered_and_qualified(): void
    {
        // Une ligne VFF tracée (archeo) : le tracé OHM qui la double est couvert.
        $vff = $this->line('Ligne de Limoges à Brive', 'principal', '600000');
        $this->trace('archeoferroviaire', 'a-vff', 'LINESTRING(1.0 45.0, 1.03 45.0)', ['line_id' => $vff->id, 'layer' => 'principal']);
        $this->trace('ohm', 'ohm-covered', 'LINESTRING(1.0 45.0001, 1.03 45.0001)', ['length_m' => 2400]);

        // Non couverts, chacun à sa latitude (ensembles distincts).
        $this->trace('ohm', 'ohm-mil', 'LINESTRING(1.0 45.2, 1.03 45.2)', ['length_m' => 2400], ['usage' => 'military']);
        $this->trace('ohm', 'ohm-ind', 'LINESTRING(1.0 45.3, 1.03 45.3)', ['length_m' => 2400], ['usage' => 'industrial']);
        $this->trace('ohm', 'ohm-fun', 'LINESTRING(1.0 45.4, 1.03 45.4)', ['length_m' => 2400, 'layer' => 'funicular']);
        $this->trace('ohm', 'ohm-tram', 'LINESTRING(1.0 45.5, 1.03 45.5)', ['length_m' => 2400, 'layer' => 'tram']);
        $this->trace('ohm', 'ohm-ng', 'LINESTRING(1.0 45.6, 1.03 45.6)', ['length_m' => 2400, 'layer' => 'narrow_gauge']);
        $this->trace('ohm', 'ohm-1000', 'LINESTRING(1.0 45.7, 1.03 45.7)', ['length_m' => 2400], ['gauge' => '1000']);
        $this->trace('ohm', 'ohm-main', 'LINESTRING(1.0 45.8, 1.03 45.8)', ['length_m' => 2400], ['usage' => 'main', 'start_date' => '1881', 'end_date' => '1938']);
        $this->trace('ohm', 'ohm-named', 'LINESTRING(1.0 45.9, 1.03 45.9)', ['length_m' => 2400, 'name' => 'chemin de fer du Blanc'], ['usage' => 'branch']);
        $this->trace('ohm', 'ohm-other', 'LINESTRING(1.0 46.0, 1.03 46.0)', ['length_m' => 2400, 'name' => 'Voie verte des Monts']);
        // Trop court (< --min-length) : écarté de la liste mais compté parmi les non couverts.
        $this->trace('ohm', 'ohm-short', 'LINESTRING(1.0 46.1, 1.005 46.1)', ['length_m' => 400]);
        // Voie de service et calques exclus.
        $this->trace('ohm', 'ohm-yard', 'LINESTRING(1.0 46.2, 1.03 46.2)', ['length_m' => 2400], ['service' => 'yard']);
        $this->trace('ohm', 'ohm-subway', 'LINESTRING(1.0 46.3, 1.03 46.3)', ['length_m' => 2400, 'layer' => 'subway']);

        // Extrémités : communes proches.
        $this->commune('87001', 'Aixe', 0.99, 45.8);
        $this->commune('87002', 'Bujaleuf', 1.04, 45.8);

        $this->artisan('vff:audit-coverage')
            ->expectsOutputToContain('→ 3. Tracés ohm non couverts par une ligne VFF…')
            ->expectsOutputToContain('10 tracés non couverts (< 20 % de leur longueur près d\'une ligne VFF) ; 9 ensembles de ≥ 1000 m à examiner ; 1 voies de service écartées.')
            ->assertSuccessful();

        $rows = $this->csv('ohm-osm-non-couverts');
        $this->assertCount(9, $rows);
        $byNet = [];
        foreach ($rows as $r) {
            $byNet[$r['noms'] ?: $r['usage'].'/'.$r['calques'].'/'.$r['écartement']] = $r['catégorie présumée'];
        }
        $this->assertEquals([
            'military/rail/' => 'militaire',
            'industrial/rail/' => 'particulier',
            '/funicular/' => 'particulier',
            '/tram/' => 'secondaire (tramway)',
            '/narrow_gauge/' => 'secondaire',
            '/rail/1000' => 'secondaire',
            'main/rail/' => 'principal',
            'chemin de fer du Blanc' => 'principal',
            'Voie verte des Monts' => 'à qualifier',
        ], $byNet);
        $this->assertNull($this->rowContaining($rows, 'proposition', 'Limoges'), 'le tracé doublant une ligne VFF est couvert');

        $main = $this->rowContaining($rows, 'usage', 'main');
        $this->assertSame(['Aixe', 'Bujaleuf'], [min($main['extrémité A'], $main['extrémité B']), max($main['extrémité A'], $main['extrémité B'])]);
        $this->assertSame('1881', $main['début']);
        $this->assertSame('1938', $main['fin']);
        $this->assertSame('2.4', $main['km']);
        $this->assertSame('1', $main['tracés']);
        $this->assertStringStartsWith('créer « Ligne d', $main['proposition']);
        $this->assertStringContainsString('(principal)', $main['proposition']);
        $this->assertSame('', $main['part en France'], 'contours de communes pas importés : part inconnue, pas « hors France »');

        $named = $this->rowContaining($rows, 'noms', 'chemin de fer du Blanc');
        $this->assertSame('créer « Chemin de fer du Blanc » (principal)', $named['proposition'], 'nom de la source repris, majuscule initiale');

        $military = $this->rowContaining($rows, 'usage', 'military');
        $this->assertStringStartsWith('créer « Ligne militaire ', $military['proposition']);
        $industrial = $this->rowContaining($rows, 'usage', 'industrial');
        $this->assertStringStartsWith('créer « Voie industrielle ', $industrial['proposition']);
        $tram = $this->rowContaining($rows, 'calques', 'tram');
        $this->assertStringStartsWith('créer « Tramway ', $tram['proposition']);
        $this->assertSame('Voie verte des Monts', $this->rowContaining($rows, 'noms', 'Voie verte')['noms']);
        $this->assertStringStartsWith('créer « Voie verte des Monts »', $this->rowContaining($rows, 'noms', 'Voie verte')['proposition']);
    }

    public function test_a_cluster_touching_a_vff_line_proposes_to_attach_it(): void
    {
        $vff = $this->line('Ligne de Tulle à Ussel', 'principal', '601000');
        // Tracé de référence perpendiculaire qui ne touche l'OHM qu'à une extrémité.
        $this->trace('sncf', 's-vff', 'LINESTRING(2.0 44.99, 2.0 45.01)', ['line_id' => $vff->id]);
        $this->trace('ohm', 'ohm-touch', 'LINESTRING(2.0 45.0, 2.1 45.0)', ['length_m' => 7900], ['usage' => 'branch']);
        $this->trace('osm', 'osm-other', 'LINESTRING(2.0 45.0002, 2.1 45.0002)', ['length_m' => 7900], ['usage' => 'branch']);
        $this->commune('19001', 'Tulle', 2.0, 45.0);

        $this->artisan('vff:audit-coverage')
            ->expectsOutputToContain('→ 3. Tracés ohm / osm non couverts par une ligne VFF…')
            ->assertSuccessful();

        $rows = $this->csv('ohm-osm-non-couverts');
        $this->assertCount(2, $rows, 'un ensemble par source');
        foreach ($rows as $r) {
            $this->assertStringStartsWith("rattacher à « Ligne de Tulle à Ussel » (#{$vff->id}, principal) ou créer « Ligne près de Tulle »", $r['proposition']);
            $this->assertSame('1.00', $r['corroboré par une autre source (part)'], 'OHM et OSM se corroborent');
        }
    }

    public function test_clusters_outside_the_communes_contours_are_flagged_abroad(): void
    {
        $this->contour('67001', 7.0, 48.0, 7.5, 48.5);
        $this->trace('ohm', 'ohm-fr', 'LINESTRING(7.1 48.1, 7.2 48.1)', ['length_m' => 7400], ['usage' => 'main']);
        $this->trace('ohm', 'ohm-de', 'LINESTRING(8.1 48.1, 8.2 48.1)', ['length_m' => 7400], ['usage' => 'main']);

        $this->artisan('vff:audit-coverage')
            ->expectsOutputToContain('ohm / principal (hors France)')
            ->assertSuccessful();

        $rows = $this->csv('ohm-osm-non-couverts');
        $this->assertCount(2, $rows);
        $abroad = array_values(array_filter($rows, fn ($r) => str_starts_with($r['proposition'], 'hors France (contexte) : ')));
        $this->assertCount(1, $abroad);
        $this->assertSame('hors France (contexte) : créer « Ligne près de France » (principal)', $abroad[0]['proposition']);
        $this->assertSame('0.00', $abroad[0]['part en France']);
        $home = array_values(array_filter($rows, fn ($r) => ! str_starts_with($r['proposition'], 'hors France')));
        $this->assertSame('1.00', $home[0]['part en France']);
        $this->assertStringStartsWith('créer « Ligne près de France » (principal)', $home[0]['proposition'], 'aucune commune : repli « France »');
    }

    public function test_min_length_and_skip_options(): void
    {
        $this->trace('ohm', 'ohm-a', 'LINESTRING(1.0 45.2, 1.03 45.2)', ['length_m' => 2400]);

        $this->artisan('vff:audit-coverage', ['--min-length' => 3000])
            ->expectsOutputToContain('1 tracés non couverts (< 20 % de leur longueur près d\'une ligne VFF) ; 0 ensembles de ≥ 3000 m à examiner ; 0 voies de service écartées.')
            ->assertSuccessful();
        $this->assertSame([], $this->csv('ohm-osm-non-couverts'));

        File::delete(storage_path('app/reports/audit-coverage/ohm-osm-non-couverts.csv'));
        $this->artisan('vff:audit-coverage', ['--skip-uncovered' => true])
            ->doesntExpectOutputToContain('→ 3.')
            ->assertSuccessful();
        $this->assertFileDoesNotExist(storage_path('app/reports/audit-coverage/ohm-osm-non-couverts.csv'));
    }

    public function test_cluster_rows_and_network_totals(): void
    {
        $long = str_repeat('Voie ferrée de l\'Écluse à Égletons ', 8);
        $this->trace('ohm', 'ohm-a', 'LINESTRING(1.0 45.2, 1.03 45.2)', ['length_m' => 12347, 'name' => $long], ['usage' => 'main']);
        $this->trace('ohm', 'ohm-b', 'LINESTRING(1.0 45.3, 1.03 45.3)', ['length_m' => 2600], ['usage' => 'main']);
        $this->trace('osm', 'osm-c', 'LINESTRING(1.0 45.5, 1.03 45.5)', ['length_m' => 3400], ['usage' => 'military']);

        $this->artisan('vff:audit-coverage')
            ->expectsTable(['Source / catégorie présumée', 'km non couverts'], [['ohm / principal', '15'], ['osm / militaire', '3']])
            ->assertSuccessful();

        $rows = $this->csv('ohm-osm-non-couverts');
        $a = $this->rowContaining($rows, 'km', '12.35');
        $this->assertNotNull($a, 'km arrondis au centième');
        $this->assertSame(200, mb_strlen($a['noms']), 'noms tronqués à 200 caractères');
        $this->assertSame(mb_substr($long, 0, 200), $a['noms']);
    }

    public function test_without_open_data_the_step_is_skipped(): void
    {
        $this->artisan('vff:audit-coverage')
            ->expectsOutputToContain('→ 3. Aucun tracé OHM / OSM en base : étape ignorée.')
            ->doesntExpectOutputToContain('tracés non couverts')
            ->expectsOutputToContain('→ 6. ')
            ->assertSuccessful();
    }

    // ─── 1, 2. synthèse, lignes sans tracé, tracés non rattachés ─────────

    public function test_lines_and_unlinked_traces_lists(): void
    {
        $none = $this->line('Ligne de Bort à Eygurande', 'principal', '602000');
        $sncfOnly = $this->line('Ligne de Felletin à Ussel', 'principal', '603000', ['alt_names' => ['name_source' => 'sncf']]);
        $this->trace('sncf', 's1', 'LINESTRING(2.0 45.0, 2.1 45.0)', ['line_id' => $sncfOnly->id]);
        $sncfOnly2 = $this->line('Ligne de Felletin à Busseau', 'principal', '603500');
        $this->trace('sncf', 's2', 'LINESTRING(2.0 45.05, 2.1 45.05)', ['line_id' => $sncfOnly2->id]);
        $precise = $this->line('Ligne de Meymac à Ussel', 'principal', '604000');
        $this->trace('ohm', 'o1', 'LINESTRING(2.0 45.1, 2.1 45.1)', ['line_id' => $precise->id, 'match_method' => 'name']);
        $this->trace('archeoferroviaire', 'a1', 'LINESTRING(3.0 45.0, 3.1 45.0)', ['layer' => 'exploitee', 'name' => 'Ligne de Bâle à Mulhouse']);
        $this->trace('archeoferroviaire', 'a2', 'LINESTRING(3.0 45.1, 3.1 45.1)', ['layer' => 'secondaire', 'name' => 'Ligne de Tulle à Argentat']);
        $this->trace('archeoferroviaire', 'a3', 'LINESTRING(3.0 45.2, 3.1 45.2)', ['layer' => 'secondaire', 'name' => 'Projet de ligne de Tulle à Ussel']);
        $this->commune('19272', 'Tulle', 3.05, 45.1);

        $dir = storage_path('app/reports/audit-coverage');
        $this->artisan('vff:audit-coverage', ['--skip-uncovered' => true])
            ->expectsOutputToContain('→ Synthèse par catégorie')
            ->expectsTable(['Catégorie', 'Lignes', 'avec tracé précis', 'sans aucun tracé', 'avec code RFN', 'km archeo', 'km SNCF'],
                [['principal', '4', '1', '1', '4', '', '10']])
            ->expectsTable(['Source', 'Rattachement', 'Tracés', 'km'], [
                ['archeoferroviaire', 'non rattaché', '3', '15'],
                ['ohm', 'name', '1', '5'],
                ['sncf', 'non rattaché', '2', '10'],
            ])
            ->expectsOutputToContain('→ 1. Lignes sans tracé précis : 3 (dont 1 sans AUCUN tracé)')
            ->expectsOutputToContain('→ 2. Tracés non rattachés')
            ->expectsOutputToContain("  Lignes sans tracé précis (3) : {$dir}/lignes-sans-trace.csv")
            ->expectsOutputToContain("  Tracés archeoferroviaire non rattachés (3) : {$dir}/traces-archeo-non-rattaches.csv")
            ->expectsOutputToContain("  Noms de lignes en double (0) : {$dir}/noms-en-double.csv")
            ->assertSuccessful();

        $without = $this->csvBy('lignes-sans-trace', 'id');
        $this->assertSame(['aucun tracé', 'tracé SNCF généralisé seulement'],
            [$without[$none->id]['état'], $without[$sncfOnly->id]['état']]);
        $this->assertSame('sncf', $without[$sncfOnly->id]['source du nom']);
        $this->assertArrayNotHasKey($precise->id, $without);

        $unlinked = $this->csvBy('traces-archeo-non-rattaches', 'libellé');
        $this->assertSame('ligne exploitée hors RFN ou à l\'étranger (calque de contexte, pas de création)', $unlinked['Ligne de Bâle à Mulhouse']['raison']);
        $this->assertSame('à examiner', $unlinked['Ligne de Tulle à Argentat']['raison']);
        $this->assertSame('Tulle', $unlinked['Ligne de Tulle à Argentat']['commune proche']);
        $this->assertSame('projet jamais réalisé (volontairement non créé)', $unlinked['Projet de ligne de Tulle à Ussel']['raison']);
        $this->assertSame(['secondaire', '5000'], [$unlinked['Ligne de Tulle à Argentat']['calque'], $unlinked['Ligne de Tulle à Argentat']['longueur_m']]);

        // Rejouable dans la même connexion (tables temporaires recréées).
        $this->artisan('vff:audit-coverage')->assertSuccessful();
        $this->artisan('vff:audit-coverage')->assertSuccessful();
    }

    // ─── 4, 5, 7. noms, catégories, doublons ─────────────────────────────

    public function test_generic_names_category_conflicts_and_duplicates(): void
    {
        $near = $this->line('Ligne près de Tulle', 'secondaire');
        $sector = $this->line('Ligne de Brive (secteur Est)', 'secondaire');
        $numbered = $this->line('Ligne de Brive (2)', 'secondaire');
        $digits = $this->line('Ligne 12 345', 'secondaire');
        $bare = $this->line('12 345', 'secondaire');
        $derived = $this->line('Ligne de Brive à Tulle', 'secondaire', null, ['alt_names' => ['name_source' => 'derived_nearest']]);
        $fine = $this->line('Ligne de Brive à Objat', 'secondaire', null, ['alt_names' => ['name_source' => 'wikipedia']]);

        // a. tracé archeo d'un autre calque que la catégorie.
        $principal = $this->line('Ligne de Paris à Brest', 'principal', '420000');
        $this->trace('archeoferroviaire', 'a-sec', 'LINESTRING(1.0 48.0, 1.1 48.0)', ['line_id' => $principal->id, 'layer' => 'secondaire', 'length_m' => 7500, 'name' => 'Tronçon', 'match_method' => 'name']);
        $this->trace('archeoferroviaire', 'a-exp', 'LINESTRING(1.0 48.1, 1.1 48.1)', ['line_id' => $principal->id, 'layer' => 'exploitee']);
        $this->trace('archeoferroviaire', 'a-same', 'LINESTRING(1.0 48.2, 1.1 48.2)', ['line_id' => $principal->id, 'layer' => 'principal']);
        // b. ligne créée par link-traces qui longe le RFN.
        $created = $this->line('Ligne de Dol à Dinan', 'secondaire', null, ['alt_names' => ['created_by' => 'vff:link-traces']]);
        $t = $this->trace('ohm', 'o-created', 'LINESTRING(1.0 48.3, 1.1 48.3)', ['line_id' => $created->id, 'length_m' => 1000]);
        DB::insert("INSERT INTO source_trace_lines (source_trace_id, line_id, role, method, overlap_m, created_at, updated_at)
            VALUES (?, ?, 'secondary', 'sncf_overlap', 600, now(), now())", [$t, $principal->id]);
        $createdLow = $this->line('Ligne de Dinan à Lamballe', 'secondaire', null, ['alt_names' => ['created_by' => 'vff:link-traces']]);
        $t2 = $this->trace('ohm', 'o-created-low', 'LINESTRING(1.0 48.4, 1.1 48.4)', ['line_id' => $createdLow->id, 'length_m' => 1000]);
        DB::insert("INSERT INTO source_trace_lines (source_trace_id, line_id, role, method, overlap_m, created_at, updated_at)
            VALUES (?, ?, 'secondary', 'sncf_overlap', 400, now(), now())", [$t2, $principal->id]);
        // c. code RFN sur une ligne secondaire.
        $metric = $this->line('Ligne de Saint-Gervais à Vallorcine', 'secondaire', '897000');
        // 7. doublons (casse ignorée).
        $this->line('Ligne de Nice à Digne', 'secondaire');
        $this->line('ligne de nice à digne', 'secondaire');

        $this->artisan('vff:audit-coverage', ['--skip-uncovered' => true])
            ->expectsOutputToContain('→ 4. Lignes au nom encore générique ou dérivé : 6')
            ->expectsOutputToContain('→ 5. Incohérences de catégorie : 3')
            ->expectsOutputToContain('→ 7. Noms en double : 1')
            ->assertSuccessful();

        $generic = array_map('intval', array_column($this->csv('noms-generiques'), 'id'));
        sort($generic);
        $expected = [$near->id, $sector->id, $numbered->id, $digits->id, $bare->id, $derived->id];
        sort($expected);
        $this->assertSame($expected, $generic);
        $this->assertNotContains($fine->id, $generic);

        $conflicts = $this->csvBy('incoherences-categorie', 'cas');
        $a = $conflicts['tracé archeo « secondaire » sur une ligne « principal »'];
        $this->assertSame([(string) $principal->id, '1', '7.5', 'Tronçon', 'name'], [$a['ligne_id'], $a['tracés'], $a['km'], $a['détail'], $a['méthodes']]);
        $b = $conflicts['ligne « secondaire » créée qui longe le RFN'];
        $this->assertSame([(string) $created->id, '0.6', 'longe « Ligne de Paris à Brest » sur 0.60'], [$b['ligne_id'], $b['km'], $b['détail']]);
        $c = $conflicts['code RFN sur une ligne « secondaire »'];
        $this->assertSame([(string) $metric->id, 'code 897000'], [$c['ligne_id'], $c['détail']]);

        $dups = $this->csv('noms-en-double');
        $this->assertSame('ligne de nice à digne', $dups[0]['nom']);
        $this->assertSame('2', $dups[0]['nombre']);
    }

    // ─── 6. Wikidata ─────────────────────────────────────────────────────

    /** @param  list<array<string, string>>  $items */
    private function wikidataCsv(array $items): void
    {
        $head = ['qid', 'libelle', 'categorie', 'codes', 'raison', 'ouverture', 'fermeture', 'longueur_km', 'terminus', 'terminus_coords', 'osm_relation', 'wikidata_url'];
        $fh = fopen(storage_path('app/reports/wikidata-lignes-absentes.csv'), 'w');
        fputcsv($fh, $head);
        foreach ($items as $it) {
            fputcsv($fh, array_map(fn ($k) => $it[$k] ?? '', $head));
        }
        fclose($fh);
    }

    public function test_wikidata_absent_lines_are_matched_by_name_points_or_not_at_all(): void
    {
        // Candidat par le nom qui ne partage qu'une extrémité (créé avant : examiné en premier).
        $decoy = $this->line('Ligne de Bort-les-Orgues à Ussel', 'principal', '604900');
        $this->trace('archeoferroviaire', 'a-decoy', 'LINESTRING(2.2 45.6, 2.3 45.6)', ['line_id' => $decoy->id, 'layer' => 'principal']);
        $traced = $this->line('Ligne de Bort-les-Orgues à Eygurande', 'principal', '605000');
        $this->trace('archeoferroviaire', 'a-bort', 'LINESTRING(2.4 45.4, 2.5 45.5)', ['line_id' => $traced->id, 'layer' => 'principal']);
        $untraced = $this->line('Ligne de Mauriac à Miécaze', 'principal', '606000');
        $created = $this->line('Ligne de Vic à Polminhac', 'secondaire', null, ['alt_names' => ['created_by' => 'vff:link-traces']]);
        $this->trace('ohm', 'o-vic', 'LINESTRING(2.6 44.97, 2.7 44.97)', ['line_id' => $created->id]);
        $other = $this->line('Ligne de Paris à Strasbourg', 'principal', '070000');
        $this->trace('osm', 'w-ps', 'LINESTRING(2.45 48.88, 2.5 48.93)', ['line_id' => $other->id]);
        $this->trace('ohm', 'o-free', 'LINESTRING(3.0 46.0, 3.1 46.0)');

        $this->wikidataCsv([
            ['qid' => 'Q1', 'libelle' => 'ligne de Bort-les-Orgues à Eygurande', 'categorie' => 'ferroviaire', 'codes' => '605000',
                'raison' => 'code absent', 'ouverture' => '1881', 'fermeture' => '1990', 'longueur_km' => '12', 'terminus' => 'Bort ; Eygurande',
                'osm_relation' => '123', 'wikidata_url' => 'https://www.wikidata.org/wiki/Q1'],
            ['qid' => 'Q2', 'libelle' => 'ligne de Mauriac à Miécaze', 'categorie' => 'ferroviaire'],
            ['qid' => 'Q3', 'libelle' => '', 'categorie' => 'ferroviaire', 'codes' => '777000',
                'terminus_coords' => '44.97,2.6 44.97,2.7'],
            ['qid' => 'Q4', 'libelle' => 'ligne de Bondy à Aulnay-sous-Bois', 'categorie' => 'ferroviaire',
                'terminus_coords' => '48.88,2.45 48.93,2.5'],
            ['qid' => 'Q5', 'libelle' => 'Voie ferrée des Combrailles', 'categorie' => 'ferroviaire',
                'terminus_coords' => '46.0,3.0 46.0,3.5'],
            ['qid' => 'Q6', 'libelle' => 'Ligne de Nulle-Part à Ailleurs', 'categorie' => 'urbain'],
            ['qid' => 'Q7', 'libelle' => '   ', 'categorie' => 'urbain'],
            ['qid' => 'Q8', 'libelle' => '', 'categorie' => 'ferroviaire'],
            ['qid' => 'Q9', 'libelle' => '', 'categorie' => 'urbain', 'terminus_coords' => '40.0,-3.0 40.1,-3.1'],
            ['qid' => 'Q10', 'libelle' => '', 'categorie' => 'ferroviaire', 'codes' => '888000', 'terminus_coords' => '45.4,4.4 45.5,4.5'],
            ['qid' => 'Q11', 'libelle' => '', 'categorie' => 'ferroviaire', 'terminus_coords' => '44.97,2.6 44.97,2.7'],
        ]);
        // Q8 : points Wikidata en base (terminus, gares), près du tracé de Bort-les-Orgues.
        DB::insert("INSERT INTO wikidata_lines (qid, category, fetched_at, points, created_at, updated_at)
            VALUES ('Q8', 'ferroviaire', now(), ST_GeomFromText('MULTIPOINT((2.4 45.4), (2.5 45.5))', 4326), now(), now())");
        // Q10 : ligne créée qui porte déjà un code : pas de note.
        $coded = $this->line('Ligne de Saint-Flour à Brioude', 'secondaire', '888000', ['alt_names' => ['created_by' => 'vff:link-traces']]);
        $this->trace('ohm', 'o-sf', 'LINESTRING(4.4 45.4, 4.5 45.5)', ['line_id' => $coded->id]);
        DB::insert("INSERT INTO wikidata_lines (qid, category, fetched_at, points, created_at, updated_at)
            VALUES ('Q6', 'urbain', now(), NULL, now(), now())");

        $this->artisan('vff:audit-coverage', ['--skip-uncovered' => true])
            ->expectsOutputToContain('→ 6. Lignes Wikidata absentes de VFF : 11')
            ->expectsTable(['Catégorie Wikidata', 'Statut', 'Items'], [
                ['ferroviaire', 'déjà dans VFF (autre nom/code)', '2'],
                ['ferroviaire', 'ligne VFF sans tracé précis', '1'],
                ['ferroviaire', 'créée par vff:link-traces', '3'],
                ['ferroviaire', 'tracé présent (osm) mais non rattaché à cette ligne', '1'],
                ['ferroviaire', 'tracé présent (ohm) mais non rattaché à cette ligne', '1'],
                ['urbain', 'aucun tracé', '2'],
                ['urbain', 'aucun tracé (item sans libellé ni coordonnées)', '1'],
            ])
            ->assertSuccessful();

        $rows = $this->csvBy('wikidata-couverture', 'qid');
        $this->assertSame(['déjà dans VFF (autre nom/code)', 'nom', (string) $traced->id, 'archeoferroviaire'],
            [$rows['Q1']['statut'], $rows['Q1']['méthode'], $rows['Q1']['ligne_vff_id'], $rows['Q1']['sources des tracés']]);
        $this->assertSame(['ligne VFF sans tracé précis', 'nom', (string) $untraced->id],
            [$rows['Q2']['statut'], $rows['Q2']['méthode'], $rows['Q2']['ligne_vff_id']]);
        $this->assertSame(['créée par vff:link-traces', 'terminus/points', (string) $created->id, 'code Wikidata 777000 non posé (ambigu ou déjà pris)'],
            [$rows['Q3']['statut'], $rows['Q3']['méthode'], $rows['Q3']['ligne_vff_id'], $rows['Q3']['note']]);
        $this->assertSame('tracé présent (osm) mais non rattaché à cette ligne', $rows['Q4']['statut']);
        $this->assertSame('', $rows['Q4']['ligne_vff_id'], 'Bondy - Aulnay ne devient pas Paris - Strasbourg');
        $this->assertSame("2/2 points Wikidata près d'un tracé ; terminus près de « Ligne de Paris à Strasbourg » (#{$other->id}), autre ligne probable", $rows['Q4']['note']);
        $this->assertSame(['tracé présent (ohm) mais non rattaché à cette ligne', "1/2 points Wikidata près d'un tracé"],
            [$rows['Q5']['statut'], $rows['Q5']['note']]);
        $this->assertSame('aucun tracé', $rows['Q6']['statut']);
        $this->assertSame('aucun tracé (item sans libellé ni coordonnées)', $rows['Q7']['statut']);
        $this->assertSame(['déjà dans VFF (autre nom/code)', 'terminus/points', (string) $traced->id], [$rows['Q8']['statut'], $rows['Q8']['méthode'], $rows['Q8']['ligne_vff_id']]);
        $this->assertSame('aucun tracé', $rows['Q9']['statut'], 'coordonnées sans libellé : pas « sans coordonnées »');
        $this->assertSame(['créée par vff:link-traces', ''], [$rows['Q10']['statut'], $rows['Q10']['note']]);
        $this->assertSame(['créée par vff:link-traces', ''], [$rows['Q11']['statut'], $rows['Q11']['note']], 'sans code Wikidata : pas de note');
        $this->assertSame(['Q1', 'ligne de Bort-les-Orgues à Eygurande', 'ferroviaire', '605000', 'code absent', '1881', '1990', '12', 'Bort ; Eygurande',
            'déjà dans VFF (autre nom/code)', 'nom', (string) $traced->id, 'Ligne de Bort-les-Orgues à Eygurande', 'principal', '605000', 'archeoferroviaire', '',
            '123', 'https://www.wikidata.org/wiki/Q1'], array_values($rows['Q1']));

        $this->assertSame(['Q6', 'Q7', 'Q9'], array_column($this->csv('wikidata-sans-trace'), 'qid'));
    }

    public function test_wikidata_step_is_skipped_without_its_file(): void
    {
        $this->artisan('vff:audit-coverage', ['--skip-uncovered' => true])
            ->expectsOutputToContain('wikidata-lignes-absentes.csv absent : étape Wikidata ignorée.')
            ->assertSuccessful();
        $this->assertFileDoesNotExist(storage_path('app/reports/audit-coverage/wikidata-couverture.csv'));
    }
}
