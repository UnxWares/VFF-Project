<?php

namespace Tests\Feature;

use App\Enums\AccidentDatePrecision;
use App\Enums\AccidentKind;
use App\Models\Accident;
use App\Models\AccidentSource;
use App\Services\Accidents\AccidentCandidate;
use App\Services\Accidents\AccidentImporter;
use App\Services\Accidents\AccidentImportResult;
use App\Services\Accidents\AccidentKindClassifier;
use App\Services\Accidents\WikipediaListReader;
use App\Services\DataInvariants;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Doublons créés par la liste de Wikipédia quand une puce nomme une gare ou une ville du parcours du train (terminus
 * de la relation) au lieu du lieu de l'accident (constat du 22/11/2026) : la puce rejoint la fiche sourcée du même
 * jour et de la même nature (AccidentImporter::onRoute), une fiche alias n'est jamais retirée, et l'invariant
 * accidents.same_day_nearby compte les couples restants.
 *
 * Les puces sont des extraits réels, raccourcis, des pages « Liste d'accidents ferroviaires en France » (révisions
 * 241585500 des années 2020, 241007547 des années 2000, 236708402 de 1922) : la lecture de leurs lieux est celle de
 * l'import.
 */
class AccidentRouteDuplicatesTest extends TestCase
{
    use RefreshDatabase;

    private const CLEON = "* le 11 septembre 2026 : un [[Transport express régional|TER]] [[TER Normandie|NOMAD]] reliant [[Rouen]] à [[Caen]] déraille aux alentours de 19h25 au niveau de la commune de [[Cléon (Seine-Maritime)|Cléon]]. Le bilan humain, est de 43 blessés légers et d'un blessé grave.";

    private const SAINT_RAPHAEL = "* le {{date|25 mars 2026}} : un train régional [[TER Sud Provence-Alpes-Côte d'Azur|Zou]], reliant [[Gare de Marseille-Saint-Charles|Marseille]] à [[Gare de Nice-Ville|Nice]] percute un convoi exceptionnel transportant deux [[mobile home]] sur un passage à niveau situé non loin de la [[gare du Dramont]].";

    private const REIMS = '* {{Date|26 août 2021}} : un train de marchandises transportant des céréales se couche sur la voie entre [[Châlons-en-Champagne]] et [[Reims]] ([[Marne (département)|Marne]]) à la hauteur de la voie LGV.';

    private const MORNAY = '* {{date|30|mai|1922}} - Sur la [[ligne de Bourg-en-Bresse à Bellegarde]], entre les [[Gare de Cize - Bolozon|gares de Cize-Bolozon]] et [[Gare de Nurieux|de Nurieux]], dans le long tunnel de Mornay, vers 21 heures, le train de voyageurs venant de [[Bourg-en-Bresse|Bourg]] percute à faible vitesse la machine de renfort placée à l\'arrière du train de marchandises.';

    private const FROMENTAL = '* {{Date-|12|juillet|2013}} - [[Fromental (Haute-Vienne)]] : une locomotive déraille, après que la voie ferrée servant à acheminer des déchets nucléaires vers le site [[Areva]] de [[Bessines-sur-Gartempe]], a été sabotée.';

    private AccidentImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importer = app(AccidentImporter::class);
    }

    /** Candidat tel que WikipediaListCollector le construit, placé où l'import l'avait placé (lieu retenu). */
    private function listed(string $bullet, ?float $lat, ?float $lng): AccidentCandidate
    {
        $entries = (new WikipediaListReader(new AccidentKindClassifier))->read("== Liste ==\n{$bullet}\n");
        $this->assertCount(1, $entries);
        $e = $entries[0];
        $c = new AccidentCandidate('wikipedia-list:fr:'.$e['date']);
        $c->date = $e['date'];
        $c->precision = $e['precision'];
        $c->kind = $e['kind'];
        [$c->communeName, $c->departmentCode] = $e['places'][0];
        $c->placeHints = array_slice($e['places'], 1);
        $c->stationHint = $e['station'];
        $c->lineArticles = $e['line_articles'];
        $c->lat = $lat;
        $c->lng = $lng;

        return $c;
    }

    /** @param  list<?string>  $names */
    private function onRoute(AccidentCandidate $listed, string $date, AccidentKind $kind, array $names, ?float $lat, ?float $lng): bool
    {
        return $this->importer->onRoute($listed, $date, AccidentDatePrecision::Day, $kind, $names, $lat, $lng);
    }

    public function test_a_bullet_that_names_the_terminus_of_the_train_joins_the_sourced_record(): void
    {
        // Caen, terminus de la relation Rouen - Caen, à 105 km de Cléon : la puce cite aussi Cléon.
        $caen = $this->listed(self::CLEON, 49.18, -0.37);
        $this->assertSame('Caen', $caen->communeName);
        $this->assertTrue($this->onRoute($caen, '2026-09-11', AccidentKind::Derailment, ['Cléon', 'Accident ferroviaire de Cléon'], 49.31, 1.052));

        // Marseille, départ de la relation Marseille - Nice, à 119 km du passage à niveau de Saint-Raphaël (BEA-TT).
        $marseille = $this->listed(self::SAINT_RAPHAEL, 43.2961, 5.3699);
        $this->assertTrue($this->onRoute($marseille, '2026-03-25', AccidentKind::LevelCrossing, ['Saint-Raphaël'], 43.4253, 6.7684));

        // Reims, bout du trajet, à 32 km de Saint-Hilaire-au-Temple (BEA-TT).
        $reims = $this->listed(self::REIMS, 49.2628, 4.0347);
        $this->assertTrue($this->onRoute($reims, '2021-08-26', AccidentKind::Derailment, ['Saint-Hilaire-au-Temple'], 49.0539, 4.3906));

        // Gare de Nurieux, commune de Nurieux-Volognat (tunnel de Mornay, Wikidata), sans position pour la puce.
        $nurieux = $this->listed(self::MORNAY, null, null);
        $this->assertTrue($this->onRoute($nurieux, '1922-05-30', AccidentKind::Collision, ['Nurieux-Volognat'], 46.1871, 5.5067));
    }

    public function test_a_real_distinct_accident_of_the_same_day_keeps_its_record(): void
    {
        // Sabotage de Fromental et catastrophe de Brétigny-sur-Orge, le même jour, à 281 km : deux accidents.
        $fromental = $this->listed(self::FROMENTAL, 46.17, 1.40);
        $this->assertFalse($this->onRoute($fromental, '2013-07-12', AccidentKind::Derailment, ['Brétigny-sur-Orge', 'Accident ferroviaire de Brétigny-sur-Orge'], 48.6073, 2.3023));

        $caen = $this->listed(self::CLEON, 49.18, -0.37);
        $this->assertFalse($this->onRoute($caen, '2026-09-11', AccidentKind::Collision, ['Cléon'], 49.31, 1.052), 'nature différente');
        $this->assertFalse($this->onRoute($caen, '2026-09-12', AccidentKind::Derailment, ['Cléon'], 49.31, 1.052), 'autre jour');
        $this->assertFalse($this->importer->onRoute($caen, '2026-09-11', AccidentDatePrecision::Month, AccidentKind::Derailment, ['Cléon'], 49.31, 1.052), 'date au mois');
    }

    public function test_only_one_sourced_record_may_answer(): void
    {
        $caen = $this->listed(self::CLEON, 49.18, -0.37);
        $cleon = new AccidentCandidate('wikidata:Q141442425');
        [$cleon->date, $cleon->precision, $cleon->kind, $cleon->communeName, $cleon->lat, $cleon->lng]
            = ['2026-09-11', AccidentDatePrecision::Day, AccidentKind::Derailment, 'Cléon', 49.31, 1.052];
        $other = clone $cleon;
        $other->communeName = 'Lisieux';
        [$other->lat, $other->lng] = [49.146, 0.226];
        $route = new ReflectionMethod(AccidentImporter::class, 'routeTarget');

        $this->assertSame($cleon, $route->invoke($this->importer, [$cleon], $caen));
        $this->assertNull($route->invoke($this->importer, [$cleon, $other], $caen), 'deux fiches répondent : aucune n\'est choisie');
    }

    public function test_a_declared_alias_is_never_retired_when_its_list_source_moves(): void
    {
        $kept = Accident::factory()->create(['slug' => '2026-09-11-cleon']);
        $alias = Accident::factory()->create(['slug' => '2026-09-11-caen', 'alias_of_id' => $kept->id]);
        $plain = Accident::factory()->create(['slug' => '2026-09-11-caen-2']);
        foreach ([$alias, $plain] as $a) {
            AccidentSource::query()->create(['accident_id' => $a->id, 'provider' => 'wikipedia', 'external_id' => 'fr:'.$a->slug, 'role' => 'list',
                'title' => 'Liste', 'url' => 'https://fr.wikipedia.org/wiki/Liste#'.$a->slug, 'licence' => 'CC BY-SA 4.0', 'retrieved_at' => now()]);
        }
        $retire = new ReflectionMethod(AccidentImporter::class, 'retire');

        $retire->invoke($this->importer, $alias->id, $kept, new AccidentImportResult);
        $retire->invoke($this->importer, $plain->id, $kept, new AccidentImportResult);

        $this->assertTrue(Accident::query()->withoutGlobalScopes()->whereKey($alias->id)->exists(), 'l\'alias garde son adresse (301)');
        $this->assertFalse(Accident::query()->withoutGlobalScopes()->whereKey($plain->id)->exists(), 'un doublon ordinaire est retiré comme avant');
        $this->assertSame('2026-09-11-cleon', Accident::redirectSlugFor('2026-09-11-caen'));
    }

    public function test_the_invariant_counts_nearby_same_day_records_without_alias(): void
    {
        $count = fn (): int => (new DataInvariants(['accidents.same_day_nearby']))->run()[0]['count'];
        $a = Accident::factory()->on('1999-05-05')->at(49.31, 1.052)->create(['slug' => '1999-05-05-a', 'kind' => AccidentKind::Derailment->value]);
        $b = Accident::factory()->on('1999-05-05')->at(49.18, -0.37)->create(['slug' => '1999-05-05-b', 'kind' => AccidentKind::Derailment->value]);
        Accident::factory()->on('1999-05-05')->at(43.3, 5.37)->create(['slug' => '1999-05-05-loin', 'kind' => AccidentKind::Derailment->value]);
        Accident::factory()->on('1999-05-05')->at(49.2, -0.3)->create(['slug' => '1999-05-05-autre-nature', 'kind' => AccidentKind::Fire->value]);

        $result = (new DataInvariants(['accidents.same_day_nearby']))->run()[0];
        $this->assertArrayNotHasKey('error', $result);
        $this->assertSame(1, $result['count']);
        $this->assertStringContainsString('1999-05-05-a et 1999-05-05-b', $result['examples'][0]);

        $b->forceFill(['alias_of_id' => $a->id])->save();
        $this->assertSame(0, $count(), 'un alias déclaré n\'est plus un doublon');
    }

    public function test_the_reviewed_distinct_pairs_are_not_counted(): void
    {
        foreach (require database_path('data/accident_distinct_pairs.php') as [$x, $y, $reason]) {
            $this->assertNotSame('', $reason);
            Accident::factory()->on(substr($x, 0, 10))->at(44.74, -0.53)->create(['slug' => $x, 'kind' => AccidentKind::Collision->value]);
            Accident::factory()->on(substr($y, 0, 10))->at(44.68, -0.40)->create(['slug' => $y, 'kind' => AccidentKind::Collision->value]);
        }

        $this->assertSame(0, (new DataInvariants(['accidents.same_day_nearby']))->run()[0]['count']);
    }

    public function test_the_route_distance_stays_below_the_nearest_reviewed_distinct_accident(): void
    {
        $this->assertSame(150000, AccidentImporter::ROUTE_MAX_M);
        $this->assertLessThan(281000, AccidentImporter::ROUTE_MAX_M, 'Fromental et Brétigny-sur-Orge, 12/07/2013');
        $this->assertGreaterThan(119000, AccidentImporter::ROUTE_MAX_M, 'Marseille pour Saint-Raphaël, 25/03/2026');
    }
}
