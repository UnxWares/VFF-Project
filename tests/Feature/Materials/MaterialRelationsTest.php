<?php

namespace Tests\Feature\Materials;

use App\Models\Era;
use App\Models\Line;
use App\Models\LineMaterial;
use App\Models\LineMaterialPeriod;
use App\Models\Material;
use App\Models\Station;
use App\Services\Materials\LineMaterialLinker;
use App\Services\Materials\MaterialRelations;
use App\Services\Materials\RelationRouter;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Relations et itinéraires cités par l'article d'une série (MaterialRelations, RelationRouter), sur un petit
 * graphe écrit à la main (époque actuelle, longueurs en kilomètres) :
 *
 *   Paris ─395 (80)─ Dreux ─395 (60)─ Surdon ─430 (15)─ Argentan ─405 (110)─ Folligny ─405 (15)─ Granville
 *   Caen ─L1 (100)─ Mézidon        Caen ─L2a (50)─ Lisieux ─L2b (55)─ Mézidon   (deux itinéraires proches)
 */
class MaterialRelationsTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int|string, Line> codes numériques rangés en clés entières */
    private array $lines = [];

    /** @var array<string, Station> */
    private array $stations = [];

    private Era $era;

    protected function setUp(): void
    {
        parent::setUp();
        $this->era = Era::factory()->create(['year' => 2030, 'is_current' => true]);
        foreach (['395000', '430000', '405000', 'L1', 'L2a', 'L2b'] as $code) {
            $this->lines[$code] = Line::factory()->create(['code' => $code, 'name' => "Ligne {$code}", 'is_lgv' => false]);
        }
        $places = ['Paris-Montparnasse' => [2.32, 48.84], 'Dreux' => [1.37, 48.73], 'Surdon' => [0.17, 48.65], 'Argentan' => [-0.01, 48.74],
            'Folligny' => [-1.40, 48.83], 'Granville' => [-1.59, 48.84], 'Caen' => [-0.35, 49.18], 'Lisieux' => [0.22, 49.14], 'Mézidon' => [-0.07, 49.07]];
        foreach ($places as $name => [$lng, $lat]) {
            $this->stations[$name] = Station::factory()->create(['name' => $name, 'geom' => Point::makeGeodetic($lat, $lng)]);
        }
        $this->edge('Paris-Montparnasse', 'Dreux', '395000', 80);
        $this->edge('Dreux', 'Surdon', '395000', 60);
        $this->edge('Surdon', 'Argentan', '430000', 15);
        $this->edge('Argentan', 'Folligny', '405000', 110);
        $this->edge('Folligny', 'Granville', '405000', 15);
        $this->edge('Caen', 'Mézidon', 'L1', 100);
        $this->edge('Caen', 'Lisieux', 'L2a', 50);
        $this->edge('Lisieux', 'Mézidon', 'L2b', 55);
    }

    private function edge(string $from, string $to, string $line, int $km, ?Era $era = null): void
    {
        $id = $this->lines[$line]->id;
        DB::table('station_edges')->insert([
            'era_id' => ($era ?? $this->era)->id, 'from_station_id' => $this->stations[$from]->id, 'to_station_id' => $this->stations[$to]->id,
            'line_id' => $id, 'length_m' => $km * 1000, 'line_ids' => "{{$id}}", 'line_lengths' => '{'.($km * 1000).'}',
        ]);
    }

    private function relations(): MaterialRelations
    {
        return MaterialRelations::fromDatabase([
            'Ligne de Paris à Granville' => [
                'stops' => ['Paris-Montparnasse', 'Dreux', 'Surdon', 'Argentan', 'Folligny', 'Granville'],
                'lines' => ['395000', '430000', '405000'],
                'source' => 'https://fr.wikipedia.org/wiki/Ligne_de_Paris_%C3%A0_Granville',
            ],
        ]);
    }

    /**
     * Codes des lignes rattachées par une section d'article.
     *
     * @return list<array{line_id: int, evidence: array<string, mixed>, successor?: string}>
     */
    private function links(MaterialRelations $relations, string $text, string $section = 'Services effectués'): array
    {
        return $relations->links(
            ['title' => 'X 2800', 'revid' => 42, 'sections' => [['title' => $section, 'level' => 2, 'text' => $text]]],
            ['fast' => false, 'from' => 1960, 'to' => 1990],
            new \DateTimeImmutable('2026-11-20'),
        );
    }

    /** @param list<array{line_id: int, evidence: array<string, mixed>}> $links */
    private function codes(array $links): array
    {
        $codes = array_values(array_unique(array_map(fn (array $l): string => (string) Line::query()->whereKey($l['line_id'])->value('code'), $links)));
        sort($codes);

        return $codes;
    }

    public function test_an_itinerary_article_links_every_line_it_chains(): void
    {
        $relations = $this->relations();
        $links = $this->links($relations, "* [[Ligne de Paris à Granville|Paris - Granville]]\n");

        $this->assertSame(['395000', '405000', '430000'], $this->codes($links));
        $route = $links[0]['evidence']['route'];
        $this->assertSame('itinerary', $route['kind']);
        $this->assertSame('Ligne de Paris à Granville', $route['title']);
        $this->assertSame(['id' => $this->stations['Granville']->id, 'name' => 'Granville'], $route['to']);
        $this->assertSame('wikipedia_material_article', $links[0]['evidence']['kind']);
    }

    public function test_an_itinerary_cited_with_an_intermediate_stop_links_nothing(): void
    {
        $relations = $this->relations();
        $links = $this->links($relations, "* Axe régional sur la [[Ligne de Paris à Granville]] avec la [[Gare de Dreux]]\n");

        $this->assertSame([], $links, 'la phrase ne parle que de Paris - Dreux');
        $this->assertSame(1, $relations->stats()['itineraries_partial']);
    }

    public function test_an_unambiguous_relation_links_the_lines_of_the_shortest_path(): void
    {
        $links = $this->links($this->relations(), "* Relation Argentan - Granville\n* [[Gare de Dreux|Dreux]] \u{2013} [[Gare de Granville|Granville]] (X4790)\n");

        $this->assertSame(['395000', '405000', '430000'], $this->codes($links));
        $argentan = array_values(array_filter($links, fn (array $l): bool => $l['evidence']['route']['label'] === 'Argentan - Granville'));
        $this->assertCount(1, $argentan, 'Argentan - Granville : la seule ligne 405000');
        $this->assertSame('medium', LineMaterialLinker::attributes([$argentan[0]['evidence']])['confidence']);
        $this->assertSame('Relation Argentan - Granville', $argentan[0]['evidence']['quote']);
        $this->assertSame($this->stations['Argentan']->id, $argentan[0]['evidence']['route']['from']['id']);
    }

    public function test_an_ambiguous_relation_is_refused_and_counted(): void
    {
        $relations = $this->relations();
        $links = $this->links($relations, "* Caen - Mézidon\n");

        $this->assertSame([], $links, 'Caen - Lisieux - Mézidon n\'est que 5 % plus long que la ligne directe');
        $this->assertSame(1, $relations->stats()['pairs_ambiguous']);
    }

    /**
     * Lyon - Saint-Gervais, par Annecy ou par Annemasse : la relation reste ambiguë, et le routeur donne les lignes
     * que les deux itinéraires parcourent (ici Mézidon - Trouville) ; MaterialRelations ne les rattache pas encore
     * (des faux restent, voir le rapport materiel-ambigu).
     */
    public function test_an_ambiguous_relation_gives_the_lines_every_close_itinerary_follows(): void
    {
        $this->lines['379000'] = Line::factory()->create(['code' => '379000', 'name' => 'Ligne 379000', 'is_lgv' => false]);
        $this->stations['Trouville'] = Station::factory()->create(['name' => 'Trouville', 'geom' => Point::makeGeodetic(49.36, 0.08)]);
        $this->edge('Mézidon', 'Trouville', '379000', 40);

        $route = (new RelationRouter)->route([$this->stations['Caen']->id], [$this->stations['Trouville']->id], 2030);

        $this->assertSame('ambiguous', $route['status']);
        $this->assertSame([$this->lines['379000']->id], $route['lines'], 'par Lisieux ou par la ligne directe, toujours Mézidon - Trouville');
        $this->assertSame([], $this->links($this->relations(), "* Caen - Trouville\n"));
    }

    /**
     * Ligne commune à tous les itinéraires proches, mais qui ne s'éloigne jamais de plus de EXIT_KM d'un des deux
     * arrêts : sortie de gare (Fives - Abbeville au départ de Lille pour Paris - Lille), jamais rattachée.
     */
    public function test_an_ambiguous_relation_drops_a_common_line_that_only_leaves_a_station(): void
    {
        $this->lines['X1'] = Line::factory()->create(['code' => 'X1', 'name' => 'Ligne X1', 'is_lgv' => false]);
        $this->stations['Caen-Ouest'] = Station::factory()->create(['name' => 'Caen-Ouest', 'geom' => Point::makeGeodetic(49.18, -0.45)]);
        $this->edge('Caen-Ouest', 'Caen', 'X1', 8);

        $route = (new RelationRouter)->route([$this->stations['Caen-Ouest']->id], [$this->stations['Mézidon']->id], 2030);

        $this->assertSame('ambiguous', $route['status']);
        $this->assertSame([], $route['lines'], '8 km parcourus par les deux itinéraires, à moins de 15 km de Caen-Ouest');
        $this->assertSame([], $this->links($this->relations(), "* Caen-Ouest - Mézidon\n"));
    }

    public function test_paris_dreux_never_reaches_argentan_granville(): void
    {
        $links = $this->links($this->relations(), "* Paris - Dreux\n", 'Services effectués > TER Basse-Normandie');

        $this->assertSame(['395000'], $this->codes($links));
        $this->assertSame('TER', $links[0]['evidence']['route']['service']);
        $this->assertSame('Paris - Dreux', $links[0]['evidence']['route']['label'], 'la ville telle que la phrase la nomme');
        $this->assertSame('Paris-Montparnasse', $links[0]['evidence']['route']['from']['name'], 'la gare retenue, telle qu\'en base');
    }

    public function test_a_future_relation_links_nothing(): void
    {
        $this->assertSame([], $this->links($this->relations(), "Les rames seront redéployées sur la relation Argentan - Granville.\n"));
    }

    public function test_the_period_of_the_sentence_is_kept_with_the_relation(): void
    {
        $links = $this->links($this->relations(), "La CC 80001 rejoint la ligne Paris \u{2014} Argentan \u{2014} Granville jusqu'en 1977.\n", 'Carrière');

        $this->assertSame(['395000', '405000', '430000'], $this->codes($links));
        $evidence = $links[0]['evidence'];
        $this->assertSame('1977-01-01', $evidence['period']['to']['date']);
        $this->assertSame('Paris - Argentan - Granville', $evidence['route']['label']);
        $this->assertSame(2030, $evidence['route']['era_year'], 'année de la phrase (1977) avant la première ère : époque actuelle');
    }

    public function test_a_narrative_future_in_an_old_story_still_links(): void
    {
        $links = $this->links($this->relations(), "Les X 2800 continueront de circuler sur la relation Argentan - Granville jusqu'en 1950, année où elles seront remplacées par des autorails plus récents.\n");

        $this->assertSame(['405000'], $this->codes($links), 'futur de récit : toutes les années citées ont plus de quinze ans');
    }

    public function test_a_relation_written_as_one_hyphenated_word_is_split_at_the_right_hyphen(): void
    {
        $links = $this->links($this->relations(), "Les autorails assurent la relation Argentan-Granville.\n");

        $this->assertSame(['405000'], $this->codes($links));
        $this->assertSame('Argentan - Granville', $links[0]['evidence']['route']['label']);
    }

    public function test_a_hyphenated_line_named_for_its_works_or_as_a_link_label_links_nothing(): void
    {
        $relations = $this->relations();

        $this->assertSame([], $this->links($relations, "Après l'électrification de la ligne Argentan-Granville, les rames changent.\n"));
        $this->assertSame([], $this->links($relations, "Elles roulent sur la ligne [[Ligne d'Argentan à Granville|Argentan-Granville]] et ailleurs.\n"), 'le lien direct est lu par LineMaterialLinker');
    }

    public function test_an_electric_series_is_not_routed_over_a_line_never_electrified(): void
    {
        foreach (['A1' => false, 'A2' => true, 'A3' => true] as $code => $electrified) {
            $this->lines[$code] = Line::factory()->create(['code' => $code, 'name' => "Ligne {$code}", 'is_lgv' => false, 'electrified' => $electrified]);
        }
        foreach (['Arras' => [2.78, 50.29], 'Hazebrouck' => [2.54, 50.72], 'Boulogne-Ville' => [1.61, 50.72]] as $name => [$lng, $lat]) {
            $this->stations[$name] = Station::factory()->create(['name' => $name, 'geom' => Point::makeGeodetic($lat, $lng)]);
        }
        // Par Saint-Pol (ligne jamais électrifiée) : 100 km ; par Hazebrouck (électrifiée) : 60 + 80 km.
        $this->edge('Arras', 'Boulogne-Ville', 'A1', 100);
        $this->edge('Arras', 'Hazebrouck', 'A2', 60);
        $this->edge('Hazebrouck', 'Boulogne-Ville', 'A3', 80);
        $relations = $this->relations();
        $page = ['title' => 'TGV POS', 'revid' => 7, 'sections' => [['title' => 'Service', 'level' => 2, 'text' => "* Arras - Boulogne-Ville\n"]]];

        $thermal = $relations->links($page, ['fast' => true, 'from' => 2007, 'to' => null], new \DateTimeImmutable('2026-11-20'));
        $electric = $relations->links($page, ['fast' => true, 'from' => 2007, 'to' => null, 'electric' => true], new \DateTimeImmutable('2026-11-20'));

        $this->assertSame(['A1'], $this->codes($thermal));
        $this->assertSame(['A2', 'A3'], $this->codes($electric));
        $this->assertTrue(LineMaterialLinker::isElectricOnly('rame_tgv', null));
        $this->assertTrue(LineMaterialLinker::isElectricOnly('automotrice', '[{"label": "25 kV 50 Hz"}]'));
        $this->assertFalse(LineMaterialLinker::isElectricOnly('automotrice', null), 'une « automotrice » sans système connu peut être thermique');
        $this->assertFalse(LineMaterialLinker::isElectricOnly('bimode', '[{"label": "25 kV 50 Hz"}]'));
    }

    /**
     * Ligne dont seul le bout d'une grande gare est sous caténaire (Brive - Toulouse par Capdenac : 1 km sur
     * 249) : une série électrique ne la suit pas, et elle ne rend pas Paris - Toulouse ambigu. Une ligne non
     * électrifiée qui partage une arête avec une ligne électrifiée (tronc commun avant Cahors) n'est pas
     * comptée dans le trajet d'une série électrique.
     */
    public function test_an_electric_series_ignores_a_line_electrified_only_at_a_station_throat(): void
    {
        foreach (['B1' => true, 'B2' => true, 'B3' => true] as $code => $electrified) {
            $this->lines[$code] = Line::factory()->create(['code' => $code, 'name' => "Ligne {$code}", 'is_lgv' => false, 'electrified' => $electrified]);
        }
        foreach (['Brive' => [1.52, 45.15], 'Capdenac' => [2.08, 44.58], 'Cahors' => [1.44, 44.45], 'Toulouse' => [1.45, 43.61]] as $name => [$lng, $lat]) {
            $this->stations[$name] = Station::factory()->create(['name' => $name, 'geom' => Point::makeGeodetic($lat, $lng)]);
        }
        // Par Cahors (B1, électrifiée) : 100 + 110 km ; par Capdenac (B2, 1 km électrifié sur 220) : 110 + 110 km.
        $this->edge('Brive', 'Cahors', 'B1', 100);
        $this->edge('Cahors', 'Toulouse', 'B1', 110);
        $this->edge('Brive', 'Capdenac', 'B2', 110);
        $this->edge('Capdenac', 'Toulouse', 'B2', 110);
        $this->segment('B1', 210, 210);
        $this->segment('B2', 220, 1);
        $page = ['title' => 'BB 26000', 'revid' => 7, 'sections' => [['title' => 'Service', 'level' => 2, 'text' => "* Brive - Toulouse\n"]]];
        $material = ['fast' => false, 'from' => 1990, 'to' => null];

        $this->assertSame([], $this->codes($this->relations()->links($page, $material, new \DateTimeImmutable('2026-11-20'))), 'en thermique : deux itinéraires proches');
        $this->assertSame(['B1'], $this->codes($this->relations()->links($page, $material + ['electric' => true], new \DateTimeImmutable('2026-11-20'))));

        // Tronc commun : l'arête Brive - Cahors porte aussi 6 km de B3, jamais électrifiée selon ses tronçons.
        $this->segment('B3', 50, 0);
        DB::table('station_edges')->where('from_station_id', $this->stations['Brive']->id)->where('line_id', $this->lines['B1']->id)
            ->update(['line_ids' => '{'.$this->lines['B1']->id.','.$this->lines['B3']->id.'}', 'line_lengths' => '{94000,6000}']);
        $this->assertSame(['B1'], $this->codes($this->relations()->links($page, $material + ['electric' => true], new \DateTimeImmutable('2026-11-20'))));
    }

    /** Tronçon de ligne à l'ère courante, électrification connue (source sncf) : longueur et part électrifiée en km. */
    private function segment(string $line, int $km, int $electrifiedKm): void
    {
        foreach (array_filter([[$km - $electrifiedKm, false], [$electrifiedKm, true]], fn (array $s): bool => $s[0] > 0) as [$length, $electrified]) {
            DB::table('line_segments')->insert([
                'line_id' => $this->lines[$line]->id, 'era_id' => $this->era->id, 'status' => 'in_service',
                'geom' => DB::raw("ST_GeomFromText('LINESTRING(1 44, 1.1 44.1)', 4326)"), 'length_meters' => $length * 1000,
                'electrified' => $electrified, 'electrification_source' => 'sncf',
            ]);
        }
    }

    /**
     * « Paris » : gares « Paris-… » du graphe. Une commune lointaine au nom composé (Paris-l'Hôpital, en
     * Saône-et-Loire) ne rend pas le nom introuvable ; deux villes de taille voisine, si.
     */
    public function test_a_far_away_compound_town_name_does_not_hide_the_city(): void
    {
        foreach (['Paris-Austerlitz' => [2.37, 48.84], 'Paris-Est' => [2.36, 48.88], 'Paris-Nord' => [2.36, 48.88], "Paris-l'Hôpital" => [4.64, 46.91]] as $name => [$lng, $lat]) {
            $this->stations[$name] = Station::factory()->create(['name' => $name, 'geom' => Point::makeGeodetic($lat, $lng)]);
        }
        $this->edge('Paris-Austerlitz', 'Dreux', '395000', 90);
        $this->edge('Paris-Est', 'Paris-Nord', 'L1', 1);
        $this->edge('Paris-Nord', 'Paris-Montparnasse', 'L1', 6);
        $this->edge("Paris-l'Hôpital", 'Surdon', 'L1', 400);
        $router = new RelationRouter;

        $found = $router->resolve('Paris', 2030);
        $this->assertNotContains($this->stations["Paris-l'Hôpital"]->id, $found);
        $this->assertContains($this->stations['Paris-Montparnasse']->id, $found);
        $this->assertCount(4, $found);

        foreach (['Lille-Flandres' => [3.07, 50.64], 'Lille-Europe' => [3.08, 50.64], 'Lille-sur-Mer' => [-1.0, 46.0], 'Lille-Plage' => [-1.0, 46.01]] as $name => [$lng, $lat]) {
            $this->stations[$name] = Station::factory()->create(['name' => $name, 'geom' => Point::makeGeodetic($lat, $lng)]);
        }
        $this->edge('Lille-Flandres', 'Lille-Europe', 'L1', 1);
        $this->edge('Lille-sur-Mer', 'Lille-Plage', 'L1', 1);
        $this->assertSame([], (new RelationRouter)->resolve('Lille', 2030), 'deux villes de taille voisine : nom ambigu');
    }

    public function test_a_city_leaves_from_its_passenger_station_and_labels_are_readable(): void
    {
        $technical = $this->stations['Argentan (IE)'] = Station::factory()->create(['name' => 'Argentan (IE)', 'geom' => Point::makeGeodetic(48.75, -0.02)]);
        $this->edge('Argentan', 'Argentan (IE)', '430000', 1);
        $router = new RelationRouter;

        $this->assertSame([$this->stations['Argentan']->id], $router->resolve('Argentan', 2030), 'l\'installation embranchée est laissée de côté');
        $this->assertNotContains($technical->id, $router->resolve('Argentan', 2030));
        $this->assertSame('Saint-Malo', RelationRouter::readable('St-Malo'));
        $this->assertSame('Pontorson-Mont-Saint-Michel', RelationRouter::readable('Pontorson-Mont-St-Michel'));
        $this->assertSame('Sainte-Pazanne', RelationRouter::readable('Ste-Pazanne'));
        $this->assertSame('Honfleur', RelationRouter::readable('Honfleur (IE)'));
        $this->assertSame('Lyon-Perrache', RelationRouter::readable('Lyon-Perrache (MIN)'));
        $this->assertSame('Metz', RelationRouter::readable('Metz-n° 2'));
        $this->assertSame('Bize (Aude)', RelationRouter::readable('Bize (Aude)'), 'une précision de lieu reste');
        $this->assertSame('Stade', RelationRouter::readable('Stade'));
    }

    /**
     * Graphe d'une ère passée troué : en 1970, le kilomètre de la ligne 905 entre Vénissieux et Saint-Fons
     * manque, et Lyon - Grenoble passait par Valence. Le chemin de l'époque actuelle, plus court et sur des
     * lignes déjà là en 1970, est retenu ; pas quand il emprunte une ligne absente du graphe de l'ère.
     */
    public function test_a_gap_in_a_past_era_graph_is_bridged_by_the_current_graph(): void
    {
        $past = Era::factory()->create(['year' => 1970, 'is_current' => false]);
        foreach (['830' => 'Ligne 830', '905' => 'Ligne 905', '908' => 'Ligne 908', 'NEW' => 'Ligne NEW'] as $code => $name) {
            $this->lines[$code] = Line::factory()->create(['code' => $code, 'name' => $name, 'is_lgv' => false]);
        }
        $places = ['Lyon-Perrache' => [4.83, 45.75], 'Vénissieux' => [4.88, 45.71], 'St-Fons' => [4.86, 45.70], 'Valence' => [4.89, 44.93],
            'Grenoble' => [5.71, 45.19], 'Moirans' => [5.57, 45.32], 'Chambéry' => [5.92, 45.57]];
        foreach ($places as $name => [$lng, $lat]) {
            $this->stations[$name] = Station::factory()->create(['name' => $name, 'geom' => Point::makeGeodetic($lat, $lng)]);
        }
        foreach ([$this->era, $past] as $era) {
            $this->edge('Lyon-Perrache', 'St-Fons', '830', 6, $era);
            $this->edge('St-Fons', 'Valence', '830', 95, $era);
            $this->edge('Valence', 'Moirans', '908', 75, $era);
            $this->edge('Moirans', 'Grenoble', '905', 20, $era);
            $this->edge('Vénissieux', 'Moirans', '905', 95, $era);
        }
        $this->edge('St-Fons', 'Vénissieux', '905', 6);
        $relations = $this->relations();

        $links = $this->links($relations, "* [[Gare de Lyon-Perrache|Lyon]] - [[Gare de Grenoble|Grenoble]]\n");

        $this->assertSame(['830', '905'], $this->codes($links), 'par Vénissieux, pas par Valence');
        $this->assertSame(1970, $links[0]['evidence']['route']['era_year'], 'l\'ère de la phrase reste dans la preuve');

        // Ligne nouvelle (absente en 1970) : le chemin actuel n'est pas repris.
        DB::table('station_edges')->where('era_id', $this->era->id)->where('line_id', $this->lines['905']->id)
            ->where('from_station_id', $this->stations['St-Fons']->id)->update(['line_id' => $this->lines['NEW']->id, 'line_ids' => '{'.$this->lines['NEW']->id.'}']);
        $links = $this->links($this->relations(), "* [[Gare de Lyon-Perrache|Lyon]] - [[Gare de Grenoble|Grenoble]]\n");
        $this->assertSame(['830', '905', '908'], $this->codes($links));
    }

    /**
     * Ligne directe trouée dans le graphe : la ligne de Motteville à Montérolier-Buchy n'y a qu'un bout, et le
     * plus court chemin faisait 250 km par Rouen pour 35 km à vol d'oiseau. Les deux arrêts sont les extrémités
     * nommées de la ligne : elle seule est retenue. Une ligne nommée n'écarte pas un chemin normal.
     */
    public function test_a_relation_between_the_named_ends_of_a_line_follows_that_line(): void
    {
        $this->lines['354000'] = Line::factory()->create(['code' => '354000', 'name' => 'Ligne de Motteville à Montérolier-Buchy', 'is_lgv' => false]);
        foreach (['R1' => 'Ligne R1', 'R2' => 'Ligne R2'] as $code => $name) {
            $this->lines[$code] = Line::factory()->create(['code' => $code, 'name' => $name, 'is_lgv' => false]);
        }
        $places = ['Motteville' => [0.85, 49.63], 'St-Ouen-du-Breuil' => [0.99, 49.63], 'Montérolier-Buchy' => [1.33, 49.55], 'Rouen-Rive-Droite' => [1.09, 49.45]];
        foreach ($places as $name => [$lng, $lat]) {
            $this->stations[$name] = Station::factory()->create(['name' => $name, 'geom' => Point::makeGeodetic($lat, $lng)]);
        }
        $this->edge('Motteville', 'St-Ouen-du-Breuil', '354000', 12);
        $this->edge('Motteville', 'Rouen-Rive-Droite', 'R1', 35);
        $this->edge('Rouen-Rive-Droite', 'Montérolier-Buchy', 'R2', 35);

        $links = $this->links($this->relations(), "* [[Gare de Montérolier - Buchy|Montérolier]] - [[Gare de Motteville|Motteville]]\n");

        $this->assertSame(['354000'], $this->codes($links));
        $this->assertSame($this->stations['Montérolier-Buchy']->id, $links[0]['evidence']['route']['from']['id']);
        $this->assertSame(['motteville', 'monterolier-buchy'], RelationRouter::namedEnds('Ligne de Motteville à Montérolier-Buchy'));
        $this->assertSame(['lyon-perrache p1', 'marseille-st-charles'], RelationRouter::namedEnds('Ligne de Lyon-Perrache P1 à Marseille-St-Charles (via Grenoble)'));
        $this->assertNull(RelationRouter::namedEnds('Raccordement de Versailles-Matelots'));

        // Chemin normal (70 km pour 35 km à vol d'oiseau) : la ligne nommée ne le remplace pas.
        DB::table('station_edges')->where('line_id', $this->lines['R1']->id)->update(['length_m' => 20000, 'line_lengths' => '{20000}']);
        DB::table('station_edges')->where('line_id', $this->lines['R2']->id)->update(['length_m' => 25000, 'line_lengths' => '{25000}']);
        $links = $this->links($this->relations(), "* [[Gare de Montérolier - Buchy|Montérolier]] - [[Gare de Motteville|Motteville]]\n");
        $this->assertSame(['R1', 'R2'], $this->codes($links));
    }

    /**
     * Gare fermée aux voyageurs, absente du graphe gare à gare (Desvres après 1968) : la relation de fret
     * « Hesdigneul - Desvres » se résout par les voies en service de l'ère, sur la ligne de la gare. Une gare
     * ouverte du même nom passe avant ; une gare fermée sans voie de sa ligne à proximité ne se résout pas.
     */
    public function test_a_freight_relation_reaches_a_station_closed_to_passengers(): void
    {
        $this->lines['310000'] = Line::factory()->create(['code' => '310000', 'name' => 'Ligne 310000', 'is_lgv' => false]);
        foreach (['Hesdigneul' => [1.70, 50.66], 'Desvres' => [1.83, 50.67], 'Pont-de-Briques' => [1.63, 50.69]] as $name => [$lng, $lat]) {
            $this->stations[$name] = Station::factory()->create(['name' => $name, 'geom' => Point::makeGeodetic($lat, $lng)]);
        }
        $this->edge('Pont-de-Briques', 'Hesdigneul', '310000', 6);
        $hesdigneul = $this->node('Hesdigneul');
        $desvres = $this->node('Desvres', 'closed_station');
        DB::table('network_edges')->insert([
            'era_id' => $this->era->id, 'from_node_id' => $hesdigneul, 'to_node_id' => $desvres, 'line_id' => $this->lines['310000']->id,
            'length_m' => 10000, 'status' => 'in_service', 'traffic_type' => 'freight',
            'geom' => DB::raw('ST_SetSRID(ST_MakeLine(ST_MakePoint(1.70, 50.66), ST_MakePoint(1.83, 50.67)), 4326)'),
        ]);
        DB::table('station_lines')->insert(['station_id' => $this->stations['Desvres']->id, 'line_id' => $this->lines['310000']->id, 'method' => 'test']);
        $text = "* Trains de marchandises Hesdigneul - Desvres\n";

        // Gare ouverte selon station_eras mais absente du graphe (trou du graphe) : pas rattachée.
        DB::table('station_eras')->insert(['station_id' => $this->stations['Desvres']->id, 'era_id' => $this->era->id, 'status' => 'active']);
        $this->assertSame([], (new RelationRouter)->resolve('Desvres', 2030));
        DB::table('station_eras')->where('station_id', $this->stations['Desvres']->id)->update(['status' => 'declassified']);

        // Ligne où aucun des deux arrêts n'a de PK sûr : la relation contourne peut-être un trou, elle est refusée.
        $this->assertCount(0, $this->links($this->relations(), $text));
        DB::table('station_lines')->insert(['station_id' => $this->stations['Hesdigneul']->id, 'line_id' => $this->lines['310000']->id, 'method' => 'test', 'pk_m' => 241000, 'pk_source' => 'sncf']);

        $links = $this->links($this->relations(), $text);

        $this->assertSame(['310000'], $this->codes($links));
        $this->assertSame(['id' => $this->stations['Desvres']->id, 'name' => 'Desvres'], $links[0]['evidence']['route']['to']);
        $router = new RelationRouter;
        $this->assertSame([$this->stations['Desvres']->id], $router->resolve('Desvres', 2030));
        $this->assertSame([], $router->resolve('Granville-Port', 2030), 'nom inconnu');

        // Une gare ouverte du même nom passe avant la gare fermée.
        $open = Station::factory()->create(['name' => 'Desvres', 'geom' => Point::makeGeodetic(48.70, -1.50)]);
        DB::table('station_edges')->insert([
            'era_id' => $this->era->id, 'from_station_id' => $open->id, 'to_station_id' => $this->stations['Granville']->id,
            'line_id' => $this->lines['405000']->id, 'length_m' => 15000, 'line_ids' => '{'.$this->lines['405000']->id.'}', 'line_lengths' => '{15000}',
        ]);
        $this->assertSame([$open->id], (new RelationRouter)->resolve('Desvres', 2030));

        // Sans voie de sa ligne à moins de CLOSED_ATTACH_M, la gare fermée ne se résout pas.
        DB::table('station_edges')->where('from_station_id', $open->id)->delete();
        DB::table('station_lines')->where('station_id', $this->stations['Desvres']->id)->update(['line_id' => $this->lines['405000']->id]);
        $this->assertSame([], (new RelationRouter)->resolve('Desvres', 2030));
    }

    /** Nœud de la topologie de l'ère courante au point d'une gare. */
    private function node(string $station, string $kind = 'station'): int
    {
        $s = $this->stations[$station];

        return (int) DB::table('network_nodes')->insertGetId([
            'era_id' => $this->era->id, 'station_id' => $s->id, 'kind' => $kind,
            'geom' => DB::raw("(SELECT geom FROM stations WHERE id = {$s->id})"),
        ]);
    }

    /**
     * Rester sur sa ligne : Saint-Loubès et Cenon sont deux gares de Paris - Bordeaux (570000) ; le chemin par
     * Chartres - Bordeaux (500000) et le raccordement d'Ambarès est 7 % plus court. La relation garde 570000.
     * Sans PK sûr de l'une des gares sur 570000, le plus court chemin reste. Règle des ères passées seulement.
     */
    public function test_two_stops_of_the_same_line_stay_on_it_rather_than_a_shorter_parallel_line(): void
    {
        // Règle des ères passées : le graphe écrit ici devient celui d'une ère passée.
        $this->era->update(['is_current' => false]);
        Era::factory()->create(['year' => 2040, 'is_current' => true]);
        $this->lines['570000'] = Line::factory()->create(['code' => '570000', 'name' => 'Ligne 570000', 'is_lgv' => false]);
        $this->lines['500000'] = Line::factory()->create(['code' => '500000', 'name' => 'Ligne 500000', 'is_lgv' => false]);
        $this->lines['570321'] = Line::factory()->create(['code' => '570321', 'name' => "Raccordement d'Ambarès", 'is_lgv' => false]);
        foreach (['St-Loubès' => [-0.43, 44.91], 'Bassens' => [-0.52, 44.90], 'Ste-Eulalie' => [-0.47, 44.91], 'Cenon' => [-0.53, 44.86]] as $name => [$lng, $lat]) {
            $this->stations[$name] = Station::factory()->create(['name' => $name, 'geom' => Point::makeGeodetic($lat, $lng)]);
        }
        $this->edge('St-Loubès', 'Bassens', '570000', 10);
        $this->edge('Bassens', 'Cenon', '570000', 5);
        [$m, $l, $j] = [$this->lines['570000']->id, $this->lines['500000']->id, $this->lines['570321']->id];
        DB::table('station_edges')->insert([
            'era_id' => $this->era->id, 'from_station_id' => $this->stations['St-Loubès']->id, 'to_station_id' => $this->stations['Ste-Eulalie']->id,
            'line_id' => $l, 'length_m' => 8000, 'line_ids' => "{{$m},{$j},{$l}}", 'line_lengths' => '{3500,800,3700}',
        ]);
        $this->edge('Ste-Eulalie', 'Cenon', '500000', 6);
        foreach (['St-Loubès' => 565014, 'Cenon' => 579950] as $station => $pk) {
            DB::table('station_lines')->insert(['station_id' => $this->stations[$station]->id, 'line_id' => $m, 'method' => 'test', 'pk_m' => $pk, 'pk_source' => 'sncf']);
        }
        $route = fn (): array => (new RelationRouter)->route([$this->stations['St-Loubès']->id], [$this->stations['Cenon']->id], 2030)['lines'];

        $this->assertSame([$m], $route(), 'par Bassens, sur sa ligne');

        DB::table('station_lines')->where('station_id', $this->stations['Cenon']->id)->update(['pk_source' => 'projected_raw']);
        $this->assertSame([$l], $route(), 'PK non sûr : le plus court chemin');
    }

    /**
     * Tronc commun : l'arête Cahors - Espère-Caillac porte 5,3 km de Monsempron-Libos - Cahors (632000), posée
     * sur la voie de Paris - Toulouse (590000, principale). Ces kilomètres reviennent à 590000, et 632000 n'est
     * plus comptée dans la relation.
     */
    public function test_a_shared_trunk_is_counted_for_the_priority_line(): void
    {
        $this->lines['590000'] = Line::factory()->create(['code' => '590000', 'name' => 'Ligne 590000', 'is_lgv' => false, 'network' => 'principal']);
        $this->lines['632000'] = Line::factory()->create(['code' => '632000', 'name' => 'Ligne 632000', 'is_lgv' => false, 'network' => 'secondaire']);
        foreach (['Cahors' => [1.44, 44.45], 'Espère-Caillac' => [1.44, 44.525]] as $name => [$lng, $lat]) {
            $this->stations[$name] = Station::factory()->create(['name' => $name, 'geom' => Point::makeGeodetic($lat, $lng)]);
        }
        [$m, $l] = [$this->lines['590000']->id, $this->lines['632000']->id];
        $edge = 'ST_SetSRID(ST_MakeLine(ST_MakePoint(1.44, 44.45), ST_MakePoint(1.44, 44.525)), 4326)';
        DB::table('station_edges')->insert([
            'era_id' => $this->era->id, 'from_station_id' => $this->stations['Cahors']->id, 'to_station_id' => $this->stations['Espère-Caillac']->id,
            'line_id' => $l, 'length_m' => 8340, 'line_ids' => "{{$l},{$m}}", 'line_lengths' => '{5268,3072}', 'geom' => DB::raw($edge),
        ]);
        foreach ([[$m, $edge], [$l, 'ST_SetSRID(ST_MakeLine(ST_MakePoint(1.44, 44.45), ST_MakePoint(1.44, 44.497)), 4326)']] as [$line, $geom]) {
            DB::table('line_segments')->insert(['line_id' => $line, 'era_id' => $this->era->id, 'status' => 'in_service', 'traffic_type' => 'mixed', 'geom' => DB::raw($geom)]);
        }
        foreach (['Cahors' => 600084, 'Espère-Caillac' => 591588] as $station => $pk) {
            DB::table('station_lines')->insert(['station_id' => $this->stations[$station]->id, 'line_id' => $m, 'method' => 'test', 'pk_m' => $pk, 'pk_source' => 'sncf']);
        }
        $text = "* Omnibus Cahors - Espère-Caillac\n";

        $this->assertSame(['590000'], $this->codes($this->links($this->relations(), $text)));

        $near = [$l => ['near' => 5220, 'priority' => 1], $m => ['near' => 8300, 'priority' => 2]];
        $this->assertSame([[$l, 40], [$m, 8300]], RelationRouter::shareTrunk([[$l, 5268], [$m, 3072]], [$l => 5268, $m => 3072], $near, [$m]));
        $apart = [[$l, 5268], [$m, 3072]];
        $this->assertSame($apart, RelationRouter::shareTrunk($apart, [$l => 5268, $m => 3072], $near, []), 'ligne prioritaire sans PK sûr aux deux gares');
        $this->assertSame($apart, RelationRouter::shareTrunk($apart, [$l => 5268, $m => 3072], $near, [$m, $l]), 'les deux lignes ont un PK sûr aux deux gares : chacune garde sa part');
        $this->assertSame($apart, RelationRouter::shareTrunk($apart, [$l => 5268, $m => 3072], $near, [$m], [$l]), 'ligne qui traverse une gare de l\'arête : elle garde sa part');
        $this->assertSame($apart, RelationRouter::shareTrunk($apart, [$l => 5268, $m => 3072], [$l => ['near' => 5268, 'priority' => 1], $m => ['near' => 3120, 'priority' => 2]], [$m]), 'voies séparées : rien ne bouge');
    }

    /**
     * « Les Pacific État continueront de circuler sur Paris-Granville jusqu'en 1950, année où elles seront
     * remplacées par des 141 P » (article « 231 État 501 à 783 ») : la série qui remplace reçoit l'itinéraire,
     * avec la période lue pour elle ; une série citée autrement (« comme les 141 P ») ne reçoit rien.
     */
    public function test_the_series_replacing_another_on_an_itinerary_gets_the_itinerary(): void
    {
        $text = "* Les Pacific État continueront de circuler sur [[Ligne de Paris à Granville|Paris-Granville]] jusqu'en [[1950 dans les chemins de fer|1950]], année où elles seront remplacées par des '''[[141 P]]'''.\n";
        $links = $this->relations()->links(
            ['title' => '231 État 501 à 783', 'revid' => 7, 'sections' => [['title' => 'Utilisation', 'level' => 2, 'text' => $text]]],
            ['fast' => false, 'from' => 1914, 'to' => 1966],
            new \DateTimeImmutable('2026-11-20'),
        );

        $successor = array_values(array_filter($links, fn (array $l): bool => ($l['successor'] ?? null) === '141 P'));
        $own = array_values(array_filter($links, fn (array $l): bool => ! isset($l['successor'])));
        $this->assertSame(['395000', '405000', '430000'], $this->codes($successor));
        $this->assertSame(['395000', '405000', '430000'], $this->codes($own));
        $this->assertSame('231 État 501 à 783', $successor[0]['evidence']['route']['replaces']);
        $this->assertSame('1950-01-01', $successor[0]['evidence']['period']['from']['date'] ?? null, 'la série qui remplace arrive en 1950');
        $this->assertNull($successor[0]['evidence']['period']['to'] ?? null);

        $compared = $this->links($this->relations(), "* Elles roulent sur [[Ligne de Paris à Granville|Paris-Granville]], comme les [[141 P]].\n");
        $this->assertSame([], array_filter($compared, fn (array $l): bool => isset($l['successor'])));
    }

    public function test_relations_are_served_with_their_periods(): void
    {
        $material = Material::factory()->create(['slug' => 'x-2800', 'name' => 'X 2800']);
        $link = LineMaterial::query()->create([
            'line_id' => $this->lines['405000']->id, 'material_id' => $material->id, 'source' => 'wikipedia', 'confidence' => 'medium',
            'evidence' => [[
                'kind' => 'wikipedia_material_article', 'article' => 'X 2800', 'revid' => 42, 'section' => 'Services', 'quote' => 'Relation Argentan - Granville',
                'route' => ['kind' => 'relation', 'label' => 'Argentan - Granville', 'from' => ['id' => 1, 'name' => 'Argentan'], 'to' => ['id' => 2, 'name' => 'Granville'], 'service' => 'omnibus'],
            ]],
        ]);
        LineMaterialPeriod::query()->create([
            'line_material_id' => $link->id, 'start_date' => '1975-01-01', 'start_precision' => 'year', 'end_date' => '1985-01-01', 'end_precision' => 'year',
            'confidence' => 'medium', 'origin' => 'material_article', 'source' => 'wikipedia', 'fingerprint' => sha1('x'),
            'relation' => ['label' => 'Argentan - Granville', 'from' => ['id' => 1, 'name' => 'Argentan'], 'to' => ['id' => 2, 'name' => 'Granville'], 'service' => 'omnibus'],
        ]);

        $relations = $link->fresh()?->relations();
        $this->assertSame('Argentan - Granville', $relations[0]['label']);
        $this->assertSame('omnibus', $relations[0]['service']);
        $this->assertSame([['start_date' => '1975-01-01', 'start_precision' => 'year', 'end_date' => '1985-01-01', 'end_precision' => 'year']], $relations[0]['periods']);

        $response = $this->getJson('/api/v1/materials/x-2800')->assertOk();
        $response->assertJsonPath('data.lines.0.relations.0.label', 'Argentan - Granville');
        $response->assertJsonPath('data.lines.0.periods.0.relation.service', 'omnibus');
        $response->assertJsonPath('data.lines.0.evidence.0.route.kind', 'relation');

        // GraphQL : mêmes relations, et la relation de chaque période.
        $this->postJson('/graphql', ['query' => '{ line(slug: "'.$this->lines['405000']->slug.'") { material_links { relations { kind label service from { id name } periods { start_date end_date } } periods { relation { label } } evidence { route { label } } } } }'])
            ->assertOk()
            ->assertJsonPath('data.line.material_links.0.relations.0.label', 'Argentan - Granville')
            ->assertJsonPath('data.line.material_links.0.relations.0.from.name', 'Argentan')
            ->assertJsonPath('data.line.material_links.0.relations.0.periods.0.end_date', '1985-01-01')
            ->assertJsonPath('data.line.material_links.0.periods.0.relation.label', 'Argentan - Granville')
            ->assertJsonPath('data.line.material_links.0.evidence.0.route.label', 'Argentan - Granville');

        // Serveur MCP : `line_materials` donne les relations de chaque série.
        $result = $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'line_materials', 'arguments' => ['line' => $this->lines['405000']->slug]]])
            ->assertOk()->json('result');
        $data = json_decode($result['content'][0]['text'], true);
        $this->assertSame('Argentan - Granville', $data['materials'][0]['relations'][0]['label']);
        $this->assertSame('omnibus', $data['materials'][0]['periods'][0]['relation']['service']);

        // Fiche de la série sur le site : relations de chaque ligne parcourue.
        $this->withCookie('locale', 'fr')->get('/materials/x-2800')->assertOk()
            ->assertInertia(fn ($page) => $page->where('lines.0.relations.0.label', 'Argentan - Granville')->etc());
    }
}
