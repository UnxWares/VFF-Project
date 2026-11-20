<?php

namespace Tests\Unit\Accidents;

use App\Enums\AccidentKind;
use App\Services\Accidents\AccidentKindClassifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Nature d'un accident : classe Wikidata, puis texte français (intitulés réels du bilan du BEA-TT,
 * types d'infobox Wikipédia). La première circonstance nommée l'emporte.
 */
class AccidentKindClassifierTest extends TestCase
{
    /** @return iterable<string, array{0: string, 1: ?AccidentKind}> */
    public static function titles(): iterable
    {
        yield 'déraillement' => ['Déraillement d’un train Intercités à Brétigny-sur-Orge (91)', AccidentKind::Derailment];
        yield 'déraillement puis incendie' => ['Déraillement et début d’incendie du TER 849 902, le 30/05/2025 à Laon (02)', AccidentKind::Derailment];
        yield 'dérive puis déraillement' => ['Dérive suivie du déraillement d\'une rame du Panoramique des Dômes à Orcines (63)', AccidentKind::Runaway];
        yield 'dérive après collision' => ['Dérive d’un TER suite à collision avec des bovidés à Formerie (60)', AccidentKind::Runaway];
        yield 'nez à nez sans collision' => ['« Situation de nez à nez », sans collision, de deux trains de SNCF V à Marseille (13)', AccidentKind::Incident];
        yield 'quasi-rattrapage' => ['Le quasi-rattrapage de deux TER, le 18 août 2017 à Ensuès-la-Redonne (13)', AccidentKind::Incident];
        yield 'rattrapage' => ['Collision par rattrapage d’un TGV et d’un TER à Denguin (64)', AccidentKind::Collision];
        yield 'heurtoir' => ['Choc d\'un train contre un heurtoir en gare de Paris-Est', AccidentKind::Collision];
        yield 'talonnage seul' => ['Talonnage d’une aiguille par un TER à Antibes (06)', AccidentKind::Incident];
        yield 'déraillement après talonnage' => ['Déraillement d’un TER suite à un talonnage d’aiguille dans la gare de Laroche-Migennes (89)', AccidentKind::Derailment];
        yield 'piéton' => ['Heurt d’un piéton par un train à Héricy (77)', AccidentKind::PersonStruck];
        yield 'quatre personnes' => ['Heurt de 4 personnes par un TER à Ciboure (64)', AccidentKind::PersonStruck];
        yield 'agents' => ['Heurt de trois agents SNCF par un TER à Schiltigheim (67)', AccidentKind::PersonStruck];
        yield 'engin de travaux' => ['Heurt d’un engin de travaux sur caténaires par un train de voyageurs à Lachapelle-Auzac (46)', AccidentKind::Collision];
        yield 'rupture de rail' => ['Rupture d’un rail sur la ligne de Toulouse à Tarbes à Carbonne (31)', AccidentKind::StructureFailure];
        yield 'effondrement' => ['Effondrement du tunnel de Vierzy', AccidentKind::StructureFailure];
        yield 'incendie' => ['Incendie d’un train Enorail à Saint-Hilaire-Bonneval (87)', AccidentKind::Fire];
        yield 'électrocution' => ['Électrocution sur caténaire à Saint Nazaire', AccidentKind::Other];
        yield 'passage à niveau' => ['Passage à niveau de Neufchâteau', AccidentKind::LevelCrossing];
        yield 'attentat' => ['Attentat du train Strasbourg-Paris', AccidentKind::Attack];
        yield 'type d’infobox' => ['[[Déraillement]]', AccidentKind::Derailment];
        yield 'nom sans circonstance' => ['Accident ferroviaire de Zoufftgen', null];
        yield 'vide' => ['', null];
    }

    #[DataProvider('titles')]
    public function test_first_named_circumstance_wins(string $title, ?AccidentKind $expected): void
    {
        $this->assertSame($expected, (new AccidentKindClassifier)->fromFrenchText($title));
    }

    /** @return iterable<string, array{0: string, 1: ?AccidentKind}> */
    public static function narratives(): iterable
    {
        yield 'percute' => ['un train de voyageurs percute l\'arrière d\'un convoi de marchandises arrêté à un carré', AccidentKind::Collision];
        yield 'tamponne' => ['le train de marchandises tamponne un train omnibus à l\'arrêt en gare', AccidentKind::Collision];
        yield 'se télescopent' => ['deux trains de permissionnaires se télescopent dans le brouillard', AccidentKind::Collision];
        yield 'prend en écharpe' => ['l\'express prend en écharpe un train de marchandises', AccidentKind::Collision];
        yield 'heurte un obstacle' => ['l\'express heurte un arbre déraciné par une tornade, la locomotive se couche', AccidentKind::Collision];
        yield 'se renversent' => ['les rails s\'écartent au passage d\'un train, dont la locomotive et six wagons se renversent', AccidentKind::Derailment];
        yield 'quitte la voie' => ['le train quitte la voie dans une courbe', AccidentKind::Derailment];
        yield 'chaudière qui explose' => ['la chaudière de la locomotive explose, tuant le mécanicien', AccidentKind::Explosion];
        yield 'viaduc qui s\'effondre' => ['le viaduc, miné par des inondations, s\'effondre au passage d\'un train', AccidentKind::StructureFailure];
        yield 'train qui s\'emballe' => ['un train excessivement chargé s\'emballe dans une descente', AccidentKind::Runaway];
        yield 'prend feu' => ['la voiture de tête prend feu', AccidentKind::Fire];
        yield 'rien de net' => ['sur la ligne d\'Andrézieux à Roanne', null];
        yield 'bombardement' => ['un express est bloqué par un bombardement', null];
    }

    #[DataProvider('narratives')]
    public function test_narrative_verbs_name_a_kind_only_when_the_text_says_so(string $text, ?AccidentKind $expected): void
    {
        $this->assertSame($expected, (new AccidentKindClassifier)->fromNarrative($text));
    }

    public function test_narrative_verbs_are_not_applied_to_titles(): void
    {
        $this->assertNull((new AccidentKindClassifier)->fromFrenchText('un train percute un camion'));
    }

    public function test_wikidata_class_maps_to_a_kind_and_generic_class_does_not(): void
    {
        $classifier = new AccidentKindClassifier;

        $this->assertSame(AccidentKind::LevelCrossing, $classifier->fromWikidataClasses(['Q1078765', 'Q19710423']));
        $this->assertSame(AccidentKind::Derailment, $classifier->fromWikidataClasses(['Q1331380']));
        $this->assertNull($classifier->fromWikidataClasses(['Q1078765']));
        $this->assertNull($classifier->fromWikidataClasses([]));
    }
}
