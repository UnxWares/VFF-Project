<?php

namespace Tests\Feature\Commands;

use App\Models\Era;
use App\Models\Line;
use App\Services\PlaceNames;
use App\Services\WikimediaClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * vff:import-heritage-lines, partie catalogue : « Liste des chemins de fer
 * touristiques de France » (Wikipédia), articles des exploitations, items
 * Wikidata (SPARQL, entités, exploitants), puis cache local. Réseau simulé.
 */
class HeritageCatalogCommandTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    private const LIST = <<<'WIKI'
        == [[Occitanie]] ==
        {| class="wikitable sortable"
        !Chemin de fer touristique
        !Type
        !Gare dépôt
        !Ligne
        !Section
        !Écartement
        !Département
        !Statut
        |-
        |[[Train à vapeur des Cévennes]]
        |Train Touristique
        |[[Gare d'Anduze|Anduze]]
        |[[Ligne de Lézan à Saint-Jean-du-Gard|Lézan à Saint-Jean-du-Gard]]
        |[[Gare d'Anduze|Anduze]] - [[Gare de Saint-Jean-du-Gard|Saint-Jean-du-Gard]]
        |1,435 (voie normale)
        |[[Gard (département)|Gard]]
        |En service
        |-
        |[[Vélorail du Larzac]]
        |[[Cyclo-draisine|Vélo-rail]]
        |[[Gare de La Bastide|La Bastide]]
        |[[Ligne de Tournemire à Le Vigan|Tournemire à Le Vigan]]
        |[[Gare de La Bastide]] - [[Gare de L'Hospitalet-du-Larzac]]
        |1,435 (voie normale)
        |[[Aveyron (département)|Aveyron]]
        |Disparu
        |-
        |[[Croisières Rhône]]
        |Train-restaurant
        |[[Gare de Lyon-Perrache|Lyon]]
        |[[Réseau ferré national]]
        |Lyon - Marseille
        |1,435 (voie normale)
        |[[Rhône (département)|Rhône]]
        |En service
        |}
        WIKI;

    /** @var list<string> */
    public static array $fetched = [];

    private Line $cevennes;

    private Line $larzac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
        Sleep::fake();
        self::$fetched = [];
        $this->era(2030, current: true);
        $list = self::LIST;
        $this->app->instance(WikimediaClient::class, new class($list) extends WikimediaClient
        {
            public function __construct(private string $list) {}

            public function fetchPages(array $titles, bool $leadOnly = false): array
            {
                $out = [];
                foreach ($titles as $t) {
                    HeritageCatalogCommandTest::$fetched[] = $t;
                    $content = match ($t) {
                        'Liste des chemins de fer touristiques de France' => $this->list,
                        'Train à vapeur des Cévennes' => "Le '''Train à vapeur des Cévennes''' circule depuis le 3 juin 1982 entre Anduze et Saint-Jean-du-Gard.",
                        'Vélorail du Larzac' => "Le '''Vélorail du Larzac''' a fonctionné de 2001 à 2015 entre La Bastide et L'Hospitalet-du-Larzac.",
                        default => null,
                    };
                    if ($content !== null) {
                        $out[$t] = ['title' => $t, 'revid' => 77, 'content' => $content];
                    }
                }

                return $out;
            }

            public function api(array $params, string $endpoint = self::FRWIKI_API): array
            {
                return ['query' => ['pages' => [['title' => 'Train à vapeur des Cévennes', 'pageprops' => ['wikibase_item' => 'Q3238850']]]]];
            }
        });
        Http::fake([
            'query.wikidata.org/*' => Http::response(['results' => ['bindings' => [
                ['item' => ['value' => 'http://www.wikidata.org/entity/Q3238850'], 'article' => ['value' => 'https://fr.wikipedia.org/wiki/Train_%C3%A0_vapeur_des_C%C3%A9vennes']],
                ['item' => ['value' => 'http://www.wikidata.org/entity/Q999']],
            ]]]),
            'www.wikidata.org/*' => function ($request) {
                $ids = explode('|', $request->data()['ids'] ?? '');

                return Http::response(['entities' => array_combine($ids, array_map(fn ($id) => match ($id) {
                    'Q3238850' => ['labels' => ['fr' => ['value' => 'Train à vapeur des Cévennes']], 'claims' => [
                        'P580' => [['mainsnak' => ['datavalue' => ['value' => ['time' => '+1982-06-03T00:00:00Z', 'precision' => 11]]]]],
                        'P137' => [['mainsnak' => ['datavalue' => ['value' => ['id' => 'Q500']]]]],
                    ]],
                    'Q500' => ['labels' => ['fr' => ['value' => 'Compagnie du train à vapeur des Cévennes']]],
                    default => ['labels' => ['en' => ['value' => 'Heritage railway '.$id]], 'claims' => []],
                }, $ids))]);
            },
        ]);

        $this->cevennes = $this->lineWithTrace('999100', 'Ligne de Lézan à Saint-Jean-du-Gard', 'LINESTRING(3.98 44.05, 3.88 44.10)', 'Ligne de Lézan à Saint-Jean-du-Gard');
        $this->larzac = $this->lineWithTrace('999200', 'Ligne de Tournemire à Le Vigan', 'LINESTRING(3.20 43.95, 3.30 43.90)', 'Ligne de Tournemire à Le Vigan',
            ['status' => 'abandoned']);
    }

    private function lineWithTrace(string $code, string $name, string $wkt, string $title, array $segment = []): Line
    {
        $line = $this->rfnLine($code, $name, ['wikipedia_title' => $title]);
        DB::insert("INSERT INTO line_reference_geometries (line_id, length_m, sources, geom, created_at, updated_at)
            VALUES (?, ROUND(ST_Length(ST_GeomFromText(?, 4326)::geography)), '[]'::jsonb, ST_Multi(ST_GeomFromText(?, 4326)), now(), now())",
            [$line->id, $wkt, $wkt]);
        $era = Era::where('year', 2030)->first();
        $this->track($line, $era, array_map(fn ($p) => [(float) explode(' ', $p)[1], (float) explode(' ', $p)[0]],
            explode(', ', trim(substr($wkt, 11), ')'))), $segment);

        return $line;
    }

    /** @return list<array<string, mixed>> */
    private function heritageEvents(Line $line): array
    {
        return DB::table('line_events')->where('line_id', $line->id)->orderBy('event_date')->get()->map(fn ($e) => (array) $e)->all();
    }

    public function test_explore(): void
    {
        foreach ([['30010', 'Anduze', 3.98, 44.05], ['30252', 'Saint-Jean-du-Gard', 3.88, 44.10], ['12021', 'La Bastide', 3.20, 43.95], ['12115', "L'Hospitalet-du-Larzac", 3.30, 43.90]] as [$i, $n, $x, $y]) {
            DB::insert("INSERT INTO communes (code_insee, type, nom, nom_norm, geom, created_at, updated_at) VALUES (?, 'commune-actuelle', ?, ?, ST_SetSRID(ST_MakePoint(?, ?), 4326), now(), now())",
                [$i, $n, PlaceNames::normalize($n), $x, $y]);
        }
        Artisan::call('vff:import-heritage-lines');
        fwrite(STDERR, Artisan::output());
        foreach ([$this->cevennes, $this->larzac] as $l) {
            fwrite(STDERR, $l->name."\n");
            foreach ($this->heritageEvents($l) as $e) {
                fwrite(STDERR, "  {$e['type']} {$e['event_date']} {$e['date_precision']} {$e['confidence']} {$e['source']} {$e['heritage_kind']} {$e['section_from']}-{$e['section_to']} {$e['section_resolution']} | {$e['description']} | {$e['source_url']}\n");
            }
        }
        fwrite(STDERR, json_encode(DB::table('line_events')->get(['line_id', 'type', 'event_date', 'heritage_kind', 'source', 'section_from', 'section_to', 'section_resolution', 'confidence', 'description'])->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n");
        fwrite(STDERR, File::get(storage_path('app/reports/heritage-exploitations.csv')));
        fwrite(STDERR, implode(', ', self::$fetched)."\n");
        fwrite(STDERR, implode("\n", array_map('basename', File::files(storage_path('app/sources/heritage'))))."\n");
        $this->assertTrue(true);
    }
}
