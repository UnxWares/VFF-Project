<?php

namespace Tests\Feature\Commands;

use App\Models\Line;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * vff:seed-off-network-lines : contrôle des entrées du fichier de curation
 * (database/data/lignes_hors_rfn.php). Une entrée invalide fait échouer la
 * commande avant toute écriture, avec un message qui dit quoi corriger.
 */
class SeedOffNetworkValidationTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    private const EVENT = ['type' => 'opening', 'date' => '1900-05-01', 'source_url' => 'https://example.org', 'quote' => 'ouverte le 1er mai 1900'];

    private const ENTRY = ['slug' => 'hors-rfn-ligne-test', 'name' => 'Ligne de A à B', 'network' => 'secondaire', 'sources' => ['https://example.org'],
        'events' => [self::EVENT]];

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
    }

    /** @return array<string, array{0: array, 1: string}> */
    public static function invalidEntries(): array
    {
        $e = ['slug' => 'hors-rfn-autre'] + self::ENTRY;
        $ev = fn (array $over) => ['events' => [$over + self::EVENT]] + $e;

        return [
            'sans clé' => [['name' => 'x'], 'slug (ligne créée) ou line (ligne existante) obligatoire'],
            'slug' => [['slug' => 'Ligne Test'] + $e, 'slug attendu « hors-rfn-… » (minuscules, chiffres, tirets)'],
            'sans nom' => [['name' => ''] + $e, 'name et network (principal, secondaire, militaire, particulier) obligatoires'],
            'catégorie' => [['network' => 'urbain'] + $e, 'name et network (principal, secondaire, militaire, particulier) obligatoires'],
            'sans source' => [['sources' => []] + $e, 'au moins une source (sources) obligatoire'],
            'code' => [['code' => '12345'] + $e, 'code 12345 invalide ou déjà porté par une autre ligne'],
            'code pris' => [['code' => '420000'] + $e, 'code 420000 invalide ou déjà porté par une autre ligne'],
            'bbox' => [['trace' => ['bbox' => [1, 2, 3]]] + $e, 'trace.bbox = [lon min, lat min, lon max, lat max] obligatoire'],
            'tiret long' => [['name' => 'Ligne A — B'] + $e, 'tiret long interdit dans les textes affichés'],
            'double tiret' => [['description' => 'A -- B'] + $e, 'tiret long interdit dans les textes affichés'],
            'type' => [$ev(['type' => 'naissance']), 'événement 0 : type inconnu « naissance »'],
            'date' => [$ev(['date' => '1900-02-30']), 'événement 0 : date invalide « 1900-02-30 »'],
            'format date' => [$ev(['date' => '1900']), 'événement 0 : date invalide « 1900 »'],
            'précision' => [$ev(['precision' => 'decade']), 'événement 0 : précision day | month | year'],
            'citation' => [$ev(['quote' => '']), 'événement 0 : source_url et quote obligatoires'],
            'url' => [$ev(['source_url' => '']), 'événement 0 : source_url et quote obligatoires'],
            'patrimoine' => [$ev(['type' => 'heritage_start', 'heritage_kind' => 'manège']), 'événement 0 : heritage_kind tourist_train | velorail | museum'],
            'from sans to' => [$ev(['from' => 'A']), 'événement 0 : from et to vont ensemble'],
            'portion' => [$ev(['from' => 'A', 'to' => 'B', 'portion' => [1, 2, 3, 4]]), 'événement 0 : portion = [lon min, lat min, lon max, lat max], avec from / to et une forme (trace)'],
        ];
    }

    #[DataProvider('invalidEntries')]
    public function test_invalid_entries_stop_the_command(array $entry, string $message): void
    {
        $this->rfnLine('420000', 'Ligne de Paris à Brest');
        $file = $this->sandbox.'/entries.php';
        File::put($file, '<?php return '.var_export([self::ENTRY, $entry], true).';');
        $before = $this->snapshot(['lines', 'line_events', 'source_traces']);

        $this->artisan('vff:seed-off-network-lines', ['--file' => $file])
            ->expectsOutputToContain($message)
            ->assertFailed();

        $this->assertSame($before, $this->snapshot(['lines', 'line_events', 'source_traces']), 'rien d\'écrit');
    }

    public function test_duplicate_entry_and_valid_heritage_portion(): void
    {
        $file = $this->sandbox.'/entries.php';
        File::put($file, '<?php return '.var_export([self::ENTRY, self::ENTRY], true).';');
        $this->artisan('vff:seed-off-network-lines', ['--file' => $file])->expectsOutputToContain('entrée en double')->assertFailed();

        File::put($file, '<?php return '.var_export([self::ENTRY + ['code' => '987000'], ['line' => 'ligne-absente', 'events' => [self::EVENT]]], true).';');
        $this->artisan('vff:seed-off-network-lines', ['--file' => $file])
            ->expectsOutputToContain('ligne-absente : ligne existante introuvable (slug ligne-absente), entrée ignorée.')
            ->assertSuccessful();
        $this->assertTrue(Line::where('slug', 'hors-rfn-ligne-test')->where('code', '987000')->exists());
        $this->assertSame(1, DB::table('line_events')->where('source', 'manual')->count());
    }
}
