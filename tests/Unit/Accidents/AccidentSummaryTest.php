<?php

namespace Tests\Unit\Accidents;

use App\Enums\AccidentDatePrecision;
use App\Enums\AccidentKind;
use App\Services\Accidents\AccidentSummary;
use Tests\TestCase;

/**
 * Résumés rédigés par VFF (fr, en, de) à partir des faits : date selon sa précision, lieu, trains,
 * bilan accordé (aucun, un, plusieurs), désaccord des sources. Aucun texte de source, aucun nom.
 */
class AccidentSummaryTest extends TestCase
{
    /** @param array<string, mixed> $overrides */
    private function write(array $overrides = []): array
    {
        return (new AccidentSummary)->write($overrides + [
            'date' => '2013-07-12', 'precision' => AccidentDatePrecision::Day, 'kind' => AccidentKind::Derailment,
            'commune' => 'Brétigny-sur-Orge', 'department' => '91',
            'trains' => [['label' => 'Intercités', 'category' => 'passenger', 'number' => null, 'material' => null, 'operator' => null, 'source' => 'bea-tt']],
            'deaths' => 7, 'injuries' => 192, 'toll_disputed' => false,
        ]);
    }

    public function test_writes_the_three_languages_from_the_facts(): void
    {
        $s = $this->write();

        $this->assertSame('Le 12 juillet 2013, un train déraille à Brétigny-sur-Orge (Essonne). Train concerné : Intercités. Bilan : 7 morts et 192 blessés.', $s['fr']);
        $this->assertSame('On 12 July 2013, a train derailed at Brétigny-sur-Orge (Essonne). Train involved: Intercités. Toll: 7 deaths and 192 injured.', $s['en']);
        $this->assertSame('Am 12. Juli 2013 entgleiste in Brétigny-sur-Orge (Essonne) ein Zug. Beteiligter Zug: Intercités. Bilanz: 7 Tote und 192 Verletzte.', $s['de']);
    }

    public function test_date_precision_place_and_toll_forms(): void
    {
        $s = $this->write([
            'date' => '1891-01-01', 'precision' => AccidentDatePrecision::Year, 'kind' => AccidentKind::Collision,
            'commune' => null, 'department' => null, 'trains' => [], 'deaths' => 1, 'injuries' => 0,
        ]);
        $this->assertSame('En 1891, une collision ferroviaire survient en France. Bilan : 1 mort et aucun blessé.', $s['fr']);
        $this->assertSame('In 1891, a railway collision occurred in France. Toll: 1 death and no injuries.', $s['en']);

        $month = $this->write(['date' => '1913-11-01', 'precision' => AccidentDatePrecision::Month, 'deaths' => 43, 'injuries' => null, 'trains' => []]);
        $this->assertStringStartsWith('En novembre 1913, ', $month['fr']);
        $this->assertStringStartsWith('Im November 1913 ', $month['de']);
        $this->assertStringEndsWith('Bilan : 43 morts ; le nombre de blessés n’est pas connu.', $month['fr']);

        $first = $this->write(['date' => '1922-08-01', 'commune' => 'Paris', 'department' => '75']);
        $this->assertStringStartsWith('Le 1er août 1922, un train déraille à Paris. ', $first['fr']);
    }

    public function test_unknown_toll_and_disputed_toll_are_said_plainly(): void
    {
        $unknown = $this->write(['deaths' => null, 'injuries' => null, 'trains' => []]);
        $this->assertStringEndsWith('Le bilan humain n’est pas donné par les sources consultées.', $unknown['fr']);

        $disputed = $this->write(['toll_disputed' => true]);
        $this->assertStringEndsWith('Les sources ne s’accordent pas sur ce bilan.', $disputed['fr']);
        $this->assertStringEndsWith('The sources disagree on this toll.', $disputed['en']);
    }

    public function test_free_french_text_from_wikipedia_stays_out_of_english_and_german(): void
    {
        $s = $this->write(['trains' => [
            ['label' => null, 'category' => null, 'number' => null, 'material' => 'Train couchettes Nice-Paris', 'operator' => null, 'source' => 'wikipedia'],
            ['label' => null, 'category' => 'freight', 'number' => null, 'material' => null, 'operator' => null, 'source' => 'wikipedia'],
        ]]);

        $this->assertStringContainsString('Trains concernés : Train couchettes Nice-Paris, train de fret.', $s['fr']);
        $this->assertStringContainsString('Train involved: freight train.', $s['en']);
        $this->assertStringContainsString('Beteiligter Zug: Güterzug.', $s['de']);
    }

    public function test_train_label_combines_service_number_and_stock(): void
    {
        $this->assertSame('TER 849 902', AccidentSummary::trainLabel(['label' => 'TER', 'category' => 'passenger', 'number' => '849 902', 'material' => null, 'operator' => null], 'fr'));
        $this->assertSame('TGV (TGV Sud-Est)', AccidentSummary::trainLabel(['label' => 'TGV', 'category' => null, 'number' => null, 'material' => 'TGV Sud-Est', 'operator' => null], 'en'));
        $this->assertSame('Bauzug', AccidentSummary::trainLabel(['label' => null, 'category' => 'works', 'number' => null, 'material' => null, 'operator' => null], 'de'));
    }
}
