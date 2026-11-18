<?php

namespace Tests\Feature;

use App\Http\Controllers\LineController;
use App\Services\LineChronicle;
use App\Services\LineFacts;
use App\Support\EventText;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Présentation des fiches ligne et gare (chantier « UI unifiée », agent donnees-fiches) : règles de
 * calcul pures, sans base. Chaque règle répond à un constat de l'audit du 21/10 (fiche-ligne.json).
 */
class SheetTextRulesTest extends TestCase
{
    // ─── Relevés rédigés (constats 10, 48, 84 : « Infobox : … = », « P1619 », « 1875-00-00 », « way ») ──

    /** @return array<string, array{0: string, 1: string, 2: string, 3: string, 4: string}> */
    public static function generatedQuotes(): array
    {
        return [
            'infobox' => ['wikipedia', 'Infobox : concessionnaire1 = Charentes ; dateconc1 = 1868',
                'Infobox de l’article Wikipédia (concessionnaire : Charentes ; début de la concession : 1868)',
                'Wikipedia article infobox (concession holder: Charentes; start of concession: 1868)',
                'Infobox des Wikipedia-Artikels (Konzessionär: Charentes; Beginn der Konzession: 1868)'],
            'wikidata année + référence' => ['wikidata', 'Wikidata Q3239643 · P1619 (date d\'ouverture officielle) = 1875-00-00 '."\u{2014}".' date retenue 1875-01-01 (précision : année) ; références : importé de Wikipédia en français (Wikidata, CC0)',
                'Wikidata, date d\'ouverture officielle : 1875 (importé de Wikipédia en français)',
                'Wikidata, official opening date: 1875 (imported from the French Wikipedia)',
                'Wikidata, offizielles Eröffnungsdatum: 1875 (importiert aus der französischsprachigen Wikipedia)'],
            'wikidata jour' => ['wikidata', 'Wikidata Q1 · P3999 (date de fermeture) = 1950-12-01 - date retenue 1950-12-01 (précision : jour) (Wikidata, CC0)',
                'Wikidata, date de fermeture : 1er décembre 1950', 'Wikidata, official closing date: 1 December 1950', 'Wikidata, Stilllegungsdatum: 1. Dezember 1950'],
            'objet OpenHistoricalMap' => ['ohm', 'way 199949273 : name=Chemins de Fer de Provence, gauge=1000, usage=branch, railway=narrow_gauge, start_date=1892 (+38 autre(s) way(s) OHM)',
                'Tracé OpenHistoricalMap « Chemins de Fer de Provence » : voie métrique, ligne secondaire, début : 1892 (et 38 autres tracés)',
                'OpenHistoricalMap route “Chemins de Fer de Provence”: metre gauge, branch line, start: 1892 (and 38 other routes)',
                'OpenHistoricalMap-Trasse „Chemins de Fer de Provence“: Meterspur, Nebenstrecke, Beginn: 1892 (und 38 weitere Trassen)'],
            'tableau RFF' => ['lignes-oubliees', "Bon-Encontre \u{2013} Vic-de-Bigorre | Artagnan \u{2013} Vic-Bigorre | ".'268,588 | 270,037 | 1,449 | IG | DECL | 17/10/1994',
                'Tableau RFF des fermetures et déclassements : section Artagnan - Vic-Bigorre, PK 268+588 à 270+037 (1,449 km), 17 octobre 1994',
                'RFF table of closures and decommissionings: section Artagnan - Vic-Bigorre, PK 268+588 to 270+037 (1.449 km), 17 October 1994',
                'RFF-Tabelle der Stilllegungen und Entwidmungen: Abschnitt Artagnan - Vic-Bigorre, PK 268+588 bis 270+037 (1,449 km), 17. Oktober 1994'],
        ];
    }

    #[DataProvider('generatedQuotes')]
    public function test_generated_quotes_are_written_as_sentences(string $source, string $raw, string $fr, string $en, string $de): void
    {
        foreach (['fr' => $fr, 'en' => $en, 'de' => $de] as $locale => $expected) {
            $q = EventText::quoteDetails($source, $raw, $locale);
            $this->assertSame([$expected, 'data'], [$q['text'], $q['kind']], $locale);
            $this->assertDoesNotMatchRegularExpression('/ = |P\d{3,}|-00-00|\bway\b/', $q['text'], $locale);
        }
    }

    public function test_real_citations_stay_as_they_are_without_link_marks(): void
    {
        $this->assertSame(['text' => 'un train touristique circule', 'lang' => 'fr', 'kind' => 'citation'],
            EventText::quoteDetails('heritage', 'un ⟦train touristique⟧ circule', 'en'));
        $this->assertSame(['text' => 'un train touristique circule', 'lang' => null, 'kind' => 'citation'],
            EventText::quoteDetails('heritage', 'un ⟦train touristique⟧ circule', 'fr'));
        // Libellé Wikidata en français dans une page anglaise : signalé.
        $this->assertSame('fr', EventText::quoteDetails('wikidata', 'Wikidata Q1 · P930 (type d\'électrification) = électrification ferroviaire 25 kV 50 Hz - date retenue 1990-01-01 (précision : année) ; qualificatifs : P580 = 1990-01-01 (Wikidata, CC0)', 'en')['lang']);
    }

    // ─── Concessions (constat 11 : « Concession à État (Non concédée) », « à PO ») ─────────────

    public function test_concession_companies_are_named_in_full(): void
    {
        $this->assertSame('Concession : Compagnie du Paris-Orléans (PO) (infobox Wikipédia)', EventText::describe('wikipedia', 'Concession à PO (infobox Wikipédia)', 'fr')['text']);
        $this->assertSame('Exploitation sans concession : Administration des chemins de fer de l’État (infobox Wikipédia)',
            EventText::describe('wikipedia', 'Concession à État (Non concédée) (infobox Wikipédia)', 'fr')['text']);
        $this->assertSame('Operated without a concession: Chemins de fer d’Alsace et de Lorraine (AL) (Wikipedia infobox)',
            EventText::describe('wikipedia', 'Concession à AL [non concédée] (infobox Wikipédia)', 'en')['text']);
        $this->assertSame('Ende der Konzession: Réseau ferré de France (RFF) (Wikipedia-Infobox)',
            EventText::describe('wikipedia', 'Fin de concession de • RFF (infobox Wikipédia)', 'de')['text']);
        $this->assertSame(['Compagnie de l’Est', false], EventText::company('EST'));
    }

    public function test_french_descriptions_get_elisions_and_the_correction_is_split_off(): void
    {
        $this->assertSame('Ouverture d’Ambert à Arlanc (Wikipédia, texte, « Historique »)',
            EventText::describe('wikipedia', 'Ouverture de Ambert à Arlanc (Wikipédia, texte, « Historique »)', 'fr', 'Ambert', 'Arlanc')['text']);
        $this->assertSame('Déclassement (Pont-de-Dore - Arlanc) : 28/07/2008',
            EventText::describe('lignes-oubliees', 'Déclassement (Pont-de-Dore - Arlanc) : 28/07/2008 - PK déduits de la liste des gares de la fiche', 'fr')['text']);
        $this->assertSame(['Fermeture aux voyageurs de Troyes à Thennelières', 'Fermeture aux voyageurs de toute la ligne, répétée par section.'],
            EventText::splitCorrection('Fermeture aux voyageurs de Troyes à Thennelières (correction sourcée : fermeture aux voyageurs de toute la ligne, répétée par section.)'));
        $this->assertSame(['Ouverture de la ligne', null], EventText::splitCorrection('Ouverture de la ligne'));
    }

    // ─── Chronologie : lieux, titres, rangement (constats 27, 55, 56, 81, 82, 83, 85) ─────────

    public function test_place_names_follow_the_line_stations_and_one_letter_typos_join_them(): void
    {
        $stations = ['saillat chassenon' => 'Saillat-Chassenon', 'thennelieres' => 'Thennelières', 'saint germain des fosses' => 'St-Germain-des-Fossés',
            'vire' => 'Vire', 'vira' => 'Vira'];
        $this->assertSame('Saillat-Chassenon', LineChronicle::stationName('Saillat - Chassenon', $stations));
        $this->assertSame('Thennelières', LineChronicle::stationName('Thenellières', $stations), 'coquille de la source');
        $this->assertSame('St-Germain-des-Fossés', LineChronicle::stationName('Saint-Germain-des-Fossés', $stations));
        $this->assertSame('Vise', LineChronicle::stationName('Vise', $stations), 'nom court : pas de rapprochement approché');
        $this->assertSame('Ailleurs-sur-Mer', LineChronicle::stationName('Ailleurs-sur-Mer', $stations));
        $this->assertNull(LineChronicle::stationName(null, $stations));
    }

    public function test_portion_texts_in_three_languages(): void
    {
        $this->assertSame('Du Puley à Genouilly', LineChronicle::placeText('Le Puley', 'Genouilly', 'fr', true));
        $this->assertSame('près d’Évry', LineChronicle::placeText('Évry', null, 'fr'));
        $this->assertSame('From Le Puley to Genouilly', LineChronicle::placeText('Le Puley', 'Genouilly', 'en', true));
        $this->assertSame('bei Évry', LineChronicle::placeText(null, 'Évry', 'de'));
        $this->assertNull(LineChronicle::placeText(null, ' ', 'fr'));
        $this->assertSame('Toute la ligne', LineChronicle::groupTitle(['key' => 'whole', 'from' => null, 'to' => null], 'fr'));
        $this->assertSame('D’Ambert à Darsac', LineChronicle::groupTitle(['key' => 'ambert|darsac', 'from' => 'Ambert', 'to' => 'Darsac'], 'fr'));
    }

    public function test_renaming_places_spares_quotations(): void
    {
        $this->assertSame('Fermeture de Saillat-Chassenon à Angoulême (Wikipédia, texte, « Saillat - Chassenon »)',
            LineChronicle::renamePlaces('Fermeture de Saillat - Chassenon à Angoulême (Wikipédia, texte, « Saillat - Chassenon »)', ['Saillat - Chassenon' => 'Saillat-Chassenon']));
    }

    public function test_facts_of_another_line_are_kept_for_reference_only(): void
    {
        $this->assertTrue(LineChronicle::isOtherLine('other', 'Ouverture d\'une autre ligne de l\'itinéraire Auxerre - Vitry', '012000'));
        $this->assertTrue(LineChronicle::isOtherLine('other', 'Section de la ligne 433000', '434000'));
        $this->assertFalse(LineChronicle::isOtherLine('other', 'Section de la ligne 434000', '434000'));
        $this->assertFalse(LineChronicle::isOtherLine('opening', 'Ouverture d\'une autre ligne', '1'));
    }

    public function test_portions_are_ordered_along_the_line_with_one_length_rule(): void
    {
        $ev = fn (int $id, string $date, ?string $from, ?string $to, array $x = []) => $x + ['id' => $id, 'type' => 'opening', 'date' => $date,
            'precision' => 'day', 'source' => 'wikipedia', 'source_url' => 'https://example.org/'.$id, 'scope' => 'localized',
            'section_from' => $from, 'section_to' => $to, 'section_length_m' => 84200];
        $pk = ['vichy' => 365000, 'courpiere' => 411289, 'giroux' => 423384, 'ambert' => 448000];
        $r = LineChronicle::build([
            $ev(1, '1883-06-09', 'Giroux', 'Courpière'),
            $ev(2, '1862-05-08', 'Vichy', 'Courpière'),
            $ev(3, '1850-01-01', null, null, ['scope' => 'whole', 'type' => 'concession']),
            $ev(4, '1885-05-05', 'Ambert', null),
            $ev(5, '1853-01-01', null, null, ['scope' => 'whole', 'type' => 'other', 'other_line' => true]),
            $ev(6, '1890-01-01', 'Nulle-Part', 'Ailleurs', ['pk_hint' => 400000]),
        ], [2], $pk, 145633, 'fr');

        $this->assertSame(['whole', 'courpiere|vichy', 'ailleurs|nulle part', 'courpiere|giroux', 'ambert'], array_column($r['groups'], 'key'));
        $byKey = array_column($r['groups'], null, 'key');
        $this->assertSame(145633, $byKey['whole']['length_m'], 'toute la ligne = longueur de l’en-tête');
        // Longueur d'une portion nommée = écart des PK de ses gares (la source disait 84,2 km) ; sens des PK.
        $this->assertSame([12095, 'Courpière', 'Giroux', 'De Courpière à Giroux'],
            [$byKey['courpiere|giroux']['length_m'], $byKey['courpiere|giroux']['from'], $byKey['courpiere|giroux']['to'], $byKey['courpiere|giroux']['title']]);
        $this->assertSame('Près d’Ambert', $byKey['ambert']['title']);
        $this->assertSame(84200, $byKey['ailleurs|nulle part']['length_m'], 'sans PK de gare : longueur de la source');
        $this->assertSame('other_line', array_column($r['events'], null, 'id')[5]['status']);
        $this->assertSame(['retained', 'unused', 'context', 'other_line'], $r['statuses']);
    }

    // ─── En-tête, Fiche, exploitants (constats 1, 29, 53, 70, 74, 78, 90, 100, 101, 104) ───────

    public function test_one_line_length_is_the_largest_known_extent(): void
    {
        $phases = ['phases' => [['length_by_category' => ['mixed' => 124744]]],
            'decades' => [['length_by_category' => ['mixed' => 49800, 'closed' => 72800, 'dismantled' => 2200]], ['length_by_category' => []]]];
        $this->assertSame(124800, LineController::lineLengthM($phases));
        $this->assertSame(0, LineController::lineLengthM(['phases' => [], 'decades' => []]));
        $this->assertSame(1875, LineController::firstOpeningYear([['kind' => 'decline', 'precision' => 'day', 'date' => '1870-01-01'],
            ['kind' => 'growth', 'precision' => 'day', 'date' => '1875-04-26']]));
    }

    public function test_other_names_compare_their_places_not_their_spelling(): void
    {
        $title = LineController::nameKey('Ligne de St-Germain-des-Fossés à Darsac');
        $this->assertSame($title, LineController::nameKey('ligne Saint-Germain-des-Fossés - Darsac'));
        $this->assertSame(LineController::nameKey('Ligne Limoges-Angoulême'), LineController::nameKey('Voie ferrée Angoulême-Limoges'));
        $this->assertSame(LineController::nameKey('Ligne de Gonesse à Lille-Frontière (LGV)'), LineController::nameKey('Ligne de Gonesse à Lille-Frontière'));
        $this->assertNotSame(LineController::nameKey('Ligne de Paris-Montparnasse à Brest'), LineController::nameKey('Ligne de Paris à Brest'));
    }

    public function test_infrastructure_managers_and_operators_before_the_opening(): void
    {
        $ops = LineController::operators([
            ['role' => 'exploitant', 'operator' => 'Compagnie des chemins de fer des Charentes', 'wikidata' => 'Q2989936', 'from_year' => 1868, 'to_year' => 1878],
            ['role' => 'exploitant', 'operator' => 'Réseau ferré de France', 'wikidata' => 'Q1071978', 'from_year' => 1997, 'to_year' => 2014],
            ['role' => 'exploitant', 'operator' => 'SNCF Réseau', 'wikidata' => 'Q21605526', 'from_year' => 2015, 'to_year' => null],
            ['role' => 'propriétaire', 'operator' => 'Réseau ferré de France', 'from_year' => null, 'to_year' => null],
            ['role' => 'propriétaire', 'operator' => 'SNCF', 'wikidata' => 'Q13646', 'from_year' => null, 'to_year' => null],
        ], 1875);
        $this->assertSame([1875, 1878, 'exploitant'], [$ops[0]['from_year'], $ops[0]['to_year'], $ops[0]['role']], 'pas exploitant avant la mise en service');
        $this->assertSame(['gestionnaire', false], [$ops[1]['role'], $ops[1]['ongoing']]);
        $this->assertSame(['gestionnaire', 2015, true], [$ops[2]['role'], $ops[2]['from_year'], $ops[2]['ongoing']]);
        $this->assertSame(['propriétaire', 1997, 2014], [$ops[3]['role'], $ops[3]['from_year'], $ops[3]['to_year']], 'RFF n’existe plus depuis 2015');
        $this->assertSame([null, null, false], [$ops[4]['from_year'], $ops[4]['to_year'], $ops[4]['ongoing']]);
    }

    public function test_fact_labels_and_notes_are_written_in_the_page_language(): void
    {
        app()->setLocale('de');
        $f = LineFacts::localized(['key' => 'max_speed', 'kmh' => 160, 'source' => 'sncf', 'scope' => 'line', 'item' => null, 'as_of' => 'today', 'km' => 9.4], 'de');
        $this->assertSame(['Höchstgeschwindigkeit', 'heute, nur auf 9,4 km der Strecke, laut SNCF Réseau'], [$f['label'], $f['note']]);
        $f = LineFacts::localized(['key' => 'length', 'km' => 160.0, 'source' => 'wikidata', 'scope' => 'line', 'item' => null, 'line_km' => 145.6], 'en');
        $this->assertSame('according to Wikidata; the VFF route measures 145.6 km', $f['note']);
        $f = LineFacts::localized(['key' => 'length', 'km' => 409.0, 'source' => 'wikidata', 'scope' => 'item', 'item' => 'LGV Sud-Est'], 'fr');
        $this->assertSame('Longueur officielle (LGV Sud-Est)', $f['label']);
    }
}
