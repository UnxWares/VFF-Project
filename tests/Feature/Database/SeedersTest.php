<?php

namespace Tests\Feature\Database;

use App\Enums\MaterialType;
use App\Models\Era;
use App\Models\Line;
use App\Models\Material;
use Database\Seeders\AdvocacySeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\EraSeeder;
use Database\Seeders\HistoricalErasSeeder;
use Database\Seeders\LineMaterialsSeeder;
use Database\Seeders\LineSectionsSeeder;
use Database\Seeders\MaterialSeeder;
use Database\Seeders\ParisBrestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Seeders : contenu attendu et idempotence (deux exécutions = même état,
 * mêmes identifiants), y compris sans commande Artisan attachée.
 */
class SeedersTest extends TestCase
{
    use RefreshDatabase;

    /**
     * État d'une table sans horodatages, trié par id.
     *
     * @return list<array<string, mixed>>
     */
    private function tableState(string $table): array
    {
        return DB::table($table)->orderBy('id')->get()
            ->map(fn ($row) => collect((array) $row)->except(['created_at', 'updated_at'])->all())
            ->all();
    }

    private function assertNoLongDash(string $text, string $where): void
    {
        $this->assertStringNotContainsString('—', $text, "tiret long dans {$where}");
        $this->assertStringNotContainsString('--', $text, "double tiret dans {$where}");
    }

    public function test_era_seeders_cover_1830_to_2030_by_decade_with_2030_current(): void
    {
        $this->seed(DatabaseSeeder::class);

        $years = Era::orderBy('year')->pluck('year')->all();
        $this->assertSame(range(1830, 2030, 10), $years);
        $this->assertSame([2030], Era::where('is_current', true)->pluck('year')->all());
        $this->assertSame('2030-12-31', Era::where('year', 2030)->first()->editable_until->toDateString());
        $this->assertSame(0, Era::where('year', '<', 2030)->whereNotNull('editable_until')->count());

        foreach (Era::all() as $era) {
            $this->assertNotSame('', trim((string) $era->label), "ère {$era->year} sans libellé");
            $this->assertGreaterThan(40, mb_strlen((string) $era->description), "ère {$era->year} sans description");
            $this->assertNoLongDash($era->label.' '.$era->description, "ère {$era->year}");
        }
        $this->assertSame('Les pionniers', Era::where('year', 1830)->value('label'));
        $this->assertSame('Le réseau aujourd\'hui', Era::where('year', 2030)->value('label'));
    }

    public function test_era_seeders_are_idempotent_and_keep_ids(): void
    {
        $this->seed([EraSeeder::class, HistoricalErasSeeder::class]);
        $first = $this->tableState('eras');

        $this->seed([EraSeeder::class, HistoricalErasSeeder::class]);
        $this->assertSame($first, $this->tableState('eras'));
        $this->assertCount(21, $first);
    }

    public function test_historical_seeder_never_steals_the_current_flag(): void
    {
        // Ordre inverse : les ères historiques ne doivent jamais devenir courantes
        // et ne touchent pas aux ères 1990-2030.
        $this->seed(HistoricalErasSeeder::class);
        $this->assertSame(16, Era::count());
        $this->assertSame(0, Era::where('is_current', true)->count());
        $this->assertSame([1830, 1980], [Era::min('year'), Era::max('year')]);

        $this->seed(EraSeeder::class);
        $this->assertSame([2030], Era::where('is_current', true)->pluck('year')->all());
    }

    public function test_material_seeder_content_and_idempotence(): void
    {
        $this->seed(MaterialSeeder::class);
        $first = $this->tableState('materials');

        $this->assertCount(33, $first);
        $this->assertSame(33, Material::distinct()->count('slug'));
        $types = array_map(fn (MaterialType $t) => $t->value, MaterialType::cases());
        foreach ($first as $row) {
            $this->assertContains($row['type'], $types, "type inconnu pour {$row['slug']}");
            $this->assertMatchesRegularExpression('/^[a-z0-9]+(-[a-z0-9]+)*$/', $row['slug']);
            if ($row['in_service_to'] !== null) {
                $this->assertGreaterThanOrEqual($row['in_service_from'], $row['in_service_to'], $row['slug']);
            }
            $this->assertNoLongDash((string) $row['description'], $row['slug']);
            // Apostrophes oubliées (« de lEst », « dAlstom ») : régression.
            $this->assertDoesNotMatchRegularExpression('/(?<![\\p{L}])(l|d)\\p{Lu}\\p{Ll}/u', (string) $row['description'], $row['slug']);
        }
        $this->assertSame('Pacific Nord modernisée, symbole de la traction vapeur sur les grandes lignes du Nord et de l\'Est.',
            Material::where('slug', '231-k')->value('description'));

        $this->seed(MaterialSeeder::class);
        $this->assertSame($first, $this->tableState('materials'));
    }

    public function test_line_materials_seeder_matches_codes_with_or_without_spaces_and_is_idempotent(): void
    {
        $this->seed(MaterialSeeder::class);
        $brest = Line::factory()->create(['code' => '420 000']);
        $lgv = Line::factory()->create(['code' => '752000']);

        $this->seed(LineMaterialsSeeder::class);
        $this->assertSame(
            ['141-r', 'agc-b81500', 'bb-22200', 'bb-67000', 'regiolis-z-51500', 'tgv-atlantique', 'voiture-corail'],
            $brest->materials()->orderBy('slug')->pluck('slug')->all(),
        );
        $this->assertSame(6, $lgv->materials()->count());
        $pivot = DB::table('line_materials')->count();
        $this->assertSame(13, $pivot);

        // Rejouable, et utilisable hors commande Artisan ($this->command null).
        (new LineMaterialsSeeder)->run();
        $this->assertSame($pivot, DB::table('line_materials')->count());
    }

    public function test_line_sections_seeder_replaces_sections_and_skips_unknown_lines(): void
    {
        $plm = Line::factory()->create(['slug' => 'rfn-752000']);
        $atl = Line::factory()->create(['slug' => 'rfn-431000']);

        $this->seed(LineSectionsSeeder::class);
        $this->assertSame(['LGV Sud-Est', 'LGV Rhône-Alpes', 'LGV Méditerranée'],
            DB::table('line_sections')->where('line_id', $plm->id)->orderBy('pk_start_m')->pluck('name')->all());
        $this->assertSame(2, DB::table('line_sections')->where('line_id', $atl->id)->count());
        // rfn-005000 absente : ignorée sans erreur.
        $this->assertSame(5, DB::table('line_sections')->count());
        $this->assertSame(['LN1', 'LGV Paris-Lyon'],
            json_decode(DB::table('line_sections')->where('name', 'LGV Sud-Est')->value('alt_names'), true));

        // Sections contiguës, sans trou ni chevauchement.
        $pks = DB::table('line_sections')->where('line_id', $plm->id)->orderBy('pk_start_m')->get(['pk_start_m', 'pk_end_m']);
        $this->assertSame([0, 409000, 498000], $pks->pluck('pk_start_m')->map(fn ($v) => (int) $v)->all());
        $this->assertSame([409000, 498000, 711000], $pks->pluck('pk_end_m')->map(fn ($v) => (int) $v)->all());

        $this->seed(LineSectionsSeeder::class);
        $this->assertSame(5, DB::table('line_sections')->count());
    }

    public function test_advocacy_seeder_flags_hirondelles_and_angoulim_and_is_idempotent(): void
    {
        $hirondelles = Line::factory()->create(['code' => '878 000', 'name' => 'Ligne d\'Andelot-en-Montagne à La Cluse']);
        $angouleme = Line::factory()->create(['code' => '610000', 'name' => 'Ligne de Limoges-Bénédictins à Angoulême']);

        $this->seed(AdvocacySeeder::class);
        $state = $this->tableState('lines');

        $h = $hirondelles->fresh();
        $this->assertTrue($h->is_threatened);
        $this->assertSame('threatened', $h->advocacy['kind']);
        $this->assertSame('La ligne des Hirondelles est en sursis', $h->advocacy['title']);
        $this->assertSame('Agir pour la ligne', $h->advocacy['cta_label']);
        $this->assertNull($h->advocacy['cta_url']);

        $a = $angouleme->fresh();
        $this->assertFalse($a->is_threatened, 'une campagne de réouverture n\'est pas une ligne menacée');
        $this->assertSame('reopening', $a->advocacy['kind']);
        $this->assertSame('Rouvrons la ligne Angoulême - Limoges', $a->advocacy['title']);
        $this->assertSame('Soutenir AngouLim', $a->advocacy['cta_label']);
        $this->assertSame('https://www.helloasso.com/associations/angoulim/adhesions/bulletin-d-adhesion-a-angoulim', $a->advocacy['cta_url']);
        $this->assertStringContainsString('13 mars 2018', $a->advocacy['message']);
        // Durée annoncée sourcée (QE AN n° 8999) et engagement rendu public (choix de l'utilisateur, 2026-10-20).
        $this->assertStringContainsString('annoncée de trois semaines', $a->advocacy['message']);
        $this->assertStringContainsString('Nous adhérons à AngouLim car nous soutenons cette initiative.', $a->advocacy['message']);

        foreach ([$h, $a] as $line) {
            // Textes français et leurs traductions (translations.en / .de).
            $this->assertNoLongDash((string) json_encode($line->advocacy, JSON_UNESCAPED_UNICODE), $line->code);
        }
        // JSON stocké sans échappement unicode (lisible en base).
        $this->assertStringContainsString('Angoulême', DB::table('lines')->where('id', $a->id)->value('advocacy'));

        $this->seed(AdvocacySeeder::class);
        $this->assertSame($state, $this->tableState('lines'));
    }

    public function test_advocacy_seeder_without_imported_lines_changes_nothing(): void
    {
        $other = Line::factory()->create(['code' => '123000']);

        $this->seed(AdvocacySeeder::class);

        $this->assertFalse((bool) $other->fresh()->is_threatened);
        $this->assertNull($other->fresh()->advocacy);
    }

    public function test_paris_brest_seeder_builds_one_segment_per_era_and_is_idempotent(): void
    {
        $this->seed([EraSeeder::class, HistoricalErasSeeder::class, ParisBrestSeeder::class]);

        $line = Line::where('slug', 'paris-brest')->firstOrFail();
        $this->assertSame('420 000', $line->code);
        $this->assertSame(21, $line->segments()->count());
        $this->assertSame(7, $line->events()->count());
        $this->assertSame(5, $line->materials()->count());

        $seg1980 = $line->segments()->whereHas('era', fn ($q) => $q->where('year', 1980))->first();
        $seg2030 = $line->segments()->whereHas('era', fn ($q) => $q->where('year', 2030))->first();
        $this->assertFalse($seg1980->electrified);
        $this->assertNull($seg1980->voltage_v);
        $this->assertSame(140, $seg1980->max_speed_kmh);
        $this->assertTrue($seg2030->electrified);
        $this->assertSame(25000, $seg2030->voltage_v);
        $this->assertSame(320, $seg2030->max_speed_kmh);

        $counts = fn () => array_map(fn ($t) => DB::table($t)->count(), ['lines', 'line_segments', 'line_events', 'stations', 'materials', 'line_materials']);
        $before = $counts();
        $segments = $this->tableState('line_segments');

        (new ParisBrestSeeder)->run(); // sans commande attachée
        $this->assertSame($before, $counts());
        $this->assertSame(array_column($segments, 'id'), array_column($this->tableState('line_segments'), 'id'));
    }
}
