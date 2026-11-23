<?php

namespace Tests\Feature\Commands;

use App\Enums\AccidentKind;
use App\Models\Accident;
use App\Models\AccidentLine;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Services\Accidents\BeaTtReportTable;
use App\Services\Webhooks\WebhookSignature;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
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

    public function test_a_bea_tt_page_read_by_hand_gives_the_commune_the_bilan_title_does_not_and_settles_the_import_doubt(): void
    {
        $url = 'https://www.bea-tt.developpement-durable.gouv.fr/saint-feliu-66-02-03-2010-accident-r1.html';
        $this->app->instance(BeaTtReportTable::class, new BeaTtReportTable([$url => [
            'slug' => '2010-03-02-saint-feliu-d-avall', 'commune_insee' => '66200', 'place' => 'Saint-Féliu-d\'Avall (66)',
            'report' => ['title' => 'Rapport d\'enquête sur la collision du 02/03/2010', 'number' => 'BEATT-2010-01', 'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/rapport-a1.html', 'published' => '2011-01-05'],
        ]]));
        // L'intitulé du bilan ne nomme aucune commune lisible : sans la table, le lieu ne serait pas confirmé.
        $read = $this->accident('2010-03-02-saint-feliu-d-avall', '2010-03-02', '66200', 'commune');
        $this->source($read, 'bea-tt', 'report', 'bea-read', ['date' => '2010-03-02', 'mode' => 'F'], 'Collision en gare', $url);
        $read->update(['review_notes' => [['code' => 'commune_ambiguous', 'detail' => 'Saint Féliu : deux communes'], ['code' => 'location_commune_only', 'detail' => 'x']]]);
        $unread = $this->accident('2010-03-03-saint-feliu-d-avall', '2010-03-03', '66200', 'commune');
        $this->source($unread, 'bea-tt', 'report', 'bea-unread', ['date' => '2010-03-03', 'mode' => 'F'], 'Collision en gare', 'https://www.bea-tt.developpement-durable.gouv.fr/autre-r2.html');

        $this->artisan('vff:verify-accidents')->assertSuccessful();

        $read->refresh();
        $this->assertSame('ok', $read->review_status->value);
        $this->assertSame('primary_source', $read->verification['rule']);
        $this->assertSame(['location_commune_only'], array_column($read->review_notes, 'code'), 'le doute d\'import est retiré, la précision du lieu reste');
        $confirmed = $read->verification['place']['confirmed_by'][0];
        $this->assertSame('66200', $confirmed['commune_insee']);
        $this->assertSame('BEATT-2010-01', $confirmed['report']['number']);
        $this->assertSame('https://www.bea-tt.developpement-durable.gouv.fr/rapport-a1.html', $confirmed['report']['url']);
        $this->assertSame('to_review', $unread->refresh()->review_status->value, 'une rangée du bilan non relue ne confirme pas le lieu');
    }

    public function test_the_day_of_the_bea_tt_page_contradicts_a_fiche_that_follows_the_bilan_and_never_corrects_it_silently(): void
    {
        $url = 'https://www.bea-tt.developpement-durable.gouv.fr/saint-feliu-66-01-12-2015-deraillement-r3.html';
        $this->app->instance(BeaTtReportTable::class, new BeaTtReportTable([$url => [
            'slug' => '2015-12-02-saint-feliu-d-avall', 'commune_insee' => '66200', 'date' => '2015-12-01', 'place' => 'Saint-Féliu-d\'Avall (66)',
            'report' => ['title' => 'Rapport sur le déraillement du 1/12/2015', 'number' => 'BEATT-2015-017', 'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/rapport-a3.html'],
        ]]));
        $a = $this->accident('2015-12-02-saint-feliu-d-avall', '2015-12-02', '66200', 'commune');
        $this->source($a, 'bea-tt', 'report', 'bea-shifted', ['date' => '2015-12-02', 'mode' => 'F'], 'Déraillement', $url);

        $this->artisan('vff:verify-accidents')->assertSuccessful();

        $a->refresh();
        $this->assertSame('to_review', $a->review_status->value);
        $this->assertSame('2015-12-02', $a->occurred_on->toDateString(), 'la date de la fiche n\'est pas changée par la vérification');
        $this->assertContains('verification_conflict', array_column($a->review_notes, 'code'));
        $this->assertSame('2015-12-01', $a->verification['date']['disagreeing'][0]['date']);
        $this->assertNull($a->verification['rule'] ?? null);
    }

    public function test_a_toll_note_still_blocks_a_fiche_the_bea_tt_page_confirms(): void
    {
        $url = 'https://www.bea-tt.developpement-durable.gouv.fr/saint-feliu-66-04-05-2011-collision-r4.html';
        $this->app->instance(BeaTtReportTable::class, new BeaTtReportTable([$url => [
            'slug' => '2011-05-04-saint-feliu-d-avall', 'commune_insee' => '66200', 'place' => 'Saint-Féliu-d\'Avall (66)',
            'report' => ['title' => 'Rapport', 'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/rapport-a4.html'],
        ]]));
        $a = $this->accident('2011-05-04-saint-feliu-d-avall', '2011-05-04', '66200', 'commune');
        $this->source($a, 'bea-tt', 'report', 'bea-toll', ['date' => '2011-05-04', 'mode' => 'F'], 'Collision', $url);
        $a->update(['review_notes' => [['code' => 'toll_mismatch', 'detail' => 'blessés : Wikidata 20, Wikipédia 16'], ['code' => 'date_mismatch', 'detail' => 'x']]]);

        $this->artisan('vff:verify-accidents')->assertSuccessful();

        $a->refresh();
        $this->assertSame('primary_source', $a->verification['rule']);
        $this->assertSame('to_review', $a->review_status->value);
        $this->assertSame(['toll_mismatch', 'date_mismatch'], array_column($a->review_notes, 'code'), 'le jour que Wikidata donne autrement reste écrit');
    }

    public function test_a_list_alone_plus_a_referenced_english_or_german_entry_confirms_with_its_own_rule(): void
    {
        $en = $this->accident('1948-02-17-millas', '1948-02-17', '66108', 'commune');
        $this->source($en, 'wikipedia', 'list', 'fr:1948-02-17-millas', ['date' => '1948-02-17', 'place' => 'Millas', 'department' => '66']);
        $this->source($en, 'wikipedia-en', 'list', 'en:1948-02-17-66108', ['date' => '1948-02-17', 'commune_insee' => '66108', 'place' => 'Millas', 'referenced' => true, 'references' => ['Semmens 1994']]);
        $de = $this->accident('1949-02-17-millas', '1949-02-17', '66108', 'commune');
        $this->source($de, 'wikipedia', 'list', 'fr:1949-02-17-millas', ['date' => '1949-02-17', 'place' => 'Millas', 'department' => '66']);
        $this->source($de, 'wikipedia-de', 'list', 'de:1949-02-17-66108', ['date' => '1949-02-17', 'commune_insee' => '66108', 'place' => 'Millas', 'referenced' => true]);
        // Entrée sans référence propre : elle compte avec Wikipédia, une seule famille.
        $bare = $this->accident('1950-02-17-millas', '1950-02-17', '66108', 'commune');
        $this->source($bare, 'wikipedia', 'list', 'fr:1950-02-17-millas', ['date' => '1950-02-17', 'place' => 'Millas', 'department' => '66']);
        $this->source($bare, 'wikipedia-en', 'list', 'en:1950-02-17-66108', ['date' => '1950-02-17', 'commune_insee' => '66108', 'place' => 'Millas', 'referenced' => false]);
        // Entrée référencée qui donne une autre commune : contradiction, jamais « ok ».
        $far = $this->accident('1951-02-17-millas', '1951-02-17', '66108', 'commune');
        $this->source($far, 'wikipedia', 'list', 'fr:1951-02-17-millas', ['date' => '1951-02-17', 'place' => 'Millas', 'department' => '66']);
        $this->source($far, 'wikipedia-en', 'list', 'en:1951-02-17-13055', ['date' => '1951-02-17', 'commune_insee' => '13055', 'place' => 'Marseille', 'referenced' => true]);

        $this->artisan('vff:verify-accidents')->assertSuccessful();

        $this->assertSame('ok', $en->refresh()->review_status->value);
        $this->assertSame('wikipedia_editions', $en->verification['rule']);
        $this->assertEqualsCanonicalizing(['wikipedia', 'wikipedia-edition'], $en->verification['families']['place']);
        $confirmed = array_column($en->verification['place']['confirmed_by'], 'provider');
        $this->assertEqualsCanonicalizing(['wikipedia', 'wikipedia-en'], $confirmed, 'les deux sources et leur permalien sont gardés');
        $this->assertSame('ok', $de->refresh()->review_status->value);
        $this->assertSame('wikipedia_editions', $de->verification['rule']);
        $this->assertSame('to_review', $bare->refresh()->review_status->value);
        $this->assertNull($bare->verification['rule'] ?? null);
        $this->assertSame(['wikipedia'], $bare->verification['families']['place']);
        $this->assertSame('to_review', $far->refresh()->review_status->value);
        $this->assertContains('verification_conflict', array_column($far->review_notes, 'code'));
    }

    public function test_the_rule_label_stays_the_weakest_when_an_edition_joins_a_wikimedia_pair(): void
    {
        // Wikidata sans référence + Wikipédia + une liste anglaise référencée : la règle reste la paire Wikimedia pour la date.
        $a = $this->accident('2001-05-06-millas', '2001-05-06', '66108', 'exact');
        $this->wikidataAndArticle($a, 'Q111', '2001-05-06', claims: $this->claims(refs: []));
        $this->source($a, 'wikipedia-en', 'list', 'en:2001-05-06-66108', ['date' => '2001-05-06', 'commune_insee' => '66108', 'referenced' => true]);

        $this->artisan('vff:verify-accidents')->assertSuccessful();

        $this->assertSame('ok', $a->refresh()->review_status->value);
        $this->assertSame('wikipedia_editions', $a->verification['rule']);
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

    public function test_the_rule_label_is_the_weakest_of_date_and_place(): void
    {
        // Le BEA-TT confirme la date seule (intitulé sans commune) ; le lieu ne vient que de Wikidata
        // sans référence et de Wikipédia.
        $a = $this->accident('2008-05-06-millas', '2008-05-06', '66108', 'exact');
        $this->wikidataAndArticle($a, 'Q401', '2008-05-06', claims: $this->claims(refs: []));
        $this->source($a, 'bea-tt', 'report', 'bea-401', ['date' => '2008-05-06', 'mode' => 'F'], 'Déraillement d\'un train de fret');

        $this->artisan('vff:verify-accidents', ['--offline' => true])->assertSuccessful();

        $a->refresh();
        $families = $a->verification['families']['date'];
        sort($families);
        $this->assertSame(['official', 'wikidata-bare', 'wikipedia'], $families);
        $this->assertSame('wikidata_wikipedia', $a->verification['rule'], 'le lieu n\'a pas deux sources indépendantes');
    }

    public function test_a_date_known_only_to_the_year_does_not_confirm_a_day(): void
    {
        $a = $this->accident('2009-05-06-millas', '2009-05-06', '66108', 'exact');
        $this->source($a, 'wikidata', 'data', 'Q501', ['date' => '2009-01-01', 'date_precision' => 'year', 'lat' => 42.69, 'lng' => 2.7]);
        $this->source($a, 'wikipedia', 'article', 'fr:Article Q501', ['date' => '2009-05-06', 'date_precision' => 'day', 'lat' => 42.69, 'lng' => 2.7]);
        $path = storage_path('app/data/accidents/wikidata/entities/Q501.json');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode(['id' => 'Q501', 'claims' => $this->claims(refs: [])]));
        // Une année différente contredit encore.
        $b = $this->accident('2010-05-06-millas', '2010-05-06', '66108', 'exact');
        $this->source($b, 'wikipedia', 'list', 'fr:2011-millas', ['date' => '2011-01-01', 'date_precision' => 'year', 'place' => 'Millas', 'department' => '66']);

        $this->artisan('vff:verify-accidents', ['--offline' => true])->assertSuccessful();

        $a->refresh();
        $this->assertSame('to_review', $a->review_status->value);
        $this->assertNull($a->verification['rule'] ?? null);
        $this->assertSame(['wikipedia'], $a->verification['families']['date']);
        $this->assertSame('2011-01-01', $b->refresh()->verification['date']['disagreeing'][0]['date']);
    }

    public function test_a_public_fiche_is_announced_once_after_its_first_verification_never_an_alias_nor_a_hidden_one(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $subscription = $user->webhookSubscriptions()->create(['url' => 'https://hooks.example.org/vff', 'events' => ['accident.published'], 'secret' => WebhookSignature::newSecret()]);
        $public = $this->accident('2017-12-14-millas', '2017-12-14', '66108', 'commune');
        $this->source($public, 'bea-tt', 'report', 'bea-1', ['date' => '2017-12-14', 'mode' => 'F'], 'Déraillement à Millas (66)');
        $hidden = $this->accident('2018-01-02-millas', '2018-01-02', '66108', 'commune', ['kind' => 'person_struck']);
        // Heurt de personne sans enquête officielle ni contexte : non publié (AccidentKind::SOURCED_ONLY), donc jamais annoncé.
        $this->source($hidden, 'wikipedia', 'inventory', 'list-2', ['date' => '2018-01-02'], 'Heurt d\'une personne à Millas (66)');
        $this->accident('2017-12-14-millas-2', '2017-12-14', '66108', 'commune', ['alias_of_id' => $public->id]);
        $sent = fn (): array => WebhookDelivery::query()->where('webhook_subscription_id', $subscription->id)->get()
            ->map(fn (WebhookDelivery $d): string => $d->orderedPayload()['data']['id'])->all();

        $this->assertSame([], $sent(), 'rien n\'est annoncé à la création, avant la vérification');
        $this->artisan('vff:verify-accidents', ['--offline' => true, '--dry-run' => true])->assertSuccessful();
        $this->assertSame([], $sent(), 'une simulation n\'annonce rien');

        $this->artisan('vff:verify-accidents', ['--offline' => true])->assertSuccessful();
        $this->assertSame(['2017-12-14-millas'], $sent());

        $this->artisan('vff:verify-accidents', ['--offline' => true])->assertSuccessful();
        $this->assertSame(['2017-12-14-millas'], $sent(), 'une fiche déjà vérifiée n\'est pas annoncée de nouveau');
    }

    public function test_one_press_article_giving_date_commune_and_toll_counts_as_a_second_family(): void
    {
        $a = $this->listed('1956-06-14-millas', '1956-06-14', 11);
        // Date écrite en clair dans un journal du lendemain : c'est elle qui compte, pas la date du journal.
        $this->press($a, 'kb-1', ['date' => '1956-06-14', 'commune_insee' => '66108', 'deaths' => 11], provider: 'delpher', published: '1956-06-15');

        $this->artisan('vff:verify-accidents', ['--offline' => true])
            ->expectsOutputToContain('confirmées entre autres par la presse numérisée : 1')
            ->assertSuccessful();

        $a->refresh();
        $this->assertSame('ok', $a->review_status->value);
        $this->assertSame('two_sources', $a->verification['rule']);
        $this->assertEqualsCanonicalizing(['wikipedia', 'press'], $a->verification['families']['date']);
        $this->assertEqualsCanonicalizing(['wikipedia', 'press'], $a->verification['families']['place']);
        $press = $this->first($a->verification['place']['confirmed_by'], 'family', 'press');
        $this->assertSame(['delpher', 'press', 'https://example.org/kb-1', '66108', 11], [$press['provider'], $press['role'], $press['url'], $press['commune_insee'], $press['deaths']]);
        $this->assertSame('2026-11-23', substr($press['retrieved_at'], 0, 10), 'la date de consultation de l\'article est gardée');
        $list = $this->first($a->verification['place']['confirmed_by'], 'family', 'wikipedia');
        $this->assertEqualsCanonicalizing(['provider', 'role', 'url', 'date', 'commune_insee', 'commune', 'retrieved_at', 'family'], array_keys($list), 'une source sans bilan ni coordonnées ne garde pas de champ vide');
        $this->assertSame(11, $a->verification['deaths']['value']);
        $this->assertCount(1, $a->verification['deaths']['confirmed_by']);
        $this->assertSame('press', $a->verification['deaths']['confirmed_by'][0]['family']);
        $this->assertSame([], $a->verification['deaths']['disagreeing']);
    }

    public function test_a_contradictory_toll_leaves_the_press_unconfirming_without_blocking(): void
    {
        $higher = $this->listed('1985-08-31-millas', '1985-08-31', 43);
        $this->press($higher, 'kb-49', ['date' => '1985-08-31', 'commune_insee' => '66108', 'deaths' => 49]);
        $lower = $this->listed('1986-08-31-millas', '1986-08-31', 43);
        $this->press($lower, 'kb-40', ['date' => '1986-08-31', 'commune_insee' => '66108', 'deaths' => 40]);
        // Un bilan provisoire plus haut que le bilan retenu contredit aussi.
        $provisionalHigher = $this->listed('1987-08-31-millas', '1987-08-31', 43);
        $this->press($provisionalHigher, 'kb-44', ['date' => '1987-08-31', 'commune_insee' => '66108', 'deaths' => 44, 'deaths_provisional' => true]);
        $noToll = $this->listed('1988-08-31-millas', '1988-08-31', 43);
        $this->press($noToll, 'kb-sans', ['date' => '1988-08-31', 'commune_insee' => '66108']);
        $unknownToll = $this->listed('1989-08-31-millas', '1989-08-31', null);
        $this->press($unknownToll, 'kb-12', ['date' => '1989-08-31', 'commune_insee' => '66108', 'deaths' => 12]);
        $this->press($unknownToll, 'kb-0', ['date' => '1989-08-31', 'commune_insee' => '66108', 'deaths' => 0, 'deaths_provisional' => true]);
        // Commune absente de la table des communes : l'article ne confirme pas le lieu.
        $unknownCommune = $this->listed('1990-08-31-millas', '1990-08-31', 43);
        $this->press($unknownCommune, 'kb-99999', ['date' => '1990-08-31', 'commune_insee' => '99999', 'deaths' => 43]);

        $this->artisan('vff:verify-accidents', ['--offline' => true])->assertSuccessful();

        foreach ([$higher, $lower, $provisionalHigher, $noToll, $unknownToll, $unknownCommune] as $a) {
            $a->refresh();
            $this->assertSame('to_review', $a->review_status->value, $a->slug);
            $this->assertSame(['wikipedia'], $a->verification['families']['date'], $a->slug.' : la presse ne confirme pas la date sans le bilan');
            $this->assertSame(['wikipedia'], $a->verification['families']['place'], $a->slug);
            $this->assertSame([], $a->verification['date']['disagreeing'], $a->slug.' : un autre bilan ne contredit pas la date');
            $this->assertNotContains('verification_conflict', array_column($a->review_notes ?? [], 'code'), $a->slug);
        }
        $this->assertSame(49, $higher->verification['deaths']['disagreeing'][0]['deaths']);
        $this->assertSame('press', $higher->verification['deaths']['disagreeing'][0]['family']);
        $this->assertCount(2, $unknownToll->verification['deaths']['disagreeing'], 'un bilan provisoire ne confirme pas une fiche sans bilan');
        $this->assertSame([], $unknownCommune->verification['place']['disagreeing']);
        $this->assertSame([], $higher->verification['deaths']['confirmed_by']);
        $this->assertTrue($provisionalHigher->verification['deaths']['disagreeing'][0]['deaths_provisional']);
        $this->assertArrayNotHasKey('deaths', $noToll->verification, 'aucun article ne donne de bilan');
        $this->assertNull($unknownToll->verification['deaths']['value']);
    }

    public function test_a_provisional_toll_at_most_the_retained_one_confirms(): void
    {
        $lower = $this->listed('1933-12-23-millas', '1933-12-23', 214);
        $this->press($lower, 'ddb-134', ['date' => '1933-12-23', 'commune_insee' => '66108', 'deaths' => 134, 'deaths_provisional' => true]);
        $equal = $this->listed('1934-12-23-millas', '1934-12-23', 214);
        // Un premier article au bilan contraire n'empêche pas le suivant de confirmer.
        $this->press($equal, 'ddb-230', ['date' => '1934-12-23', 'commune_insee' => '66108', 'deaths' => 230]);
        $this->press($equal, 'ddb-214', ['date' => '1934-12-23', 'commune_insee' => '66108', 'deaths' => 214, 'deaths_provisional' => true]);

        $this->artisan('vff:verify-accidents', ['--offline' => true])->assertSuccessful();

        $this->assertSame('ok', $lower->refresh()->review_status->value);
        $this->assertContains('press', $lower->verification['families']['date']);
        $this->assertSame('ok', $equal->refresh()->review_status->value);
        $this->assertSame(['ddb-214'], array_map(fn (array $c): string => basename($c['url']), $equal->verification['deaths']['confirmed_by']));
    }

    public function test_a_shifted_date_or_another_commune_in_the_press_contradicts_the_fiche(): void
    {
        $shifted = $this->listed('1918-12-06-millas', '1918-12-06', 74);
        $this->press($shifted, 'ddb-7', ['date' => '1918-12-07', 'commune_insee' => '66108', 'deaths' => 74]);
        // « Aujourd'hui » dans un journal du lendemain : un jour plus tard que la fiche.
        $sameDayNextPaper = $this->listed('1919-12-06-millas', '1919-12-06', 74);
        $this->press($sameDayNextPaper, 'kb-same', ['date_relative' => 'same_day', 'commune_insee' => '66108', 'deaths' => 74], published: '1919-12-07');
        $elsewhere = $this->listed('1920-12-06-millas', '1920-12-06', 74);
        $this->press($elsewhere, 'kb-marseille', ['date' => '1920-12-06', 'commune_insee' => '13055', 'deaths' => 74]);

        $this->artisan('vff:verify-accidents', ['--offline' => true])->assertSuccessful();

        $shifted->refresh();
        $this->assertSame('to_review', $shifted->review_status->value);
        $this->assertSame('1918-12-07', $shifted->verification['date']['disagreeing'][0]['date']);
        $conflict = $this->first($shifted->review_notes, 'code', 'verification_conflict');
        $this->assertSame('date 1918-12-07 (presse numérisée) au lieu de 1918-12-06', $conflict['detail']);
        $this->assertSame(['wikipedia'], $shifted->verification['families']['place'], 'un article au mauvais jour ne confirme pas la commune');
        $this->assertSame('to_review', $sameDayNextPaper->refresh()->review_status->value);
        $this->assertSame('1919-12-07', $sameDayNextPaper->verification['date']['disagreeing'][0]['date']);
        $elsewhere->refresh();
        $this->assertSame('to_review', $elsewhere->review_status->value);
        $this->assertSame('13055', $elsewhere->verification['place']['disagreeing'][0]['commune_insee']);
        $this->assertContains('verification_conflict', array_column($elsewhere->review_notes, 'code'));
    }

    public function test_yesterday_in_the_next_day_paper_confirms_the_date(): void
    {
        $a = $this->listed('1957-11-16-millas', '1957-11-16', 29);
        $this->press($a, 'ddb-hier', ['date_relative' => 'previous_day', 'commune_insee' => '66108', 'deaths' => 29], published: '1957-11-17');

        $this->artisan('vff:verify-accidents', ['--offline' => true])->assertSuccessful();

        $a->refresh();
        $this->assertSame('ok', $a->review_status->value);
        $this->assertSame('1957-11-16', $this->first($a->verification['date']['confirmed_by'], 'family', 'press')['date']);
    }

    public function test_several_press_articles_stay_one_family(): void
    {
        // Deux journaux, aucune autre source : une seule famille, jamais « ok ».
        $a = $this->accident('1922-10-29-millas', '1922-10-29', '66108', 'commune', ['deaths' => 14]);
        $this->press($a, 'ddb-1', ['date' => '1922-10-29', 'commune_insee' => '66108', 'deaths' => 14]);
        $this->press($a, 'kb-2', ['date' => '1922-10-29', 'commune_insee' => '66108', 'deaths' => 14], provider: 'delpher');

        $this->artisan('vff:verify-accidents', ['--offline' => true])->assertSuccessful();

        $a->refresh();
        $this->assertSame('to_review', $a->review_status->value);
        $this->assertNull($a->verification['rule'] ?? null);
        $this->assertSame(['press'], $a->verification['families']['date']);
        $this->assertCount(2, $a->verification['date']['confirmed_by'], 'les deux articles restent cités');
    }

    public function test_press_without_written_facts_or_not_read_or_blocked_by_a_note_confirms_nothing(): void
    {
        $bare = $this->listed('1908-11-08-millas', '1908-11-08', 10);
        $this->press($bare, 'ddb-sans-faits', null);
        $unread = $this->listed('1909-11-08-millas', '1909-11-08', 10);
        $this->press($unread, 'retronews-1', ['date' => '1909-11-08', 'commune_insee' => '66108', 'deaths' => 10], access: 'paywall');
        $notPress = $this->listed('1910-11-08-millas', '1910-11-08', 10);
        $this->press($notPress, 'site-1', ['date' => '1910-11-08', 'commune_insee' => '66108', 'deaths' => 10], kind: 'web');
        $blocked = $this->listed('1911-11-08-millas', '1911-11-08', 10);
        $this->press($blocked, 'kb-1', ['date' => '1911-11-08', 'commune_insee' => '66108', 'deaths' => 10]);
        $blocked->update(['review_notes' => [['code' => 'place_corrected', 'detail' => 'Vierzon']]]);
        // Article sans date de l'accident : la date du journal ne lui en donne pas une.
        $undated = $this->listed('1912-11-08-millas', '1912-11-08', 10);
        $this->press($undated, 'kb-sans-date', ['commune_insee' => '66108', 'deaths' => 10], published: '1912-11-09');

        $this->artisan('vff:verify-accidents', ['--offline' => true])->assertSuccessful();

        foreach ([$bare, $unread, $notPress, $undated] as $a) {
            $this->assertSame(['wikipedia'], $a->refresh()->verification['families']['date'], $a->slug);
            $this->assertSame('to_review', $a->review_status->value, $a->slug);
            $this->assertSame([], $a->verification['date']['disagreeing'], $a->slug);
        }
        $this->assertSame('to_review', $blocked->refresh()->review_status->value);
        $this->assertSame('two_sources', $blocked->verification['rule'], 'confirmée, mais la note bloquante reste');
    }

    public function test_press_confirmation_is_stable_on_a_second_run(): void
    {
        $a = $this->listed('1920-12-12-millas', '1920-12-12', 11);
        $this->press($a, 'kb-1', ['date' => '1920-12-12', 'commune_insee' => '66108', 'deaths' => 11]);
        $this->artisan('vff:verify-accidents', ['--offline' => true])->assertSuccessful();
        $verifiedAt = $a->refresh()->verified_at;

        $this->travel(1)->hours();
        $this->artisan('vff:verify-accidents', ['--offline' => true])->assertSuccessful();

        $this->assertEquals($verifiedAt, $a->refresh()->verified_at, 'un second passage ne change rien');
        $this->assertSame('ok', $a->review_status->value);
    }

    // ---- fabriques -----------------------------------------------------------

    /**
     * Première entrée d'une liste de la vérification dont le champ a cette valeur.
     *
     * @param  list<array<string, mixed>>|null  $list
     * @return array<string, mixed>
     */
    private function first(?array $list, string $field, string $value): array
    {
        $found = array_values(array_filter($list ?? [], fn (array $e): bool => ($e[$field] ?? null) === $value));
        $this->assertNotEmpty($found, "aucune entrée {$field} = {$value}");

        return $found[0];
    }

    /** Fiche à Millas que seule la liste de Wikipédia donne (date et commune), avec son bilan. */
    private function listed(string $slug, string $date, ?int $deaths): Accident
    {
        $a = $this->accident($slug, $date, '66108', 'commune', ['deaths' => $deaths]);
        $this->source($a, 'wikipedia', 'list', 'fr:'.$slug, ['date' => $date, 'place' => 'Millas', 'department' => '66']);

        return $a;
    }

    /**
     * Article du contexte (accident_references) avec les faits que le fichier en écrit.
     *
     * @param  array<string, mixed>|null  $facts
     */
    private function press(Accident $a, string $key, ?array $facts, string $provider = 'presse', string $kind = 'press', string $access = 'open', ?string $published = null): void
    {
        DB::table('accident_references')->insert([
            'accident_id' => $a->id, 'key' => $key, 'position' => DB::table('accident_references')->where('accident_id', $a->id)->count(),
            'provider' => $provider, 'kind' => $kind, 'reliability' => 'press', 'title' => 'Article '.$key, 'publisher' => 'Journal',
            'published' => $published, 'url' => 'https://example.org/'.$key, 'access' => $access, 'consulted_on' => '2026-11-23',
            'facts' => $facts !== null ? json_encode($facts) : null, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

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
