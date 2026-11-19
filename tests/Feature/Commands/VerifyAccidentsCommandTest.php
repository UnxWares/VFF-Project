<?php

namespace Tests\Feature\Commands;

use App\Enums\AccidentKind;
use App\Models\Accident;
use App\Models\AccidentLine;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * vff:verify-accidents sur de fausses fiches, sans réseau : fiche « ok » seulement si une source primaire
 * (BEA-TT) ou deux sources indépendantes donnent la date et la commune ; une liste de Wikipédia seule n'y
 * suffit pas ; Wikidata importé de Wikipédia ne compte pas à part ; une contradiction bloque ; une commune
 * voisine ne contredit rien ; doublon gardé en alias ; nature relue dans la source ; ligne citée rattachée avec
 * son permalien ; simulation sans écriture ; second passage sans changement.
 */
class VerifyAccidentsCommandTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    private const ACCIDENT_TABLES = ['accidents', 'accident_lines', 'accident_sources'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
        $this->commune('66108', 'Millas', 'millas', '66', 2.66, 42.66, 2.76, 42.72);
        $this->commune('66200', 'Saint-Féliu-d\'Avall', 'saint feliu d avall', '66', 2.76, 42.66, 2.86, 42.72);
        $this->commune('13055', 'Marseille', 'marseille', '13', 5.30, 43.20, 5.50, 43.35);
    }

    public function test_primary_source_confirms_date_and_commune_at_the_precision_it_gives(): void
    {
        $a = $this->accident('2017-12-14-millas', '2017-12-14', '66108', 'commune');
        $this->source($a, 'bea-tt', 'report', '2017-12-14-millas-pn', ['date' => '2017-12-14', 'mode' => 'PN'], 'Collision entre un TER et un autocar sur le PN 25 à Millas (66)');
        $a->update(['review_notes' => [['code' => 'location_commune_only', 'detail' => 'Millas'], ['code' => 'line_uncertain', 'detail' => '2 lignes']]]);

        $this->artisan('vff:verify-accidents')->assertSuccessful();

        $a->refresh();
        $this->assertSame('ok', $a->review_status->value);
        $this->assertSame('commune', $a->location_precision?->value, 'la précision du lieu reste celle de la source');
        $this->assertSame(['location_commune_only', 'line_uncertain'], array_column($a->review_notes, 'code'), 'les réserves sur le lieu et la ligne restent affichées');
        $this->assertSame('primary_source', $a->verification['rule']);
        $this->assertSame(['official'], $a->verification['families']['date']);
        $confirmed = $a->verification['place']['confirmed_by'][0];
        $this->assertSame('66108', $confirmed['commune_insee']);
        $this->assertSame('bea-tt', $confirmed['provider']);
        $this->assertSame($a->sources()->first()->retrieved_at->toIso8601String(), $confirmed['retrieved_at'], 'la date de lecture de la source est gardée');
        $this->assertNotNull($a->verified_at);
    }

    public function test_a_wikipedia_list_alone_never_confirms(): void
    {
        $a = $this->accident('1910-01-02-millas', '1910-01-02', '66108', 'commune');
        $this->source($a, 'wikipedia', 'list', 'fr:1910-01-02-millas', ['date' => '1910-01-02', 'place' => 'Millas', 'department' => '66']);

        $this->artisan('vff:verify-accidents')->assertSuccessful();

        $a->refresh();
        $this->assertSame('to_review', $a->review_status->value);
        $this->assertNull($a->verification['rule'] ?? null);
        $this->assertSame(['wikipedia'], $a->verification['families']['date']);
    }

    public function test_blocking_notes_keep_a_fiche_to_review_even_when_sources_agree(): void
    {
        $a = $this->accident('2017-12-14-millas', '2017-12-14', '66108', 'exact');
        $this->source($a, 'bea-tt', 'report', 'bea-1', ['date' => '2017-12-14', 'mode' => 'F'], 'Déraillement à Millas (66)');
        $a->update(['review_notes' => [['code' => 'toll_mismatch', 'detail' => 'BEA-TT 6, Wikidata 7']]]);

        $this->artisan('vff:verify-accidents')->assertSuccessful();

        $this->assertSame('to_review', $a->refresh()->review_status->value);
        $this->assertSame('primary_source', $a->verification['rule']);
    }

    public function test_wikidata_and_article_agree_but_a_wikidata_import_from_wikipedia_does_not_count_apart(): void
    {
        $bare = $this->accident('2001-05-06-millas', '2001-05-06', '66108', 'exact');
        $this->wikidataAndArticle($bare, 'Q101', '2001-05-06', claims: $this->claims(refs: []));
        $imported = $this->accident('2002-05-06-millas', '2002-05-06', '66108', 'exact');
        $this->wikidataAndArticle($imported, 'Q102', '2002-05-06', claims: $this->claims(refs: ['P143']));
        $sourced = $this->accident('2003-05-06-millas', '2003-05-06', '66108', 'exact');
        $this->wikidataAndArticle($sourced, 'Q103', '2003-05-06', claims: $this->claims(refs: ['P854']));

        $this->artisan('vff:verify-accidents')->assertSuccessful();

        $this->assertSame('ok', $bare->refresh()->review_status->value);
        $this->assertSame('wikidata_wikipedia', $bare->verification['rule'], 'Wikidata sans référence : distinguée, car elle peut être copiée de Wikipédia');
        $this->assertSame('to_review', $imported->refresh()->review_status->value, 'importé de Wikipédia : une copie ne confirme pas');
        $this->assertNull($imported->verification['rule'] ?? null);
        $this->assertSame('ok', $sourced->refresh()->review_status->value);
        $this->assertSame('two_sources', $sourced->verification['rule']);
    }

    public function test_conflicting_sources_block_and_downgrade_an_ok_fiche(): void
    {
        $a = $this->accident('2005-05-06-millas', '2005-05-06', '66108', 'exact', ['review_status' => 'ok']);
        $this->wikidataAndArticle($a, 'Q201', '2005-05-06', claims: $this->claims(refs: []));
        // Une troisième source donne un autre jour.
        $this->source($a, 'wikipedia', 'list', 'fr:2005-05-07-millas', ['date' => '2005-05-07', 'place' => 'Millas', 'department' => '66']);

        $this->artisan('vff:verify-accidents')->assertSuccessful();

        $a->refresh();
        $this->assertSame('to_review', $a->review_status->value);
        $this->assertContains('verification_conflict', array_column($a->review_notes, 'code'));
        $this->assertSame('2005-05-07', $a->verification['date']['disagreeing'][0]['date']);
        $this->assertNull($a->verification['rule'] ?? null);
    }

    public function test_a_nearby_commune_contradicts_nothing_but_a_far_one_does(): void
    {
        $near = $this->accident('2006-05-06-millas', '2006-05-06', '66108', 'exact', ['review_status' => 'ok']);
        $this->wikidataAndArticle($near, 'Q301', '2006-05-06', claims: $this->claims(refs: []));
        $this->source($near, 'wikipedia', 'list', 'fr:2006-05-06-sf', ['date' => '2006-05-06', 'place' => 'Saint-Féliu-d\'Avall', 'department' => '66']);
        $far = $this->accident('2007-05-06-millas', '2007-05-06', '66108', 'exact', ['review_status' => 'ok']);
        $this->wikidataAndArticle($far, 'Q302', '2007-05-06', claims: $this->claims(refs: []));
        $this->source($far, 'wikipedia', 'list', 'fr:2007-05-06-marseille', ['date' => '2007-05-06', 'place' => 'Marseille', 'department' => '13']);

        $this->artisan('vff:verify-accidents')->assertSuccessful();

        $this->assertSame('ok', $near->refresh()->review_status->value);
        $this->assertSame('66200', $near->verification['place']['nearby'][0]['commune_insee']);
        $this->assertSame([], $near->verification['place']['disagreeing']);
        $this->assertSame('to_review', $far->refresh()->review_status->value);
        $this->assertSame('13055', $far->verification['place']['disagreeing'][0]['commune_insee']);
    }

    public function test_two_wikidata_items_for_one_accident_keep_one_published_record_and_an_alias(): void
    {
        $keep = $this->accident('1933-10-24-le-val-dore', '1933-10-24', '66108', 'exact', ['deaths' => 37, 'review_status' => 'ok', 'wikidata_id' => 'Q22247907', 'toll_source' => 'wikipedia']);
        $this->wikidataAndArticle($keep, 'Q22247907', '1933-10-24', claims: $this->claims(refs: []));
        $alias = $this->accident('1933-10-24-conches', '1933-10-24', '66200', 'commune', ['deaths' => 36, 'kind' => 'other', 'wikidata_id' => 'Q28000484', 'lat' => 42.69, 'lng' => 2.80]);
        $this->source($alias, 'wikidata', 'data', 'Q28000484', ['date' => '1933-10-24', 'date_precision' => 'day', 'deaths' => 36]);

        $this->artisan('vff:verify-accidents')->assertSuccessful();

        $alias->refresh();
        $keep->refresh();
        $this->assertSame($keep->id, $alias->alias_of_id);
        $this->assertNull($keep->alias_of_id);
        $this->assertSame(['1933-10-24-le-val-dore'], Accident::query()->pluck('slug')->all(), 'une seule fiche publiée');
        $this->assertSame(['1933-10-24-conches'], Accident::query()->withoutGlobalScopes()->whereNotNull('alias_of_id')->pluck('slug')->all(), 'l\'alias est gardé');
        $this->assertSame(1, $alias->sources()->count(), 'avec sa source');
        $this->assertSame('Q28000484', $keep->verification['aliases'][0]['wikidata_id']);
        $this->assertSame('https://www.wikidata.org/wiki/Q28000484', $keep->verification['aliases'][0]['sources'][0]['url']);
        $this->assertContains('alias_toll_mismatch', array_column($keep->review_notes, 'code'));
        $this->assertSame('to_review', $keep->review_status->value, 'deux bilans pour le même accident : à vérifier');
    }

    public function test_two_list_bullets_of_the_same_day_are_only_flagged(): void
    {
        $a = $this->accident('1910-12-23-millas', '1910-12-23', '66108', 'commune');
        $b = $this->accident('1910-12-23-sf', '1910-12-23', '66200', 'commune', ['lat' => 42.69, 'lng' => 2.80]);
        $this->source($a, 'wikipedia', 'list', 'fr:1910-12-23-millas', ['date' => '1910-12-23', 'place' => 'Millas', 'department' => '66']);
        $this->source($b, 'wikipedia', 'list', 'fr:1910-12-23-sf', ['date' => '1910-12-23', 'place' => 'Saint-Féliu-d\'Avall', 'department' => '66']);

        $this->artisan('vff:verify-accidents')->assertSuccessful();

        $this->assertSame(2, Accident::query()->count(), 'aucune fusion sans description propre');
        $this->assertContains('possible_duplicate', array_column($a->refresh()->review_notes, 'code'));
        $this->assertContains('possible_duplicate', array_column($b->refresh()->review_notes, 'code'));
    }

    public function test_other_kind_is_read_from_the_source_when_it_says_so(): void
    {
        $said = $this->accident('2010-05-06-millas', '2010-05-06', '66108', 'exact', ['kind' => 'other', 'name' => ['fr' => 'Accident ferroviaire à Millas', 'en' => 'Rail accident at Millas', 'de' => 'Bahnunfall in Millas'], 'provenance' => ['name' => 'vff', 'location' => 'wikidata']]);
        $this->source($said, 'wikipedia', 'article', 'fr:Accident de Millas', ['date' => '2010-05-06', 'type' => 'Déraillement', 'lat' => 42.69, 'lng' => 2.7]);
        $silent = $this->accident('2011-05-06-millas', '2011-05-06', '66108', 'exact', ['kind' => 'other']);
        $this->source($silent, 'wikipedia', 'article', 'fr:Autre', ['date' => '2011-05-06', 'lat' => 42.69, 'lng' => 2.7]);

        $this->artisan('vff:verify-accidents', ['--offline' => true])->assertSuccessful();

        $said->refresh();
        $this->assertSame(AccidentKind::Derailment, $said->kind);
        $this->assertSame('wikipedia', $said->provenance['kind']);
        $this->assertSame('Déraillement à Millas', $said->name['fr']);
        $this->assertStringContainsString('un train déraille', $said->summary['fr']);
        $this->assertSame('other', $said->verification['kind']['from']);
        $this->assertSame(AccidentKind::Other, $silent->refresh()->kind, 'une source muette ne fait rien deviner');
    }

    public function test_the_line_a_source_cites_is_linked_with_the_cited_article_and_its_permalink(): void
    {
        $near = $this->rfnLine('679000', 'Ligne de Perpignan à Villefranche', ['slug' => 'rfn-679000', 'wikipedia_title' => 'Ligne de Perpignan à Villefranche']);
        $far = $this->rfnLine('830000', 'Ligne de Paris-Lyon à Marseille', ['slug' => 'rfn-830000', 'wikipedia_title' => 'Ligne de Paris-Lyon à Marseille-Saint-Charles']);
        $a = $this->accident('1930-05-31-millas', '1930-05-31', '66108', 'commune');
        $url = 'https://fr.wikipedia.org/w/index.php?title=Liste&oldid=1#Mai';
        $this->source($a, 'wikipedia', 'list', 'fr:1930-05-31-millas', [
            'date' => '1930-05-31', 'place' => 'Millas', 'department' => '66',
            'line_articles' => ['Ligne de Perpignan à Villefranche', 'Ligne de Paris-Lyon à Marseille-Saint-Charles', 'Ligne introuvable'],
        ], url: $url);
        AccidentLine::query()->create(['accident_id' => $a->id, 'line_id' => $near->id, 'is_primary' => true, 'era_year' => 1930, 'distance_m' => 900,
            'method' => 'proximity', 'confidence' => 'low', 'score' => 0.3]);

        $this->artisan('vff:verify-accidents', ['--offline' => true])
            ->expectsOutputToContain('titres sans ligne VFF : 1')->assertSuccessful();

        $links = AccidentLine::query()->where('accident_id', $a->id)->get()->keyBy('line_id');
        $this->assertSame('Ligne de Perpignan à Villefranche', $links[$near->id]->cited_as);
        $this->assertSame($url, $links[$near->id]->cited_url);
        $this->assertSame('proximity+wikipedia', $links[$near->id]->method);
        $this->assertSame('low', $links[$near->id]->confidence->value, 'la confiance du tracé ne change pas');
        $this->assertTrue($links[$near->id]->is_primary);
        $this->assertSame('Ligne de Paris-Lyon à Marseille-Saint-Charles', $links[$far->id]->cited_as);
        $this->assertSame('low', $links[$far->id]->confidence->value, 'une ligne citée mais loin du lieu garde la confiance la plus basse');
        $this->assertFalse($links[$far->id]->is_primary);
        $this->assertNull($links[$far->id]->distance_m);
        $this->assertContains('hinted_line_far', array_column($a->refresh()->review_notes, 'code'));
        $this->assertCount(2, $links);
    }

    public function test_dry_run_writes_nothing_and_a_second_run_changes_nothing(): void
    {
        $a = $this->accident('2017-12-14-millas', '2017-12-14', '66108', 'commune');
        $this->source($a, 'bea-tt', 'report', 'bea-1', ['date' => '2017-12-14', 'mode' => 'F'], 'Déraillement à Millas (66)');
        $b = $this->accident('1910-01-02-millas', '1910-01-02', '66108', 'commune', ['kind' => 'other']);
        $this->source($b, 'wikipedia', 'article', 'fr:A', ['date' => '1910-01-02', 'type' => 'Collision']);
        $before = $this->snapshot(self::ACCIDENT_TABLES);

        $this->artisan('vff:verify-accidents', ['--dry-run' => true, '--offline' => true])->expectsOutputToContain('Simulation')->assertSuccessful();
        $this->assertSame($before, $this->snapshot(self::ACCIDENT_TABLES), 'simulation : rien n\'est écrit');

        $this->artisan('vff:verify-accidents', ['--offline' => true])->assertSuccessful();
        $first = $this->snapshot(self::ACCIDENT_TABLES);
        $this->assertNotSame($before, $first);
        $verifiedAt = Accident::query()->orderBy('id')->pluck('verified_at')->all();

        $this->artisan('vff:verify-accidents', ['--offline' => true])->assertSuccessful();
        $this->assertSame($first, $this->snapshot(self::ACCIDENT_TABLES), 'rejouée : aucun changement');
        $this->assertEquals($verifiedAt, Accident::query()->orderBy('id')->pluck('verified_at')->all());
    }

    public function test_a_fiche_ok_without_a_rule_is_kept_unless_demote_is_asked(): void
    {
        $a = $this->accident('2012-05-06-millas', '2012-05-06', '66108', 'exact', ['review_status' => 'ok']);
        $this->source($a, 'wikipedia', 'article', 'fr:Seul', ['date' => '2012-05-06', 'lat' => 42.69, 'lng' => 2.7]);

        $this->artisan('vff:verify-accidents', ['--offline' => true])->expectsOutputToContain('sans confirmation')->assertSuccessful();
        $this->assertSame('ok', $a->refresh()->review_status->value);

        $this->artisan('vff:verify-accidents', ['--offline' => true, '--demote' => true])->assertSuccessful();
        $this->assertSame('to_review', $a->refresh()->review_status->value);
    }

    // ---- fabriques -----------------------------------------------------------

    private function commune(string $insee, string $name, string $norm, string $dept, float $w, float $s, float $e, float $n): void
    {
        DB::statement("INSERT INTO communes (code_insee, type, nom, nom_norm, departement, geom, created_at, updated_at)
            VALUES (?, 'commune-actuelle', ?, ?, ?, ST_SetSRID(ST_MakePoint(?, ?), 4326), now(), now())", [$insee, $name, $norm, $dept, ($w + $e) / 2, ($s + $n) / 2]);
        DB::table('commune_contours')->insert(['code_insee' => $insee, 'nom' => $name, 'departement' => $dept,
            'geom' => DB::raw("ST_Multi(ST_MakeEnvelope({$w}, {$s}, {$e}, {$n}, 4326))"), 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @param array<string, mixed> $attributes */
    private function accident(string $slug, string $date, string $insee, string $precision, array $attributes = []): Accident
    {
        $lat = $attributes['lat'] ?? 42.69;
        $lng = $attributes['lng'] ?? 2.70;
        unset($attributes['lat'], $attributes['lng']);

        return Accident::factory()->create($attributes + [
            'slug' => $slug, 'occurred_on' => $date, 'date_precision' => 'day', 'kind' => 'derailment',
            'commune_insee' => $insee, 'commune_name' => $insee === '66108' ? 'Millas' : 'Saint-Féliu-d\'Avall', 'department_code' => '66',
            'geom' => Point::makeGeodetic($lat, $lng), 'location_precision' => $precision, 'location_source' => 'wikidata',
            'review_status' => 'to_review', 'review_notes' => null, 'deaths' => 1, 'provenance' => ['date' => 'wikidata', 'location' => 'wikidata'],
            'name' => ['fr' => 'Accident', 'en' => 'Accident', 'de' => 'Unfall'],
        ]);
    }

    /** @param array<string, mixed> $facts */
    private function source(Accident $a, string $provider, string $role, string $externalId, array $facts, string $title = 'Titre', ?string $url = null): void
    {
        DB::table('accident_sources')->insert([
            'accident_id' => $a->id, 'provider' => $provider, 'external_id' => $externalId, 'role' => $role, 'title' => $title,
            'url' => $url ?? ($provider === 'wikidata' ? 'https://www.wikidata.org/wiki/'.$externalId : 'https://example.org/'.rawurlencode($externalId)),
            'licence' => 'CC0', 'retrieved_at' => '2026-11-19 10:00:00', 'facts' => json_encode($facts),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /**
     * Élément Wikidata et article de Wikipédia qui donnent la même date et un point dans Millas ; l'élément est
     * écrit dans le cache avec ses déclarations.
     *
     * @param  array<string, mixed>  $claims
     */
    private function wikidataAndArticle(Accident $a, string $qid, string $date, array $claims): void
    {
        $this->source($a, 'wikidata', 'data', $qid, ['date' => $date, 'date_precision' => 'day', 'lat' => 42.69, 'lng' => 2.7]);
        $this->source($a, 'wikipedia', 'article', "fr:Article {$qid}", ['date' => $date, 'date_precision' => 'day', 'lat' => 42.69, 'lng' => 2.7]);
        $path = storage_path("app/data/accidents/wikidata/entities/{$qid}.json");
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode(['id' => $qid, 'claims' => $claims]));
    }

    /**
     * Déclarations de date et de lieu avec les références données (propriétés Wikidata des références).
     *
     * @param  list<string>  $refs
     * @return array<string, list<array<string, mixed>>>
     */
    private function claims(array $refs): array
    {
        $statement = fn (): array => ['references' => $refs === [] ? [] : [['snaks' => array_fill_keys($refs, [['snaktype' => 'value']])]]];

        return ['P585' => [$statement()], 'P625' => [$statement()]];
    }
}
