<?php

namespace Tests\Feature\Commands;

use App\Models\Line;
use App\Services\PlaceNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * vff:merge-split-lines : les cinq passes (sections, même article,
 * couverture, orphelines, homonymes), chacune sur un cas accepté et sur ses
 * cas refusés, plus --dry-run, --only et la réutilisation dans un même
 * processus (vff:rebuild-history).
 *
 * Repères : à 46° N, 0,1° de longitude ≈ 7,7 km.
 */
class MergeSplitLinesCommandTest extends TestCase
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
    private function line(string $name, ?string $code, array $attributes = []): Line
    {
        return Line::factory()->create($attributes + [
            'name' => $name,
            'slug' => Str::slug($name).'-'.random_int(1000, 999999),
            'code' => $code,
            'network' => 'principal',
            'type' => null,
            'wikipedia_title' => null,
            'length_official_m' => null,
        ]);
    }

    /** Ligne créée par vff:link-traces depuis un tracé OHM (géométrie de travail lm_c). */
    private function created(string $name, string $wkt, array $attributes = []): Line
    {
        $line = $this->line($name, null, $attributes + ['alt_names' => ['created_by' => 'vff:link-traces', 'source' => 'ohm']]);
        $this->trace('ohm', 'c'.$line->id, $wkt, $line->id);

        return $line;
    }

    private function trace(string $source, string $ref, string $wkt, ?int $lineId, ?string $name = null, ?string $method = null): int
    {
        return (int) DB::selectOne("INSERT INTO source_traces (source, source_ref, layer, line_id, name, length_m, match_method, seen_at, geom)
            VALUES (?, ?, 'rail', ?, ?, ROUND(ST_Length(ST_GeomFromText(?, 4326)::geography)), ?, now(), ST_Multi(ST_GeomFromText(?, 4326))) RETURNING id",
            [$source, $ref, $lineId, $name, $wkt, $method, $wkt])->id;
    }

    private function reference(Line $line, string $wkt): void
    {
        DB::insert("INSERT INTO line_reference_geometries (line_id, length_m, sources, geom, created_at, updated_at)
            VALUES (?, ROUND(ST_Length(ST_GeomFromText(?, 4326)::geography)), '[]'::jsonb, ST_Multi(ST_GeomFromText(?, 4326)), now(), now())",
            [$line->id, $wkt, $wkt]);
    }

    private function gare(string $code, string $name, ?float $lng = null, ?float $lat = null, ?int $pk = null): void
    {
        DB::table('sncf_gares')->insert(['code_ligne' => $code, 'libelle' => $name, 'lat' => $lat, 'lon' => $lng, 'pk_m' => $pk]);
    }

    /** Gare VFF rattachée à la ligne par une source (Wikidata P81 par défaut). */
    private function attachedStation(Line $line, string $name, float $lng, float $lat, string $method = 'wikidata_p81'): void
    {
        $id = DB::selectOne('INSERT INTO stations (slug, name, geom, created_at, updated_at)
            VALUES (?, ?, ST_SetSRID(ST_MakePoint(?, ?), 4326), now(), now()) RETURNING id',
            [Str::slug($name).'-'.random_int(1000, 999999), $name, $lng, $lat])->id;
        DB::table('station_lines')->insert(['station_id' => $id, 'line_id' => $line->id, 'method' => $method,
            'created_at' => now(), 'updated_at' => now()]);
    }

    private function commune(string $insee, string $name, float $lng, float $lat): void
    {
        DB::insert("INSERT INTO communes (code_insee, type, nom, nom_norm, geom, created_at, updated_at)
            VALUES (?, 'commune-actuelle', ?, ?, ST_SetSRID(ST_MakePoint(?, ?), 4326), now(), now())",
            [$insee, $name, PlaceNames::normalize($name), $lng, $lat]);
    }

    /** @param  array<string, mixed>  $attributes */
    private function event(Line $line, string $source, string $url, array $attributes = []): int
    {
        return (int) DB::table('line_events')->insertGetId($attributes + [
            'line_id' => $line->id, 'type' => 'opened', 'event_date' => '1880-06-01', 'description' => 'Ouverture',
            'source' => $source, 'source_url' => $url, 'confidence' => 'high', 'date_precision' => 'day',
            'section_resolution' => 'resolved', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** @return list<array<string, string>> */
    private function csv(bool $dry = false): array
    {
        $fh = fopen(storage_path('app/reports/merge-split-lines'.($dry ? '-dry-run' : '').'.csv'), 'r');
        $head = fgetcsv($fh);
        $rows = [];
        while (($r = fgetcsv($fh)) !== false) {
            $rows[] = array_combine($head, $r);
        }
        fclose($fh);

        return $rows;
    }

    /** @return array<string, string> ligne du rapport pour une ligne source donnée */
    private function rowFor(int|string $lineId, ?string $phase = null, bool $dry = false): array
    {
        foreach ($this->csv($dry) as $r) {
            if ($r['line_id'] === (string) $lineId && ($phase === null || $r['phase'] === $phase)) {
                return $r;
            }
        }
        $this->fail("aucune ligne du rapport pour {$lineId}");
    }

    // ─── options ─────────────────────────────────────────────────────────

    public function test_unknown_phase_is_refused(): void
    {
        $this->artisan('vff:merge-split-lines', ['--only' => 'sections,doublons'])
            ->expectsOutputToContain('Passe(s) inconnue(s) : doublons')
            ->assertExitCode(2);
        $this->assertFileDoesNotExist(storage_path('app/reports/merge-split-lines.csv'));
    }

    public function test_empty_database_runs_every_phase(): void
    {
        $this->artisan('vff:merge-split-lines')
            ->expectsOutputToContain('→ 1. Sections créées à part : 0')
            ->expectsOutputToContain('→ 2. Lignes créées portant l\'article d\'une ligne à code : 0')
            ->expectsOutputToContain('→ 3. Lignes créées contenant le tracé connu d\'une ligne à code incomplète : 0')
            ->expectsOutputToContain('→ 4. Lignes à code sans aucun tracé : 0')
            ->expectsOutputToContain('→ 5. Événements Wikipédia d\'un article homonyme : 0 sur 0 ligne(s)')
            ->doesntExpectOutputToContain('Relancer vff:build-line-references')
            ->assertSuccessful();
        $this->assertSame([], $this->csv());
    }

    // ─── 1. sections ─────────────────────────────────────────────────────

    private function sectionsFixture(): array
    {
        // Sézanne : terminus partagé ; Esternay est une gare de la ligne.
        $existing = $this->line('Ligne de Coulommiers à Sézanne', '100000', ['length_official_m' => 15500]);
        $this->reference($existing, 'LINESTRING(2.0 46.0, 2.1 46.0)');
        $this->gare('100000', 'Esternay');
        $section = $this->line('Ligne d\'Esternay à Sézanne', null, ['slug' => 'archeo-esternay-sezanne']);
        $this->reference($section, 'LINESTRING(2.1 46.0, 2.2 46.0)');
        $t = $this->trace('archeoferroviaire', 'a-est', 'LINESTRING(2.1 46.0, 2.2 46.0)', $section->id, null, 'created');

        // Même ORIGINE (« Mézidon à Dives », première section de « Mézidon à Trouville-Deauville »).
        $origin = $this->line('Ligne de Mézidon à Trouville-Deauville', '101000', ['length_official_m' => 15500]);
        $this->reference($origin, 'LINESTRING(2.0 46.5, 2.1 46.5)');
        $this->gare('101000', 'Dives');
        $first = $this->line('Ligne de Mézidon à Dives', null, ['slug' => 'archeo-mezidon-dives']);
        $this->reference($first, 'LINESTRING(2.1 46.5, 2.2 46.5)');

        // Même terminus, mais l'origine n'est pas un lieu de la ligne : autre ligne.
        $other = $this->line('Ligne de Somain à Halluin', '102000', ['length_official_m' => 15500]);
        $this->reference($other, 'LINESTRING(2.0 47.0, 2.1 47.0)');
        $foreign = $this->line('Ligne d\'Armentières à Halluin', null, ['slug' => 'archeo-armentieres-halluin']);
        $this->reference($foreign, 'LINESTRING(2.1 47.0, 2.2 47.0)');

        // Longueurs incompatibles (la section n'est pas le complément) : pas candidate.
        $long = $this->line('Ligne de Paris à Melun', '103000', ['length_official_m' => 40000]);
        $this->reference($long, 'LINESTRING(2.0 47.5, 2.1 47.5)');
        $this->gare('103000', 'Juvisy');
        $short = $this->line('Ligne de Juvisy à Melun', null, ['slug' => 'archeo-juvisy-melun']);
        $this->reference($short, 'LINESTRING(2.1 47.5, 2.2 47.5)');

        // Tracé complet (≥ 85 % de l'officiel) : rien à compléter.
        $full = $this->line('Ligne de Gisors à Pont', '104000', ['length_official_m' => 8000]);
        $this->reference($full, 'LINESTRING(2.0 48.0, 2.1 48.0)');
        $this->gare('104000', 'Serqueux');
        $extra = $this->line('Ligne de Serqueux à Pont', null, ['slug' => 'archeo-serqueux-pont']);
        $this->reference($extra, 'LINESTRING(2.1 48.0, 2.11 48.0)');

        return compact('existing', 'section', 't', 'origin', 'first', 'other', 'foreign', 'short', 'extra');
    }

    public function test_sections_are_merged_into_the_incomplete_line(): void
    {
        $f = $this->sectionsFixture();

        $this->artisan('vff:merge-split-lines', ['--only' => 'sections'])
            ->expectsOutputToContain('→ 1. Sections créées à part : 3')
            ->expectsOutputToContain('✓ Ligne d\'Esternay à Sézanne → Ligne de Coulommiers à Sézanne : section 7.7 km + tracé 7.7 km ≈ 15.5 km officiels')
            ->expectsTable(['Passe : décision', 'Lignes'], [['sections : fusionné', '2'], ['sections : rejeté', '1']])
            ->expectsOutputToContain('Relancer vff:build-line-references puis vff:build-era-segments sur les lignes cibles.')
            ->doesntExpectOutputToContain('→ 2.')
            ->assertSuccessful();

        $this->assertDatabaseMissing('lines', ['id' => $f['section']->id]);
        $this->assertDatabaseMissing('lines', ['id' => $f['first']->id]);
        $this->assertDatabaseHas('lines', ['id' => $f['foreign']->id]);
        $this->assertDatabaseHas('lines', ['id' => $f['short']->id]);
        $this->assertDatabaseHas('lines', ['id' => $f['extra']->id]);
        $this->assertDatabaseHas('source_traces', ['id' => $f['t'], 'line_id' => $f['existing']->id, 'match_method' => 'merged_section']);

        $alt = json_decode(DB::table('lines')->where('id', $f['existing']->id)->value('alt_names'), true);
        $this->assertSame(['Ligne d\'Esternay à Sézanne'], $alt['sections']);
        $this->assertSame('archeo-esternay-sezanne', $alt['merged'][0]['slug']);
        $this->assertStringStartsWith('vff:merge-split-lines (sections) : section 7.7 km', $alt['merged'][0]['reason']);
        $alt = json_decode(DB::table('lines')->where('id', $f['origin']->id)->value('alt_names'), true);
        $this->assertSame(['Ligne de Mézidon à Dives'], $alt['sections']);

        $merged = $this->rowFor($f['section']->id);
        $this->assertSame(['sections', 'fusionné', (string) $f['existing']->id, '100000'],
            [$merged['phase'], $merged['decision'], $merged['target_id'], $merged['target_code']]);
        $this->assertStringEndsWith(' ; 1 tracé(s), 0 événement(s) déplacé(s), 0 en double supprimé(s)', $merged['detail']);
        $rejected = $this->rowFor($f['foreign']->id);
        $this->assertSame(['rejeté', 'origine « Armentières » étrangère à la ligne (même terminus seulement)'],
            [$rejected['decision'], $rejected['detail']]);
        $this->assertCount(3, $this->csv());
    }

    public function test_sections_dry_run_writes_nothing(): void
    {
        $f = $this->sectionsFixture();
        $before = $this->snapshot(['lines', 'source_traces']);

        $this->artisan('vff:merge-split-lines', ['--only' => 'sections', '--dry-run' => true])
            ->expectsOutputToContain('[dry-run] Ligne d\'Esternay à Sézanne → Ligne de Coulommiers à Sézanne')
            ->expectsOutputToContain('Simulation (--dry-run) : rien n\'a été écrit.')
            ->doesntExpectOutputToContain('Relancer vff:build-line-references')
            ->assertSuccessful();

        $this->assertSame($before, $this->snapshot(['lines', 'source_traces']));
        $this->assertFileDoesNotExist(storage_path('app/reports/merge-split-lines.csv'));
        $row = $this->rowFor($f['section']->id, dry: true);
        $this->assertSame('à fusionner', $row['decision']);
        $this->assertSame('section 7.7 km + tracé 7.7 km ≈ 15.5 km officiels', $row['detail'], 'sans bilan de fusion en simulation');
    }

    public function test_a_commune_is_on_the_line_of_a_non_commune_place_starting_with_its_name(): void
    {
        // « Les Loges » (commune) : la gare « Les Loges-Vaucottes-sur-Mer » (pas une commune) est sur la ligne.
        $this->commune('76390', 'Les Loges', 2.0, 49.7);
        $ifs = $this->line('Ligne des Ifs à Étretat', '360000', ['length_official_m' => 15500]);
        $this->reference($ifs, 'LINESTRING(2.0 46.0, 2.1 46.0)');
        $this->gare('360000', 'Les Loges-Vaucottes-sur-Mer');
        $loges = $this->line('Ligne des Loges à Etretat', null, ['slug' => 'archeo-loges-etretat']);
        $this->reference($loges, 'LINESTRING(2.1 46.0, 2.2 46.0)');

        // « Saint-Denis » (commune) : le lieu connu « Saint-Denis-lès-Martel » est lui-même une commune.
        $this->commune('93066', 'Saint-Denis', 2.36, 48.94);
        $this->commune('46264', 'Saint-Denis-lès-Martel', 1.65, 44.94);
        $souillac = $this->line('Ligne de Souillac à Viescamp', '700000', ['length_official_m' => 15500]);
        $this->reference($souillac, 'LINESTRING(2.0 46.5, 2.1 46.5)');
        $this->gare('700000', 'Saint-Denis-lès-Martel');
        $denis = $this->line('Ligne de Saint-Denis à Viescamp', null, ['slug' => 'archeo-saint-denis-viescamp']);
        $this->reference($denis, 'LINESTRING(2.1 46.5, 2.2 46.5)');

        // « Tours » (commune) : « Joué-lès-Tours » ne COMMENCE pas par « Tours- ».
        $this->commune('37261', 'Tours', 0.69, 47.39);
        $joue = $this->line('Ligne de Joué-lès-Tours à Chinon', '701000', ['length_official_m' => 15500]);
        $this->reference($joue, 'LINESTRING(2.0 47.0, 2.1 47.0)');
        $tours = $this->line('Ligne de Tours à Chinon', null, ['slug' => 'archeo-tours-chinon']);
        $this->reference($tours, 'LINESTRING(2.1 47.0, 2.2 47.0)');

        $this->artisan('vff:merge-split-lines', ['--only' => 'sections'])
            ->expectsTable(['Passe : décision', 'Lignes'], [['sections : fusionné', '1'], ['sections : rejeté', '2']])
            ->assertSuccessful();

        $this->assertDatabaseMissing('lines', ['id' => $loges->id]);
        $this->assertSame((string) $ifs->id, $this->rowFor($loges->id)['target_id']);
        $this->assertDatabaseHas('lines', ['id' => $denis->id]);
        $this->assertSame('origine « Saint-Denis » étrangère à la ligne (même terminus seulement)', $this->rowFor($denis->id)['detail']);
        $this->assertDatabaseHas('lines', ['id' => $tours->id]);
        $this->assertSame('origine « Tours » étrangère à la ligne (même terminus seulement)', $this->rowFor($tours->id)['detail']);
    }

    public function test_sections_named_with_a_contraction_or_the_other_way_round_are_merged(): void
    {
        // « au » : « Port-de-Piles au Blanc » (archeo) est la première section de 598000, dont le tracé connu
        // ne va que du Blanc à Argenton (relevé du 22/11/2026 : 41,7 km sur 102,2).
        $argenton = $this->line('Ligne de Port-de-Piles à Argenton-sur-Creuse', '598000', ['length_official_m' => 15500]);
        $this->reference($argenton, 'LINESTRING(2.1 46.0, 2.2 46.0)');
        $this->gare('598000', 'Le Blanc');
        $blanc = $this->line('Ligne de Port-de-Piles au Blanc', null, ['slug' => 'archeo-ligne-de-port-de-piles-au-blanc']);
        $this->reference($blanc, 'LINESTRING(2.0 46.0, 2.1 46.0)');
        $t = $this->trace('archeoferroviaire', 'a-ppb', 'LINESTRING(2.0 46.0, 2.1 46.0)', $blanc->id, 'Port-de-Piles Le Blanc', 'created');
        // Part du même terminus, mais La Châtre n'est pas un lieu de la ligne : autre ligne.
        $chatre = $this->line('Ligne d\'Argenton-sur-Creuse à La Châtre', null, ['slug' => 'archeo-argenton-la-chatre']);
        $this->reference($chatre, 'LINESTRING(2.2 46.0, 2.3 46.0)');

        // Autre sens, origine de la section = arrivée de la ligne : « Abbeville à Saint-Pol », section de 289000.
        $fives = $this->line('Ligne de Fives à Abbeville', '289000', ['length_official_m' => 15500]);
        $this->reference($fives, 'LINESTRING(2.0 46.5, 2.1 46.5)');
        $this->gare('289000', 'St-Pol-sur-Ternoise');
        $pol = $this->line('Ligne d\'Abbeville à Saint-Pol', null, ['slug' => 'archeo-abbeville-saint-pol']);
        $this->reference($pol, 'LINESTRING(2.1 46.5, 2.2 46.5)');

        // Autre sens, arrivée de la section = origine de la ligne.
        $neuville = $this->line('Ligne de Neuville-de-Poitou à Bressuire', '524000', ['length_official_m' => 15500]);
        $this->reference($neuville, 'LINESTRING(2.0 47.0, 2.1 47.0)');
        $this->gare('524000', 'Parthenay');
        $parthenay = $this->line('Ligne de Parthenay à Neuville-de-Poitou', null, ['slug' => 'archeo-parthenay-neuville']);
        $this->reference($parthenay, 'LINESTRING(2.1 47.0, 2.2 47.0)');

        // « au » dans les deux noms, terminus partagé.
        $laon = $this->line('Ligne de Laon au Cateau', '236000', ['length_official_m' => 15500]);
        $this->reference($laon, 'LINESTRING(2.0 47.5, 2.1 47.5)');
        $this->gare('236000', 'Wassigny');
        $wassigny = $this->line('Ligne de Wassigny au Cateau', null, ['slug' => 'archeo-wassigny-le-cateau']);
        $this->reference($wassigny, 'LINESTRING(2.1 47.5, 2.2 47.5)');

        $this->artisan('vff:merge-split-lines', ['--only' => 'sections'])
            ->expectsOutputToContain('→ 1. Sections créées à part : 5')
            ->expectsOutputToContain('✓ Ligne de Port-de-Piles au Blanc → Ligne de Port-de-Piles à Argenton-sur-Creuse : section 7.7 km + tracé 7.7 km ≈ 15.5 km officiels')
            ->expectsTable(['Passe : décision', 'Lignes'], [['sections : fusionné', '4'], ['sections : rejeté', '1']])
            ->assertSuccessful();

        $this->assertDatabaseHas('source_traces', ['id' => $t, 'line_id' => $argenton->id, 'match_method' => 'merged_section']);
        foreach ([[$blanc, $argenton], [$pol, $fives], [$parthenay, $neuville], [$wassigny, $laon]] as [$section, $target]) {
            $this->assertDatabaseMissing('lines', ['id' => $section->id]);
            $this->assertSame((string) $target->id, $this->rowFor($section->id)['target_id'], $section->name);
            $alt = json_decode(DB::table('lines')->where('id', $target->id)->value('alt_names'), true);
            $this->assertSame([$section->name], $alt['sections']);
        }
        $this->assertDatabaseHas('lines', ['id' => $chatre->id]);
        $rejected = $this->rowFor($chatre->id);
        $this->assertSame(['rejeté', (string) $argenton->id, 'destination « La Châtre » étrangère à la ligne (part du terminus seulement)'],
            [$rejected['decision'], $rejected['target_id'], $rejected['detail']]);
    }

    public function test_sections_without_a_shared_end_are_merged_with_a_proof(): void
    {
        // Section du milieu (3180 dans 422000) : ses deux toponymes sont des gares de la ligne, l'écart de leurs PK
        // (7,8 km) retrouve la longueur de la section (7,7 km), et ses deux bouts touchent le tracé connu.
        $loupe = $this->line('Ligne de La Loupe à Prey', '422000', ['length_official_m' => 23500]);
        $this->reference($loupe, 'MULTILINESTRING((2.0 46.0, 2.1 46.0), (2.2 46.0, 2.3 46.0))');
        $this->gare('422000', 'Senonches', pk: 7700);
        $this->gare('422000', 'Breteuil', pk: 15500);
        $breteuil = $this->line('Ligne de Breteuil à Senonches', null, ['slug' => 'archeo-breteuil-senonches']);
        $this->reference($breteuil, 'LINESTRING(2.2 46.0, 2.1 46.0)');
        $t = $this->trace('archeoferroviaire', 'a-bs', 'LINESTRING(2.2 46.0, 2.1 46.0)', $breteuil->id, 'Breteuil Senonches', 'created');

        // Nom de gare en origine (3268 dans 738000) : « Montauban » est le début de « Montauban-Ville-Bourbon ».
        $this->commune('82121', 'Montauban', 2.0, 45.0);
        $cremade = $this->line('Ligne de Montauban-Ville-Bourbon à La Crémade', '738000', ['length_official_m' => 16000]);
        $this->reference($cremade, 'LINESTRING(2.1 45.0, 2.2 45.0)');
        $this->gare('738000', 'St-Sulpice');
        $montauban = $this->line('Ligne de Montauban à Saint-Sulpice', null, ['slug' => 'archeo-montauban-saint-sulpice']);
        $this->reference($montauban, 'LINESTRING(2.0 45.0, 2.1 45.0)');

        // Gares communes (3258 dans 553000) : « Massy » n'est pas un lieu connu de la ligne, mais trois gares que
        // Wikidata rattache à la ligne sont sur la section, loin de son tracé connu.
        $chartres = $this->line('Ligne d\'Ouest-Ceinture à Chartres', '553000', ['length_official_m' => 17000]);
        $this->reference($chartres, 'LINESTRING(2.1 44.0, 2.2 44.0)');
        $this->gare('553000', 'Gallardon-Pont');
        foreach (['Ablis' => 2.02, 'Bonnelles' => 2.04, 'Limours-État' => 2.06] as $name => $lng) {
            $this->attachedStation($chartres, $name, $lng, 44.0);
        }
        $massy = $this->line('Ligne de Massy à Gallardon', null, ['slug' => 'archeo-massy-gallardon']);
        $this->reference($massy, 'LINESTRING(2.0 44.0, 2.1 44.0)');

        // Prolongement (3165 dans 682000) : « Les Bornes » n'est pas un lieu de la ligne, mais l'autre bout est à
        // Beaune-la-Rolande, cité par un événement de la ligne, et le bout des Bornes touche le tracé connu.
        $this->commune('45025', 'Beaune-la-Rolande', 2.0, 45.5);
        $bourges = $this->line('Ligne d\'Auxy-Juranville à Bourges', '682000', ['length_official_m' => 16000]);
        $this->reference($bourges, 'LINESTRING(2.1 45.5, 2.2 45.5)');
        $this->gare('682000', 'Les Bordes');
        $this->event($bourges, 'wikipedia', 'https://fr.wikipedia.org/wiki/Ligne_d%27Auxy-Juranville_%C3%A0_Bourges',
            ['type' => 'closure_freight', 'section_from' => 'Beaune-la-Rolande', 'section_to' => 'Lorris']);
        $beaune = $this->line('Ligne de Beaune-la-Rolande aux Bornes', null, ['slug' => 'archeo-beaune-la-rolande-aux-bornes']);
        $this->reference($beaune, 'LINESTRING(2.0 45.5, 2.1 45.5)');

        // Sans longueur officielle (3237 dans 696000) : deux gares de la ligne et l'écart de leurs PK.
        $gozet = $this->line('Ligne de Châteauroux à La Ville-Gozet', '696000');
        $this->reference($gozet, 'LINESTRING(2.0 46.5, 2.1 46.5)');
        $this->gare('696000', 'La Châtre', pk: 298800);
        $this->gare('696000', 'La Ville-Gozet', pk: 306500);
        $chatre = $this->line('Ligne de La Châtre à La Ville-Gozet', null, ['slug' => 'archeo-la-chatre-la-ville-gozet']);
        $this->reference($chatre, 'LINESTRING(2.1 46.5, 2.2 46.5)');

        // Refusée par la règle des extrémités partagées (3312 dans 719000 : « Saint-Denis-près-Martel » n'est ni une
        // gare ni un nom de la ligne), prouvée par la portion d'un événement de la ligne : la ligne du rapport est remplacée.
        $viescamp = $this->line('Ligne de Souillac à Viescamp-sous-Jallès', '719000', ['length_official_m' => 16000]);
        $this->reference($viescamp, 'LINESTRING(2.1 44.5, 2.2 44.5)');
        $this->event($viescamp, 'wikipedia', 'https://fr.wikipedia.org/wiki/Ligne_de_Souillac_%C3%A0_Viescamp-sous-Jall%C3%A8s',
            ['section_from' => 'Souillac', 'section_to' => 'Saint-Denis-près-Martel']);
        $denis = $this->line('Ligne de Souillac à Saint-Denis-près-Martel', null, ['slug' => 'archeo-souillac-saint-denis']);
        $this->reference($denis, 'LINESTRING(2.0 44.5, 2.1 44.5)');

        $this->artisan('vff:merge-split-lines', ['--only' => 'sections'])
            ->expectsOutputToContain('→ 1. Sections créées à part : 1')
            ->expectsOutputToContain('→ 1 bis. Sections sans extrémité partagée prouvées : 6')
            ->expectsOutputToContain('✓ Ligne de Breteuil à Senonches → Ligne de La Loupe à Prey : section sans extrémité partagée : section 7.7 km + tracé 15.5 km ≈ 23.5 km officiels ; deux toponymes sur la ligne ; PK 15.5 → 7.7 km')
            ->expectsTable(['Passe : décision', 'Lignes'], [['sections : fusionné', '6']])
            ->assertSuccessful();

        $this->assertDatabaseHas('source_traces', ['id' => $t, 'line_id' => $loupe->id, 'match_method' => 'merged_section']);
        foreach ([[$breteuil, $loupe], [$montauban, $cremade], [$massy, $chartres], [$beaune, $bourges], [$chatre, $gozet], [$denis, $viescamp]] as [$section, $target]) {
            $this->assertDatabaseMissing('lines', ['id' => $section->id]);
            $this->assertSame((string) $target->id, $this->rowFor($section->id)['target_id'], $section->name);
            $alt = json_decode(DB::table('lines')->where('id', $target->id)->value('alt_names'), true);
            $this->assertSame([$section->name], $alt['sections'], $section->name);
        }
        $this->assertStringEndsWith('≈ 16.0 km officiels ; deux toponymes sur la ligne ; 0 tracé(s), 0 événement(s) déplacé(s), 0 en double supprimé(s)',
            $this->rowFor($montauban->id)['detail'], 'sans PK pour « Montauban » : pas d\'écart affiché');
        $this->assertStringContainsString('3 gares de la ligne sur la section, loin du tracé connu (Ablis, Bonnelles, Limours-État)',
            $this->rowFor($massy->id)['detail']);
        $this->assertStringContainsString('prolonge le tracé connu jusqu\'à « Beaune-la-Rolande »', $this->rowFor($beaune->id)['detail']);
        $this->assertStringContainsString('(sans longueur officielle) ; deux toponymes sur la ligne ; PK 298.8 → 306.5 km', $this->rowFor($chatre->id)['detail']);
        $this->assertSame(['fusionné'], array_column(array_filter($this->csv(), fn (array $r) => $r['line_id'] === (string) $denis->id), 'decision'));
        $this->assertCount(6, $this->csv());
    }

    public function test_sections_without_a_shared_end_never_merge_another_line(): void
    {
        // Autre ligne qui part d'une gare de la ligne (3844 « Mortagne à La Loupe » contre 422000) : refusée par la
        // règle des extrémités partagées, la nouvelle règle n'y ajoute rien (La Loupe est le bout qui touche le tracé).
        $this->commune('28214', 'La Loupe', 2.1, 46.0);
        $this->commune('61293', 'Mortagne-au-Perche', 2.0, 46.0);
        $loupe = $this->line('Ligne de La Loupe à Prey', '422000', ['length_official_m' => 18000]);
        $this->reference($loupe, 'LINESTRING(2.1 46.0, 2.2 46.0)');
        $this->gare('422000', 'La Loupe');
        $mortagne = $this->line('Ligne de Mortagne à La Loupe', null, ['slug' => 'archeo-mortagne-la-loupe']);
        $this->reference($mortagne, 'LINESTRING(2.0 46.0, 2.1 46.0)');

        // Autre ligne qui aboutit au tracé (4104 « Thionville à Fontoy » contre 180000) : aucune extrémité partagée,
        // Thionville est sur la ligne mais au bout qui touche son tracé ; Fontoy n'en est pas un lieu.
        $this->commune('57672', 'Thionville', 2.1, 45.0);
        $this->commune('57227', 'Fontoy', 2.0, 45.0);
        $metz = $this->line('Ligne de Metz-Ville à Zoufftgen', '180000', ['length_official_m' => 16000]);
        $this->reference($metz, 'LINESTRING(2.1 45.0, 2.2 45.0)');
        $this->gare('180000', 'Thionville');
        $fontoy = $this->line('Ligne de Thionville à Fontoy', null, ['slug' => 'archeo-thionville-fontoy']);
        $this->reference($fontoy, 'LINESTRING(2.0 45.0, 2.1 45.0)');

        // Section à relire (3170 « Bettrechies à Dour », Dour en Belgique) : même cas que Thionville.
        $this->commune('59074', 'Bettrechies', 2.1, 44.5);
        $gussignies = $this->line('Ligne d\'Escaudœuvres à Gussignies', '251000', ['length_official_m' => 16000]);
        $this->reference($gussignies, 'LINESTRING(2.1 44.5, 2.2 44.5)');
        $this->gare('251000', 'Bettrechies');
        $dour = $this->line('Ligne de Bettrechies à Dour', null, ['slug' => 'archeo-bettrechies-dour']);
        $this->reference($dour, 'LINESTRING(2.0 44.5, 2.1 44.5)');

        // Section à relire (3325 « Villiers au Tronchot ») et autre ligne (3655 « Genève-Carouge à Etrembières ») :
        // aucun toponyme sur la ligne (« Villiers » est une commune, « Villiers-St-Georges » aussi), pas de ligne au rapport.
        $this->commune('36246', 'Villiers', 2.0, 44.0);
        $this->commune('77518', 'Villiers-Saint-Georges', 2.1, 44.0);
        $esternay = $this->line('Ligne de Longueville à Esternay', '003000', ['length_official_m' => 16000]);
        $this->reference($esternay, 'LINESTRING(2.1 44.0, 2.2 44.0)');
        $this->gare('003000', 'Villiers-St-Georges');
        $tronchot = $this->line('Ligne de Villiers au Tronchot', null, ['slug' => 'archeo-villiers-au-tronchot']);
        $this->reference($tronchot, 'LINESTRING(2.1 44.0, 2.0 44.0)');
        $annemasse = $this->line('Ligne de Lausanne à Annemasse (frontière)', '894000', ['length_official_m' => 16000]);
        $this->reference($annemasse, 'LINESTRING(2.1 43.5, 2.2 43.5)');
        $geneve = $this->line('Ligne de Genève-Carouge à Etrembières', null, ['slug' => 'archeo-geneve-carouge-etrembieres']);
        $this->reference($geneve, 'LINESTRING(2.0 43.5, 2.1 43.5)');

        // Court morceau près d'une gare de la ligne (4103 « Thionville à Basse-Yutz » contre 180000) : la section
        // comble moins de la moitié du manque.
        $zoufftgen = $this->line('Ligne de Metz-Ville à Zoufftgen', '180000', ['length_official_m' => 65000]);
        $this->reference($zoufftgen, 'LINESTRING(2.0 43.0, 2.65 43.0)');
        $this->gare('180000', 'Thionville');
        $yutz = $this->line('Ligne de Thionville à Basse-Yutz', null, ['slug' => 'archeo-thionville-basse-yutz']);
        $this->reference($yutz, 'LINESTRING(2.65 43.0, 2.7 43.0)');

        // Prolongement apparent (4340 « Bricon à Buxières-lès-Villiers » contre 840000) : Bricon est près du bout libre,
        // mais déjà à moins de 1 km du tracé connu ; la section n'y mène pas.
        $this->commune('52072', 'Bricon', 2.095, 41.5);
        $chatillon = $this->line('Ligne de Bricon à Châtillon-sur-Seine', '840000', ['length_official_m' => 10000]);
        $this->reference($chatillon, 'LINESTRING(2.1 41.5, 2.2 41.5)');
        $buxieres = $this->line('Ligne militaire de Bricon à Buxières-lès-Villiers', null, ['slug' => 'archeo-bricon-buxieres']);
        $this->reference($buxieres, 'LINESTRING(2.07 41.5, 2.1 41.5)');

        // Deux gares de la ligne, mais la section reprend le tracé connu sur 1,7 km de ses 10 km : pas un morceau manquant.
        $arcachon = $this->line('Ligne de Bordeaux à Arcachon', '655000', ['length_official_m' => 20000]);
        $this->reference($arcachon, 'LINESTRING(2.1 41.0, 2.2 41.0)');
        $this->gare('655000', 'Facture');
        $this->gare('655000', 'Lamothe');
        $lamothe = $this->line('Ligne de Facture à Lamothe', null, ['slug' => 'archeo-facture-lamothe']);
        $this->reference($lamothe, 'LINESTRING(2.0 41.0, 2.12 41.0)');

        // Deux gares de la ligne, mais l'écart de leurs PK (30 km) ne retrouve pas la section (7,7 km).
        $pagny = $this->line('Ligne de Bologne à Pagny-sur-Meuse', '026000', ['length_official_m' => 24000]);
        $this->reference($pagny, 'MULTILINESTRING((2.0 42.5, 2.1 42.5), (2.2 42.5, 2.3 42.5))');
        $this->gare('026000', 'Coussey', pk: 10000);
        $this->gare('026000', 'St-Germain-sur-Meuse', pk: 40000);
        $coussey = $this->line('Ligne de Coussey à Saint-Germain-sur-Meuse', null, ['slug' => 'archeo-coussey-saint-germain']);
        $this->reference($coussey, 'LINESTRING(2.1 42.5, 2.2 42.5)');

        // Deux lignes à code prouvées pour la même section : à relire, rien n'est fusionné.
        $first = $this->line('Ligne de Foix à Saint-Girons', '660000', ['length_official_m' => 16000]);
        $this->reference($first, 'LINESTRING(2.0 42.0, 2.1 42.0)');
        $second = $this->line('Ligne de Boussens à Saint-Girons', '661000', ['length_official_m' => 16000]);
        $this->reference($second, 'LINESTRING(2.2 42.0, 2.3 42.0)');
        foreach (['660000', '661000'] as $code) {
            $this->gare($code, 'Lacourt-Haut');
            $this->gare($code, 'Moulis-Bas');
        }
        $both = $this->line('Ligne de Lacourt-Haut à Moulis-Bas', null, ['slug' => 'archeo-lacourt-moulis']);
        $this->reference($both, 'LINESTRING(2.1 42.0, 2.2 42.0)');

        $this->artisan('vff:merge-split-lines', ['--only' => 'sections'])
            ->expectsOutputToContain('→ 1 bis. Sections sans extrémité partagée prouvées : 1')
            ->expectsTable(['Passe : décision', 'Lignes'], [['sections : rejeté', '7'], ['sections : à relire', '2']])
            ->doesntExpectOutputToContain('Relancer vff:build-line-references')
            ->assertSuccessful();

        foreach ([$mortagne, $fontoy, $dour, $tronchot, $geneve, $yutz, $buxieres, $lamothe, $coussey, $both] as $section) {
            $this->assertDatabaseHas('lines', ['id' => $section->id]);
        }
        $this->assertSame('origine « Mortagne » étrangère à la ligne (arrive à l\'origine seulement)', $this->rowFor($mortagne->id)['detail']);
        $this->assertSame('« Fontoy » étrangère à la ligne, « Thionville » au bout qui touche son tracé ou déjà sur ce tracé', $this->rowFor($fontoy->id)['detail']);
        $this->assertSame('« Dour » étrangère à la ligne, « Bettrechies » au bout qui touche son tracé ou déjà sur ce tracé', $this->rowFor($dour->id)['detail']);
        $this->assertSame('section plus courte que la moitié du tracé manquant', $this->rowFor($yutz->id)['detail']);
        $this->assertSame('« Buxières-lès-Villiers » étrangère à la ligne, « Bricon » au bout qui touche son tracé ou déjà sur ce tracé',
            $this->rowFor($buxieres->id)['detail']);
        $this->assertStringStartsWith('recouvre le tracé connu sur 1', $this->rowFor($lamothe->id)['detail']);
        $this->assertSame('écart des PK des deux toponymes incompatible avec la longueur de la section', $this->rowFor($coussey->id)['detail']);
        $relire = array_values(array_filter($this->csv(), fn (array $r) => $r['line_id'] === (string) $both->id));
        $this->assertSame([(string) $first->id, (string) $second->id], array_column($relire, 'target_id'));
        $this->assertSame(['à relire', 'à relire'], array_column($relire, 'decision'));
        $this->assertStringStartsWith('plusieurs lignes à code possibles ; section sans extrémité partagée', $relire[0]['detail']);
        foreach ([$tronchot, $geneve] as $section) {
            $this->assertSame([], array_filter($this->csv(), fn (array $r) => $r['line_id'] === (string) $section->id), $section->name);
        }
    }

    // ─── 2. même article ─────────────────────────────────────────────────

    public function test_same_article_merges_only_proven_duplicates(): void
    {
        $title = 'Ligne de Lure à Loulans-Verchamp';
        $coded = $this->line('Ligne de Lure à Loulans', '200000', ['wikipedia_title' => $title]);
        $this->trace('sncf', 's-lure', 'LINESTRING(3.0 46.0, 3.1 46.0)', $coded->id);
        $overlap = $this->created('Ligne de Lure à Villersexel', 'LINESTRING(3.0 46.0001, 3.08 46.0001)', ['wikipedia_title' => $title]);

        $t2 = 'Chemin de fer de la Mure';
        $this->line('Ligne de Saint-Georges à La Mure', '201000', ['wikipedia_title' => $t2]);
        $homonym = $this->created('Ligne de Saint-Georges à Corps', 'LINESTRING(3.0 46.5, 3.1 46.5)', ['wikipedia_title' => $t2, 'network' => 'secondaire']);

        $t3 = 'Ligne de Vic à Polminhac';
        $this->line('Ligne de Vic à Polminhac', '202000', ['wikipedia_title' => $t3, 'length_official_m' => 8000]);
        $untraced = $this->created('Ligne de Vic à Thiézac', 'LINESTRING(3.0 47.0, 3.1 47.0)', ['wikipedia_title' => $t3]);

        $t4 = 'Ligne de La Plaine à Hirson et Anor (frontière)';
        $hirson = $this->line('Ligne de La Plaine à Hirson et Anor', '203000', ['wikipedia_title' => $t4]);
        $this->trace('sncf', 's-hirson', 'LINESTRING(3.0 47.5, 3.1 47.5)', $hirson->id);
        $contiguous = $this->created('Ligne d\'Anor à la frontière', 'LINESTRING(3.1 47.5, 3.15 47.5)', ['wikipedia_title' => $t4]);

        $t5 = 'Ligne de Dole à Vesoul';
        $dole = $this->line('Ligne de Dole à Vesoul', '204000', ['wikipedia_title' => $t5]);
        $this->trace('sncf', 's-dole', 'LINESTRING(3.0 48.0, 3.1 48.0)', $dole->id);
        $far = $this->created('Ligne de Gray à Jussey', 'LINESTRING(3.3 48.0, 3.4 48.0)', ['wikipedia_title' => $t5]);

        // Recouvrement partiel, ni section prouvée.
        $t7 = 'Ligne de Gray à Culmont';
        $gray = $this->line('Ligne de Gray à Culmont', '207000', ['wikipedia_title' => $t7]);
        $this->trace('sncf', 's-gray', 'LINESTRING(3.0 49.0, 3.1 49.0)', $gray->id);
        $half = $this->created('Ligne de Mantoche à Autet', 'LINESTRING(3.05 49.0001, 3.15 49.0001)', ['wikipedia_title' => $t7]);
        // Ligne à code sans tracé, longueur à 25 % près.
        $t8 = 'Ligne de Paray à Lozanne';
        $this->line('Ligne de Paray à Lozanne', '208000', ['wikipedia_title' => $t8, 'length_official_m' => 6200]);
        $long = $this->created('Ligne de Paray à Charlieu', 'LINESTRING(3.0 49.5, 3.1 49.5)', ['wikipedia_title' => $t8]);
        $t9 = 'Ligne de Roanne à Lozanne';
        $this->line('Ligne de Roanne à Lozanne', '209000', ['wikipedia_title' => $t9, 'length_official_m' => 5000]);
        $tooLong = $this->created('Ligne de Roanne à Amplepuis', 'LINESTRING(3.0 49.7, 3.1 49.7)', ['wikipedia_title' => $t9]);

        $t6 = 'Ligne de Nantes à Saint-Gilles';
        $this->line('Ligne de Nantes à Sainte-Pazanne', '205000', ['wikipedia_title' => $t6]);
        $this->line('Ligne de Sainte-Pazanne à Saint-Gilles', '206000', ['wikipedia_title' => $t6]);
        $shared = $this->created('Ligne de Nantes à Saint-Gilles', 'LINESTRING(3.0 48.5, 3.1 48.5)', ['wikipedia_title' => $t6]);

        $this->artisan('vff:merge-split-lines', ['--only' => 'article'])
            ->expectsOutputToContain('→ 2. Lignes créées portant l\'article d\'une ligne à code : 10')
            ->doesntExpectOutputToContain('→ 3.')
            ->assertSuccessful();

        $this->assertDatabaseMissing('lines', ['id' => $overlap->id]);
        $this->assertStringStartsWith('même article « Ligne de Lure à Loulans-Verchamp », recouvrement 100 % / 80 %', $this->rowFor($overlap->id)['detail']);
        $this->assertDatabaseHas('source_traces', ['source_ref' => 'c'.$overlap->id, 'line_id' => $coded->id, 'match_method' => 'merged_duplicate']);

        $this->assertDatabaseHas('lines', ['id' => $homonym->id]);
        $this->assertSame(['distinct', 'catégorie secondaire ≠ principal : réseau homonyme (événements traités en passe 4)'],
            [$this->rowFor($homonym->id)['decision'], $this->rowFor($homonym->id)['detail']]);

        $this->assertDatabaseMissing('lines', ['id' => $untraced->id]);
        $this->assertStringContainsString('ligne à code sans tracé, 7.6 km pour 8.0 km officiels', $this->rowFor($untraced->id)['detail']);

        $this->assertDatabaseMissing('lines', ['id' => $contiguous->id]);
        $this->assertStringContainsString('section contiguë (0 m), toponymes de la ligne', $this->rowFor($contiguous->id)['detail']);

        $this->assertDatabaseHas('lines', ['id' => $far->id]);
        $this->assertSame(['à relire', 'même article, mais ni recouvrement (0 % / 0 %), ni section prouvée'],
            [$this->rowFor($far->id)['decision'], $this->rowFor($far->id)['detail']]);

        $this->assertSame('même article, mais ni recouvrement (50 % / 50 %), ni section prouvée', $this->rowFor($half->id)['detail']);
        $this->assertDatabaseMissing('lines', ['id' => $long->id]);
        $this->assertStringContainsString('ligne à code sans tracé, 7.2 km pour 6.2 km officiels', $this->rowFor($long->id)['detail']);
        $this->assertSame('à relire', $this->rowFor($tooLong->id)['decision'], '7,2 km pour 5 km : plus de 25 % d\'écart');
        $this->assertSame('même article, mais ni recouvrement (ligne à code sans tracé), ni section prouvée', $this->rowFor($tooLong->id)['detail']);
        $this->assertDatabaseHas('lines', ['id' => $shared->id]);
        $this->assertSame('article partagé par plusieurs lignes à code', $this->rowFor($shared->id)['detail']);
    }

    // ─── 3. couverture ───────────────────────────────────────────────────

    public function test_coverage_merges_a_created_line_containing_the_known_trace(): void
    {
        // 632000 Monsempron-Libos à Cahors : 4 km tracés sur 20 km officiels.
        $coded = $this->line('Ligne de Monsempron-Libos à Cahors', '632000', ['length_official_m' => 20000]);
        $this->trace('sncf', 's-632', 'LINESTRING(1.0 44.5, 1.05 44.5)', $coded->id);
        $this->commune('46001', 'Pradines', 1.2, 44.5);
        $this->commune('46042', 'Cahors', 1.25, 44.5);
        $created = $this->created('Ligne de Monsempron-Libos à Pradines', 'LINESTRING(1.0 44.5, 1.2 44.5)');

        // Trop longue (autre ligne prolongée).
        $coded2 = $this->line('Ligne de Libos à Fumel', '633000', ['length_official_m' => 5000]);
        $this->trace('sncf', 's-633', 'LINESTRING(1.0 45.0, 1.05 45.0)', $coded2->id);
        $tooLong = $this->created('Ligne de Libos à Penne', 'LINESTRING(1.0 45.0, 1.2 45.0)');

        // Extrémité étrangère, non localisée.
        $coded3 = $this->line('Ligne de Figeac à Capdenac', '634000', ['length_official_m' => 20000]);
        $this->trace('sncf', 's-634', 'LINESTRING(1.0 45.5, 1.05 45.5)', $coded3->id);
        $foreign = $this->created('Ligne de Figeac à Nulle-Part', 'LINESTRING(1.0 45.5, 1.2 45.5)');

        // Aucune extrémité citée, mais la seconde est proche : refusé quand même.
        $coded4 = $this->line('Ligne de Brive à Tulle', '635000', ['length_official_m' => 20000]);
        $this->trace('sncf', 's-635', 'LINESTRING(1.0 46.0, 1.05 46.0)', $coded4->id);
        $this->commune('19031', 'Brive', 0.99, 46.0);
        $this->commune('19272', 'Tulle', 1.25, 46.0);
        $this->commune('19100', 'Malemort', 1.0, 46.0);
        $this->commune('19200', 'Cornil', 1.2, 46.0);
        $uncited = $this->created('Ligne de Malemort à Cornil', 'LINESTRING(1.0 46.0, 1.2 46.0)');

        $this->artisan('vff:merge-split-lines', ['--only' => 'couverture'])
            ->expectsOutputToContain('→ 3. Lignes créées contenant le tracé connu d\'une ligne à code incomplète : 4')
            ->assertSuccessful();

        $this->assertDatabaseMissing('lines', ['id' => $created->id]);
        $this->assertSame('contient 100 % du tracé connu (4.0 km), 15.9 km pour 20.0 km officiels, extrémités : Pradines à 4.0 km d\'une extrémité',
            explode(' ; ', $this->rowFor($created->id)['detail'])[0]);
        $this->assertSame(['rejeté', 'trop longue : 15.8 km pour 5.0 km officiels (autre ligne prolongée)'],
            [$this->rowFor($tooLong->id)['decision'], $this->rowFor($tooLong->id)['detail']]);
        $this->assertSame('extrémité « Nulle-Part » étrangère à la ligne (non localisée)', $this->rowFor($foreign->id)['detail']);
        $this->assertSame('aucune extrémité citée par la ligne à code', $this->rowFor($uncited->id)['detail']);
        $this->assertDatabaseHas('lines', ['id' => $tooLong->id]);
        $this->assertDatabaseHas('lines', ['id' => $foreign->id]);
        $this->assertDatabaseHas('lines', ['id' => $uncited->id]);
    }

    public function test_coverage_merges_a_section_cited_by_both_ends(): void
    {
        // 882000 Chalon à Bourg : 3,9 km tracés sur 30 km ; la section Saint-Germain - Bourg est créée à part.
        $coded = $this->line('Ligne de Chalon à Bourg', '882000', ['length_official_m' => 30000]);
        $this->trace('sncf', 's-882', 'LINESTRING(1.0 45.0, 1.05 45.0)', $coded->id);
        $this->gare('882000', 'Saint-Germain');
        $this->commune('71076', 'Chalon', 1.0, 45.0);
        $this->commune('01053', 'Bourg', 1.3, 45.0);
        $section = $this->created('Ligne de Saint-Germain à Bourg', 'LINESTRING(1.06 45.0, 1.3 45.0)');

        // Section citée qui dépasse 120 % de l'officiel avec le tracé connu.
        $coded2 = $this->line('Ligne de Dole à Poligny', '883000', ['length_official_m' => 10000]);
        $this->trace('sncf', 's-883', 'LINESTRING(1.0 46.0, 1.05 46.0)', $coded2->id);
        $this->gare('883000', 'Mouchard');
        $tooLong = $this->created('Ligne de Mouchard à Poligny', 'LINESTRING(1.06 46.0, 1.3 46.0)');

        $this->artisan('vff:merge-split-lines', ['--only' => 'couverture'])
            ->expectsOutputToContain('  Sections citées par leurs deux extrémités : 1')
            ->assertSuccessful();

        $this->assertDatabaseMissing('lines', ['id' => $section->id]);
        $row = $this->rowFor($section->id);
        $this->assertSame('fusionné', $row['decision']);
        $this->assertStringStartsWith('section citée (extrémités = lieux de la ligne), 18.9 km + 3.9 km tracés pour 30.0 km officiels', $row['detail']);
        $this->assertDatabaseHas('source_traces', ['source_ref' => 'c'.$section->id, 'line_id' => $coded->id, 'match_method' => 'merged_section']);
        $this->assertDatabaseHas('lines', ['id' => $tooLong->id]);
    }

    // ─── 4. orphelines ───────────────────────────────────────────────────

    public function test_orphans_get_their_named_traces_and_created_twins(): void
    {
        $this->commune('14118', 'Caen', 0.0, 49.0);
        $this->commune('14762', 'Vire', 0.3, 49.0);
        $caen = $this->line('Ligne de Caen à Vire', '400000', ['length_official_m' => 22000]);
        $named = $this->trace('ohm', 'o-caen', 'LINESTRING(0.01 49.0, 0.1 49.0)', null, 'Ancienne ligne de Caen à Vire');
        $farNamed = $this->trace('osm', 'w-caen', 'LINESTRING(1.0 49.0, 1.1 49.0)', null, 'Ligne de Caen à Vire');

        // Ligne créée qui EST la ligne orpheline sous un autre nom.
        $this->commune('57630', 'Sarrebourg', 7.0, 48.7);
        $this->commune('57003', 'Abreschwiller', 7.15, 48.7);
        $sarrebourg = $this->line('Ligne de Sarrebourg à Abreschwiller', '401000', ['length_official_m' => 12000]);
        $twin = $this->created('Ligne de Sarrebourg à Hesse', 'LINESTRING(7.0 48.7, 7.15 48.7)');

        // Même passage, mais deux fois trop courte pour l'officiel : section ou autre ligne, à relire.
        $this->commune('02810', 'Villers-Cotterêts', 3.0, 49.25);
        $this->commune('02612', 'Port-aux-Perches', 3.06, 49.25);
        $villers = $this->line('Ligne de Villers-Cotterêts à Port-aux-Perches', '402000', ['length_official_m' => 20000]);
        $short = $this->created('Ligne de Villers-Cotterêts à Silly', 'LINESTRING(3.0 49.25, 3.06 49.25)');

        $this->artisan('vff:merge-split-lines', ['--only' => 'orphelines'])
            ->expectsOutputToContain('→ 4. Lignes à code sans aucun tracé : 3')
            ->expectsOutputToContain('  1 tracés nommés rattachés ; lignes à code encore sans tracé : 1')
            ->assertSuccessful();

        $this->assertDatabaseHas('source_traces', ['id' => $named, 'line_id' => $caen->id, 'match_method' => 'name_orphan', 'match_score' => 1]);
        $this->assertDatabaseHas('source_traces', ['id' => $farNamed, 'line_id' => null]);
        $rows = array_values(array_filter($this->csv(), fn ($r) => $r['target_id'] === (string) $caen->id));
        $this->assertSame(['rattaché', '1 tracé(s) nommé(s)'], [$rows[0]['decision'], $rows[0]['line_name']]);
        $this->assertStringStartsWith('6.6 km de tracés', $rows[0]['detail']);

        $this->assertDatabaseMissing('lines', ['id' => $twin->id]);
        $this->assertStringStartsWith('ligne orpheline : passe à ≤ 400 m de 2/2 points connus, extrémité commune, 11.0 km pour 12.0 km officiels',
            $this->rowFor($twin->id)['detail']);
        $this->assertDatabaseHas('lines', ['id' => $short->id]);
        $this->assertSame('passe à ≤ 400 m de 2/2 points connus, mais 4.4 km pour 20.0 km officiels : section ou autre ligne',
            $this->rowFor($short->id)['detail']);
        unset($sarrebourg, $villers);
    }

    // ─── 5. homonymes ────────────────────────────────────────────────────

    public function test_homonym_events_are_moved_to_the_article_owner(): void
    {
        $owner = $this->line('Ligne de Nantes à Pornic', '515000', ['wikipedia_title' => 'Ligne de Nantes à Pornic']);
        $holder = $this->line('Ligne de Saint-Hilaire à Pornic', null, ['wikipedia_title' => 'Ligne de Nantes à Pornic']);
        $moved = $this->event($holder, 'wikipedia', 'https://fr.wikipedia.org/wiki/Ligne_de_Nantes_%C3%A0_Pornic');
        $dup = $this->event($holder, 'wikipedia', 'https://fr.wikipedia.org/wiki/Ligne_de_Nantes_%C3%A0_Pornic', ['type' => 'closed', 'event_date' => '1939-01-01']);
        $this->event($owner, 'wikipedia', 'https://fr.wikipedia.org/wiki/Ligne_de_Nantes_%C3%A0_Pornic', ['type' => 'closed', 'event_date' => '1939-01-01']);

        // Article de deux lignes à code, portion non attribuable, ligne d'une autre catégorie : supprimé.
        $this->line('Ligne de Nantes à Sainte-Pazanne', '534000', ['wikipedia_title' => 'Ligne de Nantes à Saint-Gilles']);
        $this->line('Ligne de Sainte-Pazanne à Saint-Gilles', '535000', ['wikipedia_title' => 'Ligne de Nantes à Saint-Gilles']);
        $siding = $this->line('Embranchement de Nantes', null, ['network' => 'particulier']);
        $dropped = $this->event($siding, 'wikipedia', 'https://fr.wikipedia.org/wiki/Ligne_de_Nantes_%C3%A0_Saint-Gilles');
        // Même article, portion citée : la ligne dont les lieux contiennent la portion.
        $other = $this->line('Ligne de Machecoul à Challans', null);
        $cited = $this->event($other, 'wikipedia', 'https://fr.wikipedia.org/wiki/Ligne_de_Nantes_%C3%A0_Saint-Gilles',
            ['section_from' => 'Sainte-Pazanne', 'section_to' => 'Machecoul']);
        $this->gare('535000', 'Machecoul');

        // Sans ligne à code : l'article reste à la ligne dont le nom cite les toponymes (« Quimper » ≠ « Quimperlé »).
        $quimper = $this->line('Ligne de Quimper à Douarnenez', null, ['wikipedia_title' => 'Ligne de Quimper à Douarnenez']);
        $quimperle = $this->line('Ligne de Quimperlé à Doëlan', null, ['wikipedia_title' => 'Ligne de Quimper à Douarnenez']);
        $wrong = $this->event($quimperle, 'wikipedia', 'https://fr.wikipedia.org/wiki/Ligne_de_Quimper_%C3%A0_Douarnenez', ['event_date' => '1884-01-01']);
        $right = $this->event($quimper, 'wikipedia', 'https://fr.wikipedia.org/wiki/Ligne_de_Quimper_%C3%A0_Douarnenez');

        // Wikidata : item rattaché à une autre ligne.
        $wdLine = $this->line('Ligne de Guingamp à Paimpol', '519000');
        $elsewhere = $this->line('Ligne de Paimpol à Pontrieux', null);
        DB::insert("INSERT INTO wikidata_lines (qid, category, line_id, fetched_at, created_at, updated_at) VALUES ('Q3238', 'ferroviaire', ?, now(), now(), now())", [$wdLine->id]);
        $wd = $this->event($elsewhere, 'wikidata', 'https://www.wikidata.org/wiki/Q3238', ['type' => 'electrified']);

        $this->artisan('vff:merge-split-lines', ['--only' => 'homonymes'])
            ->expectsOutputToContain('→ 5. Événements Wikipédia d\'un article homonyme : 5 sur 4 ligne(s)')
            ->expectsOutputToContain('  Événements Wikidata d\'un item rattaché à une autre ligne : 1')
            ->assertSuccessful();

        $this->assertDatabaseHas('line_events', ['id' => $moved, 'line_id' => $owner->id, 'section_resolution' => 'pending']);
        $this->assertDatabaseMissing('line_events', ['id' => $dup]);
        $this->assertNull(DB::table('lines')->where('id', $holder->id)->value('wikipedia_title'), 'titre retiré de la ligne homonyme');
        $this->assertSame('Ligne de Nantes à Pornic', DB::table('lines')->where('id', $owner->id)->value('wikipedia_title'));
        $row = $this->rowFor($holder->id);
        $this->assertSame(['déplacé', (string) $owner->id, '515000'], [$row['decision'], $row['target_id'], $row['target_code']]);
        $this->assertSame('2 événement(s) de « Ligne de Nantes à Pornic » : article de Ligne de Nantes à Pornic (515000) ; 1 déplacé(s), 1 déjà présent(s) supprimé(s)', $row['detail']);

        $this->assertDatabaseMissing('line_events', ['id' => $dropped]);
        $this->assertSame('supprimé', $this->rowFor($siding->id)['decision']);
        $this->assertStringEndsWith('portion non attribuable, autre catégorie : supprimé ; 0 déplacé(s), 1 déjà présent(s) supprimé(s)', $this->rowFor($siding->id)['detail']);
        $pazanne = DB::table('lines')->where('code', '535000')->value('id');
        $this->assertDatabaseHas('line_events', ['id' => $cited, 'line_id' => $pazanne]);
        $this->assertStringContainsString('portion citée sur Ligne de Sainte-Pazanne à Saint-Gilles', $this->rowFor($other->id)['detail']);

        $this->assertDatabaseHas('line_events', ['id' => $wrong, 'line_id' => $quimper->id]);
        $this->assertDatabaseHas('line_events', ['id' => $right, 'line_id' => $quimper->id]);
        $this->assertStringContainsString('le nom ne cite pas les toponymes du titre, contrairement à Ligne de Quimper à Douarnenez',
            $this->rowFor($quimperle->id)['detail']);

        $this->assertDatabaseHas('line_events', ['id' => $wd, 'line_id' => $wdLine->id]);
        $this->assertSame('événement Wikidata '.$wd, $this->rowFor($elsewhere->id)['detail']);
    }

    public function test_homonyms_dry_run_only_plans(): void
    {
        $owner = $this->line('Ligne de Nantes à Pornic', '515000', ['wikipedia_title' => 'Ligne de Nantes à Pornic']);
        $holder = $this->line('Ligne de Saint-Hilaire à Pornic', null, ['wikipedia_title' => 'Ligne de Nantes à Pornic']);
        $e = $this->event($holder, 'wikipedia', 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Nantes_%C3%A0_Pornic&oldid=1');

        $this->artisan('vff:merge-split-lines', ['--only' => 'homonymes', '--dry-run' => true])->assertSuccessful();

        $this->assertDatabaseHas('line_events', ['id' => $e, 'line_id' => $holder->id]);
        $this->assertSame('Ligne de Nantes à Pornic', DB::table('lines')->where('id', $holder->id)->value('wikipedia_title'));
        $row = $this->rowFor($holder->id, dry: true);
        $this->assertSame(['à déplacer', (string) $owner->id], [$row['decision'], $row['target_id']]);
        $this->assertSame('1 événement(s) de « Ligne de Nantes à Pornic » : article de Ligne de Nantes à Pornic (515000)', $row['detail']);
    }

    // ─── réutilisation ───────────────────────────────────────────────────

    public function test_a_second_run_in_the_same_process_reports_only_its_own_decisions(): void
    {
        $this->sectionsFixture();
        $this->assertSame(0, Artisan::call('vff:merge-split-lines', ['--only' => 'sections']));
        $this->assertCount(3, $this->csv());

        $this->assertSame(0, Artisan::call('vff:merge-split-lines', ['--only' => 'sections']));
        $decisions = array_column($this->csv(), 'decision');
        $this->assertNotContains('fusionné', $decisions, 'le 2e passage ne relaie pas les fusions du 1er');
        $this->assertSame(['rejeté'], $decisions);
        $this->assertStringNotContainsString('Relancer vff:build-line-references', Artisan::output());
    }
}
