<?php

namespace Tests\Feature;

use App\Enums\AccidentDatePrecision;
use App\Enums\AccidentKind;
use App\Models\Accident;
use App\Models\AccidentSource;
use App\Services\Accidents\AccidentCandidate;
use App\Services\Accidents\AccidentImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Règle onRoute et recherche d'une fiche déjà en base (knownAccidentId) : chaque condition, une à une, y compris les
 * valeurs nulles, les noms courts et composés, les alias et les dates au mois.
 */
class AccidentOnRouteBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private AccidentImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importer = app(AccidentImporter::class);
    }

    /** @param  array<string, mixed>  $set */
    private static function listed(array $set = []): AccidentCandidate
    {
        $c = new AccidentCandidate('wikipedia-list:fr:test');
        $c->date = '2026-09-11';
        $c->precision = AccidentDatePrecision::Day;
        $c->kind = AccidentKind::Derailment;
        foreach ($set as $k => $v) {
            $c->{$k} = $v;
        }

        return $c;
    }

    /** @param  list<?string>  $names */
    private function route(AccidentCandidate $listed, array $names = [], ?float $lat = null, ?float $lng = null, array $articles = [], ?string $date = '2026-09-11', ?AccidentDatePrecision $precision = AccidentDatePrecision::Day, ?AccidentKind $kind = AccidentKind::Derailment): bool
    {
        return $this->importer->onRoute($listed, $date, $precision, $kind, $names, $lat, $lng, $articles);
    }

    public function test_date_precision_and_kind_must_all_agree_even_with_a_cited_place(): void
    {
        $listed = self::listed(['communeName' => 'Cléon']);
        $this->assertTrue($this->route($listed, ['Cléon']));
        $this->assertFalse($this->route($listed, ['Cléon'], date: null), 'fiche sans date');
        $this->assertFalse($this->route(self::listed(['communeName' => 'Cléon', 'date' => null]), ['Cléon'], date: null), 'ni l\'une ni l\'autre');
        $this->assertFalse($this->route($listed, ['Cléon'], date: '2026-09-12'));
        $this->assertFalse($this->route($listed, ['Cléon'], precision: null));
        $this->assertFalse($this->route($listed, ['Cléon'], precision: AccidentDatePrecision::Month));
        $this->assertFalse($this->route($listed, ['Cléon'], precision: AccidentDatePrecision::Year));
        $this->assertFalse($this->route(self::listed(['communeName' => 'Cléon', 'precision' => AccidentDatePrecision::Month]), ['Cléon']));
        $this->assertFalse($this->route(self::listed(['communeName' => 'Cléon', 'precision' => null]), ['Cléon']));
        $this->assertFalse($this->route($listed, ['Cléon'], kind: null));
        $this->assertFalse($this->route($listed, ['Cléon'], kind: AccidentKind::Collision));
        $this->assertFalse($this->route(self::listed(['communeName' => 'Cléon', 'kind' => null]), ['Cléon'], kind: null), 'nature inconnue des deux côtés : pas de rapprochement');
    }

    public function test_distance_needs_both_positions_and_is_capped(): void
    {
        $listed = self::listed(['lat' => 49.0, 'lng' => 2.0]);
        $km = fn (float $k): float => 49.0 + $k / 111.1949;
        $this->assertTrue($this->route($listed, [], $km(149.0), 2.0));
        $this->assertFalse($this->route($listed, [], $km(151.0), 2.0));
        $this->assertFalse($this->route($listed, [], null, null));
        $this->assertFalse($this->route($listed, [], 49.0, null), 'longitude inconnue');
        $this->assertFalse($this->route($listed, [], null, 2.0), 'latitude inconnue');
        $this->assertFalse($this->route(self::listed(['lat' => 49.0]), [], 49.0, 2.0), 'puce sans longitude');
        $this->assertFalse($this->route(self::listed(['lng' => 2.0]), [], 49.0, 2.0), 'puce sans latitude');
        $this->assertFalse($this->route(self::listed(), [], 49.0, 2.0));
        $this->assertSame(150000, AccidentImporter::ROUTE_MAX_M);
    }

    public function test_a_shared_line_article_joins_the_records_without_place_or_position(): void
    {
        $listed = self::listed(['lineArticles' => ['Ligne de Rouen à Caen']]);
        $this->assertTrue($this->route($listed, [], articles: ['Ligne de Rouen à Caen', 'Autre']));
        $this->assertFalse($this->route($listed, [], articles: ['Autre']));
        $this->assertFalse($this->route($listed, [], articles: []));
        $this->assertFalse($this->route(self::listed(), [], articles: ['Autre']), 'puce sans article de ligne');
    }

    public function test_cited_place_rules(): void
    {
        $cites = fn (string $cited, string $name, array $set = []): bool => $this->route(self::listed(['communeName' => $cited] + $set), [$name]);
        $this->assertTrue($cites('Caen', 'Caen'), 'même nom');
        $this->assertTrue($cites('Caen', 'CAEN'), 'casse ignorée');
        $this->assertTrue($cites('Nurieux', 'Nurieux-Volognat'), 'premier mot d\'un nom composé');
        $this->assertFalse($cites('Nuri', 'Nurieux-Volognat'), 'un début de mot n\'est pas un mot');
        $this->assertFalse($cites('Volognat', 'Nurieux-Volognat'), 'le dernier mot d\'un nom sans espace ne suffit pas');
        $this->assertTrue($cites('Pomponne', 'Lagny Pomponne'), 'mot d\'un nom à espaces');
        $this->assertFalse($cites('Pomponne', 'Gare Pomponnet'), 'mot entier seulement');
        $this->assertFalse($cites('Aix', 'Aix'), 'moins de 4 lettres : ignoré');
        $this->assertTrue($cites('Caen', 'Caen'), '4 lettres : compté');
        $this->assertTrue($cites('Lyon', 'Lyon'));
        $this->assertFalse($cites('Cléon', 'Cléonnet'));
        $this->assertFalse($cites('Caen', ''), 'nom vide');
        $this->assertFalse($cites('Caen', '   '));
        $this->assertFalse($cites('', 'Caen'), 'lieu cité vide');
        $this->assertTrue($cites('Autre', 'Caen', ['stationHint' => 'Caen']), 'gare citée');
        $this->assertTrue($cites('Autre', 'Caen', ['placeHints' => [['Caen', '14']]]), 'autre lieu cité');
        $this->assertFalse($this->route(self::listed(['communeName' => null]), [null, 'Caen']));
    }

    public function test_unicode_and_very_long_names_do_not_break_the_rule(): void
    {
        $this->assertTrue($this->route(self::listed(['communeName' => 'Évreux']), ['Évreux']));
        $this->assertFalse($this->route(self::listed(['communeName' => '東京都']), ['東京都']), 'sans lettres latines : pas de lieu lisible');
        $this->assertFalse($this->route(self::listed(['communeName' => str_repeat('a', 5000)]), ['Caen']));
        $this->assertTrue($this->route(self::listed(['communeName' => 'Caen']), [str_repeat('x ', 3000).'Caen']));
    }

    /** Fiche sourcée (Wikidata) en base, même jour, même nature, commune et position données. */
    private function sourced(string $slug, string $commune, float $lat, float $lng, array $over = []): Accident
    {
        $a = Accident::factory()->create($over + [
            'slug' => $slug, 'occurred_on' => '2026-09-11', 'date_precision' => AccidentDatePrecision::Day->value,
            'kind' => AccidentKind::Derailment->value, 'commune_name' => $commune, 'commune_insee' => null,
            'name' => ['fr' => "Accident de {$commune}", 'en' => null, 'de' => null],
        ]);
        Accident::query()->withoutGlobalScopes()->whereKey($a->id)->update(['geom' => DB::raw("ST_SetSRID(ST_MakePoint({$lng}, {$lat}), 4326)")]);
        AccidentSource::query()->create(['accident_id' => $a->id, 'provider' => 'wikidata', 'external_id' => 'Q'.$a->id, 'role' => 'primary',
            'title' => 'Wikidata', 'url' => 'https://www.wikidata.org/wiki/Q'.$a->id, 'licence' => 'CC0', 'retrieved_at' => now()]);

        return $a;
    }

    private function known(AccidentCandidate $c): ?int
    {
        return (new ReflectionMethod(AccidentImporter::class, 'knownAccidentId'))->invoke($this->importer, $c);
    }

    private function listedBullet(): AccidentCandidate
    {
        // Puce nommant Caen, loin de Cléon (105 km) : seul onRoute peut la rapprocher.
        return self::listed(['communeName' => 'Caen', 'placeHints' => [['Cléon', '76']], 'lat' => 49.18, 'lng' => -0.37]);
    }

    public function test_known_record_by_route_needs_one_unaliased_day_precise_sourced_record(): void
    {
        $cleon = $this->sourced('2026-09-11-cleon', 'Cléon', 49.31, 1.052);
        $this->assertSame($cleon->id, $this->known($this->listedBullet()));
    }

    public function test_known_record_by_route_refuses_an_alias_a_month_precision_and_a_missing_kind(): void
    {
        $kept = $this->sourced('2026-09-11-cleon', 'Cléon', 49.31, 1.052);
        $this->sourced('2026-09-11-cleon-alias', 'Cléon', 49.31, 1.052, ['alias_of_id' => $kept->id]);
        $this->assertSame($kept->id, $this->known($this->listedBullet()), 'l\'alias ne répond pas : une seule fiche');

        $this->assertNull($this->known(self::listed(['communeName' => 'Caen', 'placeHints' => [['Cléon', '76']], 'lat' => 49.18, 'lng' => -0.37, 'kind' => null])));
        $this->assertNull($this->known(self::listed(['communeName' => 'Caen', 'placeHints' => [['Cléon', '76']], 'lat' => 49.18, 'lng' => -0.37, 'precision' => AccidentDatePrecision::Month])));
        $this->assertNull($this->known(self::listed(['date' => null, 'communeName' => 'Caen'])));
    }

    public function test_known_record_by_route_ignores_other_kinds_precisions_and_two_answers(): void
    {
        $this->sourced('2026-09-11-cleon-m', 'Cléon', 49.31, 1.052, ['date_precision' => AccidentDatePrecision::Month->value]);
        $this->assertNull($this->known($this->listedBullet()), 'fiche au mois : jamais rapprochée par le parcours');
        $this->sourced('2026-09-11-cleon-c', 'Cléon', 49.31, 1.052, ['kind' => AccidentKind::Collision->value]);
        $this->assertNull($this->known($this->listedBullet()), 'autre nature');
        $this->sourced('2026-09-11-cleon-d', 'Cléon', 49.31, 1.052);
        $this->sourced('2026-09-11-cleon-e', 'Cléon', 49.31, 1.052);
        $this->assertNull($this->known($this->listedBullet()), 'deux fiches répondent : aucune choisie');
    }
}
